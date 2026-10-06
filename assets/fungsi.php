<?php
require_once __DIR__ . '/../inc/koneksi.php';

// Helper: tampilkan notifikasi melayang (tanpa header()) kemudian kembali
function showFloatingNotice($message, $type = 'info') {
    $bg = '#17a2b8'; // info
    if ($type === 'danger') { $bg = '#dc3545'; }
    if ($type === 'warning') { $bg = '#ffc107'; }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
    echo '<div id="__gt_toast__" style="position:fixed;top:20px;right:20px;z-index:99999;min-width:260px;max-width:360px;padding:12px 16px;border-radius:8px;color:#fff;background:'.$bg.';box-shadow:0 6px 24px rgba(0,0,0,.12);font-family:Arial,Helvetica,sans-serif">'
        . htmlspecialchars($message) .
        '</div>';
    echo '<script>setTimeout(function(){var n=document.getElementById("__gt_toast__");if(n){n.style.transition="opacity .5s";n.style.opacity="0";}},1500);setTimeout(function(){history.back();},2000);</script>';
    echo '</body></html>';
    exit;
}

// Guard: hanya super admin boleh edit/hapus (update/delete) di semua modul
function assertSuperAdminForWrite() {
    if (!isset($_SESSION)) { session_start(); }
    $role = strtolower($_SESSION['user']['role'] ?? '');
    if ($role !== 'super admin') {
        showFloatingNotice('Akses ditolak: Hanya Super Admin yang dapat mengedit dan menghapus data.', 'danger');
    }
}

function assertAdminOrSuperForPelangganUpdate() {
    if (!isset($_SESSION)) { session_start(); }
    $role = strtolower($_SESSION['user']['role'] ?? '');
    if ($role !== 'super admin' && $role !== 'admin') {
        showFloatingNotice('Akses ditolak: Role Anda TIDAK diizinkan untuk mengubah data ini.', 'danger');
    }
}

function assertAdminOrSuperForPemesananUpdate() {
    if (!isset($_SESSION)) { session_start(); }
    $role = strtolower(trim((string)($_SESSION['user']['role'] ?? '')));
    if ($role !== 'super admin' && $role !== 'admin') {
        showFloatingNotice('Akses ditolak: Role Anda TIDAK diizinkan untuk mengubah data pemesanan.', 'danger');
    }
}
function confirmQuery($res) {
    global $conn;
    if (!$res) die('SQL Error: ' . mysqli_error($conn));
    return $res;
}


// Status Pembayaran
function tambahStatusPembayaran($data) {
    global $conn;
    $status_pembayaran = mysqli_real_escape_string($conn, $data['status_pembayaran']);
    $sql = "INSERT INTO data_status_pembayaran (status_pembayaran) VALUES ('$status_pembayaran')";
    return confirmQuery(mysqli_query($conn, $sql));
}

function updateStatusPembayaran($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_status_pembayaran'];
    $status_pembayaran = mysqli_real_escape_string($conn, $data['status_pembayaran']);
    $sql = "UPDATE data_status_pembayaran SET status_pembayaran = '$status_pembayaran' WHERE id_status_pembayaran = $id";
    return confirmQuery(mysqli_query($conn, $sql));
}

function deleteStatusPembayaran($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    return confirmQuery(mysqli_query($conn, "DELETE FROM data_status_pembayaran WHERE id_status_pembayaran = $id"));
}

// Metode Pembayaran
function tambahMetodePembayaran($data) {
    global $conn;
    $metode_pembayaran = mysqli_real_escape_string($conn, $data['metode_pembayaran']);
    $sql = "INSERT INTO data_metode_pembayaran (metode_pembayaran) VALUES ('$metode_pembayaran')";
    return confirmQuery(mysqli_query($conn, $sql));
}

function updateMetodePembayaran($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_metode_pembayaran'];
    $metode_pembayaran = mysqli_real_escape_string($conn, $data['metode_pembayaran']);
    $sql = "UPDATE data_metode_pembayaran SET metode_pembayaran = '$metode_pembayaran' WHERE id_metode_pembayaran = $id";
    return confirmQuery(mysqli_query($conn, $sql));
}

function deleteMetodePembayaran($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    return confirmQuery(mysqli_query($conn, "DELETE FROM data_metode_pembayaran WHERE id_metode_pembayaran = $id"));
}

function getAllMetodePembayaran() {
    global $conn;
    return confirmQuery(mysqli_query($conn, "SELECT * FROM data_metode_pembayaran"));
}

// Jenis Pengeluaran
function tambahJenisPengeluaran($data) {
    assertSuperAdminForWrite();
    global $conn;
    $nama_pengeluaran = mysqli_real_escape_string($conn, trim($data['nama_pengeluaran']));
    $biaya_standar = isset($data['biaya_standar']) ? (float)$data['biaya_standar'] : 0.00;
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, trim($data['keterangan'])) : '';

    // Validasi duplikasi
    $check = mysqli_query($conn, "SELECT id_jenis_pengeluaran FROM data_jenis_pengeluaran WHERE LOWER(TRIM(nama_pengeluaran)) = LOWER(TRIM('$nama_pengeluaran'))");
    if (mysqli_num_rows($check) > 0) {
        showFloatingNotice('Nama jenis/pos pengeluaran sudah terdaftar!', 'warning');
    }

    $sql = "INSERT INTO data_jenis_pengeluaran (nama_pengeluaran, biaya_standar, keterangan) VALUES ('$nama_pengeluaran', $biaya_standar, '$keterangan')";
    return confirmQuery(mysqli_query($conn, $sql));
}

function updateJenisPengeluaran($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_jenis_pengeluaran'];
    $nama_pengeluaran = mysqli_real_escape_string($conn, trim($data['nama_pengeluaran']));
    $biaya_standar = isset($data['biaya_standar']) ? (float)$data['biaya_standar'] : 0.00;
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, trim($data['keterangan'])) : '';

    // Ambil nama lama dari database
    $oldQuery = mysqli_query($conn, "SELECT nama_pengeluaran FROM data_jenis_pengeluaran WHERE id_jenis_pengeluaran = $id");
    $oldRow = mysqli_fetch_assoc($oldQuery);
    $oldNama = $oldRow ? strtolower(trim($oldRow['nama_pengeluaran'])) : '';

    // Hanya cek duplikasi jika user MENGUBAH nama pengeluaran ke nama lain yang sudah terpakai
    if ($oldNama !== strtolower(trim($nama_pengeluaran))) {
        $check = mysqli_query($conn, "SELECT id_jenis_pengeluaran FROM data_jenis_pengeluaran WHERE LOWER(TRIM(nama_pengeluaran)) = LOWER(TRIM('$nama_pengeluaran')) AND id_jenis_pengeluaran != $id");
        if (mysqli_num_rows($check) > 0) {
            showFloatingNotice('Nama jenis/pos pengeluaran sudah terdaftar pada item lain!', 'warning');
        }
    }

    $sql = "UPDATE data_jenis_pengeluaran SET nama_pengeluaran = '$nama_pengeluaran', biaya_standar = $biaya_standar, keterangan = '$keterangan' WHERE id_jenis_pengeluaran = $id";
    return confirmQuery(mysqli_query($conn, $sql));
}

function deleteJenisPengeluaran($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;

    // Cek apakah jenis pengeluaran pernah digunakan di transaksi data_pengeluaran
    $cekUsed = mysqli_query($conn, "SELECT id_pengeluaran FROM data_pengeluaran WHERE jenis_pengeluaran_id = $id LIMIT 1");
    if (mysqli_num_rows($cekUsed) > 0) {
        showFloatingNotice('Tidak dapat dihapus: Jenis pengeluaran ini telah digunakan dalam riwayat pembukuan kas pengeluaran.', 'warning');
    }

    return confirmQuery(mysqli_query($conn, "DELETE FROM data_jenis_pengeluaran WHERE id_jenis_pengeluaran = $id"));
}

function getAllJenisPengeluaran() {
    global $conn;
    return confirmQuery(mysqli_query($conn, "SELECT * FROM data_jenis_pengeluaran ORDER BY nama_pengeluaran ASC"));
}

// Data Jenis Barang & Tarif Paket
function tambahJenisBarang($data) {
    assertSuperAdminForWrite();
    global $conn;
    $nama_barang = mysqli_real_escape_string($conn, trim($data['nama_barang']));
    $biaya_standar = isset($data['biaya_standar']) ? (float)$data['biaya_standar'] : 0.00;
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, trim($data['keterangan'])) : '';
    
    // Validasi duplikasi
    $check = mysqli_query($conn, "SELECT id_barang FROM data_jenis_barang WHERE LOWER(nama_barang) = LOWER('$nama_barang')");
    if (mysqli_num_rows($check) > 0) {
        showFloatingNotice('Nama jenis barang/paket sudah terdaftar!', 'warning');
    }
    
    $sql = "INSERT INTO data_jenis_barang (nama_barang, biaya_standar, keterangan) VALUES ('$nama_barang', $biaya_standar, '$keterangan')";
    return confirmQuery(mysqli_query($conn, $sql));
}

