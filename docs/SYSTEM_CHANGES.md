## Dokumentasi Perubahan Sistem (Sejak Tadi Malam)

### 1) Fitur Baru

- **Activity Log (Audit Trail, Login History)**
  - Mencatat semua aktivitas: login/logout, create/update/delete/view/export
  - Audit trail per-field untuk perubahan data (old_value vs new_value)
  - Login history (login/logout time, IP, user agent, status)
  - Statistik: total aktivitas, jenis aktivitas, top users
  - Akses halaman: hanya super admin

- **Backup Otomatis Log**
  - Backup harian H-1 otomatis ke JSON saat ada akses user pertama (ringan)
  - UI tombol backup manual (pilih tanggal) pada halaman Activity Log
  - Metadata backup dicatat di DB (`activity_log_backup`)

- **Backup Database & Situs ke Galaxy Drive**
  - Backup database ke `galaxy_drive/backups` via `mysqldump`
  - Fallback JSON export jika `mysqldump` tidak tersedia
  - Backup zip seluruh project (kecuali folder backup) ke `galaxy_drive/backups`
  - Akses aksi: hanya super admin

- **Kontrol Akses Edit/Hapus**
  - Semua operasi update*/delete* dibatasi untuk super admin
  - Pengecualian: `updatePelanggan` diperbolehkan untuk admin dan super admin
  - Notifikasi melayang ramah saat akses ditolak (tanpa error header)

- **Pintasan Clear Cache (Dashboard)**
  - Clear cache browser: Cache Storage, localStorage, sessionStorage + cache-busting reload
  - Clear cache server (super admin): reset OPcache/APCu/realpath cache

### 2) Struktur Database Baru

File: `activity_log.sql`

- `activity_log`
  - Kolom: id_log, user_id, username, nama_lengkap, role, activity_type, table_name, record_id, description, old_data, new_data, ip_address, user_agent, session_id, created_at

- `login_history`
  - Kolom: id_login, user_id, username, nama_lengkap, role, login_time, logout_time, ip_address, user_agent, session_id, status, failure_reason

- `audit_trail`
  - Kolom: id_audit, log_id (FK ke activity_log), field_name, old_value, new_value, change_type, created_at

- `system_settings`
  - Kolom: id_setting, setting_key, setting_value, description, updated_by, updated_at
  - Nilai awal: activity_log_enabled, log_retention_days, log_login_attempts, log_data_changes, dsb.

- `activity_log_backup`
  - Kolom: id_backup, backup_date, log_count, file_path, file_size, created_by, created_at

### 3) File Baru (Utama)

- Activity Log & UI
  - `assets/activity_logger.php` (kelas logger + statistik + clean + backup trigger)
  - `assets/logging_helper.php` (helper CRUD logging + resolve primary key dinamis)
  - `data_activity_log/activity_log_view.php` (halaman Activity Log)
  - `data_activity_log/get_audit_trail.php` (modal detail perubahan)
  - `data_activity_log/login_history.php` (halaman login history)

- Backup Log
  - `assets/backup/backup_utils.php` (backup JSON per tanggal, metadata ke DB)
  - `data_activity_log/backup_logs.php` (endpoint backup log manual)

- Backup Sistem (DB & Situs)
  - `assets/backup/system_backup.php` (mysqldump/JSON fallback, zip project)
  - `data_activity_log/system_backup.php` (endpoint UI super admin)

- Clear Cache Server
  - `assets/tools/clear_server_cache.php` (reset OPcache/APCu, super admin only)

### 4) File Diubah (Sorotan)

- `assets/fungsi.php`
  - Tambah logging CRUD untuk: pelanggan, pemesanan, pengiriman, pengeluaran, saldo awal
  - Guard akses: seluruh update*/delete* → super admin; `updatePelanggan` → admin/super admin
  - Notifikasi melayang untuk akses ditolak (tanpa mengubah header)

- `assets/session.php`
  - Panggil `runDailyBackupIfNeeded()` (backup H-1 jika belum ada)
  - Tetap update `last_activity`

- `inc/sidebar.php`
  - Tambah menu Activity Log + Login History (hanya super admin)

- `data_dashboard/dashboard.php`
  - Tambah tombol “Clear Cache Browser” (semua role)
  - Tambah tombol “Clear Cache Server” (super admin)
  - Script notifikasi melayang & handler pemanggilan endpoint server cache

- `data_activity_log/activity_log_view.php`
  - UI filter, statistik, tabel log, modal audit, tombol backup log, tombol backup DB/ZIP

### 5) Cara Menggunakan

1. **Import database**: jalankan `activity_log.sql` pada database aplikasi
2. **Activity Log** (super admin)
   - Sidebar → Activity Log → filter, lihat detail audit, gunakan tombol backup
3. **Backup DB & Site** (super admin)
   - Activity Log → tombol “Backup Database → Galaxy Drive” atau “Backup Site ZIP → Galaxy Drive”
   - Hasil: `galaxy_drive/backups/`
4. **Clear Cache**
   - Dashboard → “Clear Cache Browser” (paksa reload aset)
   - Dashboard → “Clear Cache Server” (reset OPcache/APCu)

### 6) Konfigurasi & Prasyarat

- Pastikan folder writable:
  - `galaxy_drive/backups`, `assets/backup/backups`
- `mysqldump` (untuk backup DB):
  - Otomatis deteksi jalur umum (Windows XAMPP/Linux)
  - Bisa override via env `MYSQLDUMP_PATH`
- Roles:
  - Super admin: semua akses
  - Admin: dapat `updatePelanggan`; lainnya read-only

### 7) Keamanan & Audit

- Seluruh aksi backup, clear cache server, dan CRUD penting tercatat pada Activity Log
- Akses ditolak ditampilkan sebagai notifikasi melayang dan tidak memodifikasi header
- Retensi dan pembersihan log tersedia melalui API logger (`cleanOldLogs`)

### 8) Pengujian Singkat

- Super admin: Activity Log (backup log, backup DB/Site), Dashboard (clear server cache)
- Admin: edit pelanggan di modul pelanggan (dapat), modul lain tidak bisa edit/delete (diblok)
- User biasa: akses Activity Log terblokir

### 9) Rencana Lanjutan (Opsional)

- RBAC granular per aksi (view/create/update/delete/export) + disable tombol UI sesuai role
- 2FA untuk super admin/admin
- Alert real-time (Telegram/Email) untuk event kritis
- Jadwal backup terenkripsi + UI restore
- Halaman System Settings terpusat (toggle log, retensi, schedule, rate limit, IP rules)

