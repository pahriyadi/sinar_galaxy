<?php
// Utilities for automatic backups of activity logs

if (!session_id()) { session_start(); }
require_once __DIR__ . '/../../inc/koneksi.php';

function ensureBackupDir(): string {
    $dir = __DIR__ . '/../backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return realpath($dir) ?: $dir;
}

function backupLogsByDate(string $dateYmd, int $createdByUserId = 0): array {
    global $conn;

    $backupDir = ensureBackupDir();
    $safeDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd) ? $dateYmd : date('Y-m-d');

    // Fetch activity_log for the date
    $logs = [];
    $counts = ['activity_log' => 0, 'login_history' => 0];

    $sql1 = "SELECT * FROM activity_log WHERE DATE(created_at) = ? ORDER BY created_at ASC";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param('s', $safeDate);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    $logs['activity_log'] = $res1 ? $res1->fetch_all(MYSQLI_ASSOC) : [];
    $counts['activity_log'] = is_array($logs['activity_log']) ? count($logs['activity_log']) : 0;

    // Fetch login_history for the date
    $sql2 = "SELECT * FROM login_history WHERE DATE(login_time) = ? ORDER BY login_time ASC";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('s', $safeDate);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $logs['login_history'] = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
    $counts['login_history'] = is_array($logs['login_history']) ? count($logs['login_history']) : 0;

    $totalCount = $counts['activity_log'] + $counts['login_history'];

    // If nothing to backup, just record metadata (optional)
    $filename = "logs_{$safeDate}.json";
    $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;
    $relPath = 'assets/backup/backups/' . $filename;

    $payload = [
        'date' => $safeDate,
        'generated_at' => date('c'),
        'counts' => $counts,
        'data' => $logs
    ];
    file_put_contents($filepath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $fileSize = @filesize($filepath) ?: 0;

    // Insert/update metadata into activity_log_backup
    if (isBackupExists($safeDate)) {
        $stmt = $conn->prepare("UPDATE activity_log_backup SET log_count = ?, file_path = ?, file_size = ?, created_by = ?, created_at = CURRENT_TIMESTAMP WHERE backup_date = ?");
        if ($stmt) {
            $stmt->bind_param('isiis', $totalCount, $relPath, $fileSize, $createdByUserId, $safeDate);
            $stmt->execute();
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO activity_log_backup (backup_date, log_count, file_path, file_size, created_by) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sisii', $safeDate, $totalCount, $relPath, $fileSize, $createdByUserId);
            $stmt->execute();
        }
    }

    return [
        'ok' => true,
        'date' => $safeDate,
        'file' => $relPath,
        'count' => $totalCount,
        'size' => $fileSize
    ];
}

function isBackupExists(string $dateYmd): bool {
    global $conn;
    $sql = "SELECT 1 FROM activity_log_backup WHERE backup_date = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $dateYmd);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res && $res->num_rows > 0;
}

?>

