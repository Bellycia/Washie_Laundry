<?php
require_once __DIR__ . '/../config.php';
roleWajib('pelanggan');

$kode   = strtoupper(trim($_GET['kode'] ?? ''));
$where  = 'p.id_pelanggan = ?';
$params = [pelangganId()];

if ($kode !== '') {
    $where   .= ' AND p.kode_pesanan LIKE ?';
    $params[] = '%' . $kode . '%';
}

$st = db()->prepare(
    "SELECT p.*, l.nama AS layanan, l.pengali_harga, k.nama AS kategori, k.tarif_dasar, d.jumlah, d.satuan
     FROM pesanan p
     JOIN layanan l ON p.id_layanan = l.id_layanan
     JOIN detail_pesanan d ON p.id_pesanan = d.id_pesanan
     JOIN kategori_barang k ON d.id_kategori = k.id_kategori
     WHERE $where
     ORDER BY p.dibuat_pada DESC"
);
$st->execute($params);
$orders = $st->fetchAll();

$pageTitle = 'Riwayat Pesanan';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro">
  <span class="eyebrow">PANTAU PESANAN</span>
  <h1>Riwayat pesanan</h1>
  <p>Daftar seluruh pesanan dari berbagai tanggal. Cari berdasarkan kode pesanan jika diperlukan.</p>
</section>

<form class="panel lookup-form" method="get" action="pesanan-saya.php">
  <label for="kode">Cari kode pesanan</label>
  <div class="lookup-row">
    <input id="kode" name="kode" type="search" placeholder="Contoh: WSH-261101" value="<?= e($kode) ?>">
    <button class="button button-primary">Cari</button>
    <a class="button button-quiet" href="pesanan-saya.php">Reset</a>
  </div>
</form>

<?php if ($orders): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Kode / Tanggal</th><th>Barang</th><th>Layanan</th><th>Berat</th>
          <th>Total biaya</th><th>Status pesanan</th><th>Pembayaran</th><th>Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td>
              <strong><?= e($o['kode_pesanan']) ?></strong><br>
              <small><?= e(date('d-m-Y', strtotime($o['dibuat_pada']))) ?></small>
            </td>
            <td><?= e($o['kategori']) ?></td>
            <td><?= e($o['layanan']) ?></td>
            <td><?= e(labelBerat($o)) ?></td>
            <?php $rin = rincianPesanan($o); ?>
            <td><?= rupiah($rin['total']) ?><?php if ($rin['diskon'] > 0): ?><br><small>Diskon <?= rupiah($rin['diskon']) ?></small><?php endif; ?><?php if (!$rin['final']): ?><br><small>(estimasi)</small><?php endif; ?></td>
            <td><?= e(labelStatus($o['status_pesanan'])) ?></td>
            <td><?= e($o['status_pembayaran'] === 'belum_ada_tagihan' ? 'Belum ada tagihan' : labelStatus($o['status_pembayaran'])) ?></td>
            <td><a href="pesanan.php?kode=<?= urlencode($o['kode_pesanan']) ?>">Lihat</a>
              <?php if ($o['status_pembayaran'] === 'menunggu_pembayaran' && $o['status_pesanan'] !== 'dibatalkan'): ?> · <a href="pembayaran.php?kode=<?= urlencode($o['kode_pesanan']) ?>"><strong>Bayar</strong></a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="empty-state">
    <img src="../assets/kosong.svg" alt="Belum ada pesanan" width="230">
    <p><?= $kode ? 'Kode pesanan tidak ditemukan pada akun ini.' : 'Belum ada riwayat pesanan. Yuk, buat pesanan pertamamu.' ?></p>
    <a class="button button-primary" href="index.php">Pesan laundry</a>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
