// Tambahkan fungsi yang hilang untuk navigasi

function navigateUp() {
    const currentUrl = new URL(window.location);
    const currentDir = currentUrl.searchParams.get('dir') || '';
    
    if (currentDir) {
        const pathParts = currentDir.split('/');
        pathParts.pop(); // Remove last directory
        const newDir = pathParts.join('/');
        
        if (newDir) {
            window.location.href = `index.php?dir=${encodeURIComponent(newDir)}`;
        } else {
            window.location.href = 'index.php';
        }
    }
}

function openFolder(folderName) {
    const currentUrl = new URL(window.location);
    const currentDir = currentUrl.searchParams.get('dir') || '';
    const newDir = currentDir ? `${currentDir}/${folderName}` : folderName;
    
    window.location.href = `index.php?dir=${encodeURIComponent(newDir)}`;
}

// Modal functions
function showUploadModal() {
    document.getElementById('uploadModal').style.display = 'block';
}

function showFolderModal() {
    document.getElementById('folderModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Preview functions
// Enhanced preview functions
function previewFile(fileName, event) {
    if (event) {
        event.stopPropagation();
    }
    
    const modal = document.getElementById('previewModal');
    const previewContent = document.getElementById('previewContent');
    const previewFileName = document.getElementById('previewFileName');
    
    // Show loading state
    previewContent.innerHTML = `
        <div class="preview-loading">
            <div class="loading"></div>
            <p>Loading preview...</p>
        </div>
    `;
    
    previewFileName.innerHTML = `
        <i class="fas fa-file"></i>
        <span>${fileName}</span>
    `;
    
    // Get file extension
    const ext = fileName.split('.').pop().toLowerCase();
    
    // Build preview URL
    const currentUrl = new URL(window.location);
    const currentDir = currentUrl.searchParams.get('dir') || '';
    const previewUrl = `preview.php?file=${encodeURIComponent(fileName)}&dir=${encodeURIComponent(currentDir)}`;
    
    // Set preview content based on file type
    setTimeout(() => {
        if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(ext)) {
            previewContent.innerHTML = `
                <div class="preview-content">
                    <img src="${previewUrl}" alt="${fileName}" onload="this.style.opacity=1" style="opacity:0; transition: opacity 0.3s;">
                </div>
            `;
        } else if (['mp4', 'webm', 'ogg', 'avi', 'mov'].includes(ext)) {
            previewContent.innerHTML = `
                <div class="preview-content">
                    <video controls style="max-width: 100%; max-height: 100%;">
                        <source src="${previewUrl}" type="video/${ext}">
                        Your browser does not support the video tag.
                    </video>
                </div>
            `;
        } else if (['mp3', 'wav', 'ogg', 'aac', 'flac'].includes(ext)) {
            previewContent.innerHTML = `
                <div class="preview-content">
                    <audio controls style="width: 100%; max-width: 500px;">
                        <source src="${previewUrl}" type="audio/${ext}">
                        Your browser does not support the audio tag.
                    </audio>
                </div>
            `;
        } else if (ext === 'pdf') {
            previewContent.innerHTML = `
                <div class="preview-content">
                    <iframe src="${previewUrl}" style="width: 100%; height: 100%;"></iframe>
                </div>
            `;
        } else if (['txt', 'log', 'md', 'json', 'xml', 'csv', 'js', 'css', 'html', 'php', 'py', 'java', 'cpp', 'c', 'h'].includes(ext)) {
            fetch(previewUrl)
                .then(response => {
                    if (!response.ok) throw new Error('Failed to load file');
                    return response.text();
                })
                .then(text => {
                    previewContent.innerHTML = `
                        <div class="preview-content">
                            <pre>${text}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    previewContent.innerHTML = `
                        <div class="preview-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>Error Loading File</h3>
                            <p>Unable to load file preview. Please try downloading the file instead.</p>
                        </div>
                    `;
                });
        } else if (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(ext)) {
            const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + previewUrl;
            previewContent.innerHTML = `
                <div class="preview-content">
                    <iframe src="https://docs.google.com/viewer?url=${encodeURIComponent(fullUrl)}&embedded=true" style="width: 100%; height: 100%;"></iframe>
                </div>
            `;
        } else {
            previewContent.innerHTML = `
                <div class="preview-unsupported">
                    <i class="fas fa-file"></i>
                    <h3>Preview Not Available</h3>
                    <p>Preview is not supported for this file type.</p>
                    <p>Click the download button to view the file.</p>
                </div>
            `;
        }
    }, 300);
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Store current file for download
    window.currentPreviewFile = fileName;
}

// Close modal function
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Clear fullscreen if active
    const modal = document.getElementById(modalId);
    if (modal && modal.querySelector('.modal-content').classList.contains('preview-fullscreen')) {
        toggleFullscreen();
    }
}

// Toggle fullscreen preview
function toggleFullscreen() {
    const modalContent = document.querySelector('#previewModal .modal-content');
    modalContent.classList.toggle('preview-fullscreen');
}

// Enhanced keyboard shortcuts
document.addEventListener('keydown', function(event) {
    const previewModal = document.getElementById('previewModal');
    
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                closeModal(modal.id);
            }
        });
    }
    
    // F11 for fullscreen in preview
    if (event.key === 'F11' && previewModal.style.display === 'block') {
        event.preventDefault();
        toggleFullscreen();
    }
    
    // Ctrl+D for download in preview
    if (event.ctrlKey && event.key === 'd' && previewModal.style.display === 'block') {
        event.preventDefault();
        downloadCurrentFile();
    }
});

function downloadFile(fileName, event) {
    if (event) {
        event.stopPropagation();
    }
    
    const currentUrl = new URL(window.location);
    const currentDir = currentUrl.searchParams.get('dir') || '';
    const downloadUrl = `download.php?file=${encodeURIComponent(fileName)}&dir=${encodeURIComponent(currentDir)}`;
    
    window.open(downloadUrl, '_blank');
}

function downloadCurrentFile() {
    if (window.currentPreviewFile) {
        downloadFile(window.currentPreviewFile);
    }
}

function deleteItem(itemName, event) {
    if (event) {
        event.stopPropagation();
    }
    
    if (confirm(`Are you sure you want to delete "${itemName}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="item_name" value="${itemName}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
    }
});