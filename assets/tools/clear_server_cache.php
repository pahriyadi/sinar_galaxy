<?php
// Clear server-side caches (OPcache/APCu/realpath) – Super Admin only
if (!session_id()) { session_start(); }

require_once __DIR__ . '/../../inc/koneksi.php';
require_once __DIR__ . '/../activity_logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['user']['role'] ?? '';
if ($role !== 'super admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden: super admin only']);
    exit;
}

$actions = [];

// Clear OPcache
if (function_exists('opcache_reset')) {
    $ok = @opcache_reset();
    $actions['opcache_reset'] = $ok ? 'done' : 'not_enabled';
} else {
    $actions['opcache_reset'] = 'unavailable';
}

// Clear APCu (if any)
if (function_exists('apcu_clear_cache')) {
    @apcu_clear_cache();
    $actions['apcu_clear_cache'] = 'done';
} else {
    $actions['apcu_clear_cache'] = 'unavailable';
}

// Clear realpath/stat cache
@clearstatcache(true);
$actions['clearstatcache'] = 'done';

// Log activity
$logger = new ActivityLogger($conn);
$logger->logActivity('update', 'Clear server cache', 'system_cache', null, null, json_encode($actions));

echo json_encode(['ok' => true, 'actions' => $actions]);
?>

