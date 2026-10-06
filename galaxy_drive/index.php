<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$userDir = 'users/' . $username;

// Buat direktori user jika belum ada
if (!file_exists($userDir)) {
    mkdir($userDir, 0777, true);
}

// Handle current directory
$currentDir = isset($_GET['dir']) ? $_GET['dir'] : '';
$fullPath = $userDir . '/' . $currentDir;

// Pastikan path aman (tidak keluar dari direktori user)
if (strpos(realpath($fullPath), realpath($userDir)) !== 0) {
    $currentDir = '';
    $fullPath = $userDir;
}

// Handle file operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload':
                if (isset($_FILES['file'])) {
                    $targetFile = $fullPath . '/' . basename($_FILES['file']['name']);
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                        $message = 'File berhasil diupload!';
                    } else {
                        $error = 'Gagal mengupload file!';
                    }
                }
                break;
            case 'create_folder':
                if (isset($_POST['folder_name']) && !empty($_POST['folder_name'])) {
                    $folderPath = $fullPath . '/' . $_POST['folder_name'];
                    if (mkdir($folderPath, 0777, true)) {
                        $message = 'Folder berhasil dibuat!';
                    } else {
                        $error = 'Gagal membuat folder!';
                    }
                }
                break;
            case 'delete':
                if (isset($_POST['item_name'])) {
                    // Perbaiki konstruksi path untuk menghindari double slash
                    $itemPath = rtrim($fullPath, '/') . '/' . $_POST['item_name'];
                    
                    // Validasi keberadaan file/folder sebelum menghapus
                    if (!file_exists($itemPath)) {
                        $error = 'File atau folder tidak ditemukan!';
                        break;
                    }
                    
                    // Pastikan path masih dalam direktori user (security check)
                    if (strpos(realpath($itemPath), realpath($userDir)) !== 0) {
                        $error = 'Akses ditolak!';
                        break;
                    }
                    
                    if (is_dir($itemPath)) {
                        // Cek apakah folder kosong sebelum menghapus
                        if (count(scandir($itemPath)) > 2) {
                            $error = 'Folder tidak kosong! Hapus isi folder terlebih dahulu.';
                        } else {
                            if (rmdir($itemPath)) {
                                $message = 'Folder berhasil dihapus!';
                            } else {
                                $error = 'Gagal menghapus folder!';
                            }
                        }
                    } else {
                        if (unlink($itemPath)) {
                            $message = 'File berhasil dihapus!';
                        } else {
                            $error = 'Gagal menghapus file!';
                        }
                    }
                }
                break;
        }
    }
}

