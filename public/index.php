<?php

declare(strict_types=1);

/**
 * public/index.php — Front Controller
 *
 * Semua request dari Apache diarahkan ke sini via .htaccess.
 * Tugasnya: load dependency, daftarkan route, lalu dispatch.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Muat konfigurasi dari file .env
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

use App\Core\Router;
use App\Controllers\ProductController;
use App\Controllers\OrderController;

$router = new Router();

// --- Produk ---
$router->get('/api/products',         [ProductController::class, 'index']);
$router->post('/api/products',        [ProductController::class, 'store']);
$router->get('/api/products/{id}',    [ProductController::class, 'show']);
$router->put('/api/products/{id}',    [ProductController::class, 'update']);
$router->delete('/api/products/{id}', [ProductController::class, 'destroy']);

// --- Pesanan ---
$router->get('/api/orders',        [OrderController::class, 'index']);
$router->post('/api/orders',       [OrderController::class, 'store']);
$router->get('/api/orders/{id}',   [OrderController::class, 'show']);
$router->put('/api/orders/{id}',   [OrderController::class, 'update']);

$router->dispatch();
