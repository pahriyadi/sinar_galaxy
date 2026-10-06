<?php
session_start();
require_once '../assets/session.php';
require_once '../inc/koneksi.php';
require_once '../assets/fungsi.php';
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';

// Ambil daftar rekening
$rekeningOptions = [];
$resRek = $conn->query("SELECT id_rekening, nama_rekening FROM data_rekening ORDER BY nama_rekening");
if ($resRek) {
  while ($r = $resRek->fetch_assoc()) { $rekeningOptions[(int)$r['id_rekening']] = $r['nama_rekening']; }
}

// Input filter
$tglDari = isset($_GET['tgl_dari']) ? trim($_GET['tgl_dari']) : '';
$tglSampai = isset($_GET['tgl_sampai']) ? trim($_GET['tgl_sampai']) : '';
$rekeningId = isset($_GET['rekening']) ? (int)$_GET['rekening'] : 0;

// Normalisasi tanggal (harus format Y-m-d)
if ($tglDari && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglDari)) $tglDari = '';
if ($tglSampai && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglSampai)) $tglSampai = '';
if ($tglDari && !$tglSampai) $tglSampai = $tglDari;
if ($tglSampai && !$tglDari) $tglDari = $tglSampai;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['filter']) || isset($_GET['rekening']))) {
  if (!$tglDari || !$tglSampai) $errors[] = 'Tanggal dari dan sampai wajib diisi.';
  if (!$rekeningId) $errors[] = 'Nama rekening wajib dipilih.';
  if (!$errors) {
    // Validasi satu bulan yang sama
    $m1 = date('Y-m', strtotime($tglDari));
    $m2 = date('Y-m', strtotime($tglSampai));
    if ($m1 !== $m2) $errors[] = 'Rentang tanggal harus berada pada bulan dan tahun yang sama.';
  }
}

// Siapkan data jurnal jika filter valid
$entries = [];
$totalDebit = 0; $totalKredit = 0;
$saldoAwal = 0; $judulPeriode = '';
$rekeningNama = $rekeningOptions[$rekeningId] ?? '-';

