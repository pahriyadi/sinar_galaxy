# 📄 Panduan Desain Universal — "Putih di Atas Kertas" (Paper White Design System)

> **Versi**: 2.0 — Terakhir diperbarui: 12 Juli 2026
> **Framework**: AdminLTE 3 + Bootstrap 4
> **Konsep**: Simpel, Klasik, Cepat, Bersih, Minimalis, Elegan

Dokumen ini mendokumentasikan **sistem desain visual universal** yang dapat diterapkan ke website manapun berbasis AdminLTE / Bootstrap 4. Seluruh gaya didefinisikan dalam satu file CSS global (`assets/css/custom.css`) sehingga mudah dipindahkan antar proyek.

---

## 1. Filosofi Desain

| Prinsip | Penjelasan |
|---------|------------|
| **Serba Putih Bersih** | Semua elemen berlatar putih (`#ffffff`). Tidak ada warna latar selain putih. |
| **Border Abu-Abu Lembut** | Border `1px solid #b8b8b8` digunakan secara seragam di semua komponen. |
| **Flat / Tanpa Bayangan** | `box-shadow: none` di semua elemen. Tidak ada efek shadow, gradient, atau animasi berat. |
| **Grid Tegas seperti Excel** | Garis pembatas tabel terlihat jelas namun tidak mencolok, mirip lembar kerja spreadsheet. |
| **Hover Hijau Lembut** | Baris tabel yang dihover berubah menjadi hijau muda tipis (`#f0f7f3`) sebagai aksen brand. |
| **Konsistensi Total** | Sidebar, header, footer, card, tabel, modal, tombol, input — semua mengikuti aturan yang sama. |

---

## 2. Palet Warna

| Token | Hex | Kegunaan |
|-------|-----|----------|
| `--putih` | `#ffffff` | Latar belakang semua elemen |
| `--border-utama` | `#b8b8b8` | Border card, sidebar, header, footer, input, modal |
| `--border-header-tabel` | `#b0b0b0` | Border header tabel (th) |
| `--border-luar-tabel` | `#a0a0a0` | Border terluar tabel dan garis tfoot |
| `--border-menu` | `#d3d3d3` | Border setiap item menu sidebar |
| `--border-submenu` | `#e0e0e0` | Border sub-menu (lebih pudar) |
| `--teks-utama` | `#333333` | Teks heading, label, judul modal |
| `--teks-konten` | `#444444` | Teks isi tabel, menu, body |
| `--teks-sekunder` | `#666666` | Teks ikon, label role user |
| `--teks-placeholder` | `#999999` | Placeholder input |
| `--hover-tabel` | `#f0f7f3` | Warna hover baris tabel (hijau muda) |
| `--hover-menu` | `#f5f5f5` | Warna hover item menu sidebar |
| `--aktif-bg` | `#e6f4ea` | Latar menu aktif (hijau brand lembut) |
| `--aktif-teks` | `#076e34` | Teks dan ikon menu aktif |
| `--aktif-border` | `#0d9f4f` | Border menu aktif (hijau brand) |
| `--focus-border` | `#0d9f4f` | Border input saat fokus |
| `--focus-glow` | `rgba(13,159,79,0.15)` | Glow tipis input saat fokus |

---

## 3. Spesifikasi Komponen

### A. Kartu / Card (`.card`)
```css
.card {
    box-shadow: none !important;
    border: 1px solid #b8b8b8 !important;
    background-color: #ffffff !important;
    border-radius: 4px !important;
}
.card-header {
    background-color: #ffffff !important;
    border-bottom: 1px solid #b8b8b8 !important;
}
.card-body {
    background-color: #ffffff !important;
}
```

