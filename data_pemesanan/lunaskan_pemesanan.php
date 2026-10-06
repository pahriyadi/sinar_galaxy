<?php
session_start();
require_once '../inc/koneksi.php';
require_once '../assets/fungsi.php';
header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  echo json_encode(['success'=>false,'msg'=>'Invalid ID']);
  exit;
}

$row = getPemesananById($id);
if (!$row) {
  echo json_encode(['success'=>false,'msg'=>'Data tidak ditemukan']);
  exit;
}

$payment_methods = [];
if (!empty($row['payment_methods']) && json_validate($row['payment_methods'])) {
  $payment_methods = json_decode($row['payment_methods'], true);
}
if (!is_array($payment_methods)) $payment_methods = [];

$harga = (float)$row['harga_id'];
$totalPaid = 0;
foreach ($payment_methods as $p) {
  $totalPaid += (float)($p['amount'] ?? 0);
}
$selisih = $harga - $totalPaid;
if ($selisih <= 0.01) {
  echo json_encode(['success'=>false,'msg'=>'Sudah lunas']);
  exit;
}

// default method & rekening ambil dari POST jika ada
$method_id = isset($_POST['method_id']) ? (int)$_POST['method_id'] : ($payment_methods[0]['method_id'] ?? $row['metode_pembayaran_id']);
$rekening_id = isset($_POST['rekening_id']) ? (int)$_POST['rekening_id'] : ($payment_methods[0]['rekening_id'] ?? $row['jenis_rekening_id']);

$payment_methods[] = [
  'method_id' => $method_id,
  'amount' => $selisih,
  'rekening_id' => $rekening_id,
  'payment_date' => date('Y-m-d')
];

$payment_json = mysqli_real_escape_string($conn, json_encode($payment_methods));
// dapatkan id status lunas
$resSt = mysqli_query($conn, "SELECT id_status_pembayaran FROM data_status_pembayaran WHERE LOWER(status_pembayaran)='lunas' LIMIT 1");
$lunasId = $row['status_pembayaran_id'];
if ($rs = mysqli_fetch_assoc($resSt)) $lunasId = $rs['id_status_pembayaran'];

$sql = "UPDATE data_pemesanan SET payment_methods='$payment_json', status_pembayaran_id='$lunasId', metode_pembayaran_id='$method_id', jenis_rekening_id='$rekening_id' WHERE id_pemesanan=$id";
if (mysqli_query($conn,$sql)) {
  echo json_encode(['success'=>true]);
} else {
  echo json_encode(['success'=>false,'msg'=>mysqli_error($conn)]);
}

function json_validate($string) {
  json_decode($string);
  return (json_last_error() == JSON_ERROR_NONE);
}
?>