if ($tglDari && $tglSampai && $rekeningId && !$errors) {
  // Periode dan saldo awal
  $periodeBulan = (int)date('n', strtotime($tglDari));
  $periodeTahun = (int)date('Y', strtotime($tglDari));
  $judulPeriode = date('F Y', strtotime($tglDari));

  // Ambil saldo awal bank untuk rekening dan bulan/tahun tsb
  $stmt = $conn->prepare("SELECT saldo_awal FROM saldo_awal_bank WHERE id_rekening=? AND bulan=? AND tahun=? LIMIT 1");
  $stmt->bind_param('iii', $rekeningId, $periodeBulan, $periodeTahun);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) { $saldoAwal = (float)$row['saldo_awal']; }
  $stmt->close();

  // Tambah entri Saldo Awal
  $entries[] = [
    'date' => $tglDari . ' 00:00:00',
    'uraian' => 'Saldo Awal',
    'debit' => $saldoAwal,
    'kredit' => 0,
  ];

  // Helper: map status pembayaran id -> label
  $statusMap = [];
  $resStatus = $conn->query("SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
  if ($resStatus) { while ($r = $resStatus->fetch_assoc()) { $statusMap[$r['id_status_pembayaran']] = strtolower($r['status_pembayaran']); } }

  // Helper: jenis pengeluaran id -> nama
  $jenisPengeluaranMap = [];
  $resJP = $conn->query("SELECT id_jenis_pengeluaran, nama_pengeluaran FROM data_jenis_pengeluaran");
  if ($resJP) { while ($r = $resJP->fetch_assoc()) { $jenisPengeluaranMap[$r['id_jenis_pengeluaran']] = $r['nama_pengeluaran']; } }

  // Batas tanggal
  $start = date('Y-m-d', strtotime($tglDari));
  $end = date('Y-m-d', strtotime($tglSampai));

  // 1) Transaksi masuk dari Pemesanan (payment_methods JSON atau single)
  $sqlPm = "SELECT id_pemesanan, nama_id, tanggal_pemesanan, harga_id, jenis_rekening_id, payment_methods FROM data_pemesanan ";
  // batasi kandidat menggunakan LIKE untuk efisiensi kasar
  $like = '%"rekening_id":' . $rekeningId . '%';
  $stmtPm = $conn->prepare($sqlPm . "WHERE (payment_methods LIKE ? OR jenis_rekening_id = ?) ");
  $stmtPm->bind_param('si', $like, $rekeningId);
  $stmtPm->execute();
  $rsPm = $stmtPm->get_result();
  while ($row = $rsPm->fetch_assoc()) {
    $nama = $row['nama_id'] ?? '-';
    // Multiple payments
    if (!empty($row['payment_methods'])) {
      $arr = json_decode($row['payment_methods'], true);
      if (is_array($arr)) {
        foreach ($arr as $p) {
          $rek = (int)($p['rekening_id'] ?? 0);
          $amt = (float)($p['amount'] ?? 0);
          $payDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : substr($row['tanggal_pemesanan'] ?? '',0,10);
          if ($rek === $rekeningId && $payDate && $payDate >= $start && $payDate <= $end && $amt > 0) {
            $entries[] = [
              'date' => $payDate . ' 12:00:00',
              'uraian' => 'terima - pemesanan dari ' . $nama,
              'debit' => $amt,
              'kredit' => 0,
            ];
          }
        }
      }
    } else {
      // Single payment (lama)
      $rekId = (int)($row['jenis_rekening_id'] ?? 0);
      if ($rekId === $rekeningId) {
        $payDate = substr($row['tanggal_pemesanan'] ?? '', 0, 10);
        if ($payDate && $payDate >= $start && $payDate <= $end) {
          $amt = (float)($row['harga_id'] ?? 0);
          if ($amt > 0) {
            $entries[] = [
              'date' => $payDate . ' 12:00:00',
              'uraian' => 'terima - pemesanan dari ' . $nama,
              'debit' => $amt,
              'kredit' => 0,
            ];
          }
        }
      }
    }
  }
  $stmtPm->close();

  // 2) Transaksi masuk dari Pengiriman (payment_methods JSON atau single)
  $sqlPk = "SELECT id_pengiriman, nama_id, tanggal_pengiriman, jumlah, jenis_rekening_id, payment_methods FROM data_pengiriman ";
  $stmtPk = $conn->prepare($sqlPk . "WHERE (payment_methods LIKE ? OR jenis_rekening_id = ?) ");
  $stmtPk->bind_param('si', $like, $rekeningId);
  $stmtPk->execute();
  $rsPk = $stmtPk->get_result();
  while ($row = $rsPk->fetch_assoc()) {
    $nama = $row['nama_id'] ?? '-';
    if (!empty($row['payment_methods'])) {
      $arr = json_decode($row['payment_methods'], true);
      if (is_array($arr)) {
        foreach ($arr as $p) {
          $rek = (int)($p['rekening_id'] ?? 0);
          $amt = (float)($p['amount'] ?? 0);
          $payDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : substr($row['tanggal_pengiriman'] ?? '',0,10);
          if ($rek === $rekeningId && $payDate && $payDate >= $start && $payDate <= $end && $amt > 0) {
            $entries[] = [
              'date' => $payDate . ' 12:00:00',
              'uraian' => 'terima - pengiriman dari ' . $nama,
              'debit' => $amt,
              'kredit' => 0,
            ];
          }
        }
      }
    } else {
      $rekId = (int)($row['jenis_rekening_id'] ?? 0);
      if ($rekId === $rekeningId) {
        $payDate = substr($row['tanggal_pengiriman'] ?? '', 0, 10);
        if ($payDate && $payDate >= $start && $payDate <= $end) {
          $amt = (float)($row['jumlah'] ?? 0);
          if ($amt > 0) {
            $entries[] = [
              'date' => $payDate . ' 12:00:00',
              'uraian' => 'terima - pengiriman dari ' . $nama,
              'debit' => $amt,
              'kredit' => 0,
            ];
          }
        }
      }
    }
  }
  $stmtPk->close();

  // 3) Pengeluaran (arus keluar) – hanya jika status Lunas (uang benar-benar keluar)
  $sqlOut = "SELECT p.tanggal_pengeluaran, p.jenis_pengeluaran_id, p.harga_operasional, p.jenis_rekening_id, p.status_pembayaran_id FROM data_pengeluaran p WHERE p.jenis_rekening_id = ? AND p.tanggal_pengeluaran >= ? AND p.tanggal_pengeluaran <= ?";
  $stmtOut = $conn->prepare($sqlOut);
  $stmtOut->bind_param('iss', $rekeningId, $start, $end);
  $stmtOut->execute();
  $rsOut = $stmtOut->get_result();
  while ($row = $rsOut->fetch_assoc()) {
    $statusLabel = $statusMap[$row['status_pembayaran_id']] ?? '';
    if ($statusLabel && strpos($statusLabel, 'lunas') === false) continue; // hanya lunas
    $tgl = substr($row['tanggal_pengeluaran'] ?? '', 0, 10);
    $jenisText = $jenisPengeluaranMap[$row['jenis_pengeluaran_id']] ?? ('Jenis #' . $row['jenis_pengeluaran_id']);
    $amt = (float)($row['harga_operasional'] ?? 0);
    if ($tgl && $amt > 0) {
      $entries[] = [
        'date' => $tgl . ' 12:00:00',
        'uraian' => 'bayar - pengeluaran ' . $jenisText,
        'debit' => 0,
        'kredit' => $amt,
      ];
    }
  }
  $stmtOut->close();

  // 4) Transfer internal (masuk/keluar)
  // Pastikan tabel ada seperti di internal_transfer_view.php
  $conn->query("CREATE TABLE IF NOT EXISTS internal_transfers (
    id_transfer INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    dari_rekening_id INT NOT NULL,
    ke_rekening_id INT NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    keterangan TEXT NULL,
    user_id INT NULL,
    asal_po VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

  $sqlTf = "SELECT t.tanggal, t.dari_rekening_id, t.ke_rekening_id, t.jumlah FROM internal_transfers t WHERE t.tanggal >= ? AND t.tanggal <= ? AND (t.dari_rekening_id = ? OR t.ke_rekening_id = ?)";
  $stmtTf = $conn->prepare($sqlTf);
  $stmtTf->bind_param('ssii', $start, $end, $rekeningId, $rekeningId);
  $stmtTf->execute();
  $rsTf = $stmtTf->get_result();
  while ($row = $rsTf->fetch_assoc()) {
    $tgl = $row['tanggal'];
    $jumlah = (float)$row['jumlah'];
    if ((int)$row['ke_rekening_id'] === $rekeningId) {
      $from = $rekeningOptions[(int)$row['dari_rekening_id']] ?? ('Rek #' . (int)$row['dari_rekening_id']);
      $entries[] = [
        'date' => $tgl . ' 12:00:00',
        'uraian' => 'terima - transfer internal dari ' . $from,
        'debit' => $jumlah,
        'kredit' => 0,
      ];
    }
    if ((int)$row['dari_rekening_id'] === $rekeningId) {
      $to = $rekeningOptions[(int)$row['ke_rekening_id']] ?? ('Rek #' . (int)$row['ke_rekening_id']);
      $entries[] = [
        'date' => $tgl . ' 12:00:00',
        'uraian' => 'bayar - transfer internal ke ' . $to,
        'debit' => 0,
        'kredit' => $jumlah,
      ];
    }
  }
  $stmtTf->close();

  // Urutkan berdasarkan tanggal
  usort($entries, function($a,$b){
    return strcmp($a['date'], $b['date']);
  });
}
?>

<style>
.report-header-box {
  background: #ffffff;
  border: 1px solid #b8b8b8;
  border-radius: 4px;
  padding: 14px 18px;
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.report-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
}
.report-icon-badge {
  width: 44px;
  height: 44px;
  border-radius: 4px;
  background: rgba(13, 159, 79, 0.1);
  color: #0d9f4f;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  border: 1px solid rgba(13, 159, 79, 0.25);
}
.report-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #202124;
  margin: 0;
  line-height: 1.3;
}
.report-subtitle {
  font-size: 0.78rem;
  color: #5f6368;
  margin: 0;
}
.filter-card-flat {
  background: #ffffff;
  border: 1px solid #b8b8b8;
  border-radius: 4px;
  box-shadow: none !important;
  margin-bottom: 16px;
}
.filter-card-flat .card-header {
  background: #ffffff;
  border-bottom: 1px solid #e0e0e0;
  padding: 10px 16px;
  font-size: 0.88rem;
  font-weight: 600;
  color: #202124;
}
.filter-card-flat .card-body {
  padding: 16px;
}
.filter-label {
  font-size: 0.78rem;
  font-weight: 600;
  color: #495057;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.table-excel-container {
  border: 1.5px solid #a0a0a0;
  border-radius: 4px;
  background: #ffffff;
  overflow: hidden;
}
.table-excel thead th {
  background: #ffffff !important;
  color: #202124 !important;
  border: 1px solid #b8b8b8 !important;
  border-bottom: 2px solid #8c8c8c !important;
  font-size: 0.82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  padding: 8px 10px;
  vertical-align: middle;
}
.table-excel tbody td {
  border: 1px solid #d0d0d0 !important;
  padding: 7px 10px;
  font-size: 0.84rem;
  vertical-align: middle;
}
.table-excel tfoot th {
  background: #fafafa !important;
  border: 1px solid #b8b8b8 !important;
  border-top: 2px solid #8c8c8c !important;
  font-size: 0.85rem;
  font-weight: 700;
  padding: 9px 10px;
}
</style>

