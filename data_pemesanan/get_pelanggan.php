<?php
require_once '../inc/koneksi.php';

header('Content-Type: application/json');

if (isset($_GET['nip_sgt'])) {
    $nip_sgt = $_GET['nip_sgt'];
    
    $sql = "SELECT nama, alamat, no_ktp, no_hp FROM data_pelanggan WHERE nip_sgt = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nip_sgt);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'nama' => $row['nama'],
            'alamat' => $row['alamat'],
            'no_ktp' => $row['no_ktp'],
            'no_hp' => $row['no_hp']
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
        'message' => 'Parameter nip_sgt tidak ditemukan'
    ]);
}
?> 