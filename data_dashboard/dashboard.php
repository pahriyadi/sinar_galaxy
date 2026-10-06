<?php
session_start();
require_once '../assets/session.php';
require_once '../inc/koneksi.php';
require_once '../assets/fungsi.php';
require_once '../assets/logging_helper.php';
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';

// Log dashboard access
logDashboardAccess();


$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$idUsers = isset($user['id_users']) ? (int)$user['id_users'] : 0;
$asalPo = isset($user['asal_po']) ? (string)$user['asal_po'] : '';
$role = isset($user['role']) ? (string)$user['role'] : '';

// Filter tanggal: default bulan berjalan
$tglDari = isset($_GET['tgl_dari']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_dari']) ? $_GET['tgl_dari'] : date('Y-m-01');
$tglSampai = isset($_GET['tgl_sampai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_sampai']) ? $_GET['tgl_sampai'] : date('Y-m-d');
if ($tglDari > $tglSampai) { $tmp = $tglDari; $tglDari = $tglSampai; $tglSampai = $tmp; }
$start = $tglDari; $end = $tglSampai;
$today = date('Y-m-d');

// Info zona waktu dan lokasi IP (best-effort)
$phpTimezone = date_default_timezone_get();
$nowLocal = date('Y-m-d H:i:s');
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
// Deteksi lokasi IP (opsional, tanpa API eksternal). Jika ada header Cloudflare/Reverse proxy, gunakan kota jika tersedia.
$cityHeader = $_SERVER['HTTP_CF_IPCITY'] ?? $_SERVER['HTTP_X_APPENGINE_CITY'] ?? '';
$regionHeader = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';

// Referensi
$rekeningMap = [];
$res = $conn->query("SELECT id_rekening, nama_rekening FROM data_rekening");
if ($res) while ($r = $res->fetch_assoc()) $rekeningMap[(int)$r['id_rekening']] = $r['nama_rekening'];
$tujuanMap = [];
$res = $conn->query("SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
if ($res) while ($r = $res->fetch_assoc()) $tujuanMap[(string)$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];
$statusMap = [];
$res = $conn->query("SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
if ($res) while ($r = $res->fetch_assoc()) $statusMap[(string)$r['id_status_pembayaran']] = strtolower($r['status_pembayaran']);

// Map user id -> username
$userNameMap = [];
$res = $conn->query("SELECT id_users, username FROM data_users");
if ($res) while ($r = $res->fetch_assoc()) $userNameMap[(int)$r['id_users']] = $r['username'];

// Penampung agregasi
$totalPendapatan = 0; $totalPendapatanHariIni = 0;
$bankTotals = []; $travelTotals = []; $lokasiTotals = []; $userTotals = [];
$pengeluaranKantor = 0; $pengeluaranTravel = 0; $pengeluaranHariIni = 0; $pengeluaranByBank = []; $pengeluaranUserTotals = [];

// Helper: cek dalam rentang
function inRange($dateYmd, $start, $end) {
  if (!$dateYmd) return false; $d = substr($dateYmd,0,10); return ($d >= $start && $d <= $end);
}

// Build where role restrictions
$wherePem = ' WHERE 1=1';
if ($role === 'admin' || $role === 'users') {
  $wherePem .= " AND (username = '".intval($idUsers)."' OR asal_po = '".intval($idUsers)."')";
}
$sqlPem = "SELECT id_pemesanan, nama_id, tanggal_pemesanan, tanggal_berangkat, no_plat_id, tujuan_id, harga_id, jenis_rekening_id, username, payment_methods, status_pembayaran_id FROM data_pemesanan".$wherePem;
$rsPem = $conn->query($sqlPem);
if ($rsPem) {
  while ($row = $rsPem->fetch_assoc()) {
    $nama = $row['nama_id'] ?? '-';
    $plat = $row['no_plat_id'] ?? '';
    $tujuanId = (string)($row['tujuan_id'] ?? '');
    $usr = (int)($row['username'] ?? 0);
    $statusLunas = isset($row['status_pembayaran_id']) && (strpos($statusMap[$row['status_pembayaran_id']] ?? '', 'lunas') !== false);
    if (!empty($row['payment_methods'])) {
      $arr = json_decode($row['payment_methods'], true);
      if (is_array($arr)) {
        foreach ($arr as $p) {
          $rek = (int)($p['rekening_id'] ?? 0);
          $amt = (float)($p['amount'] ?? 0);
          $payDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : substr($row['tanggal_pemesanan'] ?? '',0,10);
          if ($amt > 0 && inRange($payDate, $start, $end)) {
            $totalPendapatan += $amt; if ($payDate === $today) $totalPendapatanHariIni += $amt;
            $bankTotals[$rek] = ($bankTotals[$rek] ?? 0) + $amt;
            if ($plat) $travelTotals[$plat] = ($travelTotals[$plat] ?? 0) + $amt;
            if ($tujuanId) $lokasiTotals[$tujuanId] = ($lokasiTotals[$tujuanId] ?? 0) + $amt;
            if ($usr) $userTotals[$usr] = ($userTotals[$usr] ?? 0) + $amt;
          }
        }
      }
    } else {
      // Fallback single: hitung hanya jika status lunas dan tanggal dalam range
      $rek = (int)($row['jenis_rekening_id'] ?? 0);
      $payDate = substr($row['tanggal_pemesanan'] ?? '', 0, 10);
      $amt = (float)($row['harga_id'] ?? 0);
      if ($statusLunas && $amt > 0 && inRange($payDate, $start, $end)) {
        $totalPendapatan += $amt; if ($payDate === $today) $totalPendapatanHariIni += $amt;
        $bankTotals[$rek] = ($bankTotals[$rek] ?? 0) + $amt;
        if ($plat) $travelTotals[$plat] = ($travelTotals[$plat] ?? 0) + $amt;
        if ($tujuanId) $lokasiTotals[$tujuanId] = ($lokasiTotals[$tujuanId] ?? 0) + $amt;
        if ($usr) $userTotals[$usr] = ($userTotals[$usr] ?? 0) + $amt;
      }
    }
  }
}

$wherePkg = ' WHERE 1=1';
if ($role === 'admin' || $role === 'users') {
  $wherePkg .= " AND user_id = '".intval($idUsers)."' AND asal_po_id = '".$conn->real_escape_string($asalPo)."'";
}
$sqlPkg = "SELECT id_pengiriman, nama_id, tanggal_pengiriman, no_plat_id, tujuan_id, jumlah, jenis_rekening_id, user_id, payment_methods, status_pembayaran_id FROM data_pengiriman".$wherePkg;
$rsPkg = $conn->query($sqlPkg);
if ($rsPkg) {
  while ($row = $rsPkg->fetch_assoc()) {
    $plat = $row['no_plat_id'] ?? '';
    $tujuanId = (string)($row['tujuan_id'] ?? '');
    $usr = (int)($row['user_id'] ?? 0);
    $statusLunas = isset($row['status_pembayaran_id']) && (strpos($statusMap[$row['status_pembayaran_id']] ?? '', 'lunas') !== false);
    if (!empty($row['payment_methods'])) {
      $arr = json_decode($row['payment_methods'], true);
      if (is_array($arr)) {
        foreach ($arr as $p) {
          $rek = (int)($p['rekening_id'] ?? 0);
          $amt = (float)($p['amount'] ?? 0);
          $payDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : substr($row['tanggal_pengiriman'] ?? '',0,10);
          if ($amt > 0 && inRange($payDate, $start, $end)) {
            $totalPendapatan += $amt; if ($payDate === $today) $totalPendapatanHariIni += $amt;
            $bankTotals[$rek] = ($bankTotals[$rek] ?? 0) + $amt;
            if ($plat) $travelTotals[$plat] = ($travelTotals[$plat] ?? 0) + $amt;
            if ($tujuanId) $lokasiTotals[$tujuanId] = ($lokasiTotals[$tujuanId] ?? 0) + $amt;
            if ($usr) $userTotals[$usr] = ($userTotals[$usr] ?? 0) + $amt;
          }
        }
      }
    } else {
      // Fallback single: hitung hanya jika status lunas dan tanggal dalam range
      $rek = (int)($row['jenis_rekening_id'] ?? 0);
      $payDate = substr($row['tanggal_pengiriman'] ?? '', 0, 10);
      $amt = (float)($row['jumlah'] ?? 0);
      if ($statusLunas && $amt > 0 && inRange($payDate, $start, $end)) {
        $totalPendapatan += $amt; if ($payDate === $today) $totalPendapatanHariIni += $amt;
        $bankTotals[$rek] = ($bankTotals[$rek] ?? 0) + $amt;
        if ($plat) $travelTotals[$plat] = ($travelTotals[$plat] ?? 0) + $amt;
        if ($tujuanId) $lokasiTotals[$tujuanId] = ($lokasiTotals[$tujuanId] ?? 0) + $amt;
        if ($usr) $userTotals[$usr] = ($userTotals[$usr] ?? 0) + $amt;
      }
    }
  }
}

// Pengeluaran (hanya LUNAS)
$whereOut = ' WHERE 1=1';
if ($role === 'admin' || $role === 'users') {
  $whereOut .= " AND user_id = '".intval($idUsers)."' AND asal_po = '".$conn->real_escape_string($asalPo)."'";
}
$sqlOut = "SELECT tanggal_pengeluaran, kategori_pengeluaran, harga_operasional, status_pembayaran_id, jenis_rekening_id, user_id FROM data_pengeluaran".$whereOut;
$rsOut = $conn->query($sqlOut);
if ($rsOut) {
  while ($row = $rsOut->fetch_assoc()) {
    $statusLunas = isset($row['status_pembayaran_id']) && (strpos($statusMap[$row['status_pembayaran_id']] ?? '', 'lunas') !== false);
    if (!$statusLunas) continue;
    $tgl = substr($row['tanggal_pengeluaran'] ?? '', 0, 10);
    $amt = (float)($row['harga_operasional'] ?? 0);
    if ($amt > 0 && inRange($tgl, $start, $end)) {
      if (($row['kategori_pengeluaran'] ?? 'travel') === 'kantor') $pengeluaranKantor += $amt; else $pengeluaranTravel += $amt;
      $rekId = (int)($row['jenis_rekening_id'] ?? 0);
      if ($rekId) { $pengeluaranByBank[$rekId] = ($pengeluaranByBank[$rekId] ?? 0) + $amt; }
      $uidOut = (int)($row['user_id'] ?? 0);
      if ($uidOut) { $pengeluaranUserTotals[$uidOut] = ($pengeluaranUserTotals[$uidOut] ?? 0) + $amt; }
      if ($tgl === $today) $pengeluaranHariIni += $amt;
    }
  }
}

$rekapDebit = $totalPendapatan; $rekapKredit = $pengeluaranKantor + $pengeluaranTravel; $rekapNet = $rekapDebit - $rekapKredit;

// Siapkan data bank terurut untuk chart/infobox (top-N)
$bankPairs = [];
foreach ($bankTotals as $id=>$val) { $bankPairs[] = ['id'=>(int)$id, 'val'=>(float)$val]; }
usort($bankPairs, function($a,$b){ return $b['val'] <=> $a['val']; });
$bankBoxesTop = array_slice($bankPairs, 0, 6);
$bankChartTop = array_slice($bankPairs, 0, 10);
$bankChartLabels = array_map(function($p) use ($rekeningMap){
  $id = $p['id']; return $rekeningMap[$id] ?? ('Rek #'.$id);
}, $bankChartTop);
$bankChartData = array_map(function($p){ return (float)$p['val']; }, $bankChartTop);

// Daftar keberangkatan harian (pemesanan hari ini)
$keberangkatanHarian = [];
$whereHar = ' WHERE DATE(tanggal_berangkat) = \''.$today.'\'';
if ($role === 'admin' || $role === 'users') {
  $whereHar .= " AND username = '".intval($idUsers)."' AND asal_po = '".$conn->real_escape_string($asalPo)."'";
}
$sqlHar = "SELECT nama_id, no_plat_id, tujuan_id, kursi, tanggal_berangkat FROM data_pemesanan".$whereHar." ORDER BY tanggal_berangkat ASC LIMIT 12";
$rsHar = $conn->query($sqlHar);
if ($rsHar) while ($r = $rsHar->fetch_assoc()) $keberangkatanHarian[] = $r;

// Statistik pemesanan periode (jumlah order)
$wherePemCnt = ' WHERE 1=1';
if ($role === 'admin' || $role === 'users') {
  $wherePemCnt .= " AND username = '".intval($idUsers)."' AND asal_po = '".$conn->real_escape_string($asalPo)."'";
}
$wherePemCnt .= " AND DATE(tanggal_pemesanan) >= '".$conn->real_escape_string($start)."' AND DATE(tanggal_pemesanan) <= '".$conn->real_escape_string($end)."'";
$countPemesanan = 0; $resCnt = $conn->query("SELECT COUNT(*) as c FROM data_pemesanan".$wherePemCnt);
if ($resCnt && ($row=$resCnt->fetch_assoc())) $countPemesanan = (int)$row['c'];

// User online / offline (window 5 menit)
$totalUsers = 0; $onlineUsers = 0; $offlineUsers = 0;
$resTU = $conn->query("SELECT COUNT(*) c FROM data_users");
if ($resTU && ($row=$resTU->fetch_assoc())) $totalUsers = (int)$row['c'];
$resOU = $conn->query("SELECT COUNT(*) c FROM data_users WHERE last_activity IS NOT NULL AND last_activity >= (NOW() - INTERVAL 5 MINUTE)");
if ($resOU && ($row=$resOU->fetch_assoc())) $onlineUsers = (int)$row['c'];
$offlineUsers = max(0, $totalUsers - $onlineUsers);

// Daftar user online (5 menit terakhir)
$onlineUsersList = [];
$resList = $conn->query("SELECT id_users, username, asal_po, role, last_activity FROM data_users WHERE last_activity IS NOT NULL AND last_activity >= (NOW() - INTERVAL 5 MINUTE) ORDER BY last_activity DESC");
if ($resList) while ($r = $resList->fetch_assoc()) $onlineUsersList[] = $r;

// Saldo Awal (agregat semua rekening untuk bulan tglDari) dan Saldo Akhir (agregat)
$bulanFilter = (int)date('n', strtotime($start));
$tahunFilter = (int)date('Y', strtotime($start));
$saldoAwalByBank = [];
$stmtSA = $conn->prepare("SELECT id_rekening, saldo_awal FROM saldo_awal_bank WHERE bulan=? AND tahun=?");
$stmtSA->bind_param('ii', $bulanFilter, $tahunFilter);
$stmtSA->execute(); $rsSA = $stmtSA->get_result();
if ($rsSA) { while ($row = $rsSA->fetch_assoc()) { $saldoAwalByBank[(int)$row['id_rekening']] = (float)$row['saldo_awal']; } }
$stmtSA->close();

// Transfer internal (periode)
$transferCount = 0; $transferSum = 0.0;
$stmtTf = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(jumlah),0) AS s FROM internal_transfers WHERE tanggal >= ? AND tanggal <= ?");
$stmtTf->bind_param('ss', $start, $end);
$stmtTf->execute(); $rsTf2 = $stmtTf->get_result();
if ($rsTf2 && ($row=$rsTf2->fetch_assoc())) { $transferCount = (int)$row['c']; $transferSum = (float)$row['s']; }
$stmtTf->close();

// Transfer internal per bank (in/out)
$tfInByBank = []; $tfOutByBank = [];
$stmtTfx = $conn->prepare("SELECT dari_rekening_id, ke_rekening_id, jumlah FROM internal_transfers WHERE tanggal >= ? AND tanggal <= ?");
$stmtTfx->bind_param('ss', $start, $end);
$stmtTfx->execute(); $rsTfx = $stmtTfx->get_result();
if ($rsTfx) {
  while ($row = $rsTfx->fetch_assoc()) {
    $dr = (int)$row['dari_rekening_id']; $kr = (int)$row['ke_rekening_id']; $j = (float)$row['jumlah'];
    if ($kr) { $tfInByBank[$kr] = ($tfInByBank[$kr] ?? 0) + $j; }
    if ($dr) { $tfOutByBank[$dr] = ($tfOutByBank[$dr] ?? 0) + $j; }
  }
}
$stmtTfx->close();

// Hitung saldo akhir per bank
$saldoAkhirByBank = [];
// incomeByBank = $bankTotals
foreach ($rekeningMap as $rid => $nama) {
  $rid = (int)$rid;
  $awal = (float)($saldoAwalByBank[$rid] ?? 0);
  $in = (float)($bankTotals[$rid] ?? 0) + (float)($tfInByBank[$rid] ?? 0);
  $out = (float)($pengeluaranByBank[$rid] ?? 0) + (float)($tfOutByBank[$rid] ?? 0);
  $saldoAkhirByBank[$rid] = $awal + $in - $out;
}

// Pendapatan per bulan (6 bulan terakhir)
$bulanLabels = []; $bulanTotals = [];
for ($i = 5; $i >= 0; $i--) {
  $ym = date('Y-m', strtotime("-{$i} months"));
  $bulanLabels[] = date('M Y', strtotime($ym.'-01'));
  $mStart = $ym.'-01'; $mEnd = date('Y-m-t', strtotime($mStart));
  $sum = 0;
  // Scan pemesanan
  $rs = $conn->query($sqlPem); // reuse (berpotensi lebih dari perlu, tapi sederhana)
  if ($rs) {
    while ($row = $rs->fetch_assoc()) {
      $statusLunas = isset($row['status_pembayaran_id']) && (strpos($statusMap[$row['status_pembayaran_id']] ?? '', 'lunas') !== false);
      if (!empty($row['payment_methods'])) {
        $arr = json_decode($row['payment_methods'], true);
        if (is_array($arr)) foreach ($arr as $p) {
          $amt = (float)($p['amount'] ?? 0); $pd = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : substr($row['tanggal_pemesanan'] ?? '',0,10);
          if ($amt > 0 && inRange($pd, $mStart, $mEnd)) $sum += $amt;
        }
      } else {
        $amt = (float)($row['harga_id'] ?? 0); $pd = substr($row['tanggal_pemesanan'] ?? '', 0, 10);
        if ($statusLunas && $amt > 0 && inRange($pd, $mStart, $mEnd)) $sum += $amt;
      }
    }
  }
  // Scan pengiriman
  $rs2 = $conn->query($sqlPkg);
  if ($rs2) {
    while ($row = $rs2->fetch_assoc()) {
      $statusLunas = isset($row['status_pembayaran_id']) && (strpos($statusMap[$row['status_pembayaran_id']] ?? '', 'lunas') !== false);
      if (!empty($row['payment_methods'])) {
        $arr = json_decode($row['payment_methods'], true);
        if (is_array($arr)) foreach ($arr as $p) {
          $amt = (float)($p['amount'] ?? 0); $pd = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : substr($row['tanggal_pengiriman'] ?? '',0,10);
          if ($amt > 0 && inRange($pd, $mStart, $mEnd)) $sum += $amt;
        }
      } else {
        $amt = (float)($row['jumlah'] ?? 0); $pd = substr($row['tanggal_pengiriman'] ?? '', 0, 10);
        if ($statusLunas && $amt > 0 && inRange($pd, $mStart, $mEnd)) $sum += $amt;
      }
    }
  }
  $bulanTotals[] = $sum;
}
?>

<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header pb-2">
    <div class="container-fluid">
      <div class="row align-items-center mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.35rem; letter-spacing: -0.3px;">
            <i class="fas fa-tachometer-alt text-success mr-2"></i>Dashboard Utama
          </h1>
        </div>
        <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
          <div class="d-inline-flex flex-column align-items-sm-end text-sm">
            <span class="text-muted"><i class="far fa-clock mr-1"></i><?= htmlspecialchars($nowLocal) ?> WITA (<?= htmlspecialchars($phpTimezone) ?>)</span>
            <?php if ($clientIp): ?>
            <span class="text-muted"><i class="fas fa-map-marker-alt mr-1"></i>IP: <strong><?= htmlspecialchars($clientIp) ?></strong><?= $cityHeader ? ' &bull; '.htmlspecialchars($cityHeader) : '' ?><?= $regionHeader ? ' ('.htmlspecialchars($regionHeader).')' : '' ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Welcome Banner & Quick Actions -->
      <div class="card card-outline card-success mb-3" style="border-top-width: 3px;">
        <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
          <div class="d-flex align-items-center mb-2 mb-md-0">
            <img src="../<?= htmlspecialchars(getSetting('app_logo', 'img/logo_sgt.png')) ?>" alt="<?= htmlspecialchars(getSetting('app_name', 'Sinar Galaxy')) ?>" style="height: 38px; width: auto; margin-right: 14px;" class="img-thumbnail p-1">
            <div>
              <div class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                Selamat datang kembali, <?= htmlspecialchars($user['username'] ?? 'User') ?>!
              </div>
              <div class="text-muted text-sm">
                Cabang/PO: <strong class="text-dark"><?= htmlspecialchars($asalPo) ?></strong> &bull; Hak Akses: <span class="badge badge-success text-uppercase font-weight-normal px-2 py-1"><?= htmlspecialchars($role) ?></span>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center flex-wrap">
            <?php
              $dbStatusText = 'OK';
              $dbLatencyMs = null;
              $t0 = microtime(true);
              $rsPing = $conn->query('SELECT 1');
              $t1 = microtime(true);
              if (!$rsPing) {
                $dbStatusText = 'ERROR: '.$conn->error;
              } else {
                $dbLatencyMs = round(($t1 - $t0) * 1000);
              }
            ?>
            <div class="mr-3 mb-1 mb-md-0 text-sm">
              <span class="badge <?= ($dbLatencyMs!==null && $dbLatencyMs<=150) ? 'badge-success' : (($dbLatencyMs!==null && $dbLatencyMs<=400)?'badge-warning':'badge-danger') ?> px-2 py-1 font-weight-normal">
                <i class="fas fa-database mr-1"></i> DB: <?= htmlspecialchars($dbStatusText) ?> (<?= (int)$dbLatencyMs ?> ms)
              </span>
            </div>
            <div class="btn-group">
              <button id="btnClearBrowserCache" class="btn btn-sm btn-outline-secondary" title="Bersihkan cache browser & storage lokal">
                <i class="fas fa-broom mr-1"></i> Cache Browser
              </button>
              <?php if ($role === 'super admin'): ?>
              <button id="btnClearServerCache" class="btn btn-sm btn-outline-secondary" title="Reset OPcache server">
                <i class="fas fa-server mr-1"></i> Cache Server
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <style>
      @keyframes skeletonAnim { 0%{background-position:100% 0} 100%{background-position:0 0} }
      .metric-card {
        background: #ffffff;
        border: 1px solid #b8b8b8;
        border-radius: 4px;
        padding: 16px 18px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        transition: border-color .15s ease-in-out;
      }
      .metric-card:hover {
        border-color: #0d9f4f;
      }
      .metric-card .metric-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 8px;
      }
      .metric-card .metric-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      .metric-card .metric-icon {
        width: 38px;
        height: 38px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
      }
      .metric-card .metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
        margin-bottom: 4px;
      }
      .metric-card .metric-sub {
        font-size: 0.8125rem;
        color: #6b7280;
      }
      </style>

      <script>
      (function(){
        function toast(msg, type){
          var bg = type==='success' ? '#0d9f4f' : (type==='danger' ? '#dc3545' : '#0284c7');
          var n = document.createElement('div');
          n.style.cssText='position:fixed;top:20px;right:20px;z-index:99999;min-width:260px;max-width:360px;padding:12px 16px;border-radius:4px;color:#fff;background:'+bg+';border:1px solid rgba(0,0,0,0.1);font-family:inherit;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);';
          n.textContent = msg; document.body.appendChild(n);
          setTimeout(function(){n.style.transition='opacity .4s';n.style.opacity='0';},1600);
          setTimeout(function(){n.remove();},2000);
        }
        var btnB = document.getElementById('btnClearBrowserCache');
        if (btnB) btnB.addEventListener('click', function(){
          try {
            if ('caches' in window) { caches.keys().then(function(keys){ keys.forEach(function(k){ caches.delete(k); }); }); }
            if (window.localStorage) { localStorage.clear(); }
            if (window.sessionStorage) { sessionStorage.clear(); }
          } catch(e){}
          var url = window.location.pathname + window.location.search + (window.location.search ? '&' : '?') + 'cb=' + Date.now();
          toast('Cache browser dibersihkan', 'success');
          setTimeout(function(){ window.location.replace(url); }, 500);
        });
        var btnS = document.getElementById('btnClearServerCache');
        if (btnS) btnS.addEventListener('click', function(){
          btnS.disabled=true; btnS.innerHTML='<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...';
          fetch('../assets/tools/clear_server_cache.php').then(function(r){return r.json();}).then(function(d){
            if (d && d.ok) toast('Cache server dibersihkan', 'success'); else toast('Gagal clear cache server','danger');
          }).catch(function(){ toast('Terjadi kesalahan','danger'); }).finally(function(){
            btnS.disabled=false; btnS.innerHTML='<i class="fas fa-server mr-1"></i> Cache Server';
          });
        });
      })();

      window.addEventListener('load', function(){
        function hideSkeleton(idSkeleton, idCanvas){
          var sk = document.getElementById(idSkeleton);
          var cv = document.getElementById(idCanvas);
          if (sk && cv) { sk.style.display='none'; cv.style.visibility='visible'; }
        }
        hideSkeleton('skeletonPing','chartPing');
        hideSkeleton('skeletonNet','chartNetInfo');
        setTimeout(function(){
          var sL = document.getElementById('skeletonLokasi'); if (sL) sL.style.display='none';
          var sU = document.getElementById('skeletonUser'); if (sU) sU.style.display='none';
        }, 200);
      });
      </script>

      <!-- BAR FILTER HORISONTAL -->
      <div class="card mb-3">
        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
          <span class="font-weight-bold text-dark text-sm"><i class="fas fa-calendar-alt text-success mr-2"></i>Filter Rentang Periode Laporan</span>
          <span class="badge badge-secondary font-weight-normal"><?= htmlspecialchars(date('d M Y', strtotime($tglDari))) ?> s.d. <?= htmlspecialchars(date('d M Y', strtotime($tglSampai))) ?></span>
        </div>
        <div class="card-body p-3">
          <form method="get" id="filterForm" class="row align-items-end">
            <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
              <label class="text-sm font-weight-bold mb-1 text-muted">Dari Tanggal:</label>
              <input type="date" id="inputTglDari" name="tgl_dari" value="<?= htmlspecialchars($tglDari) ?>" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
              <label class="text-sm font-weight-bold mb-1 text-muted">Sampai Tanggal:</label>
              <input type="date" id="inputTglSampai" name="tgl_sampai" value="<?= htmlspecialchars($tglSampai) ?>" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6 col-sm-12 d-flex flex-wrap align-items-center pt-2 pt-md-0">
              <button class="btn btn-sm btn-success mr-2 font-weight-bold px-3" type="submit">
                <i class="fas fa-search mr-1"></i> Tampilkan
              </button>
              <a class="btn btn-sm btn-outline-secondary mr-3" href="dashboard.php">
                <i class="fas fa-undo mr-1"></i> Reset
              </a>
              <div class="btn-group btn-group-sm mt-1 mt-sm-0">
                <button type="button" class="btn btn-outline-dark" onclick="setPresetRange('today')">Hari Ini</button>
                <button type="button" class="btn btn-outline-dark" onclick="setPresetRange('month')">Bulan Ini</button>
                <button type="button" class="btn btn-outline-dark" onclick="setPresetRange('30days')">30 Hari</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <script>
      function setPresetRange(type){
        var dari = document.getElementById('inputTglDari');
        var sampai = document.getElementById('inputTglSampai');
        var now = new Date();
        var yyyy = now.getFullYear();
        var mm = String(now.getMonth() + 1).padStart(2, '0');
        var dd = String(now.getDate()).padStart(2, '0');
        var todayStr = yyyy + '-' + mm + '-' + dd;
        if(type === 'today'){
          dari.value = todayStr;
          sampai.value = todayStr;
        } else if(type === 'month'){
          dari.value = yyyy + '-' + mm + '-01';
          var lastDay = new Date(yyyy, now.getMonth() + 1, 0).getDate();
          sampai.value = yyyy + '-' + mm + '-' + String(lastDay).padStart(2, '0');
        } else if(type === '30days'){
          var prev = new Date();
          prev.setDate(prev.getDate() - 30);
          var py = prev.getFullYear();
          var pm = String(prev.getMonth() + 1).padStart(2, '0');
          var pd = String(prev.getDate()).padStart(2, '0');
          dari.value = py + '-' + pm + '-' + pd;
          sampai.value = todayStr;
        }
        document.getElementById('filterForm').submit();
      }
      </script>

      <!-- BARIS 1: 4 KARTU KPI FINANSIAL UTAMA -->
      <div class="row mb-3">
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card" style="border-top: 3px solid #0d9f4f;">
            <div class="metric-header">
              <span class="metric-title">Pendapatan (Periode)</span>
              <div class="metric-icon" style="background:#eaf8ef; color:#0d9f4f;">
                <i class="fas fa-hand-holding-usd"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($totalPendapatan,0,',','.') ?></div>
            <div class="metric-sub"><i class="fas fa-check-circle text-success mr-1"></i>Total kas masuk dari tiket & kargo</div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card" style="border-top: 3px solid #0284c7;">
            <div class="metric-header">
              <span class="metric-title">Pendapatan Hari Ini</span>
              <div class="metric-icon" style="background:#e0f2fe; color:#0284c7;">
                <i class="fas fa-calendar-day"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($totalPendapatanHariIni,0,',','.') ?></div>
            <div class="metric-sub"><i class="far fa-clock text-info mr-1"></i>Kas masuk per tanggal <?= htmlspecialchars(date('d M Y')) ?></div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card" style="border-top: 3px solid #dc2626;">
            <div class="metric-header">
              <span class="metric-title">Total Pengeluaran</span>
              <div class="metric-icon" style="background:#fee2e2; color:#dc2626;">
                <i class="fas fa-receipt"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($pengeluaranKantor + $pengeluaranTravel,0,',','.') ?></div>
            <div class="metric-sub">Kantor: <strong>Rp <?= number_format($pengeluaranKantor,0,',','.') ?></strong> | Travel: <strong>Rp <?= number_format($pengeluaranTravel,0,',','.') ?></strong></div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
          <div class="metric-card" style="border-top: 3px solid <?= $rekapNet >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
            <div class="metric-header">
              <span class="metric-title">Laba Bersih (Net)</span>
              <div class="metric-icon" style="background:<?= $rekapNet >= 0 ? '#eaf8ef' : '#fee2e2' ?>; color:<?= $rekapNet >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
                <i class="fas fa-balance-scale"></i>
              </div>
            </div>
            <div class="metric-value" style="color:<?= $rekapNet >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
              Rp <?= number_format($rekapNet,0,',','.') ?>
            </div>
            <div class="metric-sub">Selisih total pendapatan & operasional</div>
          </div>
        </div>
      </div>

      <!-- BARIS 2: 4 KARTU OPERASIONAL & TIKET -->
      <div class="row mb-3">
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card">
            <div class="metric-header">
              <span class="metric-title">Pemesanan Tiket</span>
              <div class="metric-icon" style="background:#f3f4f6; color:#4b5563;">
                <i class="fas fa-ticket-alt"></i>
              </div>
            </div>
            <div class="metric-value"><?= number_format($countPemesanan) ?> <small class="text-muted text-sm font-weight-normal">Tiket</small></div>
            <div class="metric-sub">Transaksi periode terpilih</div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card">
            <div class="metric-header">
              <span class="metric-title">Transfer Internal</span>
              <div class="metric-icon" style="background:#f3f4f6; color:#4b5563;">
                <i class="fas fa-exchange-alt"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($transferSum,0,',','.') ?></div>
            <div class="metric-sub"><?= number_format($transferCount) ?> transaksi mutasi antar rekening</div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card">
            <div class="metric-header">
              <span class="metric-title">Petugas Online</span>
              <div class="metric-icon" style="background:#eaf8ef; color:#0d9f4f;">
                <i class="fas fa-user-check"></i>
              </div>
            </div>
            <div class="metric-value"><?= number_format($onlineUsers) ?> <small class="text-muted text-sm font-weight-normal">User</small></div>
            <div class="metric-sub"><span class="badge badge-success font-weight-normal px-2 py-1"><i class="fas fa-circle text-xs mr-1"></i>Aktif 5 menit terakhir</span></div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
          <div class="metric-card">
            <div class="metric-header">
              <span class="metric-title">Petugas Offline</span>
              <div class="metric-icon" style="background:#f3f4f6; color:#6b7280;">
                <i class="fas fa-user-clock"></i>
              </div>
            </div>
            <div class="metric-value"><?= number_format($offlineUsers) ?> <small class="text-muted text-sm font-weight-normal">User</small></div>
            <div class="metric-sub">Dari total <?= number_format($totalUsers) ?> akun terdaftar</div>
          </div>
        </div>
      </div>

      <!-- BARIS 3: TABEL POSISI SALDO & MUTASI REKENING BANK / KASIR -->
      <div class="card mb-4">
        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
          <h3 class="card-title font-weight-bold text-dark text-sm m-0">
            <i class="fas fa-university text-success mr-2"></i>Posisi Saldo & Mutasi Rekening / Kasir (Periode Ini)
          </h3>
          <div>
            <a href="../data_saldo_awal/data_saldo_awal_view.php" class="btn btn-xs btn-outline-secondary mr-1">
              <i class="fas fa-edit mr-1"></i> Setting Saldo Awal
            </a>
            <a href="../data_transfer/internal_transfer_view.php" class="btn btn-xs btn-outline-secondary">
              <i class="fas fa-exchange-alt mr-1"></i> Mutasi Transfer
            </a>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
              <thead>
                <tr class="bg-light text-center">
                  <th style="width: 40px;">No</th>
                  <th class="text-left">Nama Rekening / Kasir</th>
                  <th class="text-right">Saldo Awal</th>
                  <th class="text-right">Pendapatan Masuk</th>
                  <th class="text-right">Transfer Masuk</th>
                  <th class="text-right">Transfer Keluar</th>
                  <th class="text-right">Pengeluaran</th>
                  <th class="text-right" style="background-color: #f0f7f3;">Saldo Akhir</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $sumAwal = 0; $sumMasuk = 0; $sumTfIn = 0; $sumTfOut = 0; $sumKeluar = 0; $sumAkhir = 0;
                  $noRek = 0;
                  foreach ($rekeningMap as $rid => $rname):
                    $rid = (int)$rid;
                    $noRek++;
                    $vAwal = (float)($saldoAwalByBank[$rid] ?? 0);
                    $vMasuk = (float)($bankTotals[$rid] ?? 0);
                    $vTfIn = (float)($tfInByBank[$rid] ?? 0);
                    $vTfOut = (float)($tfOutByBank[$rid] ?? 0);
                    $vKeluar = (float)($pengeluaranByBank[$rid] ?? 0);
                    $vAkhir = (float)($saldoAkhirByBank[$rid] ?? ($vAwal + $vMasuk + $vTfIn - $vTfOut - $vKeluar));

                    $sumAwal += $vAwal;
                    $sumMasuk += $vMasuk;
                    $sumTfIn += $vTfIn;
                    $sumTfOut += $vTfOut;
                    $sumKeluar += $vKeluar;
                    $sumAkhir += $vAkhir;
                ?>
                <tr>
                  <td class="text-center font-weight-bold"><?= $noRek ?></td>
                  <td class="font-weight-bold text-dark">
                    <i class="fas fa-wallet text-muted mr-1"></i><?= htmlspecialchars($rname) ?>
                  </td>
                  <td class="text-right text-muted">Rp <?= number_format($vAwal,0,',','.') ?></td>
                  <td class="text-right text-success">+Rp <?= number_format($vMasuk,0,',','.') ?></td>
                  <td class="text-right text-info">+Rp <?= number_format($vTfIn,0,',','.') ?></td>
                  <td class="text-right text-warning">-Rp <?= number_format($vTfOut,0,',','.') ?></td>
                  <td class="text-right text-danger">-Rp <?= number_format($vKeluar,0,',','.') ?></td>
                  <td class="text-right font-weight-bold" style="background-color: #fbfdfc; color: <?= $vAkhir >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
                    Rp <?= number_format($vAkhir,0,',','.') ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($noRek === 0): ?>
                <tr>
                  <td colspan="8" class="text-center text-muted py-3">Belum ada data rekening atau kasir aktif.</td>
                </tr>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr class="font-weight-bold bg-light" style="border-top: 2px solid #a0a0a0;">
                  <td colspan="2" class="text-center text-uppercase">TOTAL REKAPITULASI</td>
                  <td class="text-right">Rp <?= number_format($sumAwal,0,',','.') ?></td>
                  <td class="text-right text-success">+Rp <?= number_format($sumMasuk,0,',','.') ?></td>
                  <td class="text-right text-info">+Rp <?= number_format($sumTfIn,0,',','.') ?></td>
                  <td class="text-right text-warning">-Rp <?= number_format($sumTfOut,0,',','.') ?></td>
                  <td class="text-right text-danger">-Rp <?= number_format($sumKeluar,0,',','.') ?></td>
                  <td class="text-right" style="background-color: #f0f7f3; color: <?= $sumAkhir >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
                    Rp <?= number_format($sumAkhir,0,',','.') ?>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <!-- BARIS 4: GRAFIK BULANAN & BREAKDOWN PER BANK / TRAVEL -->
      <div class="row mb-3">
        <!-- Kolom Kiri: Tren Pendapatan 6 Bulan -->
        <div class="col-lg-7 mb-3 mb-lg-0">
          <div class="card h-100">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-chart-bar text-success mr-2"></i>Tren Pendapatan 6 Bulan Terakhir
              </h3>
              <span class="badge badge-secondary font-weight-normal">Tiket & Paket</span>
            </div>
            <div class="card-body p-3" style="min-height: 280px; position: relative;">
              <canvas id="chartBulan" style="height: 250px; max-height: 250px; width: 100%;"></canvas>
            </div>
          </div>
        </div>

        <!-- Kolom Kanan: Tabs Pendapatan Per Bank & Per Travel -->
        <div class="col-lg-5">
          <div class="card h-100">
            <div class="card-header p-2 bg-light d-flex justify-content-between align-items-center">
              <ul class="nav nav-pills" id="breakdownTabs">
                <li class="nav-item">
                  <a class="nav-link active py-1 px-3 text-sm font-weight-bold" href="#tab-bank" data-toggle="tab">
                    <i class="fas fa-university mr-1"></i> Per Bank
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link py-1 px-3 text-sm font-weight-bold" href="#tab-travel" data-toggle="tab">
                    <i class="fas fa-bus mr-1"></i> Per Travel
                  </a>
                </li>
              </ul>
              <span class="text-muted text-xs pr-2">Periode Ini</span>
            </div>
            <div class="card-body p-2">
              <div class="tab-content">
                <!-- Tab Per Bank -->
                <div class="active tab-pane" id="tab-bank">
                  <div style="height: 140px; margin-bottom: 8px;">
                    <canvas id="chartBank" style="height: 100%; max-height: 140px;"></canvas>
                  </div>
                  <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                    <table class="table table-bordered table-sm mb-0">
                      <thead>
                        <tr class="bg-light">
                          <th>Nama Bank / Kasir</th>
                          <th class="text-right">Total Masuk</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($bankPairs as $p): $id=$p['id']; $val=$p['val']; ?>
                        <tr>
                          <td><i class="fas fa-wallet text-muted mr-1"></i><?= htmlspecialchars($rekeningMap[$id] ?? ('Rek #'.$id)) ?></td>
                          <td class="text-right font-weight-bold">Rp <?= number_format($val,0,',','.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($bankPairs)===0): ?>
                        <tr><td colspan="2" class="text-center text-muted">Tidak ada data pendapatan</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Tab Per Travel -->
                <div class="tab-pane" id="tab-travel">
                  <div style="height: 140px; margin-bottom: 8px;">
                    <canvas id="chartTravel" style="height: 100%; max-height: 140px;"></canvas>
                  </div>
                  <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                    <table class="table table-bordered table-sm mb-0">
                      <thead>
                        <tr class="bg-light">
                          <th>No. Plat Kendaraan</th>
                          <th class="text-right">Total Masuk</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($travelTotals as $plat=>$val): ?>
                        <tr>
                          <td><i class="fas fa-shuttle-van text-muted mr-1"></i><?= htmlspecialchars($plat) ?></td>
                          <td class="text-right font-weight-bold">Rp <?= number_format($val,0,',','.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($travelTotals)===0): ?>
                        <tr><td colspan="2" class="text-center text-muted">Tidak ada data pendapatan travel</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BARIS 5: PENDAPATAN PER LOKASI & KINERJA PETUGAS -->
      <div class="row mb-3">
        <!-- Kolom Kiri: Lokasi Tujuan -->
        <div class="col-md-6 mb-3 mb-md-0">
          <div class="card h-100">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-map-marked-alt text-success mr-2"></i>Pendapatan per Rute / Lokasi Tujuan
              </h3>
              <span class="badge badge-secondary font-weight-normal">Periode Terpilih</span>
            </div>
            <div class="card-body p-0" style="position:relative;">
              <div id="skeletonLokasi" style="position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:#fff;z-index:1;">
                <span class="text-muted text-sm"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat data...</span>
              </div>
              <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                <table class="table table-bordered table-sm mb-0">
                  <thead>
                    <tr class="bg-light">
                      <th>Rute / Lokasi Tujuan</th>
                      <th class="text-right">Nominal Pendapatan</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $totLok = 0; foreach ($lokasiTotals as $tid=>$val): $totLok += $val; ?>
                    <tr>
                      <td><i class="fas fa-route text-muted mr-1"></i><?= htmlspecialchars($tujuanMap[$tid] ?? ('Tujuan #'.$tid)) ?></td>
                      <td class="text-right font-weight-bold">Rp <?= number_format($val,0,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($lokasiTotals)===0): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">Tidak ada data rute perjalanan</td></tr>
                    <?php endif; ?>
                  </tbody>
                  <?php if (count($lokasiTotals) > 0): ?>
                  <tfoot>
                    <tr class="bg-light font-weight-bold">
                      <td>TOTAL</td>
                      <td class="text-right">Rp <?= number_format($totLok,0,',','.') ?></td>
                    </tr>
                  </tfoot>
                  <?php endif; ?>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Kolom Kanan: Kinerja Petugas / User -->
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-users-cog text-success mr-2"></i>Pendapatan & Pengeluaran per Petugas
              </h3>
              <span class="badge badge-secondary font-weight-normal">Periode Terpilih</span>
            </div>
            <div class="card-body p-0" style="position:relative;">
              <div id="skeletonUser" style="position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:#fff;z-index:1;">
                <span class="text-muted text-sm"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat data...</span>
              </div>
              <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                <table class="table table-bordered table-sm mb-0">
                  <thead>
                    <tr class="bg-light">
                      <th>Petugas</th>
                      <th class="text-right">Pendapatan</th>
                      <th class="text-right">Pengeluaran</th>
                      <th class="text-right">Net Kas</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $allUserIds = array_unique(array_merge(array_map('intval', array_keys($userTotals)), array_map('intval', array_keys($pengeluaranUserTotals))));
                      usort($allUserIds, function($a,$b) use($userTotals,$pengeluaranUserTotals){
                        $na = ($userTotals[$a] ?? 0) - ($pengeluaranUserTotals[$a] ?? 0);
                        $nb = ($userTotals[$b] ?? 0) - ($pengeluaranUserTotals[$b] ?? 0);
                        return $nb <=> $na;
                      });
                      foreach ($allUserIds as $uid):
                        $pd = (float)($userTotals[$uid] ?? 0);
                        $pg = (float)($pengeluaranUserTotals[$uid] ?? 0);
                        $netKas = $pd - $pg;
                    ?>
                    <tr>
                      <td class="font-weight-bold"><i class="fas fa-user text-muted mr-1"></i><?= htmlspecialchars($userNameMap[(int)$uid] ?? ('User #'.(int)$uid)) ?></td>
                      <td class="text-right text-success">Rp <?= number_format($pd,0,',','.') ?></td>
                      <td class="text-right text-danger">Rp <?= number_format($pg,0,',','.') ?></td>
                      <td class="text-right font-weight-bold" style="color:<?= $netKas >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
                        Rp <?= number_format($netKas,0,',','.') ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($allUserIds)===0): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada aktivitas petugas pada periode ini</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BARIS 6: KEBERANGKATAN HARI INI & PETUGAS ONLINE -->
      <div class="row mb-3">
        <!-- Kolom Kiri: Keberangkatan Hari Ini -->
        <div class="col-lg-7 mb-3 mb-lg-0">
          <div class="card h-100">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-bus text-success mr-2"></i>Daftar Keberangkatan Armada Hari Ini (<?= htmlspecialchars(date('d M Y')) ?>)
              </h3>
              <a class="btn btn-xs btn-outline-secondary" href="../laporan/daftar_keberangkatan_all.php" target="_blank">
                <i class="fas fa-external-link-alt mr-1"></i> Cetak Manifes
              </a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                <table class="table table-bordered table-sm mb-0">
                  <thead>
                    <tr class="bg-light text-center">
                      <th style="width: 70px;">Waktu</th>
                      <th class="text-left">Nama Penumpang</th>
                      <th>No. Plat</th>
                      <th>Tujuan</th>
                      <th style="width: 60px;">Kursi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($keberangkatanHarian as $row): ?>
                    <tr>
                      <td class="text-center font-weight-bold text-dark">
                        <?= htmlspecialchars(date('H:i', strtotime($row['tanggal_berangkat']))) ?>
                      </td>
                      <td><?= htmlspecialchars($row['nama_id']) ?></td>
                      <td class="text-center"><span class="badge badge-light border font-weight-normal"><?= htmlspecialchars($row['no_plat_id']) ?></span></td>
                      <td><?= htmlspecialchars($tujuanMap[$row['tujuan_id']] ?? $row['tujuan_id']) ?></td>
                      <td class="text-center"><span class="badge badge-success font-weight-bold"><?= htmlspecialchars($row['kursi']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($keberangkatanHarian)===0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada jadwal keberangkatan pada hari ini.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Kolom Kanan: Users Online -->
        <div class="col-lg-5">
          <div class="card h-100">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-user-friends text-success mr-2"></i>Petugas Online (5 Menit Terakhir)
              </h3>
              <button class="btn btn-xs btn-outline-secondary" data-toggle="modal" data-target="#modalUsersOnline">
                <i class="fas fa-list mr-1"></i> Semua (<?= count($onlineUsersList) ?>)
              </button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                <table class="table table-bordered table-sm mb-0">
                  <thead>
                    <tr class="bg-light">
                      <th>Petugas</th>
                      <th>Cabang PO</th>
                      <th>Role</th>
                      <th class="text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (array_slice($onlineUsersList,0,6) as $u): ?>
                    <tr>
                      <td class="font-weight-bold"><i class="fas fa-user-circle text-muted mr-1"></i><?= htmlspecialchars($u['username']) ?></td>
                      <td><?= htmlspecialchars($u['asal_po']) ?></td>
                      <td><span class="badge badge-light border font-weight-normal"><?= htmlspecialchars($u['role']) ?></span></td>
                      <td class="text-center">
                        <span class="badge badge-success px-2 py-1 font-weight-normal"><i class="fas fa-circle text-xs mr-1"></i>Online</span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($onlineUsersList)===0): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada petugas aktif online saat ini.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BARIS 7: PINTASAN AKSES CEPAT SISTEM -->
      <div class="card mb-3">
        <div class="card-header py-2 px-3 bg-light">
          <span class="font-weight-bold text-dark text-sm"><i class="fas fa-bolt text-warning mr-2"></i>Pusat Akses Cepat Modul</span>
        </div>
        <div class="card-body p-3">
          <div class="row">
            <!-- Grup 1: Transaksi & Operasional -->
            <div class="col-lg-4 col-md-6 mb-3 mb-lg-0">
              <div class="text-xs text-uppercase font-weight-bold text-muted mb-2"><i class="fas fa-shuttle-van mr-1"></i> Operasional & Transaksi</div>
              <div class="list-group list-group-flush border rounded">
                <a href="../data_pemesanan/data_pemesanan_view_v2" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-ticket-alt text-success mr-2"></i>Data Pemesanan Tiket</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
                <a href="../data_pengiriman/data_pengiriman_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-shipping-fast text-info mr-2"></i>Data Pengiriman Paket</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
                <a href="../data_pengeluaran/data_pengeluaran_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-money-bill-wave text-danger mr-2"></i>Data Pengeluaran Operasional</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
              </div>
            </div>

            <!-- Grup 2: Keuangan & Laporan -->
            <div class="col-lg-4 col-md-6 mb-3 mb-lg-0">
              <div class="text-xs text-uppercase font-weight-bold text-muted mb-2"><i class="fas fa-book mr-1"></i> Keuangan & Pembukuan</div>
              <div class="list-group list-group-flush border rounded">
                <a href="../laporan/jurnal_keuangan" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-book text-primary mr-2"></i>Laporan Jurnal Keuangan</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
                <a href="../data_saldo_awal/data_saldo_awal_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-piggy-bank text-warning mr-2"></i>Master Saldo Awal Bank</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
                <a href="../data_transfer/internal_transfer_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-exchange-alt text-teal mr-2"></i>Transfer Antar Rekening</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
              </div>
            </div>

            <!-- Grup 3: Master Data -->
            <div class="col-lg-4 col-md-12">
              <div class="text-xs text-uppercase font-weight-bold text-muted mb-2"><i class="fas fa-cogs mr-1"></i> Master Data & Setting</div>
              <div class="list-group list-group-flush border rounded">
                <a href="../data_rekening/data_rekening_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-university text-secondary mr-2"></i>Setting Rekening & Kasir</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
                <a href="../data_travel/data_travel_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-bus text-secondary mr-2"></i>Setting Unit Travel / Armada</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
                <a href="../data_tujuan_perjalanan/data_tujuan_perjalanan_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-map text-secondary mr-2"></i>Setting Rute & Tarif Perjalanan</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BARIS 8: DIAGNOSTIK KONEKSI & JARINGAN (COLLAPSIBLE) -->
      <div class="card card-outline card-secondary mb-4">
        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center" data-toggle="collapse" data-target="#collapseDiag" style="cursor: pointer;">
          <span class="font-weight-bold text-dark text-sm">
            <i class="fas fa-network-wired mr-2 text-muted"></i>Diagnostik Koneksi & Latency Jaringan (Klik untuk Buka/Tutup)
          </span>
          <i class="fas fa-chevron-down text-muted text-xs"></i>
        </div>
        <div id="collapseDiag" class="collapse">
          <div class="card-body p-3">
            <div class="row">
              <div class="col-md-6 mb-3 mb-md-0">
                <div class="border rounded p-2">
                  <div class="font-weight-bold text-sm mb-1 text-muted"><i class="fas fa-wifi mr-1"></i> Live Latency Ping Server (ms)</div>
                  <div style="height: 180px; position: relative;">
                    <div id="skeletonPing" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;z-index:1;">
                      <span class="text-muted text-xs">Memuat grafik ping...</span>
                    </div>
                    <canvas id="chartPing" style="height:100%; visibility:hidden;"></canvas>
                  </div>
                  <small class="text-muted text-xs">Sampling latency otomatis tiap 5 detik.</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2">
                  <div class="font-weight-bold text-sm mb-1 text-muted"><i class="fas fa-chart-line mr-1"></i> Live Downlink Client (Browser)</div>
                  <div style="height: 180px; position: relative;">
                    <div id="skeletonNet" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;z-index:1;">
                      <span class="text-muted text-xs">Memuat grafik browser downlink...</span>
                    </div>
                    <canvas id="chartNetInfo" style="height:100%; visibility:hidden;"></canvas>
                  </div>
                  <small class="text-muted text-xs">Kualitas bandwidth downlink klien.</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL SEMUA PETUGAS ONLINE -->
      <div class="modal fade" id="modalUsersOnline" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <div class="modal-header py-2 px-3 bg-light">
              <h5 class="modal-title font-weight-bold text-dark text-sm"><i class="fas fa-user-check text-success mr-2"></i>Daftar Seluruh Petugas Online (5 Menit Terakhir)</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
              <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                  <thead>
                    <tr class="bg-light">
                      <th style="width: 50px;" class="text-center">ID</th>
                      <th>Username</th>
                      <th>Cabang PO</th>
                      <th>Role</th>
                      <th>Aktivitas Terakhir</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($onlineUsersList as $u): ?>
                    <tr>
                      <td class="text-center font-weight-bold"><?= (int)$u['id_users'] ?></td>
                      <td class="font-weight-bold"><i class="fas fa-user-circle text-muted mr-1"></i><?= htmlspecialchars($u['username']) ?></td>
                      <td><?= htmlspecialchars($u['asal_po']) ?></td>
                      <td><span class="badge badge-light border font-weight-normal"><?= htmlspecialchars($u['role']) ?></span></td>
                      <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($u['last_activity']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($onlineUsersList)===0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada user online</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light">
              <button class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include '../inc/footer.php'; ?>

<style>
/* Perbaikan ikon pada small-box agar tidak melewati batas card */
.small-box { overflow: hidden; }
.small-box .icon { top: 6px !important; right: 10px !important; }
.small-box .icon > i { font-size: 40px !important; line-height: 1 !important; }
.small-box .inner h3, .small-box .inner h4 { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Data untuk Chart
const bankLabels = <?= json_encode($bankChartLabels) ?>;
const bankData = <?= json_encode($bankChartData) ?>;

const travelLabels = <?= json_encode(array_keys($travelTotals)) ?>;
const travelData = <?= json_encode(array_values($travelTotals)) ?>;

const bulanLabels = <?= json_encode($bulanLabels) ?>;
const bulanData = <?= json_encode($bulanTotals) ?>;

function renderBar(ctxId, labels, data, label, barColor){
  const canvas = document.getElementById(ctxId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  canvas.height = canvas.clientHeight;
  canvas.width = canvas.clientWidth;
  if (window[ctxId+"_chart"]) { window[ctxId+"_chart"].destroy(); }
  const bg = barColor || 'rgba(13, 159, 79, 0.85)';
  const border = barColor ? barColor.replace('0.85', '1') : '#076e34';
  window[ctxId+"_chart"] = new Chart(ctx, {
    type: 'bar',
    data: { 
      labels: labels, 
      datasets: [{ 
        label: label, 
        data: data, 
        backgroundColor: bg,
        borderColor: border,
        borderWidth: 1,
        borderRadius: 4
      }] 
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { 
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(c) {
              var val = c.parsed.y || 0;
              return ' ' + label + ': Rp ' + val.toLocaleString('id-ID');
            }
          }
        }
      },
      scales: { 
        x: {
          grid: { display: false }
        },
        y: { 
          beginAtZero: true, 
          grid: { color: '#f3f4f6' },
          ticks: { callback: (v)=> (v>=1000000 ? (v/1000000)+' jt' : (v>=1000 ? (v/1000)+' rb' : v)) } 
        } 
      }
    }
  });
}

function initCharts(){
  renderBar('chartBank', bankLabels, bankData, 'Total Masuk', 'rgba(2, 132, 199, 0.85)');
  renderBar('chartTravel', travelLabels, travelData, 'Total Masuk', 'rgba(13, 159, 79, 0.85)');
  renderBar('chartBulan', bulanLabels, bulanData, 'Pendapatan Bulanan', 'rgba(13, 159, 79, 0.85)');
  initLivePing();
  initLiveNetInfo();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCharts); else initCharts();

// ============== Live Charts ==============
let pingChart, netChart;
let pingData = { labels: [], datasets: [{ label: 'Ping (ms)', data: [], borderColor: '#6c757d', backgroundColor: 'rgba(108,117,125,0.2)', fill: true, tension: 0.3 }] };
let netData = { labels: [], datasets: [
  { label: 'Downlink (Mbps)', data: [], borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.15)', fill: true, tension: 0.3 }
] };

function initLivePing(){
  const el = document.getElementById('chartPing'); if (!el) return;
  pingChart = new Chart(el.getContext('2d'), { type: 'line', data: pingData, options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } }, plugins:{ legend:{ display:false } } } });
  samplePing(); setInterval(samplePing, 5000);
}

