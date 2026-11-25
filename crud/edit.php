<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');
// Form edit lama tidak menerapkan aturan harga/pembayaran. Gunakan form edit petugas.
header('Location: petugas/edit_pesanan.php?id=' . (int)($_GET['id'] ?? 0));
exit;
