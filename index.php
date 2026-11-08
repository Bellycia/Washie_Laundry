<?php
require_once __DIR__ . '/../config.php';
pelangganWajib();
$pageTitle = 'Form Pemesanan';
$kategoriAktif=db()->query('SELECT * FROM kategori_barang ORDER BY id_kategori')->fetchAll();
$layananAktif=db()->query('SELECT * FROM layanan ORDER BY id_layanan')->fetchAll();
$promoHariIni=promoBerlaku(date('Y-m-d'));
$st = db()->prepare('SELECT nama,no_hp,alamat FROM pelanggan WHERE id_pelanggan=?');
$st->execute([pelangganId()]); $dataPelanggan = $st->fetch() ?: ['nama'=>'','no_hp'=>'','alamat'=>''];
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro"><span class="eyebrow">PESAN SEKARANG</span><h1>Serahkan cucianmu, sisanya urusan kami</h1><p>Isi data singkat di bawah ini. Setelah dikirim, kamu langsung mendapat kode pesanan dan perkiraan biaya.</p></section>
<div class="form-layout">
<form class="panel order-form" action="proses.php" method="post" id="orderForm" novalidate data-buka="<?= e(JAM_BUKA) ?>" data-tutup="<?= e(JAM_TUTUP) ?>" data-hari-buka="<?= e(implode(',', HARI_BUKA)) ?>">
  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))) ?>">
  <fieldset><legend><span>01</span> Data pelanggan</legend>
    <div class="form-grid">
      <div class="field"><label for="nama">Nama lengkap *</label><input id="nama" name="nama" type="text" value="<?= e($dataPelanggan['nama']) ?>" readonly required autocomplete="name" ></div>
      <div class="field"><label for="no_hp">Nomor HP aktif *</label><input id="no_hp" name="no_hp" type="tel" value="<?= e($dataPelanggan['no_hp']) ?>" readonly required autocomplete="tel" ></div>
      <div class="field field-full"><label for="alamat">Alamat lengkap *</label><textarea id="alamat" name="alamat" rows="3" required readonly autocomplete="street-address"><?= e($dataPelanggan['alamat']) ?></textarea></div>
    </div>
  </fieldset>
  <fieldset><legend><span>02</span> Detail barang & layanan</legend>
    <div class="form-grid">
      <div class="field"><label for="kategori">Kategori barang *</label><select id="kategori" name="kategori" required><option value="">Pilih kategori</option><?php foreach($kategoriAktif as $k): ?><option value="<?=e($k['kode'])?>"><?=e($k['nama'])?> (<?=e($k['satuan'])?>)</option><?php endforeach; ?></select></div>
      <div class="field"><label for="jumlah">Jumlah barang *</label><input id="jumlah" name="jumlah" type="number" min="1" max="100" step="1" placeholder="Contoh: 3" required><small id="unitHint">Pilih kategori untuk melihat satuan.</small></div>
      <div class="field"><label for="layanan">Jenis layanan *</label><select id="layanan" name="layanan" required><option value="">Pilih layanan</option><?php foreach($layananAktif as $l): ?><option value="<?=e($l['kode'])?>"><?=e($l['nama'])?> — <?=e($l['estimasi'])?> (<?=e((string)$l['pengali_harga'])?>×)</option><?php endforeach; ?></select></div>
      <div class="field"><label for="penyerahan">Metode penyerahan *</label><select id="penyerahan" name="penyerahan" required><option value="">Pilih metode</option><option value="antar_jemput">Antar-jemput (+ Rp10.000)</option><option value="mandiri">Mandiri / bawa sendiri</option></select></div>
    </div>
    <div class="pickup-fields" id="pickupFields" hidden>
      <h3>Jadwal antar-jemput</h3><p class="muted">Pilih waktu yang nyaman. Layanan antar-jemput tersedia <strong>Senin–Sabtu, pukul <?= e(str_replace(':', '.', JAM_BUKA)) ?>–<?= e(str_replace(':', '.', JAM_TUTUP)) ?> WIB</strong>. Jadwal akan diproses oleh tim laundry.</p>
      <div class="form-grid">
        <div class="field"><label for="tanggal_jemput">Tanggal jemput *</label><input id="tanggal_jemput" name="tanggal_jemput" type="date" min="<?= e(date('Y-m-d')) ?>"><small class="field-error" id="err_tanggal_jemput" role="alert"></small></div>
        <div class="field"><label for="waktu_jemput">Jam jemput *</label><input id="waktu_jemput" name="waktu_jemput" type="time" min="<?= e(JAM_BUKA) ?>" max="<?= e(JAM_TUTUP) ?>"><small class="field-error" id="err_waktu_jemput" role="alert"></small></div>
        <div class="field"><label for="tanggal_antar">Tanggal antar *</label><input id="tanggal_antar" name="tanggal_antar" type="date" min="<?= e(date('Y-m-d')) ?>"><small class="field-error" id="err_tanggal_antar" role="alert"></small></div>
        <div class="field"><label for="waktu_antar">Jam antar *</label><input id="waktu_antar" name="waktu_antar" type="time" min="<?= e(JAM_BUKA) ?>" max="<?= e(JAM_TUTUP) ?>"><small class="field-error" id="err_waktu_antar" role="alert"></small></div>
      </div>
    </div>
    <div class="field"><label for="catatan">Catatan tambahan</label><textarea id="catatan" name="catatan" rows="3" maxlength="1000" placeholder="Contoh: ada noda di bagian lengan, mohon hubungi sebelum datang."></textarea></div>
  </fieldset>
  <fieldset><legend><span>03</span> Perkiraan biaya</legend>
    <div class="summary-box" aria-live="polite">
      <div><span>Biaya laundry (perkiraan)</span><strong id="summaryLaundry">Rp0</strong></div>
      <div><span>Biaya admin</span><strong id="summaryAdmin">Rp0</strong></div>
      <div id="rowDiskon"><span>Diskon<span id="summaryPromoNama"></span></span><strong id="summaryDiskon">−Rp0</strong></div>
      <div class="summary-total"><span>Total perkiraan</span><strong id="summaryTotal">Rp0</strong></div>
    </div>
    <p class="notice">Angka ini bukan tagihan final. Untuk pakaian, berat sebenarnya diperiksa oleh tim laundry. Tagihan baru dikirim setelah berat dan biaya dikonfirmasi.</p>
  </fieldset>
  <label class="checkbox-field"><input type="checkbox" name="setuju" value="1" required> Saya paham biaya ini masih perkiraan dan jadwal akan dikonfirmasi oleh tim laundry.</label>
  <button class="button button-primary button-wide" type="submit">Kirim pemesanan <span aria-hidden="true">→</span></button>
  <p class="form-footnote">Datamu hanya dipakai untuk memproses pesanan laundry ini.</p>
