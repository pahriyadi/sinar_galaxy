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
$jenis_transaksi = $_GET['jenis_transaksi'] ?? 'semua';
$no_plat = $_GET['no_plat'] ?? '';
$filter_username = $_GET['filter_username'] ?? '';
$filter_asal_po = $_GET['filter_asal_po'] ?? '';

// Query data travel untuk dropdown
$travelOptions = [];
$resTravel = mysqli_query($conn, "SELECT no_plat, kelas FROM data_travel ORDER BY no_plat");
while ($r = mysqli_fetch_assoc($resTravel)) {
    $travelOptions[] = $r;
}

// Query data username untuk dropdown (hanya untuk super admin)
$usernameOptions = [];
$userMap = [];
if ($role === 'super admin') {
    $resUsers = mysqli_query($conn, "SELECT id_users, username, nama_lengkap FROM data_users WHERE username IS NOT NULL AND username != '' ORDER BY nama_lengkap, username");
    while ($r = mysqli_fetch_assoc($resUsers)) {
        $label = $r['nama_lengkap'] ? $r['nama_lengkap'] . ' (' . $r['username'] . ')' : $r['username'];
        $usernameOptions[$r['id_users']] = $label;
        $userMap[$r['id_users']] = $label;
    }
}

// Query data asal_po untuk dropdown (hanya untuk super admin)
$asalPoOptions = [];
if ($role === 'super admin') {
    $resAsalPo = mysqli_query($conn, "SELECT DISTINCT asal_po FROM data_users WHERE asal_po IS NOT NULL AND asal_po != '' ORDER BY asal_po");
    while ($r = mysqli_fetch_assoc($resAsalPo)) {
        $asalPoOptions[$r['asal_po']] = $r['asal_po'];
    }
}

// Query rekap pendapatan per travel
$where_pemesanan = "WHERE 1=1";
$where_pengiriman = "WHERE 1=1";

// Filter berdasarkan role dan filter tambahan
if ($role !== 'super admin') {
    $where_pemesanan .= " AND pm.username = '$id_users' AND pm.asal_po = '$asal_po'";
    $where_pengiriman .= " AND pg.user_id = '$id_users' AND pg.asal_po_id = '$asal_po'";
} else {
    if ($filter_username) {
        $where_pemesanan .= " AND pm.username = '" . mysqli_real_escape_string($conn, $filter_username) . "'";
        $where_pengiriman .= " AND pg.user_id = '" . mysqli_real_escape_string($conn, $filter_username) . "'";
    }
    if ($filter_asal_po) {
        $where_pemesanan .= " AND pm.asal_po = '" . mysqli_real_escape_string($conn, $filter_asal_po) . "'";
        $where_pengiriman .= " AND pg.asal_po_id = '" . mysqli_real_escape_string($conn, $filter_asal_po) . "'";
    }
}

if ($tgl_dari && $tgl_sampai) {
    $where_pemesanan .= " AND DATE(pm.tanggal_pemesanan) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} elseif ($tgl_dari) {
    $where_pemesanan .= " AND DATE(pm.tanggal_pemesanan) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
} elseif ($tgl_sampai) {
    $where_pemesanan .= " AND DATE(pm.tanggal_pemesanan) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} else {
    $where_pemesanan .= " AND DATE(pm.tanggal_pemesanan) = CURDATE()";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = CURDATE()";
}

if ($no_plat) {
    $where_pemesanan .= " AND pm.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
    $where_pengiriman .= " AND pg.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
}

// Query untuk pemesanan (tiket)
$sql_pemesanan = "SELECT 
    'Tiket' as jenis_transaksi,
    pm.no_plat_id,
    dt.kelas,
    COUNT(*) as jumlah_transaksi,
    SUM(pm.harga_id) as total_pendapatan,
    COUNT(DISTINCT pm.tanggal_berangkat) as jumlah_keberangkatan
FROM data_pemesanan pm
LEFT JOIN data_travel dt ON pm.no_plat_id = dt.no_plat
$where_pemesanan
GROUP BY pm.no_plat_id, dt.kelas";

