<?php
require_once __DIR__ . '/../config.php';
roleWajib('pelanggan');

function input(string $key): string {
    return trim((string)($_POST[$key] ?? ''));
}
function validDate(string $date): bool {
    if ($date === '') return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
function kembaliDenganError(string $message): never {
    http_response_code(422);
    $pageTitle = 'Pemesanan gagal';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="panel result-panel"><h1>Form belum dapat diproses</h1><p>' . e($message) . '</p><a class="button button-primary" href="index.php">Kembali ke form</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$postToken = input('csrf_token');
if ($sessionToken === '' || !hash_equals($sessionToken, $postToken)) {
    kembaliDenganError('Sesi form tidak valid. Muat ulang form, lalu coba kembali.');
}

$nama = input('nama');
$noHp = input('no_hp');
$alamat = input('alamat');
$kategori = input('kategori');
$layanan = input('layanan');
$penyerahan = input('penyerahan');
$catatan = input('catatan');
$jumlah = filter_var($_POST['jumlah'] ?? null, FILTER_VALIDATE_INT);
$setuju = input('setuju') === '1';

if (mb_strlen($nama) < 2 || mb_strlen($nama) > 100) kembaliDenganError('Nama wajib diisi (2–100 karakter).');
if (!preg_match('/^[0-9+() -]{8,20}$/', $noHp)) kembaliDenganError('Nomor HP tidak valid.');
if ($alamat === '' || mb_strlen($alamat) > 2000) kembaliDenganError('Alamat wajib diisi.');
if (!$jumlah || $jumlah < 1 || $jumlah > 100) kembaliDenganError('Jumlah harus berupa angka bulat 1 sampai 100.');
if (!$setuju) kembaliDenganError('Centang persetujuan sebelum mengirim pesanan.');
if (!in_array($penyerahan, ['antar_jemput', 'mandiri'], true)) kembaliDenganError('Pilih metode penyerahan yang tersedia.');

try {
    $kategoriData = kategoriHarga($kategori);
    $layananData = layananHarga($layanan);
} catch (InvalidArgumentException $ex) {
    kembaliDenganError($ex->getMessage());
}

$tanggalJemput = input('tanggal_jemput');
$waktuJemput = input('waktu_jemput');
$tanggalAntar = input('tanggal_antar');
$waktuAntar = input('waktu_antar');

if ($penyerahan === 'antar_jemput') {
    // Aturan sama dengan menu Jadwal petugas: antar tidak sebelum jemput, hari & jam operasional.
    if ($err = validasiPasanganJadwal($tanggalJemput, $waktuJemput, $tanggalAntar, $waktuAntar)) kembaliDenganError($err);
    if ($tanggalJemput < date('Y-m-d')) kembaliDenganError('Tanggal jemput tidak boleh di masa lalu.');
    // Jemput hari ini harus di jam yang belum lewat.
    if ($tanggalJemput === date('Y-m-d') && substr($waktuJemput, 0, 5) <= date('H:i')) {
        kembaliDenganError('Waktu jemput hari ini sudah lewat. Pilih jam yang lebih akhir atau tanggal lain.');
    }
} else {
    $tanggalJemput = $waktuJemput = $tanggalAntar = $waktuAntar = null;
}

/*
 * Perhitungan estimasi dilakukan di PHP, bukan dipercaya dari JavaScript.
 * Tarif pakaian adalah per kg estimasi; petugas harus mengonfirmasi berat aktual.
 */
$tarifDasar = (float)$kategoriData['tarif'];
$hitung = hitungBiaya((float)$jumlah, $tarifDasar, (float)$layananData['pengali'], $penyerahan, promoBerlaku(date('Y-m-d')));
$biayaLaundry = $hitung['subtotal'];
$biayaAdmin = $hitung['admin'];
$diskon = $hitung['diskon'];
$namaPromo = $hitung['nama_promo'];
$totalSementara = $hitung['total'];

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Pesanan selalu terhubung ke pelanggan yang sedang login.
    $idPelanggan = pelangganId();
    $stmt = $pdo->prepare('SELECT nama,no_hp,alamat FROM pelanggan WHERE id_pelanggan=?');
    $stmt->execute([$idPelanggan]); $pemilik = $stmt->fetch();
    if (!$pemilik || $pemilik['no_hp'] !== $noHp || $pemilik['nama'] !== $nama || $pemilik['alamat'] !== $alamat) {
        throw new RuntimeException('Data akun tidak cocok.');
    }

    $stmt = $pdo->prepare('SELECT id_layanan FROM layanan WHERE kode = ?');
    $stmt->execute([$layanan]);
    $idLayanan = $stmt->fetchColumn();
    if (!$idLayanan) throw new RuntimeException('Data layanan belum tersedia. Impor database.sql terlebih dahulu.');

    $kodePesanan = 'WSH-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $stmt = $pdo->prepare('INSERT INTO pesanan
        (kode_pesanan, id_pelanggan, id_layanan, metode_penyerahan, tanggal_jemput, waktu_jemput,
         tanggal_antar, waktu_antar, biaya_admin, diskon, nama_promo, catatan, biaya_laundry, status_pesanan, status_pembayaran)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "menunggu_konfirmasi", "belum_ada_tagihan")');
    $stmt->execute([$kodePesanan, $idPelanggan, $idLayanan, $penyerahan, $tanggalJemput, $waktuJemput,
        $tanggalAntar, $waktuAntar, $biayaAdmin, $diskon, $namaPromo, $catatan ?: null, $biayaLaundry]);
    $idPesanan = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT id_kategori FROM kategori_barang WHERE kode = ?');
    $stmt->execute([$kategori]);
    $idKategori = $stmt->fetchColumn();
    if (!$idKategori) throw new RuntimeException('Data kategori belum tersedia. Impor database.sql terlebih dahulu.');

    $stmt = $pdo->prepare('INSERT INTO detail_pesanan
        (id_pesanan, id_kategori, jumlah, satuan, tarif_satuan, subtotal_estimasi)
        VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$idPesanan, $idKategori, $jumlah, $kategoriData['satuan'], $tarifDasar, $biayaLaundry]);

    $pdo->commit();
    unset($_SESSION['csrf_token']);
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Washie order error: ' . $ex->getMessage());
    kembaliDenganError('Pesanan belum tersimpan. Silakan coba lagi beberapa saat lagi. Jika masalah berlanjut, pastikan database sudah diimpor dan config.php benar.');
}

