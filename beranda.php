<?php
require_once 'config.php';
roleWajib('pelanggan');
$pageTitle = 'Beranda';
require 'includes/header.php';
?>
<section class="hero">
  <div class="hero-copy">
    <span class="eyebrow">LAUNDRY ANTAR-JEMPUT DI BATAM</span>
    <h1>Cucian beres,<br><span>harimu lebih ringan.</span></h1>
    <p>Laundry pakaian, sepatu, dan tas dalam satu tempat. Pilih layanan dan cara penyerahan yang paling nyaman, lalu pantau statusnya kapan saja.</p>
    <div class="hero-actions">
      <a class="button button-primary" href="pelanggan/index.php">Pesan Sekarang <span aria-hidden="true">→</span></a>
      <a class="button button-quiet" href="layanan.php">Lihat harga</a>
    </div>
    <ul class="trust-list"><li>✓ Tarif jelas</li><li>✓ Berat ditimbang petugas</li><li>✓ Status bisa dipantau</li></ul>
  </div>
  <div class="hero-art">
    <img src="assets/hero.svg" alt="Ilustrasi mesin cuci, keranjang pakaian bersih, dan gelembung sabun" width="560" height="440">
    <div class="art-note"><span class="note-icon">✦</span><div><strong>Fresh start!</strong><small>Senin–Sabtu, 08.00–20.00</small></div></div>
  </div>
</section>
<section class="section-block">
  <div class="section-heading"><div><span class="eyebrow">CARA KERJA</span><h2>Semudah 3 langkah</h2></div><p>Dari pesan sampai bayar, semuanya jelas dan bisa dipantau.</p></div>
  <div class="steps-grid">
    <article class="step-card"><img src="assets/langkah-1.svg" alt="" width="120" height="90" loading="lazy"><span class="step-number">01</span><h3>Pilih layanan</h3><p>Tentukan kategori, jumlah barang, kecepatan layanan, dan cara penyerahan.</p></article>
    <article class="step-card"><img src="assets/langkah-2.svg" alt="" width="120" height="90" loading="lazy"><span class="step-number">02</span><h3>Petugas menimbang</h3><p>Petugas memeriksa cucianmu dan menimbang beratnya agar biaya akurat.</p></article>
    <article class="step-card"><img src="assets/langkah-3.svg" alt="" width="120" height="90" loading="lazy"><span class="step-number">03</span><h3>Bayar & pantau</h3><p>Tagihan final dikirim setelah penimbangan. Pantau progres dengan kode pesananmu.</p></article>
  </div>
</section>
<section class="cta-strip"><div><span class="eyebrow">MULAI SEKARANG</span><h2>Siap menyerahkan cucianmu?</h2><p>Isi data pesanan dalam hitungan menit. Biaya di awal masih berupa perkiraan.</p></div><a class="button button-primary" href="pelanggan/index.php">Pesan laundry →</a></section>
<?php require 'includes/footer.php'; ?>
