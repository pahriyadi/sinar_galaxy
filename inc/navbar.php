<?php
if (!session_id()) {
  session_start();
}

$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$username = isset($user['nama_lengkap']) && $user['nama_lengkap'] ? (string)$user['nama_lengkap'] : ((string)($user['username'] ?? ''));
$uname = (string)($user['username'] ?? '');
$role = (string)($user['role'] ?? '');
$asal_po = (string)($user['asal_po'] ?? '');

// Penentuan base prefix untuk link (diasumsikan file ini di-include dari subfolder)
$base = is_file('data_dashboard/dashboard.php') ? '' : '../';
$navbar_logo = function_exists('getSetting') ? getSetting('app_logo', 'img/logo_sgt.png') : 'img/logo_sgt.png';
$navbar_app_name = function_exists('getSetting') ? getSetting('app_name', 'Sinar Galaxy') : 'Sinar Galaxy';
?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom" style="background: #ffffff; border-color: #e0e0e0;">
  <!-- Left navbar links -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link text-dark" data-widget="pushmenu" href="#" role="button" title="Buka/Tutup Menu"><i class="fas fa-bars"></i></a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="<?= $base ?>data_dashboard/dashboard" class="nav-link text-dark font-weight-600">Dashboard</a>
    </li>
  </ul>

  <!-- Right navbar links: Profil Terpusat & Efektif -->
  <ul class="navbar-nav ml-auto align-items-center">
    <!-- Info Cabang / PO -->
    <li class="nav-item d-none d-md-inline-block mr-2">
      <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-size: 0.78rem; font-weight: 600; padding: 5px 9px;">
        <i class="fas fa-building mr-1"></i><?= $role === 'super admin' ? 'Seluruh Cabang' : 'PO ' . htmlspecialchars($asal_po ?: 'Sumbawa') ?>
      </span>
    </li>

    <!-- User Profile Dropdown -->
    <li class="nav-item dropdown user-menu">
      <a href="#" class="nav-link dropdown-toggle d-flex align-items-center p-1" data-toggle="dropdown" style="border-radius: 4px; border: 1px solid transparent;">
        <img src="<?= $base . htmlspecialchars($navbar_logo) ?>" class="user-image img-circle mr-2" alt="User Image" style="width: 30px; height: 30px; object-fit: cover; border: 1px solid #dcdcdc;">
        <div class="d-none d-lg-block text-left mr-1" style="line-height: 1.15;">
          <span class="d-block font-weight-bold text-dark" style="font-size: 0.83rem;"><?= htmlspecialchars($username ?: 'Pengguna') ?></span>
          <span class="text-muted" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.3px;"><?= htmlspecialchars($role ?: 'Staff') ?></span>
        </div>
        <i class="fas fa-chevron-down text-muted ml-1" style="font-size: 0.65rem;"></i>
      </a>

      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="border: 1px solid #c8c8c8; border-radius: 4px; box-shadow: 0 4px 14px rgba(0,0,0,0.1); min-width: 250px; padding: 0;">
        <!-- Header Profil Ringkas -->
        <div class="p-3 text-center" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0;">
          <img src="<?= $base . htmlspecialchars($navbar_logo) ?>" class="img-circle mb-2" alt="Avatar" style="width: 50px; height: 50px; border: 2px solid #0d9f4f; padding: 2px; background: #fff; object-fit: cover;">
          <div class="font-weight-bold text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($username ?: 'Pengguna') ?></div>
          <div class="text-muted" style="font-size: 0.78rem;">@<?= htmlspecialchars($uname ?: 'user') ?></div>
          <div class="mt-2">
            <span class="badge badge-success text-uppercase px-2 py-1" style="font-size: 0.72rem; font-weight: 600;"><?= htmlspecialchars($role ?: 'User') ?></span>
            <span class="badge badge-secondary px-2 py-1" style="font-size: 0.72rem; font-weight: 600;"><?= htmlspecialchars($role === 'super admin' ? 'Kantor Pusat' : ($asal_po ? 'PO ' . $asal_po : 'Cabang')) ?></span>
          </div>
        </div>

        <!-- Menu Aksi Akun -->
        <div class="p-1">
          <a href="<?= $base ?>profile/profile_enhanced" class="dropdown-item py-2 px-3" style="font-size: 0.85rem;">
            <i class="fas fa-user-circle mr-2 text-primary" style="width: 16px;"></i> Pengaturan Profil
          </a>
          <?php if ($role === 'super admin'): ?>
          <a href="<?= $base ?>data_activity_log/login_history" class="dropdown-item py-2 px-3" style="font-size: 0.85rem;">
            <i class="fas fa-clock mr-2 text-info" style="width: 16px;"></i> Riwayat Masuk Sesi
          </a>
          <?php endif; ?>
          <div class="dropdown-divider my-1"></div>
          <a href="<?= $base ?>logout" class="dropdown-item py-2 px-3 text-danger font-weight-bold" style="font-size: 0.85rem;">
            <i class="fas fa-sign-out-alt mr-2" style="width: 16px;"></i> Keluar Sistem
          </a>
        </div>
      </div>
    </li>
  </ul>
</nav>

