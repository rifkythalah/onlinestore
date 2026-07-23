# Online Store Flash Sale API ⚡

REST API Toko Online dengan proteksi **Pessimistic Locking** untuk penanganan *Race Condition* selama Flash Sale. Dibangun menggunakan **Pure PHP 8.3** dan **PostgreSQL**.

## 📌 Fitur Utama
1. **CRUD Produk** (Nama, Harga, Stok).
2. **Order & Transaksi** (Satu pesanan bisa memiliki banyak item).
3. **Pessimistic Locking (`SELECT ... FOR UPDATE`)**: Menjamin stok tidak pernah negatif (*over-selling*) meskipun ada ratusan *request* pembelian secara konkuren (bersamaan).
4. **Functional Test (CLI)**: Script pengujian otomatis berbasis cURL-multi untuk mensimulasikan ratusan request Flash Sale secara paralel.

## 🛠️ Persyaratan Sistem
- PHP 8.3+ (ext: PDO, pdo_pgsql, curl, mbstring)
- PostgreSQL 14+ (contoh menggunakan HeidiSQL / Laragon)
- Composer

## 🚀 Cara Instalasi

1. **Clone repository ini**
   ```bash
   git clone https://github.com/rifkythalah/onlinestore.git
   cd onlinestore
   ```

2. **Install dependency (phpdotenv)**
   ```bash
   composer install
   ```

3. **Konfigurasi Environment**
   - Salin `.env.example` menjadi `.env`.
   - Sesuaikan konfigurasi koneksi database PostgreSQL Anda:
     ```ini
     DB_HOST=127.0.0.1
     DB_PORT=5432
     DB_NAME=onlinestore
     DB_USER=postgres
     DB_PASS=
     ```

4. **Siapkan Database & Schema**
   Buka terminal psql atau query tools Anda (HeidiSQL), lalu jalankan:
   ```bash
   # Buat database jika belum ada
   CREATE DATABASE onlinestore;
   
   # Jalankan schema (membuat tabel)
   psql -U postgres -d onlinestore -f database/schema.sql
   
   # Jalankan seeder (mengisi 5 produk awal untuk testing)
   psql -U postgres -d onlinestore -f database/seeders.sql
   ```

5. **Jalankan Server Lokal**
   Buka terminal di root project dan jalankan server PHP bawaan:
   ```bash
   php -S localhost:8080 -t public
   ```

---

## 🏎️ Pengujian Race Condition (Flash Sale)

Kami telah menyiapkan script test khusus (`tests/FlashSaleTest.php`) yang akan mengirim **50 request pembelian secara paralel/bersamaan** untuk 1 Produk Flash Sale yang hanya memiliki stok **5**.

1. Pastikan server PHP Anda sedang berjalan (`php -S localhost:8080 -t public`).
2. Buka terminal/cmd **BARU**, lalu jalankan:
   ```bash
   php tests/FlashSaleTest.php
   ```

**Ekspektasi Hasil:**
- 5 Order berhasil (HTTP 201).
- 45 Order ditolak dengan pesan "stok tidak mencukupi" (HTTP 422).
- **Stok Akhir = 0 (Tidak Negatif)**.

---

## 📚 Dokumentasi API Endpoint

Semua request dan response menggunakan format `application/json`.

### 1. Produk API

- **`GET /api/products`** (Mendapatkan semua produk)
- **`GET /api/products/{id}`** (Mendapatkan detail satu produk)
- **`POST /api/products`** (Menambah produk baru)
  ```json
  {
      "name": "Barang A",
      "price": 100000,
      "stock": 10
  }
  ```
- **`PUT /api/products/{id}`** (Update produk / restock)
- **`DELETE /api/products/{id}`** (Menghapus produk)

### 2. Pesanan API (Order)

- **`GET /api/orders`** (Mendapatkan semua pesanan)
- **`GET /api/orders/{id}`** (Detail pesanan & item)
- **`POST /api/orders`** (Buat pesanan — **Dilengkapi Pessimistic Locking**)
  ```json
  {
      "items": [
          {
              "product_id": 2,
              "quantity": 1
          }
      ],
      "notes": "Tolong kirim cepat!"
  }
  ```

---
*Dibuat oleh Rifki Athallah untuk Assessment Test Toko Online.*
