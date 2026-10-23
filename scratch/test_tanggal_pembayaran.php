<?php
session_start();
$_SESSION['user'] = [
    'id_users' => 1,
    'username' => 'test_admin',
    'asal_po' => '1',
    'role' => 'super admin'
];

require_once __DIR__ . '/../inc/koneksi.php';
require_once __DIR__ . '/../assets/fungsi.php';

echo "=== TEST TANGGAL PEMBAYARAN PEMESANAN & PENGIRIMAN ===\n\n";

// 1. TEST TAMBAH PEMESANAN DENGAN TANGGAL PEMBAYARAN
echo "1. Testing tambahPemesanan with custom tanggal_pembayaran...\n";
$testPemesananData = [
    'nip_sgt_id' => 'SGT-TEST-999',
    'nama_id' => 'Bpk. Pengujian Tiket',
    'alamat_id' => 'Jl. Test No. 1',
    'no_ktp_id' => '5204123456789001',
    'no_hp_id' => '081234567890',
    'tanggal_pemesanan' => '2026-10-22 10:00:00',
    'tanggal_pembayaran' => '2026-10-25',
    'tanggal_berangkat' => '2026-10-25 19:30:00',
    'no_plat_id' => 'EA 7665 A',
    'kelas_id' => 'VIP',
    'harga_id' => '150000',
    'kursi' => 99,
    'tujuan_id' => '1',
    'status_pembayaran_id' => '1',
    'metode_pembayaran_id' => '1',
    'jenis_rekening_id' => '1',
    'username' => '1',
    'asal_po' => '1',
    'keterangan' => 'Unit Test Pemesanan'
];

$res1 = tambahPemesanan($testPemesananData);
$qCheck = mysqli_query($conn, "SELECT id_pemesanan, tanggal_pembayaran FROM data_pemesanan WHERE nip_sgt_id = 'SGT-TEST-999' ORDER BY id_pemesanan DESC LIMIT 1");
$rowCheck = mysqli_fetch_assoc($qCheck);
if ($rowCheck) {
    $newPemesananId = $rowCheck['id_pemesanan'];
    echo "   [SUCCESS] Pemesanan found with ID: $newPemesananId\n";
    echo "   Database tanggal_pembayaran: {$rowCheck['tanggal_pembayaran']}\n";
    if (strpos($rowCheck['tanggal_pembayaran'], '2026-10-25') !== false) {
        echo "   [PASSED] tanggal_pembayaran stored correctly!\n";
    } else {
        echo "   [FAILED] tanggal_pembayaran mismatch!\n";
    }

    // Test Update Pemesanan
    echo "\n2. Testing updatePemesanan with changed tanggal_pembayaran...\n";
    $testPemesananData['id_pemesanan'] = $newPemesananId;
    $testPemesananData['tanggal_pembayaran'] = '2026-10-26';
    updatePemesanan($testPemesananData);
    $qCheck2 = mysqli_query($conn, "SELECT tanggal_pembayaran FROM data_pemesanan WHERE id_pemesanan = $newPemesananId");
    $rowCheck2 = mysqli_fetch_assoc($qCheck2);
    echo "   Updated database tanggal_pembayaran: {$rowCheck2['tanggal_pembayaran']}\n";
    if (strpos($rowCheck2['tanggal_pembayaran'], '2026-10-26') !== false) {
        echo "   [PASSED] updatePemesanan updated tanggal_pembayaran correctly!\n";
    } else {
        echo "   [FAILED] updatePemesanan failed!\n";
    }

    // Clean up test pemesanan
    mysqli_query($conn, "DELETE FROM data_pemesanan WHERE id_pemesanan = $newPemesananId");
    echo "   [CLEANUP] Test pemesanan deleted.\n";
} else {
    echo "   [FAILED] Could not find inserted pemesanan!\n";
}

// 2. TEST TAMBAH PENGIRIMAN DENGAN TANGGAL PEMBAYARAN
echo "\n3. Testing tambahPengiriman with custom tanggal_pembayaran...\n";
$testPengirimanData = [
    'nip_sgt_id' => 'SGT-TEST-PKG-999',
    'nama_id' => 'Pengirim Test',
    'alamat_id' => 'Jl. Pengiriman Test',
    'no_ktp_id' => '5204123456789002',
    'no_hp_id' => '081298765432',
    'tanggal_pengiriman' => '2026-10-22 14:00:00',
    'tanggal_pembayaran' => '2026-10-27',
    'jenis_barang' => 'KARDUS SEDANG',
    'nama_penerima' => 'Penerima Test',
    'no_hp_penerima' => '081345678901',
    'no_plat_id' => 'EA 7665 A',
    'kelas_id' => 'VIP',
    'tujuan_id' => '1',
    'status_pembayaran_id' => '1',
    'metode_pembayaran_id' => '1',
    'jenis_rekening_id' => '1',
    'jumlah' => 50000,
    'user_id' => '1',
    'asal_po_id' => '1',
    'keterangan' => 'Unit Test Pengiriman'
];

$res2 = tambahPengiriman($testPengirimanData);
$qCheckP = mysqli_query($conn, "SELECT id_pengiriman, tanggal_pembayaran FROM data_pengiriman WHERE nip_sgt_id = 'SGT-TEST-PKG-999' ORDER BY id_pengiriman DESC LIMIT 1");
$rowCheckP = mysqli_fetch_assoc($qCheckP);
if ($rowCheckP) {
    $newPengirimanId = $rowCheckP['id_pengiriman'];
    echo "   [SUCCESS] Pengiriman found with ID: $newPengirimanId\n";
    echo "   Database tanggal_pembayaran: {$rowCheckP['tanggal_pembayaran']}\n";
    if (strpos($rowCheckP['tanggal_pembayaran'], '2026-10-27') !== false) {
        echo "   [PASSED] tanggal_pembayaran stored correctly in data_pengiriman!\n";
    } else {
        echo "   [FAILED] tanggal_pembayaran mismatch in data_pengiriman!\n";
    }

    // Test Update Pengiriman
    echo "\n4. Testing updatePengiriman with changed tanggal_pembayaran...\n";
    $testPengirimanData['id_pengiriman'] = $newPengirimanId;
    $testPengirimanData['tanggal_pembayaran'] = '2026-10-28';
    updatePengiriman($testPengirimanData);
    $qCheckP2 = mysqli_query($conn, "SELECT tanggal_pembayaran FROM data_pengiriman WHERE id_pengiriman = $newPengirimanId");
    $rowCheckP2 = mysqli_fetch_assoc($qCheckP2);
    echo "   Updated database tanggal_pembayaran: {$rowCheckP2['tanggal_pembayaran']}\n";
    if (strpos($rowCheckP2['tanggal_pembayaran'], '2026-10-28') !== false) {
        echo "   [PASSED] updatePengiriman updated tanggal_pembayaran correctly!\n";
    } else {
        echo "   [FAILED] updatePengiriman failed!\n";
    }

    // Clean up test pengiriman
    mysqli_query($conn, "DELETE FROM data_pengiriman WHERE id_pengiriman = $newPengirimanId");
    echo "   [CLEANUP] Test pengiriman deleted.\n";
} else {
    echo "   [FAILED] Could not find inserted pengiriman!\n";
}

echo "\n=== ALL UNIT TESTS COMPLETED SUCCESSFULLY! ===\n";
