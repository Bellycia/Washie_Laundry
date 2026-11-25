<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$pdo = db();
$errId = 0;      // id pesanan yang gagal divalidasi
$errMsg = '';    // pesan kesalahan
$input = [];     // nilai yang diketik petugas, dipertahankan agar bisa diperbaiki

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id_pesanan'] ?? 0);
    $tj = trim((string)($_POST['tanggal_jemput'] ?? ''));
    $wj = trim((string)($_POST['waktu_jemput'] ?? ''));
    $ta = trim((string)($_POST['tanggal_antar'] ?? ''));
    $wa = trim((string)($_POST['waktu_antar'] ?? ''));

    $err = validasiPasanganJadwal($tj, $wj, $ta, $wa);
    if ($err !== null) {
        // Tidak disimpan: tampilkan kembali halaman dengan pesan dan input yang sama.
        $errId = $id; $errMsg = $err;
        $input = ['tanggal_jemput' => $tj, 'waktu_jemput' => $wj, 'tanggal_antar' => $ta, 'waktu_antar' => $wa];
    } else {
        $st = $pdo->prepare(
            "UPDATE pesanan
             SET tanggal_jemput = ?, waktu_jemput = ?, tanggal_antar = ?, waktu_antar = ?
             WHERE id_pesanan = ? AND metode_penyerahan = 'antar_jemput'"
        );
        $st->execute([$tj, $wj, $ta, $wa, $id]);
        header('Location: jadwal.php?ok=1');
        exit;
    }
}

$rows = $pdo->query(
    "SELECT p.id_pesanan, p.kode_pesanan, p.tanggal_jemput, p.waktu_jemput, p.tanggal_antar, p.waktu_antar,
            pl.nama, pl.no_hp, pl.alamat
     FROM pesanan p
     JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
     WHERE p.metode_penyerahan = 'antar_jemput'
     ORDER BY p.tanggal_jemput, p.waktu_jemput"
)->fetchAll();

$judul = 'Jadwal';
require '_header.php';
?>
<section class="page-intro">
  <h1>Jadwal antar-jemput</h1>
  <p>Jadwal yang disimpan di sini langsung terlihat pada akun pelanggan. Operasional: Senin–Sabtu, <?= e(str_replace(':', '.', JAM_BUKA)) ?>–<?= e(str_replace(':', '.', JAM_TUTUP)) ?> WIB. Tanggal antar tidak boleh sebelum tanggal jemput.</p>
</section>

<?php if (isset($_GET['ok'])): ?>
  <p class="notice notice-success">Jadwal berhasil disimpan.</p>
<?php endif; ?>
<?php if ($errMsg): ?>
  <p class="notice notice-error" role="alert">Jadwal belum disimpan. <?= e($errMsg) ?> Silakan input ulang pada baris yang ditandai.</p>
<?php endif; ?>

<div class="panel table-wrap">
  <table id="tabelJadwal" data-buka="<?= e(JAM_BUKA) ?>" data-tutup="<?= e(JAM_TUTUP) ?>" data-hari-buka="<?= e(implode(',', HARI_BUKA)) ?>">
    <thead>
      <tr><th>Kode / Pelanggan</th><th>Alamat</th><th>Jadwal jemput</th><th>Jadwal antar</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $id = (int)$r['id_pesanan'];
        $gagal = ($errId === $id);
        $v = $gagal ? $input : [
            'tanggal_jemput' => $r['tanggal_jemput'] ?? '', 'waktu_jemput' => substr((string)$r['waktu_jemput'], 0, 5),
            'tanggal_antar'  => $r['tanggal_antar'] ?? '',  'waktu_antar'  => substr((string)$r['waktu_antar'], 0, 5),
        ];
      ?>
        <tr<?= $gagal ? ' class="row-error" style="background:#fff4f2"' : '' ?>>
          <td>
            <strong><?= e($r['kode_pesanan']) ?></strong><br>
            <?= e($r['nama']) ?><br>
            <small><?= e($r['no_hp']) ?></small>
          </td>
          <td><?= e($r['alamat']) ?></td>
          <td>
            <input form="jadwal-<?= $id ?>" type="date" name="tanggal_jemput" value="<?= e($v['tanggal_jemput']) ?>" required>
            <input form="jadwal-<?= $id ?>" type="time" name="waktu_jemput" value="<?= e($v['waktu_jemput']) ?>" min="<?= e(JAM_BUKA) ?>" max="<?= e(JAM_TUTUP) ?>" required>
          </td>
          <td>
            <input form="jadwal-<?= $id ?>" type="date" name="tanggal_antar" value="<?= e($v['tanggal_antar']) ?>" required>
            <input form="jadwal-<?= $id ?>" type="time" name="waktu_antar" value="<?= e($v['waktu_antar']) ?>" min="<?= e(JAM_BUKA) ?>" max="<?= e(JAM_TUTUP) ?>" required>
          </td>
          <td>
            <form id="jadwal-<?= $id ?>" method="post" class="form-jadwal" novalidate>
              <input type="hidden" name="id_pesanan" value="<?= $id ?>">
              <button class="small-btn" type="submit">Simpan</button>
              <small class="field-error" role="alert"><?= $gagal ? e($errMsg) : '' ?></small>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="5">Belum ada pesanan antar-jemput.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
// Validasi di browser (cermin aturan PHP). Server tetap memvalidasi ulang sebelum menyimpan.
(() => {
  const tabel = document.getElementById('tabelJadwal');
  if (!tabel) return;
  const BUKA = tabel.dataset.buka, TUTUP = tabel.dataset.tutup;
  const HARI = tabel.dataset.hariBuka.split(',').map(Number);
  const jam = s => s.replace(':', '.');
  const hari = t => { const d = new Date(t + 'T00:00:00').getDay(); return d === 0 ? 7 : d; };

  function cek(f) {
    const q = n => document.querySelector(`input[form="${f.id}"][name="${n}"]`);
    const tj = q('tanggal_jemput').value, wj = q('waktu_jemput').value;
    const ta = q('tanggal_antar').value,  wa = q('waktu_antar').value;
    if (!tj || !wj || !ta || !wa) return 'Lengkapi tanggal dan jam jemput serta antar.';
    if (ta < tj) return 'Tanggal antar tidak boleh sebelum tanggal jemput.';
    if (!HARI.includes(hari(tj))) return 'Tanggal jemput jatuh pada hari libur. Kami buka Senin–Sabtu.';
    if (!HARI.includes(hari(ta))) return 'Tanggal antar jatuh pada hari libur. Kami buka Senin–Sabtu.';
    for (const [w, l] of [[wj, 'jemput'], [wa, 'antar']]) {
      if (w < BUKA || w > TUTUP) return `Jam ${l} di luar jam operasional (${jam(BUKA)}–${jam(TUTUP)} WIB).`;
    }
    if (ta === tj && wa <= wj) return 'Jam antar harus setelah jam jemput pada hari yang sama.';
    return '';
  }

  document.querySelectorAll('form.form-jadwal').forEach(f => {
    const q = n => document.querySelector(`input[form="${f.id}"][name="${n}"]`);
    // Tanggal antar tidak bisa dipilih sebelum tanggal jemput.
    const sinkron = () => { q('tanggal_antar').min = q('tanggal_jemput').value || ''; };
    q('tanggal_jemput').addEventListener('change', sinkron); sinkron();

    f.addEventListener('submit', e => {
      const pesan = cek(f);
      f.querySelector('.field-error').textContent = pesan;
      if (pesan) e.preventDefault();
    });
  });
})();
</script>
<?php require '_footer.php'; ?>
