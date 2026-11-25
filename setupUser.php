<?php
/**
 * Setup tabel user Washie Laundry.
 * Jalankan setelah database dan tabel pelanggan dibuat melalui database.sql.
 * Jika instalasi lama masih memakai tabel akun, tabel akan diganti nama menjadi user.
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

try {
    $pdo = db();

    // Migrasi otomatis instalasi lama: akun -> user.
    $cekUser = $pdo->query("SHOW TABLES LIKE 'user'")->fetchColumn();
    $cekAkun = $pdo->query("SHOW TABLES LIKE 'akun'")->fetchColumn();
    if (!$cekUser && $cekAkun) {
        $pdo->exec('RENAME TABLE akun TO user');
        $kolomLama = $pdo->query("SHOW COLUMNS FROM user LIKE 'id_akun'")->fetch();
        if ($kolomLama) {
            $pdo->exec('ALTER TABLE user CHANGE id_akun id_user INT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
        // Nama constraint ikut diperbarui agar konsisten.
        try {
            $pdo->exec('ALTER TABLE user DROP FOREIGN KEY fk_akun_pelanggan');
            $pdo->exec('ALTER TABLE user ADD CONSTRAINT fk_user_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON UPDATE CASCADE ON DELETE CASCADE');
        } catch (Throwable $ignored) {
            // Constraint mungkin sudah bernama berbeda atau belum ada.
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS user (
        id_user INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_pelanggan INT UNSIGNED NULL UNIQUE,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('pelanggan','petugas') NOT NULL DEFAULT 'pelanggan',
        dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_user_pelanggan FOREIGN KEY (id_pelanggan)
          REFERENCES pelanggan(id_pelanggan) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Akun petugas demo hanya dibuat jika username belum tersedia.
    $cek = $pdo->prepare('SELECT id_user FROM user WHERE username = ? LIMIT 1');
    $cek->execute(['petugas']);
    if (!$cek->fetch()) {
        $buat = $pdo->prepare('INSERT INTO user (id_pelanggan, username, password_hash, role) VALUES (NULL, ?, ?, ?)');
        $buat->execute(['petugas', password_hash('password', PASSWORD_DEFAULT), 'petugas']);
        $status = 'Tabel user berhasil disiapkan dan user demo petugas dibuat.';
    } else {
        $status = 'Tabel user sudah tersedia. User petugas yang ada tidak diubah.';
    }
} catch (Throwable $e) {
    http_response_code(500);
    $status = 'Setup gagal. Pastikan database washie_laundry dan tabel pelanggan sudah dibuat melalui database.sql. Detail: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Setup User Washie</title><link rel="stylesheet" href="css/style.css"></head>
<body><main class="auth-wrap"><section class="auth-card"><h1>Setup user pengguna</h1><p><?= e($status) ?></p><p><a class="button button-primary" href="login.php">Ke halaman login</a></p><p><small>Untuk keamanan, hapus atau batasi akses setupUser.php setelah setup selesai.</small></p></section></main></body></html>
