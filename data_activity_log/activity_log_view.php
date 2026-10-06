<?php
session_start();
require_once '../inc/koneksi.php';
require_once '../assets/activity_logger.php';
require_once '../assets/logging_helper.php';

// Log activity log access
logActivityLogAccess();

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
if (!empty($_GET['activity_type'])) $filters['activity_type'] = $_GET['activity_type'];
if (!empty($_GET['table_name'])) $filters['table_name'] = $_GET['table_name'];
if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
if (!empty($_GET['ip_address'])) $filters['ip_address'] = $_GET['ip_address'];

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Get activity logs
$logs = $logger->getActivityLogs($filters, $limit, $offset);

// Get statistics
$stats = $logger->getStatistics();

// Get users for filter
$users_sql = "SELECT id_users, username, nama_lengkap FROM data_users ORDER BY nama_lengkap";
$users_result = $conn->query($users_sql);

// Get activity types for filter
$activity_types = ['login', 'logout', 'create', 'update', 'delete', 'view', 'export', 'import', 'print'];

// Get table names for filter
$tables_sql = "SELECT DISTINCT table_name FROM activity_log WHERE table_name IS NOT NULL ORDER BY table_name";
$tables_result = $conn->query($tables_sql);

require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1>Activity Log</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../data_dashboard/dashboard.php">Home</a></li>
                                <li class="breadcrumb-item active">Activity Log</li>
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
                            <h3 class="card-title">Filter Activity Log</h3>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-2">
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
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Jenis Aktivitas</label>
                                            <select name="activity_type" class="form-control">
                                                <option value="">Semua Aktivitas</option>
                                                <?php foreach ($activity_types as $type): ?>
                                                    <option value="<?= $type ?>" <?= ($filters['activity_type'] ?? '') == $type ? 'selected' : '' ?>>
                                                        <?= ucfirst($type) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Tabel</label>
                                            <select name="table_name" class="form-control">
                                                <option value="">Semua Tabel</option>
                                                <?php while ($table = $tables_result->fetch_assoc()): ?>
                                                    <option value="<?= $table['table_name'] ?>" <?= ($filters['table_name'] ?? '') == $table['table_name'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($table['table_name']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Tanggal Dari</label>
                                            <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Tanggal Sampai</label>
                                            <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>IP Address</label>
                                            <input type="text" name="ip_address" class="form-control" placeholder="IP Address" value="<?= $filters['ip_address'] ?? '' ?>">
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
                                        <a href="login_history.php" class="btn btn-info">
                                            <i class="fas fa-history"></i> Login History
                                        </a>
                                    </div>
                                </div>
                            </form>
                            <hr>
                            <div>
                                <h5>Backup Otomatis</h5>
                                <div class="form-inline mb-2">
                                    <label class="mr-2">Tanggal</label>
                                    <input type="date" id="backupDate" class="form-control mr-2" value="<?= date('Y-m-d', strtotime('-1 day')) ?>">
                                    <button class="btn btn-outline-primary" onclick="runBackup()"><i class="fas fa-download"></i> Backup</button>
                                    <small class="text-muted ml-3">Default: kemarin</small>
                                </div>
                                <div class="btn-group" role="group" aria-label="Backup System">
                                  <button class="btn btn-sm btn-secondary" onclick="backupDB()"><i class="fas fa-database"></i> Backup Database → Galaxy Drive</button>
                                  <button class="btn btn-sm btn-secondary" onclick="backupSite()"><i class="fas fa-server"></i> Backup Site ZIP → Galaxy Drive</button>
                                </div>
                                <div id="backupResult" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Log Table -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Activity Log</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="activityLogTable">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Waktu</th>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Aktivitas</th>
                                            <th>Deskripsi</th>
                                            <th>Tabel</th>
                                            <th>ID Record</th>
                                            <th>IP Address</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = $offset + 1;
                                        while ($log = $logs->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($log['nama_lengkap'] ?: $log['username']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($log['username']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $log['role'] == 'super admin' ? 'danger' : ($log['role'] == 'admin' ? 'warning' : 'info') ?>">
                                                    <?= ucfirst($log['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $log['activity_type'] == 'create' ? 'success' : ($log['activity_type'] == 'update' ? 'warning' : ($log['activity_type'] == 'delete' ? 'danger' : 'primary')) ?>">
                                                    <?= ucfirst($log['activity_type']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($log['description']) ?></td>
                                            <td><?= htmlspecialchars($log['table_name'] ?: '-') ?></td>
                                            <td><?= $log['record_id'] ?: '-' ?></td>
                                            <td>
                                                <code><?= htmlspecialchars($log['ip_address']) ?></code>
                                            </td>
                                            <td>
                                                <?php if ($log['old_data'] || $log['new_data']): ?>
                                                <button type="button" class="btn btn-sm btn-info" onclick="viewDetails(<?= $log['id_log'] ?>)">
                                                    <i class="fas fa-eye"></i> Detail
                                                </button>
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

        <!-- Detail Modal -->
        <div class="modal fade" id="detailModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detail Perubahan Data</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="detailModalBody">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
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
            $('#activityLogTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[1, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json'
                }
            });
        });

        function viewDetails(logId) {
            $.ajax({
                url: 'get_audit_trail.php',
                type: 'GET',
                data: { log_id: logId },
                success: function(response) {
                    $('#detailModalBody').html(response);
                    $('#detailModal').modal('show');
                },
                error: function() {
                    alert('Terjadi kesalahan saat memuat detail');
                }
            });
        }
    </script>
    <script>
        function runBackup() {
            const date = document.getElementById('backupDate').value;
            const btn = event.target;
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            fetch('backup_logs.php?date=' + encodeURIComponent(date))
                .then(r => r.json())
                .then(d => {
                    const el = document.getElementById('backupResult');
                    if (d && d.ok) {
                        el.innerHTML = '<div class="alert alert-success">Backup berhasil: ' + (d.file || '') + ' (' + (d.count || 0) + ' records)</div>';
                    } else {
                        el.innerHTML = '<div class="alert alert-danger">Backup gagal</div>';
                    }
                })
                .catch(() => {
                    document.getElementById('backupResult').innerHTML = '<div class="alert alert-danger">Terjadi kesalahan</div>';
                })
                .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-download"></i> Backup'; });
        }
        function backupDB() {
            const el = document.getElementById('backupResult');
            el.innerHTML = '';
            fetch('system_backup.php?action=db')
                .then(r => r.json())
                .then(d => {
                    if (d.ok) {
                        el.innerHTML = '<div class="alert alert-success">Backup DB tersimpan: ' + (d.file || '') + '</div>';
                    } else {
                        el.innerHTML = '<div class="alert alert-danger">Gagal backup DB: ' + (d.message || '') + '</div>';
                    }
                })
                .catch(() => { el.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan</div>'; });
        }
        function backupSite() {
            const el = document.getElementById('backupResult');
            el.innerHTML = '';
            fetch('system_backup.php?action=site')
                .then(r => r.json())
                .then(d => {
                    if (d.ok) {
                        el.innerHTML = '<div class="alert alert-success">Backup Site tersimpan: ' + (d.file || '') + '</div>';
                    } else {
                        el.innerHTML = '<div class="alert alert-danger">Gagal backup Site: ' + (d.message || '') + '</div>';
                    }
                })
                .catch(() => { el.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan</div>'; });
        }
    </script>
</body>
</html> 