<?php
header('Content-Type: application/json');
require_once '../inc/koneksi.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$nama = isset($_GET['nama']) ? mysqli_real_escape_string($conn, trim($_GET['nama'])) : '';

if ($id > 0) {
    $q = mysqli_query($conn, "SELECT * FROM data_jenis_barang WHERE id_barang = $id LIMIT 1");
    if ($r = mysqli_fetch_assoc($q)) {
        echo json_encode(['status' => 'success', 'data' => $r]);
        exit;
    }
} elseif (!empty($nama)) {
    $q = mysqli_query($conn, "SELECT * FROM data_jenis_barang WHERE LOWER(nama_barang) = LOWER('$nama') LIMIT 1");
    if ($r = mysqli_fetch_assoc($q)) {
        echo json_encode(['status' => 'success', 'data' => $r]);
        exit;
    }
}

// Default list all
$q = mysqli_query($conn, "SELECT * FROM data_jenis_barang ORDER BY nama_barang ASC");
$list = [];
while ($r = mysqli_fetch_assoc($q)) {
    $list[] = $r;
}
echo json_encode(['status' => 'success', 'data' => $list]);
