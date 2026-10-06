<?php
session_start();
require_once '../inc/koneksi.php';
require_once '../assets/activity_logger.php';

// Cek apakah user sudah login dan super admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super admin') {
    http_response_code(403);
    exit('Access Denied');
}

$log_id = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;

if (!$log_id) {
    echo '<div class="alert alert-danger">ID Log tidak valid</div>';
    exit;
}

$logger = new ActivityLogger($conn);

// Get log details
$log_sql = "SELECT * FROM activity_log WHERE id_log = ?";
$log_stmt = $conn->prepare($log_sql);
$log_stmt->bind_param("i", $log_id);
$log_stmt->execute();
$log_result = $log_stmt->get_result();
$log = $log_result->fetch_assoc();

if (!$log) {
    echo '<div class="alert alert-danger">Log tidak ditemukan</div>';
    exit;
}

// Get audit trail
$audit_trail = $logger->getAuditTrail($log_id);
?>

<div class="row">
    <div class="col-md-12">
        <h6>Informasi Aktivitas</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <td width="150"><strong>User</strong></td>
                <td><?= htmlspecialchars($log['nama_lengkap'] ?: $log['username']) ?></td>
            </tr>
            <tr>
                <td><strong>Role</strong></td>
                <td><?= ucfirst($log['role']) ?></td>
            </tr>
            <tr>
                <td><strong>Aktivitas</strong></td>
                <td><?= ucfirst($log['activity_type']) ?></td>
            </tr>
            <tr>
                <td><strong>Deskripsi</strong></td>
                <td><?= htmlspecialchars($log['description']) ?></td>
            </tr>
            <tr>
                <td><strong>Tabel</strong></td>
                <td><?= htmlspecialchars($log['table_name'] ?: '-') ?></td>
            </tr>
            <tr>
                <td><strong>ID Record</strong></td>
                <td><?= $log['record_id'] ?: '-' ?></td>
            </tr>
            <tr>
                <td><strong>Waktu</strong></td>
                <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
            </tr>
            <tr>
                <td><strong>IP Address</strong></td>
                <td><code><?= htmlspecialchars($log['ip_address']) ?></code></td>
            </tr>
        </table>
    </div>
</div>

<?php if ($audit_trail->num_rows > 0): ?>
<div class="row mt-3">
    <div class="col-md-12">
        <h6>Detail Perubahan Data</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Field</th>
                        <th>Nilai Lama</th>
                        <th>Nilai Baru</th>
                        <th>Jenis Perubahan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($audit = $audit_trail->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($audit['field_name']) ?></strong></td>
                        <td>
                            <?php if ($audit['old_value'] === null): ?>
                                <span class="text-muted">-</span>
                            <?php else: ?>
                                <code><?= htmlspecialchars($audit['old_value']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($audit['new_value'] === null): ?>
                                <span class="text-muted">-</span>
                            <?php else: ?>
                                <code><?= htmlspecialchars($audit['new_value']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badge_class = 'badge-primary';
                            $change_text = 'Modified';
                            
                            if ($audit['change_type'] == 'added') {
                                $badge_class = 'badge-success';
                                $change_text = 'Added';
                            } elseif ($audit['change_type'] == 'deleted') {
                                $badge_class = 'badge-danger';
                                $change_text = 'Deleted';
                            }
                            ?>
                            <span class="badge <?= $badge_class ?>"><?= $change_text ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Tidak ada detail perubahan data untuk aktivitas ini.
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($log['old_data'] || $log['new_data']): ?>
<div class="row mt-3">
    <div class="col-md-6">
        <h6>Data Lama (JSON)</h6>
        <pre class="bg-light p-2" style="max-height: 200px; overflow-y: auto;"><?= htmlspecialchars($log['old_data'] ?: 'null') ?></pre>
    </div>
    <div class="col-md-6">
        <h6>Data Baru (JSON)</h6>
        <pre class="bg-light p-2" style="max-height: 200px; overflow-y: auto;"><?= htmlspecialchars($log['new_data'] ?: 'null') ?></pre>
    </div>
</div>
<?php endif; ?> 