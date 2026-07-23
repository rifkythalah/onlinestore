<?php

namespace App\Services;

use App\Core\Database;

/**
 * Menangani seluruh logika bisnis dan query terkait Produk.
 */
class ProductService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Ambil semua produk, urut dari yang terbaru. */
    public function getAllProducts(): array
    {
        return $this->db->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
    }

    /** Ambil satu produk berdasarkan ID, null jika tidak ditemukan. */
    public function getProductById(int $id): ?array
    {
        $product = $this->db->query('SELECT * FROM products WHERE id = ?', [$id])->fetch();
        return $product ?: null;
    }

    /**
     * Tambah produk baru ke database.
     *
     * @param array $data Field: name, price, stock, description (opsional), sale_price (opsional)
     */
    public function createProduct(array $data): array
    {
        return $this->db->query(
            'INSERT INTO products (name, description, price, sale_price, stock)
             VALUES (?, ?, ?, ?, ?) RETURNING *',
            [
                $data['name'],
                $data['description'] ?? null,
                $data['price'],
                $data['sale_price'] ?? null,
                $data['stock'] ?? 0,
            ]
        )->fetch();
    }

    /**
     * Update data produk. Mengembalikan null jika ID tidak ditemukan.
     *
     * @param array $data Field: name, price, stock, description (opsional), sale_price (opsional)
     */
    public function updateProduct(int $id, array $data): ?array
    {
        if (!$this->getProductById($id)) {
            return null;
        }

        return $this->db->query(
            'UPDATE products
             SET name = ?, description = ?, price = ?, sale_price = ?, stock = ?
             WHERE id = ? RETURNING *',
            [
                $data['name'],
                $data['description'] ?? null,
                $data['price'],
                $data['sale_price'] ?? null,
                $data['stock'],
                $id,
            ]
        )->fetch();
    }

    /** Hapus produk. Mengembalikan false jika ID tidak ditemukan. */
    public function deleteProduct(int $id): bool
    {
        return $this->db->query('DELETE FROM products WHERE id = ?', [$id])->rowCount() > 0;
    }
}
