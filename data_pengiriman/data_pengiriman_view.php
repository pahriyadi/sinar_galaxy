<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';
$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$id_users = isset($user['id_users']) ? $user['id_users'] : '';
$asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';
$role = isset($user['role']) ? $user['role'] : '';


// Query data pengiriman (tanpa join, value-based)
if ($role === 'super admin') {
    $sql = "SELECT * FROM data_pengiriman";
} else {
    $sql = "SELECT * FROM data_pengiriman WHERE user_id = '$id_users' AND asal_po_id = '$asal_po'";
}
$result = $conn->query($sql);
if (!$result) {
    echo "<div class='alert alert-danger'>Gagal mengambil data pengiriman: " . htmlspecialchars(mysqli_error($conn)) . "</div>";
}

// Handle CRUD (PRG pattern — PHP header redirect)
if (isset($_POST['tambah'])) {
    $_POST['user_id'] = $id_users;
    $_POST['asal_po_id'] = $asal_po;
    tambahPengiriman($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_POST['edit'])) {
    $_POST['user_id'] = $id_users;
    $_POST['asal_po_id'] = $asal_po;
    updatePengiriman($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['hapus'])) {
    deletePengiriman($_GET['hapus']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


// Ambil data master untuk mapping id ke label
$tujuanMap = [];
$res = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
while ($r = mysqli_fetch_assoc($res)) $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];
$statusMap = [];
$res = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
while ($r = mysqli_fetch_assoc($res)) $statusMap[$r['id_status_pembayaran']] = $r['status_pembayaran'];
$metodeMap = [];
$res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
while ($r = mysqli_fetch_assoc($res)) $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran'];
$rekeningMap = [];
$res = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening");
while ($r = mysqli_fetch_assoc($res)) $rekeningMap[$r['id_rekening']] = $r['nama_rekening'];
$userMap = [];
$res = mysqli_query($conn, "SELECT id_users, username FROM data_users");
while ($r = mysqli_fetch_assoc($res)) $userMap[$r['id_users']] = $r['username'];

// Hitung statistik
$total_pengiriman = $result ? mysqli_num_rows($result) : 0;
$pengiriman_hari_ini = 0;
$total_barang = 0;
$total_pendapatan_pengiriman = 0;
$bankPendapatanPengiriman = [];
$today = date('Y-m-d');

if ($result) {
  mysqli_data_seek($result, 0);
  while ($row = mysqli_fetch_assoc($result)) {
    $isToday = isset($row['tanggal_pengiriman']) && substr($row['tanggal_pengiriman'], 0, 10) == $today;
    $isLunas = isset($row['status_pembayaran_id']) && isset($statusMap[$row['status_pembayaran_id']]) && strtolower($statusMap[$row['status_pembayaran_id']]) == 'lunas';
    if ($isToday) {
      $pengiriman_hari_ini++;
    }
    if (isset($row['jumlah']) && is_numeric($row['jumlah'])) {
      $total_barang += $row['jumlah'];
    }
    // Multiple payment support
    if ($isToday && $isLunas && !empty($row['payment_methods'])) {
      $payments = json_decode($row['payment_methods'], true);
      if (is_array($payments)) {
        foreach ($payments as $pay) {
          $rekening_id = $pay['rekening_id'] ?? null;
          $amount = $pay['amount'] ?? 0;
          if ($rekening_id && isset($rekeningMap[$rekening_id])) {
            $bankName = $rekeningMap[$rekening_id];
            if (!isset($bankPendapatanPengiriman[$bankName])) $bankPendapatanPengiriman[$bankName] = 0;
            $bankPendapatanPengiriman[$bankName] += $amount;
            $total_pendapatan_pengiriman += $amount;
          }
        }
      }
    } elseif ($isToday && $isLunas && isset($row['jumlah']) && is_numeric($row['jumlah']) && isset($rekeningMap[$row['jenis_rekening_id']])) {
      // Fallback single payment
      $bankName = $rekeningMap[$row['jenis_rekening_id']];
      if (!isset($bankPendapatanPengiriman[$bankName])) $bankPendapatanPengiriman[$bankName] = 0;
      $bankPendapatanPengiriman[$bankName] += $row['jumlah'];
      $total_pendapatan_pengiriman += $row['jumlah'];
    }
  }
  mysqli_data_seek($result, 0); // Reset pointer
}

// Sekarang baru HTML output
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>
<div class="content-wrapper" style="background-color: #fcfcfc;">
  <!-- Content Header (Page header) -->
  <div class="content-header" style="padding: 14px 18px 8px; background: #ffffff; border-bottom: 1px solid #e0e0e0; margin-bottom: 15px;">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-7">
          <div class="d-flex align-items-center">
            <div style="width: 40px; height: 40px; border-radius: 4px; background: #e6f4ea; border: 1px solid #c8e6c9; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.2rem;">
              <i class="fas fa-shipping-fast"></i>
            </div>
            <div>
              <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Pengiriman Paket</h1>
              <div class="text-muted mt-1" style="font-size: 0.83rem;">
                <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                  <i class="fas fa-building mr-1"></i><?= $role === 'super admin' ? 'Seluruh Cabang' : 'PO ' . htmlspecialchars($asal_po) ?>
                </span>
                <span class="badge badge-secondary" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                  <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars(strtoupper($role)) ?>
                </span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-sm-5 text-right">
          <button type="button" class="btn btn-default btn-sm btn-flat" onclick="location.reload();" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600; margin-right: 6px;">
            <i class="fas fa-sync-alt mr-1 text-muted"></i> Refresh Data
          </button>
          <button type="button" class="btn btn-success btn-sm btn-flat" data-toggle="modal" data-target="#modalForm" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600; padding: 6px 14px;">
            <i class="fas fa-plus mr-1"></i> Tambah Pengiriman
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Main content -->
  <section class="content" style="padding: 0 15px;">
    <!-- 4 KPI Metrics Card (Paper White v2.0) -->
    <div class="row mb-3">
      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Pengiriman</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #202124; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($total_pengiriman) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Resi</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f8f9fa; border: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                <i class="fas fa-shipping-fast" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-database mr-1"></i> Keseluruhan paket terdaftar
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Pengiriman Hari Ini</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #0d9f4f; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($pengiriman_hari_ini) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Resi</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #e6f4ea; border: 1px solid #c8e6c9; display: flex; align-items: center; justify-content: center; color: #0d9f4f;">
                <i class="fas fa-calendar-day" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-clock mr-1 text-success"></i> <?= date('d M Y') ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Paket / Barang</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #d97706; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($total_barang) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Koli</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #fef3c7; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: center; color: #d97706;">
                <i class="fas fa-boxes" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-archive mr-1 text-warning"></i> Akumulasi koli tercatat
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Pendapatan Hari Ini</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #16a34a; line-height: 1.2; margin-top: 4px;">
                  Rp <?= number_format($total_pendapatan_pengiriman, 0, ',', '.') ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #e6f4ea; border: 1px solid #c8e6c9; display: flex; align-items: center; justify-content: center; color: #16a34a;">
                <i class="fas fa-money-bill-wave" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-check-circle mr-1 text-success"></i> Kas & transfer masuk (Lunas)
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pita Kas Masuk Hari Ini per Rekening Bank -->
    <div class="p-2 mb-3" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px;">
      <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
        <span class="text-muted" style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 4px;">
          <i class="fas fa-cash-register mr-1 text-success"></i> Kas Masuk Hari Ini:
        </span>
        <?php
        $bankDisplayList = [
          'CASH (KASIR) PO SUMBAWA' => 'Cash Sumbawa',
          'CASH (KASIR) PO MATARAM' => 'Cash Mataram',
          'BANK BRI' => 'BRI',
          'BANK BCA' => 'BCA',
          'BANK BNI' => 'BNI',
          'BANK MANDIRI' => 'Mandiri'
        ];
        foreach ($bankDisplayList as $fullBank => $shortLabel) {
          $nominal = isset($bankPendapatanPengiriman[$fullBank]) ? $bankPendapatanPengiriman[$fullBank] : 0;
          $hasVal = $nominal > 0;
        ?>
        <div style="background: <?= $hasVal ? '#e6f4ea' : '#f8f9fa' ?>; border: 1px solid <?= $hasVal ? '#b7e1cd' : '#dadce0' ?>; border-radius: 3px; padding: 3px 8px; font-size: 0.8rem; display: flex; align-items: center; gap: 5px;">
          <span style="font-weight: 600; color: #3c4043;"><?= $shortLabel ?>:</span>
          <span style="font-weight: 700; color: <?= $hasVal ? '#076e34' : '#70757a' ?>;">Rp <?= number_format($nominal, 0, ',', '.') ?></span>
        </div>
        <?php } ?>
      </div>
    </div>

    <!-- Main Card & DataTables Container -->
    <div class="card" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
      <div class="card-header" style="background: #ffffff; border-bottom: 1.5px solid #a0a0a0; padding: 12px 16px;">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
          <div class="d-flex align-items-center">
            <i class="fas fa-table text-muted mr-2"></i>
            <h3 class="card-title m-0" style="font-size: 0.95rem; font-weight: 700; color: #202124; letter-spacing: 0.2px;">
              Manifest & Ekspedisi Pengiriman Paket
            </h3>
          </div>
          <div class="d-flex align-items-center" style="gap: 8px;">
            <div class="input-group input-group-sm" style="width: 200px;">
              <div class="input-group-prepend">
                <span class="input-group-text" style="background: #f8f9fa; border: 1px solid #b8b8b8; border-right: none; font-size: 0.8rem;">
                  <i class="fas fa-search text-muted"></i>
                </span>
              </div>
              <input type="text" id="searchIdInput" class="form-control form-control-sm" placeholder="Cari No. ID..." style="border: 1px solid #b8b8b8; font-size: 0.82rem;">
            </div>
          </div>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="padding: 10px 14px;">
          <table id="tablePengiriman" class="table table-bordered table-excel table-sm" data-server="1" style="width:100%;">
            <thead>
              <tr>
                <th style="width: 45px; text-align: center;">ID</th>
                <th style="width: 100px; text-align: center;">Aksi</th>
                <th>NIP/SGT</th>
                <th>Nama Pengirim</th>
                <th>Alamat</th>
                <th>No KTP</th>
                <th>No HP</th>
                <th>Tgl Kirim</th>
                <th>Jenis Barang</th>
                <th>Penerima</th>
                <th>HP Penerima</th>
                <th>No Plat</th>
                <th>Kelas</th>
                <th>Tujuan</th>
                <th>Status</th>
                <th>Metode</th>
                <th>Rekening</th>
                <th>Jumlah (Rp)</th>
                <th>Petugas</th>
                <th>Asal PO</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <!-- Data will be loaded via AJAX pagination -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

<!-- Modal Tambah/Edit Pengiriman (Paper White v2.0 - 4 Seksi) -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document" style="max-width:1150px;">
    <form id="formPengiriman" method="POST" class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none;">
      <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 12px 20px;">
        <h5 class="modal-title text-dark" id="modalFormLabel" style="font-size: 1.1rem; font-weight: 700;">
          <i class="fas fa-boxes mr-2 text-success"></i>Formulir Data Pengiriman Paket
        </h5>
        <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style="padding: 20px 22px; background: #fafafa;">
        <input type="hidden" name="id_pengiriman" id="id_pengiriman">

        <!-- Seksi 1: Identitas & Kontak Pengirim -->
        <div class="card mb-3" style="border: 1px solid #d3d3d3; border-radius: 4px; background: #ffffff; box-shadow: none;">
          <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; padding: 8px 14px;">
            <strong style="font-size: 0.88rem; color: #333333;">
              <i class="fas fa-user-tag text-success mr-1"></i> 1. Identitas & Kontak Pengirim (Pelanggan)
            </strong>
          </div>
          <div class="card-body" style="padding: 14px;">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group mb-2">
                  <label for="nip_sgt_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    NIP / SGT Pelanggan <span class="text-danger">*</span>
                  </label>
                    <div class="input-group input-group-sm">
                    <select class="form-control form-control-sm select2-pelanggan" name="nip_sgt_id" id="nip_sgt_id" onchange="autofillPelanggan()" required style="border: 1px solid #b8b8b8;">
                      <option value="">-- Pilih Pelanggan --</option>
                      <?php
                        $query = "SELECT nip_sgt, nama FROM data_pelanggan ORDER BY id_pelanggan DESC LIMIT 50";
                        $resultP = mysqli_query($conn, $query);
                        while ($rowP = mysqli_fetch_assoc($resultP)) {
                          echo '<option value="' . htmlspecialchars($rowP['nip_sgt']) . '">' . htmlspecialchars($rowP['nip_sgt']) . ' - ' . htmlspecialchars($rowP['nama']) . '</option>';
                        }
                      ?>
                    </select>
                    <div class="input-group-append">
                      <button type="button" class="btn btn-default btn-flat" data-toggle="modal" data-target="#modalCariPelanggan" style="border: 1px solid #b8b8b8; background: #f8f9fa; font-size: 0.8rem;" title="Cari Data Pelanggan">
                        <i class="fas fa-search text-muted"></i> Cari
                      </button>
                      <button type="button" class="btn btn-success btn-flat" data-toggle="modal" data-target="#modalTambahPelangganCepat" style="font-size: 0.8rem; font-weight: 600; background: #0d9f4f; border-color: #076e34;" title="Tambah Pelanggan Baru">
                        <i class="fas fa-user-plus mr-1"></i> Baru
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-2">
                  <label for="nama_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">Nama Lengkap Pengirim</label>
                  <input type="text" class="form-control form-control-sm" name="nama_id" id="nama_id" readonly style="background: #f8f9fa; border: 1px solid #b8b8b8;">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-2">
                  <label for="no_ktp_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">No. KTP / NIK</label>
                  <input type="text" class="form-control form-control-sm" name="no_ktp_id" id="no_ktp_id" readonly style="background: #f8f9fa; border: 1px solid #b8b8b8;">
                </div>
              </div>
            </div>
            <div class="row mt-1">
              <div class="col-md-4">
                <div class="form-group mb-0">
                  <label for="no_hp_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">No. HP / WhatsApp Pengirim</label>
                  <input type="text" class="form-control form-control-sm" name="no_hp_id" id="no_hp_id" readonly style="background: #f8f9fa; border: 1px solid #b8b8b8;">
                </div>
              </div>
              <div class="col-md-8">
                <div class="form-group mb-0">
                  <label for="alamat_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">Alamat Asal Pengirim</label>
                  <input type="text" class="form-control form-control-sm" name="alamat_id" id="alamat_id" readonly style="background: #f8f9fa; border: 1px solid #b8b8b8;">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Seksi 2: Identitas Penerima & Rincian Paket -->
        <div class="card mb-3" style="border: 1px solid #d3d3d3; border-radius: 4px; background: #ffffff; box-shadow: none;">
          <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; padding: 8px 14px;">
            <strong style="font-size: 0.88rem; color: #333333;">
              <i class="fas fa-box-open text-warning mr-1"></i> 2. Identitas Penerima & Rincian Paket
            </strong>
          </div>
          <div class="card-body" style="padding: 14px;">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group mb-2">
                  <label for="nama_penerima" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Nama Penerima Paket <span class="text-danger">*</span>
                  </label>
                  <input type="text" class="form-control form-control-sm" name="nama_penerima" id="nama_penerima" placeholder="Nama lengkap penerima..." required style="border: 1px solid #b8b8b8;">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-2">
                  <label for="no_hp_penerima" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    No. HP / WhatsApp Penerima <span class="text-danger">*</span>
                  </label>
                  <input type="text" class="form-control form-control-sm" name="no_hp_penerima" id="no_hp_penerima" placeholder="Contoh: 08123456789" required style="border: 1px solid #b8b8b8;">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-2">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="jenis_barang" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 0;">
                      Jenis Barang / Paket <span class="text-danger">*</span>
                    </label>
                    <?php if (strtolower($role) === 'super admin'): ?>
                    <a href="../data_jenis_barang/data_jenis_barang_view.php" target="_blank" class="text-xs text-success" title="Buka Master Tarif Barang" style="font-size: 0.78rem;">
                      <i class="fas fa-external-link-alt mr-1"></i>Master Tarif
                    </a>
                    <?php endif; ?>
                  </div>
                  <select class="form-control form-control-sm select2-barang" name="jenis_barang" id="jenis_barang" onchange="autofillOngkosBarang()" required style="border: 1px solid #b8b8b8; width: 100%;">
                    <option value="">-- Pilih Jenis Barang / Paket --</option>
                    <?php
                      $queryJB = "SELECT id_barang, nama_barang, biaya_standar, keterangan FROM data_jenis_barang ORDER BY nama_barang ASC";
                      $resultJB = mysqli_query($conn, $queryJB);
                      while ($rowJB = mysqli_fetch_assoc($resultJB)) {
                        $biayaFmt = number_format($rowJB['biaya_standar'], 0, ',', '.');
                        echo '<option value="' . htmlspecialchars($rowJB['nama_barang']) . '" data-biaya="' . (float)$rowJB['biaya_standar'] . '" data-id="' . $rowJB['id_barang'] . '">';
                        echo htmlspecialchars($rowJB['nama_barang']) . ' (Tarif Acuan: Rp ' . $biayaFmt . ')';
                        echo '</option>';
                      }
                    ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="row mt-1">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <label for="keterangan" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Keterangan Khusus / Catatan Pengiriman
                  </label>
                  <textarea class="form-control form-control-sm" name="keterangan" id="keterangan" rows="2" placeholder="Catatan titipan paket, barang pecah belah, titik pengambilan tujuan, dsb..." style="border: 1px solid #b8b8b8;"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Seksi 3: Jadwal & Armada Ekspedisi -->
        <div class="card mb-3" style="border: 1px solid #d3d3d3; border-radius: 4px; background: #ffffff; box-shadow: none;">
          <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; padding: 8px 14px;">
            <strong style="font-size: 0.88rem; color: #333333;">
              <i class="fas fa-truck-moving text-info mr-1"></i> 3. Jadwal & Armada Ekspedisi
            </strong>
          </div>
          <div class="card-body" style="padding: 14px;">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label for="tanggal_pengiriman" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Tanggal Pengiriman <span class="text-danger">*</span>
                  </label>
                  <input type="datetime-local" class="form-control form-control-sm" name="tanggal_pengiriman" id="tanggal_pengiriman" required style="border: 1px solid #b8b8b8;">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label for="no_plat_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    No. Plat Armada <span class="text-danger">*</span>
                  </label>
                  <select class="form-control form-control-sm" name="no_plat_id" id="no_plat_id" onchange="autofillTravel()" required style="border: 1px solid #b8b8b8;">
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
                <div class="form-group mb-0">
                  <label for="kelas_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">Kelas Layanan</label>
                  <input type="text" class="form-control form-control-sm" name="kelas_id" id="kelas_id" readonly style="background: #f8f9fa; border: 1px solid #b8b8b8;">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label for="tujuan_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Kota / Rute Tujuan <span class="text-danger">*</span>
                  </label>
                  <select class="form-control form-control-sm" name="tujuan_id" id="tujuan_id" required style="border: 1px solid #b8b8b8;">
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
          </div>
        </div>

        <!-- Seksi 4: Biaya & Pembayaran Kasir -->
        <div class="card mb-0" style="border: 1px solid #d3d3d3; border-radius: 4px; background: #ffffff; box-shadow: none;">
          <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; padding: 8px 14px;">
            <strong style="font-size: 0.88rem; color: #333333;">
              <i class="fas fa-receipt text-primary mr-1"></i> 4. Biaya Pengiriman & Pembayaran Kasir
            </strong>
          </div>
          <div class="card-body" style="padding: 14px;">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label for="jumlah" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Ongkos / Biaya Kirim (Rp) <span class="text-danger">*</span> <small class="text-muted font-weight-normal"><i class="fas fa-lock ml-1"></i> Auto</small>
                  </label>
                  <input type="number" class="form-control form-control-sm font-weight-bold" name="jumlah" id="jumlah" min="0" placeholder="Rp 0" readonly required style="border: 1.5px solid #0d9f4f; color: #076e34; font-size: 0.95rem; background-color: #f4f6f9; cursor: not-allowed;">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label for="status_pembayaran_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Status Pembayaran <span class="text-danger">*</span>
                  </label>
                  <select class="form-control form-control-sm" name="status_pembayaran_id" id="status_pembayaran_id" required style="border: 1px solid #b8b8b8;">
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
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label for="metode_pembayaran_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Metode Pembayaran <span class="text-danger">*</span>
                  </label>
                  <select class="form-control form-control-sm" name="metode_pembayaran_id" id="metode_pembayaran_id" required style="border: 1px solid #b8b8b8;">
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
                <div class="form-group mb-0">
                  <label for="jenis_rekening_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Rekening Kasir / Bank <span class="text-danger">*</span>
                  </label>
                  <select class="form-control form-control-sm" name="jenis_rekening_id" id="jenis_rekening_id" required style="border: 1px solid #b8b8b8;">
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
          </div>
        </div>

      </div>
      <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 12px 20px;">
        <button type="button" class="btn btn-default btn-flat btn-sm" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600;">
          <i class="fas fa-times mr-1 text-muted"></i> Batal
        </button>
        <button type="submit" id="btnSave" name="tambah" class="btn btn-success btn-flat btn-sm" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600; padding: 6px 18px;">
          <i class="fas fa-save mr-1"></i> Simpan Pengiriman
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Pencarian Pelanggan -->
<div class="modal fade" id="modalCariPelanggan" tabindex="-1" role="dialog" aria-labelledby="modalCariPelangganLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none;">
      <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 12px 20px;">
        <h5 class="modal-title text-dark" id="modalCariPelangganLabel" style="font-size: 1.05rem; font-weight: 700;">
          <i class="fas fa-address-book mr-2 text-success"></i>Pilih Data Pelanggan Terdaftar
        </h5>
        <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style="padding: 16px;">
        <div class="table-responsive">
          <table id="tabelCariPelanggan" class="table table-bordered table-excel table-hover table-sm" style="width: 100%;">
            <thead>
              <tr>
                <th style="width: 110px;">NIP/SGT</th>
                <th>Nama Pelanggan</th>
                <th>Alamat Domisili</th>
                <th>No. KTP / NIK</th>
                <th>No. HP / WA</th>
                <th style="width: 70px; text-align: center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <!-- Data dimuat secara Server-Side AJAX via data_pelanggan_modal_fetch.php -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between" style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 10px 18px;">
        <button type="button" class="btn btn-outline-success btn-sm btn-flat font-weight-bold" data-dismiss="modal" data-toggle="modal" data-target="#modalTambahPelangganCepat">
          <i class="fas fa-user-plus mr-1"></i> Tambah Pelanggan Baru
        </button>
        <button type="button" class="btn btn-default btn-flat btn-sm" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600;">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tambah Pelanggan Cepat (Langsung Pilih di Form) -->
<div class="modal fade" id="modalTambahPelangganCepat" tabindex="-1" role="dialog" aria-labelledby="modalTambahPelangganCepatLabel" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 560px;">
    <div class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: 0 4px 18px rgba(0,0,0,0.15);">
      <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 12px 20px;">
        <h5 class="modal-title font-weight-bold text-dark" id="modalTambahPelangganCepatLabel" style="font-size: 1.05rem;">
          <i class="fas fa-user-plus text-success mr-2"></i>Tambah Pelanggan Baru
        </h5>
        <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="formTambahPelangganCepat" onsubmit="handleTambahPelangganCepat(event, 'nip_sgt_id')">
        <div class="modal-body" style="padding: 18px 20px; background: #fafafa;">
          <div class="form-group mb-2">
            <label class="font-weight-bold text-dark" style="font-size: 0.84rem;">
              Nama Lengkap Pengirim <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control form-control-sm" name="nama" id="quick_nama" placeholder="Contoh: Budi Santoso" required style="border: 1px solid #b8b8b8;">
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-2">
                <label class="font-weight-bold text-dark" style="font-size: 0.84rem;">
                  No. HP / WhatsApp <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control form-control-sm" name="no_hp" id="quick_no_hp" placeholder="Contoh: 08123456789" required style="border: 1px solid #b8b8b8;">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group mb-2">
                <label class="font-weight-bold text-dark" style="font-size: 0.84rem;">
                  No. KTP / NIK
                </label>
                <input type="text" class="form-control form-control-sm" name="no_ktp" id="quick_no_ktp" placeholder="16 digit NIK (Opsional)" style="border: 1px solid #b8b8b8;">
              </div>
            </div>
          </div>
          <div class="form-group mb-2">
            <label class="font-weight-bold text-dark" style="font-size: 0.84rem;">
              Alamat Domisili Pengirim
            </label>
            <textarea class="form-control form-control-sm" name="alamat" id="quick_alamat" rows="2" placeholder="Alamat lengkap RT/RW, Desa, Kecamatan..." style="border: 1px solid #b8b8b8;"></textarea>
          </div>
          <div class="alert alert-info py-1 px-2 mb-0" style="font-size: 0.78rem; background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc;">
            <i class="fas fa-info-circle mr-1"></i> NIP/SGT pelanggan akan digenerate otomatis oleh sistem dan langsung terpilih di formulir pengiriman.
          </div>
        </div>
        <div class="modal-footer" style="background: #ffffff; border-top: 1px solid #e0e0e0; padding: 10px 18px;">
          <button type="button" class="btn btn-default btn-sm btn-flat" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff;">
            Batal
          </button>
          <button type="submit" class="btn btn-success btn-sm btn-flat font-weight-bold px-3" id="btnSubmitQuickPelanggan" style="background: #0d9f4f; border-color: #076e34;">
            <i class="fas fa-check mr-1"></i> Simpan & Pilih Pelanggan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Kwitansi Paket -->
<div class="modal fade" id="modalKwitansiPaket" tabindex="-1" role="dialog" aria-labelledby="modalKwitansiPaketLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" role="document" style="max-width: 480px;">
    <div class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none;">
      <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 12px 18px;">
        <h5 class="modal-title text-dark" id="modalKwitansiPaketLabel" style="font-size: 1.05rem; font-weight: 700;">
          <i class="fas fa-box mr-2 text-success"></i>Kwitansi Pengiriman Paket
          <small class="d-block text-muted" style="font-size: 0.78rem; font-weight: 400; margin-top: 2px;">Format Standar Struk Printer Thermal 80mm</small>
        </h5>
        <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style="padding: 16px; background: #fdfdfd;">
        <div class="alert alert-light mb-3" style="background: #f8f9fa; border: 1px solid #dadce0; border-radius: 4px; font-size: 0.8rem; padding: 10px 12px;">
          <i class="fas fa-print text-primary mr-1"></i>
          <strong>Printer Thermal 80mm:</strong> Kwitansi ini dioptimalkan untuk kertas struk thermal lebar 80mm. 
          Pastikan printer sudah terhubung.
        </div>
        <div id="kwitansiPaketArea"
          style="background:#fff; color:#222; padding:18px; border: 1px solid #e0e0e0; border-radius:4px; max-width:320px; margin:auto; font-family:monospace; font-size:12px; line-height:1.2;">
          <div style="text-align:center; font-weight:bold; font-size:14px; margin-bottom:5px;">SINAR GALAXY TRAVEL</div>
          <div style="text-align:center; font-size:10px; margin-bottom:3px;">Jl. Mangga 1 No. 7 Sumbawa Besar</div>
          <div style="text-align:center; font-size:10px; margin-bottom:10px;">Telp: 0812-3456-7890</div>
          <div style="text-align:center; border-top:1px dashed #000; border-bottom:1px dashed #000; padding:5px 0; margin-bottom:10px;">
            <span style="font-weight:bold; font-size:12px;">KWITANSI PENGIRIMAN PAKET</span>
          </div>
          <div style="margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>No. Kwitansi</span>
              <span id="kwtp_no">: <?= date('Ymd') ?>-<?= sprintf('%04d', rand(1, 9999)) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Tanggal</span>
              <span id="kwtp_tanggal">: <?= date('d/m/Y H:i') ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Pengirim</span>
              <span id="kwtp_pengirim">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Alamat Pengirim</span>
              <span id="kwtp_alamat_pengirim">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>No. HP Pengirim</span>
              <span id="kwtp_nohp_pengirim">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Asal PO</span>
              <span id="kwtp_asalpo">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Keterangan</span>
              <span id="kwtp_keterangan">: -</span>
            </div>
          </div>
          <div style="border-top:1px dashed #000; padding:5px 0; margin-bottom:10px;">
            <div style="font-weight:bold; text-align:center; margin-bottom:5px;">DETAIL PENGIRIMAN</div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Jenis Barang</span>
              <span id="kwtp_jenis_barang">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Nama Penerima</span>
              <span id="kwtp_nama_penerima">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>No HP Penerima</span>
              <span id="kwtp_nohp_penerima">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Tujuan</span>
              <span id="kwtp_tujuan">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>No. Plat</span>
              <span id="kwtp_noplat">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Kelas</span>
              <span id="kwtp_kelas">: -</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Tanggal Kirim</span>
              <span id="kwtp_tgl_kirim">: -</span>
            </div>
          </div>
          <div style="border-top:1px dashed #000; padding:5px 0; margin-bottom:10px;">
            <div style="font-weight:bold; text-align:center; margin-bottom:5px;">PEMBAYARAN</div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Jumlah</span>
              <span id="kwtp_jumlah">: Rp 0</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Status</span>
              <span style="font-weight:bold; color:#076e34;">: LUNAS</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
              <span>Metode Bayar</span>
              <span id="kwtp_metode">: -</span>
            </div>
          </div>
          <div style="border-top:1px dashed #000; padding:5px 0; margin-bottom:10px;">
            <div style="text-align:center; font-size:10px; margin-bottom:4px;">TERIMA KASIH</div>
            <div style="text-align:center; font-size:10px; margin-bottom:4px;">PAKET AKAN SEGERA DIKIRIM</div>
            <div style="text-align:center; font-size:10px;">Semoga paket sampai dengan aman dan selamat</div>
          </div>
          <div style="text-align:center; border-top:1px dashed #000; padding:5px 0; font-size:10px;">
            www.sinargalaxy.my.id<br>
            <span style="font-size:9px;">Bukti Pembayaran Resmi Sinar Galaxy</span><br>
            <span style="font-size:9px;">Petugas Kasir: <?= isset($user['username']) ? htmlspecialchars($user['username']) : 'Admin' ?></span>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 10px 18px;">
        <button id="btnPrintKwitansiPaket" class="btn btn-success btn-flat btn-sm" style="background: #0d9f4f; border-color: #076e34; font-weight: 600;">
          <i class="fas fa-print mr-1"></i> Print Thermal
        </button>
        <button id="btnDownloadKwitansiPaketPDF" class="btn btn-danger btn-flat btn-sm" style="background: #c62828; border-color: #b71c1c; font-weight: 600;">
          <i class="fas fa-file-pdf mr-1"></i> Unduh PDF
        </button>
        <button type="button" class="btn btn-default btn-flat btn-sm" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600;">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>
<!-- End Modal Kwitansi Paket -->

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
    .no-print { display: none !important; }
    .modal { position: static !important; }
    .modal-dialog { max-width: none !important; }
    .modal-content { border: none !important; box-shadow: none !important; }
    .modal-header, .modal-footer { display: none !important; }
    .modal-body { padding: 0 !important; }
    .alert { display: none !important; }
  }
  
  /* Style khusus untuk kwitansi paket */
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

