<?php

namespace App\Controllers;

use App\Services\OrderService;
use App\Core\Response;
use Exception;

/**
 * Class OrderController
 *
 * Menangani HTTP Request untuk endpoint /api/orders
 */
class OrderController
{
    private OrderService $service;

    public function __construct()
    {
        $this->service = new OrderService();
    }

    /**
     * GET /api/orders
     */
    public function index(): void
    {
        $orders = $this->service->getAllOrders();
        Response::success($orders, 'Daftar pesanan berhasil diambil.');
    }

    /**
     * GET /api/orders/{id}
     */
    public function show(string $id): void
    {
        $order = $this->service->getOrderById((int) $id);
        
        if (!$order) {
            Response::notFound("Pesanan dengan ID {$id} tidak ditemukan.");
            return;
        }

        Response::success($order, 'Detail pesanan berhasil diambil.');
    }

    /**
     * POST /api/orders
     * Endpoint krusial untuk simulasi Flash Sale
     */
    public function store(): void
    {
        $input = $this->getJsonInput();

        if (empty($input['items']) || !is_array($input['items'])) {
            Response::error('Payload harus memiliki array "items".', 400);
            return;
        }

        try {
            $result = $this->service->createOrder($input['items'], $input['notes'] ?? null);
            
            // Jika service mengembalikan string, berarti validasi stok gagal / error bisnis
            if (is_string($result)) {
                Response::unprocessable($result);
                return;
            }

            // Jika mengembalikan array, order sukses dibuat
            Response::created($result, 'Pesanan berhasil dibuat!');

        } catch (Exception $e) {
            // Tangkap jika terjadi deadlock atau error database lainnya
            Response::serverError('Terjadi kesalahan sistem saat memproses pesanan: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/orders/{id}
     * Update status pesanan (opsional)
     */
    public function update(string $id): void
    {
        // Untuk penyederhanaan, implementasi bisa dilanjutkan sesuai kebutuhan
        Response::success(null, "Endpoint update status order {$id} (TBD)");
    }

    /**
     * Helper untuk membaca JSON payload
     */
    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        return is_array($data) ? $data : [];
    }
}
