<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

        if ($method === 'OPTIONS') {
            http_response_code(204);
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            exit;
        }

        foreach ($this->routes as $route) {
            $regex = "#^" . preg_replace('/\{[a-zA-Z_]+\}/', '([^/]+)', $route['pattern']) . "$#";

            if ($route['method'] === $method && preg_match($regex, $uri, $matches)) {
                array_shift($matches);

                [$class, $methodName] = $route['handler'];
                $controller = new $class();

                call_user_func_array([$controller, $methodName], $matches);
                return;
            }
        }

        Response::notFound("Endpoint [{$method}] {$uri} tidak ditemukan.");
    }
}