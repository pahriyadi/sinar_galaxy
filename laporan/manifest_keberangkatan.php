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
$role = isset($user['role']) ? $user['role'] : '';

// Filter tanggal keberangkatan (default hari ini)
$tanggal_berangkat = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_berangkat)) {
    $tanggal_berangkat = date('Y-m-d');
}
$startDateTime = $tanggal_berangkat . ' 00:00:00';
$endDateTime = date('Y-m-d', strtotime($tanggal_berangkat . ' +1 day')) . ' 00:00:00';
$no_plat = isset($_GET['no_plat']) ? $_GET['no_plat'] : '';
$tujuan_perjalanan = isset($_GET['tujuan_perjalanan']) ? $_GET['tujuan_perjalanan'] : '';
$jenis_transaksi = isset($_GET['jenis_transaksi']) ? $_GET['jenis_transaksi'] : 'semua';

// Query data travel untuk dropdown
$travelOptions = [];
$resTravel = mysqli_query($conn, "SELECT no_plat, kelas FROM data_travel ORDER BY no_plat");
while ($r = mysqli_fetch_assoc($resTravel)) {
    $travelOptions[] = $r;
}

// Query data tujuan untuk mapping
$tujuanMap = [];
$resTujuan = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
while ($r = mysqli_fetch_assoc($resTujuan)) {
    $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];
}

// Query data status pembayaran untuk mapping
$statusMap = [];
$resStatus = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
while ($r = mysqli_fetch_assoc($resStatus)) {
    $statusMap[$r['id_status_pembayaran']] = $r['status_pembayaran'];
}

// Query data metode pembayaran untuk mapping
$metodeMap = [];
$resMetode = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
while ($r = mysqli_fetch_assoc($resMetode)) {
    $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran'];
}

// Query data rekening untuk mapping
$rekeningMap = [];
$resRekening = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening");
while ($r = mysqli_fetch_assoc($resRekening)) {
    $rekeningMap[$r['id_rekening']] = $r['nama_rekening'];
}

// Query data pemesanan untuk manifest
$where_pemesanan = "WHERE pm.tanggal_berangkat >= '" . mysqli_real_escape_string($conn, $startDateTime) . "' AND pm.tanggal_berangkat < '" . mysqli_real_escape_string($conn, $endDateTime) . "'";

// Pembatasan peran: users (username + asal_po via mapping), admin (asal_po via mapping), super admin (tanpa pembatasan)
if ($role === 'users') {
    $where_pemesanan .= " AND pm.username = '" . mysqli_real_escape_string($conn, $id_users) . "'"
                     . " AND pm.asal_po IN (SELECT id_users FROM data_users WHERE asal_po = '" . mysqli_real_escape_string($conn, $asal_po) . "')";
} elseif ($role === 'admin') {
    $where_pemesanan .= " AND pm.asal_po IN (SELECT id_users FROM data_users WHERE asal_po = '" . mysqli_real_escape_string($conn, $asal_po) . "')";
}

if ($no_plat) {
    $where_pemesanan .= " AND pm.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
}

if ($tujuan_perjalanan) {
    $where_pemesanan .= " AND pm.tujuan_id = '" . mysqli_real_escape_string($conn, $tujuan_perjalanan) . "'";
}

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
    pm.payment_methods,
    '' as nama_penerima,
    dt.kelas,
    sp.status_pembayaran,
    mp.metode_pembayaran,
    dr.nama_rekening,
    tj.nama_tujuan
FROM data_pemesanan pm
LEFT JOIN data_travel dt ON pm.no_plat_id = dt.no_plat
LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_tujuan_perjalanan tj ON pm.tujuan_id = tj.id_tujuan_perjalanan
$where_pemesanan
ORDER BY pm.tanggal_berangkat ASC, pm.no_plat_id ASC, pm.kursi ASC";

// Query data pengiriman (paket) untuk manifest
$where_pengiriman = "WHERE pg.tanggal_pengiriman >= '" . mysqli_real_escape_string($conn, $startDateTime) . "' AND pg.tanggal_pengiriman < '" . mysqli_real_escape_string($conn, $endDateTime) . "'";

