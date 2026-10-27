<?php
/**
 * migrate_add_jam_armada.php
 * -------------------------------------------------
 * 1. Create master table ref_jam_keberangkatan & seed standard times.
 * 2. Add column jam_keberangkatan to data_pemesanan (if not exists).
 * 3. (Optional) Create master table ref_armada & seed example armada.
 * 4. Add column armada_id to data_pemesanan (if not exists).
 */
require __DIR__ . '/../inc/koneksi.php';

// 1. Master jam table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS ref_jam_keberangkatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jam TIME NOT NULL UNIQUE,
    keterangan VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "ALTER TABLE ref_jam_keberangkatan ADD COLUMN IF NOT EXISTS keterangan VARCHAR(100) NULL AFTER jam");
mysqli_query($conn, "ALTER TABLE ref_jam_keberangkatan ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER keterangan");

$standardTimes = [
    ['07:00:00', 'Pagi (Reguler)'],
    ['08:00:00', 'Pagi (Reguler)'],
    ['10:00:00', 'Siang (Reguler)'],
    ['14:00:00', 'Siang (Reguler)'],
    ['17:00:00', 'Sore (Reguler)'],
    ['20:00:00', 'Malam (Reguler)']
];
foreach ($standardTimes as $t) {
    $timeVal = $t[0];
    $ketVal = $t[1];
    $chk = mysqli_query($conn, "SELECT 1 FROM ref_jam_keberangkatan WHERE jam='$timeVal'");
    if (mysqli_num_rows($chk) == 0) {
        mysqli_query($conn, "INSERT INTO ref_jam_keberangkatan (jam, keterangan, is_active) VALUES ('$timeVal', '$ketVal', 1)");
    } else {
        mysqli_query($conn, "UPDATE ref_jam_keberangkatan SET keterangan='$ketVal' WHERE jam='$timeVal' AND (keterangan IS NULL OR keterangan = '')");
    }
}

// 2. Add jam_keberangkatan column to data_pemesanan
mysqli_query($conn, "ALTER TABLE data_pemesanan ADD COLUMN IF NOT EXISTS jam_keberangkatan TIME NULL AFTER tanggal_berangkat");

// 3. Optional armada reference table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS ref_armada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$armadaSample = [
    ['Armada A','Ekonomi'],
    ['Armada B','Bisnis']
];
foreach ($armadaSample as $a) {
    $chk = mysqli_query($conn, "SELECT 1 FROM ref_armada WHERE nama='{$a[0]}'");
    if (mysqli_num_rows($chk) == 0) {
        mysqli_query($conn, "INSERT INTO ref_armada (nama, kelas) VALUES ('{$a[0]}','{$a[1]}')");
    }
}

// 4. Add armada_id column to data_pemesanan (optional linkage)
mysqli_query($conn, "ALTER TABLE data_pemesanan ADD COLUMN IF NOT EXISTS armada_id INT NULL AFTER jam_keberangkatan");

// 5. Add payment_type column to data_pemesanan (if not exists)
mysqli_query($conn, "ALTER TABLE data_pemesanan ADD COLUMN IF NOT EXISTS payment_type VARCHAR(20) NOT NULL DEFAULT 'single' AFTER payment_details");

echo "MIGRATION_COMPLETE\n";
?>
