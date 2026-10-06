<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../assets/session.php';
require_once '../inc/koneksi.php';
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';

// Ambil session user (konsisten dengan modul lain)
$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$id_users = isset($user['id_users']) ? $user['id_users'] : '';
$asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';
$role = isset($user['role']) ? $user['role'] : '';

// Filter bulan dan tahun
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Kondisi filter berdasarkan bulan dan tahun
$whereBulanTahunPemesanan = "MONTH(p.tanggal_pemesanan) = $bulan AND YEAR(p.tanggal_pemesanan) = $tahun";
$whereBulanTahunPengiriman = "MONTH(pg.tanggal_pengiriman) = $bulan AND YEAR(pg.tanggal_pengiriman) = $tahun";

// Filter berdasarkan role
if ($role !== 'super admin') {
  // data_pemesanan menyimpan id user pada kolom `username`
  $whereBulanTahunPemesanan .= " AND p.username = $id_users AND p.asal_po = '$asal_po'";
  // data_pengiriman menggunakan kolom `asal_po_id`
  $whereBulanTahunPengiriman .= " AND pg.user_id = $id_users AND pg.asal_po_id = '$asal_po'";
}

// Ambil semua tujuan perjalanan terlebih dahulu
$all_tujuan = [];
$sql_all_tujuan = "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan ORDER BY nama_tujuan";
$res_all_tujuan = $conn->query($sql_all_tujuan);
if ($res_all_tujuan) {
  while ($row = $res_all_tujuan->fetch_assoc()) {
    $all_tujuan[$row['id_tujuan_perjalanan']] = $row['nama_tujuan'];
  }
}

// Pendapatan per Tujuan Perjalanan (lama)
// -----------------------------
$tujuanPendapatan = [];

// Inisialisasi semua tujuan dengan 0
foreach ($all_tujuan as $id => $nama) {
  $tujuanPendapatan[$nama] = [
    'pemesanan' => 0,
    'pengiriman' => 0,
    'total' => 0
  ];
}

// Pemesanan
$sql_tujuan_pemesanan = "SELECT p.tujuan_id, SUM(p.harga_id) as total FROM data_pemesanan p 
                         WHERE p.status_pembayaran_id IN (SELECT id_status_pembayaran FROM data_status_pembayaran WHERE status_pembayaran = 'Lunas') 
                         AND $whereBulanTahunPemesanan GROUP BY p.tujuan_id";
$res_tujuan_pemesanan = $conn->query($sql_tujuan_pemesanan);
if ($res_tujuan_pemesanan) {
  while ($row = $res_tujuan_pemesanan->fetch_assoc()) {
    $tujuan_id = $row['tujuan_id'];
    $tujuan = isset($all_tujuan[$tujuan_id]) ? $all_tujuan[$tujuan_id] : 'Lainnya';
    if (!isset($tujuanPendapatan[$tujuan])) {
      $tujuanPendapatan[$tujuan] = [
        'pemesanan' => 0,
        'pengiriman' => 0,
        'total' => 0
      ];
    }
    $tujuanPendapatan[$tujuan]['pemesanan'] += $row['total'];
    $tujuanPendapatan[$tujuan]['total'] += $row['total'];
  }
}

// Pengiriman (lama)
$sql_tujuan_pengiriman = "SELECT pg.tujuan_id, SUM(pg.jumlah) as total FROM data_pengiriman pg 
                          WHERE pg.status_pembayaran_id IN (SELECT id_status_pembayaran FROM data_status_pembayaran WHERE status_pembayaran = 'Lunas') 
                          AND $whereBulanTahunPengiriman GROUP BY pg.tujuan_id";
$res_tujuan_pengiriman = $conn->query($sql_tujuan_pengiriman);
if ($res_tujuan_pengiriman) {
  while ($row = $res_tujuan_pengiriman->fetch_assoc()) {
    $tujuan_id = $row['tujuan_id'];
    $tujuan = isset($all_tujuan[$tujuan_id]) ? $all_tujuan[$tujuan_id] : 'Lainnya';
    if (!isset($tujuanPendapatan[$tujuan])) {
      $tujuanPendapatan[$tujuan] = [
        'pemesanan' => 0,
        'pengiriman' => 0,
        'total' => 0
      ];
    }
    $tujuanPendapatan[$tujuan]['pengiriman'] += $row['total'];
    $tujuanPendapatan[$tujuan]['total'] += $row['total'];
  }
}

// =======================
// REKALKULASI JSON MULTI-PAYMENT
// =======================
$tujuanPendapatan = [];
foreach ($all_tujuan as $id => $nama) {
  $tujuanPendapatan[$nama] = ['pemesanan'=>0,'pengiriman'=>0,'total'=>0];
}

