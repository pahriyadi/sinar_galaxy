<?php
require __DIR__ . '/../inc/koneksi.php';

$chk = mysqli_query($conn, "SHOW COLUMNS FROM data_pengiriman LIKE 'tanggal_pembayaran'");
if (mysqli_num_rows($chk) == 0) {
    $sql = "ALTER TABLE data_pengiriman ADD COLUMN tanggal_pembayaran datetime NULL AFTER tanggal_pengiriman";
    if (mysqli_query($conn, $sql)) {
        echo "COLUMN_ADDED_SUCCESS\n";
    } else {
        echo "ERROR: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "ALREADY_EXISTS\n";
}

$res = mysqli_query($conn, "DESCRIBE data_pengiriman");
while ($r = mysqli_fetch_assoc($res)) {
    if ($r['Field'] === 'tanggal_pembayaran' || $r['Field'] === 'tanggal_pengiriman') {
        echo "{$r['Field']} ({$r['Type']})\n";
    }
}
