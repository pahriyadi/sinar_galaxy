<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Jika request untuk 1 ID, kembalikan data detail terlebih dahulu
if (isset($_GET['id_pemesanan'])) {
    require_once '../inc/koneksi.php';
    require_once '../assets/fungsi.php';
    $id = (int)$_GET['id_pemesanan'];
    $data = getPemesananById($id);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

require_once '../assets/session.php';
require_once '../inc/koneksi.php';

$user = $_SESSION['user'];
$id_users = isset($user['id_users']) ? $user['id_users'] : '';
$role = isset($user['role']) ? $user['role'] : '';
$asal_po = isset($user['asal_po']) ? $user['asal_po'] : '';

// Ambil data master untuk mapping id ke label
$tujuanMap = [];
$res = mysqli_query($conn, "SELECT id_tujuan_perjalanan, nama_tujuan FROM data_tujuan_perjalanan");
while ($r = mysqli_fetch_assoc($res)) $tujuanMap[$r['id_tujuan_perjalanan']] = $r['nama_tujuan'];
$statusMap = [];
$lunasId = null;
$res = mysqli_query($conn, "SELECT id_status_pembayaran, status_pembayaran FROM data_status_pembayaran");
while ($r = mysqli_fetch_assoc($res)) {
    $statusMap[$r['id_status_pembayaran']] = $r['status_pembayaran'];
    if (strtolower($r['status_pembayaran']) === 'lunas') {
        $lunasId = $r['id_status_pembayaran'];
    }
}
$metodeMap = [];
$res = mysqli_query($conn, "SELECT id_metode_pembayaran, metode_pembayaran FROM data_metode_pembayaran");
while ($r = mysqli_fetch_assoc($res)) $metodeMap[$r['id_metode_pembayaran']] = $r['metode_pembayaran'];
$rekeningMap = [];
$res = mysqli_query($conn, "SELECT id_rekening, nama_rekening FROM data_rekening");
while ($r = mysqli_fetch_assoc($res)) $rekeningMap[$r['id_rekening']] = $r['nama_rekening'];
$userMap = [];
$res = mysqli_query($conn, "SELECT id_users, username FROM data_users");
while ($r = mysqli_fetch_assoc($res)) $userMap[$r['id_users']] = $r['username'];
$asalPoMap = [];
$res = mysqli_query($conn, "SELECT id_users, username FROM data_users");
while ($r = mysqli_fetch_assoc($res)) $asalPoMap[$r['id_users']] = $r['username'];
$travelMap = [];

// Server-side processing parameters
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$search = $_GET['search']['value'] ?? '';
$orderColumn = $_GET['order'][0]['column'] ?? 0;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$draw = intval($_GET['draw'] ?? 1);

// Base query with prepared statements
$id_users_escaped = mysqli_real_escape_string($conn, $id_users);
if ($role === 'super admin') {
    $baseQuery = "SELECT pm.* FROM data_pemesanan pm";
    $countQuery = "SELECT COUNT(*) FROM data_pemesanan pm";
} else {
    $baseQuery = "SELECT pm.* FROM data_pemesanan pm WHERE pm.username = '$id_users_escaped' AND pm.asal_po = '$id_users_escaped'";
    $countQuery = "SELECT COUNT(*) FROM data_pemesanan pm WHERE pm.username = '$id_users_escaped' AND pm.asal_po = '$id_users_escaped'";
}

// Search filter with escaped search term
if (!empty($search)) {
    $searchEscaped = mysqli_real_escape_string($conn, $search);
    $searchFilter = " AND (pm.nip_sgt_id LIKE '%$searchEscaped%' 
                          OR pm.nama_id LIKE '%$searchEscaped%' 
                          OR pm.alamat_id LIKE '%$searchEscaped%' 
                          OR pm.no_ktp_id LIKE '%$searchEscaped%' 
                          OR pm.no_hp_id LIKE '%$searchEscaped%' 
                          OR pm.tanggal_pemesanan LIKE '%$searchEscaped%' 
                          OR pm.tanggal_berangkat LIKE '%$searchEscaped%' 
                          OR pm.no_plat_id LIKE '%$searchEscaped%' 
                          OR pm.kelas_id LIKE '%$searchEscaped%' 
                          OR pm.kursi LIKE '%$searchEscaped%' 
                          OR pm.keterangan LIKE '%$searchEscaped%')";
    $baseQuery .= $searchFilter;
    $countQuery .= $searchFilter;
}