$startMonth = intval($bulan);
$startYear  = intval($tahun);
$filterMonthYear = function($date) use ($startMonth,$startYear){
  return (intval(date('n', strtotime($date))) == $startMonth && intval(date('Y', strtotime($date))) == $startYear);
};

$whereRolePem = " WHERE 1=1";
$whereRolePeng = " WHERE 1=1";
if ($role !== 'super admin') {
  // data_pemesanan menyimpan kolom id user pada field `username` (id_users)
  $whereRolePem  .= " AND pm.username = $id_users AND pm.asal_po = '$asal_po'";
  // data_pengiriman menggunakan kolom `asal_po_id`
  $whereRolePeng .= " AND pg.user_id = $id_users AND pg.asal_po_id = '$asal_po'";
}

// --- Pemesanan
$sqlPemJson = "SELECT pm.tujuan_id, pm.payment_methods, pm.harga_id, pm.tanggal_pemesanan
               FROM data_pemesanan pm $whereRolePem";
$resJson = $conn->query($sqlPemJson);
if($resJson){
  while($row=$resJson->fetch_assoc()){
    $tujuan = $all_tujuan[$row['tujuan_id']] ?? 'Lainnya';
    if(!isset($tujuanPendapatan[$tujuan])) $tujuanPendapatan[$tujuan]=['pemesanan'=>0,'pengiriman'=>0,'total'=>0];
    if($row['payment_methods']){
      $arr=json_decode($row['payment_methods'],true);
      if(is_array($arr)){
        foreach($arr as $p){
          $pDate = $p['payment_date'] ?? $row['tanggal_pemesanan'];
          if(!$filterMonthYear($pDate)) continue;
          $amt=floatval($p['amount']??0);
          $tujuanPendapatan[$tujuan]['pemesanan'] += $amt;
          $tujuanPendapatan[$tujuan]['total'] += $amt;
        }
        continue;
      }
    }
    if($filterMonthYear($row['tanggal_pemesanan'])){
      $amt=floatval($row['harga_id']);
      $tujuanPendapatan[$tujuan]['pemesanan'] += $amt;
      $tujuanPendapatan[$tujuan]['total'] += $amt;
    }
  }
}

// --- Pengiriman
$sqlPengJson = "SELECT pg.tujuan_id, pg.payment_methods, pg.jumlah, pg.tanggal_pengiriman
                FROM data_pengiriman pg $whereRolePeng";
$resJson2 = $conn->query($sqlPengJson);
if($resJson2){
  while($row=$resJson2->fetch_assoc()){
    $tujuan = $all_tujuan[$row['tujuan_id']] ?? 'Lainnya';
    if(!isset($tujuanPendapatan[$tujuan])) $tujuanPendapatan[$tujuan]=['pemesanan'=>0,'pengiriman'=>0,'total'=>0];
    if($row['payment_methods']){
      $arr=json_decode($row['payment_methods'],true);
      if(is_array($arr)){
        foreach($arr as $p){
          $pDate = $p['payment_date'] ?? $row['tanggal_pengiriman'];
          if(!$filterMonthYear($pDate)) continue;
          $amt=floatval($p['amount']??0);
          $tujuanPendapatan[$tujuan]['pengiriman'] += $amt;
          $tujuanPendapatan[$tujuan]['total'] += $amt;
        }
        continue;
      }
    }
    if($filterMonthYear($row['tanggal_pengiriman'])){
      $amt=floatval($row['jumlah']);
      $tujuanPendapatan[$tujuan]['pengiriman'] += $amt;
      $tujuanPendapatan[$tujuan]['total'] += $amt;
    }
  }
}

// Hapus tujuan dengan pendapatan 0
foreach ($tujuanPendapatan as $tujuan => $data) {
  if ($data['total'] == 0) {
    unset($tujuanPendapatan[$tujuan]);
  }
}

// Urutkan berdasarkan total pendapatan (descending)
uasort($tujuanPendapatan, function($a, $b) {
  return $b['total'] <=> $a['total'];
});

// Hitung total keseluruhan
$total_pemesanan = 0;
$total_pengiriman = 0;
$total_pendapatan = 0;

foreach ($tujuanPendapatan as $data) {
  $total_pemesanan += $data['pemesanan'];
  $total_pengiriman += $data['pengiriman'];
  $total_pendapatan += $data['total'];
}