// Query untuk pengiriman (paket)
$sql_pengiriman = "SELECT 
    'Paket' as jenis_transaksi,
    pg.no_plat_id,
    dt.kelas,
    COUNT(*) as jumlah_transaksi,
    SUM(pg.jumlah) as total_pendapatan,
    COUNT(DISTINCT pg.tanggal_pengiriman) as jumlah_keberangkatan
FROM data_pengiriman pg
LEFT JOIN data_travel dt ON pg.no_plat_id = dt.no_plat
$where_pengiriman
GROUP BY pg.no_plat_id, dt.kelas";

// Gabungkan query berdasarkan filter
if ($jenis_transaksi === 'tiket') {
    $sql = $sql_pemesanan;
} elseif ($jenis_transaksi === 'paket') {
    $sql = $sql_pengiriman;
} else {
    $sql = "($sql_pemesanan) UNION ALL ($sql_pengiriman) ORDER BY no_plat_id, jenis_transaksi";
}

$resRekap = mysqli_query($conn, $sql);

$rekap = [];
$total_pendapatan = 0;
$total_transaksi = 0;
$total_keberangkatan = 0;

while ($row = mysqli_fetch_assoc($resRekap)) {
    $rekap[] = $row;
    $total_pendapatan += $row['total_pendapatan'];
    $total_transaksi += $row['jumlah_transaksi'];
    $total_keberangkatan += $row['jumlah_keberangkatan'];
}

// Hitung rata-rata per transaksi
$rata_rata_transaksi = $total_transaksi > 0 ? $total_pendapatan / $total_transaksi : 0;

// ===============================================================
// Re-kalkulasi menggunakan kolom payment_methods JSON
// ===============================================================
$filterStart = $tgl_dari ?: date('Y-m-d');
$filterEnd   = $tgl_sampai ?: $filterStart;

// Map kelas travel
$kelasMap = [];
$resKls = mysqli_query($conn, "SELECT no_plat, kelas FROM data_travel");
while ($r = mysqli_fetch_assoc($resKls)) { $kelasMap[$r['no_plat']] = $r['kelas']; }

$rekap = [];
$total_pendapatan = 0;
$total_transaksi  = 0;
$keberangkatanSet = [];// array plat => [dates]

// Basis filter role
$whereRolePemesanan = $whereRolePengiriman = "WHERE 1=1";
if ($role !== 'super admin') {
    $whereRolePemesanan .= " AND pm.username = '$id_users' AND pm.asal_po = '$asal_po'";
    $whereRolePengiriman .= " AND pg.user_id = '$id_users' AND pg.asal_po_id = '$asal_po'";
} else {
    // Untuk super admin, bisa filter berdasarkan username dan asal_po
    if ($filter_username) {
        $whereRolePemesanan .= " AND pm.username = '" . mysqli_real_escape_string($conn, $filter_username) . "'";
        // Untuk data_pengiriman, cari user_id berdasarkan username
        $user_id = array_search($filter_username, $userMap);
        if ($user_id !== false) {
            $whereRolePengiriman .= " AND pg.user_id = '" . mysqli_real_escape_string($conn, $user_id) . "'";
        } else {
            // Jika tidak ditemukan mapping, gunakan filter_username langsung
            $whereRolePengiriman .= " AND pg.user_id = '" . mysqli_real_escape_string($conn, $filter_username) . "'";
        }
    }
    if ($filter_asal_po) {
        $whereRolePemesanan .= " AND pm.asal_po = '" . mysqli_real_escape_string($conn, $filter_asal_po) . "'";
        $whereRolePengiriman .= " AND pg.asal_po_id = '" . mysqli_real_escape_string($conn, $filter_asal_po) . "'";
    }
}
if ($no_plat) {
    $whereRolePemesanan  .= " AND pm.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
    $whereRolePengiriman .= " AND pg.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
}

