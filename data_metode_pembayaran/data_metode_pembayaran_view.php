<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Ambil data user dari session
$user = $_SESSION['user'] ?? [];
$asal_po = $user['asal_po'] ?? 'Sumbawa';
$role = $user['role'] ?? 'admin';

// Handle CRUD (PRG pattern — PHP header redirect)
if (isset($_POST['tambah'])) {
    tambahMetodePembayaran($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_POST['edit'])) {
    updateMetodePembayaran($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['hapus'])) {
    deleteMetodePembayaran($_GET['hapus']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ambil semua data metode pembayaran
$data = mysqli_query($conn, "SELECT * FROM data_metode_pembayaran ORDER BY id_metode_pembayaran ASC");
$total_metode = $data ? mysqli_num_rows($data) : 0;

// Sekarang baru HTML output
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';
?>
<div class="content-wrapper" style="background-color: #fcfcfc;">
  <!-- Content Header -->
  <div class="content-header" style="padding: 14px 18px 8px; background: #ffffff; border-bottom: 1px solid #e0e0e0; margin-bottom: 15px;">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-7">
          <div class="d-flex align-items-center">
            <div style="width: 40px; height: 40px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.2rem;">
              <i class="fas fa-credit-card"></i>
            </div>
            <div>
              <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Metode Pembayaran</h1>
              <div class="text-muted" style="font-size: 0.83rem;">
                <span>Master opsi transaksi pembayaran kasir loket (Cash / Transfer Bank)</span>
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
            <i class="fas fa-plus mr-1"></i> Tambah Metode
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content" style="padding: 0 15px;">
    <!-- 3 KPI Metrics Card (Paper White v2.0) -->
    <div class="row mb-3">
      <div class="col-lg-4 col-12 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Opsi Bayar</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #202124; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($total_metode) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Metode</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; color: #0d9f4f;">
                <i class="fas fa-wallet" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-check-circle text-success mr-1"></i> Digunakan pada tiket & kargo
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Kanal Pembayaran</div>
                <div class="font-weight-bold" style="font-size: 1.25rem; color: #0284c7; line-height: 1.2; margin-top: 4px;">
                  Tunai & Transfer
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f0f9ff; border: 1px solid #bae6fd; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                <i class="fas fa-money-bill-transfer" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-shield-alt mr-1"></i> Pembukuan kas masuk
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Status Hak Akses</div>
                <div class="font-weight-bold" style="font-size: 1.25rem; color: #1e293b; line-height: 1.2; margin-top: 4px;">
                  <?= htmlspecialchars(ucwords($role)) ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b;">
                <i class="fas fa-user-lock" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-database mr-1"></i> Sinkron ke rekening kasir
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Card -->
    <div class="card mb-4" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
      <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #b8b8b8; padding: 12px 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center">
            <i class="fas fa-table mr-2 text-muted"></i>
            <span class="font-weight-bold text-dark" style="font-size: 1rem;">Daftar Metode Pembayaran</span>
          </div>
          <button class="btn btn-primary btn-sm btn-flat" data-toggle="modal" data-target="#modalForm" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600; border-radius: 4px;">
            <i class="fas fa-plus mr-1"></i> Tambah Data
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table id="tableMetode" class="table table-excel table-hover table-bordered table-sm mb-0" style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #ffffff;">
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; text-align: center; width: 80px; font-weight: 600; color: #202124;">ID</th>
                <th style="padding: 10px 12px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">Metode Pembayaran</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; text-align: center; width: 120px; font-weight: 600; color: #202124;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = mysqli_fetch_assoc($data)): ?>
              <tr>
                <td style="padding: 8px; border: 1px solid #b8b8b8; text-align: center; font-weight: 600; color: #5f6368;"><?= $r['id_metode_pembayaran']; ?></td>
                <td style="padding: 8px 12px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">
                  <span class="badge" style="background: #f1f3f4; color: #202124; border: 1px solid #dadce0; font-size: 0.88rem; padding: 5px 10px;">
                    <i class="fas fa-money-check mr-1 text-primary"></i><?= htmlspecialchars($r['metode_pembayaran']); ?>
                  </span>
                </td>
                <td style="padding: 6px 8px; border: 1px solid #b8b8b8; text-align: center;">
                  <button class="btn btn-default btn-xs btn-flat btn-edit mr-1" data-id="<?= $r['id_metode_pembayaran']; ?>" title="Edit Metode" style="border: 1px solid #b8b8b8; background: #ffffff; border-radius: 3px; padding: 3px 8px;">
                    <i class="fas fa-pencil-alt text-primary"></i>
                  </button>
                  <button class="btn btn-default btn-xs btn-flat btn-delete" data-id="<?= $r['id_metode_pembayaran']; ?>" title="Hapus Metode" style="border: 1px solid #fecaca; background: #fff5f5; border-radius: 3px; padding: 3px 8px;">
                    <i class="fas fa-trash-alt text-danger"></i>
                  </button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit Metode Pembayaran -->
    <div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
      <div class="modal-dialog role="document">
        <form id="formMetode" method="POST" class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
          <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 14px 20px;">
            <div class="d-flex align-items-center">
              <div style="width: 32px; height: 32px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 10px; color: #0d9f4f;">
                <i class="fas fa-credit-card"></i>
              </div>
              <h5 class="modal-title font-weight-bold text-dark" id="modalFormLabel" style="font-size: 1.15rem; margin: 0;">Tambah Metode Pembayaran</h5>
            </div>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; color: #5f6368;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" style="padding: 20px; background: #ffffff;">
            <input type="hidden" name="id_metode_pembayaran" id="id_metode_pembayaran">
            <div class="form-group mb-0">
              <label for="metode_pembayaran" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                Nama Metode Pembayaran <span class="text-danger">*</span>
              </label>
              <input type="text" class="form-control form-control-sm" name="metode_pembayaran" id="metode_pembayaran" placeholder="Contoh: Cash, Transfer Bank, QRIS" required style="border: 1px solid #b8b8b8; border-radius: 4px;">
            </div>
          </div>
          <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e0e0e0; padding: 12px 20px;">
            <button type="button" class="btn btn-default btn-sm btn-flat font-weight-bold" data-dismiss="modal" style="border: 1px solid #b8b8b8; background: #ffffff; color: #4b5563; border-radius: 4px; padding: 6px 16px;">
              <i class="fas fa-times mr-1"></i> Batal
            </button>
            <button type="submit" id="btnSave" name="tambah" class="btn btn-success btn-sm btn-flat font-weight-bold" style="background-color: #0d9f4f; border-color: #076e34; border-radius: 4px; padding: 6px 18px;">
              <i class="fas fa-save mr-1"></i> Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <?php include '../inc/footer.php'; ?>