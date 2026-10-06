<?php
require_once __DIR__ . '/../inc/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $sql = "SELECT id_jenis_pengeluaran, nama_pengeluaran, biaya_standar, keterangan FROM data_jenis_pengeluaran WHERE id_jenis_pengeluaran = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'id_jenis_pengeluaran' => (int)$row['id_jenis_pengeluaran'],
            'nama_pengeluaran' => $row['nama_pengeluaran'],
            'biaya_standar' => (float)$row['biaya_standar'],
            'keterangan' => $row['keterangan'] ?? ''
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data jenis pengeluaran tidak ditemukan'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id tidak ditemukan'
    ]);
}
?>
