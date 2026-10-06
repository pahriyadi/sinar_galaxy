<?php
session_start();
require_once '../assets/session.php';
require_once '../inc/koneksi.php';

// Check session
$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];
$id_users_sess = isset($user['id_users']) ? $user['id_users'] : '';
$asal_po_sess = isset($user['asal_po']) ? $user['asal_po'] : '';
$role = isset($user['role']) ? strtolower($user['role']) : '';

// DataTables parameters
$draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 0;
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
$search = isset($_GET['search']['value']) ? mysqli_real_escape_string($conn, $_GET['search']['value']) : '';
$orderColumn = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'desc';

// Columns mapping for ordering
$columns = [
    0 => 'p.id_pelanggan',
    1 => 'p.nama',
    2 => 'p.alamat',
    3 => 'p.no_ktp',
    4 => 'p.no_hp',
    5 => 'p.nip_sgt',
    6 => 'u.username',
    7 => 'u.asal_po',
    8 => 'p.id_pelanggan' // Action column fallback
];

// Base Query
$baseSelect = "SELECT p.*, u.username AS user_input, u.asal_po AS asal_po_user 
               FROM data_pelanggan p 
               JOIN data_users u ON p.username = u.id_users";

$whereClauses = [];
if ($role !== 'super admin') {
    $whereClauses[] = "p.username = '$id_users_sess' AND p.asal_po = '$asal_po_sess'";
}

// Search Filter
if ($search !== '') {
    $whereClauses[] = "(p.id_pelanggan LIKE '%$search%' 
                        OR p.nama LIKE '%$search%' 
                        OR p.alamat LIKE '%$search%' 
                        OR p.no_ktp LIKE '%$search%' 
                        OR p.no_hp LIKE '%$search%' 
                        OR p.nip_sgt LIKE '%$search%' 
                        OR u.username LIKE '%$search%' 
                        OR u.asal_po LIKE '%$search%')";
}

// Exact ID Search (from columns[0][search][value])
$idExact = isset($_GET['columns'][0]['search']['value']) ? trim($_GET['columns'][0]['search']['value']) : '';
if ($idExact !== '') {
    if (preg_match('/^(?:\^)?(\d+)(?:\$)?$/', $idExact, $m)) {
        $idVal = (int)$m[1];
        $whereClauses[] = "p.id_pelanggan = $idVal";
    } else if (ctype_digit($idExact)) {
        $idVal = (int)$idExact;
        $whereClauses[] = "p.id_pelanggan = $idVal";
    }
}

$whereSql = "";
if (count($whereClauses) > 0) {
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
}

// Order & Limit
$orderBy = $columns[$orderColumn] ?? 'p.id_pelanggan';
$limitSql = " LIMIT $start, $length";
$orderSql = " ORDER BY $orderBy $orderDir";

// Get total records
$totalQuery = "SELECT COUNT(*) FROM data_pelanggan p";
if ($role !== 'super admin') {
    $totalQuery .= " WHERE p.username = '$id_users_sess' AND p.asal_po = '$asal_po_sess'";
}
$totalRecordsResult = mysqli_query($conn, $totalQuery);
$totalRecords = mysqli_fetch_array($totalRecordsResult)[0];

// Get filtered records
$filteredQuery = "SELECT COUNT(*) FROM data_pelanggan p JOIN data_users u ON p.username = u.id_users $whereSql";
$filteredRecordsResult = mysqli_query($conn, $filteredQuery);
$filteredRecords = mysqli_fetch_array($filteredRecordsResult)[0];

// Final Query
$finalQuery = $baseSelect . $whereSql . $orderSql . $limitSql;
$result = mysqli_query($conn, $finalQuery);

$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $actionButtons = '
        <div class="btn-group" role="group">
            <button class="btn btn-warning btn-sm btn-edit" 
                data-id="' . $row['id_pelanggan'] . '" 
                title="Edit">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-danger btn-sm btn-delete-pelanggan" 
                data-id="' . $row['id_pelanggan'] . '" 
                title="Hapus">
                <i class="fas fa-trash"></i>
            </button>
            <button class="btn btn-info btn-sm btn-kartu" 
                data-nama="' . htmlspecialchars($row['nama']) . '"
                data-alamat="' . htmlspecialchars($row['alamat']) . '"
                data-no_ktp="' . htmlspecialchars($row['no_ktp']) . '"
                data-no_hp="' . htmlspecialchars($row['no_hp']) . '"
                data-nip_sgt="' . htmlspecialchars($row['nip_sgt']) . '" 
                title="Kartu KP-SGT">
                <i class="fas fa-id-card"></i>
            </button>
        </div>';

        $data[] = [
            '<span class="badge badge-secondary">' . $row['id_pelanggan'] . '</span>',
            '<strong>' . htmlspecialchars($row['nama']) . '</strong>',
            htmlspecialchars($row['alamat']),
            '<code>' . htmlspecialchars($row['no_ktp']) . '</code>',
            '<a href="tel:' . htmlspecialchars($row['no_hp']) . '" class="text-primary"><i class="fas fa-phone mr-1"></i>' . htmlspecialchars($row['no_hp']) . '</a>',
            '<span class="badge badge-info">' . htmlspecialchars($row['nip_sgt']) . '</span>',
            htmlspecialchars($row['user_input']),
            htmlspecialchars($row['asal_po_user']),
            $actionButtons
        ];
    }
}

$response = [
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
];

header('Content-Type: application/json');
echo json_encode($response);