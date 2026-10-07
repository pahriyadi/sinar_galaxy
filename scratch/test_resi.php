<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['user'] = [
    'id_users' => 1,
    'username' => 'admin',
    'role' => 'super admin'
];

require_once __DIR__ . '/../inc/koneksi.php';

// Ambil 1 ID pengiriman paket yang ada
$res = mysqli_query($conn, "SELECT id_pengiriman FROM data_pengiriman ORDER BY id_pengiriman DESC LIMIT 1");
$row = mysqli_fetch_assoc($res);
$id = $row['id_pengiriman'] ?? 8722;

echo "=== TEST CETAK RESI PENGIRIMAN CARGO ===\n";
echo "Testing ID: $id\n";

$_GET['id'] = (string)$id;
$_GET['format'] = 'thermal80';

ob_start();
require __DIR__ . '/../data_pengiriman/cetak_resi.php';
$output = ob_get_clean();

$checks = [
    'Length > 1000' => strlen($output) > 1000,
    'Contains SINAR GALAXY' => strpos($output, 'SINAR GALAXY') !== false,
    'Contains CARGO PASS' => strpos($output, 'CARGO PASS') !== false,
    'Contains PENGIRIM (SHIPPER)' => strpos($output, 'PENGIRIM (SHIPPER)') !== false,
    'Contains PENERIMA (CONSIGNEE)' => strpos($output, 'PENERIMA (CONSIGNEE)') !== false,
    'Contains JsBarcode' => strpos($output, 'JsBarcode') !== false,
    'Contains QRCode' => strpos($output, 'QRCode') !== false,
    'Contains Tips Cetak modal' => strpos($output, 'modalPanduanCetak') !== false
];

$allPassed = true;
foreach ($checks as $name => $passed) {
    echo "- $name: " . ($passed ? "PASSED" : "FAILED") . "\n";
    if (!$passed) $allPassed = false;
}

echo "Result: " . ($allPassed ? "ALL PASSED" : "SOME FAILED") . " (" . strlen($output) . " bytes)\n\n";

echo "=== TEST PUBLIC E-RESI SECURITY & TOKEN ===\n";
// Unset session to simulate anonymous guest
unset($_SESSION['user']);

$validToken = substr(hash_hmac('sha256', $id . 'SGT_RESI_SALT_2026', 'sinar_galaxy_secret_key'), 0, 16);
echo "Valid Token: $validToken\n";

// Test with valid token
$_GET['id'] = (string)$id;
$_GET['token'] = $validToken;
ob_start();
require __DIR__ . '/../e-resi/pelanggan.php';
$publicOutput = ob_get_clean();

$publicChecks = [
    'Valid token renders CARGO PASS' => strpos($publicOutput, 'CARGO PASS') !== false,
    'Contains Resi Sah' => strpos($publicOutput, 'RESI SAH') !== false,
    'Contains Download PDF' => strpos($publicOutput, 'downloadPDF') !== false,
    'Contains Tips Cetak' => strpos($publicOutput, 'Tips Cetak') !== false
];

$allPublicPassed = true;
foreach ($publicChecks as $name => $passed) {
    echo "- $name: " . ($passed ? "PASSED" : "FAILED") . "\n";
    if (!$passed) $allPublicPassed = false;
}

echo "Result: " . ($allPublicPassed ? "ALL PASSED" : "SOME FAILED") . " (" . strlen($publicOutput) . " bytes)\n";
