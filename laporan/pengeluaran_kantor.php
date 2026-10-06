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

// Query data master untuk dropdown
$jenisPengeluaranOptions = [];
$resJenis = mysqli_query($conn, "SELECT id_jenis_pengeluaran, nama_pengeluaran FROM data_jenis_pengeluaran ORDER BY nama_pengeluaran");
if ($resJenis) {
    while ($r = mysqli_fetch_assoc($resJenis)) {
        $jenisPengeluaranOptions[] = $r;
    }
}

$rekeningOptions = [];
$resRek = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening ORDER BY nama_rekening");
if ($resRek) {
    while ($r = mysqli_fetch_assoc($resRek)) {
        $rekeningOptions[] = $r;
    }
}

// Query pengeluaran kantor
$where = "WHERE pg.kategori_pengeluaran = 'kantor'";
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
    pg.tanggal_pengeluaran,
    jp.nama_pengeluaran,
    pg.harga_operasional,
    pg.keterangan,
    dr.nama_rekening,
    mp.metode_pembayaran,
    sp.status_pembayaran,
    du.username as user_input,
    pg.asal_po
FROM data_pengeluaran pg
LEFT JOIN data_jenis_pengeluaran jp ON pg.jenis_pengeluaran_id = jp.id_jenis_pengeluaran
LEFT JOIN data_rekening dr ON pg.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_metode_pembayaran mp ON pg.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran
LEFT JOIN data_users du ON pg.user_id = du.id_users
$where
ORDER BY pg.tanggal_pengeluaran DESC, pg.id_pengeluaran DESC";

$resRekap = mysqli_query($conn, $sql);

$rekap = [];
$total_pengeluaran = 0;
$total_transaksi = 0;

if ($resRekap) {
    while ($row = mysqli_fetch_assoc($resRekap)) {
        $rekap[] = $row;
        $total_pengeluaran += $row['harga_operasional'];
        $total_transaksi++;
    }
} else {
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
  .no-print {
    display: none !important;
  }
}
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
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h1 class="report-title">Laporan Pengeluaran Operasional Kantor</h1>
                        <p class="report-subtitle">Rekapitulasi pengeluaran kantor, utilitas, konsumsi, dan operasional harian non-armada</p>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <?php if ($asal_po): ?>
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
                        <div><i class="fas fa-filter text-muted mr-1"></i> Filter Periode & Kategori Pengeluaran</div>
                        <a href="pengeluaran_kantor.php" class="btn btn-xs btn-outline-secondary" style="border-radius:3px;">
                            <i class="fas fa-undo mr-1"></i> Reset Filter
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-0">
                            <div class="row align-items-end">
                                <div class="col-md-2">
                                    <label class="filter-label" for="tgl_dari">Tanggal Dari</label>
                                    <input type="date" class="form-control form-control-sm" name="tgl_dari" id="tgl_dari" value="<?= htmlspecialchars($tgl_dari) ?>" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                </div>
                                <div class="col-md-2">
                                    <label class="filter-label" for="tgl_sampai">Tanggal Sampai</label>
                                    <input type="date" class="form-control form-control-sm" name="tgl_sampai" id="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>" style="border: 1px solid #b8b8b8; border-radius: 4px;">
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
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-receipt text-warning"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Transaksi</span>
                                <span class="info-box-number"><?= number_format($total_transaksi) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-money-bill-wave text-danger"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pengeluaran</span>
                                <span class="info-box-number text-danger">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-calculator text-info"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Rata-rata / Transaksi</span>
                                <span class="info-box-number">Rp <?= number_format($rata_rata_transaksi, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-calendar-alt text-success"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Periode Aktif</span>
                                <span class="info-box-number" style="font-size: 0.95rem;"><?= $tgl_dari && $tgl_sampai ? date('d/m/Y', strtotime($tgl_dari)) . ' - ' . date('d/m/Y', strtotime($tgl_sampai)) : 'Hari Ini' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Laporan Table Card -->
            <div class="card filter-card-flat" id="printArea">
                <div class="card-header d-flex justify-content-between align-items-center" style="background:#ffffff; border-bottom: 1px solid #e0e0e0;">
                    <div style="font-weight: 700; color: #202124; font-size: 0.95rem;">
                        <i class="fas fa-table mr-1 text-muted"></i> 
                        Tabel Detail Pengeluaran Operasional Kantor
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
                        <table class="table table-bordered table-hover table-excel mb-0" id="tablePengeluaranKantor">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 45px;">No</th>
                                    <th style="width: 120px;">Tanggal</th>
                                    <th>Jenis Pengeluaran</th>
                                    <th style="width: 150px;">Nominal</th>
                                    <th>Keterangan</th>
                                    <th style="width: 120px;">Bank</th>
                                    <th style="width: 120px;">Metode</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 100px;">User</th>
                                    <th style="width: 100px;">Asal PO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rekap)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                            Tidak ada data pengeluaran kantor
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rekap as $index => $row): ?>
                                        <tr>
                                            <td class="text-center"><?= $index + 1 ?></td>
                                            <td><?= date('d/m/Y', strtotime($row['tanggal_pengeluaran'])) ?></td>
                                            <td>
                                                <span class="badge" style="background: #f8f9fa; border: 1px solid #d0d0d0; color: #333; font-weight: 600; padding: 4px 8px;">
                                                    <?= htmlspecialchars($row['nama_pengeluaran']) ?>
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <strong style="color: #c92a2a;">
                                                    Rp <?= number_format($row['harga_operasional'], 0, ',', '.') ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['keterangan'])): ?>
                                                    <span title="<?= htmlspecialchars($row['keterangan']) ?>">
                                                        <?= strlen($row['keterangan']) > 35 ? substr(htmlspecialchars($row['keterangan']), 0, 35) . '...' : htmlspecialchars($row['keterangan']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['nama_rekening']) ?></td>
                                            <td><?= htmlspecialchars($row['metode_pembayaran']) ?></td>
                                            <td>
                                                <?php 
                                                    $st = strtolower($row['status_pembayaran']);
                                                    $bg = '#e8f5e9'; $col = '#2e7d32'; $bd = '#c8e6c9';
                                                    if (strpos($st, 'pending') !== false || strpos($st, 'proses') !== false) {
                                                        $bg = '#fff8e1'; $col = '#f57f17'; $bd = '#ffe082';
                                                    } elseif (strpos($st, 'batal') !== false || strpos($st, 'tolak') !== false) {
                                                        $bg = '#ffebee'; $col = '#c62828'; $bd = '#ffcdd2';
                                                    }
                                                ?>
                                                <span class="badge" style="background: <?= $bg ?>; color: <?= $col ?>; border: 1px solid <?= $bd ?>; padding: 4px 8px; font-weight: 600;">
                                                    <?= htmlspecialchars($row['status_pembayaran']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($row['user_input']) ?></td>
                                            <td>
                                                <span class="badge" style="background:#fff; border:1px solid #b8b8b8; color:#333;">
                                                    <?= htmlspecialchars($row['asal_po']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-right" style="font-weight: 700;">TOTAL PENGELUARAN KANTOR:</th>
                                    <th class="text-right" style="font-weight: 700; color: #c92a2a; font-size: 0.95rem;">
                                        Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?>
                                    </th>
                                    <th colspan="6"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../inc/footer.php'; ?>

<script>
function exportToExcel() {
    let table = document.getElementById("tablePengeluaranKantor");
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    let downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    downloadLink.href = url;
    downloadLink.download = 'Laporan_Pengeluaran_Kantor_<?= date('Y-m-d') ?>.xls';
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