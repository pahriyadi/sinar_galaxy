<?php
session_start();
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';
require_once '../inc/link_header.php';
require_once '../inc/sidebar.php';
require_once '../inc/navbar.php';

// Ambil session user
$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$id_users = isset($user['id_users']) ? $user['id_users'] : '';
$asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';
$role = isset($user['role']) ? $user['role'] : '';

// Ambil filter dari GET
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';
$jenis_transaksi = $_GET['jenis_transaksi'] ?? 'semua';
$no_plat = $_GET['no_plat'] ?? '';
$status_pembayaran = $_GET['status_pembayaran'] ?? '';

// Query data travel untuk dropdown
$travelOptions = [];
$resTravel = mysqli_query($conn, "SELECT no_plat, kelas FROM data_travel ORDER BY no_plat");
while ($r = mysqli_fetch_assoc($resTravel)) {
    $travelOptions[] = $r;
}

// Query data status pembayaran untuk dropdown
$statusOptions = [];
$resStatus = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran ORDER BY status_pembayaran");
while ($r = mysqli_fetch_assoc($resStatus)) {
    $statusOptions[] = $r;
}

// Query data tujuan untuk mapping
$tujuanMap = [];
$resTujuan = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
while ($r = mysqli_fetch_assoc($resTujuan)) {
    $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];
}

// Query rekap data keberangkatan
$where_pemesanan = "WHERE 1=1";
$where_pengiriman = "WHERE 1=1";

if ($role !== 'super admin') {
    $where_pemesanan .= " AND pm.username = '$id_users' AND pm.asal_po = '$asal_po'";
    $where_pengiriman .= " AND pg.user_id = '$id_users' AND pg.asal_po_id = '$asal_po'";
}

if ($tgl_dari && $tgl_sampai) {
    $where_pemesanan .= " AND DATE(pm.tanggal_pemesanan) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) BETWEEN '" . mysqli_real_escape_string($conn, $tgl_dari) . "' AND '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} elseif ($tgl_dari) {
    $where_pemesanan .= " AND DATE(pm.tanggal_pemesanan) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = '" . mysqli_real_escape_string($conn, $tgl_dari) . "'";
} elseif ($tgl_sampai) {
    $where_pemesanan .= " AND DATE(pm.tanggal_pemesanan) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = '" . mysqli_real_escape_string($conn, $tgl_sampai) . "'";
} else {
    $where_pemesanan .= " AND DATE(pm.tanggal_pemesanan) = CURDATE()";
    $where_pengiriman .= " AND DATE(pg.tanggal_pengiriman) = CURDATE()";
}

if ($no_plat) {
    $where_pemesanan .= " AND pm.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
    $where_pengiriman .= " AND pg.no_plat_id = '" . mysqli_real_escape_string($conn, $no_plat) . "'";
}

if ($status_pembayaran) {
    $where_pemesanan .= " AND pm.status_pembayaran_id = '" . mysqli_real_escape_string($conn, $status_pembayaran) . "'";
    $where_pengiriman .= " AND pg.status_pembayaran_id = '" . mysqli_real_escape_string($conn, $status_pembayaran) . "'";
}

// Query untuk pemesanan (tiket)
$sql_pemesanan = "SELECT 
    'Tiket' as jenis_transaksi,
    pm.id_pemesanan as id_transaksi,
    pm.nip_sgt_id,
    pm.nama_id,
    pm.alamat_id,
    pm.no_ktp_id,
    pm.no_hp_id,
    pm.tanggal_pemesanan,
    pm.tanggal_berangkat,
    pm.no_plat_id,
    pm.kelas_id,
    pm.harga_id,
    pm.kursi,
    pm.tujuan_id,
    pm.status_pembayaran_id,
    pm.metode_pembayaran_id,
    pm.jenis_rekening_id,
    pm.username,
    pm.asal_po,
    pm.keterangan,
    dt.kelas,
    sp.status_pembayaran,
    mp.metode_pembayaran,
    dr.nama_rekening
FROM data_pemesanan pm
LEFT JOIN data_travel dt ON pm.no_plat_id = dt.no_plat
LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
LEFT JOIN data_metode_pembayaran mp ON pm.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_rekening dr ON pm.jenis_rekening_id = dr.id_rekening
$where_pemesanan
ORDER BY pm.tanggal_berangkat ASC, pm.no_plat_id ASC, pm.kursi ASC";

