<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'layanan_tambah') {
        $nama     = trim($_POST['nama'] ?? '');
        $pengali  = (float)($_POST['pengali_harga'] ?? 0);
        $estimasi = trim($_POST['estimasi'] ?? '');

        if ($nama !== '' && $pengali > 0 && $estimasi !== '') {
            // Kode layanan dibuat dari nama; jika sudah dipakai, ditambah akhiran waktu.
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $nama);
            $dasar = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$ascii), '_'));
            $kode  = substr($dasar ?: 'layanan', 0, 15);

            $cek = $pdo->prepare('SELECT COUNT(*) FROM layanan WHERE kode = ?');
            $cek->execute([$kode]);
            if ((int)$cek->fetchColumn() > 0) {
                $kode = substr($kode, 0, 11) . '_' . substr((string)time(), -3);
            }

            $pdo->prepare('INSERT INTO layanan(kode, nama, pengali_harga, estimasi) VALUES(?, ?, ?, ?)')
                ->execute([$kode, $nama, $pengali, $estimasi]);
        }
    } elseif ($aksi === 'layanan_update') {
        $id       = (int)($_POST['id_layanan'] ?? 0);
        $pengali  = (float)($_POST['pengali_harga'] ?? 0);
        $estimasi = trim($_POST['estimasi'] ?? '');

        if ($id > 0 && $pengali > 0 && $estimasi !== '') {
            $pdo->prepare('UPDATE layanan SET pengali_harga = ?, estimasi = ? WHERE id_layanan = ?')
                ->execute([$pengali, $estimasi, $id]);
        }
    } elseif ($aksi === 'tarif_update') {
        $id    = (int)($_POST['id_kategori'] ?? 0);
        // Input boleh berupa "Rp 12.500", "12.500", atau "12500".
        $tarif = parseRupiah((string)($_POST['tarif_dasar'] ?? ''));

        if ($id < 1 || $tarif === null || $tarif < 1) {
            header('Location: layanan.php?gagal=1');
            exit;
        }
        $pdo->prepare('UPDATE kategori_barang SET tarif_dasar = ? WHERE id_kategori = ?')->execute([$tarif, $id]);
        // Rincian pada pesanan yang belum ditimbang selalu dihitung dari tarif terbaru ini,
        // jadi menu Pesanan petugas dan semua halaman pelanggan otomatis ikut berubah.
    }

    header('Location: layanan.php?updated=1');
    exit;
}

$layanan  = $pdo->query('SELECT * FROM layanan ORDER BY id_layanan')->fetchAll();
$kategori = $pdo->query('SELECT * FROM kategori_barang ORDER BY id_kategori')->fetchAll();

$judul = 'Layanan & Tarif';
require '_header.php';
?>
<section class="page-intro">
  <h1>Layanan & tarif</h1>
  <p>Tambah atau ubah layanan dan tarif kategori barang. Perubahan langsung tampil di halaman pelanggan.</p>
</section>

<?php if (isset($_GET['updated'])): ?>
  <p class="notice notice-success">Perubahan berhasil disimpan. Perhitungan pesanan yang belum ditimbang kini memakai tarif terbaru.</p>
<?php endif; ?>
<?php if (isset($_GET['gagal'])): ?>
  <p class="notice notice-error">Tarif tidak valid. Masukkan nominal rupiah lebih dari 0, contoh: Rp 12.000.</p>
<?php endif; ?>

<section class="panel">
  <h2>Tambah layanan baru</h2>
  <form method="post" class="admin-form">
    <input type="hidden" name="aksi" value="layanan_tambah">
    <div class="field">
      <label for="nama">Nama layanan</label>
      <input id="nama" name="nama" placeholder="Contoh: Kilat" required>
    </div>
    <div class="field">
      <label for="pengali">Pengali harga (x)</label>
      <input id="pengali" type="number" name="pengali_harga" min="0.01" step="0.01" placeholder="Contoh: 1.50" required>
    </div>
    <div class="field">
      <label for="estimasi">Estimasi selesai</label>
      <input id="estimasi" name="estimasi" placeholder="Contoh: 1 hari kerja" required>
    </div>
    <div class="form-actions">
      <button class="button button-primary" type="submit">Simpan layanan</button>
    </div>
  </form>
</section>

<section class="panel">
  <h2>Daftar layanan</h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nama layanan</th><th>Pengali harga</th><th>Estimasi selesai</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($layanan as $r): ?>
          <tr>
            <td><?= e($r['nama']) ?></td>
            <td><?= e((string)$r['pengali_harga']) ?>x</td>
            <td><?= e($r['estimasi']) ?></td>
            <td>
              <details class="table-edit">
                <summary class="small-btn">Edit</summary>
                <form method="post">
                  <input type="hidden" name="aksi" value="layanan_update">
                  <input type="hidden" name="id_layanan" value="<?= (int)$r['id_layanan'] ?>">
                  <div class="field">
                    <label>Pengali harga (x)</label>
                    <input type="number" name="pengali_harga" min="0.01" step="0.01" value="<?= e((string)$r['pengali_harga']) ?>" required>
                  </div>
                  <div class="field">
                    <label>Estimasi selesai</label>
                    <input name="estimasi" value="<?= e($r['estimasi']) ?>" required>
                  </div>
                  <button class="small-btn" type="submit">Simpan</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$layanan): ?>
          <tr><td colspan="4">Belum ada layanan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="panel">
  <h2>Tarif kategori</h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Kategori</th><th>Tarif saat ini</th><th>Satuan</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($kategori as $c): ?>
          <tr>
            <td><?= e($c['nama']) ?></td>
            <td><?= rupiah((float)$c['tarif_dasar']) ?></td>
            <td><?= e($c['satuan']) ?></td>
            <td>
              <details class="table-edit">
                <summary class="small-btn">Edit tarif</summary>
                <form method="post">
                  <input type="hidden" name="aksi" value="tarif_update">
                  <input type="hidden" name="id_kategori" value="<?= (int)$c['id_kategori'] ?>">
                  <div class="field">
                    <label>Tarif baru (Rp / <?= e($c['satuan']) ?>)</label>
                    <div class="input-rp" style="display:flex;align-items:center;gap:6px">
                      <span aria-hidden="true"><strong>Rp</strong></span>
                      <input type="text" name="tarif_dasar" class="input-rupiah" inputmode="numeric" autocomplete="off"
                             value="<?= e(number_format((float)$c['tarif_dasar'], 0, ',', '.')) ?>" placeholder="Contoh: 12.000" required>
                    </div>
                  </div>
                  <button class="small-btn" type="submit">Simpan</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$kategori): ?>
          <tr><td colspan="4">Belum ada kategori.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
// Format otomatis input tarif menjadi rupiah (12.500) dan cegah simpan jika kosong/0.
document.querySelectorAll('.input-rupiah').forEach(el => {
  const format = () => {
    const angka = el.value.replace(/\D+/g, '').replace(/^0+/, '');
    el.value = angka ? Number(angka).toLocaleString('id-ID') : '';
  };
  el.addEventListener('input', format);
  el.form.addEventListener('submit', ev => {
    if (!el.value) { ev.preventDefault(); el.setCustomValidity('Masukkan tarif dalam rupiah.'); el.reportValidity(); }
    else el.setCustomValidity('');
  });
  format();
});
</script>
<?php require '_footer.php'; ?>
