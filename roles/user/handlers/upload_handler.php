<?php
// ODCI/roles/user/handlers/upload_handler.php
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
    // Validate input
    if (!isset($_POST['department']) || !isset($_POST['category']) || !isset($_POST['semester']) || !isset($_FILES['files'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    $departmentId = (int)$_POST['department'];
    $category = $_POST['category'];
    $semester = $_POST['semester'];
    
    
    // Improved academic year handling
    if (isset($_POST['academic_year']) && !empty(trim($_POST['academic_year']))) {
    $academicYear = trim($_POST['academic_year']);
} else {
    // Only use fallback logic if academic_year is truly empty or not provided
    $currentYear = date('Y');
    $currentMonth = date('n');
    
    // If we're in August or later, it's the new academic year
    // Otherwise, we're still in the previous academic year
    if ($currentMonth >= 8) {
        $academicYear = $currentYear . '-' . ($currentYear + 1);
    } else {
        $academicYear = ($currentYear - 1) . '-' . $currentYear;
    }
}

    $description = $_POST['description'] ?? '';
    $tags = isset($_POST['tags']) ? json_decode($_POST['tags'], true) : [];

    // Validate academic year format
    if (!preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
        echo json_encode(['success' => false, 'message' => 'Invalid academic year format']);
        exit();
    }

    // SECURITY CHECK: User can only upload to their own department
    if ($departmentId != $userDepartmentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Can only upload to your department']);
        exit();
    }
    
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

    // Validate category
    $validCategories = [
        'ipcr_accomplishment', 'ipcr_target', 'workload', 'course_syllabus',
        'syllabus_acceptance', 'exam', 'tos', 'class_record', 'grading_sheet',
        'attendance_sheet', 'stakeholder_feedback', 'consultation', 'lecture',
        'activities', 'exam_acknowledgement', 'consultation_log'
    ];
    
    if (!in_array($category, $validCategories)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        exit();
    }

    // FIXED: Pass academicYear to the folder creation function
    $folderId = getOrCreateCategoryFolder($pdo, $departmentId, $category, $semester, $academicYear, $currentUser['id'], $userDepartmentId);
    if (!$folderId) {
        echo json_encode(['success' => false, 'message' => 'Failed to create category folder']);
        exit();
    }

    // Create upload directory with academic year in path
    $uploadDir = "../../uploads/departments/" . $departmentId . "/" . $category . "/" . $semester . "/" . $academicYear . "/";
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
            
            // Validate file size (50MB max)
            if ($fileSize > 50 * 1024 * 1024) {
                $errors[] = "File too large: " . $originalName . " (Max 50MB)";
                continue;
            }
            
            // Get file extension
            $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            // Generate unique filename
            $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($tmpName, $filePath)) {
                // Calculate file hash for duplicate detection
                $fileHash = hash_file('sha256', $filePath);
                
                // Check for duplicates - also check academic year
                $stmt = $pdo->prepare("
                    SELECT f.id, f.original_name 
                    FROM files f 
                    INNER JOIN folders fo ON f.folder_id = fo.id
                    WHERE f.file_hash = ? AND fo.department_id = ? AND fo.category = ? 
                    AND f.academic_year = ? AND f.semester = ? AND f.is_deleted = 0
                ");
                $stmt->execute([$fileHash, $departmentId, $category, $academicYear, $semester]);
                $duplicate = $stmt->fetch();
                
                if ($duplicate) {
                    unlink($filePath); // Remove the uploaded duplicate
                    $errors[] = "Duplicate file: " . $originalName . " (already exists as " . $duplicate['original_name'] . ")";
                    continue;
                }
                
                // Detect MIME type properly
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                $mimeType = $detectedMimeType ?: $fileType;
                
                // Insert file record into database
                $stmt = $pdo->prepare("
                    INSERT INTO files (
                        file_name, original_name, file_path, file_size, file_type, 
                        mime_type, file_extension, uploaded_by, folder_id, 
                        file_hash, tags, description, academic_year, semester, uploaded_at, download_count
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)
                ");
                
                $relativePath = "uploads/departments/" . $departmentId . "/" . $category . "/" . $semester . "/" . $academicYear . "/" . $fileName;
                $tagsJson = !empty($tags) ? json_encode($tags) : null;
                
                $stmt->execute([
                    $fileName,
                    $originalName,
                    $relativePath,
                    $fileSize,
                    $fileType,
                    $mimeType,
                    $fileExtension,
                    $currentUser['id'],
                    $folderId,
                    $fileHash,
                    $tagsJson,
                    $description,
                    $academicYear,
                    $semester
                ]);
                
                $uploadedFiles[] = [
                    'id' => $pdo->lastInsertId(),
                    'name' => $originalName,
                    'size' => $fileSize,
                    'category' => $category,
                    'semester' => $semester,
                    'academic_year' => $academicYear
                ];

                // Update folder file count and size
                updateFolderStats($pdo, $folderId);
                
            } else {
                $errors[] = "Failed to upload: " . $originalName;
            }
        } else {
            $uploadErrorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory',
                UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
            ];
            
            $errorCode = $_FILES['files']['error'][$key];
            $errorMessage = $uploadErrorMessages[$errorCode] ?? 'Unknown upload error';
            $errors[] = "Upload error for: " . $originalName . " (" . $errorMessage . ")";
        }
    }

    if (!empty($uploadedFiles)) {
        $response = [
            'success' => true,
            'message' => count($uploadedFiles) . ' files uploaded successfully to ' . ucfirst(str_replace('_', ' ', $category)) . ' - ' . ucfirst($semester) . ' Semester (' . $academicYear . ')',
            'uploaded_files' => $uploadedFiles,
            'departmentId' => $departmentId,
            'category' => $category,
            'semester' => $semester,
            'academic_year' => $academicYear
        ];
        
        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No files were uploaded successfully',
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during upload: ' . $e->getMessage()
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

// FIXED: Helper function to get or create category folder - now accepts academic year parameter
function getOrCreateCategoryFolder($pdo, $departmentId, $category, $semester, $academicYear, $userId, $userDepartmentId) {
    // Security check: ensure user can only create folders in their own department
    if ($departmentId != $userDepartmentId) {
        throw new Exception("Access denied: Cannot create folder in different department");
    }
    
    try {
        $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
        $folderName = $academicYear . ' - ' . $semesterName;
        
        // Check if folder exists for this category, semester, and academic year
        $stmt = $pdo->prepare("
            SELECT id FROM folders 
            WHERE department_id = ? 
            AND folder_name = ? 
            AND category = ? 
            AND is_deleted = 0
        ");
        $stmt->execute([$departmentId, $folderName, $category]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            return $folder['id'];
        }
        
        // Create new category folder
        $stmt = $pdo->prepare("
            INSERT INTO folders (
                folder_name, description, created_by, department_id, category, 
                folder_path, folder_level, created_at, file_count, folder_size
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0)
        ");
        
        $categoryNames = [
            'ipcr_accomplishment' => 'IPCR Accomplishment',
            'ipcr_target' => 'IPCR Target',
            'workload' => 'Workload',
            'course_syllabus' => 'Course Syllabus',
            'syllabus_acceptance' => 'Course Syllabus Acceptance Form',
            'exam' => 'Exam',
            'tos' => 'TOS',
            'class_record' => 'Class Record',
            'grading_sheet' => 'Grading Sheet',
            'attendance_sheet' => 'Attendance Sheet',
            'stakeholder_feedback' => 'Stakeholder\'s Feedback Form w/ Summary',
            'consultation' => 'Consultation',
            'lecture' => 'Lecture',
            'activities' => 'Activities',
            'exam_acknowledgement' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
            'consultation_log' => 'Consultation Log Sheet Form'
        ];
        
        $categoryDisplayName = $categoryNames[$category] ?? ucfirst(str_replace('_', ' ', $category));
        $description = "{$categoryDisplayName} files for {$semesterName} {$academicYear}";
        $folderPath = "/departments/{$departmentId}/{$category}/{$semester}/{$academicYear}";
        
        $stmt->execute([
            $folderName, 
            $description, 
            $userId, 
            $departmentId, 
            $category,
            $folderPath, 
            2
        ]);
        
        return $pdo->lastInsertId();
        
    } catch(Exception $e) {
        error_log("Error creating category folder: " . $e->getMessage());
        return false;
    }
}
?>