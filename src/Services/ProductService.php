<?php

namespace App\Services;

use App\Core\Database;

class ProductService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllProducts(): array
    {
        return $this->db->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
    }

    public function getProductById(int $id): ?array
    {
        $product = $this->db->query('SELECT * FROM products WHERE id = ?', [$id])->fetch();
        return $product ?: null;
    }

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

    public function deleteProduct(int $id): bool
    {
        return $this->db->query('DELETE FROM products WHERE id = ?', [$id])->rowCount() > 0;
    }
}
