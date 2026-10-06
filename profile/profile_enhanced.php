<?php
session_start();
include '../inc/koneksi.php';

// Cek login
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user']['id_users'];
$username = $_SESSION['user']['username'];
$role = $_SESSION['user']['role'];
$asal_po = $_SESSION['user']['asal_po'];

// Ambil data user
$sql = "SELECT * FROM data_users WHERE id_users = '$user_id'";
$result = mysqli_query($conn, $sql);
$user_data = mysqli_fetch_assoc($result);

$message = '';
$message_type = '';

// Proses update profil
if (isset($_POST['update_profile'])) {
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    
    // Cek apakah field ada di tabel
    $check_fields = "SHOW COLUMNS FROM data_users LIKE 'nama_lengkap'";
    $result_check = mysqli_query($conn, $check_fields);
    $has_nama_lengkap = mysqli_num_rows($result_check) > 0;
    
    $check_fields = "SHOW COLUMNS FROM data_users LIKE 'email'";
    $result_check = mysqli_query($conn, $check_fields);
    $has_email = mysqli_num_rows($result_check) > 0;
    
    // Buat query update berdasarkan field yang tersedia
    $update_fields = [];
    $update_values = [];
    
    if ($has_nama_lengkap) {
        $update_fields[] = "nama_lengkap = ?";
        $update_values[] = $nama_lengkap;
    }
    
    if ($has_email) {
        $update_fields[] = "email = ?";
        $update_values[] = $email;
    }
    
    $update_fields[] = "no_hp = ?";
    $update_values[] = $no_hp;
    
    $update_fields[] = "alamat = ?";
    $update_values[] = $alamat;
    
    if (!empty($update_fields)) {
        $update_sql = "UPDATE data_users SET " . implode(', ', $update_fields) . " WHERE id_users = '$user_id'";
        
        // Gunakan prepared statement untuk keamanan
        $stmt = mysqli_prepare($conn, $update_sql);
        if ($stmt) {
            $types = str_repeat('s', count($update_values));
            mysqli_stmt_bind_param($stmt, $types, ...$update_values);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Profil berhasil diperbarui!';
                $message_type = 'success';
                
                // Refresh data
                $result = mysqli_query($conn, $sql);
                $user_data = mysqli_fetch_assoc($result);
            } else {
                $message = 'Gagal memperbarui profil: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = 'Gagal mempersiapkan query: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
}

// Proses ganti password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verifikasi password lama
    if (!password_verify($current_password, $user_data['password'])) {
        $message = 'Password saat ini salah!';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Konfirmasi password baru tidak cocok!';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password baru minimal 6 karakter!';
        $message_type = 'danger';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $password_sql = "UPDATE data_users SET password = '$hashed_password' WHERE id_users = '$user_id'";
        
        if (mysqli_query($conn, $password_sql)) {
            $message = 'Password berhasil diubah!';
            $message_type = 'success';
        } else {
            $message = 'Gagal mengubah password: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
}

// Hitung statistik user
$last_activity = isset($user_data['last_activity']) ? $user_data['last_activity'] : 'Belum ada aktivitas';

// Cek field created_at
$check_created = "SHOW COLUMNS FROM data_users LIKE 'created_at'";
$result_check = mysqli_query($conn, $check_created);
$has_created_at = mysqli_num_rows($result_check) > 0;

$created_date = 'Tidak diketahui';
if ($has_created_at && isset($user_data['created_at'])) {
    $created_date = $user_data['created_at'];
}

// Role badge color
$role_colors = [
    'admin' => 'danger',
    'manager' => 'warning',
    'staff' => 'info',
    'user' => 'secondary'
];

$role_color = isset($role_colors[$role]) ? $role_colors[$role] : 'secondary';

// Update last activity
$update_activity = "UPDATE data_users SET last_activity = NOW() WHERE id_users = '$user_id'";
mysqli_query($conn, $update_activity);

// Cek field yang tersedia
$check_nama_lengkap = "SHOW COLUMNS FROM data_users LIKE 'nama_lengkap'";
$result_check = mysqli_query($conn, $check_nama_lengkap);
$has_nama_lengkap = mysqli_num_rows($result_check) > 0;

$check_email = "SHOW COLUMNS FROM data_users LIKE 'email'";
$result_check = mysqli_query($conn, $check_email);
$has_email = mysqli_num_rows($result_check) > 0;

// Sekarang baru muat tampilan layout
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #6c757d;
            margin: 0 auto 1rem;
        }
        
        .profile-stats {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            border-right: 1px solid #e9ecef;
        }
        
        .stat-item:last-child {
            border-right: none;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .profile-card-header {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .profile-card-body {
            padding: 2rem;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .btn-custom {
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
        }
        
        .info-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: #1976d2;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #6c757d;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
        
        .field-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #1976d2;
        }
        
        @media (max-width: 768px) {
            .stat-item {
                border-right: none;
                border-bottom: 1px solid #e9ecef;
                margin-bottom: 1rem;
            }
            
            .stat-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
        }
    </style>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">
                                <i class="fas fa-user-circle mr-2"></i>
                                Profil Pengguna
                            </h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../data_dashboard/dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Profil</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="container-fluid">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?> mr-2"></i>
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="container-fluid">
                            <div class="row align-items-center">
                                <div class="col-md-3 text-center">
                                    <div class="profile-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <h2 class="mb-2">
                                        <?= htmlspecialchars($has_nama_lengkap && $user_data['nama_lengkap'] ? $user_data['nama_lengkap'] : $username) ?>
                                        <span class="badge badge-<?= $role_color ?> ml-2">
                                            <?= ucfirst($role) ?>
                                        </span>
                                    </h2>
                                    <p class="mb-1">
                                        <i class="fas fa-at mr-2"></i>
                                        <?= htmlspecialchars($username) ?>
                                    </p>
                                    <?php if ($has_email && $user_data['email']): ?>
                                        <p class="mb-1">
                                            <i class="fas fa-envelope mr-2"></i>
                                            <?= htmlspecialchars($user_data['email']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="mb-0">
                                        <i class="fas fa-building mr-2"></i>
                                        <?= htmlspecialchars($asal_po) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Stats -->
                    <div class="profile-stats">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <i class="fas fa-user text-success"></i>
                                    </div>
                                    <div class="stat-label">ID Pengguna</div>
                                    <div class="stat-value">#<?= $user_id ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <i class="fas fa-clock text-info"></i>
                                    </div>
                                    <div class="stat-label">Aktivitas Terakhir</div>
                                    <div class="stat-value"><?= $last_activity != 'Belum ada aktivitas' ? date('d M Y H:i', strtotime($last_activity)) : $last_activity ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <i class="fas fa-shield-alt text-warning"></i>
                                    </div>
                                    <div class="stat-label">Status Akun</div>
                                    <div class="stat-value">
                                        <span class="badge badge-success">Aktif</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <i class="fas fa-key text-primary"></i>
                                    </div>
                                    <div class="stat-label">Hak Akses</div>
                                    <div class="stat-value"><?= ucfirst($role) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Profile Information -->
                        <div class="col-md-8">
                            <div class="profile-card">
                                <div class="profile-card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-edit mr-2"></i>
                                        Informasi Profil
                                    </h5>
                                </div>
                                <div class="profile-card-body">
                                    <form action="" method="post">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="username">
                                                        <i class="fas fa-user mr-1"></i>
                                                        Username
                                                    </label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
                                                    <small class="text-muted">Username tidak dapat diubah</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="role">
                                                        <i class="fas fa-user-tag mr-1"></i>
                                                        Role
                                                    </label>
                                                    <input type="text" class="form-control" value="<?= ucfirst($role) ?>" readonly>
                                                    <small class="text-muted">Role tidak dapat diubah</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($has_nama_lengkap): ?>
                                        <div class="form-group">
                                            <label for="nama_lengkap">
                                                <i class="fas fa-id-card mr-1"></i>
                                                Nama Lengkap
                                            </label>
                                            <input type="text" class="form-control" name="nama_lengkap" value="<?= htmlspecialchars($user_data['nama_lengkap'] ?? '') ?>" placeholder="Masukkan nama lengkap">
                                        </div>
                                        <?php else: ?>
                                        <div class="field-info">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Field "Nama Lengkap" belum tersedia. Jalankan file <code>add_profile_fields.sql</code> untuk menambahkan field ini.
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="asal_po">
                                                        <i class="fas fa-building mr-1"></i>
                                                        Asal PO
                                                    </label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($asal_po) ?>" readonly>
                                                    <small class="text-muted">Asal PO tidak dapat diubah</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="no_hp">
                                                        <i class="fas fa-phone mr-1"></i>
                                                        No. HP
                                                    </label>
                                                    <input type="text" class="form-control" name="no_hp" value="<?= htmlspecialchars($user_data['no_hp'] ?? '') ?>" placeholder="Masukkan nomor HP">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($has_email): ?>
                                        <div class="form-group">
                                            <label for="email">
                                                <i class="fas fa-envelope mr-1"></i>
                                                Email
                                            </label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" placeholder="Masukkan alamat email">
                                        </div>
                                        <?php else: ?>
                                        <div class="field-info">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Field "Email" belum tersedia. Jalankan file <code>add_profile_fields.sql</code> untuk menambahkan field ini.
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="form-group">
                                            <label for="alamat">
                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                Alamat
                                            </label>
                                            <textarea class="form-control" name="alamat" rows="3" placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($user_data['alamat'] ?? '') ?></textarea>
                                        </div>
                                        
                                        <div class="text-right">
                                            <button type="submit" name="update_profile" class="btn btn-primary btn-custom">
                                                <i class="fas fa-save mr-2"></i>
                                                Simpan Perubahan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Change Password -->
                            <div class="profile-card">
                                <div class="profile-card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-key mr-2"></i>
                                        Ganti Password
                                    </h5>
                                </div>
                                <div class="profile-card-body">
                                    <form action="" method="post" id="passwordForm">
                                        <div class="form-group">
                                            <label for="current_password">
                                                <i class="fas fa-lock mr-1"></i>
                                                Password Saat Ini
                                            </label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="new_password">
                                                        <i class="fas fa-lock mr-1"></i>
                                                        Password Baru
                                                    </label>
                                                    <input type="password" class="form-control" name="new_password" id="new_password" required>
                                                    <div class="password-strength" id="passwordStrength"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="confirm_password">
                                                        <i class="fas fa-lock mr-1"></i>
                                                        Konfirmasi Password Baru
                                                    </label>
                                                    <input type="password" class="form-control" name="confirm_password" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-right">
                                            <button type="submit" name="change_password" class="btn btn-warning btn-custom">
                                                <i class="fas fa-key mr-2"></i>
                                                Ganti Password
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar Info -->
                        <div class="col-md-4">
                            <div class="profile-card">
                                <div class="profile-card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Informasi Akun
                                    </h5>
                                </div>
                                <div class="profile-card-body">
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">ID Pengguna</div>
                                            <div class="info-value">#<?= $user_id ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Asal PO</div>
                                            <div class="info-value"><?= htmlspecialchars($asal_po) ?></div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($has_created_at): ?>
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Tanggal Bergabung</div>
                                            <div class="info-value"><?= date('d M Y', strtotime($created_date)) ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Aktivitas Terakhir</div>
                                            <div class="info-value"><?= $last_activity != 'Belum ada aktivitas' ? date('d M Y H:i', strtotime($last_activity)) : $last_activity ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Status</div>
                                            <div class="info-value">
                                                <span class="badge badge-success">Aktif</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="profile-card">
                                <div class="profile-card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bolt mr-2"></i>
                                        Aksi Cepat
                                    </h5>
                                </div>
                                <div class="profile-card-body">
                                    <div class="d-grid gap-2">
                                        <a href="../data_dashboard/dashboard.php" class="btn btn-outline-primary btn-custom">
                                            <i class="fas fa-tachometer-alt mr-2"></i>
                                            Dashboard
                                        </a>
                                        <?php if ($role == 'admin'): ?>
                                        <a href="../data_users/data_users_view.php" class="btn btn-outline-info btn-custom">
                                            <i class="fas fa-users mr-2"></i>
                                            Kelola User
                                        </a>
                                        <?php endif; ?>
                                        <a href="../logout.php" class="btn btn-outline-danger btn-custom">
                                            <i class="fas fa-sign-out-alt mr-2"></i>
                                            Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../inc/footer.php'; ?>
    </div>

    
    <script>
        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength';
            if (strength < 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength < 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Konfirmasi password tidak cocok!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password minimal 6 karakter!');
                return false;
            }
        });
        
        // Auto hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html> 