// Tambahkan filter tanggal pada re-kalkulasi
if ($tgl_dari && $tgl_sampai) {
    $whereRolePemesanan .= " AND DATE(pm.tanggal_pemesanan) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
    $whereRolePengiriman .= " AND DATE(pg.tanggal_pengiriman) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} elseif ($tgl_dari) {
    $whereRolePemesanan .= " AND DATE(pm.tanggal_pemesanan) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
    $whereRolePengiriman .= " AND DATE(pg.tanggal_pengiriman) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
} elseif ($tgl_sampai) {
    $whereRolePemesanan .= " AND DATE(pm.tanggal_pemesanan) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
    $whereRolePengiriman .= " AND DATE(pg.tanggal_pengiriman) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} else {
    $whereRolePemesanan .= " AND DATE(pm.tanggal_pemesanan) = CURDATE()";
    $whereRolePengiriman .= " AND DATE(pg.tanggal_pengiriman) = CURDATE()";
}

function _push_travel(&$rekap, $plat, $kelas, $jenis, $amount, $tglKeberangkatan) {
    $key = $plat.'|'.$jenis;
    if (!isset($rekap[$key])) {
        $rekap[$key] = [
            'jenis_transaksi'      => $jenis,
            'no_plat_id'           => $plat,
            'kelas'                => $kelas,
            'jumlah_transaksi'     => 0,
            'total_pendapatan'     => 0,
            'keberangkatan_dates'  => []
        ];
    }
    $rekap[$key]['jumlah_transaksi'] += 1;
    $rekap[$key]['total_pendapatan'] += $amount;
    $rekap[$key]['keberangkatan_dates'][$tglKeberangkatan] = true;
}

// ======================== PEMESANAN =====================
if ($jenis_transaksi !== 'paket') {
    $sqlPemesanan = "SELECT pm.payment_methods, pm.harga_id, pm.no_plat_id, pm.tanggal_berangkat, pm.tanggal_pemesanan
                     FROM data_pemesanan pm $whereRolePemesanan";
    $res = mysqli_query($conn, $sqlPemesanan);
    while ($row = mysqli_fetch_assoc($res)) {
        $plat  = $row['no_plat_id'];
        $kelas = $kelasMap[$plat] ?? '-';
        $jenis = 'Tiket';
        $tglKeber = date('Y-m-d', strtotime($row['tanggal_berangkat']));
        if (!empty($row['payment_methods'])) {
            $arrPay = json_decode($row['payment_methods'], true);
            if (is_array($arrPay)) {
                foreach ($arrPay as $p) {
                    $pDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : date('Y-m-d', strtotime($row['tanggal_pemesanan']));
                    if ($pDate < $filterStart || $pDate > $filterEnd) continue;
                    $amount = floatval($p['amount'] ?? 0);
                    _push_travel($rekap, $plat, $kelas, $jenis, $amount, $tglKeber);
                    $total_pendapatan += $amount;
                    $total_transaksi++;
                }
                continue;
            }
        }
        $trxDate = date('Y-m-d', strtotime($row['tanggal_pemesanan']));
        if ($trxDate < $filterStart || $trxDate > $filterEnd) continue;
        $amount = floatval($row['harga_id']);
        _push_travel($rekap, $plat, $kelas, $jenis, $amount, $tglKeber);
        $total_pendapatan += $amount;
        $total_transaksi++;
    }
}

