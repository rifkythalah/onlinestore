<?php

namespace App\Services;

use App\Core\Database;
use Exception;

/**
 * Menangani logika pembuatan dan pembacaan data Pesanan (Order).
 *
 * Bagian paling kritis di sini adalah createOrder(), yang menggunakan
 * Pessimistic Locking (SELECT ... FOR UPDATE) untuk mencegah race condition
 * saat flash sale — memastikan stok tidak pernah menjadi negatif
 * meskipun ada ratusan request yang datang secara bersamaan.
 */
class OrderService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Ambil semua pesanan, urut dari yang terbaru. */
    public function getAllOrders(): array
    {
        return $this->db->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();
    }

    /**
     * Ambil detail pesanan beserta semua item di dalamnya.
     * Mengembalikan null jika ID tidak ditemukan.
     */
    public function getOrderById(int $id): ?array
    {
        $order = $this->db->query('SELECT * FROM orders WHERE id = ?', [$id])->fetch();

        if (!$order) {
            return null;
        }

        // Sertakan detail item beserta nama produk
        $order['items'] = $this->db->query(
            'SELECT oi.*, p.name AS product_name
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?',
            [$id]
        )->fetchAll();

        return $order;
    }

    /**
     * Buat pesanan baru.
     *
     * Menggunakan database transaction + Pessimistic Locking agar aman
     * dari kondisi race condition. Setiap produk dikunci baris-nya
     * (SELECT FOR UPDATE) sebelum stok diperiksa dan dikurangi.
     *
     * @param  array       $items Array item: [['product_id' => 1, 'quantity' => 2], ...]
     * @param  string|null $notes Catatan tambahan dari pembeli (opsional)
     * @return array|string       Data order jika berhasil, pesan error jika validasi gagal
     * @throws Exception          Jika terjadi error database yang tidak terduga
     */
    public function createOrder(array $items, ?string $notes = null): array|string
    {
        if (empty($items)) {
            return 'Pesanan harus memiliki minimal satu item.';
        }

        try {
            $this->db->beginTransaction();

            $totalAmount    = 0;
            $orderItemsData = [];

            // Urutkan item berdasarkan product_id untuk menghindari deadlock
            // pada skenario pesanan paralel dengan produk yang sama.
            usort($items, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity  = (int) ($item['quantity'] ?? 0);

                if ($quantity <= 0) {
                    $this->db->rollback();
                    return 'Kuantitas item harus lebih dari 0.';
                }

                // Kunci baris produk ini agar request lain mengantre.
                // Tanpa FOR UPDATE, dua request bisa membaca stok yang sama
                // secara bersamaan dan sama-sama lolos validasi → stok negatif.
                $product = $this->db->query(
                    'SELECT * FROM products WHERE id = ? FOR UPDATE',
                    [$productId]
                )->fetch();

                if (!$product) {
                    $this->db->rollback();
                    return "Produk dengan ID {$productId} tidak ditemukan.";
                }

                if ($product['stock'] < $quantity) {
                    $this->db->rollback();
                    return "Stok produk '{$product['name']}' tidak mencukupi. Sisa stok: {$product['stock']}.";
                }

                // Kurangi stok
                $this->db->query(
                    'UPDATE products SET stock = stock - ? WHERE id = ?',
                    [$quantity, $productId]
                );

                // Gunakan harga flash sale jika sedang aktif
                $unitPrice = $product['sale_price'] ?? $product['price'];
                $subtotal  = $unitPrice * $quantity;

                $totalAmount      += $subtotal;
                $orderItemsData[]  = compact('productId', 'quantity', 'unitPrice', 'subtotal');
            }

            // Buat header pesanan dengan nomor unik
            $orderNumber = 'ORD-' . date('Ymd-His') . '-' . rand(1000, 9999);

            $orderId = $this->db->query(
                "INSERT INTO orders (order_number, total_amount, notes, status)
                 VALUES (?, ?, ?, 'confirmed') RETURNING id",
                [$orderNumber, $totalAmount, $notes]
            )->fetch()['id'];

            // Simpan semua item pesanan
            foreach ($orderItemsData as $oi) {
                $this->db->query(
                    'INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                     VALUES (?, ?, ?, ?, ?)',
                    [$orderId, $oi['productId'], $oi['quantity'], $oi['unitPrice'], $oi['subtotal']]
                );
            }

            // Lepas lock dan simpan semua perubahan
            $this->db->commit();

            return $this->getOrderById($orderId);

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
