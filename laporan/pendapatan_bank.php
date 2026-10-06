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
$no_rekening = $_GET['no_rekening'] ?? '';

// Query data rekening untuk dropdown
$rekeningOptions = [];
$resRek = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening ORDER BY nama_rekening");
while ($r = mysqli_fetch_assoc($resRek)) {
    $rekeningOptions[] = $r;
}

// Query rekap pendapatan bank
$where_pemesanan = "WHERE 1=1";
$where_pengiriman = "WHERE 1=1";

if ($role !== 'super admin') {
    $where_pemesanan .= " AND pm.username = '$id_users' AND pm.asal_po = '$asal_po'";
    $where_pengiriman .= " AND pg.user_id = '$id_users' AND pg.asal_po_id = '$asal_po'";
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

if ($no_rekening) {
    $where_pemesanan .= " AND pm.jenis_rekening_id = '" . mysqli_real_escape_string($conn, $no_rekening) . "'";
    $where_pengiriman .= " AND pg.jenis_rekening_id = '" . mysqli_real_escape_string($conn, $no_rekening) . "'";
}

// Query untuk pemesanan (tiket)
$sql_pemesanan = "SELECT 
    'Tiket' as jenis_transaksi,
    dr.id_rekening,
    dr.nama_rekening,
    mp.metode_pembayaran,
    sp.status_pembayaran,
    COUNT(*) as jumlah_transaksi,
    SUM(pm.harga_id) as total_pendapatan
FROM data_pemesanan pm
LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
$where_pemesanan
GROUP BY dr.id_rekening, mp.id_metode_pembayaran, sp.id_status_pembayaran";

// Query untuk pengiriman (paket)
$sql_pengiriman = "SELECT 
    'Paket' as jenis_transaksi,
    dr.id_rekening,
    dr.nama_rekening,
    mp.metode_pembayaran,
    sp.status_pembayaran,
    COUNT(*) as jumlah_transaksi,
    SUM(pg.jumlah) as total_pendapatan
FROM data_pengiriman pg
LEFT JOIN data_rekening dr ON pg.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_metode_pembayaran mp ON pg.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran
$where_pengiriman
GROUP BY dr.id_rekening, mp.id_metode_pembayaran, sp.id_status_pembayaran";

// Gabungkan query berdasarkan filter
if ($jenis_transaksi === 'tiket') {
    $sql = $sql_pemesanan;
} elseif ($jenis_transaksi === 'paket') {
    $sql = $sql_pengiriman;
} else {
    $sql = "($sql_pemesanan) UNION ALL ($sql_pengiriman) ORDER BY nama_rekening, jenis_transaksi, metode_pembayaran, status_pembayaran";
}

$resRekap = mysqli_query($conn, $sql);

$rekap = [];
$total_pendapatan = 0;
$total_transaksi = 0;

while ($row = mysqli_fetch_assoc($resRekap)) {
    $isDP = (stripos($row['status_pembayaran'], 'dp') !== false || stripos($row['status_pembayaran'], 'piutang') !== false);
    $row['is_dp'] = $isDP;
    $rekap[] = $row;
    
    // Tambahkan semua pendapatan, termasuk yang berstatus DP/Piutang
        $total_pendapatan += $row['total_pendapatan'];
    $total_transaksi += $row['jumlah_transaksi'];
}

// ===============================================================
// Re-kalkulasi laporan menggunakan struktur payment_methods JSON
// ===============================================================
$filterStart = $tgl_dari ?: date('Y-m-d');
$filterEnd   = $tgl_sampai ?: $filterStart;

// Peta referensi bank / metode / status
$rekeningMap = $metodeMap = $statusMap = [];
$res = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening");
while ($r = mysqli_fetch_assoc($res)) { $rekeningMap[$r['id_rekening']] = $r['nama_rekening']; }
$res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
while ($r = mysqli_fetch_assoc($res)) { $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran']; }
$res = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
while ($r = mysqli_fetch_assoc($res)) { $statusMap[$r['id_status_pembayaran']] = $r['status_pembayaran']; }

// Helper untuk menambahkan data ke rekap
function _push_rekap_bank(&$arr, $jenis, $rekId, $methodId, $statusId, $amount) {
    $key = $rekId.'|'.$jenis.'|'.$methodId.'|'.$statusId;
    if (!isset($arr[$key])) {
        $arr[$key] = [
            'jenis_transaksi'       => $jenis,
            'id_rekening'           => $rekId,
            'metode_pembayaran_id'  => $methodId,
            'status_pembayaran_id'  => $statusId,
            'jumlah_transaksi'      => 0,
            'total_pendapatan'      => 0
        ];
    }
    $arr[$key]['jumlah_transaksi'] += 1;
    $arr[$key]['total_pendapatan'] += $amount;
}

$rekap = [];
$total_pendapatan = 0;
$total_transaksi  = 0;

// Basis filter role
$whereRolePemesanan = $whereRolePengiriman = "WHERE 1=1";
if ($role !== 'super admin') {
    $whereRolePemesanan .= " AND pm.username = '$id_users' AND pm.asal_po = '$asal_po'";
    $whereRolePengiriman .= " AND pg.user_id = '$id_users' AND pg.asal_po_id = '$asal_po'";
}
if ($no_rekening) {
    $whereRolePemesanan   .= " AND pm.jenis_rekening_id = '" . mysqli_real_escape_string($conn, $no_rekening) . "'";
    $whereRolePengiriman  .= " AND pg.jenis_rekening_id = '" . mysqli_real_escape_string($conn, $no_rekening) . "'";
}

// ======================== PEMESANAN (Tiket) =====================
if ($jenis_transaksi !== 'paket') {
    $sqlPemesanan = "SELECT pm.payment_methods, pm.harga_id, pm.metode_pembayaran_id, pm.jenis_rekening_id, pm.status_pembayaran_id, pm.tanggal_pemesanan
                     FROM data_pemesanan pm $whereRolePemesanan";
    $res = mysqli_query($conn, $sqlPemesanan);
    while ($row = mysqli_fetch_assoc($res)) {
        $jenis = 'Tiket';
        if (!empty($row['payment_methods'])) {
            $arrPay = json_decode($row['payment_methods'], true);
            if (is_array($arrPay)) {
                foreach ($arrPay as $p) {
                    $pDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : date('Y-m-d', strtotime($row['tanggal_pemesanan']));
                    if ($pDate < $filterStart || $pDate > $filterEnd) continue;
                    $amount = floatval($p['amount'] ?? 0);
                    _push_rekap_bank($rekap, $jenis, $p['rekening_id'] ?? $row['jenis_rekening_id'], $p['method_id'] ?? $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount);
                    $total_pendapatan += $amount;
                    $total_transaksi++;
                }
                continue;
            }
        }
        // Fallback data single-payment lama
        $trxDate = date('Y-m-d', strtotime($row['tanggal_pemesanan']));
        if ($trxDate < $filterStart || $trxDate > $filterEnd) continue;
        $amount = floatval($row['harga_id']);
        _push_rekap_bank($rekap, $jenis, $row['jenis_rekening_id'], $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount);
        $total_pendapatan += $amount;
        $total_transaksi++;
    }
}

// ======================== PENGIRIMAN (Paket) =====================
if ($jenis_transaksi !== 'tiket') {
    $sqlPengiriman = "SELECT pg.payment_methods, pg.jumlah, pg.metode_pembayaran_id, pg.jenis_rekening_id, pg.status_pembayaran_id, pg.tanggal_pengiriman
                      FROM data_pengiriman pg $whereRolePengiriman";
    $res = mysqli_query($conn, $sqlPengiriman);
    while ($row = mysqli_fetch_assoc($res)) {
        $jenis = 'Paket';
        if (!empty($row['payment_methods'])) {
            $arrPay = json_decode($row['payment_methods'], true);
            if (is_array($arrPay)) {
                foreach ($arrPay as $p) {
                    $pDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : date('Y-m-d', strtotime($row['tanggal_pengiriman']));
                    if ($pDate < $filterStart || $pDate > $filterEnd) continue;
                    $amount = floatval($p['amount'] ?? 0);
                    _push_rekap_bank($rekap, $jenis, $p['rekening_id'] ?? $row['jenis_rekening_id'], $p['method_id'] ?? $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount);
                    $total_pendapatan += $amount;
                    $total_transaksi++;
                }
                continue;
            }
        }
        $trxDate = date('Y-m-d', strtotime($row['tanggal_pengiriman']));
        if ($trxDate < $filterStart || $trxDate > $filterEnd) continue;
        $amount = floatval($row['jumlah']);
        _push_rekap_bank($rekap, $jenis, $row['jenis_rekening_id'], $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount);
        $total_pendapatan += $amount;
        $total_transaksi++;
    }
}

// Tambahkan nama, metode, status + flag DP
foreach ($rekap as &$r) {
    $r['nama_rekening']     = $rekeningMap[$r['id_rekening']]          ?? 'Lainnya';
    $r['metode_pembayaran'] = $metodeMap[$r['metode_pembayaran_id']]   ?? 'Lainnya';
    $r['status_pembayaran'] = $statusMap[$r['status_pembayaran_id']]   ?? '';
    $r['is_dp'] = (stripos($r['status_pembayaran'], 'dp') !== false || stripos($r['status_pembayaran'], 'piutang') !== false);
}
unset($r);
// Urutkan berdasar bank lalu jenis
usort($rekap, function($a, $b) {
    return [$a['nama_rekening'], $a['jenis_transaksi']] <=> [$b['nama_rekening'], $b['jenis_transaksi']];
});
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
                            <i class="fas fa-building-columns"></i>
                        </div>
                        <div>
                            <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Laporan Pendapatan Kas & Bank</h1>
                            <div class="text-muted" style="font-size: 0.83rem;">
                                <span>Rekapitulasi arus pendapatan masuk per akun kasir tunai dan rekening bank</span>
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
                            <i class="fas fa-filter mr-1 text-muted"></i> Filter Periode & Rekening Kasir
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
                                    <label for="jenis_transaksi">Jenis Transaksi</label>
                                    <select class="form-control" name="jenis_transaksi" id="jenis_transaksi">
                                        <option value="semua" <?= ($jenis_transaksi === 'semua') ? 'selected' : '' ?>>Semua Transaksi</option>
                                        <option value="tiket" <?= ($jenis_transaksi === 'tiket') ? 'selected' : '' ?>>Tiket Saja</option>
                                        <option value="paket" <?= ($jenis_transaksi === 'paket') ? 'selected' : '' ?>>Paket Saja</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="no_rekening">Bank/Rekening</label>
                                    <select class="form-control" name="no_rekening" id="no_rekening">
                                        <option value="">-- Semua Bank --</option>
                                        <?php foreach ($rekeningOptions as $rek) { ?>
                                            <option value="<?= htmlspecialchars($rek['id_rekening']) ?>" <?= ($no_rekening == $rek['id_rekening']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($rek['nama_rekening']) ?>
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
                                    <a href="pendapatan_bank.php" class="btn btn-secondary">
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
                    <div class="col-md-4">
                        <div class="info-box bg-success">
                            <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pendapatan</span>
                                <span class="info-box-number">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box bg-info">
                            <span class="info-box-icon"><i class="fas fa-receipt"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Transaksi</span>
                                <span class="info-box-number"><?= number_format($total_transaksi) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box bg-warning">
                            <span class="info-box-icon"><i class="fas fa-university"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Bank Terlibat</span>
                                <span class="info-box-number"><?= count(array_unique(array_column($rekap, 'nama_rekening'))) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Report Table -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-table"></i> Detail Laporan</h3>
                </div>
                <div class="card-body">
                    <div id="printArea">
                        <div class="text-center mb-3">
                            <h4 class="mb-0"><strong>SINAR GALAXY TRAVEL</strong></h4>
                            <h5 class="mb-1">LAPORAN PENDAPATAN BANK</h5>
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
                                        if ($no_rekening) {
                                            $selectedBank = array_filter($rekeningOptions, function($rek) use ($no_rekening) {
                                                return $rek['id_rekening'] == $no_rekening;
                                            });
                                            $selectedBank = reset($selectedBank);
                                            echo " | Bank: " . htmlspecialchars($selectedBank['nama_rekening']);
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
                                <thead class="bg-primary text-white">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No</th>
                                        <th>Bank/Rekening</th>
                                        <th>Jenis Transaksi</th>
                                        <th>Metode Pembayaran</th>
                                        <th>Status</th>
                                        <th class="text-center">Jumlah Transaksi</th>
                                        <th class="text-right">Total Pendapatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $subtotal_bank = [];
                                    
                                    foreach ($rekap as $row) {
                                        $rowStyle = $row['is_dp'] ? 'style="background-color: #fff3cd;"' : '';
                                        $statusClass = $row['is_dp'] ? 'text-warning' : 'text-success';
                                        
                                        echo "<tr $rowStyle>";
                                        echo "<td class='text-center'>" . $no++ . "</td>";
                                        echo "<td><strong>" . htmlspecialchars($row['nama_rekening']) . "</strong></td>";
                                        echo "<td><span class='badge badge-secondary" . ($row['jenis_transaksi'] === 'Tiket' ? 'primary' : 'info') . "'>" . htmlspecialchars($row['jenis_transaksi']) . "</span></td>";
                                        echo "<td>" . htmlspecialchars($row['metode_pembayaran']) . "</td>";
                                        echo "<td class='$statusClass'><strong>" . htmlspecialchars($row['status_pembayaran']) . "</strong></td>";
                                        echo "<td class='text-center'>" . number_format($row['jumlah_transaksi']) . "</td>";
                                        echo "<td class='text-right'><strong>Rp " . number_format($row['total_pendapatan'], 0, ',', '.') . "</strong></td>";
                                        echo "</tr>";
                                        
                                        // Hitung subtotal per bank
                                        if (!isset($subtotal_bank[$row['nama_rekening']])) {
                                            $subtotal_bank[$row['nama_rekening']] = 0;
                                        }
                                        // Akumulasi pendapatan per bank tanpa mengurangi DP/Piutang
                                            $subtotal_bank[$row['nama_rekening']] += $row['total_pendapatan'];
                                    }
                                    ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <?php foreach ($subtotal_bank as $bank => $total): ?>
                                    <tr class="table-info">
                                        <th colspan="6" class="text-right">Subtotal <?= htmlspecialchars($bank) ?></th>
                                        <th class="text-right">Rp <?= number_format($total, 0, ',', '.') ?></th>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-success">
                                        <th colspan="6" class="text-right">GRAND TOTAL</th>
                                        <th class="text-right">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p><strong>Keterangan:</strong></p>
                                <ul class="mb-0">
                                    <li>Status DP/Piutang ditandai dengan background kuning</li>
                                    
                                    <li>Laporan ini mencakup transaksi tiket dan paket</li>
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
    downloadLink.download = 'Laporan_Pendapatan_Bank_<?= date('Y-m-d') ?>.xls';
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Auto submit form when filter changes
document.getElementById('jenis_transaksi').addEventListener('change', function() {
    document.querySelector('form').submit();
});

document.getElementById('no_rekening').addEventListener('change', function() {
    document.querySelector('form').submit();
});
</script>