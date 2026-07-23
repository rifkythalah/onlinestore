<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Mengelola koneksi PDO ke PostgreSQL menggunakan Singleton Pattern.
 * Hanya satu instance yang dibuat per siklus request.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    /**
     * Buat koneksi PDO. Private agar tidak bisa diinstansiasi langsung.
     *
     * @throws PDOException jika koneksi database gagal
     */
    private function __construct()
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $_ENV['DB_HOST'] ?? '127.0.0.1',
            $_ENV['DB_PORT'] ?? '5432',
            $_ENV['DB_NAME'] ?? 'onlinestore'
        );

        $this->pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'postgres', $_ENV['DB_PASS'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /** Ambil instance tunggal, buat baru jika belum ada. */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Jalankan query dengan prepared statement.
     *
     * @param string $sql    Query SQL dengan placeholder (?)
     * @param array  $params Nilai yang akan di-bind
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /** Mulai database transaction. */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /** Simpan semua perubahan dalam transaction. */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /** Batalkan semua perubahan dalam transaction. */
    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function __clone() {}
}
