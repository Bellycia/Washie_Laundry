<?php
require_once 'config.php';

// Sudah login: langsung arahkan sesuai role.
if (!empty($_SESSION['user'])) {
    header('Location: ' . (($_SESSION['user']['role'] ?? '') === 'petugas' ? 'petugas/index.php' : 'beranda.php'));
    exit;
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
    <?php elseif (isset($_GET['gagal'])): ?>
      <p class="notice notice-error">Username atau password tidak sesuai.</p>
    <?php elseif (isset($_GET['logout'])): ?>
      <p class="notice notice-success">Kamu sudah keluar dari akun.</p>
    <?php endif; ?>

    <form method="post" action="processLogin.php">
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
