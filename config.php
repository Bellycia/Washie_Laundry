<?php
declare(strict_types=1);

/*
 * Konfigurasi Washie Laundry.
 * Sesuaikan kredensial dengan MySQL/MariaDB lokal Anda.
 */
const DB_HOST = 'localhost';
const DB_NAME = 'washie_laundry';
const DB_USER = 'root';
const DB_PASS = '';

// Batam menggunakan WIB. Tanpa ini, PHP memakai UTC sehingga validasi tanggal/jam bisa meleset.
date_default_timezone_set('Asia/Jakarta');

// Sesi dimulai di sini agar semua halaman (termasuk pembayaran.php) bisa memakai $_SESSION.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Jam operasional: Senin-Sabtu, 08.00-20.00 WIB (angka hari mengikuti date('N'): 1=Senin ... 7=Minggu).
const JAM_BUKA = '08:00';
const JAM_TUTUP = '20:00';
const HARI_BUKA = [1, 2, 3, 4, 5, 6];
const NAMA_HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    pastikanSkema($pdo);
    return $pdo;
}

/**
 * Menambahkan kolom diskon/nama_promo pada tabel pesanan bila database lama belum memilikinya,
 * sehingga tidak perlu impor ulang database.sql.
 */
function pastikanSkema(PDO $pdo): void {
    $ada = $pdo->query("SHOW COLUMNS FROM pesanan LIKE 'diskon'")->fetch();
    if (!$ada) {
        $pdo->exec("ALTER TABLE pesanan
            ADD COLUMN diskon DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER biaya_admin,
            ADD COLUMN nama_promo VARCHAR(100) NULL AFTER diskon");
    }
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function rupiah(float|int $amount): string {
    return 'Rp' . number_format((float)$amount, 0, ',', '.');
}

function layananHarga(string $layanan): array {
    $st = db()->prepare('SELECT nama, pengali_harga AS pengali FROM layanan WHERE kode=? LIMIT 1');
    $st->execute([$layanan]); $row=$st->fetch();
    if (!$row) throw new InvalidArgumentException('Jenis layanan tidak valid.');
    return $row;
}

function kategoriHarga(string $kategori): array {
    $st = db()->prepare('SELECT nama, tarif_dasar AS tarif, satuan FROM kategori_barang WHERE kode=? LIMIT 1');
    $st->execute([$kategori]); $row=$st->fetch();
    if (!$row) throw new InvalidArgumentException('Kategori barang tidak valid.');
    return $row;
}

/**
 * Cek apakah tanggal (Y-m-d) jatuh pada hari operasional.
 */
function hariBuka(string $tanggal): bool {
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $d !== false && in_array((int)$d->format('N'), HARI_BUKA, true);
}

/**
 * Cek apakah waktu (H:i atau H:i:s) berada di dalam jam operasional (batas buka & tutup ikut dihitung).
 * Perbandingan string aman karena format jam selalu 24 jam dengan nol di depan.
 */
function jamOperasional(string $waktu): bool {
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $waktu)) return false;
    $hm = substr($waktu, 0, 5);
    return $hm >= JAM_BUKA && $hm <= JAM_TUTUP;
}

/**
 * Validasi satu jadwal (jemput atau antar). Mengembalikan pesan error, atau null jika valid.
 */
function validasiJadwal(string $label, string $tanggal, string $waktu): ?string {
    if (!hariBuka($tanggal)) {
        return "Tanggal $label jatuh pada hari libur kami. Kami buka Senin–Sabtu, silakan pilih tanggal lain.";
    }
    if (!jamOperasional($waktu)) {
        return "Waktu $label di luar jam operasional. Pilih jam antara " . str_replace(':', '.', JAM_BUKA) . " dan " . str_replace(':', '.', JAM_TUTUP) . " WIB.";
    }
    return null;
}


/**
 * Path dasar aplikasi (mis. /tugas02_washie_laundry) supaya redirect
 * tetap benar baik dari folder utama maupun dari folder petugas/.
 */
function basePath(): string {
    $root = rtrim(str_replace('\\', '/', (string)realpath(__DIR__)), '/');
    $doc  = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    if ($doc !== '' && str_starts_with($root, $doc)) {
        return substr($root, strlen($doc));
    }
    return '';
}

function appUrl(string $path): string {
    return basePath() . '/' . ltrim($path, '/');
}

function loginWajib(): void {
    if (empty($_SESSION['akun'])) {
        header('Location: ' . appUrl('login.php'));
        exit;
    }
}

function roleWajib(string $role): void {
    loginWajib();
    $aktif = $_SESSION['akun']['role'] ?? '';
    if ($aktif !== $role) {
        header('Location: ' . appUrl($aktif === 'petugas' ? 'petugas/index.php' : 'beranda.php'));
        exit;
    }
}

function pelangganId(): int {
    return (int)($_SESSION['akun']['id_pelanggan'] ?? 0);
}

function akunAktif(): array {
    return $_SESSION['akun'] ?? [];
}

/** Ubah nilai enum (menunggu_konfirmasi) menjadi teks rapi (Menunggu konfirmasi). */
function labelStatus(?string $nilai): string {
    return ucfirst(str_replace('_', ' ', (string)$nilai));
}


/**
 * Validasi pasangan jadwal jemput & antar (dipakai form pelanggan dan menu Jadwal petugas).
 * Mengembalikan pesan error atau null jika valid.
 */
