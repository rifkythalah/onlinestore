-- ================================================================
-- seeders.sql — Data Awal untuk Testing & Development
-- Database  : PostgreSQL (onlinestore)
--
-- Cara menjalankan (setelah schema.sql):
--   psql -U postgres -d onlinestore -f database/seeders.sql
-- ================================================================

-- Bersihkan data lama sebelum seeding ulang (urutan FK)
TRUNCATE TABLE order_items, orders, products RESTART IDENTITY CASCADE;

-- ================================================================
-- Seed: Tabel products
-- Menyertakan produk flash sale dengan stok terbatas
-- ================================================================
INSERT INTO products (name, description, price, sale_price, stock) VALUES
(
    'Laptop Gaming ASUS ROG',
    'Laptop gaming high-end dengan RTX 4070, RAM 16GB, SSD 1TB',
    25000000.00,
    19999000.00,  -- Flash sale price
    10            -- Stok sangat terbatas untuk test race condition
),
(
    'iPhone 15 Pro Max',
    'Smartphone premium Apple dengan chip A17 Pro, 256GB storage',
    22000000.00,
    18500000.00,  -- Flash sale price
    5             -- Stok sangat terbatas
),
(
    'Samsung 4K Smart TV 55"',
    'TV 4K QLED dengan fitur Smart TV, HDR10+',
    8500000.00,
    6999000.00,   -- Flash sale price
    20
),
(
    'Sony WH-1000XM5 Headphone',
    'Headphone wireless noise-cancelling premium dari Sony',
    5000000.00,
    3750000.00,   -- Flash sale price
    50
),
(
    'Mechanical Keyboard Keychron K2',
    'Keyboard mekanikal compact 75%, hot-swappable switches',
    1500000.00,
    NULL,         -- Tidak dalam flash sale
    100
);

-- Konfirmasi selesai
SELECT
    id,
    name,
    price,
    sale_price,
    stock,
    CASE WHEN sale_price IS NOT NULL THEN 'Flash Sale!' ELSE 'Regular' END AS promo_status
FROM products
ORDER BY id;
