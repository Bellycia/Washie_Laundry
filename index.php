<?php
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/koneksi.php';
wajibPelanggan(); // belum login -> halaman daftar, admin -> dashboard admin

$uid = (int) $_SESSION['user_id'];

// [PHP] Data profil akun yang sedang login
$st = $pdo->prepare('SELECT username, email, no_hp, alamat, created_at FROM pelanggan WHERE id = ?');
$st->execute([$uid]);
$profil = $st->fetch(PDO::FETCH_ASSOC);
if (!$profil) { // akun sudah tidak ada
    header('Location: logout.php');
    exit;
}

$tarifKategori = [
    'pakaian' => ['nama' => 'Pakaian', 'tarif' => 8000,  'satuan' => 'kg',     'ket' => 'Cucian harian seperti kaus, kemeja, dan celana.'],
    'sepatu'  => ['nama' => 'Sepatu',  'tarif' => 25000, 'satuan' => 'pasang', 'ket' => 'Perawatan sepatu sesuai bahan dan kondisi.'],
    'tas'     => ['nama' => 'Tas',     'tarif' => 30000, 'satuan' => 'buah',   'ket' => 'Pembersihan tas berdasarkan jenis material.'],
];

$pengaliLayanan = [
    'xpress'  => ['nama' => 'Xpress',  'waktu' => '1 hari',   'pengali' => 1.8, 'cocok' => 'Butuh cepat'],
    'reguler' => ['nama' => 'Reguler', 'waktu' => '2-3 hari', 'pengali' => 1.0, 'cocok' => 'Kebutuhan sehari-hari'],
    'hemat'   => ['nama' => 'Hemat',   'waktu' => '4-5 hari', 'pengali' => 0.8, 'cocok' => 'Tidak terburu-buru'],
];

const BIAYA_ANTAR_JEMPUT = 10000;
const BATAS_JUMLAH = 100; // maksimal per pemesanan

function hitungBiaya(int $tarif, int $jumlah, float $pengali, string $penyerahan): array {
    $subtotal = (int) round($tarif * $jumlah * $pengali);
    $diskon   = ($subtotal >= 100000) ? (int) round($subtotal * 0.10) : 0;
    $antar    = ($penyerahan === 'antar_jemput') ? BIAYA_ANTAR_JEMPUT : 0;
    return [
        'subtotal' => $subtotal,
        'diskon'   => $diskon,
        'antar'    => $antar,
        'total'    => $subtotal - $diskon + $antar,
    ];
}

// Isian awal form pesanan diambil dari profil akun
$default = [
    'nama' => $profil['username'], 'no_hp' => (string) $profil['no_hp'], 'kategori' => '',
    'jumlah' => '', 'layanan' => '', 'penyerahan' => '', 'alamat' => (string) $profil['alamat'],
];
$old          = $default;
$errors       = [];
$errorsProfil = [];
$bukaProfil   = false;

