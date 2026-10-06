<?php
if (!isset($base)) {
  $base = is_file('data_dashboard/dashboard.php') ? '' : '../';
}
$footer_logo = function_exists('getSetting') ? getSetting('app_logo', 'img/logo_sgt.png') : 'img/logo_sgt.png';
$footer_name = function_exists('getSetting') ? getSetting('app_name', 'Sinar Galaxy') : 'Sinar Galaxy';
?>
<!-- /.content-wrapper -->

  <!-- Main Footer (Paper White Design System v2.0) -->
  <footer class="main-footer">
    <div class="footer-inner d-flex flex-wrap justify-content-between align-items-center">
      <!-- Left: Brand & Copyright -->
      <div class="footer-left d-flex align-items-center my-1">
        <div class="footer-brand mr-2">
          <img src="<?= $base . htmlspecialchars($footer_logo) ?>" alt="<?= htmlspecialchars($footer_name) ?> Logo" class="footer-logo" onerror="this.style.display='none'">
        </div>
        <div class="footer-text">
          <strong class="text-dark">&copy; <?= date('Y') ?> PO. <?= htmlspecialchars($footer_name) ?> Travel.</strong>
        </div>
      </div>

      <!-- Right: System Version & Live Status -->
      <div class="footer-right d-flex align-items-center my-1">
        <span class="badge-status-pill mr-2">
          <i class="fas fa-shield-alt text-primary mr-1"></i> SIM-SGT <strong class="ml-1">v3.2.0</strong>
        </span>
        <span class="badge-status-pill badge-status-online" title="Sistem Aktif & Terhubung">
          <span class="pulse-indicator mr-1"></span> Online
        </span>
      </div>
    </div>
  </footer>
</div>
<!-- ./wrapper -->

<style>
/* Paper White Main Footer - Presisi Sempurna dengan Sidebar */
.main-footer {
  background-color: #ffffff !important;
  color: #475569 !important;
  border-top: 1px solid #b8b8b8 !important;
  border-left: none !important;
  border-right: none !important;
  border-bottom: none !important;
  box-shadow: none !important;
  padding: 0.75rem 18px !important;
  font-size: 0.85rem !important;
  box-sizing: border-box !important;
  transition: margin-left 0.3s ease-in-out !important;
}

/* Presisi Desktop: Selaras tepat dengan batas kanan sidebar (250px) */
@media (min-width: 768px) {
  .main-footer {
    margin-left: 250px !important;
  }
  body.sidebar-collapse .main-footer {
    margin-left: 4.6rem !important;
  }
}

.footer-inner {
  width: 100%;
}

.footer-brand {
  display: flex;
  align-items: center;
}

.footer-logo {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 2px rgba(0,0,0,0.06);
  transition: transform 0.2s ease;
}

.footer-logo:hover {
  transform: scale(1.08);
}

.footer-text {
  font-size: 0.84rem;
  line-height: 1.4;
}

.footer-text strong,
.footer-text .text-dark {
  color: #1e293b !important;
  font-weight: 600;
}

.footer-text .text-muted {
  color: #64748b !important;
}

/* Status Badges Paper White */
.badge-status-pill {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.65rem;
  font-size: 0.75rem;
  font-weight: 500;
  border-radius: 4px;
  background-color: #f8fafc;
  color: #334155;
  border: 1px solid #cbd5e1;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.badge-status-pill:hover {
  background-color: #f1f5f9;
  border-color: #94a3b8;
}

.badge-status-online {
  background-color: #f0fdf4;
  color: #166534;
  border-color: #bbf7d0;
  font-weight: 600;
}

.badge-status-online:hover {
  background-color: #dcfce7;
  border-color: #86efac;
}

/* Pulsing Online Dot */
.pulse-indicator {
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background-color: #22c55e;
  box-shadow: 0 0 0 rgba(34, 197, 94, 0.4);
  animation: footerPulseRing 2s infinite;
}

