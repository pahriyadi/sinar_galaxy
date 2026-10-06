# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2025-08-09

### Added
- Activity Log system (audit trail per field, login history, statistics)
- Automatic log backup (daily H-1) + manual backup by date (JSON)
- System backups to Galaxy Drive: Database (mysqldump/JSON fallback) and Site ZIP
- Access control: all edit/delete restricted to Super Admin (except update Pelanggan: Admin allowed)
- Floating notification for access denied (no header warnings)
- Dashboard shortcuts: Clear Browser Cache (all roles) and Clear Server Cache (Super Admin)

### Changed
- Sidebar: Added Activity Log and Login History (Super Admin only)
- Integrated CRUD logging for Pelanggan, Pemesanan, Pengiriman, Pengeluaran, Saldo Awal
- Session: daily backup trigger and last_activity update

### Migration
- Run `activity_log.sql` to add new tables: `activity_log`, `login_history`, `audit_trail`, `system_settings`, `activity_log_backup`.
- Ensure write permissions: `galaxy_drive/backups`, `assets/backup/backups`.

### Notes
- If `mysqldump` is unavailable, DB backup falls back to JSON export.
- Backups are stored under `galaxy_drive/backups`.

## [1.1.0] - 2025-08-08

### Added
- UI enhancements (navbar/sidebar), layout refinements, responsive improvements.

### Changed
- Sidebar and Navbar structure/styling for improved UX.

## [1.0.0] - 2025-08-01

### Initial
- Core modules: Pelanggan, Pemesanan, Pengiriman, Pengeluaran, Dashboard & Reports.

---

## Screenshots

Please place screenshots in `docs/screenshots/` and reference them here:
- Activity Log main page: `docs/screenshots/activity_log.png`
- Audit detail modal: `docs/screenshots/audit_detail.png`
- Login History: `docs/screenshots/login_history.png`
- Backup controls (Activity Log): `docs/screenshots/backup_controls.png`
- Dashboard cache buttons: `docs/screenshots/dashboard_cache.png`

## Links
- UI Flow: see `docs/UI_FLOW.md`

