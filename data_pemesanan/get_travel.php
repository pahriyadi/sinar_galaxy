<?php
require_once '../inc/koneksi.php';
header('Content-Type: application/json');
$no_plat = isset($_GET['no_plat']) ? $_GET['no_plat'] : '';
if ($no_plat === '') {
    echo json_encode(['error' => 'No Plat tidak valid']);
    exit;
}
$res = mysqli_query($conn, "SELECT kelas, harga FROM data_travel WHERE no_plat = '" . mysqli_real_escape_string($conn, $no_plat) . "' LIMIT 1");
if ($row = mysqli_fetch_assoc($res)) {
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Data tidak ditemukan']);
}
