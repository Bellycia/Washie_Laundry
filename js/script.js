document.addEventListener('DOMContentLoaded', function () {

  // ---------- Menu responsif (layar kecil) ----------
  const menuToggle = document.getElementById('menuToggle');
  const mainNav = document.getElementById('mainNav');
  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function () {
      const buka = mainNav.classList.toggle('is-open');
      menuToggle.setAttribute('aria-expanded', String(buka));
      menuToggle.textContent = buka ? '✕' : '☰';
    });
    mainNav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        mainNav.classList.remove('is-open');
        menuToggle.textContent = '☰';
        menuToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // ---------- Menu aktif mengikuti posisi scroll ----------
  if (mainNav) {
    const links = mainNav.querySelectorAll('a[href^="#"]');
    const sections = Array.from(links).map(function (a) {
      return document.getElementById(a.getAttribute('href').slice(1));
    }).filter(Boolean);

    function tandaiMenu() {
      let aktif = sections.length ? sections[0].id : '';
      sections.forEach(function (sec) {
        if (sec.getBoundingClientRect().top <= 140) aktif = sec.id;
      });
      links.forEach(function (a) {
        a.classList.toggle('active', a.getAttribute('href') === '#' + aktif);
      });
    }
    window.addEventListener('scroll', tandaiMenu, { passive: true });
    tandaiMenu();
  }

  // ---------- Form pemesanan: estimasi biaya ----------
  const form = document.getElementById('orderForm');
  if (form) {
    const kategori   = document.getElementById('kategori');
    const jumlah     = document.getElementById('jumlah');
    const layanan    = document.getElementById('layanan');
    const penyerahan = document.getElementById('penyerahan');
    const alamatBox  = document.getElementById('alamatBox');
    const satuan     = document.getElementById('satuan');

    const tarif   = { pakaian: 8000, sepatu: 25000, tas: 30000 };
    const unit    = { pakaian: 'kg', sepatu: 'pasang', tas: 'buah' };
    const pengali = { xpress: 1.8, reguler: 1, hemat: 0.8 };

    function rupiah(angka) {
      return 'Rp' + angka.toLocaleString('id-ID');
    }

    function hitungEstimasi() {
      const qty = Number(jumlah.value) || 0;
      const t = tarif[kategori.value] || 0;
      const p = pengali[layanan.value] || 0;

      const subtotal = Math.round(t * qty * p);
      const diskon = subtotal >= 100000 ? Math.round(subtotal * 0.1) : 0;
      const antar = penyerahan.value === 'antar_jemput' ? 10000 : 0;

      document.getElementById('estSubtotal').textContent = rupiah(subtotal);
      document.getElementById('estDiskon').textContent = diskon > 0 ? '- ' + rupiah(diskon) : 'Rp0';
      document.getElementById('estAntar').textContent = rupiah(antar);
      document.getElementById('estimasi').textContent = rupiah(subtotal - diskon + antar);
      satuan.textContent = unit[kategori.value] || '';
      tampilkanBatas(qty);
    }

    // Keterangan batas jumlah mengikuti satuan: kg / pasang / buah
    const jumlahInfo = document.getElementById('jumlahInfo');
    const BATAS = Number(jumlahInfo.dataset.batas) || 100;

    function teksBatas() {
      const u = unit[kategori.value];
      return 'Maksimal ' + BATAS + (u ? ' ' + u : '') + ' setiap pemesanan';
    }

    function tampilkanBatas(qty) {
      const lewat = qty > BATAS;
      jumlahInfo.textContent = teksBatas() + (lewat ? '.' : '');
      jumlahInfo.classList.toggle('field-error', lewat);
      jumlah.classList.toggle('has-error', lewat);
    }

    form.addEventListener('submit', function (e) {
      if ((Number(jumlah.value) || 0) > BATAS) {
        e.preventDefault();
        jumlah.focus();
      }
    });

    function aturAlamat() {
      alamatBox.hidden = penyerahan.value !== 'antar_jemput';
    }

    [kategori, jumlah, layanan, penyerahan].forEach(function (el) {
      el.addEventListener('input', hitungEstimasi);
      el.addEventListener('change', hitungEstimasi);
    });
    penyerahan.addEventListener('change', aturAlamat);
    aturAlamat();
    hitungEstimasi();
  }

  // Kalau ada hasil / error pesanan setelah submit, scroll ke sana
  const target = document.getElementById('hasil') || document.getElementById('pesanError');
  if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });

  // ---------- Profil: tampil/sembunyikan form ubah data + validasi ----------
  const profilForm = document.getElementById('profilForm');
  const btnUbah = document.getElementById('btnUbahProfil');
  const btnBatal = document.getElementById('btnBatalProfil');

  if (profilForm && btnUbah) {
    btnUbah.addEventListener('click', function () {
      profilForm.hidden = false;
      document.getElementById('p_no_hp').focus();
    });
    btnBatal.addEventListener('click', function () {
      profilForm.hidden = true;
    });

    const aturanProfil = {
      p_no_hp: function (v) {
        if (v !== '' && !/^[0-9]{10,13}$/.test(v)) return 'Nomor telp harus berupa angka 10-13 digit.';
        return '';
      },
      p_alamat: function (v) {
        if (v !== '' && v.length < 10) return 'Alamat minimal 10 karakter.';
        return '';
      }
    };

    function cekProfil(id) {
      const input = document.getElementById(id);
      const pesan = aturanProfil[id](input.value.trim());
      profilForm.querySelector('[data-for="' + id + '"]').textContent = pesan;
      input.classList.toggle('has-error', pesan !== '');
      return pesan === '';
    }

    Object.keys(aturanProfil).forEach(function (id) {
      document.getElementById(id).addEventListener('blur', function () { cekProfil(id); });
    });

    profilForm.addEventListener('submit', function (e) {
      let valid = true;
      Object.keys(aturanProfil).forEach(function (id) {
        if (!cekProfil(id)) valid = false;
      });
      if (!valid) {
        e.preventDefault();
        profilForm.querySelector('.has-error').focus();
      }
    });
  }

  // Notifikasi profil hilang sendiri
  const notifProfil = document.getElementById('notifProfil');
  if (notifProfil) setTimeout(function () { notifProfil.style.display = 'none'; }, 6000);
});
