<?php
include '../inc/koneksi.php';

if (isset($_GET['id_users'])) {
    $id = (int)$_GET['id_users'];
    $sql = "SELECT * FROM data_users WHERE id_users = $id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    echo json_encode($data);
}
?>
