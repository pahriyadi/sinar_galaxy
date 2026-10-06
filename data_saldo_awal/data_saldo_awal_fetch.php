<?php
include '../inc/koneksi.php';

if (isset($_GET['id_saldo_awal'])) {
    $id = (int)$_GET['id_saldo_awal'];
    $sql = "SELECT * FROM saldo_awal_bank WHERE id_saldo_awal = $id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    echo json_encode($data);
}
?>