// Get directory contents
$items = [];
if (file_exists($fullPath) && is_dir($fullPath)) {
    $files = scandir($fullPath);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $fullPath . '/' . $file;
            $items[] = [
                'name' => $file,
                'type' => is_dir($filePath) ? 'folder' : 'file',
                'size' => is_file($filePath) ? filesize($filePath) : 0,
                'modified' => filemtime($filePath)
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galaxy Drive - <?php echo htmlspecialchars($username); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <link rel="icon" href="../img/logo_sgt.png">
</head>
<body>
    <!-- Windows Explorer Style Layout -->
    <div class="explorer-container">
        <!-- Title Bar -->
        <div class="title-bar">
            <div class="title-bar-left">
                <img src="../img/logo_sgt.png" alt="Logo" class="title-logo">
                <span class="title-text">Galaxy Drive - <?php echo htmlspecialchars($username); ?></span>
            </div>
            <div class="title-bar-right">
                <button class="title-btn minimize"><i class="fas fa-minus"></i></button>
                <button class="title-btn maximize"><i class="fas fa-square"></i></button>
                <a href="logout.php" class="title-btn close"><i class="fas fa-times"></i></a>
            </div>
        </div>

        <!-- Menu Bar -->
        <div class="menu-bar">
            <div class="menu-item">File</div>
            <div class="menu-item">Edit</div>
            <div class="menu-item">View</div>
            <div class="menu-item">Tools</div>
            <div class="menu-item">Help</div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar-explorer">
            <div class="toolbar-section">
                <?php if (!empty($currentDir)): ?>
                    <button class="toolbar-btn" onclick="navigateUp()" title="Up">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                <?php endif; ?>
                <button class="toolbar-btn" onclick="location.reload()" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="toolbar-section">
                <button class="toolbar-btn" onclick="showUploadModal()" title="Upload File">
                    <i class="fas fa-upload"></i>
                </button>
                <button class="toolbar-btn" onclick="showFolderModal()" title="New Folder">
                    <i class="fas fa-folder-plus"></i>
                </button>
            </div>
            <div class="toolbar-section">
                <div class="view-options">
                    <button class="toolbar-btn active" title="Details View">
                        <i class="fas fa-list"></i>
                    </button>
                    <button class="toolbar-btn" title="Large Icons">
                        <i class="fas fa-th-large"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Address Bar -->
        <div class="address-bar">
            <div class="address-content">
                <i class="fas fa-folder-open"></i>
                <span class="address-path">
                    Galaxy Drive
                    <?php if (!empty($currentDir)): ?>
                        <?php
                        $pathParts = explode('/', $currentDir);
                        $buildPath = '';
                        foreach ($pathParts as $part) {
                            $buildPath .= $part;
                            echo ' > ' . htmlspecialchars($part);
                            $buildPath .= '/';
                        }
                        ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-section">
                    <div class="sidebar-title">Quick Access</div>
                    <div class="sidebar-item active" onclick="location.href='index.php'">
                        <i class="fas fa-home"></i>
                        <span>My Drive</span>
                    </div>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-title">This PC</div>
                    <div class="sidebar-item" onclick="location.href='index.php'">
                        <i class="fas fa-hdd"></i>
                        <span>Galaxy Drive (C:)</span>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <?php if (isset($message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- File List Header -->
                <div class="file-list-header">
                    <div class="column-header name-column">
                        <i class="fas fa-sort"></i> Name
                    </div>
                    <div class="column-header date-column">
                        <i class="fas fa-sort"></i> Date modified
                    </div>
                    <div class="column-header type-column">
                        <i class="fas fa-sort"></i> Type
                    </div>
                    <div class="column-header size-column">
                        <i class="fas fa-sort"></i> Size
                    </div>
                </div>

                <!-- File List -->
                <div class="file-list">
                    <?php foreach ($items as $item): ?>
                        <div class="file-row <?php echo $item['type']; ?>" 
                             <?php if ($item['type'] === 'folder'): ?>
                                 onclick="openFolder('<?php echo htmlspecialchars($item['name']); ?>')"
                             <?php else: ?>
                                 onclick="previewFile('<?php echo htmlspecialchars($item['name']); ?>')"
                             <?php endif; ?>>
                            
                            <div class="file-cell name-cell">
                                <div class="file-icon-small">
                                    <?php if ($item['type'] === 'folder'): ?>
                                        <i class="fas fa-folder"></i>
                                    <?php else: ?>
                                        <?php
                                        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                        switch ($ext) {
                                            case 'pdf': echo '<i class="fas fa-file-pdf"></i>'; break;
                                            case 'doc': case 'docx': echo '<i class="fas fa-file-word"></i>'; break;
                                            case 'xls': case 'xlsx': echo '<i class="fas fa-file-excel"></i>'; break;
                                            case 'ppt': case 'pptx': echo '<i class="fas fa-file-powerpoint"></i>'; break;
                                            case 'jpg': case 'jpeg': case 'png': case 'gif': echo '<i class="fas fa-file-image"></i>'; break;
                                            case 'mp4': case 'avi': case 'mov': echo '<i class="fas fa-file-video"></i>'; break;
                                            case 'mp3': case 'wav': echo '<i class="fas fa-file-audio"></i>'; break;
                                            case 'zip': case 'rar': echo '<i class="fas fa-file-archive"></i>'; break;
                                            default: echo '<i class="fas fa-file"></i>';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </div>
                                <span class="file-name-text"><?php echo htmlspecialchars($item['name']); ?></span>
                            </div>
                            
                            <div class="file-cell date-cell">
                                <?php echo date('d/m/Y H:i', $item['modified']); ?>
                            </div>
                            
                            <div class="file-cell type-cell">
                                <?php if ($item['type'] === 'folder'): ?>
                                    File folder
                                <?php else: ?>
                                    <?php
                                    $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                    echo $ext ? strtoupper($ext) . ' file' : 'File';
                                    ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="file-cell size-cell">
                                <?php if ($item['type'] === 'file'): ?>
                                    <?php echo formatFileSize($item['size']); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="file-actions-explorer">
                                <?php if ($item['type'] === 'file'): ?>
                                    <button onclick="previewFile('<?php echo htmlspecialchars($item['name']); ?>', event)" class="action-btn" title="Preview">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="downloadFile('<?php echo htmlspecialchars($item['name']); ?>', event)" class="action-btn" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                <?php endif; ?>
                                <button onclick="deleteItem('<?php echo htmlspecialchars($item['name']); ?>', event)" class="action-btn delete-btn" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($items)): ?>
                        <div class="empty-folder">
                            <i class="fas fa-folder-open"></i>
                            <p>This folder is empty</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-left">
                <?php echo count($items); ?> items
            </div>
            <div class="status-right">
                Ready
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('uploadModal')">&times;</span>
            <h2>Upload File</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="form-group">
                    <input type="file" name="file" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal preview-modal">
        <div class="modal-content">
            <div class="preview-header">
                <div class="preview-title" id="previewFileName">
                    <i class="fas fa-file"></i>
                    <span>File Preview</span>
                </div>
                <div class="preview-actions">
                    <button onclick="toggleFullscreen()" class="btn btn-primary" title="Toggle Fullscreen (F11)">
                        <i class="fas fa-expand"></i> Fullscreen
                    </button>
                    <button onclick="downloadCurrentFile()" class="btn btn-primary" title="Download (Ctrl+D)">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button class="preview-close" onclick="closeModal('previewModal')" title="Close (Esc)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="previewContent" class="preview-body">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Folder Modal -->
    <div id="folderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('folderModal')">&times;</span>
            <h2>New Folder</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_folder">
                <div class="form-group">
                    <input type="text" name="folder_name" placeholder="Folder name" required>
                </div>
                <button type="submit" class="btn btn-primary">Create Folder</button>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>

<?php
function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    return round($size, 2) . ' ' . $units[$unitIndex];
}
?>