<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/../assets/session.php';
require_once __DIR__ . '/../assets/fungsi.php';
require_once __DIR__ . '/../inc/koneksi.php';

// Ambil ID pengiriman paket (bisa single ID atau multiple IDs dipisah koma)
$id_pengiriman = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['id_pengiriman']) ? $_GET['id_pengiriman'] : '');
$format_kertas = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'thermal80'; // thermal80 (default), a4, thermal58

$shipments = [];

if (!empty($id_pengiriman)) {
    // Single or specific comma-separated IDs
    $id_list = array_map('intval', explode(',', $id_pengiriman));
    $id_in = implode(',', array_filter($id_list));
    if (!empty($id_in)) {
        $sql = "SELECT dp.*, 
                       dt.kelas,
                       tj.nama_tujuan,
                       sp.status_pembayaran,
                       mp.metode_pembayaran,
                       dr.nama_rekening, dr.nomor_rekening,
                       du.username as nama_kasir, du.asal_po as asal_po_kasir
                FROM data_pengiriman dp
                LEFT JOIN data_travel dt ON dp.no_plat_id = dt.no_plat
                LEFT JOIN data_tujuan_perjalanan tj ON dp.tujuan_id = tj.id_tujuan_perjalanan
                LEFT JOIN data_status_pembayaran sp ON dp.status_pembayaran_id = sp.id_status_pembayaran
                LEFT JOIN data_metode_pembayaran mp ON dp.metode_pembayaran_id = mp.id_metode_pembayaran
                LEFT JOIN data_rekening dr ON dp.jenis_rekening_id = dr.id_rekening
                LEFT JOIN data_users du ON dp.user_id = du.id_users
                WHERE dp.id_pengiriman IN ($id_in)
                ORDER BY dp.id_pengiriman ASC";
        $query = mysqli_query($conn, $sql);
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $shipments[] = $row;
            }
        }
    }
}

// Data Pendukung Sistem (Metode & Asal PO)
$metodeMap = [];
$resM = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
if ($resM) {
    while ($rm = mysqli_fetch_assoc($resM)) {
        $metodeMap[$rm['id_metode_pembayaran']] = $rm['metode_pembayaran'];
    }
}

$tujuanMap = [];
$resT = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
if ($resT) {
    while ($rt = mysqli_fetch_assoc($resT)) {
        $tujuanMap[$rt['id_tujuan_perjalanan']] = $rt['nama_tujuan'];
    }
}

$usersMap = [];
$resU = mysqli_query($conn, "SELECT id_users, username, asal_po FROM data_users");
if ($resU) {
    while ($ru = mysqli_fetch_assoc($resU)) {
        $usersMap[$ru['id_users']] = $ru;
    }
}

// Asset path
$appLogo = '../img/logo_sgt.png';
if (!file_exists(__DIR__ . '/../img/logo_sgt.png')) {
    $appLogo = 'https://sinargalaxy.my.id/img/logo_sgt.png';
}

