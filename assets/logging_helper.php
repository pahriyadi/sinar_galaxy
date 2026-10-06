<?php
/**
 * Logging Helper untuk Galaxy Travel
 * File ini berisi fungsi-fungsi untuk menambahkan logging ke fungsi yang sudah ada
 */

require_once 'activity_logger.php';

/**
 * Log activity untuk CRUD operations
 */
function logCRUDActivity($activity_type, $table_name, $record_id, $description, $old_data = null, $new_data = null) {
    global $conn;
    
    if (!isset($_SESSION['user'])) {
        return false;
    }
    
    $logger = new ActivityLogger($conn);
    return $logger->logActivity($activity_type, $description, $table_name, $record_id, $old_data, $new_data);
}

/**
 * Log create activity
 */
function logCreate($table_name, $record_id, $description, $new_data = null) {
    return logCRUDActivity('create', $table_name, $record_id, $description, null, $new_data);
}

/**
 * Log update activity
 */
function logUpdate($table_name, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity('update', $table_name, $record_id, $description, $old_data, $new_data);
}

/**
 * Log delete activity
 */
function logDelete($table_name, $record_id, $description, $old_data = null) {
    return logCRUDActivity('delete', $table_name, $record_id, $description, $old_data, null);
}

/**
 * Log view activity
 */
function logView($table_name, $record_id = null, $description = '') {
    return logCRUDActivity('view', $table_name, $record_id, $description);
}

/**
 * Log export activity
 */
function logExport($table_name, $description, $filters = []) {
    $description .= ' - Filters: ' . json_encode($filters);
    return logCRUDActivity('export', $table_name, null, $description);
}

/**
 * Log print activity
 */
function logPrint($table_name, $description, $filters = []) {
    $description .= ' - Filters: ' . json_encode($filters);
    return logCRUDActivity('print', $table_name, null, $description);
}

/**
 * Get old data for update logging
 */
function getOldData($table_name, $record_id) {
    global $conn;

    // Whitelist sederhana untuk mencegah SQL injection pada nama tabel
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        return null;
    }

    $primaryKey = resolvePrimaryKey($table_name);
    if ($primaryKey === null) {
        return null;
    }

    $sql = "SELECT * FROM `$table_name` WHERE `$primaryKey` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return null; }
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        return json_encode($result->fetch_assoc());
    }

    return null;
}

// Tentukan nama primary key berdasarkan nama tabel
function resolvePrimaryKey($table_name) {
    // Mapping eksplisit untuk tabel yang tidak mengikuti pola umum
    $explicitMap = [
        'saldo_awal_bank' => 'id_saldo_awal',
    ];
    if (isset($explicitMap[$table_name])) {
        return $explicitMap[$table_name];
    }

    // Pola umum: data_xxx -> id_xxx
    if (strpos($table_name, 'data_') === 0) {
        $suffix = substr($table_name, 5); // hapus 'data_'
        return 'id_' . $suffix;
    }

    // Pola umum lainnya: langsung 'id_' + table_name bila cocok
    return 'id_' . $table_name;
}

/**
 * Get new data for logging
 */
function getNewData($data) {
    return json_encode($data);
}

/**
 * Log activity untuk pelanggan
 */
function logPelangganActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_pelanggan', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk pemesanan
 */
function logPemesananActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_pemesanan', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk pengiriman
 */
function logPengirimanActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_pengiriman', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk pengeluaran
 */
function logPengeluaranActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_pengeluaran', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk users
 */
function logUsersActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_users', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk travel
 */
function logTravelActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_travel', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk rekening
 */
function logRekeningActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_rekening', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk status pembayaran
 */
function logStatusPembayaranActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_status_pembayaran', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk metode pembayaran
 */
function logMetodePembayaranActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_metode_pembayaran', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk jenis pengeluaran
 */
function logJenisPengeluaranActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_jenis_pengeluaran', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk tujuan perjalanan
 */
function logTujuanPerjalananActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'data_tujuan_perjalanan', $record_id, $description, $old_data, $new_data);
}

/**
 * Log activity untuk saldo awal
 */
function logSaldoAwalActivity($activity_type, $record_id, $description, $old_data = null, $new_data = null) {
    return logCRUDActivity($activity_type, 'saldo_awal_bank', $record_id, $description, $old_data, $new_data);
}

/**
 * Log dashboard access
 */
function logDashboardAccess() {
    global $conn;
    
    if (!isset($_SESSION['user'])) {
        return false;
    }
    
    $logger = new ActivityLogger($conn);
    return $logger->logActivity('view', 'Dashboard', 'dashboard', null, 'Dashboard accessed');
}

/**
 * Log report access
 */
function logReportAccess($report_name, $filters = []) {
    $description = "Report accessed: $report_name";
    if (!empty($filters)) {
        $description .= " - Filters: " . json_encode($filters);
    }
    
    return logCRUDActivity('view', 'reports', null, $description);
}

/**
 * Log settings access
 */
function logSettingsAccess($setting_type) {
    return logCRUDActivity('view', 'settings', null, "Settings accessed: $setting_type");
}

/**
 * Log profile access
 */
function logProfileAccess() {
    return logCRUDActivity('view', 'profile', null, 'Profile accessed');
}

/**
 * Log activity log access (only for super admin)
 */
function logActivityLogAccess() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super admin') {
        return false;
    }
    
    return logCRUDActivity('view', 'activity_log', null, 'Activity log accessed');
}

/**
 * Log login history access (only for super admin)
 */
function logLoginHistoryAccess() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super admin') {
        return false;
    }
    
    return logCRUDActivity('view', 'login_history', null, 'Login history accessed');
}

/**
 * Auto-log dashboard access on page load
 */
function autoLogDashboardAccess() {
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if ($current_page === 'dashboard.php') {
        logDashboardAccess();
    } elseif (strpos($current_page, 'activity_log_view.php') !== false) {
        logActivityLogAccess();
    } elseif (strpos($current_page, 'login_history.php') !== false) {
        logLoginHistoryAccess();
    }
}

// Auto-log dashboard access if this file is included
if (isset($_SESSION['user'])) {
    autoLogDashboardAccess();
}
?> 