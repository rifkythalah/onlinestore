<?php

/**
 * public/index.php — Front Controller
 *
 * Semua HTTP request dari Apache diarahkan ke file ini via .htaccess.
 * File ini bertanggung jawab untuk:
 * 1. Memuat autoloader Composer dan konfigurasi .env
 * 2. Mendaftarkan seluruh route API
 * 3. Mendispatch request ke controller yang sesuai
 */

declare(strict_types=1);

// ── 1. Bootstrap: Autoloader & Environment ──────────────────────────────────
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Muat konfigurasi dari file .env
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// ── 2. Impor class yang dibutuhkan ───────────────────────────────────────────
use App\Core\Router;
use App\Controllers\ProductController;
use App\Controllers\OrderController;

// ── 3. Inisialisasi Router ───────────────────────────────────────────────────
$router = new Router();

// ============================================================
// ROUTE: Products
// ============================================================

/** GET /api/products — Ambil daftar semua produk */
$router->get('/api/products', [ProductController::class, 'index']);

/** POST /api/products — Tambah produk baru */
$router->post('/api/products', [ProductController::class, 'store']);

/** GET /api/products/{id} — Detail satu produk */
$router->get('/api/products/{id}', [ProductController::class, 'show']);

/** PUT /api/products/{id} — Update produk (termasuk restock) */
$router->put('/api/products/{id}', [ProductController::class, 'update']);

/** DELETE /api/products/{id} — Hapus produk */
$router->delete('/api/products/{id}', [ProductController::class, 'destroy']);

// ============================================================
// ROUTE: Orders
// ============================================================

/** GET /api/orders — Ambil daftar semua pesanan */
$router->get('/api/orders', [OrderController::class, 'index']);

/** POST /api/orders — Buat pesanan baru (dengan proteksi race condition) */
$router->post('/api/orders', [OrderController::class, 'store']);

/** GET /api/orders/{id} — Detail pesanan beserta item-itemnya */
$router->get('/api/orders/{id}', [OrderController::class, 'show']);

/** PUT /api/orders/{id} — Update status pesanan */
$router->put('/api/orders/{id}', [OrderController::class, 'update']);

// ── 4. Dispatch Request ──────────────────────────────────────────────────────
$router->dispatch();