// Notifikasi sekali tampil (hasil pesanan & update profil)
$hasil       = $_SESSION['hasil_pesanan'] ?? null;
$flashProfil = $_SESSION['flash_profil'] ?? null;
unset($_SESSION['hasil_pesanan'], $_SESSION['flash_profil']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // ---------- [PHP] Ubah no telp & alamat di profil ----------
    if ($aksi === 'profil') {
        $hp     = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if ($hp !== '' && !preg_match('/^[0-9]{10,13}$/', $hp)) {
            $errorsProfil[] = 'Nomor telp harus berupa angka 10-13 digit.';
        }
        if ($alamat !== '' && strlen($alamat) < 10) {
            $errorsProfil[] = 'Alamat minimal 10 karakter.';
        }

        if (count($errorsProfil) === 0) {
            $pdo->prepare('UPDATE pelanggan SET no_hp = ?, alamat = ? WHERE id = ?')
                ->execute([$hp !== '' ? $hp : null, $alamat !== '' ? $alamat : null, $uid]);
            $_SESSION['flash_profil'] = 'Profil berhasil diperbarui.';
            header('Location: index.php#profil');
            exit;
        }
        $profil['no_hp']  = $hp;
        $profil['alamat'] = $alamat;
        $bukaProfil = true;
    }

    // ---------- [PHP] Form pemesanan ----------
    if ($aksi === 'pesan') {
        foreach ($old as $key => $value) {
            $old[$key] = trim($_POST[$key] ?? '');
        }

        if ($old['nama'] === '') {
            $errors[] = 'Nama wajib diisi.';
        } elseif (strlen($old['nama']) < 3) {
            $errors[] = 'Nama minimal 3 karakter.';
        }

        if ($old['no_hp'] === '') {
            $errors[] = 'Nomor HP wajib diisi.';
        } elseif (!preg_match('/^[0-9]{10,13}$/', $old['no_hp'])) {
            $errors[] = 'Nomor HP harus berupa angka 10-13 digit.';
        }

        if (!isset($tarifKategori[$old['kategori']])) {
            $errors[] = 'Pilih kategori barang.';
        }

        // Pesan batas mengikuti satuan kategori: kg / pasang / buah
        $satuanTeks = isset($tarifKategori[$old['kategori']]) ? ' ' . $tarifKategori[$old['kategori']]['satuan'] : '';
        if (!ctype_digit($old['jumlah']) || (int) $old['jumlah'] < 1) {
            $errors[] = 'Jumlah minimal 1 dan berupa angka bulat.';
        } elseif ((int) $old['jumlah'] > BATAS_JUMLAH) {
            $errors[] = 'Maksimal ' . BATAS_JUMLAH . $satuanTeks . ' setiap pemesanan.';
        }

        if (!isset($pengaliLayanan[$old['layanan']])) {
            $errors[] = 'Pilih jenis layanan.';
        }

        if (!in_array($old['penyerahan'], ['antar_jemput', 'mandiri'], true)) {
            $errors[] = 'Pilih metode penyerahan.';
        } elseif ($old['penyerahan'] === 'antar_jemput' && $old['alamat'] === '') {
            $errors[] = 'Alamat wajib diisi untuk antar-jemput.';
        }

        if (count($errors) === 0) {
            $kat   = $tarifKategori[$old['kategori']];
            $lay   = $pengaliLayanan[$old['layanan']];
            $biaya = hitungBiaya($kat['tarif'], (int) $old['jumlah'], $lay['pengali'], $old['penyerahan']);

            $jumlahTeks     = $old['jumlah'] . ' ' . $kat['satuan'];
            $layananTeks    = $lay['nama'] . ' (' . $lay['waktu'] . ')';
            $penyerahanTeks = $old['penyerahan'] === 'antar_jemput' ? 'Antar-jemput' : 'Mandiri';

            // Simpan pesanan ke database -> otomatis muncul di dashboard admin
            $stmt = $pdo->prepare('INSERT INTO pesanan
                (user_id, nama, no_hp, alamat, kategori, jumlah, layanan, penyerahan, subtotal, diskon, antar, total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $uid, $old['nama'], $old['no_hp'], $old['alamat'] !== '' ? $old['alamat'] : null,
                $kat['nama'], $jumlahTeks, $layananTeks, $penyerahanTeks,
                $biaya['subtotal'], $biaya['diskon'], $biaya['antar'], $biaya['total'],
            ]);
            $idPesanan = (int) $pdo->lastInsertId();
            $kode = 'WSH-' . date('ymd') . '-' . str_pad((string) $idPesanan, 4, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE pesanan SET kode = ? WHERE id = ?')->execute([$kode, $idPesanan]);

            // Redirect supaya refresh halaman tidak mengirim pesanan dua kali
            $_SESSION['hasil_pesanan'] = [
                'kode' => $kode, 'nama' => $old['nama'], 'no_hp' => $old['no_hp'], 'alamat' => $old['alamat'],
                'kategori' => $kat['nama'], 'jumlah' => $jumlahTeks, 'layanan' => $layananTeks,
                'penyerahan' => $penyerahanTeks, 'biaya' => $biaya,
            ];
            header('Location: index.php#pesan');
            exit;
        }
    }
}

// [PHP] Pesanan milik pelanggan ini saja
$st = $pdo->prepare('SELECT * FROM pesanan WHERE user_id = ? ORDER BY id DESC');
$st->execute([$uid]);
$daftarPesanan = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Washie Laundry - laundry pakaian, sepatu, dan tas dengan antar-jemput, tarif jelas, dan status pesanan yang bisa dipantau.">
  <title>Washie Laundry | Beranda</title>
  <link rel="icon" type="image/svg+xml" href="assets/logo.svg">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/script.js" defer></script>
</head>
<body>

<header class="site-header">
  <a class="brand" href="#beranda" aria-label="Washie Laundry, beranda">
    <img class="brand-logo" src="assets/logo.svg" alt="" width="39" height="39">
    <span>Washie<span class="brand-light">Laundry</span></span>
  </a>
  <button class="menu-toggle" id="menuToggle" type="button" aria-label="Buka menu" aria-expanded="false">☰</button>
  <nav class="main-nav" id="mainNav" aria-label="Menu utama">
    <a href="#beranda">Beranda</a>
    <a href="#tentang">Tentang Kami</a>
    <a href="#harga">Layanan</a>
    <a href="#pesan">Pesan Laundry</a>
    <a href="#pesanan-saya">Pesanan Saya</a>
    <a href="#profil">Profil</a>
  </nav>
</header>

<main class="page-shell">

<!-- ================= BERANDA ================= -->
<section class="hero" id="beranda">
  <div class="hero-copy">
    <span class="eyebrow">LAUNDRY ANTAR-JEMPUT DI BATAM</span>
    <h1>Cucian beres,<br><span>harimu lebih ringan.</span></h1>
    <p>Halo, <strong><?= e($profil['username']) ?></strong>! Laundry pakaian, sepatu, dan tas dalam satu tempat. Pilih layanan dan cara penyerahan yang paling nyaman, lalu pantau statusnya kapan saja.</p>
    <div class="hero-actions">
      <a class="button button-primary" href="#pesan">Pesan Sekarang <span aria-hidden="true">→</span></a>
      <a class="button button-quiet" href="#harga">Lihat layanan</a>
    </div>
    <ul class="trust-list"><li>✓ Tarif jelas</li><li>✓ Status bisa dipantau</li><li>✓ Senin–Sabtu, 08.00–20.00</li></ul>
  </div>
  <div class="hero-art">
    <img src="assets/hero.svg" alt="Ilustrasi mesin cuci, keranjang pakaian bersih, dan gelembung sabun" width="560" height="440">
    <div class="art-note"><span class="note-icon">✦</span><div><strong>Fresh start!</strong><small>Senin–Sabtu, 08.00–20.00</small></div></div>
  </div>
</section>

<section class="section-block tight">
  <div class="section-heading"><div><span class="eyebrow">CARA KERJA</span><h2>Semudah 3 langkah</h2></div><p>Dari pesan sampai selesai, semuanya jelas dan bisa dipantau.</p></div>
  <div class="steps-grid">
    <article class="step-card"><img src="assets/langkah-1.svg" alt="" width="120" height="90" loading="lazy"><span class="step-number">01</span><h3>Pilih layanan</h3><p>Tentukan kategori, jumlah barang, kecepatan layanan, dan cara penyerahan.</p></article>
    <article class="step-card"><img src="assets/langkah-2.svg" alt="" width="120" height="90" loading="lazy"><span class="step-number">02</span><h3>Petugas memproses</h3><p>Pesananmu masuk ke petugas, lalu dicuci sesuai layanan yang dipilih.</p></article>
    <article class="step-card"><img src="assets/langkah-3.svg" alt="" width="120" height="90" loading="lazy"><span class="step-number">03</span><h3>Pantau & terima</h3><p>Lihat status pesananmu di menu Pesanan Saya sampai cucian diserahkan.</p></article>
  </div>
</section>

<section class="cta-strip">
  <div><span class="eyebrow">MULAI SEKARANG</span><h2>Siap menyerahkan cucianmu?</h2><p>Isi data pesanan dalam hitungan menit. Biaya di awal masih berupa perkiraan.</p></div>
  <a class="button button-primary" href="#pesan">Pesan laundry →</a>
</section>

<!-- ================= TENTANG KAMI ================= -->
<section class="section-block" id="tentang">
  <div class="section-heading"><div><span class="eyebrow">KENALI WASHIE</span><h2>Tentang kami</h2></div><p>Kenali layanan kami, jam buka, dan ketentuan sebelum membuat pesanan.</p></div>

  <div class="banner"><img src="assets/toko.svg" alt="Ilustrasi gerai Washie Laundry dengan mesin cuci, keranjang cucian, dan jemuran" width="1200" height="300" loading="lazy"></div>

  <div class="content-grid">
    <article class="panel">
      <span class="panel-icon">✦</span>
      <h2>Profil Washie</h2>
      <p>Washie Laundry adalah usaha laundry kiloan dan satuan yang melayani cuci pakaian, sepatu, dan tas. Kami mengutamakan kebersihan, harga yang transparan, dan layanan yang praktis, mulai dari antar-jemput sampai pilihan kecepatan sesuai kebutuhan kamu.</p>
    </article>
    <article class="panel">
      <h2>Kontak & lokasi</h2>
      <ul class="detail-list">
        <li><strong>Alamat</strong><span>Jl. Jeruk No 67, Batam</span></li>
        <li><strong>Telepon</strong><a href="tel:+6281234567890">0812-3456-7890</a></li>
        <li><strong>Jam operasional</strong><span>Senin–Sabtu, 08.00–20.00 WIB</span></li>
        <li><strong>Hari libur</strong><span>Minggu</span></li>
      </ul>
    </article>
  </div>

  <section class="panel terms-panel" id="ketentuan">
    <h2>Ketentuan layanan laundry</h2>
    <ol>
      <li>Biaya di form pemesanan adalah perkiraan. Total akhir dikonfirmasi petugas setelah barang diperiksa.</li>
      <li>Estimasi pengerjaan: Xpress 1 hari, Reguler 2-3 hari, dan Hemat 4-5 hari, dihitung sejak barang diterima petugas.</li>
      <li>Layanan antar-jemput dikenai biaya <?= rupiahFmt(BIAYA_ANTAR_JEMPUT) ?>. Biaya laundry mulai Rp100.000 mendapat diskon 10%.</li>
      <li>Layanan tersedia Senin–Sabtu pukul 08.00–20.00 WIB dan libur pada hari Minggu.</li>
      <li>Periksa saku pakaian sebelum diserahkan, dan beri tahu petugas jika ada noda atau bahan khusus.</li>
      <li>Status pesanan dapat dipantau kapan saja di menu Pesanan Saya.</li>
    </ol>
  </section>
</section>

<!-- ================= LAYANAN ================= -->
<section class="section-block" id="harga">
  <div class="section-heading"><div><span class="eyebrow">PILIH YANG COCOK</span><h2>Layanan</h2></div><p>Tarif yang jelas untuk setiap jenis barang.</p></div>

  <div class="price-grid">
    <?php foreach ($tarifKategori as $key => $k): ?>
      <article class="price-card">
        <img class="card-img" src="assets/<?= e($key) ?>.svg" alt="Ilustrasi <?= e(strtolower($k['nama'])) ?>" width="360" height="220" loading="lazy">
        <h3><?= e($k['nama']) ?></h3>
        <p class="price"><?= rupiahFmt($k['tarif']) ?> <small>/ <?= e($k['satuan']) ?></small></p>
        <p><?= e($k['ket']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>

  <h3 class="subheading">Jenis layanan</h3>
  <div class="table-wrap"><table style="min-width:580px"><thead><tr><th>Layanan</th><th>Penyesuaian tarif</th><th>Estimasi waktu</th><th>Cocok untuk</th></tr></thead><tbody>
    <?php foreach ($pengaliLayanan as $l): ?>
      <tr><td><strong><?= e($l['nama']) ?></strong></td><td><?= $l['pengali'] ?> × tarif dasar</td><td><?= e($l['waktu']) ?></td><td><?= e($l['cocok']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <p class="notice"><strong>Catatan:</strong> Antar-jemput + <?= rupiahFmt(BIAYA_ANTAR_JEMPUT) ?> · Diskon 10% untuk biaya laundry mulai Rp100.000.</p>
</section>

<!-- ================= PESAN LAUNDRY ================= -->
<section class="section-block" id="pesan">
  <div class="section-heading"><div><span class="eyebrow">FORM PEMESANAN</span><h2>Pesan laundry</h2></div><p>Masukan No HP dan alamat di profil agar kamu tidak perlu mengisinya berulang kali setiap pemesanan.</p></div>

  <?php if ($hasil !== null): ?>
    <section class="panel result-panel" id="hasil">
      <div class="success-mark">✓</div>
      <h2>Pesanan berhasil diterima</h2>
      <p>Pesananmu sudah masuk ke petugas. Pantau statusnya di menu Pesanan Saya.</p>
      <div class="order-code"><small>Kode pesanan</small><strong><?= e($hasil['kode']) ?></strong></div>
      <div class="summary-box result-summary">
        <div><span>Nama</span><strong><?= e($hasil['nama']) ?></strong></div>
        <div><span>No. HP</span><strong><?= e($hasil['no_hp']) ?></strong></div>
        <div><span>Barang</span><strong><?= e($hasil['kategori']) ?> (<?= e($hasil['jumlah']) ?>)</strong></div>
        <div><span>Layanan</span><strong><?= e($hasil['layanan']) ?></strong></div>
        <div><span>Penyerahan</span><strong><?= e($hasil['penyerahan']) ?></strong></div>
        <?php if ($hasil['alamat'] !== '' && $hasil['penyerahan'] === 'Antar-jemput'): ?>
          <div><span>Alamat</span><strong><?= e($hasil['alamat']) ?></strong></div>
        <?php endif; ?>
        <div><span>Biaya laundry</span><strong><?= rupiahFmt($hasil['biaya']['subtotal']) ?></strong></div>
        <?php if ($hasil['biaya']['diskon'] > 0): ?>
          <div><span>Diskon 10%</span><strong>- <?= rupiahFmt($hasil['biaya']['diskon']) ?></strong></div>
        <?php endif; ?>
        <div><span>Biaya antar-jemput</span><strong><?= rupiahFmt($hasil['biaya']['antar']) ?></strong></div>
        <div class="summary-total"><span>Total bayar</span><strong><?= rupiahFmt($hasil['biaya']['total']) ?></strong></div>
      </div>
      <div class="hero-actions">
        <a class="button button-primary" href="#pesanan-saya">Lihat Pesanan Saya</a>
        <a class="button button-quiet" href="index.php#pesan">Buat pesanan baru</a>
      </div>
    </section>
  <?php endif; ?>

  <?php if (count($errors) > 0): ?>
    <div class="notice notice-error" id="pesanError">
      <p class="notice-title">Data belum valid</p>
      <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="form-layout">
    <form class="panel order-form" method="post" action="index.php#pesan" id="orderForm" novalidate>
      <input type="hidden" name="aksi" value="pesan">

      <fieldset>
        <legend><span>1</span> Data pemesan</legend>
        <div class="form-grid">
          <div class="field"><label for="nama">Nama</label><input type="text" id="nama" name="nama" value="<?= e($old['nama']) ?>"></div>
          <div class="field"><label for="no_hp">Nomor HP</label><input type="text" id="no_hp" name="no_hp" placeholder="08xxxxxxxxxx" value="<?= e($old['no_hp']) ?>"></div>
        </div>
      </fieldset>

      <fieldset>
        <legend><span>2</span> Detail laundry</legend>
        <div class="form-grid">
          <div class="field">
            <label for="kategori">Kategori barang</label>
            <select id="kategori" name="kategori">
              <option value="">-- Pilih kategori --</option>
              <?php foreach ($tarifKategori as $key => $k): ?>
                <option value="<?= e($key) ?>" <?= $old['kategori'] === $key ? 'selected' : '' ?>><?= e($k['nama']) ?> (<?= rupiahFmt($k['tarif']) ?>/<?= e($k['satuan']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="jumlah">Jumlah</label>
            <div class="input-unit">
              <input type="number" id="jumlah" name="jumlah" min="1" max="<?= BATAS_JUMLAH ?>" placeholder="Contoh: 3" value="<?= e($old['jumlah']) ?>">
              <span class="unit-suffix" id="satuan"></span>
            </div>
            <small id="jumlahInfo" data-batas="<?= BATAS_JUMLAH ?>">Maksimal <?= BATAS_JUMLAH ?> setiap pemesanan</small>
          </div>
          <div class="field">
            <label for="layanan">Jenis layanan</label>
            <select id="layanan" name="layanan">
              <option value="">-- Pilih layanan --</option>
              <?php foreach ($pengaliLayanan as $key => $l): ?>
                <option value="<?= e($key) ?>" <?= $old['layanan'] === $key ? 'selected' : '' ?>><?= e($l['nama']) ?> (<?= e($l['waktu']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="penyerahan">Metode penyerahan</label>
            <select id="penyerahan" name="penyerahan">
              <option value="">-- Pilih metode --</option>
              <option value="antar_jemput" <?= $old['penyerahan'] === 'antar_jemput' ? 'selected' : '' ?>>Antar-jemput (+ Rp10.000)</option>
              <option value="mandiri" <?= $old['penyerahan'] === 'mandiri' ? 'selected' : '' ?>>Mandiri (bawa sendiri)</option>
            </select>
          </div>
          <div class="field field-full" id="alamatBox" hidden>
            <label for="alamat">Alamat jemput/antar</label>
            <textarea id="alamat" name="alamat" rows="3" placeholder="Nama jalan, nomor rumah, patokan"><?= e($old['alamat']) ?></textarea>
          </div>
        </div>
      </fieldset>

      <button type="submit" class="button button-primary button-wide">Kirim pesanan</button>
    </form>

    <aside class="side-help">
      <div class="side-help-card soft">
        <h3>Estimasi biaya</h3>
        <div class="summary-box">
          <div><span>Biaya laundry</span><strong id="estSubtotal">Rp0</strong></div>
          <div><span>Diskon</span><strong id="estDiskon">Rp0</strong></div>
          <div><span>Antar-jemput</span><strong id="estAntar">Rp0</strong></div>
          <div class="summary-total"><span>Total</span><strong id="estimasi">Rp0</strong></div>
        </div>
        <p class="form-footnote estimate-note">Perkiraan awal, tagihan akhir mengikuti petugas.</p>
      </div>
    </aside>
  </div>
</section>

<!-- ================= PESANAN SAYA ================= -->
<section class="section-block" id="pesanan-saya">
  <div class="section-heading"><div><span class="eyebrow">RIWAYAT</span><h2>Pesanan saya</h2></div><p>Pantau status laundry kamu di sini.</p></div>

  <?php if (count($daftarPesanan) === 0): ?>
    <div class="panel empty-state">
      <img src="assets/kosong.svg" alt="" width="260" loading="lazy">
      <p>Kamu belum punya pesanan. <a href="#pesan">Pesan laundry sekarang</a></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Kode</th><th>Tanggal</th><th>Barang</th><th>Layanan</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($daftarPesanan as $p): ?>
            <tr>
              <td><strong><?= e($p['kode']) ?></strong></td>
              <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
              <td><?= e($p['kategori']) ?> <small>(<?= e($p['jumlah']) ?>)</small></td>
              <td><?= e($p['layanan']) ?><br><small><?= e($p['penyerahan']) ?></small></td>
              <td><?= rupiahFmt((int) $p['total']) ?></td>
              <td><?= badgeStatus($p['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- ================= PROFIL ================= -->
<section class="section-block" id="profil">
  <div class="section-heading"><div><span class="eyebrow">AKUN SAYA</span><h2>Profil</h2></div><p>Informasi akun yang terdaftar di Washie Laundry.</p></div>

  <?php if ($flashProfil): ?>
    <div class="notice notice-success" id="notifProfil" role="alert">✔ <?= e($flashProfil) ?></div>
  <?php endif; ?>

  <section class="panel profile-panel">
    <img class="profile-img" src="assets/profil.svg" alt="Foto profil pelanggan" width="96">
    <div>
      <h2><?= e($profil['username']) ?></h2>
      <p>Pelanggan Washie · terdaftar sejak <?= date('d-m-Y', strtotime($profil['created_at'])) ?></p>
    </div>
  </section>

  <section class="panel">
    <h2>Informasi pengguna</h2>
    <div class="detail-list">
      <div><strong>Username</strong><span><?= e($profil['username']) ?></span></div>
      <div><strong>Gmail</strong><span><?= e($profil['email']) ?></span></div>
      <div><strong>No. telp</strong><span><?= $profil['no_hp'] ? e($profil['no_hp']) : 'Belum diisi' ?></span></div>
      <div><strong>Alamat</strong><span><?= $profil['alamat'] ? e($profil['alamat']) : 'Belum diisi' ?></span></div>
    </div>

    <div class="profile-actions">
      <button type="button" class="button button-quiet" id="btnUbahProfil">Ubah no. telp & alamat</button>
    </div>

    <form class="profile-form" id="profilForm" method="post" action="index.php#profil" novalidate <?= $bukaProfil ? '' : 'hidden' ?>>
      <input type="hidden" name="aksi" value="profil">
      <?php if (count($errorsProfil) > 0): ?>
        <div class="notice notice-error"><ul><?php foreach ($errorsProfil as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="form-grid">
        <div class="field">
          <label for="p_no_hp">No. telp</label>
          <input type="text" id="p_no_hp" name="no_hp" placeholder="08xxxxxxxxxx" value="<?= e($profil['no_hp']) ?>">
          <small class="field-error" data-for="p_no_hp"></small>
        </div>
        <div class="field field-full">
          <label for="p_alamat">Alamat</label>
          <textarea id="p_alamat" name="alamat" rows="3" placeholder="Nama jalan, nomor rumah, patokan"><?= e($profil['alamat']) ?></textarea>
          <small class="field-error" data-for="p_alamat"></small>
        </div>
      </div>
      <div class="hero-actions">
        <button type="submit" class="button button-primary">Simpan</button>
        <button type="button" class="button button-quiet" id="btnBatalProfil">Batal</button>
      </div>
    </form>

    <div class="logout-box">
      <a class="button button-danger button-wide" href="logout.php">Logout</a>
    </div>
  </section>
</section>

</main>

<footer class="site-footer">
  <div class="footer-inner">
    <div><strong>Washie Laundry</strong><p>Bersih, wangi, dan transparan.</p></div>
    <p>© <?= date('Y') ?> Washie Laundry · Buka Senin–Sabtu, 08.00–20.00 WIB · Batam · 0812-3456-7890</p>
  </div>
</footer>

</body>
</html>
