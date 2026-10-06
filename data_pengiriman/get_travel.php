<?php
require_once '../inc/koneksi.php';

header('Content-Type: application/json');

if (isset($_GET['no_plat'])) {
    $no_plat = $_GET['no_plat'];
    
    $sql = "SELECT kelas FROM data_travel WHERE no_plat = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $no_plat);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'kelas' => $row['kelas']
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
        'message' => 'Parameter no_plat tidak ditemukan'
    ]);
}
?>