if ($role === 'users') {
    $where_pengiriman .= " AND pg.user_id = '" . mysqli_real_escape_string($conn, $id_users) . "'"
                       . " AND pg.asal_po_id IN (SELECT id_users FROM data_users WHERE asal_po = '" . mysqli_real_escape_string($conn, $asal_po) . "')";
} elseif ($role === 'admin') {
    $where_pengiriman .= " AND pg.asal_po_id IN (SELECT id_users FROM data_users WHERE asal_po = '" . mysqli_real_escape_string($conn, $asal_po) . "')";
}

if ($no_plat) {
    $where_pengiriman .= " AND pg.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
}

if ($tujuan_perjalanan) {
    $where_pengiriman .= " AND CAST(pg.tujuan_id AS UNSIGNED) = '" . mysqli_real_escape_string($conn, $tujuan_perjalanan) . "'";
}

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
    pg.payment_methods,
    pg.nama_penerima,
    dt.kelas,
    sp.status_pembayaran,
    mp.metode_pembayaran,
    dr.nama_rekening,
    tj.nama_tujuan
FROM data_pengiriman pg
LEFT JOIN data_travel dt ON pg.no_plat_id = dt.no_plat
LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran
LEFT JOIN data_metode_pembayaran mp ON pg.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_rekening dr ON pg.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_tujuan_perjalanan tj ON CAST(pg.tujuan_id AS UNSIGNED) = tj.id_tujuan_perjalanan
$where_pengiriman
ORDER BY pg.tanggal_pengiriman ASC, pg.no_plat_id ASC";

// Gabungkan query berdasarkan filter
if ($jenis_transaksi === 'tiket') {
    $resRekap = mysqli_query($conn, $sql_pemesanan);
} elseif ($jenis_transaksi === 'paket') {
    $resRekap = mysqli_query($conn, $sql_pengiriman);
} else {
    $sql_combined = "($sql_pemesanan) UNION ALL ($sql_pengiriman) ORDER BY tanggal_berangkat ASC, no_plat_id ASC, kursi ASC";
    $resRekap = mysqli_query($conn, $sql_combined);
}

$rekap = [];
$total_pendapatan = 0;
$total_transaksi = 0;
$total_tiket = 0;
$total_paket = 0;
$cash_total = 0;
$transfer_total = 0;
$rekening_totals = [];

while ($row = mysqli_fetch_assoc($resRekap)) {
    // Siapkan struktur hasil per baris agar decoding JSON dilakukan sekali
    $rowPayments = [];
    if (!empty($row['payment_methods'])) {
        $decoded = json_decode($row['payment_methods'], true);
        if (is_array($decoded)) {
            $rowPayments = $decoded;
        }
    }
    if (empty($rowPayments)) {
        $rowPayments = [[
            'amount' => $row['harga_id'] ?? 0,
            'rekening_id' => $row['jenis_rekening_id'] ?? null,
            'method_id' => $row['metode_pembayaran_id'] ?? null,
        ]];
    }

    $row['__payments'] = $rowPayments;

    // Hitung counters
    $total_transaksi++;
    if (($row['jenis_transaksi'] ?? '') === 'Tiket') { $total_tiket++; } else { $total_paket++; }

    foreach ($rowPayments as $pay) {
        $amount = (float)($pay['amount'] ?? 0);
        $total_pendapatan += $amount;

        $method_id = $pay['method_id'] ?? null;
        $rekening_id = $pay['rekening_id'] ?? null;

        if ($method_id && isset($metodeMap[$method_id])) {
            $method_name = strtolower($metodeMap[$method_id]);
            if (strpos($method_name, 'cash') !== false || strpos($method_name, 'tunai') !== false) {
                $cash_total += $amount;
            } else {
                $transfer_total += $amount;
            }
        }

        if ($rekening_id && isset($rekeningMap[$rekening_id])) {
            $rekening_name = $rekeningMap[$rekening_id];
            if (!isset($rekening_totals[$rekening_name])) { $rekening_totals[$rekening_name] = 0; }
            $rekening_totals[$rekening_name] += $amount;
        }
    }

    $rekap[] = $row;
}

// Format tanggal untuk display
$tanggal_display = date('d/m/Y', strtotime($tanggal_berangkat));
?>

<style>
/* ==========================================================================
   Paper White Design System v2.0 - Manifes Keberangkatan & Audit Kasir/Bank
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
    font-size: 0.82rem;
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
    padding: 7px 6px !important;
    font-size: 0.78rem;
}

.table-excel td {
    border: 1px solid #b8b8b8 !important;
    padding: 6px 7px !important;
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
    padding: 8px 6px !important;
    font-size: 0.8rem;
}

/* ==========================================================================
   Print Layout System (A4 Landscape, High-Precision 17 Columns)
   ========================================================================== */
