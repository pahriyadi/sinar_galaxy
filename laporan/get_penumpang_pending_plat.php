<?php
/**
 * API Endpoint: Ambil daftar tiket penumpang yang belum memiliki plat armada
 * PO. Sinar Galaxy Travel
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../assets/session.php';
require_once __DIR__ . '/../inc/koneksi.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$user = $_SESSION['user'] ?? [];
$role = strtolower((string)($user['role'] ?? ''));

// Guard: Hanya Super Admin dan Admin yang berhak mengakses
if (!in_array($role, ['super admin', 'admin'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak: Hanya Admin dan Super Admin yang dapat mengakses data ini.'
    ]);
    exit;
}

$tanggal = trim($_GET['tanggal'] ?? date('Y-m-d'));
$jam = trim($_GET['jam'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

$tanggalSafe = mysqli_real_escape_string($conn, $tanggal);

$whereJam = '';
if (!empty($jam) && $jam !== 'all') {
    $jamSafe = mysqli_real_escape_string($conn, $jam);
    if (strlen($jamSafe) === 5) {
        $jamPattern = $jamSafe . '%';
    } else {
        $jamPattern = $jamSafe;
    }
    $whereJam = " AND (pm.jam_keberangkatan LIKE '$jamPattern' OR TIME(pm.tanggal_berangkat) LIKE '$jamPattern') ";
}

$sql = "SELECT 
            pm.id_pemesanan,
            pm.nip_sgt_id,
            pm.nama_id as nama,
            pm.no_hp_id as no_hp,
            pm.kursi,
            pm.tanggal_berangkat,
            COALESCE(pm.jam_keberangkatan, TIME(pm.tanggal_berangkat)) as jam_berangkat,
            pm.no_plat_id,
            pm.kelas_id,
            pm.harga_id,
            tj.nama_tujuan,
            sp.status_pembayaran
        FROM data_pemesanan pm
        LEFT JOIN data_tujuan_perjalanan tj ON pm.tujuan_id = tj.id_tujuan_perjalanan
        LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
        WHERE DATE(pm.tanggal_berangkat) = '$tanggalSafe'
          AND (pm.no_plat_id IS NULL OR pm.no_plat_id = '' OR pm.no_plat_id = '-' OR pm.no_plat_id = 'BELUM DITENTUKAN')
          $whereJam
        ORDER BY pm.kursi ASC, pm.id_pemesanan ASC";

$res = mysqli_query($conn, $sql);
if (!$res) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil data penumpang: ' . mysqli_error($conn)
    ]);
    exit;
}

$list = [];
while ($row = mysqli_fetch_assoc($res)) {
    $jamDisplay = substr($row['jam_berangkat'] ?? '10:00:00', 0, 5);
    $list[] = [
        'id_pemesanan' => (int)$row['id_pemesanan'],
        'nip' => $row['nip_sgt_id'] ?? '-',
        'nama' => $row['nama'] ?? 'Tanpa Nama',
        'no_hp' => $row['no_hp'] ?? '-',
        'kursi' => (int)$row['kursi'],
        'jam' => $jamDisplay,
        'tujuan' => $row['nama_tujuan'] ?? '-',
        'status_pembayaran' => $row['status_pembayaran'] ?? 'Lunas',
        'harga' => (float)($row['harga_id'] ?? 0)
    ];
}

echo json_encode([
    'status' => 'success',
    'tanggal' => $tanggal,
    'total' => count($list),
    'data' => $list
]);
