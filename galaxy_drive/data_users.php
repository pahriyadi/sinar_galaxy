<?php
// File untuk menyimpan data users tanpa database
// Format: username => password

$users = [
    'admin' => 'sinargalaxy',
    'user1' => 'travelsumbawa',
];

// Fungsi untuk validasi login
function validateUser($username, $password) {
    global $users;
    
    // Cek apakah username ada dan password cocok
    if (isset($users[$username]) && $users[$username] === $password) {
        return true;
    }
    
    return false;
}

// Fungsi untuk mendapatkan semua users (tanpa password)
function getAllUsers() {
    global $users;
    return array_keys($users);
}

// Fungsi untuk menambah user baru
function addUser($username, $password) {
    global $users;
    
    // Cek apakah username sudah ada
    if (isset($users[$username])) {
        return false; // Username sudah ada
    }
    
    $users[$username] = $password;
    
    // Simpan ke file (optional - untuk persistensi)
    saveUsersToFile();
    
    return true;
}

// Fungsi untuk menyimpan data users ke file
function saveUsersToFile() {
    global $users;
    
    $data = "<?php\n// Auto-generated users data\n\n\$users = [\n";
    
    foreach ($users as $username => $password) {
        $data .= "    '" . addslashes($username) . "' => '" . addslashes($password) . "',\n";
    }
    
    $data .= "];\n";
    
    // Simpan ke file backup
    file_put_contents('users_backup.php', $data);
}

// Fungsi untuk load users dari file backup (jika ada)
function loadUsersFromFile() {
    global $users;
    
    if (file_exists('users_backup.php')) {
        include 'users_backup.php';
    }
}

// Load users dari file saat pertama kali
loadUsersFromFile();
?>