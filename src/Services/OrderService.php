<?php

namespace App\Services;

use App\Core\Database;
use Exception;

class OrderService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllOrders(): array
    {
        return $this->db->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();
    }

    public function getOrderById(int $id): ?array
    {
        $order = $this->db->query('SELECT * FROM orders WHERE id = ?', [$id])->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = $this->db->query(
            'SELECT oi.*, p.name AS product_name
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?',
            [$id]
        )->fetchAll();

        return $order;
    }

    public function createOrder(array $items, ?string $notes = null): array|string
    {
        if (empty($items)) {
            return 'Pesanan harus memiliki minimal satu item.';
        }

        try {
            $this->db->beginTransaction();

            $totalAmount    = 0;
            $orderItemsData = [];

            // Urutkan berdasarkan product_id untuk mencegah deadlock
            usort($items, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity  = (int) ($item['quantity'] ?? 0);

                if ($quantity <= 0) {
                    $this->db->rollback();
                    return 'Kuantitas item harus lebih dari 0.';
                }

                // Pessimistic Locking: Cegah race condition saat cek dan kurangi stok
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

                $this->db->query(
                    'UPDATE products SET stock = stock - ? WHERE id = ?',
                    [$quantity, $productId]
                );

                $unitPrice = $product['sale_price'] ?? $product['price'];
                $subtotal  = $unitPrice * $quantity;

                $totalAmount      += $subtotal;
                $orderItemsData[]  = compact('productId', 'quantity', 'unitPrice', 'subtotal');
            }

            $orderNumber = 'ORD-' . date('Ymd-His') . '-' . rand(1000, 9999);

            $orderId = $this->db->query(
                "INSERT INTO orders (order_number, total_amount, notes, status)
                 VALUES (?, ?, ?, 'confirmed') RETURNING id",
                [$orderNumber, $totalAmount, $notes]
            )->fetch()['id'];

            foreach ($orderItemsData as $oi) {
                $this->db->query(
                    'INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                     VALUES (?, ?, ?, ?, ?)',
                    [$orderId, $oi['productId'], $oi['quantity'], $oi['unitPrice'], $oi['subtotal']]
                );
            }

            $this->db->commit();

            return $this->getOrderById($orderId);

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
