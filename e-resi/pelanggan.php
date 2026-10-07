<?php
/**
 * ==============================================================================
 * E-RESI PUBLIK PENGIRIMAN CARGO - SINAR GALAXY TRAVEL
 * Akses Publik Aman Khusus Pengirim & Penerima Tanpa Memerlukan Login Admin
 * URL Contoh: https://sinargalaxy.my.id/e-resi/pelanggan?id=8722&token=a8f9c1e7...
 * ==============================================================================
 */

require_once __DIR__ . '/../inc/koneksi.php';
require_once __DIR__ . '/../assets/fungsi.php';

$id_pengiriman = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$format_kertas = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'thermal80';

// Cek session login admin
$isAdminLoggedIn = false;
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!empty($_SESSION['user']['id_users'])) {
    $isAdminLoggedIn = true;
}

$shipment = null;
$isValid = false;

if ($id_pengiriman > 0) {
    // Generate valid token untuk verifikasi keamanan
    $expectedToken = substr(hash_hmac('sha256', $id_pengiriman . 'SGT_RESI_SALT_2026', 'sinar_galaxy_secret_key'), 0, 16);
    
    // Izinkan akses jika token cocok ATAU jika sedang login sebagai admin internal
    if (($token !== '' && hash_equals($expectedToken, $token)) || $isAdminLoggedIn) {
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
                WHERE dp.id_pengiriman = $id_pengiriman
                LIMIT 1";
        $query = mysqli_query($conn, $sql);
        if ($query && mysqli_num_rows($query) > 0) {
            $shipment = mysqli_fetch_assoc($query);
            $isValid = true;
        }
    }
}

// User map for Origin resolution
$usersMap = [];
$resU = mysqli_query($conn, "SELECT id_users, username, asal_po FROM data_users");
if ($resU) {
    while ($ru = mysqli_fetch_assoc($resU)) {
        $usersMap[$ru['id_users']] = $ru;
    }
}

$appLogo = '../img/logo_sgt.png';
if (!file_exists(__DIR__ . '/../img/logo_sgt.png')) {
    $appLogo = 'https://sinargalaxy.my.id/img/logo_sgt.png';
}

$cargoImage = '../img/bus_sgt.jpg';
if (!file_exists(__DIR__ . '/../img/bus_sgt.jpg')) {
    $cargoImage = 'https://sinargalaxy.my.id/img/bus_sgt.jpg';
}

