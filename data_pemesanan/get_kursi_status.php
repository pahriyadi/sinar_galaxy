<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi login tidak valid. Silakan login kembali.'
    ]);
    exit;
}

require_once __DIR__ . '/../inc/koneksi.php';

$user = $_SESSION['user'] ?? [];
$user_role = strtolower($user['role'] ?? '');
$user_asal_po = $user['asal_po'] ?? '';
$user_id = $user['id_users'] ?? 0;

$tanggal = trim($_GET['tanggal'] ?? date('Y-m-d'));
$jam = trim($_GET['jam'] ?? '10:00');
$no_plat = trim($_GET['no_plat'] ?? '');
$exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

// Validasi format tanggal YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

// Ambil data armada dari master data_travel
$armada = null;
if (!empty($no_plat)) {
    $stmt = $conn->prepare("SELECT id_travel, no_plat, kelas, harga, jumlah_kursi FROM data_travel WHERE no_plat = ? LIMIT 1");
    $stmt->bind_param('s', $no_plat);
    $stmt->execute();
    $resArmada = $stmt->get_result();
    $armada = $resArmada->fetch_assoc();
    $stmt->close();
}

if (!$armada) {
    // Ambil armada default jika tidak dipilih
    $resDef = mysqli_query($conn, "SELECT id_travel, no_plat, kelas, harga, jumlah_kursi FROM data_travel ORDER BY id_travel ASC LIMIT 1");
    $armada = mysqli_fetch_assoc($resDef);
}

if (!$armada) {
    echo json_encode([
        'success' => false,
        'message' => 'Data armada tidak ditemukan.'
    ]);
    exit;
}

$jumlah_kursi = (int)($armada['jumlah_kursi'] ?? 11);
$kelas = strtoupper($armada['kelas'] ?? 'EKONOMI');
$no_plat_aktif = $armada['no_plat'];

// Rentang toleransi jam keberangkatan (±45 menit dari slot jadwal)
if (strlen($jam) === 5) {
    $jam_full = $jam . ':00';
} else {
    $jam_full = $jam;
}

$datetime_slot = $tanggal . ' ' . $jam_full;
$slot_start = date('Y-m-d H:i:s', strtotime("$datetime_slot -45 minutes"));
$slot_end = date('Y-m-d H:i:s', strtotime("$datetime_slot +45 minutes"));

// Query pemesanan tiket pada slot ini
$sql = "SELECT 
            pm.id_pemesanan,
            pm.nama_id,
            pm.no_hp_id,
            pm.kursi,
            pm.tanggal_berangkat,
            pm.harga_id,
            pm.status_pembayaran_id,
            sp.status_pembayaran,
            tj.nama_tujuan,
            u.username as petugas,
            u.asal_po as cabang_petugas
        FROM data_pemesanan pm
        LEFT JOIN data_status_pembayaran sp ON pm.status_pembayaran_id = sp.id_status_pembayaran
        LEFT JOIN data_tujuan_perjalanan tj ON pm.tujuan_id = tj.id_tujuan_perjalanan
        LEFT JOIN data_users u ON pm.username = u.id_users
        WHERE pm.no_plat_id = ?
          AND pm.tanggal_berangkat >= ?
          AND pm.tanggal_berangkat <= ?";

if ($exclude_id > 0) {
    $sql .= " AND pm.id_pemesanan != " . $exclude_id;
}

$stmtP = $conn->prepare($sql);
$stmtP->bind_param('sss', $no_plat_aktif, $slot_start, $slot_end);
$stmtP->execute();
$resP = $stmtP->get_result();

$booked_seats = [];
while ($row = $resP->fetch_assoc()) {
    $seat_num = (int)$row['kursi'];
    if ($seat_num > 0) {
        $status_label = strtolower($row['status_pembayaran'] ?? '');
        $is_dp = (strpos($status_label, 'dp') !== false || strpos($status_label, 'piutang') !== false);

        $booked_seats[$seat_num] = [
            'id_pemesanan' => (int)$row['id_pemesanan'],
            'nama' => $row['nama_id'] ?? 'Penumpang',
            'no_hp' => $row['no_hp_id'] ?? '-',
            'tanggal_berangkat' => $row['tanggal_berangkat'],
            'tujuan' => $row['nama_tujuan'] ?? '-',
            'status_bayar' => $row['status_pembayaran'] ?? 'Lunas',
            'is_dp' => $is_dp,
            'petugas' => $row['petugas'] ?? '-',
            'cabang_petugas' => $row['cabang_petugas'] ?? '-'
        ];
    }
}
$stmtP->close();

// Siapkan matriks kursi 1 s.d. $jumlah_kursi
$seats = [];
$terisi_count = 0;

for ($i = 1; $i <= $jumlah_kursi; $i++) {
    if (isset($booked_seats[$i])) {
        $terisi_count++;
        $info = $booked_seats[$i];
        $seats[] = [
            'nomor' => $i,
            'status' => $info['is_dp'] ? 'dp' : 'occupied',
            'status_label' => $info['is_dp'] ? 'Booking (DP)' : 'Terisi (Lunas)',
            'data' => $info
        ];
    } else {
        $seats[] = [
            'nomor' => $i,
            'status' => 'available',
            'status_label' => 'Tersedia',
            'data' => null
        ];
    }
}

$tersedia_count = $jumlah_kursi - $terisi_count;
$persentase = $jumlah_kursi > 0 ? round(($terisi_count / $jumlah_kursi) * 100) : 0;

// Otorisasi role: Apakah user saat ini berhak melakukan pemesanan di rute ini
// Cabang user vs rute
$can_book = true;
if ($user_role !== 'super admin') {
    // Admin dan kasir diizinkan booking armada
    $can_book = true;
}

echo json_encode([
    'success' => true,
    'tanggal' => $tanggal,
    'jam' => $jam,
    'armada' => [
        'id_travel' => (int)$armada['id_travel'],
        'no_plat' => $armada['no_plat'],
        'kelas' => $armada['kelas'],
        'harga' => (float)$armada['harga'],
        'jumlah_kursi' => $jumlah_kursi
    ],
    'stats' => [
        'total' => $jumlah_kursi,
        'terisi' => $terisi_count,
        'tersedia' => $tersedia_count,
        'persentase' => $persentase
    ],
    'seats' => $seats,
    'user_info' => [
        'role' => $user_role,
        'asal_po' => $user_asal_po,
        'can_book' => $can_book
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
