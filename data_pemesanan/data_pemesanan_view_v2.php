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

// Unused - data akan diload via AJAX untuk pagination
// Query untuk mengambil data master mapping (untuk digunakan di frontend)

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

// Hitung statistik dengan query terpisah yang lebih optimal
$id_users_escaped = mysqli_real_escape_string($conn, $id_users);
$whereClause = $role === 'super admin' ? '' : "WHERE username = '$id_users_escaped' AND asal_po = '$id_users_escaped'";

// Query untuk total pemesanan
$total_pemesanan = 0;
$totalQuery = "SELECT COUNT(*) as total FROM data_pemesanan $whereClause";
$totalResult = mysqli_query($conn, $totalQuery);
if ($totalResult) {
  $total_pemesanan = mysqli_fetch_assoc($totalResult)['total'];
}

// Query untuk pemesanan hari ini
$today = date('Y-m-d');
$pemesanan_hari_ini = 0;
$todayWhereClause = $whereClause ? $whereClause . " AND DATE(tanggal_pemesanan) = '$today'" : "WHERE DATE(tanggal_pemesanan) = '$today'";
$todayQuery = "SELECT COUNT(*) as total FROM data_pemesanan $todayWhereClause";
$todayResult = mysqli_query($conn, $todayQuery);
if ($todayResult) {
  $pemesanan_hari_ini = mysqli_fetch_assoc($todayResult)['total'];
}

// Query untuk pemesanan bulan ini
$bulan_ini = date('Y-m');
$pemesanan_bulan_ini = 0;
$bulanWhereClause = $whereClause ? $whereClause . " AND DATE_FORMAT(tanggal_pemesanan, '%Y-%m') = '$bulan_ini'" : "WHERE DATE_FORMAT(tanggal_pemesanan, '%Y-%m') = '$bulan_ini'";
$bulanQuery = "SELECT COUNT(*) as total FROM data_pemesanan $bulanWhereClause";
$bulanResult = mysqli_query($conn, $bulanQuery);
if ($bulanResult) {
  $pemesanan_bulan_ini = mysqli_fetch_assoc($bulanResult)['total'];
}

// Inisialisasi variabel statistik keuangan
$total_pendapatan = 0;
$pendapatan_hari_ini = 0;
$pendapatan_bulan_ini = 0;
$pendapatan_tahun_ini = 0;
$total_piutang = 0;
$bankPendapatan = [];
$bankPendapatanHari = [];
$bankPendapatanBulan = [];
$bankPendapatanTahun = [];

// Query untuk mengambil data pembayaran (dioptimasi: hanya memproses transaksi hari ini & piutang DP yang belum lunas)
$todayFilter = "(DATE(tanggal_pemesanan) = '$today' OR payment_methods LIKE '%$today%')";
$piutangFilter = "(status_pembayaran_id NOT IN (1, 3))";
$filterCond = "($todayFilter OR $piutangFilter)";
$paymentWhere = $whereClause ? $whereClause . " AND $filterCond" : "WHERE $filterCond";

$paymentQuery = "SELECT harga_id, jenis_rekening_id, tanggal_pemesanan, payment_methods, status_pembayaran_id FROM data_pemesanan $paymentWhere";
$result = mysqli_query($conn, $paymentQuery);

