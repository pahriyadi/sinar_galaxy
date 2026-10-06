# 📄 Standar Desain Page Preloader (Glassmorphism Paper White)

Dokumen ini mendokumentasikan spesifikasi implementasi **Page Preloader** (Layar Loading Halaman) pada SIM Pengumpulan ZIS BAZNAS Sumbawa. Tujuannya adalah memberikan transisi halaman yang mulus dan mencegah pengiriman form berulang (*double submit*) dengan gaya minimalis transparan modern.

---

## 1. Spesifikasi Visual & Interaksi

| Komponen | Spesifikasi |
|---|---|
| **Latar Belakang** | Semi-transparan putih `rgba(255, 255, 255, 0.6)` dengan efek blur kaca premium (`backdrop-filter: blur(4px)`). |
| **Ikon Utama** | Favicon logo resmi BAZNAS (lebar `48px`, tinggi `48px`) di bagian tengah. |
| **Spinner** | Lingkaran pemutar berputar melingkari logo dengan border atas berwarna hijau brand BAZNAS (`#0d9f4f`). |
| **Teks Utama** | Judul `BAZNAS SUMBAWA` (Hijau, `font-weight: 700`) diikuti dengan status loading *"Memuat data..."*. |
| **Efek Transisi** | Transisi keluar memudar (*fade-out*) selama `0.25s` sesudah resource halaman selesai dimuat penuh. |

---

## 2. Struktur HTML & PHP

Tampung kode HTML ini di awal elemen `<body>` pada berkas global header:

```html
<!-- Page Preloader (Paper White Minimalist Loading Screen) -->
<div id="page-preloader" class="page-preloader">
    <div class="preloader-spinner-wrapper">
        <div class="preloader-logo-container">
            <img src="<?= get_favicon_url() ?>" alt="Logo BAZNAS" class="preloader-logo-img">
            <div class="preloader-spinner"></div>
        </div>
        <div class="preloader-title">BAZNAS SUMBAWA</div>
        <div class="preloader-text">Memuat data...</div>
    </div>
</div>
```

---

## 3. Aturan CSS (Styling)

Gaya preloader didefinisikan secara global pada file `assets/css/custom.css`:

```css
/* ========================================
   PAGE PRELOADER - Paper White Style
   ======================================== */
.page-preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(255, 255, 255, 0.6); /* Transparan putih */
    backdrop-filter: blur(4px); /* Efek blur kaca premium */
    -webkit-backdrop-filter: blur(4px); /* Dukungan Safari */
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
    visibility: visible;
    transition: opacity 0.25s ease, visibility 0.25s ease;
}

.page-preloader.fade-out {
    opacity: 0;
    visibility: hidden;
}

.preloader-spinner-wrapper {
    text-align: center;
}

.preloader-logo-container {
    position: relative;
    width: 100px;
    height: 100px;
    margin: 0 auto 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preloader-logo-img {
    width: 48px;
    height: 48px;
    object-fit: contain;
    z-index: 2;
}

.preloader-spinner {
    position: absolute;
    width: 80px;
    height: 80px;
    border: 3px solid #e0e0e0;
    border-top: 3px solid #0d9f4f;
    border-radius: 50%;
    animation: preloader-spin 0.8s cubic-bezier(0.53, 0.21, 0.29, 0.86) infinite;
    z-index: 1;
}

.preloader-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0d9f4f;
    letter-spacing: 1px;
    margin-bottom: 0.25rem;
    font-family: "Source Sans Pro", sans-serif;
}

.preloader-text {
    font-size: 0.85rem;
    color: #666666;
    font-weight: 600;
    letter-spacing: 0.5px;
    font-family: "Source Sans Pro", sans-serif;
}

@keyframes preloader-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
```

---

## 4. Logika Javascript (Behavior)

Gunakan Javascript berikut untuk mengontrol kapan preloader dimuat, disembunyikan, dan dimunculkan kembali:

```javascript
// 1. Sembunyikan preloader saat halaman selesai di-load penuh
window.addEventListener('load', function() {
    const preloader = document.getElementById('page-preloader');
    if (preloader) {
        preloader.classList.add('fade-out');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 300);
    }
});

// 2. Tampilkan preloader saat form disubmit (mencegah double submit)
document.addEventListener('submit', function(e) {
    const form = e.target;
    // Kecualikan form download/target tab baru
    if (form.target === '_blank' || form.classList.contains('no-preloader')) {
        return;
    }
    const preloader = document.getElementById('page-preloader');
    if (preloader) {
        preloader.style.display = 'flex';
        preloader.offsetHeight; // memicu reflow
        preloader.classList.remove('fade-out');
    }
});

// 3. Tampilkan preloader saat mengklik tautan halaman internal (berpindah halaman)
document.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    if (!link) return;
    
    const href = link.getAttribute('href');
    const target = link.getAttribute('target');
    
    // Kecualikan anchor link internal, javascript actions, download, tab baru, & toggle dropdown
    if (!href || 
        href.startsWith('#') || 
        href.startsWith('javascript:') || 
        target === '_blank' || 
        link.hasAttribute('download') || 
        link.classList.contains('no-preloader') ||
        link.classList.contains('dropdown-toggle')
    ) {
        return;
    }
    
    const preloader = document.getElementById('page-preloader');
    if (preloader) {
        preloader.style.display = 'flex';
        preloader.offsetHeight; // memicu reflow
        preloader.classList.remove('fade-out');
    }
});
```
