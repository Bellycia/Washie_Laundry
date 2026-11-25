<?php
require_once __DIR__ . '/../config.php';
roleWajib('pelanggan'); $pageTitle = 'Pesanan'; $error = ''; $order = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['kode'])) {
  $kode = strtoupper(trim((string)$_GET['kode']));
  try {
    $stmt = db()->prepare('SELECT p.*, l.nama AS nama_layanan, l.pengali_harga, k.nama AS kategori, k.tarif_dasar, d.jumlah, d.satuan
      FROM pesanan p JOIN layanan l ON p.id_layanan=l.id_layanan
      JOIN detail_pesanan d ON p.id_pesanan=d.id_pesanan
      JOIN kategori_barang k ON d.id_kategori=k.id_kategori
      WHERE p.kode_pesanan = ? AND p.id_pelanggan = ?');
    $stmt->execute([$kode, pelangganId()]); $order = $stmt->fetch();
    if (!$order) $error = 'Kode pesanan tidak ditemukan.';
    else $rin = rincianPesanan($order);
  } catch (Throwable $ex) { $error = 'Data belum dapat dimuat. Silakan coba lagi.'; }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro"><span class="eyebrow">CEK PESANAN</span><h1>Cek pesanan</h1><p>Masukkan kode pesanan yang kamu terima setelah mengirim form untuk melihat ringkasannya.</p></section>
<form class="panel lookup-form" method="get" action="pesanan.php">
  <label for="kode">Kode pesanan</label><div class="lookup-row"><input id="kode" name="kode" type="text" placeholder="Contoh: WSH-261016-A1B2C3" value="<?= e((string)($_GET['kode'] ?? '')) ?>" required><button class="button button-primary" type="submit">Cari pesanan</button></div>
</form>
<?php if ($error): ?><p class="notice notice-error"><?= e($error) ?></p><?php endif; ?>
<?php if ($order): ?>
<?php $menunggu = $order['status_pesanan'] === 'menunggu_konfirmasi'; ?>
<section class="panel order-detail"><span class="status-pill"><?= e(labelStatus($order['status_pesanan'])) ?></span><h2><?= e($order['kode_pesanan']) ?></h2>
<?php if ($menunggu): ?><p class="notice">Pesanan menunggu konfirmasi petugas. Petugas akan memeriksa barang dan menimbang beratnya terlebih dahulu.</p><?php endif; ?>
<div class="detail-list"><div><strong>Kategori</strong><span><?= e($order['kategori']) ?> · <?= (int)$order['jumlah'] ?> <?= e($order['satuan']) ?></span></div>
<?php if ($order['berat_kg'] !== null): ?><div><strong>Berat/jumlah aktual</strong><span><?= e(labelBerat($order)) ?></span></div><?php endif; ?>
<div><strong>Layanan</strong><span><?= e($order['nama_layanan']) ?></span></div><div><strong>Penyerahan</strong><span><?= $order['metode_penyerahan'] === 'antar_jemput' ? 'Antar-jemput' : 'Mandiri' ?></span></div>
<div><strong><?= $rin['final'] ? 'Biaya laundry' : 'Perkiraan biaya laundry' ?></strong><span><?= rupiah($rin['subtotal']) ?></span></div>
<div><strong>Biaya admin</strong><span><?= rupiah($rin['admin']) ?></span></div>
<?php if ($rin['diskon'] > 0): ?><div><strong>Diskon<?= $rin['nama_promo'] ? ' (' . e($rin['nama_promo']) . ')' : '' ?></strong><span>−<?= rupiah($rin['diskon']) ?></span></div><?php endif; ?>
<div><strong><?= $rin['final'] ? 'Total tagihan' : 'Total estimasi' ?></strong><span><?= rupiah($rin['total']) ?></span></div>
<div><strong>Pembayaran</strong><span><?= e($order['status_pembayaran'] === 'belum_ada_tagihan' ? 'Belum ada tagihan' : labelStatus($order['status_pembayaran'])) ?></span></div></div>
<?php if ($order['status_pembayaran'] === 'menunggu_pembayaran' && $order['status_pesanan'] !== 'dibatalkan'): ?>
<p class="notice">Berat sudah dikonfirmasi petugas. Silakan lakukan pembayaran agar cucianmu dapat dikerjakan.</p>
<a class="button button-primary" href="pembayaran.php?kode=<?= urlencode($order['kode_pesanan']) ?>">Bayar sekarang</a>
<?php elseif ($menunggu): ?><p class="muted">Total di atas masih estimasi; tagihan final muncul setelah berat dikonfirmasi petugas.</p><?php endif; ?></section>
<?php elseif (!$error): ?>
<div class="empty-state"><img src="../assets/kosong.svg" alt="Ilustrasi keranjang cucian kosong" width="260" height="190" loading="lazy"><p>Belum ada pesanan yang ditampilkan.<br>Masukkan kode pesananmu di atas.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
