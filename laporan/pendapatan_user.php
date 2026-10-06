<?php
// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Tambahkan try-catch untuk menangani error
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../assets/session.php';
    require_once '../assets/fungsi.php';
    require_once '../inc/koneksi.php';
    require_once '../inc/link_header.php';
    require_once '../inc/sidebar.php';
    require_once '../inc/navbar.php';

    // Debug: Cek koneksi database
    if (!$conn) {
        throw new Exception("Koneksi database gagal: " . mysqli_connect_error());
    }

    // Ambil session user
    $user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
    $id_users = isset($user['id_users']) ? $user['id_users'] : '';
    $asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';
    $role = isset($user['role']) ? $user['role'] : '';

    // Debug: Cek session
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

    // Map referensi
    $rekeningMap = [];
    $metodeMap = [];
    $statusMap = [];
    $userMap = [];
    $platMap = [];
    $lokasiMap = [];

    // Ambil data referensi
    $res = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening");
    if (!$res) {
        throw new Exception("Error query data_rekening: " . mysqli_error($conn));
    }
    while ($r = mysqli_fetch_assoc($res)) {
        $rekeningMap[$r['id_rekening']] = $r['nama_rekening'];
    }

    $res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
    if (!$res) {
        throw new Exception("Error query data_metode_pembayaran: " . mysqli_error($conn));
    }
    while ($r = mysqli_fetch_assoc($res)) {
        $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran'];
    }

    $res = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
    if (!$res) {
        throw new Exception("Error query data_status_pembayaran: " . mysqli_error($conn));
    }
    while ($r = mysqli_fetch_assoc($res)) {
        $statusMap[$r['id_status_pembayaran']] = $r['status_pembayaran'];
    }



    // Mapping yang benar berdasarkan struktur database
    $lokasiMap = [];

    // Ambil data lokasi dari tabel yang benar
    $res = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
    if (!$res) {
        throw new Exception("Error query data_tujuan_perjalanan: " . mysqli_error($conn));
    }
    while ($r = mysqli_fetch_assoc($res)) {
        $lokasiMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];
    }



    // Ambil daftar user berdasarkan asal_po
    $whereUser = "WHERE 1=1";
    if ($role !== 'super admin') {
        $whereUser .= " AND asal_po = '" . mysqli_real_escape_string($conn, $asal_po) . "'";
    }
    $res = mysqli_query($conn, "SELECT id_users, username FROM data_users $whereUser ORDER BY username");
    if (!$res) {
        throw new Exception("Error query data_users: " . mysqli_error($conn));
    }
    while ($r = mysqli_fetch_assoc($res)) {
        $userMap[$r['id_users']] = $r['username'];
    }

    // Function untuk push data ke rekap
    function _push_rekap_user(&$arr, $userId, $jenis, $rekId, $methodId, $statusId, $amount, $platId = null, $lokasiId = null, $tanggal = null) {
        $key = $userId.'|'.$jenis.'|'.$rekId.'|'.$methodId.'|'.$statusId;
        if (!isset($arr[$key])) {
            $arr[$key] = [
                'user_id'               => $userId,
                'jenis_transaksi'       => $jenis,
                'id_rekening'           => $rekId,
                'metode_pembayaran_id'  => $methodId,
                'status_pembayaran_id'  => $statusId,
                'plat_id'               => $platId,
                'lokasi_id'             => $lokasiId,
                'tanggal'               => $tanggal,
                'jumlah_transaksi'      => 0,
                'total_pendapatan'      => 0
            ];
        }
        $arr[$key]['jumlah_transaksi'] += 1;
        $arr[$key]['total_pendapatan'] += $amount;
        // Update tanggal jika ada tanggal yang lebih spesifik
        if ($tanggal) {
            $arr[$key]['tanggal'] = $tanggal;
        }
    }

    $rekap = [];
    $total_pendapatan = 0;
    $total_transaksi = 0;

    // Inisialisasi userRekap
    $userRekap = [];

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

    // ======================== PEMESANAN (Tiket) =====================
    if ($jenis_transaksi !== 'paket') {
        $sqlPemesanan = "SELECT pm.username, pm.payment_methods, pm.harga_id, pm.metode_pembayaran_id, pm.jenis_rekening_id, pm.status_pembayaran_id, pm.tanggal_pemesanan, pm.no_plat_id, pm.tujuan_id
                         FROM data_pemesanan pm $whereRolePemesanan";
        $res = mysqli_query($conn, $sqlPemesanan);
        if (!$res) {
            throw new Exception("Error query data_pemesanan: " . mysqli_error($conn));
        }
        $pemCount = 0;
        while ($row = mysqli_fetch_assoc($res)) {
            $pemCount++;
            $jenis = 'Tiket';
            $userId = $row['username'];
            
            if (!empty($row['payment_methods'])) {
                $arrPay = json_decode($row['payment_methods'], true);
                if (is_array($arrPay)) {
                    foreach ($arrPay as $p) {
                        $pDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : date('Y-m-d', strtotime($row['tanggal_pemesanan']));
                        if ($pDate < $filterStart || $pDate > $filterEnd) continue;
                        $amount = floatval($p['amount'] ?? 0);
                        _push_rekap_user($rekap, $userId, $jenis, $p['rekening_id'] ?? $row['jenis_rekening_id'], $p['method_id'] ?? $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount, $row['no_plat_id'], $row['tujuan_id'], $pDate);
                        $total_pendapatan += $amount;
                        $total_transaksi++;
                    }
                    continue;
                }
            }
            // Fallback data single-payment lama
            $trxDate = date('Y-m-d', strtotime($row['tanggal_pemesanan']));
            if ($trxDate < $filterStart || $trxDate > $filterEnd) continue;
            $amount = floatval($row['harga_id']);
            _push_rekap_user($rekap, $userId, $jenis, $row['jenis_rekening_id'], $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount, $row['no_plat_id'], $row['tujuan_id'], $trxDate);
            $total_pendapatan += $amount;
            $total_transaksi++;
        }
    }

    // ======================== PENGIRIMAN (Paket) =====================
    if ($jenis_transaksi !== 'tiket') {
        $sqlPengiriman = "SELECT pg.user_id, pg.payment_methods, pg.jumlah, pg.metode_pembayaran_id, pg.jenis_rekening_id, pg.status_pembayaran_id, pg.tanggal_pengiriman, pg.no_plat_id, pg.tujuan_id
                          FROM data_pengiriman pg $whereRolePengiriman";
        $res = mysqli_query($conn, $sqlPengiriman);
        if (!$res) {
            throw new Exception("Error query data_pengiriman: " . mysqli_error($conn));
        }
        $kirimCount = 0;
        while ($row = mysqli_fetch_assoc($res)) {
            $kirimCount++;
            $jenis = 'Paket';
            $userId = $row['user_id'];
            
            if (!empty($row['payment_methods'])) {
                $arrPay = json_decode($row['payment_methods'], true);
                if (is_array($arrPay)) {
                    foreach ($arrPay as $p) {
                        $pDate = isset($p['payment_date']) ? date('Y-m-d', strtotime($p['payment_date'])) : date('Y-m-d', strtotime($row['tanggal_pengiriman']));
                        if ($pDate < $filterStart || $pDate > $filterEnd) continue;
                        $amount = floatval($p['amount'] ?? 0);
                        _push_rekap_user($rekap, $userId, $jenis, $p['rekening_id'] ?? $row['jenis_rekening_id'], $p['method_id'] ?? $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount, $row['no_plat_id'], $row['tujuan_id'], $pDate);
                        $total_pendapatan += $amount;
                        $total_transaksi++;
                    }
                    continue;
                }
            }
            $trxDate = date('Y-m-d', strtotime($row['tanggal_pengiriman']));
            if ($trxDate < $filterStart || $trxDate > $filterEnd) continue;
            $amount = floatval($row['jumlah']);
            _push_rekap_user($rekap, $userId, $jenis, $row['jenis_rekening_id'], $row['metode_pembayaran_id'], $row['status_pembayaran_id'], $amount, $row['no_plat_id'], $row['tujuan_id'], $trxDate);
            $total_pendapatan += $amount;
            $total_transaksi++;
        }
    }



    // Tambahkan nama, metode, status + flag DP
    foreach ($rekap as &$r) {
        $r['nama_user']         = $userMap[$r['user_id']] ?? 'Unknown';
        $r['nama_rekening']     = $rekeningMap[$r['id_rekening']] ?? 'Lainnya';
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
                'pengeluaran_rekening' => []
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
        
        // Akumulasi per plat - gunakan mapping yang benar
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
        
        // Akumulasi per lokasi - gunakan mapping yang benar
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

    // Ambil data pengeluaran per user
    $pengeluaran_per_user = [];
    
    // Query pengeluaran per user dengan detail per plat
    $sqlPengeluaran = "SELECT 
        dp.jenis_pengeluaran_id,
        dp.jenis_rekening_id,
        dp.harga_operasional as jumlah,
        dp.tanggal_pengeluaran as tanggal,
        dp.kategori_pengeluaran,
        dp.user_id,
        dt.no_plat,
        djp.nama_pengeluaran as nama_kategori,
        dr.nama_rekening
    FROM data_pengeluaran dp
    LEFT JOIN data_jenis_pengeluaran djp ON dp.jenis_pengeluaran_id = djp.id_jenis_pengeluaran
    LEFT JOIN data_rekening dr ON dp.jenis_rekening_id = dr.id_rekening
    LEFT JOIN data_travel dt ON dp.travel_id = dt.id_travel
    WHERE dp.tanggal_pengeluaran BETWEEN ? AND ?
    ORDER BY dp.tanggal_pengeluaran DESC";
    
    echo "<!-- DEBUG: SQL Pengeluaran Global: $sqlPengeluaran -->";
    echo "<!-- DEBUG: Filter: $filterStart - $filterEnd -->";
    
    $stmtPengeluaran = mysqli_prepare($conn, $sqlPengeluaran);
    if (!$stmtPengeluaran) {
        throw new Exception("Error preparing pengeluaran statement: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtPengeluaran, "ss", $filterStart, $filterEnd);
    if (!mysqli_stmt_execute($stmtPengeluaran)) {
        throw new Exception("Error executing pengeluaran statement: " . mysqli_stmt_error($stmtPengeluaran));
    }
    $resultPengeluaran = mysqli_stmt_get_result($stmtPengeluaran);
    if (!$resultPengeluaran) {
        throw new Exception("Error getting pengeluaran result: " . mysqli_stmt_error($stmtPengeluaran));
    }
    
    $pengeluaranCount = 0;
    
    while ($pengeluaran = mysqli_fetch_assoc($resultPengeluaran)) {
        $pengeluaranCount++;
        $kategori = $pengeluaran['nama_kategori'] ?? 'Unknown';
        $rekening = $pengeluaran['nama_rekening'] ?? 'Unknown';
        $jumlah = $pengeluaran['jumlah'];
        $tanggal = $pengeluaran['tanggal'];
        $kategori_pengeluaran = $pengeluaran['kategori_pengeluaran'] ?? 'travel';
        $no_plat = $pengeluaran['no_plat'] ?? 'Unknown';
        $user_id = $pengeluaran['user_id'];
        
        // Inisialisasi data pengeluaran untuk user ini jika belum ada
        if (!isset($pengeluaran_per_user[$user_id])) {
            $pengeluaran_per_user[$user_id] = [
                'total_pengeluaran' => 0,
                'total_transaksi_pengeluaran' => 0,
                'pengeluaran_kategori' => [],
                'pengeluaran_rekening' => [],
                'pengeluaran_detail' => [],
                'pengeluaran_detail_per_plat' => [],
                'pengeluaran_detail_per_jenis' => []
            ];
        }
        
        // Akumulasi total pengeluaran untuk user ini
        $pengeluaran_per_user[$user_id]['total_pengeluaran'] += $jumlah;
        $pengeluaran_per_user[$user_id]['total_transaksi_pengeluaran']++;
        
        // Akumulasi per kategori untuk user ini
        $kategori_key = ($kategori_pengeluaran == 'travel') ? 'Pengeluaran Travel' : 'Pengeluaran Operasional Kantor';
        if (!isset($pengeluaran_per_user[$user_id]['pengeluaran_kategori'][$kategori_key])) {
            $pengeluaran_per_user[$user_id]['pengeluaran_kategori'][$kategori_key] = [
                'total' => 0,
                'jumlah' => 0
            ];
        }
        $pengeluaran_per_user[$user_id]['pengeluaran_kategori'][$kategori_key]['total'] += $jumlah;
        $pengeluaran_per_user[$user_id]['pengeluaran_kategori'][$kategori_key]['jumlah']++;
        
        // Akumulasi per rekening untuk user ini
        if (!isset($pengeluaran_per_user[$user_id]['pengeluaran_rekening'][$rekening])) {
            $pengeluaran_per_user[$user_id]['pengeluaran_rekening'][$rekening] = [
                'total' => 0,
                'jumlah' => 0
            ];
        }
        $pengeluaran_per_user[$user_id]['pengeluaran_rekening'][$rekening]['total'] += $jumlah;
        $pengeluaran_per_user[$user_id]['pengeluaran_rekening'][$rekening]['jumlah']++;
        
        // Simpan detail per plat untuk travel
        if ($kategori_pengeluaran == 'travel') {
            if (!isset($pengeluaran_per_user[$user_id]['pengeluaran_detail_per_plat'][$no_plat])) {
                $pengeluaran_per_user[$user_id]['pengeluaran_detail_per_plat'][$no_plat] = [
                    'total' => 0,
                    'jumlah' => 0,
                    'tanggal' => $tanggal
                ];
            }
            $pengeluaran_per_user[$user_id]['pengeluaran_detail_per_plat'][$no_plat]['total'] += $jumlah;
            $pengeluaran_per_user[$user_id]['pengeluaran_detail_per_plat'][$no_plat]['jumlah']++;
        }
        
        // Simpan detail per jenis pengeluaran untuk kantor
        if ($kategori_pengeluaran == 'kantor') {
            if (!isset($pengeluaran_per_user[$user_id]['pengeluaran_detail_per_jenis'][$kategori])) {
                $pengeluaran_per_user[$user_id]['pengeluaran_detail_per_jenis'][$kategori] = [
                    'total' => 0,
                    'jumlah' => 0,
                    'tanggal' => $tanggal
                ];
            }
            $pengeluaran_per_user[$user_id]['pengeluaran_detail_per_jenis'][$kategori]['total'] += $jumlah;
            $pengeluaran_per_user[$user_id]['pengeluaran_detail_per_jenis'][$kategori]['jumlah']++;
        }
    }
    
    echo "<!-- DEBUG: Jumlah pengeluaran diproses: $pengeluaranCount -->";
    mysqli_stmt_close($stmtPengeluaran);
    
    // Set pengeluaran per user
    foreach ($userRekap as &$userData) {
        $user_id = $userData['user_id'];
        
        // Ambil data pengeluaran untuk user ini
        $userPengeluaran = $pengeluaran_per_user[$user_id] ?? [
            'total_pengeluaran' => 0,
            'total_transaksi_pengeluaran' => 0,
            'pengeluaran_kategori' => [],
            'pengeluaran_rekening' => [],
            'pengeluaran_detail' => [],
            'pengeluaran_detail_per_plat' => [],
            'pengeluaran_detail_per_jenis' => []
        ];
        
        $userData['total_pengeluaran'] = $userPengeluaran['total_pengeluaran'];
        $userData['total_transaksi_pengeluaran'] = $userPengeluaran['total_transaksi_pengeluaran'];
        $userData['pengeluaran_kategori'] = $userPengeluaran['pengeluaran_kategori'];
        $userData['pengeluaran_rekening'] = $userPengeluaran['pengeluaran_rekening'];
        
        // Tambahkan detail pengeluaran untuk user ini
        $userData['pengeluaran_detail'] = [];
        
        // Tambahkan detail pengeluaran travel per plat
        foreach ($userPengeluaran['pengeluaran_detail_per_plat'] as $no_plat => $data) {
            $userData['pengeluaran_detail'][] = [
                'tanggal' => $data['tanggal'],
                'kategori' => 'Pengeluaran Travel',
                'rekening' => $no_plat,
                'jumlah' => $data['total'],
                'kategori_pengeluaran' => 'travel'
            ];
        }
        
        // Tambahkan detail pengeluaran kantor per jenis
        foreach ($userPengeluaran['pengeluaran_detail_per_jenis'] as $jenis_pengeluaran => $data) {
            $userData['pengeluaran_detail'][] = [
                'tanggal' => $data['tanggal'],
                'kategori' => 'Pengeluaran Operasional Kantor',
                'rekening' => $jenis_pengeluaran,
                'jumlah' => $data['total'],
                'kategori_pengeluaran' => 'kantor'
            ];
        }
        
        // Tambahkan transaksi pengeluaran ke total transaksi
        $userData['total_transaksi'] += $userData['total_transaksi_pengeluaran'];
    }
    unset($userData);

    // ======================== HITUNG TOTAL TRANSAKSI PENGELUARAN =====================
    // Hitung total pengeluaran global untuk info boxes
    $total_pengeluaran_global = 0;
    $total_transaksi_pengeluaran_global = 0;
    foreach ($pengeluaran_per_user as $userPengeluaran) {
        $total_pengeluaran_global += $userPengeluaran['total_pengeluaran'];
        $total_transaksi_pengeluaran_global += $userPengeluaran['total_transaksi_pengeluaran'];
    }
    $total_transaksi += $total_transaksi_pengeluaran_global;

} catch (Exception $e) {
    // Tampilkan error dengan format yang rapi dan hentikan eksekusi
    echo '<div class="content-wrapper p-4" style="background-color: #fcfcfc;">';
    echo '  <div class="alert alert-danger" style="border-radius: 4px; border: 1px solid #d93025; background: #fce8e6; color: #a51d24;">';
    echo '    <h5 class="font-weight-bold mb-2"><i class="fas fa-exclamation-triangle mr-2"></i> Terjadi Kesalahan</h5>';
    echo '    <p class="mb-2">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '    <small class="text-muted">File: ' . htmlspecialchars($e->getFile()) . ' (Line: ' . $e->getLine() . ')</small>';
    echo '  </div>';
    echo '</div>';
    include '../inc/footer.php';
    exit;
}
?>

<style>
/* ==========================================================================
   Paper White Design System v2.0 - Laporan Pendapatan Kasir / User
   ========================================================================== */
:root {
    --bg-white: #ffffff;
    --border-gray: #b8b8b8;
    --border-light: #e0e0e0;
    --text-main: #212529;
    --text-muted: #6c757d;
    --theme-green: #0d9f4f;
    --theme-green-hover: #0b8040;
}

.report-header-box {
    background: #ffffff;
    border: 1px solid #b8b8b8;
    border-radius: 4px;
    padding: 14px 18px;
    margin-bottom: 16px;
    box-shadow: none !important;
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
    border-bottom: 1px solid #b8b8b8;
    padding: 10px 16px;
}

.stat-card-flat {
    background: #ffffff;
    border: 1px solid #b8b8b8;
    border-radius: 4px;
    padding: 12px 14px;
    height: 100%;
    display: flex;
    align-items: center;
    box-shadow: none !important;
    transition: border-color 0.15s ease;
}

.stat-card-flat:hover {
    border-color: #7a7a7a;
}

.stat-card-flat .stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-right: 12px;
    flex-shrink: 0;
}

.stat-card-flat .stat-content {
    flex-grow: 1;
    overflow: hidden;
}

.stat-card-flat .stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #6c757d;
    margin-bottom: 2px;
}

