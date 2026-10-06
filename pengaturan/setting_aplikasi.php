<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Ambil data user dari session
$user = $_SESSION['user'] ?? [];
$id_users = $user['id_users'] ?? 0;
$asal_po = $user['asal_po'] ?? 'Sumbawa';
$role = strtolower($user['role'] ?? 'users');

// Hanya admin dan super admin yang berhak mengakses pengaturan sistem
if (!in_array($role, ['super admin', 'admin'])) {
    header('Location: ../data_dashboard/dashboard');
    exit;
}

$pesan = '';
$pesan_tipe = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pengaturan'])) {
    $app_name = trim($_POST['app_name'] ?? '');
    $app_tagline = trim($_POST['app_tagline'] ?? '');
    
    if (!empty($app_name)) {
        updateSetting('app_name', $app_name, $id_users);
    }
    if (!empty($app_tagline)) {
        updateSetting('app_tagline', $app_tagline, $id_users);
    }

    // Reset to default flags
    if (isset($_POST['reset_logo']) && $_POST['reset_logo'] == '1') {
        updateSetting('app_logo', 'img/logo_sgt.png', $id_users);
    }
    if (isset($_POST['reset_favicon']) && $_POST['reset_favicon'] == '1') {
        updateSetting('app_favicon', 'img/logo_sgt.png', $id_users);
    }

    // Direktori upload
    $upload_dir = __DIR__ . '/../img/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    // Handle Upload Logo
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
        
        if (in_array($ext, $allowed_exts)) {
            if ($file['size'] <= 2 * 1024 * 1024) { // Max 2MB
                $new_filename = 'logo_custom_' . time() . '.' . $ext;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    updateSetting('app_logo', 'img/' . $new_filename, $id_users);
                } else {
                    $pesan = 'Gagal memindahkan file logo ke direktori penyimpanan.';
                    $pesan_tipe = 'danger';
                }
            } else {
                $pesan = 'Ukuran file logo terlalu besar. Maksimal 2 MB.';
                $pesan_tipe = 'danger';
            }
        } else {
            $pesan = 'Format file logo tidak didukung. Gunakan PNG, JPG, WEBP, atau SVG.';
            $pesan_tipe = 'danger';
        }
    }

    // Handle Upload Favicon
    if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['favicon_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['ico', 'png', 'svg', 'jpg', 'jpeg'];
        
        if (in_array($ext, $allowed_exts)) {
            if ($file['size'] <= 1 * 1024 * 1024) { // Max 1MB
                $new_filename = 'favicon_custom_' . time() . '.' . $ext;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    updateSetting('app_favicon', 'img/' . $new_filename, $id_users);
                } else {
                    $pesan = 'Gagal memindahkan file favicon ke direktori penyimpanan.';
                    $pesan_tipe = 'danger';
                }
            } else {
                $pesan = 'Ukuran file favicon terlalu besar. Maksimal 1 MB.';
                $pesan_tipe = 'danger';
            }
        } else {
            $pesan = 'Format file favicon tidak didukung. Gunakan ICO, PNG, atau SVG.';
            $pesan_tipe = 'danger';
        }
    }

    if (empty($pesan)) {
        $pesan = 'Pengaturan identitas website, logo, dan favicon berhasil diperbarui!';
        $pesan_tipe = 'success';
    }
}

// Ambil nilai setting terkini
$current_logo = getSetting('app_logo', 'img/logo_sgt.png');
$current_favicon = getSetting('app_favicon', 'img/logo_sgt.png');
$current_app_name = getSetting('app_name', 'Sinar Galaxy');
$current_tagline = getSetting('app_tagline', 'Travel Eksekutif Sumbawa - Mataram');

