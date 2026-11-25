<?php
require_once __DIR__ . '/../config.php';
roleWajib('petugas');

$pdo = db();
$id  = (int)($_GET['id'] ?? $_POST['id_pesanan'] ?? 0);

// Status yang berarti cucian mulai dikerjakan: hanya boleh jika pelanggan sudah membayar.
const STATUS_KERJA = ['diproses', 'siap_diantar', 'selesai'];
$statusPesanan = ['menunggu_pembayaran', 'diproses', 'siap_diantar', 'selesai', 'dibatalkan'];
$statusBayar   = ['menunggu_pembayaran', 'lunas', 'ditolak'];

$st = $pdo->prepare(
    'SELECT p.*, pl.nama, d.jumlah, d.satuan, k.nama AS kategori_nama, k.tarif_dasar,
            l.pengali_harga, l.nama AS layanan_nama
     FROM pesanan p
     JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
     LEFT JOIN detail_pesanan d ON d.id_pesanan = p.id_pesanan
     LEFT JOIN kategori_barang k ON k.id_kategori = d.id_kategori
     JOIN layanan l ON l.id_layanan = p.id_layanan
     WHERE p.id_pesanan = ?'
);
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    die('Pesanan tidak ditemukan.');
}

$satuan     = $r['satuan'] ?: 'kg';
$isKg       = strtolower($satuan) === 'kg';
$tarif      = (float)$r['tarif_dasar'];      // tarif TERBARU dari menu Layanan & Tarif
$pengali    = (float)$r['pengali_harga'];   // pengali TERBARU
$sudahLunas = $r['status_pembayaran'] === 'lunas';
$tglPesan   = date('Y-m-d', strtotime((string)$r['dibuat_pada']));
$promoList  = promoBerlaku($tglPesan);       // promo yang berlaku pada tanggal pesanan dibuat

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = (string)($_POST['status_pesanan'] ?? '');
    $bayar  = (string)($_POST['status_pembayaran'] ?? '');
    if ($status === 'menunggu_konfirmasi') $status = 'menunggu_pembayaran';

    if (!in_array($status, $statusPesanan, true) || !in_array($bayar, $statusBayar, true)) {
        $err = 'Periksa kembali status yang dipilih.';
    } elseif ($sudahLunas) {
        // Tagihan yang sudah lunas dikunci: berat/jumlah, promo, dan total tidak diubah lagi.
        $berat = (float)$r['berat_kg']; $subtotal = (float)$r['biaya_laundry'];
        $admin = (float)$r['biaya_admin']; $diskon = (float)$r['diskon'];
        $namaPromo = $r['nama_promo']; $total = (float)$r['total_final'];
    } else {
        $inputVal = $_POST['berat_kg'] ?? '0';
        $berat = $isKg ? (float)str_replace(',', '.', (string)$inputVal) : (int)$inputVal;
        
        if ($berat <= 0) {
            $err = $isKg ? 'Masukkan berat hasil timbang lebih dari 0.' : 'Masukkan jumlah item terkonfirmasi lebih dari 0.';
        } else {
            $pilih = (string)($_POST['id_promo'] ?? 'auto');
            $promoDipilih = null;
            if ($pilih !== 'auto' && $pilih !== '0') {
                foreach ($promoList as $p) if ((int)$p['id_promo'] === (int)$pilih) $promoDipilih = $p;
            }
            if ($pilih === '0') {
                $h = hitungBiaya($berat, $tarif, $pengali, $r['metode_penyerahan'], []);
            } else {
                $h = hitungBiaya($berat, $tarif, $pengali, $r['metode_penyerahan'], $promoList, $promoDipilih);
            }
            $subtotal = $h['subtotal']; $admin = $h['admin']; $diskon = $h['diskon'];
            $namaPromo = $diskon > 0 ? $h['nama_promo'] : null; $total = $h['total'];
        }
    }

    if ($err === '') {
        // Setelah berat/jumlah dikonfirmasi, tagihan terbit otomatis: menunggu pembayaran.
        if ($r['status_pesanan'] === 'menunggu_konfirmasi' && $status === 'menunggu_pembayaran') {
            $bayar = $sudahLunas ? 'lunas' : 'menunggu_pembayaran';
        }
        if ($bayar === 'lunas' && $status === 'menunggu_pembayaran') $status = 'diproses';
        if (in_array($status, STATUS_KERJA, true) && $bayar !== 'lunas') {
            $err = 'Pesanan belum dibayar pelanggan, sehingga belum dapat dikerjakan. Ubah status ke "Menunggu pembayaran" atau tandai pembayaran Lunas.';
        }
    }

    if ($err === '') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE pesanan
                 SET berat_kg = ?, biaya_laundry = ?, biaya_admin = ?, diskon = ?, nama_promo = ?, total_final = ?,
                     status_pesanan = ?, status_pembayaran = ?
                 WHERE id_pesanan = ?'
            )->execute([$berat, $subtotal, $admin, $diskon, $namaPromo, $total, $status, $bayar, $id]);

            // Simpan tarif/jumlah satuan terbaru pada rincian pesanan.
            $pdo->prepare('UPDATE detail_pesanan SET tarif_satuan = ?, subtotal_estimasi = ? WHERE id_pesanan = ?')
                ->execute([$tarif, $subtotal, $id]);

            $cek = $pdo->prepare('SELECT id_pembayaran FROM pembayaran WHERE id_pesanan = ? ORDER BY id_pembayaran DESC LIMIT 1');
            $cek->execute([$id]);
            $idBayar = $cek->fetchColumn();
            if ($idBayar) {
                $pdo->prepare('UPDATE pembayaran SET jumlah_tagihan = ?, status = ? WHERE id_pembayaran = ?')
                    ->execute([$total, $bayar, $idBayar]);
            } elseif ($bayar === 'lunas') {
                // Dilunasi langsung oleh petugas (belum ada pilihan metode dari pelanggan).
                $pdo->prepare('INSERT INTO pembayaran(id_pesanan, metode, jumlah_tagihan, status) VALUES(?, "tunai", ?, "lunas")')
                    ->execute([$id, $total]);
            }
            $pdo->commit();
            header('Location: pesanan.php?cari=' . urlencode($r['kode_pesanan']));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    // Gagal: tampilkan kembali nilai yang diketik.
    $r['berat_kg'] = $_POST['berat_kg'] ?? $r['berat_kg'];
}

