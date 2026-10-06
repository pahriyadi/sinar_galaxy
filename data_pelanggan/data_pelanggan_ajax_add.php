<?php
header('Content-Type: application/json; charset=utf-8');
if (!session_id()) {
    session_start();
}
require_once '../assets/session.php';
require_once '../assets/fungsi.php';
require_once '../inc/koneksi.php';

// Pastikan request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Metode request tidak valid.'
    ]);
    exit;
}

// Ambil user dari session
$user = $_SESSION['user'] ?? [];
$id_users = $user['id_users'] ?? 0;
$asal_po = $user['asal_po'] ?? '';

if (empty($id_users)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesi tidak valid atau telah berakhir. Silakan login kembali.'
    ]);
    exit;
}

// Sanitasi dan ambil input
$nama = trim($_POST['nama'] ?? '');
$no_ktp = trim($_POST['no_ktp'] ?? '');
$no_hp = trim($_POST['no_hp'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');
$nip_sgt = trim($_POST['nip_sgt'] ?? '');

// Validasi input wajib
if (empty($nama)) {
    echo json_encode(['status' => 'error', 'message' => 'Nama pelanggan wajib diisi.']);
    exit;
}
if (empty($no_hp)) {
    echo json_encode(['status' => 'error', 'message' => 'Nomor HP / WhatsApp wajib diisi.']);
    exit;
}

// Generate NIP SGT otomatis jika kosong
if (empty($nip_sgt)) {
    $nip_sgt = 'SGT-5204' . rand(10000000, 99999999);
}

// Cek apakah NIP SGT sudah ada
$checkNip = $conn->prepare("SELECT id_pelanggan FROM data_pelanggan WHERE nip_sgt = ? LIMIT 1");
$checkNip->bind_param("s", $nip_sgt);
$checkNip->execute();
$checkNip->store_result();
if ($checkNip->num_rows > 0) {
    // Generate nomor baru jika bentrok
    $nip_sgt = 'SGT-5204' . rand(10000000, 99999999);
}
$checkNip->close();

// Siapkan payload untuk tambahPelanggan
$pelangganData = [
    'nama' => $nama,
    'no_ktp' => $no_ktp,
    'no_hp' => $no_hp,
    'alamat' => $alamat,
    'nip_sgt' => $nip_sgt,
    'username' => $id_users,
    'asal_po' => $asal_po
];

try {
    $inserted = tambahPelanggan($pelangganData);
    if ($inserted) {
        $newId = mysqli_insert_id($conn);
        echo json_encode([
            'status' => 'success',
            'message' => 'Pelanggan baru berhasil ditambahkan!',
            'data' => [
                'id_pelanggan' => $newId,
                'nip_sgt' => $nip_sgt,
                'nama' => $nama,
                'no_ktp' => $no_ktp,
                'no_hp' => $no_hp,
                'alamat' => $alamat
            ]
        ]);
        exit;
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menyimpan data pelanggan: ' . mysqli_error($conn)
        ]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
    exit;
}
