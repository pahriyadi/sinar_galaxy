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
    tambahJamKeberangkatan($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_POST['edit'])) {
    updateJamKeberangkatan($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['hapus'])) {
    deleteJamKeberangkatan($_GET['hapus']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ambil semua data jam keberangkatan
$data = mysqli_query($conn, "SELECT * FROM ref_jam_keberangkatan ORDER BY jam ASC");
$total_jam = $data ? mysqli_num_rows($data) : 0;

// Hitung statistik jadwal paling pagi dan paling malam
$min_jam = '-';
$max_jam = '-';
$qMinMax = mysqli_query($conn, "SELECT MIN(jam) as min_j, MAX(jam) as max_j FROM ref_jam_keberangkatan WHERE is_active = 1");
if ($rMM = mysqli_fetch_assoc($qMinMax)) {
    if (!empty($rMM['min_j'])) $min_jam = substr($rMM['min_j'], 0, 5) . ' WITA';
    if (!empty($rMM['max_j'])) $max_jam = substr($rMM['max_j'], 0, 5) . ' WITA';
}

// Ambil jumlah tiket per jam
$jamUsage = [];
$qUsage = mysqli_query($conn, "SELECT jam_keberangkatan, COUNT(*) as jml FROM data_pemesanan WHERE jam_keberangkatan IS NOT NULL GROUP BY jam_keberangkatan");
if ($qUsage) {
    while ($ru = mysqli_fetch_assoc($qUsage)) {
        $jamUsage[$ru['jam_keberangkatan']] = (int)$ru['jml'];
    }
}

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
            <div style="width: 40px; height: 40px; border-radius: 4px; background: #e8f0fe; border: 1px solid #c2e7ff; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #1a73e8; font-size: 1.25rem;">
              <i class="far fa-clock"></i>
            </div>
            <div>
              <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Jadwal Jam Keberangkatan Armada</h1>
              <div class="text-muted" style="font-size: 0.83rem;">
                <span>Master referensi data jam keberangkatan reguler armada & pemesanan tiket</span>
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
            <i class="fas fa-plus mr-1"></i> Tambah Jadwal Jam
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
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Jadwal Tersedia</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #202124; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($total_jam) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Waktu Keberangkatan</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #e8f0fe; border: 1px solid #c2e7ff; display: flex; align-items: center; justify-content: center; color: #1a73e8;">
                <i class="fas fa-business-time" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-check-circle text-success mr-1"></i> Pilihan dinamis pada form pemesanan tiket
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Trip Paling Awal</div>
                <div class="font-weight-bold" style="font-size: 1.25rem; color: #0284c7; line-height: 1.2; margin-top: 4px;">
                  <?= $min_jam ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f0f9ff; border: 1px solid #bae6fd; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                <i class="fas fa-sun" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-shuttle-van mr-1"></i> Keberangkatan pagi hari pertama
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Trip Paling Malam</div>
                <div class="font-weight-bold" style="font-size: 1.25rem; color: #6d28d9; line-height: 1.2; margin-top: 4px;">
                  <?= $max_jam ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f5f3ff; border: 1px solid #ddd6fe; display: flex; align-items: center; justify-content: center; color: #6d28d9;">
                <i class="fas fa-moon" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-road mr-1"></i> Keberangkatan malam terakhir
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Card Data Table -->
    <div class="card mb-4" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
      <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #b8b8b8; padding: 12px 16px;">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center">
            <i class="fas fa-table mr-2 text-muted"></i>
            <span class="font-weight-bold text-dark" style="font-size: 1rem;">Daftar Jam Keberangkatan Aktif</span>
          </div>
          <button class="btn btn-primary btn-sm btn-flat" data-toggle="modal" data-target="#modalForm" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600; border-radius: 4px;">
            <i class="fas fa-plus mr-1"></i> Tambah Jam
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table id="tableJam" class="table table-excel table-hover table-bordered table-sm mb-0" style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #ffffff;">
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; text-align: center; width: 60px; font-weight: 600; color: #202124;">ID</th>
                <th style="padding: 10px 12px; border: 1px solid #b8b8b8; width: 140px; font-weight: 600; color: #202124;">Jam Berangkat</th>
                <th style="padding: 10px 12px; border: 1px solid #b8b8b8; font-weight: 600; color: #202124;">Keterangan / Periode</th>
                <th style="padding: 10px 12px; border: 1px solid #b8b8b8; text-align: center; width: 110px; font-weight: 600; color: #202124;">Status</th>
                <th style="padding: 10px 12px; border: 1px solid #b8b8b8; text-align: center; width: 150px; font-weight: 600; color: #202124;">Penggunaan Tiket</th>
                <th style="padding: 10px 8px; border: 1px solid #b8b8b8; text-align: center; width: 110px; font-weight: 600; color: #202124;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = mysqli_fetch_assoc($data)): 
                $jamFmt = substr($r['jam'], 0, 5);
                $fullJam = $r['jam'];
                $usedCount = $jamUsage[$fullJam] ?? ($jamUsage[$jamFmt . ':00'] ?? 0);
                $isActive = (int)($r['is_active'] ?? 1) === 1;
              ?>
              <tr>
                <td style="padding: 8px; border: 1px solid #b8b8b8; text-align: center; font-weight: 600; color: #5f6368;"><?= $r['id']; ?></td>
                <td style="padding: 8px 12px; border: 1px solid #b8b8b8; font-weight: 700; color: #1a73e8; font-size: 1.05rem;">
                  <i class="far fa-clock mr-1 text-muted" style="font-size: 0.9rem;"></i><?= htmlspecialchars($jamFmt); ?> <span style="font-size: 0.75rem; font-weight: 600; color: #5f6368;">WITA</span>
                </td>
                <td style="padding: 8px 12px; border: 1px solid #b8b8b8; color: #202124; font-weight: 500;">
                  <?= !empty($r['keterangan']) ? htmlspecialchars($r['keterangan']) : '<span class="text-muted font-italic">Tanpa keterangan</span>'; ?>
                </td>
                <td style="padding: 8px 12px; border: 1px solid #b8b8b8; text-align: center;">
                  <?php if ($isActive): ?>
                    <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-size: 0.8rem; padding: 4px 8px;">
                      <i class="fas fa-check-circle mr-1"></i>Aktif
                    </span>
                  <?php else: ?>
                    <span class="badge" style="background: #f1f3f4; color: #5f6368; border: 1px solid #dadce0; font-size: 0.8rem; padding: 4px 8px;">
                      <i class="fas fa-ban mr-1"></i>Nonaktif
                    </span>
                  <?php endif; ?>
                </td>
                <td style="padding: 8px 12px; border: 1px solid #b8b8b8; text-align: center;">
                  <span class="badge badge-light border" style="font-size: 0.82rem; padding: 4px 10px;">
                    <i class="fas fa-ticket-alt text-primary mr-1"></i><?= number_format($usedCount) ?> Tiket
                  </span>
                </td>
                <td style="padding: 6px 8px; border: 1px solid #b8b8b8; text-align: center;">
                  <button class="btn btn-default btn-xs btn-flat btn-edit mr-1" data-id="<?= $r['id']; ?>" title="Edit Jadwal" style="border: 1px solid #b8b8b8; background: #ffffff; border-radius: 3px; padding: 3px 8px;">
                    <i class="fas fa-pencil-alt text-primary"></i>
                  </button>
                  <button class="btn btn-default btn-xs btn-flat btn-delete" data-id="<?= $r['id']; ?>" data-jam="<?= htmlspecialchars($jamFmt) ?> WITA" title="Hapus Jadwal" style="border: 1px solid #fecaca; background: #fff5f5; border-radius: 3px; padding: 3px 8px;">
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

    <!-- Modal Tambah/Edit Jadwal Jam Keberangkatan -->
    <div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
      <div class="modal-dialog" role="document" style="max-width: 500px;">
        <form id="formJam" method="POST" class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
          <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 14px 20px;">
            <div class="d-flex align-items-center">
              <div style="width: 32px; height: 32px; border-radius: 4px; background: #e8f0fe; border: 1px solid #c2e7ff; display: flex; align-items: center; justify-content: center; margin-right: 10px; color: #1a73e8;">
                <i class="far fa-clock"></i>
              </div>
              <h5 class="modal-title font-weight-bold text-dark" id="modalFormLabel" style="font-size: 1.15rem; margin: 0;">Tambah Jadwal Jam Keberangkatan</h5>
            </div>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; color: #5f6368;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" style="padding: 20px; background: #ffffff;">
            <input type="hidden" name="id" id="id_jam">
            
            <div class="form-group mb-3">
              <label for="jam" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                Waktu Keberangkatan (Jam:Menit) <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="time" class="form-control form-control-sm font-weight-bold text-primary" name="jam" id="jam" required style="border: 1px solid #b8b8b8; border-radius: 4px; font-size: 1.05rem;">
                <div class="input-group-append">
                  <span class="input-group-text bg-light text-muted font-weight-bold" style="font-size: 0.85rem;">WITA</span>
                </div>
              </div>
              <small class="text-muted">Pilih atau ketik waktu keberangkatan (contoh: 07:00, 08:30, 14:00).</small>
            </div>

            <div class="form-group mb-3">
              <label for="keterangan" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                Keterangan / Periode Perjalanan
              </label>
              <input type="text" class="form-control form-control-sm" name="keterangan" id="keterangan" placeholder="Contoh: Pagi (Reguler), Siang Eksekutif, Malam" style="border: 1px solid #b8b8b8; border-radius: 4px;">
              <small class="text-muted">Keterangan ini akan tampil menyertai pilihan jam pada pemesanan tiket.</small>
            </div>

            <div class="form-group mb-0">
              <label for="is_active" class="font-weight-bold text-dark" style="font-size: 0.85rem;">
                Status Ketersediaan
              </label>
              <select class="form-control form-control-sm" name="is_active" id="is_active" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                <option value="1">Aktif (Dapat dipilih di form pemesanan tiket)</option>
                <option value="0">Nonaktif (Disembunyikan sementara dari pilihan)</option>
              </select>
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

    <?php include '../inc/footer.php'; ?>
