<?php

namespace App\Controllers;

use App\Core\Response;
use App\Services\ProductService;

class ProductController
{
    private ProductService $service;

    public function __construct()
    {
        $this->service = new ProductService();
    }

    public function index(): void
    {
        Response::success($this->service->getAllProducts(), 'Daftar produk berhasil diambil.');
    }

    public function show(string $id): void
    {
        $product = $this->service->getProductById((int) $id);

        if (!$product) {
            Response::notFound("Produk dengan ID {$id} tidak ditemukan.");
            return;
        }

        Response::success($product, 'Detail produk berhasil diambil.');
    }

    public function store(): void
    {
        $input = $this->parseInput();

        if (empty($input['name']) || !isset($input['price'])) {
            Response::error('Field name dan price wajib diisi.', 400);
            return;
        }

        if ($input['price'] < 0 || ($input['stock'] ?? 0) < 0) {
            Response::unprocessable('Harga dan stok tidak boleh bernilai negatif.');
            return;
        }

        try {
            Response::created($this->service->createProduct($input), 'Produk berhasil ditambahkan.');
        } catch (\Exception $e) {
            Response::serverError('Gagal menyimpan produk: ' . $e->getMessage());
        }
    }

    public function update(string $id): void
    {
        $input = $this->parseInput();

        if (empty($input['name']) || !isset($input['price'], $input['stock'])) {
            Response::error('Field name, price, dan stock wajib diisi.', 400);
            return;
        }

        if ($input['price'] < 0 || $input['stock'] < 0) {
            Response::unprocessable('Harga dan stok tidak boleh bernilai negatif.');
            return;
        }

        try {
            $product = $this->service->updateProduct((int) $id, $input);

            if (!$product) {
                Response::notFound("Produk dengan ID {$id} tidak ditemukan.");
                return;
            }

            Response::success($product, 'Produk berhasil diperbarui.');
        } catch (\Exception $e) {
            Response::serverError('Gagal memperbarui produk: ' . $e->getMessage());
        }
    }

    public function destroy(string $id): void
    {
        try {
            $deleted = $this->service->deleteProduct((int) $id);

            if (!$deleted) {
                Response::notFound("Produk dengan ID {$id} tidak ditemukan.");
                return;
            }

            Response::success(null, 'Produk berhasil dihapus.');
        } catch (\Exception $e) {
            Response::serverError('Gagal menghapus produk: ' . $e->getMessage());
        }
    }

    private function parseInput(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
}