// Query untuk pengiriman (paket)
$sql_pengiriman = "SELECT 
    'Paket' as jenis_transaksi,
    pg.id_pengiriman as id_transaksi,
    pg.nip_sgt_id,
    pg.nama_id,
    pg.alamat_id,
    pg.no_ktp_id,
    pg.no_hp_id,
    pg.tanggal_pengiriman as tanggal_pemesanan,
    pg.tanggal_pengiriman as tanggal_berangkat,
    pg.no_plat_id,
    pg.kelas_id,
    pg.jumlah as harga_id,
    '-' as kursi,
    pg.tujuan_id,
    pg.status_pembayaran_id,
    pg.metode_pembayaran_id,
    pg.jenis_rekening_id,
    pg.user_id as username,
    pg.asal_po_id as asal_po,
    pg.keterangan,
    dt.kelas,
    sp.status_pembayaran,
    mp.metode_pembayaran,
    dr.nama_rekening
FROM data_pengiriman pg
LEFT JOIN data_travel dt ON pg.no_plat_id = dt.no_plat
LEFT JOIN data_status_pembayaran sp ON pg.status_pembayaran_id = sp.id_status_pembayaran
LEFT JOIN data_metode_pembayaran mp ON pg.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_rekening dr ON pg.jenis_rekening_id = dr.id_rekening
$where_pengiriman
ORDER BY pg.tanggal_pengiriman ASC, pg.no_plat_id ASC";

// Gabungkan query berdasarkan filter
if ($jenis_transaksi === 'tiket') {
    $sql = $sql_pemesanan;
} elseif ($jenis_transaksi === 'paket') {
    $sql = $sql_pengiriman;
} else {
    $sql = "($sql_pemesanan) UNION ALL ($sql_pengiriman) ORDER BY tanggal_berangkat ASC, no_plat_id ASC, kursi ASC";
}

$resRekap = mysqli_query($conn, $sql);

$rekap = [];
$total_pendapatan = 0;
$total_transaksi = 0;
$total_tiket = 0;
$total_paket = 0;

while ($row = mysqli_fetch_assoc($resRekap)) {
    $rekap[] = $row;
    $total_pendapatan += $row['harga_id'];
    $total_transaksi++;
    
    if ($row['jenis_transaksi'] === 'Tiket') {
        $total_tiket++;
    } else {
        $total_paket++;
    }
}

// Hitung rata-rata per transaksi
$rata_rata_transaksi = $total_transaksi > 0 ? $total_pendapatan / $total_transaksi : 0;
?>

