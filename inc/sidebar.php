<?php
if (!session_id()) {
  session_start();
}
$user = $_SESSION['user'] ?? [];
$role = (string) ($user['role'] ?? '');
$username = (string) ($user['username'] ?? '');

// Helper: cek active menu berdasarkan URL saat ini (fleksibel dengan / tanpa .php)
$current = basename($_SERVER['PHP_SELF'] ?? '');
$currentClean = preg_replace('/\.php$/i', '', $current);

function isActive($files)
{
  global $current, $currentClean;
  if (is_string($files)) {
    $files = [$files];
  }
  foreach ($files as $f) {
    $fClean = preg_replace('/\.php$/i', '', $f);
    if ($current === $f || $currentClean === $fClean) {
      return ' active';
    }
  }
  return '';
}

function isMenuOpen($files)
{
  return isActive($files) !== '' ? 'block' : 'none';
}

// Penentuan base prefix untuk link (diasumsikan file ini di-include dari subfolder)
$base = is_file('data_dashboard/dashboard.php') ? '' : '../';

$app_logo_sidebar = function_exists('getSetting') ? getSetting('app_logo', 'img/logo_sgt.png') : 'img/logo_sgt.png';
$app_name_sidebar = function_exists('getSetting') ? getSetting('app_name', 'Sinar Galaxy') : 'Sinar Galaxy';
?>

