<?php
/**
 * Action Handler: Penetapan / Isi Plat Armada Penumpang Terjadwal
 * PO. Sinar Galaxy Travel
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../assets/session.php';
require_once __DIR__ . '/../assets/fungsi.php';
require_once __DIR__ . '/../inc/koneksi.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$user = $_SESSION['user'] ?? [];
$id_users = $user['id_users'] ?? null;
$username = $user['username'] ?? 'User';
$role = strtolower((string)($user['role'] ?? ''));

// Guard: Hanya Super Admin dan Admin yang berwenang
if (!in_array($role, ['super admin', 'admin'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak: Fitur ini hanya dapat diakses oleh Admin dan Super Admin.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Metode request tidak valid.'
    ]);
    exit;
}

$plat_baru = trim($_POST['plat_baru'] ?? '');
$tiket_ids = $_POST['tiket_ids'] ?? [];
$catatan = trim($_POST['catatan'] ?? '');

if (empty($plat_baru)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Silakan pilih Armada / Nomor Plat tujuan!'
    ]);
    exit;
}

if (empty($tiket_ids) || !is_array($tiket_ids)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Silakan pilih minimal satu tiket penumpang yang akan ditetapkan nomor platnya!'
    ]);
    exit;
}

// Sanitasi array ID tiket
$clean_ids = [];
foreach ($tiket_ids as $tid) {
    $tInt = (int)$tid;
    if ($tInt > 0) {
        $clean_ids[] = $tInt;
    }
}

if (empty($clean_ids)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Data tiket penumpang tidak valid.'
    ]);
    exit;
}

$platBaruSafe = mysqli_real_escape_string($conn, $plat_baru);

// Ambil info armada dari data_travel
$qTravel = mysqli_query($conn, "SELECT no_plat, kelas, jumlah_kursi FROM data_travel WHERE no_plat = '$platBaruSafe' LIMIT 1");
if (!$qTravel || mysqli_num_rows($qTravel) === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Data armada (' . htmlspecialchars($plat_baru) . ') tidak ditemukan di database travel.'
    ]);
    exit;
}

$travel = mysqli_fetch_assoc($qTravel);
$kelasBaru = $travel['kelas'] ?? 'EKONOMI';
$kelasBaruSafe = mysqli_real_escape_string($conn, $kelasBaru);
$kapasitas = (int)($travel['jumlah_kursi'] ?? 11);

// Transaksi Database
mysqli_begin_transaction($conn);

try {
    $idsStr = implode(',', $clean_ids);
    
    $updateParts = [
        "no_plat_id = '$platBaruSafe'",
        "kelas_id = '$kelasBaruSafe'"
    ];

    if (!empty($catatan)) {
        $catatanSafe = mysqli_real_escape_string($conn, $catatan);
        $updateParts[] = "keterangan = CONCAT(COALESCE(keterangan, ''), ' [Penetapan Plat $plat_baru: $catatanSafe]')";
    }

    $sqlUpd = "UPDATE data_pemesanan 
               SET " . implode(', ', $updateParts) . " 
               WHERE id_pemesanan IN ($idsStr)";

    $resUpd = mysqli_query($conn, $sqlUpd);
    if (!$resUpd) {
        throw new Exception("Gagal memperbarui tiket penumpang: " . mysqli_error($conn));
    }
    
    $affected = mysqli_affected_rows($conn);

    // Logging aktivitas sistem
    $ketLog = "Penetapan plat armada untuk " . count($clean_ids) . " penumpang ke unit $plat_baru ($kelasBaru).";
    if (function_exists('logActivity')) {
        logActivity('UPDATE', 'data_pemesanan', $idsStr, $ketLog);
    }

    mysqli_commit($conn);

    echo json_encode([
        'status' => 'success',
        'message' => "Berhasil menetapkan nomor plat $plat_baru ($kelasBaru) untuk $affected tiket penumpang.",
        'affected' => $affected,
        'plat_baru' => $plat_baru,
        'kelas' => $kelasBaru
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
