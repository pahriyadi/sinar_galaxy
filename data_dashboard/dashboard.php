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

// Info zona waktu dan lokasi IP
$phpTimezone = date_default_timezone_get();
$nowLocal = date('Y-m-d H:i:s');
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$cityHeader = $_SERVER['HTTP_CF_IPCITY'] ?? $_SERVER['HTTP_X_APPENGINE_CITY'] ?? '';
$regionHeader = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';

// Referensi Master
$rekeningMap = [];
$res = $conn->query("SELECT id_rekening, nama_rekening FROM data_rekening ORDER BY id_rekening ASC");
if ($res) while ($r = $res->fetch_assoc()) $rekeningMap[(int)$r['id_rekening']] = $r['nama_rekening'];

$tujuanMap = [];
$res = $conn->query("SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
if ($res) while ($r = $res->fetch_assoc()) $tujuanMap[(string)$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];

$statusMap = [];
$res = $conn->query("SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
if ($res) while ($r = $res->fetch_assoc()) $statusMap[(string)$r['id_status_pembayaran']] = strtolower($r['status_pembayaran']);

$userNameMap = [];
$userPoMap = [];
$res = $conn->query("SELECT id_users, username, asal_po FROM data_users");
if ($res) while ($r = $res->fetch_assoc()) {
    $userNameMap[(int)$r['id_users']] = $r['username'];
    $userPoMap[(int)$r['id_users']] = $r['asal_po'];
}

// 6 Bulan Terakhir Buckets (Inisialisasi)
$bulanKeys = [];
$bulanLabels = [];
$bulanTiket = [];
$bulanPaket = [];
$bulanPengeluaran = [];
$bulanTotals = [];
$bulanNet = [];

for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-{$i} months"));
    $bulanKeys[] = $ym;
    $bulanLabels[$ym] = date('M Y', strtotime($ym . '-01'));
    $bulanTiket[$ym] = 0;
    $bulanPaket[$ym] = 0;
    $bulanPengeluaran[$ym] = 0;
    $bulanTotals[$ym] = 0;
    $bulanNet[$ym] = 0;
}

// Helper: cek rentang tanggal
function inRange($dateYmd, $start, $end) {
    if (!$dateYmd) return false;
    $d = substr($dateYmd, 0, 10);
    return ($d >= $start && $d <= $end);
}

// Penampung Agregasi Periode
$totTiket = 0; $totTiketHariIni = 0; $countTiket = 0;
$totPaket = 0; $totPaketHariIni = 0; $countPaket = 0;
$bankTotals = []; $travelTotals = []; $lokasiTotals = []; $userTotals = [];
$userTiketCount = []; $userPaketCount = [];

// =========================================================================
// 1. SCAN PEMESANAN TIKET (Single-Pass Aggregation)
// =========================================================================
$wherePem = ' WHERE 1=1';
if ($role === 'admin' || $role === 'users') {
    $wherePem .= " AND (username = '".intval($idUsers)."' OR asal_po = '".intval($idUsers)."')";
}
$sqlPem = "SELECT id_pemesanan, nama_id, tanggal_pemesanan, tanggal_pembayaran, tanggal_berangkat, no_plat_id, tujuan_id, harga_id, jenis_rekening_id, username, payment_methods, status_pembayaran_id FROM data_pemesanan" . $wherePem;
$rsPem = $conn->query($sqlPem);
if ($rsPem) {
    while ($row = $rsPem->fetch_assoc()) {
        $plat = trim($row['no_plat_id'] ?? '');
        $tujuanId = (string)($row['tujuan_id'] ?? '');
        $usr = (int)($row['username'] ?? 0);
        $statusLunas = isset($row['status_pembayaran_id']) && (strpos($statusMap[$row['status_pembayaran_id']] ?? '', 'lunas') !== false);

        if (!empty($row['payment_methods'])) {
            $arr = json_decode($row['payment_methods'], true);
            if (is_array($arr) && count($arr) > 0) {
                $countedRow = false;
                foreach ($arr as $p) {
                    $amt = (float)($p['amount'] ?? 0);
                    $payDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : substr($row['tanggal_pembayaran'] ?: $row['tanggal_pemesanan'], 0, 10);
                    $ym = substr($payDate, 0, 7);

                    if ($amt > 0 && inRange($payDate, $start, $end)) {
                        $totTiket += $amt;
                        if (!$countedRow) { $countTiket++; $userTiketCount[$usr] = ($userTiketCount[$usr] ?? 0) + 1; $countedRow = true; }
                        if ($payDate === $today) $totTiketHariIni += $amt;

                        $rek = (int)($p['rekening_id'] ?? 0);
                        if ($rek) $bankTotals[$rek] = ($bankTotals[$rek] ?? 0) + $amt;
                        if ($plat && $plat !== '-' && $plat !== 'BELUM DITENTUKAN') $travelTotals[$plat] = ($travelTotals[$plat] ?? 0) + $amt;
                        if ($tujuanId) $lokasiTotals[$tujuanId] = ($lokasiTotals[$tujuanId] ?? 0) + $amt;
                        if ($usr) $userTotals[$usr] = ($userTotals[$usr] ?? 0) + $amt;
                    }

                    if ($amt > 0 && isset($bulanTiket[$ym])) {
                        $bulanTiket[$ym] += $amt;
                    }
                }
                continue;
            }
        }

        // Single payment fallback
        $amt = (float)($row['harga_id'] ?? 0);
        $payDate = substr($row['tanggal_pembayaran'] ?: $row['tanggal_pemesanan'], 0, 10);
        $ym = substr($payDate, 0, 7);

        if ($statusLunas && $amt > 0) {
            if (inRange($payDate, $start, $end)) {
                $totTiket += $amt;
                $countTiket++;
                $userTiketCount[$usr] = ($userTiketCount[$usr] ?? 0) + 1;
                if ($payDate === $today) $totTiketHariIni += $amt;

                $rek = (int)($row['jenis_rekening_id'] ?? 0);
                if ($rek) $bankTotals[$rek] = ($bankTotals[$rek] ?? 0) + $amt;
                if ($plat && $plat !== '-' && $plat !== 'BELUM DITENTUKAN') $travelTotals[$plat] = ($travelTotals[$plat] ?? 0) + $amt;
                if ($tujuanId) $lokasiTotals[$tujuanId] = ($lokasiTotals[$tujuanId] ?? 0) + $amt;
                if ($usr) $userTotals[$usr] = ($userTotals[$usr] ?? 0) + $amt;
            }

            if (isset($bulanTiket[$ym])) {
                $bulanTiket[$ym] += $amt;
            }
        }
    }
}

// =========================================================================
// 2. SCAN PENGIRIMAN PAKET (Single-Pass Aggregation)
// =========================================================================
$wherePkg = ' WHERE 1=1';
if ($role === 'admin' || $role === 'users') {
    $wherePkg .= " AND user_id = '".intval($idUsers)."' AND asal_po_id = '".$conn->real_escape_string($asalPo)."'";
}
$sqlPkg = "SELECT id_pengiriman, nama_id, tanggal_pengiriman, tanggal_pembayaran, no_plat_id, tujuan_id, jumlah, jenis_rekening_id, user_id, payment_methods, status_pembayaran_id FROM data_pengiriman" . $wherePkg;
$rsPkg = $conn->query($sqlPkg);
if ($rsPkg) {
    while ($row = $rsPkg->fetch_assoc()) {
        $plat = trim($row['no_plat_id'] ?? '');
        $tujuanId = (string)($row['tujuan_id'] ?? '');
        $usr = (int)($row['user_id'] ?? 0);
        $statusLunas = isset($row['status_pembayaran_id']) && (strpos($statusMap[$row['status_pembayaran_id']] ?? '', 'lunas') !== false);

        if (!empty($row['payment_methods'])) {
            $arr = json_decode($row['payment_methods'], true);
            if (is_array($arr) && count($arr) > 0) {
                $countedPkg = false;
                foreach ($arr as $p) {
                    $amt = (float)($p['amount'] ?? 0);
                    $payDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : substr($row['tanggal_pembayaran'] ?: $row['tanggal_pengiriman'], 0, 10);
                    $ym = substr($payDate, 0, 7);

                    if ($amt > 0 && inRange($payDate, $start, $end)) {
                        $totPaket += $amt;
                        if (!$countedPkg) { $countPaket++; $userPaketCount[$usr] = ($userPaketCount[$usr] ?? 0) + 1; $countedPkg = true; }
                        if ($payDate === $today) $totPaketHariIni += $amt;

                        $rek = (int)($p['rekening_id'] ?? 0);
                        if ($rek) $bankTotals[$rek] = ($bankTotals[$rek] ?? 0) + $amt;
                        if ($plat && $plat !== '-' && $plat !== 'BELUM DITENTUKAN') $travelTotals[$plat] = ($travelTotals[$plat] ?? 0) + $amt;
                        if ($tujuanId) $lokasiTotals[$tujuanId] = ($lokasiTotals[$tujuanId] ?? 0) + $amt;
                        if ($usr) $userTotals[$usr] = ($userTotals[$usr] ?? 0) + $amt;
                    }

                    if ($amt > 0 && isset($bulanPaket[$ym])) {
                        $bulanPaket[$ym] += $amt;
                    }
                }
                continue;
            }
        }

        // Single payment fallback
        $amt = (float)($row['jumlah'] ?? 0);
        $payDate = substr($row['tanggal_pembayaran'] ?: $row['tanggal_pengiriman'], 0, 10);
        $ym = substr($payDate, 0, 7);

        if ($statusLunas && $amt > 0) {
            if (inRange($payDate, $start, $end)) {
                $totPaket += $amt;
                $countPaket++;
                $userPaketCount[$usr] = ($userPaketCount[$usr] ?? 0) + 1;
                if ($payDate === $today) $totPaketHariIni += $amt;

                $rek = (int)($row['jenis_rekening_id'] ?? 0);
                if ($rek) $bankTotals[$rek] = ($bankTotals[$rek] ?? 0) + $amt;
                if ($plat && $plat !== '-' && $plat !== 'BELUM DITENTUKAN') $travelTotals[$plat] = ($travelTotals[$plat] ?? 0) + $amt;
                if ($tujuanId) $lokasiTotals[$tujuanId] = ($lokasiTotals[$tujuanId] ?? 0) + $amt;
                if ($usr) $userTotals[$usr] = ($userTotals[$usr] ?? 0) + $amt;
            }

            if (isset($bulanPaket[$ym])) {
                $bulanPaket[$ym] += $amt;
            }
        }
    }
}

// =========================================================================
// 3. SCAN PENGELUARAN OPERASIONAL (Kantor & Travel)
// =========================================================================
$pengeluaranKantor = 0; $pengeluaranTravel = 0; $pengeluaranHariIni = 0;
$pengeluaranByBank = []; $pengeluaranUserTotals = [];
$whereOut = ' WHERE 1=1';
if ($role === 'admin' || $role === 'users') {
    $whereOut .= " AND user_id = '".intval($idUsers)."' AND asal_po = '".$conn->real_escape_string($asalPo)."'";
}
$sqlOut = "SELECT tanggal_pengeluaran, kategori_pengeluaran, harga_operasional, status_pembayaran_id, jenis_rekening_id, user_id FROM data_pengeluaran" . $whereOut;
$rsOut = $conn->query($sqlOut);
if ($rsOut) {
    while ($row = $rsOut->fetch_assoc()) {
        $statusLunas = isset($row['status_pembayaran_id']) && (strpos($statusMap[$row['status_pembayaran_id']] ?? '', 'lunas') !== false);
        if (!$statusLunas) continue;

        $amt = (float)($row['harga_operasional'] ?? 0);
        $tgl = substr($row['tanggal_pengeluaran'] ?? '', 0, 10);
        $ym = substr($tgl, 0, 7);

        if ($amt > 0 && inRange($tgl, $start, $end)) {
            if (($row['kategori_pengeluaran'] ?? 'travel') === 'kantor') {
                $pengeluaranKantor += $amt;
            } else {
                $pengeluaranTravel += $amt;
            }
            $rekId = (int)($row['jenis_rekening_id'] ?? 0);
            if ($rekId) $pengeluaranByBank[$rekId] = ($pengeluaranByBank[$rekId] ?? 0) + $amt;
            $uidOut = (int)($row['user_id'] ?? 0);
            if ($uidOut) $pengeluaranUserTotals[$uidOut] = ($pengeluaranUserTotals[$uidOut] ?? 0) + $amt;
            if ($tgl === $today) $pengeluaranHariIni += $amt;
        }

        if ($amt > 0 && isset($bulanPengeluaran[$ym])) {
            $bulanPengeluaran[$ym] += $amt;
        }
    }
}

// Totals & Net Rekapitulasi
$totalPendapatan = $totTiket + $totPaket;
$totalPendapatanHariIni = $totTiketHariIni + $totPaketHariIni;
$totalPengeluaran = $pengeluaranKantor + $pengeluaranTravel;
$rekapDebit = $totalPendapatan;
$rekapKredit = $totalPengeluaran;
$rekapNet = $rekapDebit - $rekapKredit;
$marginNet = $totalPendapatan > 0 ? round(($rekapNet / $totalPendapatan) * 100, 1) : 0;
$avgTiket = $countTiket > 0 ? round($totTiket / $countTiket) : 0;
$avgPaket = $countPaket > 0 ? round($totPaket / $countPaket) : 0;

// Hitung Rekapitulasi 6 Bulan Terakhir
foreach ($bulanKeys as $ym) {
    $bulanTotals[$ym] = $bulanTiket[$ym] + $bulanPaket[$ym];
    $bulanNet[$ym] = $bulanTotals[$ym] - $bulanPengeluaran[$ym];
}

// Urutkan Bank Teratas
$bankPairs = [];
foreach ($bankTotals as $id => $val) {
    $bankPairs[] = ['id' => (int)$id, 'val' => (float)$val];
}
usort($bankPairs, function($a, $b) { return $b['val'] <=> $a['val']; });
$bankChartLabels = array_map(function($p) use ($rekeningMap) {
    $id = $p['id']; return $rekeningMap[$id] ?? ('Rek #' . $id);
}, $bankPairs);
$bankChartData = array_map(function($p) { return (float)$p['val']; }, $bankPairs);

// Urutkan Travel Teratas
arsort($travelTotals);
$travelChartLabels = array_slice(array_keys($travelTotals), 0, 10);
$travelChartData = array_slice(array_values($travelTotals), 0, 10);

// Saldo Awal Bank untuk Bulan Filter
$bulanFilter = (int)date('n', strtotime($start));
$tahunFilter = (int)date('Y', strtotime($start));
$saldoAwalByBank = [];
$stmtSA = $conn->prepare("SELECT id_rekening, saldo_awal FROM saldo_awal_bank WHERE bulan=? AND tahun=?");
if ($stmtSA) {
    $stmtSA->bind_param('ii', $bulanFilter, $tahunFilter);
    $stmtSA->execute();
    $rsSA = $stmtSA->get_result();
    if ($rsSA) {
        while ($row = $rsSA->fetch_assoc()) {
            $saldoAwalByBank[(int)$row['id_rekening']] = (float)$row['saldo_awal'];
        }
    }
    $stmtSA->close();
}

// Transfer Internal (Periode)
$transferCount = 0; $transferSum = 0.0;
$tfInByBank = []; $tfOutByBank = [];
$stmtTf = $conn->prepare("SELECT dari_rekening_id, ke_rekening_id, jumlah FROM internal_transfers WHERE tanggal >= ? AND tanggal <= ?");
if ($stmtTf) {
    $stmtTf->bind_param('ss', $start, $end);
    $stmtTf->execute();
    $rsTfx = $stmtTf->get_result();
    if ($rsTfx) {
        while ($row = $rsTfx->fetch_assoc()) {
            $dr = (int)$row['dari_rekening_id'];
            $kr = (int)$row['ke_rekening_id'];
            $j = (float)$row['jumlah'];
            $transferCount++;
            $transferSum += $j;
            if ($kr) $tfInByBank[$kr] = ($tfInByBank[$kr] ?? 0) + $j;
            if ($dr) $tfOutByBank[$dr] = ($tfOutByBank[$dr] ?? 0) + $j;
        }
    }
    $stmtTf->close();
}

// Hitung Saldo Akhir per Bank
$saldoAkhirByBank = [];
foreach ($rekeningMap as $rid => $nama) {
    $rid = (int)$rid;
    $awal = (float)($saldoAwalByBank[$rid] ?? 0);
    $in = (float)($bankTotals[$rid] ?? 0) + (float)($tfInByBank[$rid] ?? 0);
    $out = (float)($pengeluaranByBank[$rid] ?? 0) + (float)($tfOutByBank[$rid] ?? 0);
    $saldoAkhirByBank[$rid] = $awal + $in - $out;
}

// Daftar Keberangkatan Hari Ini
$keberangkatanHarian = [];
$whereHar = " WHERE DATE(tanggal_berangkat) = '{$today}'";
if ($role === 'admin' || $role === 'users') {
    $whereHar .= " AND username = '".intval($idUsers)."' AND asal_po = '".$conn->real_escape_string($asalPo)."'";
}
$sqlHar = "SELECT nama_id, no_plat_id, tujuan_id, kursi, tanggal_berangkat FROM data_pemesanan" . $whereHar . " ORDER BY tanggal_berangkat ASC LIMIT 15";
$rsHar = $conn->query($sqlHar);
if ($rsHar) while ($r = $rsHar->fetch_assoc()) $keberangkatanHarian[] = $r;

// Status Petugas Kasir Online / Offline (5 Menit)
$totalUsers = 0; $onlineUsers = 0; $offlineUsers = 0;
$resTU = $conn->query("SELECT COUNT(*) c FROM data_users");
if ($resTU && ($row = $resTU->fetch_assoc())) $totalUsers = (int)$row['c'];
$resOU = $conn->query("SELECT COUNT(*) c FROM data_users WHERE last_activity IS NOT NULL AND last_activity >= (NOW() - INTERVAL 5 MINUTE)");
if ($resOU && ($row = $resOU->fetch_assoc())) $onlineUsers = (int)$row['c'];
$offlineUsers = max(0, $totalUsers - $onlineUsers);

$onlineUsersList = [];
$resList = $conn->query("SELECT id_users, username, asal_po, role, last_activity FROM data_users WHERE last_activity IS NOT NULL AND last_activity >= (NOW() - INTERVAL 5 MINUTE) ORDER BY last_activity DESC");
if ($resList) while ($r = $resList->fetch_assoc()) $onlineUsersList[] = $r;

// Latency Database Check
$dbStatusText = 'OK';
$dbLatencyMs = null;
$t0 = microtime(true);
$rsPing = $conn->query('SELECT 1');
$t1 = microtime(true);
if (!$rsPing) {
    $dbStatusText = 'ERROR: ' . $conn->error;
} else {
    $dbLatencyMs = round(($t1 - $t0) * 1000);
}
?>

<div class="content-wrapper" style="background-color: #fcfcfc;">
  <!-- Section Content Header (Universal 2-Tier Header Paper White v2.0) -->
  <section class="content-header no-print" style="padding: 14px 18px 8px; margin-bottom: 0;">
    <div class="container-fluid">
      <div class="report-header-box">
        <!-- Baris 1: Judul Eksekutif & Identitas Cabang -->
        <div class="report-header-top">
          <div class="report-header-info">
            <div class="report-header-icon mr-3">
              <i class="fas fa-tachometer-alt"></i>
            </div>
            <div>
              <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                <h1 class="report-header-title">Dashboard Eksekutif &amp; Monitoring</h1>
                <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                  <i class="fas fa-building mr-1"></i><?= $role === 'super admin' ? 'Seluruh Cabang PO' : 'PO ' . htmlspecialchars($asalPo) ?>
                </span>
                <span class="badge" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                  <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars(strtoupper($role)) ?>
                </span>
                <span class="badge" style="background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; font-weight: 600;">
                  <i class="far fa-user mr-1"></i><?= htmlspecialchars($user['username'] ?? 'User') ?>
                </span>
              </div>
              <div class="report-header-subtitle">
                Ringkasan komprehensif keuangan, performa kasir, transaksi tiket, kargo ekspres, dan posisi kas per periode
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center flex-wrap mt-2 mt-md-0" style="gap: 8px;">
            <span class="badge <?= ($dbLatencyMs !== null && $dbLatencyMs <= 150) ? 'badge-success' : (($dbLatencyMs !== null && $dbLatencyMs <= 400) ? 'badge-warning' : 'badge-danger') ?> px-2 py-1 font-weight-normal">
              <i class="fas fa-database mr-1"></i> DB: <?= htmlspecialchars($dbStatusText) ?> (<?= (int)$dbLatencyMs ?> ms)
            </span>
            <span class="badge badge-light border text-muted px-2 py-1 font-weight-normal">
              <i class="far fa-clock mr-1 text-success"></i><?= htmlspecialchars(date('H:i:s')) ?> WITA
            </span>
            <?php if ($clientIp): ?>
            <span class="badge badge-light border text-muted px-2 py-1 font-weight-normal d-none d-sm-inline-block">
              <i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($clientIp) ?><?= $cityHeader ? ' &bull; ' . htmlspecialchars($cityHeader) : '' ?>
            </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Baris 2: Toolbar Aksi & Pintasan Audit -->
        <div class="report-header-toolbar no-print">
          <div class="report-header-toolbar-left">
            <span class="text-muted" style="font-size: 0.8rem;">
              <i class="fas fa-info-circle mr-1 text-info"></i> Periode aktif: <strong><?= htmlspecialchars(date('d M Y', strtotime($tglDari))) ?></strong> s.d. <strong><?= htmlspecialchars(date('d M Y', strtotime($tglSampai))) ?></strong>
            </span>
          </div>
          <div class="report-header-toolbar-right">
            <button id="btnClearBrowserCache" class="btn btn-sm btn-outline-secondary" title="Bersihkan cache browser & storage lokal">
              <i class="fas fa-broom mr-1 text-muted"></i> Cache Browser
            </button>
            <?php if ($role === 'super admin'): ?>
            <button id="btnClearServerCache" class="btn btn-sm btn-outline-secondary" title="Reset OPcache server">
              <i class="fas fa-server mr-1 text-muted"></i> Cache Server
            </button>
            <?php endif; ?>
            <a href="../laporan/jurnal_keuangan.php" class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-book mr-1 text-primary"></i> Jurnal Keuangan
            </a>
            <a href="../laporan/daftar_keberangkatan_all.php" class="btn btn-sm btn-outline-secondary" target="_blank">
              <i class="fas fa-bus mr-1 text-success"></i> Manifes Hari Ini
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <style>
      .metric-card-paper {
        background: #ffffff;
        border: 1px solid #b8b8b8;
        border-radius: 4px;
        padding: 16px 18px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
      }
      .metric-card-paper:hover {
        border-color: #0d9f4f;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
      }
      .metric-card-paper .metric-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 8px;
      }
      .metric-card-paper .metric-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      .metric-card-paper .metric-icon {
        width: 38px;
        height: 38px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
      }
      .metric-card-paper .metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
        margin-bottom: 6px;
      }
      .metric-card-paper .metric-sub {
        font-size: 0.8125rem;
        color: #6b7280;
        line-height: 1.35;
      }
      .filter-card-paper {
        background: #ffffff;
        border: 1px solid #b8b8b8;
        border-radius: 4px;
        margin-bottom: 16px;
      }
      .filter-card-paper .card-header {
        background: #fafafa;
        border-bottom: 1px solid #e5e7eb;
      }
      .chart-toggle-btn {
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #4b5563;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 3px;
        cursor: pointer;
        transition: all 0.15s ease;
      }
      .chart-toggle-btn:hover {
        background: #f3f4f6;
        color: #111827;
      }
      .chart-toggle-btn.active {
        background: #0d9f4f;
        border-color: #0d9f4f;
        color: #ffffff;
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
      </script>

      <!-- BAR FILTER HORISONTAL PAPER WHITE -->
      <div class="filter-card-paper">
        <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center">
          <span class="font-weight-bold text-dark text-sm"><i class="fas fa-calendar-alt text-success mr-2"></i>Filter Rentang Periode Laporan</span>
          <span class="badge badge-light border font-weight-normal text-muted"><?= htmlspecialchars(date('d M Y', strtotime($tglDari))) ?> s.d. <?= htmlspecialchars(date('d M Y', strtotime($tglSampai))) ?></span>
        </div>
        <div class="card-body p-3">
          <form method="get" id="filterForm" class="row align-items-end">
            <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
              <label class="text-xs font-weight-bold mb-1 text-muted">DARI TANGGAL:</label>
              <input type="date" id="inputTglDari" name="tgl_dari" value="<?= htmlspecialchars($tglDari) ?>" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
              <label class="text-xs font-weight-bold mb-1 text-muted">SAMPAI TANGGAL:</label>
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
                <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('today')">Hari Ini</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('month')">Bulan Ini</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('prev_month')">Bulan Lalu</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setPresetRange('30days')">30 Hari</button>
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
        } else if(type === 'prev_month'){
          var prevM = new Date(yyyy, now.getMonth() - 1, 1);
          var py = prevM.getFullYear();
          var pm = String(prevM.getMonth() + 1).padStart(2, '0');
          var lastDayP = new Date(py, prevM.getMonth() + 1, 0).getDate();
          dari.value = py + '-' + pm + '-01';
          sampai.value = py + '-' + pm + '-' + String(lastDayP).padStart(2, '0');
        } else if(type === '30days'){
          var prev = new Date();
          prev.setDate(prev.getDate() - 30);
          var py2 = prev.getFullYear();
          var pm2 = String(prev.getMonth() + 1).padStart(2, '0');
          var pd2 = String(prev.getDate()).padStart(2, '0');
          dari.value = py2 + '-' + pm2 + '-' + pd2;
          sampai.value = todayStr;
        }
        document.getElementById('filterForm').submit();
      }
      </script>

      <!-- BARIS 1: GRAFIK EKSEKUTIF KUALITAS TINGGI (COMBO CHART & BREAKDOWN TABS) -->
      <div class="row mb-3">
        <!-- Kolom Kiri: Tren Finansial 6 Bulan Terakhir -->
        <div class="col-lg-7 mb-3 mb-lg-0">
          <div class="card h-100" style="border: 1px solid #b8b8b8; border-radius: 4px;">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center flex-wrap" style="border-bottom: 1px solid #e0e0e0; gap: 8px;">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-chart-line text-success mr-2"></i>Tren Finansial &amp; Pendapatan 6 Bulan Terakhir
              </h3>
              <div class="d-flex align-items-center" style="gap: 4px;">
                <button type="button" class="chart-toggle-btn active" onclick="switchMainChart('all', this)">Semua (Combo)</button>
                <button type="button" class="chart-toggle-btn" onclick="switchMainChart('net', this)">Laba Bersih</button>
                <button type="button" class="chart-toggle-btn" onclick="switchMainChart('breakdown', this)">Tiket vs Paket</button>
              </div>
            </div>
            <div class="card-body p-3" style="min-height: 310px; position: relative;">
              <canvas id="chartBulan" style="height: 280px; max-height: 280px; width: 100%;"></canvas>
            </div>
            <div class="card-footer py-2 px-3 bg-white border-top text-xs text-muted d-flex justify-content-between align-items-center">
              <span><i class="fas fa-info-circle mr-1 text-info"></i>Area gradien menampilkan total omzet masuk vs pengeluaran operasional.</span>
              <span class="font-weight-bold text-dark">Data Real-Time</span>
            </div>
          </div>
        </div>

        <!-- Kolom Kanan: Tabs Pendapatan Per Bank, Per Travel, Komposisi -->
        <div class="col-lg-5">
          <div class="card h-100" style="border: 1px solid #b8b8b8; border-radius: 4px;">
            <div class="card-header p-2 bg-light d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e0e0e0;">
              <ul class="nav nav-pills" id="breakdownTabs">
                <li class="nav-item">
                  <a class="nav-link active py-1 px-3 text-xs font-weight-bold" href="#tab-bank" data-toggle="tab">
                    <i class="fas fa-university mr-1"></i> Per Bank / Kasir
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link py-1 px-3 text-xs font-weight-bold" href="#tab-travel" data-toggle="tab">
                    <i class="fas fa-bus mr-1"></i> Armada Travel
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link py-1 px-3 text-xs font-weight-bold" href="#tab-komposisi" data-toggle="tab">
                    <i class="fas fa-chart-pie mr-1"></i> Tiket vs Paket
                  </a>
                </li>
              </ul>
              <span class="text-muted text-xs pr-2">Periode Ini</span>
            </div>
            <div class="card-body p-2">
              <div class="tab-content">
                <!-- Tab Per Bank -->
                <div class="active tab-pane" id="tab-bank">
                  <div style="height: 160px; margin-bottom: 8px;">
                    <canvas id="chartBankDoughnut" style="height: 100%; max-height: 160px;"></canvas>
                  </div>
                  <div class="table-responsive" style="max-height: 140px; overflow-y: auto;">
                    <table class="table table-bordered table-sm mb-0">
                      <thead>
                        <tr class="bg-light">
                          <th>Nama Bank / Kasir</th>
                          <th class="text-right">Nominal Masuk</th>
                          <th class="text-center" style="width: 55px;">Porsi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                          foreach ($bankPairs as $p):
                            $id = $p['id']; $val = $p['val'];
                            $pct = $totalPendapatan > 0 ? round(($val / $totalPendapatan) * 100, 1) : 0;
                        ?>
                        <tr>
                          <td><i class="fas fa-wallet text-muted mr-1"></i><?= htmlspecialchars($rekeningMap[$id] ?? ('Rek #' . $id)) ?></td>
                          <td class="text-right font-weight-bold">Rp <?= number_format($val,0,',','.') ?></td>
                          <td class="text-center"><span class="badge badge-light border text-xs"><?= $pct ?>%</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($bankPairs) === 0): ?>
                        <tr><td colspan="3" class="text-center text-muted">Tidak ada data pendapatan rekening</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Tab Per Travel -->
                <div class="tab-pane" id="tab-travel">
                  <div style="height: 160px; margin-bottom: 8px;">
                    <canvas id="chartTravelBar" style="height: 100%; max-height: 160px;"></canvas>
                  </div>
                  <div class="table-responsive" style="max-height: 140px; overflow-y: auto;">
                    <table class="table table-bordered table-sm mb-0">
                      <thead>
                        <tr class="bg-light">
                          <th>No. Plat Kendaraan</th>
                          <th class="text-right">Total Omzet</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($travelTotals as $plat => $val): ?>
                        <tr>
                          <td><i class="fas fa-shuttle-van text-muted mr-1"></i><?= htmlspecialchars($plat) ?></td>
                          <td class="text-right font-weight-bold">Rp <?= number_format($val,0,',','.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($travelTotals) === 0): ?>
                        <tr><td colspan="2" class="text-center text-muted">Tidak ada data pendapatan travel</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Tab Komposisi Tiket vs Paket -->
                <div class="tab-pane" id="tab-komposisi">
                  <div style="height: 160px; margin-bottom: 8px;">
                    <canvas id="chartKomposisiDoughnut" style="height: 100%; max-height: 160px;"></canvas>
                  </div>
                  <div class="table-responsive" style="max-height: 140px; overflow-y: auto;">
                    <table class="table table-bordered table-sm mb-0">
                      <thead>
                        <tr class="bg-light">
                          <th>Sumber Layanan</th>
                          <th class="text-right">Volume</th>
                          <th class="text-right">Total Omzet</th>
                          <th class="text-center" style="width: 55px;">Porsi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                          $pctTiket = $totalPendapatan > 0 ? round(($totTiket / $totalPendapatan) * 100, 1) : 0;
                          $pctPaket = $totalPendapatan > 0 ? round(($totPaket / $totalPendapatan) * 100, 1) : 0;
                        ?>
                        <tr>
                          <td><i class="fas fa-ticket-alt text-success mr-1"></i>Tiket Penumpang</td>
                          <td class="text-right"><?= number_format($countTiket) ?> pax</td>
                          <td class="text-right font-weight-bold">Rp <?= number_format($totTiket,0,',','.') ?></td>
                          <td class="text-center"><span class="badge badge-success"><?= $pctTiket ?>%</span></td>
                        </tr>
                        <tr>
                          <td><i class="fas fa-box text-info mr-1"></i>Kargo &amp; Titipan Paket</td>
                          <td class="text-right"><?= number_format($countPaket) ?> koli</td>
                          <td class="text-right font-weight-bold">Rp <?= number_format($totPaket,0,',','.') ?></td>
                          <td class="text-center"><span class="badge badge-info"><?= $pctPaket ?>%</span></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BARIS 2: 4 KARTU KPI FINANSIAL UTAMA -->
      <div class="row mb-3">
        <!-- Card 1: Total Pendapatan -->
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card-paper" style="border-top: 3px solid #0d9f4f;">
            <div class="metric-header">
              <span class="metric-title">Total Pendapatan (Omzet)</span>
              <div class="metric-icon" style="background:#eaf8ef; color:#0d9f4f;">
                <i class="fas fa-hand-holding-usd"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($totalPendapatan,0,',','.') ?></div>
            <div class="metric-sub">
              <div class="d-flex justify-content-between text-xs mb-1">
                <span><i class="fas fa-ticket-alt text-success mr-1"></i>Tiket (<?= number_format($countTiket) ?>):</span>
                <strong>Rp <?= number_format($totTiket,0,',','.') ?></strong>
              </div>
              <div class="d-flex justify-content-between text-xs">
                <span><i class="fas fa-box text-info mr-1"></i>Paket (<?= number_format($countPaket) ?>):</span>
                <strong>Rp <?= number_format($totPaket,0,',','.') ?></strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 2: Pendapatan Hari Ini -->
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card-paper" style="border-top: 3px solid #0284c7;">
            <div class="metric-header">
              <span class="metric-title">Pendapatan Hari Ini</span>
              <div class="metric-icon" style="background:#e0f2fe; color:#0284c7;">
                <i class="fas fa-calendar-day"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($totalPendapatanHariIni,0,',','.') ?></div>
            <div class="metric-sub">
              <div class="d-flex justify-content-between text-xs mb-1">
                <span><i class="far fa-clock text-info mr-1"></i>Kas Tiket Hari Ini:</span>
                <strong>Rp <?= number_format($totTiketHariIni,0,',','.') ?></strong>
              </div>
              <div class="d-flex justify-content-between text-xs">
                <span><i class="fas fa-shipping-fast text-info mr-1"></i>Kas Paket Hari Ini:</span>
                <strong>Rp <?= number_format($totPaketHariIni,0,',','.') ?></strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 3: Total Pengeluaran -->
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card-paper" style="border-top: 3px solid #dc2626;">
            <div class="metric-header">
              <span class="metric-title">Total Pengeluaran</span>
              <div class="metric-icon" style="background:#fee2e2; color:#dc2626;">
                <i class="fas fa-receipt"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($totalPengeluaran,0,',','.') ?></div>
            <div class="metric-sub">
              <div class="d-flex justify-content-between text-xs mb-1">
                <span><i class="fas fa-building text-danger mr-1"></i>Biaya Kantor:</span>
                <strong>Rp <?= number_format($pengeluaranKantor,0,',','.') ?></strong>
              </div>
              <div class="d-flex justify-content-between text-xs">
                <span><i class="fas fa-shuttle-van text-danger mr-1"></i>Biaya Armada/Travel:</span>
                <strong>Rp <?= number_format($pengeluaranTravel,0,',','.') ?></strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 4: Laba Bersih (Net) -->
        <div class="col-xl-3 col-md-6 col-12">
          <div class="metric-card-paper" style="border-top: 3px solid <?= $rekapNet >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
            <div class="metric-header">
              <span class="metric-title">Laba Bersih (Net Profit)</span>
              <div class="metric-icon" style="background:<?= $rekapNet >= 0 ? '#eaf8ef' : '#fee2e2' ?>; color:<?= $rekapNet >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
                <i class="fas fa-balance-scale"></i>
              </div>
            </div>
            <div class="metric-value" style="color:<?= $rekapNet >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
              Rp <?= number_format($rekapNet,0,',','.') ?>
            </div>
            <div class="metric-sub">
              <div class="d-flex justify-content-between align-items-center text-xs">
                <span><i class="fas fa-percentage text-muted mr-1"></i>Margin Keuntungan:</span>
                <span class="badge <?= $rekapNet >= 0 ? 'badge-success' : 'badge-danger' ?> px-2 py-1 font-weight-bold">
                  <?= $marginNet >= 0 ? '+' : '' ?><?= $marginNet ?>%
                </span>
              </div>
              <small class="text-muted text-xs d-block mt-1">Selisih total pendapatan & operasional</small>
            </div>
          </div>
        </div>
      </div>

      <!-- BARIS 3: 4 KARTU OPERASIONAL & MUTASI -->
      <div class="row mb-3">
        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card-paper">
            <div class="metric-header">
              <span class="metric-title">Volume Penumpang</span>
              <div class="metric-icon" style="background:#f3f4f6; color:#0d9f4f;">
                <i class="fas fa-ticket-alt"></i>
              </div>
            </div>
            <div class="metric-value"><?= number_format($countTiket) ?> <small class="text-muted text-sm font-weight-normal">Tiket</small></div>
            <div class="metric-sub">
              Rata-rata tiket: <strong>Rp <?= number_format($avgTiket,0,',','.') ?></strong> / pax
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card-paper">
            <div class="metric-header">
              <span class="metric-title">Titipan Kargo / Paket</span>
              <div class="metric-icon" style="background:#f3f4f6; color:#0284c7;">
                <i class="fas fa-box-open"></i>
              </div>
            </div>
            <div class="metric-value"><?= number_format($countPaket) ?> <small class="text-muted text-sm font-weight-normal">Paket</small></div>
            <div class="metric-sub">
              Rata-rata ongkir: <strong>Rp <?= number_format($avgPaket,0,',','.') ?></strong> / resi
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
          <div class="metric-card-paper">
            <div class="metric-header">
              <span class="metric-title">Mutasi Internal</span>
              <div class="metric-icon" style="background:#f3f4f6; color:#0b3b7b;">
                <i class="fas fa-exchange-alt"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($transferSum,0,',','.') ?></div>
            <div class="metric-sub">
              <?= number_format($transferCount) ?> transaksi transfer antar rekening/kasir
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
          <div class="metric-card-paper">
            <div class="metric-header">
              <span class="metric-title">Petugas Kasir Online</span>
              <div class="metric-icon" style="background:#eaf8ef; color:#0d9f4f;">
                <i class="fas fa-user-check"></i>
              </div>
            </div>
            <div class="metric-value">
              <?= number_format($onlineUsers) ?> <small class="text-muted text-sm font-weight-normal">dari <?= number_format($totalUsers) ?> User</small>
            </div>
            <div class="metric-sub d-flex justify-content-between align-items-center">
              <span class="badge badge-success font-weight-normal px-2 py-1"><i class="fas fa-circle text-xs mr-1"></i>Aktif 5 menit</span>
              <button class="btn btn-xs btn-outline-secondary" data-toggle="modal" data-target="#modalUsersOnline">
                <i class="fas fa-eye mr-1"></i> Lihat
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- BARIS 4: POSISI SALDO & MUTASI REKENING BANK / KASIR -->
      <div class="card mb-4" style="border: 1px solid #b8b8b8; border-radius: 4px;">
        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e0e0e0;">
          <h3 class="card-title font-weight-bold text-dark text-sm m-0">
            <i class="fas fa-university text-success mr-2"></i>Posisi Saldo &amp; Rekapitulasi Mutasi Rekening / Kasir (Periode Ini)
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
                <tr class="bg-light text-center" style="border-bottom: 2px solid #b8b8b8;">
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
                  <td class="text-center font-weight-bold text-muted"><?= $noRek ?></td>
                  <td class="font-weight-bold text-dark">
                    <i class="fas fa-wallet text-muted mr-1"></i><?= htmlspecialchars($rname) ?>
                  </td>
                  <td class="text-right text-muted">Rp <?= number_format($vAwal,0,',','.') ?></td>
                  <td class="text-right text-success font-weight-bold">+Rp <?= number_format($vMasuk,0,',','.') ?></td>
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
                  <td class="text-right text-success font-weight-bold">+Rp <?= number_format($sumMasuk,0,',','.') ?></td>
                  <td class="text-right text-info">+Rp <?= number_format($sumTfIn,0,',','.') ?></td>
                  <td class="text-right text-warning">-Rp <?= number_format($sumTfOut,0,',','.') ?></td>
                  <td class="text-right text-danger">-Rp <?= number_format($sumKeluar,0,',','.') ?></td>
                  <td class="text-right font-weight-bold" style="background-color: #f0f7f3; color: <?= $sumAkhir >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
                    Rp <?= number_format($sumAkhir,0,',','.') ?>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <!-- BARIS 5: PENDAPATAN PER RUTE / LOKASI & KINERJA PETUGAS -->
      <div class="row mb-3">
        <!-- Kolom Kiri: Lokasi Tujuan -->
        <div class="col-md-6 mb-3 mb-md-0">
          <div class="card h-100" style="border: 1px solid #b8b8b8; border-radius: 4px;">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e0e0e0;">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-map-marked-alt text-success mr-2"></i>Pendapatan per Rute / Lokasi Tujuan
              </h3>
              <span class="badge badge-light border font-weight-normal text-muted">Periode Terpilih</span>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-bordered table-sm mb-0">
                  <thead>
                    <tr class="bg-light">
                      <th>Rute / Lokasi Tujuan</th>
                      <th class="text-right">Nominal Pendapatan</th>
                      <th class="text-left" style="width: 110px;">Kontribusi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      arsort($lokasiTotals);
                      $totLok = 0;
                      foreach ($lokasiTotals as $tid => $val):
                        $totLok += $val;
                        $pctL = $totalPendapatan > 0 ? round(($val / $totalPendapatan) * 100, 1) : 0;
                    ?>
                    <tr>
                      <td><i class="fas fa-route text-muted mr-1"></i><?= htmlspecialchars($tujuanMap[$tid] ?? ('Tujuan #' . $tid)) ?></td>
                      <td class="text-right font-weight-bold">Rp <?= number_format($val,0,',','.') ?></td>
                      <td class="align-middle">
                        <div class="d-flex align-items-center" style="gap: 6px;">
                          <div class="progress flex-grow-1" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: <?= min(100, $pctL) ?>%"></div>
                          </div>
                          <span class="text-xs text-muted" style="min-width: 32px;"><?= $pctL ?>%</span>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($lokasiTotals) === 0): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">Tidak ada data transaksi rute perjalanan pada periode ini.</td></tr>
                    <?php endif; ?>
                  </tbody>
                  <?php if (count($lokasiTotals) > 0): ?>
                  <tfoot>
                    <tr class="bg-light font-weight-bold">
                      <td>TOTAL</td>
                      <td class="text-right">Rp <?= number_format($totLok,0,',','.') ?></td>
                      <td class="text-muted text-xs">100%</td>
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
          <div class="card h-100" style="border: 1px solid #b8b8b8; border-radius: 4px;">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e0e0e0;">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-users-cog text-success mr-2"></i>Kinerja Petugas &amp; Kasir (Penerimaan Kas)
              </h3>
              <a href="../laporan/pendapatan_user.php" class="btn btn-xs btn-outline-secondary">
                <i class="fas fa-external-link-alt mr-1"></i> Laporan Detail
              </a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-bordered table-sm mb-0">
                  <thead>
                    <tr class="bg-light">
                      <th>Petugas (PO)</th>
                      <th class="text-right">Penerimaan</th>
                      <th class="text-right">Pengeluaran</th>
                      <th class="text-right">Net Kasir</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $allUserIds = array_unique(array_merge(array_map('intval', array_keys($userTotals)), array_map('intval', array_keys($pengeluaranUserTotals))));
                      usort($allUserIds, function($a, $b) use ($userTotals, $pengeluaranUserTotals) {
                        $na = ($userTotals[$a] ?? 0) - ($pengeluaranUserTotals[$a] ?? 0);
                        $nb = ($userTotals[$b] ?? 0) - ($pengeluaranUserTotals[$b] ?? 0);
                        return $nb <=> $na;
                      });
                      foreach ($allUserIds as $uid):
                        $pd = (float)($userTotals[$uid] ?? 0);
                        $pg = (float)($pengeluaranUserTotals[$uid] ?? 0);
                        $netKas = $pd - $pg;
                        $poLabel = $userPoMap[$uid] ?? '';
                    ?>
                    <tr>
                      <td class="font-weight-bold text-dark">
                        <i class="fas fa-user text-muted mr-1"></i><?= htmlspecialchars($userNameMap[(int)$uid] ?? ('User #' . (int)$uid)) ?>
                        <?php if ($poLabel): ?>
                        <span class="badge badge-light border text-muted font-weight-normal ml-1" style="font-size: 0.7rem;"><?= htmlspecialchars($poLabel) ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-right text-success font-weight-bold">Rp <?= number_format($pd,0,',','.') ?></td>
                      <td class="text-right text-danger">Rp <?= number_format($pg,0,',','.') ?></td>
                      <td class="text-right font-weight-bold" style="color:<?= $netKas >= 0 ? '#0d9f4f' : '#dc2626' ?>;">
                        Rp <?= number_format($netKas,0,',','.') ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($allUserIds) === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada aktivitas transaksi petugas pada periode ini.</td></tr>
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
          <div class="card h-100" style="border: 1px solid #b8b8b8; border-radius: 4px;">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e0e0e0;">
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
                      <td><i class="far fa-user text-muted mr-1"></i><?= htmlspecialchars($row['nama_id']) ?></td>
                      <td class="text-center"><span class="badge badge-light border font-weight-normal"><?= htmlspecialchars($row['no_plat_id']) ?></span></td>
                      <td><?= htmlspecialchars($tujuanMap[$row['tujuan_id']] ?? $row['tujuan_id']) ?></td>
                      <td class="text-center"><span class="badge badge-success font-weight-bold"><?= htmlspecialchars($row['kursi']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($keberangkatanHarian) === 0): ?>
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
          <div class="card h-100" style="border: 1px solid #b8b8b8; border-radius: 4px;">
            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e0e0e0;">
              <h3 class="card-title font-weight-bold text-dark text-sm m-0">
                <i class="fas fa-user-friends text-success mr-2"></i>Petugas Aktif Online (5 Menit Terakhir)
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
                    <?php foreach (array_slice($onlineUsersList, 0, 6) as $u): ?>
                    <tr>
                      <td class="font-weight-bold text-dark"><i class="fas fa-user-circle text-muted mr-1"></i><?= htmlspecialchars($u['username']) ?></td>
                      <td><?= htmlspecialchars($u['asal_po']) ?></td>
                      <td><span class="badge badge-light border font-weight-normal"><?= htmlspecialchars($u['role']) ?></span></td>
                      <td class="text-center">
                        <span class="badge badge-success px-2 py-1 font-weight-normal"><i class="fas fa-circle text-xs mr-1"></i>Online</span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($onlineUsersList) === 0): ?>
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
      <div class="card mb-3" style="border: 1px solid #b8b8b8; border-radius: 4px;">
        <div class="card-header py-2 px-3 bg-light" style="border-bottom: 1px solid #e0e0e0;">
          <span class="font-weight-bold text-dark text-sm"><i class="fas fa-bolt text-warning mr-2"></i>Pusat Akses Cepat Modul Aplikasi</span>
        </div>
        <div class="card-body p-3">
          <div class="row">
            <!-- Grup 1: Transaksi & Operasional -->
            <div class="col-lg-4 col-md-6 mb-3 mb-lg-0">
              <div class="text-xs text-uppercase font-weight-bold text-muted mb-2"><i class="fas fa-shuttle-van mr-1"></i> Operasional &amp; Transaksi</div>
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
              <div class="text-xs text-uppercase font-weight-bold text-muted mb-2"><i class="fas fa-book mr-1"></i> Keuangan &amp; Pembukuan</div>
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
              <div class="text-xs text-uppercase font-weight-bold text-muted mb-2"><i class="fas fa-cogs mr-1"></i> Master Data &amp; Setting</div>
              <div class="list-group list-group-flush border rounded">
                <a href="../data_rekening/data_rekening_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-university text-secondary mr-2"></i>Setting Rekening &amp; Kasir</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
                <a href="../data_travel/data_travel_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-bus text-secondary mr-2"></i>Setting Unit Travel / Armada</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
                <a href="../data_tujuan_perjalanan/data_tujuan_perjalanan_view" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center text-sm">
                  <span><i class="fas fa-map text-secondary mr-2"></i>Setting Rute &amp; Tarif Perjalanan</span>
                  <i class="fas fa-chevron-right text-muted text-xs"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BARIS 8: DIAGNOSTIK KONEKSI & JARINGAN (COLLAPSIBLE) -->
      <div class="card card-outline card-secondary mb-4" style="border: 1px solid #b8b8b8; border-radius: 4px;">
        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center" data-toggle="collapse" data-target="#collapseDiag" style="cursor: pointer; border-bottom: 1px solid #e0e0e0;">
          <span class="font-weight-bold text-dark text-sm">
            <i class="fas fa-network-wired mr-2 text-muted"></i>Diagnostik Koneksi &amp; Latency Jaringan (Klik untuk Buka/Tutup)
          </span>
          <i class="fas fa-chevron-down text-muted text-xs"></i>
        </div>
        <div id="collapseDiag" class="collapse">
          <div class="card-body p-3">
            <div class="row">
              <div class="col-md-6 mb-3 mb-md-0">
                <div class="border rounded p-2 bg-white">
                  <div class="font-weight-bold text-sm mb-1 text-muted"><i class="fas fa-wifi mr-1"></i> Live Latency Ping Server (ms)</div>
                  <div style="height: 180px; position: relative;">
                    <canvas id="chartPing" style="height:100%;"></canvas>
                  </div>
                  <small class="text-muted text-xs">Sampling latency otomatis tiap 5 detik.</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 bg-white">
                  <div class="font-weight-bold text-sm mb-1 text-muted"><i class="fas fa-chart-line mr-1"></i> Live Downlink Client (Browser)</div>
                  <div style="height: 180px; position: relative;">
                    <canvas id="chartNetInfo" style="height:100%;"></canvas>
                  </div>
                  <small class="text-muted text-xs">Kualitas estimasi bandwidth downlink browser klien.</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL SEMUA PETUGAS ONLINE -->
      <div class="modal fade" id="modalUsersOnline" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px;">
            <div class="modal-header py-2 px-3 bg-light" style="border-bottom: 1px solid #e0e0e0;">
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
                      <td class="text-center font-weight-bold text-muted"><?= (int)$u['id_users'] ?></td>
                      <td class="font-weight-bold"><i class="fas fa-user-circle text-muted mr-1"></i><?= htmlspecialchars($u['username']) ?></td>
                      <td><?= htmlspecialchars($u['asal_po']) ?></td>
                      <td><span class="badge badge-light border font-weight-normal"><?= htmlspecialchars($u['role']) ?></span></td>
                      <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($u['last_activity']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($onlineUsersList) === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada user online saat ini.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light" style="border-top: 1px solid #e0e0e0;">
              <button class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include '../inc/footer.php'; ?>

<!-- Chart.js 4.4.1 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
// =========================================================================
// DATA DARI SERVER UNTUK CHART.JS
// =========================================================================
const monthLabels = <?= json_encode(array_values($bulanLabels)) ?>;
const monthTotals = <?= json_encode(array_values($bulanTotals)) ?>;
const monthTiket = <?= json_encode(array_values($bulanTiket)) ?>;
const monthPaket = <?= json_encode(array_values($bulanPaket)) ?>;
const monthPengeluaran = <?= json_encode(array_values($bulanPengeluaran)) ?>;
const monthNet = <?= json_encode(array_values($bulanNet)) ?>;

const bankLabels = <?= json_encode($bankChartLabels) ?>;
const bankData = <?= json_encode($bankChartData) ?>;

const travelLabels = <?= json_encode($travelChartLabels) ?>;
const travelData = <?= json_encode($travelChartData) ?>;

const komposisiLabels = ['Tiket Penumpang', 'Kargo / Paket'];
const komposisiData = [<?= (float)$totTiket ?>, <?= (float)$totPaket ?>];

// Palette Warna Modern Paper White
const chartColors = [
  '#0d9f4f', '#0284c7', '#0b3b7b', '#f59e0b', '#8b5cf6',
  '#10b981', '#ec4899', '#6366f1', '#14b8a6', '#f97316'
];

let mainChartInstance = null;
let bankChartInstance = null;
let travelChartInstance = null;
let komposisiChartInstance = null;

// =========================================================================
// 1. MAIN 6-MONTH COMBO CHART (AREA + BAR + LINE)
// =========================================================================
function initMainChart(){
  const canvas = document.getElementById('chartBulan');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  // Gradien Lembut untuk Total Pendapatan
  const gradIncome = ctx.createLinearGradient(0, 0, 0, 260);
  gradIncome.addColorStop(0, 'rgba(13, 159, 79, 0.28)');
  gradIncome.addColorStop(1, 'rgba(13, 159, 79, 0.01)');

  const gradNet = ctx.createLinearGradient(0, 0, 0, 260);
  gradNet.addColorStop(0, 'rgba(11, 59, 123, 0.22)');
  gradNet.addColorStop(1, 'rgba(11, 59, 123, 0.01)');

  mainChartInstance = new Chart(ctx, {
    data: {
      labels: monthLabels,
      datasets: [
        {
          type: 'line',
          label: 'Total Pendapatan',
          data: monthTotals,
          borderColor: '#0d9f4f',
          backgroundColor: gradIncome,
          borderWidth: 2.5,
          fill: true,
          tension: 0.35,
          pointBackgroundColor: '#ffffff',
          pointBorderColor: '#0d9f4f',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          order: 1
        },
        {
          type: 'line',
          label: 'Laba Bersih',
          data: monthNet,
          borderColor: '#0b3b7b',
          backgroundColor: gradNet,
          borderWidth: 2,
          borderDash: [5, 4],
          fill: false,
          tension: 0.35,
          pointBackgroundColor: '#ffffff',
          pointBorderColor: '#0b3b7b',
          pointBorderWidth: 2,
          pointRadius: 3.5,
          pointHoverRadius: 5.5,
          order: 2
        },
        {
          type: 'line',
          label: 'Pengeluaran',
          data: monthPengeluaran,
          borderColor: '#dc2626',
          backgroundColor: 'transparent',
          borderWidth: 2,
          tension: 0.35,
          pointBackgroundColor: '#ffffff',
          pointBorderColor: '#dc2626',
          pointBorderWidth: 2,
          pointRadius: 3,
          pointHoverRadius: 5,
          order: 3
        },
        {
          type: 'bar',
          label: 'Tiket Penumpang',
          data: monthTiket,
          backgroundColor: 'rgba(52, 211, 153, 0.75)',
          borderColor: '#10b981',
          borderWidth: 1,
          borderRadius: 4,
          order: 4
        },
        {
          type: 'bar',
          label: 'Kargo & Paket',
          data: monthPaket,
          backgroundColor: 'rgba(56, 189, 248, 0.75)',
          borderColor: '#0284c7',
          borderWidth: 1,
          borderRadius: 4,
          order: 5
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: {
            boxWidth: 12,
            boxHeight: 12,
            usePointStyle: true,
            pointStyle: 'circle',
            font: { size: 11, family: 'inherit', weight: '500' },
            padding: 12
          }
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.92)',
          padding: 12,
          cornerRadius: 4,
          titleFont: { size: 12, weight: 'bold' },
          bodyFont: { size: 11 },
          callbacks: {
            label: function(c) {
              const val = c.raw || 0;
              return '  ' + c.dataset.label + ': Rp ' + Number(val).toLocaleString('id-ID');
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 11 } }
        },
        y: {
          beginAtZero: true,
          grid: { color: '#f1f5f9' },
          ticks: {
            font: { size: 11 },
            callback: function(v) {
              if (v >= 1000000000) return (v / 1000000000).toFixed(1) + ' M';
              if (v >= 1000000) return (v / 1000000).toFixed(0) + ' jt';
              if (v >= 1000) return (v / 1000).toFixed(0) + ' rb';
              return v;
            }
          }
        }
      }
    }
  });
}

