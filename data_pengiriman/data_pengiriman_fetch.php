<?php
session_start();
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

// Server-side processing parameters
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$search = $_GET['search']['value'] ?? '';
$orderColumn = $_GET['order'][0]['column'] ?? 0;
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$draw = intval($_GET['draw'] ?? 1);

// Base query
$id_users_escaped = mysqli_real_escape_string($conn, $id_users);
if ($role === 'super admin') {
    $baseQuery = "SELECT dp.* FROM data_pengiriman dp";
    $countQuery = "SELECT COUNT(*) FROM data_pengiriman dp";
} else {
    $baseQuery = "SELECT dp.* FROM data_pengiriman dp WHERE dp.user_id = '$id_users_escaped' OR dp.asal_po_id = '$id_users_escaped'";
    $countQuery = "SELECT COUNT(*) FROM data_pengiriman dp WHERE dp.user_id = '$id_users_escaped' OR dp.asal_po_id = '$id_users_escaped'";
}

// Search filter
if (!empty($search)) {
    $searchEscaped = mysqli_real_escape_string($conn, $search);
    $searchFilter = " AND (dp.nip_sgt_id LIKE '%$searchEscaped%' 
                          OR dp.nama_id LIKE '%$searchEscaped%' 
                          OR dp.nama_penerima LIKE '%$searchEscaped%'
                          OR dp.no_hp_id LIKE '%$searchEscaped%'
                          OR dp.no_hp_penerima LIKE '%$searchEscaped%'
                          OR dp.jenis_barang LIKE '%$searchEscaped%'
                          OR dp.no_plat_id LIKE '%$searchEscaped%'
                          OR dp.keterangan LIKE '%$searchEscaped%')";
    if (strpos($baseQuery, 'WHERE') !== false) {
        $baseQuery .= $searchFilter;
        $countQuery .= $searchFilter;
    } else {
        $baseQuery .= " WHERE " . ltrim($searchFilter, ' AND');
        $countQuery .= " WHERE " . ltrim($searchFilter, ' AND');
    }
}

// Khusus: pencarian exact pada kolom ID (kolom 0) melalui columns[0][search][value]
$idExact = isset($_GET['columns'][0]['search']['value']) ? trim($_GET['columns'][0]['search']['value']) : '';
if ($idExact !== '') {
    // Jika val regex seperti ^123$ dari client, ambil angkanya
    if (preg_match('/^(?:\^)?(\d+)(?:\$)?$/', $idExact, $m)) {
        $idVal = (int)$m[1];
        $baseQuery .= (strpos($baseQuery, 'WHERE') !== false ? " AND" : " WHERE") . " dp.id_pengiriman = $idVal";
        $countQuery .= (strpos($countQuery, 'WHERE') !== false ? " AND" : " WHERE") . " dp.id_pengiriman = $idVal";
    } else if (ctype_digit($idExact)) {
        $idVal = (int)$idExact;
        $baseQuery .= (strpos($baseQuery, 'WHERE') !== false ? " AND" : " WHERE") . " dp.id_pengiriman = $idVal";
        $countQuery .= (strpos($countQuery, 'WHERE') !== false ? " AND" : " WHERE") . " dp.id_pengiriman = $idVal";
    }
}

// Get total records count
$totalRecordsQuery = "SELECT COUNT(*) FROM data_pengiriman" . ($role !== 'super admin' ? " WHERE user_id = '$id_users_escaped' OR asal_po_id = '$id_users_escaped'" : "");
$totalRecords = 0;
$totalRecordResult = mysqli_query($conn, $totalRecordsQuery);
if ($totalRecordResult) {
    $totalRecords = mysqli_fetch_array($totalRecordResult)[0];
}

// Get filtered records count
$filteredRecords = 0;
$filteredRecordResult = mysqli_query($conn, $countQuery);
if ($filteredRecordResult) {
    $filteredRecords = mysqli_fetch_array($filteredRecordResult)[0];
}

