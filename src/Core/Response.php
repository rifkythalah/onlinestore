<?php

namespace App\Core;

/**
 * Class Response
 *
 * Helper untuk mengirim response JSON yang terstandarisasi
 * dengan HTTP status code yang sesuai.
 */
class Response
{
    /**
     * Kirim response JSON sukses.
     *
     * @param mixed  $data    Data yang dikembalikan ke client
     * @param string $message Pesan sukses
     * @param int    $status  HTTP status code (default: 200)
     * @return void
     */
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): void
    {
        self::json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Kirim response JSON untuk resource yang baru dibuat (HTTP 201).
     *
     * @param mixed  $data    Data resource yang baru dibuat
     * @param string $message Pesan sukses
     * @return void
     */
    public static function created(mixed $data = null, string $message = 'Resource created successfully'): void
    {
        self::success($data, $message, 201);
    }

    /**
     * Kirim response JSON error.
     *
     * @param string $message Pesan error
     * @param int    $status  HTTP status code (default: 400)
     * @param mixed  $errors  Detail error validasi (opsional)
     * @return void
     */
    public static function error(string $message, int $status = 400, mixed $errors = null): void
    {
        $body = [
            'status'  => 'error',
            'message' => $message,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        self::json($body, $status);
    }

    /**
     * Kirim response 404 Not Found.
     *
     * @param string $message Pesan error
     * @return void
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }

    /**
     * Kirim response 422 Unprocessable Entity (validasi gagal / stok habis).
     *
     * @param string $message Pesan error
     * @param mixed  $errors  Detail error (opsional)
     * @return void
     */
    public static function unprocessable(string $message, mixed $errors = null): void
    {
        self::error($message, 422, $errors);
    }

    /**
     * Kirim response 409 Conflict (race condition terdeteksi / resource conflict).
     *
     * @param string $message Pesan error
     * @return void
     */
    public static function conflict(string $message): void
    {
        self::error($message, 409);
    }

    /**
     * Kirim response 500 Internal Server Error.
     *
     * @param string $message Pesan error
     * @return void
     */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500);
    }

    /**
     * Encode data ke JSON dan kirim sebagai HTTP response.
     *
     * @param mixed $data   Data yang akan di-encode
     * @param int   $status HTTP status code
     * @return void
     */
    private static function json(mixed $data, int $status): void
    {
        // Set header Content-Type JSON dan HTTP status code
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