function switchMainChart(mode, btnEl){
  if (!mainChartInstance) return;
  document.querySelectorAll('.chart-toggle-btn').forEach(b => b.classList.remove('active'));
  if (btnEl) btnEl.classList.add('active');

  const ds = mainChartInstance.data.datasets;
  // ds[0]: Total Pendapatan
  // ds[1]: Laba Bersih
  // ds[2]: Pengeluaran
  // ds[3]: Tiket
  // ds[4]: Paket

  if (mode === 'all') {
    ds[0].hidden = false;
    ds[1].hidden = false;
    ds[2].hidden = false;
    ds[3].hidden = false;
    ds[4].hidden = false;
  } else if (mode === 'net') {
    ds[0].hidden = false;
    ds[1].hidden = false;
    ds[2].hidden = false;
    ds[3].hidden = true;
    ds[4].hidden = true;
  } else if (mode === 'breakdown') {
    ds[0].hidden = true;
    ds[1].hidden = true;
    ds[2].hidden = true;
    ds[3].hidden = false;
    ds[4].hidden = false;
  }
  mainChartInstance.update();
}

// =========================================================================
// 2. DOUGHNUT CHART PER BANK / KASIR
// =========================================================================
function initBankChart(){
  const canvas = document.getElementById('chartBankDoughnut');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  bankChartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: bankLabels.length ? bankLabels : ['Belum Ada'],
      datasets: [{
        data: bankData.length ? bankData : [1],
        backgroundColor: chartColors.slice(0, bankLabels.length || 1),
        borderColor: '#ffffff',
        borderWidth: 2,
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: {
          display: true,
          position: 'right',
          labels: {
            boxWidth: 10,
            boxHeight: 10,
            font: { size: 10 },
            padding: 6
          }
        },
        tooltip: {
          callbacks: {
            label: function(c) {
              const val = c.raw || 0;
              return ' ' + c.label + ': Rp ' + Number(val).toLocaleString('id-ID');
            }
          }
        }
      }
    }
  });
}