$cargoImage = '../img/bus_sgt.jpg';
if (!file_exists(__DIR__ . '/../img/bus_sgt.jpg')) {
    $cargoImage = 'https://sinargalaxy.my.id/img/bus_sgt.jpg';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resi Pengiriman Paket / Cargo - Sinar Galaxy Express</title>
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
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 700;
      font-size: 12.5px;
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

    /* Container Resi Keseluruhan */
    .sheet-wrapper {
      max-width: 960px;
      margin: 0 auto 30px auto;
      background: transparent;
      display: flex;
      flex-direction: column;
      gap: 16px;
      page-break-after: always;
    }

    .sheet-wrapper:last-child {
      page-break-after: auto;
    }

    /* ==========================================================================
       MODE 1: FORMAT A4 / STANDAR FULL COLOR RESMI
       ========================================================================== */
    .resi-card-top {
      background: #ffffff;
      border: 2px solid #0a3871;
      border-radius: 16px;
      overflow: hidden;
      display: flex;
      box-shadow: 0 8px 24px rgba(10, 56, 113, 0.12);
      position: relative;
    }

    .resi-main {
      flex: 1;
      padding: 16px 20px 0 20px;
      display: flex;
      flex-direction: column;
      background: #ffffff;
    }

    .resi-main-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #0a3871;
      padding-bottom: 10px;
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
      line-height: 1.1;
      letter-spacing: -0.5px;
    }

    .brand-subtitle {
      font-family: 'Montserrat', sans-serif;
      font-size: 11px;
      font-weight: 800;
      color: #0b3b7b;
      letter-spacing: 5px;
      margin-top: 1px;
    }

    .brand-tagline {
      font-size: 9.5px;
      color: #4a5568;
      font-weight: 600;
      margin-top: 3px;
    }

    .header-badge-container {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 4px;
    }

    .badge-resi-title {
      background: #0b3b7b;
      color: #ffffff;
      font-family: 'Montserrat', sans-serif;
      font-size: 16px;
      font-weight: 900;
      padding: 6px 20px;
      border-radius: 24px;
      letter-spacing: 0.8px;
      box-shadow: 0 3px 8px rgba(11, 59, 123, 0.25);
      text-transform: uppercase;
    }

    .badge-service-type {
      background: #ffb703;
      color: #000000;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 18px;
      border-radius: 14px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .resi-main-body {
      display: flex;
      gap: 16px;
      padding: 14px 0 10px 0;
      align-items: stretch;
    }

    .cargo-visual-box {
      width: 270px;
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

    .cargo-visual-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .cargo-badge-floating {
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

    .resi-fields-grid {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 5px;
      justify-content: center;
    }

    .resi-field-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .field-icon-bubble {
      width: 25px;
      height: 25px;
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
      font-size: 11.5px;
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
      font-size: 11.5px;
      font-weight: 700;
      color: #0b3b7b;
      min-height: 25px;
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

    .resi-stub {
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
      font-size: 13px;
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
      width: 94px;
      height: 94px;
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
      font-size: 9.5px;
      font-style: italic;
      font-weight: 800;
      color: #0b3b7b;
      text-align: center;
      line-height: 1.2;
    }

    /* SLIP BAWAH A4 */
    .resi-card-bottom {
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

    .bottom-box-icon {
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
      font-size: 9.5px;
      color: #2d3748;
      line-height: 1.45;
      font-weight: 600;
    }

    .terms-list li {
      margin-bottom: 3px;
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
       MODE 2: BOARDING PASS RESI CARGO (THERMAL 80MM / LANDSCAPE SLIP)
       ========================================================================== */
    .thermal-wrapper {
      display: none;
      background: #ffffff;
      margin: 0 auto 30px auto;
      color: #000000;
      page-break-after: always;
    }

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
      gap: 14px;
    }

    .bp-logo-brand {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #0b3b7b;
      font-weight: 900;
      font-size: 16px;
      letter-spacing: 0.5px;
    }

    .bp-logo-brand i {
      font-size: 18px;
      color: #e65100;
    }

    .bp-sub-brand {
      color: #e65100;
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
      color: #0b3b7b;
      font-weight: 900;
      font-size: 12px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .bp-badge-box {
      border: 1px solid #e65100;
      color: #e65100;
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

    .bp-cell-val.highlight-lg {
      font-size: 17px;
      font-weight: 900;
      line-height: 1.1;
      color: #0b3b7b;
    }

    .bp-cell-val.time-highlight {
      font-size: 16px;
      font-weight: 900;
      line-height: 1.1;
      color: #000000;
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

    .bp-stub-section {
      width: 175px;
      padding-left: 10px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-left: 1px dotted #94a3b8;
    }

    .bp-stub-badge-box {
      border: 1px solid #e65100;
      color: #e65100;
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
      font-size: 12px;
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

    .thermal-qr-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      margin: 8px 0;
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
    body.view-format-a4 .sheet-wrapper {
      display: flex !important;
    }
    body.view-format-a4 .thermal-wrapper {
      display: none !important;
    }

    body.view-format-thermal80 .sheet-wrapper {
      display: none !important;
    }
    body.view-format-thermal80 .thermal-wrapper.mode-80 {
      display: block !important;
    }
    body.view-format-thermal80 .thermal-wrapper.mode-58 {
      display: none !important;
    }

    body.view-format-thermal58 .sheet-wrapper {
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

      body.view-format-a4 .sheet-wrapper {
        display: flex !important;
        max-width: 100% !important;
        margin: 0 auto !important;
        padding: 0 !important;
        box-shadow: none !important;
        page-break-after: always !important;
      }

      body.view-format-a4 .resi-card-top,
      body.view-format-a4 .resi-card-bottom {
        box-shadow: none !important;
        border: 2px solid #0a3871 !important;
      }

      /* Print Thermal 80mm Boarding Pass */
      body.view-format-thermal80 {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      body.view-format-thermal80 .thermal-wrapper.mode-80 {
        display: block !important;
        width: 195mm !important;
        max-width: 200mm !important;
        margin: 0 auto !important;
        padding: 3mm 4mm !important;
        border: 1px solid #000000 !important;
        box-shadow: none !important;
        page-break-after: always !important;
        break-inside: avoid !important;
      }

      /* Print Thermal 58mm */
      body.view-format-thermal58 .thermal-wrapper.mode-58 {
        display: block !important;
        width: 56mm !important;
        max-width: 58mm !important;
        margin: 0 auto !important;
        padding: 2mm 1mm !important;
        border: none !important;
        box-shadow: none !important;
        page-break-after: always !important;
        break-inside: avoid !important;
      }

      /* Page Rules */
      body.view-format-a4 @page {
        size: A4 portrait;
        margin: 8mm 6mm;
      }

      body.view-format-thermal80 @page {
        size: 205mm 80mm landscape;
        margin: 2mm 3mm;
      }

      body.view-format-thermal58 @page {
        size: 58mm auto;
        margin: 0;
      }
    }
  </style>
</head>
<body class="view-format-<?= in_array($format_kertas, ['a4', 'thermal58']) ? $format_kertas : 'thermal80' ?>">

  <!-- Floating Action Bar with Format Selector -->
  <div class="action-bar no-print">
    <div style="display: flex; align-items: center; gap: 10px;">
      <span style="font-weight: 800; color: #0b3b7b; font-size: 15px;">
        <i class="fas fa-boxes mr-1" style="color: #e65100;"></i> Resi Pengiriman Cargo
      </span>
      <span style="font-size: 11px; background: #e2e8f0; padding: 2px 7px; border-radius: 10px; font-weight: 700;">
        <?= count($shipments) ?> Resi
      </span>
    </div>

    <!-- Format Selector Buttons -->
    <div style="display: flex; align-items: center; gap: 6px;">
      <span style="font-size: 12px; font-weight: 700; color: #4a5568;"><i class="fas fa-print mr-1"></i> Ukuran:</span>
      <div class="format-switcher-group">
        <button type="button" class="format-btn <?= $format_kertas === 'thermal80' || !in_array($format_kertas, ['a4', 'thermal58']) ? 'active' : '' ?>" onclick="switchFormat('thermal80')">
          <i class="fas fa-ticket-alt"></i> Resi 80mm Cargo
        </button>
        <button type="button" class="format-btn <?= $format_kertas === 'a4' ? 'active' : '' ?>" onclick="switchFormat('a4')">
          <i class="fas fa-file-invoice"></i> A4 Surat Jalan
        </button>
        <button type="button" class="format-btn <?= $format_kertas === 'thermal58' ? 'active' : '' ?>" onclick="switchFormat('thermal58')">
          <i class="fas fa-mobile-alt"></i> Thermal 58mm
        </button>
      </div>
    </div>

    <?php 
      $firstHpPengirim = !empty($shipments[0]['no_hp_id']) ? $shipments[0]['no_hp_id'] : '';
      $firstHpPenerima = !empty($shipments[0]['no_hp_penerima']) ? $shipments[0]['no_hp_penerima'] : '';
      $firstNamaPengirim = !empty($shipments[0]['nama_id']) ? $shipments[0]['nama_id'] : '';
      $firstNamaPenerima = !empty($shipments[0]['nama_penerima']) ? $shipments[0]['nama_penerima'] : '';
    ?>

    <!-- Share & Print Action Buttons -->
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
      <button type="button" onclick="kirimWAPaket(0, 'pengirim')" class="action-btn" style="background: #25D366; color: #ffffff; box-shadow: 0 2px 8px rgba(37, 211, 102, 0.4);" title="Kirim resi ke WhatsApp Pengirim">
        <i class="fab fa-whatsapp"></i> WA Pengirim
      </button>
      <button type="button" onclick="kirimWAPaket(0, 'penerima')" class="action-btn" style="background: #128C7E; color: #ffffff;" title="Kirim info paket ke WhatsApp Penerima">
        <i class="fab fa-whatsapp"></i> WA Penerima
      </button>
      <button type="button" onclick="downloadGambarResi()" class="action-btn" style="background: #0284c7; color: #ffffff;" title="Simpan sebagai gambar PNG">
        <i class="fas fa-file-image"></i> Simpan Gambar
      </button>
      <button type="button" onclick="downloadPDFResi()" class="action-btn" style="background: #dc2626; color: #ffffff;" title="Download dokumen PDF resmi">
        <i class="fas fa-file-pdf"></i> Download PDF
      </button>
      <button type="button" onclick="openPanduanModal()" class="action-btn" style="background: #f59e0b; color: #ffffff;" title="Tips & Panduan Pengaturan Cetak Browser">
        <i class="fas fa-lightbulb"></i> Tips Cetak
      </button>
      <button type="button" onclick="window.print()" class="action-btn btn-print">
        <i class="fas fa-print"></i> Cetak Resi
      </button>
      <button type="button" onclick="window.close()" class="action-btn btn-close-window">
        <i class="fas fa-times"></i> Tutup
      </button>
    </div>
  </div>

  <?php if (empty($shipments)): ?>
    <div style="max-width: 600px; margin: 60px auto; text-align: center; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);" class="no-print">
      <i class="fas fa-box-open" style="font-size: 48px; color: #f59e0b; margin-bottom: 16px;"></i>
      <h3 style="font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 8px;">Data Resi Pengiriman Tidak Ditemukan</h3>
      <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Silakan pilih data pengiriman dari modul Pengiriman Paket.</p>
      <button onclick="window.close()" class="action-btn btn-close-window">Tutup Jendela</button>
    </div>
  <?php else: ?>

    <?php 
    foreach ($shipments as $idx => $s): 
      $idPengiriman = (int)$s['id_pengiriman'];
      $noResi = !empty($s['nip_sgt_id']) ? $s['nip_sgt_id'] : ('SGT-PKG-' . str_pad($idPengiriman, 6, '0', STR_PAD_LEFT));
      $namaPengirim = !empty($s['nama_id']) ? $s['nama_id'] : '-';
      $alamatPengirim = !empty($s['alamat_id']) ? $s['alamat_id'] : '-';
      $noHpPengirim = !empty($s['no_hp_id']) ? $s['no_hp_id'] : '-';
      
      $namaPenerima = !empty($s['nama_penerima']) ? $s['nama_penerima'] : '-';
      $noHpPenerima = !empty($s['no_hp_penerima']) ? $s['no_hp_penerima'] : '-';
      
      $jenisBarang = !empty($s['jenis_barang']) ? $s['jenis_barang'] : 'PAKET / DOKUMEN';
      $keteranganBarang = !empty($s['keterangan']) ? $s['keterangan'] : '-';
      
      $kotaTujuan = !empty($s['nama_tujuan']) ? $s['nama_tujuan'] : ($tujuanMap[$s['tujuan_id']] ?? '-');
      $noPlat = !empty($s['no_plat_id']) ? $s['no_plat_id'] : '-';
      $kelasTravel = !empty($s['kelas']) ? $s['kelas'] : (!empty($s['kelas_id']) ? $s['kelas_id'] : 'EXECUTIVE');

      // Resolusi Kota Asal
      $asalRaw = !empty($s['asal_po_id']) ? $s['asal_po_id'] : (!empty($s['asal_po_kasir']) ? $s['asal_po_kasir'] : '');
      $kotaAsal = 'SUMBAWA';
      if (!empty($asalRaw)) {
          if (is_numeric($asalRaw) && isset($usersMap[(int)$asalRaw])) {
              $kotaAsal = !empty($usersMap[(int)$asalRaw]['asal_po']) ? $usersMap[(int)$asalRaw]['asal_po'] : $usersMap[(int)$asalRaw]['username'];
          } else {
              $kotaAsal = $asalRaw;
          }
      }
      $kotaAsal = preg_replace('/^(PO\s*-\s*|PO\s+)/i', '', trim($kotaAsal));
      $kotaTujuan = preg_replace('/^(PO\s*-\s*|PO\s+)/i', '', trim($kotaTujuan));

      // Parsing Tanggal Kirim
      $tglKirimRaw = $s['tanggal_pengiriman'];
      $timeKirim = strtotime($tglKirimRaw);
      
      $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
      ];
      $bulanIndoShort = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
      ];
      $hariIndo = [
        0 => 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
      ];

      if ($timeKirim) {
        $hariTeks = $hariIndo[date('w', $timeKirim)];
        $tglTeks = date('j', $timeKirim) . ' ' . $bulanIndo[(int)date('n', $timeKirim)] . ' ' . date('Y', $timeKirim);
        $tglTeksSingkat = date('j', $timeKirim) . ' ' . $bulanIndoShort[(int)date('n', $timeKirim)] . ' ' . date('Y', $timeKirim);
        $jamTeks = date('H:i', $timeKirim);
        if ($jamTeks === '00:00') {
          $jamTeks = '14:00';
        }
        $jamTeksDisplay = $jamTeks . ' WITA';
        $tglKirimDisplay = $hariTeks . ', ' . $tglTeks;
        $tglKirimBoardingPass = $tglTeksSingkat;
        $tglKirimShort = date('d/m/Y', $timeKirim) . ' ' . $jamTeks . ' WITA';
      } else {
        $tglKirimDisplay = '-';
        $tglKirimBoardingPass = '-';
        $tglKirimShort = '-';
        $jamTeksDisplay = '14:00 WITA';
      }

      $ongkosKirim = (float)($s['jumlah'] ?? 0);
      $ongkosKirimDisplay = 'Rp. ' . number_format($ongkosKirim, 0, ',', '.');
      $ongkosKirimSimple = 'Rp ' . number_format($ongkosKirim, 0, ',', '.');

      // Informasi Pembayaran
      $metodePembayaran = !empty($s['metode_pembayaran']) ? $s['metode_pembayaran'] : ($metodeMap[$s['metode_pembayaran_id']] ?? 'TUNAI (KASIR)');
      $noRef = !empty($s['nomor_rekening']) ? $s['nomor_rekening'] . ' (' . ($s['nama_rekening'] ?? '') . ')' : '-';
      
      $tglCetak = date('d/m/Y H:i') . ' WITA';
      $tglTransferDisplay = $tglKirimDisplay;

      // Token Keamanan Lacak Publik
      $resiToken = substr(hash_hmac('sha256', $idPengiriman . 'SGT_RESI_SALT_2026', 'sinar_galaxy_secret_key'), 0, 16);
      
      // QR Data
      $qrData = "SINAR GALAXY CARGO | RESI: " . $noResi . " | PENGIRIM: " . $namaPengirim . " | PENERIMA: " . $namaPenerima . " | TUJUAN: " . $kotaTujuan . " | TARIF: " . $ongkosKirimDisplay . " | STATUS: LUNAS";
    ?>

    <!-- ====================================================================
         1. TEMPLATE FORMAT A4 (SURAT JALAN & RESI CARGO RESMI)
         ==================================================================== -->
    <div class="sheet-wrapper" id="shipment_sheet_<?= $idx ?>" 
         data-idx="<?= $idx ?>" 
         data-id="<?= $idPengiriman ?>" 
         data-resi="<?= htmlspecialchars($noResi) ?>" 
         data-token="<?= htmlspecialchars($resiToken) ?>" 
         data-pengirim="<?= htmlspecialchars($namaPengirim) ?>" 
         data-hppengirim="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $noHpPengirim)) ?>" 
         data-penerima="<?= htmlspecialchars($namaPenerima) ?>" 
         data-hppenerima="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $noHpPenerima)) ?>" 
         data-tujuan="<?= htmlspecialchars($kotaTujuan) ?>" 
         data-asal="<?= htmlspecialchars($kotaAsal) ?>" 
         data-barang="<?= htmlspecialchars($jenisBarang) ?>" 
         data-tgl="<?= htmlspecialchars($tglKirimDisplay) ?>" 
         data-jam="<?= htmlspecialchars($jamTeksDisplay) ?>" 
         data-plat="<?= htmlspecialchars($noPlat) ?>" 
         data-tarif="<?= htmlspecialchars($ongkosKirimDisplay) ?>">
      
      <!-- SLIP 1: RESI UTAMA (ATAS) -->
      <div class="resi-card-top">
        <div class="resi-main">
          
          <div class="resi-main-header">
            <div class="brand-group">
              <img src="<?= $appLogo ?>" alt="Logo Sinar Galaxy" class="brand-logo-img">
              <div class="brand-text">
                <div class="brand-title">SINAR GALAXY</div>
                <div class="brand-subtitle">EXPRESS & CARGO</div>
                <div class="brand-tagline">Jasa Pengiriman Cepat, Aman & Terpercaya</div>
              </div>
            </div>

            <div class="header-badge-container">
              <div class="badge-resi-title">RESI PENGIRIMAN</div>
              <div class="badge-service-type">CARGO LOGISTICS</div>
            </div>
          </div>

          <div class="resi-main-body">
            <div class="cargo-visual-box">
              <img src="<?= $cargoImage ?>" alt="Armada Sinar Galaxy">
              <div class="cargo-badge-floating">
                <i class="fas fa-truck-loading mr-1"></i> <?= htmlspecialchars($noPlat) ?> (<?= htmlspecialchars($kelasTravel) ?>)
              </div>
            </div>

            <div class="resi-fields-grid">
              <div class="resi-field-row">
                <div class="field-icon-bubble"><i class="fas fa-user"></i></div>
                <div class="field-label">Nama Pengirim</div>
                <div class="field-separator">:</div>
                <div class="field-value-box">
                  <?= htmlspecialchars($namaPengirim) ?> 
                  <?= (!empty($noHpPengirim) && $noHpPengirim !== '-') ? ' (' . htmlspecialchars($noHpPengirim) . ')' : '' ?>
                </div>
              </div>

              <div class="resi-field-row">
                <div class="field-icon-bubble"><i class="fas fa-user-check"></i></div>
                <div class="field-label">Nama Penerima</div>
                <div class="field-separator">:</div>
                <div class="field-value-box" style="font-weight: 800; color: #0b3b7b;">
                  <?= htmlspecialchars($namaPenerima) ?>
                  <?= (!empty($noHpPenerima) && $noHpPenerima !== '-') ? ' (' . htmlspecialchars($noHpPenerima) . ')' : '' ?>
                </div>
              </div>

              <div class="resi-field-row">
                <div class="field-icon-bubble"><i class="fas fa-box"></i></div>
                <div class="field-label">Jenis Barang</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars(strtoupper($jenisBarang)) ?></div>
              </div>

              <div class="resi-field-row">
                <div class="field-icon-bubble"><i class="fas fa-map-marker-alt"></i></div>
                <div class="field-label">Kota Tujuan</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars(strtoupper($kotaTujuan)) ?> (DARI: <?= htmlspecialchars(strtoupper($kotaAsal)) ?>)</div>
              </div>

              <div class="resi-field-row">
                <div class="field-icon-bubble"><i class="fas fa-calendar-alt"></i></div>
                <div class="field-label">Tanggal Pengiriman</div>
                <div class="field-separator">:</div>
                <div class="field-value-box"><?= htmlspecialchars($tglKirimDisplay) ?> (<?= htmlspecialchars($jamTeksDisplay) ?>)</div>
              </div>

              <div class="resi-field-row">
                <div class="field-icon-bubble"><i class="fas fa-money-bill-wave"></i></div>
                <div class="field-label">Ongkos Kirim</div>
                <div class="field-separator">:</div>
                <div class="field-value-box price-highlight"><?= htmlspecialchars($ongkosKirimDisplay) ?> (LUNAS)</div>
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
              Kirim Cepat Sampai Tujuan Dengan Selamat
            </div>
          </div>
        </div>

        <!-- Sisi Kanan (Stub Sobek Pengirim / Penerima) -->
        <div class="resi-stub">
          <div class="stub-header">
            <div class="stub-header-title">No. Resi Pengiriman</div>
            <div class="stub-ticket-no-pill"><?= htmlspecialchars($noResi) ?></div>
          </div>

          <div class="stub-body">
            <div class="stub-brand-mini">
              <img src="<?= $appLogo ?>" alt="Logo" style="height: 24px; margin-bottom: 2px;">
              <div class="stub-brand-title">SINAR GALAXY</div>
              <div class="stub-brand-sub">CARGO</div>
            </div>

            <div class="stub-qr-box" id="qrcode_a4_<?= $idx ?>"></div>

            <div class="stub-footer-note">
              Simpan Resi Anda<br>Untuk Pengambilan Paket
            </div>
          </div>
        </div>
      </div>

      <!-- SLIP 2: INFORMASI PEMBAYARAN & KETENTUAN CARGO (BAWAH) -->
      <div class="resi-card-bottom">
        <div class="bottom-col-brand">
          <div class="bottom-brand-header">
            <div class="brand-group" style="gap: 8px;">
              <img src="<?= $appLogo ?>" alt="Logo" style="height: 36px;">
              <div class="brand-text">
                <div class="brand-title" style="font-size: 15px;">SINAR GALAXY</div>
                <div class="brand-subtitle" style="font-size: 8px; letter-spacing: 4px;">EXPRESS</div>
              </div>
            </div>
            <div class="bottom-pill-badge">BUKTI RESI PENGIRIMAN</div>
          </div>

          <div class="bottom-thanks-box">
            <div class="bottom-box-icon"><i class="fas fa-shipping-fast"></i></div>
            <div class="bottom-thanks-text">
              Terima Kasih<br>Telah Mempercayakan<br>Pengiriman Kepada Kami
            </div>
          </div>

          <div class="bottom-route-strip">
            <i class="fas fa-map-marker-alt"></i> Ekspedisi & Titipan Paket Antar Kota / Provinsi
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
            <div class="bfield-label">No. Referensi / Transaksi</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($noRef) ?></div>
          </div>

          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-check-circle text-success"></i></div>
            <div class="bfield-label">Status Pembayaran</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box" style="color: #0d8a43; font-weight: 800;">LUNAS (TERBAYAR)</div>
          </div>

          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="bfield-label">Tanggal Transaksi</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($tglTransferDisplay) ?></div>
          </div>
        </div>

        <div class="bottom-col-terms">
          <div>
            <div class="terms-title">
              <i class="fas fa-shield-alt text-danger"></i> KETENTUAN EKSPEDISI
            </div>

            <ol class="terms-list">
              <li>Resi ini merupakan tanda bukti pengiriman & pembayaran sah.</li>
              <li>Penerima wajib menunjukkan nomor resi saat pengambilan barang di pool/loket.</li>
              <li>Barang terlarang & berbahaya (narkoba, senjata, bahan peledak) dilarang dikirim.</li>
              <li>Kehilangan/kerusakan akibat kelalaian pembungkusan bukan tanggung jawab ekspedisi.</li>
            </ol>
          </div>

          <div class="terms-footer-brand">
            <div class="tf-logo-title"><i class="fas fa-star mr-1"></i> Sinar Galaxy Cargo</div>
            <div class="tf-slogan">Cepat, Tepat & Terpercaya</div>
          </div>
        </div>
      </div>

    </div>


    <!-- ====================================================================
         2. TEMPLATE THERMAL 80MM (CARGO BOARDING PASS 80MM - IDENTIK MODERN)
         ==================================================================== -->
    <div class="thermal-wrapper mode-80" id="thermal_80_<?= $idx ?>"
         data-shipment-idx="<?= $idx ?>" 
         data-id="<?= $idPengiriman ?>" 
         data-resi="<?= htmlspecialchars($noResi) ?>" 
         data-token="<?= htmlspecialchars($resiToken) ?>" 
         data-pengirim="<?= htmlspecialchars($namaPengirim) ?>" 
         data-hppengirim="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $noHpPengirim)) ?>" 
         data-penerima="<?= htmlspecialchars($namaPenerima) ?>" 
         data-hppenerima="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $noHpPenerima)) ?>" 
         data-tujuan="<?= htmlspecialchars($kotaTujuan) ?>" 
         data-asal="<?= htmlspecialchars($kotaAsal) ?>" 
         data-barang="<?= htmlspecialchars($jenisBarang) ?>" 
         data-tgl="<?= htmlspecialchars($tglKirimDisplay) ?>" 
         data-jam="<?= htmlspecialchars($jamTeksDisplay) ?>" 
         data-plat="<?= htmlspecialchars($noPlat) ?>" 
         data-tarif="<?= htmlspecialchars($ongkosKirimDisplay) ?>">
      
      <!-- Cargo Header -->
      <div class="bp-header">
        <div class="bp-header-left">
          <div class="bp-logo-brand">
            <i class="fas fa-box-open"></i>
            <span>Sinar Galaxy</span>
          </div>
          <div class="bp-sub-brand">
            Express & Cargo
          </div>
        </div>

        <div class="bp-header-right">
          <div class="bp-group-brand">
            <span>Sinar Galaxy Group</span>
            <i class="fas fa-crown" style="font-size: 10px;"></i>
          </div>
          <div class="bp-badge-box">
            CARGO PASS
          </div>
        </div>
      </div>

      <!-- Cargo Body -->
      <div class="bp-body">
        
        <!-- Sisi Kiri (Main Section) -->
        <div class="bp-main-section">
          <div class="bp-grid-info">
            
            <!-- Row 1: Pengirim, Tanggal Kirim, Jenis Barang -->
            <div class="bp-cell">
              <span class="bp-cell-label">PENGIRIM (SHIPPER)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars(strtoupper($namaPengirim)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">TANGGAL (DATE)</span>
              <span class="bp-cell-val"><?= htmlspecialchars($tglKirimBoardingPass) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">JENIS BARANG</span>
              <span class="bp-cell-val bold-lg" style="color: #0b3b7b; font-size: 12.5px;"><?= htmlspecialchars(strtoupper($jenisBarang)) ?></span>
            </div>

            <!-- Row 2: Dari, Jam Kirim, Penerima -->
            <div class="bp-cell">
              <span class="bp-cell-label">DARI (ORIGIN)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars(strtoupper($kotaAsal)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">JAM KIRIM (TIME)</span>
              <span class="bp-cell-val time-highlight"><?= htmlspecialchars($jamTeksDisplay) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">PENERIMA (CONSIGNEE)</span>
              <span class="bp-cell-val highlight-lg"><?= htmlspecialchars(strtoupper($namaPenerima)) ?></span>
            </div>

            <!-- Row 3: Tujuan, Armada Plat, No Resi -->
            <div class="bp-cell">
              <span class="bp-cell-label">TUJUAN (DESTINATION)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars(strtoupper($kotaTujuan)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">ARMADA / PLAT BUS</span>
              <span class="bp-cell-val"><?= htmlspecialchars(strtoupper($noPlat)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">NO. RESI (AWB)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars($noResi) ?></span>
            </div>

            <!-- Row 4: No HP Penerima, Ongkos Kirim & Status, Loket Asal -->
            <div class="bp-cell">
              <span class="bp-cell-label">NO. HP PENERIMA</span>
              <span class="bp-cell-val"><?= htmlspecialchars($noHpPenerima) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">ONGKOS KIRIM & STATUS</span>
              <span class="bp-cell-val" style="color: #0d8a43; font-weight: 900;"><?= htmlspecialchars($ongkosKirimDisplay) ?> (LUNAS)</span>
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
                <span class="bp-pay-lbl">TGL BAYAR:</span>
                <span class="bp-pay-val"><?= htmlspecialchars($tglTransferDisplay) ?></span>
              </div>
              <div class="bp-pay-item">
                <span class="bp-pay-lbl">STATUS:</span>
                <span class="bp-pay-val" style="color: #0d8a43; font-weight: 900;">LUNAS</span>
              </div>
            </div>
          </div>

          <!-- Bottom Main: Barcode 1D + Red Warning Notes -->
          <div class="bp-barcode-bottom-row">
            <svg id="barcode_th80_<?= $idx ?>" class="bp-barcode-img"></svg>
            <div class="bp-warning-text">
              TUNJUKKAN RESI INI SAAT PENGAMBILAN BARANG DI LOKET TUJUAN<br>
              SIMPAN RESI INI SEBAGAI BUKTI PEMBAYARAN & TITIPAN SAH
            </div>
          </div>
        </div>

        <!-- Pemisah Vertikal (Strip Barcode / Divider) -->
        <div class="bp-vertical-strip">
          <div class="bp-strip-barcode-container">
            <div id="qrcode_vert_<?= $idx ?>"></div>
          </div>
        </div>

        <!-- Sisi Kanan (Passenger / Consignee Stub) -->
        <div class="bp-stub-section">
          <div class="bp-stub-badge-box">
            RESI CARGO
          </div>

          <div class="bp-stub-grid">
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">PENERIMA</span>
              <span class="bp-stub-val" style="font-size: 8.5px; font-weight: 900;"><?= htmlspecialchars(strtoupper($namaPenerima)) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">PENGIRIM</span>
              <span class="bp-stub-val" style="font-size: 8.5px;"><?= htmlspecialchars(strtoupper($namaPengirim)) ?></span>
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
              <span class="bp-stub-val"><?= htmlspecialchars($tglKirimBoardingPass) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">BARANG</span>
              <span class="bp-stub-val" style="font-size: 8px;"><?= htmlspecialchars(strtoupper($jenisBarang)) ?></span>
            </div>
            <div class="bp-stub-row">
              <span class="bp-stub-lbl">NO. RESI</span>
              <span class="bp-stub-val"><?= htmlspecialchars($noResi) ?></span>
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
         data-shipment-idx="<?= $idx ?>" 
         data-id="<?= $idPengiriman ?>">
      <div class="thermal-header">
        <div class="th-brand">SINAR GALAXY</div>
        <div class="th-sub">EXPRESS & CARGO LOGISTICS</div>
        <div class="th-contact">081763333330 | 082339860600</div>
      </div>

      <div class="thermal-double-divider"></div>
      <div class="th-title-badge">RESI PENGIRIMAN</div>
      <div class="thermal-divider"></div>

      <table class="thermal-table">
        <tr>
          <td style="width: 32%; font-weight: bold;">No.Resi</td>
          <td style="width: 4%;">:</td>
          <td style="width: 64%; font-weight: bold;"><?= htmlspecialchars($noResi) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Pengirim</td>
          <td>:</td>
          <td><?= htmlspecialchars($namaPengirim) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Penerima</td>
          <td>:</td>
          <td style="font-weight: 900;"><?= htmlspecialchars($namaPenerima) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">No.HP Pnr</td>
          <td>:</td>
          <td><?= htmlspecialchars($noHpPenerima) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Barang</td>
          <td>:</td>
          <td style="font-weight: bold;"><?= htmlspecialchars($jenisBarang) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Dari</td>
          <td>:</td>
          <td><?= htmlspecialchars($kotaAsal) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Tujuan</td>
          <td>:</td>
          <td style="font-weight: bold;"><?= htmlspecialchars($kotaTujuan) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Tgl Kirim</td>
          <td>:</td>
          <td><?= htmlspecialchars($tglKirimShort) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Armada</td>
          <td>:</td>
          <td><?= htmlspecialchars($noPlat) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Pembayaran</td>
          <td>:</td>
          <td><?= htmlspecialchars($metodePembayaran) ?><?= !empty($noRef) && $noRef !== '-' ? ' (' . htmlspecialchars($noRef) . ')' : '' ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Tarif</td>
          <td>:</td>
          <td style="font-weight: 900;"><?= htmlspecialchars($ongkosKirimSimple) ?></td>
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
        Tunjukkan resi saat ambil barang.<br>
        Dicetak: <?= $tglCetak ?><br>
        * Pengiriman Cepat & Terpercaya *
      </div>
    </div>


    <script>
      // Generate QR Code & Barcode
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
            JsBarcode("#barcode_th80_<?= $idx ?>", "<?= $noResi ?>", {
              format: "CODE128",
              width: 1.4,
              height: 30,
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
              height: 36,
              colorDark : "#000000",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.L
            });
          } catch(e) {
            qrVert.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=36x36&data=<?= urlencode($qrData) ?>" alt="QR">';
          }
        }

        // 4. QR Code Thermal 58mm
        var qr58 = document.getElementById("qrcode_th58_<?= $idx ?>");
        if (qr58) {
          try {
            new QRCode(qr58, {
              text: qrText,
              width: 90,
              height: 90,
              colorDark : "#000000",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.M
            });
          } catch(e) {
            qr58.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?= urlencode($qrData) ?>" alt="QR">';
          }
        }
      })();
    </script>

    <?php endforeach; ?>

  <?php endif; ?>

  <!-- Toast Notification Container -->
  <div id="sgt_toast" style="display: none; position: fixed; bottom: 30px; right: 30px; background: #0b3b7b; color: #ffffff; padding: 14px 22px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); z-index: 99999; font-size: 13px; font-weight: 700; align-items: center; gap: 10px; border-left: 5px solid #25D366;">
    <i id="sgt_toast_icon" class="fas fa-info-circle text-warning"></i>
    <span id="sgt_toast_msg">Notifikasi</span>
  </div>

  <script>
    function showToast(msg, iconClass = 'fa-info-circle', duration = 3500) {
      const toast = document.getElementById('sgt_toast');
      const toastMsg = document.getElementById('sgt_toast_msg');
      const toastIcon = document.getElementById('sgt_toast_icon');
      if (!toast) return;

      toastMsg.innerText = msg;
      toastIcon.className = 'fas ' + iconClass;
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

    // Mendapatkan element target resi sesuai mode aktif
    function getTargetElement(targetIdx = 0) {
      const format = getActiveFormat();
      if (format === 'thermal80') {
        return document.getElementById('thermal_80_' + targetIdx);
      } else if (format === 'thermal58') {
        return document.getElementById('thermal_58_' + targetIdx);
      } else {
        return document.getElementById('shipment_sheet_' + targetIdx);
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

    // Format teks pesan WhatsApp resmi resi pengiriman
    function buildWhatsAppPaketMessage(data, targetRole = 'pengirim') {
      const currentOrigin = window.location.origin;
      const currentPath = window.location.pathname;
      
      let publicUrl = '';
      if (currentPath.includes('/data_pengiriman/')) {
        const basePath = currentPath.substring(0, currentPath.indexOf('/data_pengiriman/'));
        publicUrl = currentOrigin + basePath + '/e-resi/pelanggan?id=' + data.id + (data.token ? '&token=' + data.token : '');
      } else {
        publicUrl = currentOrigin + '/e-resi/pelanggan?id=' + data.id + (data.token ? '&token=' + data.token : '');
      }

      if (targetRole === 'penerima') {
        return `*RESI PENGIRIMAN PAKET SINAR GALAXY CARGO*\n` +
               `-------------------------------------------\n` +
               `Halo Bapak/Ibu *${data.penerima}*,\n` +
               `Ada titipan paket kiriman dari *${data.pengirim}* yang sedang dalam perjalanan bersama *Sinar Galaxy Express*.\n\n` +
               `*DETAIL PENGIRIMAN:* \n` +
               `📦 *No. Resi* : *${data.resi}*\n` +
               `🏷 *Jenis Barang* : ${data.barang}\n` +
               `📍 *Rute* : ${data.asal} ➔ *${data.tujuan}*\n` +
               `🗓 *Tgl Kirim* : ${data.tgl} (${data.jam})\n` +
               `🚌 *Armada* : ${data.plat}\n` +
               `✅ *Status* : *LUNAS (TERBAYAR)*\n\n` +
               `🔗 *Lacak / Lihat Resi Digital Resmi:*\n${publicUrl}\n\n` +
               `_Catatan: Harap bawa kartu identitas / tunjukkan resi saat pengambilan di pool/loket tujuan._\n\n` +
               `*Layanan Pelanggan PO Sumbawa:* 081763333330\n` +
               `*Layanan Pelanggan PO Mataram:* 082339860600\n` +
               `*Website:* https://sinargalaxy.my.id`;
      } else {
        return `*BUKTI RESI PENGIRIMAN SINAR GALAXY EXPRESS*\n` +
               `-------------------------------------------\n` +
               `Halo Bapak/Ibu *${data.pengirim}*,\n` +
               `Terima kasih telah mempercayakan pengiriman paket Anda kepada *Sinar Galaxy Cargo*.\n\n` +
               `*DETAIL RESI PENGIRIMAN:* \n` +
               `📦 *No. Resi* : *${data.resi}*\n` +
               `👤 *Penerima* : *${data.penerima}*\n` +
               `🏷 *Barang* : ${data.barang}\n` +
               `📍 *Tujuan* : *${data.tujuan}*\n` +
               `🗓 *Jadwal* : ${data.tgl} (${data.jam})\n` +
               `💰 *Ongkos Kirim* : ${data.tarif}\n` +
               `✅ *Status* : *LUNAS*\n\n` +
               `🔗 *Cek & Unduh Resi Digital Anda:*\n${publicUrl}\n\n` +
               `*Layanan Pelanggan PO Sumbawa:* 081763333330\n` +
               `*Layanan Pelanggan PO Mataram:* 082339860600\n` +
               `*Website Resmi:* https://sinargalaxy.my.id`;
      }
    }

    // =========================================================================
    // FITUR 1: KIRIM RESI VIA WHATSAPP (PENGIRIM ATAU PENERIMA)
    // =========================================================================
    async function kirimWAPaket(targetIdx = 0, targetRole = 'pengirim') {
      const sheet = document.getElementById('shipment_sheet_' + targetIdx);
      if (!sheet) {
        alert('Data pengiriman tidak ditemukan.');
        return;
      }

      const data = {
        idx: targetIdx,
        id: sheet.dataset.id,
        token: sheet.dataset.token || '',
        resi: sheet.dataset.resi || 'SGT-PKG',
        pengirim: sheet.dataset.pengirim || 'Pengirim',
        hppengirim: sheet.dataset.hppengirim || '',
        penerima: sheet.dataset.penerima || 'Penerima',
        hppenerima: sheet.dataset.hppenerima || '',
        tujuan: sheet.dataset.tujuan || '-',
        asal: sheet.dataset.asal || '-',
        barang: sheet.dataset.barang || '-',
        tgl: sheet.dataset.tgl || '-',
        jam: sheet.dataset.jam || '-',
        plat: sheet.dataset.plat || '-',
        tarif: sheet.dataset.tarif || '-'
      };

      const rawPhone = (targetRole === 'penerima') ? data.hppenerima : data.hppengirim;
      const targetName = (targetRole === 'penerima') ? data.penerima : data.pengirim;
      let waPhone = formatWhatsAppNumber(rawPhone);

      if (!waPhone || waPhone.length < 8) {
        const inputPrompt = prompt(
          `Nomor WhatsApp untuk ${targetRole} "${targetName}" belum terisi.\n\nSilakan masukkan nomor WhatsApp tujuan (contoh: 08123456789):`,
          ""
        );
        if (inputPrompt === null) {
          return;
        }
        waPhone = formatWhatsAppNumber(inputPrompt);
        if (!waPhone || waPhone.length < 8) {
          alert("Nomor WhatsApp yang dimasukkan tidak valid. Pengiriman dibatalkan.");
          return;
        }
      }

      const waMessage = buildWhatsAppPaketMessage(data, targetRole);
      const elem = getTargetElement(targetIdx);

      showToast(`Menyiapkan resi untuk dikirim ke ${waPhone}...`, 'fa-circle-notch fa-spin', 0);

      try {
        const canvas = await html2canvas(elem, {
          scale: 2,
          useCORS: true,
          logging: false,
          backgroundColor: '#ffffff'
        });

        canvas.toBlob(async function(blob) {
          const fileName = `Resi_${data.resi.replace(/\s+/g, '_')}_${targetName.replace(/[^a-zA-Z0-9]/g, '_')}.png`;
          
          if (blob) {
            try {
              if (navigator.clipboard && window.ClipboardItem) {
                const item = new ClipboardItem({ 'image/png': blob });
                await navigator.clipboard.write([item]);
              }
            } catch (clipErr) {
              console.log('Clipboard error:', clipErr);
            }

            const imgUrl = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = imgUrl;
            downloadLink.download = fileName;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
          }

          hideToast();
          showToast(`Membuka WhatsApp ke +${waPhone}... Gambar resi telah siap!`, 'fa-whatsapp', 4000);

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
    // FITUR 2: DOWNLOAD GAMBAR RESI PNG RESOLUSI TINGGI
    // =========================================================================
    async function downloadGambarResi(targetIdx = 0) {
      const sheet = document.getElementById('shipment_sheet_' + targetIdx);
      if (!sheet) {
        alert('Data resi tidak ditemukan.');
        return;
      }

      const pengirim = sheet.dataset.pengirim || 'Pengirim';
      const resi = sheet.dataset.resi || 'SGT-PKG';
      const elem = getTargetElement(targetIdx);

      showToast('Merender gambar resi resolusi tinggi...', 'fa-spinner fa-spin', 0);

      try {
        const canvas = await html2canvas(elem, {
          scale: 2.5,
          useCORS: true,
          logging: false,
          backgroundColor: '#ffffff'
        });

        const imgData = canvas.toDataURL('image/png');
        const fileName = `Resi_${resi.replace(/\s+/g, '_')}_${pengirim.replace(/[^a-zA-Z0-9]/g, '_')}.png`;

        const link = document.createElement('a');
        link.href = imgData;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        hideToast();
        showToast('Gambar PNG resi berhasil disimpan!', 'fa-check-circle', 3000);
      } catch (e) {
        console.error('Download image error:', e);
        hideToast();
        alert('Gagal mendownload gambar resi: ' + e.message);
      }
    }

    // =========================================================================
    // FITUR 3: DOWNLOAD DOKUMEN PDF RESMI
    // =========================================================================
    async function downloadPDFResi(targetIdx = 0) {
      const sheet = document.getElementById('shipment_sheet_' + targetIdx);
      if (!sheet) {
        alert('Data resi tidak ditemukan.');
        return;
      }

      const pengirim = sheet.dataset.pengirim || 'Pengirim';
      const resi = sheet.dataset.resi || 'SGT-PKG';
      const format = getActiveFormat();
      const elem = getTargetElement(targetIdx);

      showToast('Menyusun file PDF Resi Cargo...', 'fa-file-pdf', 0);

      let pdfOptions = {
        margin: [4, 4, 4, 4],
        filename: `Resi_${resi.replace(/\s+/g, '_')}_${pengirim.replace(/[^a-zA-Z0-9]/g, '_')}.pdf`,
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

    // Switch Format Interaktif
    function switchFormat(format) {
      document.body.className = 'view-format-' + format;
      
      document.querySelectorAll('.format-btn').forEach(function(btn) {
        btn.classList.remove('active');
      });
      if (window.event && window.event.currentTarget) {
        window.event.currentTarget.classList.add('active');
      }

      const url = new URL(window.location);
      url.searchParams.set('format', format);
      window.history.replaceState({}, '', url);
    }

    // Modal Panduan Cetak
    function openPanduanModal() {
      const m = document.getElementById('modalPanduanCetak');
      if (m) m.style.display = 'flex';
    }

    function closePanduanModal() {
      const m = document.getElementById('modalPanduanCetak');
      if (m) m.style.display = 'none';
    }
  </script>

  <!-- Modal Panduan Tips Cetak Browser -->
  <div id="modalPanduanCetak" class="no-print" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.65); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; width: 92%; max-width: 620px; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.25);">
      <div style="background: linear-gradient(135deg, #0b3b7b 0%, #1e5bb0 100%); color: #ffffff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 16px;">
          <i class="fas fa-lightbulb" style="color: #fbbf24; font-size: 20px;"></i>
          <span>Tips & Panduan Pengaturan Cetak Resi Cargo</span>
        </div>
        <button type="button" onclick="closePanduanModal()" style="background: transparent; border: none; color: #ffffff; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
      </div>
      
      <div style="padding: 20px; max-height: 75vh; overflow-y: auto; font-size: 13px; line-height: 1.6; color: #334155;">
        
        <div style="margin-bottom: 14px; padding: 12px 14px; background: #f0fdf4; border-left: 4px solid #22c55e; border-radius: 8px;">
          <div style="color: #166534; font-size: 13.5px; font-weight: 800; margin-bottom: 4px;">
            <i class="fas fa-ticket-alt mr-1"></i> 1. Format Resi 80mm Cargo (Rekomendasi)
          </div>
          <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
            <tr><td style="width: 38%; font-weight: 700;">Layout / Orientasi</td><td>: <strong>Landscape (Mendatar)</strong></td></tr>
            <tr><td style="font-weight: 700;">Ukuran Kertas (Paper Size)</td><td>: <strong>80mm Roll</strong> / <strong>User Defined (200 x 80 mm)</strong></td></tr>
            <tr><td style="font-weight: 700;">Margin (Margins)</td><td>: <strong>None</strong> atau <strong>Minimum (0 - 2mm)</strong></td></tr>
            <tr><td style="font-weight: 700;">Skala (Scale)</td><td>: <strong>100%</strong> atau <strong>Fit to Printable Area</strong></td></tr>
            <tr><td style="font-weight: 700;">Grafik Latar Belakang</td><td>: <span style="color: #15803d; font-weight: 800;">✓ Centang (Background graphics)</span></td></tr>
          </table>
        </div>

        <div style="margin-bottom: 14px; padding: 12px 14px; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 8px;">
          <div style="color: #1e40af; font-size: 13.5px; font-weight: 800; margin-bottom: 4px;">
            <i class="fas fa-file-invoice mr-1"></i> 2. Format A4 Surat Jalan Resmi
          </div>
          <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
            <tr><td style="width: 38%; font-weight: 700;">Layout / Orientasi</td><td>: <strong>Portrait (Tegak)</strong></td></tr>
            <tr><td style="font-weight: 700;">Ukuran Kertas</td><td>: <strong>A4</strong></td></tr>
            <tr><td style="font-weight: 700;">Margin</td><td>: <strong>Default</strong> atau <strong>Minimum</strong></td></tr>
            <tr><td style="font-weight: 700;">Grafik Latar Belakang</td><td>: <span style="color: #15803d; font-weight: 800;">✓ Centang (Background graphics)</span></td></tr>
          </table>
        </div>

        <div style="padding: 12px 14px; background: #fefce8; border-left: 4px solid #eab308; border-radius: 8px;">
          <div style="color: #854d0e; font-size: 13.5px; font-weight: 800; margin-bottom: 4px;">
            <i class="fas fa-mobile-alt mr-1"></i> 3. Format Mini Thermal 58mm
          </div>
          <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
            <tr><td style="width: 38%; font-weight: 700;">Layout / Orientasi</td><td>: <strong>Portrait (Tegak)</strong></td></tr>
            <tr><td style="font-weight: 700;">Ukuran Kertas</td><td>: <strong>58mm Roll</strong></td></tr>
            <tr><td style="font-weight: 700;">Margin</td><td>: <strong>None (0mm)</strong></td></tr>
          </table>
        </div>

        <div style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 8px;">
          <button type="button" onclick="closePanduanModal()" class="action-btn btn-close-window" style="padding: 8px 16px; font-size: 12.5px;">Tutup</button>
          <button type="button" onclick="closePanduanModal(); window.print();" class="action-btn btn-print" style="padding: 8px 18px; font-size: 12.5px;">
            <i class="fas fa-print mr-1"></i> Buka Dialog Cetak
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] == '1' && !empty($shipments)): ?>
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
