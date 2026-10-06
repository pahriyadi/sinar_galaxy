# Sistem Activity Log - Galaxy Travel

## Overview
Sistem Activity Log adalah fitur untuk melacak dan mencatat semua aktivitas pengguna dalam sistem Galaxy Travel. Fitur ini hanya dapat diakses oleh **Super Admin** untuk keamanan dan privasi.

## Fitur Utama

### 1. Pelacakan Aktivitas (Activity Tracking)
- **Login/Logout**: Mencatat setiap kali user login dan logout
- **CRUD Operations**: Mencatat create, read, update, delete pada semua tabel
- **Data Changes**: Menyimpan data lama dan baru untuk audit trail
- **IP Address**: Mencatat IP address pengguna
- **User Agent**: Mencatat browser dan device yang digunakan
- **Session ID**: Mencatat session untuk tracking

### 2. Login History
- **Login Time**: Waktu login yang tepat
- **Logout Time**: Waktu logout (jika ada)
- **Duration**: Durasi session
- **Status**: Success atau Failed
- **Failure Reason**: Alasan kegagalan login

### 3. Audit Trail
- **Field Changes**: Perubahan per field
- **Old Value**: Nilai sebelum perubahan
- **New Value**: Nilai setelah perubahan
- **Change Type**: Added, Modified, atau Deleted

### 4. Statistics Dashboard
- **Total Activities**: Jumlah total aktivitas
- **Total Logins**: Jumlah total login
- **Active Users**: User yang aktif
- **Activity Types**: Jenis aktivitas yang dilakukan

## Struktur Database

### 1. activity_log
```sql
CREATE TABLE `activity_log` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('super admin','admin','users') NOT NULL,
  `activity_type` enum('login','logout','create','update','delete','view','export','import','print') NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `old_data` longtext DEFAULT NULL,
  `new_data` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`)
);
```

### 2. login_history
```sql
CREATE TABLE `login_history` (
  `id_login` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('super admin','admin','users') NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `failure_reason` text DEFAULT NULL,
  PRIMARY KEY (`id_login`)
);
```

### 3. audit_trail
```sql
CREATE TABLE `audit_trail` (
  `id_audit` int(11) NOT NULL AUTO_INCREMENT,
  `log_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `change_type` enum('added','modified','deleted') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_audit`),
  FOREIGN KEY (`log_id`) REFERENCES `activity_log` (`id_log`) ON DELETE CASCADE
);
```

### 4. system_settings
```sql
CREATE TABLE `system_settings` (
  `id_setting` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_setting`),
  UNIQUE KEY `setting_key` (`setting_key`)
);
```

## File-File Utama

### 1. assets/activity_logger.php
Class utama untuk menangani semua operasi logging:
- `logActivity()`: Log aktivitas umum
- `logLogin()`: Log login activity
- `logLogout()`: Log logout activity
- `getActivityLogs()`: Ambil data activity log
- `getLoginHistory()`: Ambil data login history
- `getAuditTrail()`: Ambil data audit trail
- `getStatistics()`: Ambil statistik

### 2. assets/logging_helper.php
Helper functions untuk kemudahan penggunaan:
- `logCreate()`: Log create activity
- `logUpdate()`: Log update activity
- `logDelete()`: Log delete activity
- `logView()`: Log view activity
- `logExport()`: Log export activity
- `logPrint()`: Log print activity

### 3. data_activity_log/activity_log_view.php
Halaman utama untuk melihat Activity Log (Super Admin only)

### 4. data_activity_log/login_history.php
Halaman untuk melihat Login History (Super Admin only)

### 5. data_activity_log/get_audit_trail.php
File untuk menampilkan detail audit trail

## Cara Penggunaan

### 1. Setup Database
```sql
-- Import file activity_log.sql ke database
source activity_log.sql;
```

### 2. Include di File yang Perlu Logging
```php
require_once '../assets/logging_helper.php';

// Log create activity
logCreate('data_pelanggan', $id, 'Menambah pelanggan baru', $new_data);

// Log update activity
$old_data = getOldData('data_pelanggan', $id);
logUpdate('data_pelanggan', $id, 'Update data pelanggan', $old_data, $new_data);

// Log delete activity
$old_data = getOldData('data_pelanggan', $id);
logDelete('data_pelanggan', $id, 'Hapus data pelanggan', $old_data);
```

### 3. Auto-Logging
Sistem sudah terintegrasi dengan `session.php` untuk auto-logging:
- Dashboard access
- Activity log access
- Login history access

## Konfigurasi

### System Settings
```sql
-- Enable/disable activity logging
UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'activity_log_enabled';

-- Set retention period (days)
UPDATE system_settings SET setting_value = '365' WHERE setting_key = 'log_retention_days';

-- Enable/disable specific logging
UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'log_login_attempts';
UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'log_data_changes';
UPDATE system_settings SET setting_value = '0' WHERE setting_key = 'log_page_views';
```

## Keamanan

### 1. Access Control
- Hanya Super Admin yang dapat mengakses Activity Log
- Session validation untuk setiap request
- IP address tracking untuk security audit

### 2. Data Protection
- Sensitive data tidak disimpan dalam log
- Data dienkripsi dalam database
- Retention policy untuk menghapus log lama

### 3. Privacy
- User biasa tidak dapat melihat log aktivitas mereka
- Hanya admin yang dapat melihat log sistem
- Log dapat dihapus sesuai kebijakan perusahaan

## Maintenance

### 1. Clean Old Logs
```php
$logger = new ActivityLogger($conn);
$logger->cleanOldLogs();
```

### 2. Backup Logs
```sql
-- Backup activity logs
INSERT INTO activity_log_backup (backup_date, log_count, file_path, created_by)
SELECT CURDATE(), COUNT(*), '/backup/logs_' || CURDATE() || '.sql', 1
FROM activity_log
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### 3. Performance Optimization
```sql
-- Add indexes for better performance
ALTER TABLE activity_log ADD INDEX idx_created_at (created_at);
ALTER TABLE activity_log ADD INDEX idx_user_id (user_id);
ALTER TABLE activity_log ADD INDEX idx_activity_type (activity_type);
ALTER TABLE login_history ADD INDEX idx_login_time (login_time);
```

## Monitoring

### 1. Daily Monitoring
- Check failed login attempts
- Monitor unusual activity patterns
- Review high-volume operations

### 2. Weekly Reports
- Generate activity summary
- Identify top users
- Track system usage

### 3. Monthly Analysis
- Performance analysis
- Security audit
- Compliance review

## Troubleshooting

### 1. Logs Not Being Created
- Check if logging is enabled in system_settings
- Verify database connection
- Check user permissions

### 2. Performance Issues
- Add database indexes
- Implement log rotation
- Optimize queries

### 3. Storage Issues
- Implement log retention policy
- Set up automated cleanup
- Monitor disk space

## Best Practices

### 1. Security
- Regularly review access logs
- Monitor failed login attempts
- Implement alert system for suspicious activity

### 2. Performance
- Regular database maintenance
- Optimize log queries
- Implement log rotation

### 3. Compliance
- Follow data retention policies
- Regular audit reviews
- Document all changes

## Integration

### 1. Existing System
- Minimal changes to existing code
- Backward compatible
- Non-intrusive implementation

### 2. Future Enhancements
- Real-time monitoring
- Alert system
- Advanced analytics
- Export functionality

## Support

Untuk bantuan teknis atau pertanyaan tentang sistem Activity Log, silakan hubungi tim IT atau administrator sistem. 