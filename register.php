<?php
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/koneksi.php';
wajibTamu();

$old    = ['username' => '', 'email' => '', 'alamat' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['username'] = trim($_POST['username'] ?? '');
    $old['email']    = trim($_POST['email'] ?? '');
    $old['alamat']   = trim($_POST['alamat'] ?? '');
    $password        = $_POST['password'] ?? '';

    // [PHP] Validasi kelengkapan & format
    if ($old['username'] === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]{4,30}$/', $old['username'])) {
        $errors[] = 'Username 4-30 karakter, hanya huruf, angka, titik, dan underscore.';
    }

    if ($old['email'] === '') {
        $errors[] = 'Email wajib diisi.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($old['alamat'] === '') {
        $errors[] = 'Alamat wajib diisi.';
    } elseif (strlen($old['alamat']) < 10) {
        $errors[] = 'Alamat minimal 10 karakter.';
    }

    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }

    // [PHP] Cek username / email sudah dipakai
    if (count($errors) === 0) {
        $cek = $pdo->prepare('SELECT username, email FROM pelanggan WHERE username = ? OR email = ?');
        $cek->execute([$old['username'], $old['email']]);
        foreach ($cek->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (strcasecmp($row['username'] ?? '', $old['username']) === 0) {
                $errors[] = 'Username sudah dipakai.';
            }
            if (strcasecmp($row['email'], $old['email']) === 0) {
                $errors[] = 'Email sudah terdaftar. Silakan masuk.';
            }
        }
        $errors = array_unique($errors);
    }

    // [PHP] Simpan akun (password di-hash, role selalu pelanggan)
    if (count($errors) === 0) {
        $pdo->prepare('INSERT INTO pelanggan (username, email, alamat, password, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([$old['username'], $old['email'], $old['alamat'], password_hash($password, PASSWORD_DEFAULT), 'pelanggan']);

        $_SESSION['sukses'] = 'Registrasi berhasil! Silakan masuk dengan akun kamu.';
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar | Washie Laundry</title>
  <link rel="icon" type="image/svg+xml" href="assets/logo.svg">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/auth.js" defer></script>
</head>
<body>
<main class="page-shell auth-shell">
  <section class="auth-wrap">
    <div class="auth-card">
      <img src="assets/logo.svg" width="58" alt="Logo Washie">
      <span class="eyebrow">BUAT AKUN BARU</span>
      <h1>Daftar pelanggan</h1>
      <p>Daftar dulu untuk mulai memesan laundry.</p>

      <?php if (count($errors) > 0): ?>
        <div class="notice notice-error" id="errorServer">
          <p class="notice-title">Data belum valid</p>
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" action="register.php" id="authForm" data-mode="register" novalidate>
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" placeholder="Minimal 4 karakter" value="<?= e($old['username']) ?>" autocomplete="username">
          <small class="field-error" data-for="username"></small>
        </div>
        <div class="field">
          <label for="email">Gmail</label>
          <input id="email" name="email" type="email" placeholder="nama@gmail.com" value="<?= e($old['email']) ?>" autocomplete="email">
          <small class="field-error" data-for="email"></small>
        </div>
        <div class="field">
          <label for="alamat">Alamat</label>
          <textarea id="alamat" name="alamat" rows="3" placeholder="Nama jalan, nomor rumah, patokan"><?= e($old['alamat']) ?></textarea>
          <small class="field-error" data-for="alamat"></small>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="Minimal 6 karakter" autocomplete="new-password">
          <small class="field-error" data-for="password"></small>
        </div>
        <button type="submit" class="button button-primary button-wide">Daftar akun</button>
      </form>

      <p class="auth-switch">Sudah memiliki akun? <a href="login.php"><strong>Masuk sekarang</strong></a></p>
    </div>
  </section>
</main>
</body>
</html>
