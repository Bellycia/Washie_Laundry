-- Opsional: semua tabel sudah dibuat otomatis oleh config/koneksi.php
-- Akun admin bawaan juga dibuat otomatis: username admin / email admin@washie.com / password admin123
CREATE DATABASE IF NOT EXISTS washie_laundry CHARACTER SET utf8mb4;
USE washie_laundry;

CREATE TABLE IF NOT EXISTS pelanggan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(15) NULL,
    alamat TEXT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'pelanggan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pesanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    kode VARCHAR(30) NOT NULL DEFAULT '',
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(13) NOT NULL,
    alamat TEXT NULL,
    kategori VARCHAR(50) NOT NULL,
    jumlah VARCHAR(30) NOT NULL,
    layanan VARCHAR(50) NOT NULL,
    penyerahan VARCHAR(30) NOT NULL,
    subtotal INT NOT NULL,
    diskon INT NOT NULL DEFAULT 0,
    antar INT NOT NULL DEFAULT 0,
    total INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Menunggu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (status)
);
