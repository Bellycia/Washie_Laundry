<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');
header('Location: petugas/pesanan.php');
exit;
