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
if (!$currentUser || !$currentUser['is_approved']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not approved']);
    exit();
}

try {
    // Validate input
    if (!isset($_POST['department']) || !isset($_POST['semester']) || !isset($_FILES['files'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    $departmentId = (int)$_POST['department'];
    $semester = $_POST['semester'];
    $description = $_POST['description'] ?? '';
    $tags = isset($_POST['tags']) ? json_decode($_POST['tags'], true) : [];
    
    // Validate department exists
    $stmt = $pdo->prepare("SELECT id, department_name FROM departments WHERE id = ? AND is_active = 1");
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Invalid department']);
        exit();
    }

    // Validate semester
    if (!in_array($semester, ['first', 'second'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid semester']);
        exit();
    }

    // Create or get semester folder
    $folderId = getOrCreateSemesterFolder($pdo, $departmentId, $semester, $currentUser['id']);
    if (!$folderId) {
        echo json_encode(['success' => false, 'message' => 'Failed to create folder']);
        exit();
    }

    // Create upload directory if it doesn't exist
    $uploadDir = "../../uploads/departments/" . $departmentId . "/" . $semester . "/";
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit();
        }
    }

    $uploadedFiles = [];
    $errors = [];

    // Process each uploaded file
    foreach ($_FILES['files']['name'] as $key => $originalName) {
        if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['files']['tmp_name'][$key];
            $fileSize = $_FILES['files']['size'][$key];
            $fileType = $_FILES['files']['type'][$key];
            
            // Get file extension
            $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            // Generate unique filename
            $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($tmpName, $filePath)) {
                // Calculate file hash for duplicate detection
                $fileHash = hash_file('sha256', $filePath);
                
                // Insert file record into database
                $stmt = $pdo->prepare("
                    INSERT INTO files (
                        file_name, original_name, file_path, file_size, file_type, 
                        mime_type, file_extension, uploaded_by, folder_id, 
                        file_hash, tags, description, uploaded_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $relativePath = "uploads/departments/" . $departmentId . "/" . $semester . "/" . $fileName;
                $tagsJson = !empty($tags) ? json_encode($tags) : null;
                
                $stmt->execute([
                    $fileName,
                    $originalName,
                    $relativePath,
                    $fileSize,
                    $fileType,
                    $fileType,
                    $fileExtension,
                    $currentUser['id'],
                    $folderId,
                    $fileHash,
                    $tagsJson,
                    $description
                ]);
                
                $uploadedFiles[] = [
                    'id' => $pdo->lastInsertId(),
                    'name' => $originalName,
                    'size' => $fileSize
                ];

                // Update folder file count and size
                updateFolderStats($pdo, $folderId);
                
            } else {
                $errors[] = "Failed to upload: " . $originalName;
            }
        } else {
            $errors[] = "Upload error for: " . $originalName . " (Error code: " . $_FILES['files']['error'][$key] . ")";
        }
    }

    if (!empty($uploadedFiles)) {
        $response = [
            'success' => true,
            'message' => count($uploadedFiles) . ' files uploaded successfully',
            'uploaded_files' => $uploadedFiles,
            'departmentId' => $departmentId
        ];
        
        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No files were uploaded',
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during upload'
    ]);
}

// Helper function to update folder statistics
function updateFolderStats($pdo, $folderId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE folders SET 
                file_count = (SELECT COUNT(*) FROM files WHERE folder_id = ? AND is_deleted = 0),
                folder_size = (SELECT COALESCE(SUM(file_size), 0) FROM files WHERE folder_id = ? AND is_deleted = 0),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$folderId, $folderId, $folderId]);
    } catch (Exception $e) {
        error_log("Error updating folder stats: " . $e->getMessage());
    }
}

// Helper function to get or create semester folder (already defined in main file)
function getOrCreateSemesterFolder($pdo, $departmentId, $semester, $userId) {
    try {
        $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
        $academicYear = date('Y') . '-' . (date('Y') + 1);
        $folderName = $academicYear . ' - ' . $semesterName;
        
        // Check if folder exists
        $stmt = $pdo->prepare("
            SELECT id FROM folders 
            WHERE department_id = ? AND folder_name = ? AND is_deleted = 0
        ");
        $stmt->execute([$departmentId, $folderName]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            return $folder['id'];
        }
        
        // Create new folder
        $stmt = $pdo->prepare("
            INSERT INTO folders (
                folder_name, description, created_by, department_id, 
                folder_path, folder_level, created_at, file_count, folder_size
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 0, 0)
        ");
        
        $description = "Academic files for {$semesterName} {$academicYear}";
        $folderPath = "/departments/{$departmentId}/{$semester}";
        
        $stmt->execute([
            $folderName, 
            $description, 
            $userId, 
            $departmentId, 
            $folderPath, 
            1
        ]);
        
        return $pdo->lastInsertId();
        
    } catch(Exception $e) {
        error_log("Error creating semester folder: " . $e->getMessage());
        return false;
    }
}
?>