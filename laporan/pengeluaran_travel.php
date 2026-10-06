<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';

// Ambil session user
$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$id_users = isset($user['id_users']) ? $user['id_users'] : '';
$asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';
$role = isset($user['role']) ? $user['role'] : '';

// Ambil filter dari GET
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';
$jenis_pengeluaran = $_GET['jenis_pengeluaran'] ?? '';
$travel_id = $_GET['travel_id'] ?? '';

// Query data travel untuk dropdown
$travelOptions = [];
$resTravel = mysqli_query($conn, "SELECT id_travel, no_plat, kelas FROM data_travel ORDER BY no_plat");
if ($resTravel) {
    while ($r = mysqli_fetch_assoc($resTravel)) {
        $travelOptions[] = $r;
    }
} else {
    // Handle error jika query gagal
    error_log("Error in travel query: " . mysqli_error($conn));
}

// Query data jenis pengeluaran untuk dropdown
$jenisPengeluaranOptions = [];
$resJenis = mysqli_query($conn, "SELECT id_jenis_pengeluaran, nama_pengeluaran FROM data_jenis_pengeluaran ORDER BY nama_pengeluaran");
if ($resJenis) {
    while ($r = mysqli_fetch_assoc($resJenis)) {
        $jenisPengeluaranOptions[] = $r;
    }
} else {
    // Handle error jika query gagal
    error_log("Error in jenis pengeluaran query: " . mysqli_error($conn));
}

// Query rekap pengeluaran per travel
$where = "WHERE pg.kategori_pengeluaran = 'travel'";

if ($role !== 'super admin') {
    $where .= " AND pg.user_id = '$id_users' AND pg.asal_po = '$asal_po'";
}

if ($tgl_dari && $tgl_sampai) {
    $where .= " AND DATE(pg.tanggal_pengeluaran) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} elseif ($tgl_dari) {
    $where .= " AND DATE(pg.tanggal_pengeluaran) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
} elseif ($tgl_sampai) {
    $where .= " AND DATE(pg.tanggal_pengeluaran) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} else {
    $where .= " AND DATE(pg.tanggal_pengeluaran) = CURDATE()";
}

if ($jenis_pengeluaran) {
    $where .= " AND pg.jenis_pengeluaran_id = '" . mysqli_real_escape_string($conn, $jenis_pengeluaran) . "'";
}

if ($travel_id) {
    $where .= " AND pg.travel_id = '" . mysqli_real_escape_string($conn, $travel_id) . "'";
}

$sql = "SELECT 
    pg.travel_id,
    dt.kelas,
    CONCAT(UPPER(pg.kategori_pengeluaran), ' - ', jp.nama_pengeluaran) as nama_pengeluaran,
    COUNT(*) as jumlah_transaksi,
    SUM(pg.harga_operasional) as total_pengeluaran,
    COUNT(DISTINCT pg.tanggal_pengeluaran) as jumlah_hari
FROM data_pengeluaran pg
LEFT JOIN data_travel dt ON pg.travel_id = dt.id_travel
LEFT JOIN data_jenis_pengeluaran jp ON pg.jenis_pengeluaran_id = jp.id_jenis_pengeluaran
$where
GROUP BY pg.travel_id, dt.kelas, pg.kategori_pengeluaran, jp.id_jenis_pengeluaran
ORDER BY pg.travel_id ASC, pg.kategori_pengeluaran ASC, jp.nama_pengeluaran ASC";

$resRekap = mysqli_query($conn, $sql);

$rekap = [];
$total_pengeluaran = 0;
$total_transaksi = 0;
$total_hari = 0;

if ($resRekap) {
    while ($row = mysqli_fetch_assoc($resRekap)) {
        $rekap[] = $row;
        $total_pengeluaran += $row['total_pengeluaran'];
        $total_transaksi += $row['jumlah_transaksi'];
        $total_hari += $row['jumlah_hari'];
    }
} else {
    // Handle error jika query gagal
    error_log("Error in rekap query: " . mysqli_error($conn));
}

// Hitung rata-rata per transaksi
$rata_rata_transaksi = $total_transaksi > 0 ? $total_pengeluaran / $total_transaksi : 0;
?>

<style>
@media print {
  #printArea {
    margin-top: 18mm !important;
  }
}
</style>