### B. Tabel (`.table` & `table.dataTable`)
```css
/* Base: border-collapse collapse wajib pada semua tabel termasuk DataTables */
.table,
.table-excel,
table.dataTable {
    border-collapse: collapse !important;
    background-color: #ffffff !important;
    width: 100% !important;
}

/* Outer border: dipasang langsung pada table agar menyatu/collapse dengan border td */
.table,
.table-excel,
table.dataTable {
    border: 1.5px solid #a0a0a0 !important;
}

/* Wrapper DataTables: hilangkan border agar tidak dobel dengan border table */
.dataTables_wrapper {
    border: none !important;
    margin-bottom: 1rem !important;
}

/* Header tabel (thead-dark & thead-light dipaksa putih bersih) */
.table thead th,
.table-excel thead th,
table.dataTable thead th,
table.dataTable thead td,
thead.thead-dark th,
thead.thead-light th {
    background-color: #ffffff !important;
    color: #333333 !important;
    border: 1px solid #b0b0b0 !important;
    font-weight: 600 !important;
    padding: 8px 10px !important;
    text-align: center !important;
}

/* Sel body */
.table tbody tr td,
table.dataTable tbody tr td {
    background-color: #ffffff !important;
    border: 1px solid #b8b8b8 !important;
    padding: 7px 10px !important;
    color: #444444 !important;
}

/* ANTI-ZEBRA: matikan striping bawaan Bootstrap/DataTables di CSS */
.table-striped > tbody > tr:nth-of-type(odd) > *,
.table-striped > tbody > tr:nth-of-type(even) > *,
table.dataTable tbody tr.odd > *,
table.dataTable tbody tr.even > *,
table.dataTable tbody td.sorting_1 {
    background-color: #ffffff !important;
    background: #ffffff !important;
}

/* HOVER: satu-satunya penanda baris aktif */
.table tbody tr:hover td,
table.dataTable tbody tr:hover td {
    background-color: #f0f7f3 !important;
    transition: background-color 0.15s ease-in-out !important;
}
```

### C. Form Input (`.form-control`, `.custom-select`, `textarea`)
```css
.form-control, .custom-select, textarea.form-control {
    border: 1px solid #b8b8b8 !important;
    border-radius: 4px !important;
    background-color: #ffffff !important;
    color: #333333 !important;
    box-shadow: none !important;
}
.form-control:focus, .custom-select:focus {
    border-color: #0d9f4f !important;
    box-shadow: 0 0 0 0.15rem rgba(13, 159, 79, 0.15) !important;
}
/* Select2 */
.select2-container--bootstrap4 .select2-selection {
    border: 1px solid #b8b8b8 !important;
}
/* Input group addon */
.input-group-text {
    border: 1px solid #b8b8b8 !important;
    background-color: #f8f9fa !important;
}
/* Label */
label { color: #333333 !important; font-weight: 600 !important; }
```

### D. Modal (`.modal-content`)
```css
.modal-content {
    background-color: #ffffff !important;
    border: 1px solid #b8b8b8 !important;
    border-radius: 4px !important;
    box-shadow: none !important;
}
.modal-header {
    background-color: #ffffff !important;
    border-bottom: 1px solid #b8b8b8 !important;
}
.modal-header .modal-title {
    font-weight: 600 !important;
    color: #333333 !important;
}
.modal-body {
    background-color: #ffffff !important;
    color: #444444 !important;
}
.modal-footer {
    background-color: #ffffff !important;
    border-top: 1px solid #b8b8b8 !important;
}
```

### E. Tombol / Button (`.btn`)
```css
.btn {
    border-radius: 4px !important;
    box-shadow: none !important;
    font-weight: 500 !important;
    transition: all 0.2s ease-in-out !important;
}
/* Secondary / Cancel → abu-abu terang */
.btn-secondary {
    background-color: #f8f9fa !important;
    border: 1px solid #b8b8b8 !important;
    color: #444444 !important;
}
/* Default / Light → putih berbingkai */
.btn-default, .btn-light {
    background-color: #ffffff !important;
    border: 1px solid #b8b8b8 !important;
    color: #444444 !important;
}
```

