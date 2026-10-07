<?php
/**
 * ==============================================================================
 * E-TIKET PUBLIK PENUMPANG - SINAR GALAXY TRAVEL
 * Akses Publik Aman Khusus Penumpang Tanpa Memerlukan Login Admin
 * URL Contoh: https://sinargalaxy.my.id/e-tiket/pelanggan?id=31297&token=a8f9c1e7...
 * ==============================================================================
 */

require_once __DIR__ . '/../inc/koneksi.php';
require_once __DIR__ . '/../assets/fungsi.php';

$id_pemesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$format_kertas = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'thermal80';

// Cek apakah user sedang login sebagai admin internal (bisa bypass token jika session ada)
$isAdminLoggedIn = false;
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!empty($_SESSION['user']['id_users'])) {
    $isAdminLoggedIn = true;
}

$isValid = false;
$ticket = null;

if ($id_pemesanan > 0) {
    // Validasi token atau admin session
    if ($isAdminLoggedIn || validateTiketToken($id_pemesanan, $token)) {
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
                WHERE pm.id_pemesanan = $id_pemesanan
                LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            $ticket = mysqli_fetch_assoc($res);
            $isValid = true;
        }
    }
}

// Logo & Image Assets
$appLogo = '../img/logo_sgt.png';
$busImage = '../img/travel_vip.jpg';
if (!file_exists(__DIR__ . '/' . $busImage)) {
    $busImage = '../img/travel_ekonomi.jpg';
}

// Master mapping jika dibutuhkan fallback
$tujuanMap = [];
$res = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan']; } }

$usersMap = [];
$res = mysqli_query($conn, "SELECT id_users, username, asal_po FROM data_users");
if ($res) { 
    while ($r = mysqli_fetch_assoc($res)) { 
        $usersMap[$r['id_users']] = !empty($r['asal_po']) ? $r['asal_po'] : $r['username']; 
    } 
}

