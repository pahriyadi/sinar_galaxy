<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$role = strtolower(trim((string)($user['role'] ?? '')));

// Ambil filter dari GET
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';
$jenis_transaksi = $_GET['jenis_transaksi'] ?? 'semua';
$no_plat = $_GET['no_plat'] ?? '';
$status_pembayaran = $_GET['status_pembayaran'] ?? '';
$tujuan_perjalanan = $_GET['tujuan_perjalanan'] ?? '';

// Query data travel untuk dropdown
$travelOptions = [];
$resTravel = mysqli_query($conn, "SELECT no_plat, kelas, jumlah_kursi FROM data_travel ORDER BY no_plat");
while ($r = mysqli_fetch_assoc($resTravel)) {
    $travelOptions[] = $r;
}

// Query data status pembayaran untuk dropdown
$statusOptions = [];
$resStatus = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran ORDER BY status_pembayaran");
while ($r = mysqli_fetch_assoc($resStatus)) {
    $statusOptions[] = $r;
}

// Query data tujuan perjalanan untuk dropdown
$tujuanOptions = [];
$resTujuan = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan ORDER BY nama_tujuan");
while ($r = mysqli_fetch_assoc($resTujuan)) {
    $tujuanOptions[] = $r;
}

// Query data tujuan untuk mapping
$tujuanMap = [];
$resTujuan = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
while ($r = mysqli_fetch_assoc($resTujuan)) {
    $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];
}

// Query rekap data keberangkatan
$where_pemesanan = "WHERE 1=1";
$where_pengiriman = "WHERE 1=1";

// Pembatasan akses berdasarkan role
// - users: batasi berdasarkan user + asal_po
// - admin: batasi berdasarkan asal_po saja
// - super admin: tanpa pembatasan
if ($role === 'users') {
    // username berbasis id_users, asal_po di tabel transaksi menyimpan id_users asal_po (bukan teks), maka mapping via subquery
    $where_pemesanan .= " AND pm.username = '" . mysqli_real_escape_string($conn, $id_users) . "'"
                      . " AND pm.asal_po IN (SELECT id_users FROM data_users WHERE asal_po = '" . mysqli_real_escape_string($conn, $asal_po) . "')";
    $where_pengiriman .= " AND pg.user_id = '" . mysqli_real_escape_string($conn, $id_users) . "'"
                       . " AND pg.asal_po_id IN (SELECT id_users FROM data_users WHERE asal_po = '" . mysqli_real_escape_string($conn, $asal_po) . "')";
}

if ($tgl_dari && $tgl_sampai) {
    $where_pemesanan .= " AND DATE(pm.tanggal_berangkat) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} elseif ($tgl_dari) {
    $where_pemesanan .= " AND DATE(pm.tanggal_berangkat) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
} elseif ($tgl_sampai) {
    $where_pemesanan .= " AND DATE(pm.tanggal_berangkat) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} else {
    $where_pemesanan .= " AND DATE(pm.tanggal_berangkat) = CURDATE()";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = CURDATE()";
}

if ($no_plat) {
    $where_pemesanan .= " AND pm.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
    $where_pengiriman .= " AND pg.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
}

if ($status_pembayaran) {
    $where_pemesanan .= " AND pm.status_pembayaran_id = '" . mysqli_real_escape_string($conn, $status_pembayaran) . "'";
    $where_pengiriman .= " AND pg.status_pembayaran_id = '" . mysqli_real_escape_string($conn, $status_pembayaran) . "'";
}

if ($tujuan_perjalanan) {
    $where_pemesanan .= " AND pm.tujuan_id = '" . mysqli_real_escape_string($conn, $tujuan_perjalanan) . "'";
    $where_pengiriman .= " AND pg.tujuan_id = '" . mysqli_real_escape_string($conn, $tujuan_perjalanan) . "'";
}

// Query untuk pemesanan (tiket)
$sql_pemesanan = "SELECT 
    'Tiket' as jenis_transaksi,
    pm.id_pemesanan as id_transaksi,
    pm.nip_sgt_id,
    pm.nama_id,
    pm.alamat_id,
    pm.no_ktp_id,
    pm.no_hp_id,
    pm.tanggal_pemesanan,
    pm.tanggal_berangkat,
    pm.no_plat_id,
    pm.kelas_id,
    pm.harga_id,
    pm.kursi,
    pm.tujuan_id,
    pm.status_pembayaran_id,
    pm.metode_pembayaran_id,
    pm.jenis_rekening_id,
    pm.username,
    pm.asal_po,
    pm.keterangan,
    dt.kelas,
    sp.status_pembayaran,
    mp.metode_pembayaran,
    dr.nama_rekening
FROM data_pemesanan pm
LEFT JOIN data_travel dt ON pm.no_plat_id = dt.no_plat
LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
$where_pemesanan
ORDER BY pm.tanggal_berangkat ASC, pm.no_plat_id ASC, pm.kursi ASC";

// Query untuk pengiriman (paket)
$sql_pengiriman = "SELECT 
    'Paket' as jenis_transaksi,
    pg.id_pengiriman as id_transaksi,
    pg.nip_sgt_id,
    pg.nama_id,
    pg.alamat_id,
    pg.no_ktp_id,
    pg.no_hp_id,
    pg.tanggal_pengiriman as tanggal_pemesanan,
    pg.tanggal_pengiriman as tanggal_berangkat,
    pg.no_plat_id,
    pg.kelas_id,
    pg.jumlah as harga_id,
    '-' as kursi,
    pg.tujuan_id,
    pg.status_pembayaran_id,
    pg.metode_pembayaran_id,
    pg.jenis_rekening_id,
    pg.user_id as username,
    pg.asal_po_id as asal_po,
    pg.keterangan,
    dt.kelas,
    sp.status_pembayaran,
    mp.metode_pembayaran,
    dr.nama_rekening
