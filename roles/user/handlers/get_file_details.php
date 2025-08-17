<?php
// ODCI/roles/user/handlers/get_file_details.php
require_once '../../../includes/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || !$currentUser['is_approved']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not approved']);
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
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

try {
    // Validate file ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
        exit();
    }
    
    $fileId = (int)$_GET['id'];
    
    // Get comprehensive file details with security check
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.file_name,
            f.original_name,
            f.file_path,
            f.file_size,
            f.file_type,
            f.mime_type,
            f.file_extension,
            f.uploaded_at,
            f.updated_at,
            f.description,
            f.tags,
            f.download_count,
            f.last_downloaded,
            f.last_downloaded_by,
            f.is_favorite,
            f.thumbnail_path,
            f.file_hash,
            f.version,
            f.parent_file_id,
            f.expiry_date,
            fo.id as folder_id,
            fo.folder_name,
            fo.category,
            fo.folder_path as folder_path_db,
            fo.department_id,
            d.department_name,
            d.department_code,
            uploader.name as uploader_name,
            uploader.misurname as uploader_surname,
            uploader.username as uploader_username,
            uploader.employee_id as uploader_employee_id,
            uploader.position as uploader_position,
            last_downloader.name as last_downloader_name,
            last_downloader.misurname as last_downloader_surname,
            last_downloader.username as last_downloader_username
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        INNER JOIN departments d ON fo.department_id = d.id
        LEFT JOIN users uploader ON f.uploaded_by = uploader.id
        LEFT JOIN users last_downloader ON f.last_downloaded_by = last_downloader.id
        WHERE f.id = ? 
        AND f.is_deleted = 0 
        AND fo.is_deleted = 0
        AND fo.department_id = ?
    ");
    
    $stmt->execute([$fileId, $userDepartmentId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found or access denied']);
        exit();
    }
    
    // Process and format the file data
    $fileDetails = [
        'id' => $file['id'],
        'file_name' => $file['file_name'],
        'original_name' => $file['original_name'] ?: $file['file_name'],
        'file_path' => $file['file_path'],
        'file_size' => (int)$file['file_size'],
        'file_type' => $file['file_type'],
        'mime_type' => $file['mime_type'],
        'file_extension' => $file['file_extension'] ?: pathinfo($file['file_name'], PATHINFO_EXTENSION),
        'uploaded_at' => $file['uploaded_at'],
        'updated_at' => $file['updated_at'],
        'description' => $file['description'],
        'download_count' => (int)($file['download_count'] ?: 0),
        'last_downloaded' => $file['last_downloaded'],
        'is_favorite' => (bool)$file['is_favorite'],
        'thumbnail_path' => $file['thumbnail_path'],
        'file_hash' => $file['file_hash'],
        'version' => (int)($file['version'] ?: 1),
        'parent_file_id' => $file['parent_file_id'],
        'expiry_date' => $file['expiry_date'],
        'folder_id' => $file['folder_id'],
        'folder_name' => $file['folder_name'],
        'category' => $file['category'],
        'folder_path' => $file['folder_path_db'],
        'department_name' => $file['department_name'],
        'department_code' => $file['department_code']
    ];
    
    // Format uploader information
    if ($file['uploader_name']) {
        $uploaderFullName = $file['uploader_name'];
        if ($file['uploader_surname']) {
            $uploaderFullName .= ' ' . $file['uploader_surname'];
        }
        $fileDetails['uploader_name'] = $uploaderFullName;
        $fileDetails['uploader_employee_id'] = $file['uploader_employee_id'];
        $fileDetails['uploader_position'] = $file['uploader_position'];
    } else {
        $fileDetails['uploader_name'] = $file['uploader_username'] ?: 'Unknown User';
        $fileDetails['uploader_employee_id'] = null;
        $fileDetails['uploader_position'] = null;
    }
    
    // Format last downloader information
    if ($file['last_downloader_name']) {
        $lastDownloaderFullName = $file['last_downloader_name'];
        if ($file['last_downloader_surname']) {
            $lastDownloaderFullName .= ' ' . $file['last_downloader_surname'];
        }
        $fileDetails['last_downloader_name'] = $lastDownloaderFullName;
    } else {
        $fileDetails['last_downloader_name'] = $file['last_downloader_username'];
    }
    
    // Process tags
    if (!empty($file['tags'])) {
        $tagsData = json_decode($file['tags'], true);
        if (is_array($tagsData)) {
            $fileDetails['tags'] = $tagsData;
        } else {
            // Handle comma-separated tags as fallback
            $fileDetails['tags'] = array_map('trim', explode(',', $file['tags']));
        }
    } else {
        $fileDetails['tags'] = [];
    }
    
    // Get additional file statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_downloads_today,
            COUNT(DISTINCT user_id) as unique_downloaders
        FROM file_downloads 
        WHERE file_id = ? 
        AND DATE(downloaded_at) = CURDATE()
    ");
    $statsStmt->execute([$fileId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $fileDetails['downloads_today'] = (int)$stats['total_downloads_today'];
        $fileDetails['unique_downloaders'] = (int)$stats['unique_downloaders'];
    } else {
        $fileDetails['downloads_today'] = 0;
        $fileDetails['unique_downloaders'] = 0;
    }
    
    // Check if file physically exists
    $physicalPath = '../../' . $file['file_path'];
    $fileDetails['file_exists'] = file_exists($physicalPath);
    
    // Get file creation and modification times from filesystem
    if ($fileDetails['file_exists']) {
        $fileDetails['file_created'] = date('Y-m-d H:i:s', filectime($physicalPath));
        $fileDetails['file_modified'] = date('Y-m-d H:i:s', filemtime($physicalPath));
    }
    
    // Get related files (same category and folder)
    $relatedStmt = $pdo->prepare("
        SELECT 
            f2.id,
            f2.file_name,
            f2.original_name,
            f2.file_size,
            f2.uploaded_at,
            f2.download_count
        FROM files f2
        INNER JOIN folders fo2 ON f2.folder_id = fo2.id
        WHERE fo2.category = ? 
        AND fo2.department_id = ?
        AND f2.id != ?
        AND f2.is_deleted = 0
        ORDER BY f2.uploaded_at DESC
        LIMIT 5
    ");
    $relatedStmt->execute([$file['category'], $userDepartmentId, $fileId]);
    $relatedFiles = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fileDetails['related_files'] = array_map(function($relatedFile) {
        return [
            'id' => $relatedFile['id'],
            'name' => $relatedFile['original_name'] ?: $relatedFile['file_name'],
            'size' => (int)$relatedFile['file_size'],
            'uploaded_at' => $relatedFile['uploaded_at'],
            'download_count' => (int)($relatedFile['download_count'] ?: 0)
        ];
    }, $relatedFiles);
    
    // Get recent download activity (last 5 downloads)
    $activityStmt = $pdo->prepare("
        SELECT 
            fd.downloaded_at,
            u.name as downloader_name,
            u.misurname as downloader_surname,
            u.username as downloader_username
        FROM file_downloads fd
        LEFT JOIN users u ON fd.user_id = u.id
        WHERE fd.file_id = ?
        ORDER BY fd.downloaded_at DESC
        LIMIT 5
    ");
    $activityStmt->execute([$fileId]);
    $recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fileDetails['recent_downloads'] = array_map(function($activity) {
        $downloaderName = 'Unknown User';
        if ($activity['downloader_name']) {
            $downloaderName = $activity['downloader_name'];
            if ($activity['downloader_surname']) {
                $downloaderName .= ' ' . $activity['downloader_surname'];
            }
        } elseif ($activity['downloader_username']) {
            $downloaderName = $activity['downloader_username'];
        }
        
        return [
            'downloaded_at' => $activity['downloaded_at'],
            'downloader_name' => $downloaderName
        ];
    }, $recentActivity);
    
    echo json_encode([
        'success' => true,
        'file' => $fileDetails
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching file details for file ID {$fileId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching file details'
    ]);
}
?>