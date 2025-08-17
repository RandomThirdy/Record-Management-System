// ODCI/roles/user/assets/js/files.js

document.addEventListener('DOMContentLoaded', function() {
    initializeFilesPage();
});

function initializeFilesPage() {
    setupEventListeners();
    setupViewToggle();
    setupFilters();
    setupDropdowns();
    loadUserPreferences();
}

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('fileSearch');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                handleSearch(this.value);
            }, 300);
        });
    }

    // Filter changes
    const categoryFilter = document.getElementById('categoryFilter');
    const sortFilter = document.getElementById('sortFilter');
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            updateFilters();
        });
    }
    
    if (sortFilter) {
        sortFilter.addEventListener('change', function() {
            updateFilters();
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            closeAllDropdowns();
        }
    });

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('fileDetailsModal');
        if (e.target === modal) {
            closeFileDetails();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape key closes modals and dropdowns
        if (e.key === 'Escape') {
            closeFileDetails();
            closeAllDropdowns();
        }
        
        // Ctrl/Cmd + F focuses search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });
}

function setupViewToggle() {
    const viewToggles = document.querySelectorAll('.view-toggle');
    const filesGrid = document.getElementById('filesGrid');
    
    viewToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const view = this.dataset.view;
            
            // Update active state
            viewToggles.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update grid view
            if (filesGrid) {
                if (view === 'list') {
                    filesGrid.classList.remove('files-grid');
                    filesGrid.classList.add('files-list');
                } else {
                    filesGrid.classList.remove('files-list');
                    filesGrid.classList.add('files-grid');
                }
            }
            
            // Save preference
            localStorage.setItem('filesView', view);
        });
    });
}

function setupFilters() {
    // Initialize filter state
    updateFilterDisplay();
}

function setupDropdowns() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleDropdown(this);
        });
    });
}

function loadUserPreferences() {
    // Load saved view preference
    const savedView = localStorage.getItem('filesView');
    if (savedView) {
        const viewToggle = document.querySelector(`[data-view="${savedView}"]`);
        if (viewToggle) {
            viewToggle.click();
        }
    }
}

// Search functionality
function handleSearch(searchTerm) {
    updateURL({ search: searchTerm, page: 1 });
}

function clearFilters() {
    document.getElementById('fileSearch').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('sortFilter').value = 'uploaded_at-DESC';
    
    updateURL({ search: '', category: '', sort: 'uploaded_at', order: 'DESC', page: 1 });
}

function updateFilters() {
    const category = document.getElementById('categoryFilter').value;
    const sortValue = document.getElementById('sortFilter').value;
    const [sort, order] = sortValue.split('-');
    
    updateURL({ category, sort, order, page: 1 });
}

function updateURL(params) {
    const url = new URL(window.location);
    
    // Update URL parameters
    Object.keys(params).forEach(key => {
        if (params[key]) {
            url.searchParams.set(key, params[key]);
        } else {
            url.searchParams.delete(key);
        }
    });
    
    // Navigate to new URL
    window.location.href = url.toString();
}

function updateFilterDisplay() {
    const hasFilters = window.currentFilters.search || window.currentFilters.category;
    const filterSection = document.querySelector('.filter-section');
    
    if (hasFilters && filterSection) {
        filterSection.classList.add('has-filters');
    }
}

// Dropdown functionality
function toggleDropdown(button) {
    closeAllDropdowns();
    
    const dropdown = button.closest('.dropdown');
    const menu = dropdown.querySelector('.dropdown-menu');
    
    if (menu) {
        menu.classList.add('show');
    }
}

function closeAllDropdowns() {
    const dropdownMenus = document.querySelectorAll('.dropdown-menu');
    dropdownMenus.forEach(menu => {
        menu.classList.remove('show');
    });
}

// File actions
function downloadFile(fileId) {
    // Show loading state
    showNotification('Preparing download...', 'info');
    
    // Create download link
    const downloadUrl = `../../handlers/download_file.php?id=${fileId}`;
    
    // Create temporary link and click it
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Update download count in UI
    updateDownloadCount(fileId);
    
    showNotification('Download started successfully!', 'success');
}

function viewFileDetails(fileId) {
    const modal = document.getElementById('fileDetailsModal');
    const content = document.getElementById('fileDetailsContent');
    
    if (!modal || !content) return;
    
    // Show modal with loading state
    content.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Loading file details...</p>
        </div>
    `;
    
    modal.classList.add('show');
    
    // Fetch file details
    fetchFileDetails(fileId)
        .then(data => {
            displayFileDetails(data);
        })
        .catch(error => {
            content.innerHTML = `
                <div class="error-state">
                    <i class='bx bx-error-circle' style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i>
                    <h4>Error loading file details</h4>
                    <p>${error.message}</p>
                </div>
            `;
        });
}

function fetchFileDetails(fileId) {
    return fetch(`../../handlers/get_file_details.php?id=${fileId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch file details');
            }
            return response.json();
        });
}

