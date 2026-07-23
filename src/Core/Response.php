<?php

namespace App\Core;

/**
 * Menangani semua HTTP response dalam format JSON.
 *
 * Setiap response memiliki struktur yang konsisten:
 * { "status": "success|error", "message": "...", "data": ... }
 */
class Response
{
    /**
     * Kirim response sukses (HTTP 200).
     *
     * @param mixed  $data    Data yang dikembalikan ke client
     * @param string $message Pesan deskriptif
     */
    public static function success(mixed $data = null, string $message = 'Success'): void
    {
        self::send(['status' => 'success', 'message' => $message, 'data' => $data], 200);
    }

    /**
     * Kirim response resource baru berhasil dibuat (HTTP 201).
     *
     * @param mixed  $data    Data resource yang baru dibuat
     * @param string $message Pesan deskriptif
     */
    public static function created(mixed $data = null, string $message = 'Created successfully'): void
    {
        self::send(['status' => 'success', 'message' => $message, 'data' => $data], 201);
    }

    /**
     * Kirim response error generik.
     *
     * @param string $message Pesan error yang informatif
     * @param int    $status  HTTP status code
     * @param mixed  $errors  Detail validasi tambahan (opsional)
     */
    public static function error(string $message, int $status = 400, mixed $errors = null): void
    {
        $body = ['status' => 'error', 'message' => $message];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        self::send($body, $status);
    }

    /** Kirim response 404 Not Found. */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }

    /**
     * Kirim response 422 Unprocessable Entity.
     * Digunakan saat validasi bisnis gagal, misalnya stok tidak cukup.
     */
    public static function unprocessable(string $message, mixed $errors = null): void
    {
        self::error($message, 422, $errors);
    }

    /** Kirim response 500 Internal Server Error. */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500);
    }

    /** Encode ke JSON dan kirim ke client. */
    private static function send(mixed $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
