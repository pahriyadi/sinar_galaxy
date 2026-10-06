<?php
require_once '../inc/koneksi.php';

header('Content-Type: application/json');

if (!isset($_GET['keyword']) || empty($_GET['keyword'])) {
    echo json_encode([]);
    exit;
}

$keyword = isset($_GET['keyword']) ? mysqli_real_escape_string($conn, $_GET['keyword']) : '';
$where = $keyword ? "WHERE nip_sgt LIKE '%$keyword%' OR nama LIKE '%$keyword%'" : '';
$sql = "SELECT id_pelanggan, nip_sgt, nama, alamat, no_ktp, no_hp FROM data_pelanggan $where ORDER BY nama ASC";
$result = mysqli_query($conn, $sql);

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

$total = mysqli_num_rows(mysqli_query($conn, "SELECT id_pelanggan FROM data_pelanggan"));
$filtered = $keyword ? count($data) : $total;

echo json_encode([
    'data' => $data,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered
]);
?> 