function displayFileDetails(file) {
    const content = document.getElementById('fileDetailsContent');
    const category = window.fileCategories[file.category] || { name: 'Uncategorized', color: '#6b7280', icon: 'bxs-file' };
    
    content.innerHTML = `
        <div class="file-details">
            <div class="file-preview">
                <div class="preview-icon" style="background-color: ${category.color}">
                    <i class='bx ${getFileIcon(file.file_name)}'></i>
                </div>
                <div class="preview-info">
                    <h4>${escapeHtml(file.file_name)}</h4>
                    <p class="file-path">${escapeHtml(file.file_path || '')}</p>
                </div>
            </div>
            
            <div class="details-grid">
                <div class="detail-item">
                    <label>Original Name:</label>
                    <span>${escapeHtml(file.original_name || file.file_name)}</span>
                </div>
                <div class="detail-item">
                    <label>File Size:</label>
                    <span>${formatFileSize(file.file_size)}</span>
                </div>
                <div class="detail-item">
                    <label>File Type:</label>
                    <span>${escapeHtml(file.file_type || 'Unknown')}</span>
                </div>
                <div class="detail-item">
                    <label>Category:</label>
                    <span class="category-tag" style="background-color: ${category.color}20; color: ${category.color};">
                        ${escapeHtml(category.name)}
                    </span>
                </div>
                <div class="detail-item">
                    <label>Folder:</label>
                    <span>${escapeHtml(file.folder_name || 'Unknown')}</span>
                </div>
                <div class="detail-item">
                    <label>Uploaded:</label>
                    <span>${formatDate(file.uploaded_at)}</span>
                </div>
                <div class="detail-item">
                    <label>Uploaded By:</label>
                    <span>${escapeHtml(file.uploader_name || 'Unknown User')}</span>
                </div>
                <div class="detail-item">
                    <label>Downloads:</label>
                    <span>${file.download_count || 0} times</span>
                </div>
                ${file.last_downloaded ? `
                    <div class="detail-item">
                        <label>Last Downloaded:</label>
                        <span>${formatDate(file.last_downloaded)}</span>
                    </div>
                ` : ''}
                ${file.description ? `
                    <div class="detail-item full-width">
                        <label>Description:</label>
                        <div class="description-content">${escapeHtml(file.description)}</div>
                    </div>
                ` : ''}
                ${file.tags ? `
                    <div class="detail-item full-width">
                        <label>Tags:</label>
                        <div class="tags-container">
                            ${file.tags.split(',').map(tag => 
                                `<span class="detail-tag">${escapeHtml(tag.trim())}</span>`
                            ).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
            
            <div class="modal-actions">
                <button class="btn-download" onclick="downloadFile(${file.id})">
                    <i class='bx bx-download'></i>
                    Download File
                </button>
                <button class="btn-share" onclick="shareFile(${file.id})">
                    <i class='bx bx-share'></i>
                    Share
                </button>
                <button class="btn-copy" onclick="copyFileLink(${file.id})">
                    <i class='bx bx-link'></i>
                    Copy Link
                </button>
            </div>
        </div>
    `;
}

function closeFileDetails() {
    const modal = document.getElementById('fileDetailsModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

function shareFile(fileId) {
    // Implementation for sharing file
    const shareData = {
        title: 'Shared File from CVSU Naic',
        text: 'Check out this file shared from CVSU Naic document management system.',
        url: `${window.location.origin}/shared/file/${fileId}`
    };
    
    if (navigator.share) {
        navigator.share(shareData)
            .then(() => showNotification('File shared successfully!', 'success'))
            .catch((error) => {
                console.error('Error sharing:', error);
                copyFileLink(fileId);
            });
    } else {
        copyFileLink(fileId);
    }
}

function copyFileLink(fileId) {
    const link = `${window.location.origin}/shared/file/${fileId}`;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(link)
            .then(() => showNotification('File link copied to clipboard!', 'success'))
            .catch(() => fallbackCopyToClipboard(link));
    } else {
        fallbackCopyToClipboard(link);
    }
}

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.top = '-1000px';
    textArea.style.left = '-1000px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Link copied to clipboard!', 'success');
    } catch (err) {
        showNotification('Failed to copy link', 'error');
    }
    
    document.body.removeChild(textArea);
}

function favoriteFile(fileId) {
    // Implementation for favoriting file
    fetch(`../../handlers/favorite_file.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ file_id: fileId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.is_favorite ? 'Added to favorites!' : 'Removed from favorites!', 'success');
            updateFavoriteButton(fileId, data.is_favorite);
        } else {
            showNotification(data.message || 'Failed to update favorite', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Failed to update favorite', 'error');
    });
}