<?php
// =========================================================================
// NOTIFIKASI STICKY HEADER: PENUGASAN PLAT ARMADA (H-2 s/d HARI-H)
// Muncul otomatis bagi role Super Admin & Admin jika ada tiket belum ada plat
// =========================================================================
if (in_array(strtolower((string)$role), ['super admin', 'admin']) && isset($conn) && $conn instanceof mysqli):
    $qPendingH2 = @$conn->query("SELECT 
        COUNT(*) as total_pending,
        SUM(CASE WHEN DATE(tanggal_berangkat) = CURDATE() THEN 1 ELSE 0 END) as jml_hari_ini,
        SUM(CASE WHEN DATE(tanggal_berangkat) = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as jml_h1,
        SUM(CASE WHEN DATE(tanggal_berangkat) = DATE_ADD(CURDATE(), INTERVAL 2 DAY) THEN 1 ELSE 0 END) as jml_h2,
        MIN(DATE(tanggal_berangkat)) as tgl_terdekat
    FROM data_pemesanan
    WHERE (no_plat_id IS NULL OR no_plat_id = '' OR no_plat_id = '-' OR no_plat_id = 'BELUM DITENTUKAN')
      AND DATE(tanggal_berangkat) <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
      AND DATE(tanggal_berangkat) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)");

    $dataPending = $qPendingH2 ? $qPendingH2->fetch_assoc() : null;
    $totalPendingH2 = (int)($dataPending['total_pending'] ?? 0);

    if ($totalPendingH2 > 0):
        $tglTarget = $dataPending['tgl_terdekat'] ?: date('Y-m-d');
        $jmlHariIni = (int)($dataPending['jml_hari_ini'] ?? 0);
        $jmlH1 = (int)($dataPending['jml_h1'] ?? 0);
        $jmlH2 = (int)($dataPending['jml_h2'] ?? 0);
?>
<div id="stickyPendingPlatAlert" class="main-header no-print border-bottom" style="background: linear-gradient(90deg, #fffbeb 0%, #fef3c7 100%); border-color: #fcd34d !important; padding: 7px 16px; font-size: 0.83rem; box-shadow: 0 2px 5px rgba(0,0,0,0.05); position: relative;">
  <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 8px;">
    
    <!-- Informasi & Peringatan -->
    <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
      <span class="badge text-uppercase font-weight-bold px-2 py-1" style="background: #d97706; color: #ffffff; font-size: 0.72rem; letter-spacing: 0.3px; border-radius: 4px; box-shadow: 0 1px 3px rgba(217,119,6,0.3);">
        <i class="fas fa-exclamation-triangle mr-1"></i> Perhatian: H-2 Keberangkatan
      </span>
      <span class="text-dark font-weight-600">
        Terdapat <strong class="text-danger" style="font-size: 0.95rem;"><?= $totalPendingH2 ?> Penumpang</strong> yang belum ditentukan nomor plat armadanya!
      </span>

      <!-- Rincian Waktu -->
      <div class="d-inline-flex align-items-center ml-lg-2 flex-wrap" style="gap: 4px;">
        <?php if ($jmlHariIni > 0): ?>
          <span class="badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; font-size: 0.72rem;">
            Hari Ini: <strong><?= $jmlHariIni ?></strong>
          </span>
        <?php endif; ?>
        <?php if ($jmlH1 > 0): ?>
          <span class="badge" style="background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; font-size: 0.72rem;">
            Besok (H-1): <strong><?= $jmlH1 ?></strong>
          </span>
        <?php endif; ?>
        <?php if ($jmlH2 > 0): ?>
          <span class="badge" style="background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; font-size: 0.72rem;">
            Lusa (H-2): <strong><?= $jmlH2 ?></strong>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tombol Aksi Cepat & Minimize -->
    <div class="d-flex align-items-center ml-auto" style="gap: 6px;">
      <a href="<?= $base ?>laporan/daftar_keberangkatan_all?tgl_dari=<?= $tglTarget ?>&no_plat=PENDING&auto_open=isi_plat" class="btn btn-sm font-weight-bold text-white" style="background-color: #0b3b7b; border-color: #082852; padding: 3px 12px; font-size: 0.78rem; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.12);" title="Buka modal penetapan plat untuk keberangkatan terdekat">
        <i class="fas fa-bus mr-1"></i> Tetapkan Plat Sekarang
      </a>
      <button type="button" class="btn btn-xs btn-outline-secondary ml-1" onclick="dismissPendingPlatAlert()" title="Sembunyikan notifikasi sementara" style="border-radius: 3px; font-size: 0.7rem; padding: 2px 6px; border-color: #d1d5db; background: rgba(255,255,255,0.8);">
        <i class="fas fa-times"></i>
      </button>
    </div>

  </div>
</div>

<script>
(function() {
  if (sessionStorage.getItem('hide_pending_plat_alert') === '1') {
    var el = document.getElementById('stickyPendingPlatAlert');
    if (el) el.style.display = 'none';
  }
})();

function dismissPendingPlatAlert() {
  var el = document.getElementById('stickyPendingPlatAlert');
  if (el) {
    el.style.display = 'none';
    sessionStorage.setItem('hide_pending_plat_alert', '1');
  }
}
</script>
<?php
    endif;
endif;
?>
