// Main JavaScript functionality for folders.php
// This file contains all interactive features and functionality

// Global variables
let selectedFiles = [];
let selectedTags = [];

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

// File handling functions
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
        'rar': 'bxs-file-archive'
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

// Tag handling functions
function addTag(tag) {
    if (!selectedTags.includes(tag)) {
        selectedTags.push(tag);
        displaySelectedTags();
    }
}

function addCustomTag() {
    const input = document.getElementById('customTag');
    const tag = input.value.trim();
    if (tag && !selectedTags.includes(tag)) {
        selectedTags.push(tag);
        displaySelectedTags();
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
}

function resetForm() {
    selectedFiles = [];
    selectedTags = [];
    document.getElementById('uploadForm').reset();
    document.getElementById('uploadPrompt').style.display = 'block';
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('selectedTags').innerHTML = '';
    document.getElementById('uploadProgress').style.display = 'none';
}

// Department tree functionality
function toggleDepartment(deptCode) {
    const content = document.getElementById(`content-${deptCode}`);
    const icon = document.getElementById(`icon-${deptCode}`);
    const header = content.previousElementSibling;
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.classList.remove('rotated');
        header.classList.remove('active');
    } else {
        content.classList.add('show');
        icon.classList.add('rotated');
        header.classList.add('active');
        
        // Load files for this department if not already loaded
        loadDepartmentFiles(deptCode);
    }
}

function showSemester(deptCode, semester) {
    // Update tab active state
    const tabs = document.querySelectorAll(`#content-${deptCode} .semester-tab`);
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    // Show/hide semester content
    const firstSemester = document.getElementById(`files-${deptCode}-first`);
    const secondSemester = document.getElementById(`files-${deptCode}-second`);
    
    if (semester === 'first') {
        firstSemester.style.display = 'grid';
        secondSemester.style.display = 'none';
    } else {
        firstSemester.style.display = 'none';
        secondSemester.style.display = 'grid';
    }
}

function loadDepartmentFiles(deptCode) {
    // AJAX call to load files from database
    fetch('get_department_files.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ department: deptCode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderFiles(deptCode, 'first', data.first_semester || []);
            renderFiles(deptCode, 'second', data.second_semester || []);
        }
    })
    .catch(error => {
        console.error('Error loading files:', error);
        // Fallback to sample data
        const sampleFiles = getSampleFiles();
        if (sampleFiles[deptCode]) {
            renderFiles(deptCode, 'first', sampleFiles[deptCode]['first'] || []);
            renderFiles(deptCode, 'second', sampleFiles[deptCode]['second'] || []);
        }
    });
}

function getSampleFiles() {
    return {
        'TED': {
            'first': [
                { name: 'Curriculum_Guide_2024.pdf', size: '2.4 MB', uploader: 'Dr. Maria Santos', date: '2024-01-15', type: 'pdf' },
                { name: 'Teaching_Methods.docx', size: '1.8 MB', uploader: 'Prof. Juan Cruz', date: '2024-01-20', type: 'docx' }
            ],
            'second': [
                { name: 'Assessment_Tools.xlsx', size: '956 KB', uploader: 'Dr. Ana Garcia', date: '2024-06-10', type: 'xlsx' }
            ]
        },
        'MD': {
            'first': [
                { name: 'Medical_Guidelines_2024.pdf', size: '3.2 MB', uploader: 'Dr. Roberto Lopez', date: '2024-02-01', type: 'pdf' }
            ],
            'second': []
        }
    };
}

function renderFiles(deptCode, semester, files) {
    const container = document.getElementById(`files-${deptCode}-${semester}`);
    
    if (files.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class='bx bx-folder-open empty-icon'></i>
                <p>No files in ${semester === 'first' ? 'First' : 'Second'} Semester</p>
                <small>Files uploaded to this semester will appear here</small>
            </div>
        `;
        return;
    }
    
    let html = '';
    files.forEach(file => {
        const fileIcon = getFileIconClass(file.type);
        html += `
            <div class="file-card" onclick="downloadFile('${file.name}')">
                <div class="file-header">
                    <div class="file-icon">
                        <i class='bx ${fileIcon}'></i>
                    </div>
                    <div class="file-info">
                        <div class="file-name">${file.name}</div>
                    </div>
                </div>
                <div class="file-meta">
                    <span>${file.size}</span>
                    <span>${formatDate(file.date)}</span>
                </div>
                <div class="file-uploader">
                    <i class='bx bx-user'></i> ${file.uploader}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function getFileIconClass(type) {
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
        'txt': 'bxs-file-txt',
        'zip': 'bxs-file-archive'
    };
    
    return iconMap[type] || 'bxs-file';
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
}

function downloadFile(filename) {
    // Implement file download functionality
    alert(`Downloading: ${filename}`);
}

function simulateUpload() {
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    let progress = 0;
    
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 100) progress = 100;
        
        progressBar.style.width = progress + '%';
        progressPercent.textContent = Math.round(progress) + '%';
        
        if (progress >= 100) {
            clearInterval(interval);
        }
    }, 200);
}

// Event Listeners and Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Form submission handler
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const department = document.getElementById('department').value;
        const semester = document.querySelector('input[name="semester"]:checked')?.value;
        const description = document.getElementById('fileDescription').value;
        
        if (!department || !semester || selectedFiles.length === 0) {
            alert('Please fill in all required fields and select at least one file.');
            return;
        }
        
        // Show progress
        document.getElementById('uploadProgress').style.display = 'block';
        
        // Create FormData for AJAX upload
        const formData = new FormData();
        formData.append('department', department);
        formData.append('semester', semester);
        formData.append('description', description);
        formData.append('tags', JSON.stringify(selectedTags));
        
        selectedFiles.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });
        
        // AJAX upload
        fetch('upload_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Files uploaded successfully!');
                closeUploadModal();
                // Refresh the department files if needed
                location.reload();
            } else {
                alert('Upload failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Upload failed. Please try again.');
        })
        .finally(() => {
            document.getElementById('uploadProgress').style.display = 'none';
        });
        
        // Simulate progress for demo
        simulateUpload();
    });

    // Close modal when clicking outside
    document.getElementById('uploadModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeUploadModal();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeUploadModal();
        }
    });

    // Sidebar functionality
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
    menuBar.addEventListener('click', function () {
        sidebar.classList.toggle('hide');
    })

    // Search functionality
    const searchButton = document.querySelector('#content nav form .form-input button');
    const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
    const searchForm = document.querySelector('#content nav form');

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
    })

    // Responsive handling
    if(window.innerWidth < 768) {
        sidebar.classList.add('hide');
    } else if(window.innerWidth > 576) {
        searchButtonIcon.classList.replace('bx-x', 'bx-search');
        searchForm.classList.remove('show');
    }

    window.addEventListener('resize', function () {
        if(this.innerWidth > 576) {
            searchButtonIcon.classList.replace('bx-x', 'bx-search');
            searchForm.classList.remove('show');
        }
    })

    // Dark mode toggle
    const switchMode = document.getElementById('switch-mode');
    switchMode.addEventListener('change', function () {
        if(this.checked) {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }
    })

    // Department search functionality
    document.getElementById('departmentSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        
        // Filter department items based on search
        document.querySelectorAll('.department-item').forEach(item => {
            const deptName = item.querySelector('.department-name').textContent.toLowerCase();
            const deptCode = item.querySelector('.department-code').textContent.toLowerCase();
            
            if (deptName.includes(searchTerm) || deptCode.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
});