.stat-card-flat .stat-value {
    font-size: 1.12rem;
    font-weight: 700;
    color: #212529;
    line-height: 1.2;
}

.table-excel-container {
    background: #ffffff;
    border: 1px solid #b8b8b8;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 16px;
}

.table-excel {
    width: 100%;
    border-collapse: collapse !important;
    font-size: 0.84rem;
    background: #ffffff;
    color: #212529;
    margin-bottom: 0 !important;
}

.table-excel th {
    background-color: #ffffff !important;
    color: #212529 !important;
    border: 1px solid #b8b8b8 !important;
    font-weight: 700 !important;
    text-align: center;
    vertical-align: middle !important;
    padding: 8px 10px !important;
    font-size: 0.82rem;
}

.table-excel td {
    border: 1px solid #b8b8b8 !important;
    padding: 7px 10px !important;
    vertical-align: middle !important;
    background-color: #ffffff;
}

.table-excel tbody tr:nth-of-type(even) td {
    background-color: #fafafa !important;
}

.table-excel tbody tr:hover td {
    background-color: #f1f3f4 !important;
}

.table-excel tfoot th, .table-excel tfoot td {
    background-color: #f8f9fa !important;
    border: 1px solid #b8b8b8 !important;
    font-weight: 700 !important;
    color: #212529 !important;
    padding: 8px 10px !important;
}