// Khusus: pencarian exact pada kolom ID (kolom 0) melalui columns[0][search][value]
$idExact = isset($_GET['columns'][0]['search']['value']) ? trim($_GET['columns'][0]['search']['value']) : '';
if ($idExact !== '') {
    // Jika val regex seperti ^123$ dari client, ambil angkanya
    if (preg_match('/^(?:\^)?(\d+)(?:\$)?$/', $idExact, $m)) {
        $idVal = (int)$m[1];
        $baseQuery .= (strpos($baseQuery, 'WHERE') !== false ? " AND" : " WHERE") . " pm.id_pemesanan = $idVal";
        $countQuery .= (strpos($countQuery, 'WHERE') !== false ? " AND" : " WHERE") . " pm.id_pemesanan = $idVal";
    } else if (ctype_digit($idExact)) {
        $idVal = (int)$idExact;
        $baseQuery .= (strpos($baseQuery, 'WHERE') !== false ? " AND" : " WHERE") . " pm.id_pemesanan = $idVal";
        $countQuery .= (strpos($countQuery, 'WHERE') !== false ? " AND" : " WHERE") . " pm.id_pemesanan = $idVal";
    }
}

// Get total records count (before search filter applied)
$totalRecordsQuery = "SELECT COUNT(*) FROM data_pemesanan" . ($role !== 'super admin' ? " WHERE username = '$id_users_escaped' AND asal_po = '$id_users_escaped'" : "");
$totalRecords = 0;
$totalRecordResult = mysqli_query($conn, $totalRecordsQuery);
if ($totalRecordResult) {
    $totalRecords = mysqli_fetch_array($totalRecordResult)[0];
}

// Get filtered records count (with search filter applied)
$filteredRecords = 0;
$filteredRecordResult = mysqli_query($conn, $countQuery);
if ($filteredRecordResult) {
    $filteredRecords = mysqli_fetch_array($filteredRecordResult)[0];
}

// Order by
$orderColumns = ['id_pemesanan', 'id_pemesanan', 'nip_sgt_id', 'nama_id', 'alamat_id', 'no_ktp_id', 'no_hp_id', 'tanggal_pemesanan', 'tanggal_berangkat', 'no_plat_id', 'kelas_id', 'harga_id', 'kursi', 'tujuan_id', 'status_pembayaran_id', 'metode_pembayaran_id', 'jenis_rekening_id', 'username', 'asal_po', 'keterangan'];
$orderColumnName = $orderColumns[$orderColumn] ?? 'id_pemesanan';
$orderQuery = " ORDER BY $orderColumnName $orderDir";

// Limit
$limitQuery = " LIMIT $start, $length";

// Execute query
$finalQuery = $baseQuery . $orderQuery . $limitQuery;
$result = mysqli_query($conn, $finalQuery);

