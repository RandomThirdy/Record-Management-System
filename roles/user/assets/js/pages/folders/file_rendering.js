// File rendering and download functionality
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
    link.href = `../handlers/download_file.php?id=${fileId}&dept_id=${userDepartmentId}`;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}