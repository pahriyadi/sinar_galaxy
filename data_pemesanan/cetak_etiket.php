<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Ambil ID pemesanan (bisa single ID atau multiple IDs dipisah koma)
$id_pemesanan = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['id_pemesanan']) ? $_GET['id_pemesanan'] : '');
$no_plat_filter = isset($_GET['no_plat']) ? $_GET['no_plat'] : '';
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';

$tickets = [];

if (!empty($id_pemesanan)) {
    // Single or specific comma-separated IDs
    $id_list = array_map('intval', explode(',', $id_pemesanan));
    $id_in = implode(',', array_filter($id_list));
    if (!empty($id_in)) {
        $sql = "SELECT pm.*, 
                       dt.kelas, dt.jumlah_kursi,
                       tj.nama_tujuan,
                       sp.status_pembayaran,
                       mp.metode_pembayaran,
                       dr.nama_rekening, dr.no_rekening, dr.atas_nama,
                       du.username as nama_kasir, du.asal_po as asal_po_kasir
                FROM data_pemesanan pm
                LEFT JOIN data_travel dt ON pm.no_plat_id = dt.no_plat
                LEFT JOIN data_tujuan_perjalanan tj ON pm.tujuan_id = tj.id_tujuan_perjalanan
                LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
                LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
                LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
                LEFT JOIN data_users du ON pm.username = du.id_users
                WHERE pm.id_pemesanan IN ($id_in)
                ORDER BY pm.id_pemesanan ASC";
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $tickets[] = $row;
            }
        }
    }
} elseif (!empty($no_plat_filter) && !empty($tanggal_filter)) {
    // Filter by travel trip
    $plat_esc = mysqli_real_escape_string($conn, $no_plat_filter);
    $tgl_esc = mysqli_real_escape_string($conn, $tanggal_filter);
    $sql = "SELECT pm.*, 
                   dt.kelas, dt.jumlah_kursi,
                   tj.nama_tujuan,
                   sp.status_pembayaran,
                   mp.metode_pembayaran,
                   dr.nama_rekening, dr.no_rekening, dr.atas_nama,
                   du.username as nama_kasir, du.asal_po as asal_po_kasir
            FROM data_pemesanan pm
            LEFT JOIN data_travel dt ON pm.no_plat_id = dt.no_plat
            LEFT JOIN data_tujuan_perjalanan tj ON pm.tujuan_id = tj.id_tujuan_perjalanan
            LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
            LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
            LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
            LEFT JOIN data_users du ON pm.username = du.id_users
            WHERE pm.no_plat_id = '$plat_esc' AND DATE(pm.tanggal_berangkat) = '$tgl_esc'
            ORDER BY pm.kursi ASC, pm.id_pemesanan ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $tickets[] = $row;
        }
    }
}

