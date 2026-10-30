<?php
// Set zona waktu lokal ke WITA (Sumbawa berada di WITA)
// PHP timezone
date_default_timezone_set('Asia/Makassar');
// Setting koneksi database
$host = "localhost";       // Sesuaikan
$user = "u314128537_demo";            // Sesuaikan
$pass = "kQR*8rgkdemo";                // Sesuaikan
$dbname = "u314128537_demo_projek";  // Sesuaikan

// Nonaktifkan mysqli reporting exception agar fallback dapat dieksekusi dengan mulus
mysqli_report(MYSQLI_REPORT_OFF);

// Mencoba koneksi dengan user utama
$conn = @new mysqli($host, $user, $pass, $dbname);

// Jika gagal dan di localhost, coba fallback ke user default XAMPP (root tanpa password)
if (($conn->connect_errno || $conn->connect_error) && in_array($host, ['localhost', '127.0.0.1'])) {
    $conn = @new mysqli($host, 'root', '', $dbname);
}

// Cek koneksi akhir
if ($conn->connect_error) {
    error_log("Database connection error: " . $conn->connect_error);
    die("Koneksi database gagal. Silakan hubungi administrator sistem.");
}

// Set character set UTF-8 (penting untuk data teks)
$conn->set_charset("utf8mb4");

// Set timezone untuk sesi MySQL ke +08:00 (WITA) agar NOW(), CURDATE(), dll sesuai WITA
@$conn->query("SET time_zone = '+08:00'");

/**
 * Hitung total pembayaran dari kolom JSON `payment_methods`.
 * Jika $startDate (dan opsional $endDate) diberikan maka hanya menghitung
 * pembayaran yang tanggalnya berada pada rentang tersebut.
 */
if (!function_exists('totalPembayaranJSON')) {
function totalPembayaranJSON($json, $startDate = null, $endDate = null) {
    $total = 0;
    if (!$json) return 0;
    $arr = json_decode($json, true);
    if (!is_array($arr)) return 0;
    foreach ($arr as $p) {
        $payDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : null;
        if ($startDate) {
            $sd = date('Y-m-d', strtotime($startDate));
            $ed = $endDate ? date('Y-m-d', strtotime($endDate)) : $sd;
            if ($payDate && ($payDate < $sd || $payDate > $ed)) {
                continue;
            }
        }
        $total += floatval($p['amount'] ?? 0);
    }
    return $total;
}
}

/**
 * Mengambil nilai konfigurasi sistem dari tabel `system_settings`.
 * Nilai di-cache dalam static memory agar tidak memicu multiple query dalam 1 request.
 * Dilengkapi auto-healing: otomatis membuat tabel dan menyuntikkan data default jika belum ada di database server.
 */
