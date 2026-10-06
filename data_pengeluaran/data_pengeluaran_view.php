<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$id_users = isset($user['id_users']) ? $user['id_users'] : '';
$asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';
$role = isset($user['role']) ? $user['role'] : '';

// Handle CRUD (PRG pattern — PHP header redirect)
if (isset($_POST['tambah'])) {
    $_POST['user_id'] = $id_users;
    $_POST['asal_po'] = $asal_po;
    tambahPengeluaran($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_POST['edit'])) {
    $_POST['user_id'] = $id_users;
    $_POST['asal_po'] = $asal_po;
    updatePengeluaran($_POST);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_GET['hapus'])) {
    deletePengeluaran($_GET['hapus']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Query data pengeluaran
if ($role === 'super admin') {
    $sql = "SELECT * FROM data_pengeluaran";
} else {
    $sql = "SELECT * FROM data_pengeluaran WHERE user_id = '$id_users' AND asal_po = '$asal_po'";
}
$result = $conn->query($sql);
if (!$result) {
    echo "<div class='alert alert-danger'>Gagal mengambil data pengeluaran: " . htmlspecialchars(mysqli_error($conn)) . "</div>";
}

// Ambil data master untuk mapping id ke label
$jenisPengeluaranMap = [];
$res = mysqli_query($conn, "SELECT id_jenis_pengeluaran, nama_pengeluaran FROM data_jenis_pengeluaran");
while ($r = mysqli_fetch_assoc($res)) $jenisPengeluaranMap[$r['id_jenis_pengeluaran']] = $r['nama_pengeluaran'];
$platMap = [];
$kelasMap = [];
$hargaMap = [];
$res = mysqli_query($conn, "SELECT id_travel, no_plat, kelas, harga FROM data_travel");
while ($r = mysqli_fetch_assoc($res)) {
  $platMap[$r['id_travel']] = $r['no_plat'];
  $kelasMap[$r['id_travel']] = $r['kelas'];
  $hargaMap[$r['id_travel']] = $r['harga'];
}
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

// Hitung statistik komprehensif
$total_pengeluaran = $result ? mysqli_num_rows($result) : 0;
$total_biaya = 0;
$pengeluaran_hari_ini = 0;
$biaya_hari_ini = 0;
$total_travel = 0;
$biaya_travel = 0;
$total_kantor = 0;
$biaya_kantor = 0;

if ($result) {
  $today = date('Y-m-d');
  mysqli_data_seek($result, 0);
  while ($row = mysqli_fetch_assoc($result)) {
    $tgl = isset($row['tanggal_pengeluaran']) ? substr($row['tanggal_pengeluaran'], 0, 10) : '';
    $biaya = isset($row['harga_operasional']) && is_numeric($row['harga_operasional']) ? (float)$row['harga_operasional'] : 0;
    $kat = $row['kategori_pengeluaran'] ?? 'travel';

    $total_biaya += $biaya;
    if ($tgl === $today) {
      $pengeluaran_hari_ini++;
      $biaya_hari_ini += $biaya;
    }
    if ($kat === 'kantor') {
      $total_kantor++;
      $biaya_kantor += $biaya;
    } else {
      $total_travel++;
      $biaya_travel += $biaya;
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
            <div style="width: 40px; height: 40px; border-radius: 4px; background: #fee2e2; border: 1px solid #fecaca; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #dc2626; font-size: 1.2rem;">
              <i class="fas fa-money-bill-trend-up"></i>
            </div>
            <div>
              <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Pengeluaran Kas</h1>
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
            <i class="fas fa-plus mr-1"></i> Catat Pengeluaran
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
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Transaksi</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #202124; line-height: 1.2; margin-top: 4px;">
                  <?= number_format($total_pengeluaran) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Transaksi</small>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #f8f9fa; border: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                <i class="fas fa-receipt" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-database mr-1"></i> Akumulasi catatan biaya
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Akumulasi Beban Biaya</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #dc2626; line-height: 1.2; margin-top: 4px;">
                  Rp <?= number_format($total_biaya, 0, ',', '.') ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #fef2f2; border: 1px solid #fecaca; display: flex; align-items: center; justify-content: center; color: #dc2626;">
                <i class="fas fa-calculator" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-arrow-down mr-1 text-danger"></i> Total kas & bank keluar
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Pengeluaran Hari Ini</div>
                <div class="font-weight-bold" style="font-size: 1.45rem; color: #b45309; line-height: 1.2; margin-top: 4px;">
                  Rp <?= number_format($biaya_hari_ini, 0, ',', '.') ?>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #fef3c7; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: center; color: #d97706;">
                <i class="fas fa-calendar-day" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              <i class="fas fa-clock mr-1 text-warning"></i> <?= number_format($pengeluaran_hari_ini) ?> transaksi hari ini
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6 mb-2">
        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Beban Kategori</div>
                <div class="font-weight-bold" style="font-size: 1.1rem; color: #202124; line-height: 1.3; margin-top: 4px;">
                  <span class="text-primary"><?= number_format($total_travel) ?> Armada</span> / <span class="text-secondary"><?= number_format($total_kantor) ?> Kantor</span>
                </div>
              </div>
              <div style="width: 42px; height: 42px; border-radius: 4px; background: #e0f2fe; border: 1px solid #bae6fd; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                <i class="fas fa-pie-chart" style="font-size: 1.15rem;"></i>
              </div>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
              Rp <?= number_format($biaya_travel, 0, ',', '.') ?> (Armada)
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter & Toolbar Panel (Paper White) -->
    <div class="p-3 mb-3" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px;">
      <div class="row align-items-center">
        <div class="col-md-7">
          <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
            <span style="font-size: 0.85rem; font-weight: 700; color: #333333;">
              <i class="fas fa-filter text-muted mr-1"></i> Filter Kategori:
            </span>
            <div class="btn-group btn-group-sm" role="group">
              <button type="button" class="btn btn-default btn-flat active-filter" id="btnFilterSemua" onclick="setKategoriFilter('semua')" style="border: 1px solid #b8b8b8; font-weight: 600; background: #e6f4ea; color: #076e34;">
                Semua (<?= $total_pengeluaran ?>)
              </button>
              <button type="button" class="btn btn-default btn-flat" id="btnFilterTravel" onclick="setKategoriFilter('travel')" style="border: 1px solid #b8b8b8; font-weight: 600; background: #ffffff; color: #495057;">
                Armada Travel (<?= $total_travel ?>)
              </button>
              <button type="button" class="btn btn-default btn-flat" id="btnFilterKantor" onclick="setKategoriFilter('kantor')" style="border: 1px solid #b8b8b8; font-weight: 600; background: #ffffff; color: #495057;">
                Operasional Kantor (<?= $total_kantor ?>)
              </button>
            </div>
            <input type="hidden" id="filterKategori" value="semua">
          </div>
        </div>
        <div class="col-md-5 text-right mt-2 mt-md-0">
          <span class="text-muted" style="font-size: 0.82rem;">
            Total Terfilter: <strong id="totalSemuaText" class="text-dark"><?= $total_pengeluaran ?> Data</strong>
          </span>
        </div>
      </div>
    </div>

    <!-- Main Card & DataTables Container -->
    <div class="card" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
      <div class="card-header" style="background: #ffffff; border-bottom: 1.5px solid #a0a0a0; padding: 12px 16px;">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
          <div class="d-flex align-items-center">
            <i class="fas fa-table text-muted mr-2"></i>
            <h3 class="card-title m-0" style="font-size: 0.95rem; font-weight: 700; color: #202124; letter-spacing: 0.2px;">
              Buku Kas & Catatan Pengeluaran Biaya
            </h3>
          </div>
          <div>
            <span class="badge" style="background:#e6f4ea; color:#076e34; border:1px solid #b7e1cd; font-size: 0.8rem; font-weight: 600;">
              Total Beban: Rp <?= number_format($total_biaya, 0, ',', '.') ?>
            </span>
          </div>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="padding: 10px 14px;">
          <table id="tablePengeluaran" class="table table-bordered table-excel table-sm" style="width:100%;">
          <thead>
            <tr>
                <th style="width: 45px; text-align: center;">ID</th>
                <th style="width: 85px;">Tanggal</th>
                <th style="width: 80px; text-align: center;">Kategori</th>
                <th>Jenis Pengeluaran</th>
                <th style="width: 120px;">Nominal Beban (Rp)</th>
                <th>No. Plat Armada</th>
                <th>Keterangan / Keperluan</th>
                <th style="text-align: center;">Status</th>
                <th>Metode Bayar</th>
                <th>Rekening Kas/Bank</th>
                <th>Petugas</th>
                <th>Asal PO</th>
                <th style="width: 75px; text-align: center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
              <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php 
                  $kategori = $row['kategori_pengeluaran'] ?? 'travel';
                  $isKantor = ($kategori === 'kantor');
                ?>
                <tr data-kategori="<?= $kategori ?>">
                    <td style="text-align: center;"><span class="badge" style="background:#f1f3f4; color:#202124; border:1px solid #dadce0;"><?= $row['id_pengeluaran'] ?></span></td>
                    <td><?= htmlspecialchars($row['tanggal_pengeluaran']) ?></td>
                    <td style="text-align: center;">
                      <?php if ($isKantor): ?>
                        <span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-weight: 600;">Kantor</span>
                      <?php else: ?>
                        <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600;">Travel</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <strong>
                        <?= isset($jenisPengeluaranMap[$row['jenis_pengeluaran_id']]) ? htmlspecialchars($jenisPengeluaranMap[$row['jenis_pengeluaran_id']]) : htmlspecialchars($row['jenis_pengeluaran_id']) ?>
                      </strong>
                    </td>
                    <td style="font-weight: 700; color: #dc2626;">
                      Rp <?= number_format($row['harga_operasional'], 0, ',', '.') ?>
                    </td>
                    <td>
                      <?php if (!empty($row['travel_id']) && isset($platMap[$row['travel_id']])): ?>
                        <span class="badge" style="background: #f1f3f4; color: #202124; border: 1px solid #dadce0; font-weight: 600;">
                          <i class="fas fa-bus mr-1 text-muted"></i><?= htmlspecialchars($platMap[$row['travel_id']]) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($row['keterangan'])): ?>
                        <span title="<?= htmlspecialchars($row['keterangan']) ?>">
                          <?= htmlspecialchars($row['keterangan']) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                      <?php 
                        $status_raw = isset($statusMap[$row['status_pembayaran_id']]) ? $statusMap[$row['status_pembayaran_id']] : $row['status_pembayaran_id'];
                        $isLunas = (strpos(strtolower($status_raw), 'lunas') !== false);
                      ?>
                      <?php if ($isLunas): ?>
                        <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">Lunas</span>
                      <?php else: ?>
                        <span class="badge" style="background: #fff8e1; color: #b78103; border: 1px solid #ffe082; font-weight: 600;"><?= htmlspecialchars($status_raw) ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= isset($metodeMap[$row['metode_pembayaran_id']]) ? htmlspecialchars($metodeMap[$row['metode_pembayaran_id']]) : htmlspecialchars($row['metode_pembayaran_id']) ?>
                    </td>
                    <td>
                      <span class="badge" style="background: #f8f9fa; color: #3c4043; border: 1px solid #dadce0;">
                        <i class="fas fa-university mr-1 text-muted"></i><?= isset($rekeningMap[$row['jenis_rekening_id']]) ? htmlspecialchars($rekeningMap[$row['jenis_rekening_id']]) : htmlspecialchars($row['jenis_rekening_id']) ?>
                      </span>
                    </td>
                    <td><?= isset($userMap[$row['user_id']]) ? htmlspecialchars($userMap[$row['user_id']]) : htmlspecialchars($row['user_id']) ?></td>
                    <td><?= htmlspecialchars($row['asal_po']) ?></td>
                    <td style="text-align: center;">
                      <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-default btn-xs btn-flat btn-edit"
                          data-id="<?= $row['id_pengeluaran'] ?>"
                          data-tanggal="<?= htmlspecialchars($row['tanggal_pengeluaran']) ?>"
                          data-kategori="<?= htmlspecialchars($row['kategori_pengeluaran'] ?? 'travel') ?>"
                          data-jenis="<?= htmlspecialchars($row['jenis_pengeluaran_id']) ?>"
                          data-hargaoperasional="<?= htmlspecialchars($row['harga_operasional']) ?>"
                          data-keterangan="<?= htmlspecialchars($row['keterangan'] ?? '') ?>"
                          data-travel="<?= htmlspecialchars($row['travel_id'] ?? '') ?>"
                          data-kelas="<?= htmlspecialchars($row['kelas'] ?? '') ?>"
                          data-harga="<?= htmlspecialchars($row['harga'] ?? '') ?>"
                          data-status="<?= htmlspecialchars($row['status_pembayaran_id']) ?>"
                          data-metode="<?= htmlspecialchars($row['metode_pembayaran_id']) ?>"
                          data-rekening="<?= htmlspecialchars($row['jenis_rekening_id']) ?>"
                          data-user="<?= htmlspecialchars($row['user_id']) ?>"
                          data-asalpo="<?= htmlspecialchars($row['asal_po']) ?>" 
                          title="Edit"
                          style="border: 1px solid #b8b8b8; background: #ffffff; padding: 2px 6px;">
                          <i class="fas fa-edit text-warning"></i>
                        </button>
                        <button class="btn btn-default btn-xs btn-flat btn-delete" data-id="<?= $row['id_pengeluaran'] ?>" title="Hapus" style="border: 1px solid #b8b8b8; background: #ffffff; padding: 2px 6px;">
                          <i class="fas fa-trash text-danger"></i>
                        </button>
                      </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                  <td colspan="13" class="text-center text-muted" style="padding: 24px;">
                    <i class="fas fa-inbox fa-2x mb-2 text-muted"></i>
                    <br>Belum ada catatan data pengeluaran kas
                  </td>
              </tr>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr style="background: #fafafa; font-weight: 700; border-top: 1.5px solid #a0a0a0;">
              <td colspan="4" class="text-right" style="padding: 8px;">TOTAL REKAPITULASI BIAYA KELUAR:</td>
              <td colspan="9" style="padding: 8px; color: #dc2626; font-size: 1rem;">
                Rp <?= number_format($total_biaya, 0, ',', '.') ?>
              </td>
            </tr>
          </tfoot>
        </table>
        </div>
      </div>
    </div>

<!-- Modal Tambah/Edit Pengeluaran (Paper White v2.0 - 3 Seksi) -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="modalFormLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document" style="max-width:1050px;">
    <form id="formPengeluaran" method="POST" class="modal-content" style="border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none;">
      <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 12px 20px;">
        <h5 class="modal-title text-dark" id="modalFormLabel" style="font-size: 1.1rem; font-weight: 700;">
          <i class="fas fa-receipt mr-2 text-danger"></i>Formulir Catatan Pengeluaran Kas
        </h5>
        <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style="padding: 20px 22px; background: #fafafa;">
        <input type="hidden" name="id_pengeluaran" id="id_pengeluaran">

        <!-- Seksi 1: Waktu & Klasifikasi Pengeluaran -->
        <div class="card mb-3" style="border: 1px solid #d3d3d3; border-radius: 4px; background: #ffffff; box-shadow: none;">
          <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; padding: 8px 14px;">
            <strong style="font-size: 0.88rem; color: #333333;">
              <i class="fas fa-calendar-alt text-primary mr-1"></i> 1. Waktu & Klasifikasi Pengeluaran
            </strong>
          </div>
          <div class="card-body" style="padding: 14px;">
            <div class="row">
              <div class="col-md-4">
                <div class="form-group mb-0">
                  <label for="tanggal_pengeluaran" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Tanggal Pengeluaran <span class="text-danger">*</span>
                  </label>
                  <input type="date" class="form-control form-control-sm" name="tanggal_pengeluaran" id="tanggal_pengeluaran" required style="border: 1px solid #b8b8b8;">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-0">
                  <label for="kategori_pengeluaran" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Kategori Pengeluaran <span class="text-danger">*</span>
                  </label>
                  <select class="form-control form-control-sm" name="kategori_pengeluaran" id="kategori_pengeluaran" onchange="toggleTravelFields()" required style="border: 1px solid #b8b8b8;">
                    <option value="">-- Pilih Kategori --</option>
                    <option value="travel">Pengeluaran Travel (Armada)</option>
                    <option value="kantor">Pengeluaran Operasional Kantor</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-0">
                  <label for="jenis_pengeluaran_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Jenis / Nama Pengeluaran <span class="text-danger">*</span>
                  </label>
                  <select class="form-control form-control-sm" name="jenis_pengeluaran_id" id="jenis_pengeluaran_id" onchange="autofillJenisPengeluaran()" required style="border: 1px solid #b8b8b8;">
                    <option value="">-- Pilih Jenis Pengeluaran --</option>
                    <?php
                      $query = "SELECT id_jenis_pengeluaran, nama_pengeluaran, biaya_standar, keterangan FROM data_jenis_pengeluaran ORDER BY nama_pengeluaran ASC";
                      $resultJenis = mysqli_query($conn, $query);
                      while ($rowJenis = mysqli_fetch_assoc($resultJenis)) {
                        $biayaFmt = number_format($rowJenis['biaya_standar'], 0, ',', '.');
                        $label = htmlspecialchars(trim($rowJenis['nama_pengeluaran']));
                        if ($rowJenis['biaya_standar'] > 0) {
                          $label .= ' (Rp ' . $biayaFmt . ')';
                        }
                        echo '<option value="' . $rowJenis['id_jenis_pengeluaran'] . '" data-biaya="' . (float)$rowJenis['biaya_standar'] . '" data-keterangan="' . htmlspecialchars($rowJenis['keterangan'] ?? '') . '">' . $label . '</option>';
                      }
                    ?>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Seksi 2: Rincian Armada & Keterangan Biaya -->
        <div class="card mb-3" style="border: 1px solid #d3d3d3; border-radius: 4px; background: #ffffff; box-shadow: none;">
          <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; padding: 8px 14px;">
            <strong style="font-size: 0.88rem; color: #333333;">
              <i class="fas fa-bus text-info mr-1"></i> 2. Rincian Unit Armada & Keterangan Biaya
            </strong>
          </div>
          <div class="card-body" style="padding: 14px;">
            <!-- Field Travel (hanya aktif saat kategori travel) -->
            <div id="travelFields" style="display: none; margin-bottom: 12px; padding: 10px; background: #f8f9fa; border: 1px dashed #ced4da; border-radius: 4px;">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-0">
                    <label for="travel_id" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                      No. Plat Kendaraan Armada <span class="text-danger">*</span>
                    </label>
                    <select class="form-control form-control-sm" name="travel_id" id="travel_id" onchange="autofillTravel()" style="border: 1px solid #b8b8b8;">
                      <option value="">-- Pilih Armada Kendaraan --</option>
                      <?php
                        $query = "SELECT id_travel, no_plat FROM data_travel";
                        $resultTravel = mysqli_query($conn, $query);
                        while ($rowTravel = mysqli_fetch_assoc($resultTravel)) {
                          echo '<option value="' . $rowTravel['id_travel'] . '">' . htmlspecialchars($rowTravel['no_plat']) . '</option>';
                        }
                      ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-0">
                    <label for="kelas" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">Kelas Armada</label>
                    <input type="text" class="form-control form-control-sm" name="kelas" id="kelas" readonly style="background: #ffffff; border: 1px solid #b8b8b8;">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-0">
                    <label for="harga" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">Tarif Standar</label>
                    <input type="number" class="form-control form-control-sm" name="harga" id="harga" min="0" readonly style="background: #ffffff; border: 1px solid #b8b8b8;">
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <label for="keterangan" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Rincian Keperluan / Catatan Pengeluaran
                  </label>
                  <textarea class="form-control form-control-sm" name="keterangan" id="keterangan" rows="2" placeholder="Tuliskan catatan nota/bukti pengeluaran, nomor faktur, perbaikan suku cadang, bensin, dsb..." style="border: 1px solid #b8b8b8;"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Seksi 3: Biaya & Sumber Dana Kasir -->
        <div class="card mb-0" style="border: 1px solid #d3d3d3; border-radius: 4px; background: #ffffff; box-shadow: none;">
          <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; padding: 8px 14px;">
            <strong style="font-size: 0.88rem; color: #333333;">
              <i class="fas fa-coins text-warning mr-1"></i> 3. Nominal Biaya & Sumber Dana Pembayaran
            </strong>
          </div>
          <div class="card-body" style="padding: 14px;">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label for="harga_operasional" style="font-size: 0.84rem; font-weight: 600; margin-bottom: 3px;">
                    Nominal Biaya (Rp) <span class="text-danger">*</span>
                  </label>
                  <input type="number" class="form-control form-control-sm font-weight-bold" name="harga_operasional" id="harga_operasional" min="0" placeholder="Rp 0" required style="border: 1.5px solid #dc2626; color: #dc2626; font-size: 0.95rem;">
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
                      $resultStatus = mysqli_query($conn, $query);
                      while ($rowStatus = mysqli_fetch_assoc($resultStatus)) {
                        echo '<option value="' . $rowStatus['id_status_pembayaran'] . '">' . htmlspecialchars($rowStatus['status_pembayaran']) . '</option>';
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
                      $resultMetode = mysqli_query($conn, $query);
                      while ($rowMetode = mysqli_fetch_assoc($resultMetode)) {
                        echo '<option value="' . $rowMetode['id_metode_pembayaran'] . '">' . htmlspecialchars($rowMetode['metode_pembayaran']) . '</option>';
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
                    <option value="">-- Rekening yang Dipotong --</option>
                    <?php
                      $query = "SELECT id_rekening, nama_rekening FROM data_rekening";
                      $resultRekening = mysqli_query($conn, $query);
                      while ($rowRekening = mysqli_fetch_assoc($resultRekening)) {
                        echo '<option value="' . $rowRekening['id_rekening'] . '">' . htmlspecialchars($rowRekening['nama_rekening']) . '</option>';
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
          <i class="fas fa-save mr-1"></i> Simpan Catatan Pengeluaran
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Filter pengeluaran kategori dengan tombol preset
function setKategoriFilter(kat) {
  $('#filterKategori').val(kat);
  
  // Update button visual
  $('#btnFilterSemua, #btnFilterTravel, #btnFilterKantor').css({
    'background': '#ffffff',
    'color': '#495057'
  });
  
  if (kat === 'semua') {
    $('#btnFilterSemua').css({'background': '#e6f4ea', 'color': '#076e34'});
  } else if (kat === 'travel') {
    $('#btnFilterTravel').css({'background': '#e6f4ea', 'color': '#076e34'});
  } else if (kat === 'kantor') {
    $('#btnFilterKantor').css({'background': '#e6f4ea', 'color': '#076e34'});
  }

  // Terapkan ke DataTables kolom Kategori (kolom 2)
  if ($.fn.DataTable.isDataTable('#tablePengeluaran')) {
    var table = $('#tablePengeluaran').DataTable();
    if (kat === 'semua') {
      table.column(2).search('').draw();
    } else if (kat === 'travel') {
      table.column(2).search('Travel').draw();
    } else if (kat === 'kantor') {
      table.column(2).search('Kantor').draw();
    }
    var info = table.page.info();
    $('#totalSemuaText').text(info.recordsDisplay + ' Data');
  }
}

function toggleTravelFields() {
  var kategori = document.getElementById('kategori_pengeluaran').value;
  var travelFields = document.getElementById('travelFields');
  var travelSelect = document.getElementById('travel_id');
  
  if (kategori === 'travel') {
    travelFields.style.display = 'block';
    travelSelect.required = true;
  } else {
    travelFields.style.display = 'none';
    travelSelect.required = false;
    document.getElementById('travel_id').value = '';
    document.getElementById('kelas').value = '';
    document.getElementById('harga').value = '';
  }
}

function autofillTravel() {
  var travelId = document.getElementById('travel_id').value;
  if (travelId) {
    fetch('get_travel.php?id=' + travelId)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('kelas').value = data.kelas || '';
          document.getElementById('harga').value = data.harga || '';
        }
      })
      .catch(error => console.error('Error:', error));
  } else {
    document.getElementById('kelas').value = '';
    document.getElementById('harga').value = '';
  }
}

// Map tarif master data jenis pengeluaran untuk referensi instan
var tarifPengeluaranMap = {
  <?php
    $resMapP = mysqli_query($conn, "SELECT id_jenis_pengeluaran, biaya_standar FROM data_jenis_pengeluaran");
    if ($resMapP) {
      while ($rmp = mysqli_fetch_assoc($resMapP)) {
        echo (int)$rmp['id_jenis_pengeluaran'] . ': ' . (float)$rmp['biaya_standar'] . ",\n";
      }
    }
  ?>
};

// Autofill Pengeluaran berdasarkan Jenis / Pos Pengeluaran
function autofillJenisPengeluaran(targetVal) {
  var $sel = $('#jenis_pengeluaran_id');
  var id = targetVal || ($sel.length ? $sel.val() : '');
  if (!id && document.getElementById('jenis_pengeluaran_id')) {
    id = document.getElementById('jenis_pengeluaran_id').value;
  }
  if (!id) return;

  var biaya = null;
  var $opt = $sel.find('option:selected');
  if ($opt.length && $opt.val()) {
    var rawBiaya = $opt.attr('data-biaya') || $opt.data('biaya');
    if (rawBiaya !== undefined && rawBiaya !== null && rawBiaya !== '') {
      biaya = parseFloat(rawBiaya);
    }
  }

  // Fallback ke tarifPengeluaranMap
  if ((biaya === null || isNaN(biaya) || biaya <= 0) && id && typeof tarifPengeluaranMap !== 'undefined' && tarifPengeluaranMap[id] !== undefined) {
    biaya = parseFloat(tarifPengeluaranMap[id]);
  }

  // Masukkan nominal ke input Nominal Biaya (#harga_operasional)
  var inputHarga = document.getElementById('harga_operasional');
  if (inputHarga && biaya !== null && !isNaN(biaya) && biaya > 0) {
    inputHarga.value = biaya;
    $(inputHarga).trigger('input').trigger('change');
  }

  // Autofill Keterangan jika masih kosong dan ada keterangan di opsi
  var $ketInput = $('#keterangan');
  if ($ketInput.length && !$ketInput.val().trim() && $opt.length) {
    var optKet = $opt.attr('data-keterangan') || $opt.data('keterangan');
    if (optKet && optKet.trim()) {
      $ketInput.val(optKet.trim());
    }
  }

  // Autofill Status Pembayaran (Default ke LUNAS / id: 1 jika belum dipilih)
  var $status = $('#status_pembayaran_id');
  if ($status.length && (!$status.val() || $status.val() === '')) {
    $status.val('1').trigger('change');
  }

  // Autofill Metode Pembayaran (Default ke TUNAI / id: 2 jika belum dipilih)
  var $metode = $('#metode_pembayaran_id');
  if ($metode.length && (!$metode.val() || $metode.val() === '')) {
    $metode.val('2').trigger('change');
  }

  // Autofill Rekening Kasir (Default ke rekening kasir PO asal jika belum dipilih)
  var $rek = $('#jenis_rekening_id');
  if ($rek.length && (!$rek.val() || $rek.val() === '')) {
    <?php
      $defRekP = 6; // Default CASH (KASIR) PO SUMBAWA
      if (stripos($asal_po, 'mataram') !== false) {
        $defRekP = 4; // CASH (KASIR) PO MATARAM
      }
    ?>
    var defRekPVal = '<?= $defRekP ?>';
    if (defRekPVal && $rek.find('option[value="' + defRekPVal + '"]').length) {
      $rek.val(defRekPVal).trigger('change');
    }
  }
}

// Event change pada dropdown jenis pengeluaran
$(document).on('change', '#jenis_pengeluaran_id', function() {
  autofillJenisPengeluaran($(this).val());
});

// Handle edit button
$(document).on('click', '.btn-edit', function() {
  var data = $(this).data();
  
  $('#id_pengeluaran').val(data.id);
  $('#tanggal_pengeluaran').val(data.tanggal);
  $('#kategori_pengeluaran').val(data.kategori);
  $('#jenis_pengeluaran_id').val(data.jenis);
  $('#harga_operasional').val(data.hargaoperasional);
  $('#keterangan').val(data.keterangan);
  $('#travel_id').val(data.travel);
  $('#kelas').val(data.kelas);
  $('#harga').val(data.harga);
  $('#status_pembayaran_id').val(data.status);
  $('#metode_pembayaran_id').val(data.metode);
  $('#jenis_rekening_id').val(data.rekening);
  
  toggleTravelFields();
  
  $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
  $('#modalFormLabel').html('<i class="fas fa-edit mr-2 text-warning"></i> Edit Catatan Pengeluaran');
  $('#modalForm').modal('show');
});

// Reset form saat modal ditutup
$('#modalForm').on('hidden.bs.modal', function() {
  $('#formPengeluaran')[0].reset();
  $('#travelFields').hide();
  $('#btnSave').attr('name', 'tambah').html('<i class="fas fa-save mr-1"></i> Simpan Catatan Pengeluaran');
  $('#modalFormLabel').html('<i class="fas fa-receipt mr-2 text-danger"></i> Formulir Catatan Pengeluaran Kas');
});

// Handle delete button dengan SweetAlert2 Paper White
$(document).on('click', '.btn-delete', function(e) {
  e.preventDefault();
  var id = $(this).data('id');
  
  if (typeof Swal !== 'undefined') {
    Swal.fire({
      title: 'Konfirmasi Hapus Data?',
      text: 'Catatan pengeluaran ini akan dihapus permanen dari buku kas!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#c62828',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus',
      cancelButtonText: 'Batal'
    }).then(function(result) {
      if (result.isConfirmed) {
        window.location.href = '?hapus=' + id;
      }
    });
  } else {
    if (confirm('Apakah Anda yakin ingin menghapus data pengeluaran ini?')) {
      window.location.href = '?hapus=' + id;
    }
  }
});
</script>

<?php include '../inc/footer.php'; ?>
