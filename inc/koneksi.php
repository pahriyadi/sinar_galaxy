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
 */
if (!function_exists('getSetting')) {
function getSetting($key, $default = '') {
    global $conn;
    static $cached_settings = null;
    
    if ($cached_settings === null) {
        $cached_settings = [];
        if ($conn && !$conn->connect_error) {
            $res = @$conn->query("SELECT setting_key, setting_value FROM system_settings");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $cached_settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }
    
    return isset($cached_settings[$key]) && $cached_settings[$key] !== '' ? $cached_settings[$key] : $default;
}
}

/**
 * Menyimpan / memperbarui nilai konfigurasi sistem di tabel `system_settings`.
 */
if (!function_exists('updateSetting')) {
function updateSetting($key, $value, $userId = null) {
    global $conn;
    if (!$conn || $conn->connect_error) return false;
    
    $key = trim($key);
    $value = trim($value);
    
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at) 
                           VALUES (?, ?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()");
    if ($stmt) {
        $stmt->bind_param('ssi', $key, $value, $userId);
        $res = $stmt->execute();
        $stmt->close();
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
?>
