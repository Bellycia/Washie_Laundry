<?php
require_once 'config.php';

// Sudah login: langsung arahkan sesuai role.
if (!empty($_SESSION['akun'])) {
    header('Location: ' . (($_SESSION['akun']['role'] ?? '') === 'petugas' ? 'petugas/index.php' : 'beranda.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare(
        'SELECT a.*, p.nama
         FROM akun a
         LEFT JOIN pelanggan p ON p.id_pelanggan = a.id_pelanggan
         WHERE a.username = ?
         LIMIT 1'
    );
    $stmt->execute([$username]);
    $akun = $stmt->fetch();

    if ($akun && password_verify($password, $akun['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['akun'] = [
            'id_akun'      => (int)$akun['id_akun'],
            'id_pelanggan' => (int)$akun['id_pelanggan'],
            'username'     => $akun['username'],
            'role'         => $akun['role'],
            'nama'         => $akun['nama'] ?? 'Petugas',
        ];
        header('Location: ' . ($akun['role'] === 'petugas' ? 'petugas/index.php' : 'beranda.php'));
        exit;
    }
    $error = 'Username atau password tidak sesuai.';
}

$pageTitle = 'Login';
require 'includes/auth_header.php';
?>
<section class="auth-wrap">
  <div class="auth-card">
    <img src="assets/logo.svg" width="58" alt="Logo Washie">
    <span class="eyebrow">SELAMAT DATANG</span>
    <h1>Masuk ke Washie</h1>
    <p>Login untuk melanjutkan ke dashboard sesuai status akun.</p>

    <?php if (isset($_GET['daftar'])): ?>
      <p class="notice notice-success">Registrasi berhasil. Silakan login.</p>
    <?php elseif (isset($_GET['logout'])): ?>
      <p class="notice notice-success">Kamu sudah keluar dari akun.</p>
    <?php endif; ?>

    <?php if ($error): ?>
      <p class="notice notice-error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="login.php">
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" placeholder="Masukkan username" required autocomplete="username">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="Masukkan password" required autocomplete="current-password">
      </div>
      <button class="button button-primary button-wide">Login</button>
    </form>

    <p class="form-footnote">Belum memiliki akun? <a href="register.php">Registrasi pelanggan</a></p>
  </div>
</section>
<?php require 'includes/footer.php'; ?>