<div class="content-wrapper" style="background-color: #fcfcfc;">
    <!-- Content Header -->
    <div class="content-header" style="padding: 14px 18px 8px; background: #ffffff; border-bottom: 1px solid #e0e0e0; margin-bottom: 15px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.2rem;">
                            <i class="fas fa-list-check"></i>
                        </div>
                        <div>
                            <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Manifes Keberangkatan Armada</h1>
                            <div class="text-muted" style="font-size: 0.83rem;">
                                <span>Rekap jadwal keberangkatan unit travel dan manifest paket kargo</span>
                                <span class="mx-1">•</span>
                                <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                                    <i class="fas fa-building mr-1"></i><?= $role === 'super admin' ? 'Seluruh Cabang PO' : 'PO ' . htmlspecialchars($asal_po) ?>
                                </span>
                                <span class="badge badge-secondary" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                                    <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars(strtoupper($role)) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-5 text-right no-print">
                    <button type="button" class="btn btn-default btn-sm btn-flat" onclick="window.print();" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600; margin-right: 6px;">
                        <i class="fas fa-print mr-1 text-muted"></i> Cetak Manifes
                    </button>
                    <button type="button" class="btn btn-success btn-sm btn-flat" onclick="exportToExcel()" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600;">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="content" style="padding: 0 15px;">
        <div class="container-fluid">
            <div class="no-print">
                <!-- Filter Card (Paper White v2.0) -->
                <div class="card mb-3" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
                    <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #b8b8b8; padding: 10px 16px;">
                        <h3 class="card-title font-weight-bold text-dark" style="font-size: 0.95rem; margin: 0;">
                            <i class="fas fa-filter mr-1 text-muted"></i> Filter Periode & Parameter Manifes
                        </h3>
                    </div>
                    <div class="card-body p-3">
                        <form method="GET" class="mb-0">
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label for="tgl_dari" class="font-weight-bold text-dark" style="font-size: 0.82rem;">Tanggal Dari</label>
                                    <input type="date" class="form-control form-control-sm" name="tgl_dari" id="tgl_dari" value="<?= htmlspecialchars($tgl_dari) ?>" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="tgl_sampai" class="font-weight-bold text-dark" style="font-size: 0.82rem;">Tanggal Sampai</label>
                                    <input type="date" class="form-control form-control-sm" name="tgl_sampai" id="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="jenis_transaksi" class="font-weight-bold text-dark" style="font-size: 0.82rem;">Jenis Transaksi</label>
                                    <select class="form-control form-control-sm" name="jenis_transaksi" id="jenis_transaksi" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                        <option value="semua" <?= ($jenis_transaksi === 'semua') ? 'selected' : '' ?>>Semua Transaksi</option>
                                        <option value="tiket" <?= ($jenis_transaksi === 'tiket') ? 'selected' : '' ?>>Tiket Saja</option>
                                        <option value="paket" <?= ($jenis_transaksi === 'paket') ? 'selected' : '' ?>>Paket Saja</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="no_plat" class="font-weight-bold text-dark" style="font-size: 0.82rem;">No. Plat Travel</label>
                                    <select class="form-control form-control-sm" name="no_plat" id="no_plat" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                        <option value="">-- Semua Plat --</option>
                                        <?php foreach ($travelOptions as $travel) { ?>
                                            <option value="<?= htmlspecialchars($travel['no_plat']) ?>" <?= ($no_plat == $travel['no_plat']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($travel['no_plat']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="status_pembayaran" class="font-weight-bold text-dark" style="font-size: 0.82rem;">Status Bayar</label>
                                    <select class="form-control form-control-sm" name="status_pembayaran" id="status_pembayaran" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                        <option value="">-- Semua Status --</option>
                                        <?php foreach ($statusOptions as $status) { ?>
                                            <option value="<?= htmlspecialchars($status['id_status_pembayaran']) ?>" <?= ($status_pembayaran == $status['id_status_pembayaran']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($status['status_pembayaran']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2 align-self-end">
                                    <button type="submit" class="btn btn-success btn-sm btn-block btn-flat font-weight-bold" style="background-color: #0d9f4f; border-color: #076e34; border-radius: 4px; padding: 6px 12px;">
                                        <i class="fas fa-search mr-1"></i> Terapkan Filter
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-1">
                                <div class="col-md-12">
                                    <a href="daftar_keberangkatan.php" class="btn btn-default btn-xs btn-flat font-weight-bold" style="border: 1px solid #b8b8b8; background: #ffffff; color: #5f6368; border-radius: 3px; padding: 4px 10px;">
                                        <i class="fas fa-sync-alt mr-1"></i> Reset Filter
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 4 KPI Metrics Card (Paper White v2.0) -->
                <div class="row mb-3">
                    <div class="col-lg-3 col-6 mb-2">
                        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Pendapatan</div>
                                        <div class="font-weight-bold" style="font-size: 1.35rem; color: #0d9f4f; line-height: 1.2; margin-top: 4px;">
                                            Rp <?= number_format($total_pendapatan, 0, ',', '.') ?>
                                        </div>
                                    </div>
                                    <div style="width: 42px; height: 42px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; color: #0d9f4f;">
                                        <i class="fas fa-money-bill-wave" style="font-size: 1.15rem;"></i>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                                    <i class="fas fa-receipt mr-1"></i> Omzet manifes periode ini
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-6 mb-2">
                        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Transaksi</div>
                                        <div class="font-weight-bold" style="font-size: 1.45rem; color: #202124; line-height: 1.2; margin-top: 4px;">
                                            <?= number_format($total_transaksi) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Record</small>
                                        </div>
                                    </div>
                                    <div style="width: 42px; height: 42px; border-radius: 4px; background: #f0f9ff; border: 1px solid #bae6fd; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                                        <i class="fas fa-clipboard-list" style="font-size: 1.15rem;"></i>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                                    <i class="fas fa-calculator mr-1"></i> Rata-rata: Rp <?= number_format($rata_rata_transaksi, 0, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-6 mb-2">
                        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Tiket Penumpang</div>
                                        <div class="font-weight-bold" style="font-size: 1.45rem; color: #1e293b; line-height: 1.2; margin-top: 4px;">
                                            <?= number_format($total_tiket) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Tiket</small>
                                        </div>
                                    </div>
                                    <div style="width: 42px; height: 42px; border-radius: 4px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b;">
                                        <i class="fas fa-ticket" style="font-size: 1.15rem;"></i>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                                    <i class="fas fa-users mr-1"></i> Jumlah pemesanan kursi
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-6 mb-2">
                        <div class="card h-100 mb-0" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Paket Titipan</div>
                                        <div class="font-weight-bold" style="font-size: 1.45rem; color: #b45309; line-height: 1.2; margin-top: 4px;">
                                            <?= number_format($total_paket) ?> <small style="font-size: 0.8rem; font-weight: 500; color: #5f6368;">Kargo</small>
                                        </div>
                                    </div>
                                    <div style="width: 42px; height: 42px; border-radius: 4px; background: #fef3c7; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: center; color: #b45309;">
                                        <i class="fas fa-box" style="font-size: 1.15rem;"></i>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                                    <i class="fas fa-dolly mr-1"></i> Pengiriman barang koli
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="card mb-4" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
                <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #b8b8b8; padding: 12px 16px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title font-weight-bold text-dark" style="font-size: 1rem; margin: 0;">
                            <i class="fas fa-table mr-1 text-muted"></i> Lembar Kerja Manifes Keberangkatan
                        </h3>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div id="printArea">
                        <div class="text-center mb-3">
                            <h4 class="mb-0 font-weight-bold" style="color: #202124;">SINAR GALAXY TRAVEL</h4>
                            <h5 class="mb-1 font-weight-bold" style="color: #0d9f4f;">MANIFES DAFTAR KEBERANGKATAN</h5>
                            <p class="mb-0">
                            <small class="text-muted">
                                <?php
                                        if ($tgl_dari && $tgl_sampai) {
                                            echo "Periode: " . date('d/m/Y', strtotime($tgl_dari)) . " s/d " . date('d/m/Y', strtotime($tgl_sampai));
                                        } elseif ($tgl_dari) {
                                            echo "Tanggal: " . date('d/m/Y', strtotime($tgl_dari));
                                        } elseif ($tgl_sampai) {
                                            echo "Tanggal: " . date('d/m/Y', strtotime($tgl_sampai));
                                    } else {
                                            echo "Tanggal: " . date('d/m/Y');
                                        }
                                        if ($jenis_transaksi !== 'semua') {
                                            echo " | Jenis: " . ucfirst($jenis_transaksi);
                                    }
                                    if ($no_plat) {
                                            echo " | Plat: " . htmlspecialchars($no_plat);
                                    }
                                        if ($status_pembayaran) {
                                            $selectedStatus = array_filter($statusOptions, function($status) use ($status_pembayaran) {
                                                return $status['id_status_pembayaran'] == $status_pembayaran;
                                            });
                                            $selectedStatus = reset($selectedStatus);
                                            echo " | Status: " . htmlspecialchars($selectedStatus['status_pembayaran']);
                                        }
                                        if ($asal_po && $role !== 'super admin') {
                                            echo " | PO: " . htmlspecialchars($asal_po);
                                    }
                                ?>
                            </small>
                            </p>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-excel table-hover table-bordered table-sm mb-0" id="tableRekap" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #ffffff;">
                                        <th class="text-center" style="width: 35px; border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">No</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Jenis</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Nama</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">NIP/SGT</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">No KTP</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">No HP</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Alamat</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">No Plat</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Kelas</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600; text-align: center;">Kursi</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Tujuan</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Tgl Pesan</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Tgl Berangkat</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Status</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Metode</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Rekening</th>
                                        <th class="text-right" style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Jumlah (Rp)</th>
                                        <th style="border: 1px solid #b8b8b8; color: #202124; font-weight: 600;">Keterangan</th>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $subtotal_plat = [];

                                    foreach ($rekap as $row) {
                                        $statusClass = (stripos($row['status_pembayaran'], 'lunas') !== false) ? 'text-success' : 'text-warning';
                                        $tujuanID = trim((string) $row['tujuan_id']);
                                        $namaTujuan = $tujuanMap[$tujuanID] ?? '-';

                                        echo "<tr>";
                                        echo "<td class='text-center'>" . $no++ . "</td>";
                                        echo "<td><span class='badge badge-success" . ($row['jenis_transaksi'] === 'Tiket' ? 'primary' : 'info') . "'>" . htmlspecialchars($row['jenis_transaksi']) . "</span></td>";
                                        echo "<td><strong>" . htmlspecialchars($row['nama_id']) . "</strong></td>";
                                        echo "<td>" . htmlspecialchars($row['nip_sgt_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['no_ktp_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['no_hp_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['alamat_id']) . "</td>";
                                        echo "<td><strong>" . htmlspecialchars($row['no_plat_id']) . "</strong></td>";
                                        echo "<td><span class='badge badge-secondary'>" . htmlspecialchars($row['kelas']) . "</span></td>";
                                        echo "<td class='text-center'>" . htmlspecialchars($row['kursi']) . "</td>";
                                        echo "<td>" . htmlspecialchars($namaTujuan) . "</td>";
                                        echo "<td>" . date('d/m/Y', strtotime($row['tanggal_pemesanan'])) . "</td>";
                                        echo "<td><strong>" . date('d/m/Y', strtotime($row['tanggal_berangkat'])) . "</strong></td>";
                                        echo "<td class='$statusClass'><strong>" . htmlspecialchars($row['status_pembayaran']) . "</strong></td>";
                                        echo "<td>" . htmlspecialchars($row['metode_pembayaran']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['nama_rekening']) . "</td>";
                                        echo "<td class='text-right'><strong>Rp " . number_format($row['harga_id'], 0, ',', '.') . "</strong></td>";
                                        echo "<td>" . htmlspecialchars($row['keterangan']) . "</td>";
                                        echo "</tr>";
                                        
                                        // Hitung subtotal per plat
                                        if (!isset($subtotal_plat[$row['no_plat_id']])) {
                                            $subtotal_plat[$row['no_plat_id']] = 0;
                                        }
                                        $subtotal_plat[$row['no_plat_id']] += $row['harga_id'];
                                    }
                                    ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <?php foreach ($subtotal_plat as $plat => $total): ?>
                                    <tr class="table-info">
                                        <th colspan="16" class="text-right">Subtotal <?= htmlspecialchars($plat) ?></th>
                                        <th class="text-right">Rp <?= number_format($total, 0, ',', '.') ?></th>
                                        <th></th>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-success">
                                        <th colspan="16" class="text-right">GRAND TOTAL</th>
                                        <th class="text-right">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p><strong>Keterangan:</strong></p>
                                <ul class="mb-0">
                                    <li>Status Lunas ditandai dengan warna hijau</li>
                                    <li>Status DP/Piutang ditandai dengan warna kuning</li>
                                    <li>Laporan ini mencakup transaksi tiket dan paket</li>
                                    <li>Data diurutkan berdasarkan tanggal keberangkatan</li>
                                </ul>
                            </div>
                            <div class="col-md-6 text-right">
                                <p class="mb-1">Dicetak pada: <?= date('d/m/Y H:i:s') ?></p>
                                <p class="mb-0">Oleh: <?= htmlspecialchars($user['username']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../inc/footer.php'; ?>

<script>
function exportToExcel() {
    let table = document.getElementById("tableRekap");
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    let downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    downloadLink.href = url;
    downloadLink.download = 'Laporan_Daftar_Keberangkatan_<?= date('Y-m-d') ?>.xls';
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Auto submit form when filter changes
document.getElementById('jenis_transaksi').addEventListener('change', function() {
    document.querySelector('form').submit();
});

document.getElementById('no_plat').addEventListener('change', function() {
    document.querySelector('form').submit();
});

document.getElementById('status_pembayaran').addEventListener('change', function() {
    document.querySelector('form').submit();
});
</script>
