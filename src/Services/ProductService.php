<?php

namespace App\Services;

use App\Core\Database;

/**
 * Class ProductService
 *
 * Menangani logika bisnis dan query database untuk entitas Product.
 */
class ProductService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Ambil semua produk
     */
    public function getAllProducts(): array
    {
        $stmt = $this->db->query("SELECT * FROM products ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    /**
     * Ambil detail satu produk berdasarkan ID
     */
    public function getProductById(int $id): ?array
    {
        $stmt = $this->db->query("SELECT * FROM products WHERE id = ?", [$id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    /**
     * Buat produk baru
     */
    public function createProduct(array $data): array
    {
        $sql = "INSERT INTO products (name, description, price, sale_price, stock) 
                VALUES (?, ?, ?, ?, ?) RETURNING *";
        
        $stmt = $this->db->query($sql, [
            $data['name'],
            $data['description'] ?? null,
            $data['price'],
            $data['sale_price'] ?? null,
            $data['stock'] ?? 0
        ]);

        return $stmt->fetch();
    }

    /**
     * Update produk yang sudah ada (termasuk restock)
     */
    public function updateProduct(int $id, array $data): ?array
    {
        // Pastikan produk ada
        if (!$this->getProductById($id)) {
            return null;
        }

        $sql = "UPDATE products 
                SET name = ?, description = ?, price = ?, sale_price = ?, stock = ? 
                WHERE id = ? RETURNING *";
        
        $stmt = $this->db->query($sql, [
            $data['name'],
            $data['description'] ?? null,
            $data['price'],
            $data['sale_price'] ?? null,
            $data['stock'],
            $id
        ]);

        return $stmt->fetch();
    }

    /**
     * Hapus produk
     */
    public function deleteProduct(int $id): bool
    {
        $stmt = $this->db->query("DELETE FROM products WHERE id = ?", [$id]);
        return $stmt->rowCount() > 0;
    }
}
