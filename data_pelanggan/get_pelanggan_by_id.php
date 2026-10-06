<?php
require_once '../inc/koneksi.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    $sql = "SELECT id_pelanggan, nama, alamat, no_ktp, no_hp, nip_sgt FROM data_pelanggan WHERE id_pelanggan = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'id_pelanggan' => $row['id_pelanggan'],
            'nama' => $row['nama'],
            'alamat' => $row['alamat'],
            'no_ktp' => $row['no_ktp'],
            'no_hp' => $row['no_hp'],
            'nip_sgt' => $row['nip_sgt']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data pelanggan tidak ditemukan'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id tidak ditemukan'
    ]);
}
?> 