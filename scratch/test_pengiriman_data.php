<?php
require_once __DIR__ . '/../inc/koneksi.php';

$res = mysqli_query($conn, "SELECT dp.*, dt.kelas, tj.nama_tujuan, sp.status_pembayaran, mp.metode_pembayaran, dr.nama_rekening, dr.nomor_rekening, du.username as nama_kasir, du.asal_po as asal_po_kasir 
FROM data_pengiriman dp
LEFT JOIN data_travel dt ON dp.no_plat_id = dt.no_plat
LEFT JOIN data_tujuan_perjalanan tj ON dp.tujuan_id = tj.id_tujuan_perjalanan
LEFT JOIN data_status_pembayaran sp ON dp.status_pembayaran_id = sp.id_status_pembayaran
LEFT JOIN data_metode_pembayaran mp ON dp.metode_pembayaran_id = mp.id_metode_pembayaran
LEFT JOIN data_rekening dr ON dp.jenis_rekening_id = dr.id_rekening
LEFT JOIN data_users du ON dp.user_id = du.id_users
ORDER BY dp.id_pengiriman DESC LIMIT 3");

while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: " . $r['id_pengiriman'] . "\n";
    echo "Resi/NIP: " . ($r['nip_sgt_id'] ?? '-') . "\n";
    echo "Pengirim: " . $r['nama_id'] . " (" . $r['no_hp_id'] . ") - " . $r['alamat_id'] . "\n";
    echo "Penerima: " . $r['nama_penerima'] . " (" . $r['no_hp_penerima'] . ")\n";
    echo "Barang: " . $r['jenis_barang'] . " | Ket: " . $r['keterangan'] . "\n";
    echo "Tujuan: " . ($r['nama_tujuan'] ?? $r['tujuan_id']) . " | Plat: " . $r['no_plat_id'] . " (" . ($r['kelas'] ?? '-') . ")\n";
    echo "Tgl: " . $r['tanggal_pengiriman'] . " | Jumlah: Rp " . number_format($r['jumlah']) . "\n";
    echo "Status: " . ($r['status_pembayaran'] ?? $r['status_pembayaran_id']) . " | Metode: " . ($r['metode_pembayaran'] ?? '-') . "\n";
    echo "Asal PO: " . $r['asal_po_id'] . " | Kasir: " . ($r['nama_kasir'] ?? '-') . "\n";
    echo "--------------------------------------------------------\n";
}
