<?php
require_once '../inc/koneksi.php';

header('Content-Type: application/json');

if (!isset($_GET['keyword']) || empty($_GET['keyword'])) {
    echo json_encode([]);
    exit;
}

$keyword = mysqli_real_escape_string($conn, $_GET['keyword']);

// Query pencarian berdasarkan NIP/SGT atau nama pelanggan
$sql = "SELECT id_pelanggan, nip_sgt, nama, alamat, no_ktp, no_hp 
        FROM data_pelanggan 
        WHERE nip_sgt LIKE '%$keyword%' OR nama LIKE '%$keyword%'
        ORDER BY nama ASC 
        LIMIT 20";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([]);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'id_pelanggan' => $row['id_pelanggan'],
        'nip_sgt' => htmlspecialchars($row['nip_sgt']),
        'nama' => htmlspecialchars($row['nama']),
        'alamat' => htmlspecialchars($row['alamat']),
        'no_ktp' => htmlspecialchars($row['no_ktp']),
        'no_hp' => htmlspecialchars($row['no_hp'])
    ];
}

echo json_encode($data);
?> 