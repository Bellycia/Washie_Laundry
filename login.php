<?php
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/koneksi.php';
wajibTamu();

$identitas = '';
$errors    = [];

// [PHP] Notifikasi sukses dari halaman registrasi (tampil sekali)
$sukses = $_SESSION['sukses'] ?? null;
unset($_SESSION['sukses']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identitas = trim($_POST['identitas'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($identitas === '') {
        $errors[] = 'Username atau email wajib diisi.';
    }
    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    }

    if (count($errors) === 0) {
        // [PHP] Bisa masuk dengan username ATAU email
        $stmt = $pdo->prepare('SELECT id, username, password, role FROM pelanggan WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identitas, $identitas]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['username'];
            $_SESSION['role']    = $user['role'];
            // admin -> dashboard admin, selain itu -> dashboard pelanggan
            header('Location: ' . halamanAwal());
            exit;
        }
        $errors[] = 'Username/email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk | Washie Laundry</title>
  <link rel="icon" type="image/svg+xml" href="assets/logo.svg">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/auth.js" defer></script>
</head>
<body>
<main class="page-shell auth-shell">
  <section class="auth-wrap">
    <div class="auth-card">
      <img src="assets/logo.svg" width="58" alt="Logo Washie">
      <span class="eyebrow">SELAMAT DATANG</span>
      <h1>Masuk ke Washie</h1>
      <p>Masuk dengan username atau email akun kamu.</p>

      <?php if ($sukses): ?>
        <div class="notice notice-success" id="notifSukses" role="alert">✔ <?= e($sukses) ?></div>
      <?php endif; ?>

      <?php if (count($errors) > 0): ?>
        <div class="notice notice-error" id="errorServer">
          <p class="notice-title">Gagal masuk</p>
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" action="login.php" id="authForm" data-mode="login" novalidate>
        <div class="field">
          <label for="identitas">Username / Email</label>
          <input id="identitas" name="identitas" type="text" placeholder="Username atau email" value="<?= e($identitas) ?>" autocomplete="username">
          <small class="field-error" data-for="identitas"></small>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="Password kamu" autocomplete="current-password">
          <small class="field-error" data-for="password"></small>
        </div>
        <button type="submit" class="button button-primary button-wide">Masuk</button>
      </form>

      <p class="auth-switch">Belum punya akun? <a href="register.php"><strong>Daftar sekarang</strong></a></p>
    </div>
  </section>
</main>
</body>
</html>
