<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$pdo = db();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['aksi'] ?? '') === 'tambah') {
        $nama      = trim($_POST['nama'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $mulai     = $_POST['mulai'] ?? '';
        $berakhir  = $_POST['berakhir'] ?? '';
        $jenis     = ($_POST['jenis_diskon'] ?? '') === 'nominal' ? 'nominal' : 'persen';
        $nilai     = (float)($_POST['nilai_diskon'] ?? 0);
        $minimal   = max(0, (float)($_POST['minimal_belanja'] ?? 0));
        $aktif     = (int)($_POST['aktif'] ?? 1) === 1 ? 1 : 0;

        if ($jenis === 'persen') {
            $nilai = min(100, $nilai);
        }

        if ($nama === '' || $mulai === '' || $berakhir === '' || $nilai <= 0) {
            $err = 'Lengkapi nama, periode, dan nilai diskon.';
        } elseif ($berakhir < $mulai) {
            $err = 'Tanggal berakhir tidak boleh sebelum tanggal mulai.';
        } else {
            $pdo->prepare(
                'INSERT INTO promo(nama, deskripsi, mulai, berakhir, jenis_diskon, nilai_diskon, minimal_belanja, aktif)
                 VALUES(?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$nama, $deskripsi, $mulai, $berakhir, $jenis, $nilai, $minimal, $aktif]);
        }
    } else {
        $pdo->prepare('DELETE FROM promo WHERE id_promo = ?')->execute([(int)($_POST['id_promo'] ?? 0)]);
    }

    if ($err === '') {
        header('Location: promo.php');
        exit;
    }
}

$rows = $pdo->query('SELECT * FROM promo ORDER BY mulai DESC')->fetchAll();

$judul = 'Promo';
require '_header.php';
?>
<section class="page-intro">
  <h1>Promo</h1>
  <p>Promo yang aktif dapat dipilih saat menghitung tagihan pesanan.</p>
</section>

<?php if ($err): ?>
  <p class="notice notice-error"><?= e($err) ?></p>
<?php endif; ?>

<div class="panel">
  <h2>Tambah promo</h2>
  <form method="post" class="admin-form">
    <input type="hidden" name="aksi" value="tambah">
    <div class="field">
      <label for="nama">Nama promo</label>
      <input id="nama" name="nama" required>
    </div>
    <div class="field">
      <label for="deskripsi">Deskripsi</label>
      <textarea id="deskripsi" name="deskripsi" rows="2" required></textarea>
    </div>
    <div class="field">
      <label for="mulai">Tanggal mulai</label>
      <input id="mulai" type="date" name="mulai" required>
    </div>
    <div class="field">
      <label for="berakhir">Tanggal berakhir</label>
      <input id="berakhir" type="date" name="berakhir" required>
    </div>
    <div class="field">
      <label for="jenisDiskon">Jenis diskon</label>
      <select name="jenis_diskon" id="jenisDiskon">
        <option value="persen">Persen (%)</option>
        <option value="nominal">Nominal (Rp)</option>
      </select>
    </div>
    <div class="field">
      <label for="nilaiDiskon" id="labelNilai">Nilai diskon (%)</label>
      <input type="number" name="nilai_diskon" id="nilaiDiskon" min="1" max="100" step=".01" required>
    </div>
    <div class="field">
      <label for="minimal">Minimal belanja (Rp)</label>
      <input id="minimal" type="number" name="minimal_belanja" min="0" value="0">
    </div>
    <div class="field">
      <label for="aktif">Status promo</label>
      <select id="aktif" name="aktif">
        <option value="1">Aktif</option>
        <option value="0">Nonaktif</option>
      </select>
    </div>
    <div class="form-actions">
      <button class="button button-primary" type="submit">Simpan promo</button>
    </div>
  </form>
</div>

<div class="panel table-wrap">
  <h2>Daftar promo</h2>
  <table>
    <thead>
      <tr><th>Promo</th><th>Periode</th><th>Diskon</th><th>Minimal belanja</th><th>Status</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['nama']) ?></strong><br><small><?= e($r['deskripsi']) ?></small></td>
          <td><?= e($r['mulai']) ?> – <?= e($r['berakhir']) ?></td>
          <td><?= $r['jenis_diskon'] === 'persen' ? e((string)$r['nilai_diskon']) . '%' : rupiah((float)$r['nilai_diskon']) ?></td>
          <td><?= rupiah((float)$r['minimal_belanja']) ?></td>
          <td><?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Hapus promo ini?')">
              <input type="hidden" name="id_promo" value="<?= (int)$r['id_promo'] ?>">
              <button class="small-btn danger" type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="6">Belum ada promo.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
(() => {
  const jenis = document.getElementById('jenisDiskon');
  const label = document.getElementById('labelNilai');
  const nilai = document.getElementById('nilaiDiskon');

  function sesuaikan() {
    const persen = jenis.value === 'persen';
    label.textContent = persen ? 'Nilai diskon (%)' : 'Nilai diskon (Rp)';
    nilai.max = persen ? '100' : '';
    nilai.step = persen ? '.01' : '1';
    nilai.placeholder = persen ? 'Contoh: 20' : 'Contoh: 10000';
  }

  jenis.addEventListener('change', sesuaikan);
  sesuaikan();
})();
</script>
<?php require '_footer.php'; ?>
