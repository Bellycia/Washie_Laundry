<?php
require_once 'config.php';
if (!empty($_SESSION['user'])) { header('Location: '.(($_SESSION['user']['role'] ?? '')==='petugas' ? 'petugas/index.php' : 'beranda.php')); exit; }

$errors = [];
$nama = $hp = $alamat = $username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nama === '' || !preg_match("/^[\\p{L}][\\p{L} .'-]{1,79}$/u", $nama)) {
        $errors['nama'] = 'Nama wajib diisi minimal 2 karakter dan hanya boleh berisi huruf, spasi, titik, apostrof, atau tanda hubung.';
    }
    if (!preg_match('/^[0-9]{10,15}$/', $hp)) {
        $errors['no_hp'] = 'Nomor HP harus berupa angka saja, tanpa spasi atau tanda +, dengan panjang 10–15 digit.';
    }
    if ($alamat === '' || mb_strlen($alamat) < 5) {
        $errors['alamat'] = 'Alamat wajib diisi minimal 5 karakter.';
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]{4,50}$/', $username)) {
        $errors['username'] = 'Username harus 4–50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password minimal 8 karakter dan harus mengandung setidaknya 1 huruf serta 1 angka.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $st = $pdo->prepare('INSERT INTO pelanggan(nama,no_hp,alamat) VALUES(?,?,?)');
            $st->execute([$nama, $hp, $alamat]);
            $id = (int)$pdo->lastInsertId();
            $st = $pdo->prepare('INSERT INTO user(id_pelanggan,username,password_hash,role) VALUES(?,?,?,"pelanggan")');
            $st->execute([$id, $username, password_hash($password, PASSWORD_DEFAULT)]);
            $pdo->commit();
            header('Location: login.php?daftar=berhasil');
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors['umum'] = 'Registrasi gagal. Username mungkin sudah digunakan atau nomor HP sudah terdaftar. Periksa kembali lalu coba lagi.';
        }
    }
}
$pageTitle = 'Registrasi';
require 'includes/auth_header.php';
?>
<section class="auth-wrap"><div class="auth-card">
<img src="assets/logo.svg" width="58" alt="Logo Washie"><span class="eyebrow">BUAT AKUN BARU</span><h1>Registrasi pelanggan</h1>
<?php if (!empty($errors['umum'])): ?><p class="notice notice-error" role="alert"><?=e($errors['umum'])?></p><?php endif; ?>
<form method="post" action="register.php" novalidate>
<div class="field"><label for="nama">Nama lengkap</label><input id="nama" name="nama" value="<?=e($nama)?>" placeholder="Nama sesuai identitas" minlength="2" maxlength="80" pattern="[\p{L}][\p{L} .'-]{1,79}" required autocomplete="name" aria-describedby="nama-help nama-error"><small id="nama-help">Gunakan huruf dan spasi; tanda titik, apostrof, atau hubung diperbolehkan.</small><?php if(isset($errors['nama'])): ?><small class="notice-error" id="nama-error" role="alert"><?=e($errors['nama'])?></small><?php endif; ?></div>
<div class="field"><label for="no_hp">Nomor HP</label><input id="no_hp" name="no_hp" type="tel" inputmode="numeric" value="<?=e($hp)?>" placeholder="08xxxxxxxxxx" minlength="10" maxlength="15" pattern="[0-9]{10,15}" required autocomplete="tel" aria-describedby="hp-help hp-error"><small id="hp-help">Angka saja, 10–15 digit. Jangan gunakan spasi atau tanda +.</small><?php if(isset($errors['no_hp'])): ?><small class="notice-error" id="hp-error" role="alert"><?=e($errors['no_hp'])?></small><?php endif; ?></div>
<div class="field"><label for="alamat">Alamat lengkap</label><textarea id="alamat" name="alamat" placeholder="Alamat tempat tinggal" minlength="5" required autocomplete="street-address" aria-describedby="alamat-error"><?=e($alamat)?></textarea><?php if(isset($errors['alamat'])): ?><small class="notice-error" id="alamat-error" role="alert"><?=e($errors['alamat'])?></small><?php endif; ?></div>
<div class="field"><label for="username">Username</label><input id="username" name="username" value="<?=e($username)?>" placeholder="4–50 karakter" minlength="4" maxlength="50" pattern="[a-zA-Z0-9_.-]{4,50}" required autocomplete="username" aria-describedby="username-help username-error"><small id="username-help">Huruf, angka, titik, garis bawah, atau tanda hubung.</small><?php if(isset($errors['username'])): ?><small class="notice-error" id="username-error" role="alert"><?=e($errors['username'])?></small><?php endif; ?></div>
<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" placeholder="Minimal 8 karakter, berisi huruf dan angka" required autocomplete="new-password" aria-describedby="password-help password-error"><small id="password-help">Minimal 8 karakter dan wajib mengandung huruf serta angka.</small><?php if(isset($errors['password'])): ?><small class="notice-error" id="password-error" role="alert"><?=e($errors['password'])?></small><?php endif; ?></div>
<button class="button button-primary button-wide" type="submit">Daftar akun</button></form>
<p class="form-footnote">Sudah punya akun? <a href="login.php">Login</a></p></div></section>
<script>
document.querySelector('form').addEventListener('submit', function(event) {
  const phone = document.getElementById('no_hp');
  phone.setCustomValidity(/^[0-9]{10,15}$/.test(phone.value) ? '' : 'Nomor HP harus berupa angka saja dan terdiri dari 10–15 digit.');
  if (!this.checkValidity()) { event.preventDefault(); this.reportValidity(); }
});
document.getElementById('no_hp').addEventListener('input', function(){ this.setCustomValidity(''); });
</script>
<?php require 'includes/footer.php'; ?>
