<?php
/**
 * test_crud.php
 * -------------------------------------------------
 * Skrip ini menguji operasi CRUD pada tabel data_pemesanan
 * serta memverifikasi keberadaan atribut data‑payment_type
 * dan data‑payment_methods pada tombol Edit (fetch UI).
 *
 * Jalankan:
 *   php C:\xampp\htdocs\sinargalaxy.my.id\scratch\test_crud.php
 */

require __DIR__ . '/../inc/koneksi.php';   // koneksi MySQLi $conn

// -----------------------------------------------------------------
// 1. CREATE – sisipkan data contoh
// -----------------------------------------------------------------
$now = date('Y-m-d H:i:s');
$paymentMethods = json_encode([
    ['metode' => 'Transfer', 'tanggal' => '2026-10-22', 'jumlah' => 50000],
    ['metode' => 'Cash',     'tanggal' => '2026-10-23', 'jumlah' => 25000]
]);

$insertSql = "
INSERT INTO data_pemesanan
  (nip_sgt_id, nama_id, alamat_id, no_ktp_id, no_hp_id,
   tanggal_pemesanan, tanggal_berangkat, no_plat_id,
   kelas_id, harga_id, kursi, tujuan_id,
   status_pembayaran_id, metode_pembayaran_id, jenis_rekening_id,
   keterangan, payment_type, payment_methods)
VALUES
  ('12345678', 'Test Nama', 'Jalan Test 1', '987654321', '08123456789',
   '$now', '$now', 'B 1234 CD',
   'Ekonomi', 100000, 'A1', 'Jakarta',
   1, 2, 1,
   'Uji coba', 'multiple', '$paymentMethods')
";

if (!mysqli_query($conn, $insertSql)) {
    exit("❌ CREATE gagal: " . mysqli_error($conn) . PHP_EOL);
}
$insertId = mysqli_insert_id($conn);
echo "✅ CREATE berhasil – ID baru = $insertId" . PHP_EOL;

// -----------------------------------------------------------------
// 2. READ – ambil kembali data dan bandingkan
// -----------------------------------------------------------------
$readSql = "SELECT * FROM data_pemesanan WHERE id_pemesanan = $insertId";
$res = mysqli_query($conn, $readSql);
$row = mysqli_fetch_assoc($res);

if (!$row) {
    exit("❌ READ gagal – tidak menemukan ID $insertId" . PHP_EOL);
}
if ($row['payment_type'] !== 'multiple') {
    exit("❌ READ gagal – payment_type tidak sesuai" . PHP_EOL);
}
if (json_decode($row['payment_methods'], true) !== json_decode($paymentMethods, true)) {
    exit("❌ READ gagal – payment_methods tidak sesuai" . PHP_EOL);
}
echo "✅ READ berhasil – data ter‑verifikasi" . PHP_EOL;

// -----------------------------------------------------------------
// 3. UPDATE – ubah beberapa nilai
// -----------------------------------------------------------------
$updateSql = "
UPDATE data_pemesanan
SET
    nama_id = 'Nama Di‑update',
    payment_type = 'single',
    payment_methods = NULL
WHERE id_pemesanan = $insertId
";
if (!mysqli_query($conn, $updateSql)) {
    exit("❌ UPDATE gagal: " . mysqli_error($conn) . PHP_EOL);
}
echo "✅ UPDATE berhasil – nilai diubah" . PHP_EOL;

// Verifikasi update
$res = mysqli_query($conn, $readSql);
$row = mysqli_fetch_assoc($res);
if ($row['payment_type'] !== 'single' || $row['payment_methods'] !== null) {
    exit("❌ UPDATE verifikasi gagal – nilai tidak berubah" . PHP_EOL);
}
echo "✅ UPDATE verifikasi berhasil" . PHP_EOL;

// -----------------------------------------------------------------
// 4. FETCH UI – periksa tombol Edit pada data_pemesanan_fetch.php
// -----------------------------------------------------------------
ob_start();
include __DIR__ . '/../data_pemesanan/data_pemesanan_fetch.php';   // file ini echo HTML
$fetchOutput = ob_get_clean();

// Cari baris tombol Edit yang mengandung ID yang baru dibuat
$pattern = '/data-id="\s*' . $insertId . '\s*".*?class="btn btn-warning btn-sm btn-edit".*?>/s';
if (!preg_match($pattern, $fetchOutput, $matches)) {
    exit("❌ FETCH UI gagal – tombol Edit tidak ditemukan untuk ID $insertId" . PHP_EOL);
}
$buttonHtml = $matches[0];

// Pastikan atribut payment_type & payment_methods ada
if (strpos($buttonHtml, 'data-payment_type="multiple"') === false) {
    exit("❌ FETCH UI gagal – data-payment_type tidak ada atau tidak bernilai 'multiple'" . PHP_EOL);
}
if (strpos($buttonHtml, 'data-payment_methods=') === false) {
    exit("❌ FETCH UI gagal – data-payment_methods tidak ada" . PHP_EOL);
}
echo "✅ FETCH UI berhasil – tombol Edit meng‑output atribut payment_type & payment_methods" . PHP_EOL;

// -----------------------------------------------------------------
// 5. DELETE – bersihkan data uji
// -----------------------------------------------------------------
$deleteSql = "DELETE FROM data_pemesanan WHERE id_pemesanan = $insertId";
if (!mysqli_query($conn, $deleteSql)) {
    exit("❌ DELETE gagal: " . mysqli_error($conn) . PHP_EOL);
}
echo "✅ DELETE berhasil – baris uji dihapus" . PHP_EOL;

echo PHP_EOL . "🟢 Semua tes CRUD selesai tanpa error." . PHP_EOL;