### F. Sidebar
```css
.main-sidebar {
    background-color: #ffffff !important;
    border-right: 1px solid #b8b8b8 !important;
    box-shadow: none !important;
}
.brand-link, .user-panel {
    border-bottom: 1px solid #b8b8b8 !important;
    background-color: #ffffff !important;
}
/* Setiap menu berbingkai */
.nav-sidebar .nav-item .nav-link {
    border: 1px solid #d3d3d3 !important;
    border-radius: 4px !important;
    margin-bottom: 6px !important;
    background-color: #ffffff !important;
    color: #444444 !important;
}
/* Sub-menu lebih pudar */
.nav-sidebar .nav-treeview .nav-item .nav-link {
    padding-left: 2rem !important;
    border-color: #e0e0e0 !important;
}
/* Menu aktif */
.nav-sidebar > .nav-item > .nav-link.active {
    background-color: #e6f4ea !important;
    color: #076e34 !important;
    border: 1px solid #0d9f4f !important;
    border-left: 3px solid #0d9f4f !important;
}
/* Menu hover */
.nav-sidebar .nav-link:hover {
    background-color: #f5f5f5 !important;
    border-color: #b8b8b8 !important;
}
```

### G. Header Navbar
```css
.main-header {
    background-color: #ffffff !important;
    border-bottom: 1px solid #b8b8b8 !important;
    box-shadow: none !important;
}
```

### H. Footer
```css
.main-footer {
    background-color: #ffffff !important;
    border-top: 1px solid #b8b8b8 !important;
    box-shadow: none !important;
}
```

### I. Dashboard Card / Small Box
```css
.small-box {
    background-color: #ffffff !important;
    border: 1px solid #b8b8b8 !important;
    box-shadow: none !important;
}
.small-box > .inner h3 { color: #333333 !important; }
.small-box > .inner p  { color: #666666 !important; }
/* Override semua bg- variant */
.small-box.bg-primary, .small-box.bg-success,
.small-box.bg-info, .small-box.bg-warning {
    background-color: #ffffff !important;
}
/* Ikon transparan halus */
.small-box > .icon i { color: rgba(0,0,0,0.08) !important; }
/* Footer link */
.small-box .small-box-footer {
    background-color: #f8f9fa !important;
    color: #555555 !important;
    border-top: 1px solid #b8b8b8 !important;
}
```

### J. Alert Ringkasan (`.alert-excel`)
```css
.alert-excel {
    background-color: #ffffff !important;
    border: 1px solid #b8b8b8 !important;
    color: #333333 !important;
    border-left: 4px solid #007bff !important;
    box-shadow: none !important;
}
```

---

## 4. Penerapan HTML

### Sidebar
```html
<aside class="main-sidebar sidebar-light-success border-right elevation-0">
```

### Card
```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title font-weight-bold">Judul</h3>
    </div>
    <div class="card-body">
        <!-- konten -->
    </div>
</div>
```

### Tabel
```html
<table class="table table-bordered table-hover">
    ...
</table>
```

### Alert Ringkasan
```html
<div class="alert alert-info alert-excel mb-4 shadow-none">
    <h5><i class="icon fas fa-info-circle"></i> Ringkasan</h5>
    ...
</div>
```

---

## 5. Cara Menerapkan ke Proyek Baru

1. **Salin file** `assets/css/custom.css` ke proyek baru.
2. **Muat di header** setelah AdminLTE CSS:
   ```html
   <link rel="stylesheet" href="assets/css/custom.css">
   ```
3. **Ubah class sidebar** pada `<aside>` menjadi:
   ```
   sidebar-light-success border-right elevation-0
   ```
4. **Ubah label role user** dari `text-light` menjadi `text-muted`.
5. **Selesai!** — Seluruh elemen otomatis mengikuti desain putih bersih.

---

## 6. Kustomisasi Warna Brand

Untuk mengubah warna aksen (hijau BAZNAS → warna lain), cukup ubah variabel CSS di bagian `:root`:

```css
:root {
    --baznas-green: #0d9f4f;      /* Warna brand utama */
    --baznas-green-dark: #076e34;  /* Warna brand gelap (tombol) */
}
```

Lalu cari-ganti nilai `#0d9f4f` dan `#076e34` di seluruh file CSS dengan warna brand baru Anda.

---

> **Catatan**: Desain ini sengaja menggunakan `!important` secara intensif untuk memastikan override yang konsisten di atas framework AdminLTE / Bootstrap 4 yang memiliki banyak rule bawaan.

---

TIP
Jangan lupa Anda dapat menggunakan perintah /learn jika sewaktu-waktu ingin menyimpan preferensi desain "Putih Kertas" ini agar asisten AI kami selalu mengikutinya pada sesi coding Anda berikutnya.