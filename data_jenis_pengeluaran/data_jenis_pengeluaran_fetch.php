<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../inc/koneksi.php';

if (isset($_GET['id_jenis_pengeluaran'])) {
    $id = (int)$_GET['id_jenis_pengeluaran'];
    $sql = "SELECT id_jenis_pengeluaran, nama_pengeluaran, biaya_standar, keterangan FROM data_jenis_pengeluaran WHERE id_jenis_pengeluaran = $id";
    $result = mysqli_query($conn, $sql);
    if ($result && $data = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'status' => 'success',
            'id_jenis_pengeluaran' => (int)$data['id_jenis_pengeluaran'],
            'nama_pengeluaran' => $data['nama_pengeluaran'],
            'biaya_standar' => (float)$data['biaya_standar'],
            'keterangan' => $data['keterangan'] ?? ''
        ]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
exit;