// =========================================================================
// 3. HORIZONTAL BAR CHART ARMADA TRAVEL
// =========================================================================
function initTravelChart(){
  const canvas = document.getElementById('chartTravelBar');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  travelChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: travelLabels.length ? travelLabels : ['Belum Ada'],
      datasets: [{
        label: 'Omzet Unit',
        data: travelData.length ? travelData : [0],
        backgroundColor: '#0d9f4f',
        borderColor: '#076e34',
        borderWidth: 1,
        borderRadius: 4
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(c) {
              const val = c.raw || 0;
              return ' Omzet: Rp ' + Number(val).toLocaleString('id-ID');
            }
          }
        }
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: { color: '#f1f5f9' },
          ticks: {
            font: { size: 10 },
            callback: (v) => (v >= 1000000 ? (v/1000000)+' jt' : (v>=1000 ? (v/1000)+' rb' : v))
          }
        },
        y: {
          grid: { display: false },
          ticks: { font: { size: 10 } }
        }
      }
    }
  });
}

// =========================================================================
// 4. DOUGHNUT CHART KOMPOSISI TIKET VS PAKET
// =========================================================================
function initKomposisiChart(){
  const canvas = document.getElementById('chartKomposisiDoughnut');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  komposisiChartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: komposisiLabels,
      datasets: [{
        data: komposisiData,
        backgroundColor: ['#0d9f4f', '#0284c7'],
        borderColor: '#ffffff',
        borderWidth: 2,
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: {
          display: true,
          position: 'right',
          labels: {
            boxWidth: 10,
            boxHeight: 10,
            font: { size: 11 },
            padding: 8
          }
        },
        tooltip: {
          callbacks: {
            label: function(c) {
              const val = c.raw || 0;
              return ' ' + c.label + ': Rp ' + Number(val).toLocaleString('id-ID');
            }
          }
        }
      }
    }
  });
}

