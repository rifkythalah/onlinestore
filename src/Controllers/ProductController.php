<?php

namespace App\Controllers;

use App\Services\ProductService;
use App\Core\Response;

/**
 * Class ProductController
 *
 * Menangani HTTP Request untuk endpoint /api/products
 */
class ProductController
{
    private ProductService $service;

    public function __construct()
    {
        $this->service = new ProductService();
    }

    /**
     * GET /api/products
     */
    public function index(): void
    {
        $products = $this->service->getAllProducts();
        Response::success($products, 'Daftar produk berhasil diambil.');
    }

    /**
     * GET /api/products/{id}
     */
    public function show(string $id): void
    {
        $product = $this->service->getProductById((int) $id);
        
        if (!$product) {
            Response::notFound("Produk dengan ID {$id} tidak ditemukan.");
            return;
        }

        Response::success($product, 'Detail produk berhasil diambil.');
    }

    /**
     * POST /api/products
     */
    public function store(): void
    {
        $input = $this->getJsonInput();

        // Validasi dasar
        if (empty($input['name']) || !isset($input['price'])) {
            Response::error('Nama dan Harga produk wajib diisi.', 400);
            return;
        }

        if ($input['price'] < 0 || (isset($input['stock']) && $input['stock'] < 0)) {
            Response::error('Harga dan stok tidak boleh negatif.', 422);
            return;
        }

        try {
            $product = $this->service->createProduct($input);
            Response::created($product, 'Produk berhasil ditambahkan.');
        } catch (\Exception $e) {
            Response::serverError('Gagal menambahkan produk: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/products/{id}
     */
    public function update(string $id): void
    {
        $input = $this->getJsonInput();

        if (empty($input['name']) || !isset($input['price']) || !isset($input['stock'])) {
            Response::error('Nama, Harga, dan Stok wajib diisi.', 400);
            return;
        }

        if ($input['price'] < 0 || $input['stock'] < 0) {
            Response::error('Harga dan stok tidak boleh negatif.', 422);
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

    /**
     * DELETE /api/products/{id}
     */
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

    /**
     * Helper untuk membaca JSON payload dari request body
     */
    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        return is_array($data) ? $data : [];
    }
}
