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
$jenis_laporan = $_GET['jenis_laporan'] ?? 'semua';

// Query rekap pendapatan
$where_pendapatan = "WHERE 1=1";
if ($role !== 'super admin') {
    $where_pendapatan .= " AND pm.username = '$id_users' AND pm.asal_po = '$asal_po'";
}
if ($tgl_dari && $tgl_sampai) {
    $where_pendapatan .= " AND DATE(pm.tanggal_pemesanan) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} elseif ($tgl_dari) {
    $where_pendapatan .= " AND DATE(pm.tanggal_pemesanan) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
} elseif ($tgl_sampai) {
    $where_pendapatan .= " AND DATE(pm.tanggal_pemesanan) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} else {
    $where_pendapatan .= " AND DATE(pm.tanggal_pemesanan) = CURDATE()";
}

$sql_pendapatan = "SELECT 
    'Pendapatan' as jenis,
    dr.nama_rekening,
    mp.metode_pembayaran,
    sp.status_pembayaran,
    COUNT(*) as transaksi,
    SUM(pm.harga_id) as total
FROM data_pemesanan pm
LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
$where_pendapatan
GROUP BY dr.id_rekening, mp.id_metode_pembayaran, sp.id_status_pembayaran";

// Query rekap pengeluaran
$where_pengeluaran = "WHERE 1=1";
if ($role !== 'super admin') {
    $where_pengeluaran .= " AND pg.user_id = '$id_users' AND pg.asal_po = '$asal_po'";
}
if ($tgl_dari && $tgl_sampai) {
    $where_pengeluaran .= " AND DATE(pg.tanggal_pengeluaran) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} elseif ($tgl_dari) {
    $where_pengeluaran .= " AND DATE(pg.tanggal_pengeluaran) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
} elseif ($tgl_sampai) {
    $where_pengeluaran .= " AND DATE(pg.tanggal_pengeluaran) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} else {
    $where_pengeluaran .= " AND DATE(pg.tanggal_pengeluaran) = CURDATE()";
}

$sql_pengeluaran = "SELECT 
    'Pengeluaran' as jenis,
    dr.nama_rekening,
    CONCAT(UPPER(pg.kategori_pengeluaran), ' - ', jp.nama_pengeluaran) as metode_pembayaran,
    sp.status_pembayaran,
    COUNT(*) as transaksi,
    SUM(pg.harga_operasional) as total
FROM data_pengeluaran pg
LEFT JOIN data_rekening dr ON pg.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_jenis_pengeluaran jp ON pg.jenis_pengeluaran_id = jp.id_jenis_pengeluaran
LEFT JOIN data_metode_pembayaran mp ON pg.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran
$where_pengeluaran
GROUP BY dr.id_rekening, pg.kategori_pengeluaran, jp.id_jenis_pengeluaran, sp.id_status_pembayaran";

// Gabungkan query berdasarkan filter
if ($jenis_laporan === 'pendapatan') {
    $sql = $sql_pendapatan;
} elseif ($jenis_laporan === 'pengeluaran') {
    $sql = $sql_pengeluaran;
} else {
    $sql = "($sql_pendapatan) UNION ALL ($sql_pengeluaran) ORDER BY jenis, nama_rekening, metode_pembayaran, status_pembayaran";
}

$resRekap = mysqli_query($conn, $sql);

$rekap = [];// akan di override setelah rekalkulasi
$total_pendapatan = 0;
$total_pengeluaran = 0;
$grandTotal = 0;

// =============================================================
// REKALKULASI REKAP KEUANGAN BERDASARKAN payment_methods JSON
// (Override hasil lama)
// =============================================================
$rekap = [];
$total_pendapatan = 0;
$total_pengeluaran = 0;
$grandTotal = 0;

$filterStart = $tgl_dari ?: date('Y-m-d');
$filterEnd   = $tgl_sampai ?: $filterStart;

$rekeningMap = $metodeMap = $statusMap = [];
$res = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening");
while($r=mysqli_fetch_assoc($res)){$rekeningMap[$r['id_rekening']]=$r['nama_rekening'];}
$res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
while($r=mysqli_fetch_assoc($res)){$metodeMap[$r['id_metode_pembayaran']]=$r['metode_pembayaran'];}
$res = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
while($r=mysqli_fetch_assoc($res)){$statusMap[$r['id_status_pembayaran']]=$r['status_pembayaran'];}

function _push_rekap(&$arr,$jenis,$rekName,$metode,$status,$amount){
  $key=$jenis.'|'.$rekName.'|'.$metode.'|'.$status;
  if(!isset($arr[$key])){$arr[$key]=['jenis'=>$jenis,'nama_rekening'=>$rekName,'metode_pembayaran'=>$metode,'status_pembayaran'=>$status,'transaksi'=>0,'total'=>0];}
  $arr[$key]['transaksi']+=1; $arr[$key]['total']+=$amount;
}

