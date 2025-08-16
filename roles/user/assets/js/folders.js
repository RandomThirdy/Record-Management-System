// ODCI/roles/user/assets/js/folders.js

// Global variables
let selectedFiles = [];
let selectedTags = [];
const userDepartmentId = window.userDepartmentId || null;
const fileCategories = window.fileCategories || {};

// Modal functionality
function openUploadModal() {
    const modal = document.getElementById('uploadModal');
    const container = document.getElementById('modalContainer');
    modal.style.opacity = '1';
    modal.style.visibility = 'visible';
    setTimeout(() => {
        container.style.transform = 'scale(1) translateY(0)';
    }, 10);
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    const container = document.getElementById('modalContainer');
    container.style.transform = 'scale(0.9) translateY(20px)';
    setTimeout(() => {
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
        document.body.style.overflow = 'auto';
        resetForm();
    }, 300);
}

function handleFileSelect(files) {
    selectedFiles = Array.from(files);
    displayFilePreview();
}

function handleDrop(event) {
    event.preventDefault();
    const dropZone = event.target;
    dropZone.style.borderColor = '#10b981';
    dropZone.style.background = 'linear-gradient(135deg, #f0fdf4, #ecfdf5)';
    
    const files = Array.from(event.dataTransfer.files);
    selectedFiles = files;
    displayFilePreview();
}

function displayFilePreview() {
    const uploadPrompt = document.getElementById('uploadPrompt');
    const filePreview = document.getElementById('filePreview');
    
    if (selectedFiles.length > 0) {
        uploadPrompt.style.display = 'none';
        filePreview.style.display = 'block';
        
        let html = '<h4 style="margin: 0 0 16px 0; color: #065f46; font-size: 16px;">Selected Files:</h4>';
        
        selectedFiles.forEach((file, index) => {
            const fileIcon = getFileIcon(file.name);
            html += `
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: white; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e5e7eb;">
                    <div style="display: flex; align-items: center;">
                        <i class='bx ${fileIcon}' style="font-size: 24px; color: #10b981; margin-right: 12px;"></i>
                        <div>
                            <div style="font-weight: 500; color: #374151; font-size: 14px;">${file.name}</div>
                            <div style="font-size: 12px; color: #6b7280;">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" onclick="removeFile(${index})" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; padding: 6px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                        <i class='bx bx-x' style="font-size: 16px;"></i>
                    </button>
                </div>
            `;
        });
        
        filePreview.innerHTML = html;
    } else {
        uploadPrompt.style.display = 'block';
        filePreview.style.display = 'none';
    }
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    displayFilePreview();
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

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function addTag(tag) {
    if (!selectedTags.includes(tag)) {
        selectedTags.push(tag);
        displaySelectedTags();
        updateTagsInput();
    }
}

function addCustomTag() {
    const input = document.getElementById('customTag');
    const tag = input.value.trim();
    if (tag && !selectedTags.includes(tag)) {
        selectedTags.push(tag);
        displaySelectedTags();
        updateTagsInput();
        input.value = '';
    }
}

function displaySelectedTags() {
    const container = document.getElementById('selectedTags');
    let html = '';
    selectedTags.forEach((tag, index) => {
        html += `
            <div style="background: #10b981; color: white; border-radius: 16px; padding: 6px 12px; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                ${tag}
                <button type="button" onclick="removeTag(${index})" style="background: none; border: none; color: white; cursor: pointer; font-size: 14px; padding: 0; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center;">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        `;
    });
    container.innerHTML = html;
}

function removeTag(index) {
    selectedTags.splice(index, 1);
    displaySelectedTags();
    updateTagsInput();
}

function updateTagsInput() {
    const tagsInput = document.getElementById('tagsInput');
    if (tagsInput) {
        tagsInput.value = JSON.stringify(selectedTags);
    }
}

function resetForm() {
    selectedFiles = [];
    selectedTags = [];
    const form = document.getElementById('uploadForm');
    if (form) form.reset();
    
    const uploadPrompt = document.getElementById('uploadPrompt');
    const filePreview = document.getElementById('filePreview');
    const selectedTagsContainer = document.getElementById('selectedTags');
    const uploadProgress = document.getElementById('uploadProgress');
    
    if (uploadPrompt) uploadPrompt.style.display = 'block';
    if (filePreview) filePreview.style.display = 'none';
    if (selectedTagsContainer) selectedTagsContainer.innerHTML = '';
    if (uploadProgress) uploadProgress.style.display = 'none';
    updateTagsInput();
}

function simulateUpload() {
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    let progress = 0;
    
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 100) progress = 100;
        
        if (progressBar) progressBar.style.width = progress + '%';
        if (progressPercent) progressPercent.textContent = Math.round(progress) + '%';
        
        if (progress >= 100) {
            clearInterval(interval);
        }
    }, 200);
}

