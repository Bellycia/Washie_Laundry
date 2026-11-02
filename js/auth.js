// [JS] Validasi form daftar & masuk (sisi browser). Validasi PHP tetap jadi pengaman utama.
document.addEventListener('DOMContentLoaded', function () {

  const form = document.getElementById('authForm');
  if (!form) return;
  const modeDaftar = form.dataset.mode === 'register';

  const aturan = {
    username: function (v) {
      if (v === '') return 'Username wajib diisi.';
      if (!/^[a-zA-Z0-9_.]{4,30}$/.test(v)) return 'Username 4-30 karakter: huruf, angka, titik, atau underscore.';
      return '';
    },
    identitas: function (v) {
      if (v === '') return 'Username atau email wajib diisi.';
      return '';
    },
    email: function (v) {
      if (v === '') return 'Email wajib diisi.';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Format email tidak valid.';
      return '';
    },
    alamat: function (v) {
      if (v === '') return 'Alamat wajib diisi.';
      if (v.length < 10) return 'Alamat minimal 10 karakter.';
      return '';
    },
    password: function (v) {
      if (v === '') return 'Password wajib diisi.';
      if (modeDaftar && v.length < 6) return 'Password minimal 6 karakter.';
      return '';
    }
  };

  // Hanya cek kolom yang ada di form ini
  const fields = Object.keys(aturan).filter(function (n) { return document.getElementById(n); });

  function cekField(nama) {
    const input = document.getElementById(nama);
    const nilai = nama === 'password' ? input.value : input.value.trim();
    const pesan = aturan[nama](nilai);
    form.querySelector('[data-for="' + nama + '"]').textContent = pesan;
    input.classList.toggle('has-error', pesan !== '');
    return pesan === '';
  }

  fields.forEach(function (nama) {
    const input = document.getElementById(nama);
    input.addEventListener('blur', function () { cekField(nama); });
    input.addEventListener('input', function () {
      if (input.classList.contains('has-error')) cekField(nama);
    });
  });

  form.addEventListener('submit', function (e) {
    let valid = true;
    fields.forEach(function (nama) {
      if (!cekField(nama)) valid = false;
    });
    if (!valid) {
      e.preventDefault();
      form.querySelector('.has-error').focus();
    }
  });

  // Notifikasi sukses hilang sendiri setelah 6 detik
  const notif = document.getElementById('notifSukses');
  if (notif) setTimeout(function () { notif.style.display = 'none'; }, 6000);
});
