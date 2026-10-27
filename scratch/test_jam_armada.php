<?php
/**
 * test_jam_armada.php
 * -------------------------------------------------
 * Menguji integrasi field Jam Keberangkatan & Armada
 * pada CRUD pemesanan tiket.
 */

// Simulasi session untuk user admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user'] = [
    'id_users' => 1,
    'username' => 'admin_test',
    'role' => 'super admin',
    'asal_po' => 1
];

require_once __DIR__ . '/../inc/koneksi.php';
require_once __DIR__ . '/../assets/fungsi.php';

echo "=== 1. TEST REFERENSI JAM KEBERANGKATAN ===\n";
$expectedJams = ['07:00:00', '08:00:00', '10:00:00', '14:00:00', '17:00:00', '20:00:00'];
$res = mysqli_query($conn, "SELECT jam FROM ref_jam_keberangkatan ORDER BY jam ASC");
$existingJams = [];
while ($row = mysqli_fetch_assoc($res)) {
    $existingJams[] = $row['jam'];
}

foreach ($expectedJams as $ej) {
    if (!in_array($ej, $existingJams)) {
        die("❌ Gagal: Jam $ej tidak ditemukan di ref_jam_keberangkatan!\n");
    }
}
echo "✅ Semua jam standar (" . implode(', ', $expectedJams) . ") tersedia di tabel referensi.\n";

mysqli_query($conn, "DELETE FROM data_pemesanan WHERE nip_sgt_id = 'TESTJAM999'");

echo "\n=== 2. TEST CREATE PEMESANAN DENGAN JAM KEBERANGKATAN ===\n";
$testTglDate = date('Y-m-d', strtotime('+1 day'));
$testJam = '10:00:00';
$insertData = [
    'nip_sgt_id' => 'TESTJAM999',
    'nama_id' => 'Budi Tester Jam',
    'alamat_id' => 'Jl. Angkasa No 10',
    'no_ktp_id' => '6471012345678901',
    'no_hp_id' => '081299887766',
    'tanggal_pemesanan' => date('Y-m-d H:i:s'),
    'tanggal_pembayaran' => date('Y-m-d H:i:s'),
    'tanggal_berangkat_date' => $testTglDate,
    'jam_keberangkatan' => $testJam,
    'no_plat_id' => 'KT 1234 SG',
    'kelas_id' => 'Eksekutif',
    'harga_id' => 250000,
    'kursi' => 3,
    'tujuan_id' => 1,
    'status_pembayaran_id' => 1,
    'metode_pembayaran_id' => 1,
    'jenis_rekening_id' => 1,
    'username' => 1,
    'asal_po' => 1,
    'keterangan' => 'Uji coba referensi jam'
];

$createdId = tambahPemesanan($insertData);
if (!$createdId) {
    die("❌ Gagal create pemesanan!\n");
}
echo "✅ Berhasil tambah pemesanan baru. ID = $createdId\n";

echo "\n=== 3. TEST READ DATA PEMESANAN & VERIFIKASI JAM ===\n";
$rowCreated = getPemesananById($createdId);
if (!$rowCreated) {
    die("❌ Gagal getPemesananById($createdId)!\n");
}
echo "Data dari DB: \n";
echo "- tanggal_berangkat: " . $rowCreated['tanggal_berangkat'] . "\n";
echo "- jam_keberangkatan: " . $rowCreated['jam_keberangkatan'] . "\n";

$expectedDatetime = $testTglDate . ' ' . $testJam;
if (substr($rowCreated['tanggal_berangkat'], 0, 19) !== $expectedDatetime) {
    die("❌ tanggal_berangkat tidak cocok! Diharapkan: $expectedDatetime, Didapat: " . $rowCreated['tanggal_berangkat'] . "\n");
}
if ($rowCreated['jam_keberangkatan'] !== $testJam) {
    die("❌ jam_keberangkatan tidak cocok! Diharapkan: $testJam, Didapat: " . $rowCreated['jam_keberangkatan'] . "\n");
}
echo "✅ Kolom tanggal_berangkat & jam_keberangkatan tersimpan dengan tepat!\n";

echo "\n=== 4. TEST UPDATE JAM KEBERANGKATAN ===\n";
$newJam = '17:00:00';
$updateData = $rowCreated;
$updateData['tanggal_berangkat_date'] = $testTglDate;
$updateData['jam_keberangkatan'] = $newJam;
$resUpdate = updatePemesanan($updateData);
if (!$resUpdate) {
    die("❌ Gagal update pemesanan!\n");
}

$rowUpdated = getPemesananById($createdId);
echo "- Setelah update jam_keberangkatan: " . $rowUpdated['jam_keberangkatan'] . "\n";
echo "- Setelah update tanggal_berangkat: " . $rowUpdated['tanggal_berangkat'] . "\n";
if ($rowUpdated['jam_keberangkatan'] !== $newJam) {
    die("❌ jam_keberangkatan gagal terupdate!\n");
}
$expectedUpdatedDatetime = $testTglDate . ' ' . $newJam;
if (substr($rowUpdated['tanggal_berangkat'], 0, 19) !== $expectedUpdatedDatetime) {
    die("❌ tanggal_berangkat gagal terupdate!\n");
}
echo "✅ Update jam keberangkatan berhasil!\n";

echo "\n=== 5. TEST FETCH AJAX & ACTION BUTTON ATTRIBUTES ===\n";
// Ambil baris HTML via data_pemesanan_fetch.php
$_GET = [
    'start' => 0,
    'length' => 10,
    'search' => ['value' => 'TESTJAM999']
];
$origDir = getcwd();
chdir(__DIR__ . '/../data_pemesanan');
ob_start();
include __DIR__ . '/../data_pemesanan/data_pemesanan_fetch.php';
$fetchJson = ob_get_clean();
chdir($origDir);
$fetchResult = null;
if (preg_match('/\{[\s\S]*\}/', $fetchJson, $m)) {
    $fetchResult = json_decode($m[0], true);
}

if (!isset($fetchResult['data']) || count($fetchResult['data']) === 0) {
    die("❌ data_pemesanan_fetch.php tidak mengembalikan data untuk ID $createdId\n");
}

$btnHtml = '';
foreach ($fetchResult['data'][0] as $colVal) {
    if (strpos($colVal, 'btn-edit') !== false) {
        $btnHtml = $colVal;
        break;
    }
}

if (strpos($btnHtml, 'data-jam="' . $newJam . '"') === false) {
    die("❌ data-jam tidak ditemukan atau nilainya salah pada tombol edit!\nHTML: $btnHtml\n");
}
echo "✅ Tombol Edit berhasil menyertakan atribut data-jam=\"$newJam\"!\n";

echo "\n=== 6. TEST DELETE / CLEANUP DATA UJI ===\n";
$delSql = "DELETE FROM data_pemesanan WHERE id_pemesanan = $createdId";
mysqli_query($conn, $delSql) or die("❌ Gagal delete data uji!\n");
$chkDel = getPemesananById($createdId);
if ($chkDel) {
    die("❌ Data uji belum terhapus!\n");
}
echo "✅ Data uji berhasil dibersihkan.\n";

echo "\n🟢 SEMUA PENGUJIAN JAM KEBERANGKATAN LULUS 100% TANPA ERROR!\n";
?>
