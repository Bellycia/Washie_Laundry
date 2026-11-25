<?php
// Sesi sudah dimulai di config.php. Pastikan config.php dimuat sebelum header.
if (!function_exists('e')) require_once __DIR__ . '/../config.php';
$page = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$inCustomer = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/pelanggan/');
$assetBase = $inCustomer ? '../' : '';
$customerBase = $inCustomer ? '' : 'pelanggan/';
function active(string $file, string $page): string { return $file === $page ? ' active' : ''; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Washie Laundry - laundry pakaian, sepatu, dan tas dengan antar-jemput, tarif jelas, dan status pesanan yang bisa dipantau.">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' | Washie Laundry' : 'Washie Laundry' ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= $assetBase ?>assets/logo.svg">
  <link rel="stylesheet" href="<?= $assetBase ?>css/style.css">
  <script src="<?= $assetBase ?>js/script.js" defer></script>
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= $assetBase ?>beranda.php" aria-label="Washie Laundry, beranda">
    <img class="brand-logo" src="<?= $assetBase ?>assets/logo.svg" alt="" width="39" height="39">
    <span>Washie<span class="brand-light">Laundry</span></span>
  </a>
  <button class="menu-toggle" id="menuToggle" type="button" aria-label="Buka menu" aria-expanded="false">☰</button>
  <nav class="main-nav" id="mainNav" aria-label="Menu utama">
    <a class="<?= active('beranda.php', $page) ?>" href="<?= $assetBase ?>beranda.php">Beranda</a>
    <a class="<?= active('informasi.php', $page) ?>" href="<?= $customerBase ?>informasi.php">Tentang Kami</a>
    <a class="<?= active('layanan.php', $page) ?>" href="<?= $assetBase ?>layanan.php">Layanan & Harga</a>
    <a class="<?= active('index.php', $page) ?>" href="<?= $customerBase ?>index.php">Pesan Laundry</a>
    <a class="<?= active('pesanan.php', $page) ?>" href="<?= $customerBase ?>pesanan.php">Cek Pesanan</a>
    <a class="<?= active('pembayaran.php', $page) ?>" href="<?= $customerBase ?>pembayaran.php">Pembayaran</a>
    <a class="<?= active('pesanan-saya.php', $page) ?>" href="<?= $customerBase ?>pesanan-saya.php">Riwayat Pesanan</a>
    <a class="<?= active('profil.php', $page) ?>" href="<?= $customerBase ?>profil.php">Profil</a>
    <a href="<?= $assetBase ?>logout.php">Logout</a>
  </nav>
</header>
<main class="page-shell">