// Role filter
$wherePem = $wherePeng = " WHERE 1=1";
// Selaraskan field asal_po untuk pengeluaran dengan query atas (tanpa ubah skema)
if($role!=='super admin'){
  $wherePem .= " AND pm.username = '$id_users' AND pm.asal_po = '$asal_po'";
  $wherePeng .= " AND pg.user_id = '$id_users' AND pg.asal_po = '$asal_po'";
}

// ---------- Pendapatan Pemesanan & Pengiriman ----------
$sqlPem = "SELECT pm.payment_methods, pm.harga_id, pm.jenis_rekening_id, pm.metode_pembayaran_id, sp.status_pembayaran, pm.tanggal_pemesanan
           FROM data_pemesanan pm
           LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran".$wherePem;
$res=mysqli_query($conn,$sqlPem);
if($res){while($row=mysqli_fetch_assoc($res)){
  $status=$row['status_pembayaran'];
  if($row['payment_methods']){
    $arr=json_decode($row['payment_methods'],true);
    foreach($arr as $p){
      $pDate=date('Y-m-d',strtotime($p['payment_date']??$row['tanggal_pemesanan']));
      if($pDate<$filterStart||$pDate>$filterEnd) continue;
      $amt=floatval($p['amount']??0);
      _push_rekap($rekap,'Pendapatan',$rekeningMap[$p['rekening_id']??$row['jenis_rekening_id']]??'-',$metodeMap[$p['method_id']??$row['metode_pembayaran_id']]??'-',$status,$amt);
      $total_pendapatan+=$amt;
    }
  }else{
    $pDate=date('Y-m-d',strtotime($row['tanggal_pemesanan'])); if($pDate<$filterStart||$pDate>$filterEnd) continue;
    $amt=floatval($row['harga_id']);
    _push_rekap($rekap,'Pendapatan',$rekeningMap[$row['jenis_rekening_id']]??'-',$metodeMap[$row['metode_pembayaran_id']]??'-',$status,$amt);
    $total_pendapatan+=$amt;
  }
}}

$sqlPeng = "SELECT pg.payment_methods, pg.jumlah, pg.jenis_rekening_id, pg.metode_pembayaran_id, sp.status_pembayaran, pg.tanggal_pengiriman
            FROM data_pengiriman pg
            LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran".$wherePeng;
$res=mysqli_query($conn,$sqlPeng);
if($res){while($row=mysqli_fetch_assoc($res)){
  $status=$row['status_pembayaran'];
  if($row['payment_methods']){
    $arr=json_decode($row['payment_methods'],true);
    foreach($arr as $p){
      $pDate=date('Y-m-d',strtotime($p['payment_date']??$row['tanggal_pengiriman']));
      if($pDate<$filterStart||$pDate>$filterEnd) continue;
      $amt=floatval($p['amount']??0);
      _push_rekap($rekap,'Pendapatan',$rekeningMap[$p['rekening_id']??$row['jenis_rekening_id']]??'-',$metodeMap[$p['method_id']??$row['metode_pembayaran_id']]??'-',$status,$amt);
      $total_pendapatan+=$amt;
    }
  }else{
    $pDate=date('Y-m-d',strtotime($row['tanggal_pengiriman'])); if($pDate<$filterStart||$pDate>$filterEnd) continue;
    $amt=floatval($row['jumlah']);
    _push_rekap($rekap,'Pendapatan',$rekeningMap[$row['jenis_rekening_id']]??'-',$metodeMap[$row['metode_pembayaran_id']]??'-',$status,$amt);
    $total_pendapatan+=$amt;
  }
}}

// Pengeluaran
$sqlOut = "SELECT pg.harga_operasional, dr.nama_rekening, CONCAT(UPPER(pg.kategori_pengeluaran), ' - ', jp.nama_pengeluaran) as metode, sp.status_pembayaran, pg.tanggal_pengeluaran
           FROM data_pengeluaran pg
           LEFT JOIN data_rekening dr ON pg.jenis_rekening_id = dr.id_rekening
           LEFT JOIN data_jenis_pengeluaran jp ON pg.jenis_pengeluaran_id = jp.id_jenis_pengeluaran
           LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran".$wherePeng;
$res=mysqli_query($conn,$sqlOut);
if($res){while($row=mysqli_fetch_assoc($res)){
  $pDate=date('Y-m-d',strtotime($row['tanggal_pengeluaran'])); if($pDate<$filterStart||$pDate>$filterEnd) continue;
  $amt=floatval($row['harga_operasional']);
  _push_rekap($rekap,'Pengeluaran',$row['nama_rekening'],$row['metode'],$row['status_pembayaran'],$amt);
  $total_pengeluaran+=$amt;
}}

