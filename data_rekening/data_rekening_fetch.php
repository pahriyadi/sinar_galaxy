<?php
include '../inc/koneksi.php';

if (isset($_GET['id_rekening'])) {
    $id = (int)$_GET['id_rekening'];
    $sql = "SELECT * FROM data_rekening WHERE id_rekening = $id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    echo json_encode($data);
}
?>