// ======================== PENGIRIMAN =====================
if ($jenis_transaksi !== 'tiket') {
    $sqlPengiriman = "SELECT pg.payment_methods, pg.jumlah, pg.no_plat_id, pg.tanggal_pengiriman
                      FROM data_pengiriman pg $whereRolePengiriman";
    $res = mysqli_query($conn, $sqlPengiriman);
    while ($row = mysqli_fetch_assoc($res)) {
        $plat  = $row['no_plat_id'];
        $kelas = $kelasMap[$plat] ?? '-';
        $jenis = 'Paket';
        $tglKeber = date('Y-m-d', strtotime($row['tanggal_pengiriman']));
        if (!empty($row['payment_methods'])) {
            $arrPay = json_decode($row['payment_methods'], true);
            if (is_array($arrPay)) {
                foreach ($arrPay as $p) {
                    $pDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : $tglKeber;
                    if ($pDate < $filterStart || $pDate > $filterEnd) continue;
                    $amount = floatval($p['amount'] ?? 0);
                    _push_travel($rekap, $plat, $kelas, $jenis, $amount, $tglKeber);
                    $total_pendapatan += $amount;
                    $total_transaksi++;
                }
                continue;
            }
        }
        $trxDate = $tglKeber;
        if ($trxDate < $filterStart || $trxDate > $filterEnd) continue;
        $amount = floatval($row['jumlah']);
        _push_travel($rekap, $plat, $kelas, $jenis, $amount, $tglKeber);
        $total_pendapatan += $amount;
        $total_transaksi++;
    }
}

// Hitung jumlah keberangkatan unik
foreach ($rekap as &$r) {
    $r['jumlah_keberangkatan'] = count($r['keberangkatan_dates']);
    unset($r['keberangkatan_dates']);
}
unset($r);

// Sort by plat
usort($rekap, function($a,$b){return [$a['no_plat_id'],$a['jenis_transaksi']]<=>[$b['no_plat_id'],$b['jenis_transaksi']];});

