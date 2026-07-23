<?php

namespace App\Core;

/**
 * Class Router
 *
 * Simple HTTP Router untuk mencocokkan method + URI
 * ke handler (Controller) yang sesuai.
 *
 * Mendukung URL parameter dinamis, contoh: /api/products/{id}
 */
class Router
{
    /** @var array Daftar route yang terdaftar: [method, pattern, handler] */
    private array $routes = [];

    /**
     * Daftarkan route GET.
     *
     * @param string   $path    URL path (contoh: /api/products)
     * @param callable $handler Fungsi atau [Controller::class, 'method']
     * @return void
     */
    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Daftarkan route POST.
     *
     * @param string   $path    URL path
     * @param callable $handler Handler
     * @return void
     */
    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Daftarkan route PUT.
     *
     * @param string   $path    URL path
     * @param callable $handler Handler
     * @return void
     */
    public function put(string $path, callable $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Daftarkan route DELETE.
     *
     * @param string   $path    URL path
     * @param callable $handler Handler
     * @return void
     */
    public function delete(string $path, callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Tambahkan route ke daftar internal.
     *
     * @param string   $method  HTTP method
     * @param string   $path    URL path
     * @param callable $handler Handler
     * @return void
     */
    private function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $path,
            'handler' => $handler,
        ];
    }

    /**
     * Jalankan routing: cocokkan request yang masuk ke route yang terdaftar.
     * Jika ditemukan, eksekusi handler. Jika tidak, kembalikan 404.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $this->getUri();

        // Handle preflight CORS OPTIONS request
        if ($method === 'OPTIONS') {
            http_response_code(204);
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            exit;
        }

        foreach ($this->routes as $route) {
            // Konversi pattern {param} menjadi regex capture group
            $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $route['pattern']);
            $pattern = "#^{$pattern}$#";

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                // Hapus index 0 (full match), sisanya adalah parameter URL
                array_shift($matches);

                // Panggil handler dengan parameter URL yang ditemukan
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }

        // Tidak ada route yang cocok → 404
        Response::notFound("Endpoint [{$method}] {$uri} tidak ditemukan.");
    }

    /**
     * Ambil URI bersih dari request (tanpa query string dan tanpa base path).
     *
     * @return string URI path yang bersih
     */
    private function getUri(): string
    {
        // Hapus query string dari URI
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Normalisasi: hapus trailing slash kecuali root "/"
        $uri = rtrim($uri, '/') ?: '/';

        return $uri;
    }
}
