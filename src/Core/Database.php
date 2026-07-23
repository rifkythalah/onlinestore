<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Class Database
 *
 * Singleton class untuk mengelola koneksi PDO ke PostgreSQL.
 * Hanya satu instance koneksi yang akan dibuat selama siklus request.
 */
class Database
{
    /** @var Database|null Instance tunggal (Singleton) */
    private static ?Database $instance = null;

    /** @var PDO Objek koneksi PDO aktif */
    private PDO $pdo;

    /**
     * Constructor privat — cegah instantiasi langsung dari luar.
     * Membuat koneksi PDO ke PostgreSQL menggunakan konfigurasi dari .env
     *
     * @throws PDOException Jika koneksi gagal
     */
    private function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $name = $_ENV['DB_NAME'] ?? 'onlinestore';
        $user = $_ENV['DB_USER'] ?? 'postgres';
        $pass = $_ENV['DB_PASS'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$name}";

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Lempar exception saat error SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Default fetch sebagai array asosiatif
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Gunakan prepared statement native
        ]);
    }

    /**
     * Dapatkan instance tunggal Database (Singleton Pattern).
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Dapatkan objek PDO untuk eksekusi query langsung.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Jalankan query dengan prepared statement.
     *
     * @param string $sql   Query SQL dengan placeholder (? atau :nama)
     * @param array  $params Parameter untuk di-bind ke query
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Mulai DB Transaction.
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit (simpan) DB Transaction.
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rollback (batalkan) DB Transaction.
     */
    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Cegah cloning instance (Singleton safeguard).
     */
    private function __clone() {}
}
