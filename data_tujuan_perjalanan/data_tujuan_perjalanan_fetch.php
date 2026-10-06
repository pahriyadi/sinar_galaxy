<?php
include '../inc/koneksi.php';

if (isset($_GET['id_tujuan_perjalanan'])) {
    $id = (int)$_GET['id_tujuan_perjalanan'];
    $sql = "SELECT * FROM data_tujuan_perjalanan WHERE id_tujuan_perjalanan = $id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    echo json_encode($data);
}
?>
