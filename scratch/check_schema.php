<?php
require __DIR__ . '/../inc/koneksi.php';

echo "=== DATA_PEMESANAN ===\n";
$res1 = mysqli_query($conn, "DESCRIBE data_pemesanan");
while ($r = mysqli_fetch_assoc($res1)) {
    echo "{$r['Field']} | {$r['Type']} | Null: {$r['Null']} | Default: {$r['Default']}\n";
}

echo "\n=== DATA_PENGIRIMAN ===\n";
$res2 = mysqli_query($conn, "DESCRIBE data_pengiriman");
while ($r = mysqli_fetch_assoc($res2)) {
    echo "{$r['Field']} | {$r['Type']} | Null: {$r['Null']} | Default: {$r['Default']}\n";
}