<div class="content-wrapper">
  <!-- Header Paper White v2.0 -->
  <div class="content-header p-0 pt-3 non-printable">
    <div class="container-fluid">
      <div class="report-header-box">
        <div class="report-title-group">
          <div class="report-icon-badge">
            <i class="fas fa-file-invoice-dollar"></i>
          </div>
          <div>
            <h1 class="report-title">Jurnal Pembukuan Keuangan</h1>
            <p class="report-subtitle">Pencatatan kronologis arus mutasi kas masuk dan keluar per rekening bank secara bulanan</p>
          </div>
        </div>
        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
          <?php if($rekeningId && !$errors): ?>
            <span class="badge" style="background:#ffffff; border:1px solid #b8b8b8; color:#202124; padding:6px 10px; font-weight:600;">
              <i class="fas fa-university mr-1 text-primary"></i> <?= htmlspecialchars($rekeningNama) ?> (<?= htmlspecialchars($judulPeriode) ?>)
            </span>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printReport()" style="border-radius:4px; font-weight:600;">
            <i class="fas fa-print mr-1"></i> Cetak Jurnal
          </button>
        </div>
      </div>
    </div>
  </div>

  <section class="content non-printable">
    <div class="container-fluid">
      <!-- Filter Card Flat -->
      <div class="card filter-card-flat">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div><i class="fas fa-filter text-muted mr-1"></i> Filter Periode & Rekening Buku Jurnal</div>
          <a href="jurnal_keuangan.php" class="btn btn-xs btn-outline-secondary" style="border-radius:3px;">
            <i class="fas fa-undo mr-1"></i> Reset Filter
          </a>
        </div>
        <div class="card-body">
          <?php if ($errors): ?>
            <div class="alert alert-warning mb-3" style="border-radius:4px;"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
          <?php endif; ?>
          <form method="get" class="mb-0">
            <div class="row align-items-end">
              <div class="col-md-3">
                <label class="filter-label">Tanggal Dari</label>
                <input type="date" class="form-control form-control-sm" name="tgl_dari" value="<?= htmlspecialchars($tglDari) ?>" required style="border: 1px solid #b8b8b8; border-radius: 4px;">
              </div>
              <div class="col-md-3">
                <label class="filter-label">Tanggal Sampai</label>
                <input type="date" class="form-control form-control-sm" name="tgl_sampai" value="<?= htmlspecialchars($tglSampai) ?>" required style="border: 1px solid #b8b8b8; border-radius: 4px;">
              </div>
              <div class="col-md-4">
                <label class="filter-label">Pilih Rekening Bank</label>
                <select class="form-control form-control-sm" name="rekening" required style="border: 1px solid #b8b8b8; border-radius: 4px;">
                  <option value="">-- Pilih Rekening --</option>
                  <?php foreach ($rekeningOptions as $id=>$nama): ?>
                    <option value="<?= (int)$id ?>" <?= ($rekeningId==$id?'selected':'') ?>><?= htmlspecialchars($nama) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <button type="submit" name="filter" value="1" class="btn btn-sm btn-block" style="background:#0d9f4f; color:#fff; border:1px solid #0d9f4f; border-radius:4px; font-weight:600; height: 31px;">
                  <i class="fas fa-search mr-1"></i> Tampilkan
                </button>
              </div>
            </div>
            <div class="mt-2">
              <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Catatan: Rentang tanggal wajib berada dalam satu bulan kalender yang sama untuk kalkulasi saldo awal yang tepat.</small>
            </div>
          </form>
        </div>
      </div>

      <!-- Main Jurnal Card -->
      <div class="card filter-card-flat">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:#ffffff; border-bottom: 1px solid #e0e0e0;">
          <div style="font-weight: 700; color: #202124; font-size: 0.95rem;">
            <i class="fas fa-list mr-1 text-muted"></i>
            Tabel Mutasi Jurnal Arus Kas
            <?php if($rekeningId && !$errors): ?>
              <span class="text-muted font-weight-normal" style="font-size: 0.8rem; margin-left: 8px;">(<?= htmlspecialchars($rekeningNama) ?>, <?= htmlspecialchars($judulPeriode) ?>)</span>
            <?php endif; ?>
          </div>
          <div>
            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="printReport()" style="border-radius:3px; font-weight:600;">
              <i class="fas fa-print mr-1"></i> Print
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive table-excel-container" style="border: none; border-radius: 0;">
            <table class="table table-bordered table-hover table-sm table-excel mb-0">
              <thead>
                <tr>
                  <th class="text-center" style="width:50px;">No</th>
                  <th>Uraian Transaksi</th>
                  <th style="width:160px;" class="text-right">Debit (Masuk)</th>
                  <th style="width:160px;" class="text-right">Kredit (Keluar)</th>
                  <th style="width:180px;" class="text-right">Saldo Berjalan</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($tglDari && $tglSampai && $rekeningId && !$errors): ?>
                  <?php 
                    $running = 0; 
                    $no = 0; 
                    foreach ($entries as $e): 
                      $no++; 
                      $running += ($e['debit'] - $e['kredit']);
                      $totalDebit += $e['debit'];
                      $totalKredit += $e['kredit'];
                  ?>
                    <tr>
                      <td class="text-center"><?= $no ?></td>
                      <td>
                        <strong><?= htmlspecialchars(date('d/m/Y', strtotime($e['date']))) ?></strong>
                        <span class="text-muted mx-1">&bull;</span>
                        <?= htmlspecialchars($e['uraian']) ?>
                      </td>
                      <td class="text-right">
                        <?= $e['debit']>0 ? '<strong style="color:#2e7d32;">Rp ' . number_format($e['debit'],0,',','.') . '</strong>' : '<span class="text-muted">-</span>' ?>
                      </td>
                      <td class="text-right">
                        <?= $e['kredit']>0 ? '<strong style="color:#c92a2a;">Rp ' . number_format($e['kredit'],0,',','.') . '</strong>' : '<span class="text-muted">-</span>' ?>
                      </td>
                      <td class="text-right font-weight-bold" style="color: #202124;">
                        Rp <?= number_format($running,0,',','.') ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-info-circle fa-2x mb-2 d-block"></i> Silakan tentukan tanggal dan pilih rekening bank untuk memuat jurnal pembukuan.</td></tr>
                <?php endif; ?>
              </tbody>
              <?php if ($tglDari && $tglSampai && $rekeningId && !$errors): ?>
              <tfoot>
                <tr>
                  <th colspan="2" class="text-right" style="font-weight: 700;">TOTAL MUTASI:</th>
                  <th class="text-right" style="font-weight: 700; color:#2e7d32;">Rp <?= number_format($totalDebit,0,',','.') ?></th>
                  <th class="text-right" style="font-weight: 700; color:#c92a2a;">Rp <?= number_format($totalKredit,0,',','.') ?></th>
                  <th class="text-right" style="font-weight: 700; color:#202124;">Rp <?= number_format(($totalDebit - $totalKredit),0,',','.') ?></th>
                </tr>
              </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '../inc/footer.php'; ?>