<?php include '../inc/footer.php'; ?>

<!-- Library html2pdf dan html2canvas untuk cetak & unduh kwitansi -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

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
      .catch(error => console.error('Error:', error));
  } else {
    // Jangan reset field jika sedang dalam mode edit pengiriman
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
    fetch('get_travel.php?no_plat=' + noPlat)
        .then(response => response.json())
        .then(data => {
        if (data.success) {
          document.getElementById('kelas_id').value = data.kelas;
        }
      })
      .catch(error => console.error('Error:', error));
  } else {
    document.getElementById('kelas_id').value = '';
  }
}

// Map tarif master data jenis barang untuk referensi instan
var tarifBarangMap = {
  <?php
    $resMap = mysqli_query($conn, "SELECT nama_barang, biaya_standar FROM data_jenis_barang");
    if ($resMap) {
      while ($rm = mysqli_fetch_assoc($resMap)) {
        echo json_encode($rm['nama_barang']) . ': ' . (float)$rm['biaya_standar'] . ",\n";
      }
    }
  ?>
};

// Autofill Seksi 4: Biaya Pengiriman & Pembayaran Kasir berdasarkan Jenis Barang / Paket
function autofillOngkosBarang(targetVal) {
  var $sel = $('#jenis_barang');
  var val = targetVal || ($sel.length ? $sel.val() : '');
  if (!val && document.getElementById('jenis_barang')) {
    val = document.getElementById('jenis_barang').value;
  }

  var biaya = null;

  // 1. Ambil dari option yang terpilih di DOM jika ada data-biaya
  var $opt = $sel.find('option:selected');
  if ($opt.length && $opt.val()) {
    var rawBiaya = $opt.attr('data-biaya') || $opt.data('biaya');
    if (rawBiaya !== undefined && rawBiaya !== null && rawBiaya !== '') {
      biaya = parseFloat(rawBiaya);
    }
  }

  // 2. Jika belum ketemu, cari di tarifBarangMap berdasarkan nama_barang
  if ((biaya === null || isNaN(biaya) || biaya <= 0) && val && typeof tarifBarangMap !== 'undefined' && tarifBarangMap[val] !== undefined) {
    biaya = parseFloat(tarifBarangMap[val]);
  }

  // 3. Fallback pencarian case-insensitive di tarifBarangMap
  if ((biaya === null || isNaN(biaya) || biaya <= 0) && val && typeof tarifBarangMap !== 'undefined') {
    var searchStr = val.toString().trim().toLowerCase();
    for (var k in tarifBarangMap) {
      if (k.toString().trim().toLowerCase() === searchStr) {
        biaya = parseFloat(tarifBarangMap[k]);
        break;
      }
    }
  }

  // 4. Masukkan nominal ke input Ongkos / Biaya Kirim (#jumlah)
  var inputJumlah = document.getElementById('jumlah');
  if (inputJumlah && biaya !== null && !isNaN(biaya) && biaya > 0) {
    inputJumlah.value = biaya;
    $(inputJumlah).trigger('input').trigger('change');
  }

  // 5. Autofill Status Pembayaran (Default ke LUNAS / id: 1 jika belum dipilih)
  var $status = $('#status_pembayaran_id');
  if ($status.length && (!$status.val() || $status.val() === '')) {
    $status.val('1').trigger('change');
  }

  // 6. Autofill Metode Pembayaran (Default ke TUNAI KASIR / id: 2 jika belum dipilih)
  var $metode = $('#metode_pembayaran_id');
  if ($metode.length && (!$metode.val() || $metode.val() === '')) {
    $metode.val('2').trigger('change');
  }

  // 7. Autofill Rekening Kasir (Default ke rekening kasir cabang asal PO jika belum dipilih)
  var $rek = $('#jenis_rekening_id');
  if ($rek.length && (!$rek.val() || $rek.val() === '')) {
    <?php
      $defRek = 6; // Default CASH (KASIR) PO SUMBAWA
      if (stripos($asal_po, 'mataram') !== false) {
        $defRek = 4; // CASH (KASIR) PO MATARAM
      }
    ?>
    var defRekVal = '<?= $defRek ?>';
    if (defRekVal && $rek.find('option[value="' + defRekVal + '"]').length) {
      $rek.val(defRekVal).trigger('change');
    } else {
      var $cashOpt = $rek.find('option').filter(function() {
        return $(this).text().toUpperCase().indexOf('CASH') !== -1;
      }).first();
      if ($cashOpt.length) {
        $rek.val($cashOpt.val()).trigger('change');
      }
    }
  }
}