.detail-card-flat {
    background: #ffffff;
    border: 1px solid #b8b8b8;
    border-radius: 4px;
    box-shadow: none !important;
    margin-bottom: 20px;
}

.detail-card-flat .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #b8b8b8;
    padding: 12px 18px;
}

.sub-card-flat {
    background: #ffffff;
    border: 1px solid #b8b8b8;
    border-radius: 4px;
    box-shadow: none !important;
    margin-bottom: 16px;
}

.sub-card-flat .card-header {
    background: #ffffff;
    border-bottom: 1px solid #b8b8b8;
    padding: 9px 14px;
}

@media print {
    .no-print, .main-sidebar, .main-header, .main-footer {
        display: none !important;
    }
    .content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    .report-header-box, .stat-card-flat, .filter-card-flat, .detail-card-flat, .sub-card-flat, .table-excel-container {
        border: 1px solid #000 !important;
    }
}
</style>

<div class="content-wrapper" style="background-color: #fcfcfc;">
    <div class="content-header" style="padding: 14px 18px 8px; margin-bottom: 0;">
        <div class="container-fluid">
            <!-- Header Box Paper White v2.0 -->
            <div class="report-header-box">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <div class="d-flex align-items-center">
                            <div style="width: 44px; height: 44px; border-radius: 4px; background: #e6f4ea; border: 1px solid #b7e1cd; display: flex; align-items: center; justify-content: center; margin-right: 14px; color: #0d9f4f; font-size: 1.3rem;">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div>
                                <h1 class="m-0 text-dark" style="font-size: 1.35rem; font-weight: 700; letter-spacing: -0.3px;">Laporan Pendapatan per Kasir / User</h1>
                                <div class="text-muted" style="font-size: 0.83rem; margin-top: 2px;">
                                    <span>Rekapitulasi penerimaan uang tiket, titipan paket, dan beban per kasir loket</span>
                                    <span class="mx-1">•</span>
                                    <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                                        <i class="fas fa-building mr-1"></i><?= $role === 'super admin' ? 'Seluruh Cabang PO' : 'PO ' . htmlspecialchars($asal_po) ?>
                                    </span>
                                    <span class="badge" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                                        <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars(strtoupper($role)) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 text-right no-print mt-2 mt-md-0">
                        <button type="button" class="btn btn-default btn-sm btn-flat" onclick="window.print();" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600; margin-right: 6px;">
                            <i class="fas fa-print mr-1 text-muted"></i> Cetak Laporan
                        </button>
                        <button type="button" class="btn btn-success btn-sm btn-flat" onclick="exportToExcel()" style="background-color: #0d9f4f; border-color: #076e34; font-weight: 600;">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <section class="content" style="padding: 0 18px 24px;">
        <div class="container-fluid">
            <!-- Filter Form - Paper White v2.0 -->
            <div class="filter-card-flat no-print">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark" style="font-size: 0.92rem; margin: 0;">
                        <i class="fas fa-filter mr-1 text-muted"></i> Parameter Filter Petugas Loket / Kasir
                    </h3>
                </div>
                <div class="card-body" style="padding: 14px 16px;">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Tanggal Dari:</label>
                            <input type="date" name="tgl_dari" value="<?= htmlspecialchars($tgl_dari) ?>" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                        </div>
                        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Tanggal Sampai:</label>
                            <input type="date" name="tgl_sampai" value="<?= htmlspecialchars($tgl_sampai) ?>" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                        </div>
                        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Jenis Transaksi:</label>
                            <select name="jenis_transaksi" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="semua" <?= $jenis_transaksi === 'semua' ? 'selected' : '' ?>>Semua Transaksi</option>
                                <option value="tiket" <?= $jenis_transaksi === 'tiket' ? 'selected' : '' ?>>Tiket Penumpang</option>
                                <option value="paket" <?= $jenis_transaksi === 'paket' ? 'selected' : '' ?>>Paket Barang</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="form-label font-weight-bold text-dark" style="font-size: 0.8rem; margin-bottom: 4px;">Petugas / Kasir:</label>
                            <select name="user_id" class="form-control form-control-sm" style="border: 1px solid #b8b8b8; border-radius: 4px;">
                                <option value="">Semua Petugas / Kasir</option>
                                <?php foreach ($userMap as $uid => $uname): ?>
                                    <option value="<?= $uid ?>" <?= $selected_user === $uid ? 'selected' : '' ?>><?= htmlspecialchars($uname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-primary btn-sm btn-flat font-weight-bold" style="background-color: #0d9f4f; border-color: #076e34;">
                                    <i class="fas fa-search mr-1"></i> Terapkan Filter
                                </button>
                                <a href="pendapatan_user.php" class="btn btn-default btn-sm btn-flat font-weight-bold" style="border: 1px solid #b8b8b8; background: #ffffff;">
                                    <i class="fas fa-undo mr-1"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- KPI Ringkasan Flat (Paper White v2.0) -->
            <?php 
            $net_profit_global = $total_pendapatan - $total_pengeluaran_global; 
            ?>
            <div class="row mb-3">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #e6f4ea; border: 1px solid #b7e1cd; color: #0d9f4f;">
                            <i class="fas fa-arrow-circle-up"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Pendapatan</div>
                            <div class="stat-value" style="color: #0d9f4f;">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #e8f0fe; border: 1px solid #d2e3fc; color: #1a73e8;">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Transaksi</div>
                            <div class="stat-value"><?= number_format($total_transaksi, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #fef7e0; border: 1px solid #feefc3; color: #b06000;">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Jumlah Petugas</div>
                            <div class="stat-value"><?= count($userRekap) ?> <small style="font-size: 0.75rem; font-weight: normal; color: #6c757d;">Orang</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #fce8e6; border: 1px solid #fad2cf; color: #d93025;">
                            <i class="fas fa-arrow-circle-down"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Pengeluaran</div>
                            <div class="stat-value" style="color: #d93025;">Rp <?= number_format($total_pengeluaran_global, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: <?= $net_profit_global >= 0 ? '#e6f4ea' : '#fce8e6' ?>; border: 1px solid <?= $net_profit_global >= 0 ? '#b7e1cd' : '#fad2cf' ?>; color: <?= $net_profit_global >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Net Profit (Kas)</div>
                            <div class="stat-value" style="color: <?= $net_profit_global >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                                Rp <?= number_format($net_profit_global, 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card-flat">
                        <div class="stat-icon" style="background: #f1f3f4; border: 1px solid #dadce0; color: #5f6368;">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Periode</div>
                            <div class="stat-value" style="font-size: 0.88rem; font-weight: 600; color: #495057;">
                                <?= $filterStart === $filterEnd ? date('d/m/Y', strtotime($filterStart)) : date('d/m/y', strtotime($filterStart)) . ' - ' . date('d/m/y', strtotime($filterEnd)) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Ringkasan per Kasir / User (Paper White v2.0) -->
            <div class="table-excel-container">
                <div class="p-3 bg-white border-bottom" style="border-color: #b8b8b8 !important; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="m-0 font-weight-bold text-dark" style="font-size: 0.95rem;">
                        <i class="fas fa-list-alt mr-1 text-muted"></i> Ringkasan Rekapitulasi per Petugas Loket / Kasir
                    </h3>
                    <span class="badge" style="background: #f1f3f4; color: #495057; border: 1px solid #dadce0; font-weight: 600;">
                        Total: <?= count($userRekap) ?> Petugas
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table-excel" id="tableUserRingkasan">
                        <thead>
                            <tr>
                                <th style="width: 4%;">#</th>
                                <th style="width: 24%; text-align: left;">Nama Petugas / Kasir</th>
                                <th style="width: 14%; text-align: center;">Total Transaksi</th>
                                <th style="width: 18%; text-align: right;">Total Pendapatan (Debit)</th>
                                <th style="width: 18%; text-align: right;">Total Pengeluaran (Kredit)</th>
                                <th style="width: 14%; text-align: right;">Net Profit</th>
                                <th style="width: 8%; text-align: center;" class="no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($userRekap)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle mr-1"></i> Tidak ada data transaksi kasir pada periode dan filter yang dipilih.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = 1; 
                                foreach ($userRekap as $userData): 
                                    $net_user = $userData['total_pendapatan'] - $userData['total_pengeluaran'];
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td>
                                            <strong class="text-dark"><i class="fas fa-user mr-1 text-muted"></i> <?= htmlspecialchars($userData['nama_user']) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge" style="background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; font-weight: 600; padding: 4px 8px;">
                                                <?= number_format($userData['total_transaksi'], 0, ',', '.') ?>
                                            </span>
                                        </td>
                                        <td class="text-right font-weight-bold" style="color: #0d9f4f;">
                                            Rp <?= number_format($userData['total_pendapatan'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-right font-weight-bold" style="color: #d93025;">
                                            Rp <?= number_format($userData['total_pengeluaran'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-right font-weight-bold" style="color: <?= $net_user >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                                            Rp <?= number_format($net_user, 0, ',', '.') ?>
                                        </td>
                                        <td class="text-center no-print">
                                            <button type="button" class="btn btn-default btn-xs btn-flat" onclick="showDetail('<?= $userData['user_id'] ?>')" title="Lihat Rincian Buku Kas" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600; padding: 3px 8px;">
                                                <i class="fas fa-eye text-primary mr-1"></i> Rincian
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-center">TOTAL KESELURUHAN</th>
                                <th class="text-center">
                                    <span class="badge" style="background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; font-weight: 700; padding: 4px 8px;">
                                        <?= number_format($total_transaksi, 0, ',', '.') ?>
                                    </span>
                                </th>
                                <th class="text-right" style="color: #0d9f4f;">
                                    Rp <?= number_format($total_pendapatan, 0, ',', '.') ?>
                                </th>
                                <th class="text-right" style="color: #d93025;">
                                    Rp <?= number_format($total_pengeluaran_global, 0, ',', '.') ?>
                                </th>
                                <th class="text-right" style="color: <?= $net_profit_global >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                                    Rp <?= number_format($net_profit_global, 0, ',', '.') ?>
                                </th>
                                <th class="no-print"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Detail Section per User (Paper White v2.0) -->
            <?php foreach ($userRekap as $userData): 
                $user_net = $userData['total_pendapatan'] - $userData['total_pengeluaran'];
            ?>
                <div class="detail-card-flat detail-section" id="detail-<?= $userData['user_id'] ?>" style="display: none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.05rem;">
                                <i class="fas fa-user-circle mr-1 text-success"></i> Rincian Buku Kas & Rekap: <?= htmlspecialchars($userData['nama_user']) ?>
                            </h3>
                            <small class="text-muted d-block mt-1">Audit arus kas masuk (pembayaran tiket/paket) dan keluar (beban operasional/kantor)</small>
                        </div>
                        <button type="button" class="btn btn-default btn-xs btn-flat no-print" onclick="hideDetail('<?= $userData['user_id'] ?>')" style="border: 1px solid #b8b8b8; background: #ffffff;">
                            <i class="fas fa-times text-danger mr-1"></i> Tutup Rincian
                        </button>
                    </div>
                    <div class="card-body" style="padding: 18px;">
                        <!-- 4 Mini KPI User -->
                        <div class="row mb-3">
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="stat-card-flat">
                                    <div class="stat-icon" style="background: #e6f4ea; border: 1px solid #b7e1cd; color: #0d9f4f;">
                                        <i class="fas fa-arrow-up"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Total Pendapatan</div>
                                        <div class="stat-value" style="color: #0d9f4f;">Rp <?= number_format($userData['total_pendapatan'], 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="stat-card-flat">
                                    <div class="stat-icon" style="background: #fce8e6; border: 1px solid #fad2cf; color: #d93025;">
                                        <i class="fas fa-arrow-down"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Total Pengeluaran</div>
                                        <div class="stat-value" style="color: #d93025;">Rp <?= number_format($userData['total_pengeluaran'], 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="stat-card-flat">
                                    <div class="stat-icon" style="background: <?= $user_net >= 0 ? '#e6f4ea' : '#fce8e6' ?>; border: 1px solid <?= $user_net >= 0 ? '#b7e1cd' : '#fad2cf' ?>; color: <?= $user_net >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                                        <i class="fas fa-balance-scale"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Net Profit Kasir</div>
                                        <div class="stat-value" style="color: <?= $user_net >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                                            Rp <?= number_format($user_net, 0, ',', '.') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="stat-card-flat">
                                    <div class="stat-icon" style="background: #e8f0fe; border: 1px solid #d2e3fc; color: #1a73e8;">
                                        <i class="fas fa-receipt"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Total Transaksi</div>
                                        <div class="stat-value"><?= number_format($userData['total_transaksi'], 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabel Detail Transaksi (Arus Kas) -->
                        <div class="sub-card-flat">
                            <div class="card-header">
                                <h5 class="card-title font-weight-bold text-dark m-0" style="font-size: 0.92rem;">
                                    <i class="fas fa-book mr-1 text-muted"></i> Buku Kas Harian Petugas (Arus Kas Masuk & Keluar)
                                </h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table-excel">
                                    <thead>
                                        <tr>
                                            <th style="width: 4%;">#</th>
                                            <th style="width: 12%;">Tanggal</th>
                                            <th style="width: 12%; text-align: center;">Kategori</th>
                                            <th style="width: 36%; text-align: left;">Keterangan Transaksi</th>
                                            <th style="width: 12%; text-align: right;">Debit (Pendapatan)</th>
                                            <th style="width: 12%; text-align: right;">Kredit (Pengeluaran)</th>
                                            <th style="width: 12%; text-align: right;">Saldo Akumulasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no_trans = 1; 
                                        $saldo = 0;
                                        $semuaTransaksi = [];
                                        
                                        // Pendapatan
                                        foreach ($userData['detail'] as $detail) {
                                            $semuaTransaksi[] = [
                                                'tanggal' => $detail['tanggal'] ?? date('Y-m-d'),
                                                'jenis' => 'Pendapatan',
                                                'keterangan' => $detail['jenis_transaksi'] . ' - ' . $detail['nama_rekening'],
                                                'debit' => $detail['total_pendapatan'],
                                                'kredit' => 0
                                            ];
                                        }
                                        
                                        // Pengeluaran
                                        if (isset($userData['pengeluaran_detail']) && is_array($userData['pengeluaran_detail'])) {
                                            foreach ($userData['pengeluaran_detail'] as $pengeluaran) {
                                                $semuaTransaksi[] = [
                                                    'tanggal' => $pengeluaran['tanggal'],
                                                    'jenis' => 'Pengeluaran',
                                                    'keterangan' => $pengeluaran['kategori'] . ' - ' . $pengeluaran['rekening'],
                                                    'debit' => 0,
                                                    'kredit' => $pengeluaran['jumlah']
                                                ];
                                            }
                                        }
                                        
                                        // Urutkan berdasarkan tanggal
                                        usort($semuaTransaksi, function($a, $b) {
                                            return strtotime($a['tanggal']) - strtotime($b['tanggal']);
                                        });
                                        ?>
                                        <?php if (empty($semuaTransaksi)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-3 text-muted">
                                                    Tidak ada rincian transaksi untuk petugas ini.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($semuaTransaksi as $transaksi): 
                                                $saldo += $transaksi['debit'] - $transaksi['kredit'];
                                            ?>
                                                <tr>
                                                    <td class="text-center"><?= $no_trans++ ?></td>
                                                    <td class="text-center"><?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?></td>
                                                    <td class="text-center">
                                                        <?php if ($transaksi['jenis'] === 'Pendapatan'): ?>
                                                            <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                                                                <i class="fas fa-arrow-down mr-1"></i> Masuk (Debit)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge" style="background: #fce8e6; color: #a51d24; border: 1px solid #fad2cf; font-weight: 600;">
                                                                <i class="fas fa-arrow-up mr-1"></i> Keluar (Kredit)
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($transaksi['keterangan']) ?></td>
                                                    <td class="text-right font-weight-bold" style="color: #0d9f4f;">
                                                        <?= $transaksi['debit'] > 0 ? 'Rp ' . number_format($transaksi['debit'], 0, ',', '.') : '-' ?>
                                                    </td>
                                                    <td class="text-right font-weight-bold" style="color: #d93025;">
                                                        <?= $transaksi['kredit'] > 0 ? 'Rp ' . number_format($transaksi['kredit'], 0, ',', '.') : '-' ?>
                                                    </td>
                                                    <td class="text-right font-weight-bold" style="color: <?= $saldo >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                                                        Rp <?= number_format($saldo, 0, ',', '.') ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="4" class="text-center">TOTAL KAS PETUGAS</th>
                                            <th class="text-right" style="color: #0d9f4f;">
                                                Rp <?= number_format($userData['total_pendapatan'], 0, ',', '.') ?>
                                            </th>
                                            <th class="text-right" style="color: #d93025;">
                                                Rp <?= number_format($userData['total_pengeluaran'], 0, ',', '.') ?>
                                            </th>
                                            <th class="text-right" style="color: <?= $user_net >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                                                Rp <?= number_format($user_net, 0, ',', '.') ?>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Status Balance Keseluruhan -->
                        <?php
                        $total_rekening = array_sum(array_column($userData['pendapatan_rekening'], 'total'));
                        $total_plat = array_sum(array_column($userData['pendapatan_plat'], 'total'));
                        $total_lokasi = array_sum(array_column($userData['pendapatan_lokasi'], 'total'));
                        $total_pengeluaran = $userData['total_pengeluaran'];
                        $is_balance = ($total_rekening == $total_plat && $total_plat == $total_lokasi && $total_rekening > 0);
                        ?>
                        <div class="sub-card-flat mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title font-weight-bold text-dark m-0" style="font-size: 0.92rem;">
                                    <i class="fas fa-balance-scale mr-1 text-muted"></i> Verifikasi Konsistensi Data (Balance Check)
                                </h5>
                                <?php if ($is_balance): ?>
                                    <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 700; font-size: 0.8rem; padding: 4px 10px;">
                                        <i class="fas fa-check-circle mr-1"></i> STATUS: BALANCE
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fce8e6; color: #a51d24; border: 1px solid #fad2cf; font-weight: 700; font-size: 0.8rem; padding: 4px 10px;">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> STATUS: TIDAK BALANCE
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body" style="padding: 14px;">
                                <div class="row text-center">
                                    <div class="col-md-2 col-sm-4 mb-2">
                                        <div style="font-size: 0.75rem; color: #6c757d; font-weight: 600; text-transform: uppercase;">Total Rekening</div>
                                        <div style="font-size: 1rem; font-weight: 700; color: #1a73e8;">Rp <?= number_format($total_rekening, 0, ',', '.') ?></div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 mb-2">
                                        <div style="font-size: 0.75rem; color: #6c757d; font-weight: 600; text-transform: uppercase;">Total Armada/Plat</div>
                                        <div style="font-size: 1rem; font-weight: 700; color: #0d9f4f;">Rp <?= number_format($total_plat, 0, ',', '.') ?></div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 mb-2">
                                        <div style="font-size: 0.75rem; color: #6c757d; font-weight: 600; text-transform: uppercase;">Total Lokasi/Loket</div>
                                        <div style="font-size: 1rem; font-weight: 700; color: #b06000;">Rp <?= number_format($total_lokasi, 0, ',', '.') ?></div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 mb-2">
                                        <div style="font-size: 0.75rem; color: #6c757d; font-weight: 600; text-transform: uppercase;">Total Pengeluaran</div>
                                        <div style="font-size: 1rem; font-weight: 700; color: #d93025;">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 mb-2">
                                        <div style="font-size: 0.75rem; color: #6c757d; font-weight: 600; text-transform: uppercase;">Net Profit Kasir</div>
                                        <div style="font-size: 1rem; font-weight: 700; color: <?= ($total_rekening - $total_pengeluaran) >= 0 ? '#0d9f4f' : '#d93025' ?>;">
                                            Rp <?= number_format($total_rekening - $total_pengeluaran, 0, ',', '.') ?>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 mb-2">
                                        <div style="font-size: 0.75rem; color: #6c757d; font-weight: 600; text-transform: uppercase;">Kesesuaian</div>
                                        <div style="font-size: 0.95rem; font-weight: 700;">
                                            <?= $is_balance ? '<span class="text-success">Sesuai (100%)</span>' : '<span class="text-danger">Selisih Terdeteksi</span>' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3 Kolom Kategori Pendapatan (Rekening, Plat, Lokasi) -->
                        <div class="row">
                            <!-- Pendapatan per Rekening -->
                            <div class="col-md-4 mb-3">
                                <div class="sub-card-flat h-100">
                                    <div class="card-header">
                                        <h6 class="card-title font-weight-bold text-dark m-0" style="font-size: 0.88rem;">
                                            <i class="fas fa-university mr-1 text-muted"></i> Pendapatan per Rekening
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="list-group list-group-flush" style="font-size: 0.84rem;">
                                            <?php if (empty($userData['pendapatan_rekening'])): ?>
                                                <li class="list-group-item text-muted text-center py-3">Tidak ada data rekening</li>
                                            <?php else: ?>
                                                <?php foreach ($userData['pendapatan_rekening'] as $rekening => $data): ?>
                                                    <li class="list-group-item" style="border-color: #b8b8b8;">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <strong class="text-dark"><?= htmlspecialchars($rekening) ?></strong>
                                                            <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                                                                Rp <?= number_format($data['total'], 0, ',', '.') ?>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.78rem;">
                                                            <span>Tiket: Rp <?= number_format($data['tiket'], 0, ',', '.') ?></span>
                                                            <span>Paket: Rp <?= number_format($data['paket'], 0, ',', '.') ?></span>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="p-2 border-top text-right no-print" style="border-color: #b8b8b8 !important;">
                                        <a href="../laporan/pendapatan_bank.php?tgl_dari=<?= $filterStart ?>&tgl_sampai=<?= $filterEnd ?>&jenis_transaksi=semua" class="btn btn-default btn-xs btn-flat" target="_blank" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600;">
                                            <i class="fas fa-external-link-alt mr-1"></i> Laporan Bank
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pendapatan per Plat -->
                            <div class="col-md-4 mb-3">
                                <div class="sub-card-flat h-100">
                                    <div class="card-header">
                                        <h6 class="card-title font-weight-bold text-dark m-0" style="font-size: 0.88rem;">
                                            <i class="fas fa-bus mr-1 text-muted"></i> Pendapatan per Armada / Plat
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="list-group list-group-flush" style="font-size: 0.84rem;">
                                            <?php if (empty($userData['pendapatan_plat'])): ?>
                                                <li class="list-group-item text-muted text-center py-3">Tidak ada data armada</li>
                                            <?php else: ?>
                                                <?php foreach ($userData['pendapatan_plat'] as $plat => $data): ?>
                                                    <li class="list-group-item" style="border-color: #b8b8b8;">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <strong class="text-dark"><?= htmlspecialchars($plat) ?></strong>
                                                            <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                                                                Rp <?= number_format($data['total'], 0, ',', '.') ?>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.78rem;">
                                                            <span>Tiket: Rp <?= number_format($data['tiket'], 0, ',', '.') ?></span>
                                                            <span>Paket: Rp <?= number_format($data['paket'], 0, ',', '.') ?></span>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="p-2 border-top text-right no-print" style="border-color: #b8b8b8 !important;">
                                        <a href="../laporan/pendapatan_travel.php?tgl_dari=<?= $filterStart ?>&tgl_sampai=<?= $filterEnd ?>&jenis_transaksi=semua" class="btn btn-default btn-xs btn-flat" target="_blank" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600;">
                                            <i class="fas fa-external-link-alt mr-1"></i> Laporan Travel
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pendapatan per Lokasi -->
                            <div class="col-md-4 mb-3">
                                <div class="sub-card-flat h-100">
                                    <div class="card-header">
                                        <h6 class="card-title font-weight-bold text-dark m-0" style="font-size: 0.88rem;">
                                            <i class="fas fa-map-marker-alt mr-1 text-muted"></i> Pendapatan per Loket / Lokasi
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="list-group list-group-flush" style="font-size: 0.84rem;">
                                            <?php if (empty($userData['pendapatan_lokasi'])): ?>
                                                <li class="list-group-item text-muted text-center py-3">Tidak ada data lokasi</li>
                                            <?php else: ?>
                                                <?php foreach ($userData['pendapatan_lokasi'] as $lokasi => $data): ?>
                                                    <li class="list-group-item" style="border-color: #b8b8b8;">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <strong class="text-dark"><?= htmlspecialchars($lokasi) ?></strong>
                                                            <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #b7e1cd; font-weight: 600;">
                                                                Rp <?= number_format($data['total'], 0, ',', '.') ?>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.78rem;">
                                                            <span>Tiket: Rp <?= number_format($data['tiket'], 0, ',', '.') ?></span>
                                                            <span>Paket: Rp <?= number_format($data['paket'], 0, ',', '.') ?></span>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="p-2 border-top text-right no-print" style="border-color: #b8b8b8 !important;">
                                        <a href="../laporan/pendapatan_lokasi.php?tgl_dari=<?= $filterStart ?>&tgl_sampai=<?= $filterEnd ?>&jenis_transaksi=semua" class="btn btn-default btn-xs btn-flat" target="_blank" style="border: 1px solid #b8b8b8; background: #ffffff; font-weight: 600;">
                                            <i class="fas fa-external-link-alt mr-1"></i> Laporan Lokasi
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seksi Pengeluaran User -->
                        <?php
                        $pengeluaran_travel = $userData['pengeluaran_kategori']['Pengeluaran Travel']['total'] ?? 0;
                        $pengeluaran_kantor = $userData['pengeluaran_kategori']['Pengeluaran Operasional Kantor']['total'] ?? 0;
                        $jumlah_transaksi_travel = $userData['pengeluaran_kategori']['Pengeluaran Travel']['jumlah'] ?? 0;
                        $jumlah_transaksi_kantor = $userData['pengeluaran_kategori']['Pengeluaran Operasional Kantor']['jumlah'] ?? 0;
                        ?>
                        <?php if ($userData['total_pengeluaran'] > 0): ?>
                            <div class="row mt-2">
                                <div class="col-md-6 mb-3">
                                    <div class="sub-card-flat h-100">
                                        <div class="card-header">
                                            <h6 class="card-title font-weight-bold text-dark m-0" style="font-size: 0.88rem;">
                                                <i class="fas fa-money-bill-wave mr-1 text-danger"></i> Pengeluaran Kasir per Kategori
                                            </h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <ul class="list-group list-group-flush" style="font-size: 0.84rem;">
                                                <?php if ($pengeluaran_travel > 0): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center" style="border-color: #b8b8b8;">
                                                        <div>
                                                            <strong class="text-dark">Beban Operasional Travel</strong>
                                                            <div class="text-muted" style="font-size: 0.75rem;"><?= $jumlah_transaksi_travel ?> transaksi</div>
                                                        </div>
                                                        <span class="badge" style="background: #fce8e6; color: #a51d24; border: 1px solid #fad2cf; font-weight: 600;">
                                                            Rp <?= number_format($pengeluaran_travel, 0, ',', '.') ?>
                                                        </span>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($pengeluaran_kantor > 0): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center" style="border-color: #b8b8b8;">
                                                        <div>
                                                            <strong class="text-dark">Beban Operasional Kantor</strong>
                                                            <div class="text-muted" style="font-size: 0.75rem;"><?= $jumlah_transaksi_kantor ?> transaksi</div>
                                                        </div>
                                                        <span class="badge" style="background: #fce8e6; color: #a51d24; border: 1px solid #fad2cf; font-weight: 600;">
                                                            Rp <?= number_format($pengeluaran_kantor, 0, ',', '.') ?>
                                                        </span>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="sub-card-flat h-100">
                                        <div class="card-header">
                                            <h6 class="card-title font-weight-bold text-dark m-0" style="font-size: 0.88rem;">
                                                <i class="fas fa-wallet mr-1 text-danger"></i> Pengeluaran Kasir per Sumber Dana / Rekening
                                            </h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <ul class="list-group list-group-flush" style="font-size: 0.84rem;">
                                                <?php foreach ($userData['pengeluaran_rekening'] ?? [] as $rekening => $data): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center" style="border-color: #b8b8b8;">
                                                        <div>
                                                            <strong class="text-dark"><?= htmlspecialchars($rekening) ?></strong>
                                                            <div class="text-muted" style="font-size: 0.75rem;"><?= $data['jumlah'] ?> transaksi</div>
                                                        </div>
                                                        <span class="badge" style="background: #fce8e6; color: #a51d24; border: 1px solid #fad2cf; font-weight: 600;">
                                                            Rp <?= number_format($data['total'], 0, ',', '.') ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </section>
</div>

<script>
function showDetail(userId) {
    const detailSection = document.getElementById('detail-' + userId);
    if (!detailSection) return;

    // Sembunyikan semua detail terlebih dahulu
    document.querySelectorAll('.detail-section').forEach(section => {
        section.style.display = 'none';
    });

    // Tampilkan detail terpilih
    detailSection.style.display = 'block';
    detailSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function hideDetail(userId) {
    const detailSection = document.getElementById('detail-' + userId);
    if (detailSection) {
        detailSection.style.display = 'none';
    }
}

function exportToExcel() {
    const table = document.getElementById('tableUserRingkasan');
    if (!table) {
        alert('Tabel data kasir tidak ditemukan!');
        return;
    }

    // Clone table agar elemen no-print / tombol aksi tidak ikut ter-export
    const cloneTable = table.cloneNode(true);
    cloneTable.querySelectorAll('.no-print').forEach(el => el.remove());

    const html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Laporan Pendapatan Kasir</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
            <meta http-equiv="content-type" content="text/plain; charset=UTF-8"/>
            <style>
                table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11pt; }
                th { background-color: #f2f2f2; border: 1px solid #000000; text-align: center; font-weight: bold; padding: 6px; }
                td { border: 1px solid #000000; padding: 5px; }
            </style>
        </head>
        <body>
            <h3 style="margin-bottom: 4px;">Laporan Pendapatan per Kasir / Petugas Loket</h3>
            <p style="margin-top: 0; font-size: 10pt; color: #555;">Periode: <?= $filterStart === $filterEnd ? date('d/m/Y', strtotime($filterStart)) : date('d/m/Y', strtotime($filterStart)) . ' s/d ' . date('d/m/Y', strtotime($filterEnd)) ?> | Asal PO: <?= htmlspecialchars($asal_po ?: 'Semua Cabang') ?></p>
            ${cloneTable.outerHTML}
        </body>
        </html>
    `;

    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Laporan_Pendapatan_Kasir_${new Date().toISOString().slice(0, 10)}.xls`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../inc/footer.php'; ?>