async function samplePing(){
  const start = performance.now();
  try {
    await fetch('../assets/ping.php?rand=' + Math.random(), { cache:'no-store' });
    const ms = Math.round(performance.now() - start);
    const ts = new Date();
    pushChartPoint(pingData, ts.toLocaleTimeString(), ms, 20);
    if (pingChart) pingChart.update();
  } catch(e){ /* ignore */ }
}

function initLiveNetInfo(){
  const el = document.getElementById('chartNetInfo'); if (!el) return;
  netChart = new Chart(el.getContext('2d'), { type: 'line', data: netData, options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } }, plugins:{ legend:{ display:false } } } });
  sampleNetInfo(); setInterval(sampleNetInfo, 5000);
}

function sampleNetInfo(){
  const ts = new Date();
  const nav = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  let down = null, eff = '';
  if (nav){ down = nav.downlink || null; eff = nav.effectiveType || ''; }
  pushChartPoint(netData, ts.toLocaleTimeString(), down ?? 0, 20);
  if (netChart) netChart.update();
}

function pushChartPoint(datasetObj, label, value, maxPoints){
  datasetObj.labels.push(label);
  datasetObj.datasets[0].data.push(value);
  if (datasetObj.labels.length > maxPoints){ datasetObj.labels.shift(); datasetObj.datasets[0].data.shift(); }
}
</script>

