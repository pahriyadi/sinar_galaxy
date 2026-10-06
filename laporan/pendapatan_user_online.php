<?php
// Versi yang dioptimalkan untuk server online
error_reporting(0); // Matikan error reporting untuk production
ini_set('display_errors', 0);

try {
    session_start();
    require_once '../assets/session.php';
    require_once '../assets/fungsi.php';
    require_once '../inc/koneksi.php';
    require_once '../inc/link_header.php';
    require_once '../inc/sidebar.php';
    require_once '../inc/navbar.php';

    // Cek koneksi database
    if (!$conn) {
        throw new Exception("Koneksi database gagal");
    }

    // Ambil session user
    $user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
    $id_users = isset($user['id_users']) ? $user['id_users'] : '';
    $asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';
    $role = isset($user['role']) ? $user['role'] : '';

    // Cek session
    if (empty($user)) {
        throw new Exception("Session user kosong. Silakan login kembali.");
    }

    // Hanya admin atau super admin
    if (!in_array($role, ['admin', 'super admin'])) {
        echo '<div class="alert alert-danger m-3">Anda tidak memiliki akses ke laporan ini.</div>';
        exit;
    }

    // Ambil filter dari GET
    $tgl_dari = $_GET['tgl_dari'] ?? '';
    $tgl_sampai = $_GET['tgl_sampai'] ?? '';
    $jenis_transaksi = $_GET['jenis_transaksi'] ?? 'semua';
    $selected_user = $_GET['user_id'] ?? '';

    // Set default tanggal hari ini jika tidak ada filter
    if (!$tgl_dari && !$tgl_sampai) {
        $tgl_dari = date('Y-m-d');
        $tgl_sampai = date('Y-m-d');
    }

    $filterStart = $tgl_dari;
    $filterEnd = $tgl_sampai;

    // Map referensi dengan error handling
    $rekeningMap = [];
    $metodeMap = [];
    $statusMap = [];
    $userMap = [];
    $lokasiMap = [];

    // Ambil data referensi dengan try-catch
    $queries = [
        "SELECT id_rekening, nama_rekening FROM data_rekening" => &$rekeningMap,
        "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran" => &$metodeMap,
        "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran" => &$statusMap,
        "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan" => &$lokasiMap
    ];

    foreach ($queries as $sql => &$map) {
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $key = isset($r['id_rekening']) ? 'id_rekening' : 
                       (isset($r['id_metode_pembayaran']) ? 'id_metode_pembayaran' : 
                       (isset($r['id_status_pembayaran']) ? 'id_status_pembayaran' : 'id_tujuan_perjalanan'));
                $value = isset($r['nama_rekening']) ? 'nama_rekening' : 
                        (isset($r['metode_pembayaran']) ? 'metode_pembayaran' : 
                        (isset($r['status_pembayaran']) ? 'status_pembayaran' : 'nama_tujuan'));
                $map[$r[$key]] = $r[$value];
            }
        }
    }

    // Ambil daftar user
    $whereUser = "WHERE 1=1";
    if ($role !== 'super admin') {
        $whereUser .= " AND asal_po = '" . mysqli_real_escape_string($conn, $asal_po) . "'";
    }
    $res = mysqli_query($conn, "SELECT id_users, username FROM data_users $whereUser ORDER BY username");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $userMap[$r['id_users']] = $r['username'];
        }
    }

    // Function untuk push data ke rekap (versi sederhana)
    function _push_rekap_user_simple(&$arr, $userId, $jenis, $rekId, $methodId, $statusId, $amount, $platId = null, $lokasiId = null, $tanggal = null) {
        $key = $userId.'|'.$jenis.'|'.$rekId.'|'.$methodId.'|'.$statusId;
        if (!isset($arr[$key])) {
            $arr[$key] = [
                'user_id' => $userId,
                'jenis_transaksi' => $jenis,
                'id_rekening' => $rekId,
                'metode_pembayaran_id' => $methodId,
                'status_pembayaran_id' => $statusId,
                'plat_id' => $platId,
                'lokasi_id' => $lokasiId,
                'tanggal' => $tanggal,
                'jumlah_transaksi' => 0,
                'total_pendapatan' => 0
            ];
        }
        $arr[$key]['jumlah_transaksi'] += 1;
        $arr[$key]['total_pendapatan'] += $amount;
        if ($tanggal) {
            $arr[$key]['tanggal'] = $tanggal;
        }
    }

    $rekap = [];
    $total_pendapatan = 0;
    $total_transaksi = 0;

    // Basis filter role
    $whereRolePemesanan = $whereRolePengiriman = "WHERE 1=1";
    if ($role !== 'super admin') {
        $whereRolePemesanan .= " AND pm.asal_po = '$asal_po'";
        $whereRolePengiriman .= " AND pg.asal_po_id = '$asal_po'";
    }

    if ($selected_user) {
        $whereRolePemesanan .= " AND pm.username = '" . mysqli_real_escape_string($conn, $selected_user) . "'";
        $whereRolePengiriman .= " AND pg.user_id = '" . mysqli_real_escape_string($conn, $selected_user) . "'";
    }

    // Proses data pemesanan dan pengiriman dengan error handling
    if ($jenis_transaksi !== 'paket') {
        $sqlPemesanan = "SELECT pm.username, pm.payment_methods, pm.harga_id, pm.metode_pembayaran_id, pm.jenis_rekening_id, pm.status_pembayaran_id, pm.tanggal_pemesanan, pm.no_plat_id, pm.tujuan_id FROM data_pemesanan pm $whereRolePemesanan";
        $res = mysqli_query($conn, $sqlPemesanan);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $jenis = 'Tiket';
                $userId = $row['username'];
                
                if (!empty($row['payment_methods'])) {
                    $arrPay = json_decode($row['payment_methods'], true);
                    if (is_array($arrPay)) {
                        foreach ($arrPay as $p) {
                            $pDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : date('Y-m-d', strtotime($row['tanggal_pemesanan']));
                            if ($pDate < $filterStart || $pDate > $filterEnd) continue;
                            $amount = floatval($p['amount'] ?? 0);
                            _push_rekap_user_simple($rekap, $userId, $jenis, $p['rekening_id'] ?? $row['jenis_rekening_id'], $p['method_id'] ?? $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount, $row['no_plat_id'], $row['tujuan_id'], $pDate);
                            $total_pendapatan += $amount;
                            $total_transaksi++;
                        }
                        continue;
                    }
                }
                $trxDate = date('Y-m-d', strtotime($row['tanggal_pemesanan']));
                if ($trxDate < $filterStart || $trxDate > $filterEnd) continue;
                $amount = floatval($row['harga_id']);
                _push_rekap_user_simple($rekap, $userId, $jenis, $row['jenis_rekening_id'], $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount, $row['no_plat_id'], $row['tujuan_id'], $trxDate);
                $total_pendapatan += $amount;
                $total_transaksi++;
            }
        }
    }

    if ($jenis_transaksi !== 'tiket') {
        $sqlPengiriman = "SELECT pg.user_id, pg.payment_methods, pg.jumlah, pg.metode_pembayaran_id, pg.jenis_rekening_id, pg.status_pembayaran_id, pg.tanggal_pengiriman, pg.no_plat_id, pg.tujuan_id FROM data_pengiriman pg $whereRolePengiriman";
        $res = mysqli_query($conn, $sqlPengiriman);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $jenis = 'Paket';
                $userId = $row['user_id'];
                
                if (!empty($row['payment_methods'])) {
                    $arrPay = json_decode($row['payment_methods'], true);
                    if (is_array($arrPay)) {
                        foreach ($arrPay as $p) {
                            $pDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : date('Y-m-d', strtotime($row['tanggal_pengiriman']));
                            if ($pDate < $filterStart || $pDate > $filterEnd) continue;
                            $amount = floatval($p['amount'] ?? 0);
                            _push_rekap_user_simple($rekap, $userId, $jenis, $p['rekening_id'] ?? $row['jenis_rekening_id'], $p['method_id'] ?? $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount, $row['no_plat_id'], $row['tujuan_id'], $pDate);
                            $total_pendapatan += $amount;
                            $total_transaksi++;
                        }
                        continue;
                    }
                }
                $trxDate = date('Y-m-d', strtotime($row['tanggal_pengiriman']));
                if ($trxDate < $filterStart || $trxDate > $filterEnd) continue;
                $amount = floatval($row['jumlah']);
                _push_rekap_user_simple($rekap, $userId, $jenis, $row['jenis_rekening_id'], $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount, $row['no_plat_id'], $row['tujuan_id'], $trxDate);
                $total_pendapatan += $amount;
                $total_transaksi++;
            }
        }
    }

    // Tambahkan nama, metode, status
    foreach ($rekap as &$r) {
        $r['nama_user'] = $userMap[$r['user_id']] ?? 'Unknown';
        $r['nama_rekening'] = $rekeningMap[$r['id_rekening']] ?? 'Lainnya';
        $r['metode_pembayaran'] = $metodeMap[$r['metode_pembayaran_id']] ?? 'Lainnya';
        $r['status_pembayaran'] = $statusMap[$r['status_pembayaran_id']] ?? '';
        $r['is_dp'] = (stripos($r['status_pembayaran'], 'dp') !== false || stripos($r['status_pembayaran'], 'piutang') !== false);
    }
    unset($r);

    // Group by user
    $userRekap = [];
    foreach ($rekap as $r) {
        $userId = $r['user_id'];
        if (!isset($userRekap[$userId])) {
            $userRekap[$userId] = [
                'user_id' => $userId,
                'nama_user' => $r['nama_user'],
                'total_pendapatan' => 0,
                'total_transaksi' => 0,
                'total_pengeluaran' => 0,
                'detail' => [],
                'pendapatan_rekening' => [],
                'pendapatan_plat' => [],
                'pendapatan_lokasi' => [],
                'pengeluaran_kategori' => [],
                'pengeluaran_rekening' => [],
                'pengeluaran_detail' => []
            ];
        }
        $userRekap[$userId]['total_pendapatan'] += $r['total_pendapatan'];
        $userRekap[$userId]['total_transaksi'] += $r['jumlah_transaksi'];
        $userRekap[$userId]['detail'][] = $r;
        
        // Akumulasi per rekening
        $rekening = $r['nama_rekening'];
        if (!isset($userRekap[$userId]['pendapatan_rekening'][$rekening])) {
            $userRekap[$userId]['pendapatan_rekening'][$rekening] = [
                'total' => 0,
                'tiket' => 0,
                'paket' => 0
            ];
        }
        $userRekap[$userId]['pendapatan_rekening'][$rekening]['total'] += $r['total_pendapatan'];
        $userRekap[$userId]['pendapatan_rekening'][$rekening][strtolower($r['jenis_transaksi'])] += $r['total_pendapatan'];
        
        // Akumulasi per plat
        $plat = $r['plat_id'] ?? 'Unknown'; // Gunakan plat_id langsung karena itu adalah no_plat (string)
        if (!isset($userRekap[$userId]['pendapatan_plat'][$plat])) {
            $userRekap[$userId]['pendapatan_plat'][$plat] = [
                'total' => 0,
                'tiket' => 0,
                'paket' => 0
            ];
        }
        $userRekap[$userId]['pendapatan_plat'][$plat]['total'] += $r['total_pendapatan'];
        $userRekap[$userId]['pendapatan_plat'][$plat][strtolower($r['jenis_transaksi'])] += $r['total_pendapatan'];
        
        // Akumulasi per lokasi
        $lokasi = $lokasiMap[$r['lokasi_id']] ?? $r['lokasi_id'] ?? 'Unknown';
        if (!isset($userRekap[$userId]['pendapatan_lokasi'][$lokasi])) {
            $userRekap[$userId]['pendapatan_lokasi'][$lokasi] = [
                'total' => 0,
                'tiket' => 0,
                'paket' => 0
            ];
        }
        $userRekap[$userId]['pendapatan_lokasi'][$lokasi]['total'] += $r['total_pendapatan'];
        $userRekap[$userId]['pendapatan_lokasi'][$lokasi][strtolower($r['jenis_transaksi'])] += $r['total_pendapatan'];
    }

    // Ambil data pengeluaran untuk setiap user
    foreach ($userRekap as $userId => &$userData) {
        $sqlPengeluaran = "SELECT dp.jenis_pengeluaran_id, dp.jenis_rekening_id, dp.harga_operasional as jumlah, dp.tanggal_pengeluaran as tanggal, dp.kategori_pengeluaran, djp.nama_pengeluaran as nama_kategori, dr.nama_rekening FROM data_pengeluaran dp LEFT JOIN data_jenis_pengeluaran djp ON dp.jenis_pengeluaran_id = djp.id_jenis_pengeluaran LEFT JOIN data_rekening dr ON dp.jenis_rekening_id = dr.id_rekening WHERE dp.user_id = ? AND dp.tanggal_pengeluaran BETWEEN ? AND ? ORDER BY dp.tanggal_pengeluaran DESC";
        
        $stmtPengeluaran = mysqli_prepare($conn, $sqlPengeluaran);
        if ($stmtPengeluaran) {
            mysqli_stmt_bind_param($stmtPengeluaran, "iss", $userId, $filterStart, $filterEnd);
            mysqli_stmt_execute($stmtPengeluaran);
            $resultPengeluaran = mysqli_stmt_get_result($stmtPengeluaran);
            
            $userData['total_pengeluaran'] = 0;
            $userData['total_transaksi_pengeluaran'] = 0;
            
            while ($pengeluaran = mysqli_fetch_assoc($resultPengeluaran)) {
                $kategori = $pengeluaran['nama_kategori'] ?? 'Unknown';
                $rekening = $pengeluaran['nama_rekening'] ?? 'Unknown';
                $jumlah = $pengeluaran['jumlah'];
                $tanggal = $pengeluaran['tanggal'];
                $kategori_pengeluaran = $pengeluaran['kategori_pengeluaran'] ?? 'travel';
                
                $userData['total_pengeluaran'] += $jumlah;
                $userData['total_transaksi_pengeluaran']++;
                
                $userData['pengeluaran_detail'][] = [
                    'tanggal' => $tanggal,
                    'kategori' => $kategori,
                    'rekening' => $rekening,
                    'jumlah' => $jumlah,
                    'kategori_pengeluaran' => $kategori_pengeluaran
                ];
                
                $kategori_key = ($kategori_pengeluaran == 'travel') ? 'Pengeluaran Travel' : 'Pengeluaran Operasional Kantor';
                if (!isset($userData['pengeluaran_kategori'][$kategori_key])) {
                    $userData['pengeluaran_kategori'][$kategori_key] = [
                        'total' => 0,
                        'jumlah' => 0
                    ];
                }
                $userData['pengeluaran_kategori'][$kategori_key]['total'] += $jumlah;
                $userData['pengeluaran_kategori'][$kategori_key]['jumlah']++;
                
                if (!isset($userData['pengeluaran_rekening'][$rekening])) {
                    $userData['pengeluaran_rekening'][$rekening] = [
                        'total' => 0,
                        'jumlah' => 0
                    ];
                }
                $userData['pengeluaran_rekening'][$rekening]['total'] += $jumlah;
                $userData['pengeluaran_rekening'][$rekening]['jumlah']++;
            }
            
            $userData['total_transaksi'] += $userData['total_transaksi_pengeluaran'];
            mysqli_stmt_close($stmtPengeluaran);
        }
    }
    unset($userData);

    // Hitung total transaksi pengeluaran global
    $total_transaksi_pengeluaran = 0;
    foreach ($userRekap as $userId => $userData) {
        $total_transaksi_pengeluaran += $userData['total_transaksi_pengeluaran'] ?? 0;
    }
    $total_transaksi += $total_transaksi_pengeluaran;

} catch (Exception $e) {
    // Tampilkan error yang user-friendly
    echo '<div class="content-wrapper">';
    echo '<section class="content-header">';
    echo '<div class="container-fluid">';
    echo '<h1><i class="fas fa-exclamation-triangle"></i> Error</h1>';
    echo '</div>';
    echo '</section>';
    echo '<section class="content">';
    echo '<div class="container-fluid">';
    echo '<div class="alert alert-danger">';
    echo '<h4><i class="icon fas fa-ban"></i> Terjadi Kesalahan</h4>';
    echo '<p>Maaf, terjadi kesalahan saat memuat laporan. Silakan coba lagi atau hubungi administrator.</p>';
    echo '<p><strong>Detail:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
    echo '<div class="text-center">';
    echo '<a href="javascript:history.back()" class="btn btn-primary">Kembali</a>';
    echo '</div>';
    echo '</div>';
    echo '</section>';
    echo '</div>';
    exit;
}
?>