FROM data_pengiriman pg
LEFT JOIN data_travel dt ON pg.no_plat_id = dt.no_plat
LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran
LEFT JOIN data_metode_pembayaran mp ON pg.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_rekening dr ON pg.jenis_rekening_id = dr.id_rekening
$where_pengiriman
ORDER BY pg.tanggal_pengiriman ASC, pg.no_plat_id ASC";

// Gabungkan query berdasarkan filter
if ($jenis_transaksi === 'tiket') {
    $sql = $sql_pemesanan;
} elseif ($jenis_transaksi === 'paket') {
    $sql = $sql_pengiriman;
} else {
    $sql = "($sql_pemesanan) UNION ALL ($sql_pengiriman) ORDER BY tanggal_berangkat ASC, no_plat_id ASC, kursi ASC";
}

$resRekap = mysqli_query($conn, $sql);

$rekap = [];
$total_pendapatan = 0;
$total_transaksi = 0;
$total_tiket = 0;
$total_paket = 0;

while ($row = mysqli_fetch_assoc($resRekap)) {
    $rekap[] = $row;
    $total_pendapatan += $row['harga_id'];
    $total_transaksi++;
    
    if ($row['jenis_transaksi'] === 'Tiket') {
        $total_tiket++;
    } else {
        $total_paket++;
    }
}

// Hitung rata-rata per transaksi
$rata_rata_transaksi = $total_transaksi > 0 ? $total_pendapatan / $total_transaksi : 0;
?>

<style>
/* ==========================================================================
   Paper White Design System v2.0 & Optimal Print Architecture
   Manifes Keberangkatan & Kargo Seluruh Cabang PO
   ========================================================================== */
:root {
    --bg-white: #ffffff;
    --border-gray: #b8b8b8;
    --border-light: #e0e0e0;
    --text-main: #212529;
    --text-muted: #6c757d;
    --theme-green: #0d9f4f;
    --theme-green-hover: #0b8040;
}

.report-header-box {
    background: #ffffff;
    border: 1px solid #b8b8b8;
    border-radius: 4px;
    padding: 14px 18px;
    margin-bottom: 16px;
    box-shadow: none !important;
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
    border-bottom: 1px solid #b8b8b8;
    padding: 10px 16px;
}

.stat-card-flat {
    background: #ffffff;
    border: 1px solid #b8b8b8;
    border-radius: 4px;
    padding: 12px 14px;
    height: 100%;
    display: flex;
    align-items: center;
    box-shadow: none !important;
    transition: border-color 0.15s ease;
}

.stat-card-flat:hover {
    border-color: #7a7a7a;
}

.stat-card-flat .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-right: 12px;
    flex-shrink: 0;
}

.stat-card-flat .stat-content {
    flex-grow: 1;
    overflow: hidden;
}

.stat-card-flat .stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #6c757d;
    margin-bottom: 2px;
}

.stat-card-flat .stat-value {
    font-size: 1.15rem;
    font-weight: 700;
    color: #212529;
    line-height: 1.2;
}

.table-excel-container {
    background: #ffffff;
    border: 1px solid #b8b8b8;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 16px;
}

.table-excel {
    width: 100%;
    border-collapse: collapse !important;
    font-size: 0.84rem;
    background: #ffffff;
    color: #212529;
    margin-bottom: 0 !important;
}

.table-excel th {
    background-color: #ffffff !important;
    color: #212529 !important;
    border: 1px solid #b8b8b8 !important;
    font-weight: 700 !important;
    text-align: center;
    vertical-align: middle !important;
    padding: 8px 10px !important;
    font-size: 0.82rem;
}

.table-excel td {
    border: 1px solid #b8b8b8 !important;
    padding: 7px 10px !important;
    vertical-align: middle !important;
    background-color: #ffffff;
}

.table-excel tbody tr:nth-of-type(even) td {
    background-color: #fafafa !important;
}

.table-excel tbody tr:hover td {
    background-color: #f1f3f4 !important;
}

.table-excel tfoot th, .table-excel tfoot td {
    background-color: #f8f9fa !important;
    border: 1px solid #b8b8b8 !important;
    font-weight: 700 !important;
    color: #212529 !important;
    padding: 8px 10px !important;
}

/* ==========================================================================
   Print Layout System (Landscape A4, Zero Glitch, High Quality)
   ========================================================================== */
#printReportDocument {
    display: none;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 8mm 8mm 10mm 8mm;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body, html {
        background: #ffffff !important;
        color: #000000 !important;
        font-family: Arial, "Helvetica Neue", Helvetica, sans-serif !important;
        font-size: 9.5pt !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .no-print,
    .main-header,
    .main-sidebar,
    .main-footer,
    .content-header,
    .filter-card-flat,
    .stat-card-flat,
    .table-excel-container,
    .modal,
    .alert,
    .btn {
        display: none !important;
    }

    .content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        border: none !important;
    }

    #printReportDocument {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
    }

    .print-kop {
        text-align: center;
        border-bottom: 2.5px solid #000000;
        padding-bottom: 6px;
        margin-bottom: 8px;
    }

    .print-kop h1 {
        font-size: 16pt !important;
        font-weight: 800 !important;
        margin: 0 0 3px 0 !important;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #000000 !important;
    }

    .print-kop h2 {
        font-size: 11.5pt !important;
        font-weight: 700 !important;
        margin: 0 0 4px 0 !important;
        text-transform: uppercase;
        color: #000000 !important;
    }

    .print-meta-table {
        width: 100%;
        margin-bottom: 8px;
        font-size: 8.5pt;
        border-collapse: collapse;
    }

    .print-meta-table td {
        padding: 2px 4px;
        vertical-align: top;
        border: none !important;
    }

    .print-table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 8.5pt !important;
        margin-bottom: 10px !important;
    }

    .print-table thead {
        display: table-header-group !important;
    }

    .print-table th {
        background-color: #f0f0f0 !important;
        color: #000000 !important;
        border: 1px solid #000000 !important;
        padding: 4px 5px !important;
        font-weight: 700 !important;
        text-align: center !important;
        vertical-align: middle !important;
        font-size: 8.5pt !important;
    }

    .print-table td {
        border: 1px solid #000000 !important;
        padding: 4px 5px !important;
        vertical-align: middle !important;
        color: #000000 !important;
        font-size: 8pt !important;
    }

    .print-table tr {
        page-break-inside: avoid !important;
    }

    .print-table tfoot th, .print-table tfoot td {
        background-color: #f0f0f0 !important;
        font-weight: 700 !important;
        border: 1px solid #000000 !important;
        padding: 4px 5px !important;
    }

    .print-signatures {
        margin-top: 15px;
        page-break-inside: avoid !important;
        width: 100%;
    }

    .print-signatures td {
        border: none !important;
        text-align: center;
        vertical-align: top;
        font-size: 8.5pt;
        padding: 4px 10px;
    }
}
</style>