<!-- Print Area -->
<div id="printArea" style="display:none;">
  <div class="print-header">
    <h1>SINAR GALAXY TRAVEL</h1>
    <h2>JURNAL KEUANGAN</h2>
    <div class="periode">
      <?php
        if ($tglDari && $tglSampai) {
          echo "Periode: " . date('d/m/Y', strtotime($tglDari)) . " s/d " . date('d/m/Y', strtotime($tglSampai));
        } elseif ($tglDari) {
          echo "Tanggal: " . date('d/m/Y', strtotime($tglDari));
        } elseif ($tglSampai) {
          echo "Tanggal: " . date('d/m/Y', strtotime($tglSampai));
        } else {
          echo "Tanggal: " . date('d/m/Y');
        }
        if ($rekeningId) {
          echo " | Rekening: " . htmlspecialchars($rekeningNama);
        }
      ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered table-striped" style="border-collapse: collapse; width: 100%; border: 2px solid #000;">
      <thead>
        <tr>
          <th class="text-center" style="width: 40px; background-color: #1f4e79 !important; color: #ffffff !important; border: 1px solid #000; padding: 8px; font-weight: bold; text-align: center;">No</th>
          <th style="background-color: #1f4e79 !important; color: #ffffff !important; border: 1px solid #000; padding: 8px; font-weight: bold; text-align: center;">Uraian</th>
          <th style="width: 160px; background-color: #1f4e79 !important; color: #ffffff !important; border: 1px solid #000; padding: 8px; font-weight: bold; text-align: center;">Debit</th>
          <th style="width: 160px; background-color: #1f4e79 !important; color: #ffffff !important; border: 1px solid #000; padding: 8px; font-weight: bold; text-align: center;">Kredit</th>
          <th style="width: 180px; background-color: #1f4e79 !important; color: #ffffff !important; border: 1px solid #000; padding: 8px; font-weight: bold; text-align: center;">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($tglDari && $tglSampai && $rekeningId && !$errors): ?>
          <?php 
            $runningP = 0; $noP = 0; $totalDP = 0; $totalKP = 0; 
            foreach ($entries as $e): 
              $noP++; 
              $runningP += ($e['debit'] - $e['kredit']);
              $totalDP += $e['debit'];
              $totalKP += $e['kredit'];
          ?>
            <tr>
              <td style="text-align:center; border: 1px solid #000; padding: 6px; color: #000; background-color: #fff;"><?= $noP ?></td>
              <td style="border: 1px solid #000; padding: 6px; color: #000; background-color: #fff;"><?= htmlspecialchars(date('d/m/Y', strtotime($e['date'])) . ' - ' . $e['uraian']) ?></td>
              <td style="text-align:right; border: 1px solid #000; padding: 6px; color: #000; background-color: #fff;"><?= $e['debit']>0 ? 'Rp ' . number_format($e['debit'],0,',','.') : '-' ?></td>
              <td style="text-align:right; border: 1px solid #000; padding: 6px; color: #000; background-color: #fff;"><?= $e['kredit']>0 ? 'Rp ' . number_format($e['kredit'],0,',','.') : '-' ?></td>
              <td style="text-align:right; border: 1px solid #000; padding: 6px; color: #000; background-color: #fff;">Rp <?= number_format($runningP,0,',','.') ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <th colspan="2" style="text-align:right; border: 1px solid #000; padding: 6px; background:#f0f0f0;">TOTAL</th>
            <th style="text-align:right; border: 1px solid #000; padding: 6px; background:#f0f0f0;">Rp <?= number_format($totalDP,0,',','.') ?></th>
            <th style="text-align:right; border: 1px solid #000; padding: 6px; background:#f0f0f0;">Rp <?= number_format($totalKP,0,',','.') ?></th>
            <th style="text-align:right; border: 1px solid #000; padding: 6px; background:#f0f0f0;">Rp <?= number_format(($totalDP-$totalKP),0,',','.') ?></th>
          </tr>
        <?php else: ?>
          <tr><td colspan="5" style="text-align:center; border: 1px solid #000; padding: 6px; color: #000; background-color: #fff;">Silakan pilih tanggal dan rekening, pastikan satu bulan yang sama.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@media print {
  * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
  body { font-family: Arial, sans-serif !important; font-size: 12px !important; color: #000 !important; background: #fff !important; margin: 0 !important; padding: 0 !important; }
  .printable { display: block !important; }
  .non-printable { display: none !important; }
  .no-print { display: none !important; }
  @page { size: portrait; margin: 15mm 10mm; }
  #printArea { margin: 0 !important; padding: 0 !important; width: 100% !important; }
  .table { font-size: 12px !important; width: 100% !important; border-collapse: collapse !important; }
  .table th, .table td { padding: 6px 10px !important; font-size: 12px !important; white-space: normal !important; color: #000 !important; background: #fff !important; border: 1px solid #000 !important; }
  .table th { background-color: #1f4e79 !important; color: #fff !important; }
  .print-header { text-align: center !important; margin-bottom: 20px !important; border-bottom: 2px solid #000 !important; padding-bottom: 10px !important; }
  .print-header h1 { font-size: 18px !important; font-weight: bold !important; margin: 0 0 5px 0 !important; color: #000 !important; }
  .print-header h2 { font-size: 14px !important; font-weight: bold !important; margin: 0 0 10px 0 !important; color: #000 !important; }
  .print-header .periode { font-size: 11px !important; color: #333 !important; margin-bottom: 15px !important; }
}
</style>

<script>
function printReport() {
  const nonPrintables = document.querySelectorAll('.non-printable');
  nonPrintables.forEach(el => el.style.display = 'none');

  const printArea = document.getElementById('printArea');
  const win = window.open('', '_blank', 'width=800,height=600');
  const html = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Jurnal Keuangan - <?= date('d/m/Y') ?></title>
      <meta charset="UTF-8">
      <style>
        @page { size: portrait; margin: 15mm 10mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; background: #fff; margin: 0; padding: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background-color: #1f4e79 !important; color: #fff !important; text-align: center; }
        .print-header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 8px; }
        .print-header h1 { font-size: 18px; margin: 0 0 5px 0; }
        .print-header h2 { font-size: 14px; margin: 0 0 8px 0; }
        .periode { font-size: 11px; color: #333; }
      </style>
    </head>
    <body>
      ${printArea.innerHTML}
    </body>
    </html>
  `;
  win.document.write(html);
  win.document.close();
  win.onload = function(){
    win.focus();
    win.print();
    setTimeout(function(){ win.close(); nonPrintables.forEach(el => el.style.display = ''); }, 800);
  };
  setTimeout(function(){
    if (!win.closed) {
      win.focus(); win.print();
      setTimeout(function(){ win.close(); nonPrintables.forEach(el => el.style.display = ''); }, 800);
    }
  }, 1500);
}
</script>

