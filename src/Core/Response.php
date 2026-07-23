<?php

namespace App\Core;

class Response
{
    public static function success(mixed $data = null, string $message = 'Success'): void
    {
        self::send(['status' => 'success', 'message' => $message, 'data' => $data], 200);
    }

    public static function created(mixed $data = null, string $message = 'Created successfully'): void
    {
        self::send(['status' => 'success', 'message' => $message, 'data' => $data], 201);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): void
    {
        $body = ['status' => 'error', 'message' => $message];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        self::send($body, $status);
    }

    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }

    public static function unprocessable(string $message, mixed $errors = null): void
    {
        self::error($message, 422, $errors);
    }

    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500);
    }

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
