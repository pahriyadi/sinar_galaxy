<?php
/**
 * API Endpoint: Ambil daftar armada yang membawa penumpang/paket pada tanggal tertentu
 * PO. Sinar Galaxy Travel
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../assets/session.php';
require_once '../inc/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['user'] ?? [];
$role = strtolower((string)($user['role'] ?? ''));
if ($role !== 'super admin') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: Hanya Super Admin yang berwenang']);
    exit;
}

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$tanggalSafe = mysqli_real_escape_string($conn, $tanggal);

// Ambil plat dari pemesanan tiket
$sqlTiket = "SELECT 
    pm.no_plat_id,
    COALESCE(dt.kelas, pm.kelas_id, 'EKONOMI') as kelas,
    COUNT(pm.id_pemesanan) as total_penumpang,
    GROUP_CONCAT(DISTINCT TIME_FORMAT(pm.tanggal_berangkat, '%H:%i') ORDER BY pm.tanggal_berangkat SEPARATOR ', ') as jam_list
FROM data_pemesanan pm
LEFT JOIN data_travel dt ON pm.no_plat_id = dt.no_plat
WHERE DATE(pm.tanggal_berangkat) = '$tanggalSafe'
  AND pm.no_plat_id IS NOT NULL 
  AND pm.no_plat_id != ''
GROUP BY pm.no_plat_id, kelas
ORDER BY pm.no_plat_id ASC";

$resTiket = mysqli_query($conn, $sqlTiket);
$fleets = [];

if ($resTiket) {
    while ($row = mysqli_fetch_assoc($resTiket)) {
        $plat = $row['no_plat_id'];
        $fleets[$plat] = [
            'no_plat' => $plat,
            'kelas' => $row['kelas'],
            'total_penumpang' => (int)$row['total_penumpang'],
            'total_paket' => 0,
            'jam_list' => $row['jam_list'] ?: '-'
        ];
    }
}

// Ambil jumlah paket kiriman pada plat tersebut
$sqlPaket = "SELECT 
    pg.no_plat_id,
    COUNT(pg.id_pengiriman) as total_paket
FROM data_pengiriman pg
WHERE DATE(pg.tanggal_pengiriman) = '$tanggalSafe'
  AND pg.no_plat_id IS NOT NULL 
  AND pg.no_plat_id != ''
GROUP BY pg.no_plat_id";

$resPaket = mysqli_query($conn, $sqlPaket);
if ($resPaket) {
    while ($row = mysqli_fetch_assoc($resPaket)) {
        $plat = $row['no_plat_id'];
        if (isset($fleets[$plat])) {
            $fleets[$plat]['total_paket'] = (int)$row['total_paket'];
        } else {
            $fleets[$plat] = [
                'no_plat' => $plat,
                'kelas' => 'CARGO',
                'total_penumpang' => 0,
                'total_paket' => (int)$row['total_paket'],
                'jam_list' => '-'
            ];
        }
    }
}

echo json_encode([
    'status' => 'success',
    'tanggal' => $tanggal,
    'total_armada' => count($fleets),
    'data' => array_values($fleets)
]);
exit;
