<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$page = basename($_SERVER['PHP_SELF'] ?? 'index.php');
if ($page === 'edit_pesanan.php') {
    $page = 'pesanan.php'; // halaman edit tetap menandai menu Pesanan
}

function navP(string $file, string $current): string {
    return $file === $current ? ' active' : '';
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul ?? 'Petugas') ?> | Washie Laundry</title>
  <link rel="icon" type="image/svg+xml" href="../assets/logo.svg">
  <link rel="stylesheet" href="../css/style.css">
  <script src="../js/script.js" defer></script>
</head>
<body>
<header class="site-header">
  <a class="brand" href="index.php" aria-label="Washie Laundry, beranda petugas">
    <img class="brand-logo" src="../assets/logo.svg" width="39" height="39" alt="">
    <span>Washie<span class="brand-light">Petugas</span></span>
  </a>
  <button class="menu-toggle" id="menuToggle" type="button" aria-label="Buka menu" aria-expanded="false">☰</button>
  <nav class="main-nav" id="mainNav" aria-label="Menu petugas">
    <a class="<?= navP('index.php', $page) ?>" href="index.php">Beranda</a>
    <a class="<?= navP('pesanan.php', $page) ?>" href="pesanan.php">Pesanan</a>
    <a class="<?= navP('layanan.php', $page) ?>" href="layanan.php">Layanan & Tarif</a>
    <a class="<?= navP('jadwal.php', $page) ?>" href="jadwal.php">Jadwal</a>
    <a class="<?= navP('promo.php', $page) ?>" href="promo.php">Promo</a>
    <a class="<?= navP('laporan.php', $page) ?>" href="laporan.php?jenis=semua">Laporan Transaksi</a>
    <a class="<?= navP('profil.php', $page) ?>" href="profil.php">Profil</a>
    <a href="../logout.php">Logout</a>
  </nav>
</header>
<main class="page-shell admin-page">
