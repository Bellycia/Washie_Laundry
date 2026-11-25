<?php
require_once __DIR__ . '/../config.php';
roleWajib('pelanggan');
$pageTitle = 'Pembayaran'; $order = null; $message = ''; $error = '';
$kode = strtoupper(trim((string)($_GET['kode'] ?? $_POST['kode'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals((string)($_SESSION['payment_token'] ?? ''), (string)($_POST['payment_token'] ?? ''))) {
    $error = 'Sesi tidak valid. Muat ulang halaman dan coba kembali.';
  } else {
    $metode = (string)($_POST['metode'] ?? '');
    if (!in_array($metode, ['transfer_bank','ewallet','qris','tunai'], true)) {
      $error = 'Pilih metode pembayaran yang valid.';
    } else {
      try {
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id_pesanan, kode_pesanan, total_final, status_pesanan, status_pembayaran FROM pesanan WHERE kode_pesanan=? AND id_pelanggan=? FOR UPDATE');
        $stmt->execute([$kode, pelangganId()]); $found = $stmt->fetch();
        if (!$found) {
          $pdo->rollBack(); $error = 'Pesanan tidak ditemukan.';
        } elseif ($found['total_final'] === null || $found['status_pesanan'] === 'menunggu_konfirmasi') {
          $pdo->rollBack(); $error = 'Tagihan belum tersedia. Petugas perlu mengonfirmasi berat dan total biaya terlebih dahulu.';
        } elseif ($found['status_pesanan'] === 'dibatalkan') {
          $pdo->rollBack(); $error = 'Pesanan ini sudah dibatalkan.';
        } elseif ($found['status_pembayaran'] === 'lunas') {
          $pdo->rollBack(); $error = 'Pesanan ini sudah lunas.';
        } else {
          // Memilih metode = pembayaran tercatat lunas. Pesanan lalu siap dikerjakan petugas (status Diproses).
          $stmt = $pdo->prepare('SELECT id_pembayaran FROM pembayaran WHERE id_pesanan=? ORDER BY id_pembayaran DESC LIMIT 1');
          $stmt->execute([$found['id_pesanan']]); $existing = $stmt->fetchColumn();
          if ($existing) {
            $pdo->prepare('UPDATE pembayaran SET metode=?, jumlah_tagihan=?, status="lunas" WHERE id_pembayaran=?')->execute([$metode, $found['total_final'], $existing]);
          } else {
            $pdo->prepare('INSERT INTO pembayaran (id_pesanan, metode, jumlah_tagihan, status) VALUES (?, ?, ?, "lunas")')
                ->execute([$found['id_pesanan'], $metode, $found['total_final']]);
          }
          $statusBaru = $found['status_pesanan'] === 'menunggu_pembayaran' ? 'diproses' : $found['status_pesanan'];
          $pdo->prepare('UPDATE pesanan SET status_pembayaran="lunas", status_pesanan=? WHERE id_pesanan=?')->execute([$statusBaru, $found['id_pesanan']]);
          $pdo->commit();
          $message = 'Pembayaran berhasil dicatat. Status pembayaran kini Lunas dan cucianmu akan segera dikerjakan petugas.';
        }
      } catch (Throwable $ex) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); $error = 'Metode pembayaran belum dapat disimpan. Silakan coba lagi.'; }
    }
  }
}
$_SESSION['payment_token'] ??= bin2hex(random_bytes(32));

