  <!-- /.content-wrapper -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline-block">
      <div class="d-flex align-items-center">
        <span class="badge badge-primary mr-2">
          <i class="fas fa-code"></i> v3.2.0
        </span>
        <span class="badge badge-success">
          <i class="fas fa-circle"></i> Online
        </span>
      </div>
    </div>
    <!-- Default to the left -->
    <div class="d-flex align-items-center">
      <div class="footer-brand mr-3">
        <?php
        // Deteksi path untuk logo
        $current_path = $_SERVER['PHP_SELF'];
        $logo_path = '';
        
        if (strpos($current_path, '/inc/') !== false) {
            // Jika di folder inc
            $logo_path = '../img/logo_sgt.png';
        } elseif (strpos($current_path, '/profile/') !== false) {
            // Jika di folder profile
            $logo_path = '../img/logo_sgt.png';
        } elseif (strpos($current_path, '/data_') !== false) {
            // Jika di folder data_*
            $logo_path = '../img/logo_sgt.png';
        } elseif (strpos($current_path, '/laporan/') !== false) {
            // Jika di folder laporan
            $logo_path = '../img/logo_sgt.png';
        } else {
            // Jika di root
            $logo_path = 'img/logo_sgt.png';
        }
        ?>
        <img src="<?= $logo_path ?>" alt="Galaxy Travel" class="footer-logo" onerror="this.style.display='none'">
      </div>
      <div class="footer-text">
        <strong>&copy; <?= date('Y') ?> Galaxy Travel Management System.</strong>
        <span class="text-muted ml-2">All rights reserved.</span>
      </div>
    </div>
  </footer>
</div>
<!-- ./wrapper -->

<style>
.main-footer {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-top: none;
  padding: 1rem 1.5rem;
  box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
  position: relative;
  overflow: hidden;
}

.main-footer::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
  opacity: 0.3;
  pointer-events: none;
}

.footer-brand {
  display: flex;
  align-items: center;
  position: relative;
  z-index: 1;
}

.footer-logo {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  margin-right: 0.5rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
  transition: transform 0.3s ease;
}

.footer-logo:hover {
  transform: scale(1.1);
}

.footer-text {
  font-size: 0.9rem;
  position: relative;
  z-index: 1;
}

.footer-text strong {
  color: white;
  font-weight: 600;
}

.footer-text .text-muted {
  color: rgba(255,255,255,0.8) !important;
}

.badge {
  font-size: 0.75rem;
  padding: 0.375rem 0.75rem;
  border-radius: 20px;
  font-weight: 500;
  position: relative;
  z-index: 1;
  transition: all 0.3s ease;
}

.badge-primary {
  background: rgba(255,255,255,0.2);
  color: white;
  border: 1px solid rgba(255,255,255,0.3);
  backdrop-filter: blur(10px);
}

.badge-primary:hover {
  background: rgba(255,255,255,0.3);
  transform: translateY(-1px);
}

.badge-success {
  background: rgba(40, 167, 69, 0.8);
  color: white;
  border: 1px solid rgba(40, 167, 69, 0.9);
  backdrop-filter: blur(10px);
}

.badge-success:hover {
  background: rgba(40, 167, 69, 0.9);
  transform: translateY(-1px);
}

@media (max-width: 768px) {
  .main-footer {
    text-align: center;
    padding: 1rem;
  }
  
  .float-right {
    float: none !important;
    margin-top: 0.5rem;
  }
  
  .footer-brand {
    justify-content: center;
    margin-bottom: 0.5rem;
  }
  
  .footer-text {
    text-align: center;
  }
  
  .badge {
    margin: 0.25rem;
  }
}

/* Animasi untuk badge online */
@keyframes pulse {
  0% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.7; transform: scale(1.05); }
  100% { opacity: 1; transform: scale(1); }
}

.badge-success i {
  animation: pulse 2s infinite;
}

/* Hover effect untuk footer */
.main-footer:hover {
  background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
  transition: background 0.3s ease;
}

.main-footer:hover::before {
  opacity: 0.4;
  transition: opacity 0.3s ease;
}

/* Loading animation untuk logo */
@keyframes logoGlow {
  0% { box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
  50% { box-shadow: 0 2px 8px rgba(255,255,255,0.3); }
  100% { box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
}

.footer-logo {
  animation: logoGlow 3s infinite;
}

/* Responsive improvements */
@media (max-width: 576px) {
  .main-footer {
    padding: 0.75rem;
  }
  
  .footer-text {
    font-size: 0.8rem;
  }
  
  .badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
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

<!-- JavaScript untuk Menu Aktif -->
<script>
$(document).ready(function() {
  // Inisialisasi treeview AdminLTE
  $('[data-widget="treeview"]').Treeview('init');
  
  // Inisialisasi Select2
  $('.select2').select2({
    theme: 'bootstrap-5'
  });
  
  // Inisialisasi DateRangePicker
  $('.daterange').daterangepicker({
    locale: {
      format: 'DD/MM/YYYY'
    }
  });
  
  // Inisialisasi Colorpicker
  $('.colorpicker').colorpicker();
  
  // Inisialisasi Bootstrap Slider
  $('.slider').slider();
  
  // Tambahkan efek hover untuk menu
  $('.nav-sidebar .nav-link').hover(
    function() {
      if (!$(this).hasClass('active')) {
        $(this).css('background-color', 'rgba(255, 255, 255, 0.1)');
      }
    },
    function() {
      if (!$(this).hasClass('active')) {
        $(this).css('background-color', '');
      }
    }
  );
  
  // Log untuk debugging menu aktif
  console.log('Current page:', window.location.pathname);
  console.log('Active menu detected');
  
  // Footer animation
  $('.main-footer').hover(
    function() {
      $(this).find('.badge-success i').css('animation-duration', '1s');
      $(this).find('.footer-logo').css('animation-duration', '1s');
    },
    function() {
      $(this).find('.badge-success i').css('animation-duration', '2s');
      $(this).find('.footer-logo').css('animation-duration', '3s');
    }
  );
  
  // Auto update tahun copyright
  const currentYear = new Date().getFullYear();
  $('.footer-text strong').html(`&copy; ${currentYear} Galaxy Travel Management System.`);
  
  // Add loading effect to logo
  $('.footer-logo').on('load', function() {
    $(this).addClass('loaded');
  }).on('error', function() {
    $(this).hide();
  });
  
  // Smooth scroll to top when clicking on footer
  $('.main-footer').click(function() {
    $('html, body').animate({
      scrollTop: 0
    }, 800);
  });
});
</script>

<!-- Custom JS -->
<script src="../data_status_pembayaran/data_status_pembayaran.js"></script>
<script src="../data_metode_pembayaran/data_metode_pembayaran.js"></script>
<script src="../data_jenis_pengeluaran/data_jenis_pengeluaran.js"></script>
<script src="../data_rekening/data_rekening.js"></script>
<script src="../data_travel/data_travel.js"></script>
<script src="../data_tujuan_perjalanan/data_tujuan_perjalanan.js"></script>
<script src="../data_users/data_users.js"></script>
<script src="../data_pelanggan/data_pelanggan.js"></script>
<script src="../data_pemesanan/data_pemesanan.js"></script>
<script src="../data_pengiriman/data_pengiriman.js"></script>
<script src="../data_pengeluaran/data_pengeluaran.js"></script>
</body>
</html> 