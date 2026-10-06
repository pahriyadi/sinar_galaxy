<?php
// System backup utilities: database + project files → galaxy_drive/backups

if (!session_id()) { session_start(); }
require_once __DIR__ . '/../../inc/koneksi.php';

function ensureGalaxyBackupDir(): string {
    $dir = realpath(__DIR__ . '/../../galaxy_drive');
    if ($dir === false) {
        // fallback ke assets/backup/backups
        $fallback = __DIR__ . '/backups';
        if (!is_dir($fallback)) { @mkdir($fallback, 0775, true); }
        return realpath($fallback) ?: $fallback;
    }
    $backupDir = $dir . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($backupDir)) { @mkdir($backupDir, 0775, true); }
    return $backupDir;
}

function detectMysqldumpPath(): ?string {
    // Cek environment override
    if (!empty($_ENV['MYSQLDUMP_PATH']) && file_exists($_ENV['MYSQLDUMP_PATH'])) {
        return $_ENV['MYSQLDUMP_PATH'];
    }
    // Windows XAMPP umum
    $candidates = [
        'mysqldump',
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'D:\\xampp\\mysql\\bin\\mysqldump.exe',
        'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump'
    ];
    foreach ($candidates as $path) {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            if (preg_match('/\\.exe$/i', $path) && file_exists($path)) return $path;
        } else {
            // non-Windows, asumsikan tersedia di PATH atau file_exists
            if ($path === 'mysqldump') return $path;
            if (file_exists($path)) return $path;
        }
    }
    return null;
}

function backupDatabaseSql(string $host, string $user, string $pass, string $dbname, string $destFile): array {
    $mysqldump = detectMysqldumpPath();
    if ($mysqldump === null) {
        return ['ok' => false, 'message' => 'mysqldump tidak ditemukan'];
    }
    $hostArg = escapeshellarg($host);
    $userArg = escapeshellarg($user);
    $dbArg = escapeshellarg($dbname);
    $passArg = $pass !== '' ? "-p" . escapeshellarg($pass) : '';
    $cmd = "$mysqldump -h $hostArg -u $userArg $passArg --single-transaction --routines --events $dbArg";
    // Eksekusi dan simpan ke file
    $descriptors = [1 => ['file', $destFile, 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return ['ok' => false, 'message' => 'Gagal menjalankan mysqldump'];
    }
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    if ($exit !== 0) {
        return ['ok' => false, 'message' => trim($stderr) ?: 'mysqldump exit ' . $exit];
    }
    return ['ok' => true, 'message' => 'SQL dump created'];
}

function exportDatabaseJson(mysqli $conn, string $destFile): array {
    $db = $conn->real_escape_string($conn->query('SELECT DATABASE()')->fetch_row()[0] ?? '');
    $tablesRes = $conn->query('SHOW TABLES');
    $data = ['database' => $db, 'generated_at' => date('c'), 'tables' => []];
    if ($tablesRes) {
        while ($row = $tablesRes->fetch_array()) {
            $table = $row[0];
            $rows = [];
            $res = $conn->query("SELECT * FROM `$table`");
            if ($res) { $rows = $res->fetch_all(MYSQLI_ASSOC); }
            $data['tables'][$table] = $rows;
        }
    }
    file_put_contents($destFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return ['ok' => true, 'message' => 'JSON export created'];
}

function backupDatabaseToGalaxy(): array {
    global $host, $user, $pass, $dbname, $conn;
    $targetDir = ensureGalaxyBackupDir();
    $stamp = date('Ymd_His');
    $sqlFile = $targetDir . DIRECTORY_SEPARATOR . "db_{$dbname}_{$stamp}.sql";
    $jsonFallback = $targetDir . DIRECTORY_SEPARATOR . "db_{$dbname}_{$stamp}.json";

    $sql = backupDatabaseSql($host, $user, $pass, $dbname, $sqlFile);
    if ($sql['ok']) {
        return ['ok' => true, 'type' => 'sql', 'file' => relGalaxyPath($sqlFile)];
    }
    // fallback JSON
    $json = exportDatabaseJson($conn, $jsonFallback);
    if ($json['ok']) {
        return ['ok' => true, 'type' => 'json', 'file' => relGalaxyPath($jsonFallback), 'note' => $sql['message']];
    }
    return ['ok' => false, 'message' => 'Backup database gagal'];
}

function relGalaxyPath(string $abs): string {
    // relatif terhadap root project
    $root = realpath(__DIR__ . '/../../');
    if ($root && strpos($abs, $root) === 0) {
        $rel = str_replace(['\\', $root . DIRECTORY_SEPARATOR], ['/', ''], $abs);
        return $rel;
    }
    return $abs;
}

function zipDir(string $dir, string $zipPath, array $exclude = []): bool {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    $dir = rtrim($dir, DIRECTORY_SEPARATOR);
    $len = strlen($dir) + 1;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = $file->getPathname();
        $rel = substr($path, $len);
        $relUnix = str_replace('\\', '/', $rel);
        $skip = false;
        foreach ($exclude as $ex) {
            if (strpos($relUnix, $ex) === 0) { $skip = true; break; }
        }
        if ($skip) continue;
        if ($file->isFile()) {
            $zip->addFile($path, $relUnix);
        }
    }
    return $zip->close();
}

function backupProjectToGalaxy(): array {
    $projectRoot = realpath(__DIR__ . '/../../');
    $targetDir = ensureGalaxyBackupDir();
    $stamp = date('Ymd_His');
    $zipFile = $targetDir . DIRECTORY_SEPARATOR . "site_trevalku_{$stamp}.zip";
    $exclude = [
        'galaxy_drive/backups',
        'assets/backup/backups',
        '.git',
        'vendor' // jika ada
    ];
    $ok = zipDir($projectRoot, $zipFile, $exclude);
    if ($ok) {
        return ['ok' => true, 'file' => relGalaxyPath($zipFile)];
    }
    return ['ok' => false, 'message' => 'Gagal membuat ZIP project'];
}

?>