// Inisialisasi DataTables Server-Side untuk modal cari pelanggan (Cepat & Ringan)
var tabelCariPelangganInit = false;
var tabelCariPelangganObj = null;

$('#modalCariPelanggan').on('shown.bs.modal', function () {
  if (!tabelCariPelangganInit) {
    tabelCariPelangganObj = $('#tabelCariPelanggan').DataTable({
      processing: true,
      serverSide: true,
      ajax: '../data_pelanggan/data_pelanggan_modal_fetch.php',
      pageLength: 8,
      lengthChange: false,
      autoWidth: false,
      ordering: true,
      order: [[0, 'desc']],
      language: {
        search: 'Cari Pelanggan:',
        searchPlaceholder: 'Ketik nama / no hp / nip...',
        processing: '<i class="fas fa-spinner fa-spin mr-1"></i> Memuat data pelanggan...',
        zeroRecords: 'Pelanggan tidak ditemukan. Silakan klik tombol Tambah Pelanggan Baru.',
        info: 'Menampilkan _START_ - _END_ dari _TOTAL_ pelanggan',
        infoEmpty: 'Tidak ada data',
        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
      }
    });
    tabelCariPelangganInit = true;
  } else {
    tabelCariPelangganObj.columns.adjust().draw();
  }
});

// Setup Select2 khusus di dalam Modal Form agar tidak tertimpa/terganggu
function initModalPengirimanSelect2() {
  if (typeof $.fn.select2 === 'undefined') return;

  var $modal = $('#modalForm');

  // 1. Select2 Pelanggan
  var $selPelanggan = $('#nip_sgt_id');
  if ($selPelanggan.length) {
    if (!$selPelanggan.hasClass('select2-hidden-accessible')) {
      $selPelanggan.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $modal,
        placeholder: '-- Pilih Pelanggan --',
        allowClear: true
      });
    }
  }

  // 2. Select2 Jenis Barang
  var $selBarang = $('#jenis_barang');
  if ($selBarang.length) {
    if (!$selBarang.hasClass('select2-hidden-accessible')) {
      $selBarang.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $modal,
        placeholder: '-- Pilih / Cari Jenis Barang / Paket --',
        allowClear: true
      });
    }
  }
}

