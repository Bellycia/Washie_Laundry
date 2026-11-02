<?php
// [PHP] Helper login, sesi, dan hak akses (admin vs pelanggan)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const STATUS_PESANAN = ['Menunggu', 'Diproses', 'Selesai', 'Diserahkan'];

function sudahLogin(): bool {
    return isset($_SESSION['user_id']);
}

function adalahAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// Admin -> dashboard admin, pelanggan -> dashboard pelanggan
function halamanAwal(): string {
    return adalahAdmin() ? 'admin.php' : 'index.php';
}

// Halaman login/daftar: kalau sudah login, kirim ke dashboard sesuai role
function wajibTamu(): void {
    if (sudahLogin()) {
        header('Location: ' . halamanAwal());
        exit;
    }
}

// Halaman khusus pelanggan
function wajibPelanggan(): void {
    if (!sudahLogin()) {
        header('Location: register.php');
        exit;
    }
    if (adalahAdmin()) {
        header('Location: admin.php');
        exit;
    }
}

// Halaman khusus admin
function wajibAdmin(): void {
    if (!sudahLogin()) {
        header('Location: login.php');
        exit;
    }
    if (!adalahAdmin()) {
        header('Location: index.php');
        exit;
    }
}

function rupiahFmt(int $angka): string {
    return 'Rp' . number_format($angka, 0, ',', '.');
}

function e(?string $teks): string {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function badgeStatus(string $status): string {
    return '<span class="status-pill status-' . e(strtolower($status)) . '">' . e($status) . '</span>';
}