<div class="content-wrapper" style="background-color: #fcfcfc;">
    <!-- Content Header -->
    <div class="content-header" style="padding: 14px 18px 8px; background: #ffffff; border-bottom: 1px solid #e0e0e0; margin-bottom: 15px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; border-radius: 4px; background: #fee2e2; border: 1px solid #fecaca; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #dc2626; font-size: 1.2rem;">
                            <i class="fas fa-truck-moving"></i>
                        </div>
                        <div>
                            <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Laporan Biaya & Pengeluaran Armada</h1>
                            <div class="text-muted" style="font-size: 0.83rem;">
                                <span>Rekap beban operasional unit kendaraan (BBM, servis, uang jalan driver)</span>
                                <span class="mx-1">•</span>
                                <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                                    <i class="fas fa-building mr-1"></i><?= $role === 'super admin' ? 'Seluruh Cabang PO' : 'PO ' . htmlspecialchars($asal_po) ?>
                                </span>
                                <span class="badge badge-secondary" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                                    <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars(strtoupper($role)) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-5 text-right no-print">
                    <button type="button" class="btn btn-default btn-sm btn-flat" onclick="window.print();" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600; margin-right: 6px;">
                        <i class="fas fa-print mr-1 text-muted"></i> Cetak Laporan
                    </button>
                    <button type="button" class="btn btn-success btn-sm btn-flat" onclick="exportToExcel()" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600;">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="content" style="padding: 0 15px;">
        <div class="container-fluid">
            <div class="no-print">
                <!-- Filter Card (Paper White v2.0) -->
                <div class="card mb-3" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
                    <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #b8b8b8; padding: 10px 16px;">
                        <h3 class="card-title font-weight-bold text-dark" style="font-size: 0.95rem; margin: 0;">
                            <i class="fas fa-filter mr-1 text-muted"></i> Parameter Filter Pengeluaran Armada
                        </h3>
                    </div>
                    <div class="card-body p-3">
                        <form method="GET" class="mb-3">
                            <div class="row">
                                <div class="col-md-2">
                                    <label for="tgl_dari">Tanggal Dari</label>
                                    <input type="date" class="form-control" name="tgl_dari"
                                        id="tgl_dari" value="<?= htmlspecialchars($tgl_dari) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="tgl_sampai">Tanggal Sampai</label>
                                    <input type="date" class="form-control" name="tgl_sampai"
                                        id="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="jenis_pengeluaran">Jenis Pengeluaran</label>
                                    <select class="form-control" name="jenis_pengeluaran" id="jenis_pengeluaran">
                                        <option value="">-- Semua Jenis --</option>
                                        <?php foreach ($jenisPengeluaranOptions as $jenis) { ?>
                                            <option value="<?= htmlspecialchars($jenis['id_jenis_pengeluaran']) ?>" <?= ($jenis_pengeluaran == $jenis['id_jenis_pengeluaran']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($jenis['nama_pengeluaran']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="travel_id">No. Plat Travel</label>
                                    <select class="form-control" name="travel_id" id="travel_id">
                                        <option value="">-- Semua Travel --</option>
                                        <?php foreach ($travelOptions as $travel) { ?>
                                            <option value="<?= htmlspecialchars($travel['id_travel']) ?>" <?= ($travel_id == $travel['id_travel']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($travel['no_plat']) ?> (<?= htmlspecialchars($travel['kelas']) ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2 align-self-end">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <a href="pengeluaran_travel.php" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                    <button type="button" class="btn btn-success" onclick="window.print();">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="info-box bg-danger">
                            <span class="info-box-icon"><i class="fas fa-arrow-down"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pengeluaran</span>
                                <span class="info-box-number">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box bg-info">
                            <span class="info-box-icon"><i class="fas fa-receipt"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Transaksi</span>
                                <span class="info-box-number"><?= number_format($total_transaksi) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box bg-warning">
                            <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Jumlah Hari</span>
                                <span class="info-box-number"><?= number_format($total_hari) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box bg-primary">
                            <span class="info-box-icon"><i class="fas fa-calculator"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Rata-rata per Transaksi</span>
                                <span class="info-box-number">Rp <?= number_format($rata_rata_transaksi, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="printArea">
                <div class="text-center mb-3">
                    <h4 class="mb-0"><strong>SINAR GALAXY TRAVEL</strong></h4>
                    <h5 class="mb-1">LAPORAN PENGELUARAN PER TRAVEL</h5>
                    <p class="mb-0">
                        <small>
                            <?php
                                if ($tgl_dari && $tgl_sampai) {
                                    echo "Periode: " . date('d/m/Y', strtotime($tgl_dari)) . " s/d " . date('d/m/Y', strtotime($tgl_sampai));
                                } elseif ($tgl_dari) {
                                    echo "Tanggal: " . date('d/m/Y', strtotime($tgl_dari));
                                } elseif ($tgl_sampai) {
                                    echo "Tanggal: " . date('d/m/Y', strtotime($tgl_sampai));
                                } else {
                                    echo "Tanggal: " . date('d/m/Y');
                                }
                                if ($jenis_pengeluaran) {
                                    $selectedJenis = array_filter($jenisPengeluaranOptions, function($jenis) use ($jenis_pengeluaran) {
                                        return $jenis['id_jenis_pengeluaran'] == $jenis_pengeluaran;
                                    });
                                    $selectedJenis = reset($selectedJenis);
                                    echo " | Jenis: " . htmlspecialchars($selectedJenis['nama_pengeluaran']);
                                }
                                if ($travel_id) {
                                    echo " | Plat: " . htmlspecialchars($travel_id);
                                }
                                if ($asal_po && $role !== 'super admin') {
                                    echo " | PO: " . htmlspecialchars($asal_po);
                                }
                            ?>
                        </small>
                    </p>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableRekap">
                        <thead class="bg-danger text-white">
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th>No. Plat Travel</th>
                                <th>Kelas</th>
                                <th>Jenis Pengeluaran</th>
                                <th class="text-center">Jumlah Transaksi</th>
                                <th class="text-center">Jumlah Hari</th>
                                <th class="text-right">Total Pengeluaran</th>
                                <th class="text-right">Rata-rata per Transaksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $subtotal_plat = [];
                            
                            foreach ($rekap as $row) {
                                $rata_rata = $row['jumlah_transaksi'] > 0 ? $row['total_pengeluaran'] / $row['jumlah_transaksi'] : 0;
                                
                                echo "<tr>";
                                echo "<td class='text-center'>" . $no++ . "</td>";
                                // Ambil no_plat dari travel_id
                                $no_plat = $row['travel_id'];
                                foreach ($travelOptions as $travel) {
                                    if ($travel['id_travel'] == $row['travel_id']) {
                                        $no_plat = $travel['no_plat'];
                                        break;
                                    }
                                }
                                echo "<td><strong>" . htmlspecialchars($no_plat) . "</strong></td>";
                                echo "<td><span class='badge badge-secondary'>" . htmlspecialchars($row['kelas']) . "</span></td>";
                                echo "<td><span class='badge badge-info'>" . htmlspecialchars($row['nama_pengeluaran']) . "</span></td>";
                                echo "<td class='text-center'>" . number_format($row['jumlah_transaksi']) . "</td>";
                                echo "<td class='text-center'>" . number_format($row['jumlah_hari']) . "</td>";
                                echo "<td class='text-right'><strong>Rp " . number_format($row['total_pengeluaran'], 0, ',', '.') . "</strong></td>";
                                echo "<td class='text-right'>Rp " . number_format($rata_rata, 0, ',', '.') . "</td>";
                                echo "</tr>";
                                
                                // Hitung subtotal per plat
                                if (!isset($subtotal_plat[$row['travel_id']])) {
                                    $subtotal_plat[$row['travel_id']] = [
                                        'total' => 0,
                                        'transaksi' => 0,
                                        'hari' => 0
                                    ];
                                }
                                $subtotal_plat[$row['travel_id']]['total'] += $row['total_pengeluaran'];
                                $subtotal_plat[$row['travel_id']]['transaksi'] += $row['jumlah_transaksi'];
                                $subtotal_plat[$row['travel_id']]['hari'] += $row['jumlah_hari'];
                            }
                            ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <?php foreach ($subtotal_plat as $plat => $data): ?>
                            <tr class="table-info">
                                <th colspan="4" class="text-right">Subtotal <?= htmlspecialchars($plat) ?></th>
                                <th class="text-center"><?= number_format($data['transaksi']) ?></th>
                                <th class="text-center"><?= number_format($data['hari']) ?></th>
                                <th class="text-right">Rp <?= number_format($data['total'], 0, ',', '.') ?></th>
                                <th class="text-right">Rp <?= number_format($data['transaksi'] > 0 ? $data['total'] / $data['transaksi'] : 0, 0, ',', '.') ?></th>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-danger">
                                <th colspan="4" class="text-right">GRAND TOTAL</th>
                                <th class="text-center"><?= number_format($total_transaksi) ?></th>
                                <th class="text-center"><?= number_format($total_hari) ?></th>
                                <th class="text-right">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></th>
                                <th class="text-right">Rp <?= number_format($rata_rata_transaksi, 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <p><strong>Keterangan:</strong></p>
                        <ul class="mb-0">
                            <li>Jumlah transaksi = total pengeluaran yang dilakukan</li>
                            <li>Jumlah hari = total hari pengeluaran yang berbeda</li>
                            <li>Rata-rata per transaksi = total pengeluaran ÷ jumlah transaksi</li>
                            <li>Laporan ini mencakup semua jenis pengeluaran operasional</li>
                        </ul>
                    </div>
                    <div class="col-md-6 text-right">
                        <p class="mb-1">Dicetak pada: <?= date('d/m/Y H:i:s') ?></p>
                        <p class="mb-0">Oleh: <?= htmlspecialchars($user['username']) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../inc/footer.php'; ?>

<script>
function exportToExcel() {
    let table = document.getElementById("tableRekap");
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    let downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    downloadLink.href = url;
    downloadLink.download = 'Laporan_Pengeluaran_Travel_<?= date('Y-m-d') ?>.xls';
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Auto submit form when filter changes
document.getElementById('jenis_pengeluaran').addEventListener('change', function() {
    document.querySelector('form').submit();
});

document.getElementById('travel_id').addEventListener('change', function() {
    document.querySelector('form').submit();
});
</script> 