<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$pdo = db();
$msg = '';

// Hapus satu atau beberapa pesanan yang dicentang.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $_POST['pilih'] ?? []),
        fn($id) => $id > 0
    )));

    if ($ids) {
        $placeholder = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM pesanan WHERE id_pesanan IN ($placeholder)")->execute($ids);
        $msg = count($ids) . ' pesanan berhasil dihapus.';
    }
}

$cari = trim($_GET['cari'] ?? '');

$sql = 'SELECT p.*, pl.nama, pl.no_hp, pl.alamat,
               l.nama AS layanan_nama, l.pengali_harga, k.nama AS kategori_nama, k.tarif_dasar, d.jumlah, d.satuan
        FROM pesanan p
        JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
        JOIN layanan l ON l.id_layanan = p.id_layanan
        LEFT JOIN detail_pesanan d ON d.id_pesanan = p.id_pesanan
        LEFT JOIN kategori_barang k ON k.id_kategori = d.id_kategori';

if ($cari !== '') {
    $st = $pdo->prepare($sql . ' WHERE p.kode_pesanan LIKE ? OR pl.nama LIKE ? ORDER BY p.dibuat_pada DESC');
    $st->execute(["%$cari%", "%$cari%"]);
    $rows = $st->fetchAll();
} else {
    $rows = $pdo->query($sql . ' ORDER BY p.dibuat_pada DESC')->fetchAll();
}

$judul = 'Pesanan';
require '_header.php';
?>
<section class="page-intro">
  <h1>Pesanan</h1>
  <p>Kelola penimbangan, biaya, serta status pesanan dan pembayaran.</p>
</section>

<?php if ($msg): ?>
  <p class="notice notice-success"><?= e($msg) ?></p>
<?php endif; ?>

<form class="panel" method="get">
  <div class="field">
    <label for="cari">Cari kode pesanan atau nama pelanggan</label>
    <input id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Masukkan kode atau nama">
  </div>
  <div class="form-actions">
    <button class="button button-primary" type="submit">Cari</button>
    <?php if ($cari !== ''): ?>
      <a class="button button-quiet" href="pesanan.php">Reset</a>
    <?php endif; ?>
  </div>
</form>

<form method="post" id="deleteForm">
  <input type="hidden" name="aksi" value="hapus">
  <div class="panel table-wrap">
    <div class="panel-head">
      <h2>Daftar pesanan</h2>
      <button class="small-btn danger" type="button" id="openDeleteConfirm">Hapus terpilih</button>
    </div>
    <table>
      <thead>
        <tr>
          <th>Pilih</th><th>Kode / Pelanggan</th><th>Barang</th><th>Layanan</th><th>Berat</th>
          <th>Total</th><th>Status pesanan</th><th>Pembayaran</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $rin = rincianPesanan($r); $total = $rin['total']; ?>
          <tr>
            <td><input type="checkbox" name="pilih[]" value="<?= (int)$r['id_pesanan'] ?>" aria-label="Pilih pesanan <?= e($r['kode_pesanan']) ?>"></td>
            <td>
              <strong><?= e($r['kode_pesanan']) ?></strong><br>
              <?= e($r['nama']) ?><br>
              <small><?= e($r['no_hp']) ?></small>
            </td>
            <td><?= e($r['kategori_nama'] ?? '-') ?></td>
            <td>
              <?= e($r['layanan_nama']) ?><br>
              <small><?= e($r['metode_penyerahan'] === 'mandiri' ? 'Bawa sendiri' : 'Antar jemput') ?></small>
            </td>
            <td><?= e(labelBerat($r)) ?></td>
            <td><?= rupiah((float)$total) ?><?php if ($rin['diskon'] > 0): ?><br><small>Diskon <?= rupiah($rin['diskon']) ?></small><?php endif; ?><?php if (!$rin['final']): ?><br><small>(estimasi)</small><?php endif; ?></td>
            <td><?= e(labelStatus($r['status_pesanan'])) ?></td>
            <td><?= e(labelStatus($r['status_pembayaran'])) ?></td>
            <td><a class="small-btn" href="edit_pesanan.php?id=<?= (int)$r['id_pesanan'] ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="9">Data pesanan tidak ditemukan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</form>

<div id="deleteModal" class="confirm-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
  <div class="confirm-card">
    <img src="../assets/logo.svg" width="54" alt="Logo Washie">
    <span class="eyebrow">KONFIRMASI</span>
    <h2 id="deleteTitle">Hapus pesanan?</h2>
    <p id="deleteMessage">Pesanan yang dipilih akan dihapus dan tidak dapat dikembalikan.</p>
    <div class="confirm-actions">
      <button type="button" class="button button-primary" id="confirmDelete">Ya, hapus</button>
      <button type="button" class="button button-quiet" id="cancelDelete">Batal</button>
    </div>
  </div>
</div>

<script>
(() => {
  const modal   = document.getElementById('deleteModal');
  const form    = document.getElementById('deleteForm');
  const buka    = document.getElementById('openDeleteConfirm');
  const ya      = document.getElementById('confirmDelete');
  const batal   = document.getElementById('cancelDelete');
  const judul   = document.getElementById('deleteTitle');
  const pesan   = document.getElementById('deleteMessage');
  if (!modal || !form) return;

  const jumlahTerpilih = () => form.querySelectorAll('input[name="pilih[]"]:checked').length;
  const tutup = () => { modal.hidden = true; };

  buka.addEventListener('click', () => {
    const n = jumlahTerpilih();
    if (n === 0) {
      judul.textContent = 'Belum ada yang dipilih';
      pesan.textContent = 'Centang satu atau beberapa pesanan pada tabel terlebih dahulu.';
      ya.hidden = true;
    } else {
      judul.textContent = 'Hapus pesanan?';
      pesan.textContent = `${n} pesanan yang dipilih akan dihapus dan tidak dapat dikembalikan.`;
      ya.hidden = false;
    }
    modal.hidden = false;
  });

  batal.addEventListener('click', tutup);
  modal.addEventListener('click', e => { if (e.target === modal) tutup(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) tutup(); });
  ya.addEventListener('click', () => { if (jumlahTerpilih()) form.submit(); });
})();
</script>
<?php require '_footer.php'; ?>
