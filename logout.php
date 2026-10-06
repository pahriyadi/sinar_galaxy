<?php
session_start();
include 'inc/koneksi.php';
include 'assets/activity_logger.php';

// Log logout activity
if (isset($_SESSION['user'])) {
    $logger = new ActivityLogger($conn);
    $logger->logLogout();
    $logger->logActivity('logout', 'User logged out successfully');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
header('Location: login.php?logout=1');
exit;
?>
