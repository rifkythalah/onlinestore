# Online Store Flash Sale API

REST API toko online yang dibangun dengan **Pure PHP 8.3** dan **PostgreSQL**, dirancang khusus untuk menangani lonjakan transaksi *flash sale* secara aman melalui mekanisme **Pessimistic Locking**.

## Fitur

- CRUD lengkap untuk **Produk** (termasuk harga flash sale dan manajemen stok)
- Pembuatan **Pesanan** dengan minimal satu item pesanan
- **Perlindungan Race Condition** — mencegah stok negatif saat ratusan request datang bersamaan
- **Functional Test CLI** untuk membuktikan ketahanan sistem terhadap race condition

## Teknologi

- PHP 8.3 (tanpa framework)
- PostgreSQL 14
- Composer (PSR-4 Autoload + phpdotenv)
- Apache (Laragon) dengan mod_rewrite

## Arsitektur

Proyek ini mengikuti pola mini-MVC manual tanpa framework:

```
Request masuk → public/index.php → Router::dispatch()
    → mencocokkan method + URL, instantiate Controller yang sesuai
        → Controller memvalidasi format input
            → melempar logic bisnis ke Service (query DB, hitung total, cek stok)
                → Response mengembalikan hasil dalam format JSON konsisten
```

- **Router** — mendaftarkan route dan mencocokkan request ke Controller + method yang tepat, termasuk parameter dinamis seperti `{id}`
- **Database** — koneksi PDO ke PostgreSQL dengan pola Singleton, mendukung transaction (`beginTransaction`, `commit`, `rollback`)
- **Response** — helper standarisasi output JSON (`success`, `created`, `error`, `notFound`, `unprocessable`, `serverError`)
- **Controllers** — lapisan validasi format request
- **Services** — lapisan logic bisnis (perhitungan harga, pengecekan stok, pessimistic locking)

## Struktur Proyek

```
├── public/
│   └── index.php          # Front controller — entry point semua request
├── src/
│   ├── Core/
│   │   ├── Database.php   # Singleton PDO dengan dukungan transaction
│   │   ├── Router.php     # HTTP router dengan URL parameter dinamis
│   │   └── Response.php   # Helper untuk standarisasi JSON response
│   ├── Controllers/
│   │   ├── ProductController.php
│   │   └── OrderController.php
│   └── Services/
│       ├── ProductService.php
│       └── OrderService.php   # Pessimistic Locking ada di sini
├── database/
│   ├── schema.sql         # DDL tabel + constraint + trigger
│   └── seeders.sql        # Data produk awal untuk testing
├── tests/
│   └── FlashSaleTest.php  # Functional test race condition via CLI
├── .env.example
└── .htaccess
```

## Instalasi

**Prasyarat:** PHP 8.3 dengan ekstensi `zip` dan `pdo_pgsql` aktif, PostgreSQL 14, dan Composer.

**1. Clone repository**
```bash
git clone https://github.com/rifkythalah/onlinestore.git
cd onlinestore
```

**2. Install dependency**
```bash
composer install
```

**3. Konfigurasi environment**
```bash
cp .env.example .env
# Edit .env, sesuaikan DB_USER, DB_PASS, dan DB_NAME
```

**4. Buat database dan jalankan schema**
```bash
psql -U postgres -c "CREATE DATABASE onlinestore;"
psql -U postgres -d onlinestore -f database/schema.sql
psql -U postgres -d onlinestore -f database/seeders.sql
```

**5. Jalankan server**
```bash
php -S localhost:8080 -t public
```

---

## Endpoint API

Semua response menggunakan format JSON dengan struktur:
```json
{ "status": "success|error", "message": "...", "data": ... }
```

### Produk

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/products` | Daftar semua produk |
| GET | `/api/products/{id}` | Detail satu produk |
| POST | `/api/products` | Tambah produk baru |
| PUT | `/api/products/{id}` | Update produk / restock |
| DELETE | `/api/products/{id}` | Hapus produk |

**Contoh POST /api/products:**
```json
{
    "name": "Laptop Gaming",
    "description": "RTX 4070, 16GB RAM",
    "price": 25000000,
    "sale_price": 19999000,
    "stock": 10
}
```

### Pesanan

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/orders` | Daftar semua pesanan |
| GET | `/api/orders/{id}` | Detail pesanan + item |
| POST | `/api/orders` | Buat pesanan baru *(race condition protected)* |
| PUT | `/api/orders/{id}` | Update status pesanan |

**Contoh POST /api/orders:**
```json
{
    "items": [
        { "product_id": 2, "quantity": 1 }
    ],
    "notes": "Tolong kirim cepat"
}
```

**Contoh PUT /api/orders/{id}:**
```json
{
    "status": "cancelled"
}
```

---

## Testing Manual

Cara tercepat untuk mencoba API ini adalah lewat **Postman**:

1. Set base URL ke `http://localhost:8080` (atau URL ngrok jika diakses dari luar)
2. **GET** `{{base_url}}/api/products` — melihat daftar produk
3. **POST** `{{base_url}}/api/orders` dengan body:
   ```json
   { "items": [ { "product_id": 2, "quantity": 999 } ] }
   ```
   Diharapkan gagal (HTTP 422) karena stok tidak mencukupi
4. **POST** `{{base_url}}/api/orders` dengan body:
   ```json
   { "items": [ { "product_id": 2, "quantity": 1 } ] }
   ```
   Diharapkan berhasil (HTTP 201)

Atau lewat `curl`:
```bash
curl http://localhost:8080/api/products

curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -d '{"items":[{"product_id":2,"quantity":1}]}'
```

---

## Penanganan Race Condition

Saat flash sale, ratusan pembeli bisa menekan tombol "Beli" secara bersamaan. Tanpa pengamanan, dua request bisa membaca nilai stok yang sama, sama-sama lolos validasi, dan akhirnya membuat stok menjadi negatif (*over-selling*).

Solusi yang diterapkan menggunakan **Pessimistic Locking** di level database:

```sql
BEGIN;
  -- Kunci baris produk. Request lain yang menyasar produk yang sama
  -- akan mengantre di sini hingga transaksi ini selesai.
  SELECT * FROM products WHERE id = ? FOR UPDATE;

  -- Validasi stok setelah lock diperoleh
  -- Kurangi stok jika mencukupi
  UPDATE products SET stock = stock - ? WHERE id = ?;

  INSERT INTO orders ...;
  INSERT INTO order_items ...;
COMMIT; -- Lock dilepas di sini
```

Lapisan pengaman tambahan: tabel `products` memiliki constraint `CHECK (stock >= 0)` di level database sebagai jaring pengaman terakhir.

---

## Menjalankan Functional Test (Race Condition)

Pastikan server sudah berjalan, lalu buka terminal baru dan jalankan:

```bash
# Default: 50 request ke produk ID 2
php tests/FlashSaleTest.php

# Kustomisasi URL, produk, dan jumlah request
php tests/FlashSaleTest.php http://localhost:8080/api/orders 2 50
```

**Hasil yang diharapkan** (produk ID 2 memiliki stok 5):
```
  Order Berhasil (HTTP 201)   : 5
  Order Ditolak  (HTTP 422)   : 45
  HASIL : SISTEM AMAN
```

---

## HTTP Status Code

| Kode | Situasi |
|------|---------|
| 200 | Request berhasil |
| 201 | Resource berhasil dibuat |
| 400 | Request tidak valid (field kurang) |
| 404 | Data tidak ditemukan |
| 422 | Validasi bisnis gagal (contoh: stok habis) |
| 500 | Error internal server |
