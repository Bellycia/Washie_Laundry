<?php
/** Proses login Washie Laundry (POST only). */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    header('Location: ' . appUrl('login.php?gagal=1'));
    exit;
}

$stmt = db()->prepare(
    'SELECT a.*, p.nama
     FROM user a
     LEFT JOIN pelanggan p ON p.id_pelanggan = a.id_pelanggan
     WHERE a.username = ?
     LIMIT 1'
);
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: ' . appUrl('login.php?gagal=1'));
    exit;
}

// Regenerasi ID mencegah session fixation setelah autentikasi berhasil.
session_regenerate_id(true);
$_SESSION['user'] = [
    'id_user'      => (int)$user['id_user'],
    'id_pelanggan' => (int)($user['id_pelanggan'] ?? 0),
    'username'     => $user['username'],
    'role'         => $user['role'],
    'nama'         => $user['nama'] ?? 'Petugas',
];

header('Location: ' . appUrl($user['role'] === 'petugas' ? 'petugas/index.php' : 'beranda.php'));
exit;
