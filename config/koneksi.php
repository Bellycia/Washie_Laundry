<?php
// [PHP] Koneksi database (sesuaikan dengan MySQL kamu, default XAMPP)
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'washie_laundry';

function kolomAda(PDO $pdo, string $tabel, string $kolom): bool {
    return $pdo->query("SHOW COLUMNS FROM `$tabel` LIKE '$kolom'")->rowCount() > 0;
}

try {
    // Buat database & tabel otomatis kalau belum ada
    $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4");
    $pdo->exec("USE `$dbName`");

    // Tabel akun (role: 'pelanggan' atau 'admin')
    $pdo->exec("CREATE TABLE IF NOT EXISTS pelanggan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        no_hp VARCHAR(15) NULL,
        alamat TEXT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'pelanggan',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabel pesanan laundry
    $pdo->exec("CREATE TABLE IF NOT EXISTS pesanan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        kode VARCHAR(30) NOT NULL DEFAULT '',
        nama VARCHAR(100) NOT NULL,
        no_hp VARCHAR(13) NOT NULL,
        alamat TEXT NULL,
        kategori VARCHAR(50) NOT NULL,
        jumlah VARCHAR(30) NOT NULL,
        layanan VARCHAR(50) NOT NULL,
        penyerahan VARCHAR(30) NOT NULL,
        subtotal INT NOT NULL,
        diskon INT NOT NULL DEFAULT 0,
        antar INT NOT NULL DEFAULT 0,
        total INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Menunggu',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (status)
    )");

    // Penyesuaian otomatis kalau tabel dari versi sebelumnya sudah ada
    if (!kolomAda($pdo, 'pelanggan', 'password')) {
        $pdo->exec("ALTER TABLE pelanggan ADD password VARCHAR(255) NOT NULL DEFAULT ''");
    }
    if (!kolomAda($pdo, 'pelanggan', 'role')) {
        $pdo->exec("ALTER TABLE pelanggan ADD role VARCHAR(20) NOT NULL DEFAULT 'pelanggan'");
    }
    if (!kolomAda($pdo, 'pelanggan', 'username')) {
        $pdo->exec("ALTER TABLE pelanggan ADD username VARCHAR(30) NULL");
        // akun lama diberi username dari bagian depan email
        $pdo->exec("UPDATE pelanggan SET username = CONCAT(LEFT(SUBSTRING_INDEX(email, '@', 1), 20), '_', id) WHERE username IS NULL OR username = ''");
        try { $pdo->exec("ALTER TABLE pelanggan ADD UNIQUE (username)"); } catch (PDOException $e) {}
        try { $pdo->exec("UPDATE pelanggan SET username = 'admin' WHERE email = 'admin@washie.com'"); } catch (PDOException $e) {}
    }
    if (kolomAda($pdo, 'pelanggan', 'nama')) {
        $pdo->exec("ALTER TABLE pelanggan MODIFY nama VARCHAR(100) NULL");
    }
    if (kolomAda($pdo, 'pelanggan', 'no_hp')) {
        $pdo->exec("ALTER TABLE pelanggan MODIFY no_hp VARCHAR(15) NULL");
    } else {
        $pdo->exec("ALTER TABLE pelanggan ADD no_hp VARCHAR(15) NULL");
    }
    if (kolomAda($pdo, 'pelanggan', 'alamat')) {
        $pdo->exec("ALTER TABLE pelanggan MODIFY alamat TEXT NULL");
    } else {
        $pdo->exec("ALTER TABLE pelanggan ADD alamat TEXT NULL");
    }

    // Akun admin bawaan (dibuat sekali, kalau belum ada admin)
    if ((int) $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE role = 'admin'")->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO pelanggan (username, email, password, role) VALUES (?, ?, ?, 'admin')
                       ON DUPLICATE KEY UPDATE role = 'admin', password = VALUES(password)")
            ->execute(['admin', 'admin@washie.com', password_hash('admin123', PASSWORD_DEFAULT)]);
    }
} catch (PDOException $e) {
    die('Koneksi database gagal. Pastikan MySQL menyala dan pengaturan di config/koneksi.php benar.');
}