function updateFavoriteButton(fileId, isFavorite) {
    const favoriteButtons = document.querySelectorAll(`[onclick="favoriteFile(${fileId})"]`);
    favoriteButtons.forEach(button => {
        const icon = button.querySelector('i');
        if (icon) {
            icon.className = isFavorite ? 'bx bxs-heart' : 'bx bx-heart';
        }
        button.title = isFavorite ? 'Remove from favorites' : 'Add to favorites';
    });
}

function updateDownloadCount(fileId) {
    const statItems = document.querySelectorAll(`[data-file-id="${fileId}"] .stat-item`);
    statItems.forEach(item => {
        const countSpan = item.querySelector('span');
        if (countSpan && item.querySelector('i.bx-download')) {
            const currentCount = parseInt(countSpan.textContent) || 0;
            countSpan.textContent = currentCount + 1;
        }
    });
}

// Utility functions
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function getFileIcon(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const iconMap = {
        'pdf': 'bxs-file-pdf',
        'doc': 'bxs-file-doc',
        'docx': 'bxs-file-doc',
        'xls': 'bxs-spreadsheet',
        'xlsx': 'bxs-spreadsheet',
        'ppt': 'bxs-file-blank',
        'pptx': 'bxs-file-blank',
        'jpg': 'bxs-file-image',
        'jpeg': 'bxs-file-image',
        'png': 'bxs-file-image',
        'gif': 'bxs-file-image',
        'txt': 'bxs-file-txt',
        'zip': 'bxs-file-archive',
        'rar': 'bxs-file-archive',
        'mp4': 'bxs-videos',
        'avi': 'bxs-videos',
        'mp3': 'bxs-music',
        'wav': 'bxs-music'
    };
    return iconMap[ext] || 'bxs-file';
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class='bx ${getNotificationIcon(type)}'></i>
            <span>${escapeHtml(message)}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class='bx bx-x'></i>
        </button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        max-width: 400px;
        animation: slideIn 0.3s ease;
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'bx-check-circle',
        'error': 'bx-error-circle',
        'warning': 'bx-error',
        'info': 'bx-info-circle'
    };
    return icons[type] || icons.info;
}

function getNotificationColor(type) {
    const colors = {
        'success': '#10b981',
        'error': '#ef4444',
        'warning': '#f59e0b',
        'info': '#3b82f6'
    };
    return colors[type] || colors.info;
}

// Add notification animations to document
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .notification-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 4px;
        color: white;
        cursor: pointer;
        padding: 4px;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s ease;
    }
    
    .notification-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    
    .file-details {
        font-family: 'Poppins', sans-serif;
    }
    
    .file-preview {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: #f8fafc;
        border-radius: 12px;
        margin-bottom: 24px;
    }
    
    .preview-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 32px;
    }
    
    .preview-info h4 {
        margin: 0 0 4px 0;
        font-size: 18px;
        color: #1f2937;
        word-break: break-word;
    }
    
    .file-path {
        margin: 0;
        font-size: 12px;
        color: #6b7280;
        font-family: 'Monaco', 'Consolas', monospace;
    }
    
    .details-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 12px;
        background: #f9fafb;
        border-radius: 8px;
        gap: 12px;
    }
    
    .detail-item.full-width {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .detail-item label {
        font-weight: 600;
        color: #374151;
        font-size: 13px;
        min-width: 100px;
        flex-shrink: 0;
    }
    
    .detail-item span {
        color: #6b7280;
        font-size: 13px;
        text-align: right;
        word-break: break-word;
    }
    
    .description-content {
        margin-top: 8px;
        padding: 12px;
        background: white;
        border-radius: 6px;
        color: #374151;
        line-height: 1.5;
        font-size: 13px;
    }
    
    .tags-container {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    
    .detail-tag {
        background: #e0f2fe;
        color: #0369a1;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .modal-actions {
        display: flex;
        gap: 12px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    
    .modal-actions button {
        flex: 1;
        padding: 12px 16px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-download {
        background: #10b981;
        color: white;
    }
    
    .btn-download:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .btn-share {
        background: #3b82f6;
        color: white;
    }
    
    .btn-share:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }
    
    .btn-copy {
        background: #6b7280;
        color: white;
    }
    
    .btn-copy:hover {
        background: #4b5563;
        transform: translateY(-1px);
    }
    
    .error-state {
        text-align: center;
        padding: 40px 20px;
    }
    
    .error-state h4 {
        color: #374151;
        margin: 8px 0;
    }
    
    .error-state p {
        color: #6b7280;
        margin: 0;
    }
`;

document.head.appendChild(style);