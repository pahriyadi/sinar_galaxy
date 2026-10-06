<?php
include '../inc/koneksi.php';

if (isset($_GET['id_travel'])) {
    $id = (int)$_GET['id_travel'];
    $sql = "SELECT * FROM data_travel WHERE id_travel = $id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    echo json_encode($data);
}
?>
