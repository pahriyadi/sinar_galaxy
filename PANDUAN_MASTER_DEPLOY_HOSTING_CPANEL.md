# 🚀 CETAK BIRU MASTER: PANDUAN DEPLOYMENT WEB APP & ERP KE CPANEL / HOSTING (GIT & TERMINAL)

Panduan ini adalah **Standar Operasional Prosedur (SOP) Universal** untuk mendeploy seluruh aplikasi web berbasis PHP / Framework (CodeIgniter 4, Laravel, Native) dari GitHub ke server hosting cPanel / Hostinger secara cepat, aman, dan tanpa downtime.

---

## 📑 DAFTAR ISI
1. [Struktur Berkas Konfigurasi Wajib](#1-struktur-berkas-konfigurasi-wajib)
2. [Setup Awal di Server Hosting Baru (Fresh Install)](#2-setup-awal-di-server-hosting-baru-fresh-install)
3. [Alur Update Rutin (Ongoing Deployment 3 Detik)](#3-alur-update-rutin-ongoing-deployment-3-detik)
4. [Otomatisasi Deployment via cPanel Git Version Control (`.cpanel.yml`)](#4-otomatisasi-deployment-via-cpanel-git-version-control-cpanelyml)
5. [Strategi Update Database Otomatis (Idempotent Seeders)](#5-strategi-update-database-otomatis-idempotent-seeders)
6. [Checklist Troubleshooting & Masalah Umum](#6-checklist-troubleshooting--masalah-umum)

---

## 1. STRUKTUR BERKAS KONFIGURASI WAJIB

Setiap project yang akan di-deploy ke cPanel wajib menyertakan berkas-berkas berikut:

### 1.1. Root `.htaccess` (Pengarah Traffic ke `/public`)
Diletakkan di direktori utama (*root*) project:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # 1. Lindungi berkas sensitif dari akses web langsung
    RewriteRule ^\.env - [F,L,NC]
    RewriteRule ^composer\.(json|lock) - [F,L,NC]

    # 2. Arahkan semua request ke folder public/
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L,QSA]
</IfModule>

# Blokir Directory Browsing
Options -Indexes
```

### 1.2. `public/.htaccess` (Handler Routing Framework)
Diletakkan di dalam folder `public/`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php/$1 [L,QSA]
</IfModule>
```

### 1.3. Berkas Konfigurasi Server (`.env`)
Dibuat di server hosting (disalin dari `.env.example`):
```ini
CI_ENVIRONMENT = production

app.baseURL = 'https://domainanda.com/'
app.indexPage = ''
app.forceGlobalSecureRequests = true
app.appTimezone = 'Asia/Makassar'

database.default.hostname = localhost
database.default.database = nama_cpanel_database
database.default.username = nama_cpanel_userdb
database.default.password = PasswordKuatDatabase123!
database.default.DBDriver = MySQLi
database.default.DBPrefix = 
database.default.port     = 3306
database.default.charset  = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci

logger.threshold = 4
```

### 1.4. Berkas `.cpanel.yml` (Untuk Auto-Deploy cPanel Git)
Diletakkan di root project repositori Git:
```yaml
---
deployment:
  tasks:
    - export DEPLOYPATH=/home/USERCPANEL/public_html/domainanda.com/
    - /bin/rsync -av --exclude='.git' --exclude='.cpanel.yml' --exclude='.env' * $DEPLOYPATH
```

---

## 2. SETUP AWAL DI SERVER HOSTING BARU (FRESH INSTALL)

Jika Anda ingin memasang sistem baru di domain/hosting baru, ikuti 5 langkah berikut:

### 🔹 Langkah 1: Buat Database di cPanel
1. Buka cPanel → menu **MySQL Databases**.
2. Buat Database baru (contoh: `u123456_erp`).
3. Buat User Database baru (contoh: `u123456_user`) beserta Password.
4. Tambahkan User ke Database dan centang **ALL PRIVILEGES**.

### 🔹 Langkah 2: Import Master Database SQL
1. Buka cPanel → menu **phpMyAdmin**.
2. Pilih database yang baru dibuat → klik menu **Import**.
3. Pilih file database master (contoh: `sawamawa_master_clean_install.sql`) → klik **Kirim / Go**.

### 🔹 Langkah 3: Clone Repositori Git ke Hosting
Buka cPanel → menu **Terminal**, jalankan:
```bash
cd /home/USERCPANEL/public_html/
git clone https://github.com/USERNAME/NAMA-REPO.git folder-project
cd folder-project
```

### 🔹 Langkah 4: Buat File `.env`
```bash
cp .env.example .env
nano .env   # atau edit melalui File Manager cPanel
```
Sesuaikan URL `app.baseURL` dan kredensial database yang telah dibuat.

### 🔹 Langkah 5: Set Izin Folder Writable
```bash
chmod -R 775 writable/
```

---

## 3. ALUR UPDATE RUTIN (ONGOING DEPLOYMENT 3 DETIK)

### 💻 Tahap 1: Di Terminal Komputer Lokal (VS Code / PowerShell)
```bash
git add .
git commit -m "feat: nama pembaruan fitur"
git push origin main
```

### 🌐 Tahap 2: Di Terminal cPanel Hosting (Cukup 1 Baris Perintah)
```bash
cd /home/USERCPANEL/public_html/folder-project && git pull origin main && php spark db:seed NamaSeederUpdate
```
*(Ganti nama seeder jika ada pembaruan skema database, atau cukup `git pull origin main` jika hanya perubahan kodingan).*

---

## 4. OTOMATISASI DEPLOYMENT VIA CPANEL GIT VERSION CONTROL (`.cpanel.yml`)

Jika ingin deploy otomatis via tombol di cPanel:
1. Pastikan file `.cpanel.yml` ada di root project.
2. Buka cPanel → **Git™ Version Control** → Daftarkan repositori GitHub Anda.
3. Saat ada perubahan di GitHub, masuk ke cPanel Git → klik **Manage** → klik **Pull or Deploy** → klik **Update from Remote** lalu **Deploy HEAD Commit**.

---

## 5. STRATEGI UPDATE DATABASE OTOMATIS (IDEMPOTENT SEEDERS)

Agar database server tidak error saat ada penambahan data/tabel baru:
1. **Seeder Aman (*Idempotent*)**: Seeder selalu memeriksa keberadaan data terlebih dahulu (`where('code')`), sehingga tidak akan menduplikasi data yang sudah ada saat dijalankan berulang kali.
2. **Auto-Hook ke Web Admin**: Seeder otomatis didaftarkan ke tombol **"Sinkronisasi Data"** di menu **Sistem & Pengaturan → Database & Backup**. Admin dapat memperbarui database cukup dengan 1 klik dari browser.

---

## 6. CHECKLIST TROUBLESHOOTING & MASALAH UMUM

| Kendala | Penyebab | Solusi |
|---|---|---|
| **Error 500 / Layar Putih** | Versi PHP di bawah 8.1 atau ekstensi PHP belum aktif | Buka cPanel → **Select PHP Version** → Pilih **PHP 8.1 / 8.2** → Centang ekstensi: `intl`, `mbstring`, `mysqli`, `curl`, `gd`, `fileinfo`, `json`. |
| **Error 404 pada Submenu** | Mod Rewrite belum aktif atau `.htaccess` hilang | Pastikan berkas `.htaccess` ada di root dan di dalam folder `public/`. Pastikan `app.indexPage = ''` di `.env`. |
| **Session Logout Sendiri / Error Log** | Folder `writable/` tidak bisa ditulis | Jalankan `chmod -R 775 writable/` di terminal cPanel. |
| **Gagal `git pull` (Conflict)** | Ada file yang diedit manual di server | Jalankan `git stash && git pull origin main`. |
| **Tampilan CSS / JS Rusak** | URL `app.baseURL` salah protokol | Pastikan di `.env` memakai `https://` dan diakhiri `/` (contoh: `https://domainanda.com/`). |