@keyframes footerPulseRing {
  0% {
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
  }
  70% {
    box-shadow: 0 0 0 5px rgba(34, 197, 94, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
  }
}

/* Responsive adjustment */
@media (max-width: 767.98px) {
  .main-footer {
    margin-left: 0 !important;
    padding: 0.75rem 1rem !important;
  }
  .footer-inner {
    flex-direction: column;
    text-align: center;
    gap: 0.5rem;
  }
  .footer-left {
    justify-content: center;
  }
  .footer-right {
    justify-content: center;
  }
}

/* Sembunyikan Footer Saat Cetak */
@media print {
  .main-footer,
  footer.main-footer {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
  }
}
</style>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- OverlayScrollbars -->
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.4.0/js/OverlayScrollbars.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- DateRangePicker -->
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
<!-- Bootstrap Colorpicker -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap-colorpicker@3.4.0/dist/js/bootstrap-colorpicker.min.js"></script>
<!-- Bootstrap Slider -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap-slider@11.0.2/dist/bootstrap-slider.min.js"></script>
<!-- DataTables & Plugins -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>

<!-- JavaScript untuk Menu Aktif dan Interaksi UI -->
<script>
// Anti-Cache: Matikan seluruh browser cache untuk request AJAX di semua modul
if (typeof $ !== 'undefined') {
  $.ajaxSetup({
    cache: false,
    headers: {
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache'
    }
  });
}

// Auto-Purge Browser Cache API jika versi sistem diperbarui
(function() {
  var currentSysVer = '3.3.0';
  if (typeof window !== 'undefined' && window.localStorage) {
    var storedVer = localStorage.getItem('sgt_sys_ver');
    if (storedVer !== currentSysVer) {
      localStorage.setItem('sgt_sys_ver', currentSysVer);
      if ('caches' in window) {
        caches.keys().then(function(names) {
          for (var name of names) {
            caches.delete(name);
          }
        });
      }
    }
  }
})();

$(document).ready(function() {
  // Inisialisasi treeview AdminLTE secara aman
  if (typeof $.fn.Treeview !== 'undefined' && $('[data-widget="treeview"]').length) {
    try {
      $('[data-widget="treeview"]').Treeview({
        animationSpeed: 250,
        accordion: true,
        expandSidebar: false,
        sidebarButtonSelector: '[data-widget="pushmenu"]',
        trigger: '.nav-link',
        expandOnHover: false
      });
    } catch(e) {
      // Treeview fallback handled by AdminLTE data-api
    }
  }

  // Pastikan tombol pushmenu bekerja pada semua halaman
  $(document).on('click', '[data-widget="pushmenu"]', function(e){ e.preventDefault(); });
  
  // Efek transisi menu treeview
  $('.nav-treeview').css({
    'transition': 'max-height 0.3s ease, opacity 0.3s ease',
    'overflow': 'hidden'
  });
  
  // Inisialisasi Select2 jika elemen tersedia
  if (typeof $.fn.select2 !== 'undefined' && $('.select2').length) {
    $('.select2').select2({
      theme: 'bootstrap-5',
      width: '100%',
      placeholder: 'Pilih opsi...',
      allowClear: true,
      dropdownCssClass: 'select2-dropdown-animated'
    });
  }
  
  // Inisialisasi DateRangePicker jika elemen tersedia
  if (typeof $.fn.daterangepicker !== 'undefined' && $('.daterange').length) {
    $('.daterange').daterangepicker({
      locale: {
        format: 'DD/MM/YYYY',
        applyLabel: 'Terapkan',
        cancelLabel: 'Batal',
        fromLabel: 'Dari',
        toLabel: 'Sampai'
      },
      autoApply: false,
      showDropdowns: true
    });
  }
  
  // Inisialisasi Colorpicker jika ada
  if (typeof $.fn.colorpicker !== 'undefined' && $('.colorpicker').length) {
    $('.colorpicker').colorpicker();
  }
  
  // Inisialisasi Slider jika ada
  if (typeof $.fn.slider !== 'undefined' && $('.slider').length) {
    $('.slider').slider();
  }
  
  // Deteksi menu aktif pada sidebar
  var currentUrl = window.location.pathname;
  var currentFile = currentUrl.split('/').pop();

  $('.nav-sidebar .nav-link').each(function() {
    var linkUrl = $(this).attr('href');
    if (linkUrl) {
      var linkFile = linkUrl.split('/').pop();
      if (currentFile && linkFile && currentFile === linkFile) {
        $(this).addClass('active');
        $(this).closest('.has-treeview').addClass('menu-open');
        $(this).closest('.has-treeview').find('> .nav-link').addClass('active');
        $(this).append('<span class="active-indicator"></span>');
      }
    }
  });
  
  // Ripple effect saat klik menu dengan event pointer yang aman
  $('.nav-sidebar .nav-link').on('click', function(e) {
    $('.nav-sidebar .nav-link').removeClass('clicked');
    $(this).addClass('clicked');
    
    var posX = (e && typeof e.offsetX !== 'undefined') ? e.offsetX : 10;
    var posY = (e && typeof e.offsetY !== 'undefined') ? e.offsetY : 10;
    
    var ripple = $('<span class="nav-link-ripple"></span>');
    ripple.css({
      'position': 'absolute',
      'top': posY + 'px',
      'left': posX + 'px',
      'background-color': 'rgba(231, 74, 59, 0.2)',
      'border-radius': '50%',
      'transform': 'scale(0)',
      'animation': 'ripple 0.6s linear',
      'pointer-events': 'none'
    });
    
    $(this).append(ripple);
    setTimeout(function() {
      ripple.remove();
    }, 600);
  });
  
  // Update tahun otomatis di footer jika diperlukan
  const currentYear = new Date().getFullYear();
  $('.footer-text strong, .footer-text .text-dark').html(`&copy; ${currentYear} PO. Sinar Galaxy Travel.`);
});
</script>

<style>
.active-indicator {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #28a745;
  box-shadow: 0 0 5px rgba(40,167,69,0.6);
}
@keyframes ripple {
  to {
    transform: scale(4);
    opacity: 0;
  }
}
.nav-link-ripple {
  width: 10px;
  height: 10px;
  opacity: 1;
}
</style>

<!-- Custom JS Modul (Otomatis Di-refresh oleh Auto Cache Buster) -->
<script src="<?= function_exists('asset_ver') ? asset_ver('data_status_pembayaran/data_status_pembayaran.js', $base) : $base . 'data_status_pembayaran/data_status_pembayaran.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_metode_pembayaran/data_metode_pembayaran.js', $base) : $base . 'data_metode_pembayaran/data_metode_pembayaran.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_jenis_pengeluaran/data_jenis_pengeluaran.js', $base) : $base . 'data_jenis_pengeluaran/data_jenis_pengeluaran.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_jenis_barang/data_jenis_barang.js', $base) : $base . 'data_jenis_barang/data_jenis_barang.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_rekening/data_rekening.js', $base) : $base . 'data_rekening/data_rekening.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_travel/data_travel.js', $base) : $base . 'data_travel/data_travel.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_tujuan_perjalanan/data_tujuan_perjalanan.js', $base) : $base . 'data_tujuan_perjalanan/data_tujuan_perjalanan.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_users/data_users.js', $base) : $base . 'data_users/data_users.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_pelanggan/data_pelanggan.js', $base) : $base . 'data_pelanggan/data_pelanggan.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_pemesanan/data_pemesanan.js', $base) : $base . 'data_pemesanan/data_pemesanan.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_pengiriman/data_pengiriman.js', $base) : $base . 'data_pengiriman/data_pengiriman.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_pengeluaran/data_pengeluaran.js', $base) : $base . 'data_pengeluaran/data_pengeluaran.js?v=3.3.0' ?>"></script>
<script src="<?= function_exists('asset_ver') ? asset_ver('data_saldo_awal/data_saldo_awal.js', $base) : $base . 'data_saldo_awal/data_saldo_awal.js?v=3.3.0' ?>"></script>
<!-- Universal Page Preloader Script -->
<script src="<?= function_exists('asset_ver') ? asset_ver('assets/js/preloader.js', $base) : $base . 'assets/js/preloader.js?v=3.3.0' ?>"></script>
</body>
</html>