// Order by
$orderColumns = ['id_pengiriman', 'id_pengiriman', 'nip_sgt_id', 'nama_id', 'alamat_id', 'no_ktp_id', 'no_hp_id', 'tanggal_pengiriman', 'jenis_barang', 'nama_penerima', 'no_hp_penerima', 'no_plat_id', 'kelas_id', 'tujuan_id', 'status_pembayaran_id', 'metode_pembayaran_id', 'jenis_rekening_id', 'jumlah', 'user_id', 'asal_po_id', 'keterangan'];
$orderColumnName = $orderColumns[$orderColumn] ?? 'id_pengiriman';
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
        
        $status_class = 'badge-secondary';
        if (strpos(strtolower($statusText), 'lunas') !== false) $status_class = 'badge-success';
        elseif (strpos(strtolower($statusText), 'pending') !== false) $status_class = 'badge-warning';
        elseif (strpos(strtolower($statusText), 'batal') !== false) $status_class = 'badge-danger';

        $actionButtons = '
          <div class="btn-group" role="group">
            <button class="btn btn-warning btn-sm btn-edit" 
              data-id="' . $row['id_pengiriman'] . '"
              data-nip="' . htmlspecialchars($row['nip_sgt_id']) . '"
              data-nama="' . htmlspecialchars($row['nama_id']) . '"
              data-alamat="' . htmlspecialchars($row['alamat_id']) . '"
              data-ktp="' . htmlspecialchars($row['no_ktp_id']) . '"
              data-hp="' . htmlspecialchars($row['no_hp_id']) . '"
              data-tgl="' . htmlspecialchars($row['tanggal_pengiriman']) . '"
              data-barang="' . htmlspecialchars($row['jenis_barang']) . '"
              data-penerima="' . htmlspecialchars($row['nama_penerima']) . '"
              data-hppenerima="' . htmlspecialchars($row['no_hp_penerima']) . '"
              data-plat="' . htmlspecialchars($row['no_plat_id']) . '"
              data-kelas="' . htmlspecialchars($row['kelas_id']) . '"
              data-tujuan="' . htmlspecialchars($row['tujuan_id']) . '"
              data-status="' . htmlspecialchars($row['status_pembayaran_id']) . '"
              data-metode="' . htmlspecialchars($row['metode_pembayaran_id']) . '"
              data-rekening="' . htmlspecialchars($row['jenis_rekening_id']) . '"
              data-jumlah="' . htmlspecialchars($row['jumlah']) . '"
              data-user="' . htmlspecialchars($row['user_id']) . '"
              data-asalpo="' . htmlspecialchars($row['asal_po_id']) . '"
              data-keterangan="' . htmlspecialchars($row['keterangan']) . '">
              <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-danger btn-sm btn-delete-pengiriman" data-id="' . $row['id_pengiriman'] . '">
              <i class="fas fa-trash"></i>
            </button>';
        
        if (strtolower($statusText) === 'lunas') {
            $actionButtons .= '
            <button class="btn btn-primary btn-sm btn-kwitansi-paket" 
              data-id="' . $row['id_pengiriman'] . '"
              data-id-pengiriman="' . $row['id_pengiriman'] . '"
              data-nama="' . htmlspecialchars($row['nama_id']) . '"
              data-alamat="' . htmlspecialchars($row['alamat_id']) . '"
              data-nohp="' . htmlspecialchars($row['no_hp_id']) . '"
              data-asalpo="' . htmlspecialchars($row['asal_po_id']) . '"
              data-keterangan="' . htmlspecialchars($row['keterangan']) . '"
              data-jenis-barang="' . htmlspecialchars($row['jenis_barang']) . '"
              data-nama-penerima="' . htmlspecialchars($row['nama_penerima']) . '"
              data-nohp-penerima="' . htmlspecialchars($row['no_hp_penerima']) . '"
              data-tgl="' . htmlspecialchars($row['tanggal_pengiriman']) . '"
              data-noplat="' . htmlspecialchars($row['no_plat_id']) . '"
              data-kelas="' . htmlspecialchars($row['kelas_id']) . '"
              data-tujuan="' . htmlspecialchars($tujuanText) . '"
              data-status="' . htmlspecialchars($statusText) . '"
              data-metode="' . htmlspecialchars($metodeText) . '"
              data-jumlah="' . htmlspecialchars($row['jumlah']) . '"
              title="Kwitansi Thermal Kasir">
              <i class="fas fa-box"></i>
            </button>
            <a href="cetak_resi.php?id=' . $row['id_pengiriman'] . '" target="_blank" class="btn btn-primary btn-sm btn-resi-paket" title="Cetak Resi Cargo / Paket" style="background-color: #0b3b7b; border-color: #082852; color: #fff;">
              <i class="fas fa-boxes"></i>
            </a>';
        }

        $actionButtons .= '</div>';

        $data[] = [
            '<span class="badge badge-secondary">' . $row['id_pengiriman'] . '</span>',
            $actionButtons,
            '<span class="badge badge-info">' . htmlspecialchars($row['nip_sgt_id']) . '</span>',
            '<strong>' . htmlspecialchars($row['nama_id']) . '</strong>',
            htmlspecialchars($row['alamat_id']),
            '<code>' . htmlspecialchars($row['no_ktp_id']) . '</code>',
            '<a href="tel:' . $row['no_hp_id'] . '" class="text-primary"><i class="fas fa-phone mr-1"></i>' . htmlspecialchars($row['no_hp_id']) . '</a>',
            '<span class="badge badge-light">' . $row['tanggal_pengiriman'] . '</span>',
            '<span class="badge badge-warning">' . htmlspecialchars($row['jenis_barang']) . '</span>',
            '<strong>' . htmlspecialchars($row['nama_penerima']) . '</strong>',
            '<a href="tel:' . $row['no_hp_penerima'] . '" class="text-success"><i class="fas fa-phone mr-1"></i>' . htmlspecialchars($row['no_hp_penerima']) . '</a>',
            '<span class="badge badge-dark">' . htmlspecialchars($row['no_plat_id']) . '</span>',
            '<span class="badge badge-success">' . htmlspecialchars($row['kelas_id']) . '</span>',
            '<span class="badge badge-success">' . htmlspecialchars($tujuanText) . '</span>',
            '<span class="badge ' . $status_class . '">' . htmlspecialchars($statusText) . '</span>',
            '<span class="badge badge-info">' . htmlspecialchars($metodeText) . '</span>',
            '<span class="badge badge-dark">' . htmlspecialchars($rekeningText) . '</span>',
            '<strong>Rp ' . number_format($row['jumlah']) . '</strong>',
            htmlspecialchars($userMap[$row['user_id']] ?? $row['user_id']),
            htmlspecialchars($row['asal_po_id']),
            htmlspecialchars($row['keterangan'])
        ];
    }
}

$response = [
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'description' => 'Optimized Shipment Data',
    'data' => $data
];

header('Content-Type: application/json');
echo json_encode($response);