// Judul halaman
$page_title = "Laporan Pendapatan per Rute & Kota Tujuan";
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
            <i class="fas fa-location-dot"></i>
          </div>
          <div>
            <h1 class="report-title">Laporan Pendapatan per Rute & Kota Tujuan</h1>
            <p class="report-subtitle">Analisis kontribusi omzet pemesanan tiket dan kargo berdasarkan kota destinasi</p>
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
        <!-- Filter Form (Paper White v2.0) -->
        <div class="card filter-card-flat">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="fas fa-filter text-muted mr-1"></i> Filter Periode Bulan & Tahun</div>
            <a href="pendapatan_lokasi.php" class="btn btn-xs btn-outline-secondary" style="border-radius:3px;">
              <i class="fas fa-undo mr-1"></i> Reset Filter
            </a>
          </div>
          <div class="card-body">
            <form action="" method="GET" class="mb-0">
              <div class="row align-items-end">
                <div class="col-md-3">
                  <label class="filter-label" for="bulan">Pilih Bulan</label>
                  <select name="bulan" id="bulan" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                      <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="filter-label" for="tahun">Pilih Tahun</label>
                  <select name="tahun" id="tahun" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                    <?php for ($i = date('Y') - 5; $i <= date('Y') + 5; $i++): ?>
                      <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>>
                        <?= $i ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn btn-sm btn-block font-weight-bold" style="background-color: #0d9f4f; color: #fff; border: 1px solid #0d9f4f; border-radius: 4px; height: 31px;">
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
              <span class="info-box-icon"><i class="fas fa-ticket text-success"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Pendapatan Tiket</span>
                <span class="info-box-number text-success">Rp <?= number_format($total_pemesanan, 0, ',', '.') ?></span>
                <span class="text-muted" style="font-size: 0.8rem;">
                  <?= ($total_pendapatan > 0) ? number_format($total_pemesanan / $total_pendapatan * 100, 1) : 0 ?>% dari total pendapatan
                </span>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-box">
              <span class="info-box-icon"><i class="fas fa-box text-info"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Pendapatan Paket Kargo</span>
                <span class="info-box-number text-info">Rp <?= number_format($total_pengiriman, 0, ',', '.') ?></span>
                <span class="text-muted" style="font-size: 0.8rem;">
                  <?= ($total_pendapatan > 0) ? number_format($total_pengiriman / $total_pendapatan * 100, 1) : 0 ?>% dari total pendapatan
                </span>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-box">
              <span class="info-box-icon"><i class="fas fa-money-bill-wave text-primary"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Omzet Rute</span>
                <span class="info-box-number text-primary">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></span>
                <span class="text-muted" style="font-size: 0.8rem;">
                  Periode: <?= date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="row" id="printArea">
        <div class="col-md-8">
          <div class="card filter-card-flat">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#ffffff; border-bottom: 1px solid #e0e0e0;">
              <div style="font-weight: 700; color: #202124; font-size: 0.95rem;">
                <i class="fas fa-table mr-1 text-muted"></i> 
                Tabel Pendapatan per Lokasi Tujuan
              </div>
              <span class="badge" style="background: #ffffff; border: 1px solid #b8b8b8; color: #555; padding: 4px 8px;">
                Total Rute: <?= count($tujuanPendapatan) ?>
              </span>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive table-excel-container" style="border: none; border-radius: 0;">
                <table class="table table-bordered table-hover table-excel mb-0" id="tablePendapatanLokasi">
                  <thead>
                    <tr>
                      <th class="text-center" style="width: 45px;">No</th>
                      <th>Lokasi / Rute Tujuan</th>
                      <th class="text-right" style="width: 150px;">Pendapatan Tiket</th>
                      <th class="text-right" style="width: 150px;">Pendapatan Paket</th>
                      <th class="text-right" style="width: 160px;">Total Omzet</th>
                      <th class="text-center" style="width: 130px;">Kontribusi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($tujuanPendapatan)): ?>
                      <tr>
                        <td colspan="6" class="text-center text-muted py-3">Tidak ada data pendapatan untuk periode ini</td>
                      </tr>
                    <?php else: ?>
                      <?php $no = 1; foreach ($tujuanPendapatan as $tujuan => $data): ?>
                        <tr>
                          <td class="text-center"><?= $no++ ?></td>
                          <td><strong style="color: #202124;"><i class="fas fa-map-marker-alt text-danger mr-1"></i> <?= htmlspecialchars($tujuan) ?></strong></td>
                          <td class="text-right">Rp <?= number_format($data['pemesanan'], 0, ',', '.') ?></td>
                          <td class="text-right">Rp <?= number_format($data['pengiriman'], 0, ',', '.') ?></td>
                          <td class="text-right font-weight-bold" style="color: #0d9f4f;">Rp <?= number_format($data['total'], 0, ',', '.') ?></td>
                          <td class="text-center">
                            <?php $percentage = ($total_pendapatan > 0) ? ($data['total'] / $total_pendapatan * 100) : 0; ?>
                            <span class="badge" style="background: rgba(13,159,79,0.1); color: #0d9f4f; border: 1px solid rgba(13,159,79,0.3); font-weight: 600; padding: 4px 8px;">
                              <?= number_format($percentage, 1) ?>%
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                  <tfoot>
                    <tr>
                      <th colspan="2" class="text-right" style="font-weight: 700;">TOTAL OMZET:</th>
                      <th class="text-right" style="font-weight: 700;">Rp <?= number_format($total_pemesanan, 0, ',', '.') ?></th>
                      <th class="text-right" style="font-weight: 700;">Rp <?= number_format($total_pengiriman, 0, ',', '.') ?></th>
                      <th class="text-right" style="font-weight: 700; color: #0d9f4f; font-size: 0.95rem;">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></th>
                      <th class="text-center" style="font-weight: 700;">100%</th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card filter-card-flat">
            <div class="card-header" style="background:#ffffff; border-bottom: 1px solid #e0e0e0;">
              <h3 class="card-title font-weight-bold" style="font-size: 0.95rem; color: #202124;">
                <i class="fas fa-chart-pie mr-1 text-muted"></i> Distribusi Omzet per Rute
              </h3>
            </div>
            <div class="card-body">
              <canvas id="chartTujuan" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div>
          <div class="card filter-card-flat mt-3">
            <div class="card-header" style="background:#ffffff; border-bottom: 1px solid #e0e0e0;">
              <h3 class="card-title font-weight-bold" style="font-size: 0.95rem; color: #202124;">
                <i class="fas fa-info-circle mr-1 text-muted"></i> Ikhtisar Kinerja Rute
              </h3>
            </div>
            <div class="card-body" style="font-size: 0.85rem;">
              <p class="mb-2"><i class="fas fa-calendar-check text-success mr-1"></i> Periode: <strong><?= date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) ?></strong></p>
              <p class="mb-2"><i class="fas fa-check-circle text-success mr-1"></i> Berdasarkan transaksi pemesanan & paket kargo berstatus <strong>Lunas</strong>.</p>
              <p class="mb-0"><i class="fas fa-trophy text-warning mr-1"></i> Rute Tertinggi: 
                <?php 
                if (!empty($tujuanPendapatan)) {
                  reset($tujuanPendapatan);
                  $top_location = key($tujuanPendapatan);
                  $top_amount = current($tujuanPendapatan)['total'];
                  echo "<strong>" . htmlspecialchars($top_location) . "</strong> (Rp " . number_format($top_amount, 0, ',', '.') . ")"; 
                } else {
                  echo "-";
                }
                ?>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