</form>
<aside class="side-help">
  <div class="side-help-card"><img class="side-photo" src="../assets/pickup.svg" alt="Ilustrasi kurir Washie membawa tas cucian" loading="lazy" width="360" height="200"><h2>Perlu diketahui</h2><ul><li>Isi nomor HP yang bisa dihubungi.</li><li>Antar-jemput hanya di jam operasional: Senin–Sabtu, 08.00–20.00 WIB.</li><li>Jangan bayar sebelum tagihan final diterima.</li></ul><a href="informasi.php">Baca ketentuan →</a></div>
  <div class="side-help-card soft"><h3>Alur pesanan</h3><ol><li>Kirim form</li><li>Tim laundry memeriksa barang dan menimbang</li><li>Tagihan final dikirim</li><li>Pilih metode pembayaran</li></ol></div>
</aside>
</div>
<script>
window.WASHIE_CATALOG = {
 kategori: <?=json_encode(array_column(array_map(fn($x)=>['kode'=>$x['kode'],'label'=>$x['nama'],'unit'=>$x['satuan'],'tarif'=>(float)$x['tarif_dasar']],$kategoriAktif),null,'kode'),JSON_UNESCAPED_UNICODE)?>,
 layanan: <?=json_encode(array_column(array_map(fn($x)=>['kode'=>$x['kode'],'label'=>$x['nama'],'multiplier'=>(float)$x['pengali_harga']],$layananAktif),null,'kode'),JSON_UNESCAPED_UNICODE)?>,
 promo: <?=json_encode(array_map(fn($x)=>['nama'=>$x['nama'],'jenis'=>$x['jenis_diskon'],'nilai'=>(float)$x['nilai_diskon'],'min'=>(float)$x['minimal_belanja']],$promoHariIni),JSON_UNESCAPED_UNICODE)?>
};
</script><script src="../js/script.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
