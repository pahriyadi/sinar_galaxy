<?php
session_start();
include 'inc/koneksi.php';
include 'assets/activity_logger.php';

// Jika sudah login, langsung alihkan ke dashboard
if (isset($_SESSION['user'])) {
    header('Location: data_dashboard/dashboard');
    exit;
}

// Proses login (gunakan prepared statement)
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($stmt = $conn->prepare('SELECT id_users, username, password, role, asal_po FROM data_users WHERE username = ? LIMIT 1')) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Regenerasi session ID untuk mencegah serangan session fixation
                session_regenerate_id(true);

                // Get user full name
                $user_info_sql = "SELECT nama_lengkap FROM data_users WHERE id_users = ?";
                $user_info_stmt = $conn->prepare($user_info_sql);
                $user_info_stmt->bind_param('i', $user['id_users']);
                $user_info_stmt->execute();
                $user_info_result = $user_info_stmt->get_result();
                $user_info = $user_info_result->fetch_assoc();
                
                $_SESSION['user'] = [
                    'id_users' => (int)$user['id_users'],
                    'username' => $user['username'],
                    'nama_lengkap' => $user_info['nama_lengkap'] ?? $user['username'],
                    'role' => $user['role'],
                    'asal_po' => $user['asal_po']
                ];
                
                // Log successful login
                $logger = new ActivityLogger($conn);
                $logger->logLogin('success');
                
                header('Location: data_dashboard/dashboard');
                exit;
            } else {
                // Log failed login
                $logger = new ActivityLogger($conn);
                $logger->logLogin('failed', 'Password salah');
                $error = 'Password salah!';
            }
        } else {
            // Log failed login
            $logger = new ActivityLogger($conn);
            $logger->logLogin('failed', 'Username tidak ditemukan');
            $error = 'Username tidak ditemukan!';
        }
        $stmt->close();
    } else {
        $error = 'Terjadi kesalahan koneksi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Galaxy Travel Management System</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/icheck-bootstrap@3.0.1/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/custom.css?v=2.2">
  <link rel="icon" href="<?= htmlspecialchars(getSetting('app_favicon', 'img/logo_sgt.png')) ?>">
  <title><?= htmlspecialchars(getSetting('app_name', 'Galaxy Travel')) ?> | Login</title>
  <style>
    body {
      background-color: #f8fafc;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #333333;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    
    .login-box {
      width: 400px;
      max-width: 90vw;
    }
    
    .login-logo {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    
    .login-logo img {
      width: 75px;
      height: 75px;
      border-radius: 50%;
      margin-bottom: 0.75rem;
      border: 1px solid #b8b8b8;
    }
    
    .login-logo h1 {
      color: #333333;
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0;
      letter-spacing: 0.04em;
    }
    
    .login-logo p {
      color: #666666;
      font-size: 0.95rem;
      margin: 0.25rem 0 0 0;
    }
    
    .card {
      border: 1px solid #b8b8b8;
      border-radius: 4px;
      box-shadow: none;
      background: #ffffff;
    }
    
    .login-card-body {
      padding: 2rem 1.75rem;
      border-radius: 4px;
      background: #ffffff;
    }
    
    .login-box-msg {
      color: #555555;
      font-size: 0.95rem;
      font-weight: 500;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    
    .input-group {
      margin-bottom: 1.25rem;
    }
    
    .form-control {
      border: 1px solid #b8b8b8;
      border-radius: 4px;
      padding: 0.65rem 0.85rem;
      font-size: 0.9rem;
      background-color: #ffffff;
      color: #333333;
      box-shadow: none;
    }
    
    .form-control:focus {
      border-color: #0d9f4f;
      box-shadow: 0 0 0 0.15rem rgba(13, 159, 79, 0.15);
    }
    
    .input-group-text {
      background: #f8f9fa;
      border: 1px solid #b8b8b8;
      border-left: none;
      border-radius: 0 4px 4px 0;
      color: #666666;
    }
    
    .input-group .form-control {
      border-right: none;
      border-radius: 4px 0 0 4px;
    }
    
    .btn-primary {
      background-color: #0d9f4f;
      border: 1px solid #0d9f4f;
      border-radius: 4px;
      padding: 0.65rem 1.5rem;
      font-weight: 600;
      font-size: 0.95rem;
      color: #ffffff;
      box-shadow: none;
      transition: all 0.2s ease-in-out;
    }
    
    .btn-primary:hover {
      background-color: #076e34;
      border-color: #076e34;
      color: #ffffff;
    }
    
    .alert {
      border: 1px solid #b8b8b8;
      border-radius: 4px;
      font-weight: 500;
    }
    
    .alert-danger {
      background-color: #fde8e8;
      color: #9b1c1c;
      border-color: #f8b4b4;
    }
    
    .alert-success {
      background-color: #e6f4ea;
      color: #076e34;
      border-color: #0d9f4f;
    }
    
    .footer-text {
      text-align: center;
      color: #666666;
      margin-top: 1.5rem;
      font-size: 0.85rem;
    }
    
    .footer-text a {
      color: #076e34;
      text-decoration: none;
      font-weight: 600;
    }
    
    .footer-text a:hover {
      text-decoration: underline;
    }
    
    @media (max-width: 576px) {
      .login-box {
        width: 95vw;
      }
      
      .login-card-body {
        padding: 1.5rem 1.25rem;
      }
      
      .login-logo h1 {
        font-size: 1.3rem;
      }
    }
  </style>
</head>
<body>
<?php $base = ''; include 'inc/preloader.php'; ?>
<div class="login-box">
  <div class="login-logo">
    <img src="<?= htmlspecialchars(getSetting('app_logo', 'img/logo_sgt.png')) ?>" alt="Logo" onerror="this.style.display='none'">
    <h1><?= htmlspecialchars(strtoupper(getSetting('app_name', 'GALAXY TRAVEL'))) ?></h1>
    <p><?= htmlspecialchars(getSetting('app_tagline', 'Management System')) ?></p>
  </div>
  
  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">
        <i class="fas fa-sign-in-alt mr-2"></i>
        Silakan login untuk mengakses sistem
      </p>
      
      <?php if (isset($error)): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      
      <?php if (isset($_GET['logout'])): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle mr-2"></i>
          Anda berhasil logout dari sistem
        </div>
      <?php endif; ?>
      
      <form action="" method="post" id="loginForm">
        <div class="input-group">
          <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
          <div class="input-group-append">
            <div class="input-group-text">
              <i class="fas fa-user"></i>
            </div>
          </div>
        </div>
        
        <div class="input-group">
          <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <i class="fas fa-lock"></i>
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-12">
            <button type="submit" name="login" class="btn btn-primary btn-block">
              <i class="fas fa-sign-in-alt mr-2"></i>
              Login ke Sistem
            </button>
          </div>
        </div>
      </form>
      
      <div class="text-center mt-4">
        <small class="text-muted">
          <i class="fas fa-shield-alt mr-1"></i>
          Sistem Keamanan Terjamin
        </small>
      </div>
    </div>
  </div>
  
  <div class="footer-text">
    <p>&copy; <?php echo date('Y'); ?> Galaxy Travel. All rights reserved.</p>
    <p>Powered by <a href="#" target="_blank">Travel Management System | Albiandra Projeck</a></p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script>
// Auto focus pada username field
document.addEventListener('DOMContentLoaded', function() {
    const usernameField = document.querySelector('input[name="username"]');
    if (usernameField) {
        usernameField.focus();
    }
});

// Form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const username = document.querySelector('input[name="username"]').value.trim();
    const password = document.querySelector('input[name="password"]').value.trim();
    
    if (!username || !password) {
        e.preventDefault();
        alert('Mohon lengkapi username dan password!');
        return false;
    }
});

// Enter key to submit
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const form = document.getElementById('loginForm');
        if (form.requestSubmit) {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }
});
</script>
<!-- Universal Page Preloader Script -->
<script src="assets/js/preloader.js?v=2.1"></script>
</body>
</html>