<?php
session_start();
require_once '../inc/koneksi.php';
require_once '../assets/activity_logger.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

// Cek apakah user adalah super admin
$user = $_SESSION['user'];
if ($user['role'] !== 'super admin') {
    header('Location: ../data_dashboard/dashboard.php');
    exit;
}

$logger = new ActivityLogger($conn);

// Get filters
$filters = [];
if (!empty($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Get login history
$login_history = $logger->getLoginHistory($filters, $limit, $offset);

// Get users for filter
$users_sql = "SELECT id_users, username, nama_lengkap FROM data_users ORDER BY nama_lengkap";
$users_result = $conn->query($users_sql);

// Get statistics
$stats = $logger->getStatistics();

require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1>Login History</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../data_dashboard/dashboard.php">Home</a></li>
                                <li class="breadcrumb-item"><a href="activity_log_view.php">Activity Log</a></li>
                                <li class="breadcrumb-item active">Login History</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?= number_format($stats['total_logins'] ?? 0) ?></h3>
                                    <p>Total Login</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-sign-in-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?= number_format($stats['total_activities'] ?? 0) ?></h3>
                                    <p>Total Aktivitas</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3><?= count($stats['top_users'] ?? []) ?></h3>
                                    <p>User Aktif</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3><?= count($stats['activities_by_type'] ?? []) ?></h3>
                                    <p>Jenis Aktivitas</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-list"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Filter Login History</h3>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>User</label>
                                            <select name="user_id" class="form-control">
                                                <option value="">Semua User</option>
                                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                                    <option value="<?= $user['id_users'] ?>" <?= ($filters['user_id'] ?? '') == $user['id_users'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($user['nama_lengkap'] ?: $user['username']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select name="status" class="form-control">
                                                <option value="">Semua Status</option>
                                                <option value="success" <?= ($filters['status'] ?? '') == 'success' ? 'selected' : '' ?>>Success</option>
                                                <option value="failed" <?= ($filters['status'] ?? '') == 'failed' ? 'selected' : '' ?>>Failed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Tanggal Dari</label>
                                            <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Tanggal Sampai</label>
                                            <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Filter
                                        </button>
                                        <a href="?" class="btn btn-secondary">
                                            <i class="fas fa-refresh"></i> Reset
                                        </a>
                                        <a href="activity_log_view.php" class="btn btn-info">
                                            <i class="fas fa-list"></i> Activity Log
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Login History Table -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Login History</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="loginHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Waktu Login</th>
                                            <th>Waktu Logout</th>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>IP Address</th>
                                            <th>User Agent</th>
                                            <th>Durasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = $offset + 1;
                                        while ($login = $login_history->fetch_assoc()): 
                                            $login_time = strtotime($login['login_time']);
                                            $logout_time = $login['logout_time'] ? strtotime($login['logout_time']) : null;
                                            $duration = $logout_time ? ($logout_time - $login_time) : null;
                                        ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= date('d/m/Y H:i:s', $login_time) ?></td>
                                            <td>
                                                <?php if ($logout_time): ?>
                                                    <?= date('d/m/Y H:i:s', $logout_time) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($login['nama_lengkap'] ?: $login['username']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($login['username']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $login['role'] == 'super admin' ? 'danger' : ($login['role'] == 'admin' ? 'warning' : 'info') ?>">
                                                    <?= ucfirst($login['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $login['status'] == 'success' ? 'success' : 'danger' ?>">
                                                    <?= ucfirst($login['status']) ?>
                                                </span>
                                                <?php if ($login['failure_reason']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($login['failure_reason']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($login['ip_address']) ?></code>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars(substr($login['user_agent'], 0, 50)) ?>...</small>
                                            </td>
                                            <td>
                                                <?php if ($duration): ?>
                                                    <?php
                                                    $hours = floor($duration / 3600);
                                                    $minutes = floor(($duration % 3600) / 60);
                                                    $seconds = $duration % 60;
                                                    
                                                    if ($hours > 0) {
                                                        echo $hours . 'h ' . $minutes . 'm';
                                                    } elseif ($minutes > 0) {
                                                        echo $minutes . 'm ' . $seconds . 's';
                                                    } else {
                                                        echo $seconds . 's';
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <?php include '../inc/footer.php'; ?>
    
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap4.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#loginHistoryTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[1, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json'
                }
            });
        });
    </script>
</body>
</html> 