if (!function_exists('getSetting')) {
function getSetting($key, $default = '') {
    global $conn, $global_system_settings;
    
    if (!isset($global_system_settings) || $global_system_settings === null) {
        $global_system_settings = [];
        if ($conn && !$conn->connect_error) {
            $res = @$conn->query("SELECT setting_key, setting_value FROM system_settings");
            if (!$res) {
                // Auto-create tabel system_settings jika belum ada di database (misal baru deploy ke hosting)
                @$conn->query("CREATE TABLE IF NOT EXISTS `system_settings` (
                  `id_setting` int(11) NOT NULL AUTO_INCREMENT,
                  `setting_key` varchar(100) NOT NULL,
                  `setting_value` text NOT NULL,
                  `description` text DEFAULT NULL,
                  `updated_by` int(11) DEFAULT NULL,
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id_setting`),
                  UNIQUE KEY `setting_key` (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

                @$conn->query("INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
                  ('app_name', 'Sinar Galaxy Travel', 'Nama brand travel'),
                  ('app_tagline', 'Travel Eksekutif Sumbawa - Mataram', 'Slogan travel'),
                  ('app_logo', 'img/logo_custom_1791347038.png', 'Logo utama website'),
                  ('app_favicon', 'img/favicon_custom_1791347038.png', 'Favicon browser')");

                $res = @$conn->query("SELECT setting_key, setting_value FROM system_settings");
            }
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $global_system_settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }
    
    // Cerdas memilih fallback jika setting spesifik belum ada di database
    if (!isset($global_system_settings[$key]) || $global_system_settings[$key] === '') {
        $appRoot = dirname(__DIR__);
        if ($key === 'app_logo') {
            return file_exists($appRoot . '/img/logo_custom_1791347038.png') ? 'img/logo_custom_1791347038.png' : ($default ?: 'img/logo_sgt.png');
        }
        if ($key === 'app_favicon') {
            return file_exists($appRoot . '/img/favicon_custom_1791347038.png') ? 'img/favicon_custom_1791347038.png' : ($default ?: 'img/logo_sgt.png');
        }
        if ($key === 'app_name') {
            return !empty($default) && $default !== 'Sinar Galaxy' ? $default : 'Sinar Galaxy Travel';
        }
        if ($key === 'app_tagline') {
            return !empty($default) ? $default : 'Travel Eksekutif Sumbawa - Mataram';
        }
        return $default;
    }
    
    return $global_system_settings[$key];
}
}

/**
 * Menyimpan / memperbarui nilai konfigurasi sistem di tabel `system_settings`.
 */
if (!function_exists('updateSetting')) {
function updateSetting($key, $value, $userId = null) {
    global $conn, $global_system_settings;
    if (!$conn || $conn->connect_error) return false;
    
    $key = trim($key);
    $value = trim($value);
    
    // Pastikan tabel ada sebelum insert/update
    @$conn->query("CREATE TABLE IF NOT EXISTS `system_settings` (
      `id_setting` int(11) NOT NULL AUTO_INCREMENT,
      `setting_key` varchar(100) NOT NULL,
      `setting_value` text NOT NULL,
      `description` text DEFAULT NULL,
      `updated_by` int(11) DEFAULT NULL,
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id_setting`),
      UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at) 
                           VALUES (?, ?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()");
    if ($stmt) {
        $stmt->bind_param('ssi', $key, $value, $userId);
        $res = $stmt->execute();
        $stmt->close();
        
        // Perbarui cache memori aktif seketika
        if ($res) {
            if (!isset($global_system_settings) || !is_array($global_system_settings)) {
                $global_system_settings = [];
            }
            $global_system_settings[$key] = $value;
        }
        return $res;
    }
    return false;
}
}

/**
 * Cache Buster Helper: Otomatis mendeteksi modifikasi file (filemtime)
 * Mengharuskan browser mengunduh file CSS/JS terbaru tanpa cache lawas.
 */
if (!function_exists('asset_ver')) {
function asset_ver($relPath, $base = '') {
    $cleanRel = ltrim($relPath, '/');
    $realPath = dirname(__DIR__) . '/' . $cleanRel;
    $v = file_exists($realPath) ? filemtime($realPath) : '3.3.0';
    return $base . $cleanRel . '?v=' . $v;
}
}

/**
 * Auto-Migration Schema: Fitur Carter 1 Unit (v3.5.1)
 * Otomatis membuat 3 kolom baru di `data_travel` & `data_pemesanan` jika belum tersedia di database server.
 */
if (isset($conn) && !$conn->connect_error && getSystemSetting('schema_v351_carter_migrated', '0') !== '1') {
    $chk1 = @$conn->query("SHOW COLUMNS FROM `data_travel` LIKE 'biaya_jemput_carter'");
    if ($chk1 && $chk1->num_rows === 0) {
        @$conn->query("ALTER TABLE `data_travel` ADD COLUMN `biaya_jemput_carter` DECIMAL(15,2) NOT NULL DEFAULT 150000.00 AFTER `harga`");
    }
    $chk2 = @$conn->query("SHOW COLUMNS FROM `data_pemesanan` LIKE 'kode_group_carter'");
    if ($chk2 && $chk2->num_rows === 0) {
        @$conn->query("ALTER TABLE `data_pemesanan` ADD COLUMN `kode_group_carter` VARCHAR(50) DEFAULT NULL AFTER `keterangan`");
        @$conn->query("ALTER TABLE `data_pemesanan` ADD INDEX `idx_kode_group_carter` (`kode_group_carter`)");
    }
    $chk3 = @$conn->query("SHOW COLUMNS FROM `data_pemesanan` LIKE 'biaya_jemput_carter'");
    if ($chk3 && $chk3->num_rows === 0) {
        @$conn->query("ALTER TABLE `data_pemesanan` ADD COLUMN `biaya_jemput_carter` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `kode_group_carter`");
    }
    setSystemSetting('schema_v351_carter_migrated', '1', 0);
}
?>