// Base prefix untuk include
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
              <i class="fas fa-sliders-h"></i>
            </div>
            <div>
              <h1 class="m-0 text-dark" style="font-size: 1.3rem; font-weight: 700;">Pengaturan Logo & Identitas Website</h1>
              <p class="text-muted m-0" style="font-size: 0.82rem;">
                Kustomisasi logo utama, favicon browser, dan nama brand Sinar Galaxy Travel
              </p>
            </div>
          </div>
        </div>
        <div class="col-sm-5 text-right">
          <a href="../index" target="_blank" class="btn btn-sm btn-outline-success font-weight-bold">
            <i class="fas fa-external-link-alt mr-1"></i> Lihat Landing Page
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">

      <?php if (!empty($pesan)): ?>
        <div class="alert alert-<?= $pesan_tipe ?> alert-dismissible fade show border shadow-none mb-3" role="alert">
          <i class="fas <?= $pesan_tipe === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-2"></i>
          <strong><?= $pesan ?></strong>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" action="setting_aplikasi">
        <div class="row">
          
          <!-- KOLOM KIRI: UPLOAD LOGO & FAVICON -->
          <div class="col-lg-7 col-md-12 mb-3">
            
            <!-- KARTU LOGO UTAMA -->
            <div class="card border mb-3">
              <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-sm text-dark">
                  <i class="fas fa-image text-success mr-2"></i>Logo Utama Website & Aplikasi
                </span>
                <span class="badge badge-light border text-muted">Format: PNG, JPG, WEBP, SVG</span>
              </div>
              <div class="card-body p-3">
                <div class="row align-items-center">
                  <div class="col-sm-4 text-center mb-3 mb-sm-0">
                    <div class="p-3 border rounded bg-light d-inline-block text-center" style="min-width: 140px;">
                      <img src="../<?= htmlspecialchars($current_logo) ?>?v=<?= time() ?>" id="previewLogo" alt="Logo Saat Ini" style="max-height: 80px; max-width: 100%; object-fit: contain;">
                      <small class="d-block text-muted mt-2 font-weight-bold" style="font-size: 0.72rem;">Logo Aktif</small>
                    </div>
                  </div>
                  <div class="col-sm-8">
                    <label class="font-weight-bold text-xs text-muted mb-1" for="logo_file">
                      PILIH FILE LOGO BARU
                    </label>
                    <div class="custom-file mb-2">
                      <input type="file" class="custom-file-input" name="logo_file" id="logo_file" accept=".png,.jpg,.jpeg,.webp,.svg" onchange="previewImage(this, 'previewLogo')">
                      <label class="custom-file-label" for="logo_file" id="logo_file_label" style="font-size: 13px;">Pilih gambar logo...</label>
                    </div>
                    <small class="text-muted d-block" style="font-size: 0.78rem;">
                      <i class="fas fa-info-circle text-info mr-1"></i> Disarankan menggunakan format PNG transparan dengan resolusi minimal 200×200 pixel (Maks. 2 MB).
                    </small>
                    <div class="custom-control custom-checkbox mt-2">
                      <input type="checkbox" class="custom-control-input" id="reset_logo" name="reset_logo" value="1">
                      <label class="custom-control-label text-xs text-secondary" for="reset_logo">
                        Kembalikan ke Logo Bawaan SGT (logo_sgt.png)
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- KARTU FAVICON BROWSER -->
            <div class="card border">
              <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-sm text-dark">
                  <i class="fas fa-globe text-success mr-2"></i>Favicon (Ikon Tab Browser)
                </span>
                <span class="badge badge-light border text-muted">Format: ICO, PNG, SVG</span>
              </div>
              <div class="card-body p-3">
                <div class="row align-items-center">
                  <div class="col-sm-4 text-center mb-3 mb-sm-0">
                    <!-- Browser Tab Mockup -->
                    <div class="border rounded bg-light p-2 d-inline-block text-left" style="width: 140px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                      <div class="d-flex align-items-center px-1 py-1 rounded bg-white border" style="font-size: 0.72rem;">
                        <img src="../<?= htmlspecialchars($current_favicon) ?>?v=<?= time() ?>" id="previewFavicon" alt="Favicon" style="width: 16px; height: 16px; object-fit: contain; margin-right: 5px;">
                        <span class="text-truncate font-weight-bold text-dark" style="max-width: 90px;"><?= htmlspecialchars($current_app_name) ?></span>
                      </div>
                      <small class="d-block text-center text-muted mt-2 font-weight-bold" style="font-size: 0.7rem;">Pratinjau Tab</small>
                    </div>
                  </div>
                  <div class="col-sm-8">
                    <label class="font-weight-bold text-xs text-muted mb-1" for="favicon_file">
                      PILIH FILE FAVICON BARU
                    </label>
                    <div class="custom-file mb-2">
                      <input type="file" class="custom-file-input" name="favicon_file" id="favicon_file" accept=".ico,.png,.svg,.jpg,.jpeg" onchange="previewImage(this, 'previewFavicon')">
                      <label class="custom-file-label" for="favicon_file" id="favicon_file_label" style="font-size: 13px;">Pilih icon tab...</label>
                    </div>
                    <small class="text-muted d-block" style="font-size: 0.78rem;">
                      <i class="fas fa-info-circle text-info mr-1"></i> Ikon kecil yang muncul di tab browser pengguna. Disarankan rasio 1:1 persegi (Maks. 1 MB).
                    </small>
                    <div class="custom-control custom-checkbox mt-2">
                      <input type="checkbox" class="custom-control-input" id="reset_favicon" name="reset_favicon" value="1">
                      <label class="custom-control-label text-xs text-secondary" for="reset_favicon">
                        Kembalikan ke Favicon Bawaan SGT
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- KOLOM KANAN: IDENTITAS BRAND & SIMPAN -->
          <div class="col-lg-5 col-md-12 mb-3">
            
            <div class="card border mb-3">
              <div class="card-header bg-white py-2 px-3 border-bottom">
                <span class="font-weight-bold text-sm text-dark">
                  <i class="fas fa-tag text-success mr-2"></i>Identitas & Nama Brand
                </span>
              </div>
              <div class="card-body p-3">
                
                <div class="form-group mb-3">
                  <label class="font-weight-bold text-xs text-muted mb-1" for="app_name">
                    NAMA BRAND / TRAVEL <span class="text-danger">*</span>
                  </label>
                  <input type="text" class="form-control form-control-sm font-weight-bold" name="app_name" id="app_name" value="<?= htmlspecialchars($current_app_name) ?>" required>
                  <small class="text-muted" style="font-size: 0.75rem;">Ditampilkan pada sidebar, navbar, dan header kwitansi.</small>
                </div>

                <div class="form-group mb-3">
                  <label class="font-weight-bold text-xs text-muted mb-1" for="app_tagline">
                    TAGLINE / SLOGAN UTAMA
                  </label>
                  <input type="text" class="form-control form-control-sm" name="app_tagline" id="app_tagline" value="<?= htmlspecialchars($current_tagline) ?>">
                  <small class="text-muted" style="font-size: 0.75rem;">Ditampilkan di bawah judul atau footer web.</small>
                </div>

                <hr class="my-3">

                <div class="p-2 bg-light border rounded mb-3 text-xs text-muted">
                  <i class="fas fa-shield-alt text-success mr-1"></i>
                  Perubahan logo dan favicon langsung berdampak secara otomatis ke:
                  <ul class="mb-0 pl-3 mt-1">
                    <li>Halaman Utama (Landing Page publik)</li>
                    <li>Halaman Login Sistem</li>
                    <li>Header & Sidebar Seluruh Halaman Dasbor</li>
                    <li>Kwitansi Pembayaran & Kartu Tiket</li>
                  </ul>
                </div>

                <button type="submit" name="simpan_pengaturan" class="btn btn-success btn-block font-weight-bold py-2 shadow-none">
                  <i class="fas fa-save mr-1"></i> Simpan Perubahan Identitas
                </button>

              </div>
            </div>

          </div>

        </div>
      </form>

    </div>
  </section>
</div>

<?php require_once '../inc/footer.php'; ?>

<script>
function previewImage(input, previewId) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById(previewId).src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
    
    // Update label input
    var labelId = input.id + '_label';
    var labelElem = document.getElementById(labelId);
    if (labelElem) {
      labelElem.innerText = input.files[0].name;
    }
  }
}
</script>
