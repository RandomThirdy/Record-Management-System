<?php
// preview_file.php - Handler for file preview with security
require_once '../../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || !$currentUser['is_approved']) {
    header('Location: ../login.php');
    exit();
}

// Get user's department ID for security check
$userDepartmentId = null;
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $user = $stmt->fetch();
        if ($user && $user['department_id']) {
            $userDepartmentId = $user['department_id'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

if (!$userDepartmentId) {
    http_response_code(403);
    echo "Access denied: No department assigned";
    exit();
}

try {
    $fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$fileId) {
        http_response_code(400);
        echo "File ID is required";
        exit();
    }
    
    // Get file information with security check
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.file_name,
            f.original_name,
            f.file_path,
            f.file_size,
            f.file_type,
            f.mime_type,
            f.uploaded_by,
            f.uploaded_at,
            f.description,
            fo.department_id,
            fo.category,
            d.department_name,
            d.department_code,
            u.first_name,
            u.last_name
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        INNER JOIN departments d ON fo.department_id = d.id
        LEFT JOIN users u ON f.uploaded_by = u.id
        WHERE f.id = ? AND f.is_deleted = 0
    ");
    
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        echo "File not found";
        exit();
    }
    
    // SECURITY CHECK: User can only preview files from their department
    if ($file['department_id'] != $userDepartmentId) {
        http_response_code(403);
        echo "Access denied: Can only preview files from your department";
        exit();
    }
    
    // Build file path
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . ltrim($file['file_path'], '/');
    
    // Check if file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo "File not found on server";
        exit();
    }
    
    // Log the preview access
    logFileAccess($pdo, $fileId, $currentUser['id'], 'preview');
    
    $uploaderName = trim(($file['first_name'] ?? '') . ' ' . ($file['last_name'] ?? '')) ?: 'Unknown User';
    $fileExtension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
    
    // Determine if file can be previewed inline
    $previewableTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'html', 'htm'];
    $isPreviewable = in_array($fileExtension, $previewableTypes);
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($file['original_name'] ?: $file['file_name']); ?> - File Preview</title>
        <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Poppins', sans-serif;
                background: #f8fafc;
                color: #1f2937;
                line-height: 1.6;
            }
            
            .preview-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                background: white;
                min-height: 100vh;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            }
            
            .preview-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 0;
                border-bottom: 2px solid #e5e7eb;
                margin-bottom: 30px;
            }
            
            .file-info h1 {
                font-size: 24px;
                color: #1f2937;
                margin-bottom: 10px;
                word-break: break-word;
            }
            
            .file-meta {
                display: flex;
                gap: 20px;
                font-size: 14px;
                color: #6b7280;
            }
            
            .file-meta span {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .preview-actions {
                display: flex;
                gap: 10px;
            }
            
            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            
            .btn-primary {
                background: #3b82f6;
                color: white;
            }
            
            .btn-primary:hover {
                background: #2563eb;
                transform: translateY(-2px);
            }
            
            .btn-secondary {
                background: #6b7280;
                color: white;
            }
            
            .btn-secondary:hover {
                background: #4b5563;
            }
            
            .preview-content {
                background: #ffffff;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }
            
            .preview-iframe {
                width: 100%;
                height: 600px;
                border: none;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }
            
            .preview-image {
                max-width: 100%;
                height: auto;
                display: block;
                margin: 0 auto;
                border-radius: 8px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            }
            
            .no-preview {
                text-align: center;
                padding: 60px 20px;
                color: #6b7280;
            }
            
            .no-preview i {
                font-size: 64px;
                margin-bottom: 20px;
                color: #d1d5db;
            }
            
            .file-description {
                margin-top: 20px;
                padding: 15px;
                background: #f8fafc;
                border-radius: 8px;
                border-left: 4px solid #3b82f6;
            }
            
            .file-description h3 {
                margin-bottom: 10px;
                color: #1f2937;
            }
        </style>
    </head>
    <body>
        <div class="preview-container">
            <div class="preview-header">
                <div class="file-info">
                    <h1><?php echo htmlspecialchars($file['original_name'] ?: $file['file_name']); ?></h1>
                    <div class="file-meta">
                        <span><i class='bx bx-file'></i> <?php echo formatFileSize($file['file_size']); ?></span>
                        <span><i class='bx bx-calendar'></i> <?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?></span>
                        <span><i class='bx bx-user'></i> <?php echo htmlspecialchars($uploaderName); ?></span>
                        <span><i class='bx bx-building'></i> <?php echo htmlspecialchars($file['department_name']); ?></span>
                    </div>
                </div>
                <div class="preview-actions">
                    <form method="post" action="download_file.php" style="display: inline;">
                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-download'></i> Download
                        </button>
                    </form>
                    <button onclick="window.close()" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Close
                    </button>
                </div>
            </div>
            
            <div class="preview-content">
                <?php if ($isPreviewable): ?>
                    <?php if ($fileExtension === 'pdf'): ?>
                        <iframe src="<?php echo '/uploads/' . ltrim($file['file_path'], '/'); ?>#toolbar=1&navpanes=1&scrollbar=1" 
                                class="preview-iframe"
                                title="PDF Preview">
                        </iframe>
                    <?php elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="<?php echo '/uploads/' . ltrim($file['file_path'], '/'); ?>" 
                             alt="<?php echo htmlspecialchars($file['original_name'] ?: $file['file_name']); ?>"
                             class="preview-image">
                    <?php elseif (in_array($fileExtension, ['txt', 'html', 'htm'])): ?>
                        <iframe src="<?php echo '/uploads/' . ltrim($file['file_path'], '/'); ?>" 
                                class="preview-iframe"
                                title="Text Preview">
                        </iframe>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-preview">
                        <i class='bx bx-file'></i>
                        <h3>Preview Not Available</h3>
                        <p>This file type cannot be previewed in the browser.</p>
                        <p>Please download the file to view its contents.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($file['description'])): ?>
                <div class="file-description">
                    <h3><i class='bx bx-info-circle'></i> Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($file['description'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    
    <?php
    
} catch (Exception $e) {
    error_log("File preview error: " . $e->getMessage());
    http_response_code(500);
    echo "Error previewing file: " . $e->getMessage();
    exit();
}

function logFileAccess($pdo, $fileId, $userId, $action) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO file_access_log (file_id, user_id, action, ip_address, user_agent, accessed_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500);
        
        $stmt->execute([$fileId, $userId, $action, $ipAddress, $userAgent]);
        
    } catch (Exception $e) {
        error_log("Error logging file access: " . $e->getMessage());
    }
}

function formatFileSize($bytes) {
    if (!$bytes) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}
?>