// Nilai awal form
$beratAwal = $r['berat_kg'] !== null ? $r['berat_kg'] : '';
$promoTerpilih = 'auto';
if ($r['total_final'] !== null) {
    $promoTerpilih = '0';
    foreach ($promoList as $p) if ($r['nama_promo'] !== null && $p['nama'] === $r['nama_promo']) $promoTerpilih = (string)$p['id_promo'];
}
if (isset($_POST['id_promo'])) $promoTerpilih = (string)$_POST['id_promo'];

$statusAwal = $_POST['status_pesanan'] ?? $r['status_pesanan'];
if ($statusAwal === 'menunggu_konfirmasi') $statusAwal = 'menunggu_pembayaran';
$bayarAwal = $_POST['status_pembayaran'] ?? $r['status_pesanan'];
if ($bayarAwal === 'belum_ada_tagihan' || $bayarAwal === 'menunggu_verifikasi') $bayarAwal = 'menunggu_pembayaran';

$judul = 'Edit Pesanan';
require '_header.php';
$kunci = $sudahLunas ? 'disabled' : '';
$labelBerat = $isKg ? 'Berat hasil timbang (kg)' : 'Jumlah terkonfirmasi (' . $satuan . ')';
?>
<section class="page-intro">
  <h1>Edit pesanan <?= e($r['kode_pesanan']) ?></h1>
  <p>Isi <?= $isKg ? 'berat hasil timbang' : 'jumlah item' ?>. Harga dihitung otomatis: <?= $isKg ? 'berat' : 'jumlah' ?> × tarif × layanan, dikurangi diskon promo, ditambah biaya admin bila antar-jemput.</p>