<div class="content-wrapper" style="background-color: #fcfcfc;">
    <!-- Content Header (Paper White v2.0) -->
    <div class="content-header no-print" style="padding: 14px 18px 8px; margin-bottom: 0;">
        <div class="container-fluid">
            <div class="report-header-box">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <div class="d-flex align-items-center">
                            <div style="width: 44px; height: 44px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 14px; color: #0d9f4f; font-size: 1.3rem;">
                                <i class="fas fa-bus-alt"></i>
                            </div>
                            <div>
                                <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Manifes Keberangkatan (Semua Cabang)</h1>
                                <div class="text-muted" style="font-size: 0.83rem; margin-top: 2px;">
                                    <span>Rekap jadwal penumpang & paket barang terpadu lintas seluruh kantor cabang</span>
                                    <span class="mx-1">•</span>
                                    <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                                        <i class="fas fa-building mr-1"></i><?= $role === 'super admin' ? 'Seluruh Cabang PO' : 'PO ' . htmlspecialchars($asal_po) ?>
                                    </span>
                                    <span class="badge" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                                        <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars(strtoupper($role)) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 text-right no-print mt-2 mt-md-0">
                        <?php if ($role === 'super admin'): ?>
                        <button type="button" class="btn btn-warning btn-sm btn-flat font-weight-bold" id="btnOpenModalGantiArmada" style="background-color: #f59e0b; border-color: #d97706; color: #ffffff; margin-right: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.12);" title="Alihkan seluruh penumpang dari travel yang batal ke armada pengganti">
                            <i class="fas fa-exchange-alt mr-1"></i> Ganti Armada Pengganti
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-default btn-sm btn-flat" onclick="previewPrint()" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600; margin-right: 6px;">
                            <i class="fas fa-eye mr-1 text-info"></i> Pratinjau Cetak
                        </button>
                        <button type="button" class="btn btn-primary btn-sm btn-flat" onclick="printReport()" style="background-color: #0056b3; border-color: #004085; font-weight: 600; margin-right: 6px;">
                            <i class="fas fa-print mr-1"></i> Cetak Manifes (A4)
                        </button>
                        <button type="button" class="btn btn-success btn-sm btn-flat" onclick="exportToExcel()" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600;">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="content no-print" style="padding: 0 18px 24px;">
        <div class="container-fluid">
            <!-- Filter Card (Paper White v2.0) -->
            <div class="filter-card-flat no-print">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark" style="font-size: 0.92rem; margin: 0;">
                        <i class="fas fa-filter mr-1 text-muted"></i> Parameter Filter Manifes Keberangkatan
                    </h3>
                </div>
                <div class="card-body" style="padding: 14px 16px;">
                    <form method="GET" class="row align-items-end">
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Tanggal Dari:</label>
                            <input type="date" name="tgl_dari" value="<?= htmlspecialchars($tgl_dari) ?>" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Tanggal Sampai:</label>
                            <input type="date" name="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Jenis Transaksi:</label>
                            <select name="jenis_transaksi" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="semua" <?= $jenis_transaksi === 'semua' ? 'selected' : '' ?>>Semua (Tiket & Paket)</option>
                                <option value="tiket" <?= $jenis_transaksi === 'tiket' ? 'selected' : '' ?>>Tiket Penumpang</option>
                                <option value="paket" <?= $jenis_transaksi === 'paket' ? 'selected' : '' ?>>Paket Barang</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Armada / No. Plat:</label>
                            <select name="no_plat" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="">-- Semua Armada --</option>
                                <?php foreach ($travelOptions as $travel): ?>
                                    <option value="<?= htmlspecialchars($travel['no_plat']) ?>" <?= ($no_plat == $travel['no_plat']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($travel['no_plat']) ?> (<?= htmlspecialchars($travel['kelas']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Status Pembayaran:</label>
                            <select name="status_pembayaran" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="">-- Semua Status --</option>
                                <?php foreach ($statusOptions as $status): ?>
                                    <option value="<?= htmlspecialchars($status['id_status_pembayaran']) ?>" <?= ($status_pembayaran == $status['id_status_pembayaran']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['status_pembayaran']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Tujuan Perjalanan:</label>
                            <select name="tujuan_perjalanan" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="">-- Semua Tujuan --</option>
                                <?php foreach ($tujuanOptions as $tujuan): ?>
                                    <option value="<?= htmlspecialchars($tujuan['id_tujuan_perjalanan']) ?>" <?= ($tujuan_perjalanan == $tujuan['id_tujuan_perjalanan']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tujuan['nama_tujuan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 text-right mt-2">
                            <button type="submit" class="btn btn-primary btn-sm btn-flat font-weight-bold" style="background-color: #0d9f4f; border-color: #076e34;">
                                <i class="fas fa-search mr-1"></i> Terapkan Filter
                            </button>
                            <a href="daftar_keberangkatan_all.php" class="btn btn-default btn-sm btn-flat font-weight-bold ml-1" style="border: 1px solid #b8b8b8; background: #ffffff;">
                                <i class="fas fa-undo mr-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- KPI Metrik Flat (Paper White v2.0) -->
            <div class="row mb-3">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #e6f4ea; border: 1px solid #b7e1cd; color: #0d9f4f;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Nilai Manifes</div>
                            <div class="stat-value" style="color: #0d9f4f;">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #e8f0fe; border: 1px solid #d2e3fc; color: #1a73e8;">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Transaksi</div>
                            <div class="stat-value"><?= number_format($total_transaksi, 0, ',', '.') ?> <small style="font-size: 0.75rem; font-weight: normal; color: #6c757d;">Item</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #e6f4ea; border: 1px solid #b7e1cd; color: #076e34;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Penumpang (Tiket)</div>
                            <div class="stat-value" style="color: #076e34;"><?= number_format($total_tiket, 0, ',', '.') ?> <small style="font-size: 0.75rem; font-weight: normal; color: #6c757d;">Orang</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #fef7e0; border: 1px solid #feefc3; color: #b06000;">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Titipan (Paket)</div>
                            <div class="stat-value" style="color: #b06000;"><?= number_format($total_paket, 0, ',', '.') ?> <small style="font-size: 0.75rem; font-weight: normal; color: #6c757d;">Koli/Paket</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Layar (Paper White v2.0) -->
            <div class="table-excel-container">
                <div class="p-3 bg-white border-bottom" style="border-color: #b8b8b8 !important; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="m-0 font-weight-bold text-dark" style="font-size: 0.95rem;">
                        <i class="fas fa-list-alt mr-1 text-muted"></i> Daftar Rincian Manifes Keberangkatan & Kargo
                    </h3>
                    <span class="badge" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                        Menampilkan: <?= count($rekap) ?> Data Transaksi
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table-excel" id="tableManifesScreen">
                        <thead>
                            <tr>
                                <th style="width: 3%; text-align: center;">#</th>
                                <th style="width: 7%; text-align: center;">Jenis</th>
                                <th style="width: 17%; text-align: left;">Nama Penumpang / Pengirim</th>
                                <th style="width: 11%; text-align: left;">No. Identitas (KTP)</th>
                                <th style="width: 10%; text-align: left;">No. Handphone</th>
                                <th style="width: 9%; text-align: center;">No. Plat</th>
                                <th style="width: 6%; text-align: center;">Kursi</th>
                                <th style="width: 12%; text-align: left;">Kota Tujuan</th>
                                <th style="width: 15%; text-align: left;">Lokasi Jemput / Alamat</th>
                                <th style="width: 10%; text-align: right;">Tarif / Ongkir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rekap)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle mr-1"></i> Tidak ada data manifes keberangkatan pada filter yang ditentukan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = 1; 
                                foreach ($rekap as $row): 
                                    $tujuanID = trim((string) $row['tujuan_id']);
                                    $namaTujuan = $tujuanMap[$tujuanID] ?? ($row['tujuan_id'] ?: '-');
                                    $isTiket = ($row['jenis_transaksi'] === 'Tiket');
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td class="text-center">
                                            <?php if ($isTiket): ?>
                                                <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600; font-size: 0.75rem; padding: 3px 6px;">
                                                    <i class="fas fa-user mr-1"></i> Tiket
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #fef7e0; color: #b06000; border: 1px solid #feefc3; font-weight: 600; font-size: 0.75rem; padding: 3px 6px;">
                                                    <i class="fas fa-box mr-1"></i> Paket
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($row['nama_id']) ?></strong>
                                        </td>
                                        <td style="color: #495057; font-family: monospace; font-size: 0.85rem;">
                                            <?= htmlspecialchars($row['no_ktp_id'] ?: '-') ?>
                                        </td>
                                        <td>
                                            <span class="text-dark font-weight-bold" style="font-size: 0.84rem;">
                                                <?= htmlspecialchars($row['no_hp_id'] ?: '-') ?>
                                            </span>
                                        </td>
                                        <td class="text-center font-weight-bold" style="color: #0056b3;">
                                            <?= htmlspecialchars($row['no_plat_id'] ?: '-') ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($isTiket && !empty($row['kursi']) && $row['kursi'] !== '-'): ?>
                                                <span class="badge badge-secondary" style="background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; font-weight: 700; padding: 4px 7px;">
                                                    <?= htmlspecialchars($row['kursi']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-dark font-weight-bold"><?= htmlspecialchars($namaTujuan) ?></span>
                                        </td>
                                        <td style="font-size: 0.82rem; color: #495057;">
                                            <?= htmlspecialchars($row['keterangan'] ?: ($row['alamat_id'] ?: '-')) ?>
                                        </td>
                                        <td class="text-right font-weight-bold" style="color: #0d9f4f;">
                                            Rp <?= number_format($row['harga_id'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-center">TOTAL REKAPITULASI MANIFES</th>
                                <th class="text-center">
                                    <span class="badge" style="background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; font-weight: 700;">
                                        <?= $total_tiket ?> Penumpang
                                    </span>
                                </th>
                                <th></th>
                                <th class="text-right text-muted" style="font-size: 0.8rem;">
                                    <?= $total_paket ?> Paket
                                </th>
                                <th class="text-right" style="color: #0d9f4f; font-size: 0.95rem;">
                                    Rp <?= number_format($total_pendapatan, 0, ',', '.') ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<?php if ($role === 'super admin'): ?>
<!-- Modal Ganti Armada Pengganti (Tukar Plat Massal) -->
<div class="modal fade no-print" id="modalGantiArmada" tabindex="-1" role="dialog" aria-labelledby="modalGantiArmadaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form id="formGantiArmada" class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 6px; box-shadow: 0 8px 30px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background: #fffbeb; border-bottom: 1px solid #fde68a; padding: 14px 20px;">
                <div class="d-flex align-items-center">
                    <div style="width: 38px; height: 38px; border-radius: 4px; background: #fef3c7; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #d97706; font-size: 1.2rem;">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark" id="modalGantiArmadaLabel" style="font-size: 1.15rem; margin: 0;">
                            Pengalihan Armada Travel (Tukar Plat Pengganti)
                        </h5>
                        <small class="text-muted">Pindahkan seluruh penumpang & paket dari armada yang batal ke mobil pengganti dalam satu klik</small>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; color: #5f6368;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body" style="padding: 22px 24px; background: #ffffff;">
                <div class="alert alert-warning" style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 0.85rem; border-radius: 4px; padding: 10px 14px;">
                    <i class="fas fa-info-circle mr-1"></i>
                    Fitur ini akan secara massal memperbarui seluruh tiket penumpang yang telah dijadwalkan pada armada asal ke nomor plat dan kelas armada baru yang Anda pilih.
                </div>

                <div class="row">
                    <!-- 1. Tanggal Keberangkatan -->
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                            Tanggal Keberangkatan <span class="text-danger">*</span>
                        </label>
                        <input type="date" class="form-control form-control-sm" name="tanggal_berangkat" id="ga_tanggal_berangkat" value="<?= htmlspecialchars($tgl_dari ?: date('Y-m-d')) ?>" required style="border: 1px solid #b8b8b8;">
                        <small class="text-muted">Pilih tanggal jadwal armada yang batal berangkat.</small>
                    </div>

                    <!-- 2. Jam Keberangkatan Baru (Opsional) -->
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                            Jam Berangkat Baru <span class="text-muted font-weight-normal">(Opsional)</span>
                        </label>
                        <input type="time" class="form-control form-control-sm" name="jam_baru" id="ga_jam_baru" style="border: 1px solid #b8b8b8;">
                        <small class="text-muted">Kosongkan jika jam berangkat tetap sama seperti semula.</small>
                    </div>
                </div>

                <div class="row">
                    <!-- 3. Plat Asal (Mobil Lama yang Batal) -->
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                            Armada Asal (Plat Lama / Batal) <span class="text-danger">*</span>
                        </label>
                        <select class="form-control form-control-sm" name="plat_lama" id="ga_plat_lama" required style="border: 1.5px solid #ef4444; font-weight: 600;">
                            <option value="">-- Memuat Armada Terjadwal... --</option>
                        </select>
                        <div id="ga_plat_lama_info" class="mt-1 text-muted" style="font-size: 0.78rem;">
                            Memuat daftar armada terjadwal...
                        </div>
                    </div>

                    <!-- 4. Plat Pengganti (Mobil Baru) -->
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                            Armada Pengganti (Plat Baru) <span class="text-danger">*</span>
                        </label>
                        <select class="form-control form-control-sm" name="plat_baru" id="ga_plat_baru" required style="border: 1.5px solid #0d9f4f; font-weight: 600; color: #076e34;">
                            <option value="">-- Pilih Armada Pengganti --</option>
                            <?php foreach ($travelOptions as $tc): ?>
                                <option value="<?= htmlspecialchars($tc['no_plat']) ?>" data-kelas="<?= htmlspecialchars($tc['kelas']) ?>" data-kursi="<?= (int)($tc['jumlah_kursi'] ?? 0) ?>">
                                    <?= htmlspecialchars($tc['no_plat']) ?> - <?= htmlspecialchars($tc['kelas']) ?> (Kapasitas: <?= (int)($tc['jumlah_kursi'] ?? 0) ?> Kursi)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Armada yang siap jalan menggantikan.</small>
                    </div>
                </div>

                <!-- 5. Opsi Paket & Catatan -->
                <div class="card p-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="ga_pindah_paket" name="pindah_paket" value="1" checked>
                        <label class="custom-control-label font-weight-bold text-dark" for="ga_pindah_paket" style="font-size: 0.84rem; cursor: pointer;">
                            <i class="fas fa-boxes mr-1 text-primary"></i> Pindahkan juga paket kiriman / kargo pada armada tersebut ke plat baru
                        </label>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark" style="font-size: 0.82rem; margin-bottom: 3px;">
                            Alasan / Catatan Pengalihan <span class="text-muted font-weight-normal">(Opsional)</span>:
                        </label>
                        <input type="text" class="form-control form-control-sm" name="catatan" id="ga_catatan" placeholder="Contoh: Penggantian unit travel karena kendala teknis / rotasi armada" style="border: 1px solid #cbd5e1;">
                    </div>
                </div>

                <!-- Preview Ringkasan Pengalihan -->
                <div id="ga_preview_box" class="p-3 d-none" style="background: #e6f4ea; border: 1px solid #b7e1cd; border-radius: 4px;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge badge-success font-weight-bold mr-2" style="font-size: 0.8rem;">SIAP DIALIHKAN</span>
                            <span id="ga_summary_text" class="text-dark font-weight-600" style="font-size: 0.88rem;"></span>
                        </div>
                        <i class="fas fa-arrow-right text-success" style="font-size: 1.2rem;"></i>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e0e0e0; padding: 12px 20px;">
                <button type="button" class="btn btn-default btn-sm btn-flat font-weight-bold" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff; color: #4b5563; border-radius: 4px; padding: 6px 16px;">
                    <i class="fas fa-times mr-1"></i> Batal
                </button>
                <button type="submit" id="btnSubmitGantiArmada" class="btn btn-warning btn-sm btn-flat font-weight-bold" style="background-color: #f59e0b; border-color: #d97706; color: #ffffff; border-radius: 4px; padding: 6px 20px;">
                    <i class="fas fa-exchange-alt mr-1"></i> Alihkan Seluruh Penumpang Sekarang
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ==========================================================================
     DOKUMEN CETAK RESMI (Format Landscape A4 Profesional untuk Print & PDF)
     ========================================================================== -->
<div id="printReportDocument">
    <!-- Kop Laporan Resmi -->
    <div class="print-kop">
        <h1>PO. SINAR GALAXY TRAVEL</h1>
        <h2>DAFTAR MANIFES KEBERANGKATAN PENUMPANG & PENGIRIMAN PAKET</h2>
        <div style="font-size: 9pt; color: #333; margin-top: 2px;">
            Kantor Pusat & Operasional Travel Antar Kota / Antar Provinsi
        </div>
    </div>

    <!-- Parameter & Informasi Manifes -->
    <table class="print-meta-table">
        <tr>
            <td style="width: 14%; font-weight: bold;">Tanggal Berangkat</td>
            <td style="width: 36%;">: 
                <?php
                if ($tgl_dari && $tgl_sampai) {
                    echo date('d/m/Y', strtotime($tgl_dari)) . " s/d " . date('d/m/Y', strtotime($tgl_sampai));
                } elseif ($tgl_dari) {
                    echo date('d/m/Y', strtotime($tgl_dari));
                } elseif ($tgl_sampai) {
                    echo date('d/m/Y', strtotime($tgl_sampai));
                } else {
                    echo date('d/m/Y') . " (Hari Ini)";
                }
                ?>
            </td>
            <td style="width: 15%; font-weight: bold;">Armada / Plat</td>
            <td style="width: 35%;">: <?= htmlspecialchars($no_plat ?: 'Semua Armada') ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Cabang / PO</td>
            <td>: <?= htmlspecialchars($asal_po ?: 'Seluruh Cabang PO') ?></td>
            <td style="font-weight: bold;">Tujuan Perjalanan</td>
            <td>: 
                <?php
                if ($tujuan_perjalanan) {
                    $selectedTujuan = array_filter($tujuanOptions, function($tujuan) use ($tujuan_perjalanan) {
                        return $tujuan['id_tujuan_perjalanan'] == $tujuan_perjalanan;
                    });
                    $selectedTujuan = reset($selectedTujuan);
                    echo htmlspecialchars($selectedTujuan['nama_tujuan'] ?? 'Semua Tujuan');
                } else {
                    echo 'Semua Kota Tujuan';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Petugas / Kasir</td>
            <td>: <?= htmlspecialchars($user['username'] ?? 'Petugas') ?></td>
            <td style="font-weight: bold;">Waktu Cetak</td>
            <td>: <?= date('d/m/Y H:i:s') ?> WITA</td>
        </tr>
    </table>

    <!-- Ringkasan Singkat Cetak -->
    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; border: 1px solid #000; padding: 4px 8px; font-size: 8.5pt; background: #fafafa;">
        <div><strong>Total Muatan:</strong> <?= count($rekap) ?> Transaksi</div>
        <div><strong>Penumpang (Tiket):</strong> <?= $total_tiket ?> Orang</div>
        <div><strong>Paket Titipan:</strong> <?= $total_paket ?> Koli</div>
        <div><strong>Total Pembayaran:</strong> Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
    </div>

    <!-- Tabel Data Cetak -->
    <table class="print-table">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 8%;">Jenis</th>
                <th style="width: 18%; text-align: left;">Nama Penumpang / Pengirim</th>
                <th style="width: 12%; text-align: left;">No. Identitas (KTP)</th>
                <th style="width: 11%; text-align: left;">No. HP</th>
                <th style="width: 9%;">No. Plat</th>
                <th style="width: 6%;">Kursi</th>
                <th style="width: 14%; text-align: left;">Kota Tujuan</th>
                <th style="width: 18%; text-align: left;">Lokasi Jemput / Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rekap)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 12px;">Tidak ada catatan keberangkatan pada kriteria ini.</td>
                </tr>
            <?php else: ?>
                <?php 
                $pNo = 1; 
                foreach ($rekap as $row): 
                    $tujuanID = trim((string) $row['tujuan_id']);
                    $namaTujuan = $tujuanMap[$tujuanID] ?? ($row['tujuan_id'] ?: '-');
                    $isTiket = ($row['jenis_transaksi'] === 'Tiket');
                ?>
                    <tr>
                        <td style="text-align: center;"><?= $pNo++ ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($row['jenis_transaksi']) ?></td>
                        <td style="font-weight: bold;"><?= htmlspecialchars($row['nama_id']) ?></td>
                        <td><?= htmlspecialchars($row['no_ktp_id'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['no_hp_id'] ?: '-') ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($row['no_plat_id'] ?: '-') ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= ($isTiket && !empty($row['kursi']) && $row['kursi'] !== '-') ? htmlspecialchars($row['kursi']) : '-' ?></td>
                        <td><?= htmlspecialchars($namaTujuan) ?></td>
                        <td><?= htmlspecialchars($row['keterangan'] ?: ($row['alamat_id'] ?: '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Lembar Tanda Tangan 3 Pihak (Resmi Operasional Travel) -->
    <table class="print-signatures">
        <tr>
            <td style="width: 33%;">
                Petugas Loket / Kasir,
                <br><br><br><br>
                <strong>( <?= htmlspecialchars($user['username'] ?? '...........................') ?> )</strong>
            </td>
            <td style="width: 34%;">
                Pemeriksa Kendaraan (Checker),
                <br><br><br><br>
                <strong>( .......................................... )</strong>
            </td>
            <td style="width: 33%;">
                Pengemudi / Driver Armada,
                <br><br><br><br>
                <strong>( .......................................... )</strong>
            </td>
        </tr>
    </table>
</div>

<!-- Modal Pratinjau Cetak (Preview Modal) -->
<div class="modal fade no-print" id="modalPreviewPrint" tabindex="-1" role="dialog" aria-labelledby="modalPreviewPrintLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document" style="max-width: 95%;">
        <div class="modal-content" style="border-radius: 4px; border: 1px solid #b8b8b8;">
            <div class="modal-header" style="background: #f8f9fa; border-bottom: 1px solid #b8b8b8; padding: 12px 18px;">
                <h5 class="modal-title font-weight-bold text-dark" id="modalPreviewPrintLabel">
                    <i class="fas fa-print mr-2 text-primary"></i> Pratinjau Lembar Manifes Cetak (Landscape A4)
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="background: #525659; padding: 20px; max-height: 75vh; overflow-y: auto;">
                <div id="previewPaper" style="background: #ffffff; color: #000000; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-radius: 2px; min-height: 500px;">
                    <!-- Konten hasil render di-inject lewat JS -->
                </div>
            </div>
            <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #b8b8b8; padding: 10px 18px;">
                <button type="button" class="btn btn-default btn-sm btn-flat font-weight-bold" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff;">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
                <button type="button" class="btn btn-primary btn-sm btn-flat font-weight-bold" onclick="executePrintFromModal()">
                    <i class="fas fa-print mr-1"></i> Kirim ke Printer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Cetak Dokumen Langsung menggunakan Browser Print Engine
 * Bersih dari popup blocker dan terformat landscape A4 optimal.
 */
function printReport() {
    window.print();
}

/**
 * Pratinjau Cetak menggunakan Modal interaktif
 */
function previewPrint() {
    const printDoc = document.getElementById('printReportDocument');
    const previewContainer = document.getElementById('previewPaper');
    
    if (printDoc && previewContainer) {
        previewContainer.innerHTML = printDoc.innerHTML;
        // Tampilkan modal (menggunakan jQuery Bootstrap modal yang sudah tersedia di proyek)
        if (typeof $ !== 'undefined' && $('#modalPreviewPrint').length) {
            $('#modalPreviewPrint').modal('show');
        } else {
            window.print();
        }
    }
}

function executePrintFromModal() {
    if (typeof $ !== 'undefined') {
        $('#modalPreviewPrint').modal('hide');
    }
    setTimeout(() => {
        window.print();
    }, 400);
}

/**
 * Export Data Tabel ke Excel (.xls)
 */
function exportToExcel() {
    const table = document.getElementById('tableManifesScreen');
    if (!table) {
        alert('Tabel data manifes tidak ditemukan!');
        return;
    }

    const cloneTable = table.cloneNode(true);
    cloneTable.querySelectorAll('.no-print').forEach(el => el.remove());

    const html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Manifes Keberangkatan</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
            <meta http-equiv="content-type" content="text/plain; charset=UTF-8"/>
            <style>
                table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 10pt; }
                th { background-color: #f2f2f2; border: 1px solid #000000; text-align: center; font-weight: bold; padding: 6px; }
                td { border: 1px solid #000000; padding: 5px; }
            </style>
        </head>
        <body>
            <h3 style="margin-bottom: 2px;">PO. SINAR GALAXY TRAVEL - MANIFES KEBERANGKATAN</h3>
            <p style="margin-top: 0; font-size: 9pt; color: #555;">
                Periode: <?= $tgl_dari ?: date('Y-m-d') ?> s/d <?= $tgl_sampai ?: date('Y-m-d') ?> | Armada: <?= htmlspecialchars($no_plat ?: 'Semua') ?> | Asal PO: <?= htmlspecialchars($asal_po ?: 'Semua') ?>
            </p>
            ${cloneTable.outerHTML}
        </body>
        </html>
    `;

    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Manifes_Keberangkatan_${new Date().toISOString().slice(0, 10)}.xls`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Shortcut Keyboard Ctrl+P untuk langsung memanggil print
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        printReport();
    }
});
</script>
<?php include '../inc/footer.php'; ?>

<?php if ($role === 'super admin'): ?>
<!-- Handler Interaktif Fitur Ganti Armada Pengganti (Tukar Plat Massal) - Khusus Super Admin -->
<script>
$(function () {
    const defaultPlatFilter = '<?= htmlspecialchars($no_plat ?? '') ?>';

    // Buka Modal Ganti Armada
    $('#btnOpenModalGantiArmada').on('click', function () {
        const tgl = $('#ga_tanggal_berangkat').val();
        loadArmadaAsal(tgl, defaultPlatFilter);
        $('#modalGantiArmada').modal('show');
    });

    // Event saat tanggal keberangkatan pada modal diganti
    $('#ga_tanggal_berangkat').on('change', function () {
        const tgl = $(this).val();
        loadArmadaAsal(tgl, '');
    });

    // Fungsi load armada asal yang memiliki penumpang pada tanggal yang dipilih
    function loadArmadaAsal(tanggal, selectedPlat) {
        const $select = $('#ga_plat_lama');
        const $info = $('#ga_plat_lama_info');
        $select.html('<option value="">-- Sedang memuat data armada... --</option>').prop('disabled', true);
        $info.html('<i class="fas fa-spinner fa-spin mr-1"></i> Memeriksa manifes penumpang pada tanggal ' + tanggal + '...');
        $('#ga_preview_box').addClass('d-none');

        $.ajax({
            url: 'get_manifest_fleet.php',
            type: 'GET',
            data: { tanggal: tanggal },
            dataType: 'json',
            success: function (res) {
                $select.prop('disabled', false).empty();
                if (res.status === 'success' && res.data && res.data.length > 0) {
                    $select.append('<option value="">-- Pilih Armada yang Batal / Mengalami Kendala --</option>');
                    let autoSelectFound = false;

                    res.data.forEach(function (item) {
                        const isSel = (selectedPlat && selectedPlat === item.no_plat) ? 'selected' : '';
                        if (isSel) autoSelectFound = true;
                        const label = item.no_plat + ' (' + item.kelas + ') - ' + item.total_penumpang + ' Penumpang' + (item.total_paket > 0 ? ', ' + item.total_paket + ' Paket' : '');
                        $select.append(`<option value="${item.no_plat}" data-kelas="${item.kelas}" data-penumpang="${item.total_penumpang}" data-paket="${item.total_paket}" data-jam="${item.jam_list}" ${isSel}>${label}</option>`);
                    });

                    $info.html(`<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Ditemukan ${res.data.length} armada aktif berpenumpang pada tanggal ini.</span>`);

                    if (autoSelectFound) {
                        $select.trigger('change');
                    }
                } else {
                    $select.append('<option value="">-- Tidak ada armada berpenumpang pada tanggal ini --</option>');
                    $info.html('<span class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i> Tidak ditemukan manifes keberangkatan armada pada tanggal ini.</span>');
                }
            },
            error: function () {
                $select.prop('disabled', false).html('<option value="">-- Gagal memuat armada --</option>');
                $info.html('<span class="text-danger">Gagal menghubungi server untuk memuat armada.</span>');
            }
        });
    }

    // Update preview ringkasan saat plat lama atau plat baru dipilih
    $('#ga_plat_lama, #ga_plat_baru').on('change', function () {
        updatePreviewBox();
    });

    function updatePreviewBox() {
        const platLama = $('#ga_plat_lama').val();
        const platBaru = $('#ga_plat_baru').val();
        const $optLama = $('#ga_plat_lama option:selected');
        const $optBaru = $('#ga_plat_baru option:selected');
        const $box = $('#ga_preview_box');
        const $text = $('#ga_summary_text');

        if (platLama && platBaru) {
            if (platLama === platBaru) {
                $box.removeClass('d-none').css({'background': '#fee2e2', 'border-color': '#fca5a5'});
                $text.html('<span class="text-danger font-weight-bold"><i class="fas fa-times-circle mr-1"></i> Peringatan: Armada asal dan armada pengganti tidak boleh sama!</span>');
                $('#btnSubmitGantiArmada').prop('disabled', true);
                return;
            }

            $('#btnSubmitGantiArmada').prop('disabled', false);
            const jmlP = $optLama.data('penumpang') || 0;
            const jmlK = $optLama.data('paket') || 0;
            const kelasBaru = $optBaru.data('kelas') || 'Travel';
            const kursiBaru = $optBaru.data('kursi') || 0;

            $box.removeClass('d-none').css({'background': '#e6f4ea', 'border-color': '#b7e1cd'});
            let summary = `Mengalihkan <strong>${jmlP} Penumpang</strong>`;
            if (jmlK > 0) summary += ` &amp; <strong>${jmlK} Paket</strong>`;
            summary += ` dari armada <strong>${platLama}</strong> &rarr; ke unit baru <strong>${platBaru} (${kelasBaru} - ${kursiBaru} Kursi)</strong>.`;
            $text.html(summary);
        } else {
            $box.addClass('d-none');
            $('#btnSubmitGantiArmada').prop('disabled', false);
        }
    }

    // Submit form pengalihan armada
    $('#formGantiArmada').on('submit', function (e) {
        e.preventDefault();

        const tgl = $('#ga_tanggal_berangkat').val();
        const platLama = $('#ga_plat_lama').val();
        const platBaru = $('#ga_plat_baru').val();
        const jamBaru = $('#ga_jam_baru').val();
        const catatan = $('#ga_catatan').val();
        const pindahPaket = $('#ga_pindah_paket').is(':checked') ? 1 : 0;
        const $optLama = $('#ga_plat_lama option:selected');
        const jmlP = $optLama.data('penumpang') || 0;

        if (!platLama || !platBaru) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Perhatian', 'Silakan pilih armada asal dan armada pengganti terlebih dahulu.', 'warning');
            } else {
                alert('Silakan pilih armada asal dan armada pengganti terlebih dahulu.');
            }
            return;
        }

        if (platLama === platBaru) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', 'Nomor plat pengganti tidak boleh sama dengan plat asal!', 'error');
            } else {
                alert('Nomor plat pengganti tidak boleh sama dengan plat asal!');
            }
            return;
        }

        const confirmMsg = `Apakah Anda yakin ingin mengalihkan seluruh penumpang (${jmlP} Orang) dari armada ${platLama} ke armada pengganti ${platBaru} untuk keberangkatan tanggal ${tgl}?`;

        const doSubmit = function () {
            const $btn = $('#btnSubmitGantiArmada');
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Sedang Mengalihkan...');

            $.ajax({
                url: 'ganti_armada_aksi.php',
                type: 'POST',
                data: {
                    tanggal_berangkat: tgl,
                    plat_lama: platLama,
                    plat_baru: platBaru,
                    jam_baru: jamBaru,
                    pindah_paket: pindahPaket,
                    catatan: catatan
                },
                dataType: 'json',
                success: function (res) {
                    $btn.prop('disabled', false).html(originalText);
                    if (res.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Pengalihan Berhasil!',
                                text: res.message,
                                icon: 'success',
                                confirmButtonColor: '#0d9f4f',
                                confirmButtonText: '<i class="fas fa-check mr-1"></i> Selesai & Muat Ulang Manifes'
                            }).then(function () {
                                // Reload halaman dengan filter plat baru agar langsung terlihat
                                const currentUrl = new URL(window.location.href);
                                currentUrl.searchParams.set('tgl_dari', tgl);
                                currentUrl.searchParams.set('tgl_sampai', tgl);
                                currentUrl.searchParams.set('no_plat', platBaru);
                                window.location.href = currentUrl.toString();
                            });
                        } else {
                            alert(res.message);
                            window.location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Gagal Mengalihkan', res.message, 'error');
                        } else {
                            alert('Gagal: ' + res.message);
                        }
                    }
                },
                error: function (xhr, status, error) {
                    $btn.prop('disabled', false).html(originalText);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Kesalahan Server', 'Gagal memproses pengalihan armada: ' + error, 'error');
                    } else {
                        alert('Kesalahan Server: ' + error);
                    }
                }
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Konfirmasi Pengalihan Armada',
                html: `<div class="text-left" style="font-size: 0.92rem;">
                    <p>Anda akan melakukan pergantian armada secara massal:</p>
                    <ul class="mb-2">
                        <li><strong>Tanggal</strong>: ${tgl}</li>
                        <li><strong>Armada Asal</strong>: <span class="text-danger font-weight-bold">${platLama}</span></li>
                        <li><strong>Armada Pengganti</strong>: <span class="text-success font-weight-bold">${platBaru}</span></li>
                        <li><strong>Jumlah Penumpang</strong>: ${jmlP} Orang</li>
                    </ul>
                    <p class="text-muted mb-0">Seluruh data tiket penumpang akan otomatis dialihkan ke armada baru ini.</p>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-exchange-alt mr-1"></i> Ya, Alihkan Sekarang',
                cancelButtonText: 'Batal'
            }).then(function (result) {
                if (result.isConfirmed) {
                    doSubmit();
                }
            });
        } else {
            if (confirm(confirmMsg)) {
                doSubmit();
            }
        }
    });
});
</script>
<?php endif; ?>