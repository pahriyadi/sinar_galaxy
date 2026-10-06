<?php
session_start();
require_once '../inc/koneksi.php';
require_once '../assets/backup/system_backup.php';
require_once '../assets/activity_logger.php';

// super admin only
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super admin') {
    header('Location: ../data_dashboard/dashboard.php');
    exit;
}

$action = $_GET['action'] ?? '';
$result = ['ok' => false, 'message' => 'Unknown action'];

if ($action === 'db') {
    $result = backupDatabaseToGalaxy();
} elseif ($action === 'site') {
    $result = backupProjectToGalaxy();
}

// log
$logger = new ActivityLogger($conn);
$logger->logActivity('export', 'System backup: ' . $action, 'system_backup', null, null, json_encode($result));

header('Content-Type: application/json');
echo json_encode($result);
?>

