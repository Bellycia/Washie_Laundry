<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$pdo       = db();
$pelanggan = $pdo->query('SELECT id_pelanggan, nama FROM pelanggan ORDER BY nama')->fetchAll();
$layanan   = $pdo->query('SELECT id_layanan, nama FROM layanan ORDER BY id_layanan')->fetchAll();
$kategori  = $pdo->query('SELECT id_kategori, nama FROM kategori_barang ORDER BY id_kategori')->fetchAll();

$pageTitle = 'Tambah Pesanan';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro">
  <h1>Tambah pesanan</h1>
  <p>Catat pesanan pelanggan secara manual.</p>
</section>

<form class="panel order-form" method="post" action="proses_tambah.php">
  <div class="field">
    <label>Pelanggan</label>
    <select name="id_pelanggan" required>
      <?php foreach ($pelanggan as $v): ?>
        <option value="<?= (int)$v['id_pelanggan'] ?>"><?= e($v['nama']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label>Layanan</label>
    <select name="id_layanan" required>
      <?php foreach ($layanan as $v): ?>
        <option value="<?= (int)$v['id_layanan'] ?>"><?= e($v['nama']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label>Kategori</label>
    <select name="id_kategori" required>
      <?php foreach ($kategori as $v): ?>
        <option value="<?= (int)$v['id_kategori'] ?>"><?= e($v['nama']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label>Jumlah</label>
    <input type="number" name="jumlah" min="1" required>
  </div>
  <div class="field">
    <label>Metode penyerahan</label>
    <select name="metode_penyerahan">
      <option value="mandiri">Mandiri</option>
      <option value="antar_jemput">Antar jemput</option>
    </select>
  </div>
  <div class="field">
    <label>Status pesanan</label>
    <select name="status_pesanan">
      <option value="menunggu_konfirmasi">Menunggu konfirmasi</option>
      <option value="diproses">Diproses</option>
      <option value="selesai">Selesai</option>
    </select>
  </div>
  <div class="form-actions">
    <button class="button button-primary" type="submit">Simpan</button>
    <a class="button button-quiet" href="../petugas/pesanan.php">Kembali</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