// Department tree functionality
function toggleDepartment(deptId) {
    const content = document.getElementById(`content-${deptId}`);
    const icon = document.getElementById(`icon-${deptId}`);
    const header = content ? content.previousElementSibling : null;
    
    if (!content || !icon) return;
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.classList.remove('rotated');
        if (header) header.classList.remove('active');
    } else {
        content.classList.add('show');
        icon.classList.add('rotated');
        if (header) header.classList.add('active');
        
        // Load category file counts for this department if not already loaded
        loadDepartmentCategories(deptId);
    }
}

// Category functionality
function toggleCategory(deptId, categoryKey) {
    const content = document.getElementById(`category-content-${deptId}-${categoryKey}`);
    const icon = document.getElementById(`icon-${deptId}-${categoryKey}`);
    const header = content ? content.previousElementSibling : null;
    
    if (!content || !icon) return;
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.classList.remove('rotated');
        if (header) header.classList.remove('active');
    } else {
        content.classList.add('show');
        icon.classList.add('rotated');
        if (header) header.classList.add('active');
        
        // Load files for this category if not already loaded
        loadCategoryFiles(deptId, categoryKey);
    }
}

function showCategorySemester(deptId, categoryKey, semester) {
    // Update tab active state
    const tabs = document.querySelectorAll(`#category-content-${deptId}-${categoryKey} .semester-tab`);
    tabs.forEach(tab => tab.classList.remove('active'));
    if (event && event.target) {
        event.target.classList.add('active');
    }
    
    // Show/hide semester content
    const firstSemester = document.getElementById(`files-${deptId}-${categoryKey}-first`);
    const secondSemester = document.getElementById(`files-${deptId}-${categoryKey}-second`);
    
    if (semester === 'first') {
        if (firstSemester) firstSemester.style.display = 'grid';
        if (secondSemester) secondSemester.style.display = 'none';
    } else {
        if (firstSemester) firstSemester.style.display = 'none';
        if (secondSemester) secondSemester.style.display = 'grid';
    }
}

function loadDepartmentCategories(deptId) {
    // Security check: Only load files for user's department
    if (deptId != userDepartmentId) {
        console.error('Access denied: Cannot load categories from different department');
        return;
    }
    
    // AJAX call to load category file counts
    fetch('handlers/category_counts.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            department_id: deptId,
            user_department_id: userDepartmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCategoryCounts(deptId, data.counts || {});
        } else {
            console.error('Error loading category counts:', data.message);
            if (data.message.includes('Access denied')) {
                alert('Access denied: You can only view files from your department.');
            }
        }
    })
    .catch(error => {
        console.error('Error loading category counts:', error);
    });
}

function updateCategoryCounts(deptId, counts) {
    Object.keys(fileCategories).forEach(categoryKey => {
        const countElement = document.querySelector(`[data-category="${categoryKey}"] .category-count`);
        if (countElement) {
            const count = counts[categoryKey] || 0;
            countElement.textContent = `${count} file${count !== 1 ? 's' : ''}`;
        }
    });
}

function loadCategoryFiles(deptId, categoryKey) {
    // Security check: Only load files for user's department
    if (deptId != userDepartmentId) {
        console.error('Access denied: Cannot load files from different department');
        return;
    }
    
    // AJAX call to load files from database for specific category
    fetch('handlers/category_files.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            department_id: deptId,
            category: categoryKey,
            user_department_id: userDepartmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderCategoryFiles(deptId, categoryKey, 'first', data.first_semester || []);
            renderCategoryFiles(deptId, categoryKey, 'second', data.second_semester || []);
        } else {
            console.error('Error loading category files:', data.message);
            if (data.message.includes('Access denied')) {
                alert('Access denied: You can only view files from your department.');
            }
        }
    })
    .catch(error => {
        console.error('Error loading category files:', error);
    });
}

