CREATE DATABASE IF NOT EXISTS washie_laundry
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE washie_laundry;

CREATE TABLE IF NOT EXISTS pelanggan (
    id_pelanggan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL UNIQUE,
    alamat TEXT NOT NULL,
    email VARCHAR(120) NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS akun (
    id_akun INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan INT UNSIGNED NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('pelanggan','petugas') NOT NULL DEFAULT 'pelanggan',
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_akun_pelanggan FOREIGN KEY (id_pelanggan)
      REFERENCES pelanggan(id_pelanggan) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS layanan (
    id_layanan TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(20) NOT NULL UNIQUE,
    nama VARCHAR(30) NOT NULL,
    pengali_harga DECIMAL(5,2) NOT NULL,
    estimasi VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kategori_barang (
    id_kategori TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(20) NOT NULL UNIQUE,
    nama VARCHAR(30) NOT NULL,
    tarif_dasar DECIMAL(12,2) NOT NULL,
    satuan VARCHAR(20) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pesanan (
    id_pesanan BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_pesanan VARCHAR(30) NOT NULL UNIQUE,
    id_pelanggan INT UNSIGNED NOT NULL,
    id_layanan TINYINT UNSIGNED NOT NULL,
    metode_penyerahan ENUM('antar_jemput','mandiri') NOT NULL,
    tanggal_jemput DATE NULL,
    waktu_jemput TIME NULL,
    tanggal_antar DATE NULL,
    waktu_antar TIME NULL,
    biaya_admin DECIMAL(12,2) NOT NULL DEFAULT 0,
    diskon DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Potongan promo',
    nama_promo VARCHAR(100) NULL,
    catatan TEXT NULL,
    berat_kg DECIMAL(7,2) NULL COMMENT 'Diisi petugas setelah penimbangan',
    biaya_laundry DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Estimasi berdasarkan jumlah barang',
    total_final DECIMAL(12,2) NULL COMMENT 'Diisi setelah berat dikonfirmasi',
    status_pesanan ENUM('menunggu_konfirmasi','menunggu_pembayaran','diproses','siap_diantar','selesai','dibatalkan')
        NOT NULL DEFAULT 'menunggu_konfirmasi',
    status_pembayaran ENUM('belum_ada_tagihan','menunggu_pembayaran','menunggu_verifikasi','lunas','ditolak')
        NOT NULL DEFAULT 'belum_ada_tagihan',
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pesanan_pelanggan FOREIGN KEY (id_pelanggan)
        REFERENCES pelanggan(id_pelanggan) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pesanan_layanan FOREIGN KEY (id_layanan)
        REFERENCES layanan(id_layanan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_pesanan (
    id_detail BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pesanan BIGINT UNSIGNED NOT NULL,
    id_kategori TINYINT UNSIGNED NOT NULL,
    jumlah INT UNSIGNED NOT NULL,
    satuan VARCHAR(20) NOT NULL,
    tarif_satuan DECIMAL(12,2) NOT NULL,
    subtotal_estimasi DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_detail_pesanan FOREIGN KEY (id_pesanan)
        REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detail_kategori FOREIGN KEY (id_kategori)
        REFERENCES kategori_barang(id_kategori) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembayaran (
    id_pembayaran BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pesanan BIGINT UNSIGNED NOT NULL,
    metode ENUM('transfer_bank','ewallet','qris','tunai') NOT NULL,
    jumlah_tagihan DECIMAL(12,2) NOT NULL,
    status ENUM('menunggu_pembayaran','menunggu_verifikasi','lunas','ditolak') NOT NULL DEFAULT 'menunggu_pembayaran',
    referensi VARCHAR(100) NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pembayaran_pesanan FOREIGN KEY (id_pesanan)
        REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS promo (
    id_promo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    deskripsi TEXT NOT NULL,
    mulai DATE NOT NULL,
    berakhir DATE NOT NULL,
    jenis_diskon ENUM('persen','nominal') NOT NULL,
    nilai_diskon DECIMAL(12,2) NOT NULL,
    minimal_belanja DECIMAL(12,2) NOT NULL DEFAULT 0,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO layanan (kode, nama, pengali_harga, estimasi) VALUES
('xpress', 'Xpress', 1.80, '1 hari kerja'),
('reguler', 'Reguler', 1.00, '2–3 hari kerja'),
('hemat', 'Hemat', 0.80, '4–5 hari kerja');

INSERT IGNORE INTO kategori_barang (kode, nama, tarif_dasar, satuan) VALUES
('pakaian', 'Pakaian', 8000, 'kg'),
('sepatu', 'Sepatu', 25000, 'pasang'),
('tas', 'Tas', 30000, 'buah');

-- Data contoh untuk pengujian. Aman diimpor berulang kali (tidak membuat data ganda).
INSERT IGNORE INTO pelanggan (nama, no_hp, alamat, email) VALUES
('Alya Putri', '081234567801', 'Batam Center', 'alya@example.test'),
('Bima Pratama', '081234567802', 'Batu Aji', 'bima@example.test'),
('Citra Lestari', '081234567803', 'Sekupang', 'citra@example.test');

-- Pesanan contoh 1: baru masuk, menunggu petugas menimbang (belum ada tagihan).
INSERT IGNORE INTO pesanan
 (kode_pesanan, id_pelanggan, id_layanan, metode_penyerahan, tanggal_jemput, waktu_jemput, tanggal_antar, waktu_antar,
  biaya_admin, biaya_laundry, status_pesanan, status_pembayaran)
SELECT 'WSH-261010-AAA111', pl.id_pelanggan, l.id_layanan, 'antar_jemput', '2026-10-20', '09:00:00', '2026-10-22', '17:00:00',
       10000, 40000, 'menunggu_konfirmasi', 'belum_ada_tagihan'
FROM pelanggan pl, layanan l WHERE pl.no_hp = '081234567801' AND l.kode = 'reguler';

-- Pesanan contoh 2: sudah ditimbang, tagihan final tersedia (untuk mencoba halaman Pembayaran).
INSERT IGNORE INTO pesanan
 (kode_pesanan, id_pelanggan, id_layanan, metode_penyerahan, biaya_admin, biaya_laundry, berat_kg, total_final,
  status_pesanan, status_pembayaran)
SELECT 'WSH-261010-BBB222', pl.id_pelanggan, l.id_layanan, 'mandiri', 0, 48000, 6.50, 52000,
       'menunggu_pembayaran', 'menunggu_pembayaran'
FROM pelanggan pl, layanan l WHERE pl.no_hp = '081234567802' AND l.kode = 'reguler';

INSERT INTO detail_pesanan (id_pesanan, id_kategori, jumlah, satuan, tarif_satuan, subtotal_estimasi)
SELECT p.id_pesanan, k.id_kategori, 5, 'kg', 8000, 40000
FROM pesanan p, kategori_barang k
WHERE p.kode_pesanan = 'WSH-261010-AAA111' AND k.kode = 'pakaian'
  AND NOT EXISTS (SELECT 1 FROM detail_pesanan d WHERE d.id_pesanan = p.id_pesanan);

INSERT INTO detail_pesanan (id_pesanan, id_kategori, jumlah, satuan, tarif_satuan, subtotal_estimasi)
SELECT p.id_pesanan, k.id_kategori, 6, 'kg', 8000, 48000
FROM pesanan p, kategori_barang k
WHERE p.kode_pesanan = 'WSH-261010-BBB222' AND k.kode = 'pakaian'
  AND NOT EXISTS (SELECT 1 FROM detail_pesanan d WHERE d.id_pesanan = p.id_pesanan);

-- Contoh perintah SQL:
-- SELECT * FROM pesanan WHERE status_pesanan = 'menunggu_konfirmasi';
-- UPDATE pesanan SET catatan = 'Mohon hubungi sebelum menjemput' WHERE id_pesanan = 1;
-- DELETE FROM pesanan WHERE id_pesanan = 1; -- hati-hati: detail dan pembayaran ikut terhapus

-- Akun demo: password seluruh akun demo adalah password.
INSERT IGNORE INTO akun (id_pelanggan, username, password_hash, role)
SELECT id_pelanggan, 'alya', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pelanggan'
FROM pelanggan WHERE no_hp='081234567801';
INSERT IGNORE INTO akun (id_pelanggan, username, password_hash, role)
SELECT id_pelanggan, 'bima', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pelanggan'
FROM pelanggan WHERE no_hp='081234567802';
INSERT IGNORE INTO akun (username, password_hash, role)
VALUES ('petugas', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'petugas');
