<?php
// Mulai sesi
session_start();

// Koneksi ke database
require_once '../inc/koneksi.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Update last_activity untuk user yang sedang login
$username = $_SESSION['username'] ?? '';
if (!empty($username)) {
    $current_time = date('Y-m-d H:i:s');
    $update_query = "UPDATE data_users SET last_activity = '$current_time' WHERE username = '$username'";
    $conn->query($update_query);
}

// Ambil data admin yang online
$admin_online_list = [];
$admin_status = [];

// Ambil semua admin dan super admin
$sql_admin = "SELECT username, last_activity FROM data_users WHERE role IN ('admin', 'super admin')";
$result_admin = $conn->query($sql_admin);

if ($result_admin && $result_admin->num_rows > 0) {
    while ($row = $result_admin->fetch_assoc()) {
        $last_activity = strtotime($row['last_activity']);
        $current_time = time();
        $is_online = ($current_time - $last_activity) < 300; // 5 menit
        
        if ($is_online) {
            $admin_online_list[] = $row['username'];
        }
        
        $admin_status[$row['username']] = $is_online;
    }
}

// Kirim response
echo json_encode([
    'success' => true,
    'admin_online_count' => count($admin_online_list),
    'admin_online_list' => $admin_online_list,
    'admin_status' => $admin_status
]);
