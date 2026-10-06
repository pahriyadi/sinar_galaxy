<?php
session_start();
require_once '../inc/koneksi.php';
require_once '../assets/activity_logger.php';
require_once '../assets/backup/backup_utils.php';

// Hanya super admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super admin') {
    header('Location: ../data_dashboard/dashboard.php');
    exit;
}

$date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d', strtotime('-1 day'));
$uid = (int)($_SESSION['user']['id_users'] ?? 0);
$result = backupLogsByDate($date, $uid);

// Log activity
$logger = new ActivityLogger($conn);
$logger->logActivity('export', 'Backup activity logs', 'activity_log', null, null, json_encode($result));

header('Content-Type: application/json');
echo json_encode($result);
?>

