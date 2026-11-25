<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tambah.php');
    exit;
}

$idPelanggan = (int)($_POST['id_pelanggan'] ?? 0);
$idLayanan   = (int)($_POST['id_layanan'] ?? 0);
$idKategori  = (int)($_POST['id_kategori'] ?? 0);
$jumlah      = (int)($_POST['jumlah'] ?? 0);
$metode      = $_POST['metode_penyerahan'] ?? 'mandiri';
$status      = $_POST['status_pesanan'] ?? 'menunggu_konfirmasi';

if (
    $idPelanggan < 1 || $idLayanan < 1 || $idKategori < 1 || $jumlah < 1
    || !in_array($metode, ['mandiri', 'antar_jemput'], true)
    || !in_array($status, ['menunggu_konfirmasi', 'diproses', 'selesai'], true)
) {
    die('Data tidak valid.');
}

$pdo = db();

$st = $pdo->prepare('SELECT tarif_dasar, satuan FROM kategori_barang WHERE id_kategori = ?');
$st->execute([$idKategori]);
$kat = $st->fetch();
if (!$kat) {
    die('Kategori tidak ditemukan.');
}

$st = $pdo->prepare('SELECT pengali_harga FROM layanan WHERE id_layanan = ?');
$st->execute([$idLayanan]);
$lay = $st->fetch();
if (!$lay) {
    die('Layanan tidak ditemukan.');
}

$h        = hitungBiaya((float)$jumlah, (float)$kat['tarif_dasar'], (float)$lay['pengali_harga'], $metode, promoBerlaku(date('Y-m-d')));
$admin    = $h['admin'];
$subtotal = $h['subtotal'];
$diskon   = $h['diskon'];
$namaPromo = $h['nama_promo'];
$kode     = 'WSH-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO pesanan(kode_pesanan, id_pelanggan, id_layanan, metode_penyerahan, biaya_admin, diskon, nama_promo, biaya_laundry, status_pesanan, status_pembayaran)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, "belum_ada_tagihan")'
    )->execute([$kode, $idPelanggan, $idLayanan, $metode, $admin, $diskon, $namaPromo, $subtotal, $status]);
    $idPesanan = (int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO detail_pesanan(id_pesanan, id_kategori, jumlah, satuan, tarif_satuan, subtotal_estimasi)
         VALUES(?, ?, ?, ?, ?, ?)'
    )->execute([$idPesanan, $idKategori, $jumlah, $kat['satuan'], $kat['tarif_dasar'], $subtotal]);

    $pdo->commit();
    header('Location: petugas/pesanan.php');
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
