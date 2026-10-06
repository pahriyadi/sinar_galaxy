<?php
include '../inc/koneksi.php';

if (isset($_GET['id_status_pembayaran'])) {
    $id = (int)$_GET['id_status_pembayaran'];
    $sql = "SELECT * FROM data_status_pembayaran WHERE id_status_pembayaran = $id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    echo json_encode($data);
}
?>