// Format Data Tiket jika valid
if ($isValid && $ticket) {
    $idPemesanan = (int)$ticket['id_pemesanan'];
    $noTiket = 'SGT ' . str_pad($idPemesanan, 6, '0', STR_PAD_LEFT);
    $namaPenumpang = !empty($ticket['nama_id']) ? $ticket['nama_id'] : '-';
    $noKursi = !empty($ticket['kursi']) ? $ticket['kursi'] : '-';
    $kotaTujuan = !empty($ticket['nama_tujuan']) ? $ticket['nama_tujuan'] : ($tujuanMap[$ticket['tujuan_id']] ?? '-');
    $noHp = !empty($ticket['no_hp_id']) ? $ticket['no_hp_id'] : '-';
    $noPlat = !empty($ticket['no_plat_id']) ? $ticket['no_plat_id'] : '-';
    $kelasTravel = !empty($ticket['kelas']) ? $ticket['kelas'] : 'Executive';
    
    // Ambil Nama Kota Asal Keberangkatan (Bukan ID User / Integer)
    $rawAsal = !empty($ticket['asal_po']) ? $ticket['asal_po'] : (!empty($ticket['asal_po_kasir']) ? $ticket['asal_po_kasir'] : 'Sumbawa');
    if (is_numeric($rawAsal) && isset($usersMap[$rawAsal])) {
        $kotaAsal = $usersMap[$rawAsal];
    } elseif (!empty($rawAsal) && !is_numeric($rawAsal)) {
        $kotaAsal = $rawAsal;
    } else {
        $kotaAsal = 'Sumbawa';
    }
    $asalPOCabang = $kotaAsal;
    
    // Formatting Waktu Berangkat
    $tglBerangkatRaw = $ticket['tanggal_berangkat'];
    $timeBerangkat = strtotime($tglBerangkatRaw);
    
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

    if ($timeBerangkat) {
        $hariTeks = $hariIndo[date('w', $timeBerangkat)];
        $tglTeks = date('j', $timeBerangkat) . ' ' . $bulanIndo[(int)date('n', $timeBerangkat)] . ' ' . date('Y', $timeBerangkat);
        $tglTeksSingkat = date('j', $timeBerangkat) . ' ' . $bulanIndoShort[(int)date('n', $timeBerangkat)] . ' ' . date('Y', $timeBerangkat);
        $jamTeks = date('H:i', $timeBerangkat);
        if ($jamTeks === '00:00') {
            $jamTeks = '19:30';
        }
        $jamTeksDisplay = $jamTeks . ' WITA';
        $tglBerangkatDisplay = $hariTeks . ', ' . $tglTeks;
        $tglBerangkatBoardingPass = $tglTeksSingkat;
    } else {
        $tglBerangkatDisplay = '-';
        $tglBerangkatBoardingPass = '-';
        $jamTeksDisplay = '19:30 WITA';
    }

    $hargaTiket = (float)($ticket['harga_id'] ?? 0);
    $hargaTiketDisplay = 'Rp. ' . number_format($hargaTiket, 0, ',', '.');
    $metodePembayaran = !empty($ticket['metode_pembayaran']) ? $ticket['metode_pembayaran'] : 'Transfer Bank / Cash';
    $noRef = !empty($ticket['nomor_rekening']) ? $ticket['nomor_rekening'] . ' (' . ($ticket['nama_rekening'] ?? '') . ')' : '-';
    $namaPengirim = $namaPenumpang;

    $tglPesanRaw = $ticket['tanggal_pemesanan'];
    $timePesan = strtotime($tglPesanRaw);
    $tglTransferDisplay = $timePesan ? date('j', $timePesan) . ' ' . $bulanIndo[(int)date('n', $timePesan)] . ' ' . date('Y', $timePesan) : date('d/m/Y');
    $catatan = !empty($ticket['keterangan']) ? $ticket['keterangan'] : (!empty($ticket['alamat_id']) ? 'Jemput: ' . $ticket['alamat_id'] : 'Armada: ' . $noPlat . ' / ' . $kelasTravel);

    // QR Code Verification Content
    $qrData = "SINAR GALAXY TRAVEL | TIKET: " . $noTiket . " | NAMA: " . $namaPenumpang . " | KURSI: " . $noKursi . " | TUJUAN: " . $kotaTujuan . " | TGL: " . $tglBerangkatDisplay . " | STATUS: LUNAS (RESMI)";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isValid ? 'E-Tiket Resmi SGT: ' . htmlspecialchars($noTiket) . ' - ' . htmlspecialchars($namaPenumpang) : 'Verifikasi E-Tiket - Sinar Galaxy Travel' ?></title>
  <link rel="icon" href="../img/logo_sgt.png" type="image/png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,600;0,700;0,800;1,700;1,800&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: #f0f4f9;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1a202c;
      padding: 20px 12px;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    /* Public Passenger Header Bar */
    .passenger-nav {
      max-width: 960px;
      margin: 0 auto 20px auto;
      background: #ffffff;
      padding: 14px 20px;
      border-radius: 14px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      border: 1px solid #dce4ee;
    }

    .passenger-nav .brand-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .badge-verified {
      background: #10b981;
      color: #ffffff;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      letter-spacing: 0.5px;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .btn-action {
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 12px;
      cursor: pointer;
      border: none;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
    }

    .btn-download-img {
      background: #0284c7;
      color: #ffffff;
    }
    .btn-download-img:hover {
      background: #0369a1;
    }

    .btn-download-pdf {
      background: #dc2626;
      color: #ffffff;
    }
    .btn-download-pdf:hover {
      background: #b91c1c;
    }

    .btn-print {
      background: #0b3b7b;
      color: #ffffff;
    }
    .btn-print:hover {
      background: #072752;
    }

    .btn-cs {
      background: #25D366;
      color: #ffffff;
    }
    .btn-cs:hover {
      background: #1eb956;
    }

    /* Container Tiket */
    .ticket-sheet {
      max-width: 960px;
      margin: 0 auto 30px auto;
      background: transparent;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    /* SLIP ATAS */
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

    /* SLIP BAWAH */
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
       BOARDING PASS STYLING (CARD PASS AIRLINE STYLE)
       ========================================================================== */
    .bp-card-wrapper {
      width: 760px;
      max-width: 100%;
      background: #ffffff;
      color: #000000;
      font-family: 'Montserrat', 'Plus Jakarta Sans', Arial, sans-serif;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      padding: 10px 14px 8px 14px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      margin: 0 auto 30px auto;
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

    .bp-vertical-strip {
      width: 44px;
      border-left: 1.5px dashed #000000;
      padding: 0 4px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
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

    /* Print styling */
    @media print {
      body {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
      }
      .no-print {
        display: none !important;
      }
      .bp-card-wrapper {
        width: 195mm !important;
        max-width: 200mm !important;
        margin: 0 auto !important;
        padding: 3mm 4mm !important;
        border: 1px solid #000000 !important;
        box-shadow: none !important;
        page-break-after: always !important;
        break-inside: avoid !important;
      }
      .ticket-sheet {
        margin: 0 auto !important;
        box-shadow: none !important;
        page-break-after: always !important;
      }
      @page {
        size: 205mm 80mm landscape;
        margin: 2mm 3mm;
      }
    }
  </style>
</head>
<body>

  <?php if (!$isValid): ?>
    <!-- Halaman Jika Tiket Tidak Ditemukan atau Token Tidak Valid -->
    <div style="max-width: 580px; margin: 60px auto; background: #ffffff; border-radius: 16px; padding: 40px 30px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
      <div style="width: 70px; height: 70px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
        <i class="fas fa-ticket-alt" style="font-size: 32px; color: #ef4444;"></i>
      </div>
      <h2 style="font-size: 20px; font-weight: 900; color: #1e293b; margin-bottom: 10px;">E-Tiket Tidak Ditemukan</h2>
      <p style="font-size: 13.5px; color: #64748b; line-height: 1.6; margin-bottom: 24px;">
        Tautan verifikasi tiket yang Anda buka mungkin salah, kadaluarsa, atau nomor tiket tidak terdaftar pada sistem kami.
      </p>
      <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px dashed #cbd5e1; margin-bottom: 24px; text-align: left; font-size: 12.5px; color: #475569;">
        <strong>Butuh Bantuan?</strong><br>
        Silakan hubungi Layanan Pelanggan Resmi Sinar Galaxy Travel dengan menyertakan bukti pembayaran Anda.
      </div>
      <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
        <a href="https://api.whatsapp.com/send?phone=628176333330&text=Halo%20Admin%20Sinar%20Galaxy%2C%20saya%20ingin%20menanyakan%20status%20E-Tiket%20saya" target="_blank" class="btn-action btn-cs">
          <i class="fab fa-whatsapp"></i> Hubungi CS PO Sumbawa
        </a>
        <a href="https://api.whatsapp.com/send?phone=6282339860600&text=Halo%20Admin%20Sinar%20Galaxy%2C%20saya%20ingin%20menanyakan%20status%20E-Tiket%20saya" target="_blank" class="btn-action btn-cs">
          <i class="fab fa-whatsapp"></i> Hubungi CS PO Mataram
        </a>
      </div>
    </div>
  <?php else: ?>

    <!-- Navigation & Action Bar Khusus Penumpang -->
    <div class="passenger-nav no-print">
      <div class="brand-info">
        <img src="<?= $appLogo ?>" alt="Logo" style="height: 38px;">
        <div>
          <div style="font-size: 15px; font-weight: 900; color: #0b3b7b;">E-TIKET RESMI SINAR GALAXY</div>
          <div style="font-size: 11px; color: #64748b;">No. Tiket: <strong><?= htmlspecialchars($noTiket) ?></strong> (<?= htmlspecialchars($namaPenumpang) ?>)</div>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <span class="badge-verified">
          <i class="fas fa-check-circle"></i> TIKET RESMI & LUNAS
        </span>
        <button type="button" onclick="downloadGambar()" class="btn-action btn-download-img" title="Simpan sebagai gambar PNG">
          <i class="fas fa-file-image"></i> Simpan Gambar
        </button>
        <button type="button" onclick="downloadPDF()" class="btn-action btn-download-pdf" title="Download dokumen PDF resmi">
          <i class="fas fa-file-pdf"></i> Unduh PDF
        </button>
        <button type="button" onclick="openPanduanModal()" class="btn-action" style="background: #f59e0b; color: #ffffff;" title="Tips & Panduan Pengaturan Cetak Browser">
          <i class="fas fa-lightbulb"></i> Tips Cetak
        </button>
        <button type="button" onclick="window.print()" class="btn-action btn-print" title="Cetak tiket">
          <i class="fas fa-print"></i> Cetak
        </button>
      </div>
    </div>

    <!-- TAMPILAN RESMI 1: BOARDING PASS AIRLINE STYLE (PERSIS FOTO LION GROUP) -->
    <div class="bp-card-wrapper" id="public_bp_card">
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

      <div class="bp-body">
        <div class="bp-main-section">
          <div class="bp-grid-info">
            
            <!-- Row 1: Nama, Tanggal, Kelas -->
            <div class="bp-cell">
              <span class="bp-cell-label">NAMA PENUMPANG (NAME)</span>
              <span class="bp-cell-val bold-lg"><?= htmlspecialchars(strtoupper($namaPenumpang)) ?></span>
            </div>
            <div class="bp-cell">
              <span class="bp-cell-label">TANGGAL (DATE)</span>
              <span class="bp-cell-val"><?= htmlspecialchars($tglBerangkatBoardingPass) ?></span>
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
            <svg id="barcode_public_bp" class="bp-barcode-img"></svg>
            <div class="bp-warning-text">
              HARAP TIBA DI TITIK KEBERANGKATAN 30 MENIT SEBELUM JADWAL<br>
              SIMPAN TIKET INI SEBAGAI BUKTI PEMBAYARAN SAH
            </div>
          </div>
        </div>

        <div class="bp-vertical-strip">
          <div id="qrcode_public_vert"></div>
        </div>

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

    <!-- TAMPILAN RESMI 2: E-TIKET SLIP LENGKAP A4 -->
    <div class="ticket-sheet" id="public_ticket_card">
      
      <!-- SLIP 1: TIKET PENUMPANG UTAMA (ATAS) -->
      <div class="ticket-card-top">
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

        <!-- Sisi Kanan (Stub Sobek Tiket) -->
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

            <div class="stub-qr-box" id="qrcode_public"></div>

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
            <div class="bfield-icon"><i class="fas fa-check-circle text-success"></i></div>
            <div class="bfield-label">Status Pembayaran</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box" style="color: #0d8a43; font-weight: 800;">LUNAS (TERBAYAR)</div>
          </div>

          <div class="bottom-field-row">
            <div class="bfield-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="bfield-label">Tanggal Transfer</div>
            <div class="field-separator">:</div>
            <div class="bfield-val-box"><?= htmlspecialchars($tglTransferDisplay) ?></div>
          </div>
        </div>

        <div class="bottom-col-terms">
          <div>
            <div class="terms-title">
              <i class="fas fa-exclamation-circle text-danger"></i> KETENTUAN TIKET
            </div>

            <ol class="terms-list">
              <li>Tiket ini sebagai tanda bukti pembayaran sah.</li>
              <li>Harap tiba di terminal/titik kumpul 30 menit sebelum jadwal keberangkatan.</li>
              <li>Tiket tidak dapat dibatalkan atau dikembalikan (kecuali ada ketentuan khusus).</li>
              <li>Simpan tiket ini selama dalam perjalanan.</li>
            </ol>
          </div>

          <div class="terms-footer-brand">
            <div class="tf-logo-title"><i class="fas fa-star mr-1"></i> Sinar Galaxy Travel</div>
            <div class="tf-slogan">Selalu Hadir Untuk Perjalanan Anda</div>
          </div>
        </div>
      </div>

    </div>

    <!-- Script QR Code & Export -->
    <script>
      (function() {
        var qrElem = document.getElementById("qrcode_public");
        if (qrElem) {
          try {
            new QRCode(qrElem, {
              text: "<?= addslashes($qrData) ?>",
              width: 88,
              height: 88,
              colorDark : "#0a3871",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.M
            });
          } catch(e) {
            qrElem.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=88x88&data=<?= urlencode($qrData) ?>" alt="QR">';
          }
        }

        // Barcode 1D Boarding Pass
        try {
          if (window.JsBarcode) {
            JsBarcode("#barcode_public_bp", "<?= $noTiket ?>", {
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

        // QR Code Vertikal Strip
        var qrVert = document.getElementById("qrcode_public_vert");
        if (qrVert) {
          try {
            new QRCode(qrVert, {
              text: "<?= addslashes($qrData) ?>",
              width: 36,
              height: 72,
              colorDark : "#000000",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.L
            });
          } catch(e) {
            qrVert.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=36x72&data=<?= urlencode($qrData) ?>" alt="QR">';
          }
        }
      })();

      async function downloadGambar() {
        const elem = document.getElementById('public_bp_card') || document.getElementById('public_ticket_card');
        if (!elem) return;
        try {
          const canvas = await html2canvas(elem, {
            scale: 2.5,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
          });
          const imgData = canvas.toDataURL('image/png');
          const link = document.createElement('a');
          link.href = imgData;
          link.download = 'BoardingPass_<?= str_replace(' ', '_', $noTiket) ?>_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $namaPenumpang) ?>.png';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        } catch(e) {
          alert('Gagal mendownload gambar: ' + e.message);
        }
      }

      async function downloadPDF() {
        const elem = document.getElementById('public_bp_card') || document.getElementById('public_ticket_card');
        if (!elem) return;
        const options = {
          margin: [4, 4, 4, 4],
          filename: 'BoardingPass_<?= str_replace(' ', '_', $noTiket) ?>_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $namaPenumpang) ?>.pdf',
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2, useCORS: true, logging: false },
          jsPDF: { unit: 'mm', format: [205, 80], orientation: 'landscape' }
        };
        try {
          await html2pdf().set(options).from(elem).save();
        } catch(e) {
          alert('Gagal membuat PDF: ' + e.message);
        }
      }

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
            <span>Tips & Panduan Pengaturan Cetak Browser</span>
          </div>
          <button type="button" onclick="closePanduanModal()" style="background: transparent; border: none; color: #ffffff; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        
        <div style="padding: 20px; max-height: 75vh; overflow-y: auto; font-size: 13px; line-height: 1.6; color: #334155;">
          <div style="margin-bottom: 14px; padding: 12px 14px; background: #f0fdf4; border-left: 4px solid #22c55e; border-radius: 8px;">
            <div style="color: #166534; font-size: 13.5px; font-weight: 800; margin-bottom: 4px;">
              <i class="fas fa-ticket-alt mr-1"></i> Format Boarding Pass (Rekomendasi)
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
              <tr><td style="width: 38%; font-weight: 700;">Layout / Orientasi</td><td>: <strong>Landscape (Mendatar)</strong></td></tr>
              <tr><td style="font-weight: 700;">Ukuran Kertas (Paper Size)</td><td>: <strong>80mm Roll</strong> / <strong>User Defined (200 x 80 mm)</strong></td></tr>
              <tr><td style="font-weight: 700;">Margin (Margins)</td><td>: <strong>None</strong> atau <strong>Minimum (0 - 2mm)</strong></td></tr>
              <tr><td style="font-weight: 700;">Skala (Scale)</td><td>: <strong>100%</strong> atau <strong>Fit to Printable Area</strong></td></tr>
              <tr><td style="font-weight: 700;">Grafik Latar Belakang</td><td>: <span style="color: #15803d; font-weight: 800;">✓ Centang (Background graphics)</span></td></tr>
            </table>
          </div>

          <div style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" onclick="closePanduanModal()" class="btn-action" style="background: #64748b; color: #fff; padding: 8px 16px; font-size: 12.5px;">Tutup</button>
            <button type="button" onclick="closePanduanModal(); window.print();" class="btn-action btn-print" style="padding: 8px 18px; font-size: 12.5px;">
              <i class="fas fa-print mr-1"></i> Buka Dialog Cetak
            </button>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>

</body>
</html>
