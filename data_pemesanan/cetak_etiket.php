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
$format_kertas = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'a4'; // a4, thermal80, thermal58

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
                       dr.nama_rekening, dr.nomor_rekening,
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
                   dr.nama_rekening, dr.nomor_rekening,
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
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan']; } }

$metodeMap = [];
$res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran']; } }

$rekeningMap = [];
$res = mysqli_query($conn, "SELECT * FROM data_rekening");
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rekeningMap[$r['id_rekening']] = $r; } }

$usersMap = [];
$res = mysqli_query($conn, "SELECT id_users, username, asal_po FROM data_users");
if ($res) { 
    while ($r = mysqli_fetch_assoc($res)) { 
        $usersMap[$r['id_users']] = !empty($r['asal_po']) ? $r['asal_po'] : $r['username']; 
    } 
}

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
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,600;0,700;0,800;1,700;1,800&family=Montserrat:wght@700;800;900&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

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
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      border: 1px solid #dce4ee;
    }

    .format-switcher-group {
      display: flex;
      align-items: center;
      background: #edf2f7;
      padding: 4px;
      border-radius: 8px;
      gap: 4px;
    }

    .format-btn {
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      border: none;
      background: transparent;
      color: #4a5568;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .format-btn.active {
      background: #0b3b7b;
      color: #ffffff;
      box-shadow: 0 2px 6px rgba(11, 59, 123, 0.3);
    }

    .action-btn {
      padding: 8px 18px;
      border-radius: 6px;
      font-weight: 700;
      font-size: 13px;
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
       MODE 1: FORMAT A4 / STANDAR FULL COLOR (DESAIN ASLI REFERENSI)
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

    .ticket-main {
      flex: 1;
      padding: 16px 20px 0 20px;
      display: flex;
      flex-direction: column;
      background: #ffffff;
    }

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

    .ticket-main-body {
      display: flex;
      gap: 16px;
      padding: 14px 0 10px 0;
      align-items: stretch;
    }

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
       MODE 2 & 3: THERMAL LAYOUT STYLES (80mm & 58mm)
       ========================================================================== */
    .thermal-wrapper {
      display: none;
      background: #ffffff;
      margin: 0 auto 30px auto;
      color: #000000;
      page-break-after: always;
    }

    /* ==========================================================================
       MODE 2: BOARDING PASS STYLE (THERMAL 80MM / LANDSCAPE PASS)
       IDENTIK DENGAN FOTO LION AIR GROUP BOARDING PASS
       ========================================================================== */
    .thermal-wrapper.mode-80 {
      width: 760px;
      max-width: 100%;
      background: #ffffff;
      color: #000000;
      font-family: 'Montserrat', 'Plus Jakarta Sans', Arial, sans-serif;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      padding: 10px 14px 8px 14px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      position: relative;
    }

    .bp-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 6px;
      border-bottom: 1px solid #e2e8f0;
    }

    .bp-header-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .bp-logo-brand {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #c92a2a;
      font-weight: 900;
      font-size: 16px;
      letter-spacing: 0.5px;
    }

    .bp-logo-brand i {
      font-size: 18px;
    }

    .bp-sub-brand {
      color: #c92a2a;
      font-weight: 800;
      font-size: 13px;
      font-style: italic;
    }

    .bp-header-right {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 2px;
    }

    .bp-group-brand {
      color: #c92a2a;
      font-weight: 900;
      font-size: 12px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .bp-badge-box {
      border: 1px solid #c92a2a;
      color: #c92a2a;
      padding: 1px 8px;
      font-size: 8.5px;
      font-weight: 800;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      border-radius: 2px;
    }

    .bp-body {
      display: flex;
      padding-top: 10px;
    }

    .bp-main-section {
      flex: 1;
      padding-right: 14px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .bp-grid-info {
      display: grid;
      grid-template-columns: 2.2fr 1.5fr 1.6fr;
      row-gap: 8px;
      column-gap: 12px;
    }

    .bp-cell {
      display: flex;
      flex-direction: column;
    }

    .bp-cell-label {
      font-size: 8.5px;
      font-weight: 800;
      color: #000000;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 1px;
    }

    .bp-cell-val {
      font-size: 13px;
      font-weight: 800;
      color: #000000;
      text-transform: uppercase;
      line-height: 1.15;
    }

    .bp-cell-val.bold-lg {
      font-size: 14px;
      font-weight: 900;
    }

    .bp-cell-val.seat-highlight {
      font-size: 26px;
      font-weight: 900;
      line-height: 1;
      color: #000000;
    }

    .bp-cell-val.time-highlight {
      font-size: 18px;
      font-weight: 900;
      line-height: 1.1;
      color: #000000;
    }

    .bp-class-group {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
    }

    .bp-group-letter {
      font-size: 24px;
      font-weight: 900;
      line-height: 1;
      margin-left: 6px;
    }

    .bp-barcode-bottom-row {
      display: flex;
      flex-direction: column;
      margin-top: 6px;
    }

    .bp-barcode-img {
      height: 30px;
      width: 220px;
    }

    .bp-warning-text {
      font-size: 7.5px;
      font-weight: 800;
      color: #c92a2a;
      line-height: 1.25;
      text-transform: uppercase;
      margin-top: 3px;
    }

    .bp-payment-box {
      margin-top: 6px;
      padding: 4px 8px;
      background: #f8fafc;
      border: 1px dashed #94a3b8;
      border-radius: 4px;
    }

    .bp-payment-header {
      font-size: 8px;
      font-weight: 800;
      color: #0b3b7b;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-bottom: 3px;
      display: flex;
      align-items: center;
      gap: 4px;
      border-bottom: 1px solid #e2e8f0;
      padding-bottom: 2px;
    }

    .bp-payment-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2px 8px;
      font-size: 8px;
    }

    .bp-pay-item {
      display: flex;
      gap: 4px;
      align-items: baseline;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .bp-pay-lbl {
      font-weight: 700;
      color: #475569;
      font-size: 7.5px;
      flex-shrink: 0;
    }

    .bp-pay-val {
      font-weight: 800;
      color: #0f172a;
      font-size: 8px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Vertical Divider with Strip Barcode / Perforation */
    .bp-vertical-strip {
      width: 44px;
      border-left: 1.5px dashed #000000;
      padding: 0 4px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .bp-strip-barcode-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }

    /* Passenger Stub (Sisi Kanan) */
    .bp-stub-section {
      width: 175px;
      padding-left: 10px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-left: 1px dotted #94a3b8;
    }

    .bp-stub-badge-box {
      border: 1px solid #c92a2a;
      color: #c92a2a;
      padding: 2px 6px;
      font-size: 8.5px;
      font-weight: 800;
      letter-spacing: 0.8px;
      text-align: center;
      text-transform: uppercase;
      margin-bottom: 6px;
    }

    .bp-stub-grid {
      display: flex;
      flex-direction: column;
      gap: 3px;
      font-size: 9px;
    }

    .bp-stub-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      line-height: 1.2;
    }

    .bp-stub-lbl {
      font-weight: 700;
      color: #333;
      font-size: 8px;
      text-transform: uppercase;
      width: 58px;
    }

    .bp-stub-val {
      font-weight: 800;
      color: #000;
      font-size: 9px;
      text-transform: uppercase;
      text-align: right;
      flex: 1;
    }

    .bp-stub-val.seat {
      font-size: 13px;
      font-weight: 900;
    }

    /* ==========================================================================
       MODE 3: MINI THERMAL 58MM
       ========================================================================== */
    .thermal-wrapper.mode-58 {
      width: 58mm;
      max-width: 58mm;
      font-family: 'Courier Prime', monospace, Courier;
      font-size: 9.5px;
      line-height: 1.2;
      padding: 8px 5px;
      border: 1px solid #cbd5e1;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .thermal-header {
      text-align: center;
      margin-bottom: 8px;
    }

    .th-brand {
      font-weight: 900;
      font-size: 12px;
      letter-spacing: 1px;
      text-transform: uppercase;
    }

    .th-sub {
      font-size: 10px;
      font-weight: 700;
      margin-bottom: 2px;
    }

    .th-contact {
      font-size: 9px;
      color: #222;
    }

    .thermal-divider {
      border-top: 1px dashed #000;
      margin: 6px 0;
    }

    .thermal-double-divider {
      border-top: 2px solid #000;
      margin: 6px 0;
    }

    .th-title-badge {
      font-weight: 900;
      font-size: 13px;
      text-align: center;
      margin: 4px 0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .thermal-table {
      width: 100%;
      border-collapse: collapse;
      margin: 4px 0;
    }

    .thermal-table td {
      padding: 2px 0;
      vertical-align: top;
    }

    .thermal-table td.label {
      width: 38%;
      font-weight: 700;
    }

    .thermal-table td.sep {
      width: 4%;
      text-align: center;
    }

    .thermal-table td.val {
      width: 58%;
      font-weight: 700;
      word-break: break-word;
    }

    .thermal-highlight {
      font-weight: 900;
      font-size: 13px;
    }

    .thermal-wrapper.mode-58 .thermal-highlight {
      font-size: 11px;
    }

    .thermal-qr-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      margin: 10px 0;
      text-align: center;
    }

    .thermal-qr-container {
      background: #ffffff;
      padding: 4px;
      display: inline-block;
    }

    .thermal-footer {
      text-align: center;
      font-size: 9px;
      margin-top: 6px;
      line-height: 1.3;
    }

    /* ==========================================================================
       VIEWPORT SWITCHING VIA JAVASCRIPT / CLASS
       ========================================================================== */
    body.view-format-a4 .ticket-sheet {
      display: flex !important;
    }
    body.view-format-a4 .thermal-wrapper {
      display: none !important;
    }

    body.view-format-thermal80 .ticket-sheet {
      display: none !important;
    }
    body.view-format-thermal80 .thermal-wrapper.mode-80 {
      display: block !important;
    }
    body.view-format-thermal80 .thermal-wrapper.mode-58 {
      display: none !important;
    }

    body.view-format-thermal58 .ticket-sheet {
      display: none !important;
    }
    body.view-format-thermal58 .thermal-wrapper.mode-80 {
      display: none !important;
    }
    body.view-format-thermal58 .thermal-wrapper.mode-58 {
      display: block !important;
    }

    /* ==========================================================================
       PRINT MEDIA QUERY (DEDICATED @PAGE SIZES FOR THERMAL & A4)
       ========================================================================== */
    @media print {
      body {
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      .no-print,
      .action-bar {
        display: none !important;
      }

      /* Print A4 */
      body.view-format-a4 {
        margin: 0 !important;
        padding: 0 !important;
      }

      body.view-format-a4 .ticket-sheet {
        display: flex !important;
        max-width: 100% !important;
        margin: 0 auto !important;
        padding: 0 !important;
        box-shadow: none !important;
        page-break-after: always !important;
      }

      body.view-format-a4 .ticket-card-top,
      body.view-format-a4 .ticket-card-bottom {
        box-shadow: none !important;
        border: 2px solid #0a3871 !important;
      }

      body.view-format-a4 .field-value-box,
      body.view-format-a4 .bfield-val-box {
        border: 1.5px solid #a2c1e8 !important;
      }

      /* Print Thermal 80mm */
      body.view-format-thermal80 .thermal-wrapper.mode-80 {
        display: block !important;
        width: 80mm !important;
        max-width: 80mm !important;
        margin: 0 auto !important;
        padding: 4px 2px !important;
        border: none !important;
        box-shadow: none !important;
        page-break-after: always !important;
      }

      /* Print Thermal 58mm */
      body.view-format-thermal58 .thermal-wrapper.mode-58 {
        display: block !important;
        width: 58mm !important;
        max-width: 58mm !important;
        margin: 0 auto !important;
        padding: 3px 1px !important;
        border: none !important;
        box-shadow: none !important;
        page-break-after: always !important;
      }

      /* Page Rules */
      body.view-format-a4 @page {
        size: A4 portrait;
        margin: 8mm 6mm;
      }

      body.view-format-thermal80 @page {
        size: 80mm auto;
        margin: 2mm;
      }

      body.view-format-thermal58 @page {
        size: 58mm auto;
        margin: 1mm;
      }
    }
  </style>
</head>
<body class="view-format-<?= in_array($format_kertas, ['thermal80', 'thermal58']) ? $format_kertas : 'a4' ?>">

  <!-- Floating Action Bar with Format Selector -->
  <div class="action-bar no-print">
    <div style="display: flex; align-items: center; gap: 10px;">
      <span style="font-weight: 800; color: #0b3b7b; font-size: 15px;">
        <i class="fas fa-ticket-alt mr-1"></i> E-Tiket Penumpang
      </span>
      <span style="font-size: 11px; background: #e2e8f0; padding: 2px 7px; border-radius: 10px; font-weight: 700;">
        <?= count($tickets) ?> Tiket
      </span>
    </div>

    <!-- Format Selector Buttons -->
    <div style="display: flex; align-items: center; gap: 6px;">
      <span style="font-size: 12px; font-weight: 700; color: #4a5568;"><i class="fas fa-print mr-1"></i> Ukuran:</span>
      <div class="format-switcher-group">
        <button type="button" class="format-btn <?= $format_kertas === 'a4' || !in_array($format_kertas, ['thermal80', 'thermal58']) ? 'active' : '' ?>" onclick="switchFormat('a4')">
          <i class="fas fa-file-invoice"></i> A4 Warna Resmi
        </button>
        <button type="button" class="format-btn <?= $format_kertas === 'thermal80' ? 'active' : '' ?>" onclick="switchFormat('thermal80')">
          <i class="fas fa-ticket-alt"></i> Boarding Pass 80mm
        </button>
        <button type="button" class="format-btn <?= $format_kertas === 'thermal58' ? 'active' : '' ?>" onclick="switchFormat('thermal58')">
          <i class="fas fa-mobile-alt"></i> Thermal 58mm
        </button>
      </div>
    </div>

    <?php 
      $firstHp = !empty($tickets[0]['no_hp_id']) ? $tickets[0]['no_hp_id'] : '';
      $firstNama = !empty($tickets[0]['nama_id']) ? $tickets[0]['nama_id'] : '';
    ?>

    <!-- Share & Print Action Buttons -->
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
      <button type="button" onclick="kirimWhatsAppLangsung(0)" class="action-btn" style="background: #25D366; color: #ffffff; box-shadow: 0 2px 8px rgba(37, 211, 102, 0.4);" title="Kirim langsung ke WhatsApp pelanggan: <?= htmlspecialchars($firstNama . (!empty($firstHp) ? ' (' . $firstHp . ')' : '')) ?>">
        <i class="fab fa-whatsapp" style="font-size: 15px;"></i> Kirim WA <?= !empty($firstHp) && count($tickets) === 1 ? '(' . htmlspecialchars($firstHp) . ')' : 'Pelanggan' ?>
      </button>
      <button type="button" onclick="downloadGambarTiket()" class="action-btn" style="background: #0284c7; color: #ffffff;" title="Download tiket dalam format gambar resolusi tinggi (PNG)">
        <i class="fas fa-file-image"></i> Simpan Gambar (PNG)
      </button>
      <button type="button" onclick="downloadPDFTiket()" class="action-btn" style="background: #dc2626; color: #ffffff;" title="Download dokumen PDF resmi">
        <i class="fas fa-file-pdf"></i> Download PDF
      </button>
      <button type="button" onclick="window.print()" class="action-btn btn-print">
        <i class="fas fa-print"></i> Cetak Tiket
      </button>
      <button type="button" onclick="window.close()" class="action-btn btn-close-window">
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
      // Formatting Data (Solusi 1: Nomor Tiket Berkelanjutan Berbasis id_pemesanan)
      $idPemesanan = (int)$t['id_pemesanan'];
      $noTiket = 'SGT ' . str_pad($idPemesanan, 6, '0', STR_PAD_LEFT);
      $namaPenumpang = !empty($t['nama_id']) ? $t['nama_id'] : '-';
      $noKursi = !empty($t['kursi']) ? $t['kursi'] : '-';
      $kotaTujuan = !empty($t['nama_tujuan']) ? $t['nama_tujuan'] : ($tujuanMap[$t['tujuan_id']] ?? '-');
      $noHp = !empty($t['no_hp_id']) ? $t['no_hp_id'] : '-';
      $noKtp = !empty($t['no_ktp_id']) ? $t['no_ktp_id'] : '-';
      $noPlat = !empty($t['no_plat_id']) ? $t['no_plat_id'] : '-';
      $kelasTravel = !empty($t['kelas']) ? $t['kelas'] : 'Executive';
      
      // Ambil Nama Kota Asal Keberangkatan (Bukan ID User / Integer)
      $rawAsal = !empty($t['asal_po']) ? $t['asal_po'] : (!empty($t['asal_po_kasir']) ? $t['asal_po_kasir'] : 'Sumbawa');
      if (is_numeric($rawAsal) && isset($usersMap[$rawAsal])) {
        $kotaAsal = $usersMap[$rawAsal];
      } elseif (!empty($rawAsal) && !is_numeric($rawAsal)) {
        $kotaAsal = $rawAsal;
      } else {
        $kotaAsal = 'Sumbawa';
      }
      $asalPOCabang = $kotaAsal;
      
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
          $jamTeks = '19:30';
        }
        $jamTeksDisplay = $jamTeks . ' WITA';
        $tglBerangkatDisplay = $hariTeks . ', ' . $tglTeks;
        $tglBerangkatShort = date('d/m/Y', $timeBerangkat) . ' ' . $jamTeks . ' WITA';
      } else {
        $tglBerangkatDisplay = '-';
        $tglBerangkatShort = '-';
        $jamTeksDisplay = '19:30 WITA';
      }

      $hargaTiket = (float)($t['harga_id'] ?? 0);
      $hargaTiketDisplay = 'Rp. ' . number_format($hargaTiket, 0, ',', '.');
      $hargaTiketSimple = 'Rp ' . number_format($hargaTiket, 0, ',', '.');

      // Informasi Pembayaran
      $metodePembayaran = !empty($t['metode_pembayaran']) ? $t['metode_pembayaran'] : ($metodeMap[$t['metode_pembayaran_id']] ?? 'Transfer Bank / Cash');
      $noRef = !empty($t['nomor_rekening']) ? $t['nomor_rekening'] . ' (' . ($t['nama_rekening'] ?? '') . ')' : '-';
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
      $tglCetak = date('d/m/Y H:i');
      
      $catatan = !empty($t['keterangan']) ? $t['keterangan'] : (!empty($t['alamat_id']) ? 'Jemput: ' . $t['alamat_id'] : 'Armada: ' . $noPlat . ' / ' . $kelasTravel);

      // Format Token Publik Aman
      $tiketToken = generateTiketToken($idPemesanan);
      
      // QR Code Content
      $qrData = "SINAR GALAXY TRAVEL | TIKET: " . $noTiket . " | NAMA: " . $namaPenumpang . " | KURSI: " . $noKursi . " | TUJUAN: " . $kotaTujuan . " | TGL: " . $tglBerangkatDisplay . " | STATUS: LUNAS";
    ?>

    <!-- ====================================================================
         1. TEMPLATE A4 WARNA RESMI (IDENTIK DENGAN GAMBAR KLIEN)
         ==================================================================== -->
    <div class="ticket-sheet" id="ticket_sheet_<?= $idx ?>" 
         data-ticket-idx="<?= $idx ?>" 
         data-phone="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $noHp)) ?>" 
         data-nama="<?= htmlspecialchars($namaPenumpang) ?>" 
         data-tiket="<?= htmlspecialchars($noTiket) ?>" 
         data-tujuan="<?= htmlspecialchars($kotaTujuan) ?>" 
         data-tgl="<?= htmlspecialchars($tglBerangkatDisplay) ?>" 
         data-jam="<?= htmlspecialchars($jamTeksDisplay) ?>" 
         data-kursi="<?= htmlspecialchars($noKursi) ?>" 
         data-plat="<?= htmlspecialchars($noPlat) ?>" 
         data-harga="<?= htmlspecialchars($hargaTiketDisplay) ?>" 
         data-id="<?= $idPemesanan ?>"
         data-token="<?= htmlspecialchars($tiketToken) ?>">
      
      <!-- SLIP 1: TIKET PENUMPANG UTAMA (ATAS) -->
      <div class="ticket-card-top">
        
        <!-- Sisi Kiri (Main Ticket Body) -->
        <div class="ticket-main">
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

          <div class="ticket-main-body">
            <div class="bus-visual-box">
              <img src="<?= $busImage ?>" alt="Armada Sinar Galaxy">
              <div class="bus-badge-floating">
                <i class="fas fa-bus mr-1"></i> <?= htmlspecialchars($noPlat) ?> (<?= htmlspecialchars($kelasTravel) ?>)
              </div>
            </div>

            <div class="ticket-fields-grid">
              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-user"></i></div>
                <div class="field-label">Nama Penumpang</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($namaPenumpang) ?></div>
              </div>

              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-chair"></i></div>
                <div class="field-label">No. Kursi</div>
                <div class="field-separator">:</div>
                <div class="field-value-box" style="font-weight: 800; color: #0b3b7b;">
                  Kursi <?= htmlspecialchars($noKursi) ?>
                </div>
              </div>

              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-map-marker-alt"></i></div>
                <div class="field-label">Tujuan</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($kotaTujuan) ?></div>
              </div>

              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-calendar-alt"></i></div>
                <div class="field-label">Tanggal Keberangkatan</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($tglBerangkatDisplay) ?></div>
              </div>

              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-clock"></i></div>
                <div class="field-label">Jam Berangkat</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($jamTeksDisplay) ?></div>
              </div>

              <div class="ticket-field-row">
                <div class="field-icon-bubble"><i class="fas fa-tag"></i></div>
                <div class="field-label">Harga Tiket</div>
                <div class="field-separator">:</div>
                <div class="field-value-box price-highlight"><?= htmlspecialchars($hargaTiketDisplay) ?></div>
              </div>
            </div>
          </div>

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

            <div class="stub-qr-box" id="qrcode_a4_<?= $idx ?>"></div>

            <div class="stub-footer-note">
              Jaga Tiket Anda<br>Selama Perjalanan
            </div>
          </div>
        </div>
      </div>

      <!-- SLIP 2: INFORMASI PEMBAYARAN & KETENTUAN (BAWAH) -->
      <div class="ticket-card-bottom">
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

        <div class="bottom-col-payment">
          <div class="info-payment-header-pill">
            <i class="fas fa-receipt"></i> INFORMASI PEMBAYARAN
          </div>

          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-credit-card"></i></div>
            <div class="bfield-label">Metode Pembayaran</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($metodePembayaran) ?></div>
          </div>

          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="bfield-label">No. Referensi / Bukti Transfer</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($noRef) ?></div>
          </div>

          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-user-check"></i></div>
            <div class="bfield-label">Nama Pengirim</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($namaPengirim) ?></div>
          </div>

          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="bfield-label">Tanggal Transfer</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($tglTransferDisplay) ?></div>
          </div>

          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-sticky-note"></i></div>
            <div class="bfield-label">Catatan</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($catatan) ?></div>
          </div>
        </div>

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

      <!-- Quick Action Per Tiket (Tampil jika ada lebih dari 1 tiket) -->
      <?php if (count($tickets) > 1): ?>
      <div class="no-print" style="display: flex; justify-content: flex-end; gap: 8px; margin-top: -6px; margin-bottom: 10px;">
        <button type="button" onclick="kirimWhatsAppLangsung(<?= $idx ?>)" class="action-btn" style="background: #25D366; color: #fff; font-size: 11px; padding: 4px 10px;" title="Kirim langsung ke WhatsApp <?= htmlspecialchars($noHp) ?>">
          <i class="fab fa-whatsapp"></i> Kirim WA <?= !empty($noHp) && $noHp !== '-' ? '(' . htmlspecialchars($noHp) . ')' : 'Pelanggan' ?>
        </button>
        <button type="button" onclick="downloadGambarTiket(<?= $idx ?>)" class="action-btn" style="background: #0284c7; color: #fff; font-size: 11px; padding: 4px 10px;">
          <i class="fas fa-image"></i> Simpan Gambar
        </button>
        <button type="button" onclick="downloadPDFTiket(<?= $idx ?>)" class="action-btn" style="background: #dc2626; color: #fff; font-size: 11px; padding: 4px 10px;">
          <i class="fas fa-file-pdf"></i> Unduh PDF
        </button>
      </div>
      <?php endif; ?>

    </div>


    <!-- ====================================================================
         2. TEMPLATE THERMAL 80MM (BOARDING PASS AIRLINE STYLE - IDENTIK GAMBAR)
         ==================================================================== -->
    <div class="thermal-wrapper mode-80" id="thermal_80_<?= $idx ?>"
         data-ticket-idx="<?= $idx ?>" 
         data-phone="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $noHp)) ?>" 
         data-nama="<?= htmlspecialchars($namaPenumpang) ?>" 
         data-tiket="<?= htmlspecialchars($noTiket) ?>" 
         data-tujuan="<?= htmlspecialchars($kotaTujuan) ?>" 
         data-tgl="<?= htmlspecialchars($tglBerangkatDisplay) ?>" 
         data-jam="<?= htmlspecialchars($jamTeksDisplay) ?>" 
         data-kursi="<?= htmlspecialchars($noKursi) ?>" 
         data-plat="<?= htmlspecialchars($noPlat) ?>" 
         data-harga="<?= htmlspecialchars($hargaTiketDisplay) ?>" 
         data-id="<?= $idPemesanan ?>"
         data-token="<?= htmlspecialchars($tiketToken) ?>">
      
      <!-- Boarding Pass Header -->
      <div class="bp-header">
        <div class="bp-header-left">
          <div class="bp-logo-brand">
            <i class="fas fa-bus-alt"></i>
            <span>Sinar Galaxy</span>
          </div>
          <div class="bp-sub-brand">
            Executive Travel
          </div>
        </div>

        <div class="bp-header-right">
          <div class="bp-group-brand">
            <span>Sinar Galaxy Group</span>
            <i class="fas fa-crown" style="font-size: 10px;"></i>
          </div>
          <div class="bp-badge-box">
            BOARDING PASS
          </div>
        </div>
      </div>

      <!-- Boarding Pass Body -->
      <div class="bp-body">
        
        <!-- Sisi Kiri (Main Pass Section) -->
        <div class="bp-main-section">
          <div class="bp-grid-info">
            
            <!-- Row 1: Nama, Tanggal, Kelas -->
            <div class="bp-cell">
              <span class="bp-cell-label">NAMA PENUMPANG (NAME)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars(strtoupper($namaPenumpang)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">TANGGAL (DATE)</span>
              <span class="bp-cell-val"><?= htmlspecialchars($tglBerangkatDisplay) ?></span>
            </div>
            <div class="bp-cell">
              <div class="bp-class-group">
                <span class="bp-cell-val bold-lg" style="font-size: 13px;"><?= htmlspecialchars(strtoupper($kelasTravel)) ?></span>
                <span class="bp-cell-label" style="margin-left:auto;">Group <strong class="bp-group-letter"><?= substr(strtoupper($kelasTravel), 0, 1) ?></strong></span>
              </div>
            </div>

            <!-- Row 2: Dari, Keberangkatan, Kursi -->
            <div class="bp-cell">
              <span class="bp-cell-label">DARI (FROM)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars(strtoupper($kotaAsal)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">JAM BERANGKAT (TIME)</span>
              <span class="bp-cell-val time-highlight"><?= htmlspecialchars($jamTeksDisplay) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">NO. KURSI (SEAT)</span>
              <span class="bp-cell-val seat-highlight"><?= htmlspecialchars($noKursi) ?></span>
            </div>

            <!-- Row 3: Tujuan, Armada Plat, No Tiket -->
            <div class="bp-cell">
              <span class="bp-cell-label">TUJUAN (TO)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars(strtoupper($kotaTujuan)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">ARMADA / PLAT BUS</span>
              <span class="bp-cell-val"><?= htmlspecialchars(strtoupper($noPlat)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">NO. TIKET (PNR)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars($noTiket) ?></span>
            </div>

            <!-- Row 4: No HP, Tarif & Status, Loket Asal -->
            <div class="bp-cell">
              <span class="bp-cell-label">NO. HP PENUMPANG</span>
              <span class="bp-cell-val"><?= htmlspecialchars($noHp) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">TARIF & STATUS BAYAR</span>
              <span class="bp-cell-val" style="color: #0d8a43; font-weight: 900;"><?= htmlspecialchars($hargaTiketDisplay) ?> (LUNAS)</span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">LOKET / CABANG</span>
              <span class="bp-cell-val">PO <?= htmlspecialchars(strtoupper($kotaAsal)) ?></span>
            </div>

          </div>

          <!-- INFORMASI PEMBAYARAN LENGKAP -->
          <div class="bp-payment-box">
            <div class="bp-payment-header">
              <i class="fas fa-receipt mr-1"></i> INFORMASI PEMBAYARAN
            </div>
            <div class="bp-payment-grid">
              <div class="bp-pay-item">
                <span class="bp-pay-lbl">METODE:</span>
                <span class="bp-pay-val"><?= htmlspecialchars($metodePembayaran) ?></span>
              </div>
              <div class="bp-pay-item">
                <span class="bp-pay-lbl">NO. REF:</span>
                <span class="bp-pay-val"><?= htmlspecialchars($noRef) ?></span>
              </div>
              <div class="bp-pay-item">
                <span class="bp-pay-lbl">PENGIRIM:</span>
                <span class="bp-pay-val"><?= htmlspecialchars($namaPengirim) ?></span>
              </div>
              <div class="bp-pay-item">
                <span class="bp-pay-lbl">TGL TRANSFER:</span>
                <span class="bp-pay-val"><?= htmlspecialchars($tglTransferDisplay) ?></span>
              </div>
              <?php if (!empty($catatan) && $catatan !== '-'): ?>
              <div class="bp-pay-item" style="grid-column: span 2;">
                <span class="bp-pay-lbl">CATATAN:</span>
                <span class="bp-pay-val"><?= htmlspecialchars($catatan) ?></span>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Bottom Main: Barcode 1D + Red Warning Notes -->
          <div class="bp-barcode-bottom-row">
            <svg id="barcode_th80_<?= $idx ?>" class="bp-barcode-img"></svg>
            <div class="bp-warning-text">
              HARAP TIBA DI TITIK KEBERANGKATAN 30 MENIT SEBELUM JADWAL<br>
              SIMPAN TIKET INI SEBAGAI BUKTI PEMBAYARAN SAH
            </div>
          </div>
        </div>

        <!-- Pemisah Vertikal (Strip Barcode / Aztec Strip) -->
        <div class="bp-vertical-strip">
          <div class="bp-strip-barcode-container">
            <div id="qrcode_vert_<?= $idx ?>"></div>
          </div>
        </div>

        <!-- Sisi Kanan (Passenger Stub) -->
        <div class="bp-stub-section">
          <div class="bp-stub-badge-box">
            BOARDING PASS
          </div>

          <div class="bp-stub-grid">
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">PENUMPANG</span>
              <span class="bp-stub-val" style="font-size: 8.5px;"><?= htmlspecialchars(strtoupper($namaPenumpang)) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">DARI</span>
              <span class="bp-stub-val bold-lg"><?= htmlspecialchars(strtoupper($kotaAsal)) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">TUJUAN</span>
              <span class="bp-stub-val bold-lg"><?= htmlspecialchars(strtoupper($kotaTujuan)) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">TANGGAL</span>
              <span class="bp-stub-val"><?= $timeBerangkat ? date('d/m/Y', $timeBerangkat) : '-' ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">JAM</span>
              <span class="bp-stub-val" style="font-weight: 900;"><?= $jamTeks ?> WITA</span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">KURSI</span>
              <span class="bp-stub-val seat"><?= htmlspecialchars($noKursi) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">NO. TIKET</span>
              <span class="bp-stub-val"><?= htmlspecialchars($noTiket) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">ARMADA</span>
              <span class="bp-stub-val"><?= htmlspecialchars(strtoupper($noPlat)) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">BAYAR</span>
              <span class="bp-stub-val" style="font-size: 8px;"><?= htmlspecialchars($metodePembayaran) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">STATUS</span>
              <span class="bp-stub-val" style="color: #0d8a43; font-weight: 900;">LUNAS</span>
            </div>
          </div>
        </div>

      </div>

    </div>


    <!-- ====================================================================
         3. TEMPLATE THERMAL 58MM (OPTIMAL UNTUK MINI MOBILE PRINTER 58MM)
         ==================================================================== -->
    <div class="thermal-wrapper mode-58" id="thermal_58_<?= $idx ?>"
         data-ticket-idx="<?= $idx ?>" 
         data-phone="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $noHp)) ?>" 
         data-nama="<?= htmlspecialchars($namaPenumpang) ?>" 
         data-tiket="<?= htmlspecialchars($noTiket) ?>" 
         data-tujuan="<?= htmlspecialchars($kotaTujuan) ?>" 
         data-tgl="<?= htmlspecialchars($tglBerangkatDisplay) ?>" 
         data-jam="<?= htmlspecialchars($jamTeksDisplay) ?>" 
         data-kursi="<?= htmlspecialchars($noKursi) ?>" 
         data-plat="<?= htmlspecialchars($noPlat) ?>" 
         data-harga="<?= htmlspecialchars($hargaTiketDisplay) ?>" 
         data-id="<?= $idPemesanan ?>">
      <div class="thermal-header">
        <div class="th-brand">SINAR GALAXY</div>
        <div class="th-sub">TRAVEL & BUS AKAP/AKDP</div>
        <div class="th-contact">081763333330 | 082339860600</div>
      </div>

      <div class="thermal-double-divider"></div>
      <div class="th-title-badge" style="font-size: 11px;">TIKET PENUMPANG</div>
      <div class="thermal-divider"></div>

      <table class="thermal-table">
        <tr>
          <td style="width: 32%; font-weight: bold;">No.Tiket</td>
          <td style="width: 4%;">:</td>
          <td style="width: 64%; font-weight: bold;"><?= htmlspecialchars($noTiket) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Nama</td>
          <td>:</td>
          <td style="font-weight: bold;"><?= htmlspecialchars($namaPenumpang) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Seat</td>
          <td>:</td>
          <td style="font-weight: 900;">KURSI <?= htmlspecialchars($noKursi) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Tujuan</td>
          <td>:</td>
          <td style="font-weight: bold;"><?= htmlspecialchars($kotaTujuan) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Berangkat</td>
          <td>:</td>
          <td style="font-weight: bold;"><?= htmlspecialchars($tglBerangkatShort) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Plat/Armada</td>
          <td>:</td>
          <td><?= htmlspecialchars($noPlat) ?> (<?= htmlspecialchars($kelasTravel) ?>)</td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Pembayaran</td>
          <td>:</td>
          <td><?= htmlspecialchars($metodePembayaran) ?><?= !empty($noRef) && $noRef !== '-' ? ' (' . htmlspecialchars($noRef) . ')' : '' ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Tarif</td>
          <td>:</td>
          <td style="font-weight: 900;"><?= htmlspecialchars($hargaTiketSimple) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Status</td>
          <td>:</td>
          <td style="font-weight: 900;">LUNAS</td>
        </tr>
      </table>

      <div class="thermal-divider"></div>

      <!-- QR Code Thermal 58mm -->
      <div class="thermal-qr-wrap">
        <div class="thermal-qr-container" id="qrcode_th58_<?= $idx ?>"></div>
      </div>

      <div class="thermal-divider"></div>
      <div class="thermal-footer">
        Simpan tiket ini selama perjalanan.<br>
        Tiba 30 mnt sebelum jadwal.<br>
        Dicetak: <?= $tglCetak ?><br>
        * Selamat Menikmati Perjalanan *
      </div>
    </div>


    <script>
      // Generate QR Code untuk masing-masing container
      (function() {
        var qrText = "<?= addslashes($qrData) ?>";

        // 1. QR Code A4
        var qrA4 = document.getElementById("qrcode_a4_<?= $idx ?>");
        if (qrA4) {
          try {
            new QRCode(qrA4, {
              text: qrText,
              width: 88,
              height: 88,
              colorDark : "#0a3871",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.M
            });
          } catch(e) {
            qrA4.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=88x88&data=<?= urlencode($qrData) ?>" alt="QR">';
          }
        }

        // 2. Barcode 1D Thermal 80mm Boarding Pass
        try {
          if (window.JsBarcode) {
            JsBarcode("#barcode_th80_<?= $idx ?>", "<?= $noTiket ?>", {
              format: "CODE128",
              width: 1.4,
              height: 32,
              displayValue: false,
              margin: 0
            });
          }
        } catch(bErr) {
          console.error("Barcode 1D error:", bErr);
        }

        // 3. Vertical Strip QR Code (Divider Perforation)
        var qrVert = document.getElementById("qrcode_vert_<?= $idx ?>");
        if (qrVert) {
          try {
            new QRCode(qrVert, {
              text: qrText,
              width: 36,
              height: 72,
              colorDark : "#000000",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.L
            });
          } catch(e) {
            qrVert.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=36x72&data=<?= urlencode($qrData) ?>" alt="QR" style="width:36px;height:72px;">';
          }
        }

        // 3. QR Code Thermal 58mm
        var qrTh58 = document.getElementById("qrcode_th58_<?= $idx ?>");
        if (qrTh58) {
          try {
            new QRCode(qrTh58, {
              text: qrText,
              width: 80,
              height: 80,
              colorDark : "#000000",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.M
            });
          } catch(e) {
            qrTh58.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?= urlencode($qrData) ?>" alt="QR">';
          }
        }
      })();
    </script>

    <?php endforeach; ?>

  <?php endif; ?>

  <!-- Loading Toast & Notification Container -->
  <div id="sgt_toast" style="display: none; position: fixed; bottom: 25px; right: 25px; z-index: 99999; background: #0b3b7b; color: #fff; padding: 14px 22px; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); font-size: 13px; font-weight: 700; align-items: center; gap: 10px; max-width: 380px;">
    <i class="fas fa-spinner fa-spin" id="sgt_toast_icon" style="font-size: 18px; color: #ffb703;"></i>
    <span id="sgt_toast_msg">Sedang memproses...</span>
  </div>

  <script>
    // Helper Toast
    function showToast(msg, iconClass = 'fa-spinner fa-spin', duration = 4000) {
      const toast = document.getElementById('sgt_toast');
      const icon = document.getElementById('sgt_toast_icon');
      const text = document.getElementById('sgt_toast_msg');
      if (!toast) return;
      
      icon.className = 'fas ' + iconClass;
      text.innerHTML = msg;
      toast.style.display = 'flex';

      if (duration > 0) {
        setTimeout(function() {
          toast.style.display = 'none';
        }, duration);
      }
    }

    function hideToast() {
      const toast = document.getElementById('sgt_toast');
      if (toast) toast.style.display = 'none';
    }

    // Mendapatkan format yang sedang aktif
    function getActiveFormat() {
      if (document.body.classList.contains('view-format-thermal80')) return 'thermal80';
      if (document.body.classList.contains('view-format-thermal58')) return 'thermal58';
      return 'a4';
    }

    // Mendapatkan element target tiket sesuai mode aktif
    function getTargetElement(targetIdx = 0) {
      const format = getActiveFormat();
      if (format === 'thermal80') {
        return document.getElementById('thermal_80_' + targetIdx);
      } else if (format === 'thermal58') {
        return document.getElementById('thermal_58_' + targetIdx);
      } else {
        return document.getElementById('ticket_sheet_' + targetIdx);
      }
    }

    // Format nomor WhatsApp ke standar internasional 62xxx
    function formatWhatsAppNumber(phone) {
      if (!phone) return '';
      let clean = phone.replace(/[^0-9]/g, '');
      if (clean.startsWith('0')) {
        clean = '62' + clean.substring(1);
      } else if (clean.startsWith('8')) {
        clean = '62' + clean;
      }
      return clean;
    }

    // Format teks pesan WhatsApp resmi dengan tautan publik terverifikasi token
    function buildWhatsAppMessage(data) {
      // Buat URL E-Tiket publik aman
      const currentOrigin = window.location.origin;
      const currentPath = window.location.pathname;
      
      let publicUrl = '';
      if (currentPath.includes('/data_pemesanan/')) {
        const basePath = currentPath.substring(0, currentPath.indexOf('/data_pemesanan/'));
        publicUrl = currentOrigin + basePath + '/e-tiket/pelanggan?id=' + data.id + (data.token ? '&token=' + data.token : '');
      } else {
        publicUrl = currentOrigin + '/e-tiket/pelanggan?id=' + data.id + (data.token ? '&token=' + data.token : '');
      }

      return `*E-TIKET RESMI SINAR GALAXY TRAVEL*\n` +
             `-------------------------------------------\n` +
             `Halo Bapak/Ibu *${data.nama}*,\n` +
             `Terima kasih telah memesan tiket perjalanan bersama *Sinar Galaxy Travel*.\n\n` +
             `*DETAIL TIKET PERJALANAN:*\n` +
             `🎟 *No. Tiket* : *${data.tiket}*\n` +
             `👤 *Penumpang* : ${data.nama}\n` +
             `💺 *No. Kursi* : *KURSI ${data.kursi}*\n` +
             `📍 *Tujuan* : *${data.tujuan}*\n` +
             `🗓 *Jadwal* : ${data.tgl}\n` +
             `⏰ *Waktu* : *${data.jam}*\n` +
             `🚌 *Armada* : ${data.plat}\n` +
             `💰 *Tarif* : ${data.harga}\n` +
             `✅ *Status* : *LUNAS*\n\n` +
             `🔗 *Lihat / Unduh E-Tiket Digital Anda:*\n${publicUrl}\n\n` +
             `_Catatan: Harap tiba di titik keberangkatan 30 menit sebelum jadwal. Simpan pesan dan tiket ini sebagai bukti sah._\n\n` +
             `*Customer Care PO Sumbawa:* 081763333330\n` +
             `*Customer Care PO Mataram:* 082339860600\n` +
             `*Website Resmi:* https://sinargalaxy.my.id`;
    }

    // =========================================================================
    // FITUR 1: KIRIM LANGSUNG KE NOMOR WHATSAPP PELANGGAN
    // =========================================================================
    async function kirimWhatsAppLangsung(targetIdx = 0) {
      const sheet = document.getElementById('ticket_sheet_' + targetIdx);
      if (!sheet) {
        alert('Data tiket tidak ditemukan.');
        return;
      }

      const data = {
        idx: targetIdx,
        id: sheet.dataset.id,
        token: sheet.dataset.token || '',
        nama: sheet.dataset.nama || 'Penumpang',
        phone: sheet.dataset.phone || '',
        tiket: sheet.dataset.tiket || 'SGT',
        tujuan: sheet.dataset.tujuan || '-',
        tgl: sheet.dataset.tgl || '-',
        jam: sheet.dataset.jam || '-',
        kursi: sheet.dataset.kursi || '-',
        plat: sheet.dataset.plat || '-',
        harga: sheet.dataset.harga || '-'
      };

      let waPhone = formatWhatsAppNumber(data.phone);

      // Jika nomor HP kosong atau tidak valid, minta input nomor tujuan
      if (!waPhone || waPhone.length < 8) {
        const inputPrompt = prompt(
          `Nomor WhatsApp untuk penumpang "${data.nama}" belum terisi.\n\nSilakan masukkan nomor WhatsApp tujuan (contoh: 08123456789):`,
          ""
        );
        if (inputPrompt === null) {
          return; // Dibatalkan oleh user
        }
        waPhone = formatWhatsAppNumber(inputPrompt);
        if (!waPhone || waPhone.length < 8) {
          alert("Nomor WhatsApp yang dimasukkan tidak valid. Pengiriman dibatalkan.");
          return;
        }
      }

      const waMessage = buildWhatsAppMessage(data);
      const elem = getTargetElement(targetIdx);

      showToast(`Menyiapkan tiket untuk dikirim ke ${waPhone}...`, 'fa-circle-notch fa-spin', 0);

      try {
        // 1. Render element tiket ke canvas PNG
        const canvas = await html2canvas(elem, {
          scale: 2,
          useCORS: true,
          logging: false,
          backgroundColor: '#ffffff'
        });

        // 2. Download file gambar PNG otomatis ke perangkat (agar kasir/admin siap melampirkan)
        canvas.toBlob(async function(blob) {
          const fileName = `E-Tiket_${data.tiket.replace(/\s+/g, '_')}_${data.nama.replace(/[^a-zA-Z0-9]/g, '_')}.png`;
          
          if (blob) {
            // Salin gambar ke clipboard jika didukung browser modern (bisa langsung Ctrl+V di WA Web)
            try {
              if (navigator.clipboard && window.ClipboardItem) {
                const item = new ClipboardItem({ 'image/png': blob });
                await navigator.clipboard.write([item]);
              }
            } catch (clipErr) {
              console.log('Clipboard write not permitted, skipping:', clipErr);
            }

            // Download file gambar
            const imgUrl = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = imgUrl;
            downloadLink.download = fileName;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
          }

          hideToast();
          showToast(`Membuka WhatsApp ke +${waPhone}... Gambar tiket telah siap!`, 'fa-whatsapp', 4000);

          // 3. Buka WhatsApp secara instan tertuju ke nomor pelanggan
          setTimeout(function() {
            openWhatsAppLink(waPhone, waMessage);
          }, 600);

        }, 'image/png');

      } catch (err) {
        console.error('Error render canvas:', err);
        hideToast();
        openWhatsAppLink(waPhone, waMessage);
      }
    }

    // Alias fungsi lama
    function kirimWhatsAppTiket(targetIdx = 0) {
      kirimWhatsAppLangsung(targetIdx);
    }

    function openWhatsAppLink(phone, message) {
      let waUrl = '';
      const encodedMsg = encodeURIComponent(message);
      if (phone && phone.length >= 8) {
        waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodedMsg}`;
      } else {
        waUrl = `https://api.whatsapp.com/send?text=${encodedMsg}`;
      }
      window.open(waUrl, '_blank');
    }

    // =========================================================================
    // FITUR 2: DOWNLOAD TIKET SEBAGAI GAMBAR PNG RESOLUSI TINGGI
    // =========================================================================
    async function downloadGambarTiket(targetIdx = 0) {
      const sheet = document.getElementById('ticket_sheet_' + targetIdx);
      if (!sheet) {
        alert('Data tiket tidak ditemukan.');
        return;
      }

      const nama = sheet.dataset.nama || 'Penumpang';
      const tiket = sheet.dataset.tiket || 'SGT';
      const elem = getTargetElement(targetIdx);

      showToast('Merender gambar tiket resolusi tinggi...', 'fa-spinner fa-spin', 0);

      try {
        const canvas = await html2canvas(elem, {
          scale: 2.5,
          useCORS: true,
          logging: false,
          backgroundColor: '#ffffff'
        });

        const imgData = canvas.toDataURL('image/png');
        const fileName = `E-Tiket_${tiket.replace(/\s+/g, '_')}_${nama.replace(/[^a-zA-Z0-9]/g, '_')}.png`;

        const link = document.createElement('a');
        link.href = imgData;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        hideToast();
        showToast('Gambar PNG tiket berhasil disimpan!', 'fa-check-circle', 3000);
      } catch (e) {
        console.error('Download image error:', e);
        hideToast();
        alert('Gagal mendownload gambar tiket: ' + e.message);
      }
    }

    // =========================================================================
    // FITUR 3: DOWNLOAD TIKET SEBAGAI DOKUMEN PDF RESMI
    // =========================================================================
    async function downloadPDFTiket(targetIdx = 0) {
      const sheet = document.getElementById('ticket_sheet_' + targetIdx);
      if (!sheet) {
        alert('Data tiket tidak ditemukan.');
        return;
      }

      const nama = sheet.dataset.nama || 'Penumpang';
      const tiket = sheet.dataset.tiket || 'SGT';
      const format = getActiveFormat();
      const elem = getTargetElement(targetIdx);

      showToast('Menyusun file PDF E-Tiket...', 'fa-file-pdf', 0);

      let pdfOptions = {
        margin: [4, 4, 4, 4],
        filename: `E-Tiket_${tiket.replace(/\s+/g, '_')}_${nama.replace(/[^a-zA-Z0-9]/g, '_')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };

      if (format === 'thermal80') {
        pdfOptions.margin = [3, 3, 3, 3];
        pdfOptions.jsPDF = { unit: 'mm', format: [205, 80], orientation: 'landscape' };
      } else if (format === 'thermal58') {
        pdfOptions.margin = [2, 2, 2, 2];
        pdfOptions.jsPDF = { unit: 'mm', format: [58, 200], orientation: 'portrait' };
      }

      try {
        await html2pdf().set(pdfOptions).from(elem).save();
        hideToast();
        showToast('Dokumen PDF berhasil diunduh!', 'fa-check-circle', 3000);
      } catch (err) {
        console.error('PDF error:', err);
        hideToast();
        alert('Gagal membuat PDF: ' + err.message);
      }
    }

    // Fungsi switch format kertas secara interaktif
    function switchFormat(format) {
      document.body.className = 'view-format-' + format;
      
      // Update tombol aktif
      document.querySelectorAll('.format-btn').forEach(function(btn) {
        btn.classList.remove('active');
      });
      if (window.event && window.event.currentTarget) {
        window.event.currentTarget.classList.add('active');
      }

      // Update URL param tanpa reload halaman
      const url = new URL(window.location);
      url.searchParams.set('format', format);
      window.history.replaceState({}, '', url);
    }
  </script>

  <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] == '1' && !empty($tickets)): ?>
    <script>
      window.addEventListener('load', function() {
        setTimeout(function() {
          window.print();
        }, 600);
      });
    </script>
  <?php endif; ?>

</body>
</html>
