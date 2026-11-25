<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$pdo = db();

$stat = [
    'pesanan'    => $pdo->query('SELECT COUNT(*) FROM pesanan')->fetchColumn(),
    'menunggu'   => $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'menunggu_konfirmasi'")->fetchColumn(),
    'proses'     => $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan IN ('diproses', 'siap_diantar')")->fetchColumn(),
    'pendapatan' => $pdo->query("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM pembayaran WHERE status = 'lunas'")->fetchColumn(),
];

$terbaru = $pdo->query(
    'SELECT p.id_pesanan, p.kode_pesanan, pl.nama, p.metode_penyerahan, p.status_pesanan, p.status_pembayaran
     FROM pesanan p
     JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
     ORDER BY p.dibuat_pada DESC
     LIMIT 8'
)->fetchAll();

$judul = 'Beranda Petugas';
require '_header.php';
?>
<section class="page-intro">
  <h1>Beranda</h1>
  <p>Ringkasan pesanan dan pendapatan Washie Laundry.</p>
</section>

<div class="admin-grid">
  <div class="stat"><span class="muted">Total pesanan</span><strong><?= number_format((int)$stat['pesanan']) ?></strong></div>
  <div class="stat"><span class="muted">Menunggu konfirmasi</span><strong><?= number_format((int)$stat['menunggu']) ?></strong></div>
  <div class="stat"><span class="muted">Sedang dikerjakan</span><strong><?= number_format((int)$stat['proses']) ?></strong></div>
  <div class="stat"><span class="muted">Pendapatan lunas</span><strong><?= rupiah((float)$stat['pendapatan']) ?></strong></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Pesanan terbaru</h2>
    <a class="small-btn" href="pesanan.php">Lihat semua</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Kode</th><th>Pelanggan</th><th>Penyerahan</th><th>Status</th><th>Pembayaran</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($terbaru as $r): ?>
          <tr>
            <td><?= e($r['kode_pesanan']) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td><?= e(labelStatus($r['metode_penyerahan'])) ?></td>
            <td><?= e(labelStatus($r['status_pesanan'])) ?></td>
            <td><?= e(labelStatus($r['status_pembayaran'])) ?></td>
            <td><a class="small-btn" href="pesanan.php?cari=<?= urlencode($r['kode_pesanan']) ?>">Lihat</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$terbaru): ?>
          <tr><td colspan="6">Belum ada pesanan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require '_footer.php'; ?>
