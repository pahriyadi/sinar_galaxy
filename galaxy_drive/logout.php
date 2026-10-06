<?php
session_start();

// Simpan username untuk pesan logout
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Hapus semua data session
session_unset();
session_destroy();

// Mulai session baru untuk pesan
session_start();
$_SESSION['logout_message'] = 'Anda telah berhasil logout, ' . htmlspecialchars($username) . '!';

// Redirect ke halaman login
header('Location: login.php');
exit();
?>