// =========================================================================
// 5. LIVE NETWORK & PING CHARTS
// =========================================================================
let pingChart, netChart;
const pingData = { labels: [], datasets: [{ label: 'Ping (ms)', data: [], borderColor: '#0d9f4f', backgroundColor: 'rgba(13, 159, 79, 0.1)', fill: true, tension: 0.3 }] };
const netData = { labels: [], datasets: [{ label: 'Downlink (Mbps)', data: [], borderColor: '#0284c7', backgroundColor: 'rgba(2, 132, 199, 0.1)', fill: true, tension: 0.3 }] };

function initLivePing(){
  const el = document.getElementById('chartPing');
  if (!el) return;
  pingChart = new Chart(el.getContext('2d'), {
    type: 'line',
    data: pingData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
        x: { grid: { display: false } }
      },
      plugins: { legend: { display: false } }
    }
  });
  samplePing();
  setInterval(samplePing, 5000);
}

async function samplePing(){
  const start = performance.now();
  try {
    await fetch('../assets/ping.php?rand=' + Math.random(), { cache: 'no-store' });
    const ms = Math.round(performance.now() - start);
    const ts = new Date();
    pushChartPoint(pingData, ts.toLocaleTimeString(), ms, 20);
    if (pingChart) pingChart.update();
  } catch(e){}
}