#printReportDocument {
    display: none;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 6mm 6mm 8mm 6mm;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body, html {
        background: #ffffff !important;
        color: #000000 !important;
        font-family: Arial, "Helvetica Neue", Helvetica, sans-serif !important;
        font-size: 8pt !important;
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
        padding-bottom: 4px;
        margin-bottom: 6px;
    }

    .print-kop h1 {
        font-size: 14pt !important;
        font-weight: 800 !important;
        margin: 0 0 2px 0 !important;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #000000 !important;
    }

    .print-kop h2 {
        font-size: 10.5pt !important;
        font-weight: 700 !important;
        margin: 0 0 3px 0 !important;
        text-transform: uppercase;
        color: #000000 !important;
    }

    .print-meta-table {
        width: 100%;
        margin-bottom: 6px;
        font-size: 7.5pt;
        border-collapse: collapse;
    }

    .print-meta-table td {
        padding: 1.5px 3px;
        vertical-align: top;
        border: none !important;
    }

    .print-table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 7.5pt !important;
        margin-bottom: 8px !important;
    }

    .print-table thead {
        display: table-header-group !important;
    }

    .print-table th {
        background-color: #f0f0f0 !important;
        color: #000000 !important;
        border: 1px solid #000000 !important;
        padding: 3px 3px !important;
        font-weight: 700 !important;
        text-align: center !important;
        vertical-align: middle !important;
        font-size: 7.5pt !important;
    }

    .print-table td {
        border: 1px solid #000000 !important;
        padding: 3px 3px !important;
        vertical-align: middle !important;
        color: #000000 !important;
        font-size: 7.2pt !important;
    }

    .print-table tr {
        page-break-inside: avoid !important;
    }

    .print-table tfoot th, .print-table tfoot td {
        background-color: #f0f0f0 !important;
        font-weight: 700 !important;
        border: 1px solid #000000 !important;
        padding: 3px 3px !important;
        font-size: 7.5pt !important;
    }

    .print-signatures {
        margin-top: 12px;
        page-break-inside: avoid !important;
        width: 100%;
    }

    .print-signatures td {
        border: none !important;
        text-align: center;
        vertical-align: top;
        font-size: 8pt;
        padding: 2px 10px;
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
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Manifes Keberangkatan & Audit Kasir/Bank</h1>
                                <div class="text-muted" style="font-size: 0.83rem; margin-top: 2px;">
                                    <span>Audit rincian 17 kolom manifes penumpang, kargo, pembayaran tunai kasir, dan transfer rekening</span>
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
                        <i class="fas fa-filter mr-1 text-muted"></i> Parameter Filter Manifes Keberangkatan & Audit
                    </h3>
                </div>
                <div class="card-body" style="padding: 14px 16px;">
                    <form method="GET" class="row align-items-end">
                        <div class="col-lg-3 col-md-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Tanggal Keberangkatan:</label>
                            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal_berangkat) ?>" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;" required>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Nomor Plat Armada:</label>
                            <select name="no_plat" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="">-- Semua Armada --</option>
                                <?php foreach ($travelOptions as $plat): ?>
                                    <option value="<?= htmlspecialchars($plat['no_plat']) ?>" <?= ($plat['no_plat'] == $no_plat) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($plat['no_plat']) ?> (<?= htmlspecialchars($plat['kelas']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Kota Tujuan:</label>
                            <select name="tujuan_perjalanan" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="">-- Semua Tujuan --</option>
                                <?php foreach ($tujuanMap as $id => $nama): ?>
                                    <option value="<?= htmlspecialchars($id) ?>" <?= ($id == $tujuan_perjalanan) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nama) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-2">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Jenis Transaksi:</label>
                            <select name="jenis_transaksi" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="semua" <?= ($jenis_transaksi === 'semua') ? 'selected' : '' ?>>Semua (Tiket & Paket)</option>
                                <option value="tiket" <?= ($jenis_transaksi === 'tiket') ? 'selected' : '' ?>>Tiket Penumpang</option>
                                <option value="paket" <?= ($jenis_transaksi === 'paket') ? 'selected' : '' ?>>Paket Barang</option>
                            </select>
                        </div>
                        <div class="col-12 text-right mt-2">
                            <button type="submit" class="btn btn-primary btn-sm btn-flat font-weight-bold" style="background-color: #0d9f4f; border-color: #076e34;">
                                <i class="fas fa-search mr-1"></i> Terapkan Filter
                            </button>
                            <a href="manifest_keberangkatan.php" class="btn btn-default btn-sm btn-flat font-weight-bold ml-1" style="border: 1px solid #b8b8b8; background: #ffffff;">
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
                            <div class="stat-label">Total Penerimaan</div>
                            <div class="stat-value" style="color: #0d9f4f;">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #e8f0fe; border: 1px solid #d2e3fc; color: #1a73e8;">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Penerimaan Tunai (Kasir)</div>
                            <div class="stat-value" style="color: #1a73e8;">Rp <?= number_format($cash_total, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #fef7e0; border: 1px solid #feefc3; color: #b06000;">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Transfer Bank</div>
                            <div class="stat-value" style="color: #b06000;">Rp <?= number_format($transfer_total, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #f1f3f4; border: 1px solid #dadce0; color: #5f6368;">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Transaksi</div>
                            <div class="stat-value"><?= $total_transaksi ?> <small style="font-size: 0.75rem; font-weight: normal; color: #6c757d;">(<?= $total_tiket ?> Tiket / <?= $total_paket ?> Paket)</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Layar (Paper White v2.0 - 17 Kolom Keuangan) -->
            <div class="table-excel-container">
                <div class="p-3 bg-white border-bottom" style="border-color: #b8b8b8 !important; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="m-0 font-weight-bold text-dark" style="font-size: 0.95rem;">
                        <i class="fas fa-table mr-1 text-muted"></i> Manifes Rincian Penerimaan Keberangkatan & Akun Rekening
                    </h3>
                    <span class="badge" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                        Keberangkatan: <?= $tanggal_display ?> (<?= count($rekap) ?> Baris Data)
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table-excel" id="tableManifestScreen">
                        <thead>
                            <tr>
                                <th style="width: 3%; text-align: center;">No</th>
                                <th style="width: 5%; text-align: center;">Jenis</th>
                                <th style="width: 13%; text-align: left;">Nama</th>
                                <th style="width: 8%; text-align: left;">No. KTP</th>
                                <th style="width: 8%; text-align: left;">No. HP</th>
                                <th style="width: 7%; text-align: center;">No. Plat</th>
                                <th style="width: 4%; text-align: center;">Kursi</th>
                                <th style="width: 8%; text-align: left;">Tujuan</th>
                                <th style="width: 6%; text-align: center;">Metode</th>
                                <th style="width: 6%; text-align: center;">Tgl Bayar</th>
                                <th style="width: 6%; text-align: right;">Kasir SBW</th>
                                <th style="width: 6%; text-align: right;">Kasir MTR</th>
                                <th style="width: 5%; text-align: right;">BRI</th>
                                <th style="width: 5%; text-align: right;">BNI</th>
                                <th style="width: 5%; text-align: right;">Mandiri</th>
                                <th style="width: 5%; text-align: right;">BCA</th>
                                <th style="width: 6%; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rekap)): ?>
                                <tr>
                                    <td colspan="17" class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle mr-1"></i> Tidak ada catatan manifes keberangkatan pada tanggal dan kriteria ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = 1; 
                                foreach ($rekap as $row): 
                                    $isTiket = ($row['jenis_transaksi'] === 'Tiket');
                                    
                                    // Hitung nilai kasir dan bank per baris
                                    $row_kasir_sbw = 0;
                                    $row_kasir_mtr = 0;
                                    $row_bri = 0;
                                    $row_bni = 0;
                                    $row_mandiri = 0;
                                    $row_bca = 0;
                                    $row_total = 0;

                                    foreach (($row['__payments'] ?? []) as $p) {
                                        $amt = (float)($p['amount'] ?? 0);
                                        $row_total += $amt;
                                        $rekId = $p['rekening_id'] ?? null;
                                        if ($rekId && isset($rekeningMap[$rekId])) {
                                            $rName = strtolower($rekeningMap[$rekId]);
                                            if (strpos($rName, 'kasir') !== false && strpos($rName, 'sumbawa') !== false) {
                                                $row_kasir_sbw += $amt;
                                            } elseif (strpos($rName, 'kasir') !== false && strpos($rName, 'mataram') !== false) {
                                                $row_kasir_mtr += $amt;
                                            } elseif (strpos($rName, 'bri') !== false) {
                                                $row_bri += $amt;
                                            } elseif (strpos($rName, 'bni') !== false) {
                                                $row_bni += $amt;
                                            } elseif (strpos($rName, 'mandiri') !== false) {
                                                $row_mandiri += $amt;
                                            } elseif (strpos($rName, 'bca') !== false) {
                                                $row_bca += $amt;
                                            }
                                        }
                                    }

                                    // Metode bayar ringkas
                                    $methods = [];
                                    foreach (($row['__payments'] ?? []) as $payment) {
                                        $mId = $payment['method_id'] ?? null;
                                        if ($mId && isset($metodeMap[$mId])) {
                                            $methods[] = $metodeMap[$mId];
                                        }
                                    }
                                    $metode_txt = !empty($methods) ? implode(', ', array_unique($methods)) : ($metodeMap[$row['metode_pembayaran_id']] ?? '-');

                                    // Tanggal bayar
                                    $tgl_bayar = date('d/m/y', strtotime($row['tanggal_pemesanan']));
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td class="text-center">
                                            <?php if ($isTiket): ?>
                                                <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600; font-size: 0.72rem; padding: 2px 5px;">Tiket</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #fef7e0; color: #b06000; border: 1px solid #feefc3; font-weight: 600; font-size: 0.72rem; padding: 2px 5px;">Paket</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$isTiket && !empty($row['nama_penerima'])): ?>
                                                <strong class="text-dark"><?= htmlspecialchars($row['nama_id']) ?></strong> <span class="text-muted" style="font-size: 0.75rem;">➔ <?= htmlspecialchars($row['nama_penerima']) ?></span>
                                            <?php else: ?>
                                                <strong class="text-dark"><?= htmlspecialchars($row['nama_id']) ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #495057; font-family: monospace; font-size: 0.78rem;"><?= htmlspecialchars($row['no_ktp_id'] ?: '-') ?></td>
                                        <td style="font-size: 0.8rem;"><?= htmlspecialchars($row['no_hp_id'] ?: '-') ?></td>
                                        <td class="text-center font-weight-bold" style="color: #0056b3; font-size: 0.8rem;"><?= htmlspecialchars($row['no_plat_id'] ?: '-') ?></td>
                                        <td class="text-center">
                                            <?php if ($isTiket && !empty($row['kursi']) && $row['kursi'] !== '-'): ?>
                                                <span class="badge badge-secondary" style="background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; font-weight: 700; padding: 3px 6px;"><?= htmlspecialchars($row['kursi']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 0.8rem;"><?= htmlspecialchars($row['nama_tujuan'] ?? ($tujuanMap[$row['tujuan_id']] ?? '-')) ?></td>
                                        <td class="text-center" style="font-size: 0.75rem;"><?= htmlspecialchars($metode_txt) ?></td>
                                        <td class="text-center" style="font-size: 0.75rem;"><?= $tgl_bayar ?></td>
                                        <td class="text-right"><?= $row_kasir_sbw > 0 ? number_format($row_kasir_sbw, 0, ',', '.') : '-' ?></td>
                                        <td class="text-right"><?= $row_kasir_mtr > 0 ? number_format($row_kasir_mtr, 0, ',', '.') : '-' ?></td>
                                        <td class="text-right"><?= $row_bri > 0 ? number_format($row_bri, 0, ',', '.') : '-' ?></td>
                                        <td class="text-right"><?= $row_bni > 0 ? number_format($row_bni, 0, ',', '.') : '-' ?></td>
                                        <td class="text-right"><?= $row_mandiri > 0 ? number_format($row_mandiri, 0, ',', '.') : '-' ?></td>
                                        <td class="text-right"><?= $row_bca > 0 ? number_format($row_bca, 0, ',', '.') : '-' ?></td>
                                        <td class="text-right font-weight-bold" style="color: #0d9f4f;">Rp <?= number_format($row_total, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <?php
                            $tot_kasir_sbw = 0;
                            $tot_kasir_mtr = 0;
                            $tot_bri = 0;
                            $tot_bni = 0;
                            $tot_mandiri = 0;
                            $tot_bca = 0;

                            foreach ($rekening_totals as $rekName => $amt) {
                                $rLower = strtolower($rekName);
                                if (strpos($rLower, 'kasir') !== false && strpos($rLower, 'sumbawa') !== false) {
                                    $tot_kasir_sbw += $amt;
                                } elseif (strpos($rLower, 'kasir') !== false && strpos($rLower, 'mataram') !== false) {
                                    $tot_kasir_mtr += $amt;
                                } elseif (strpos($rLower, 'bri') !== false) {
                                    $tot_bri += $amt;
                                } elseif (strpos($rLower, 'bni') !== false) {
                                    $tot_bni += $amt;
                                } elseif (strpos($rLower, 'mandiri') !== false) {
                                    $tot_mandiri += $amt;
                                } elseif (strpos($rLower, 'bca') !== false) {
                                    $tot_bca += $amt;
                                }
                            }
                            ?>
                            <tr>
                                <th colspan="10" class="text-center">TOTAL REKAPITULASI AUDIT</th>
                                <th class="text-right"><?= $tot_kasir_sbw > 0 ? number_format($tot_kasir_sbw, 0, ',', '.') : '-' ?></th>
                                <th class="text-right"><?= $tot_kasir_mtr > 0 ? number_format($tot_kasir_mtr, 0, ',', '.') : '-' ?></th>
                                <th class="text-right"><?= $tot_bri > 0 ? number_format($tot_bri, 0, ',', '.') : '-' ?></th>
                                <th class="text-right"><?= $tot_bni > 0 ? number_format($tot_bni, 0, ',', '.') : '-' ?></th>
                                <th class="text-right"><?= $tot_mandiri > 0 ? number_format($tot_mandiri, 0, ',', '.') : '-' ?></th>
                                <th class="text-right"><?= $tot_bca > 0 ? number_format($tot_bca, 0, ',', '.') : '-' ?></th>
                                <th class="text-right" style="color: #0d9f4f; font-size: 0.9rem;">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- ==========================================================================
     DOKUMEN CETAK RESMI (Format A4 Landscape Optimal untuk Manifes 17 Kolom)
     ========================================================================== -->
<div id="printReportDocument">
    <!-- Kop Laporan Resmi -->
    <div class="print-kop">
        <h1>PO. SINAR GALAXY TRAVEL</h1>
        <h2>MANIFES KEBERANGKATAN & AUDIT KEUANGAN PERJALANAN</h2>
        <div style="font-size: 8pt; color: #222; margin-top: 1px;">
            Laporan Rekonsiliasi Kasir Loket, Bank Transfer, dan Manifes Muatan Armada
        </div>
    </div>

    <!-- Parameter & Informasi Manifes -->
    <table class="print-meta-table">
        <tr>
            <td style="width: 14%; font-weight: bold;">Tgl Keberangkatan</td>
            <td style="width: 36%;">: <?= $tanggal_display ?></td>
            <td style="width: 15%; font-weight: bold;">Nomor Plat Armada</td>
            <td style="width: 35%;">: <?= htmlspecialchars($no_plat ?: 'Semua Armada') ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Cabang / PO</td>
            <td>: <?= htmlspecialchars($asal_po ?: 'Seluruh Cabang PO') ?></td>
            <td style="font-weight: bold;">Tujuan Perjalanan</td>
            <td>: <?= htmlspecialchars($tujuan_perjalanan ? ($tujuanMap[$tujuan_perjalanan] ?? 'Semua Tujuan') : 'Semua Kota Tujuan') ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Petugas / Kasir</td>
            <td>: <?= htmlspecialchars($user['username'] ?? 'Petugas') ?></td>
            <td style="font-weight: bold;">Waktu Cetak</td>
            <td>: <?= date('d/m/Y H:i:s') ?> WITA</td>
        </tr>
    </table>

    <!-- Ringkasan Statistik Cetak -->
    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; border: 1px solid #000; padding: 3px 6px; font-size: 7.5pt; background: #fafafa;">
        <div><strong>Total Muatan:</strong> <?= $total_transaksi ?> Transaksi (<?= $total_tiket ?> Tiket / <?= $total_paket ?> Paket)</div>
        <div><strong>Penerimaan Tunai:</strong> Rp <?= number_format($cash_total, 0, ',', '.') ?></div>
        <div><strong>Transfer Rekening:</strong> Rp <?= number_format($transfer_total, 0, ',', '.') ?></div>
        <div><strong>Total Nilai Manifes:</strong> Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
    </div>

    <!-- Tabel Data Cetak 17 Kolom -->
    <table class="print-table">
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th style="width: 5%;">Jenis</th>
                <th style="width: 14%; text-align: left;">Nama</th>
                <th style="width: 9%; text-align: left;">No. KTP</th>
                <th style="width: 8%; text-align: left;">No. HP</th>
                <th style="width: 7%;">No. Plat</th>
                <th style="width: 4%;">Kursi</th>
                <th style="width: 9%; text-align: left;">Tujuan</th>
                <th style="width: 6%;">Metode</th>
                <th style="width: 6%;">Tgl Bayar</th>
                <th style="width: 5%; text-align: right;">SBW</th>
                <th style="width: 5%; text-align: right;">MTR</th>
                <th style="width: 5%; text-align: right;">BRI</th>
                <th style="width: 5%; text-align: right;">BNI</th>
                <th style="width: 5%; text-align: right;">Mandiri</th>
                <th style="width: 5%; text-align: right;">BCA</th>
                <th style="width: 6%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rekap)): ?>
                <tr>
                    <td colspan="17" style="text-align: center; padding: 10px;">Tidak ada data manifes pada tanggal ini.</td>
                </tr>
            <?php else: ?>
                <?php 
                $pNo = 1; 
                foreach ($rekap as $row): 
                    $isTiket = ($row['jenis_transaksi'] === 'Tiket');
                    
                    $p_kasir_sbw = 0;
                    $p_kasir_mtr = 0;
                    $p_bri = 0;
                    $p_bni = 0;
                    $p_mandiri = 0;
                    $p_bca = 0;
                    $p_total = 0;

                    foreach (($row['__payments'] ?? []) as $p) {
                        $amt = (float)($p['amount'] ?? 0);
                        $p_total += $amt;
                        $rekId = $p['rekening_id'] ?? null;
                        if ($rekId && isset($rekeningMap[$rekId])) {
                            $rLower = strtolower($rekeningMap[$rekId]);
                            if (strpos($rLower, 'kasir') !== false && strpos($rLower, 'sumbawa') !== false) {
                                $p_kasir_sbw += $amt;
                            } elseif (strpos($rLower, 'kasir') !== false && strpos($rLower, 'mataram') !== false) {
                                $p_kasir_mtr += $amt;
                            } elseif (strpos($rLower, 'bri') !== false) {
                                $p_bri += $amt;
                            } elseif (strpos($rLower, 'bni') !== false) {
                                $p_bni += $amt;
                            } elseif (strpos($rLower, 'mandiri') !== false) {
                                $p_mandiri += $amt;
                            } elseif (strpos($rLower, 'bca') !== false) {
                                $p_bca += $amt;
                            }
                        }
                    }

                    $methods = [];
                    foreach (($row['__payments'] ?? []) as $payment) {
                        $mId = $payment['method_id'] ?? null;
                        if ($mId && isset($metodeMap[$mId])) {
                            $methods[] = $metodeMap[$mId];
                        }
                    }
                    $metode_txt = !empty($methods) ? implode(', ', array_unique($methods)) : ($metodeMap[$row['metode_pembayaran_id']] ?? '-');
                    $tgl_bayar = date('d/m/y', strtotime($row['tanggal_pemesanan']));
                ?>
                    <tr>
                        <td style="text-align: center;"><?= $pNo++ ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($row['jenis_transaksi']) ?></td>
                        <td>
                            <?php if (!$isTiket && !empty($row['nama_penerima'])): ?>
                                <?= htmlspecialchars($row['nama_id']) ?> ➔ <?= htmlspecialchars($row['nama_penerima']) ?>
                            <?php else: ?>
                                <?= htmlspecialchars($row['nama_id']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['no_ktp_id'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['no_hp_id'] ?: '-') ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($row['no_plat_id'] ?: '-') ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= ($isTiket && !empty($row['kursi']) && $row['kursi'] !== '-') ? htmlspecialchars($row['kursi']) : '-' ?></td>
                        <td><?= htmlspecialchars($row['nama_tujuan'] ?? ($tujuanMap[$row['tujuan_id']] ?? '-')) ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars($metode_txt) ?></td>
                        <td style="text-align: center;"><?= $tgl_bayar ?></td>
                        <td style="text-align: right;"><?= $p_kasir_sbw > 0 ? number_format($p_kasir_sbw, 0, ',', '.') : '-' ?></td>
                        <td style="text-align: right;"><?= $p_kasir_mtr > 0 ? number_format($p_kasir_mtr, 0, ',', '.') : '-' ?></td>
                        <td style="text-align: right;"><?= $p_bri > 0 ? number_format($p_bri, 0, ',', '.') : '-' ?></td>
                        <td style="text-align: right;"><?= $p_bni > 0 ? number_format($p_bni, 0, ',', '.') : '-' ?></td>
                        <td style="text-align: right;"><?= $p_mandiri > 0 ? number_format($p_mandiri, 0, ',', '.') : '-' ?></td>
                        <td style="text-align: right;"><?= $p_bca > 0 ? number_format($p_bca, 0, ',', '.') : '-' ?></td>
                        <td style="text-align: right; font-weight: bold;">Rp <?= number_format($p_total, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="10" class="text-center">TOTAL KESELURUHAN</th>
                <th class="text-right"><?= $tot_kasir_sbw > 0 ? number_format($tot_kasir_sbw, 0, ',', '.') : '-' ?></th>
                <th class="text-right"><?= $tot_kasir_mtr > 0 ? number_format($tot_kasir_mtr, 0, ',', '.') : '-' ?></th>
                <th class="text-right"><?= $tot_bri > 0 ? number_format($tot_bri, 0, ',', '.') : '-' ?></th>
                <th class="text-right"><?= $tot_bni > 0 ? number_format($tot_bni, 0, ',', '.') : '-' ?></th>
                <th class="text-right"><?= $tot_mandiri > 0 ? number_format($tot_mandiri, 0, ',', '.') : '-' ?></th>
                <th class="text-right"><?= $tot_bca > 0 ? number_format($tot_bca, 0, ',', '.') : '-' ?></th>
                <th class="text-right" style="font-size: 8pt;">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></th>
            </tr>
        </tfoot>
    </table>

    <!-- Lembar Pengesahan Tanda Tangan 3 Pihak -->
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

<!-- Modal Pratinjau Cetak -->
<div class="modal fade no-print" id="modalPreviewPrint" tabindex="-1" role="dialog" aria-labelledby="modalPreviewPrintLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document" style="max-width: 96%;">
        <div class="modal-content" style="border-radius: 4px; border: 1px solid #b8b8b8;">
            <div class="modal-header" style="background: #f8f9fa; border-bottom: 1px solid #b8b8b8; padding: 12px 18px;">
                <h5 class="modal-title font-weight-bold text-dark" id="modalPreviewPrintLabel">
                    <i class="fas fa-print mr-2 text-primary"></i> Pratinjau Lembar Manifes Cetak (Landscape A4)
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="background: #525659; padding: 16px; max-height: 75vh; overflow-y: auto;">
                <div id="previewPaper" style="background: #ffffff; color: #000000; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-radius: 2px; min-height: 500px;">
                    <!-- Konten di-inject lewat JavaScript -->
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
 * Cetak Dokumen Langsung menggunakan Browser Native Print Engine
 */
function printReport() {
    window.print();
}

/**
 * Pratinjau Cetak menggunakan Modal
 */
function previewPrint() {
    const printDoc = document.getElementById('printReportDocument');
    const previewContainer = document.getElementById('previewPaper');
    
    if (printDoc && previewContainer) {
        previewContainer.innerHTML = printDoc.innerHTML;
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
    const table = document.getElementById('tableManifestScreen');
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
                table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 9pt; }
                th { background-color: #f2f2f2; border: 1px solid #000000; text-align: center; font-weight: bold; padding: 5px; }
                td { border: 1px solid #000000; padding: 4px; }
            </style>
        </head>
        <body>
            <h3 style="margin-bottom: 2px;">PO. SINAR GALAXY TRAVEL - MANIFES KEBERANGKATAN & AUDIT KEUANGAN</h3>
            <p style="margin-top: 0; font-size: 8.5pt; color: #555;">
                Tanggal: <?= $tanggal_display ?> | Armada: <?= htmlspecialchars($no_plat ?: 'Semua Armada') ?> | Tujuan: <?= htmlspecialchars($tujuan_perjalanan ? ($tujuanMap[$tujuan_perjalanan] ?? 'Semua') : 'Semua') ?>
            </p>
            ${cloneTable.outerHTML}
        </body>
        </html>
    `;

    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Manifes_Audit_${new Date().toISOString().slice(0, 10)}.xls`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Shortcut Keyboard Ctrl+P
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        printReport();
    }
});
</script>

<?php include '../inc/footer.php'; ?>