function validasiPasanganJadwal(string $tglJemput, string $wktJemput, string $tglAntar, string $wktAntar): ?string {
    $valid = static function (string $t): bool {
        $d = DateTime::createFromFormat('Y-m-d', $t);
        return $d !== false && $d->format('Y-m-d') === $t;
    };
    if (!$valid($tglJemput) || !$valid($tglAntar) || $wktJemput === '' || $wktAntar === '') {
        return 'Lengkapi tanggal dan jam jemput serta antar.';
    }
    if ($tglAntar < $tglJemput) return 'Tanggal antar tidak boleh sebelum tanggal jemput.';
    if ($err = validasiJadwal('jemput', $tglJemput, $wktJemput)) return $err;
    if ($err = validasiJadwal('antar', $tglAntar, $wktAntar)) return $err;
    if ($tglAntar === $tglJemput && substr($wktAntar, 0, 5) <= substr($wktJemput, 0, 5)) {
        return 'Jam antar harus setelah jam jemput pada hari yang sama.';
    }
    return null;
}

const BIAYA_ADMIN = 10000;

/** Ubah input rupiah bebas ("Rp 12.500", "12500") menjadi angka. Null jika tidak valid. */
function parseRupiah(string $teks): ?float {
    $angka = preg_replace('/\D+/', '', $teks);
    return $angka === '' ? null : (float)$angka;
}

/** Promo yang berlaku pada tanggal tertentu dan aktif. */
function promoBerlaku(string $tanggal): array {
    $st = db()->prepare('SELECT * FROM promo WHERE aktif = 1 AND mulai <= ? AND berakhir >= ? ORDER BY nama');
    $st->execute([$tanggal, $tanggal]);
    return $st->fetchAll();
}

function nilaiDiskon(array $promo, float $subtotal): float {
    if ($subtotal < (float)$promo['minimal_belanja']) return 0.0;
    $d = $promo['jenis_diskon'] === 'persen'
        ? round($subtotal * min(100, (float)$promo['nilai_diskon']) / 100)
        : (float)$promo['nilai_diskon'];
    return (float)min($subtotal, $d);
}

/** Pilih promo dengan potongan terbesar dari daftar yang diberikan. Mengembalikan [promo|null, diskon]. */
function promoTerbaik(array $daftar, float $subtotal): array {
    $best = null; $nilai = 0.0;
    foreach ($daftar as $p) {
        $d = nilaiDiskon($p, $subtotal);
        if ($d > $nilai) { $best = $p; $nilai = $d; }
    }
    return [$best, $nilai];
}

/**
 * Hitung rincian biaya: subtotal = jumlah/berat x tarif x pengali; total = subtotal - diskon + admin.
 * Admin hanya untuk antar-jemput. Promo bisa dipaksa lewat $promo (petugas); null = pilih otomatis.
 */
function hitungBiaya(float $jumlah, float $tarif, float $pengali, string $metode, array $promoKandidat, ?array $promo = null): array {
    $subtotal = (float)round($jumlah * $tarif * $pengali);
    $admin = $metode === 'antar_jemput' ? (float)BIAYA_ADMIN : 0.0;
    if ($promo !== null) { $diskon = nilaiDiskon($promo, $subtotal); }
    else { [$promo, $diskon] = promoTerbaik($promoKandidat, $subtotal); }
    return [
        'subtotal' => $subtotal, 'admin' => $admin, 'diskon' => $diskon,
        'promo' => $promo, 'nama_promo' => $promo['nama'] ?? null,
        'total' => max(0.0, $subtotal - $diskon + $admin),
    ];
}

/**
 * Rincian biaya sebuah baris pesanan.
 * - Sudah dikonfirmasi petugas (total_final terisi): memakai nilai tersimpan (tagihan tidak berubah sendiri).
 * - Belum dikonfirmasi: dihitung ulang dari tarif, pengali, dan promo TERBARU.
 * Baris harus memuat: total_final, biaya_laundry, biaya_admin, diskon, nama_promo, metode_penyerahan,
 * dibuat_pada, jumlah, tarif_dasar, pengali_harga.
 */
function rincianPesanan(array $r): array {
    if ($r['total_final'] !== null) {
        return ['final' => true, 'subtotal' => (float)$r['biaya_laundry'], 'admin' => (float)$r['biaya_admin'],
                'diskon' => (float)($r['diskon'] ?? 0), 'nama_promo' => $r['nama_promo'] ?? null, 'total' => (float)$r['total_final']];
    }
    $h = hitungBiaya((float)$r['jumlah'], (float)$r['tarif_dasar'], (float)$r['pengali_harga'],
                     $r['metode_penyerahan'], promoBerlaku(date('Y-m-d', strtotime((string)$r['dibuat_pada']))));
    return ['final' => false, 'subtotal' => $h['subtotal'], 'admin' => $h['admin'], 'diskon' => $h['diskon'],
            'nama_promo' => $h['nama_promo'], 'total' => $h['total']];
}

/** Nilai berat/jumlah yang ditampilkan: aktual petugas bila ada, selain itu input pelanggan. */
function labelBerat(array $r): string {
    $angka = static fn($v) => rtrim(rtrim(number_format((float)$v, 2, ',', '.'), '0'), ',');
    if ($r['berat_kg'] !== null) return $angka($r['berat_kg']) . ' ' . ($r['satuan'] ?? 'kg') . ' (aktual)';
    return $angka($r['jumlah']) . ' ' . ($r['satuan'] ?? '') . ' (dari pelanggan)';
}
