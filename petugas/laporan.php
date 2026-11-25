<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$pdo   = db();
$awal  = $_GET['awal'] ?? date('Y-m-01');
$akhir = $_GET['akhir'] ?? date('Y-m-d');
$jenis = $_GET['jenis'] ?? 'semua';
if (!in_array($jenis, ['semua', 'antar_jemput', 'mandiri'], true)) {
    $jenis = 'semua';
}

$sql = 'SELECT p.kode_pesanan, p.dibuat_pada, pl.nama, p.metode_penyerahan, p.biaya_laundry, p.biaya_admin, p.diskon,
               p.total_final, p.status_pembayaran,
               COALESCE(pb.jumlah_tagihan, p.total_final, p.biaya_laundry - p.diskon + p.biaya_admin) AS nilai
        FROM pesanan p
        JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
        LEFT JOIN pembayaran pb ON pb.id_pesanan = p.id_pesanan
             AND pb.id_pembayaran = (SELECT MAX(x.id_pembayaran) FROM pembayaran x WHERE x.id_pesanan = p.id_pesanan)
        WHERE DATE(p.dibuat_pada) BETWEEN ? AND ?';
$args = [$awal, $akhir];

if ($jenis !== 'semua') {
    $sql   .= ' AND p.metode_penyerahan = ?';
    $args[] = $jenis;
}

$st = $pdo->prepare($sql . ' ORDER BY p.dibuat_pada DESC');
$st->execute($args);
$rows = $st->fetchAll();

$total      = count($rows);
$lunas      = 0;
$pendapatan = 0;
foreach ($rows as $r) {
    if ($r['status_pembayaran'] === 'lunas') {
        $lunas++;
        $pendapatan += (float)$r['nilai'];
    }
}
$belum = $total - $lunas;

$tab = [
    'semua'        => 'Semua transaksi',
    'antar_jemput' => 'Antar jemput',
    'mandiri'      => 'Bawa sendiri',
];

$judul = 'Laporan Transaksi';
require '_header.php';
?>
<section class="page-intro">
  <h1>Laporan transaksi</h1>
  <p>Rekap transaksi berdasarkan periode dan metode penyerahan.</p>
</section>

<div class="action-row report-tabs">
  <?php foreach ($tab as $kode => $nama): ?>
    <a class="small-btn <?= $jenis === $kode ? 'is-active' : '' ?>"
       <?= $jenis === $kode ? 'aria-current="page"' : '' ?>
       href="?jenis=<?= e($kode) ?>&awal=<?= e($awal) ?>&akhir=<?= e($akhir) ?>"><?= e($nama) ?></a>
  <?php endforeach; ?>
</div>

<form class="panel admin-form" method="get">
  <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
  <div class="field">
    <label for="awal">Dari tanggal</label>
    <input id="awal" type="date" name="awal" value="<?= e($awal) ?>" required>
  </div>
  <div class="field">
    <label for="akhir">Sampai tanggal</label>
    <input id="akhir" type="date" name="akhir" value="<?= e($akhir) ?>" required>
  </div>
  <div class="form-actions">
    <button class="button button-primary" type="submit">Tampilkan</button>
  </div>
</form>

<div class="admin-grid">
  <div class="stat"><span class="muted">Jumlah transaksi</span><strong><?= $total ?></strong></div>
  <div class="stat"><span class="muted">Lunas</span><strong><?= $lunas ?></strong></div>
  <div class="stat"><span class="muted">Belum lunas</span><strong><?= $belum ?></strong></div>
  <div class="stat"><span class="muted">Total pendapatan</span><strong><?= rupiah($pendapatan) ?></strong></div>
</div>

<div class="panel table-wrap">
  <h2><?= e($tab[$jenis]) ?></h2>
  <table>
    <thead>
      <tr>
        <th>Tanggal</th><th>Kode</th><th>Pelanggan</th><th>Metode</th>
        <th>Biaya laundry</th><th>Admin</th><th>Diskon</th><th>Total</th><th>Status pembayaran</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['dibuat_pada']) ?></td>
          <td><?= e($r['kode_pesanan']) ?></td>
          <td><?= e($r['nama']) ?></td>
          <td><?= e(labelStatus($r['metode_penyerahan'])) ?></td>
          <td><?= rupiah((float)$r['biaya_laundry']) ?></td>
          <td><?= rupiah((float)$r['biaya_admin']) ?></td>
          <td><?= rupiah((float)$r['diskon']) ?></td>
          <td><?= rupiah((float)$r['nilai']) ?></td>
          <td><?= e(labelStatus($r['status_pembayaran'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="9">Tidak ada transaksi pada periode ini.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require '_footer.php'; ?>
