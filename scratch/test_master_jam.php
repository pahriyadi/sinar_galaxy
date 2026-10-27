<?php
/**
 * test_master_jam.php
 * -------------------------------------------------
 * Menguji fungsionalitas Master Data Jam Keberangkatan:
 * - Create (tambah jam baru)
 * - Read (cek di database)
 * - Update (edit jam & keterangan)
 * - Fetch endpoint AJAX
 * - Delete (hapus jam uji)
 */

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

echo "=== 1. TEST TAMBAH JAM KEBERANGKATAN BARU DI MASTER DATA ===\n";
// Bersihkan jika ada bekas test
mysqli_query($conn, "DELETE FROM ref_jam_keberangkatan WHERE jam IN ('05:30:00', '05:45:00')");

$dataNew = [
    'jam' => '05:30',
    'keterangan' => 'Subuh / Early Bird Eksekutif',
    'is_active' => 1
];

$resAdd = tambahJamKeberangkatan($dataNew);
if (!$resAdd) {
    die("❌ Gagal tambahJamKeberangkatan!\n");
}
$newJamId = mysqli_insert_id($conn);
echo "✅ Berhasil tambah jadwal jam baru. ID = $newJamId\n";

echo "\n=== 2. TEST READ JADWAL JAM BARU ===\n";
$rowJam = getJamKeberangkatanById($newJamId);
if (!$rowJam) {
    die("❌ Gagal getJamKeberangkatanById($newJamId)!\n");
}
echo "Data Jam di DB:\n";
echo "- Jam: " . $rowJam['jam'] . "\n";
echo "- Keterangan: " . $rowJam['keterangan'] . "\n";
echo "- Status Aktif: " . $rowJam['is_active'] . "\n";

if ($rowJam['jam'] !== '05:30:00') {
    die("❌ Jam tidak cocok!\n");
}
if ($rowJam['keterangan'] !== 'Subuh / Early Bird Eksekutif') {
    die("❌ Keterangan tidak cocok!\n");
}
echo "✅ Jam baru tersimpan dengan format dan atribut yang tepat!\n";

echo "\n=== 3. TEST UPDATE JADWAL JAM ===\n";
$dataUpdate = [
    'id' => $newJamId,
    'jam' => '05:45',
    'keterangan' => 'Pagi Buta (Update)',
    'is_active' => 1
];
$resUpd = updateJamKeberangkatan($dataUpdate);
if (!$resUpd) {
    die("❌ Gagal updateJamKeberangkatan!\n");
}
$rowUpd = getJamKeberangkatanById($newJamId);
if ($rowUpd['jam'] !== '05:45:00' || $rowUpd['keterangan'] !== 'Pagi Buta (Update)') {
    die("❌ Update jam gagal tersimpan!\n");
}
echo "✅ Berhasil update jadwal jam ke 05:45:00 dengan keterangan baru!\n";

echo "\n=== 4. TEST FETCH ENDPOINT AJAX DATA JAM ===\n";
$cmd = "C:\\xampp\\php\\php.exe -r \"session_start(); \$_SESSION['user']=['id_users'=>1,'role'=>'super admin']; \$_GET=['id'=>$newJamId]; include 'data_jam_keberangkatan/data_jam_keberangkatan_fetch.php';\"";
$fetchJson = shell_exec($cmd);
$fetchObj = null;
if (preg_match('/\{[\s\S]*\}/', $fetchJson, $m)) {
    $fetchObj = json_decode($m[0], true);
}

if (!isset($fetchObj['id']) || $fetchObj['id'] != $newJamId) {
    die("❌ Fetch endpoint data_jam_keberangkatan_fetch.php tidak mengembalikan data ID $newJamId!\nRaw: $fetchJson\n");
}
echo "✅ Endpoint AJAX data_jam_keberangkatan_fetch.php berfungsi normal: " . json_encode($fetchObj) . "\n";

echo "\n=== 5. TEST HAPUS JADWAL JAM DARI MASTER DATA ===\n";
$resDel = deleteJamKeberangkatan($newJamId);
if (!$resDel) {
    die("❌ Gagal deleteJamKeberangkatan!\n");
}
$chkDel = getJamKeberangkatanById($newJamId);
if ($chkDel) {
    die("❌ Data jam belum terhapus!\n");
}
echo "✅ Jadwal jam uji berhasil dihapus dari master data!\n";

echo "\n🟢 SEMUA PENGUJIAN MASTER DATA JAM KEBERANGKATAN LULUS 100%!\n";
?>
