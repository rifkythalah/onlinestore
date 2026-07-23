<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Services\OrderService;
use Exception;

class OrderController
{
    private OrderService $service;

    public function __construct()
    {
        $this->service = new OrderService();
    }

    public function index(): void
    {
        Response::success($this->service->getAllOrders(), 'Daftar pesanan berhasil diambil.');
    }

    public function show(string $id): void
    {
        $order = $this->service->getOrderById((int) $id);

        if (!$order) {
            Response::notFound("Pesanan dengan ID {$id} tidak ditemukan.");
            return;
        }

        Response::success($order, 'Detail pesanan berhasil diambil.');
    }

    public function store(): void
    {
        $input = $this->parseInput();

        if (empty($input['items']) || !is_array($input['items'])) {
            Response::error('Field "items" wajib diisi dan harus berupa array.', 400);
            return;
        }

        try {
            $result = $this->service->createOrder($input['items'], $input['notes'] ?? null);

            if (is_string($result)) {
                Response::unprocessable($result);
                return;
            }

            Response::created($result, 'Pesanan berhasil dibuat.');
        } catch (Exception $e) {
            Response::serverError('Terjadi kesalahan saat memproses pesanan: ' . $e->getMessage());
        }
    }

    public function update(string $id): void
    {
        $input  = $this->parseInput();
        $status = $input['status'] ?? null;

        $validStatuses = ['pending', 'confirmed', 'cancelled'];

        if (!$status || !in_array($status, $validStatuses)) {
            Response::error('Field "status" wajib diisi. Nilai yang valid: ' . implode(', ', $validStatuses), 400);
            return;
        }

        try {
            $db   = Database::getInstance();
            $stmt = $db->query(
                "UPDATE orders SET status = ? WHERE id = ? RETURNING *",
                [$status, (int) $id]
            );

            $order = $stmt->fetch();

            if (!$order) {
                Response::notFound("Pesanan dengan ID {$id} tidak ditemukan.");
                return;
            }

            Response::success($order, 'Status pesanan berhasil diperbarui.');
        } catch (Exception $e) {
            Response::serverError('Gagal memperbarui status pesanan: ' . $e->getMessage());
        }
    }

    private function parseInput(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
}
