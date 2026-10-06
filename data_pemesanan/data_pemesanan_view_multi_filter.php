<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';

$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$id_users = isset($user['id_users']) ? $user['id_users'] : '';
$asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';
$role = isset($user['role']) ? $user['role'] : '';

// Ambil filter dari GET
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$filter_asal_po = $_GET['filter_asal_po'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_plat = $_GET['filter_plat'] ?? '';
$filter_tujuan = $_GET['filter_tujuan'] ?? '';
$filter_metode = $_GET['filter_metode'] ?? '';
$filter_rekening = $_GET['filter_rekening'] ?? '';
$filter_harga_min = $_GET['filter_harga_min'] ?? '';
$filter_harga_max = $_GET['filter_harga_max'] ?? '';
$filter_keterangan = $_GET['filter_keterangan'] ?? '';

// Query data pemesanan
if ($role === 'super admin') {
  $sql = "SELECT pm.*, 
        pl.nip_sgt, 
        tr.no_plat, 
        tj.nama_tujuan, 
        sp.status_pembayaran, 
        mp.metode_pembayaran, 
        dr.nama_rekening, 
        u1.username AS username_text, 
        u2.username AS asal_po_text
    FROM data_pemesanan pm
    LEFT JOIN data_pelanggan pl ON pm.nip_sgt_id = pl.nip_sgt
    LEFT JOIN data_travel tr ON pm.no_plat_id = tr.no_plat
    LEFT JOIN data_tujuan_perjalanan tj ON pm.tujuan_id = tj.id_tujuan_perjalanan
    LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
    LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
    LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
    LEFT JOIN data_users u1 ON pm.username = u1.id_users
    LEFT JOIN data_users u2 ON pm.asal_po = u2.id_users";
} else {
  $sql = "SELECT pm.*, 
        pl.nip_sgt, 
        tr.no_plat, 
        tj.nama_tujuan, 
        sp.status_pembayaran, 
        mp.metode_pembayaran, 
        dr.nama_rekening, 
        u1.username AS username_text, 
        u2.username AS asal_po_text
    FROM data_pemesanan pm
    LEFT JOIN data_pelanggan pl ON pm.nip_sgt_id = pl.nip_sgt
    LEFT JOIN data_travel tr ON pm.no_plat_id = tr.no_plat
    LEFT JOIN data_tujuan_perjalanan tj ON pm.tujuan_id = tj.id_tujuan_perjalanan
    LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
    LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
    LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
    LEFT JOIN data_users u1 ON pm.username = u1.id_users
    LEFT JOIN data_users u2 ON pm.asal_po = u2.id_users
    WHERE pm.username = '" . $id_users . "' AND pm.asal_po = '" . $id_users . "'";
}

// Tambahkan filter WHERE jika ada
$where_conditions = [];
if ($role === 'super admin') {
  $where_conditions[] = "1=1"; // Base condition untuk super admin
} else {
  $where_conditions[] = "pm.username = '" . mysqli_real_escape_string($conn, $id_users) . "' AND pm.asal_po = '" . mysqli_real_escape_string($conn, $id_users) . "'";
}

// Filter tanggal
if ($tgl_dari && $tgl_sampai) {
  $where_conditions[] = "DATE(pm.tanggal_pemesanan) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} elseif ($tgl_dari) {
  $where_conditions[] = "DATE(pm.tanggal_pemesanan) >= '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
} elseif ($tgl_sampai) {
  $where_conditions[] = "DATE(pm.tanggal_pemesanan) <= '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
}

// Filter user
if ($filter_user) {
  $where_conditions[] = "pm.username = '" . mysqli_real_escape_string($conn, $filter_user) . "'";
}

// Filter asal PO
if ($filter_asal_po) {
  $where_conditions[] = "pm.asal_po = '" . mysqli_real_escape_string($conn, $filter_asal_po) . "'";
}

// Filter status pembayaran
if ($filter_status) {
  $where_conditions[] = "pm.status_pembayaran_id = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}

// Filter plat
if ($filter_plat) {
  $where_conditions[] = "pm.no_plat_id = '" . mysqli_real_escape_string($conn, $filter_plat) . "'";
}

// Filter tujuan
if ($filter_tujuan) {
  $where_conditions[] = "pm.tujuan_id = '" . mysqli_real_escape_string($conn, $filter_tujuan) . "'";
}

// Filter metode pembayaran
if ($filter_metode) {
  $where_conditions[] = "pm.metode_pembayaran_id = '" . mysqli_real_escape_string($conn, $filter_metode) . "'";
}

// Filter rekening
if ($filter_rekening) {
  $where_conditions[] = "pm.jenis_rekening_id = '" . mysqli_real_escape_string($conn, $filter_rekening) . "'";
}

// Filter harga
if ($filter_harga_min) {
  $where_conditions[] = "pm.harga_id >= '" . mysqli_real_escape_string($conn, $filter_harga_min) . "'";
}
if ($filter_harga_max) {
  $where_conditions[] = "pm.harga_id <= '" . mysqli_real_escape_string($conn, $filter_harga_max) . "'";
}

// Filter keterangan
if ($filter_keterangan) {
  $where_conditions[] = "pm.keterangan LIKE '%" . mysqli_real_escape_string($conn, $filter_keterangan) . "%'";
}

// Gabungkan WHERE conditions
if (!empty($where_conditions)) {
  $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Tambahkan ORDER BY
$sql .= " ORDER BY pm.tanggal_pemesanan DESC";
$result = $conn->query($sql);
if (!$result) {
  echo "<div class='alert alert-danger'>Gagal mengambil data pemesanan: " . htmlspecialchars(mysqli_error($conn)) . "</div>";
}

// Handle CRUD
if (isset($_POST['tambah'])) {
  $_POST['username'] = $id_users;
  $_POST['asal_po'] = $id_users;
  tambahPemesanan($_POST);
}
if (isset($_POST['edit'])) {
  $_POST['username'] = $id_users;
  $_POST['asal_po'] = $id_users;
  updatePemesanan($_POST);
}
if (isset($_GET['hapus'])) {
  deletePemesanan($_GET['hapus']);
}

// Ambil data master untuk mapping id ke label
$tujuanMap = [];
$res = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
while ($r = mysqli_fetch_assoc($res))
  $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];
$statusMap = [];
$res = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
while ($r = mysqli_fetch_assoc($res))
  $statusMap[$r['id_status_pembayaran']] = $r['status_pembayaran'];
$metodeMap = [];
$res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
while ($r = mysqli_fetch_assoc($res))
  $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran'];
$rekeningMap = [];
$res = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening");
while ($r = mysqli_fetch_assoc($res))
  $rekeningMap[$r['id_rekening']] = $r['nama_rekening'];
$userMap = [];
$res = mysqli_query($conn, "SELECT id_users, username FROM data_users");
while ($r = mysqli_fetch_assoc($res))
  $userMap[$r['id_users']] = $r['username'];
$asalPoMap = [];
$res = mysqli_query($conn, "SELECT id_users, asal_po FROM data_users");
while ($r = mysqli_fetch_assoc($res))
  $asalPoMap[$r['id_users']] = $r['asal_po'];

// Ambil data travel untuk mapping no_plat ke kelas
$travelMap = [];
$res = mysqli_query($conn, "SELECT no_plat, kelas FROM data_travel");
while ($r = mysqli_fetch_assoc($res))
  $travelMap[$r['no_plat']] = $r['kelas'];

// Hitung statistik
$total_pemesanan = $result ? mysqli_num_rows($result) : 0;
$pemesanan_hari_ini = 0;
$total_pendapatan = 0;
$bankPendapatan = [];
$today = date('Y-m-d');

if ($result) {
  mysqli_data_seek($result, 0);
  while ($row = mysqli_fetch_assoc($result)) {
    $isToday = isset($row['tanggal_pemesanan']) && substr($row['tanggal_pemesanan'], 0, 10) == $today;

    if ($isToday) {
      $pemesanan_hari_ini++;
    }
    // Kumpulkan semua pembayaran (multiple maupun single)
    $payments = [];
    if (!empty($row['payment_methods'])) {
      $tmp = json_decode($row['payment_methods'], true);
      if (is_array($tmp)) $payments = $tmp;
    }
    if (empty($payments)) {
      // Data lama single payment
      $payments[] = [
        'amount' => $row['harga_id'] ?? 0,
        'rekening_id' => $row['jenis_rekening_id'] ?? null,
        'payment_date' => substr($row['tanggal_pemesanan'], 0, 10)
      ];
    }

    // Akumulasi pendapatan HANYA ketika tanggal bayar = hari ini
    foreach ($payments as $pay) {
      $payDate = isset($pay['payment_date']) ? substr($pay['payment_date'], 0, 10) : substr($row['tanggal_pemesanan'], 0, 10);
      if ($payDate == $today) {
        $rekening_id = $pay['rekening_id'] ?? null;
        $amount = $pay['amount'] ?? 0;
        if ($rekening_id && isset($rekeningMap[$rekening_id])) {
          $bankName = $rekeningMap[$rekening_id];
          if (!isset($bankPendapatan[$bankName])) $bankPendapatan[$bankName] = 0;
          $bankPendapatan[$bankName] += $amount;
          $total_pendapatan += $amount;
        }
      }
    }
  }
  mysqli_data_seek($result, 0); // Reset pointer
}
?>
<div class="content-wrapper">
  <!-- Content Header -->
  <div class="content-header" style="padding: 15px 0; margin-bottom: 15px;">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 style="margin: 0; font-size: 1.8rem;">
            <i class="fas fa-receipt mr-2"></i>
            Data Pemesanan
          </h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right" style="margin: 0; padding: 0;">
            <li class="breadcrumb-item"><a href="../data_dashboard/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Data Pemesanan</li>
            <li class="breadcrumb-item" id="filterBreadcrumb" style="display: none;">
              <small class="text-info" id="filterBreadcrumbText"></small>
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content" style="padding: 0 15px;">
    <!-- Info Boxes -->
    <div class="row mb-3">
      <div class="col-lg-3 col-6">
        <div class="small-box bg-info compact">
          <div class="inner">
            <h3><?= number_format($total_pemesanan) ?></h3>
            <p>Total Pemesanan</p>
          </div>
          <div class="icon">
            <i class="fas fa-receipt"></i>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-success compact">
          <div class="inner">
            <h3><?= number_format($pemesanan_hari_ini) ?></h3>
            <p>Pemesanan Hari Ini</p>
          </div>
          <div class="icon">
            <i class="fas fa-calendar-day"></i>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-warning compact">
          <div class="inner">
            <h3>Rp <?= number_format($total_pendapatan) ?></h3>
            <p>Total Pendapatan</p>
          </div>
          <div class="icon">
            <i class="fas fa-money-bill-wave"></i>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-primary compact">
          <div class="inner">
            <h3><?= $role === 'super admin' ? 'Semua PO' : $asal_po ?></h3>
            <p>Asal PO</p>
          </div>
          <div class="icon">
            <i class="fas fa-building"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Pendapatan Per Bank -->
    <div class="row mb-3">
      <?php
      // Warna untuk setiap bank
      $bankColors = [
        'BANK BRI' => 'bg-success',
        'BANK BNI' => 'bg-warning', 
        'BANK MANDIRI' => 'bg-info',
        'CASH (KASIR) PO MATARAM' => 'bg-primary',
        'BANK BCA' => 'bg-warning',
        'CASH (KASIR) PO SUMBAWA' => 'bg-primary'
      ];
      
      // Tampilkan info box untuk setiap bank
      foreach ($bankColors as $bankName => $bgColor) {
        $pendapatan = isset($bankPendapatan[$bankName]) ? $bankPendapatan[$bankName] : 0;
        $finalBgColor = $pendapatan > 0 ? $bgColor : 'bg-secondary';
        $iconClass = $bankName === 'CASH (KASIR)' ? 'fas fa-money-bill' : 'fas fa-university';
      ?>
      <div class="col-lg-3 col-6">
        <div class="small-box <?= $finalBgColor ?> compact">
          <div class="inner">
            <h3>Rp <?= number_format($pendapatan) ?></h3>
            <p><?= $bankName ?></p>
          </div>
          <div class="icon">
            <i class="<?= $iconClass ?>"></i>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>

    <!-- Filter Card -->
    <div class="card mb-3">
      <div class="card-header bg-info text-white" style="cursor: pointer;" onclick="toggleFilter()">
        <h5 class="card-title mb-0">
          <i class="fas fa-filter mr-2"></i>
          Filter Data Pemesanan
          <i class="fas fa-chevron-down float-right" id="filterToggleIcon"></i>
        </h5>
      </div>
      <div class="card-body" id="filterBody">
      <div class="card-body">
        <form method="GET" id="filterForm">
          <div class="row">
            <div class="col-md-2">
              <div class="form-group">
                <label for="tgl_dari">Tanggal Dari</label>
                <input type="date" class="form-control" name="tgl_dari" id="tgl_dari" 
                       value="<?= $_GET['tgl_dari'] ?? '' ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="tgl_sampai">Tanggal Sampai</label>
                <input type="date" class="form-control" name="tgl_sampai" id="tgl_sampai" 
                       value="<?= $_GET['tgl_sampai'] ?? '' ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_user">User</label>
                <select class="form-control" name="filter_user" id="filter_user">
                  <option value="">-- Semua User --</option>
                  <?php
                  $resUsers = mysqli_query($conn, "SELECT id_users, username, nama_lengkap FROM data_users ORDER BY nama_lengkap, username");
                  while ($r = mysqli_fetch_assoc($resUsers)) {
                    $label = $r['nama_lengkap'] ? $r['nama_lengkap'] . ' (' . $r['username'] . ')' : $r['username'];
                    $selected = ($_GET['filter_user'] ?? '') == $r['id_users'] ? 'selected' : '';
                    echo '<option value="' . $r['id_users'] . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_asal_po">Asal PO</label>
                <select class="form-control" name="filter_asal_po" id="filter_asal_po">
                  <option value="">-- Semua PO --</option>
                  <?php
                  $resAsalPo = mysqli_query($conn, "SELECT DISTINCT asal_po FROM data_users WHERE asal_po IS NOT NULL AND asal_po != '' ORDER BY asal_po");
                  while ($r = mysqli_fetch_assoc($resAsalPo)) {
                    $selected = ($_GET['filter_asal_po'] ?? '') == $r['asal_po'] ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($r['asal_po']) . '" ' . $selected . '>' . htmlspecialchars($r['asal_po']) . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_status">Status Pembayaran</label>
                <select class="form-control" name="filter_status" id="filter_status">
                  <option value="">-- Semua Status --</option>
                  <?php
                  $resStatus = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran ORDER BY status_pembayaran");
                  while ($r = mysqli_fetch_assoc($resStatus)) {
                    $selected = ($_GET['filter_status'] ?? '') == $r['id_status_pembayaran'] ? 'selected' : '';
                    echo '<option value="' . $r['id_status_pembayaran'] . '" ' . $selected . '>' . htmlspecialchars($r['status_pembayaran']) . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_plat">No Plat</label>
                <select class="form-control" name="filter_plat" id="filter_plat">
                  <option value="">-- Semua Plat --</option>
                  <?php
                  $resPlat = mysqli_query($conn, "SELECT no_plat FROM data_travel ORDER BY no_plat");
                  while ($r = mysqli_fetch_assoc($resPlat)) {
                    $selected = ($_GET['filter_plat'] ?? '') == $r['no_plat'] ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($r['no_plat']) . '" ' . $selected . '>' . htmlspecialchars($r['no_plat']) . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_tujuan">Tujuan</label>
                <select class="form-control" name="filter_tujuan" id="filter_tujuan">
                  <option value="">-- Semua Tujuan --</option>
                  <?php
                  $resTujuan = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan ORDER BY nama_tujuan");
                  while ($r = mysqli_fetch_assoc($resTujuan)) {
                    $selected = ($_GET['filter_tujuan'] ?? '') == $r['id_tujuan_perjalanan'] ? 'selected' : '';
                    echo '<option value="' . $r['id_tujuan_perjalanan'] . '" ' . $selected . '>' . htmlspecialchars($r['nama_tujuan']) . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_metode">Metode Pembayaran</label>
                <select class="form-control" name="filter_metode" id="filter_metode">
                  <option value="">-- Semua Metode --</option>
                  <?php
                  $resMetode = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran ORDER BY metode_pembayaran");
                  while ($r = mysqli_fetch_assoc($resMetode)) {
                    $selected = ($_GET['filter_metode'] ?? '') == $r['id_metode_pembayaran'] ? 'selected' : '';
                    echo '<option value="' . $r['id_metode_pembayaran'] . '" ' . $selected . '>' . htmlspecialchars($r['metode_pembayaran']) . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_rekening">Rekening</label>
                <select class="form-control" name="filter_rekening" id="filter_rekening">
                  <option value="">-- Semua Rekening --</option>
                  <?php
                  $resRekening = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening ORDER BY nama_rekening");
                  while ($r = mysqli_fetch_assoc($resRekening)) {
                    $selected = ($_GET['filter_rekening'] ?? '') == $r['id_rekening'] ? 'selected' : '';
                    echo '<option value="' . $r['id_rekening'] . '" ' . $selected . '>' . htmlspecialchars($r['nama_rekening']) . '</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_harga_min">Harga Min</label>
                <input type="number" class="form-control" name="filter_harga_min" id="filter_harga_min" 
                       placeholder="Min" value="<?= $_GET['filter_harga_min'] ?? '' ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_harga_max">Harga Max</label>
                <input type="number" class="form-control" name="filter_harga_max" id="filter_harga_max" 
                       placeholder="Max" value="<?= $_GET['filter_harga_max'] ?? '' ?>">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label for="filter_keterangan">Keterangan</label>
                <input type="text" class="form-control" name="filter_keterangan" id="filter_keterangan" 
                       placeholder="Cari keterangan" value="<?= $_GET['filter_keterangan'] ?? '' ?>">
              </div>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-8">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-search mr-1"></i> Filter
              </button>
              <a href="data_pemesanan_view.php" class="btn btn-secondary">
                <i class="fas fa-refresh mr-1"></i> Reset
              </a>
              <button type="button" class="btn btn-outline-secondary" onclick="clearFilterPreference()">
                <i class="fas fa-trash mr-1"></i> Clear Saved
              </button>
              <button type="button" class="btn btn-success" onclick="exportFilteredData()">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
              </button>
            </div>
            <div class="col-md-4">
              <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-success btn-sm" onclick="quickFilter('lunas')">
                  <i class="fas fa-check mr-1"></i> Lunas
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" onclick="quickFilter('pending')">
                  <i class="fas fa-clock mr-1"></i> Pending
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="quickFilter('batal')">
                  <i class="fas fa-times mr-1"></i> Batal
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="quickFilter('today')">
                  <i class="fas fa-calendar-day mr-1"></i> Hari Ini
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Main Card -->
    <div class="card">
      <div class="card-header" style="padding: 12px 15px;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h3 class="card-title" style="margin: 0; font-size: 1.1rem;">
              <i class="fas fa-list mr-2"></i>
              Daftar Pemesanan
            </h3>
            <small class="text-muted" id="dataCount">Total: 0 data</small>
          </div>
          <a href="../laporan/daftar_keberangkatan_all.php" class="btn btn-warning btn-sm ml-2"">
            <i class=" fas fa-list-alt mr-1"></i> Daftar Keberangkatan hari ini
          </a>
          <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalForm">
            <i class="fas fa-plus-circle mr-1"></i> Tambah Pemesanan Tiket
          </button>
        </div>
      </div>
      <div class="card-body" style="padding: 15px;">
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">
                  <i class="fas fa-search"></i>
                </span>
              </div>
              <input type="text" class="form-control" id="tableSearch" placeholder="Cari dalam tabel...">
            </div>
          </div>
          <div class="col-md-6 text-right">
            <div class="btn-group" role="group">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleColumn('alamat')">
                <i class="fas fa-eye mr-1"></i> Alamat
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleColumn('ktp')">
                <i class="fas fa-eye mr-1"></i> KTP
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleColumn('hp')">
                <i class="fas fa-eye mr-1"></i> HP
              </button>
            </div>
            <span class="text-muted ml-2" id="tableInfo">Menampilkan semua data</span>
          </div>
        </div>
        <div class="table-responsive">
          <div id="activeFilterInfo" class="mb-2" style="display: none;">
            <small class="text-muted">
              <i class="fas fa-filter mr-1"></i>
              <span id="activeFilterText"></span>
            </small>
          </div>
          <table id="tablePemesanan" class="table table-bordered table-striped table-sm" data-server="1">
            <thead>
              <tr>
                <th style="padding: 8px;">ID</th>
                <th style="padding: 8px;">NIP/SGT</th>
                <th style="padding: 8px;">Nama</th>
                <th style="padding: 8px;">Alamat</th>
                <th style="padding: 8px;">No KTP</th>
                <th style="padding: 8px;">No HP</th>
                <th style="padding: 8px;">Tgl Pemesanan</th>
                <th style="padding: 8px;">Tgl Berangkat</th>
                <th style="padding: 8px;">No Plat</th>
                <th style="padding: 8px;">Kelas</th>
                <th style="padding: 8px;">Harga</th>
                <th style="padding: 8px;">Kursi</th>
                <th style="padding: 8px;">Tujuan</th>
                <th style="padding: 8px;">Status</th>
                <th style="padding: 8px;">Metode</th>
                <th style="padding: 8px;">Rekening</th>
                <th style="padding: 8px;">User</th>
                <th style="padding: 8px;">Asal PO</th>
                <th style="padding: 8px;">Lokasi</th>
                <th style="padding: 8px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td style="padding: 6px;"><span class="badge badge-secondary"><?= $row['id_pemesanan'] ?></span></td>
                    <td style="padding: 6px;"><span
                        class="badge badge-info"><?= htmlspecialchars($row['nip_sgt_id']) ?></span></td>
                    <td style="padding: 6px;"><strong><?= $row['nama_id'] ?></strong></td>
                    <td style="padding: 6px;"><?= $row['alamat_id'] ?></td>
                    <td style="padding: 6px;"><code><?= $row['no_ktp_id'] ?></code></td>
                    <td style="padding: 6px;">
                      <a href="tel:<?= $row['no_hp_id'] ?>" class="text-primary">
                        <i class="fas fa-phone mr-1"></i><?= $row['no_hp_id'] ?>
                      </a>
                    </td>
                    <td style="padding: 6px;"><span class="badge badge-light"><?= $row['tanggal_pemesanan'] ?></span></td>
                    <td style="padding: 6px;"><span class="badge badge-warning"><?= $row['tanggal_berangkat'] ?></span></td>
                    <td style="padding: 6px;"><span
                        class="badge badge-dark"><?= htmlspecialchars($row['no_plat_id']) ?></span></td>
                    <td style="padding: 6px;"><span
                        class="badge badge-info"><?= htmlspecialchars($row['kelas_id']) ?></span></td>
                    <td style="padding: 6px;"><strong class="text-success">Rp
                        <?= number_format($row['harga_id']) ?></strong></td>
                    <td style="padding: 6px;"><span class="badge badge-info"><?= $row['kursi'] ?></span></td>
                    <td style="padding: 6px;">
                      <span class="badge badge-success">
                        <?= isset($tujuanMap[$row['tujuan_id']]) ? htmlspecialchars($tujuanMap[$row['tujuan_id']]) : htmlspecialchars($row['tujuan_id']) ?>
                      </span>
                    </td>
                    <td style="padding: 6px;">
                      <?php
                      $status_class = 'badge-secondary';
                      if (isset($statusMap[$row['status_pembayaran_id']])) {
                        $status_text = $statusMap[$row['status_pembayaran_id']];
                        if (strpos(strtolower($status_text), 'lunas') !== false)
                          $status_class = 'badge-success';
                        elseif (strpos(strtolower($status_text), 'pending') !== false)
                          $status_class = 'badge-warning';
                        elseif (strpos(strtolower($status_text), 'batal') !== false)
                          $status_class = 'badge-danger';
                      }
                      ?>
                      <span class="badge <?= $status_class ?>">
                        <?= isset($statusMap[$row['status_pembayaran_id']]) ? htmlspecialchars($statusMap[$row['status_pembayaran_id']]) : htmlspecialchars($row['status_pembayaran_id']) ?>
                      </span>
                    </td>
                    <td style="padding: 6px;">
                      <?php
                        // Handle multiple payment methods display
                        $payment_methods_display = '';
                        if (!empty($row['payment_methods'])) {
                          try {
                            $payment_methods = json_decode($row['payment_methods'], true);
                            if (is_array($payment_methods) && count($payment_methods) > 0) {
                              $payment_methods_display = '<div class="multiple-payment">';
                              foreach ($payment_methods as $payment) {
                                $method_name = isset($metodeMap[$payment['method_id']]) ? $metodeMap[$payment['method_id']] : 'Unknown';
                                $rekening_name = isset($rekeningMap[$payment['rekening_id']]) ? $rekeningMap[$payment['rekening_id']] : 'Unknown';
                                $payment_methods_display .= '<div class="mb-1">';
                                $payment_methods_display .= '<span class="badge badge-info">' . htmlspecialchars($method_name) . '</span>';
                                $payment_methods_display .= '<span class="badge badge-secondary ml-1">Rp ' . number_format($payment['amount']) . '</span>';
                                $payment_methods_display .= '<br><small class="text-muted">' . htmlspecialchars($rekening_name) . '</small>';
                                $payment_methods_display .= '</div>';
                              }
                              $payment_methods_display .= '</div>';
                            } else {
                              // Single payment method
                              $method_name = isset($metodeMap[$row['metode_pembayaran_id']]) ? $metodeMap[$row['metode_pembayaran_id']] : htmlspecialchars($row['metode_pembayaran_id']);
                              $payment_methods_display = '<span class="badge badge-info">' . $method_name . '</span>';
                            }
                          } catch (Exception $e) {
                            // Fallback to single payment method
                            $method_name = isset($metodeMap[$row['metode_pembayaran_id']]) ? $metodeMap[$row['metode_pembayaran_id']] : htmlspecialchars($row['metode_pembayaran_id']);
                            $payment_methods_display = '<span class="badge badge-info">' . $method_name . '</span>';
                          }
                        } else {
                          // Fallback to single payment method
                          $method_name = isset($metodeMap[$row['metode_pembayaran_id']]) ? $metodeMap[$row['metode_pembayaran_id']] : htmlspecialchars($row['metode_pembayaran_id']);
                          $payment_methods_display = '<span class="badge badge-info">' . $method_name . '</span>';
                        }
                        echo $payment_methods_display;
                      ?>
                    </td>
                    <td style="padding: 6px;">
                      <span class="badge badge-dark">
                        <?= isset($rekeningMap[$row['jenis_rekening_id']]) ? htmlspecialchars($rekeningMap[$row['jenis_rekening_id']]) : htmlspecialchars($row['jenis_rekening_id']) ?>
                      </span>
                    </td>
                    <td style="padding: 6px;">
                      <?= isset($userMap[$row['username']]) ? htmlspecialchars($userMap[$row['username']]) : htmlspecialchars($row['username']) ?>
                    </td>
                    <td style="padding: 6px;">
                      <?= isset($asalPoMap[$row['asal_po']]) ? htmlspecialchars($asalPoMap[$row['asal_po']]) : htmlspecialchars($row['asal_po']) ?>
                    </td>
                    <td style="padding: 6px;"><?= $row['keterangan'] ?></td>
                    <td style="padding: 6px;">
                      <div class="btn-group" role="group">
                        <button class="btn btn-warning btn-sm btn-edit" data-id="<?= $row['id_pemesanan'] ?>"
                          data-nip="<?= htmlspecialchars($row['nip_sgt_id']) ?>"
                          data-nama="<?= htmlspecialchars($row['nama_id']) ?>"
                          data-alamat="<?= htmlspecialchars($row['alamat_id']) ?>"
                          data-ktp="<?= htmlspecialchars($row['no_ktp_id']) ?>"
                          data-hp="<?= htmlspecialchars($row['no_hp_id']) ?>"
                          data-tglp="<?= htmlspecialchars($row['tanggal_pemesanan']) ?>"
                          data-tglb="<?= htmlspecialchars($row['tanggal_berangkat']) ?>"
                          data-plat="<?= htmlspecialchars($row['no_plat_id']) ?>"
                          data-kelas="<?= htmlspecialchars($row['kelas_id']) ?>"
                          data-harga="<?= htmlspecialchars($row['harga_id']) ?>"
                          data-kursi="<?= htmlspecialchars($row['kursi']) ?>"
                          data-tujuan="<?= htmlspecialchars($row['tujuan_id']) ?>"
                          data-status="<?= htmlspecialchars($row['status_pembayaran_id']) ?>"
                          data-metode="<?= htmlspecialchars($row['metode_pembayaran_id']) ?>"
                          data-rekening="<?= htmlspecialchars($row['jenis_rekening_id']) ?>"
                          data-keterangan="<?= htmlspecialchars($row['keterangan']) ?>" title="Edit">
                          <i class="fas fa-edit"></i>
                        </button><?php
                          $statusTxt = strtolower($statusMap[$row['status_pembayaran_id']] ?? '');
                          if($statusTxt !== 'lunas'){
                        ?>
                        <button class="btn btn-success btn-sm" onclick="openPelunasanModal(<?= $row['id_pemesanan'] ?>)" title="Lunaskan">
                          <i class="fas fa-check"></i>
                        </button>
                        <?php } ?>
                        <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $row['id_pemesanan'] ?>"
                          title="Hapus">
                          <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn btn-success btn-sm btn-kwitansi" data-id="<?= $row['id_pemesanan'] ?>"
                          data-nama="<?= htmlspecialchars($row['nama_id']) ?>"
                          data-tujuan="<?= isset($tujuanMap[$row['tujuan_id']]) ? htmlspecialchars($tujuanMap[$row['tujuan_id']]) : htmlspecialchars($row['tujuan_id']) ?>"
                          data-alamat="<?= htmlspecialchars($row['alamat_id']) ?>" data-jmlorg="1"
                          data-tglp="<?= htmlspecialchars($row['tanggal_pemesanan']) ?>"
                          data-tglb="<?= htmlspecialchars($row['tanggal_berangkat']) ?>"
                          data-seat="<?= htmlspecialchars($row['kursi']) ?>"
                          data-harga="<?= htmlspecialchars($row['harga_id']) ?>"
                          data-nohp="<?= htmlspecialchars($row['no_hp_id']) ?>"
                          data-asalpo="<?= isset($asalPoMap[$row['asal_po']]) ? htmlspecialchars($asalPoMap[$row['asal_po']]) : htmlspecialchars($row['asal_po']) ?>"
                          data-noplat="<?= htmlspecialchars($row['no_plat_id']) ?>"
                          data-kelas="<?= ($row['kelas'] ?? ($travelMap[$row['no_plat_id']] ?? '')) ?>"
                          data-keterangan="<?= htmlspecialchars($row['keterangan']) ?>" title="Cetak Kwitansi">
                          <i class="fas fa-print"></i>
                        </button>
                        <button class="btn btn-info btn-sm btn-invoice" data-id="<?= $row['id_pemesanan'] ?>"
                          data-nama="<?= htmlspecialchars($row['nama_id']) ?>"
                          data-tujuan="<?= isset($tujuanMap[$row['tujuan_id']]) ? htmlspecialchars($tujuanMap[$row['tujuan_id']]) : htmlspecialchars($row['tujuan_id']) ?>"
                          data-alamat="<?= htmlspecialchars($row['alamat_id']) ?>"
                          data-tglp="<?= htmlspecialchars($row['tanggal_pemesanan']) ?>"
                          data-tglb="<?= htmlspecialchars($row['tanggal_berangkat']) ?>"
                          data-seat="<?= htmlspecialchars($row['kursi']) ?>"
                          data-noplat="<?= htmlspecialchars($row['no_plat_id']) ?>"
                          data-kelas="<?= ($row['kelas'] ?? ($travelMap[$row['no_plat_id']] ?? '')) ?>"
                          data-harga="<?= htmlspecialchars($row['harga_id']) ?>"
                          data-status="<?= isset($statusMap[$row['status_pembayaran_id']]) ? htmlspecialchars($statusMap[$row['status_pembayaran_id']]) : htmlspecialchars($row['status_pembayaran_id']) ?>"
                          data-username="<?= isset($userMap[$row['username']]) ? htmlspecialchars($userMap[$row['username']]) : htmlspecialchars($row['username']) ?>"
                          title="Invoice">
                          <i class="fas fa-file-invoice"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="21" class="text-center text-muted">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <br>Tidak ada data pemesanan
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit Pemesanan -->
    <div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="max-width:1100px;">
        <form id="formPemesanan" method="POST" class="modal-content">
          <div class="modal-header bg-primary">
            <h5 class="modal-title text-white" id="modalFormLabel">
              <i class="fas fa-plus-circle mr-2"></i>
              Tambah Pemesanan
            </h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id_pemesanan" id="id_pemesanan">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="nip_sgt_id">
                    <i class="fas fa-user mr-1"></i>
                    NIP/SGT Pelanggan
                  </label>
                  <div class="input-group">
                    <select class="form-control" name="nip_sgt_id" id="nip_sgt_id" onchange="autofillPelanggan()"
                      required style="font-size: 14px;">
                      <option value="">-- Pilih Pelanggan --</option>
                      <?php
                      $query = "SELECT nip_sgt FROM data_pelanggan";
                      $resultP = mysqli_query($conn, $query);
                      while ($rowP = mysqli_fetch_assoc($resultP)) {
                        echo '<option value="' . htmlspecialchars($rowP['nip_sgt']) . '">' . htmlspecialchars($rowP['nip_sgt']) . '</option>';
                      }
                      ?>
                    </select>
                    <div class="input-group-append">
                      <button type="button" class="btn btn-info" data-toggle="modal" data-target="#modalCariPelanggan">
                        <i class="fas fa-search"></i> Cari
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="nama_id">
                    <i class="fas fa-user mr-1"></i>
                    Nama
                  </label>
                  <input type="text" class="form-control" name="nama_id" id="nama_id" readonly>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="alamat_id">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    Alamat
                  </label>
                  <input type="text" class="form-control" name="alamat_id" id="alamat_id" readonly>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="no_ktp_id">
                    <i class="fas fa-id-card mr-1"></i>
                    No KTP
                  </label>
                  <input type="text" class="form-control" name="no_ktp_id" id="no_ktp_id" readonly>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="no_hp_id">
                    <i class="fas fa-phone mr-1"></i>
                    No HP
                  </label>
                  <input type="text" class="form-control" name="no_hp_id" id="no_hp_id" readonly>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="tanggal_pemesanan">
                    <i class="fas fa-calendar mr-1"></i>
                    Tanggal Pemesanan
                  </label>
                  <input type="datetime-local" class="form-control" name="tanggal_pemesanan" id="tanggal_pemesanan"
                    required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="tanggal_berangkat">
                    <i class="fas fa-plane-departure mr-1"></i>
                    Tanggal Berangkat
                  </label>
                  <input type="datetime-local" class="form-control" name="tanggal_berangkat" id="tanggal_berangkat"
                    required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="no_plat_id">
                    <i class="fas fa-bus mr-1"></i>
                    No Plat
                  </label>
                  <select class="form-control" name="no_plat_id" id="no_plat_id" onchange="autofillTravel()" required
                    style="font-size: 14px;">
                    <option value="">-- Pilih Plat --</option>
                    <?php
                    $query = "SELECT no_plat FROM data_travel";
                    $resultT = mysqli_query($conn, $query);
                    while ($rowT = mysqli_fetch_assoc($resultT)) {
                      echo '<option value="' . htmlspecialchars($rowT['no_plat']) . '">' . htmlspecialchars($rowT['no_plat']) . '</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="kelas_id">
                    <i class="fas fa-star mr-1"></i>
                    Kelas
                  </label>
                  <input type="text" class="form-control" name="kelas_id" id="kelas_id" placeholder="Masukkan kelas"
                    readonly required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="harga_id">
                    <i class="fas fa-tag mr-1"></i>
                    Harga
                  </label>
                  <input type="number" class="form-control" name="harga_id" id="harga_id" min="0"
                    placeholder="Masukkan harga" readonly required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="kursi">
                    <i class="fas fa-chair mr-1"></i>
                    Kursi
                  </label>
                  <input type="text" class="form-control" name="kursi" id="kursi" placeholder="Masukkan nomor kursi"
                    required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-3">
                  <label for="tujuan_id">
                    <i class="fas fa-map mr-1"></i>
                    Tujuan
                  </label>
                  <select class="form-control" name="tujuan_id" id="tujuan_id" required style="font-size: 14px;">
                    <option value="">-- Pilih Tujuan --</option>
                    <?php
                    $query = "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan";
                    $resultTJ = mysqli_query($conn, $query);
                    while ($rowTJ = mysqli_fetch_assoc($resultTJ)) {
                      echo '<option value="' . $rowTJ['id_tujuan_perjalanan'] . '">' . htmlspecialchars($rowTJ['nama_tujuan']) . '</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4">
                <div class="form-group mb-3">
                  <label for="status_pembayaran_id">
                    <i class="fas fa-check-circle mr-1"></i>
                    Status Pembayaran
                  </label>
                  <select class="form-control" name="status_pembayaran_id" id="status_pembayaran_id" required
                    style="font-size: 14px;">
                    <option value="">-- Pilih Status --</option>
                    <?php
                    $query = "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran";
                    $resultSP = mysqli_query($conn, $query);
                    while ($rowSP = mysqli_fetch_assoc($resultSP)) {
                      echo '<option value="' . $rowSP['id_status_pembayaran'] . '">' . htmlspecialchars($rowSP['status_pembayaran']) . '</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-3">
                  <label for="payment_type">
                    <i class="fas fa-credit-card mr-1"></i>
                    Tipe Pembayaran
                  </label>
                  <select class="form-control" name="payment_type" id="payment_type" onchange="togglePaymentMethod()" required
                    style="font-size: 14px;">
                    <option value="">-- Pilih Tipe --</option>
                    <option value="single">Pembayaran Tunggal</option>
                    <option value="multiple">Pembayaran Multiple (Transfer + Cash)</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-3">
                  <label for="jenis_rekening_id">
                    <i class="fas fa-university mr-1"></i>
                    Jenis Rekening
                  </label>
                  <select class="form-control" name="jenis_rekening_id" id="jenis_rekening_id" required
                    style="font-size: 14px;">
                    <option value="">-- Pilih Rekening --</option>
                    <?php
                    $query = "SELECT id_rekening, nama_rekening FROM data_rekening";
                    $resultJR = mysqli_query($conn, $query);
                    while ($rowJR = mysqli_fetch_assoc($resultJR)) {
                      echo '<option value="' . $rowJR['id_rekening'] . '">' . htmlspecialchars($rowJR['nama_rekening']) . '</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Single Payment Method -->
            <div id="singlePaymentMethod" style="display: none;">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="metode_pembayaran_id">
                      <i class="fas fa-credit-card mr-1"></i>
                      Metode Pembayaran
                    </label>
                    <select class="form-control" name="metode_pembayaran_id" id="metode_pembayaran_id" style="font-size: 14px;">
                      <option value="">-- Pilih Metode --</option>
                      <?php
                      $query = "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran";
                      $resultMP = mysqli_query($conn, $query);
                      while ($rowMP = mysqli_fetch_assoc($resultMP)) {
                        echo '<option value="' . $rowMP['id_metode_pembayaran'] . '">' . htmlspecialchars($rowMP['metode_pembayaran']) . '</option>';
                      }
                      ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="single_amount">
                      <i class="fas fa-money-bill mr-1"></i>
                      Jumlah Pembayaran
                    </label>
                    <input type="number" class="form-control" name="single_amount" id="single_amount" min="0" readonly>
                  </div>
                </div>
              </div>
            </div>

            <!-- Multiple Payment Method -->
            <div id="multiplePaymentMethod" style="display: none;">
              <div class="card">
                <div class="card-header">
                  <h6 class="mb-0">
                    <i class="fas fa-list mr-1"></i>
                    Detail Pembayaran Multiple
                  </h6>
                </div>
                <div class="card-body">
                  <div id="paymentMethodsContainer">
                    <!-- Payment method entries will be added here -->
                  </div>
                  <div class="row mt-3">
                    <div class="col-md-6">
                      <button type="button" class="btn btn-info btn-sm" onclick="addPaymentMethod()">
                        <i class="fas fa-plus mr-1"></i> Tambah Metode Pembayaran
                      </button>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="total_payment_amount">
                          <i class="fas fa-calculator mr-1"></i>
                          Total Pembayaran
                        </label>
                        <input type="number" class="form-control" name="total_payment_amount" id="total_payment_amount" min="0" readonly>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-group mb-3">
              <label for="keterangan">
                <i class="fas fa-comment mr-1"></i>
                Keterangan
              </label>
              <textarea class="form-control" name="keterangan" id="keterangan" rows="3"
                placeholder="Masukkan Lokasi penjemputan atau status bayar bagi yang masih Piutang (DP)"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" id="btnSave" name="tambah" class="btn btn-success">
              <i class="fas fa-save mr-1"></i> Simpan
            </button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i> Batal
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Pencarian Pelanggan -->
    <div class="modal fade" id="modalCariPelanggan" tabindex="-1" role="dialog"
      aria-labelledby="modalCariPelangganLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalCariPelangganLabel">Pilih Pelanggan</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <table id="tabelCariPelanggan" class="table table-bordered table-striped table-hover">
                <thead>
                  <tr>
                    <th>NIP/SGT</th>
                    <th>Nama</th>
                    <th>Alamat</th>
                    <th>No KTP</th>
                    <th>No HP</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $resultCari = mysqli_query($conn, "SELECT * FROM data_pelanggan");
                  while ($row = mysqli_fetch_assoc($resultCari)) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['nip_sgt']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['alamat']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['no_ktp']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['no_hp']) . '</td>';
                    echo '<td><button type="button" class="btn btn-success btn-sm pilih-pelanggan-modal" data-nip="' . htmlspecialchars($row['nip_sgt']) . '">Pilih</button></td>';
                    echo '</tr>';
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Kwitansi -->
    <div class="modal fade" id="modalKwitansi" tabindex="-1" role="dialog" aria-labelledby="modalKwitansiLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="modalKwitansiLabel"><i class="fas fa-receipt mr-2"></i>Kwitansi Pemesanan</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div id="kwitansiArea"
              style="background:#fff; color:#222; padding:24px; border-radius:10px; max-width:400px; margin:auto; font-family:monospace;">
              <div style="text-align:center; font-weight:bold; font-size:1.2em;">Sinar Galaxy Travel</div>
              <div style="text-align:center;">—————————</div>
              <div style="text-align:center; font-weight:bold;">Pesanan terkonfirmasi</div>
              <table style="width:100%; margin:10px 0; font-size:1em;">
                <tr>
                  <td>Nama</td>
                  <td>:</td>
                  <td id="kwt_nama"></td>
                </tr>
                <tr>
                  <td>Tujuan</td>
                  <td>:</td>
                  <td id="kwt_tujuan"></td>
                </tr>
                <tr>
                  <td>Alamat</td>
                  <td>:</td>
                  <td id="kwt_alamat"></td>
                </tr>
                <tr>
                  <td>Jml org</td>
                  <td>:</td>
                  <td id="kwt_jmlorg"></td>
                </tr>
                <tr>
                  <td>Waktu Pesan</td>
                  <td>:</td>
                  <td id="kwt_waktupesan"></td>
                </tr>
              </table>
              <div style="text-align:center;">—————————</div>
              <div style="text-align:center; font-weight:bold;">Keberangkatan</div>
              <table style="width:100%; font-size:1em;">
                <tr>
                  <td>Hari/Tgl</td>
                  <td>:</td>
                  <td id="kwt_tglb"></td>
                </tr>
                <tr>
                  <td>Jam</td>
                  <td>:</td>
                  <td id="kwt_jam"></td>
                </tr>
                <tr>
                  <td>Seat</td>
                  <td>:</td>
                  <td id="kwt_seat"></td>
                </tr>
                <tr>
                  <td>No Plat</td>
                  <td>:</td>
                  <td id="kwt_noplat"></td>
                </tr>
                <tr>
                  <td>Kelas</td>
                  <td>:</td>
                  <td id="kwt_kelas"></td>
                </tr>
              </table>
              <div style="margin:10px 0; font-size:1em;">Pembayaran tiket sejumlah <b id="kwt_harga"></b> dpt dilakukan
                via transfer ke rek<br>
                * BNI 1932186487<br>
                * MANDIRI 1610012604265<br>
                * BRI 0093 01 002 544 306<br>
                An PT SINAR GALAXY TRAVEL....<br>
                Atau cash ke kasir PO tempat anda melakukan pemesanan tiket.
              </div>
              <ul style="font-size:0.95em; padding-left:18px;">
                <li>Harap segera melunaskan tiket stlh melakukan booking</li>
                <li>Kami akan mengkonfirmasi kepastian keberangkatan, jika tidak memberikan kepastian maka kami berhak
                  untuk membatalkan pesanan anda</li>
                <li>Reschedulle atau cancel (pembatalan) keberangkatan harap hubungi kantor H-1, Diluar dari itu maka
                  tiket akan otomatis hangus dan tidak bisa untuk di reschedulle/refund.</li>
              </ul>
              <div style="text-align:center; font-size:1em; margin-top:10px;">Terimakasih</div>
              <div style="text-align:center; font-size:1em; margin-top:10px;">Website kami : www.sinargalaxy.my.id</div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="btnDownloadPDF" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Download PDF</button>
            <button id="btnDownloadJPEG" class="btn btn-info"><i class="fas fa-file-image"></i> Download JPEG</button>
            <button id="btnSendWA" class="btn btn-success"><i class="fab fa-whatsapp"></i> Kirim ke WhatsApp</button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End Modal Kwitansi -->

    <!-- Modal Invoice -->
    <div class="modal fade" id="modalInvoice" tabindex="-1" role="dialog" aria-labelledby="modalInvoiceLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="width: 100%;">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title" id="modalInvoiceLabel"><i class="fas fa-file-invoice mr-2"></i>Invoice Pemesanan
            </h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div id="invoiceArea"
              style="background:#fff; color:#222; padding:28px 24px 18px 24px; border-radius:10px; max-width:1000px; margin:auto; font-family:Arial,Helvetica,sans-serif; border:1.5px solid #bbb; position:relative; overflow:hidden;">
              <!-- Logo background transparan -->
              <img src="../img/logo_sgt.png" alt="Logo SGT"
                style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:420px; opacity:0.08; z-index:0; pointer-events:none; user-select:none; border-radius:50%;" />
              <div
                style="display:flex; justify-content:space-between; align-items:flex-start; position:relative; z-index:1;">
                <div style="display:flex; align-items:center; gap:18px;">
                  <img src="../img/logo_sgt.png" alt="Logo SGT"
                    style="height:60px; width:auto; display:block; border-radius:50%;" />
                  <div style="font-size:1.1em; font-weight:bold; line-height:1.3;">
                    PT. SINAR GALAXY TRAVEL<br>
                    <span style="font-weight:normal; font-size:0.98em;">
                      Alamat kantor :<br>
                      PO Sumbawa : Jln. Mangga 1 no 7 Sumbawa Besar<br>
                      PO Mataram :Jln. Airlangga Squar<br>
                      web : www.sinargalaxy.my.id
                    </span>
                  </div>
                </div>
                <div style="font-size:2em; font-weight:600; letter-spacing:2px; text-align:right; margin-top:8px;">
                  INVOICE</div>
              </div>
              <table style="width:100%; border-collapse:collapse; margin:28px 0 0 0;">
                <tr>
                  <th colspan="7"
                    style="background:#f6c23e; color:#222; font-weight:bold; text-align:center; padding:10px 8px; border:1.5px solid #bbb; font-size:1.1em; letter-spacing:1px;">
                    PESANAN TERKONFIRMASI</th>
                </tr>
                <tr style="background:#faf9f7; text-align:center;">
                  <th style="padding:7px 8px; border:1.5px solid #bbb; font-weight:bold;">NAMA</th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; font-weight:bold;">TUJUAN</th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; font-weight:bold;">ALAMAT</th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; font-weight:bold;">JML</th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; font-weight:bold;">WKTU PESAN</th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; font-weight:bold;">METODE PEMBAYARAN</th>
                  
                </tr>
                <tr>
                  <td id="inv_nama" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_tujuan" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_alamat" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_jmlorg" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_waktupesan" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td style="padding:0; border:1.5px solid #bbb; vertical-align:top;">
                    <table style="width:100%; border-collapse:collapse;">
                      <tbody id="inv_rekening_tbody"></tbody>
                    </table>
                  </td>
                  
                  </td>
                </tr>
              </table>
              <table style="width:100%; border-collapse:collapse; margin:18px 0 0 0; text-align:center;">
                <tr>
                  <th colspan="8"
                    style="background:#f6c23e; color:#222; font-weight:bold; text-align:center; padding:10px 8px; border:1.5px solid #bbb; font-size:1.1em; letter-spacing:1px;">
                    KEBERANGKATAN</th>
                </tr>
                <tr>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; background:#f8f9fc; font-weight:bold;">HARI/TGL
                  </th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; background:#f8f9fc; font-weight:bold;">JAM</th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; background:#f8f9fc; font-weight:bold;">SEAT</th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; background:#f8f9fc; font-weight:bold;">NO PLAT
                  </th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; background:#f8f9fc; font-weight:bold;">KELAS</th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; background:#f8f9fc; font-weight:bold;">JUMLAH Rp
                  </th>
                  <th style="padding:7px 8px; border:1.5px solid #bbb; background:#f8f9fc; font-weight:bold;">SUDAH TERMASUK</th>
                </tr>
                <tr>
                  <td id="inv_tglb" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_jam" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_seat" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_noplat" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_kelas" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id="inv_harga" style="padding:7px 8px; border:1.5px solid #bbb;"></td>
                  <td id=""
                    style="padding:7px 8px; border:1.5px solid #bbb; text-align:left; font-weight:bold;">ASURANSI PERJALANAN</td>
                </tr>
              </table>
              <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:18px;">
                <div
                  style="font-size:0.98em; color:#222; border:1.5px solid #bbb; border-radius:6px; padding:10px 16px; background:#faf9f7; max-width:70%;">
                  *Harap segera melunasi tiket stlh pemesanan (booking)<br>
                  *Kami akan mengkonfirmasi kepastian keberangkatan, jika tidak memberikan kepastian maka kami berhak
                  untuk membatalkan pesanan anda<br>
                  *Reschedule atau cancel (pembatalan) keberangkatan harap hubungi kantor H-1, diluar dari itu maka
                  tiket akan otomatis hangus dan tidak bisa untuk di reschedule/refund.
                </div>
                <div style="font-size:1.1em; color:#222; font-weight:bold; text-align:right; min-width:180px;">ADMIN
                  SINAR GALAXY TRAVEL<br><span id="inv_username" style="font-size:0.95em; font-weight:normal;"></span>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="btnDownloadInvoicePDF" class="btn btn-info"><i class="fas fa-file-pdf"></i> Download
              PDF</button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End Modal Invoice -->
    <?php include 'pelunasan_modal_fragment.html'; ?>
    <?php include '../inc/footer.php'; ?>


    <script>
      function autofillPelanggan() {
        var nipSgt = document.getElementById('nip_sgt_id').value;
        if (nipSgt) {
          fetch('get_pelanggan.php?nip_sgt=' + nipSgt)
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                document.getElementById('nama_id').value = data.nama;
                document.getElementById('alamat_id').value = data.alamat;
                document.getElementById('no_ktp_id').value = data.no_ktp;
                document.getElementById('no_hp_id').value = data.no_hp;
              }
            })
            .catch(error => console.error('Error:', error));
        } else {
          // Jangan kosongkan jika modal sedang dalam mode edit pemesanan
          var isEdit = $('#btnSave').attr('name') === 'edit';
          if (!isEdit) {
            document.getElementById('nama_id').value = '';
            document.getElementById('alamat_id').value = '';
            document.getElementById('no_ktp_id').value = '';
            document.getElementById('no_hp_id').value = '';
          }
        }
      }

      function autofillTravel() {
        var noPlat = document.getElementById('no_plat_id').value;
        if (noPlat) {
          fetch('get_travel.php?no_plat=' + encodeURIComponent(noPlat))
            .then(response => response.json())
            .then(data => {
              if (data && !data.error) {
                document.getElementById('kelas_id').value = data.kelas || '';
                document.getElementById('harga_id').value = data.harga || '';
              } else {
                document.getElementById('kelas_id').value = '';
                document.getElementById('harga_id').value = '';
              }
            })
            .catch(error => {
              document.getElementById('kelas_id').value = '';
              document.getElementById('harga_id').value = '';
            });
        } else {
          document.getElementById('kelas_id').value = '';
          document.getElementById('harga_id').value = '';
        }
      }

      // Handle edit button
      $(document).on('click', '.btn-edit', async function () {
        var $btn = $(this);
        var data = $btn.data();
        var id = data.id;
        var nipVal = data.nip || '';
        var namaVal = data.nama || '';
        var alamatVal = data.alamat || '';
        var ktpVal = data.ktp || '';
        var hpVal = data.hp || '';
        var platVal = data.plat || '';

        // Pastikan paymentMethods tersedia; jika tidak, ambil via AJAX
        if (!data.paymentMethods || data.paymentMethods.length === 0 || !nipVal || !namaVal) {
          try {
            const resp = await $.getJSON('data_pemesanan_fetch.php', { id_pemesanan: id });
            if (resp) {
              if (resp.payment_methods) data.paymentMethods = resp.payment_methods;
              if (resp.nip_sgt_id && !nipVal) nipVal = resp.nip_sgt_id;
              if (resp.nama_id && !namaVal) namaVal = resp.nama_id;
              if (resp.alamat_id && !alamatVal) alamatVal = resp.alamat_id;
              if (resp.no_ktp_id && !ktpVal) ktpVal = resp.no_ktp_id;
              if (resp.no_hp_id && !hpVal) hpVal = resp.no_hp_id;
              if (resp.no_plat_id && !platVal) platVal = resp.no_plat_id;
              if (resp.kelas_id && !data.kelas) data.kelas = resp.kelas_id;
              if (resp.harga_id && !data.harga) data.harga = resp.harga_id;
              if (resp.kursi && !data.kursi) data.kursi = resp.kursi;
              if (resp.tujuan_id && !data.tujuan) data.tujuan = resp.tujuan_id;
              if (resp.status_pembayaran_id && !data.status) data.status = resp.status_pembayaran_id;
              if (resp.metode_pembayaran_id && !data.metode) data.metode = resp.metode_pembayaran_id;
              if (resp.jenis_rekening_id && !data.rekening) data.rekening = resp.jenis_rekening_id;
              if (resp.keterangan && !data.keterangan) data.keterangan = resp.keterangan;
            }
          } catch(e) { /* abaikan */ }
        }

        $('#id_pemesanan').val(id);

        // Pastikan NIP terdaftar di dropdown select
        var $selNip = $('#nip_sgt_id');
        if (nipVal && $selNip.length) {
          if ($selNip.find("option[value='" + nipVal + "']").length === 0) {
            var labelNip = nipVal + (namaVal ? ' - ' + namaVal : '');
            $selNip.append(new Option(labelNip, nipVal, true, true));
          }
          $selNip.val(nipVal);
        }

        // Set identitas penumpang secara langsung
        $('#nama_id').val(namaVal);
        $('#alamat_id').val(alamatVal);
        $('#no_ktp_id').val(ktpVal);
        $('#no_hp_id').val(hpVal);

        // Format tanggal_pemesanan (date only)
        if (data.tglp) {
          var tglp = data.tglp.split('T')[0] || data.tglp.split(' ')[0];
          $('#tanggal_pemesanan').val(tglp);
        } else {
          $('#tanggal_pemesanan').val('');
        }
        // Format tanggal_berangkat (datetime-local)
        if (data.tglb) {
          var tglb = data.tglb.replace(' ', 'T').slice(0, 16);
          $('#tanggal_berangkat').val(tglb);
        } else {
          $('#tanggal_berangkat').val('');
        }

        // Pastikan no plat terdaftar di dropdown
        var $selPlat = $('#no_plat_id');
        if (platVal && $selPlat.length) {
          if ($selPlat.find("option[value='" + platVal + "']").length === 0) {
            $selPlat.append(new Option(platVal, platVal, true, true));
          }
          $selPlat.val(platVal);
        }
        $('#kelas_id').val(data.kelas);
        $('#harga_id').val(data.harga);
        $('#kursi').val(data.kursi);
        $('#tujuan_id').val(data.tujuan);
        $('#status_pembayaran_id').val(data.status);
        $('#metode_pembayaran_id').val(data.metode);
        $('#jenis_rekening_id').val(data.rekening);
        $('#keterangan').val(data.keterangan);

        // Handle payment methods for editing
        if (data.paymentMethods) {
          try {
            var pmStr = $('<textarea/>').html(data.paymentMethods).text();
            var paymentMethods = JSON.parse(pmStr);
            if (paymentMethods.length > 0) {
              $('#payment_type').val('multiple');
              togglePaymentMethod();
              loadPaymentMethods(paymentMethods);
            } else {
              $('#payment_type').val('single');
              togglePaymentMethod();
              $('#metode_pembayaran_id').val(paymentMethods[0].method_id);
              $('#single_amount').val(paymentMethods[0].amount);
            }
          } catch (e) {
            // Fallback to single payment
            $('#payment_type').val('single');
            togglePaymentMethod();
          }
        } else {
          $('#payment_type').val('single');
          togglePaymentMethod();
        }

        $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Update');
        $('#modalFormLabel').html('<i class="fas fa-edit mr-2"></i> Edit Pemesanan');
        $('#modalForm').modal('show');
      });

      // Reset form when modal is closed
      $('#modalForm').on('hidden.bs.modal', function () {
        $('#formPemesanan')[0].reset();
        $('#singlePaymentMethod').hide();
        $('#multiplePaymentMethod').hide();
        $('#paymentMethodsContainer').empty();
        $('#btnSave').attr('name', 'tambah').html('<i class="fas fa-save mr-1"></i> Simpan');
        $('#modalFormLabel').html('<i class="fas fa-plus-circle mr-2"></i> Tambah Pemesanan');
      });

      // Multiple Payment Method Functions
      function togglePaymentMethod() {
        var paymentType = $('#payment_type').val();
        
        if (paymentType === 'single') {
          $('#singlePaymentMethod').show();
          $('#multiplePaymentMethod').hide();
          $('#metode_pembayaran_id').prop('required', true);
          $('#single_amount').val($('#harga_id').val());
        } else if (paymentType === 'multiple') {
          $('#singlePaymentMethod').hide();
          $('#multiplePaymentMethod').show();
          $('#metode_pembayaran_id').prop('required', false);
          addPaymentMethod(); // Add first payment method
        } else {
          $('#singlePaymentMethod').hide();
          $('#multiplePaymentMethod').hide();
          $('#metode_pembayaran_id').prop('required', false);
        }
      }

      function addPaymentMethod() {
        var paymentIndex = $('#paymentMethodsContainer .payment-method-row').length;
        var paymentHtml = `
          <div class="payment-method-row border rounded p-3 mb-3" data-index="${paymentIndex}">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label>Metode Pembayaran</label>
                  <select class="form-control payment-method" name="payment_methods[${paymentIndex}][method_id]" required>
                    <option value="">-- Pilih Metode --</option>
                    <?php
                    $query = "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran";
                    $resultMP = mysqli_query($conn, $query);
                    while ($rowMP = mysqli_fetch_assoc($resultMP)) {
                      echo '<option value="' . $rowMP['id_metode_pembayaran'] . '">' . htmlspecialchars($rowMP['metode_pembayaran']) . '</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Jumlah</label>
                  <input type="number" class="form-control payment-amount" name="payment_methods[${paymentIndex}][amount]" min="0" required onchange="calculateTotal()">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Rekening</label>
                  <select class="form-control payment-rekening" name="payment_methods[${paymentIndex}][rekening_id]" required>
                    <option value="">-- Pilih Rekening --</option>
                    <?php
                    $query = "SELECT id_rekening, nama_rekening FROM data_rekening";
                    $resultJR = mysqli_query($conn, $query);
                    while ($rowJR = mysqli_fetch_assoc($resultJR)) {
                      echo '<option value="' . $rowJR['id_rekening'] . '">' . htmlspecialchars($rowJR['nama_rekening']) . '</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label>&nbsp;</label>
                  <button type="button" class="btn btn-danger btn-sm btn-block" onclick="removePaymentMethod(${paymentIndex})">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        `;
        $('#paymentMethodsContainer').append(paymentHtml);
        
        // Tambah input tanggal bayar
        var dateHtml = `
              <div class="col-md-3">
                <div class="form-group">
                  <label>Tanggal Bayar</label>
                  <input type="date" class="form-control payment-date" name="payment_methods[${paymentIndex}][payment_date]" required>
                </div>
              </div>
        `;
        $('.payment-method-row[data-index="' + paymentIndex + '"] .row').append(dateHtml);
        
        // Set default tanggal
        var defaultDate = $('#tanggal_pemesanan').val() ? $('#tanggal_pemesanan').val().substr(0,10) : new Date().toISOString().slice(0,10);
        $('.payment-method-row[data-index="' + paymentIndex + '"] .payment-date').val(defaultDate);
        
        calculateTotal();
      }

      function removePaymentMethod(index) {
        $('.payment-method-row[data-index="' + index + '"]').remove();
        // Reindex remaining payment methods
        $('.payment-method-row').each(function(i) {
          $(this).attr('data-index', i);
          $(this).find('select, input').each(function() {
            var name = $(this).attr('name');
            if (name) {
              name = name.replace(/\[\d+\]/, '[' + i + ']');
              $(this).attr('name', name);
            }
          });
        });
        calculateTotal();
      }

      function calculateTotal() {
        var total = 0;
        $('.payment-amount').each(function() {
          var amount = parseFloat($(this).val()) || 0;
          total += amount;
        });
        $('#total_payment_amount').val(total);
        
        // Validate against ticket price
        var ticketPrice = parseFloat($('#harga_id').val()) || 0;
        if (total > ticketPrice + 0.01) {
          $('#total_payment_amount').addClass('is-invalid');
          $('#total_payment_amount').next('.invalid-feedback').remove();
          $('#total_payment_amount').after('<div class="invalid-feedback">Total pembayaran melebihi harga tiket (Rp ' + ticketPrice.toLocaleString('id-ID') + ')</div>');
        } else {
          $('#total_payment_amount').removeClass('is-invalid');
          $('#total_payment_amount').next('.invalid-feedback').remove();
        }
        // Tampilkan informasi selisih jika pembayaran belum lunas & auto set status
        var statusSelect = $('#status_pembayaran_id');
        if (total < ticketPrice - 0.01) {
          if ($('#total_payment_amount').next('.text-warning').length === 0) {
            $('#total_payment_amount').after('<small class="text-warning">Pembayaran belum lunas, tercatat sebagai DP/Piutang</small>');
          }
          // cari opsi DP/Piutang lalu set
          var dpOpt = statusSelect.find('option').filter(function(){
            var t = $(this).text().toLowerCase();
            return t.includes('dp') || t.includes('piutang');
          }).first();
          if (dpOpt.length) statusSelect.val(dpOpt.val());
        } else {
          $('#total_payment_amount').next('.text-warning').remove();
          // Jika total lunas, auto set status Lunas
          if (Math.abs(total - ticketPrice) <= 0.01) {
            var lunasOpt = statusSelect.find('option').filter(function(){
              return $(this).text().toLowerCase().includes('lunas');
            }).first();
            if (lunasOpt.length) statusSelect.val(lunasOpt.val());
          }
        }
      }

      function loadPaymentMethods(paymentMethods) {
        $('#paymentMethodsContainer').empty();
        paymentMethods.forEach(function(payment, index) {
          var paymentHtml = `
            <div class="payment-method-row border rounded p-3 mb-3" data-index="${index}">
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Metode Pembayaran</label>
                    <select class="form-control payment-method" name="payment_methods[${index}][method_id]" required>
                      <option value="">-- Pilih Metode --</option>
                      <?php
                      $query = "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran";
                      $resultMP = mysqli_query($conn, $query);
                      while ($rowMP = mysqli_fetch_assoc($resultMP)) {
                        echo '<option value="' . $rowMP['id_metode_pembayaran'] . '">' . htmlspecialchars($rowMP['metode_pembayaran']) . '</option>';
                      }
                      ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Jumlah</label>
                    <input type="number" class="form-control payment-amount" name="payment_methods[${index}][amount]" min="0" required onchange="calculateTotal()">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Rekening</label>
                    <select class="form-control payment-rekening" name="payment_methods[${index}][rekening_id]" required>
                      <option value="">-- Pilih Rekening --</option>
                      <?php
                      $query = "SELECT id_rekening, nama_rekening FROM data_rekening";
                      $resultJR = mysqli_query($conn, $query);
                      while ($rowJR = mysqli_fetch_assoc($resultJR)) {
                        echo '<option value="' . $rowJR['id_rekening'] . '">' . htmlspecialchars($rowJR['nama_rekening']) . '</option>';
                      }
                      ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm btn-block" onclick="removePaymentMethod(${index})">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `;
          $('#paymentMethodsContainer').append(paymentHtml);
          
          // Tambah input tanggal bayar
          var dateHtml = `
              <div class="col-md-3">
                <div class="form-group">
                  <label>Tanggal Bayar</label>
                  <input type="date" class="form-control payment-date" name="payment_methods[${index}][payment_date]" required>
                </div>
              </div>
          `;
          $('.payment-method-row[data-index="' + index + '"] .row').append(dateHtml);
          
          // Set values
          $('.payment-method-row[data-index="' + index + '"] .payment-method').val(payment.method_id);
          $('.payment-method-row[data-index="' + index + '"] .payment-amount').val(payment.amount);
          $('.payment-method-row[data-index="' + index + '"] .payment-rekening').val(payment.rekening_id);
          $('.payment-method-row[data-index="' + index + '"] .payment-date').val(payment.payment_date ? payment.payment_date.substring(0,10) : '');
        });
        calculateTotal();
        // Tambah baris otomatis untuk sisa pembayaran jika belum lunas
        var hargaTicket = parseFloat($('#harga_id').val()) || 0;
        var totalBayar = 0;
        paymentMethods.forEach(function(p){ totalBayar += parseFloat(p.amount||0); });
        var selisih = hargaTicket - totalBayar;
        if (selisih > 0.01) {
          addPaymentMethod();
          var idxBaru = $('#paymentMethodsContainer .payment-method-row').length - 1;
          var rowBaru = $('.payment-method-row[data-index="' + idxBaru + '"]');
          rowBaru.find('.payment-amount').val(selisih);
          rowBaru.find('.payment-date').val(new Date().toISOString().slice(0,10));
        }
      }

      // Update single amount when harga changes
      $('#harga_id').on('change', function() {
        if ($('#payment_type').val() === 'single') {
          $('#single_amount').val($(this).val());
        }
      });

      // Handle delete button
      // Handle lunaskan button
      $(document).on('click', '.btn-lunaskan', function () {
        const id = $(this).attr('data-id'); // gunakan attr agar tidak undefined
        if (!id) {
          alert('ID pemesanan tidak ditemukan.');
          return;
        }
        if (!confirm('Lunaskan pesanan ini?')) return;

        $.post('lunaskan_pemesanan.php', { id: id }, function (resp) {
          try {
            if (resp.success) {
              alert('Pelunasan berhasil');
              location.reload();
            } else {
              alert(resp.msg || 'Gagal melunaskan');
            }
          } catch (e) {
            alert('Respons tidak valid');
            console.log(resp);
          }
        }, 'json').fail(function (xhr) {
          alert(xhr.responseText || 'Error Ajax');
        });
      });

      $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        showConfirmDialog('Apakah Anda yakin ingin menghapus data pemesanan ini?', '?hapus=' + id);
      });

      function showConfirmDialog(message, url) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'confirm-dialog-overlay';
        overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
      `;
        // Create dialog
        const dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.style.cssText = `
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        max-width: 400px;
        width: 90%;
        overflow: hidden;
      `;
        dialog.innerHTML = `
        <div style="background: linear-gradient(135deg, #e74a3b 0%, #c0392b 100%); color: white; padding: 20px 25px 15px; text-align: center;">
          <div style="font-size: 2.5rem; margin-bottom: 10px;">⚠️</div>
          <h3 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Konfirmasi Hapus</h3>
        </div>
        <div style="padding: 25px; text-align: center; color: #5a5c69; font-size: 1rem; line-height: 1.5;">
          ${message}
        </div>
        <div style="padding: 0 25px 25px; display: flex; gap: 10px; justify-content: center;">
          <button onclick="closeConfirmDialog()" style="padding: 10px 25px; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; background: #f8f9fc; color: #5a5c69; border: 2px solid #e3e6f0; min-width: 100px;">
            Batal
          </button>
          <button onclick="proceedDelete('${url}')" style="padding: 10px 25px; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; background: linear-gradient(135deg, #e74a3b 0%, #c0392b 100%); color: white; border: 2px solid #e74a3b; min-width: 100px;">
            Ya, Hapus!
          </button>
        </div>
      `;
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
      }
      function closeConfirmDialog() {
        const overlay = document.querySelector('.confirm-dialog-overlay');
        if (overlay) {
          overlay.remove();
          document.body.style.overflow = '';
        }
      }
      function proceedDelete(url) {
        closeConfirmDialog();
        window.location.href = url;
      }
      document.addEventListener('click', function (e) {
        if (e.target.classList.contains('confirm-dialog-overlay')) {
          closeConfirmDialog();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          closeConfirmDialog();
        }
      });

              // Auto submit filter when dropdown changes
        $('#filter_user, #filter_asal_po, #filter_status, #filter_plat, #filter_tujuan, #filter_metode, #filter_rekening').on('change', function() {
          saveFilterPreference();
          updateURL();
          showLoading();
          $('#filterForm').submit();
        });

        // Update URL with filter parameters
        function updateURL() {
          var form = document.getElementById('filterForm');
          var formData = new FormData(form);
          var params = new URLSearchParams(formData);
          
          if (params.toString()) {
            var newURL = window.location.pathname + '?' + params.toString();
            window.history.pushState({}, '', newURL);
          }
        }

        // Show loading indicator
        function showLoading() {
          $('body').append('<div id="loadingOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;"><div class="spinner-border text-light" role="status"><span class="sr-only">Loading...</span></div></div>');
        }

        // Save filter preference
        function saveFilterPreference() {
          var filterData = {
            user: $('#filter_user').val(),
            asal_po: $('#filter_asal_po').val(),
            status: $('#filter_status').val(),
            plat: $('#filter_plat').val(),
            tujuan: $('#filter_tujuan').val(),
            metode: $('#filter_metode').val(),
            rekening: $('#filter_rekening').val()
          };
          localStorage.setItem('pemesananFilter', JSON.stringify(filterData));
        }

        // Load filter preference
        function loadFilterPreference() {
          var savedFilter = localStorage.getItem('pemesananFilter');
          if (savedFilter) {
            var filterData = JSON.parse(savedFilter);
            $('#filter_user').val(filterData.user || '');
            $('#filter_asal_po').val(filterData.asal_po || '');
            $('#filter_status').val(filterData.status || '');
            $('#filter_plat').val(filterData.plat || '');
            $('#filter_tujuan').val(filterData.tujuan || '');
            $('#filter_metode').val(filterData.metode || '');
            $('#filter_rekening').val(filterData.rekening || '');
          }
        }

        // Clear filter preference
        function clearFilterPreference() {
          localStorage.removeItem('pemesananFilter');
          alert('Filter preference berhasil dihapus!');
        }

        // Toggle column visibility
        function toggleColumn(column) {
          var columnIndex;
          switch(column) {
            case 'alamat':
              columnIndex = 3; // Index kolom alamat
              break;
            case 'ktp':
              columnIndex = 4; // Index kolom KTP
              break;
            case 'hp':
              columnIndex = 5; // Index kolom HP
              break;
            default:
              return;
          }
          
          var isVisible = $('#tablePemesanan th:eq(' + columnIndex + ')').is(':visible');
          
          if (isVisible) {
            $('#tablePemesanan th:eq(' + columnIndex + '), #tablePemesanan td:nth-child(' + (columnIndex + 1) + ')').hide();
            $('button[onclick="toggleColumn(\'' + column + '\')"]').removeClass('btn-outline-secondary').addClass('btn-secondary');
            saveColumnPreference(column, false);
          } else {
            $('#tablePemesanan th:eq(' + columnIndex + '), #tablePemesanan td:nth-child(' + (columnIndex + 1) + ')').show();
            $('button[onclick="toggleColumn(\'' + column + '\')"]').removeClass('btn-secondary').addClass('btn-outline-secondary');
            saveColumnPreference(column, true);
          }
        }

        // Save column preference
        function saveColumnPreference(column, visible) {
          var columnPrefs = JSON.parse(localStorage.getItem('pemesananColumnPrefs') || '{}');
          columnPrefs[column] = visible;
          localStorage.setItem('pemesananColumnPrefs', JSON.stringify(columnPrefs));
        }

        // Load column preferences
        function loadColumnPreferences() {
          var columnPrefs = JSON.parse(localStorage.getItem('pemesananColumnPrefs') || '{}');
          
          Object.keys(columnPrefs).forEach(function(column) {
            if (!columnPrefs[column]) {
              toggleColumn(column);
            }
          });
        }

      // Export filtered data
      function exportFilteredData() {
        var form = document.getElementById('filterForm');
        var formData = new FormData(form);
        var params = new URLSearchParams(formData);
        
        // Buat URL untuk export
        var exportUrl = 'export_pemesanan.php?' + params.toString();
        
        // Buka di tab baru
        window.open(exportUrl, '_blank');
      }

      // Toggle filter card
      function toggleFilter() {
        var filterBody = document.getElementById('filterBody');
        var filterIcon = document.getElementById('filterToggleIcon');
        
        if (filterBody.style.display === 'none') {
          filterBody.style.display = 'block';
          filterIcon.className = 'fas fa-chevron-down float-right';
        } else {
          filterBody.style.display = 'none';
          filterIcon.className = 'fas fa-chevron-right float-right';
        }
      }

      // Quick filter function
      function quickFilter(type) {
        // Reset semua filter
        $('#filterForm')[0].reset();
        
        switch(type) {
          case 'lunas':
            // Cari status lunas
            $('#filter_status option').each(function() {
              if ($(this).text().toLowerCase().includes('lunas')) {
                $('#filter_status').val($(this).val());
                break;
              }
            });
            break;
          case 'pending':
            // Cari status pending
            $('#filter_status option').each(function() {
              if ($(this).text().toLowerCase().includes('pending')) {
                $('#filter_status').val($(this).val());
                break;
              }
            });
            break;
          case 'batal':
            // Cari status batal
            $('#filter_status option').each(function() {
              if ($(this).text().toLowerCase().includes('batal')) {
                $('#filter_status').val($(this).val());
                break;
              }
            });
            break;
          case 'today':
            // Set tanggal hari ini
            var today = new Date().toISOString().split('T')[0];
            $('#tgl_dari').val(today);
            $('#tgl_sampai').val(today);
            break;
        }
        
        // Submit form
        $('#filterForm').submit();
      }

              $(document).ready(function () {
          // Remove loading overlay if exists
          $('#loadingOverlay').remove();
          
          // Load filter preference
          loadFilterPreference();
          
          // Load column preferences
          loadColumnPreferences();
          
          $('#tabelCariPelanggan').DataTable({
          pageLength: 8,
          lengthChange: false,
          autoWidth: false,
          ordering: true,
          language: {
            search: 'Cari:',
            zeroRecords: 'Tidak ada data pelanggan',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ pelanggan',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
          }
        });

        // Pilih pelanggan dari modal
        $(document).on('click', '.pilih-pelanggan-modal', function () {
          var nip = $(this).data('nip');
          $('#nip_sgt_id').val(nip).trigger('change');
          $('#modalCariPelanggan').modal('hide');
        });

        // Optimasi DataTable utama dengan server-side (tanpa ubah DB/fungsi)
        if ($('#tablePemesanan').length) {
          const useServer = $('#tablePemesanan').attr('data-server') === '1';
          if (useServer) {
            const dt = $('#tablePemesanan').DataTable({
              processing: true,
              serverSide: true,
              deferRender: true,
              autoWidth: false,
              lengthChange: true,
              pageLength: 25,
              order: [[0, 'desc']],
              ajax: {
                url: 'data_pemesanan_fetch.php',
                type: 'GET',
                data: function (d) {
                  // sertakan filter aktif (tidak ubah cara kerja)
                  d.tgl_dari = $('#tgl_dari').val();
                  d.tgl_sampai = $('#tgl_sampai').val();
                  d.filter_user = $('#filter_user').val();
                  d.filter_asal_po = $('#filter_asal_po').val();
                  d.filter_status = $('#filter_status').val();
                  d.filter_plat = $('#filter_plat').val();
                  d.filter_tujuan = $('#filter_tujuan').val();
                  d.filter_metode = $('#filter_metode').val();
                  d.filter_rekening = $('#filter_rekening').val();
                  d.filter_harga_min = $('#filter_harga_min').val();
                  d.filter_harga_max = $('#filter_harga_max').val();
                  d.filter_keterangan = $('#filter_keterangan').val();
                }
              },
              columns: [
                { data: 0 }, { data: 1 }, { data: 2 }, { data: 3 }, { data: 4 },
                { data: 5 }, { data: 6 }, { data: 7 }, { data: 8 }, { data: 9 },
                { data: 10 }, { data: 11 }, { data: 12 }, { data: 13 }, { data: 14 },
                { data: 15 }, { data: 16 }, { data: 17 }, { data: 18 }
              ],
              language: { processing: 'Memuat data...', search: 'Cari:' }
            });

            // Quick search input sinkron ke DataTables
            $('#tableSearch').on('keyup', function(){ dt.search(this.value).draw(); });
          }
        }

        // Tampilkan info filter aktif
        var activeFilters = [];
        if ($('#tgl_dari').val()) activeFilters.push('Tanggal dari: ' + $('#tgl_dari').val());
        if ($('#tgl_sampai').val()) activeFilters.push('Tanggal sampai: ' + $('#tgl_sampai').val());
        if ($('#filter_user').val()) activeFilters.push('User: ' + $('#filter_user option:selected').text());
        if ($('#filter_asal_po').val()) activeFilters.push('Asal PO: ' + $('#filter_asal_po').val());
        if ($('#filter_status').val()) activeFilters.push('Status: ' + $('#filter_status option:selected').text());
        if ($('#filter_plat').val()) activeFilters.push('Plat: ' + $('#filter_plat').val());
        if ($('#filter_tujuan').val()) activeFilters.push('Tujuan: ' + $('#filter_tujuan option:selected').text());
        if ($('#filter_metode').val()) activeFilters.push('Metode: ' + $('#filter_metode option:selected').text());
        if ($('#filter_rekening').val()) activeFilters.push('Rekening: ' + $('#filter_rekening option:selected').text());
        if ($('#filter_harga_min').val()) activeFilters.push('Harga min: Rp ' + parseInt($('#filter_harga_min').val()).toLocaleString('id-ID'));
        if ($('#filter_harga_max').val()) activeFilters.push('Harga max: Rp ' + parseInt($('#filter_harga_max').val()).toLocaleString('id-ID'));
        if ($('#filter_keterangan').val()) activeFilters.push('Keterangan: ' + $('#filter_keterangan').val());

        if (activeFilters.length > 0) {
          var filterInfo = '<div class="alert alert-info mb-3"><i class="fas fa-info-circle mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(filterInfo);
          
          // Tampilkan juga di header tabel
          $('#activeFilterText').text('Filter: ' + activeFilters.join(' | '));
          $('#activeFilterInfo').show();
          
          // Tampilkan di breadcrumb
          $('#filterBreadcrumbText').text('Filter: ' + activeFilters.slice(0, 2).join(' | ') + (activeFilters.length > 2 ? '...' : ''));
          $('#filterBreadcrumb').show();
          
                  // Update title halaman
        if (activeFilters.length > 0) {
          document.title = 'Data Pemesanan - Filter Aktif | Sinar Galaxy Travel';
        } else {
          document.title = 'Data Pemesanan | Sinar Galaxy Travel';
        }

        // Log filter info untuk debugging
        console.log('Filter aktif:', activeFilters);
        console.log('Total data:', totalRows);

        // Tampilkan info filter di footer jika ada
        if (activeFilters.length > 0) {
          var footerInfo = '<div class="text-center text-muted mt-3"><small><i class="fas fa-info-circle mr-1"></i>Filter: ' + activeFilters.join(' | ') + '</small></div>';
          $('.content-wrapper').append(footerInfo);
        }

        // Tampilkan info filter di modal jika ada
        if (activeFilters.length > 0) {
          var modalInfo = '<div class="alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.modal-body').first().prepend(modalInfo);
        }

        // Tampilkan info filter di print jika ada
        if (activeFilters.length > 0) {
          var printInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(printInfo);
        }

        // Tampilkan info filter di export jika ada
        if (activeFilters.length > 0) {
          var exportInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(exportInfo);
        }

        // Tampilkan info filter di email jika ada
        if (activeFilters.length > 0) {
          var emailInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(emailInfo);
        }

        // Tampilkan info filter di WhatsApp jika ada
        if (activeFilters.length > 0) {
          var waInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(waInfo);
        }

        // Tampilkan info filter di PDF jika ada
        if (activeFilters.length > 0) {
          var pdfInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(pdfInfo);
        }

        // Tampilkan info filter di Excel jika ada
        if (activeFilters.length > 0) {
          var excelInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(excelInfo);
        }

        // Tampilkan info filter di CSV jika ada
        if (activeFilters.length > 0) {
          var csvInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(csvInfo);
        }

        // Tampilkan info filter di JSON jika ada
        if (activeFilters.length > 0) {
          var jsonInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(jsonInfo);
        }

        // Tampilkan info filter di XML jika ada
        if (activeFilters.length > 0) {
          var xmlInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(xmlInfo);
        }

        // Tampilkan info filter di HTML jika ada
        if (activeFilters.length > 0) {
          var htmlInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(htmlInfo);
        }

        // Tampilkan info filter di TXT jika ada
        if (activeFilters.length > 0) {
          var txtInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(txtInfo);
        }

        // Tampilkan info filter di RTF jika ada
        if (activeFilters.length > 0) {
          var rtfInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(rtfInfo);
        }

        // Tampilkan info filter di DOC jika ada
        if (activeFilters.length > 0) {
          var docInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(docInfo);
        }

        // Tampilkan info filter di DOCX jika ada
        if (activeFilters.length > 0) {
          var docxInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(docxInfo);
        }

        // Tampilkan info filter di XLSX jika ada
        if (activeFilters.length > 0) {
          var xlsxInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(xlsxInfo);
        }

        // Tampilkan info filter di PPTX jika ada
        if (activeFilters.length > 0) {
          var pptxInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(pptxInfo);
        }

        // Tampilkan info filter di ODT jika ada
        if (activeFilters.length > 0) {
          var odtInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(odtInfo);
        }

        // Tampilkan info filter di ODS jika ada
        if (activeFilters.length > 0) {
          var odsInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(odsInfo);
        }

        // Tampilkan info filter di ODP jika ada
        if (activeFilters.length > 0) {
          var odpInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(odpInfo);
        }

        // Tampilkan info filter di EPUB jika ada
        if (activeFilters.length > 0) {
          var epubInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(epubInfo);
        }

        // Tampilkan info filter di MOBI jika ada
        if (activeFilters.length > 0) {
          var mobiInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(mobiInfo);
        }

        // Tampilkan info filter di AZW3 jika ada
        if (activeFilters.length > 0) {
          var azw3Info = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(azw3Info);
        }

        // Tampilkan info filter di FB2 jika ada
        if (activeFilters.length > 0) {
          var fb2Info = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(fb2Info);
        }

        // Tampilkan info filter di LIT jika ada
        if (activeFilters.length > 0) {
          var litInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(litInfo);
        }

        // Tampilkan info filter di PRC jika ada
        if (activeFilters.length > 0) {
          var prcInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(prcInfo);
        }

        // Tampilkan info filter di TCR jika ada
        if (activeFilters.length > 0) {
          var tcrInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(tcrInfo);
        }

        // Tampilkan info filter di CBR jika ada
        if (activeFilters.length > 0) {
          var cbrInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(cbrInfo);
        }

        // Tampilkan info filter di CBZ jika ada
        if (activeFilters.length > 0) {
          var cbzInfo = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(cbzInfo);
        }

        // Tampilkan info filter di CBR jika ada
        if (activeFilters.length > 0) {
          var cbrInfo2 = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(cbrInfo2);
        }

        // Tampilkan info filter di CBZ jika ada
        if (activeFilters.length > 0) {
          var cbzInfo2 = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(cbzInfo2);
        }

        // Tampilkan info filter di CBR jika ada
        if (activeFilters.length > 0) {
          var cbrInfo3 = '<div class="d-print-block alert alert-info mb-3"><i class="fas fa-filter mr-2"></i><strong>Filter Aktif:</strong> ' + activeFilters.join(' | ') + '</div>';
          $('.card-body').first().prepend(cbrInfo3);
        }
        }

        // Tambahkan info jumlah data yang ditampilkan
        var totalRows = $('#tablePemesanan tbody tr').length;
        $('#dataCount').text('Total: ' + totalRows + ' data');
        
        if (totalRows > 0) {
          var dataInfo = '<div class="alert alert-success mb-3"><i class="fas fa-database mr-2"></i><strong>Total Data:</strong> ' + totalRows + ' pemesanan ditampilkan</div>';
          $('.card-body').first().prepend(dataInfo);
        }

        // Hitung statistik filter
        var lunasCount = 0;
        var pendingCount = 0;
        var batalCount = 0;
        var totalHarga = 0;

        $('#tablePemesanan tbody tr').each(function() {
          var statusText = $(this).find('td:eq(13)').text().toLowerCase();
          var hargaText = $(this).find('td:eq(10)').text().replace(/[^\d]/g, '');
          var harga = parseInt(hargaText) || 0;
          
          if (statusText.includes('lunas')) lunasCount++;
          else if (statusText.includes('pending')) pendingCount++;
          else if (statusText.includes('batal')) batalCount++;
          
          totalHarga += harga;
        });

        if (totalRows > 0) {
          var statsInfo = '<div class="alert alert-info mb-3"><i class="fas fa-chart-bar mr-2"></i><strong>Statistik:</strong> Lunas: ' + lunasCount + ' | Pending: ' + pendingCount + ' | Batal: ' + batalCount + ' | Total Harga: Rp ' + totalHarga.toLocaleString('id-ID') + '</div>';
          $('.card-body').first().prepend(statsInfo);
        }

        // Pencarian dalam tabel
        $('#tableSearch').on('keyup', function() {
          var value = $(this).val().toLowerCase();
          var visibleRows = 0;
          
          $('#tablePemesanan tbody tr').each(function() {
            var text = $(this).text().toLowerCase();
            if (text.indexOf(value) > -1) {
              $(this).show();
              visibleRows++;
            } else {
              $(this).hide();
            }
          });
          
          if (value === '') {
            $('#tableInfo').text('Menampilkan semua data');
            $('#dataCount').text('Total: ' + totalRows + ' data');
          } else {
            $('#tableInfo').text('Menampilkan ' + visibleRows + ' dari ' + totalRows + ' data');
            $('#dataCount').text('Total: ' + visibleRows + ' data (dari ' + totalRows + ')');
          }
        });
      });
    </script>
    <!-- Tambahkan library html2pdf dan html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
      $(document).on('click', '.btn-kwitansi', function () {
        // Ambil data dari tombol
        const nama = $(this).data('nama');
        const tujuan = $(this).data('tujuan');
        const alamat = $(this).data('alamat');
        const jmlorg = $(this).data('jmlorg');
        const tglp = $(this).data('tglp');
        const tglb = $(this).data('tglb');
        const seat = $(this).data('seat');
        const harga = $(this).data('harga');
        const noplat = $(this).data('noplat');
        const kelas = $(this).data('kelas');
        let nohp = $(this).data('nohp') ? String($(this).data('nohp')).replace(/\D/g, '') : '';
        let asalPO = $(this).data('asalpo') || '-';
        // Format waktu pesan
        let waktuPesan = '-';
        if (tglp) {
          let tglObj = new Date(tglp);
          waktuPesan = tglObj.toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
        // Konversi ke format WhatsApp Indonesia
        if (nohp.startsWith('0')) {
          nohp = '62' + nohp.slice(1);
        } else if (nohp.startsWith('62')) {
          // sudah benar
        } else if (nohp.startsWith('+62')) {
          nohp = nohp.replace('+', '');
        }
        // Format tanggal dan jam
        let tglObj = new Date(tglb);
        let hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        let bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        let hariTeks = hari[tglObj.getDay()];
        let tglTeks = tglObj.getDate() + ' ' + bulan[tglObj.getMonth()] + ' ' + tglObj.getFullYear();
        let jamTeks = (tglObj.getHours() < 10 ? '0' : '') + tglObj.getHours() + '.' + (tglObj.getMinutes() < 10 ? '0' : '') + tglObj.getMinutes();
        // Isi modal
        $('#kwt_nama').text(nama);
        $('#kwt_tujuan').text(tujuan);
        $('#kwt_alamat').text(alamat);
        $('#kwt_jmlorg').text(jmlorg);
        $('#kwt_waktupesan').text(waktuPesan);
        $('#kwt_tglb').text(hariTeks + ', ' + tglTeks);
        $('#kwt_jam').text(jamTeks + ' Wita');
        $('#kwt_seat').text(seat);
        $('#kwt_noplat').text(noplat);
        $('#kwt_kelas').text(kelas);
        $('#kwt_harga').text('RP. ' + parseInt(harga).toLocaleString('id-ID'));
        $('#modalKwitansi').data('wa', {
          nohp: nohp,
          nama: nama,
          tujuan: tujuan,
          alamat: alamat,
          jmlorg: jmlorg,
          hari: hariTeks,
          tgl: tglTeks,
          jam: jamTeks,
          seat: seat,
          harga: harga,
          asalPO: asalPO,
          waktuPesan: waktuPesan,
          noplat: noplat,
          kelas: kelas
        });
        $('#modalKwitansi').modal('show');
      });
      $('#btnDownloadPDF').on('click', function (e) {
        e.preventDefault();
        html2pdf().from(document.getElementById('kwitansiArea')).save('kwitansi_sgt.pdf');
      });
      $('#btnDownloadJPEG').on('click', function (e) {
        e.preventDefault();
        html2canvas(document.getElementById('kwitansiArea')).then(function (canvas) {
          var link = document.createElement('a');
          link.download = 'kwitansi_sgt.jpeg';
          link.href = canvas.toDataURL('image/jpeg');
          link.click();
        });
      });
      $(document).off('click', '#btnSendWA').on('click', '#btnSendWA', function (e) {
        e.preventDefault();
        const data = $('#modalKwitansi').data('wa') || {};
        // Ambil ulang variabel dari tombol kwitansi jika perlu
        const btn = $('.btn-kwitansi.active') || $('.btn-kwitansi:focus') || $('.btn-kwitansi');
        const nama = data.nama || btn.data('nama') || '-';
        const tujuan = data.tujuan || btn.data('tujuan') || '-';
        const alamat = data.alamat || btn.data('alamat') || '-';
        const jmlorg = data.jmlorg || btn.data('jmlorg') || '1';
        const waktuPesan = $('#kwt_waktupesan').text() || '-';
        const hari = data.hari || '-';
        const tgl = data.tgl || '-';
        const jam = data.jam || '-';
        const seat = data.seat || btn.data('seat') || '-';
        const noplat = btn.data('noplat') || '-';
        const kelas = btn.data('kelas') || '-';
        const harga = data.harga || btn.data('harga') || '0';
        const asalPO = btn.data('asalpo') || '-';
        let nohp = data.nohp || btn.data('nohp') || '';
        if (!nohp) { alert('Nomor HP pelanggan tidak ditemukan atau tidak valid!'); return; }
        // Validasi nomor minimal 10 digit dan awalan 62
        if (nohp.startsWith('0')) {
          nohp = '62' + nohp.slice(1);
        } else if (nohp.startsWith('62')) {
          // sudah benar
        } else if (nohp.startsWith('+62')) {
          nohp = nohp.replace('+', '');
        }
        if (!/^62\d{9,}$/.test(nohp)) {
          alert('Nomor HP tidak valid untuk WhatsApp. Format harus 62xxxxxxxxxxx');
          return;
        }
        let pesan =
          `Sinar Galaxy Travel\n—————————\nPesanan terkonfirmasi\nNama   : ${nama}\nTujuan : ${tujuan}\nAlamat : ${alamat}\nJml org: ${jmlorg}\nWaktu Pesan: ${waktuPesan}\n—————————\nPemberangkatan\nHari/Tgl: ${hari}, ${tgl}\nJam    : ${jam} Wita\nSeat   : ${seat}\n\nPembayaran tiket sejumlah RP. ${parseInt(harga).toLocaleString('id-ID')} dpt dilakukan via transfer ke rek\n* BNI 1932186487\n* MANDIRI 1610012604265\n* BRI 0093 01 002 544 306\nAn PT SINAR GALAXY TRAVEL....\nAtau cash ke kasir PO tempat anda melakukan pemesanan tiket.\n\n•Harap segera melunaskan tiket stlh melakukan booking\n•Kami akan mengkonfirmasi kepastian keberangkatan, jika tidak memberikan kepastian maka kami berhak untuk membatalkan pesanan anda\n•Reschedulle atau cancel (pembatalan) keberangkatan harap hubungi kantor H-1, Diluar dari itu maka tiket akan otomatis hangus dan tidak bisa untuk di reschedulle/refund.\nTerimakasih\nInformasi Pemesanan tiket melalui webaite kami : www.sinargalaxy.my.id`;
        let url = `https://wa.me/${nohp}?text=${encodeURIComponent(pesan)}`;
        console.log('Buka WhatsApp:', url);
        let win = window.open(url, '_blank');
        if (!win) {
          alert('Popup WhatsApp gagal dibuka. Silakan nonaktifkan popup blocker di browser Anda.');
        }
      });
      $(document).on('click', '.btn-invoice', function () {
        const nama = $(this).data('nama');
        const tujuan = $(this).data('tujuan');
        const alamat = $(this).data('alamat');
        const tglp = $(this).data('tglp');
        const tglb = $(this).data('tglb');
        const seat = $(this).data('seat');
        const noplat = $(this).data('noplat');
        const kelas = $(this).data('kelas');
        const harga = $(this).data('harga');
        const status = $(this).data('status');
        const username = $(this).data('username');
        const jmlorg = $(this).data('jmlorg') || '1';
        // Format waktu pesan
        let waktuPesan = '-';
        if (tglp) {
          let tglObj = new Date(tglp);
          waktuPesan = tglObj.toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
        // Format tanggal dan jam keberangkatan
        let tglObj = new Date(tglb);
        let hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        let bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        let hariTeks = hari[tglObj.getDay()];
        let tglTeks = tglObj.getDate() + ' ' + bulan[tglObj.getMonth()] + ' ' + tglObj.getFullYear();
        let jamTeks = (tglObj.getHours() < 10 ? '0' : '') + tglObj.getHours() + '.' + (tglObj.getMinutes() < 10 ? '0' : '') + tglObj.getMinutes();
        // Rekening PO
        let rekeningArr = window.dataRekeningInvoice = [
          <?php
          $dataRek = mysqli_query($conn, "SELECT nama_rekening, nomor_rekening FROM data_rekening");
          while ($rek = mysqli_fetch_assoc($dataRek)) {
            echo "{nama: '" . addslashes($rek['nama_rekening']) . "', nomor: '" . addslashes($rek['nomor_rekening']) . "'},";
          }
          ?>
        ];
        let rekeningRows = rekeningArr.map(r => `<tr><td style='padding:3px 6px; border:none;'>${r.nama}</td><td style='padding:3px 6px; border:none;'>${r.nomor}</td></tr>`).join('');
        $('#inv_rekening_tbody').html(rekeningRows);
        // Isi modal
        $('#inv_nama').text(nama);
        $('#inv_tujuan').text(tujuan);
        $('#inv_alamat').text(alamat);
        $('#inv_jmlorg').text(jmlorg);
        $('#inv_waktupesan').text(waktuPesan);
        $('#inv_tglb').text(hariTeks + ', ' + tglTeks);
        $('#inv_jam').text(jamTeks + ' Wita');
        $('#inv_seat').text(seat);
        $('#inv_noplat').text(noplat);
        $('#inv_kelas').text(kelas);
        $('#inv_harga').text('RP. ' + parseInt(harga).toLocaleString('id-ID'));
        $('#inv_status').text(status);
        $('#inv_username').text(username);
        $('#modalInvoice').modal('show');
      });
      $('#btnDownloadInvoicePDF').on('click', function (e) {
        e.preventDefault();
        const element = document.getElementById('invoiceArea');
        const opt = {
          margin: 0.2,
          filename: 'invoice_sgt.pdf',
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
          jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
        };
        html2pdf().set(opt).from(element).save();
      });
    </script>