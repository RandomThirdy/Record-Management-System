<?php
require_once '../../includes/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['department_id'])) {
        echo json_encode(['success' => false, 'message' => 'Department ID required']);
        exit();
    }
    
    $departmentId = (int)$input['department_id'];
    
    // Verify department exists and user has access
    $stmt = $pdo->prepare("SELECT id, department_name FROM departments WHERE id = ? AND is_active = 1");
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Department not found']);
        exit();
    }

    // Get files for first semester
    $firstSemesterFiles = getDepartmentFilesBySemester($pdo, $departmentId, 'first');
    
    // Get files for second semester
    $secondSemesterFiles = getDepartmentFilesBySemester($pdo, $departmentId, 'second');
    
    echo json_encode([
        'success' => true,
        'department' => $department,
        'first_semester' => $firstSemesterFiles,
        'second_semester' => $secondSemesterFiles
    ]);

} catch (Exception $e) {
    error_log("Error fetching department files: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching files'
    ]);
}

function getDepartmentFilesBySemester($pdo, $departmentId, $semester) {
    try {
        $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
        $academicYear = date('Y') . '-' . (date('Y') + 1);
        
        $query = "
            SELECT 
                f.id,
                f.file_name,
                f.original_name,
                f.file_path,
                f.file_size,
                f.file_type,
                f.file_extension,
                f.uploaded_at,
                f.download_count,
                f.tags,
                f.description,
                fo.folder_name,
                u.name as uploader_name,
                COALESCE(u.name, CONCAT(u.misurname, ', ', u.surname)) as full_uploader_name
            FROM files f
            JOIN folders fo ON f.folder_id = fo.id
            JOIN users u ON f.uploaded_by = u.id
            WHERE fo.department_id = ? 
                AND f.is_deleted = 0 
                AND fo.is_deleted = 0
                AND (
                    fo.folder_name LIKE ? 
                    OR fo.folder_name LIKE ?
                    OR fo.folder_path LIKE ?
                )
            ORDER BY f.uploaded_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $departmentId,
            "%{$semesterName}%",
            "%{$semester}%",
            "%/{$semester}%"
        ]);
        
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process files to include additional information
        foreach ($files as &$file) {
            // Parse tags if they exist
            if ($file['tags']) {
                $file['tags_array'] = json_decode($file['tags'], true);
            } else {
                $file['tags_array'] = [];
            }
            
            // Format file size
            $file['formatted_size'] = formatFileSize($file['file_size']);
            
            // Format upload date
            $file['formatted_date'] = date('M d, Y', strtotime($file['uploaded_at']));
            $file['formatted_datetime'] = date('M d, Y H:i', strtotime($file['uploaded_at']));
            
            // Determine if file is accessible by current user
            $file['can_download'] = true; // Add permission logic here if needed
            
            // Get file icon based on extension
            $file['icon_class'] = getFileIconClass($file['file_extension']);
        }
        
        return $files;
        
    } catch (Exception $e) {
        error_log("Error fetching semester files: " . $e->getMessage());
        return [];
    }
}

function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function getFileIconClass($extension) {
    $ext = strtolower($extension);
    
    $iconMap = [
        'pdf' => 'bxs-file-pdf',
        'doc' => 'bxs-file-doc',
        'docx' => 'bxs-file-doc',
        'xls' => 'bxs-spreadsheet',
        'xlsx' => 'bxs-spreadsheet',
        'ppt' => 'bxs-file-blank',
        'pptx' => 'bxs-file-blank',
        'jpg' => 'bxs-file-image',
        'jpeg' => 'bxs-file-image',
        'png' => 'bxs-file-image',
        'gif' => 'bxs-file-image',
        'svg' => 'bxs-file-image',
        'txt' => 'bxs-file-txt',
        'rtf' => 'bxs-file-txt',
        'zip' => 'bxs-file-archive',
        'rar' => 'bxs-file-archive',
        '7z' => 'bxs-file-archive',
        'tar' => 'bxs-file-archive',
        'gz' => 'bxs-file-archive',
        'mp4' => 'bxs-videos',
        'avi' => 'bxs-videos',
        'mov' => 'bxs-videos',
        'wmv' => 'bxs-videos',
        'flv' => 'bxs-videos',
        'mp3' => 'bxs-music',
        'wav' => 'bxs-music',
        'flac' => 'bxs-music',
        'aac' => 'bxs-music',
        'ogg' => 'bxs-music'
    ];
    
    return isset($iconMap[$ext]) ? $iconMap[$ext] : 'bxs-file';
}
?>