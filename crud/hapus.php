<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id_pesanan'] ?? 0);
    db()->prepare('DELETE FROM pesanan WHERE id_pesanan = ?')->execute([$id]);
    header('Location: petugas/pesanan.php');
    exit;
}

$st = db()->prepare('SELECT kode_pesanan FROM pesanan WHERE id_pesanan = ?');
$st->execute([$id]);
$kode = $st->fetchColumn();
if (!$kode) {
    die('Pesanan tidak ditemukan.');
}

$pageTitle = 'Hapus Pesanan';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
  <h1>Hapus pesanan?</h1>
  <p>Pesanan <strong><?= e($kode) ?></strong> akan dihapus beserta detail dan pembayaran terkait.</p>
  <form method="post" class="form-actions">
    <input type="hidden" name="id_pesanan" value="<?= $id ?>">
    <button class="button button-primary" type="submit">Ya, hapus</button>
    <a class="button button-quiet" href="../petugas/pesanan.php">Batal</a>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
