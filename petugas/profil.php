<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$st = db()->prepare('SELECT username, role FROM akun WHERE id_akun = ?');
$st->execute([akunAktif()['id_akun']]);
$akun = $st->fetch();

$judul = 'Profil';
require '_header.php';
?>
<section class="page-intro">
  <h1>Profil</h1>
  <p>Akun petugas yang sedang digunakan.</p>
</section>

<section class="panel profile-admin">
  <img class="profile-img" src="../assets/profil.svg" alt="Foto profil petugas">
  <div>
    <h2><?= e($akun['username'] ?? '-') ?></h2>
    <p>Petugas Washie Laundry</p>
  </div>
</section>

<section class="panel">
  <h2>Informasi akun</h2>
  <div class="detail-list">
    <div><strong>Status akun</strong><span class="status-pill">Petugas</span></div>
    <div><strong>Username</strong><span><?= e($akun['username'] ?? '-') ?></span></div>
    <div><strong>Password</strong><span>••••••••</span></div>
  </div>
  <a class="button button-quiet" href="../logout.php">Keluar dari akun</a>
</section>
<?php require '_footer.php'; ?>
