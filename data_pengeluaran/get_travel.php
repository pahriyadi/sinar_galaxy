<?php
require_once '../inc/koneksi.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    $sql = "SELECT kelas, harga FROM data_travel WHERE id_travel = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'kelas' => $row['kelas'],
            'harga' => $row['harga']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data travel tidak ditemukan'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id tidak ditemukan'
    ]);
}
?>
