<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Ambil data user dari session (SEBELUM HTML output)
$user = $_SESSION['user'] ?? [];
$id_users = $user['id_users'] ?? '';
$asal_po = $user['asal_po'] ?? '';
$role = $user['role'] ?? '';

// Handle CRUD (PRG pattern — HARUS sebelum link_header.php agar header() bisa bekerja)
if (isset($_POST['tambah'])) {
  $_POST['username'] = $id_users;
  $_POST['asal_po'] = $asal_po;
  tambahPelanggan($_POST);
  header('Location: ' . $_SERVER['PHP_SELF']);
  exit;
}
if (isset($_POST['edit'])) {
  $_POST['username'] = $id_users;
  $_POST['asal_po'] = $asal_po;
  updatePelanggan($_POST);
  header('Location: ' . $_SERVER['PHP_SELF']);
  exit;
}
if (isset($_GET['hapus'])) {
  deletePelanggan($_GET['hapus']);
  header('Location: ' . $_SERVER['PHP_SELF']);
  exit;
}

// Query data pelanggan
if ($role === 'super admin') {
  $sql = "SELECT p.*, u.username AS user_input, u.asal_po AS asal_po_user 
            FROM data_pelanggan p 
            JOIN data_users u ON p.username = u.id_users";
} else {
  $sql = "SELECT p.*, u.username AS user_input, u.asal_po AS asal_po_user 
            FROM data_pelanggan p 
            JOIN data_users u ON p.username = u.id_users 
            WHERE p.username = '" . $user['id_users'] . "' AND p.asal_po = '" . $user['asal_po'] . "'";
}
$result = $conn->query($sql);

// Hitung statistik pelanggan
$total_pelanggan = $result ? mysqli_num_rows($result) : 0;
$pelanggan_hari_ini = 0;
$pelanggan_bulan_ini = 0;