if ($kode !== '') {
  try {
    $stmt = db()->prepare('SELECT p.*, pl.nama FROM pesanan p JOIN pelanggan pl ON p.id_pelanggan=pl.id_pelanggan WHERE p.kode_pesanan=? AND p.id_pelanggan=?');
    $stmt->execute([$kode, pelangganId()]); $order = $stmt->fetch();
    if (!$order) $error = $error ?: 'Kode pesanan tidak ditemukan.';
  } catch (Throwable $ex) { $error = 'Data belum dapat dimuat. Silakan coba lagi.'; }
}
$tagihan = [];
if ($kode === '') {
  $st = db()->prepare("SELECT kode_pesanan, total_final FROM pesanan WHERE id_pelanggan=? AND total_final IS NOT NULL AND status_pembayaran IN ('menunggu_pembayaran','ditolak') AND status_pesanan<>'dibatalkan' ORDER BY dibuat_pada DESC");
  $st->execute([pelangganId()]); $tagihan = $st->fetchAll();
}
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro"><span class="eyebrow">TAGIHAN & PEMBAYARAN</span><h1>Pembayaran</h1><p>Metode pembayaran bisa dipilih setelah petugas menimbang cucianmu dan mengonfirmasi tagihan final. Pesanan baru dikerjakan setelah dibayar.</p></section>
<?php if ($message): ?><p class="notice notice-success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice notice-error"><?= e($error) ?></p><?php endif; ?>
<?php if (!$order && $tagihan): ?>
<section class="panel"><h2>Menunggu pembayaran</h2><p>Berat pesananmu sudah dikonfirmasi petugas. Silakan lakukan pembayaran:</p>
<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Total tagihan</th><th>Aksi</th></tr></thead><tbody>
<?php foreach ($tagihan as $t): ?><tr><td><?= e($t['kode_pesanan']) ?></td><td><?= rupiah((float)$t['total_final']) ?></td><td><a class="small-btn" href="pembayaran.php?kode=<?= urlencode($t['kode_pesanan']) ?>">Bayar</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php if (!$order): ?>
<form class="panel lookup-form" method="get" action="pembayaran.php"><label for="kode">Kode pesanan</label><div class="lookup-row"><input id="kode" name="kode" type="text" placeholder="Masukkan kode pesanan" value="<?= e($kode) ?>" required><button class="button button-primary" type="submit">Cek tagihan</button></div></form>
<?php elseif ($order['total_final'] === null || $order['status_pesanan'] === 'menunggu_konfirmasi'): ?>
<section class="panel order-detail"><span class="status-pill">Menunggu konfirmasi petugas</span><h2><?= e($order['kode_pesanan']) ?></h2><p>Halo, <?= e($order['nama']) ?>. Tagihanmu belum tersedia karena petugas belum menimbang dan mengonfirmasi biaya final.</p><p class="muted">Mohon jangan membayar berdasarkan perkiraan awal.</p></section>
<?php elseif ($order['status_pembayaran'] === 'lunas'): ?>
<section class="panel order-detail"><span class="status-pill">Lunas</span><h2><?= e($order['kode_pesanan']) ?></h2><p class="bill-amount"><?= rupiah((float)$order['total_final']) ?></p><p>Pembayaran sudah diterima. Status pesanan: <strong><?= e(labelStatus($order['status_pesanan'])) ?></strong>.</p><a class="button button-quiet" href="pesanan-saya.php">Lihat riwayat pesanan</a></section>
<?php else: ?>
<section class="panel payment-panel"><span class="eyebrow">TAGIHAN FINAL</span><h2><?= e($order['kode_pesanan']) ?></h2>
<div class="summary-box"><div><span>Biaya laundry</span><strong><?= rupiah((float)$order['biaya_laundry']) ?></strong></div><div><span>Biaya admin</span><strong><?= rupiah((float)$order['biaya_admin']) ?></strong></div><?php if ((float)$order['diskon'] > 0): ?><div><span>Diskon<?= $order['nama_promo'] ? ' (' . e($order['nama_promo']) . ')' : '' ?></span><strong>−<?= rupiah((float)$order['diskon']) ?></strong></div><?php endif; ?></div>
<p class="bill-amount"><?= rupiah((float)$order['total_final']) ?></p><p class="muted">Atas nama: <?= e($order['nama']) ?></p>
<form method="post" action="pembayaran.php?kode=<?= urlencode($kode) ?>" class="payment-form"><input type="hidden" name="kode" value="<?= e($kode) ?>"><input type="hidden" name="payment_token" value="<?= e($_SESSION['payment_token']) ?>"><fieldset><legend>Pilih metode pembayaran</legend>
<label class="radio-card"><input type="radio" name="metode" value="transfer_bank" required><span><strong>Transfer bank</strong><small>Petugas akan mengirim nomor rekening tujuan.</small></span></label>
<label class="radio-card"><input type="radio" name="metode" value="ewallet" required><span><strong>E-wallet</strong><small>Bayar lewat dompet digital pilihanmu.</small></span></label>
<label class="radio-card"><input type="radio" name="metode" value="qris" required><span><strong>QRIS</strong><small>Scan kode QR dari aplikasi pembayaran apa pun.</small></span></label>
<label class="radio-card"><input type="radio" name="metode" value="tunai" required><span><strong>Tunai</strong><small>Bayar langsung saat cucian diantar atau diambil.</small></span></label>
</fieldset><button class="button button-primary button-wide" type="submit">Konfirmasi metode</button><p class="form-footnote">Setelah metode dipilih, status pembayaran menjadi Lunas dan pesanan mulai dikerjakan.</p></form></section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