function updateJenisBarang($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_barang'];
    $nama_barang = mysqli_real_escape_string($conn, trim($data['nama_barang']));
    $biaya_standar = isset($data['biaya_standar']) ? (float)$data['biaya_standar'] : 0.00;
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, trim($data['keterangan'])) : '';
    
    // Ambil nama lama dari database
    $oldQuery = mysqli_query($conn, "SELECT nama_barang FROM data_jenis_barang WHERE id_barang = $id");
    $oldRow = mysqli_fetch_assoc($oldQuery);
    $oldNama = $oldRow ? strtolower(trim($oldRow['nama_barang'])) : '';

    // Hanya validasi duplikasi jika nama diubah
    if ($oldNama !== strtolower(trim($nama_barang))) {
        $check = mysqli_query($conn, "SELECT id_barang FROM data_jenis_barang WHERE LOWER(TRIM(nama_barang)) = LOWER(TRIM('$nama_barang')) AND id_barang != $id");
        if (mysqli_num_rows($check) > 0) {
            showFloatingNotice('Nama jenis barang/paket sudah terdaftar pada item lain!', 'warning');
        }
    }

    $sql = "UPDATE data_jenis_barang SET nama_barang = '$nama_barang', biaya_standar = $biaya_standar, keterangan = '$keterangan' WHERE id_barang = $id";
    return confirmQuery(mysqli_query($conn, $sql));
}

function deleteJenisBarang($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;

    // Cek apakah barang pernah digunakan di transaksi data_pengiriman
    $qB = mysqli_query($conn, "SELECT nama_barang FROM data_jenis_barang WHERE id_barang = $id");
    if ($rB = mysqli_fetch_assoc($qB)) {
        $nb = mysqli_real_escape_string($conn, $rB['nama_barang']);
        $cekUsed = mysqli_query($conn, "SELECT id_pengiriman FROM data_pengiriman WHERE LOWER(jenis_barang) = LOWER('$nb') LIMIT 1");
        if (mysqli_num_rows($cekUsed) > 0) {
            showFloatingNotice('Tidak dapat dihapus: Jenis barang ini telah digunakan dalam riwayat data pengiriman paket.', 'warning');
        }
    }

    return confirmQuery(mysqli_query($conn, "DELETE FROM data_jenis_barang WHERE id_barang = $id"));
}

function getAllJenisBarang() {
    global $conn;
    return confirmQuery(mysqli_query($conn, "SELECT * FROM data_jenis_barang ORDER BY nama_barang ASC"));
}