// Master mapping jika dibutuhkan fallback
$tujuanMap = [];
$res = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
while ($r = mysqli_fetch_assoc($res)) { $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan']; }

$metodeMap = [];
$res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
while ($r = mysqli_fetch_assoc($res)) { $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran']; }

$rekeningMap = [];
$res = mysqli_query($conn, "SELECT id_rekening, nama_rekening, no_rekening, atas_nama FROM data_rekening");
while ($r = mysqli_fetch_assoc($res)) { $rekeningMap[$r['id_rekening']] = $r; }

// Logo paths
$appLogo = '../img/logo_sgt.png';
$busImage = '../img/travel_vip.jpg';
if (!file_exists(__DIR__ . '/' . $busImage)) {
    $busImage = '../img/travel_ekonomi.jpg';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Tiket Penumpang - Sinar Galaxy Travel</title>
  <link rel="icon" href="../img/logo_sgt.png" type="image/png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,600;0,700;0,800;1,700;1,800&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

  <style>
    /* ==========================================================================
       RESET & BASE SETUP
       ========================================================================== */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: #e9eef5;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1a202c;
      padding: 24px 12px;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    /* Action bar */
    .action-bar {
      max-width: 960px;
      margin: 0 auto 20px auto;
      background: #ffffff;
      padding: 12px 20px;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .action-btn {
      padding: 8px 18px;
      border-radius: 6px;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      border: none;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn-print {
      background: #0d6efd;
      color: #ffffff;
    }
    .btn-print:hover {
      background: #0b5ed7;
    }

    .btn-close-window {
      background: #6c757d;
      color: #ffffff;
    }
    .btn-close-window:hover {
      background: #5c636a;
    }

    /* Container Tiket Keseluruhan */
    .ticket-sheet {
      max-width: 960px;
      margin: 0 auto 30px auto;
      background: transparent;
      display: flex;
      flex-direction: column;
      gap: 16px;
      page-break-after: always;
    }

    .ticket-sheet:last-child {
      page-break-after: auto;
    }

    /* ==========================================================================
       SLIP 1: TIKET PENUMPANG (ATAS)
       ========================================================================== */
    .ticket-card-top {
      background: #ffffff;
      border: 2px solid #0a3871;
      border-radius: 16px;
      overflow: hidden;
      display: flex;
      box-shadow: 0 8px 24px rgba(10, 56, 113, 0.12);
      position: relative;
    }

    /* Sisi Kiri (Utama) */
    .ticket-main {
      flex: 1;
      padding: 16px 20px 0 20px;
      display: flex;
      flex-direction: column;
      background: #ffffff;
    }

    /* Header Tiket Utama */
    .ticket-main-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 8px;
      border-bottom: 1.5px solid #edf2f7;
    }

    .brand-group {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-logo-img {
      height: 48px;
      width: auto;
      object-fit: contain;
    }

    .brand-text {
      display: flex;
      flex-direction: column;
    }

    .brand-title {
      font-family: 'Montserrat', sans-serif;
      font-size: 20px;
      font-weight: 900;
      color: #0b3b7b;
      letter-spacing: 0.5px;
      line-height: 1.1;
    }

    .brand-subtitle {
      font-family: 'Montserrat', sans-serif;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 6px;
      color: #0b3b7b;
      margin-top: 1px;
      border-top: 1px solid #0b3b7b;
      border-bottom: 1px solid #0b3b7b;
      padding: 1px 0;
      text-align: center;
    }

    .brand-tagline {
      font-size: 11px;
      font-style: italic;
      color: #2d3748;
      font-weight: 600;
      margin-top: 3px;
    }

    /* Badge Header Kanan */
    .header-badge-container {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 4px;
    }

    .badge-ticket-title {
      background: #0b3b7b;
      color: #ffffff;
      font-family: 'Montserrat', sans-serif;
      font-size: 17px;
      font-weight: 900;
      padding: 6px 22px;
      border-radius: 24px;
      letter-spacing: 0.8px;
      box-shadow: 0 3px 8px rgba(11, 59, 123, 0.25);
      text-transform: uppercase;
    }

    .badge-bus-type {
      background: #ffb703;
      color: #000000;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 18px;
      border-radius: 14px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    /* Body Area (Bus Image + Form Data) */
    .ticket-main-body {
      display: flex;
      gap: 16px;
      padding: 14px 0 10px 0;
      align-items: stretch;
    }

    /* Visual Bus Sisi Kiri */
    .bus-visual-box {
      width: 280px;
      min-height: 175px;
      border-radius: 12px;
      overflow: hidden;
      position: relative;
      background: linear-gradient(135deg, #cde4ff 0%, #e2f0ff 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: inset 0 0 0 1px rgba(10, 56, 113, 0.15);
    }

    .bus-visual-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .bus-badge-floating {
      position: absolute;
      bottom: 8px;
      left: 8px;
      background: rgba(11, 59, 123, 0.88);
      color: #ffffff;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 700;
      backdrop-filter: blur(2px);
    }

    /* Form Fields Sisi Kanan */
    .ticket-fields-grid {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 6px;
      justify-content: center;
    }

    .ticket-field-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .field-icon-bubble {
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: #0b3b7b;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      flex-shrink: 0;
    }

    .field-label {
      width: 145px;
      font-size: 12px;
      font-weight: 700;
      color: #1a202c;
      flex-shrink: 0;
    }

    .field-separator {
      font-weight: 700;
      color: #1a202c;
      margin-right: 4px;
    }

    .field-value-box {
      flex: 1;
      background: #ffffff;
      border: 1.5px solid #a2c1e8;
      border-radius: 7px;
      padding: 3.5px 10px;
      font-size: 12px;
      font-weight: 700;
      color: #0b3b7b;
      min-height: 26px;
      display: flex;
      align-items: center;
    }

    .field-value-box.price-highlight {
      color: #0d8a43;
      font-size: 13px;
      font-weight: 800;
      background: #f4faf6;
      border-color: #9bd6b3;
    }

    /* Banner Bawah (Metode Pembayaran) */
    .payment-bar-banner {
      background: linear-gradient(90deg, #0b3b7b 0%, #1153a8 70%, #0b3b7b 100%);
      margin: 4px -20px 0 -20px;
      padding: 6px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: #ffffff;
    }

    .pay-badge-pill {
      background: #082852;
      color: #ffffff;
      font-size: 10px;
      font-weight: 800;
      padding: 4px 12px;
      border-radius: 12px;
      letter-spacing: 0.5px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .pay-icons-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .pay-chip {
      background: #ffffff;
      color: #0b3b7b;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 9.5px;
      font-weight: 800;
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      line-height: 1;
      min-width: 60px;
      text-align: center;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
    }

    .pay-chip .bank-code {
      font-family: 'Montserrat', sans-serif;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.5px;
    }

    .pay-chip .bank-desc {
      font-size: 6.5px;
      color: #4a5568;
      font-weight: 700;
      text-transform: uppercase;
      margin-top: 1px;
    }

    .pay-slogan {
      font-size: 11px;
      font-style: italic;
      color: #ffffff;
      font-weight: 600;
      letter-spacing: 0.2px;
      white-space: nowrap;
    }

    /* Sisi Kanan (Stub Tiket / Bagian Sobek) */
    .ticket-stub {
      width: 220px;
      border-left: 2.5px dashed #0a3871;
      background: #ffffff;
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
    }

    .stub-header {
      width: 100%;
      background: #0b3b7b;
      padding: 8px 10px 10px 10px;
      text-align: center;
      color: #ffffff;
    }

    .stub-header-title {
      font-size: 11px;
      font-weight: 700;
      margin-bottom: 4px;
      letter-spacing: 0.5px;
      color: #dbeafe;
    }

    .stub-ticket-no-pill {
      background: #ffffff;
      color: #0b3b7b;
      font-family: 'Montserrat', sans-serif;
      font-size: 14px;
      font-weight: 900;
      padding: 4px 10px;
      border-radius: 18px;
      letter-spacing: 0.8px;
      display: inline-block;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
    }

    .stub-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 10px 12px;
      gap: 6px;
      width: 100%;
    }

    .stub-brand-mini {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .stub-brand-title {
      font-family: 'Montserrat', sans-serif;
      font-size: 12px;
      font-weight: 900;
      color: #0b3b7b;
    }

    .stub-brand-sub {
      font-family: 'Montserrat', sans-serif;
      font-size: 8px;
      font-weight: 800;
      letter-spacing: 3px;
      color: #0b3b7b;
    }

    .stub-qr-box {
      width: 96px;
      height: 96px;
      background: #ffffff;
      padding: 4px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .stub-qr-box img {
      width: 100% !important;
      height: 100% !important;
    }

    .stub-footer-note {
      font-size: 10px;
      font-style: italic;
      font-weight: 800;
      color: #0b3b7b;
      text-align: center;
      line-height: 1.2;
    }

    /* ==========================================================================
       SLIP 2: INFORMASI PEMBAYARAN & KETENTUAN (BAWAH)
       ========================================================================== */
    .ticket-card-bottom {
      background: #ffffff;
      border: 2px solid #0a3871;
      border-radius: 16px;
      overflow: hidden;
      display: grid;
      grid-template-columns: 24% 46% 30%;
      box-shadow: 0 8px 24px rgba(10, 56, 113, 0.12);
      min-height: 220px;
    }

    /* Kolom 1 Bawah (Brand & Ucapan) */
    .bottom-col-brand {
      padding: 16px 14px 0 14px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      border-right: 1.5px solid #edf2f7;
      text-align: center;
      position: relative;
    }

    .bottom-brand-header {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }

    .bottom-pill-badge {
      background: #0b3b7b;
      color: #ffffff;
      font-family: 'Montserrat', sans-serif;
      font-size: 11px;
      font-weight: 800;
      padding: 4px 14px;
      border-radius: 14px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-top: 4px;
    }

    .bottom-thanks-box {
      margin: 10px 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }

    .bottom-bus-icon {
      font-size: 32px;
      color: #0b3b7b;
    }

    .bottom-thanks-text {
      font-size: 11px;
      font-style: italic;
      font-weight: 700;
      color: #2d3748;
      line-height: 1.25;
    }

    .bottom-route-strip {
      background: #0b3b7b;
      color: #ffffff;
      font-size: 9px;
      font-weight: 700;
      padding: 6px 8px;
      width: calc(100% + 28px);
      margin-left: -14px;
      margin-right: -14px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      letter-spacing: 0.2px;
    }

    /* Kolom 2 Bawah (Informasi Pembayaran) */
    .bottom-col-payment {
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      border-right: 2px dotted #a0aec0;
    }

    .info-payment-header-pill {
      background: #0b3b7b;
      color: #ffffff;
      font-size: 12px;
      font-weight: 800;
      padding: 5px 14px;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      align-self: flex-start;
      margin-bottom: 2px;
      letter-spacing: 0.3px;
    }

    .bottom-field-row {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .bfield-icon {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #0b3b7b;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      flex-shrink: 0;
    }

    .bfield-label {
      width: 160px;
      font-size: 11px;
      font-weight: 700;
      color: #1a202c;
      flex-shrink: 0;
    }

    .bfield-val-box {
      flex: 1;
      background: #ffffff;
      border: 1.5px solid #a2c1e8;
      border-radius: 6px;
      padding: 3px 8px;
      font-size: 11px;
      font-weight: 700;
      color: #0b3b7b;
      min-height: 24px;
      display: flex;
      align-items: center;
      word-break: break-word;
    }

    /* Kolom 3 Bawah (Ketentuan Tiket) */
    .bottom-col-terms {
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background: #ffffff;
    }

    .terms-title {
      font-size: 12px;
      font-weight: 800;
      color: #0b3b7b;
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .terms-list {
      padding-left: 18px;
      font-size: 10px;
      color: #2d3748;
      line-height: 1.45;
      font-weight: 600;
    }

    .terms-list li {
      margin-bottom: 4px;
    }

    .terms-footer-brand {
      background: #0b3b7b;
      color: #ffffff;
      padding: 8px 12px;
      border-radius: 8px;
      text-align: center;
      margin-top: 8px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .tf-logo-title {
      font-family: 'Montserrat', sans-serif;
      font-size: 12px;
      font-weight: 900;
      color: #ffb703;
      letter-spacing: 0.5px;
    }

    .tf-slogan {
      font-size: 10px;
      font-style: italic;
      color: #ffffff;
      font-weight: 700;
      margin-top: 1px;
    }

    /* ==========================================================================
       PRINT MEDIA QUERY (100% SAMA PERSIS DENGAN DESAIN)
       ========================================================================== */
    @media print {
      body {
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      .no-print {
        display: none !important;
      }

      .action-bar {
        display: none !important;
      }

      .ticket-sheet {
        max-width: 100% !important;
        margin: 0 auto !important;
        padding: 0 !important;
        box-shadow: none !important;
        page-break-after: always !important;
      }

      .ticket-card-top,
      .ticket-card-bottom {
        box-shadow: none !important;
        border: 2px solid #0a3871 !important;
      }

      .field-value-box,
      .bfield-val-box {
        border: 1.5px solid #a2c1e8 !important;
      }

      @page {
        size: A4 portrait;
        margin: 8mm 6mm;
      }
    }
  </style>
</head>
<body>

  <!-- Floating Action Bar (Hidden when printed) -->
  <div class="action-bar no-print">
    <div style="display: flex; align-items: center; gap: 10px;">
      <span style="font-weight: 800; color: #0b3b7b; font-size: 16px;">
        <i class="fas fa-ticket-alt mr-1"></i> E-Tiket Penumpang Sinar Galaxy
      </span>
      <span style="font-size: 12px; background: #e2e8f0; padding: 3px 8px; border-radius: 12px; font-weight: 600;">
        <?= count($tickets) ?> Tiket Dimuat
      </span>
    </div>

    <div style="display: flex; gap: 10px;">
      <button onclick="window.print()" class="action-btn btn-print">
        <i class="fas fa-print"></i> Cetak E-Tiket
      </button>
      <button onclick="window.close()" class="action-btn btn-close-window">
        <i class="fas fa-times"></i> Tutup
      </button>
    </div>
  </div>

  <?php if (empty($tickets)): ?>
    <div style="max-width: 600px; margin: 60px auto; text-align: center; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);" class="no-print">
      <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f59e0b; margin-bottom: 16px;"></i>
      <h3 style="font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 8px;">Data Tiket Tidak Ditemukan</h3>
      <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Silakan pilih tiket dari modul Pemesanan atau Manifes Keberangkatan.</p>
      <button onclick="window.close()" class="action-btn btn-close-window">Tutup Jendela</button>
    </div>
  <?php else: ?>

    <?php 
    foreach ($tickets as $idx => $t): 
      // Formatting Data
      $idPemesanan = (int)$t['id_pemesanan'];
      $noTiket = !empty($t['nip_sgt_id']) ? $t['nip_sgt_id'] : 'SGT ' . str_pad($idPemesanan, 6, '0', STR_PAD_LEFT);
      $namaPenumpang = !empty($t['nama_id']) ? $t['nama_id'] : '-';
      $noKursi = !empty($t['kursi']) ? $t['kursi'] : '-';
      $kotaTujuan = !empty($t['nama_tujuan']) ? $t['nama_tujuan'] : ($tujuanMap[$t['tujuan_id']] ?? '-');
      
      // Formatting Tanggal & Jam Keberangkatan
      $tglBerangkatRaw = $t['tanggal_berangkat'];
      $timeBerangkat = strtotime($tglBerangkatRaw);
      
      $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
      ];
      $hariIndo = [
        0 => 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
      ];

      if ($timeBerangkat) {
        $hariTeks = $hariIndo[date('w', $timeBerangkat)];
        $tglTeks = date('j', $timeBerangkat) . ' ' . $bulanIndo[(int)date('n', $timeBerangkat)] . ' ' . date('Y', $timeBerangkat);
        $jamTeks = date('H:i', $timeBerangkat);
        if ($jamTeks === '00:00') {
          // Default jam keberangkatan travel jika tidak diset
          $jamTeks = '19:30';
        }
        $jamTeksDisplay = $jamTeks . ' WITA';
        $tglBerangkatDisplay = $hariTeks . ', ' . $tglTeks;
      } else {
        $tglBerangkatDisplay = '-';
        $jamTeksDisplay = '19:30 WITA';
      }

      $hargaTiket = (float)($t['harga_id'] ?? 0);
      $hargaTiketDisplay = 'Rp. ' . number_format($hargaTiket, 0, ',', '.');

      // Informasi Pembayaran
      $metodePembayaran = !empty($t['metode_pembayaran']) ? $t['metode_pembayaran'] : ($metodeMap[$t['metode_pembayaran_id']] ?? 'Transfer Bank / Cash');
      $noRef = !empty($t['no_rekening']) ? $t['no_rekening'] . ' (' . $t['nama_rekening'] . ')' : '-';
      if (!empty($t['payment_methods'])) {
        $pmArr = json_decode($t['payment_methods'], true);
        if (is_array($pmArr) && !empty($pmArr)) {
          $mNames = [];
          foreach ($pmArr as $p) {
            if (isset($metodeMap[$p['method_id']])) {
              $mNames[] = $metodeMap[$p['method_id']];
            }
          }
          if (!empty($mNames)) {
            $metodePembayaran = implode(', ', array_unique($mNames));
          }
        }
      }

      $namaPengirim = $namaPenumpang;
      $tglPesanRaw = $t['tanggal_pemesanan'];
      $timePesan = strtotime($tglPesanRaw);
      $tglTransferDisplay = $timePesan ? date('j', $timePesan) . ' ' . $bulanIndo[(int)date('n', $timePesan)] . ' ' . date('Y', $timePesan) : date('d/m/Y');
      
      $catatan = !empty($t['keterangan']) ? $t['keterangan'] : (!empty($t['alamat_id']) ? 'Jemput: ' . $t['alamat_id'] : 'Armada: ' . ($t['no_plat_id'] ?: '-') . ' / ' . ($t['kelas'] ?: 'Eksekutif'));

      // QR Code Content
      $qrData = "SINAR GALAXY TRAVEL | TIKET: " . $noTiket . " | NAMA: " . $namaPenumpang . " | KURSI: " . $noKursi . " | TUJUAN: " . $kotaTujuan . " | TGL: " . $tglBerangkatDisplay . " | STATUS: LUNAS";
    ?>

    <div class="ticket-sheet">
      
      <!-- ====================================================================
           SLIP 1: TIKET PENUMPANG UTAMA (ATAS)
           ==================================================================== -->
      <div class="ticket-card-top">
        
        <!-- Sisi Kiri (Main Ticket Body) -->
        <div class="ticket-main">
          
          <!-- Header Bar -->
          <div class="ticket-main-header">
            <div class="brand-group">
              <img src="<?= $appLogo ?>" alt="Logo Sinar Galaxy" class="brand-logo-img">
              <div class="brand-text">
                <div class="brand-title">SINAR GALAXY</div>
                <div class="brand-subtitle">TRAVEL</div>
                <div class="brand-tagline">Perjalanan Nyaman, Sampai Tujuan</div>
              </div>
            </div>

            <div class="header-badge-container">
              <div class="badge-ticket-title">TIKET PENUMPANG</div>
              <div class="badge-bus-type">BUS AKAP / AKDP</div>
            </div>
          </div>

          <!-- Body (Bus Visual + Form Fields) -->
          <div class="ticket-main-body">
            <!-- Visual Bus -->
            <div class="bus-visual-box">
              <img src="<?= $busImage ?>" alt="Armada Sinar Galaxy">
              <div class="bus-badge-floating">
                <i class="fas fa-bus mr-1"></i> <?= htmlspecialchars($t['no_plat_id'] ?: 'Sinar Galaxy VIP') ?> (<?= htmlspecialchars($t['kelas'] ?: 'Executive') ?>)
              </div>
            </div>

            <!-- Form Data Fields -->
            <div class="ticket-fields-grid">
              
              <!-- 1. Nama Penumpang -->
              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-user"></i></div>
                <div class="field-label">Nama Penumpang</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($namaPenumpang) ?></div>
              </div>

              <!-- 2. No Kursi -->
              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-chair"></i></div>
                <div class="field-label">No. Kursi</div>
                <div class="field-separator">:</div>
                <div class="field-value-box" style="font-weight: 800; color: #0b3b7b;">
                  Kursi <?= htmlspecialchars($noKursi) ?>
                </div>
              </div>

              <!-- 3. Tujuan -->
              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-map-marker-alt"></i></div>
                <div class="field-label">Tujuan</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($kotaTujuan) ?></div>
              </div>

              <!-- 4. Tanggal Keberangkatan -->
              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-calendar-alt"></i></div>
                <div class="field-label">Tanggal Keberangkatan</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($tglBerangkatDisplay) ?></div>
              </div>

              <!-- 5. Jam Berangkat -->
              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-clock"></i></div>
                <div class="field-label">Jam Berangkat</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($jamTeksDisplay) ?></div>
              </div>

              <!-- 6. Harga Tiket -->
              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-tag"></i></div>
                <div class="field-label">Harga Tiket</div>
                <div class="field-separator">:</div>
                <div class="field-value-box price-highlight"><?= htmlspecialchars($hargaTiketDisplay) ?></div>
              </div>

            </div>
          </div>

          <!-- Bottom Payment Methods Banner -->
          <div class="payment-bar-banner">
            <div class="pay-badge-pill">METODE PEMBAYARAN</div>
            
            <div class="pay-icons-row">
              <div class="pay-chip">
                <span class="bank-code" style="color: #00529c;">BRI</span>
                <span class="bank-desc">Transfer Bank</span>
              </div>
              <div class="pay-chip">
                <span class="bank-code" style="color: #e35205;">BNI</span>
                <span class="bank-desc">Transfer Bank</span>
              </div>
              <div class="pay-chip">
                <span class="bank-code" style="color: #00368a;">BCA</span>
                <span class="bank-desc">Transfer Bank</span>
              </div>
              <div class="pay-chip">
                <span class="bank-code" style="color: #002d62;">Mandiri</span>
                <span class="bank-desc">Transfer Bank</span>
              </div>
              <div class="pay-chip">
                <span class="bank-code" style="color: #0d8a43;"><i class="fas fa-money-bill-wave"></i></span>
                <span class="bank-desc">Cash Kasir</span>
              </div>
            </div>

            <div class="pay-slogan">
              Mudah Bayarnya Aman Perjalanannya
            </div>
          </div>

        </div>

        <!-- Sisi Kanan (Stub Bagian Sobek Penumpang) -->
        <div class="ticket-stub">
          
          <div class="stub-header">
            <div class="stub-header-title">No. Tiket</div>
            <div class="stub-ticket-no-pill"><?= htmlspecialchars($noTiket) ?></div>
          </div>

          <div class="stub-body">
            <div class="stub-brand-mini">
              <img src="<?= $appLogo ?>" alt="Logo" style="height: 24px; margin-bottom: 2px;">
              <div class="stub-brand-title">SINAR GALAXY</div>
              <div class="stub-brand-sub">TRAVEL</div>
            </div>

            <div class="stub-qr-box" id="qrcode_<?= $idx ?>"></div>

            <div class="stub-footer-note">
              Jaga Tiket Anda<br>Selama Perjalanan
            </div>
          </div>

        </div>

      </div>

      <!-- ====================================================================
           SLIP 2: INFORMASI PEMBAYARAN & KETENTUAN (BAWAH)
           ==================================================================== -->
      <div class="ticket-card-bottom">
        
        <!-- Kolom 1: Brand & Ucapan -->
        <div class="bottom-col-brand">
          <div class="bottom-brand-header">
            <div class="brand-group" style="gap: 8px;">
              <img src="<?= $appLogo ?>" alt="Logo" style="height: 36px;">
              <div class="brand-text">
                <div class="brand-title" style="font-size: 15px;">SINAR GALAXY</div>
                <div class="brand-subtitle" style="font-size: 8px; letter-spacing: 4px;">TRAVEL</div>
              </div>
            </div>
            <div class="bottom-pill-badge">TIKET PENUMPANG</div>
          </div>

          <div class="bottom-thanks-box">
            <div class="bottom-bus-icon"><i class="fas fa-bus-alt"></i></div>
            <div class="bottom-thanks-text">
              Terima Kasih<br>Telah Memilih<br>Sinar Galaxy Travel
            </div>
          </div>

          <div class="bottom-route-strip">
            <i class="fas fa-map-marker-alt"></i> Melayani Perjalanan Antar Kota & Provinsi
          </div>
        </div>

        <!-- Kolom 2: Informasi Pembayaran -->
        <div class="bottom-col-payment">
          <div class="info-payment-header-pill">
            <i class="fas fa-receipt"></i> INFORMASI PEMBAYARAN
          </div>

          <!-- 1. Metode Pembayaran -->
          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-credit-card"></i></div>
            <div class="bfield-label">Metode Pembayaran</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($metodePembayaran) ?></div>
          </div>

          <!-- 2. No Referensi / Bukti -->
          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="bfield-label">No. Referensi / Bukti Transfer</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($noRef) ?></div>
          </div>

          <!-- 3. Nama Pengirim -->
          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-user-check"></i></div>
            <div class="bfield-label">Nama Pengirim</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($namaPengirim) ?></div>
          </div>

          <!-- 4. Tanggal Transfer -->
          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="bfield-label">Tanggal Transfer</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($tglTransferDisplay) ?></div>
          </div>

          <!-- 5. Catatan -->
          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-sticky-note"></i></div>
            <div class="bfield-label">Catatan</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($catatan) ?></div>
          </div>

        </div>

        <!-- Kolom 3: Ketentuan Tiket -->
        <div class="bottom-col-terms">
          <div>
            <div class="terms-title">
              <i class="fas fa-exclamation-circle text-danger"></i> KETENTUAN TIKET
            </div>

            <ol class="terms-list">
              <li>Tiket ini sebagai tanda bukti pembayaran.</li>
              <li>Harap tiba di terminal 30 menit sebelum keberangkatan.</li>
              <li>Tiket tidak dapat dibatalkan atau dikembalikan (kecuali ada ketentuan khusus).</li>
              <li>Simpan tiket ini selama perjalanan.</li>
            </ol>
          </div>

          <div class="terms-footer-brand">
            <div class="tf-logo-title"><i class="fas fa-star mr-1"></i> Sinar Galaxy Travel</div>
            <div class="tf-slogan">Selalu Hadir Untuk Perjalanan Anda</div>
          </div>
        </div>

      </div>

    </div>

    <script>
      // Generate QR Code untuk tiket ini
      (function() {
        var qrContainer = document.getElementById("qrcode_<?= $idx ?>");
        if (qrContainer) {
          try {
            new QRCode(qrContainer, {
              text: "<?= addslashes($qrData) ?>",
              width: 88,
              height: 88,
              colorDark : "#0a3871",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.M
            });
          } catch(e) {
            qrContainer.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=88x88&data=<?= urlencode($qrData) ?>" alt="QR Code">';
          }
        }
      })();
    </script>

    <?php endforeach; ?>

  <?php endif; ?>

  <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] == '1' && !empty($tickets)): ?>
    <script>
      window.addEventListener('load', function() {
        setTimeout(function() {
          window.print();
        }, 500);
      });
    </script>
  <?php endif; ?>

</body>
</html>
