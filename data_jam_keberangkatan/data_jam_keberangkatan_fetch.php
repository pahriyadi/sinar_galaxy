<?php
require_once __DIR__ . '/../assets/session.php';
require_once __DIR__ . '/../inc/koneksi.php';
require_once __DIR__ . '/../assets/fungsi.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $data = getJamKeberangkatanById($id);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Kembalikan semua jam aktif dalam format JSON
$res = mysqli_query($conn, "SELECT id, jam, keterangan, is_active FROM ref_jam_keberangkatan ORDER BY jam ASC");
$list = [];
while ($row = mysqli_fetch_assoc($res)) {
    $list[] = $row;
}
header('Content-Type: application/json');
echo json_encode($list);
exit;
?>