<aside class="main-sidebar sidebar-light-success border-right elevation-0">
  <a href="<?= $base ?>data_dashboard/dashboard" class="brand-link" style="border-bottom: 1px solid #e0e0e0; background: #ffffff;">
    <img src="<?= $base ?><?= htmlspecialchars($app_logo_sidebar) ?>" alt="Logo" class="brand-image img-circle elevation-0"
      style="opacity: .9; object-fit: contain;">
    <span class="brand-text font-weight-bold" style="color: #202124; font-size: 0.95rem;"><?= htmlspecialchars($app_name_sidebar) ?></span>
  </a>

  <div class="sidebar p-0">
    <nav class="mt-2 px-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
          <a href="<?= $base ?>data_dashboard/dashboard" class="nav-link<?= isActive(['dashboard']) ?>">
            <i class="nav-icon fas fa-gauge"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <li class="nav-header">OPERASIONAL</li>
        <li class="nav-item has-treeview<?= isActive(['kontrol_kursi', 'data_pemesanan_view_v2','data_pemesanan_view', 'data_pengiriman_view']) ?>">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-receipt"></i>
            <p>
              Tiket & Paket
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview"
            style="display: <?= isMenuOpen(['kontrol_kursi', 'data_pemesanan_view_v2', 'data_pemesanan_view', 'data_pengiriman_view']) ?>;">
            <li class="nav-item">
              <a href="<?= $base ?>data_pemesanan/kontrol_kursi"
                class="nav-link<?= isActive('kontrol_kursi') ?>">
                <i class="fas fa-couch nav-icon text-success"></i>
                <p>Kontrol Kursi & Armada</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?= $base ?>data_pemesanan/data_pemesanan_view_v2"
                class="nav-link<?= isActive('data_pemesanan_view_v2') ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Pemesanan Tiket</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?= $base ?>data_pemesanan/data_pemesanan_view"
                class="nav-link<?= isActive('data_pemesanan_view') ?>"
                onclick="return confirm('Halaman arsip ini akan memakan waktu untuk dibuka. Lanjut?')">
                <i class="far fa-circle nav-icon"></i>
                <p>Arsip Tiket (V1)</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="<?= $base ?>data_pengiriman/data_pengiriman_view"
                class="nav-link<?= isActive('data_pengiriman_view') ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Pengiriman Paket</p>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="<?= $base ?>data_pengeluaran/data_pengeluaran_view"
            class="nav-link<?= isActive('data_pengeluaran_view') ?>">
            <i class="nav-icon fas fa-money-bill-trend-up"></i>
            <p>Pengeluaran Kas</p>
          </a>
        </li>

        <li class="nav-header">DATA MASTER</li>
        <?php if ($role === 'super admin'): ?>
          <li class="nav-item has-treeview<?= isActive(['data_pelanggan_view', 'data_tujuan_perjalanan_view', 'data_travel_view', 'data_jenis_barang_view', 'data_metode_pembayaran_view', 'data_rekening_view', 'data_status_pembayaran_view', 'data_jenis_pengeluaran_view']) ?>">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-database"></i>
              <p>
                Master Data
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview"
              style="display: <?= isMenuOpen(['data_pelanggan_view', 'data_tujuan_perjalanan_view', 'data_travel_view', 'data_jenis_barang_view', 'data_metode_pembayaran_view', 'data_rekening_view', 'data_status_pembayaran_view', 'data_jenis_pengeluaran_view']) ?>;">
              <li class="nav-item"><a href="<?= $base ?>data_pelanggan/data_pelanggan_view"
                  class="nav-link<?= isActive('data_pelanggan_view') ?>"><i class="far fa-circle nav-icon"></i>
                  <p>Database Pelanggan</p>
                </a></li>
              <li class="nav-item"><a href="<?= $base ?>data_tujuan_perjalanan/data_tujuan_perjalanan_view"
                  class="nav-link<?= isActive('data_tujuan_perjalanan_view') ?>"><i
                    class="far fa-circle nav-icon"></i>
                  <p>Rute & Tujuan</p>
                </a></li>
              <li class="nav-item"><a href="<?= $base ?>data_travel/data_travel_view"
                  class="nav-link<?= isActive('data_travel_view') ?>"><i class="far fa-circle nav-icon"></i>
                  <p>Armada Kendaraan</p>
                </a></li>
              <li class="nav-item"><a href="<?= $base ?>data_jenis_barang/data_jenis_barang_view"
                  class="nav-link<?= isActive('data_jenis_barang_view') ?>"><i class="far fa-circle nav-icon"></i>
                  <p>Tarif Paket / Barang</p>
                </a></li>
              <li class="nav-item"><a href="<?= $base ?>data_metode_pembayaran/data_metode_pembayaran_view"
                  class="nav-link<?= isActive('data_metode_pembayaran_view') ?>"><i
                    class="far fa-circle nav-icon"></i>
                  <p>Metode Bayar</p>
                </a></li>
              <li class="nav-item"><a href="<?= $base ?>data_rekening/data_rekening_view"
                  class="nav-link<?= isActive('data_rekening_view') ?>"><i class="far fa-circle nav-icon"></i>
                  <p>Rekening Kas & Bank</p>
                </a></li>
              <li class="nav-item"><a href="<?= $base ?>data_status_pembayaran/data_status_pembayaran_view"
                  class="nav-link<?= isActive('data_status_pembayaran_view') ?>"><i
                    class="far fa-circle nav-icon"></i>
                  <p>Status Pembayaran</p>
                </a></li>
              <li class="nav-item"><a href="<?= $base ?>data_jenis_pengeluaran/data_jenis_pengeluaran_view"
                  class="nav-link<?= isActive('data_jenis_pengeluaran_view') ?>"><i
                    class="far fa-circle nav-icon"></i>
                  <p>Kategori Pengeluaran</p>
                </a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a href="<?= $base ?>data_pelanggan/data_pelanggan_view"
              class="nav-link<?= isActive('data_pelanggan_view') ?>">
              <i class="nav-icon fas fa-users"></i>
              <p>Database Pelanggan</p>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($role !== 'admin'): ?>
          <li class="nav-header">LAPORAN & REKAP</li>
          <li class="nav-item"><a href="<?= $base ?>laporan/daftar_keberangkatan"
              class="nav-link<?= isActive('daftar_keberangkatan') ?>"><i class="nav-icon fas fa-list"></i>
              <p>Manifes Keberangkatan</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/daftar_keberangkatan_all"
              class="nav-link<?= isActive('daftar_keberangkatan_all') ?>"><i class="nav-icon fas fa-list-check"></i>
              <p>Manifes Semua Cabang</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/pendapatan_travel"
              class="nav-link<?= isActive('pendapatan_travel') ?>"><i class="nav-icon fas fa-bus"></i>
              <p>Pendapatan per Armada</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/pendapatan_lokasi"
              class="nav-link<?= isActive('pendapatan_lokasi') ?>"><i class="nav-icon fas fa-location-dot"></i>
              <p>Pendapatan per Rute</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/pendapatan_bank"
              class="nav-link<?= isActive('pendapatan_bank') ?>"><i class="nav-icon fas fa-building-columns"></i>
              <p>Pendapatan Kas & Bank</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/pendapatan_user"
              class="nav-link<?= isActive('pendapatan_user') ?>"><i class="nav-icon fas fa-user"></i>
              <p>Pendapatan per Kasir</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/pendapatan_user_online"
              class="nav-link<?= isActive('pendapatan_user_online') ?>"><i class="nav-icon fas fa-user-clock"></i>
              <p>Status Kasir Online</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/pengeluaran_travel"
              class="nav-link<?= isActive('pengeluaran_travel') ?>"><i class="nav-icon fas fa-arrow-trend-down"></i>
              <p>Pengeluaran Armada</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/pengeluaran_kantor"
              class="nav-link<?= isActive('pengeluaran_kantor') ?>"><i class="nav-icon fas fa-building"></i>
              <p>Pengeluaran Operasional</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/pengeluaran_bank"
              class="nav-link<?= isActive('pengeluaran_bank') ?>"><i class="nav-icon fas fa-sack-dollar"></i>
              <p>Pengeluaran Kas & Bank</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/rekap_keuangan"
              class="nav-link<?= isActive('rekap_keuangan') ?>"><i class="nav-icon fas fa-chart-line"></i>
              <p>Rekapitulasi Arus Kas</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>laporan/jurnal_keuangan"
              class="nav-link<?= isActive('jurnal_keuangan') ?>"><i class="nav-icon fas fa-file-invoice"></i>
              <p>Jurnal Pembukuan</p>
            </a></li>
        <?php endif; ?>

        <?php if ($role === 'super admin'): ?>
          <li class="nav-header">KEUANGAN & USER</li>
          <li class="nav-item"><a href="<?= $base ?>data_users/data_users_view"
              class="nav-link<?= isActive('data_users_view') ?>"><i class="nav-icon fas fa-users"></i>
              <p>Manajemen Pengguna</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>data_saldo_awal/data_saldo_awal_view"
              class="nav-link<?= isActive('data_saldo_awal_view') ?>"><i class="nav-icon fas fa-wallet"></i>
              <p>Saldo Awal Kas & Bank</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>data_transfer/internal_transfer_view"
              class="nav-link<?= isActive('internal_transfer_view') ?>"><i class="nav-icon fas fa-right-left"></i>
              <p>Mutasi Antar Rekening</p>
            </a></li>

          <li class="nav-header">SISTEM & LOG</li>
          <li class="nav-item"><a href="<?= $base ?>data_activity_log/activity_log_view"
              class="nav-link<?= isActive('activity_log_view') ?>"><i class="nav-icon fas fa-clipboard-list"></i>
              <p>Log Aktivitas</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>data_activity_log/login_history"
              class="nav-link<?= isActive('login_history') ?>"><i class="nav-icon fas fa-clock"></i>
              <p>Riwayat Masuk Sesi</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>data_activity_log/backup_logs"
              class="nav-link<?= isActive('backup_logs') ?>"><i class="nav-icon fas fa-database"></i>
              <p>Pencadangan Log</p>
            </a></li>
          <li class="nav-item"><a href="<?= $base ?>galaxy_drive/index"
              class="nav-link<?= isActive('index') ?>"><i class="nav-icon fas fa-cloud"></i>
              <p>Galaxy Drive</p>
            </a></li>
          <li class="nav-item"><a href="#" id="btnClearCache" class="nav-link"><i class="nav-icon fas fa-broom"></i>
              <p>Bersihkan Cache</p>
            </a></li>
        <?php endif; ?>

        <?php if (in_array($role, ['super admin', 'admin'])): ?>
          <li class="nav-header">PENGATURAN</li>
          <li class="nav-item"><a href="<?= $base ?>pengaturan/setting_aplikasi"
              class="nav-link<?= isActive('setting_aplikasi') ?>"><i class="nav-icon fas fa-sliders-h text-success"></i>
              <p>Identitas & Logo Web</p>
            </a></li>
        <?php endif; ?>

        <li class="nav-item mt-3 pt-2 mb-3" style="border-top: 1px solid #e0e0e0;">
          <a href="<?= $base ?>logout" class="nav-link text-danger" style="font-weight: 600;">
            <i class="nav-icon fas fa-sign-out-alt"></i>
            <p>Keluar Sistem</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>

<?php if ($role === 'super admin'): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var btn = document.getElementById('btnClearCache');
      if (!btn) return;
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!window.Swal) { return window.location.href = '<?= $base ?>assets/tools/clear_server_cache'; }
        Swal.fire({
          title: 'Bersihkan cache server?',
          text: 'OPcache/APCu/realpath cache akan dibersihkan.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Ya, bersihkan',
          cancelButtonText: 'Batal'
        }).then(function (res) {
          if (!res.isConfirmed) return;
          fetch('<?= $base ?>assets/tools/clear_server_cache', { method: 'POST' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              Swal.fire('Selesai', 'Cache server dibersihkan', 'success');
            })
            .catch(function () { Swal.fire('Gagal', 'Tidak dapat membersihkan cache', 'error'); });
        });
      });
    });
  </script>
<?php endif; ?>