function renderCategoryFiles(deptId, categoryKey, semester, files) {
    const container = document.getElementById(`files-${deptId}-${categoryKey}-${semester}`);
    
    if (!container) return;
    
    if (files.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class='bx bx-folder-open empty-icon'></i>
                <p>No files in ${semester === 'first' ? 'First' : 'Second'} Semester</p>
                <small>Files uploaded to this category and semester will appear here</small>
            </div>
        `;
        return;
    }
    
    let html = '';
    files.forEach(file => {
        const fileIcon = getFileIcon(file.file_name);
        const fileSize = formatFileSize(parseInt(file.file_size));
        const uploadDate = new Date(file.uploaded_at).toLocaleDateString();
        
        html += `
            <div class="file-card" onclick="downloadFile('${file.id}', '${file.file_name}')">
                <div class="file-header">
                    <div class="file-icon">
                        <i class='bx ${fileIcon}'></i>
                    </div>
                    <div class="file-info">
                        <div class="file-name" title="${file.original_name || file.file_name}">${file.original_name || file.file_name}</div>
                    </div>
                </div>
                <div class="file-meta">
                    <span>${fileSize}</span>
                    <span>${uploadDate}</span>
                </div>
                <div class="file-uploader">
                    <i class='bx bx-user'></i> ${file.uploader_name}
                </div>
                ${file.description ? `<div style="font-size: 11px; color: #6b7280; margin-top: 6px; padding: 6px; background: #f8fafc; border-radius: 4px; line-height: 1.3;">${file.description}</div>` : ''}
                ${file.tags && file.tags.length > 0 ? `
                    <div style="margin-top: 6px;">
                        ${file.tags.map(tag => `<span style="display: inline-block; background: #e0f2fe; color: #0369a1; font-size: 9px; padding: 2px 6px; border-radius: 10px; margin-right: 4px; margin-top: 2px;">${tag}</span>`).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function downloadFile(fileId, fileName) {
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = `handlers/download_file.php?id=${fileId}&dept_id=${userDepartmentId}`;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Sidebar functionality
function initializeSidebar() {
    const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');
    allSideMenu.forEach(item=> {
        const li = item.parentElement;
        item.addEventListener('click', function () {
            allSideMenu.forEach(i=> {
                i.parentElement.classList.remove('active');
            })
            li.classList.add('active');
        })
    });

    // Toggle sidebar
    const menuBar = document.querySelector('#content nav .bx.bx-menu');
    const sidebar = document.getElementById('sidebar');
    if (menuBar && sidebar) {
        menuBar.addEventListener('click', function () {
            sidebar.classList.toggle('hide');
        })
    }
}

// Search functionality
function initializeSearch() {
    const searchButton = document.querySelector('#content nav form .form-input button');
    const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
    const searchForm = document.querySelector('#content nav form');

    if (searchButton && searchButtonIcon && searchForm) {
        searchButton.addEventListener('click', function (e) {
            if(window.innerWidth < 576) {
                e.preventDefault();
                searchForm.classList.toggle('show');
                if(searchForm.classList.contains('show')) {
                    searchButtonIcon.classList.replace('bx-search', 'bx-x');
                } else {
                    searchButtonIcon.classList.replace('bx-x', 'bx-search');
                }
            }
        });
    }
}

// Dark mode toggle
function initializeDarkMode() {
    const switchMode = document.getElementById('switch-mode');
    if (switchMode) {
        switchMode.addEventListener('change', function () {
            if(this.checked) {
                document.body.classList.add('dark');
            } else {
                document.body.classList.remove('dark');
            }
        });
    }
}

// Responsive handling
function initializeResponsive() {
    const sidebar = document.getElementById('sidebar');
    const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
    const searchForm = document.querySelector('#content nav form');

    if(window.innerWidth < 768 && sidebar) {
        sidebar.classList.add('hide');
    } else if(window.innerWidth > 576 && searchButtonIcon && searchForm) {
        searchButtonIcon.classList.replace('bx-x', 'bx-search');
        searchForm.classList.remove('show');
    }

    window.addEventListener('resize', function () {
        if(this.innerWidth > 576 && searchButtonIcon && searchForm) {
            searchButtonIcon.classList.replace('bx-x', 'bx-search');
            searchForm.classList.remove('show');
        }
    });
}

// Department search functionality
function initializeDepartmentSearch() {
    const departmentSearch = document.getElementById('departmentSearch');
    if (departmentSearch) {
        departmentSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            // Search within categories and files
            document.querySelectorAll('.category-item').forEach(categoryItem => {
                const categoryName = categoryItem.querySelector('.category-name')?.textContent.toLowerCase() || '';
                let hasMatchingFiles = false;
                
                // Check if category name matches
                const categoryMatches = categoryName.includes(searchTerm);
                
                // Check files within this category
                categoryItem.querySelectorAll('.file-card').forEach(card => {
                    const fileName = card.querySelector('.file-name')?.textContent.toLowerCase() || '';
                    const uploader = card.querySelector('.file-uploader')?.textContent.toLowerCase() || '';
                    const description = card.querySelector('div[style*="background: #f8fafc"]')?.textContent.toLowerCase() || '';
                    
                    const fileMatches = fileName.includes(searchTerm) || uploader.includes(searchTerm) || description.includes(searchTerm);
                    
                    if (fileMatches) {
                        card.style.display = 'block';
                        hasMatchingFiles = true;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Show/hide category based on matches
                if (categoryMatches || hasMatchingFiles || searchTerm === '') {
                    categoryItem.style.display = 'block';
                } else {
                    categoryItem.style.display = 'none';
                }
            });
            
            // Also filter department items
            document.querySelectorAll('.department-item').forEach(item => {
                const deptName = item.querySelector('.department-name')?.textContent.toLowerCase() || '';
                const deptCode = item.querySelector('.department-code')?.textContent.toLowerCase() || '';
                
                if (deptName.includes(searchTerm) || deptCode.includes(searchTerm) || searchTerm === '') {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
}

// Upload form handler
function initializeUploadForm() {
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const category = document.querySelector('select[name="category"]')?.value;
            const semester = document.querySelector('input[name="semester"]:checked')?.value;
            const description = document.getElementById('fileDescription')?.value || '';
            
            if (!category || !semester || selectedFiles.length === 0) {
                alert('Please select a category, semester, and at least one file.');
                return;
            }
            
            // Show progress
            const uploadProgress = document.getElementById('uploadProgress');
            if (uploadProgress) {
                uploadProgress.style.display = 'block';
            }
            
            const formData = new FormData();
            formData.append('department', userDepartmentId); 
            formData.append('category', category);
            formData.append('semester', semester);
            formData.append('description', description);
            formData.append('tags', JSON.stringify(selectedTags));
            
            selectedFiles.forEach((file, index) => {
                formData.append('files[]', file);
            });
            
            fetch('handlers/upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Files uploaded successfully!');
                    closeUploadModal();
                    // Refresh the category counts and files
                    loadDepartmentCategories(userDepartmentId);
                    loadCategoryFiles(userDepartmentId, category);
                } else {
                    alert('Upload failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Upload failed. Please try again.');
            })
            .finally(() => {
                const uploadProgress = document.getElementById('uploadProgress');
                if (uploadProgress) {
                    uploadProgress.style.display = 'none';
                }
            });
            
            // Simulate progress for demo
            simulateUpload();
        });
    }
}

// Modal event listeners
function initializeModalEvents() {
    // Close modal when clicking outside
    const uploadModal = document.getElementById('uploadModal');
    if (uploadModal) {
        uploadModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });
    }

    // Handle keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeUploadModal();
        }
    });

    // File input change handler
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            handleFileSelect(this.files);
        });
    }

    // Drop zone events
    const dropZone = document.getElementById('dropZone');
    if (dropZone) {
        dropZone.addEventListener('click', function() {
            const fileInput = document.getElementById('fileInput');
            if (fileInput) fileInput.click();
        });

        dropZone.addEventListener('dragover', function(event) {
            event.preventDefault();
            this.style.borderColor = '#059669';
            this.style.background = '#d1fae5';
        });

        dropZone.addEventListener('dragleave', function() {
            this.style.borderColor = '#10b981';
            this.style.background = 'linear-gradient(135deg, #f0fdf4, #ecfdf5)';
        });

        dropZone.addEventListener('drop', handleDrop);
    }

    // Custom tag input
    const customTagInput = document.getElementById('customTag');
    if (customTagInput) {
        customTagInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addCustomTag();
            }
        });
    }
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeSidebar();
    initializeSearch();
    initializeDarkMode();
    initializeResponsive();
    initializeDepartmentSearch();
    initializeUploadForm();
    initializeModalEvents();

    // Auto-load user's department on page load
    if (userDepartmentId) {
        // Auto-expand user's department after a short delay
        setTimeout(() => {
            toggleDepartment(userDepartmentId);
        }, 500);
    }
});

// Make functions available globally for onclick handlers
window.openUploadModal = openUploadModal;
window.closeUploadModal = closeUploadModal;
window.handleDrop = handleDrop;
window.removeFile = removeFile;
window.addTag = addTag;
window.addCustomTag = addCustomTag;
window.removeTag = removeTag;
window.toggleDepartment = toggleDepartment;
window.toggleCategory = toggleCategory;
window.showCategorySemester = showCategorySemester;
window.downloadFile = downloadFile;