// Hitung rata-rata
$rata_rata_transaksi = $total_transaksi > 0 ? $total_pendapatan / $total_transaksi : 0;
// ===============================================================
?>
<div class="content-wrapper" style="background-color: #fcfcfc;">
    <!-- Content Header -->
    <div class="content-header" style="padding: 14px 18px 8px; background: #ffffff; border-bottom: 1px solid #e0e0e0; margin-bottom: 15px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.2rem;">
                            <i class="fas fa-bus"></i>
                        </div>
                        <div>
                            <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Laporan Pendapatan per Armada Travel</h1>
                            <div class="text-muted" style="font-size: 0.83rem;">
                                <span>Rekapitulasi omzet tiket dan titipan paket berdasarkan nomor plat unit travel</span>
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
                            <i class="fas fa-filter mr-1 text-muted"></i> Filter Periode & Parameter Armada
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
                                <div class="col-md-2">
                                    <label for="jenis_transaksi">Jenis Transaksi</label>
                                    <select class="form-control" name="jenis_transaksi" id="jenis_transaksi">
                                        <option value="semua" <?= ($jenis_transaksi === 'semua') ? 'selected' : '' ?>>Semua Transaksi</option>
                                        <option value="tiket" <?= ($jenis_transaksi === 'tiket') ? 'selected' : '' ?>>Tiket Saja</option>
                                        <option value="paket" <?= ($jenis_transaksi === 'paket') ? 'selected' : '' ?>>Paket Saja</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="no_plat">No. Plat Travel</label>
                                    <select class="form-control" name="no_plat" id="no_plat">
                                        <option value="">-- Semua Travel --</option>
                                        <?php foreach ($travelOptions as $travel) { ?>
                                            <option value="<?= htmlspecialchars($travel['no_plat']) ?>" <?= ($no_plat == $travel['no_plat']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($travel['no_plat']) ?> (<?= htmlspecialchars($travel['kelas']) ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <?php if ($role === 'super admin'): ?>
                                <div class="col-md-2">
                                    <label for="filter_username">Username</label>
                                    <select class="form-control" name="filter_username" id="filter_username">
                                        <option value="">-- Semua User --</option>
                                        <?php foreach ($usernameOptions as $id => $label) { ?>
                                            <option value="<?= htmlspecialchars($id) ?>" <?= ($filter_username == $id) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_asal_po">Asal PO</label>
                                    <select class="form-control" name="filter_asal_po" id="filter_asal_po">
                                        <option value="">-- Semua PO --</option>
                                        <?php foreach ($asalPoOptions as $po) { ?>
                                            <option value="<?= htmlspecialchars($po) ?>" <?= ($filter_asal_po == $po) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($po) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <a href="pendapatan_travel.php" class="btn btn-secondary">
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
                        <div class="info-box bg-success">
                            <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pendapatan</span>
                                <span class="info-box-number">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></span>
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
                                <span class="info-box-text">Jumlah Keberangkatan</span>
                                <span class="info-box-number"><?= number_format($total_keberangkatan) ?></span>
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
                    <h5 class="mb-1">LAPORAN PENDAPATAN PER TRAVEL</h5>
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
                                if ($jenis_transaksi !== 'semua') {
                                    echo " | Jenis: " . ucfirst($jenis_transaksi);
                            }
                            if ($no_plat) {
                                    echo " | Plat: " . htmlspecialchars($no_plat);
                            }
                                if ($asal_po && $role !== 'super admin') {
                                    echo " | PO: " . htmlspecialchars($asal_po);
                            }
                                if ($filter_username && $role === 'super admin') {
                                    echo " | Username: " . htmlspecialchars($filter_username);
                            }
                                if ($filter_asal_po && $role === 'super admin') {
                                    echo " | Asal PO: " . htmlspecialchars($filter_asal_po);
                            }
                                if ($role === 'super admin' && !$filter_username && !$filter_asal_po) {
                                    echo " | Semua User & PO";
                            }
                                if ($role === 'super admin' && ($filter_username || $filter_asal_po)) {
                                    echo " | Filter Aktif";
                            }
                                if ($role !== 'super admin') {
                                    echo " | User: " . htmlspecialchars($user['username']) . " | PO: " . htmlspecialchars($asal_po);
                            }
                                if ($role === 'super admin' && !$filter_username && !$filter_asal_po) {
                                    echo " | Semua Data";
                            }
                                if ($role === 'super admin' && ($filter_username || $filter_asal_po)) {
                                    echo " | Filter: " . ($filter_username ? htmlspecialchars($filter_username) : '') . ($filter_username && $filter_asal_po ? ' | ' : '') . ($filter_asal_po ? htmlspecialchars($filter_asal_po) : '');
                            }
                        ?>
                    </small>
                    </p>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableRekap">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th>No. Plat Travel</th>
                                <th>Kelas</th>
                                <th>Jenis Transaksi</th>
                                <th class="text-center">Jumlah Transaksi</th>
                                <th class="text-center">Jumlah Keberangkatan</th>
                                <th class="text-right">Total Pendapatan</th>
                                <th class="text-right">Rata-rata per Transaksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $subtotal_plat = [];
                            
                            foreach ($rekap as $row) {
                                $rata_rata = $row['jumlah_transaksi'] > 0 ? $row['total_pendapatan'] / $row['jumlah_transaksi'] : 0;

                                echo "<tr>";
                                echo "<td class='text-center'>" . $no++ . "</td>";
                                echo "<td><strong>" . htmlspecialchars($row['no_plat_id']) . "</strong></td>";
                                echo "<td><span class='badge badge-secondary'>" . htmlspecialchars($row['kelas']) . "</span></td>";
                                echo "<td><span class='badge badge-secondary" . ($row['jenis_transaksi'] === 'Tiket' ? 'primary' : 'info') . "'>" . htmlspecialchars($row['jenis_transaksi']) . "</span></td>";
                                echo "<td class='text-center'>" . number_format($row['jumlah_transaksi']) . "</td>";
                                echo "<td class='text-center'>" . number_format($row['jumlah_keberangkatan']) . "</td>";
                                echo "<td class='text-right'><strong>Rp " . number_format($row['total_pendapatan'], 0, ',', '.') . "</strong></td>";
                                echo "<td class='text-right'>Rp " . number_format($rata_rata, 0, ',', '.') . "</td>";
                                echo "</tr>";
                                
                                // Hitung subtotal per plat
                                if (!isset($subtotal_plat[$row['no_plat_id']])) {
                                    $subtotal_plat[$row['no_plat_id']] = [
                                        'total' => 0,
                                        'transaksi' => 0,
                                        'keberangkatan' => 0
                                    ];
                                }
                                $subtotal_plat[$row['no_plat_id']]['total'] += $row['total_pendapatan'];
                                $subtotal_plat[$row['no_plat_id']]['transaksi'] += $row['jumlah_transaksi'];
                                $subtotal_plat[$row['no_plat_id']]['keberangkatan'] += $row['jumlah_keberangkatan'];
                            }
                            ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <?php foreach ($subtotal_plat as $plat => $data): ?>
                            <tr class="table-info">
                                <th colspan="4" class="text-right">Subtotal <?= htmlspecialchars($plat) ?></th>
                                <th class="text-center"><?= number_format($data['transaksi']) ?></th>
                                <th class="text-center"><?= number_format($data['keberangkatan']) ?></th>
                                <th class="text-right">Rp <?= number_format($data['total'], 0, ',', '.') ?></th>
                                <th class="text-right">Rp <?= number_format($data['transaksi'] > 0 ? $data['total'] / $data['transaksi'] : 0, 0, ',', '.') ?></th>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-success">
                                <th colspan="4" class="text-right">GRAND TOTAL</th>
                                <th class="text-center"><?= number_format($total_transaksi) ?></th>
                                <th class="text-center"><?= number_format($total_keberangkatan) ?></th>
                                <th class="text-right">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></th>
                                <th class="text-right">Rp <?= number_format($rata_rata_transaksi, 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <p><strong>Keterangan:</strong></p>
                        <ul class="mb-0">
                            <li>Jumlah transaksi = total penumpang/paket yang dipesan</li>
                            <li>Jumlah keberangkatan = total hari keberangkatan yang berbeda</li>
                            <li>Rata-rata per transaksi = total pendapatan ÷ jumlah transaksi</li>
                            <li>Laporan ini mencakup transaksi tiket dan paket</li>
                            <?php if ($role === 'super admin'): ?>
                            <li>Filter Username dan Asal PO hanya tersedia untuk Super Admin</li>
                            <?php if ($filter_username || $filter_asal_po): ?>
                            <li>Filter yang aktif: <?= $filter_username ? 'Username: ' . htmlspecialchars($filter_username) : '' ?><?= $filter_username && $filter_asal_po ? ' | ' : '' ?><?= $filter_asal_po ? 'Asal PO: ' . htmlspecialchars($filter_asal_po) : '' ?></li>
                            <?php else: ?>
                            <li>Menampilkan semua data (tidak ada filter)</li>
                            <?php endif; ?>
                            <?php else: ?>
                            <li>Laporan ditampilkan untuk User: <?= htmlspecialchars($user['username']) ?> | PO: <?= htmlspecialchars($asal_po) ?></li>
                            <?php endif; ?>
                            <li>Laporan ini menggunakan sistem payment_methods JSON untuk perhitungan yang akurat</li>
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
    downloadLink.download = 'Laporan_Pendapatan_Travel_<?= date('Y-m-d') ?><?= $filter_username ? '_' . $filter_username : '' ?><?= $filter_asal_po ? '_' . $filter_asal_po : '' ?><?= $role !== 'super admin' ? '_' . $user['username'] : '' ?>.xls';
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Auto submit form when filter changes
document.getElementById('jenis_transaksi').addEventListener('change', function() {
    document.querySelector('form').submit();
});

document.getElementById('no_plat').addEventListener('change', function() {
    document.querySelector('form').submit();
});

// Auto submit form when username and asal_po filter changes (only for super admin)
const filterUsername = document.getElementById('filter_username');
const filterAsalPo = document.getElementById('filter_asal_po');

if (filterUsername) {
    filterUsername.addEventListener('change', function() {
        document.querySelector('form').submit();
    });
}

if (filterAsalPo) {
    filterAsalPo.addEventListener('change', function() {
        document.querySelector('form').submit();
    });
}
</script>

<style>
@media print {
  #printArea {
    margin-top: 18mm !important;
  }
}
</style>