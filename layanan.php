<?php
require_once 'config.php';
roleWajib('pelanggan');

$pdo      = db();
$kategori = $pdo->query('SELECT * FROM kategori_barang ORDER BY id_kategori')->fetchAll();
$layanan  = $pdo->query('SELECT * FROM layanan ORDER BY id_layanan')->fetchAll();
// Promo yang diinput petugas: tampilkan yang aktif dan belum berakhir (sedang berlaku atau akan datang).
$promoTampil = $pdo->prepare('SELECT * FROM promo WHERE aktif = 1 AND berakhir >= ? ORDER BY mulai, nama');
$promoTampil->execute([date('Y-m-d')]);
$promoTampil = $promoTampil->fetchAll();
$hariIni = date('Y-m-d');
$tglIndo = fn(string $t) => date('d-m-Y', strtotime($t));

$pageTitle = 'Layanan dan Harga';
require 'includes/header.php';
?>
<section class="page-intro">
  <span class="eyebrow">PILIH YANG COCOK</span>
  <h1>Layanan & harga</h1>
  <p>Tarif yang jelas untuk setiap jenis barang. Harga terbaru mengikuti pengaturan petugas.</p>
</section>

<h2 class="subheading">Kategori barang</h2>
<div class="price-grid">
  <?php foreach ($kategori as $k): ?>
    <?php $gambar = 'assets/' . $k['kode'] . '.svg'; ?>
    <article class="price-card">
      <?php if (is_file(__DIR__ . '/' . $gambar)): ?>
        <img class="card-img" src="<?= e($gambar) ?>" alt="Ilustrasi <?= e($k['nama']) ?>" width="360" height="220" loading="lazy">
      <?php endif; ?>
      <h3><?= e($k['nama']) ?></h3>
      <p class="price"><?= rupiah((float)$k['tarif_dasar']) ?> <small>/ <?= e($k['satuan']) ?></small></p>
      <p>Tarif berlaku sesuai hasil pemeriksaan petugas.</p>
    </article>
  <?php endforeach; ?>
</div>

<h2 class="subheading">Jenis layanan</h2>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Layanan</th><th>Penyesuaian tarif</th>
        <?php foreach ($kategori as $k): ?><th>Tarif <?= e($k['nama']) ?> / <?= e($k['satuan']) ?></th><?php endforeach; ?>
        <th>Estimasi waktu</th></tr>
    </thead>
    <tbody>
      <?php foreach ($layanan as $l): ?>
        <tr>
          <td><strong><?= e($l['nama']) ?></strong></td>
          <td><?= e((string)(float)$l['pengali_harga']) ?> × tarif dasar</td>
          <?php foreach ($kategori as $k): ?><td><?= rupiah((float)$k['tarif_dasar'] * (float)$l['pengali_harga']) ?></td><?php endforeach; ?>
          <td><?= e($l['estimasi']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($promoTampil): ?>
<h2 class="subheading">Promo</h2>
<div class="table-wrap">
  <table>
    <thead><tr><th>Promo</th><th>Periode</th><th>Diskon</th><th>Minimal belanja</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($promoTampil as $p): $berlaku = $p['mulai'] <= $hariIni; ?>
        <tr>
          <td><strong><?= e($p['nama']) ?></strong><br><small><?= e($p['deskripsi']) ?></small></td>
          <td><?= e($tglIndo($p['mulai'])) ?> – <?= e($tglIndo($p['berakhir'])) ?></td>
          <td><?= $p['jenis_diskon'] === 'persen' ? e((string)(float)$p['nilai_diskon']) . '%' : rupiah((float)$p['nilai_diskon']) ?></td>
          <td><?= (float)$p['minimal_belanja'] > 0 ? rupiah((float)$p['minimal_belanja']) : 'Tanpa minimum' ?></td>
          <td><?= $berlaku ? 'Sedang berlaku' : 'Mulai ' . e($tglIndo($p['mulai'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="notice">Diskon otomatis dipotong dari biaya laundry saat pesanan dibuat pada periode promo. Jika ada beberapa promo yang memenuhi syarat, diskon terbesar yang dipakai.</p>
<?php endif; ?>

<p class="notice"><strong>Catatan:</strong> Harga final dihitung setelah petugas menimbang barang. Biaya antar-jemput Rp10.000 ditambahkan untuk pesanan antar-jemput.</p>
<a class="button button-primary" href="pelanggan/index.php">Pesan sekarang</a>
<?php require 'includes/footer.php'; ?>