// Data Rekening
function tambahRekening($data, $files) {
    global $conn;
    $nama_rekening = mysqli_real_escape_string($conn, $data['nama_rekening']);
    $nomor_rekening = mysqli_real_escape_string($conn, $data['nomor_rekening']);
    $gambar = '';
    if (isset($files['gambar']) && $files['gambar']['error'] == 0) {
        $ext = pathinfo($files['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = 'rekening_' . time() . '.' . $ext;
        move_uploaded_file($files['gambar']['tmp_name'], __DIR__ . '/../img/' . $gambar);
    }
    $sql = "INSERT INTO data_rekening (nama_rekening, nomor_rekening, gambar) VALUES ('$nama_rekening', '$nomor_rekening', '$gambar')";
    return confirmQuery(mysqli_query($conn, $sql));
}

function updateRekening($data, $files) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_rekening'];
    $nama_rekening = mysqli_real_escape_string($conn, $data['nama_rekening']);
    $nomor_rekening = mysqli_real_escape_string($conn, $data['nomor_rekening']);
    $gambar_sql = '';
    if (isset($files['gambar']) && $files['gambar']['error'] == 0) {
        $ext = pathinfo($files['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = 'rekening_' . time() . '.' . $ext;
        move_uploaded_file($files['gambar']['tmp_name'], __DIR__ . '/../img/' . $gambar);
        $gambar_sql = ", gambar = '$gambar'";
    }
    $sql = "UPDATE data_rekening SET nama_rekening = '$nama_rekening', nomor_rekening = '$nomor_rekening' $gambar_sql WHERE id_rekening = $id";
    return confirmQuery(mysqli_query($conn, $sql));
}

function deleteRekening($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    return confirmQuery(mysqli_query($conn, "DELETE FROM data_rekening WHERE id_rekening = $id"));
}

function getAllRekening() {
    global $conn;
    return confirmQuery(mysqli_query($conn, "SELECT * FROM data_rekening"));
}

// Data Travel
function tambahTravel($data) {
    global $conn;
    $no_plat = mysqli_real_escape_string($conn, $data['no_plat']);
    $kelas = mysqli_real_escape_string($conn, $data['kelas']);
    $harga = (float)$data['harga'];
    $jumlah_kursi = (int)$data['jumlah_kursi'];
    $sql = "INSERT INTO data_travel (no_plat, kelas, harga, jumlah_kursi) VALUES ('$no_plat', '$kelas', $harga, $jumlah_kursi)";
    return confirmQuery(mysqli_query($conn, $sql));
}

function updateTravel($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_travel'];
    $no_plat = mysqli_real_escape_string($conn, $data['no_plat']);
    $kelas = mysqli_real_escape_string($conn, $data['kelas']);
    $harga = (float)$data['harga'];
    $jumlah_kursi = (int)$data['jumlah_kursi'];
    $sql = "UPDATE data_travel SET no_plat = '$no_plat', kelas = '$kelas', harga = $harga, jumlah_kursi = $jumlah_kursi WHERE id_travel = $id";
    return confirmQuery(mysqli_query($conn, $sql));
}

function deleteTravel($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    return confirmQuery(mysqli_query($conn, "DELETE FROM data_travel WHERE id_travel = $id"));
}

function getAllTravel() {
    global $conn;
    return confirmQuery(mysqli_query($conn, "SELECT * FROM data_travel"));
}

// Data Tujuan Perjalanan
function tambahTujuanPerjalanan($data) {
    global $conn;
    $nama_tujuan = mysqli_real_escape_string($conn, $data['nama_tujuan']);
    $sql = "INSERT INTO data_tujuan_perjalanan (nama_tujuan) VALUES ('$nama_tujuan')";
    return confirmQuery(mysqli_query($conn, $sql));
}

function updateTujuanPerjalanan($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_tujuan_perjalanan'];
    $nama_tujuan = mysqli_real_escape_string($conn, $data['nama_tujuan']);
    $sql = "UPDATE data_tujuan_perjalanan SET nama_tujuan = '$nama_tujuan' WHERE id_tujuan_perjalanan = $id";
    return confirmQuery(mysqli_query($conn, $sql));
}

function deleteTujuanPerjalanan($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    return confirmQuery(mysqli_query($conn, "DELETE FROM data_tujuan_perjalanan WHERE id_tujuan_perjalanan = $id"));
}

function getAllTujuanPerjalanan() {
    global $conn;
    return confirmQuery(mysqli_query($conn, "SELECT * FROM data_tujuan_perjalanan"));
}

// Data Users
function tambahUsers($data) {
    assertSuperAdminForWrite();
    global $conn;
    $username = mysqli_real_escape_string($conn, trim($data['username']));
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $asal_po = mysqli_real_escape_string($conn, trim($data['asal_po']));
    $alamat = mysqli_real_escape_string($conn, trim($data['alamat']));
    $no_hp = mysqli_real_escape_string($conn, trim($data['no_hp']));
    $role = mysqli_real_escape_string($conn, trim($data['role']));

    // Cek username duplikat
    $check = mysqli_query($conn, "SELECT id_users FROM data_users WHERE LOWER(username) = LOWER('$username')");
    if (mysqli_num_rows($check) > 0) {
        showFloatingNotice('Username sudah digunakan! Silakan gunakan username lain.', 'warning');
    }

    $sql = "INSERT INTO data_users (username, password, asal_po, alamat, no_hp, role) VALUES ('$username', '$password', '$asal_po', '$alamat', '$no_hp', '$role')";
    return confirmQuery(mysqli_query($conn, $sql));
}

function updateUsers($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_users'];
    $username = mysqli_real_escape_string($conn, trim($data['username']));
    $asal_po = mysqli_real_escape_string($conn, trim($data['asal_po']));
    $alamat = mysqli_real_escape_string($conn, trim($data['alamat']));
    $no_hp = mysqli_real_escape_string($conn, trim($data['no_hp']));
    $role = mysqli_real_escape_string($conn, trim($data['role']));

    // Cek username duplikat (kecuali ID sendiri)
    $check = mysqli_query($conn, "SELECT id_users FROM data_users WHERE LOWER(username) = LOWER('$username') AND id_users != $id");
    if (mysqli_num_rows($check) > 0) {
        showFloatingNotice('Username sudah digunakan oleh pengguna lain!', 'warning');
    }

    $sql_password = '';
    if (!empty($data['password'])) {
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $sql_password = ", password = '$password'";
    }
    $sql = "UPDATE data_users SET username = '$username', asal_po = '$asal_po', alamat = '$alamat', no_hp = '$no_hp', role = '$role' $sql_password WHERE id_users = $id";
    return confirmQuery(mysqli_query($conn, $sql));
}

function deleteUsers($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    return confirmQuery(mysqli_query($conn, "DELETE FROM data_users WHERE id_users = $id"));
}

function getAllUsers() {
    global $conn;
    return confirmQuery(mysqli_query($conn, "SELECT * FROM data_users"));
}


// CRUD Data Pelanggan
function tambahPelanggan($data) {
    global $conn;
    $nama = mysqli_real_escape_string($conn, $data['nama']);
    $alamat = mysqli_real_escape_string($conn, $data['alamat']);
    $no_ktp = mysqli_real_escape_string($conn, $data['no_ktp']);
    $no_hp = mysqli_real_escape_string($conn, $data['no_hp']);
    $nip_sgt = mysqli_real_escape_string($conn, $data['nip_sgt']);
    $username = isset($data['username']) ? (int)$data['username'] : 0;
    $asal_po = mysqli_real_escape_string($conn, $data['asal_po']);
    if (empty($username)) {
        die('SQL Error: Username (id_users) tidak boleh kosong!');
    }
    $sql = "INSERT INTO data_pelanggan (nama, alamat, no_ktp, no_hp, nip_sgt, username, asal_po) VALUES ('$nama', '$alamat', '$no_ktp', '$no_hp', '$nip_sgt', $username, '$asal_po')";
    $result = confirmQuery(mysqli_query($conn, $sql));
    
    // Log activity jika berhasil
    if ($result) {
        $new_id = mysqli_insert_id($conn);
        $new_data = json_encode([
            'nama' => $nama,
            'alamat' => $alamat,
            'no_ktp' => $no_ktp,
            'no_hp' => $no_hp,
            'nip_sgt' => $nip_sgt,
            'username' => $username,
            'asal_po' => $asal_po
        ]);
        
        // Include logging helper jika belum
        if (!function_exists('logCreate')) {
            require_once __DIR__ . '/logging_helper.php';
        }
        logCreate('data_pelanggan', $new_id, "Menambah pelanggan baru: $nama", $new_data);
    }
    
    return $result;
}

function updatePelanggan($data) {
    // Khusus pelanggan: admin dan super admin boleh edit
    assertAdminOrSuperForPelangganUpdate();
    global $conn;
    $id = (int)$data['id_pelanggan'];
    $nama = mysqli_real_escape_string($conn, $data['nama']);
    $alamat = mysqli_real_escape_string($conn, $data['alamat']);
    $no_ktp = mysqli_real_escape_string($conn, $data['no_ktp']);
    $no_hp = mysqli_real_escape_string($conn, $data['no_hp']);
    $nip_sgt = mysqli_real_escape_string($conn, $data['nip_sgt']);
    $username = isset($data['username']) ? (int)$data['username'] : 0;
    $asal_po = mysqli_real_escape_string($conn, $data['asal_po']);
    if (empty($username)) {
        die('SQL Error: Username (id_users) tidak boleh kosong!');
    }
    
    // Get old data for logging
    $old_data = null;
    if (!function_exists('getOldData')) {
        require_once __DIR__ . '/logging_helper.php';
    }
    $old_data = getOldData('data_pelanggan', $id);
    
    $sql = "UPDATE data_pelanggan SET nama='$nama', alamat='$alamat', no_ktp='$no_ktp', no_hp='$no_hp', nip_sgt='$nip_sgt', username=$username, asal_po='$asal_po' WHERE id_pelanggan=$id";
    $result = confirmQuery(mysqli_query($conn, $sql));
    
    // Log activity jika berhasil
    if ($result) {
        $new_data = json_encode([
            'nama' => $nama,
            'alamat' => $alamat,
            'no_ktp' => $no_ktp,
            'no_hp' => $no_hp,
            'nip_sgt' => $nip_sgt,
            'username' => $username,
            'asal_po' => $asal_po
        ]);
        
        logUpdate('data_pelanggan', $id, "Update data pelanggan: $nama", $old_data, $new_data);
    }
    
    return $result;
}

function deletePelanggan($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    
    // Get old data for logging
    $old_data = null;
    if (!function_exists('getOldData')) {
        require_once __DIR__ . '/logging_helper.php';
    }
    $old_data = getOldData('data_pelanggan', $id);
    
    $result = confirmQuery(mysqli_query($conn, "DELETE FROM data_pelanggan WHERE id_pelanggan = $id"));
    
    // Log activity jika berhasil
    if ($result && $old_data) {
        $old_data_array = json_decode($old_data, true);
        $nama = $old_data_array['nama'] ?? 'Unknown';
        logDelete('data_pelanggan', $id, "Hapus data pelanggan: $nama", $old_data);
    }
    
    return $result;
}

function getPelangganById($id) {
    global $conn;
    $id = (int)$id;
    $result = mysqli_query($conn, "SELECT * FROM data_pelanggan WHERE id_pelanggan = $id LIMIT 1");
    return mysqli_fetch_assoc($result);
}


// CRUD Data Pemesanan
function tambahPemesanan($data) {
    global $conn;
    $nip_sgt_id = isset($data['nip_sgt_id']) ? mysqli_real_escape_string($conn, $data['nip_sgt_id']) : '';
    $nama_id = isset($data['nama_id']) ? mysqli_real_escape_string($conn, $data['nama_id']) : '';
    $alamat_id = isset($data['alamat_id']) ? mysqli_real_escape_string($conn, $data['alamat_id']) : '';
    $no_ktp_id = isset($data['no_ktp_id']) ? mysqli_real_escape_string($conn, $data['no_ktp_id']) : '';
    $no_hp_id = isset($data['no_hp_id']) ? mysqli_real_escape_string($conn, $data['no_hp_id']) : '';
    $tanggal_pemesanan = isset($data['tanggal_pemesanan']) ? mysqli_real_escape_string($conn, $data['tanggal_pemesanan']) : '';
    $tanggal_berangkat = isset($data['tanggal_berangkat']) ? mysqli_real_escape_string($conn, $data['tanggal_berangkat']) : '';
    $no_plat_id = isset($data['no_plat_id']) ? mysqli_real_escape_string($conn, $data['no_plat_id']) : '';
    $kelas_id = isset($data['kelas_id']) ? mysqli_real_escape_string($conn, $data['kelas_id']) : '';
    $harga_id = isset($data['harga_id']) ? mysqli_real_escape_string($conn, $data['harga_id']) : '';
    $kursi = isset($data['kursi']) ? (int)$data['kursi'] : 0;
    $tujuan_id = isset($data['tujuan_id']) ? mysqli_real_escape_string($conn, $data['tujuan_id']) : '';
    $status_pembayaran_id = isset($data['status_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['status_pembayaran_id']) : '';
    $metode_pembayaran_id = isset($data['metode_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['metode_pembayaran_id']) : '';
    $jenis_rekening_id = isset($data['jenis_rekening_id']) ? mysqli_real_escape_string($conn, $data['jenis_rekening_id']) : '';
    $username = isset($data['username']) ? mysqli_real_escape_string($conn, $data['username']) : '';
    $asal_po = isset($data['asal_po']) ? mysqli_real_escape_string($conn, $data['asal_po']) : '';
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, $data['keterangan']) : '';

    // Handle multiple payment methods
    $payment_methods = [];
    $payment_details = '';
    
    if (isset($data['payment_methods']) && is_array($data['payment_methods'])) {
        $total_amount = 0;
        foreach ($data['payment_methods'] as $payment) {
            if (!empty($payment['method_id']) && !empty($payment['amount']) && !empty($payment['rekening_id'])) {
                $payment_methods[] = [
                    'method_id' => (int)$payment['method_id'],
                    'amount' => (float)$payment['amount'],
                    'rekening_id' => (int)$payment['rekening_id'],
                    'payment_date' => !empty($payment['payment_date']) ? $payment['payment_date'] : (isset($tanggal_pemesanan) ? substr($tanggal_pemesanan, 0, 10) : (isset($tanggal_pengiriman) ? substr($tanggal_pengiriman, 0, 10) : date('Y-m-d')))
                ];
                $total_amount += (float)$payment['amount'];
            }
        }
        
        // Validasi jumlah pembayaran vs harga tiket
        $diff = $total_amount - (float)$harga_id;
        if ($diff > 0.01) {
            die('SQL Error: Total pembayaran melebihi harga tiket!');
        }
        if ($diff < -0.01) {
            // Izinkan hanya jika status pembayaran DP/Piutang
            $statusLabel = '';
            $resStat = mysqli_query($conn, "SELECT status_pembayaran FROM data_status_pembayaran WHERE id_status_pembayaran = '$status_pembayaran_id' LIMIT 1");
            if ($rowStat = mysqli_fetch_assoc($resStat)) {
                $statusLabel = strtolower($rowStat['status_pembayaran']);
            }
            if (strpos($statusLabel, 'dp') === false && strpos($statusLabel, 'piutang') === false) {
                // Auto set status ke DP / Piutang
                $resDP = mysqli_query($conn, "SELECT id_status_pembayaran FROM data_status_pembayaran WHERE LOWER(status_pembayaran) LIKE '%dp%' OR LOWER(status_pembayaran) LIKE '%piutang%' LIMIT 1");
                if ($rowDP = mysqli_fetch_assoc($resDP)) {
                    $status_pembayaran_id = $rowDP['id_status_pembayaran'];
                }
            }
        }
        
        $payment_details = 'Multiple payment: ' . count($payment_methods) . ' metode pembayaran';
        // Sinkronkan kolom metode_pembayaran_id dengan metode pertama
        if (count($payment_methods) > 0) {
            $metode_pembayaran_id = $payment_methods[0]['method_id'];
        }
    } else {
        // Fallback to single payment method (backward compatibility)
        $payment_methods = [[
            'method_id' => (int)$metode_pembayaran_id,
            'amount' => (float)$harga_id,
            'rekening_id' => (int)$jenis_rekening_id,
            'payment_date' => (isset($tanggal_pemesanan) ? substr($tanggal_pemesanan, 0, 10) : (isset($tanggal_pengiriman) ? substr($tanggal_pengiriman, 0, 10) : date('Y-m-d')))
        ]];
        $payment_details = 'Single payment method';
    }
    
    $payment_methods_json = mysqli_real_escape_string($conn, json_encode($payment_methods));
    $payment_details = mysqli_real_escape_string($conn, $payment_details);

    $required = [
        'nip_sgt_id' => $nip_sgt_id,
        'nama_id' => $nama_id,
        'alamat_id' => $alamat_id,
        'no_ktp_id' => $no_ktp_id,
        'no_hp_id' => $no_hp_id,
        'tanggal_pemesanan' => $tanggal_pemesanan,
        'tanggal_berangkat' => $tanggal_berangkat,
        'no_plat_id' => $no_plat_id,
        'kelas_id' => $kelas_id,
        'harga_id' => $harga_id,
        'kursi' => $kursi,
        'tujuan_id' => $tujuan_id,
        'status_pembayaran_id' => $status_pembayaran_id,
        'username' => $username,
        'asal_po' => $asal_po
    ];
    foreach ($required as $key => $val) {
        if ($val === '' || $val === null) {
            die('SQL Error: Field ' . $key . ' tidak boleh kosong!');
        }
    }

    $sql = "INSERT INTO data_pemesanan (
        nip_sgt_id, nama_id, alamat_id, no_ktp_id, no_hp_id, tanggal_pemesanan, tanggal_berangkat, no_plat_id, kelas_id, harga_id, kursi, tujuan_id, status_pembayaran_id, metode_pembayaran_id, jenis_rekening_id, payment_methods, payment_details, username, asal_po, keterangan
    ) VALUES (
        '$nip_sgt_id', '$nama_id', '$alamat_id', '$no_ktp_id', '$no_hp_id', '$tanggal_pemesanan', '$tanggal_berangkat', '$no_plat_id', '$kelas_id', '$harga_id', $kursi, '$tujuan_id', '$status_pembayaran_id', '$metode_pembayaran_id', '$jenis_rekening_id', '$payment_methods_json', '$payment_details', '$username', '$asal_po', '$keterangan'
    )";
    $result = confirmQuery(mysqli_query($conn, $sql));
    if ($result) {
        $new_id = mysqli_insert_id($conn);
        if (!function_exists('logCreate')) { require_once __DIR__ . '/logging_helper.php'; }
        $new_data = json_encode([
            'nip_sgt_id'=>$nip_sgt_id,'nama_id'=>$nama_id,'alamat_id'=>$alamat_id,'no_ktp_id'=>$no_ktp_id,'no_hp_id'=>$no_hp_id,
            'tanggal_pemesanan'=>$tanggal_pemesanan,'tanggal_berangkat'=>$tanggal_berangkat,'no_plat_id'=>$no_plat_id,'kelas_id'=>$kelas_id,
            'harga_id'=>$harga_id,'kursi'=>$kursi,'tujuan_id'=>$tujuan_id,'status_pembayaran_id'=>$status_pembayaran_id,
            'metode_pembayaran_id'=>$metode_pembayaran_id,'jenis_rekening_id'=>$jenis_rekening_id,'username'=>$username,'asal_po'=>$asal_po
        ]);
        logCreate('data_pemesanan', $new_id, 'Tambah pemesanan', $new_data);
    }
    return $result;
}

function updatePemesanan($data) {
    assertAdminOrSuperForPemesananUpdate();
    global $conn;
    $id = isset($data['id_pemesanan']) ? (int)$data['id_pemesanan'] : 0;
    $nip_sgt_id = isset($data['nip_sgt_id']) ? mysqli_real_escape_string($conn, trim($data['nip_sgt_id'])) : '';
    $nama_id = isset($data['nama_id']) ? mysqli_real_escape_string($conn, trim($data['nama_id'])) : '';
    $alamat_id = isset($data['alamat_id']) ? mysqli_real_escape_string($conn, trim($data['alamat_id'])) : '';
    $no_ktp_id = isset($data['no_ktp_id']) ? mysqli_real_escape_string($conn, trim($data['no_ktp_id'])) : '';
    $no_hp_id = isset($data['no_hp_id']) ? mysqli_real_escape_string($conn, trim($data['no_hp_id'])) : '';

    // Fallback perlindungan identitas penumpang: jika field kosong, pertahankan data yang sudah ada di database
    if ($id > 0) {
        $old_data_rec = getPemesananById($id);
        if ($old_data_rec) {
            if (empty($nip_sgt_id) && !empty($old_data_rec['nip_sgt_id'])) { $nip_sgt_id = mysqli_real_escape_string($conn, $old_data_rec['nip_sgt_id']); }
            if (empty($nama_id) && !empty($old_data_rec['nama_id'])) { $nama_id = mysqli_real_escape_string($conn, $old_data_rec['nama_id']); }
            if (empty($alamat_id) && !empty($old_data_rec['alamat_id'])) { $alamat_id = mysqli_real_escape_string($conn, $old_data_rec['alamat_id']); }
            if (empty($no_ktp_id) && !empty($old_data_rec['no_ktp_id'])) { $no_ktp_id = mysqli_real_escape_string($conn, $old_data_rec['no_ktp_id']); }
            if (empty($no_hp_id) && !empty($old_data_rec['no_hp_id'])) { $no_hp_id = mysqli_real_escape_string($conn, $old_data_rec['no_hp_id']); }
        }
    }

    $tanggal_pemesanan = isset($data['tanggal_pemesanan']) ? mysqli_real_escape_string($conn, $data['tanggal_pemesanan']) : '';
    $tanggal_berangkat = isset($data['tanggal_berangkat']) ? mysqli_real_escape_string($conn, $data['tanggal_berangkat']) : '';
    $no_plat_id = isset($data['no_plat_id']) ? mysqli_real_escape_string($conn, $data['no_plat_id']) : '';
    $kelas_id = isset($data['kelas_id']) ? mysqli_real_escape_string($conn, $data['kelas_id']) : '';
    $harga_id = isset($data['harga_id']) ? mysqli_real_escape_string($conn, $data['harga_id']) : '';
    $kursi = isset($data['kursi']) ? (int)$data['kursi'] : 0;
    $tujuan_id = isset($data['tujuan_id']) ? mysqli_real_escape_string($conn, $data['tujuan_id']) : '';
    $status_pembayaran_id = isset($data['status_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['status_pembayaran_id']) : '';
    $metode_pembayaran_id = isset($data['metode_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['metode_pembayaran_id']) : '';
    $jenis_rekening_id = isset($data['jenis_rekening_id']) ? mysqli_real_escape_string($conn, $data['jenis_rekening_id']) : '';
    $username = isset($data['username']) ? mysqli_real_escape_string($conn, $data['username']) : '';
    $asal_po = isset($data['asal_po']) ? mysqli_real_escape_string($conn, $data['asal_po']) : '';
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, $data['keterangan']) : '';

    // Handle multiple payment methods
    $payment_methods = [];
    $payment_details = '';
    
    if (isset($data['payment_methods']) && is_array($data['payment_methods'])) {
        $total_amount = 0;
        foreach ($data['payment_methods'] as $payment) {
            if (!empty($payment['method_id']) && !empty($payment['amount']) && !empty($payment['rekening_id'])) {
                $payment_methods[] = [
                    'method_id' => (int)$payment['method_id'],
                    'amount' => (float)$payment['amount'],
                    'rekening_id' => (int)$payment['rekening_id'],
                    'payment_date' => !empty($payment['payment_date']) ? $payment['payment_date'] : (isset($tanggal_pemesanan) ? substr($tanggal_pemesanan, 0, 10) : (isset($tanggal_pengiriman) ? substr($tanggal_pengiriman, 0, 10) : date('Y-m-d')))
                ];
                $total_amount += (float)$payment['amount'];
            }
        }
        
        // Validasi jumlah pembayaran vs harga tiket
        $diff = $total_amount - (float)$harga_id;
        if ($diff > 0.01) {
            die('SQL Error: Total pembayaran melebihi harga tiket!');
        }
        if ($diff < -0.01) {
            // Izinkan hanya jika status pembayaran DP/Piutang
            $statusLabel = '';
            $resStat = mysqli_query($conn, "SELECT status_pembayaran FROM data_status_pembayaran WHERE id_status_pembayaran = '$status_pembayaran_id' LIMIT 1");
            if ($rowStat = mysqli_fetch_assoc($resStat)) {
                $statusLabel = strtolower($rowStat['status_pembayaran']);
            }
            if (strpos($statusLabel, 'dp') === false && strpos($statusLabel, 'piutang') === false) {
                // Auto set status ke DP / Piutang
                $resDP = mysqli_query($conn, "SELECT id_status_pembayaran FROM data_status_pembayaran WHERE LOWER(status_pembayaran) LIKE '%dp%' OR LOWER(status_pembayaran) LIKE '%piutang%' LIMIT 1");
                if ($rowDP = mysqli_fetch_assoc($resDP)) {
                    $status_pembayaran_id = $rowDP['id_status_pembayaran'];
                }
            }
        }
        
        $payment_details = 'Multiple payment: ' . count($payment_methods) . ' metode pembayaran';
        // Sinkronkan kolom metode_pembayaran_id dengan metode pertama
        if (count($payment_methods) > 0) {
            $metode_pembayaran_id = $payment_methods[0]['method_id'];
        }
    } else {
        // Fallback to single payment method (backward compatibility)
        $payment_methods = [[
            'method_id' => (int)$metode_pembayaran_id,
            'amount' => (float)$harga_id,
            'rekening_id' => (int)$jenis_rekening_id,
            'payment_date' => (isset($tanggal_pemesanan) ? substr($tanggal_pemesanan, 0, 10) : (isset($tanggal_pengiriman) ? substr($tanggal_pengiriman, 0, 10) : date('Y-m-d')))
        ]];
        $payment_details = 'Single payment method';
    }
    
    $payment_methods_json = mysqli_real_escape_string($conn, json_encode($payment_methods));
    $payment_details = mysqli_real_escape_string($conn, $payment_details);

    $required = [
        'id_pemesanan' => $id,
        'nip_sgt_id' => $nip_sgt_id,
        'nama_id' => $nama_id,
        'alamat_id' => $alamat_id,
        'no_ktp_id' => $no_ktp_id,
        'no_hp_id' => $no_hp_id,
        'tanggal_pemesanan' => $tanggal_pemesanan,
        'tanggal_berangkat' => $tanggal_berangkat,
        'no_plat_id' => $no_plat_id,
        'kelas_id' => $kelas_id,
        'harga_id' => $harga_id,
        'kursi' => $kursi,
        'tujuan_id' => $tujuan_id,
        'status_pembayaran_id' => $status_pembayaran_id,
        'username' => $username,
        'asal_po' => $asal_po
    ];
    foreach ($required as $key => $val) {
        if ($val === '' || $val === null) {
            die('SQL Error: Field ' . $key . ' tidak boleh kosong!');
        }
    }

    $sql = "UPDATE data_pemesanan SET 
        nip_sgt_id='$nip_sgt_id', nama_id='$nama_id', alamat_id='$alamat_id', no_ktp_id='$no_ktp_id', no_hp_id='$no_hp_id', tanggal_pemesanan='$tanggal_pemesanan', tanggal_berangkat='$tanggal_berangkat', no_plat_id='$no_plat_id', kelas_id='$kelas_id', harga_id='$harga_id', kursi=$kursi, tujuan_id='$tujuan_id', status_pembayaran_id='$status_pembayaran_id', metode_pembayaran_id='$metode_pembayaran_id', jenis_rekening_id='$jenis_rekening_id', payment_methods='$payment_methods_json', payment_details='$payment_details', username='$username', asal_po='$asal_po', keterangan='$keterangan'
        WHERE id_pemesanan=$id";
    // old data sebelum update
    if (!function_exists('getOldData')) { require_once __DIR__ . '/logging_helper.php'; }
    $old_data = getOldData('data_pemesanan', $id);
    
    $result = confirmQuery(mysqli_query($conn, $sql));
    if ($result) {
        $new_data = json_encode([
            'nip_sgt_id'=>$nip_sgt_id,'nama_id'=>$nama_id,'alamat_id'=>$alamat_id,'no_ktp_id'=>$no_ktp_id,'no_hp_id'=>$no_hp_id,
            'tanggal_pemesanan'=>$tanggal_pemesanan,'tanggal_berangkat'=>$tanggal_berangkat,'no_plat_id'=>$no_plat_id,'kelas_id'=>$kelas_id,
            'harga_id'=>$harga_id,'kursi'=>$kursi,'tujuan_id'=>$tujuan_id,'status_pembayaran_id'=>$status_pembayaran_id,
            'metode_pembayaran_id'=>$metode_pembayaran_id,'jenis_rekening_id'=>$jenis_rekening_id,'username'=>$username,'asal_po'=>$asal_po
        ]);
        logUpdate('data_pemesanan', $id, 'Update pemesanan', $old_data, $new_data);
    }
    return $result;
}

function deletePemesanan($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    if (!function_exists('getOldData')) { require_once __DIR__ . '/logging_helper.php'; }
    $old_data = getOldData('data_pemesanan', $id);
    $result = confirmQuery(mysqli_query($conn, "DELETE FROM data_pemesanan WHERE id_pemesanan = $id"));
    if ($result && $old_data) {
        logDelete('data_pemesanan', $id, 'Hapus pemesanan', $old_data);
    }
    return $result;
}

function getPemesananById($id) {
    global $conn;
    $id = (int)$id;
    $result = mysqli_query($conn, "SELECT * FROM data_pemesanan WHERE id_pemesanan = $id LIMIT 1");
    return mysqli_fetch_assoc($result);
}


// CRUD Data Pengiriman
function tambahPengiriman($data) {
    global $conn;
    $nip_sgt_id = isset($data['nip_sgt_id']) ? mysqli_real_escape_string($conn, $data['nip_sgt_id']) : '';
    $nama_id = isset($data['nama_id']) ? mysqli_real_escape_string($conn, $data['nama_id']) : '';
    $alamat_id = isset($data['alamat_id']) ? mysqli_real_escape_string($conn, $data['alamat_id']) : '';
    $no_ktp_id = isset($data['no_ktp_id']) ? mysqli_real_escape_string($conn, $data['no_ktp_id']) : '';
    $no_hp_id = isset($data['no_hp_id']) ? mysqli_real_escape_string($conn, $data['no_hp_id']) : '';
    $tanggal_pengiriman = isset($data['tanggal_pengiriman']) ? mysqli_real_escape_string($conn, $data['tanggal_pengiriman']) : '';
    $jenis_barang = isset($data['jenis_barang']) ? mysqli_real_escape_string($conn, $data['jenis_barang']) : '';
    $nama_penerima = isset($data['nama_penerima']) ? mysqli_real_escape_string($conn, $data['nama_penerima']) : '';
    $no_hp_penerima = isset($data['no_hp_penerima']) ? mysqli_real_escape_string($conn, $data['no_hp_penerima']) : '';
    $no_plat_id = isset($data['no_plat_id']) ? mysqli_real_escape_string($conn, $data['no_plat_id']) : '';
    $kelas_id = isset($data['kelas_id']) ? mysqli_real_escape_string($conn, $data['kelas_id']) : '';
    $tujuan_id = isset($data['tujuan_id']) ? mysqli_real_escape_string($conn, $data['tujuan_id']) : '';
    $status_pembayaran_id = isset($data['status_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['status_pembayaran_id']) : '';
    $metode_pembayaran_id = isset($data['metode_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['metode_pembayaran_id']) : '';
    $jenis_rekening_id = isset($data['jenis_rekening_id']) ? mysqli_real_escape_string($conn, $data['jenis_rekening_id']) : '';
    $jumlah = isset($data['jumlah']) ? (int)$data['jumlah'] : 0;
    $user_id = isset($data['user_id']) ? mysqli_real_escape_string($conn, $data['user_id']) : '';
    $asal_po_id = isset($data['asal_po_id']) ? mysqli_real_escape_string($conn, $data['asal_po_id']) : '';
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, $data['keterangan']) : '';

    // Handle multiple payment methods
    $payment_methods = [];
    $payment_details = '';
    
    if (isset($data['payment_methods']) && is_array($data['payment_methods'])) {
        $total_amount = 0;
        foreach ($data['payment_methods'] as $payment) {
            if (!empty($payment['method_id']) && !empty($payment['amount']) && !empty($payment['rekening_id'])) {
                $payment_methods[] = [
                    'method_id' => (int)$payment['method_id'],
                    'amount' => (float)$payment['amount'],
                    'rekening_id' => (int)$payment['rekening_id'],
                    'payment_date' => !empty($payment['payment_date']) ? $payment['payment_date'] : (isset($tanggal_pemesanan) ? substr($tanggal_pemesanan, 0, 10) : (isset($tanggal_pengiriman) ? substr($tanggal_pengiriman, 0, 10) : date('Y-m-d')))
                ];
                $total_amount += (float)$payment['amount'];
            }
        }
        
        // Validasi jumlah pembayaran vs total pengiriman
        $diff = $total_amount - (float)$jumlah;
        if ($diff > 0.01) {
            die('SQL Error: Total pembayaran melebihi total biaya pengiriman!');
        }
        if ($diff < -0.01) {
            // Izinkan hanya jika status pembayaran DP/Piutang
            $statusLabel = '';
            $resStat = mysqli_query($conn, "SELECT status_pembayaran FROM data_status_pembayaran WHERE id_status_pembayaran = '$status_pembayaran_id' LIMIT 1");
            if ($rowStat = mysqli_fetch_assoc($resStat)) {
                $statusLabel = strtolower($rowStat['status_pembayaran']);
            }
            if (strpos($statusLabel, 'dp') === false && strpos($statusLabel, 'piutang') === false) {
                // Auto set status ke DP / Piutang
                $resDP = mysqli_query($conn, "SELECT id_status_pembayaran FROM data_status_pembayaran WHERE LOWER(status_pembayaran) LIKE '%dp%' OR LOWER(status_pembayaran) LIKE '%piutang%' LIMIT 1");
                if ($rowDP = mysqli_fetch_assoc($resDP)) {
                    $status_pembayaran_id = $rowDP['id_status_pembayaran'];
                }
            }
        }
        
        $payment_details = 'Multiple payment: ' . count($payment_methods) . ' metode pembayaran';
        // Sinkronkan kolom metode_pembayaran_id dengan metode pertama
        if (count($payment_methods) > 0) {
            $metode_pembayaran_id = $payment_methods[0]['method_id'];
        }
    } else {
        // Fallback to single payment method (backward compatibility)
        $payment_methods = [[
            'method_id' => (int)$metode_pembayaran_id,
            'amount' => (float)$jumlah,
            'rekening_id' => (int)$jenis_rekening_id,
            'payment_date' => (isset($tanggal_pemesanan) ? substr($tanggal_pemesanan, 0, 10) : (isset($tanggal_pengiriman) ? substr($tanggal_pengiriman, 0, 10) : date('Y-m-d')))
        ]];
        $payment_details = 'Single payment method';
    }
    
    $payment_methods_json = mysqli_real_escape_string($conn, json_encode($payment_methods));
    $payment_details = mysqli_real_escape_string($conn, $payment_details);

    $required = [
        'nip_sgt_id' => $nip_sgt_id,
        'nama_id' => $nama_id,
        'alamat_id' => $alamat_id,
        'no_ktp_id' => $no_ktp_id,
        'no_hp_id' => $no_hp_id,
        'tanggal_pengiriman' => $tanggal_pengiriman,
        'jenis_barang' => $jenis_barang,
        'nama_penerima' => $nama_penerima,
        'no_hp_penerima' => $no_hp_penerima,
        'no_plat_id' => $no_plat_id,
        'kelas_id' => $kelas_id,
        'tujuan_id' => $tujuan_id,
        'status_pembayaran_id' => $status_pembayaran_id,
        'jumlah' => $jumlah,
        'user_id' => $user_id,
        'asal_po_id' => $asal_po_id
    ];
    foreach ($required as $key => $val) {
        if ($val === '' || $val === null) {
            die('SQL Error: Field ' . $key . ' tidak boleh kosong!');
        }
    }

    $sql = "INSERT INTO data_pengiriman (
        nip_sgt_id, nama_id, alamat_id, no_ktp_id, no_hp_id, tanggal_pengiriman, jenis_barang, nama_penerima, no_hp_penerima, no_plat_id, kelas_id, tujuan_id, status_pembayaran_id, metode_pembayaran_id, jenis_rekening_id, payment_methods, payment_details, jumlah, user_id, asal_po_id, keterangan
    ) VALUES (
        '$nip_sgt_id', '$nama_id', '$alamat_id', '$no_ktp_id', '$no_hp_id', '$tanggal_pengiriman', '$jenis_barang', '$nama_penerima', '$no_hp_penerima', '$no_plat_id', '$kelas_id', '$tujuan_id', '$status_pembayaran_id', '$metode_pembayaran_id', '$jenis_rekening_id', '$payment_methods_json', '$payment_details', $jumlah, '$user_id', '$asal_po_id', '$keterangan'
    )";
    $result = confirmQuery(mysqli_query($conn, $sql));
    if ($result) {
        $new_id = mysqli_insert_id($conn);
        if (!function_exists('logCreate')) { require_once __DIR__ . '/logging_helper.php'; }
        $new_data = json_encode([
            'nip_sgt_id'=>$nip_sgt_id,'nama_id'=>$nama_id,'alamat_id'=>$alamat_id,'no_ktp_id'=>$no_ktp_id,'no_hp_id'=>$no_hp_id,
            'tanggal_pengiriman'=>$tanggal_pengiriman,'jenis_barang'=>$jenis_barang,'nama_penerima'=>$nama_penerima,
            'no_hp_penerima'=>$no_hp_penerima,'no_plat_id'=>$no_plat_id,'kelas_id'=>$kelas_id,'tujuan_id'=>$tujuan_id,
            'status_pembayaran_id'=>$status_pembayaran_id,'metode_pembayaran_id'=>$metode_pembayaran_id,'jenis_rekening_id'=>$jenis_rekening_id,
            'jumlah'=>$jumlah,'user_id'=>$user_id,'asal_po_id'=>$asal_po_id
        ]);
        logCreate('data_pengiriman', $new_id, 'Tambah pengiriman', $new_data);
    }
    return $result;
}

function updatePengiriman($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = isset($data['id_pengiriman']) ? (int)$data['id_pengiriman'] : 0;
    $nip_sgt_id = isset($data['nip_sgt_id']) ? mysqli_real_escape_string($conn, $data['nip_sgt_id']) : '';
    $nama_id = isset($data['nama_id']) ? mysqli_real_escape_string($conn, $data['nama_id']) : '';
    $alamat_id = isset($data['alamat_id']) ? mysqli_real_escape_string($conn, $data['alamat_id']) : '';
    $no_ktp_id = isset($data['no_ktp_id']) ? mysqli_real_escape_string($conn, $data['no_ktp_id']) : '';
    $no_hp_id = isset($data['no_hp_id']) ? mysqli_real_escape_string($conn, $data['no_hp_id']) : '';
    $tanggal_pengiriman = isset($data['tanggal_pengiriman']) ? mysqli_real_escape_string($conn, $data['tanggal_pengiriman']) : '';
    $jenis_barang = isset($data['jenis_barang']) ? mysqli_real_escape_string($conn, $data['jenis_barang']) : '';
    $nama_penerima = isset($data['nama_penerima']) ? mysqli_real_escape_string($conn, $data['nama_penerima']) : '';
    $no_hp_penerima = isset($data['no_hp_penerima']) ? mysqli_real_escape_string($conn, $data['no_hp_penerima']) : '';
    $no_plat_id = isset($data['no_plat_id']) ? mysqli_real_escape_string($conn, $data['no_plat_id']) : '';
    $kelas_id = isset($data['kelas_id']) ? mysqli_real_escape_string($conn, $data['kelas_id']) : '';
    $tujuan_id = isset($data['tujuan_id']) ? mysqli_real_escape_string($conn, $data['tujuan_id']) : '';
    $status_pembayaran_id = isset($data['status_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['status_pembayaran_id']) : '';
    $metode_pembayaran_id = isset($data['metode_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['metode_pembayaran_id']) : '';
    $jenis_rekening_id = isset($data['jenis_rekening_id']) ? mysqli_real_escape_string($conn, $data['jenis_rekening_id']) : '';
    $jumlah = isset($data['jumlah']) ? (int)$data['jumlah'] : 0;
    $user_id = isset($data['user_id']) ? mysqli_real_escape_string($conn, $data['user_id']) : '';
    $asal_po_id = isset($data['asal_po_id']) ? mysqli_real_escape_string($conn, $data['asal_po_id']) : '';
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, $data['keterangan']) : '';

    // Handle multiple payment methods
    $payment_methods = [];
    $payment_details = '';
    
    if (isset($data['payment_methods']) && is_array($data['payment_methods'])) {
        $total_amount = 0;
        foreach ($data['payment_methods'] as $payment) {
            if (!empty($payment['method_id']) && !empty($payment['amount']) && !empty($payment['rekening_id'])) {
                $payment_methods[] = [
                    'method_id' => (int)$payment['method_id'],
                    'amount' => (float)$payment['amount'],
                    'rekening_id' => (int)$payment['rekening_id'],
                    'payment_date' => !empty($payment['payment_date']) ? $payment['payment_date'] : (isset($tanggal_pemesanan) ? substr($tanggal_pemesanan, 0, 10) : (isset($tanggal_pengiriman) ? substr($tanggal_pengiriman, 0, 10) : date('Y-m-d')))
                ];
                $total_amount += (float)$payment['amount'];
            }
        }
        
        // Validasi jumlah pembayaran vs total pengiriman
        $diff = $total_amount - (float)$jumlah;
        if ($diff > 0.01) {
            die('SQL Error: Total pembayaran melebihi total biaya pengiriman!');
        }
        if ($diff < -0.01) {
            // Izinkan hanya jika status pembayaran DP/Piutang
            $statusLabel = '';
            $resStat = mysqli_query($conn, "SELECT status_pembayaran FROM data_status_pembayaran WHERE id_status_pembayaran = '$status_pembayaran_id' LIMIT 1");
            if ($rowStat = mysqli_fetch_assoc($resStat)) {
                $statusLabel = strtolower($rowStat['status_pembayaran']);
            }
            if (strpos($statusLabel, 'dp') === false && strpos($statusLabel, 'piutang') === false) {
                // Auto set status ke DP / Piutang
                $resDP = mysqli_query($conn, "SELECT id_status_pembayaran FROM data_status_pembayaran WHERE LOWER(status_pembayaran) LIKE '%dp%' OR LOWER(status_pembayaran) LIKE '%piutang%' LIMIT 1");
                if ($rowDP = mysqli_fetch_assoc($resDP)) {
                    $status_pembayaran_id = $rowDP['id_status_pembayaran'];
                }
            }
        }
        
        $payment_details = 'Multiple payment: ' . count($payment_methods) . ' metode pembayaran';
        // Sinkronkan kolom metode_pembayaran_id dengan metode pertama
        if (count($payment_methods) > 0) {
            $metode_pembayaran_id = $payment_methods[0]['method_id'];
        }
    } else {
        // Fallback to single payment method (backward compatibility)
        $payment_methods = [[
            'method_id' => (int)$metode_pembayaran_id,
            'amount' => (float)$jumlah,
            'rekening_id' => (int)$jenis_rekening_id,
            'payment_date' => (isset($tanggal_pemesanan) ? substr($tanggal_pemesanan, 0, 10) : (isset($tanggal_pengiriman) ? substr($tanggal_pengiriman, 0, 10) : date('Y-m-d')))
        ]];
        $payment_details = 'Single payment method';
    }
    
    $payment_methods_json = mysqli_real_escape_string($conn, json_encode($payment_methods));
    $payment_details = mysqli_real_escape_string($conn, $payment_details);

    $required = [
        'id_pengiriman' => $id,
        'nip_sgt_id' => $nip_sgt_id,
        'nama_id' => $nama_id,
        'alamat_id' => $alamat_id,
        'no_ktp_id' => $no_ktp_id,
        'no_hp_id' => $no_hp_id,
        'tanggal_pengiriman' => $tanggal_pengiriman,
        'jenis_barang' => $jenis_barang,
        'nama_penerima' => $nama_penerima,
        'no_hp_penerima' => $no_hp_penerima,
        'no_plat_id' => $no_plat_id,
        'kelas_id' => $kelas_id,
        'tujuan_id' => $tujuan_id,
        'status_pembayaran_id' => $status_pembayaran_id,
        'jumlah' => $jumlah,
        'user_id' => $user_id,
        'asal_po_id' => $asal_po_id
    ];
    foreach ($required as $key => $val) {
        if ($val === '' || $val === null) {
            die('SQL Error: Field ' . $key . ' tidak boleh kosong!');
        }
    }

    $sql = "UPDATE data_pengiriman SET 
        nip_sgt_id='$nip_sgt_id', nama_id='$nama_id', alamat_id='$alamat_id', no_ktp_id='$no_ktp_id', no_hp_id='$no_hp_id', tanggal_pengiriman='$tanggal_pengiriman', jenis_barang='$jenis_barang', nama_penerima='$nama_penerima', no_hp_penerima='$no_hp_penerima', no_plat_id='$no_plat_id', kelas_id='$kelas_id', tujuan_id='$tujuan_id', status_pembayaran_id='$status_pembayaran_id', metode_pembayaran_id='$metode_pembayaran_id', jenis_rekening_id='$jenis_rekening_id', payment_methods='$payment_methods_json', payment_details='$payment_details', jumlah=$jumlah, user_id='$user_id', asal_po_id='$asal_po_id', keterangan='$keterangan'
        WHERE id_pengiriman=$id";
    if (!function_exists('getOldData')) { require_once __DIR__ . '/logging_helper.php'; }
    $old_data = getOldData('data_pengiriman', $id);
    $result = confirmQuery(mysqli_query($conn, $sql));
    if ($result) {
        $new_data = json_encode([
            'nip_sgt_id'=>$nip_sgt_id,'nama_id'=>$nama_id,'alamat_id'=>$alamat_id,'no_ktp_id'=>$no_ktp_id,'no_hp_id'=>$no_hp_id,
            'tanggal_pengiriman'=>$tanggal_pengiriman,'jenis_barang'=>$jenis_barang,'nama_penerima'=>$nama_penerima,
            'no_hp_penerima'=>$no_hp_penerima,'no_plat_id'=>$no_plat_id,'kelas_id'=>$kelas_id,'tujuan_id'=>$tujuan_id,
            'status_pembayaran_id'=>$status_pembayaran_id,'metode_pembayaran_id'=>$metode_pembayaran_id,'jenis_rekening_id'=>$jenis_rekening_id,
            'jumlah'=>$jumlah,'user_id'=>$user_id,'asal_po_id'=>$asal_po_id
        ]);
        logUpdate('data_pengiriman', $id, 'Update pengiriman', $old_data, $new_data);
    }
    return $result;
}

function deletePengiriman($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    if (!function_exists('getOldData')) { require_once __DIR__ . '/logging_helper.php'; }
    $old_data = getOldData('data_pengiriman', $id);
    $result = confirmQuery(mysqli_query($conn, "DELETE FROM data_pengiriman WHERE id_pengiriman = $id"));
    if ($result && $old_data) {
        logDelete('data_pengiriman', $id, 'Hapus pengiriman', $old_data);
    }
    return $result;
}

function getPengirimanById($id) {
    global $conn;
    $id = (int)$id;
    $result = mysqli_query($conn, "SELECT * FROM data_pengiriman WHERE id_pengiriman = $id LIMIT 1");
    return mysqli_fetch_assoc($result);
}


// CRUD Data Pengeluaran
function tambahPengeluaran($data) {
    global $conn;
    $tanggal_pengeluaran = isset($data['tanggal_pengeluaran']) ? mysqli_real_escape_string($conn, $data['tanggal_pengeluaran']) : '';
    $jenis_pengeluaran_id = isset($data['jenis_pengeluaran_id']) ? mysqli_real_escape_string($conn, $data['jenis_pengeluaran_id']) : '';
    $kategori_pengeluaran = isset($data['kategori_pengeluaran']) ? mysqli_real_escape_string($conn, $data['kategori_pengeluaran']) : 'travel';
    $harga_operasional = isset($data['harga_operasional']) ? (float)$data['harga_operasional'] : 0;
    $travel_id = isset($data['travel_id']) && !empty($data['travel_id']) ? mysqli_real_escape_string($conn, $data['travel_id']) : 'NULL';
    $kelas = isset($data['kelas']) ? mysqli_real_escape_string($conn, $data['kelas']) : '';
    $harga = isset($data['harga']) ? mysqli_real_escape_string($conn, $data['harga']) : '';
    $status_pembayaran_id = isset($data['status_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['status_pembayaran_id']) : '';
    $metode_pembayaran_id = isset($data['metode_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['metode_pembayaran_id']) : '';
    $jenis_rekening_id = isset($data['jenis_rekening_id']) ? mysqli_real_escape_string($conn, $data['jenis_rekening_id']) : '';
    $user_id = isset($data['user_id']) ? mysqli_real_escape_string($conn, $data['user_id']) : '';
    $asal_po = isset($data['asal_po']) ? mysqli_real_escape_string($conn, $data['asal_po']) : '';
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, $data['keterangan']) : '';

    $required = [
        'tanggal_pengeluaran' => $tanggal_pengeluaran,
        'jenis_pengeluaran_id' => $jenis_pengeluaran_id,
        'harga_operasional' => $harga_operasional,
        'status_pembayaran_id' => $status_pembayaran_id,
        'metode_pembayaran_id' => $metode_pembayaran_id,
        'jenis_rekening_id' => $jenis_rekening_id,
        'user_id' => $user_id,
        'asal_po' => $asal_po
    ];
    foreach ($required as $key => $val) {
        if ($val === '' || $val === null) {
            die('SQL Error: Field ' . $key . ' tidak boleh kosong!');
        }
    }

    // Jika kategori kantor, travel_id harus NULL
    if ($kategori_pengeluaran === 'kantor') {
        $travel_id = 'NULL';
        $kelas = '';
        $harga = '';
    }

    $sql = "INSERT INTO data_pengeluaran (
        tanggal_pengeluaran, jenis_pengeluaran_id, kategori_pengeluaran, harga_operasional, travel_id, kelas, harga, status_pembayaran_id, metode_pembayaran_id, jenis_rekening_id, user_id, asal_po, keterangan
    ) VALUES (
        '$tanggal_pengeluaran', '$jenis_pengeluaran_id', '$kategori_pengeluaran', $harga_operasional, $travel_id, '$kelas', '$harga', '$status_pembayaran_id', '$metode_pembayaran_id', '$jenis_rekening_id', '$user_id', '$asal_po', '$keterangan'
    )";
    $result = confirmQuery(mysqli_query($conn, $sql));
    if ($result) {
        $new_id = mysqli_insert_id($conn);
        if (!function_exists('logCreate')) { require_once __DIR__ . '/logging_helper.php'; }
        $new_data = json_encode([
            'tanggal_pengeluaran'=>$tanggal_pengeluaran,'jenis_pengeluaran_id'=>$jenis_pengeluaran_id,'kategori_pengeluaran'=>$kategori_pengeluaran,
            'harga_operasional'=>$harga_operasional,'travel_id'=>$travel_id,'kelas'=>$kelas,'harga'=>$harga,
            'status_pembayaran_id'=>$status_pembayaran_id,'metode_pembayaran_id'=>$metode_pembayaran_id,'jenis_rekening_id'=>$jenis_rekening_id,
            'user_id'=>$user_id,'asal_po'=>$asal_po
        ]);
        logCreate('data_pengeluaran', $new_id, 'Tambah pengeluaran', $new_data);
    }
    return $result;
}

function updatePengeluaran($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = isset($data['id_pengeluaran']) ? (int)$data['id_pengeluaran'] : 0;
    $tanggal_pengeluaran = isset($data['tanggal_pengeluaran']) ? mysqli_real_escape_string($conn, $data['tanggal_pengeluaran']) : '';
    $jenis_pengeluaran_id = isset($data['jenis_pengeluaran_id']) ? mysqli_real_escape_string($conn, $data['jenis_pengeluaran_id']) : '';
    $kategori_pengeluaran = isset($data['kategori_pengeluaran']) ? mysqli_real_escape_string($conn, $data['kategori_pengeluaran']) : 'travel';
    $harga_operasional = isset($data['harga_operasional']) ? (float)$data['harga_operasional'] : 0;
    $travel_id = isset($data['travel_id']) && !empty($data['travel_id']) ? mysqli_real_escape_string($conn, $data['travel_id']) : 'NULL';
    $kelas = isset($data['kelas']) ? mysqli_real_escape_string($conn, $data['kelas']) : '';
    $harga = isset($data['harga']) ? mysqli_real_escape_string($conn, $data['harga']) : '';
    $status_pembayaran_id = isset($data['status_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['status_pembayaran_id']) : '';
    $metode_pembayaran_id = isset($data['metode_pembayaran_id']) ? mysqli_real_escape_string($conn, $data['metode_pembayaran_id']) : '';
    $jenis_rekening_id = isset($data['jenis_rekening_id']) ? mysqli_real_escape_string($conn, $data['jenis_rekening_id']) : '';
    $user_id = isset($data['user_id']) ? mysqli_real_escape_string($conn, $data['user_id']) : '';
    $asal_po = isset($data['asal_po']) ? mysqli_real_escape_string($conn, $data['asal_po']) : '';
    $keterangan = isset($data['keterangan']) ? mysqli_real_escape_string($conn, $data['keterangan']) : '';

    $required = [
        'id_pengeluaran' => $id,
        'tanggal_pengeluaran' => $tanggal_pengeluaran,
        'jenis_pengeluaran_id' => $jenis_pengeluaran_id,
        'harga_operasional' => $harga_operasional,
        'status_pembayaran_id' => $status_pembayaran_id,
        'metode_pembayaran_id' => $metode_pembayaran_id,
        'jenis_rekening_id' => $jenis_rekening_id,
        'user_id' => $user_id,
        'asal_po' => $asal_po
    ];
    foreach ($required as $key => $val) {
        if ($val === '' || $val === null) {
            die('SQL Error: Field ' . $key . ' tidak boleh kosong!');
        }
    }

    // Jika kategori kantor, travel_id harus NULL
    if ($kategori_pengeluaran === 'kantor') {
        $travel_id = 'NULL';
        $kelas = '';
        $harga = '';
    }

    $sql = "UPDATE data_pengeluaran SET 
        tanggal_pengeluaran='$tanggal_pengeluaran', jenis_pengeluaran_id='$jenis_pengeluaran_id', kategori_pengeluaran='$kategori_pengeluaran', harga_operasional=$harga_operasional, travel_id=$travel_id, kelas='$kelas', harga='$harga', status_pembayaran_id='$status_pembayaran_id', metode_pembayaran_id='$metode_pembayaran_id', jenis_rekening_id='$jenis_rekening_id', user_id='$user_id', asal_po='$asal_po', keterangan='$keterangan'
        WHERE id_pengeluaran=$id";
    if (!function_exists('getOldData')) { require_once __DIR__ . '/logging_helper.php'; }
    $old_data = getOldData('data_pengeluaran', $id);
    $result = confirmQuery(mysqli_query($conn, $sql));
    if ($result) {
        $new_data = json_encode([
            'tanggal_pengeluaran'=>$tanggal_pengeluaran,'jenis_pengeluaran_id'=>$jenis_pengeluaran_id,'kategori_pengeluaran'=>$kategori_pengeluaran,
            'harga_operasional'=>$harga_operasional,'travel_id'=>$travel_id,'kelas'=>$kelas,'harga'=>$harga,
            'status_pembayaran_id'=>$status_pembayaran_id,'metode_pembayaran_id'=>$metode_pembayaran_id,'jenis_rekening_id'=>$jenis_rekening_id,
            'user_id'=>$user_id,'asal_po'=>$asal_po
        ]);
        logUpdate('data_pengeluaran', $id, 'Update pengeluaran', $old_data, $new_data);
    }
    return $result;
}

function deletePengeluaran($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    if (!function_exists('getOldData')) { require_once __DIR__ . '/logging_helper.php'; }
    $old_data = getOldData('data_pengeluaran', $id);
    $result = confirmQuery(mysqli_query($conn, "DELETE FROM data_pengeluaran WHERE id_pengeluaran = $id"));
    if ($result && $old_data) {
        logDelete('data_pengeluaran', $id, 'Hapus pengeluaran', $old_data);
    }
    return $result;
}

function getPengeluaranById($id) {
    global $conn;
    $id = (int)$id;
    $result = mysqli_query($conn, "SELECT * FROM data_pengeluaran WHERE id_pengeluaran = $id LIMIT 1");
    return mysqli_fetch_assoc($result);
}

// Data Saldo Awal Bank
function tambahSaldoAwal($data) {
    global $conn;
    $id_rekening = (int)$data['id_rekening'];
    $bulan = (int)$data['bulan'];
    $tahun = (int)$data['tahun'];
    $saldo_awal = (float)$data['saldo_awal'];
    $created_by = mysqli_real_escape_string($conn, $data['created_by']);
    
    $sql = "INSERT INTO saldo_awal_bank (id_rekening, bulan, tahun, saldo_awal, created_by) 
            VALUES ($id_rekening, $bulan, $tahun, $saldo_awal, '$created_by')
            ON DUPLICATE KEY UPDATE 
            saldo_awal = $saldo_awal, 
            updated_at = CURRENT_TIMESTAMP, 
            created_by = '$created_by'";
    $result = confirmQuery(mysqli_query($conn, $sql));
    if ($result) {
        $new_id = mysqli_insert_id($conn);
        if (!function_exists('logCreate')) { require_once __DIR__ . '/logging_helper.php'; }
        $new_data = json_encode([
            'id_rekening'=>$id_rekening,'bulan'=>$bulan,'tahun'=>$tahun,'saldo_awal'=>$saldo_awal,'created_by'=>$created_by
        ]);
        logCreate('saldo_awal_bank', $new_id, 'Tambah saldo awal', $new_data);
    }
    return $result;
}

function updateSaldoAwal($data) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$data['id_saldo_awal'];
    $id_rekening = (int)$data['id_rekening'];
    $bulan = (int)$data['bulan'];
    $tahun = (int)$data['tahun'];
    $saldo_awal = (float)$data['saldo_awal'];
    $created_by = mysqli_real_escape_string($conn, $data['created_by']);
    
    $sql = "UPDATE saldo_awal_bank SET 
            id_rekening = $id_rekening, 
            bulan = $bulan, 
            tahun = $tahun, 
            saldo_awal = $saldo_awal, 
            created_by = '$created_by',
            updated_at = CURRENT_TIMESTAMP
            WHERE id_saldo_awal = $id";
    if (!function_exists('getOldData')) { require_once __DIR__ . '/logging_helper.php'; }
    $old_data = getOldData('saldo_awal_bank', $id);
    $result = confirmQuery(mysqli_query($conn, $sql));
    if ($result) {
        $new_data = json_encode([
            'id_rekening'=>$id_rekening,'bulan'=>$bulan,'tahun'=>$tahun,'saldo_awal'=>$saldo_awal,'created_by'=>$created_by
        ]);
        logUpdate('saldo_awal_bank', $id, 'Update saldo awal', $old_data, $new_data);
    }
    return $result;
}

function deleteSaldoAwal($id) {
    assertSuperAdminForWrite();
    global $conn;
    $id = (int)$id;
    if (!function_exists('getOldData')) { require_once __DIR__ . '/logging_helper.php'; }
    $old_data = getOldData('saldo_awal_bank', $id);
    $result = confirmQuery(mysqli_query($conn, "DELETE FROM saldo_awal_bank WHERE id_saldo_awal = $id"));
    if ($result && $old_data) {
        logDelete('saldo_awal_bank', $id, 'Hapus saldo awal', $old_data);
    }
    return $result;
}

function getAllSaldoAwal() {
    global $conn;
    $sql = "SELECT sa.*, dr.nama_rekening, dr.nomor_rekening 
            FROM saldo_awal_bank sa 
            LEFT JOIN data_rekening dr ON sa.id_rekening = dr.id_rekening 
            ORDER BY sa.tahun DESC, sa.bulan DESC, dr.nama_rekening ASC";
    return confirmQuery(mysqli_query($conn, $sql));
}

function getSaldoAwalById($id) {
    global $conn;
    $id = (int)$id;
    $result = mysqli_query($conn, "SELECT * FROM saldo_awal_bank WHERE id_saldo_awal = $id LIMIT 1");
    return mysqli_fetch_assoc($result);
}



