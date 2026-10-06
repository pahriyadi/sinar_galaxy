<?php
session_start();

// Cek login
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

if (isset($_GET['file']) && isset($_GET['dir'])) {
    $username = $_SESSION['username'];
    $userDir = 'users/' . $username;
    $currentDir = $_GET['dir'];
    $fileName = $_GET['file'];
    
    $filePath = $userDir . '/' . $currentDir . '/' . $fileName;
    
    // Pastikan file ada dan dalam direktori user
    if (file_exists($filePath) && strpos(realpath($filePath), realpath($userDir)) === 0) {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeType = mime_content_type($filePath);
        
        // Set appropriate headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=3600');
        
        // For PDF files, set inline disposition
        if ($ext === 'pdf') {
            header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
        }
        
        // Output file
        readfile($filePath);
        exit();
    }
}

header('HTTP/1.0 404 Not Found');
exit();
?>