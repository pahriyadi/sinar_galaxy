<?php
session_start();

// Include data users
require_once 'data_users.php';

// Jika sudah login, redirect ke index
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit();
}

// Cek pesan logout
$logout_message = '';
if (isset($_SESSION['logout_message'])) {
    $logout_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            
            // Validasi menggunakan data_users.php
            if (!empty($username) && !empty($password)) {
                if (validateUser($username, $password)) {
                    // Login berhasil
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['username'] = $username;
                    $_SESSION['login_time'] = time();
                    
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Username atau password salah!';
                }
            } else {
                $error = 'Username dan password harus diisi!';
            }
        } elseif ($_POST['action'] === 'register') {
            $username = trim($_POST['reg_username']);
            $password = trim($_POST['reg_password']);
            $confirm_password = trim($_POST['reg_confirm_password']);
            
            if (!empty($username) && !empty($password) && !empty($confirm_password)) {
                if ($password === $confirm_password) {
                    if (addUser($username, $password)) {
                        $success = 'Akun berhasil dibuat! Silakan login.';
                    } else {
                        $error = 'Username sudah ada! Pilih username lain.';
                    }
                } else {
                    $error = 'Password dan konfirmasi password tidak cocok!';
                }
            } else {
                $error = 'Semua field harus diisi!';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Galaxy Drive</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="../img/logo_sgt.png" alt="Logo" class="login-logo">
                <h1>Galaxy Drive</h1>
                <p>Sistem Penyimpanan Online</p>
            </div>
            
            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-btn active" onclick="showTab('login')">Login</button>
                <button class="tab-btn" onclick="showTab('register')">Daftar</button>
            </div>
            
            <?php if (!empty($logout_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($logout_message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <div id="loginTab" class="tab-content active">
                <form method="POST" class="login-form">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                </form>
            </div>
            
            <!-- Register Form -->
            <div id="registerTab" class="tab-content">
                <form method="POST" class="login-form">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label for="reg_username">Username</label>
                        <input type="text" id="reg_username" name="reg_username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_password">Password</label>
                        <input type="password" id="reg_password" name="reg_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_confirm_password">Konfirmasi Password</label>
                        <input type="password" id="reg_confirm_password" name="reg_confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-user-plus"></i> Daftar
                    </button>
                </form>
            </div>
            
            <div class="login-info">
                <h4>Penyimpanan Online Data Sinar Galaxy Travel:</h4>
                <p>Login Atau buat akun baru di tab "Daftar"</p>
            </div>
        </div>
    </div>
    
    <script>
        // Function to show tabs
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            const selectedTab = document.getElementById(tabName + 'Tab');
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Update tab navigation background
            const tabNav = document.querySelector('.tab-navigation');
            if (tabNav) {
                if (tabName === 'register') {
                    tabNav.classList.add('register-active');
                } else {
                    tabNav.classList.remove('register-active');
                }
            }
        }
        
        // Add form submission handling
        document.addEventListener('DOMContentLoaded', function() {
            // Handle form submissions
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        // Add loading state
                        submitBtn.classList.add('loading');
                        submitBtn.disabled = true;
                        
                        // Re-enable button after 5 seconds if form doesn't submit
                        setTimeout(() => {
                            submitBtn.classList.remove('loading');
                            submitBtn.disabled = false;
                        }, 5000);
                    }
                });
            });
            
            // Handle input focus effects
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#667eea';
                    this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.1)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.borderColor = '#e2e8f0';
                    this.style.boxShadow = 'none';
                });
            });
            
            // Initialize page state
            const loginTab = document.getElementById('loginTab');
            const loginBtn = document.querySelector('.tab-btn');
            
            if (loginTab) {
                loginTab.classList.add('active');
            }
            
            if (loginBtn) {
                loginBtn.classList.add('active');
            }
        });
    </script>
</body>
</html>