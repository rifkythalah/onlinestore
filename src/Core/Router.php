<?php

namespace App\Core;

/**
 * Router sederhana berbasis method + path.
 * Mendukung parameter dinamis pada URL, contoh: /api/products/{id}
 */
class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $path,
            'handler' => $handler,
        ];
    }

    /**
     * Cocokkan request yang masuk ke route yang terdaftar lalu panggil handler-nya.
     * Kembalikan 404 jika tidak ada yang cocok.
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $this->parseUri();

        // Tanggapi CORS preflight request
        if ($method === 'OPTIONS') {
            http_response_code(204);
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            exit;
        }

        foreach ($this->routes as $route) {
            // Ubah {param} menjadi regex capture group
            $regex = preg_replace('/\{[a-zA-Z_]+\}/', '([^/]+)', $route['pattern']);
            $regex = "#^{$regex}$#";

            if ($route['method'] === $method && preg_match($regex, $uri, $matches)) {
                array_shift($matches); // Buang full match, ambil param saja
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }

        Response::notFound("Endpoint [{$method}] {$uri} tidak ditemukan.");
    }

    /** Ambil path URI bersih tanpa query string dan trailing slash. */
    private function parseUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return rtrim($uri, '/') ?: '/';
    }
}