$grandTotal = $total_pendapatan - $total_pengeluaran;
// =============================================================

if ($resRekap) {
    while ($row = mysqli_fetch_assoc($resRekap)) {
        $isDP = (stripos($row['status_pembayaran'], 'dp') !== false || stripos($row['status_pembayaran'], 'piutang') !== false);
        $row['is_dp'] = $isDP;
        $rekap[] = $row;
        
        if ($row['jenis'] === 'Pendapatan') {
            if ($isDP) {
                $total_pendapatan -= $row['total'];
            } else {
                $total_pendapatan += $row['total'];
            }
        } else {
            if ($isDP) {
                $total_pengeluaran -= $row['total'];
            } else {
                $total_pengeluaran += $row['total'];
            }
        }
    }
} else {
    // Handle error jika query gagal
    error_log("Error in rekap keuangan query: " . mysqli_error($conn));
}

$grandTotal = $total_pendapatan - $total_pengeluaran;
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
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h1 class="report-title">Rekapitulasi Arus Kas Keuangan</h1>
                        <p class="report-subtitle">Ikhtisar komprehensif penerimaan, pengeluaran operasional, dan kalkulasi laba/rugi bersih</p>
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
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printReport()" style="border-radius:4px; font-weight:600;">
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
                        <div><i class="fas fa-filter text-muted mr-1"></i> Filter Periode & Cakupan Laporan</div>
                        <a href="rekap_keuangan.php" class="btn btn-xs btn-outline-secondary" style="border-radius:3px;">
                            <i class="fas fa-undo mr-1"></i> Reset Filter
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-0">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label class="filter-label" for="tgl_dari">Tanggal Dari</label>
                                    <input type="date" class="form-control form-control-sm" name="tgl_dari"
                                        id="tgl_dari" value="<?= htmlspecialchars($tgl_dari) ?>" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                </div>
                                <div class="col-md-3">
                                    <label class="filter-label" for="tgl_sampai">Tanggal Sampai</label>
                                    <input type="date" class="form-control form-control-sm" name="tgl_sampai"
                                        id="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="filter-label" for="jenis_laporan">Cakupan Laporan</label>
                                    <select class="form-control form-control-sm" name="jenis_laporan" id="jenis_laporan" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                        <option value="semua" <?= ($jenis_laporan === 'semua') ? 'selected' : '' ?>>Semua (Pendapatan & Pengeluaran)</option>
                                        <option value="pendapatan" <?= ($jenis_laporan === 'pendapatan') ? 'selected' : '' ?>>Hanya Pendapatan</option>
                                        <option value="pengeluaran" <?= ($jenis_laporan === 'pengeluaran') ? 'selected' : '' ?>>Hanya Pengeluaran</option>
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
                            <span class="info-box-icon"><i class="fas fa-arrow-up text-success"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pendapatan</span>
                                <span class="info-box-number text-success">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-arrow-down text-danger"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pengeluaran</span>
                                <span class="info-box-number text-danger">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon"><i class="fas fa-balance-scale <?= $grandTotal >= 0 ? 'text-primary' : 'text-warning' ?>"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Laba / (Rugi) Bersih</span>
                                <span class="info-box-number <?= $grandTotal >= 0 ? 'text-primary' : 'text-danger' ?>">
                                    Rp <?= number_format($grandTotal, 0, ',', '.') ?>
                                </span>
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
                        Tabel Rekapitulasi Arus Kas
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
                                    <th style="width: 120px;">Jenis</th>
                                    <th>Bank / Rekening</th>
                                    <th>Metode / Kategori</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width: 130px;">Jml Transaksi</th>
                                    <th class="text-right" style="width: 170px;">Total Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $subtotal_jenis = [];
                                
                                foreach ($rekap as $row) {
                                    $isDP = !empty($row['is_dp']);
                                    $rowStyle = $isDP ? 'style="background-color: #fff9e6;"' : '';
                                    $statusStyle = $isDP ? 'color: #b78103;' : ($row['jenis'] === 'Pendapatan' ? 'color: #2e7d32;' : 'color: #c92a2a;');
                                    $isPendapatan = ($row['jenis'] === 'Pendapatan');
                                    
                                    echo "<tr $rowStyle>";
                                    echo "<td class='text-center'>" . $no++ . "</td>";
                                    if ($isPendapatan) {
                                        echo "<td><span class='badge' style='background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; font-weight: 600; padding: 4px 8px;'>Pendapatan</span></td>";
                                    } else {
                                        echo "<td><span class='badge' style='background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; font-weight: 600; padding: 4px 8px;'>Pengeluaran</span></td>";
                                    }
                                    echo "<td><strong style='color: #202124;'>" . htmlspecialchars($row['nama_rekening']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['metode_pembayaran']) . "</td>";
                                    echo "<td style='$statusStyle font-weight: 700;'>" . htmlspecialchars($row['status_pembayaran']) . "</td>";
                                    echo "<td class='text-center'>" . number_format($row['transaksi']) . "</td>";
                                    echo "<td class='text-right'><strong style='" . ($isPendapatan ? 'color: #2e7d32;' : 'color: #c92a2a;') . "'>Rp " . number_format($row['total'], 0, ',', '.') . "</strong></td>";
                                    echo "</tr>";
                                    
                                    // Hitung subtotal per jenis
                                    if (!isset($subtotal_jenis[$row['jenis']])) {
                                        $subtotal_jenis[$row['jenis']] = 0;
                                    }
                                    if ($isDP) {
                                        $subtotal_jenis[$row['jenis']] -= $row['total'];
                                    } else {
                                        $subtotal_jenis[$row['jenis']] += $row['total'];
                                    }
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <?php foreach ($subtotal_jenis as $jenis => $total): ?>
                                <tr style="background: #f8f9fa;">
                                    <th colspan="6" class="text-right" style="font-weight: 600;">Subtotal <?= htmlspecialchars($jenis) ?>:</th>
                                    <th class="text-right" style="font-weight: 700; color: <?= $jenis === 'Pendapatan' ? '#2e7d32' : '#c92a2a' ?>;">
                                        Rp <?= number_format($total, 0, ',', '.') ?>
                                    </th>
                                </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <th colspan="6" class="text-right" style="font-weight: 700; font-size: 0.95rem;">GRAND TOTAL (LABA / RUGI BERSIH):</th>
                                    <th class="text-right" style="font-weight: 700; font-size: 1rem; color: <?= $grandTotal >= 0 ? '#1b5e20' : '#c92a2a' ?>;">
                                        Rp <?= number_format($grandTotal, 0, ',', '.') ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p><strong>Keterangan:</strong></p>
                                <ul class="mb-0">
                                    <li>Status DP/Piutang ditandai dengan background kuning</li>
                                    <li>Total sudah dikurangi dengan DP/Piutang</li>
                                    <li>Laba/Rugi = Total Pendapatan - Total Pengeluaran</li>
                                    <li>Laporan ini mencakup semua transaksi keuangan</li>
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
    downloadLink.download = 'Laporan_Rekap_Keuangan_<?= date('Y-m-d') ?>.xls';
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Auto submit form when filter changes
document.getElementById('jenis_laporan').addEventListener('change', function() {
    document.querySelector('form').submit();
});
</script> 
<style>
@media print {
  * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
  body { font-family: Arial, sans-serif !important; font-size: 12px !important; color: #000 !important; background: #fff !important; margin: 0 !important; padding: 0 !important; }
  .printable { display: block !important; }
  .no-print { display: none !important; }
  @page { size: portrait; margin: 15mm 10mm; }
  #printArea { margin: 0 !important; padding: 0 !important; width: 100% !important; }
  #tableRekap { font-size: 12px !important; width: 100% !important; border-collapse: collapse !important; }
  #tableRekap th, #tableRekap td { padding: 6px 10px !important; font-size: 12px !important; white-space: normal !important; color: #000 !important; background: #fff !important; border: 1px solid #000 !important; }
  #tableRekap thead th { background-color: #1f4e79 !important; color: #fff !important; text-align: center !important; }
}
</style>

<script>
function printReport() {
  const nonPrintables = document.querySelectorAll('.no-print');
  nonPrintables.forEach(el => el.style.display = 'none');

  const printArea = document.getElementById('printArea');
  const win = window.open('', '_blank', 'width=800,height=600');
  const html = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Rekap Keuangan - <?= date('d/m/Y') ?></title>
      <meta charset="UTF-8">
      <style>
        @page { size: portrait; margin: 15mm 10mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; background: #fff; margin: 0; padding: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background-color: #1f4e79 !important; color: #fff !important; text-align: center; }
        .print-header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 8px; }
        .print-header h1 { font-size: 18px; margin: 0 0 5px 0; }
        .print-header h2 { font-size: 14px; margin: 0 0 8px 0; }
        .periode { font-size: 11px; color: #333; }
      </style>
    </head>
    <body>
      ${printArea.innerHTML}
    </body>
    </html>
  `;
  win.document.write(html);
  win.document.close();
  win.onload = function(){
    win.focus();
    win.print();
    setTimeout(function(){ win.close(); nonPrintables.forEach(el => el.style.display = ''); }, 800);
  };
  setTimeout(function(){
    if (!win.closed) {
      win.focus(); win.print();
      setTimeout(function(){ win.close(); nonPrintables.forEach(el => el.style.display = ''); }, 800);
    }
  }, 1500);
}
</script> 