$(document).ready(function() {
  initModalPengirimanSelect2();

  // Delegasi event change & select2:select untuk memicu autofill ongkos
  $(document).on('change select2:select', '#jenis_barang', function(e) {
    var val = $(this).val();
    if (e && e.params && e.params.data && e.params.data.id) {
      val = e.params.data.id;
    }
    autofillOngkosBarang(val);
  });
});

$('#modalForm').on('shown.bs.modal', function() {
  initModalPengirimanSelect2();
  var curNip = $('#nip_sgt_id').val();
  if (curNip) {
    $('#nip_sgt_id').trigger('change.select2');
  }
});

// Pilih pelanggan dari modal pencarian
$(document).on('click', '.pilih-pelanggan-modal', function() {
  var nip = $(this).data('nip');
  var nama = $(this).data('nama');
  var alamat = $(this).data('alamat');
  var ktp = $(this).data('ktp');
  var hp = $(this).data('hp');

  // Jika belum ada di select2, tambahkan option baru
  if ($('#nip_sgt_id').find("option[value='" + nip + "']").length === 0) {
    var newOption = new Option(nip + (nama ? ' - ' + nama : ''), nip, true, true);
    $('#nip_sgt_id').append(newOption).trigger('change');
  } else {
    $('#nip_sgt_id').val(nip).trigger('change');
  }

  // Isi data langsung
  if (nama) document.getElementById('nama_id').value = nama;
  if (alamat) document.getElementById('alamat_id').value = alamat;
  if (ktp) document.getElementById('no_ktp_id').value = ktp;
  if (hp) document.getElementById('no_hp_id').value = hp;

  $('#modalCariPelanggan').modal('hide');
});

