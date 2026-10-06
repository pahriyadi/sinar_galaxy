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
$no_rekening = $_GET['no_rekening'] ?? '';

// Query data rekening untuk dropdown
$rekeningOptions = [];
$resRek = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening ORDER BY nama_rekening");
if ($resRek) {
    while ($r = mysqli_fetch_assoc($resRek)) {
        $rekeningOptions[] = $r;
    }
} else {
    // Handle error jika query gagal
    error_log("Error in rekening query: " . mysqli_error($conn));
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

// Query rekap pengeluaran bank
$where = "WHERE 1=1";

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

if ($no_rekening) {
    $where .= " AND pg.jenis_rekening_id = '" . mysqli_real_escape_string($conn, $no_rekening) . "'";
}

$sql = "SELECT 
    dr.id_rekening,
    dr.nama_rekening,
    CONCAT(UPPER(pg.kategori_pengeluaran), ' - ', jp.nama_pengeluaran) as nama_pengeluaran,
    mp.metode_pembayaran,
    sp.status_pembayaran,
    COUNT(*) as jumlah_transaksi,
    SUM(pg.harga_operasional) as total_pengeluaran
FROM data_pengeluaran pg
LEFT JOIN data_rekening dr ON pg.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_jenis_pengeluaran jp ON pg.jenis_pengeluaran_id = jp.id_jenis_pengeluaran
LEFT JOIN data_metode_pembayaran mp ON pg.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran
$where
GROUP BY dr.id_rekening, pg.kategori_pengeluaran, jp.id_jenis_pengeluaran, mp.id_metode_pembayaran, sp.id_status_pembayaran
ORDER BY dr.nama_rekening, pg.kategori_pengeluaran, jp.nama_pengeluaran, mp.metode_pembayaran, sp.status_pembayaran";

$resRekap = mysqli_query($conn, $sql);

$rekap = [];
$total_pengeluaran = 0;
$total_transaksi = 0;

if ($resRekap) {
    while ($row = mysqli_fetch_assoc($resRekap)) {
        $isDP = (stripos($row['status_pembayaran'], 'dp') !== false || stripos($row['status_pembayaran'], 'piutang') !== false);
        $row['is_dp'] = $isDP;
        $rekap[] = $row;
        
        if ($isDP) {
            $total_pengeluaran -= $row['total_pengeluaran'];
        } else {
            $total_pengeluaran += $row['total_pengeluaran'];
        }
        $total_transaksi += $row['jumlah_transaksi'];
    }
} else {
    // Handle error jika query gagal
    error_log("Error in rekap pengeluaran bank query: " . mysqli_error($conn));
}
?>

<style>
.report-header-box {
  background: #ffffff;
  border: 1px solid #b8b8b8;
  border-radius: 4px;
  padding: 14px 18px;
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.report-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
}
.report-icon-badge {
  width: 44px;
  height: 44px;
  border-radius: 4px;
  background: rgba(13, 159, 79, 0.1);
  color: #0d9f4f;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  border: 1px solid rgba(13, 159, 79, 0.25);
}
.report-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #202124;
  margin: 0;
  line-height: 1.3;
}
.report-subtitle {
  font-size: 0.78rem;
  color: #5f6368;
  margin: 0;
}
.filter-card-flat {
  background: #ffffff;
  border: 1px solid #b8b8b8;
  border-radius: 4px;
  box-shadow: none !important;
  margin-bottom: 16px;
}
.filter-card-flat .card-header {
  background: #ffffff;
  border-bottom: 1px solid #e0e0e0;
  padding: 10px 16px;
  font-size: 0.88rem;
  font-weight: 600;
  color: #202124;
}
.filter-card-flat .card-body {
  padding: 16px;
}
.filter-label {
  font-size: 0.78rem;
  font-weight: 600;
  color: #495057;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.table-excel-container {
  border: 1.5px solid #a0a0a0;
  border-radius: 4px;
  background: #ffffff;
  overflow: hidden;
}
.table-excel thead th {
  background: #ffffff !important;
  color: #202124 !important;
  border: 1px solid #b8b8b8 !important;
  border-bottom: 2px solid #8c8c8c !important;
  font-size: 0.82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  padding: 8px 10px;
  vertical-align: middle;
}
.table-excel tbody td {
  border: 1px solid #d0d0d0 !important;
  padding: 7px 10px;
  font-size: 0.84rem;
  vertical-align: middle;
}
.table-excel tfoot th {
  background: #fafafa !important;
  border: 1px solid #b8b8b8 !important;
  border-top: 2px solid #8c8c8c !important;
  font-size: 0.85rem;
  font-weight: 700;
  padding: 9px 10px;
}
</style>