function exportToExcel() {
  let table = document.getElementById("tablePendapatanLokasi");
  let html = table.outerHTML;
  let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
  let downloadLink = document.createElement("a");
  document.body.appendChild(downloadLink);
  downloadLink.href = url;
  downloadLink.download = 'Laporan_Pendapatan_Rute_<?= $bulan ?>_<?= $tahun ?>.xls';
  downloadLink.click();
  document.body.removeChild(downloadLink);
}
</script>

<?php include '../inc/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Data untuk chart
  var tujuanLabels = [];
  var tujuanData = [];
  var backgroundColors = [
    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#5a5c69', '#6f42c1', '#fd7e14', '#20c997', '#6c757d',
    '#007bff', '#6610f2', '#17a2b8', '#ffc107', '#dc3545', '#343a40', '#7952b3', '#ff851b', '#39cccc', '#605ca8'
  ];

  <?php foreach ($tujuanPendapatan as $tujuan => $data): ?>
    tujuanLabels.push('<?= addslashes($tujuan) ?>');
    tujuanData.push(<?= $data['total'] ?>);
  <?php endforeach; ?>

  // Chart Tujuan
  var ctx = document.getElementById('chartTujuan').getContext('2d');
  var chartTujuan = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: tujuanLabels,
      datasets: [{
        data: tujuanData,
        backgroundColor: backgroundColors.slice(0, tujuanLabels.length),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          labels: {
            boxWidth: 12
          }
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              var label = context.label || '';
              var value = context.raw;
              var total = context.dataset.data.reduce((a, b) => a + b, 0);
              var percentage = Math.round((value / total) * 100);
              return label + ': Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' (' + percentage + '%)';
            }
          }
        }
      }
    }
  });
</script>