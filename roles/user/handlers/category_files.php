<?php
// ODCI/roles/user/handlers/category_files.php
require_once '../../../includes/config.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Get user's department ID
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

// Ensure user has a department
if (!$userDepartmentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['department_id']) || !isset($input['category'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Department ID and category required']);
    exit();
}

$requestedDepartmentId = intval($input['department_id']);
$category = $input['category'];
$requestedUserDepartmentId = intval($input['user_department_id'] ?? 0);

// SECURITY CHECK: User can only access files from their own department
if ($requestedDepartmentId != $userDepartmentId || $requestedUserDepartmentId != $userDepartmentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Can only view files from your department']);
    exit();
}

try {
    // Get files for first semester
    $firstSemesterFiles = getFilesByCategorySemester($pdo, $requestedDepartmentId, $category, 'first');
    
    // Get files for second semester  
    $secondSemesterFiles = getFilesByCategorySemester($pdo, $requestedDepartmentId, $category, 'second');
    
    echo json_encode([
        'success' => true,
        'first_semester' => $firstSemesterFiles,
        'second_semester' => $secondSemesterFiles
    ]);

} catch(Exception $e) {
    error_log("Error fetching category files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

function getFilesByCategorySemester($pdo, $departmentId, $category, $semester) {
    try {
        $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
        $academicYear = date('Y') . '-' . (date('Y') + 1);
        $folderName = $academicYear . ' - ' . $semesterName;
        
        $stmt = $pdo->prepare("
            SELECT 
                f.id,
                f.file_name,
                f.original_name,
                f.file_path,
                f.file_size,
                f.file_type,
                f.uploaded_at,
                f.description,
                f.tags,
                f.download_count,
                COALESCE(CONCAT(u.name, ' ', COALESCE(u.surname, '')), u.username, 'Unknown User') as uploader_name
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            LEFT JOIN users u ON f.uploaded_by = u.id
            WHERE fo.department_id = ? 
            AND fo.category = ?
            AND (fo.folder_name = ? OR fo.folder_name LIKE ?)
            AND f.is_deleted = 0 
            AND fo.is_deleted = 0
            ORDER BY f.uploaded_at DESC
        ");
        
        $stmt->execute([
            $departmentId, 
            $category, 
            $folderName, 
            '%' . $semesterName . '%'
        ]);
        
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process files to ensure proper data format
        foreach ($files as &$file) {
            // Parse tags if they exist
            if (!empty($file['tags'])) {
                $decoded = json_decode($file['tags'], true);
                $file['tags'] = is_array($decoded) ? $decoded : [];
            } else {
                $file['tags'] = [];
            }
            
            // Ensure uploader name is not empty
            if (empty($file['uploader_name'])) {
                $file['uploader_name'] = 'Unknown User';
            }
            
            // Ensure download count is set
            $file['download_count'] = intval($file['download_count'] ?? 0);
        }
        
        return $files;
        
    } catch (Exception $e) {
        error_log("Error getting files for category {$category}, semester {$semester}: " . $e->getMessage());
        return [];
    }
}
?>