// Handler Simpan Pelanggan Cepat via AJAX
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

  // Kwitansi Paket
  $(document).on('click', '.btn-kwitansi-paket', function () {
    // Ambil data dari tombol
    const idPengiriman = $(this).data('id-pengiriman');
    const nama = $(this).data('nama');
    const alamat = $(this).data('alamat');
    const nohp = $(this).data('nohp');
    const asalPO = $(this).data('asalpo');
    const keterangan = $(this).data('keterangan');
    const jenisBarang = $(this).data('jenis-barang');
    const namaPenerima = $(this).data('nama-penerima');
    const nohpPenerima = $(this).data('nohp-penerima');
    const tgl = $(this).data('tgl');
    const noplat = $(this).data('noplat');
    const kelas = $(this).data('kelas');
    const tujuan = $(this).data('tujuan');
    const status = $(this).data('status');
    const metode = $(this).data('metode');
    const jumlah = $(this).data('jumlah');

    // Format tanggal pengiriman
    let tglObj = new Date(tgl);
    let hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    let bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    let hariTeks = hari[tglObj.getDay()];
    let tglTeks = tglObj.getDate() + ' ' + bulan[tglObj.getMonth()] + ' ' + tglObj.getFullYear();
    let jamTeks = (tglObj.getHours() < 10 ? '0' : '') + tglObj.getHours() + ':' + (tglObj.getMinutes() < 10 ? '0' : '') + tglObj.getMinutes();

    // Isi modal
    $('#kwtp_no').text(': SGT-PKG-' + idPengiriman.toString().padStart(6, '0'));
    $('#kwtp_tanggal').text(': ' + new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'}));
    $('#kwtp_pengirim').text(': ' + nama);
    $('#kwtp_alamat_pengirim').text(': ' + alamat);
    $('#kwtp_nohp_pengirim').text(': ' + nohp);
    $('#kwtp_asalpo').text(': ' + asalPO);
    $('#kwtp_keterangan').text(': ' + (keterangan || '-'));
    $('#kwtp_jenis_barang').text(': ' + jenisBarang);
    $('#kwtp_nama_penerima').text(': ' + namaPenerima);
    $('#kwtp_nohp_penerima').text(': ' + nohpPenerima);
    $('#kwtp_tujuan').text(': ' + tujuan);
    $('#kwtp_noplat').text(': ' + noplat);
    $('#kwtp_kelas').text(': ' + kelas);
    $('#kwtp_tgl_kirim').text(': ' + hariTeks + ', ' + tglTeks + ' ' + jamTeks + ' Wita');
    $('#kwtp_jumlah').text(': Rp ' + parseInt(jumlah).toLocaleString('id-ID'));
    $('#kwtp_metode').text(': ' + (metode || 'Cash/Transfer'));

    // Update status berdasarkan data sebenarnya
    const statusElement = $('#kwitansiPaketArea').find('span[style*="color:#28a745"]');
    if (status && status.toLowerCase().includes('lunas')) {
      statusElement.text(': LUNAS').css('color', '#28a745');
    } else {
      statusElement.text(': ' + (status || 'PENDING')).css('color', '#ffc107');
      // Tampilkan pesan peringatan
      alert('Perhatian: Status pembayaran bukan "Lunas". Kwitansi paket sebaiknya hanya dicetak untuk pembayaran yang sudah lunas.');
    }

    $('#modalKwitansiPaket').modal('show');
  });

  // Print Thermal Kwitansi Paket
  $('#btnPrintKwitansiPaket').on('click', function (e) {
    e.preventDefault();
    
    Swal.fire({
      title: 'Cetak Kwitansi?',
      text: "Apakah Anda yakin ingin mencetak kwitansi paket ini dengan printer thermal 80mm?",
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#aaa',
      confirmButtonText: 'Ya, Cetak!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        // Buat window baru untuk print thermal
        const printWindow = window.open('', '_blank', 'width=320,height=600');
        const printContent = document.getElementById('kwitansiPaketArea').innerHTML;
        
        printWindow.document.write(`
          <!DOCTYPE html>
          <html>
          <head>
            <title>Kwitansi Paket - SGT</title>
            <style>
              body {
                font-family: monospace;
                font-size: 12px;
                line-height: 1.2;
                margin: 0;
                padding: 10px;
                width: 80mm;
                max-width: 80mm;
                background: white;
                color: black;
              }
              .thermal-receipt {
                width: 100%;
              }
              .print-info {
                text-align: center;
                font-size: 10px;
                color: #666;
                margin-top: 10px;
                border-top: 1px dashed #ccc;
                padding-top: 5px;
              }
              @media print {
                @page { margin: 0; size: 80mm auto; }
                .no-print { display: none !important; }
              }
            </style>
          </head>
          <body>
            <div class="thermal-receipt">
              ${printContent}
            </div>
            <div class="print-info">
              <strong>Printer Thermal 80mm - Sinar Galaxy Travel</strong><br>
              Kwitansi paket ini dioptimalkan untuk printer thermal
            </div>
            <div class="no-print" style="text-align:center; margin-top:20px;">
              <button onclick="window.print()" style="padding: 10px 20px; margin: 5px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Thermal</button>
              <button onclick="window.close()" style="padding: 10px 20px; margin: 5px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Tutup</button>
            </div>
            <script>
              window.onload = function() {
                // window.print(); // Uncomment if auto-print is desired
              };
            <\/script>
          </body>
          </html>
        `);
        printWindow.document.close();
      }
    });
  });


  // Download PDF Kwitansi Paket
  $('#btnDownloadKwitansiPaketPDF').on('click', function (e) {
    e.preventDefault();
    const element = document.getElementById('kwitansiPaketArea');
    const opt = {
      margin: 0.1,
      filename: 'kwitansi_paket_sgt.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
      jsPDF: { unit: 'mm', format: [80, 'auto'], orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
  });
</script>
