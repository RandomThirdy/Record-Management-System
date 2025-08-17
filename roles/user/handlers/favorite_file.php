<?php
// ODCI/roles/user/handlers/favorite_file.php
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
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['file_id']) || !is_numeric($input['file_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
        exit();
    }
    
    $fileId = (int)$input['file_id'];
    
    // Verify file exists and user has access (same department)
    $stmt = $pdo->prepare("
        SELECT f.id, f.is_favorite, fo.department_id
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        WHERE f.id = ? AND f.is_deleted = 0 AND fo.is_deleted = 0
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit();
    }
    
    // Security check: ensure file belongs to user's department
    if ($file['department_id'] != $userDepartmentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    // Toggle favorite status
    $newFavoriteStatus = !$file['is_favorite'];
    
    $updateStmt = $pdo->prepare("
        UPDATE files 
        SET is_favorite = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$newFavoriteStatus ? 1 : 0, $fileId]);
    
    if ($updateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'is_favorite' => $newFavoriteStatus,
            'message' => $newFavoriteStatus ? 'Added to favorites' : 'Removed from favorites'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update favorite status'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error toggling favorite for file ID {$fileId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating favorite status'
    ]);
}
?>