<div class="content-wrapper">
    <!-- Header Paper White v2.0 -->
    <div class="content-header p-0 pt-3">
        <div class="container-fluid">
            <div class="report-header-box">
                <div class="report-title-group">
                    <div class="report-icon-badge">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                    <div>
                        <h1 class="report-title">Laporan Pengeluaran Kas & Bank</h1>
                        <p class="report-subtitle">Rekapitulasi arus kas keluar operasional berdasarkan rekening bank dan metode pembayaran</p>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <?php if ($asal_po && $role !== 'super admin'): ?>
                        <span class="badge" style="background:#ffffff; border:1px solid #b8b8b8; color:#202124; padding:6px 10px; font-weight:600;">
                            <i class="fas fa-map-marker-alt text-danger mr-1"></i> PO: <?= htmlspecialchars($asal_po) ?>
                        </span>
                    <?php endif; ?>
                    <span class="badge" style="background:rgba(13,159,79,0.1); border:1px solid rgba(13,159,79,0.3); color:#0d9f4f; padding:6px 10px; font-weight:600;">
                        <i class="fas fa-user-shield mr-1"></i> <?= htmlspecialchars(strtoupper($role)) ?>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print();" style="border-radius:4px; font-weight:600;">
                        <i class="fas fa-print mr-1"></i> Cetak
                    </button>
                    <button type="button" class="btn btn-sm" onclick="exportToExcel()" style="background:#0d9f4f; color:#fff; border-radius:4px; font-weight:600;">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="no-print">
                <!-- Filter Card Flat -->
                <div class="card filter-card-flat">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-filter text-muted mr-1"></i> Filter Periode & Rekening Bank</div>
                        <a href="pengeluaran_bank.php" class="btn btn-xs btn-outline-secondary" style="border-radius:3px;">
                            <i class="fas fa-undo mr-1"></i> Reset Filter
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-0">
                            <div class="row align-items-end">
                                <div class="col-md-2">
                                    <label class="filter-label" for="tgl_dari">Tanggal Dari</label>
                                    <input type="date" class="form-control form-control-sm" name="tgl_dari"
                                        id="tgl_dari" value="<?= htmlspecialchars($tgl_dari) ?>" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                </div>
                                <div class="col-md-2">
                                    <label class="filter-label" for="tgl_sampai">Tanggal Sampai</label>
                                    <input type="date" class="form-control form-control-sm" name="tgl_sampai"
                                        id="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="filter-label" for="jenis_pengeluaran">Jenis Pengeluaran</label>
                                    <select class="form-control form-control-sm" name="jenis_pengeluaran" id="jenis_pengeluaran" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                        <option value="">-- Semua Jenis --</option>
                                        <?php foreach ($jenisPengeluaranOptions as $jenis) { ?>
                                            <option value="<?= htmlspecialchars($jenis['id_jenis_pengeluaran']) ?>" <?= ($jenis_pengeluaran == $jenis['id_jenis_pengeluaran']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($jenis['nama_pengeluaran']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="filter-label" for="no_rekening">Bank / Rekening</label>
                                    <select class="form-control form-control-sm" name="no_rekening" id="no_rekening" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                        <option value="">-- Semua Bank --</option>
                                        <?php foreach ($rekeningOptions as $rek) { ?>
                                            <option value="<?= htmlspecialchars($rek['id_rekening']) ?>" <?= ($no_rekening == $rek['id_rekening']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($rek['nama_rekening']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-sm btn-block" style="background:#0d9f4f; color:#fff; border:1px solid #0d9f4f; border-radius:4px; font-weight:600; height: 31px;">
                                        <i class="fas fa-search mr-1"></i> Terapkan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-arrow-down text-danger"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pengeluaran Bank</span>
                                <span class="info-box-number text-danger">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-receipt text-info"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Transaksi</span>
                                <span class="info-box-number"><?= number_format($total_transaksi) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-university text-warning"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Bank / Akun Terlibat</span>
                                <span class="info-box-number"><?= count(array_unique(array_column($rekap, 'nama_rekening'))) ?> Akun</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Laporan Card -->
            <div class="card filter-card-flat" id="printArea">
                <div class="card-header d-flex justify-content-between align-items-center" style="background:#ffffff; border-bottom: 1px solid #e0e0e0;">
                    <div style="font-weight: 700; color: #202124; font-size: 0.95rem;">
                        <i class="fas fa-table mr-1 text-muted"></i> 
                        Tabel Rekapitulasi Pengeluaran per Rekening Bank
                        <?php if ($tgl_dari && $tgl_sampai): ?>
                            <span class="text-muted font-weight-normal" style="font-size: 0.8rem; margin-left: 8px;">(<?= date('d/m/Y', strtotime($tgl_dari)) ?> s/d <?= date('d/m/Y', strtotime($tgl_sampai)) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <span class="badge" style="background: #ffffff; border: 1px solid #b8b8b8; color: #555; padding: 4px 8px;">
                        Total Baris: <?= count($rekap) ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive table-excel-container" style="border: none; border-radius: 0;">
                        <table class="table table-bordered table-hover table-excel mb-0" id="tableRekap">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 45px;">No</th>
                                    <th>Bank / Rekening</th>
                                    <th>Jenis Pengeluaran</th>
                                    <th>Metode Pembayaran</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width: 130px;">Jml Transaksi</th>
                                    <th class="text-right" style="width: 170px;">Total Pengeluaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $subtotal_bank = [];
                                
                                foreach ($rekap as $row) {
                                    $rowStyle = $row['is_dp'] ? 'style="background-color: #fff9e6;"' : '';
                                    $statusClass = $row['is_dp'] ? 'color: #b78103;' : 'color: #c92a2a;';
                                    
                                    echo "<tr $rowStyle>";
                                    echo "<td class='text-center'>" . $no++ . "</td>";
                                    echo "<td><strong style='color: #202124;'>" . htmlspecialchars($row['nama_rekening']) . "</strong></td>";
                                    echo "<td><span class='badge' style='background: #f8f9fa; border: 1px solid #d0d0d0; color: #333; font-weight: 600; padding: 4px 8px;'>" . htmlspecialchars($row['nama_pengeluaran']) . "</span></td>";
                                    echo "<td>" . htmlspecialchars($row['metode_pembayaran']) . "</td>";
                                    echo "<td style='$statusClass font-weight: 700;'>" . htmlspecialchars($row['status_pembayaran']) . "</td>";
                                    echo "<td class='text-center'>" . number_format($row['jumlah_transaksi']) . "</td>";
                                    echo "<td class='text-right'><strong style='color: #c92a2a;'>Rp " . number_format($row['total_pengeluaran'], 0, ',', '.') . "</strong></td>";
                                    echo "</tr>";
                                    
                                    // Hitung subtotal per bank
                                    if (!isset($subtotal_bank[$row['nama_rekening']])) {
                                        $subtotal_bank[$row['nama_rekening']] = 0;
                                    }
                                    if ($row['is_dp']) {
                                        $subtotal_bank[$row['nama_rekening']] -= $row['total_pengeluaran'];
                                    } else {
                                        $subtotal_bank[$row['nama_rekening']] += $row['total_pengeluaran'];
                                    }
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <?php foreach ($subtotal_bank as $bank => $total): ?>
                                <tr style="background: #f8f9fa;">
                                    <th colspan="6" class="text-right" style="font-weight: 600;">Subtotal <?= htmlspecialchars($bank) ?>:</th>
                                    <th class="text-right" style="font-weight: 700; color: #202124;">Rp <?= number_format($total, 0, ',', '.') ?></th>
                                </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <th colspan="6" class="text-right" style="font-weight: 700; font-size: 0.95rem;">GRAND TOTAL PENGELUARAN:</th>
                                    <th class="text-right" style="font-weight: 700; color: #c92a2a; font-size: 1rem;">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p><strong>Keterangan:</strong></p>
                                <ul class="mb-0">
                                    <li>Status DP/Piutang ditandai dengan background kuning</li>
                                    <li>Total pengeluaran sudah dikurangi dengan DP/Piutang</li>
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
    downloadLink.download = 'Laporan_Pengeluaran_Bank_<?= date('Y-m-d') ?>.xls';
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Auto submit form when filter changes
document.getElementById('jenis_pengeluaran').addEventListener('change', function() {
    document.querySelector('form').submit();
});

document.getElementById('no_rekening').addEventListener('change', function() {
    document.querySelector('form').submit();
});
</script> 