-- ================================================================
-- schema.sql — DDL Schema Database Toko Online
-- Database  : PostgreSQL (onlinestore)
-- Dibuat    : 2026-07-23
--
-- Cara menjalankan:
--   psql -U postgres -d onlinestore -f database/schema.sql
-- ================================================================

-- Hapus tabel jika sudah ada (urutan: FK dulu)
DROP TABLE IF EXISTS order_items CASCADE;
DROP TABLE IF EXISTS orders CASCADE;
DROP TABLE IF EXISTS products CASCADE;

-- ================================================================
-- Tabel: products
-- Menyimpan data produk beserta stok inventaris
-- ================================================================
CREATE TABLE products (
    id          BIGSERIAL PRIMARY KEY,
    name        VARCHAR(255)   NOT NULL,
    description TEXT,
    price       NUMERIC(15, 2) NOT NULL CHECK (price >= 0),
    sale_price  NUMERIC(15, 2) DEFAULT NULL CHECK (sale_price >= 0),
    stock       INTEGER        NOT NULL DEFAULT 0,
    created_at  TIMESTAMP      NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP      NOT NULL DEFAULT NOW(),

    -- Constraint kritis: Stok inventaris TIDAK BOLEH NEGATIF
    -- Ini adalah lapisan pengaman terakhir di level database
    CONSTRAINT chk_stock_non_negative CHECK (stock >= 0)
);

COMMENT ON TABLE  products            IS 'Tabel produk toko online';
COMMENT ON COLUMN products.stock      IS 'Jumlah stok inventaris (tidak boleh negatif - dijaga oleh CHECK constraint)';
COMMENT ON COLUMN products.sale_price IS 'Harga flash sale, NULL jika tidak sedang flash sale';

-- ================================================================
-- Tabel: orders
-- Menyimpan data header pesanan
-- ================================================================
CREATE TABLE orders (
    id           BIGSERIAL PRIMARY KEY,
    order_number VARCHAR(50)    NOT NULL UNIQUE,
    status       VARCHAR(20)    NOT NULL DEFAULT 'pending'
                                CHECK (status IN ('pending', 'confirmed', 'cancelled')),
    total_amount NUMERIC(15, 2) NOT NULL DEFAULT 0 CHECK (total_amount >= 0),
    notes        TEXT,
    created_at   TIMESTAMP      NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP      NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE  orders              IS 'Tabel pesanan (header)';
COMMENT ON COLUMN orders.order_number IS 'Nomor unik pesanan (format: ORD-YYYYMMDD-XXXXX)';
COMMENT ON COLUMN orders.status       IS 'Status pesanan: pending, confirmed, cancelled';

-- ================================================================
-- Tabel: order_items
-- Menyimpan detail item dalam setiap pesanan (minimal 1 per order)
-- ================================================================
CREATE TABLE order_items (
    id         BIGSERIAL PRIMARY KEY,
    order_id   BIGINT         NOT NULL REFERENCES orders(id)   ON DELETE CASCADE,
    product_id BIGINT         NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    quantity   INTEGER        NOT NULL CHECK (quantity > 0),
    unit_price NUMERIC(15, 2) NOT NULL CHECK (unit_price >= 0),
    subtotal   NUMERIC(15, 2) NOT NULL CHECK (subtotal >= 0),
    created_at TIMESTAMP      NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE  order_items            IS 'Tabel item dalam pesanan (detail)';
COMMENT ON COLUMN order_items.unit_price IS 'Snapshot harga saat transaksi (tidak berubah walau harga produk berubah)';
COMMENT ON COLUMN order_items.subtotal   IS 'quantity * unit_price';

-- ================================================================
-- Index untuk performa query yang sering digunakan
-- ================================================================
CREATE INDEX idx_order_items_order_id   ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
CREATE INDEX idx_orders_status          ON orders(status);
CREATE INDEX idx_orders_order_number    ON orders(order_number);

-- ================================================================
-- Fungsi & Trigger: auto-update kolom updated_at
-- ================================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_products_updated_at
    BEFORE UPDATE ON products
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER trg_orders_updated_at
    BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Konfirmasi selesai
SELECT 'Schema database berhasil dibuat!' AS status;
