<?php
require_once 'config.php';
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$role = $_SESSION['user']['role'] ?? 'pelanggan';
$cancelUrl = $role === 'petugas' ? 'petugas/index.php' : 'beranda.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['konfirmasi'] ?? '') === 'ya') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: login.php?logout=berhasil');
    exit;
}
$pageTitle = 'Konfirmasi Logout';
require 'includes/auth_header.php';
?>
<section class="auth-wrap"><div class="auth-card">
<img src="assets/logo.svg" width="58" alt="Logo Washie">
<span class="eyebrow">KONFIRMASI</span>
<h1>Keluar dari akun?</h1>
<p>Apakah Anda yakin ingin logout dari Washie Laundry? Pilih <strong>Ya, logout</strong> untuk kembali ke halaman login, atau pilih <strong>Tidak</strong> untuk tetap masuk.</p>
<form method="post" action="logout.php">
<button class="button button-primary button-wide" type="submit" name="konfirmasi" value="ya">Ya, logout</button>
<a class="button button-quiet button-wide" style="display:block;text-align:center;margin-top:10px" href="<?=e($cancelUrl)?>">Tidak, kembali</a>
</form>
</div></section>
<?php require 'includes/footer.php'; ?>
