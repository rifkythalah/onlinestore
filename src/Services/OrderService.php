<?php

namespace App\Services;

use App\Core\Database;
use Exception;

/**
 * Class OrderService
 *
 * Menangani logika pembuatan pesanan (Order) termasuk perlindungan
 * terhadap kondisi Race Condition menggunakan Pessimistic Locking.
 */
class OrderService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Ambil semua pesanan
     */
    public function getAllOrders(): array
    {
        $stmt = $this->db->query("SELECT * FROM orders ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    /**
     * Ambil detail pesanan beserta item-itemnya
     */
    public function getOrderById(int $id): ?array
    {
        $stmt = $this->db->query("SELECT * FROM orders WHERE id = ?", [$id]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        // Ambil order_items
        $stmtItems = $this->db->query("
            SELECT oi.*, p.name as product_name 
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ", [$id]);
        
        $order['items'] = $stmtItems->fetchAll();
        return $order;
    }

    /**
     * Buat pesanan baru dengan PROTEKSI RACE CONDITION (Pessimistic Locking)
     *
     * @param array $items Array asosiatif, misal: [['product_id' => 1, 'quantity' => 2]]
     * @param string|null $notes Catatan opsional
     * @return array|string Array data order jika sukses, String pesan error jika gagal
     * @throws Exception Jika terjadi kesalahan server/database
     */
    public function createOrder(array $items, ?string $notes = null): array|string
    {
        if (empty($items)) {
            return "Pesanan minimal harus memiliki satu item.";
        }

        try {
            // 1. Mulai Transaksi Database
            $this->db->beginTransaction();

            $totalAmount = 0;
            $orderItemsData = [];

            // Urutkan item berdasarkan product_id untuk menghindari Deadlock jika ada pesanan multi-item paralel
            usort($items, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity  = (int) $item['quantity'];

                if ($quantity <= 0) {
                    $this->db->rollback();
                    return "Kuantitas item tidak valid (harus lebih besar dari 0).";
                }

                // =========================================================================
                // 2. KUNCI BARIS PRODUK (Pessimistic Lock: SELECT ... FOR UPDATE)
                // =========================================================================
                // Query ini akan menahan request lain yang mencoba membaca produk yang sama,
                // hingga transaksi ini selesai (commit/rollback).
                // Mencegah dua request membaca stok lama (over-selling).
                $stmt = $this->db->query("SELECT * FROM products WHERE id = ? FOR UPDATE", [$productId]);
                $product = $stmt->fetch();

                if (!$product) {
                    $this->db->rollback();
                    return "Produk dengan ID {$productId} tidak ditemukan.";
                }

                // 3. Validasi Ketersediaan Stok
                if ($product['stock'] < $quantity) {
                    $this->db->rollback();
                    return "Mohon maaf, stok untuk produk '{$product['name']}' tidak mencukupi. (Sisa: {$product['stock']})";
                }

                // 4. Kurangi Stok secara Atomic di memori transaksi
                $newStock = $product['stock'] - $quantity;
                $this->db->query("UPDATE products SET stock = ? WHERE id = ?", [$newStock, $productId]);

                // Hitung harga (Gunakan sale_price jika ada promo flash sale)
                $unitPrice = $product['sale_price'] !== null ? $product['sale_price'] : $product['price'];
                $subtotal  = $unitPrice * $quantity;
                
                $totalAmount += $subtotal;

                // Simpan data item sementara untuk di-insert setelah order dibuat
                $orderItemsData[] = [
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal'   => $subtotal,
                ];
            }

            // 5. Buat Header Order
            $orderNumber = 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
            $stmtOrder = $this->db->query("
                INSERT INTO orders (order_number, total_amount, notes, status) 
                VALUES (?, ?, ?, 'confirmed') RETURNING id
            ", [$orderNumber, $totalAmount, $notes]);
            
            $orderId = $stmtOrder->fetch()['id'];

            // 6. Buat Detail Order Items
            foreach ($orderItemsData as $oi) {
                $this->db->query("
                    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ", [
                    $orderId, 
                    $oi['product_id'], 
                    $oi['quantity'], 
                    $oi['unit_price'], 
                    $oi['subtotal']
                ]);
            }

            // 7. Selesaikan Transaksi! Kunci baris dilepas di sini.
            $this->db->commit();

            return $this->getOrderById($orderId);

        } catch (Exception $e) {
            // Jika ada error apa pun, batalkan seluruh perubahan
            $this->db->rollback();
            
            // Re-throw untuk ditangani oleh controller
            throw $e;
        }
    }
}
