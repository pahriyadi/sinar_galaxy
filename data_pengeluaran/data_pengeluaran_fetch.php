<?php
session_start();
require_once '../assets/session.php';
require_once '../inc/koneksi.php';

$user = $_SESSION['user'];
$id_users = isset($user['id_users']) ? $user['id_users'] : '';
$role = isset($user['role']) ? $user['role'] : '';
$asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';

// Ambil data master untuk mapping id ke label
$statusMap = [];
$res = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
while ($r = mysqli_fetch_assoc($res)) $statusMap[$r['id_status_pembayaran']] = $r['status_pembayaran'];
$metodeMap = [];
$res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
while ($r = mysqli_fetch_assoc($res)) $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran'];
$rekeningMap = [];
$res = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening");
while ($r = mysqli_fetch_assoc($res)) $rekeningMap[$r['id_rekening']] = $r['nama_rekening'];
$userMap = [];
$res = mysqli_query($conn, "SELECT id_users, username FROM data_users");
while ($r = mysqli_fetch_assoc($res)) $userMap[$r['id_users']] = $r['username'];

if ($role === 'super admin') {
    $sql = "SELECT * FROM data_pengeluaran";
} else {
    $sql = "SELECT * FROM data_pengeluaran WHERE user_id = '$id_users' AND asal_po = '$asal_po'";
}

$result = $conn->query($sql);

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            $row['id_pengeluaran'],
            $row['tanggal_pengeluaran'],
            $row['kategori_pengeluaran'] ?? 'travel',
            $row['jenis_pengeluaran_id'],
            $row['harga_operasional'],
            $row['travel_id'] ?? '',
            $row['keterangan'] ?? '',
            $row['status_pembayaran_id'],
            $row['metode_pembayaran_id'],
            $row['jenis_rekening_id'],
            $row['user_id'],
            $row['asal_po'],
            '<button class="btn btn-sm btn-warning btn-edit" '
            .'data-id="'.$row['id_pengeluaran'].'" '
            .'data-tanggal="'.$row['tanggal_pengeluaran'].'" '
            .'data-kategori="'.($row['kategori_pengeluaran'] ?? 'travel').'" '
            .'data-jenis="'.$row['jenis_pengeluaran_id'].'" '
            .'data-hargaoperasional="'.$row['harga_operasional'].'" '
            .'data-keterangan="'.($row['keterangan'] ?? '').'" '
            .'data-travel="'.($row['travel_id'] ?? '').'" '
            .'data-kelas="'.($row['kelas'] ?? '').'" '
            .'data-harga="'.($row['harga'] ?? '').'" '
            .'data-status="'.$row['status_pembayaran_id'].'" '
            .'data-metode="'.$row['metode_pembayaran_id'].'" '
            .'data-rekening="'.$row['jenis_rekening_id'].'" '
            .'data-user="'.$row['user_id'].'" '
            .'data-asalpo="'.$row['asal_po'].'" '
            .'>Edit</button> '
            .'<button class="btn btn-sm btn-danger btn-delete" data-id="'.$row['id_pengeluaran'].'">Hapus</button>'
        ];
    }
}
echo json_encode(['data' => $data]);
