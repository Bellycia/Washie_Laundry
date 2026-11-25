document.addEventListener('DOMContentLoaded', () => {
  // ---------- Menu responsif untuk layar kecil ----------
  const menuToggle = document.getElementById('menuToggle');
  const mainNav = document.getElementById('mainNav');
  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', () => {
      const isOpen = mainNav.classList.toggle('is-open');
      menuToggle.setAttribute('aria-expanded', String(isOpen));
      menuToggle.textContent = isOpen ? '✕' : '☰';
    });
  }

  // ---------- Form pemesanan (hanya ada di index.php) ----------
  const form = document.getElementById('orderForm');
  if (!form) return;

  const kategori = document.getElementById('kategori');
  const jumlah = document.getElementById('jumlah');
  const layanan = document.getElementById('layanan');
  const penyerahan = document.getElementById('penyerahan');
  const pickupFields = document.getElementById('pickupFields');
  const tanggalJemput = document.getElementById('tanggal_jemput');
  const waktuJemput = document.getElementById('waktu_jemput');
  const tanggalAntar = document.getElementById('tanggal_antar');
  const waktuAntar = document.getElementById('waktu_antar');
  const pickupInputs = [tanggalJemput, waktuJemput, tanggalAntar, waktuAntar];

  // Jam operasional dibaca dari atribut data-* yang diisi PHP (config.php), jadi hanya satu sumber aturan.
  const JAM_BUKA = form.dataset.buka || '08:00';
  const JAM_TUTUP = form.dataset.tutup || '20:00';
  const HARI_BUKA = (form.dataset.hariBuka || '1,2,3,4,5,6').split(',').map(Number);
  const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const jamLabel = jam => jam.replace(':', '.');

  const kategoriInfo = window.WASHIE_CATALOG?.kategori || {};
  const layananInfo = window.WASHIE_CATALOG?.layanan || {};
  const rupiah = value => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0
  }).format(value);

  const promoInfo = window.WASHIE_CATALOG?.promo || [];
  const unitHint = document.getElementById('unitHint');

  // ---------- Validasi jam & hari operasional ----------
  function todayString() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }
  function nowTimeString() {
    const d = new Date();
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  }
  function isoDay(dateStr) {            // 1 = Senin ... 7 = Minggu
    const day = new Date(dateStr + 'T00:00:00').getDay();
    return day === 0 ? 7 : day;
  }
  function setFieldError(input, message) {
    const box = document.getElementById('err_' + input.id);
    input.setCustomValidity(message);
    input.classList.toggle('has-error', message !== '');
    if (box) box.textContent = message;
  }

  function validateSchedule() {
    const isPickup = penyerahan.value === 'antar_jemput';
    pickupInputs.forEach(i => setFieldError(i, ''));
    if (!isPickup) return true;

    const dateRule = (input, label) => {
      if (!input.value) return;
      if (!HARI_BUKA.includes(isoDay(input.value))) {
        const hari = NAMA_HARI[new Date(input.value + 'T00:00:00').getDay()];
        setFieldError(input, `${hari} kami libur. Pilih hari Senin–Sabtu untuk ${label}.`);
      } else if (input.value < todayString()) {
        setFieldError(input, `Tanggal ${label} tidak boleh di masa lalu.`);
      }
    };
    const timeRule = (input, label) => {
      if (!input.value) return;
      if (input.value < JAM_BUKA || input.value > JAM_TUTUP) {
        setFieldError(input, `Di luar jam operasional. Pilih jam ${label} antara ${jamLabel(JAM_BUKA)} dan ${jamLabel(JAM_TUTUP)} WIB.`);
      }
    };
    dateRule(tanggalJemput, 'jemput');
    dateRule(tanggalAntar, 'antar');
    timeRule(waktuJemput, 'jemput');
    timeRule(waktuAntar, 'antar');

    // Jemput hari ini: jam tidak boleh sudah lewat.
    if (tanggalJemput.value === todayString() && waktuJemput.value && waktuJemput.validity.customError === false
        && waktuJemput.value <= nowTimeString()) {
      setFieldError(waktuJemput, 'Jam jemput hari ini sudah lewat. Pilih jam yang lebih akhir.');
    }
    // Antar tidak boleh sebelum jemput.
    if (tanggalJemput.value && tanggalAntar.value && tanggalAntar.value < tanggalJemput.value && !tanggalAntar.validationMessage) {
      setFieldError(tanggalAntar, 'Tanggal antar tidak boleh sebelum tanggal jemput.');
    }
    if (tanggalJemput.value && tanggalJemput.value === tanggalAntar.value && waktuJemput.value && waktuAntar.value
        && !waktuAntar.validationMessage && waktuAntar.value <= waktuJemput.value) {
      setFieldError(waktuAntar, 'Jam antar harus setelah jam jemput di hari yang sama.');
    }
    return pickupInputs.every(i => i.validity.valid);
  }

  // ---------- Tampilan jadwal & ringkasan biaya ----------
  function updatePickupFields() {
    const isPickup = penyerahan.value === 'antar_jemput';
    pickupFields.hidden = !isPickup;
    pickupInputs.forEach(input => {
      input.required = isPickup;
      input.disabled = !isPickup;
    });
    validateSchedule();
    updateSummary();
  }

  function updateSummary() {
    const item = kategoriInfo[kategori.value];
    const service = layananInfo[layanan.value];
    const count = Number(jumlah.value) || 0;
    const admin = penyerahan.value === 'antar_jemput' ? 10000 : 0;
    if (unitHint) {
      unitHint.textContent = item
        ? `Satuan: ${item.unit}. Berat pakaian sebenarnya ditimbang petugas.`
        : 'Pilih kategori untuk melihat satuan.';
    }

    // Hanya pratinjau; proses.php menghitung ulang di server.
    const laundry = item && service && count > 0 ? Math.round(item.tarif * count * service.multiplier) : 0;
    document.getElementById('summaryLaundry').textContent = rupiah(laundry);
    // Diskon: promo yang berlaku hari ini dengan potongan terbesar (sama dengan perhitungan di server).
    let diskon = 0, namaPromo = '';
    promoInfo.forEach(p => {
      if (laundry <= 0 || laundry < p.min) return;
      const d = Math.min(laundry, p.jenis === 'persen' ? Math.round(laundry * Math.min(100, p.nilai) / 100) : p.nilai);
      if (d > diskon) { diskon = d; namaPromo = p.nama; }
    });
    document.getElementById('summaryAdmin').textContent = rupiah(admin);
    document.getElementById('summaryDiskon').textContent = diskon > 0 ? '−' + rupiah(diskon) : rupiah(0);
    document.getElementById('summaryPromoNama').textContent = namaPromo ? ` (${namaPromo})` : '';
    document.getElementById('summaryTotal').textContent = rupiah(Math.max(0, laundry - diskon + admin));
  }

  [kategori, jumlah, layanan, penyerahan].forEach(el => {
    el.addEventListener('change', updateSummary);
    el.addEventListener('input', updateSummary);
  });
  penyerahan.addEventListener('change', updatePickupFields);
  pickupInputs.forEach(input => {
    input.addEventListener('input', validateSchedule);
    input.addEventListener('change', validateSchedule);
  });
  const sinkronAntar = () => {
    tanggalAntar.min = tanggalJemput.value || todayString();
    if (tanggalAntar.value && tanggalJemput.value && tanggalAntar.value < tanggalJemput.value) {
      tanggalAntar.value = tanggalJemput.value;
    }
    validateSchedule();
  };
  tanggalJemput.addEventListener('change', sinkronAntar);
  tanggalJemput.addEventListener('input', sinkronAntar);
  sinkronAntar();
  updatePickupFields();

  // Tombol kembali di browser bisa memulihkan halaman dengan tombol kirim yang masih nonaktif.
  window.addEventListener('pageshow', () => {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = false;
    const fb = form.querySelector('.inline-feedback');
    if (fb) fb.remove();
  });

  // ---------- Kirim form ----------
  form.addEventListener('submit', event => {
    const scheduleOk = validateSchedule();
    if (!scheduleOk || !form.checkValidity()) {
      event.preventDefault();
      const firstInvalid = form.querySelector(':invalid');
      if (firstInvalid) {
        firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
        firstInvalid.focus({preventScroll: true});
      }
      form.reportValidity();
      return;
    }
    const existing = form.querySelector('.inline-feedback');
    if (existing) existing.remove();
    const confirmation = document.createElement('p');
    confirmation.className = 'inline-feedback';
    confirmation.textContent = 'Pesananmu sedang dikirim...';
    form.appendChild(confirmation);
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;   // cegah klik ganda
  });
});