$pageTitle = 'Pesanan berhasil dibuat';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel result-panel">
  <div class="success-mark">✓</div><span class="eyebrow">PESANAN DITERIMA</span>
  <h1>Terima kasih, <?= e($nama) ?>!</h1>
  <p>Pesananmu sudah kami terima. Simpan kode berikut untuk memantau status pesananmu.</p>
  <div class="order-code"><small>Kode pesanan</small><strong><?= e($kodePesanan) ?></strong></div>
  <div class="summary-box result-summary">
    <div><span>Kategori</span><strong><?= e($kategoriData['nama']) ?> · <?= (int)$jumlah ?> <?= e($kategoriData['satuan']) ?></strong></div>
    <div><span>Layanan</span><strong><?= e($layananData['nama']) ?></strong></div>
    <div><span>Penyerahan</span><strong><?= $penyerahan === 'antar_jemput' ? 'Antar-jemput' : 'Mandiri / bawa sendiri' ?></strong></div>
    <div><span>Biaya laundry estimasi</span><strong><?= rupiah($biayaLaundry) ?></strong></div>
    <div><span>Biaya admin</span><strong><?= rupiah($biayaAdmin) ?></strong></div>
<?php if ($diskon > 0): ?>    <div><span>Diskon<?= $namaPromo ? ' (' . e($namaPromo) . ')' : '' ?></span><strong>−<?= rupiah($diskon) ?></strong></div>
<?php endif; ?>    <div class="summary-total"><span>Total sementara</span><strong><?= rupiah($totalSementara) ?></strong></div>
  </div>
  <p class="notice">Total ini masih perkiraan. Petugas akan memeriksa barang dan menimbang berat aktual sebelum tagihan final dikirim. Kamu bisa memantau progresnya kapan saja lewat menu Pesanan Saya dengan kode di atas.</p>
  <div class="hero-actions"><a class="button button-primary" href="pesanan-saya.php?kode=<?= urlencode($kodePesanan) ?>">Lihat pesanan</a><a class="button button-quiet" href="index.php">Buat pesanan lain</a></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
