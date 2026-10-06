<?php
/**
 * Action Handler: Pengalihan Penumpang & Kargo ke Armada Pengganti (Tukar Plat Massal)
 * PO. Sinar Galaxy Travel
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['user'] ?? [];
$id_users = $user['id_users'] ?? null;
$username = $user['username'] ?? 'User';
$role = strtolower((string)($user['role'] ?? ''));

// Guard: Hanya Super Admin yang berwenang mengalihkan armada keberangkatan
if ($role !== 'super admin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak: Fitur pengalihan armada keberangkatan hanya dapat diakses oleh Super Admin.'
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

$tanggal = trim($_POST['tanggal_berangkat'] ?? '');
$plat_lama = trim($_POST['plat_lama'] ?? '');
$plat_baru = trim($_POST['plat_baru'] ?? '');
$jam_baru = trim($_POST['jam_baru'] ?? '');
$pindah_paket = isset($_POST['pindah_paket']) ? (int)$_POST['pindah_paket'] : 1;
$catatan = trim($_POST['catatan'] ?? '');

// Validasi input wajib
if (empty($tanggal) || empty($plat_lama) || empty($plat_baru)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Mohon lengkapi parameter: Tanggal Keberangkatan, Plat Lama, dan Plat Pengganti.'
    ]);
    exit;
}

if ($plat_lama === $plat_baru) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nomor plat pengganti tidak boleh sama dengan nomor plat lama!'
    ]);
    exit;
}

$tanggalSafe = mysqli_real_escape_string($conn, $tanggal);
$platLamaSafe = mysqli_real_escape_string($conn, $plat_lama);
$platBaruSafe = mysqli_real_escape_string($conn, $plat_baru);

// Ambil info armada pengganti dari data_travel
$qTravelBaru = mysqli_query($conn, "SELECT no_plat, kelas, jumlah_kursi FROM data_travel WHERE no_plat = '$platBaruSafe' LIMIT 1");
if (!$qTravelBaru || mysqli_num_rows($qTravelBaru) === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Data armada pengganti (' . htmlspecialchars($plat_baru) . ') tidak ditemukan di database data travel.'
    ]);
    exit;
}
$travelBaru = mysqli_fetch_assoc($qTravelBaru);
$kelasBaru = $travelBaru['kelas'];
$kelasBaruSafe = mysqli_real_escape_string($conn, $kelasBaru);
$kapasitasBaru = (int)($travelBaru['jumlah_kursi'] ?? 0);

// Hitung data penumpang yang terdampak
$qCountTiket = mysqli_query($conn, "SELECT COUNT(*) as jml FROM data_pemesanan WHERE DATE(tanggal_berangkat) = '$tanggalSafe' AND no_plat_id = '$platLamaSafe'");
$rCountTiket = mysqli_fetch_assoc($qCountTiket);
$totalTiket = (int)($rCountTiket['jml'] ?? 0);

// Hitung data paket yang terdampak
$totalPaket = 0;
if ($pindah_paket === 1) {
    $qCountPaket = mysqli_query($conn, "SELECT COUNT(*) as jml FROM data_pengiriman WHERE DATE(tanggal_pengiriman) = '$tanggalSafe' AND no_plat_id = '$platLamaSafe'");
    $rCountPaket = mysqli_fetch_assoc($qCountPaket);
    $totalPaket = (int)($rCountPaket['jml'] ?? 0);
}

if ($totalTiket === 0 && $totalPaket === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => "Tidak ditemukan penumpang atau paket pada plat $plat_lama untuk tanggal $tanggal."
    ]);
    exit;
}

// Mulai transaksi database
mysqli_begin_transaction($conn);

try {
    // 1. Update data pemesanan tiket
    $updateTiketParts = [
        "no_plat_id = '$platBaruSafe'",
        "kelas_id = '$kelasBaruSafe'"
    ];

    if (!empty($jam_baru)) {
        // Validasi format jam HH:MM atau HH:MM:SS
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $jam_baru)) {
            $jamFormatted = strlen($jam_baru) === 5 ? $jam_baru . ':00' : $jam_baru;
            $jamSafe = mysqli_real_escape_string($conn, $jamFormatted);
            $updateTiketParts[] = "tanggal_berangkat = STR_TO_DATE(CONCAT('$tanggalSafe ', '$jamSafe'), '%Y-%m-%d %H:%i:%s')";
        }
    }

    if (!empty($catatan)) {
        $catatanSafe = mysqli_real_escape_string($conn, $catatan);
        $updateTiketParts[] = "keterangan = CONCAT(COALESCE(keterangan, ''), ' [Pengalihan Armada $plat_lama -> $plat_baru: $catatanSafe]')";
    } else {
        $updateTiketParts[] = "keterangan = CONCAT(COALESCE(keterangan, ''), ' [Dialihkan dari armada $plat_lama]')";
    }

    $sqlUpdTiket = "UPDATE data_pemesanan 
                    SET " . implode(', ', $updateTiketParts) . " 
                    WHERE DATE(tanggal_berangkat) = '$tanggalSafe' 
                      AND no_plat_id = '$platLamaSafe'";
    $resUpdTiket = mysqli_query($conn, $sqlUpdTiket);
    if (!$resUpdTiket) {
        throw new Exception("Gagal memperbarui tiket penumpang: " . mysqli_error($conn));
    }
    $affectedTiket = mysqli_affected_rows($conn);

    // 2. Update data pengiriman paket jika opsi dicentang
    $affectedPaket = 0;
    if ($pindah_paket === 1 && $totalPaket > 0) {
        $updatePaketParts = [
            "no_plat_id = '$platBaruSafe'",
            "kelas_id = '$kelasBaruSafe'"
        ];
        if (!empty($catatan)) {
            $catatanSafe = mysqli_real_escape_string($conn, $catatan);
            $updatePaketParts[] = "keterangan = CONCAT(COALESCE(keterangan, ''), ' [Pengalihan Armada $plat_lama -> $plat_baru: $catatanSafe]')";
        } else {
            $updatePaketParts[] = "keterangan = CONCAT(COALESCE(keterangan, ''), ' [Dialihkan dari armada $plat_lama]')";
        }

        $sqlUpdPaket = "UPDATE data_pengiriman 
                        SET " . implode(', ', $updatePaketParts) . " 
                        WHERE DATE(tanggal_pengiriman) = '$tanggalSafe' 
                          AND no_plat_id = '$platLamaSafe'";
        $resUpdPaket = mysqli_query($conn, $sqlUpdPaket);
        if (!$resUpdPaket) {
            throw new Exception("Gagal memperbarui paket kargo: " . mysqli_error($conn));
        }
        $affectedPaket = mysqli_affected_rows($conn);
    }

    // Commit transaksi
    mysqli_commit($conn);

    // Catat log aktivitas jika modul activity logger tersedia
    if (function_exists('logActivity')) {
        $ketLog = "Pengalihan armada keberangkatan $tanggal: Plat $plat_lama -> $plat_baru ($kelasBaru). Penumpang: $affectedTiket, Paket: $affectedPaket.";
        logActivity('UPDATE', 'laporan_keberangkatan', null, $ketLog);
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Berhasil mengalihkan $affectedTiket tiket penumpang" . ($affectedPaket > 0 ? " dan $affectedPaket paket kiriman" : "") . " dari armada $plat_lama ke armada pengganti $plat_baru ($kelasBaru).",
        'tanggal' => $tanggal,
        'plat_lama' => $plat_lama,
        'plat_baru' => $plat_baru,
        'kelas_baru' => $kelasBaru,
        'affected_tiket' => $affectedTiket,
        'affected_paket' => $affectedPaket
    ]);
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
    exit;
}