<!-- HTML Content Paper White v2.0 -->
<div class="content-wrapper" style="background-color: #fcfcfc;">
    <!-- Content Header -->
    <div class="content-header" style="padding: 14px 18px 8px; background: #ffffff; border-bottom: 1px solid #e0e0e0; margin-bottom: 15px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.2rem;">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div>
                            <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Monitoring & Pendapatan Kasir Online</h1>
                            <div class="text-muted" style="font-size: 0.83rem;">
                                <span>Pemantauan real-time setoran transaksi kasir aktif dan arus kas loket cabang</span>
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
                        <i class="fas fa-print mr-1 text-muted"></i> Cetak Laporan
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <section class="content" style="padding: 0 15px;">
        <div class="container-fluid">
            <!-- Filter Form (Paper White v2.0) -->
            <div class="card mb-3" style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; box-shadow: none !important;">
                <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #b8b8b8; padding: 10px 16px;">
                    <h3 class="card-title font-weight-bold text-dark" style="font-size: 0.95rem; margin: 0;">
                        <i class="fas fa-filter mr-1 text-muted"></i> Parameter Filter Kasir Online
                    </h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="form-inline">
                        <div class="form-group mr-3">
                            <label class="mr-2">Tanggal Dari:</label>
                            <input type="date" name="tgl_dari" value="<?= htmlspecialchars($tgl_dari) ?>" class="form-control">
                        </div>
                        <div class="form-group mr-3">
                            <label class="mr-2">Tanggal Sampai:</label>
                            <input type="date" name="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>" class="form-control">
                        </div>
                        <div class="form-group mr-3">
                            <label class="mr-2">Jenis Transaksi:</label>
                            <select name="jenis_transaksi" class="form-control">
                                <option value="semua" <?= $jenis_transaksi === 'semua' ? 'selected' : '' ?>>Semua</option>
                                <option value="tiket" <?= $jenis_transaksi === 'tiket' ? 'selected' : '' ?>>Tiket</option>
                                <option value="paket" <?= $jenis_transaksi === 'paket' ? 'selected' : '' ?>>Paket</option>
                            </select>
                        </div>
                        <div class="form-group mr-3">
                            <label class="mr-2">User:</label>
                            <select name="user_id" class="form-control">
                                <option value="">Semua User</option>
                                <?php foreach ($userMap as $uid => $uname): ?>
                                    <option value="<?= $uid ?>" <?= $selected_user === $uid ? 'selected' : '' ?>><?= htmlspecialchars($uname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="?" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Boxes -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($total_pendapatan, 0, ',', '.') ?></h3>
                            <p>Total Pendapatan</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-money-bill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($total_transaksi, 0, ',', '.') ?></h3>
                            <p>Total Transaksi</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= count($userRekap) ?></h3>
                            <p>Jumlah User</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= number_format(array_sum(array_column($userRekap, 'total_pengeluaran')), 0, ',', '.') ?></h3>
                            <p>Total Pengeluaran</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ringkasan per User -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Ringkasan per User</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th class="text-right">Total Transaksi</th>
                                <th class="text-right">Total Pendapatan</th>
                                <th class="text-right">Total Pengeluaran</th>
                                <th class="text-right">Net Profit</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($userRekap as $userData): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($userData['nama_user']) ?></strong></td>
                                    <td class="text-right"><?= number_format($userData['total_transaksi'], 0, ',', '.') ?></td>
                                    <td class="text-right"><strong class="text-success">Rp <?= number_format($userData['total_pendapatan'], 0, ',', '.') ?></strong></td>
                                    <td class="text-right"><strong class="text-danger">Rp <?= number_format($userData['total_pengeluaran'], 0, ',', '.') ?></strong></td>
                                    <td class="text-right">
                                        <strong class="<?= ($userData['total_pendapatan'] - $userData['total_pengeluaran']) >= 0 ? 'text-success' : 'text-danger' ?>">
                                            Rp <?= number_format($userData['total_pendapatan'] - $userData['total_pengeluaran'], 0, ',', '.') ?>
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-info" onclick="showDetail('<?= $userData['user_id'] ?>')">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detail per User -->
            <?php foreach ($userRekap as $userData): ?>
                <div class="card card-outline card-info" id="detail-<?= $userData['user_id'] ?>" style="display: none;">
                    <div class="card-header">
                        <h3 class="card-title">Detail - <?= htmlspecialchars($userData['nama_user']) ?></h3>
                    </div>
                    <div class="card-body">
                        <!-- Tabel Detail Transaksi -->
                        <h5>Detail Transaksi (Arus Kas)</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="bg-info text-white">
                                    <tr>
                                        <th>#</th>
                                        <th>Tanggal</th>
                                        <th>Jenis</th>
                                        <th>Keterangan</th>
                                        <th class="text-right">Debit (Pendapatan)</th>
                                        <th class="text-right">Kredit (Pengeluaran)</th>
                                        <th class="text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1; 
                                    $saldo = 0;
                                    
                                    // Gabungkan data pendapatan dan pengeluaran
                                    $semuaTransaksi = [];
                                    
                                    // Tambahkan data pendapatan
                                    foreach ($userData['detail'] as $detail) {
                                        $semuaTransaksi[] = [
                                            'tanggal' => $detail['tanggal'] ?? date('Y-m-d'),
                                            'jenis' => 'Pendapatan',
                                            'keterangan' => $detail['jenis_transaksi'] . ' - ' . $detail['nama_rekening'],
                                            'debit' => $detail['total_pendapatan'],
                                            'kredit' => 0,
                                            'warna' => 'table-success'
                                        ];
                                    }
                                    
                                    // Tambahkan data pengeluaran
                                    foreach ($userData['pengeluaran_detail'] as $pengeluaran) {
                                        $semuaTransaksi[] = [
                                            'tanggal' => $pengeluaran['tanggal'],
                                            'jenis' => 'Pengeluaran',
                                            'keterangan' => $pengeluaran['kategori'] . ' - ' . $pengeluaran['rekening'],
                                            'debit' => 0,
                                            'kredit' => $pengeluaran['jumlah'],
                                            'warna' => 'table-danger'
                                        ];
                                    }
                                    
                                    // Urutkan berdasarkan tanggal
                                    usort($semuaTransaksi, function($a, $b) {
                                        return strtotime($a['tanggal']) - strtotime($b['tanggal']);
                                    });
                                    
                                    foreach ($semuaTransaksi as $transaksi): 
                                        $saldo += $transaksi['debit'] - $transaksi['kredit'];
                                    ?>
                                        <tr class="<?= $transaksi['warna'] ?>">
                                            <td><?= $no++ ?></td>
                                            <td><?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $transaksi['jenis'] == 'Pendapatan' ? 'success' : 'danger' ?>">
                                                    <?= $transaksi['jenis'] ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($transaksi['keterangan']) ?></td>
                                            <td class="text-right">
                                                <?php if ($transaksi['debit'] > 0): ?>
                                                    <strong class="text-success">Rp <?= number_format($transaksi['debit'], 0, ',', '.') ?></strong>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right">
                                                <?php if ($transaksi['kredit'] > 0): ?>
                                                    <strong class="text-danger">Rp <?= number_format($transaksi['kredit'], 0, ',', '.') ?></strong>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right">
                                                <strong class="<?= $saldo >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    Rp <?= number_format($saldo, 0, ',', '.') ?>
                                                </strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </section>
</div>

<script>
function showDetail(userId) {
    // Hide all detail sections
    document.querySelectorAll('[id^="detail-"]').forEach(el => el.style.display = 'none');
    
    // Show selected detail section
    const detailEl = document.getElementById('detail-' + userId);
    if (detailEl) {
        detailEl.style.display = 'block';
        detailEl.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<?php include '../inc/footer.php'; ?> 