if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $tanggalPemesanan = substr($row['tanggal_pemesanan'], 0, 10);
    $tahunPemesanan = substr($row['tanggal_pemesanan'], 0, 4);
    $bulanPemesanan = substr($row['tanggal_pemesanan'], 0, 7);

    // Kumpulkan semua pembayaran (multiple maupun single)
    $payments = [];
    if (!empty($row['payment_methods'])) {
      $tmp = json_decode($row['payment_methods'], true);
      if (is_array($tmp))
        $payments = $tmp;
    }
    if (empty($payments)) {
      // Data lama single payment
      $payments[] = [
        'amount' => $row['harga_id'] ?? 0,
        'rekening_id' => $row['jenis_rekening_id'] ?? null,
        'payment_date' => $tanggalPemesanan
      ];
    }

    // Hitung total harga tiket untuk piutang
    $hargaTiket = $row['harga_id'] ?? 0;
    $totalBayar = 0;

    // Proses setiap pembayaran
    foreach ($payments as $pay) {
      $payDate = isset($pay['payment_date']) ? substr($pay['payment_date'], 0, 10) : $tanggalPemesanan;
      $payYear = substr($payDate, 0, 4);
      $payMonth = substr($payDate, 0, 7);

      $rekening_id = $pay['rekening_id'] ?? null;
      $amount = $pay['amount'] ?? 0;
      $totalBayar += $amount;

      if ($rekening_id && isset($rekeningMap[$rekening_id])) {
        $bankName = $rekeningMap[$rekening_id];

        // Total pendapatan semua waktu
        if (!isset($bankPendapatan[$bankName]))
          $bankPendapatan[$bankName] = 0;
        $bankPendapatan[$bankName] += $amount;
        $total_pendapatan += $amount;

        // Pendapatan hari ini
        if ($payDate == $today) {
          if (!isset($bankPendapatanHari[$bankName]))
            $bankPendapatanHari[$bankName] = 0;
          $bankPendapatanHari[$bankName] += $amount;
          $pendapatan_hari_ini += $amount;
        }

        // Pendapatan bulan ini
        if ($payMonth == $bulan_ini) {
          if (!isset($bankPendapatanBulan[$bankName]))
            $bankPendapatanBulan[$bankName] = 0;
          $bankPendapatanBulan[$bankName] += $amount;
          $pendapatan_bulan_ini += $amount;
        }

        // Pendapatan tahun ini
        if ($payYear == date('Y')) {
          if (!isset($bankPendapatanTahun[$bankName]))
            $bankPendapatanTahun[$bankName] = 0;
          $bankPendapatanTahun[$bankName] += $amount;
          $pendapatan_tahun_ini += $amount;
        }
      }
    }

    // Hitung piutang (selisih antara harga tiket dan total bayar)
    if ($totalBayar < $hargaTiket) {
      $total_piutang += ($hargaTiket - $totalBayar);
    }
  }
}
?>
<div class="content-wrapper">
  <!-- Content Header -->
  <section class="content-header pb-2">
    <div class="container-fluid">
      <div class="row align-items-center mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.35rem; letter-spacing: -0.3px;">
            <i class="fas fa-ticket-alt text-success mr-2"></i>Pemesanan Tiket
          </h1>
        </div>
        <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
          <div class="d-inline-flex flex-column align-items-sm-end text-sm">
            <span class="text-muted"><i class="far fa-calendar-check mr-1"></i><?= date('d M Y') ?> &bull; Cabang PO: <strong class="text-dark"><?= htmlspecialchars($role === 'super admin' ? 'Semua Cabang' : $asal_po) ?></strong></span>
            <span class="text-muted">Hak Akses: <span class="badge badge-success text-uppercase font-weight-normal px-2 py-0"><?= htmlspecialchars($role) ?></span></span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <style>
      .metric-card-pm {
        background: #ffffff;
        border: 1px solid #b8b8b8;
        border-radius: 4px;
        padding: 14px 16px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        transition: border-color .15s ease-in-out;
      }
      .metric-card-pm:hover {
        border-color: #0d9f4f;
      }
      .metric-card-pm .metric-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 6px;
      }
      .metric-card-pm .metric-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      .metric-card-pm .metric-icon {
        width: 36px;
        height: 36px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
      }
      .metric-card-pm .metric-value {
        font-size: 1.45rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
        margin-bottom: 3px;
      }
      .metric-card-pm .metric-sub {
        font-size: 0.8rem;
        color: #6b7280;
      }
      .bank-chip {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 4px;
        font-size: 0.825rem;
        background: #ffffff;
        border: 1px solid #b8b8b8;
        margin-right: 8px;
        margin-bottom: 6px;
      }
      .bank-chip.has-value {
        border-color: #0d9f4f;
        background: #f0f7f3;
      }
      .bank-chip .chip-name {
        font-weight: 600;
        color: #333333;
        margin-right: 8px;
      }
      .bank-chip .chip-val {
        font-weight: 700;
        color: #0d9f4f;
      }
      #tablePemesanan thead th {
        font-size: 12px !important;
        white-space: nowrap;
        padding: 8px 6px !important;
      }
      #tablePemesanan tbody td {
        font-size: 12.5px !important;
        padding: 6px 8px !important;
        vertical-align: middle !important;
      }
      .btn-group .btn {
        padding: 3px 6px !important;
        font-size: 11px !important;
      }
      </style>

      <!-- BARIS 1: 4 KARTU METRIK UTAMA -->
      <div class="row mb-3">
        <div class="col-xl-3 col-md-6 col-12 mb-2 mb-xl-0">
          <div class="metric-card-pm" style="border-top: 3px solid #6b7280;">
            <div class="metric-header">
              <span class="metric-title">Total Pemesanan</span>
              <div class="metric-icon" style="background:#f3f4f6; color:#4b5563;">
                <i class="fas fa-ticket-alt"></i>
              </div>
            </div>
            <div class="metric-value"><?= number_format($total_pemesanan) ?> <small class="text-muted text-sm font-weight-normal">Tiket</small></div>
            <div class="metric-sub"><i class="fas fa-database text-muted mr-1"></i>Akumulasi tiket di cabang ini</div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-2 mb-xl-0">
          <div class="metric-card-pm" style="border-top: 3px solid #0284c7;">
            <div class="metric-header">
              <span class="metric-title">Pemesanan Hari Ini</span>
              <div class="metric-icon" style="background:#e0f2fe; color:#0284c7;">
                <i class="fas fa-calendar-day"></i>
              </div>
            </div>
            <div class="metric-value"><?= number_format($pemesanan_hari_ini) ?> <small class="text-muted text-sm font-weight-normal">Tiket</small></div>
            <div class="metric-sub"><i class="far fa-clock text-info mr-1"></i>Pemesanan per <?= date('d M Y') ?></div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12 mb-2 mb-xl-0">
          <div class="metric-card-pm" style="border-top: 3px solid #0d9f4f;">
            <div class="metric-header">
              <span class="metric-title">Pendapatan Hari Ini</span>
              <div class="metric-icon" style="background:#eaf8ef; color:#0d9f4f;">
                <i class="fas fa-hand-holding-usd"></i>
              </div>
            </div>
            <div class="metric-value">Rp <?= number_format($pendapatan_hari_ini) ?></div>
            <div class="metric-sub"><i class="fas fa-check-circle text-success mr-1"></i>Total kas & transfer masuk hari ini</div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
          <div class="metric-card-pm" style="border-top: 3px solid <?= $total_piutang > 0 ? '#d97706' : '#0d9f4f' ?>;">
            <div class="metric-header">
              <span class="metric-title">Total Piutang (DP)</span>
              <div class="metric-icon" style="background:<?= $total_piutang > 0 ? '#fef3c7' : '#eaf8ef' ?>; color:<?= $total_piutang > 0 ? '#d97706' : '#0d9f4f' ?>;">
                <i class="fas fa-balance-scale"></i>
              </div>
            </div>
            <div class="metric-value" style="color:<?= $total_piutang > 0 ? '#d97706' : '#0d9f4f' ?>;">
              Rp <?= number_format($total_piutang) ?>
            </div>
            <div class="metric-sub"><i class="fas fa-info-circle mr-1"></i>Sisa pembayaran tiket belum lunas</div>
          </div>
        </div>
      </div>

      <!-- BARIS 2: PITA KASIR / PENDAPATAN HARI INI PER REKENING & KAS -->
      <div class="card mb-3">
        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
          <span class="font-weight-bold text-dark text-sm"><i class="fas fa-wallet text-success mr-2"></i>Rincian Penerimaan Hari Ini per Rekening & Kasir</span>
          <span class="text-muted text-xs">Per <?= date('d M Y') ?></span>
        </div>
        <div class="card-body p-2 d-flex flex-wrap align-items-center">
          <?php
          $bankListCheck = [
            'CASH (KASIR) PO SUMBAWA' => 'Cash Sumbawa',
            'CASH (KASIR) PO MATARAM' => 'Cash Mataram',
            'BANK BRI' => 'BRI',
            'BANK BCA' => 'BCA',
            'BANK BNI' => 'BNI',
            'BANK MANDIRI' => 'Mandiri'
          ];
          foreach ($bankListCheck as $fullName => $shortLabel):
            $nominal = isset($bankPendapatanHari[$fullName]) ? (float)$bankPendapatanHari[$fullName] : 0;
            $hasVal = $nominal > 0;
          ?>
            <div class="bank-chip <?= $hasVal ? 'has-value' : '' ?>">
              <i class="<?= strpos($fullName, 'CASH') !== false ? 'fas fa-money-bill-wave text-success mr-1' : 'fas fa-university text-primary mr-1' ?>"></i>
              <span class="chip-name"><?= $shortLabel ?>:</span>
              <span class="chip-val" style="color: <?= $hasVal ? '#0d9f4f' : '#6b7280' ?>;">Rp <?= number_format($nominal, 0, ',', '.') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- BANNER NOTIFIKASI KONTROL KASIR (COMPACT & DISMISSIBLE) -->
      <div class="alert alert-light border mb-3 p-2 d-flex justify-content-between align-items-center" role="alert" style="border-color: #b8b8b8 !important; border-left: 4px solid #0d9f4f !important;">
        <div class="d-flex align-items-center text-sm">
          <i class="fas fa-shield-alt text-success fa-lg mr-2"></i>
          <span>
            <strong>Panduan Kasir:</strong> Pastikan pencocokan fisik kas dengan sistem dilakukan setiap sebelum lepas piket. Untuk pemesanan berstatus DP, lakukan pelunasan saat penumpang melunasi sisa tagihan.
          </span>
        </div>
        <button type="button" class="close text-muted text-sm ml-2" data-dismiss="alert" aria-label="Close" style="font-size: 16px;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- CARD UTAMA: DAFTAR DATA PEMESANAN -->
      <div class="card mb-4">
        <div class="card-header py-2 px-3 bg-light d-flex flex-column flex-md-row justify-content-between align-items-md-center">
          <div class="d-flex align-items-center mb-2 mb-md-0">
            <h3 class="card-title font-weight-bold text-dark text-sm m-0">
              <i class="fas fa-list text-success mr-2"></i>Daftar Pemesanan Tiket
            </h3>
          </div>
          <div class="d-flex gap-2 align-items-center flex-wrap" style="gap: 6px;">
            <?php if ($role === 'super admin'): ?>
              <a href="../laporan/manifest_keberangkatan.php" class="btn btn-outline-secondary btn-sm" target="_blank">
                <i class="fas fa-clipboard-list mr-1"></i> Manifest Bos-SGT
              </a>
            <?php endif; ?>
            <a href="../laporan/daftar_keberangkatan_all.php" class="btn btn-outline-secondary btn-sm" target="_blank">
              <i class="fas fa-bus mr-1"></i> Manifest Keberangkatan
            </a>
            <button class="btn btn-success btn-sm font-weight-bold px-3" data-toggle="modal" data-target="#modalForm">
              <i class="fas fa-plus-circle mr-1"></i> Tambah Pemesanan
            </button>
          </div>
        </div>

        <div class="card-body p-2">
          <!-- QUICK FILTER & PENCARIAN TIKET -->
          <div class="row mb-2 align-items-center px-1">
            <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text bg-light"><i class="fas fa-hashtag text-muted"></i></span>
                </div>
                <input type="text" id="searchIdInput" class="form-control" placeholder="Cari ID Tiket (Enter)...">
              </div>
            </div>
            <div class="col-md-9 col-sm-6 text-sm-right text-muted text-xs">
              <i class="fas fa-info-circle mr-1"></i>Gunakan kolom pencarian di pojok kanan tabel untuk filter nama, no hp, plat, tujuan, atau tanggal.
            </div>
          </div>

          <div class="table-responsive">
            <table id="tablePemesanan" class="table table-bordered table-sm table-excel w-100" data-server="1">
              <thead>
                <tr class="bg-light text-center">
                  <th style="width: 45px;">ID</th>
                  <th style="width: 130px;">Aksi</th>
                  <th>NIP/SGT</th>
                  <th>Nama Penumpang</th>
                  <th>Alamat</th>
                  <th>No KTP</th>
                  <th>No HP</th>
                  <th>Tgl Pesan</th>
                  <th>Tgl Berangkat</th>
                  <th>No Plat</th>
                  <th>Kelas</th>
                  <th class="text-right">Harga</th>
                  <th class="text-center">Kursi</th>
                  <th>Tujuan</th>
                  <th class="text-center">Status</th>
                  <th>Metode</th>
                  <th>Rekening</th>
                  <th>Petugas</th>
                  <th>Asal PO</th>
                  <th>Lokasi Jemput</th>
                </tr>
              </thead>
              <tbody>
                <!-- Data dimuat secara asinkron melalui DataTables Server-Side -->
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <!-- Modal Tambah/Edit Pemesanan -->
    <div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="max-width:1050px;">
        <form id="formPemesanan" method="POST" class="modal-content border">
          <div class="modal-header bg-light py-2 px-3 border-bottom">
            <h5 class="modal-title font-weight-bold text-dark text-sm" id="modalFormLabel">
              <i class="fas fa-plus-circle text-success mr-2"></i>Tambah Pemesanan Tiket
            </h5>
            <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-3">
            <input type="hidden" name="id_pemesanan" id="id_pemesanan">

            <!-- SEKSI 1: DATA PELANGGAN -->
            <div class="card mb-3 border">
              <div class="card-header py-1 px-3 bg-light border-bottom">
                <span class="font-weight-bold text-xs text-uppercase text-muted">
                  <i class="fas fa-user-circle text-success mr-1"></i> 1. Identitas Penumpang
                </span>
              </div>
              <div class="card-body p-2">
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="nip_sgt_id">
                        NIP/SGT Pelanggan <span class="text-danger">*</span>
                      </label>
                      <div class="input-group input-group-sm">
                        <select class="form-control" name="nip_sgt_id" id="nip_sgt_id" onchange="autofillPelanggan()" required style="font-size: 13px;">
                          <option value="">-- Pilih Pelanggan --</option>
                          <?php
                          $query = "SELECT nip_sgt, nama FROM data_pelanggan ORDER BY id_pelanggan DESC LIMIT 50";
                          $resultP = mysqli_query($conn, $query);
                          while ($rowP = mysqli_fetch_assoc($resultP)) {
                            $label = $rowP['nip_sgt'] . (!empty($rowP['nama']) ? ' - ' . $rowP['nama'] : '');
                            echo '<option value="' . htmlspecialchars($rowP['nip_sgt']) . '">' . htmlspecialchars($label) . '</option>';
                          }
                          ?>
                        </select>
                        <div class="input-group-append">
                          <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#modalCariPelanggan" title="Cari dari database pelanggan">
                            <i class="fas fa-search"></i> Cari
                          </button>
                          <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalTambahPelangganCepat" title="Tambah Pelanggan Baru Langsung" style="background-color: #0d9f4f; border-color: #076e34;">
                            <i class="fas fa-user-plus"></i> + Baru
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="nama_id">Nama Lengkap</label>
                      <input type="text" class="form-control form-control-sm bg-light" name="nama_id" id="nama_id" readonly placeholder="Otomatis dari NIP">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="no_hp_id">No Handphone / WA</label>
                      <input type="text" class="form-control form-control-sm bg-light" name="no_hp_id" id="no_hp_id" readonly placeholder="Otomatis dari NIP">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-1">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="no_ktp_id">No KTP / NIK</label>
                      <input type="text" class="form-control form-control-sm bg-light" name="no_ktp_id" id="no_ktp_id" readonly placeholder="Otomatis dari NIP">
                    </div>
                  </div>
                  <div class="col-md-8">
                    <div class="form-group mb-1">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="alamat_id">Alamat Lengkap</label>
                      <input type="text" class="form-control form-control-sm bg-light" name="alamat_id" id="alamat_id" readonly placeholder="Otomatis dari NIP">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- SEKSI 2: JADWAL & ARMADA TRAVEL -->
            <div class="card mb-3 border">
              <div class="card-header py-1 px-3 bg-light border-bottom">
                <span class="font-weight-bold text-xs text-uppercase text-muted">
                  <i class="fas fa-shuttle-van text-primary mr-1"></i> 2. Rute, Jadwal & Armada
                </span>
              </div>
              <div class="card-body p-2">
                <div class="row">
                  <div class="col-md-3">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="tanggal_pemesanan">Tgl Pemesanan <span class="text-danger">*</span></label>
                      <input type="datetime-local" class="form-control form-control-sm" name="tanggal_pemesanan" id="tanggal_pemesanan" required>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="tanggal_berangkat">Tgl Keberangkatan <span class="text-danger">*</span></label>
                      <input type="datetime-local" class="form-control form-control-sm" name="tanggal_berangkat" id="tanggal_berangkat" required>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="no_plat_id">Unit Armada / Plat <span class="text-danger">*</span></label>
                      <select class="form-control form-control-sm" name="no_plat_id" id="no_plat_id" onchange="autofillTravel()" required style="font-size: 13px;">
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
                  <div class="col-md-3">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="kelas_id">Kelas Layanan</label>
                      <input type="text" class="form-control form-control-sm bg-light" name="kelas_id" id="kelas_id" placeholder="Otomatis plat" readonly required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-1">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="tujuan_id">Tujuan Perjalanan <span class="text-danger">*</span></label>
                      <select class="form-control form-control-sm" name="tujuan_id" id="tujuan_id" required style="font-size: 13px;">
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
                  <div class="col-md-4">
                    <div class="form-group mb-1">
                      <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="text-xs font-weight-bold text-muted mb-0" for="kursi">
                          Nomor Kursi <span class="text-danger">*</span>
                        </label>
                        <button type="button" id="btnOpenSeatPicker" class="btn btn-xs btn-outline-success font-weight-bold px-2" style="font-size: 11px;">
                          <i class="fas fa-chair mr-1"></i> Pilih Denah
                        </button>
                      </div>
                      <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm font-weight-bold" name="kursi" id="kursi" placeholder="Pilih dari denah..." required>
                        <div class="input-group-append">
                          <button type="button" class="btn btn-outline-success" id="btnOpenSeatPickerAddon" title="Buka Denah Kursi">
                            <i class="fas fa-th"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-1">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="harga_id">Tarif / Harga Tiket (Rp) <span class="text-danger">*</span></label>
                      <input type="number" class="form-control form-control-sm font-weight-bold" name="harga_id" id="harga_id" min="0" placeholder="Rp 0" required>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- SEKSI 3: PEMBAYARAN & REKENING KASIR -->
            <div class="card mb-3 border">
              <div class="card-header py-1 px-3 bg-light border-bottom">
                <span class="font-weight-bold text-xs text-uppercase text-muted">
                  <i class="fas fa-money-check-alt text-success mr-1"></i> 3. Status & Metode Pembayaran
                </span>
              </div>
              <div class="card-body p-2">
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="status_pembayaran_id">Status Pembayaran <span class="text-danger">*</span></label>
                      <select class="form-control form-control-sm" name="status_pembayaran_id" id="status_pembayaran_id" required style="font-size: 13px;">
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
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="payment_type">Tipe Pembayaran <span class="text-danger">*</span></label>
                      <select class="form-control form-control-sm" name="payment_type" id="payment_type" onchange="togglePaymentMethod()" required style="font-size: 13px;">
                        <option value="">-- Pilih Tipe --</option>
                        <option value="single">Pembayaran Tunggal (Single)</option>
                        <option value="multiple">Pembayaran Multiple (Transfer + Cash)</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-2">
                      <label class="text-xs font-weight-bold text-muted mb-1" for="jenis_rekening_id">Rekening / Kasir Tujuan <span class="text-danger">*</span></label>
                      <select class="form-control form-control-sm" name="jenis_rekening_id" id="jenis_rekening_id" required style="font-size: 13px;">
                        <option value="">-- Pilih Rekening / Kasir --</option>
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
                <div id="singlePaymentMethod" style="display: none;" class="p-2 border rounded bg-light mb-2">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group mb-1">
                        <label class="text-xs font-weight-bold text-muted mb-1" for="metode_pembayaran_id">Kanal Pembayaran</label>
                        <select class="form-control form-control-sm" name="metode_pembayaran_id" id="metode_pembayaran_id" style="font-size: 13px;">
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
                      <div class="form-group mb-1">
                        <label class="text-xs font-weight-bold text-muted mb-1" for="single_amount">Jumlah Nominal Bayar</label>
                        <input type="number" class="form-control form-control-sm font-weight-bold" name="single_amount" id="single_amount" min="0" readonly>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Multiple Payment Method -->
                <div id="multiplePaymentMethod" style="display: none;" class="p-2 border rounded bg-light mb-2">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="font-weight-bold text-xs text-muted">Rincian Pembayaran Multiple:</span>
                    <button type="button" class="btn btn-xs btn-outline-success" onclick="addPaymentMethod()">
                      <i class="fas fa-plus mr-1"></i> Tambah Split Pembayaran
                    </button>
                  </div>
                  <div id="paymentMethodsContainer">
                    <!-- Dynamic multiple payment methods -->
                  </div>
                  <div class="row mt-2 align-items-center">
                    <div class="col-md-6 ml-auto">
                      <div class="input-group input-group-sm">
                        <div class="input-group-prepend"><span class="input-group-text bg-light font-weight-bold">Total Terbayar</span></div>
                        <input type="number" class="form-control font-weight-bold text-right text-success" name="total_payment_amount" id="total_payment_amount" min="0" readonly>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- SEKSI 4: KETERANGAN / PENJEMPUTAN -->
            <div class="form-group mb-0">
              <label class="text-xs font-weight-bold text-muted mb-1" for="keterangan">
                <i class="fas fa-map-pin text-danger mr-1"></i> Titik Jemput / Catatan Khusus
              </label>
              <textarea class="form-control form-control-sm" name="keterangan" id="keterangan" rows="2" placeholder="Masukkan lokasi penjemputan spesifik atau keterangan piutang (DP)..."></textarea>
            </div>
          </div>
          <div class="modal-footer py-2 px-3 bg-light border-top">
            <button type="submit" id="btnSave" name="tambah" class="btn btn-sm btn-success font-weight-bold px-4">
              <i class="fas fa-save mr-1"></i> Simpan Transaksi
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i> Batal
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Interactive Seat Picker -->
    <div class="modal fade" id="modalSeatPicker" tabindex="-1" role="dialog" aria-labelledby="modalSeatPickerLabel" aria-hidden="true" style="z-index: 1060;">
      <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
        <div class="modal-content border shadow-sm">
          <div class="modal-header bg-light py-2 px-3 border-bottom">
            <h5 class="modal-title font-weight-bold text-dark text-sm" id="modalSeatPickerLabel">
              <i class="fas fa-chair text-success mr-2"></i>Pilih Nomor Kursi dari Denah
            </h5>
            <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
              <div>
                <span class="text-xs text-muted font-weight-bold d-block">Armada & Jadwal:</span>
                <span class="font-weight-bold text-sm text-dark" id="pickerArmadaInfo">-</span>
              </div>
              <div class="text-right">
                <span class="text-xs text-muted font-weight-bold d-block">Sisa Kursi:</span>
                <span class="badge badge-success font-weight-bold px-2 py-1" id="pickerSisaBadge">0 Kosong</span>
              </div>
            </div>

            <!-- Legend ringkas -->
            <div class="d-flex justify-content-center flex-wrap mb-3 p-1 bg-light border rounded text-xs" style="gap: 8px;">
              <span class="seat-legend-item m-0"><span class="seat-legend-box" style="background:#fff; border:1.5px solid #0d9f4f; width:14px; height:14px;"></span> Kosong</span>
              <span class="seat-legend-item m-0"><span class="seat-legend-box" style="background:#f8fafc; border:1.5px solid #cbd5e1; width:14px; height:14px;"></span> Terisi</span>
              <span class="seat-legend-item m-0"><span class="seat-legend-box" style="background:#fffbeb; border:1.5px solid #f59e0b; width:14px; height:14px;"></span> DP</span>
              <span class="seat-legend-item m-0"><span class="seat-legend-box" style="background:#0d9f4f; border:1.5px solid #076e34; width:14px; height:14px;"></span> Pilihan Anda</span>
            </div>

            <!-- Loading indicator -->
            <div id="pickerLoading" class="text-center py-4" style="display: none;">
              <div class="spinner-border text-success spinner-border-sm" role="status"></div>
              <span class="text-muted ml-2 text-xs font-weight-bold">Memuat denah kursi real-time...</span>
            </div>

            <!-- Cabin container inside picker modal -->
            <div id="pickerCabin" class="cabin-container shadow-none p-3" style="max-width: 420px;">
              <div class="cabin-windshield mb-2 py-1 text-xs">
                <i class="fas fa-arrows-alt-v mr-1"></i> DEPAN / KACA DEPAN ARMADA
              </div>
              <div id="pickerSeatLayout"></div>
            </div>

            <div class="mt-2 text-center text-xs text-muted">
              Pilihan saat ini: <strong class="text-success h6 font-weight-bold mb-0" id="pickerSelectedLabel">Belum Dipilih</strong>
            </div>
          </div>
          <div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">
              Batal
            </button>
            <button type="button" id="btnConfirmSeatSelection" class="btn btn-sm btn-success font-weight-bold px-4">
              <i class="fas fa-check mr-1"></i> Konfirmasi Kursi Ini
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Pencarian Pelanggan -->
    <div class="modal fade" id="modalCariPelanggan" tabindex="-1" role="dialog" aria-labelledby="modalCariPelangganLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border">
          <div class="modal-header bg-light py-2 px-3 border-bottom">
            <h5 class="modal-title font-weight-bold text-dark text-sm" id="modalCariPelangganLabel">
              <i class="fas fa-users text-success mr-2"></i>Pilih Data Pelanggan Terdaftar
            </h5>
            <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-2">
            <div class="table-responsive">
              <table id="tabelCariPelanggan" class="table table-bordered table-sm table-excel w-100">
                <thead>
                  <tr class="bg-light">
                    <th>NIP/SGT</th>
                    <th>Nama Penumpang</th>
                    <th>Alamat</th>
                    <th>No KTP</th>
                    <th>No HP</th>
                    <th style="width: 70px;" class="text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Dimuat via AJAX Server-Side DataTables untuk efisiensi & kecepatan maksimal -->
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between">
            <button type="button" class="btn btn-outline-success btn-sm font-weight-bold" data-dismiss="modal" data-toggle="modal" data-target="#modalTambahPelangganCepat">
              <i class="fas fa-user-plus mr-1"></i> Tambah Pelanggan Baru
            </button>
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Tambah Pelanggan Cepat (Langsung Pilih di Form Pemesanan) -->
    <div class="modal fade" id="modalTambahPelangganCepat" tabindex="-1" role="dialog" aria-labelledby="modalTambahPelangganCepatLabel" aria-hidden="true" style="z-index: 1060;">
      <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 560px;">
        <div class="modal-content border">
          <div class="modal-header bg-light py-2 px-3 border-bottom">
            <h5 class="modal-title font-weight-bold text-dark text-sm" id="modalTambahPelangganCepatLabel">
              <i class="fas fa-user-plus text-success mr-2"></i>Tambah Pelanggan Baru
            </h5>
            <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form id="formTambahPelangganCepat" onsubmit="handleTambahPelangganCepat(event, 'nip_sgt_id')">
            <div class="modal-body p-3 bg-light">
              <div class="form-group mb-2">
                <label class="text-xs font-weight-bold text-dark mb-1">
                  Nama Lengkap Penumpang <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control form-control-sm" name="nama" id="quick_nama" placeholder="Contoh: Budi Santoso" required>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-2">
                    <label class="text-xs font-weight-bold text-dark mb-1">
                      No. HP / WhatsApp <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control form-control-sm" name="no_hp" id="quick_no_hp" placeholder="Contoh: 08123456789" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-2">
                    <label class="text-xs font-weight-bold text-dark mb-1">
                      No. KTP / NIK
                    </label>
                    <input type="text" class="form-control form-control-sm" name="no_ktp" id="quick_no_ktp" placeholder="16 digit NIK (Opsional)">
                  </div>
                </div>
              </div>
              <div class="form-group mb-2">
                <label class="text-xs font-weight-bold text-dark mb-1">
                  Alamat Domisili
                </label>
                <textarea class="form-control form-control-sm" name="alamat" id="quick_alamat" rows="2" placeholder="Alamat lengkap RT/RW, Desa, Kecamatan..."></textarea>
              </div>
              <div class="alert alert-info py-1 px-2 mb-0" style="font-size: 0.8rem;">
                <i class="fas fa-info-circle mr-1"></i> NIP/SGT pelanggan akan digenerate otomatis dan langsung terpilih di formulir tiket.
              </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light border-top">
              <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-sm btn-success font-weight-bold px-3" id="btnSubmitQuickPelanggan" style="background-color: #0d9f4f; border-color: #076e34;">
                <i class="fas fa-check mr-1"></i> Simpan & Pilih Pelanggan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Kwitansi -->
    <div class="modal fade" id="modalKwitansi" tabindex="-1" role="dialog" aria-labelledby="modalKwitansiLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content border">
          <div class="modal-header bg-light py-2 px-3 border-bottom">
            <h5 class="modal-title font-weight-bold text-dark text-sm" id="modalKwitansiLabel">
              <i class="fas fa-receipt text-success mr-2"></i>Kwitansi Konfirmasi Pemesanan
            </h5>
            <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-3">
            <div id="kwitansiArea"
              style="background:#fff; color:#222; padding:24px; border-radius:4px; max-width:400px; margin:auto; font-family:monospace; border:1px solid #b8b8b8;">
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
          <div class="modal-footer py-2 px-3 bg-light border-top">
            <button id="btnDownloadPDF" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf mr-1"></i> Download PDF</button>
            <button id="btnDownloadJPEG" class="btn btn-sm btn-outline-info"><i class="fas fa-file-image mr-1"></i> Download JPEG</button>
            <button id="btnSendWA" class="btn btn-sm btn-success font-weight-bold"><i class="fab fa-whatsapp mr-1"></i> Kirim ke WhatsApp</button>
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End Modal Kwitansi -->

    <!-- Modal Kwitansi Pelanggan -->
    <div class="modal fade" id="modalKwitansiPelanggan" tabindex="-1" role="dialog" aria-labelledby="modalKwitansiPelangganLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content border">
          <div class="modal-header bg-light py-2 px-3 border-bottom">
            <h5 class="modal-title font-weight-bold text-dark text-sm" id="modalKwitansiPelangganLabel">
              <i class="fas fa-print text-success mr-2"></i>Kwitansi Pelanggan Thermal 80mm (Lunas)
            </h5>
            <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-3">
            <div class="alert alert-light border mb-2 p-2 text-xs" style="border-left: 3px solid #0d9f4f !important;">
              <i class="fas fa-info-circle text-success mr-1"></i>
              <strong>Format Thermal 80mm:</strong> Pastikan printer thermal sudah siap. Status pembayaran harus "Lunas" untuk kwitansi sah.
            </div>
            <div id="kwitansiPelangganArea"
              style="background:#fff; color:#222; padding:18px; border-radius:4px; max-width:320px; margin:auto; font-family:monospace; font-size:12px; line-height:1.2; border:1px solid #b8b8b8;">
              <div style="text-align:center; font-weight:bold; font-size:14px; margin-bottom:5px;">SINAR GALAXY TRAVEL</div>
              <div style="text-align:center; font-size:10px; margin-bottom:3px;">PO Sumbawa : Jl. Mangga 1 No. 7 | 081763333330</div>
              <div style="text-align:center; font-size:10px; margin-bottom:8px;">PO Mataram : Jl. Airlangga Squar | 082339860600</div>
              <div style="text-align:center; border-top:1px dashed #000; border-bottom:1px dashed #000; padding:4px 0; margin-bottom:8px;">
                <span style="font-weight:bold; font-size:12px;">KWITANSI PELANGGAN</span>
              </div>
              <div style="margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>No. Kwitansi</span>
                  <span id="kwtp_no">: <?= date('Ymd') ?>-<?= sprintf('%04d', rand(1, 9999)) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Tanggal</span>
                  <span id="kwtp_tanggal">: <?= date('d/m/Y H:i') ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Nama</span>
                  <span id="kwtp_nama">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Alamat</span>
                  <span id="kwtp_alamat">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>No. HP</span>
                  <span id="kwtp_nohp">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Asal PO</span>
                  <span id="kwtp_asalpo">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Keterangan</span>
                  <span id="kwtp_keterangan">: -</span>
                </div>
              </div>
              <div style="border-top:1px dashed #000; padding:5px 0; margin-bottom:8px;">
                <div style="font-weight:bold; text-align:center; margin-bottom:4px;">DETAIL PERJALANAN</div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Tujuan</span>
                  <span id="kwtp_tujuan">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Tgl Berangkat</span>
                  <span id="kwtp_tglb">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Jam</span>
                  <span id="kwtp_jam">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>No. Plat</span>
                  <span id="kwtp_noplat">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Kelas</span>
                  <span id="kwtp_kelas">: -</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Seat</span>
                  <span id="kwtp_seat">: -</span>
                </div>
              </div>
              <div style="border-top:1px dashed #000; padding:5px 0; margin-bottom:8px;">
                <div style="font-weight:bold; text-align:center; margin-bottom:4px;">PEMBAYARAN</div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Harga Tiket</span>
                  <span id="kwtp_harga">: Rp 0</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Status</span>
                  <span style="font-weight:bold; color:#0d9f4f;">: LUNAS</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                  <span>Metode Bayar</span>
                  <span id="kwtp_metode">: -</span>
                </div>
              </div>
              <div style="border-top:1px dashed #000; padding:5px 0; margin-bottom:8px;">
                <div style="text-align:center; font-size:10px; margin-bottom:3px;">TERIMA KASIH & SELAMAT JALAN</div>
                <div style="text-align:center; font-size:10px;">Perjalanan anda terlindungi dari Asuransi</div>
              </div>
              <div style="text-align:center; border-top:1px dashed #000; padding:4px 0; font-size:10px;">
                www.sinargalaxy.my.id<br>
                <span style="font-size:9px;">Kwitansi ini sah sebagai bukti pembayaran</span><br>
                <span style="font-size:9px;">Dicetak oleh: <?= isset($user['username']) ? htmlspecialchars($user['username']) : 'Admin' ?></span>
              </div>
            </div>
          </div>
          <div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between">
            <div>
              <button id="btnPrintKwitansiPelanggan" class="btn btn-sm btn-success font-weight-bold">
                <i class="fas fa-print mr-1"></i> Print Thermal
              </button>
              <button id="btnDownloadKwitansiPelangganPDF" class="btn btn-sm btn-outline-danger ml-1">
                <i class="fas fa-file-pdf mr-1"></i> Download PDF
              </button>
              <a id="btnBukaEtiketResmi" href="#" target="_blank" class="btn btn-sm btn-primary font-weight-bold ml-1" style="background-color: #0b3b7b; border-color: #082852;">
                <i class="fas fa-id-card mr-1"></i> Cetak E-Tiket Resmi
              </a>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End Modal Kwitansi Pelanggan -->

    <!-- Modal Invoice -->
    <div class="modal fade" id="modalInvoice" tabindex="-1" role="dialog" aria-labelledby="modalInvoiceLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="width: 100%;">
        <div class="modal-content border">
          <div class="modal-header bg-light py-2 px-3 border-bottom">
            <h5 class="modal-title font-weight-bold text-dark text-sm" id="modalInvoiceLabel">
              <i class="fas fa-file-invoice text-success mr-2"></i>Invoice Resmi Pemesanan Tiket
            </h5>
            <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-3">
            <div id="invoiceArea"
              style="background:#fff; color:#222; padding:28px 24px 18px 24px; border-radius:4px; max-width:1000px; margin:auto; font-family:Arial,Helvetica,sans-serif; border:1px solid #b8b8b8; position:relative; overflow:hidden;">
              <img src="../img/logo_sgt.png" alt="Logo SGT"
                style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:420px; opacity:0.08; z-index:0; pointer-events:none; user-select:none; border-radius:50%;" />
              <div style="display:flex; justify-content:space-between; align-items:flex-start; position:relative; z-index:1;">
                <div style="display:flex; align-items:center; gap:18px;">
                  <img src="../img/logo_sgt.png" alt="Logo SGT" style="height:60px; width:auto; display:block; border-radius:50%;" />
                  <div style="font-size:1.1em; font-weight:bold; line-height:1.3;">
                    PT. SINAR GALAXY TRAVEL<br>
                    <span style="font-weight:normal; font-size:0.98em;">
                      Alamat kantor :<br>
                      PO Sumbawa : Jln. Mangga 1 no 7 Sumbawa Besar<br>
                      PO Mataram : Jln. Airlangga Squar<br>
                      web : www.sinargalaxy.my.id
                    </span>
                  </div>
                </div>
                <div style="font-size:2em; font-weight:600; letter-spacing:2px; text-align:right; margin-top:8px;">INVOICE</div>
              </div>
              <table style="width:100%; border-collapse:collapse; margin:24px 0 0 0;">
                <tr>
                  <th colspan="7"
                    style="background:#f0f7f3; color:#076e34; font-weight:bold; text-align:center; padding:9px 8px; border:1px solid #b8b8b8; font-size:1.05em; letter-spacing:0.5px;">
                    PESANAN TERKONFIRMASI</th>
                </tr>
                <tr style="background:#fafafa; text-align:center;">
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">NAMA</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">TUJUAN</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">ALAMAT</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">JML</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">WKTU PESAN</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">METODE PEMBAYARAN</th>
                </tr>
                <tr>
                  <td id="inv_nama" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_tujuan" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_alamat" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_jmlorg" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_waktupesan" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td style="padding:0; border:1px solid #b8b8b8; vertical-align:top;">
                    <table style="width:100%; border-collapse:collapse;">
                      <tbody id="inv_rekening_tbody"></tbody>
                    </table>
                  </td>
                </tr>
              </table>
              <table style="width:100%; border-collapse:collapse; margin:16px 0 0 0; text-align:center;">
                <tr>
                  <th colspan="8"
                    style="background:#f0f7f3; color:#076e34; font-weight:bold; text-align:center; padding:9px 8px; border:1px solid #b8b8b8; font-size:1.05em; letter-spacing:0.5px;">
                    KEBERANGKATAN</th>
                </tr>
                <tr style="background:#fafafa;">
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">HARI/TGL</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">JAM</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">SEAT</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">NO PLAT</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">KELAS</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">JUMLAH Rp</th>
                  <th style="padding:7px 8px; border:1px solid #b8b8b8; font-weight:bold;">SUDAH TERMASUK</th>
                </tr>
                <tr>
                  <td id="inv_tglb" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_jam" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_seat" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_noplat" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_kelas" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td id="inv_harga" style="padding:7px 8px; border:1px solid #b8b8b8;"></td>
                  <td style="padding:7px 8px; border:1px solid #b8b8b8; text-align:left; font-weight:bold;">ASURANSI PERJALANAN</td>
                </tr>
              </table>
              <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:18px;">
                <div style="font-size:0.92em; color:#444; border:1px solid #b8b8b8; border-radius:4px; padding:8px 14px; background:#f9fafb; max-width:70%;">
                  * Harap segera melunasi tiket stlh pemesanan (booking)<br>
                  * Kami akan mengkonfirmasi kepastian keberangkatan. Jika tidak memberikan kepastian maka kami berhak membatalkan pesanan.<br>
                  * Reschedule atau cancel keberangkatan harap hubungi kantor H-1, diluar itu tiket otomatis hangus.
                </div>
                <div style="font-size:1.05em; color:#222; font-weight:bold; text-align:right; min-width:180px;">
                  ADMIN SINAR GALAXY<br><span id="inv_username" style="font-size:0.95em; font-weight:normal;"></span>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer py-2 px-3 bg-light border-top">
            <button id="btnDownloadInvoicePDF" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf mr-1"></i> Download PDF</button>
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End Modal Invoice -->
    <!-- End Modal Invoice -->
    <?php include 'pelunasan_modal_fragment.html'; ?>
    <?php include '../inc/footer.php'; ?>


    <script>
      function autofillPelanggan() {
        var nipSgt = document.getElementById('nip_sgt_id').value;
        if (nipSgt) {
          fetch('get_pelanggan.php?nip_sgt=' + encodeURIComponent(nipSgt))
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                document.getElementById('nama_id').value = data.nama || '';
                document.getElementById('alamat_id').value = data.alamat || '';
                document.getElementById('no_ktp_id').value = data.no_ktp || '';
                document.getElementById('no_hp_id').value = data.no_hp || '';
              }
            })
            .catch(error => {
              console.error('Error autofill pelanggan:', error);
            });
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

        // Pastikan data detail pemesanan diambil via AJAX jika ada data yang kosong atau paymentMethods belum ada
        if (!nipVal || !namaVal || !data.paymentMethods || data.paymentMethods.length === 0) {
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
          } catch (e) { /* abaikan */ }
        }

        $('#id_pemesanan').val(id);

        // 1. Pastikan NIP terdaftar di dropdown select agar nilai awal tidak hilang/kosong
        var $selNip = $('#nip_sgt_id');
        if (nipVal && $selNip.length) {
          if ($selNip.find("option[value='" + nipVal + "']").length === 0) {
            var labelNip = nipVal + (namaVal ? ' - ' + namaVal : '');
            $selNip.append(new Option(labelNip, nipVal, true, true));
          }
          $selNip.val(nipVal);
        }

        // 2. Set Identitas Penumpang langsung dari data pemesanan
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

        // 3. Pastikan no plat terdaftar di dropdown
        var $selPlat = $('#no_plat_id');
        if (platVal && $selPlat.length) {
          if ($selPlat.find("option[value='" + platVal + "']").length === 0) {
            $selPlat.append(new Option(platVal, platVal, true, true));
          }
          $selPlat.val(platVal);
        }

        $('#kelas_id').val(data.kelas || '');
        $('#harga_id').val(data.harga || '');
        $('#kursi').val(data.kursi || '');
        $('#tujuan_id').val(data.tujuan || '');
        $('#status_pembayaran_id').val(data.status || '');
        $('#metode_pembayaran_id').val(data.metode || '');
        $('#jenis_rekening_id').val(data.rekening || '');
        $('#keterangan').val(data.keterangan || '');

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
        var defaultDate = $('#tanggal_pemesanan').val() ? $('#tanggal_pemesanan').val().substr(0, 10) : new Date().toISOString().slice(0, 10);
        $('.payment-method-row[data-index="' + paymentIndex + '"] .payment-date').val(defaultDate);

        calculateTotal();
      }

      function removePaymentMethod(index) {
        $('.payment-method-row[data-index="' + index + '"]').remove();
        // Reindex remaining payment methods
        $('.payment-method-row').each(function (i) {
          $(this).attr('data-index', i);
          $(this).find('select, input').each(function () {
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
        $('.payment-amount').each(function () {
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
          var dpOpt = statusSelect.find('option').filter(function () {
            var t = $(this).text().toLowerCase();
            return t.includes('dp') || t.includes('piutang');
          }).first();
          if (dpOpt.length) statusSelect.val(dpOpt.val());
        } else {
          $('#total_payment_amount').next('.text-warning').remove();
          // Jika total lunas, auto set status Lunas
          if (Math.abs(total - ticketPrice) <= 0.01) {
            var lunasOpt = statusSelect.find('option').filter(function () {
              return $(this).text().toLowerCase().includes('lunas');
            }).first();
            if (lunasOpt.length) statusSelect.val(lunasOpt.val());
          }
        }
      }

      function loadPaymentMethods(paymentMethods) {
        $('#paymentMethodsContainer').empty();
        paymentMethods.forEach(function (payment, index) {
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
          $('.payment-method-row[data-index="' + index + '"] .payment-date').val(payment.payment_date ? payment.payment_date.substring(0, 10) : '');
        });
        calculateTotal();
        // Tambah baris otomatis untuk sisa pembayaran jika belum lunas
        var hargaTicket = parseFloat($('#harga_id').val()) || 0;
        var totalBayar = 0;
        paymentMethods.forEach(function (p) { totalBayar += parseFloat(p.amount || 0); });
        var selisih = hargaTicket - totalBayar;
        if (selisih > 0.01) {
          addPaymentMethod();
          var idxBaru = $('#paymentMethodsContainer .payment-method-row').length - 1;
          var rowBaru = $('.payment-method-row[data-index="' + idxBaru + '"]');
          rowBaru.find('.payment-amount').val(selisih);
          rowBaru.find('.payment-date').val(new Date().toISOString().slice(0, 10));
        }
      }

      // Update single amount when harga changes
      $('#harga_id').on('change', function () {
        if ($('#payment_type').val() === 'single') {
          $('#single_amount').val($(this).val());
        }
      });

      // Handle delete button
      // Handle lunaskan button
      $(document).on('click', '.btn-lunaskan', function () {
        const id = $(this).attr('data-id') ?? null; // gunakan attr agar tidak undefined
        if (!id) {
          alert('ID pemesanan tidak ditemukan.');
          return;
        }
        if (!confirm('Lunaskan pesanan ini?')) return;

        $.post('lunaskan_pemesanan.php', { id: id }, function (resp) {
          try {
            if (resp && resp.success) {
              if (typeof Swal !== 'undefined') {
                Swal.fire('Berhasil', 'Pelunasan berhasil', 'success').then(() => {
                  location.reload();
                });
              } else {
                alert('Pelunasan berhasil');
                location.reload();
              }
            } else {
              const errorMsg = resp?.msg || 'Gagal melunaskan';
              if (typeof Swal !== 'undefined') {
                Swal.fire('Error', errorMsg, 'error');
              } else {
                alert(errorMsg);
              }
            }
          } catch (e) {
            const errorMsg = 'Respons tidak valid';
            if (typeof Swal !== 'undefined') {
              Swal.fire('Error', errorMsg, 'error');
            } else {
              alert(errorMsg);
            }
          }
        }, 'json').fail(function (xhr) {
          const errorMsg = xhr.responseText || 'Error Ajax';
          if (typeof Swal !== 'undefined') {
            Swal.fire('Error', errorMsg, 'error');
          } else {
            alert(errorMsg);
          }
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
        background: rgba(0, 0, 0, 0.4);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
      `;
        // Create dialog
        const dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.style.cssText = `
        background: #ffffff;
        border-radius: 4px;
        border: 1.5px solid #a0a0a0;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        max-width: 420px;
        width: 90%;
        overflow: hidden;
      `;
        dialog.innerHTML = `
        <div style="background: #fff8f8; color: #b71c1c; padding: 14px 18px; border-bottom: 1px solid #f1caca; display: flex; align-items: center; gap: 10px;">
          <i class="fa fa-exclamation-triangle" style="font-size: 1.25rem;"></i>
          <h4 style="font-size: 1rem; font-weight: 700; margin: 0; letter-spacing: 0.3px;">Konfirmasi Hapus Data</h4>
        </div>
        <div style="padding: 20px 18px; color: #333333; font-size: 0.95rem; line-height: 1.5;">
          ${message}
          <div style="margin-top: 10px; font-size: 0.82rem; color: #777777;">
            <i class="fa fa-info-circle text-muted"></i> Tindakan ini tidak dapat dibatalkan setelah data dihapus.
          </div>
        </div>
        <div style="padding: 12px 18px; background: #fafafa; border-top: 1px solid #e0e0e0; display: flex; gap: 8px; justify-content: flex-end;">
          <button onclick="closeConfirmDialog()" class="btn btn-default btn-flat" style="padding: 6px 14px; font-size: 0.85rem; font-weight: 600; border-radius: 3px; border: 1px solid #b8b8b8; background: #ffffff;">
            <i class="fa fa-times text-muted"></i> Batal
          </button>
          <button onclick="proceedDelete('${url}')" class="btn btn-danger btn-flat" style="padding: 6px 14px; font-size: 0.85rem; font-weight: 600; border-radius: 3px; background: #c62828; border: 1px solid #b71c1c;">
            <i class="fa fa-trash"></i> Ya, Hapus
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

      function handleTambahPelangganCepat(e, targetSelectId) {
        e.preventDefault();
        var form = e.target;
        var btn = document.getElementById('btnSubmitQuickPelanggan');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...';

        var formData = new FormData(form);

        fetch('../data_pelanggan/data_pelanggan_ajax_add.php', {
          method: 'POST',
          body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-check mr-1"></i> Simpan & Pilih Pelanggan';

          if (res.status === 'success') {
            var p = res.data;
            var $select = $('#' + targetSelectId);

            var label = p.nip_sgt + (p.nama ? ' - ' + p.nama : '');
            var newOpt = new Option(label, p.nip_sgt, true, true);
            $select.append(newOpt).trigger('change');

            if (document.getElementById('nama_id')) document.getElementById('nama_id').value = p.nama;
            if (document.getElementById('alamat_id')) document.getElementById('alamat_id').value = p.alamat;
            if (document.getElementById('no_ktp_id')) document.getElementById('no_ktp_id').value = p.no_ktp;
            if (document.getElementById('no_hp_id')) document.getElementById('no_hp_id').value = p.no_hp;

            $('#modalTambahPelangganCepat').modal('hide');
            form.reset();

            if (typeof Swal !== 'undefined') {
              Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Pelanggan berhasil ditambahkan & dipilih!',
                showConfirmButton: false,
                timer: 2500
              });
            }
          } else {
            alert(res.message || 'Gagal menyimpan data pelanggan.');
          }
        })
        .catch(function(err) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-check mr-1"></i> Simpan & Pilih Pelanggan';
          console.error(err);
          alert('Terjadi kesalahan koneksi.');
        });
      }

      $(document).ready(function () {
        $('#tabelCariPelanggan').DataTable({
          processing: true,
          serverSide: true,
          pageLength: 8,
          lengthChange: false,
          autoWidth: false,
          ordering: false,
          ajax: {
            url: '../data_pelanggan/data_pelanggan_modal_fetch.php',
            type: 'GET'
          },
          language: {
            processing: 'Memuat data...',
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
          var nama = $(this).data('nama') || '';
          var $sel = $('#nip_sgt_id');
          if ($sel.find("option[value='" + nip + "']").length === 0) {
            var label = nip + (nama ? ' - ' + nama : '');
            $sel.append(new Option(label, nip, true, true));
          }
          $sel.val(nip).trigger('change');
          if (typeof autofillPelanggan === 'function') {
            autofillPelanggan();
          }
          $('#modalCariPelanggan').modal('hide');
        });

        // Server-side DataTable untuk tabel pemesanan utama
        if ($('#tablePemesanan').length && !$.fn.DataTable.isDataTable('#tablePemesanan')) {
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
                type: 'GET'
              },
              language: {
                processing: 'Memuat data...',
                search: 'Cari:',
                zeroRecords: 'Tidak ada data pemesanan',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                lengthMenu: 'Tampilkan _MENU_ data',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
              }
            });

            // Pencarian khusus kolom ID (kolom 0)
            $('#searchIdInput').on('keyup change', function () {
              const val = this.value || '';
              if (/^\d+$/.test(val)) {
                dt.column(0).search('^' + val + '$', true, false).draw();
              } else {
                dt.column(0).search(val, false, true).draw();
              }
            });
          }
        }
      });
    </script>
    <!-- CSS untuk Print Thermal -->
    <style>
      @media print {
        @page {
          margin: 0;
          size: 80mm auto;
        }

        body {
          margin: 0;
          padding: 5px;
          font-family: monospace;
          font-size: 12px;
          line-height: 1.2;
          width: 80mm;
          max-width: 80mm;
        }

        .no-print {
          display: none !important;
        }

        .modal {
          position: static !important;
        }

        .modal-dialog {
          max-width: none !important;
        }

        .modal-content {
          border: none !important;
          box-shadow: none !important;
        }

        .modal-header,
        .modal-footer {
          display: none !important;
        }

        .modal-body {
          padding: 0 !important;
        }

        .alert {
          display: none !important;
        }
      }

      /* Style khusus untuk kwitansi pelanggan */
      .thermal-receipt {
        font-family: monospace;
        font-size: 12px;
        line-height: 1.2;
        width: 80mm;
        max-width: 80mm;
        margin: 0 auto;
        padding: 10px;
        background: white;
        color: black;
      }

      .thermal-receipt .header {
        text-align: center;
        font-weight: bold;
        margin-bottom: 5px;
      }

      .thermal-receipt .divider {
        border-top: 1px dashed #000;
        border-bottom: 1px dashed #000;
        padding: 5px 0;
        margin: 5px 0;
        text-align: center;
        font-weight: bold;
      }

      .thermal-receipt .info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 3px;
        font-size: 11px;
      }

      .thermal-receipt .footer {
        text-align: center;
        font-size: 10px;
        margin-top: 10px;
      }
    </style>

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
        const btn = $('.btn-kwitansi.active').length ? $('.btn-kwitansi.active') :
          $('.btn-kwitansi:focus').length ? $('.btn-kwitansi:focus') :
            $('.btn-kwitansi').first();
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
        // WhatsApp URL generated successfully
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

      // Kwitansi Pelanggan
      $(document).on('click', '.btn-kwitansi-pelanggan', function () {
        // Ambil data dari tombol
        const idPemesanan = $(this).data('id-pemesanan');
        const nama = $(this).data('nama');
        const tujuan = $(this).data('tujuan');
        const alamat = $(this).data('alamat');
        const tglp = $(this).data('tglp');
        const tglb = $(this).data('tglb');
        const seat = $(this).data('seat');
        const harga = $(this).data('harga');
        const noplat = $(this).data('noplat');
        const kelas = $(this).data('kelas');
        const nohp = $(this).data('nohp');
        const asalPO = $(this).data('asalpo');
        const keterangan = $(this).data('keterangan');
        const status = $(this).data('status');
        const metode = $(this).data('metode');

        // Format tanggal dan jam keberangkatan
        let tglObj = new Date(tglb);
        let hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        let bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        let hariTeks = hari[tglObj.getDay()];
        let tglTeks = tglObj.getDate() + ' ' + bulan[tglObj.getMonth()] + ' ' + tglObj.getFullYear();
        let jamTeks = (tglObj.getHours() < 10 ? '0' : '') + tglObj.getHours() + ':' + (tglObj.getMinutes() < 10 ? '0' : '') + tglObj.getMinutes();

        // Isi modal
        $('#kwtp_no').text(': SGT-' + idPemesanan.toString().padStart(6, '0'));
        $('#kwtp_tanggal').text(': ' + new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));

        // Update informasi admin yang mencetak
        const adminName = '<?= isset($user['username']) ? htmlspecialchars($user['username']) : 'Admin' ?>';
        $('#kwitansiPelangganArea').find('span:contains("Dicetak oleh:")').next().text(adminName);
        $('#kwtp_nama').text(': ' + nama);
        $('#kwtp_alamat').text(': ' + alamat);
        $('#kwtp_nohp').text(': ' + nohp);
        $('#kwtp_asalpo').text(': ' + asalPO);
        $('#kwtp_keterangan').text(': ' + (keterangan || '-'));
        $('#kwtp_tujuan').text(': ' + tujuan);
        $('#kwtp_tglb').text(': ' + hariTeks + ', ' + tglTeks);
        $('#kwtp_jam').text(': ' + jamTeks + ' Wita');
        $('#kwtp_noplat').text(': ' + noplat);
        $('#kwtp_kelas').text(': ' + kelas);
        $('#kwtp_seat').text(': ' + seat);
        $('#kwtp_harga').text(': Rp ' + parseInt(harga).toLocaleString('id-ID'));
        $('#kwtp_metode').text(': ' + (metode || 'Cash/Transfer'));
        $('#btnBukaEtiketResmi').attr('href', 'cetak_etiket.php?id=' + idPemesanan);

        // Update status berdasarkan data sebenarnya
        const statusElement = $('#kwitansiPelangganArea').find('span[style*="color:#28a745"]');
        if (status && status.toLowerCase().includes('lunas')) {
          statusElement.text(': LUNAS').css('color', '#28a745');
        } else {
          statusElement.text(': ' + (status || 'PENDING')).css('color', '#ffc107');
          // Tampilkan pesan peringatan
          alert('Perhatian: Status pembayaran bukan "Lunas". Kwitansi pelanggan sebaiknya hanya dicetak untuk pembayaran yang sudah lunas.');
        }

        $('#modalKwitansiPelanggan').modal('show');
      });

      // Print Thermal Kwitansi Pelanggan
      $('#btnPrintKwitansiPelanggan').on('click', function (e) {
        e.preventDefault();

        if (!confirm('Apakah Anda yakin ingin mencetak kwitansi pelanggan ini dengan printer thermal 80mm?')) {
          return;
        }

        // Buat window baru untuk print thermal
        const printWindow = window.open('', '_blank', 'width=320,height=600');
        const printContent = document.getElementById('kwitansiPelangganArea').innerHTML;

        printWindow.document.write(`
          <!DOCTYPE html>
          <html>
          <head>
            <title>Kwitansi Pelanggan - SGT</title>
            <style>
              @media print {
                @page {
                  margin: 0;
                  size: 80mm auto;
                }
                body {
                  margin: 0;
                  padding: 5px;
                  font-family: monospace;
                  font-size: 12px;
                  line-height: 1.2;
                  width: 80mm;
                  max-width: 80mm;
                }
                .no-print { display: none !important; }
                .modal { position: static !important; }
                .modal-dialog { max-width: none !important; }
                .modal-content { border: none !important; box-shadow: none !important; }
                .modal-header, .modal-footer { display: none !important; }
                .modal-body { padding: 0 !important; }
                .alert { display: none !important; }
              }
              body {
                font-family: monospace;
                font-size: 12px;
                line-height: 1.2;
                margin: 0;
                padding: 5px;
                width: 80mm;
                max-width: 80mm;
              }
              .thermal-receipt {
                font-family: monospace;
                font-size: 12px;
                line-height: 1.2;
                width: 80mm;
                max-width: 80mm;
                margin: 0 auto;
                padding: 10px;
                background: white;
                color: black;
              }
              .print-info {
                text-align: center;
                font-size: 10px;
                color: #666;
                margin-top: 10px;
                border-top: 1px dashed #ccc;
                padding-top: 5px;
              }
            </style>
          </head>
          <body>
            <div class="thermal-receipt">
              ${printContent}
            </div>
            <div class="print-info">
              <strong>Printer Thermal 80mm - Sinar Galaxy Travel</strong><br>
              Kwitansi ini dioptimalkan untuk printer thermal
            </div>
            <div class="no-print" style="text-align:center; margin-top:20px;">
              <button onclick="window.print()" style="padding: 10px 20px; margin: 5px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Thermal</button>
              <button onclick="window.close()" style="padding: 10px 20px; margin: 5px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Tutup</button>
            </div>
          </body>
          </html>
        `);

        printWindow.document.close();
      });

      // Download PDF Kwitansi Pelanggan
      $('#btnDownloadKwitansiPelangganPDF').on('click', function (e) {
        e.preventDefault();
        const element = document.getElementById('kwitansiPelangganArea');
        const opt = {
          margin: 0.1,
          filename: 'kwitansi_pelanggan_sgt.pdf',
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
          jsPDF: { unit: 'mm', format: [80, 'auto'], orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
      });

      /* ==========================================================
         SEAT PICKER DENAH INTERAKTIF LOGIC
         ========================================================== */
      let pickerSelectedSeat = null;
      let pickerSeatsData = [];

      // Buka Modal Seat Picker
      $('#btnOpenSeatPicker, #btnOpenSeatPickerAddon').on('click', function() {
        let tglBerangkat = $('#tanggal_berangkat').val();
        let noPlat = $('#no_plat_id').val();

        if (!tglBerangkat) {
          alert('Silakan tentukan Tanggal & Jam Keberangkatan terlebih dahulu!');
          $('#tanggal_berangkat').focus();
          return;
        }

        if (!noPlat) {
          alert('Silakan pilih Unit Armada / Plat terlebih dahulu!');
          $('#no_plat_id').focus();
          return;
        }

        let parts = tglBerangkat.split('T');
        let tgl = parts[0];
        let jam = parts[1] || '10:00';
        let excludeId = $('#id_pemesanan').val() || 0;

        $('#pickerArmadaInfo').text(noPlat + ' • ' + tgl + ' ' + jam + ' WITA');
        $('#pickerLoading').show();
        $('#pickerCabin').css('opacity', '0.3');
        $('#modalSeatPicker').modal('show');

        pickerSelectedSeat = $('#kursi').val() ? parseInt($('#kursi').val()) : null;
        updatePickerSelectionLabel();

        $.ajax({
          url: 'get_kursi_status.php',
          type: 'GET',
          data: {
            tanggal: tgl,
            jam: jam,
            no_plat: noPlat,
            exclude_id: excludeId
          },
          dataType: 'json',
          success: function(resp) {
            $('#pickerLoading').hide();
            $('#pickerCabin').css('opacity', '1');

            if (resp && resp.success) {
              pickerSeatsData = resp.seats;
              $('#pickerSisaBadge').text(resp.stats.tersedia + ' Kosong / ' + resp.stats.total + ' Seat');
              renderPickerCabin(resp);
            } else {
              alert(resp.message || 'Gagal memuat denah kursi.');
              $('#modalSeatPicker').modal('hide');
            }
          },
          error: function() {
            $('#pickerLoading').hide();
            $('#pickerCabin').css('opacity', '1');
            alert('Gagal menghubungi server untuk memuat status kursi.');
          }
        });
      });

      function renderPickerCabin(resp) {
        let armada = resp.armada;
        let totalSeats = armada.jumlah_kursi;
        let kelas = armada.kelas ? armada.kelas.toUpperCase() : 'EKONOMI';
        let seats = resp.seats;
        let seatMap = {};
        seats.forEach(function(s) { seatMap[s.nomor] = s; });

        let html = '';

        if (kelas.indexOf('VIP') !== -1 && totalSeats <= 9) {
          // VIP 9 SEATS
          html += '<div class="seat-row">' + renderPickerBox(seatMap[1]) + '<div class="seat-aisle"><i class="fas fa-ellipsis-v"></i></div><div class="seat-box seat-driver"><i class="fas fa-id-badge"></i><span class="seat-label">SUPIR</span></div></div>';
          html += '<div class="seat-row">' + renderPickerBox(seatMap[2]) + '<div class="seat-aisle"><i class="fas fa-ellipsis-v"></i></div>' + renderPickerBox(seatMap[3]) + '</div>';
          html += '<div class="seat-row">' + renderPickerBox(seatMap[4]) + '<div class="seat-aisle"><i class="fas fa-ellipsis-v"></i></div>' + renderPickerBox(seatMap[5]) + '</div>';
          html += '<div class="seat-row">' + renderPickerBox(seatMap[6]) + renderPickerBox(seatMap[7]) + renderPickerBox(seatMap[8]) + renderPickerBox(seatMap[9]) + '</div>';
        } else if (totalSeats > 11) {
          // PREMIO 15 SEATS
          html += '<div class="seat-row">' + renderPickerBox(seatMap[1]) + '<div class="seat-aisle"></div><div class="seat-box seat-driver"><i class="fas fa-id-badge"></i><span class="seat-label">SUPIR</span></div></div>';
          html += '<div class="seat-row">' + renderPickerBox(seatMap[2]) + renderPickerBox(seatMap[3]) + renderPickerBox(seatMap[4]) + '</div>';
          html += '<div class="seat-row">' + renderPickerBox(seatMap[5]) + renderPickerBox(seatMap[6]) + renderPickerBox(seatMap[7]) + '</div>';
          html += '<div class="seat-row">' + renderPickerBox(seatMap[8]) + renderPickerBox(seatMap[9]) + renderPickerBox(seatMap[10]) + '</div>';
          html += '<div class="seat-row">';
          for (let k = 11; k <= totalSeats; k++) { html += renderPickerBox(seatMap[k]); }
          html += '</div>';
        } else {
          // EKONOMI 11 SEATS
          html += '<div class="seat-row">' + renderPickerBox(seatMap[1]) + '<div class="seat-aisle"></div><div class="seat-box seat-driver"><i class="fas fa-id-badge"></i><span class="seat-label">SUPIR</span></div></div>';
          html += '<div class="seat-row">' + renderPickerBox(seatMap[2]) + renderPickerBox(seatMap[3]) + '<div class="seat-box" style="background:#f8fafc; border:1px dashed #cbd5e1; color:#94a3b8; cursor:default;"><i class="fas fa-door-open"></i><span class="seat-label">PINTU</span></div></div>';
          html += '<div class="seat-row">' + renderPickerBox(seatMap[4]) + renderPickerBox(seatMap[5]) + renderPickerBox(seatMap[6]) + '</div>';
          html += '<div class="seat-row">';
          for (let k = 7; k <= totalSeats; k++) { html += renderPickerBox(seatMap[k]); }
          html += '</div>';
        }

        $('#pickerSeatLayout').html(html);

        // Bind click on available seat boxes
        $('.picker-seat-box').off('click').on('click', function() {
          let num = $(this).data('nomor');
          let status = $(this).data('status');

          if (status !== 'available') {
            alert('Kursi #' + num + ' sudah dipesan oleh penumpang lain!');
            return;
          }

          pickerSelectedSeat = num;
          $('.picker-seat-box').removeClass('seat-selected');
          $(this).addClass('seat-selected');
          updatePickerSelectionLabel();
        });
      }

      function renderPickerBox(seatObj) {
        if (!seatObj) return '<div class="seat-box" style="visibility:hidden;"></div>';
        let num = seatObj.nomor;
        let status = seatObj.status;
        let isSelected = (pickerSelectedSeat === num);
        let cls = 'seat-available picker-seat-box';
        let icon = 'fa-chair';
        let label = 'Kosong';

        if (status === 'occupied') {
          cls = 'seat-occupied seat-disabled';
          icon = 'fa-user-check';
          label = seatObj.data ? seatObj.data.nama.substring(0, 6) : 'Terisi';
        } else if (status === 'dp') {
          cls = 'seat-dp seat-disabled';
          icon = 'fa-user-clock';
          label = 'DP';
        }

        if (isSelected && status === 'available') {
          cls += ' seat-selected';
        }

        return '<div class="seat-box ' + cls + '" data-nomor="' + num + '" data-status="' + status + '" style="height:56px;">' +
               '<i class="fas ' + icon + '"></i>' +
               '<span class="seat-num" style="font-size:0.9rem;">' + num + '</span>' +
               '<span class="seat-label">' + label + '</span>' +
               '</div>';
      }

      function updatePickerSelectionLabel() {
        if (pickerSelectedSeat) {
          $('#pickerSelectedLabel').text('Kursi #' + pickerSelectedSeat);
        } else {
          $('#pickerSelectedLabel').text('Belum Dipilih');
        }
      }

      // Konfirmasi Pilihan Kursi
      $('#btnConfirmSeatSelection').on('click', function() {
        if (!pickerSelectedSeat) {
          alert('Silakan klik salah satu kursi kosong (berwarna hijau) pada denah!');
          return;
        }

        $('#kursi').val(pickerSelectedSeat);
        $('#modalSeatPicker').modal('hide');
      });

      // Handle auto-open dari Kontrol Kursi jika URL memiliki parameter action=new
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('action') === 'new') {
        $('#modalForm').modal('show');
        if (urlParams.get('kursi')) $('#kursi').val(urlParams.get('kursi'));
        if (urlParams.get('tanggal_berangkat')) $('#tanggal_berangkat').val(urlParams.get('tanggal_berangkat'));
        if (urlParams.get('no_plat')) {
          $('#no_plat_id').val(urlParams.get('no_plat'));
          if (typeof autofillTravel === 'function') autofillTravel();
        }
        if (urlParams.get('tujuan')) $('#tujuan_id').val(urlParams.get('tujuan'));
      }
    </script>