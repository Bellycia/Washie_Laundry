<?php
require_once __DIR__ . '/../config.php';
roleWajib('pelanggan');

$st = db()->prepare(
    'SELECT p.nama, p.no_hp, p.alamat, a.username, a.role, a.dibuat_pada
     FROM akun a
     LEFT JOIN pelanggan p ON p.id_pelanggan = a.id_pelanggan
     WHERE a.id_akun = ?'
);
$st->execute([akunAktif()['id_akun']]);
$profil = $st->fetch();

$pageTitle = 'Profil Saya';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro">
  <span class="eyebrow">AKUN SAYA</span>
  <h1>Profil</h1>
  <p>Informasi akun yang terdaftar di Washie Laundry.</p>
</section>

<section class="panel profile-panel">
  <img class="profile-img" src="../assets/profil.svg" alt="Foto profil pelanggan" width="96">
  <div>
    <h2><?= e($profil['nama'] ?? 'Pelanggan') ?></h2>
    <p>Kelola informasi akun dan keamanan login.</p>
  </div>
</section>

<section class="panel">
  <h2>Informasi pribadi</h2>
  <div class="detail-list">
    <div><strong>Status akun</strong><span class="status-pill"><?= e(ucfirst($profil['role'])) ?></span></div>
    <div><strong>Username</strong><span><?= e($profil['username']) ?></span></div>
    <div><strong>Password</strong><span>••••••••</span></div>
    <div><strong>Nama lengkap</strong><span><?= e($profil['nama'] ?? '-') ?></span></div>
    <div><strong>Nomor HP</strong><span><?= e($profil['no_hp'] ?? '-') ?></span></div>
    <div><strong>Alamat</strong><span><?= e($profil['alamat'] ?? '-') ?></span></div>
    <div><strong>Terdaftar sejak</strong><span><?= e(date('d-m-Y', strtotime($profil['dibuat_pada']))) ?></span></div>
  </div>
  <p class="notice">Untuk menjaga keamanan, perubahan data pesanan hanya dapat dilakukan oleh petugas. Pelanggan memiliki akses baca terhadap pesanan.</p>
  <a class="button button-quiet" href="../logout.php">Keluar dari akun</a>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