if ($result) {
  $today = date('Y-m-d');
  $month_start = date('Y-m-01');

  mysqli_data_seek($result, 0);
  while ($row = mysqli_fetch_assoc($result)) {
    if (isset($row['created_at'])) {
      $created_date = date('Y-m-d', strtotime($row['created_at']));
      if ($created_date == $today)
        $pelanggan_hari_ini++;
      if ($created_date >= $month_start)
        $pelanggan_bulan_ini++;
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
            <div style="width: 40px; height: 40px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.2rem;">
              <i class="fas fa-users"></i>
            </div>
            <div>
              <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Database Pelanggan</h1>
              <div class="text-muted" style="font-size: 0.83rem;">
                <span>Data identitas penumpang, nomor kontak WhatsApp, dan kartu loyalitas SGT</span>
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
        <div class="col-sm-5 text-right">
          <button type="button" class="btn btn-default btn-sm btn-flat" onclick="location.reload();" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600; margin-right: 6px;">
            <i class="fas fa-sync-alt mr-1 text-muted"></i> Refresh Data
          </button>
          <button type="button" class="btn btn-success btn-sm btn-flat" data-toggle="modal" data-target="#modalForm" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600; padding: 6px 14px;">
            <i class="fas fa-plus mr-1"></i> Tambah Pelanggan
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
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Pelanggan</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #202124; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($total_pelanggan) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Orang</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f8f9fa; border: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: center; color: #0d9f4f;">
                <i class="fas fa-user-friends" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-database mr-1"></i> Database kontak aktif
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Pelanggan Baru (Bulan Ini)</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #0284c7; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($pelanggan_bulan_ini) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Orang</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f0f9ff; border: 1px solid #bae6fd; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                <i class="fas fa-user-plus" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-calendar-alt mr-1"></i> Periode <?= date('F Y') ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Asal Cabang PO</div>
                <div class="font-weight-bold" style="font-size: 1.25rem; color: #1e293b; line-height: 1.2; margin-top: 4px;">
                  <?= $role === 'super admin' ? 'Semua Cabang' : htmlspecialchars($asal_po) ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b;">
                <i class="fas fa-building" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-map-marker-alt mr-1"></i> Lokasi operasional loket
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Kartu Pelanggan</div>
                <div class="font-weight-bold" style="font-size: 1.1rem; color: #0d9f4f; line-height: 1.2; margin-top: 6px;">
                  <i class="fas fa-id-card mr-1"></i> KP-SGT Digital
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; color: #0d9f4f;">
                <i class="fas fa-qrcode" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-download mr-1"></i> Siap cetak & download
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Card -->
    <div class="card mb-4" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
      <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #b8b8b8; padding: 12px 16px;">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div class="d-flex align-items-center">
            <i class="fas fa-table mr-2 text-muted"></i>
            <span class="font-weight-bold text-dark" style="font-size: 1rem;">Daftar Database Pelanggan Terdaftar</span>
          </div>
          <div class="d-flex align-items-center">
            <input type="text" id="searchIdInput" class="form-control form-control-sm text-center mr-2" 
                   placeholder="Cari ID Pelanggan..." style="width: 170px; border: 1px solid #b8b8b8; border-radius: 4px; font-weight: normal;">
            <button class="btn btn-primary btn-sm btn-flat" data-toggle="modal" data-target="#modalForm" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600; border-radius: 4px;">
              <i class="fas fa-plus mr-1"></i> Tambah Pelanggan
            </button>
          </div>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table id="tablePelanggan" class="table table-excel table-hover table-bordered table-sm mb-0" data-server="1" style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #ffffff;">
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; text-align: center; width: 60px; font-weight: 600; color: #202124;">ID</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">Nama Pelanggan</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">Alamat Domisili</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">No. KTP / NIK</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">No. HP / WA</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">NIP SGT</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">User Input</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">Asal PO</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; text-align: center; width: 110px; font-weight: 600; color: #202124;">Aksi</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit Pelanggan -->
    <div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <form id="formPelanggan" method="POST" class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
          <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 14px 20px;">
            <div class="d-flex align-items-center">
              <div style="width: 32px; height: 32px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 10px; color: #0d9f4f;">
                <i class="fas fa-user-plus"></i>
              </div>
              <h5 class="modal-title font-weight-bold text-dark" id="modalFormLabel" style="font-size: 1.15rem; margin: 0;">Tambah Pelanggan</h5>
            </div>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; color: #5f6368;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" style="padding: 20px; background: #ffffff;">
            <input type="hidden" name="id_pelanggan" id="id_pelanggan">
            
            <!-- Seksi 1: Identitas Pokok Pelanggan -->
            <div class="mb-3 p-3" style="background: #fafafa; border: 1px solid #e2e8f0; border-radius: 4px;">
              <div class="font-weight-bold text-dark mb-2 pb-1 border-bottom" style="font-size: 0.9rem;">
                <i class="fas fa-id-card-clip mr-1 text-primary"></i> 1. Identitas Pokok Pelanggan
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-2">
                    <label for="nama" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                      Nama Lengkap <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control form-control-sm" name="nama" id="nama" placeholder="Contoh: Budi Santoso" required style="border: 1px solid #b8b8b8; border-radius: 4px;">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-2">
                    <label for="no_ktp" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                      No. KTP / NIK <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control form-control-sm" name="no_ktp" id="no_ktp" placeholder="16 digit NIK KTP" required style="border: 1px solid #b8b8b8; border-radius: 4px;">
                  </div>
                </div>
              </div>
              <div class="row mt-1">
                <div class="col-md-6">
                  <div class="form-group mb-0">
                    <label for="nip_sgt" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                      NIP SGT (Nomor Loyalitas)
                    </label>
                    <input type="text" class="form-control form-control-sm bg-light" name="nip_sgt" id="nip_sgt" readonly
                      value="SGT-5204<?= rand(10000000, 99999999) ?>" style="border: 1px solid #ced4da; font-weight: 600; color: #0d9f4f; border-radius: 4px;">
                    <small class="text-muted" style="font-size: 0.75rem;">Digenerate otomatis oleh sistem SGT</small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-0">
                    <label for="no_hp" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                      No. HP / WhatsApp <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control form-control-sm" name="no_hp" id="no_hp" placeholder="Contoh: 08123456789" required style="border: 1px solid #b8b8b8; border-radius: 4px;">
                  </div>
                </div>
              </div>
            </div>

            <!-- Seksi 2: Alamat Domisili -->
            <div class="p-3" style="background: #fafafa; border: 1px solid #e2e8f0; border-radius: 4px;">
              <div class="font-weight-bold text-dark mb-2 pb-1 border-bottom" style="font-size: 0.9rem;">
                <i class="fas fa-map-location-dot mr-1 text-primary"></i> 2. Alamat & Domisili Penumpang
              </div>
              <div class="form-group mb-0">
                <label for="alamat" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                  Alamat Lengkap <span class="text-danger">*</span>
                </label>
                <textarea class="form-control form-control-sm" name="alamat" id="alamat" rows="3" placeholder="Masukkan alamat lengkap RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten" required style="border: 1px solid #b8b8b8; border-radius: 4px;"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e0e0e0; padding: 12px 20px;">
            <button type="button" class="btn btn-default btn-sm btn-flat font-weight-bold" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff; color: #4b5563; border-radius: 4px; padding: 6px 16px;">
              <i class="fas fa-times mr-1"></i> Batal
            </button>
            <button type="submit" id="btnSave" name="tambah" class="btn btn-success btn-sm btn-flat font-weight-bold" style="background-color: #0d9f4f; border-color: #076e34; border-radius: 4px; padding: 6px 18px;">
              <i class="fas fa-save mr-1"></i> Simpan Data
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Include Kartu Anggota -->
    <?php include '../data_pelanggan/kartu_anggota_sgt.php'; ?>

    <?php include '../inc/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
      // Handle tombol kartu pelanggan
      $(document).on('click', '.btn-kartu', function () {
        $('#kp_nama').text($(this).data('nama'));
        $('#kp_alamat').text($(this).data('alamat'));
        $('#kp_no_ktp').text($(this).data('no_ktp'));
        $('#kp_no_hp').text($(this).data('no_hp'));
        $('#kp_nip_sgt').text($(this).data('nip_sgt'));
        $('#kartuPelangganDepan').show();
        $('#kartuPelangganBelakang').hide();
        $('#modalKartu').modal('show');
      });

      $('#btnFlipKartu').on('click', function () {
        $('.kartu-pelanggan-sgt').toggle();
      });

      $('#btnDownloadKartu').on('click', function () {
        var $kartu = $('#kartuPelangganDepan').is(':visible') ? $('#kartuPelangganDepan') : $('#kartuPelangganBelakang');
        html2canvas($kartu[0], { backgroundColor: null }).then(function (canvas) {
          var link = document.createElement('a');
          link.download = 'kartu-pelanggan-sgt.jpg';
          link.href = canvas.toDataURL('image/jpeg', 0.95);
          link.click();
        });
      });

      // Simple page ready
      $(document).ready(function () {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
      });
    </script>