</section>

<?php if ($err): ?>
  <p class="notice notice-error" role="alert"><?= e($err) ?></p>
<?php endif; ?>
<?php if ($sudahLunas): ?>
  <p class="notice">Pembayaran sudah lunas, sehingga data dikunci. Status pesanan masih dapat diperbarui.</p>
<?php endif; ?>

<form class="panel admin-form" method="post" id="editOrder">
  <input type="hidden" name="id_pesanan" value="<?= (int)$id ?>">
  <div class="field">
    <label>Pelanggan</label>
    <input value="<?= e($r['nama'] ?? '') ?>" disabled>
  </div>
  <div class="field">
    <label>Kategori barang</label>
    <input value="<?= e($r['kategori_nama'] ?? '-') ?>" disabled>
  </div>
  <div class="field">
    <label>Input pelanggan</label>
    <input value="<?= e((string)$r['jumlah']) ?> <?= e($satuan) ?>" disabled>
  </div>
  <div class="field">
    <label>Layanan</label>
    <input value="<?= e($r['layanan_nama']) ?> (<?= e((string)$r['pengali_harga']) ?>x)" disabled>
  </div>
  <div class="field">
    <label>Metode penyerahan</label>
    <input value="<?= e($r['metode_penyerahan'] === 'mandiri' ? 'Bawa sendiri' : 'Antar jemput') ?> · Biaya admin <?= rupiah($r['metode_penyerahan'] === 'mandiri' ? 0 : BIAYA_ADMIN) ?>" disabled>
  </div>
  <div class="field">
    <label>Tarif saat ini</label>
    <input value="<?= rupiah($tarif) ?> / <?= e($satuan) ?>" disabled>
  </div>

  <div class="field">
    <label for="berat"><?= e($labelBerat) ?></label>
    <input type="number" name="berat_kg" id="berat" min="<?= $isKg ? '0.01' : '1' ?>" step="<?= $isKg ? '0.01' : '1' ?>" value="<?= e((string)$beratAwal) ?>" <?= $kunci ?> <?= $sudahLunas ? '' : 'required' ?>>
  </div>
  <div class="field">
    <label for="promo">Promo</label>
    <select name="id_promo" id="promo" <?= $kunci ?>>
      <option value="auto" <?= $promoTerpilih === 'auto' ? 'selected' : '' ?>>Otomatis (promo terbaik yang berlaku)</option>
      <option value="0" <?= $promoTerpilih === '0' ? 'selected' : '' ?>>Tanpa promo</option>
      <?php foreach ($promoList as $p): ?>
        <option value="<?= (int)$p['id_promo'] ?>" <?= $promoTerpilih === (string)$p['id_promo'] ? 'selected' : '' ?>
                data-type="<?= e($p['jenis_diskon']) ?>"
                data-value="<?= e((string)$p['nilai_diskon']) ?>"
                data-min="<?= e((string)$p['minimal_belanja']) ?>">
          <?= e($p['nama']) ?> (<?= $p['jenis_diskon'] === 'persen' ? e((string)(float)$p['nilai_diskon']) . '%' : rupiah((float)$p['nilai_diskon']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="field field-full">
    <label>Rincian harga</label>
    <div class="summary-box" aria-live="polite">
      <div><span>Biaya laundry (<?= $isKg ? 'berat' : 'jumlah' ?> × tarif × layanan)</span><strong id="rLaundry">Rp0</strong></div>
      <div><span>Biaya admin</span><strong id="rAdmin">Rp0</strong></div>
      <div><span>Diskon <span id="rPromoNama"></span></span><strong id="rDiskon">Rp0</strong></div>
      <div class="summary-total"><span>Total tagihan</span><strong id="rTotal">Isi <?= $isKg ? 'berat' : 'jumlah' ?> terlebih dahulu</strong></div>
    </div>
  </div>

  <div class="field">
    <label for="status_pesanan">Status pesanan</label>
    <select name="status_pesanan" id="status_pesanan">
      <?php foreach ($statusPesanan as $v): ?>
        <option value="<?= $v ?>" <?= $statusAwal === $v ? 'selected' : '' ?>><?= e(labelStatus($v)) ?></option>
      <?php endforeach; ?>
    </select>
    <small class="muted">Diproses / Siap diantar / Selesai hanya bisa dipilih jika pembayaran Lunas.</small>
  </div>
  <div class="field">
    <label for="status_pembayaran">Status pembayaran</label>
    <select name="status_pembayaran" id="status_pembayaran">
      <?php foreach ($statusBayar as $v): ?>
        <option value="<?= $v ?>" <?= $bayarAwal === $v ? 'selected' : '' ?>><?= e(labelStatus($v)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-actions">
    <button class="button button-primary" type="submit">Simpan perubahan</button>
    <a class="button button-quiet" href="pesanan.php">Kembali</a>
  </div>
</form>

<script>
(() => {
  const berat = document.getElementById('berat');
  const promo = document.getElementById('promo');
  const tarif   = <?= json_encode($tarif) ?>;
  const pengali = <?= json_encode($pengali) ?>;
  const admin   = <?= json_encode($r['metode_penyerahan'] === 'antar_jemput' ? (float)BIAYA_ADMIN : 0.0) ?>;
  const kunci   = <?= json_encode($sudahLunas) ?>;
  const isKg    = <?= json_encode($isKg) ?>;
  const tersimpan = <?= json_encode([
      'subtotal' => (float)$r['biaya_laundry'], 'admin' => (float)$r['biaya_admin'],
      'diskon' => (float)$r['diskon'], 'promo' => $r['nama_promo'], 'total' => (float)$r['total_final']]) ?>;
  const rp = n => 'Rp' + Math.round(n).toLocaleString('id-ID');
  const $ = id => document.getElementById(id);

  const potongan = (o, subtotal) => {
    if (subtotal < Number(o.dataset.min)) return 0;
    const nilai = Number(o.dataset.value);
    const d = o.dataset.type === 'persen' ? Math.round(subtotal * Math.min(100, nilai) / 100) : nilai;
    return Math.min(subtotal, d);
  };

  function hitung() {
    if (kunci) {
      $('rLaundry').textContent = rp(tersimpan.subtotal); $('rAdmin').textContent = rp(tersimpan.admin);
      $('rDiskon').textContent = '−' + rp(tersimpan.diskon); $('rPromoNama').textContent = tersimpan.promo ? '(' + tersimpan.promo + ')' : '';
      $('rTotal').textContent = rp(tersimpan.total);
      return;
    }
    const b = isKg ? (parseFloat(berat.value) || 0) : (parseInt(berat.value, 10) || 0);
    const subtotal = Math.round(b * tarif * pengali);
    let diskon = 0, nama = '';
    if (promo.value === 'auto') {
      [...promo.options].filter(o => o.dataset.type).forEach(o => {
        const d = potongan(o, subtotal);
        if (d > diskon) { diskon = d; nama = o.textContent.trim().replace(/\s*\(.*\)$/, ''); }
      });
    } else if (promo.value !== '0') {
      const o = promo.options[promo.selectedIndex];
      diskon = potongan(o, subtotal);
      if (diskon > 0) nama = o.textContent.trim().replace(/\s*\(.*\)$/, '');
    }
    $('rLaundry').textContent = b > 0 ? rp(subtotal) : 'Rp0';
    $('rAdmin').textContent = rp(admin);
    $('rDiskon').textContent = '−' + rp(b > 0 ? diskon : 0);
    $('rPromoNama').textContent = b > 0 && nama ? '(' + nama + ')' : '';
    $('rTotal').textContent = b > 0 ? rp(Math.max(0, subtotal - diskon + admin)) : (isKg ? 'Isi berat terlebih dahulu' : 'Isi jumlah terlebih dahulu');
  }
  berat.addEventListener('input', hitung);
  promo.addEventListener('change', hitung);
  hitung();
})();
</script>
<?php require '_footer.php'; ?>