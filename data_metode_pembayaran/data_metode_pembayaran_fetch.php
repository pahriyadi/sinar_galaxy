<?php
include '../inc/koneksi.php';

if (isset($_GET['id_metode_pembayaran'])) {
    $id = (int)$_GET['id_metode_pembayaran'];
    $sql = "SELECT * FROM data_metode_pembayaran WHERE id_metode_pembayaran = $id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    echo json_encode($data);
}
?>
