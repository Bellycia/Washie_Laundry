<?php
require_once __DIR__ . '/../config.php';
roleWajib('pelanggan'); $pageTitle = 'Tentang Kami'; require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro"><span class="eyebrow">KENALI WASHIE</span><h1>Tentang Washie Laundry</h1><p>Kenali layanan kami, jam buka, dan ketentuan sebelum membuat pesanan.</p></section>
<div class="banner"><img src="../assets/toko.svg" alt="Ilustrasi gerai Washie Laundry dengan mesin cuci dan keranjang" width="1100" height="320" loading="lazy"></div>
<div class="content-grid">
  <article class="panel"><span class="panel-icon">✦</span><h2>Profil Washie</h2><p>Washie adalah layanan laundry berbasis web yang memudahkan kamu memesan laundry pakaian, sepatu, dan tas, memilih kecepatan layanan, lalu memantau statusnya tanpa perlu bolak-balik bertanya.</p><p class="muted">Kami percaya cucian bersih tidak perlu ribet: tarif jelas, berat ditimbang, dan tagihan baru dikirim setelah dikonfirmasi.</p></article>
  <article class="panel"><h2>Kontak & lokasi</h2><ul class="detail-list"><li><strong>Alamat</strong><span>Batam, Kepulauan Riau</span></li><li><strong>Telepon</strong><a href="tel:+6281234567890">0812-3456-7890</a></li><li><strong>Jam operasional</strong><span>Senin–Sabtu, 08.00–20.00 WIB</span></li><li><strong>Hari libur</strong><span>Minggu</span></li><li><strong>Email</strong><a href="mailto:halo@washie.example">halo@washie.example</a></li></ul></article>
</div>
<section class="panel terms-panel"><h2>Ketentuan layanan</h2><ol><li>Biaya awal adalah perkiraan; berat dan kondisi barang diperiksa petugas terlebih dahulu.</li><li>Tagihan final baru muncul setelah petugas menyimpan hasil penimbangan dan biaya.</li><li>Jadwal jemput dan antar hanya bisa dipilih pada jam operasional (Senin–Sabtu, 08.00–20.00 WIB) dan akan dikonfirmasi petugas.</li><li>Periksa saku pakaian dan tulis noda atau bahan khusus pada kolom catatan.</li><li>Pembayaran dikonfirmasi petugas sesuai metode yang kamu pilih; pembayaran online otomatis belum tersedia.</li></ol></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