function initLiveNetInfo(){
  const el = document.getElementById('chartNetInfo');
  if (!el) return;
  netChart = new Chart(el.getContext('2d'), {
    type: 'line',
    data: netData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
        x: { grid: { display: false } }
      },
      plugins: { legend: { display: false } }
    }
  });
  sampleNetInfo();
  setInterval(sampleNetInfo, 5000);
}

function sampleNetInfo(){
  const ts = new Date();
  const nav = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  let down = null;
  if (nav) down = nav.downlink || null;
  pushChartPoint(netData, ts.toLocaleTimeString(), down ?? 0, 20);
  if (netChart) netChart.update();
}

function pushChartPoint(datasetObj, label, value, maxPoints){
  datasetObj.labels.push(label);
  datasetObj.datasets[0].data.push(value);
  if (datasetObj.labels.length > maxPoints){
    datasetObj.labels.shift();
    datasetObj.datasets[0].data.shift();
  }
}

// Inisialisasi Saat Halaman Siap
function initAllDashboardCharts(){
  initMainChart();
  initBankChart();
  initTravelChart();
  initKomposisiChart();
  initLivePing();
  initLiveNetInfo();

  // Resize listener for tabs
  $('a[data-toggle="tab"]').on('shown.bs.tab', function(e){
    if (bankChartInstance) bankChartInstance.resize();
    if (travelChartInstance) travelChartInstance.resize();
    if (komposisiChartInstance) komposisiChartInstance.resize();
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAllDashboardCharts);
} else {
  initAllDashboardCharts();
}
</script>
