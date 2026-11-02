<?php
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/koneksi.php';
wajibAdmin();

$filter = $_GET['status'] ?? '';
if (!in_array($filter, STATUS_PESANAN, true)) {
    $filter = '';
}
$qs = $filter !== '' ? '?status=' . urlencode($filter) : '';

// [PHP] Admin mengubah status pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id > 0 && in_array($status, STATUS_PESANAN, true)) {
        $pdo->prepare('UPDATE pesanan SET status = ? WHERE id = ?')->execute([$status, $id]);
        $_SESSION['flash'] = 'Status pesanan berhasil diperbarui menjadi "' . $status . '".';
    }
    header('Location: admin.php' . $qs);
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// [PHP] Ringkasan jumlah pesanan per status
$jumlahStatus = array_fill_keys(STATUS_PESANAN, 0);
$pendapatan = 0;
foreach ($pdo->query('SELECT status, COUNT(*) AS n, SUM(total) AS t FROM pesanan GROUP BY status') as $r) {
    if (isset($jumlahStatus[$r['status']])) {
        $jumlahStatus[$r['status']] = (int) $r['n'];
    }
    if (in_array($r['status'], ['Selesai', 'Diserahkan'], true)) {
        $pendapatan += (int) $r['t'];
    }
}
$totalPesanan = array_sum($jumlahStatus);

// [PHP] Daftar pesanan masuk dari semua pelanggan
if ($filter !== '') {
    $stmt = $pdo->prepare('SELECT * FROM pesanan WHERE status = ? ORDER BY id DESC');
    $stmt->execute([$filter]);
} else {
    $stmt = $pdo->query('SELECT * FROM pesanan ORDER BY id DESC');
}
$daftar = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin | Washie Laundry</title>
  <link rel="icon" type="image/svg+xml" href="assets/logo.svg">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
  <span class="brand">
    <img class="brand-logo" src="assets/logo.svg" alt="" width="39" height="39">
    <span>Washie<span class="brand-light">Laundry</span><span class="admin-note">ADMIN</span></span>
  </span>
  <nav class="main-nav">
    <a class="active" href="admin.php">Dashboard</a>
    <a class="nav-logout" href="logout.php">Logout (<?= e($_SESSION['nama']) ?>)</a>
  </nav>
</header>

<main class="page-shell">
  <section class="page-intro">
    <span class="eyebrow">DASHBOARD ADMIN</span>
    <h1>Pesanan masuk</h1>
    <p>Pesanan yang dibuat pelanggan masuk ke sini. Ubah statusnya agar pelanggan bisa memantau.</p>
  </section>

  <?php if ($flash): ?>
    <div class="notice notice-success" id="notifSukses" role="alert">✔ <?= e($flash) ?></div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat"><span>Total pesanan</span><strong><?= $totalPesanan ?></strong></div>
    <?php foreach (STATUS_PESANAN as $st): ?>
      <div class="stat"><span><?= e($st) ?></span><strong><?= $jumlahStatus[$st] ?></strong></div>
    <?php endforeach; ?>
    <div class="stat stat-green"><span>Pendapatan (selesai)</span><strong><?= rupiahFmt($pendapatan) ?></strong></div>
  </div>

  <div class="filter-tabs">
    <a href="admin.php" class="<?= $filter === '' ? 'active' : '' ?>">Semua</a>
    <?php foreach (STATUS_PESANAN as $st): ?>
      <a href="admin.php?status=<?= urlencode($st) ?>" class="<?= $filter === $st ? 'active' : '' ?>"><?= e($st) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (count($daftar) === 0): ?>
    <div class="panel empty-state" style="margin-top:20px">
      <img src="assets/kosong.svg" alt="" width="260">
      <p>Belum ada pesanan<?= $filter !== '' ? ' dengan status ini' : '' ?>.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Kode</th><th>Pelanggan</th><th>Barang</th><th>Layanan</th><th>Total</th><th>Status</th><th>Ubah status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($daftar as $p): ?>
            <tr>
              <td><strong><?= e($p['kode']) ?></strong><br><small><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></small></td>
              <td>
                <?= e($p['nama']) ?><br>
                <small><?= e($p['no_hp']) ?></small>
                <?php if (!empty($p['alamat'])): ?><br><small><?= e($p['alamat']) ?></small><?php endif; ?>
              </td>
              <td><?= e($p['kategori']) ?><br><small><?= e($p['jumlah']) ?></small></td>
              <td><?= e($p['layanan']) ?><br><small><?= e($p['penyerahan']) ?></small></td>
              <td><?= rupiahFmt((int) $p['total']) ?></td>
              <td><?= badgeStatus($p['status']) ?></td>
              <td>
                <form method="post" action="admin.php<?= e($qs) ?>" class="status-form">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <select name="status" aria-label="Status pesanan">
                    <?php foreach (STATUS_PESANAN as $st): ?>
                      <option value="<?= e($st) ?>" <?= $p['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="button button-primary">Simpan</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>

<footer class="site-footer">
  <div class="footer-inner"><div><strong>Washie Laundry</strong></div><p>© <?= date('Y') ?> Washie Laundry · Dashboard Admin</p></div>
</footer>

<script>
  var n = document.getElementById('notifSukses');
  if (n) setTimeout(function () { n.style.display = 'none'; }, 5000);
</script>
</body>
</html>
