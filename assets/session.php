<?php
if (!session_id()) {
    session_start();
}

// Selalu muat koneksi database terlebih dahulu agar $conn selalu tersedia
require_once __DIR__ . '/../inc/koneksi.php';

// Anti-Cache HTTP Headers: Cegah browser menyimpan cache sesi & halaman
if (!headers_sent()) {
    header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0, post-check=0, pre-check=0");
    header("Pragma: no-cache");
    header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
}

// Guard: redirect ke halaman login dari subdirektori mana pun
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

// Update last_activity untuk user yang sedang login
if (isset($_SESSION['user']['id_users']) && isset($conn) && $conn instanceof mysqli) {
    $id_users = (int)$_SESSION['user']['id_users'];
    $sql_update_activity = "UPDATE data_users SET last_activity = NOW() WHERE id_users = $id_users";
    @$conn->query($sql_update_activity);
}

// Include logging helper untuk auto-logging
require_once __DIR__ . '/logging_helper.php';

// Trigger backup otomatis harian (untuk super admin akan bekerja saat akses pertama)
require_once __DIR__ . '/activity_logger.php';
if (isset($conn) && $conn instanceof mysqli) {
    $__logger = new ActivityLogger($conn);
    $__logger->runDailyBackupIfNeeded();
}
?>
