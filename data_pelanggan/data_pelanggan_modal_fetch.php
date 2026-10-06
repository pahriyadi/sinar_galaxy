<?php
header('Content-Type: application/json; charset=utf-8');
if (!session_id()) {
    session_start();
}
require_once '../assets/session.php';
require_once '../inc/koneksi.php';

// Check session
$user = $_SESSION['user'] ?? [];
$id_users_sess = $user['id_users'] ?? '';
$asal_po_sess = $user['asal_po'] ?? '';
$role = strtolower($user['role'] ?? '');

$draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 0;
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
if ($length <= 0 || $length > 100) $length = 10;

$search = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';

$whereClauses = [];
if ($role !== 'super admin' && !empty($id_users_sess)) {
    // Tampilkan pelanggan yang dibuat oleh user / cabang ini atau publik
    $id_escaped = mysqli_real_escape_string($conn, $id_users_sess);
    $po_escaped = mysqli_real_escape_string($conn, $asal_po_sess);
    // Catatan: pelanggan bisa dicari lintas cabang jika diperlukan, tapi ikuti role jika ada
    // Supaya kasir bisa cari pelanggan yang sudah terdaftar di sistem:
    // jika role admin tetap bisa mencari seluruh pelanggan untuk transaksi
}

if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $whereClauses[] = "(nama LIKE '%$s%' OR nip_sgt LIKE '%$s%' OR no_hp LIKE '%$s%' OR no_ktp LIKE '%$s%' OR alamat LIKE '%$s%')";
}

$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total
$countTotalRes = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM data_pelanggan");
$totalRecords = $countTotalRes ? (int)mysqli_fetch_assoc($countTotalRes)['cnt'] : 0;

$countFilteredRes = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM data_pelanggan $whereSql");
$filteredRecords = $countFilteredRes ? (int)mysqli_fetch_assoc($countFilteredRes)['cnt'] : $totalRecords;

// Order & Limit
$orderColumnIdx = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir = (isset($_GET['order'][0]['dir']) && strtolower($_GET['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

$colMap = [
    0 => 'nip_sgt',
    1 => 'nama',
    2 => 'alamat',
    3 => 'no_ktp',
    4 => 'no_hp',
    5 => 'id_pelanggan'
];
$orderField = $colMap[$orderColumnIdx] ?? 'id_pelanggan';

$query = "SELECT id_pelanggan, nip_sgt, nama, alamat, no_ktp, no_hp 
          FROM data_pelanggan 
          $whereSql 
          ORDER BY $orderField $orderDir 
          LIMIT $start, $length";

$result = mysqli_query($conn, $query);

$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $nip = htmlspecialchars($row['nip_sgt'] ?? '', ENT_QUOTES);
        $nama = htmlspecialchars($row['nama'] ?? '', ENT_QUOTES);
        $alamat = htmlspecialchars($row['alamat'] ?? '', ENT_QUOTES);
        $ktp = htmlspecialchars($row['no_ktp'] ?? '', ENT_QUOTES);
        $hp = htmlspecialchars($row['no_hp'] ?? '', ENT_QUOTES);

        $actionBtn = '<button type="button" class="btn btn-success btn-xs btn-flat pilih-pelanggan-modal" ' .
            'data-nip="' . $nip . '" ' .
            'data-nama="' . $nama . '" ' .
            'data-alamat="' . $alamat . '" ' .
            'data-ktp="' . $ktp . '" ' .
            'data-hp="' . $hp . '" ' .
            'style="background:#0d9f4f; border-color:#076e34; padding:3px 10px; font-weight:600;">' .
            '<i class="fas fa-check mr-1"></i>Pilih</button>';

        $data[] = [
            '<span class="badge" style="background:#f1f3f4; color:#202124; border:1px solid #dadce0;">' . $nip . '</span>',
            '<strong>' . $nama . '</strong>',
            $alamat,
            '<code>' . $ktp . '</code>',
            $hp,
            $actionBtn
        ];
    }
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
]);