if ($isValid && $shipment) {
    $noResi = !empty($shipment['nip_sgt_id']) ? $shipment['nip_sgt_id'] : ('SGT-PKG-' . str_pad($id_pengiriman, 6, '0', STR_PAD_LEFT));
    $namaPengirim = !empty($shipment['nama_id']) ? $shipment['nama_id'] : '-';
    $alamatPengirim = !empty($shipment['alamat_id']) ? $shipment['alamat_id'] : '-';
    $noHpPengirim = !empty($shipment['no_hp_id']) ? $shipment['no_hp_id'] : '-';
    
    $namaPenerima = !empty($shipment['nama_penerima']) ? $shipment['nama_penerima'] : '-';
    $noHpPenerima = !empty($shipment['no_hp_penerima']) ? $shipment['no_hp_penerima'] : '-';
    
    $jenisBarang = !empty($shipment['jenis_barang']) ? $shipment['jenis_barang'] : 'PAKET / DOKUMEN';
    $keteranganBarang = !empty($shipment['keterangan']) ? $shipment['keterangan'] : '-';
    
    $kotaTujuan = !empty($shipment['nama_tujuan']) ? $shipment['nama_tujuan'] : '-';
    $noPlat = !empty($shipment['no_plat_id']) ? $shipment['no_plat_id'] : '-';
    $kelasTravel = !empty($shipment['kelas']) ? $shipment['kelas'] : (!empty($shipment['kelas_id']) ? $shipment['kelas_id'] : 'EXECUTIVE');

    // Resolusi Kota Asal
    $asalRaw = !empty($shipment['asal_po_id']) ? $shipment['asal_po_id'] : (!empty($shipment['asal_po_kasir']) ? $shipment['asal_po_kasir'] : '');
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
    $tglKirimRaw = $shipment['tanggal_pengiriman'];
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

    $ongkosKirim = (float)($shipment['jumlah'] ?? 0);
    $ongkosKirimDisplay = 'Rp. ' . number_format($ongkosKirim, 0, ',', '.');
    $metodePembayaran = !empty($shipment['metode_pembayaran']) ? $shipment['metode_pembayaran'] : 'TUNAI (KASIR)';
    $noRef = !empty($shipment['nomor_rekening']) ? $shipment['nomor_rekening'] . ' (' . ($shipment['nama_rekening'] ?? '') . ')' : '-';
    $tglTransferDisplay = $tglKirimDisplay;

    // QR Data
    $qrData = "SINAR GALAXY CARGO | RESI: " . $noResi . " | PENGIRIM: " . $namaPengirim . " | PENERIMA: " . $namaPenerima . " | TUJUAN: " . $kotaTujuan . " | TARIF: " . $ongkosKirimDisplay . " | STATUS: LUNAS";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isValid ? 'E-Resi: ' . htmlspecialchars($noResi) . ' - Sinar Galaxy Express' : 'Verifikasi Resi Pengiriman - Sinar Galaxy' ?></title>
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
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: #f1f5f9;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #0f172a;
      padding: 20px 12px 40px 12px;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    .public-nav {
      max-width: 860px;
      margin: 0 auto 20px auto;
      background: #ffffff;
      padding: 14px 20px;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      border: 1px solid #e2e8f0;
    }

    .brand-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .badge-verified {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #a7f3d0;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 11.5px;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-action {
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 700;
      font-size: 12.5px;
      cursor: pointer;
      border: none;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
    }

    .btn-download-img { background: #0284c7; color: #ffffff; }
    .btn-download-pdf { background: #dc2626; color: #ffffff; }
    .btn-print { background: #0d6efd; color: #ffffff; }
    .btn-cs { background: #25D366; color: #ffffff; }

    /* Card Wrapper */
    .bp-card-wrapper {
      max-width: 780px;
      margin: 0 auto 24px auto;
      background: #ffffff;
      color: #000000;
      font-family: 'Montserrat', 'Plus Jakarta Sans', Arial, sans-serif;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      padding: 12px 16px 10px 16px;
      box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
      position: relative;
    }

    .bp-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 8px;
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

    .bp-logo-brand i { font-size: 18px; color: #e65100; }
    .bp-sub-brand { color: #e65100; font-weight: 800; font-size: 13px; font-style: italic; }

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

    .bp-cell-val.bold-lg { font-size: 14px; font-weight: 900; }
    .bp-cell-val.highlight-lg { font-size: 17px; font-weight: 900; line-height: 1.1; color: #0b3b7b; }
    .bp-cell-val.time-highlight { font-size: 16px; font-weight: 900; line-height: 1.1; color: #000000; }

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

    .bp-barcode-img { height: 30px; width: 220px; }

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
      @page {
        size: 205mm 80mm landscape;
        margin: 2mm 3mm;
      }
    }
  </style>
</head>
<body>

  <?php if (!$isValid): ?>
    <!-- Halaman Jika Resi Tidak Ditemukan atau Token Tidak Valid -->
    <div style="max-width: 580px; margin: 60px auto; background: #ffffff; border-radius: 16px; padding: 40px 30px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
      <div style="width: 70px; height: 70px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
        <i class="fas fa-box-open" style="font-size: 32px; color: #ef4444;"></i>
      </div>
      <h2 style="font-size: 20px; font-weight: 900; color: #1e293b; margin-bottom: 10px;">Resi Pengiriman Tidak Ditemukan</h2>
      <p style="font-size: 13.5px; color: #64748b; line-height: 1.6; margin-bottom: 24px;">
        Tautan pelacakan atau nomor resi yang Anda buka mungkin salah, kadaluarsa, atau belum terdaftar pada sistem kami.
      </p>
      <div style="background: #f8fafc; padding: 16px; border-radius: 10px; border: 1px dashed #cbd5e1; margin-bottom: 24px; text-align: left; font-size: 12.5px; color: #475569;">
        <strong>Butuh Bantuan Lacak Paket?</strong><br>
        Silakan hubungi Layanan Pelanggan Resmi Sinar Galaxy Express dengan menyertakan nama pengirim & penerima.
      </div>
      <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
        <a href="https://api.whatsapp.com/send?phone=628176333330&text=Halo%20Admin%20Sinar%20Galaxy%2C%20saya%20ingin%20menanyakan%20status%20paket%20kiriman%20saya" target="_blank" class="btn-action btn-cs">
          <i class="fab fa-whatsapp"></i> Hubungi CS PO Sumbawa
        </a>
        <a href="https://api.whatsapp.com/send?phone=6282339860600&text=Halo%20Admin%20Sinar%20Galaxy%2C%20saya%20ingin%20menanyakan%20status%20paket%20kiriman%20saya" target="_blank" class="btn-action btn-cs">
          <i class="fab fa-whatsapp"></i> Hubungi CS PO Mataram
        </a>
      </div>
    </div>
  <?php else: ?>

    <!-- Navigation & Action Bar Khusus Pengirim/Penerima -->
    <div class="public-nav no-print">
      <div class="brand-info">
        <img src="<?= $appLogo ?>" alt="Logo" style="height: 38px;">
        <div>
          <div style="font-size: 15px; font-weight: 900; color: #0b3b7b;">E-RESI RESMI SINAR GALAXY CARGO</div>
          <div style="font-size: 11px; color: #64748b;">No. Resi: <strong><?= htmlspecialchars($noResi) ?></strong> (<?= htmlspecialchars($namaPenerima) ?>)</div>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <span class="badge-verified">
          <i class="fas fa-check-circle"></i> RESI SAH & TERBAYAR
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
        <button type="button" onclick="window.print()" class="btn-action btn-print" title="Cetak resi">
          <i class="fas fa-print"></i> Cetak
        </button>
      </div>
    </div>

    <!-- TAMPILAN RESMI: BOARDING PASS CARGO RESI 80MM -->
    <div class="bp-card-wrapper" id="public_resi_card">
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

      <div class="bp-body">
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
            <svg id="barcode_public_resi" class="bp-barcode-img"></svg>
            <div class="bp-warning-text">
              TUNJUKKAN RESI INI SAAT PENGAMBILAN BARANG DI LOKET TUJUAN<br>
              SIMPAN RESI INI SEBAGAI BUKTI PEMBAYARAN & TITIPAN SAH
            </div>
          </div>
        </div>

        <div class="bp-vertical-strip">
          <div id="qrcode_public_vert"></div>
        </div>

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

    <script>
      (function() {
        var qrText = "<?= addslashes($qrData) ?>";

        try {
          if (window.JsBarcode) {
            JsBarcode("#barcode_public_resi", "<?= $noResi ?>", {
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

        var qrVert = document.getElementById("qrcode_public_vert");
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
            qrVert.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=36x72&data=<?= urlencode($qrData) ?>" alt="QR">';
          }
        }
      })();

      async function downloadGambar() {
        const elem = document.getElementById('public_resi_card');
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
          link.download = 'Resi_<?= str_replace(' ', '_', $noResi) ?>_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $namaPenerima) ?>.png';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        } catch(e) {
          alert('Gagal mendownload gambar: ' + e.message);
        }
      }

      async function downloadPDF() {
        const elem = document.getElementById('public_resi_card');
        if (!elem) return;
        const options = {
          margin: [3, 3, 3, 3],
          filename: 'Resi_<?= str_replace(' ', '_', $noResi) ?>_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $namaPenerima) ?>.pdf',
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
              <i class="fas fa-ticket-alt mr-1"></i> Format Resi 80mm Cargo (Rekomendasi)
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
