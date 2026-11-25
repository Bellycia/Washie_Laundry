<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$st = db()->prepare('SELECT username, role FROM user WHERE id_user = ?');
$st->execute([userAktif()['id_user']]);
$user = $st->fetch();

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
    <h2><?= e($user['username'] ?? '-') ?></h2>
    <p>Petugas Washie Laundry</p>
  </div>
</section>

<section class="panel">
  <h2>Informasi akun</h2>
  <div class="detail-list">
    <div><strong>Status akun</strong><span class="status-pill">Petugas</span></div>
    <div><strong>Username</strong><span><?= e($user['username'] ?? '-') ?></span></div>
    <div><strong>Password</strong><span>••••••••</span></div>
  </div>
  <a class="button button-quiet" href="../logout.php">Keluar dari akun</a>
</section>
<?php require '_footer.php'; ?>