$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $tujuanText = isset($tujuanMap[$row['tujuan_id']]) ? $tujuanMap[$row['tujuan_id']] : $row['tujuan_id'];
        $statusText = isset($statusMap[$row['status_pembayaran_id']]) ? $statusMap[$row['status_pembayaran_id']] : $row['status_pembayaran_id'];
        $metodeText = isset($metodeMap[$row['metode_pembayaran_id']]) ? $metodeMap[$row['metode_pembayaran_id']] : $row['metode_pembayaran_id'];
        $rekeningText = isset($rekeningMap[$row['jenis_rekening_id']]) ? $rekeningMap[$row['jenis_rekening_id']] : $row['jenis_rekening_id'];
        
        // Status class logic
        $status_class = 'badge-secondary';
        if (strpos(strtolower($statusText), 'lunas') !== false) {
            $status_class = 'badge-success';
        } elseif (strpos(strtolower($statusText), 'pending') !== false) {
            $status_class = 'badge-warning';
        } elseif (strpos(strtolower($statusText), 'batal') !== false) {
            $status_class = 'badge-danger';
        }

        // Payment methods handling
        $payment_display = '';
        if (!empty($row['payment_methods'])) {
            try {
                $payment_methods = json_decode($row['payment_methods'], true);
                if (is_array($payment_methods) && count($payment_methods) > 0) {
                    foreach ($payment_methods as $payment) {
                        $method_name = isset($metodeMap[$payment['method_id']]) ? $metodeMap[$payment['method_id']] : 'Unknown';
                        $payment_display .= '<span class="badge badge-info">' . htmlspecialchars($method_name) . '</span><br>';
                    }
                } else {
                    $payment_display = '<span class="badge badge-info">' . htmlspecialchars($metodeText) . '</span>';
                }
            } catch (Exception $e) {
                $payment_display = '<span class="badge badge-info">' . htmlspecialchars($metodeText) . '</span>';
            }
        } else {
            $payment_display = '<span class="badge badge-info">' . htmlspecialchars($metodeText) . '</span>';
        }

        // Action buttons
        $isDp = ($lunasId === null) ? true : ($row['status_pembayaran_id'] != $lunasId);
        $actionButtons = '
          <div class="btn-group" role="group">
            <button class="btn btn-warning btn-sm btn-edit" data-id="' . $row['id_pemesanan'] . '" data-nip="' . htmlspecialchars($row['nip_sgt_id']) . '" data-nama="' . htmlspecialchars($row['nama_id']) . '" data-alamat="' . htmlspecialchars($row['alamat_id']) . '" data-ktp="' . htmlspecialchars($row['no_ktp_id']) . '" data-hp="' . htmlspecialchars($row['no_hp_id']) . '" data-tglp="' . htmlspecialchars($row['tanggal_pemesanan']) . '" data-tglb="' . htmlspecialchars($row['tanggal_berangkat']) . '" data-plat="' . htmlspecialchars($row['no_plat_id']) . '" data-kelas="' . htmlspecialchars($row['kelas_id']) . '" data-harga="' . htmlspecialchars($row['harga_id']) . '" data-kursi="' . htmlspecialchars($row['kursi']) . '" data-tujuan="' . htmlspecialchars($row['tujuan_id']) . '" data-status="' . htmlspecialchars($row['status_pembayaran_id']) . '" data-metode="' . htmlspecialchars($row['metode_pembayaran_id']) . '" data-rekening="' . htmlspecialchars($row['jenis_rekening_id']) . '" data-keterangan="' . htmlspecialchars($row['keterangan']) . '">
              <i class="fas fa-edit"></i>
            </button>';
        
        if ($isDp) {
            $actionButtons .= '<button class="btn btn-success btn-sm" onclick="openPelunasanModal(' . $row['id_pemesanan'] . ')">
              <i class="fas fa-check"></i>
            </button>';
        }
        
        $actionButtons .= '
            <button class="btn btn-danger btn-sm btn-delete" data-id="' . $row['id_pemesanan'] . '">
              <i class="fas fa-trash"></i>
            </button>
            <button class="btn btn-success btn-sm btn-kwitansi" data-id="' . $row['id_pemesanan'] . '" data-nama="' . htmlspecialchars($row['nama_id']) . '" data-tujuan="' . htmlspecialchars($tujuanText) . '" data-alamat="' . htmlspecialchars($row['alamat_id']) . '" data-jmlorg="1" data-tglp="' . htmlspecialchars($row['tanggal_pemesanan']) . '" data-tglb="' . htmlspecialchars($row['tanggal_berangkat']) . '" data-seat="' . htmlspecialchars($row['kursi']) . '" data-harga="' . htmlspecialchars($row['harga_id']) . '" data-nohp="' . htmlspecialchars($row['no_hp_id']) . '" data-asalpo="' . htmlspecialchars($row['asal_po']) . '" data-noplat="' . htmlspecialchars($row['no_plat_id']) . '" data-kelas="' . htmlspecialchars($row['kelas_id']) . '" data-keterangan="' . htmlspecialchars($row['keterangan']) . '">
              <i class="fas fa-print"></i>
            </button>';
        
        if ($statusText === 'lunas' || strtolower($statusText) === 'lunas') {
            $asalpo_text = $asalPoMap[$row['asal_po']] ?? $row['asal_po'] ?? '-';
            $metode_text = $metodeText ?? '-';
            $row_keterangan = $row['keterangan'] ?? '';
            
            $actionButtons .= '<button class="btn btn-primary btn-sm btn-kwitansi-pelanggan" data-id="' . $row['id_pemesanan'] . '" data-id-pemesanan="' . $row['id_pemesanan'] . '" data-nama="' . htmlspecialchars($row['nama_id']) . '" data-tujuan="' . htmlspecialchars($tujuanText) . '" data-alamat="' . htmlspecialchars($row['alamat_id'] ?? '') . '" data-tglp="' . htmlspecialchars($row['tanggal_pemesanan']) . '" data-tglb="' . htmlspecialchars($row['tanggal_berangkat']) . '" data-seat="' . htmlspecialchars($row['kursi'] ?? '') . '" data-harga="' . htmlspecialchars($row['harga_id']) . '" data-nohp="' . htmlspecialchars($row['no_hp_id'] ?? '') . '" data-asalpo="' . htmlspecialchars($asalpo_text) . '" data-noplat="' . htmlspecialchars($row['no_plat_id'] ?? '') . '" data-kelas="' . htmlspecialchars($row['kelas_id'] ?? '') . '" data-keterangan="' . htmlspecialchars($row_keterangan) . '" data-status="' . htmlspecialchars($statusText) . '" data-metode="' . htmlspecialchars($metode_text) . '">
              <i class="fas fa-receipt"></i>
            </button>';
        }
        
        $actionButtons .= '
            <button class="btn btn-info btn-sm btn-invoice" data-id="' . $row['id_pemesanan'] . '" data-nama="' . htmlspecialchars($row['nama_id']) . '" data-tujuan="' . htmlspecialchars($tujuanText) . '" data-alamat="' . htmlspecialchars($row['alamat_id']) . '" data-tglp="' . htmlspecialchars($row['tanggal_pemesanan']) . '" data-tglb="' . htmlspecialchars($row['tanggal_berangkat']) . '" data-seat="' . htmlspecialchars($row['kursi']) . '" data-noplat="' . htmlspecialchars($row['no_plat_id']) . '" data-kelas="' . htmlspecialchars($row['kelas_id']) . '" data-harga="' . htmlspecialchars($row['harga_id']) . '" data-status="' . htmlspecialchars($statusText) . '" data-username="' . htmlspecialchars($userMap[$row['username']] ?? $row['username']) . '">
              <i class="fas fa-file-invoice"></i>
            </button>
            <a href="cetak_etiket.php?id=' . $row['id_pemesanan'] . '" target="_blank" class="btn btn-primary btn-sm btn-etiket" title="Cetak E-Tiket Penumpang" style="background-color: #0b3b7b; border-color: #082852; color: #fff;">
              <i class="fas fa-id-card"></i>
            </a>
          </div>';

        $data[] = [
            '<span class="badge badge-secondary">' . htmlspecialchars($row['id_pemesanan']) . '</span>',
            $actionButtons,
            '<span class="badge badge-info">' . htmlspecialchars($row['nip_sgt_id']) . '</span>',
            '<strong>' . htmlspecialchars($row['nama_id']) . '</strong>',
            htmlspecialchars($row['alamat_id'] ?? ''),
            '<code>' . htmlspecialchars($row['no_ktp_id'] ?? '') . '</code>',
            '<a href="tel:' . ($row['no_hp_id'] ?? '') . '" class="text-primary"><i class="fas fa-phone mr-1"></i>' . htmlspecialchars($row['no_hp_id'] ?? '') . '</a>',
            '<span class="badge badge-light">' . htmlspecialchars($row['tanggal_pemesanan'] ?? '') . '</span>',
            '<span class="badge badge-warning">' . htmlspecialchars($row['tanggal_berangkat'] ?? '') . '</span>',
            '<span class="badge badge-dark">' . htmlspecialchars($row['no_plat_id'] ?? '') . '</span>',
            '<span class="badge badge-info">' . htmlspecialchars($row['kelas_id'] ?? '') . '</span>',
            '<strong class="text-success">Rp ' . number_format($row['harga_id'] ?? 0) . '</strong>',
            '<span class="badge badge-info">' . htmlspecialchars($row['kursi'] ?? '') . '</span>',
            '<span class="badge badge-success">' . htmlspecialchars($tujuanText) . '</span>',
            '<span class="badge ' . $status_class . '">' . htmlspecialchars($statusText) . '</span>',
            $payment_display,
            '<span class="badge badge-dark">' . htmlspecialchars($rekeningText) . '</span>',
            htmlspecialchars($userMap[$row['username']] ?? $row['username']),
            htmlspecialchars($asalPoMap[$row['asal_po']] ?? $row['asal_po']),
            htmlspecialchars($row['keterangan'] ?? '')
        ];
    }
}

// Response untuk DataTables
$response = [
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
];

header('Content-Type: application/json');
echo json_encode($response);

