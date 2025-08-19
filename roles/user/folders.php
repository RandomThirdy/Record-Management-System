<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);

if (!$currentUser) {
    // User not found, logout
    header('Location: logout.php');
    exit();
}

// Check if user is approved
if (!$currentUser['is_approved']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_not_approved');
    exit();
}

// Get user's department ID - CRITICAL for filtering
$userDepartmentId = null;

// First, try to get department_id from currentUser
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
    // If not available, fetch from database
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.department_code, d.department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        $userDept = $stmt->fetch();
        
        if ($userDept && $userDept['department_id']) {
            $userDepartmentId = $userDept['department_id'];
            $currentUser['department_id'] = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

// If user has no department assigned, restrict access
if (!$userDepartmentId) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=no_department_assigned');
    exit();
}

// Define file categories
$fileCategories = [
    'ipcr_accomplishment' => [
        'name' => 'IPCR Accomplishment',
        'icon' => 'bxs-trophy',
        'color' => '#f59e0b'
    ],
    'ipcr_target' => [
        'name' => 'IPCR Target',
        'icon' => 'bxs-bullseye',
        'color' => '#ef4444'
    ],
    'workload' => [
        'name' => 'Workload',
        'icon' => 'bxs-briefcase',
        'color' => '#8b5cf6'
    ],
    'course_syllabus' => [
        'name' => 'Course Syllabus',
        'icon' => 'bxs-book-content',
        'color' => '#06b6d4'
    ],
    'syllabus_acceptance' => [
        'name' => 'Course Syllabus Acceptance Form',
        'icon' => 'bxs-check-circle',
        'color' => '#10b981'
    ],
    'exam' => [
        'name' => 'Exam',
        'icon' => 'bxs-file-doc',
        'color' => '#dc2626'
    ],
    'tos' => [
        'name' => 'TOS',
        'icon' => 'bxs-spreadsheet',
        'color' => '#059669'
    ],
    'class_record' => [
        'name' => 'Class Record',
        'icon' => 'bxs-data',
        'color' => '#7c3aed'
    ],
    'grading_sheet' => [
        'name' => 'Grading Sheet',
        'icon' => 'bxs-calculator',
        'color' => '#ea580c'
    ],
    'attendance_sheet' => [
        'name' => 'Attendance Sheet',
        'icon' => 'bxs-user-check',
        'color' => '#0284c7'
    ],
    'stakeholder_feedback' => [
        'name' => 'Stakeholder\'s Feedback Form w/ Summary',
        'icon' => 'bxs-comment-detail',
        'color' => '#9333ea'
    ],
    'consultation' => [
        'name' => 'Consultation',
        'icon' => 'bxs-chat',
        'color' => '#0d9488'
    ],
    'lecture' => [
        'name' => 'Lecture',
        'icon' => 'bxs-chalkboard',
        'color' => '#7c2d12'
    ],
    'activities' => [
        'name' => 'Activities',
        'icon' => 'bxs-game',
        'color' => '#be185d'
    ],
    'exam_acknowledgement' => [
        'name' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
        'icon' => 'bxs-receipt',
        'color' => '#1e40af'
    ],
    'consultation_log' => [
        'name' => 'Consultation Log Sheet Form',
        'icon' => 'bxs-notepad',
        'color' => '#374151'
    ]
];

// Get departments from database - ONLY user's department
function getUserDepartment($pdo, $departmentId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ? AND is_active = 1");
        $stmt->execute([$departmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching user department: " . $e->getMessage());
        return null;
    }
}

// Get all departments for upload modal (optional - you might want to restrict this too)
function getAllDepartments($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

// Get ONLY user's department
$userDepartment = getUserDepartment($pdo, $userDepartmentId);
$departments = $userDepartment ? [$userDepartment] : []; // Show only user's department

// For upload modal, decide if you want to show all departments or just user's department
$allDepartments = getAllDepartments($pdo); // Change this if you want to restrict upload too

// Department configuration with colors and icons
$departmentConfig = [
    'TED' => ['icon' => 'bxs-graduation', 'color' => '#f59e0b'],
    'MD' => ['icon' => 'bxs-business', 'color' => '#1e40af'],
    'FASD' => ['icon' => 'bx bx-water', 'color' => '#0284c7'],
    'ASD' => ['icon' => 'bxs-palette', 'color' => '#d946ef'],
    'ITD' => ['icon' => 'bxs-chip', 'color' => '#0f766e'],
    'NSTP' => ['icon' => 'bxs-user-check', 'color' => '#22c55e'],
    'OTHR' => ['icon' => 'bxs-file', 'color' => '#6b7280']
];

// Get department information for profile image - SAFE ACCESS
$departmentImage = null;
$departmentCode = null;

if ($userDepartment) {
    $departmentCode = $userDepartment['department_code'];
    $departmentImage = "../../img/{$departmentCode}.jpg";
}

// Get or create category folder - ONLY for user's department
function getOrCreateCategoryFolder($pdo, $departmentId, $category, $semester, $userId, $userDepartmentId) {
    // Security check: ensure user can only create folders in their own department
    if ($departmentId != $userDepartmentId) {
        throw new Exception("Access denied: Cannot create folder in different department");
    }
    
    try {
        $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
        $academicYear = date('Y') . '-' . (date('Y') + 1);
        $folderName = $academicYear . ' - ' . $semesterName;
        
        // Check if folder exists - ONLY in user's department
        $stmt = $pdo->prepare("
            SELECT id FROM folders 
            WHERE department_id = ? AND folder_name = ? AND category = ? AND is_deleted = 0
        ");
        $stmt->execute([$departmentId, $folderName, $category]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            return $folder['id'];
        }
        
        // Create new folder - ONLY in user's department
        $stmt = $pdo->prepare("
            INSERT INTO folders (folder_name, description, created_by, department_id, category, folder_path, folder_level, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Academic files for {$semesterName} {$academicYear}";
        $folderPath = "/departments/{$departmentId}/{$category}/{$semester}";
        
        $stmt->execute([$folderName, $description, $userId, $departmentId, $category, $folderPath, 2]);
        return $pdo->lastInsertId();
        
    } catch(Exception $e) {
        error_log("Error creating category folder: " . $e->getMessage());
        return false;
    }
}

// Format file size function
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
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
        'txt' => 'bxs-file-txt',
        'zip' => 'bxs-file-archive',
        'rar' => 'bxs-file-archive',
        'mp4' => 'bxs-videos',
        'avi' => 'bxs-videos',
        'mp3' => 'bxs-music',
        'wav' => 'bxs-music'
    ];
    
    return isset($iconMap[$ext]) ? $iconMap[$ext] : 'bxs-file';
}

// Get user initials helper function
function getUserInitials($fullName) {
    $names = explode(' ', $fullName);
    $initials = '';
    foreach($names as $name) {
        if(!empty($name)) {
            $initials .= strtoupper($name[0]);
        }
    }
    return substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $userDepartment ? $userDepartment['department_name'] : 'Department'; ?> Folders - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/folders.css">
    <style>
        .profile::after {
            content: '<?php echo $departmentCode; ?>';
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: var(--blue);
            color: white;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: 500;
            min-width: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Upload Modal -->
    <div id="uploadModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s ease;">
        <div id="modalContainer" style="background: white; border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: scale(0.9) translateY(20px); transition: all 0.3s ease; font-family: 'Poppins', sans-serif;">
        
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 24px; border-radius: 16px 16px 0 0; position: relative; overflow: hidden; font-family: 'Poppins', sans-serif;">
                <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255, 255, 255, 0.1); border-radius: 50%;"></div>
                <div style="position: absolute; bottom: -30px; left: -30px; width: 80px; height: 80px; background: rgba(255, 255, 255, 0.1); border-radius: 50%;"></div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1;">
                    <div>
                        <h2 style="margin: 0; font-size: 24px; font-weight: 600; margin-bottom: 4px;">
                            <i class='bx bxs-cloud-upload' style="margin-right: 8px; font-size: 28px;"></i>
                            Upload File
                        </h2>
                        <p style="margin: 0; opacity: 0.9; font-size: 14px;">Share your documents with your department</p>
                    </div>
                    <button onclick="closeUploadModal()" style="background: rgba(255, 255, 255, 0.2); border: none; border-radius: 8px; padding: 8px; color: white; cursor: pointer; transition: all 0.3s ease; font-size: 20px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <form id="uploadForm" enctype="multipart/form-data" style="padding: 0;">
                
                <!-- Department Selection - Pre-filled with user's department -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-building' style="color: #10b981; margin-right: 8px;"></i>
                        Your Department
                    </label>
                    <div style="padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #374151; font-size: 14px;">
                        <?php echo $userDepartment ? htmlspecialchars($userDepartment['department_name']) . ' (' . $userDepartment['department_code'] . ')' : 'No Department Assigned'; ?>
                    </div>
                    <input type="hidden" name="department" value="<?php echo $userDepartmentId; ?>">
                </div>

                <!-- Category Selection -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-category' style="color: #10b981; margin-right: 8px;"></i>
                        File Category
                    </label>
                    <select name="category" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'">
                        <option value="">Select a category...</option>
                        <?php foreach ($fileCategories as $key => $category): ?>
                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Academic Year Selection -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-calendar-alt' style="color: #10b981; margin-right: 8px;"></i>
                        Academic Year
                    </label>
                    <select name="academic_year" required 
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;" 
                        onfocus="this.style.borderColor='#10b981'" 
                        onblur="this.style.borderColor='#e5e7eb'">
                        <option value="">Select academic year...</option>
                    </select>
                    <small style="display: flex; align-items: center; gap: 4px; margin-top: 8px; color: #6b7280; font-size: 12px;">
                        <i class='bx bx-info-circle' style="color: #6b7280; font-size: 14px;"></i>
                        Academic year follows the format: Start Year - End Year (e.g., 2024-2025)
                    </small>
                </div>

                <!-- Semester Selection -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-calendar' style="color: #10b981; margin-right: 8px;"></i>
                        Academic Semester
                    </label>
                    <div class="semester-selection-container">
                        <!-- First Semester Option -->
                        <input type="radio" name="semester" value="first" id="semester-first" required>
                        <label for="semester-first">
                            <div class="semester-option">
                                <i class='bx bxs-calendar-check semester-icon'></i>
                                <div class="semester-info">
                                    <h4>First Semester</h4>
                                </div>
                            </div>
                        </label>
                        
                        <!-- Second Semester Option -->
                        <input type="radio" name="semester" value="second" id="semester-second" required>
                        <label for="semester-second">
                            <div class="semester-option">
                                <i class='bx bxs-calendar-star semester-icon'></i>
                                <div class="semester-info">
                                    <h4>Second Semester</h4>
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Academic Period Summary (will be populated by JavaScript) -->
                    <div id="academicPeriodSummary" class="academic-period-summary"></div>
                </div>

                <!-- File Upload Area -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-file-plus' style="color: #10b981; margin-right: 8px;"></i>
                        Upload File
                    </label>
                    
                    <div id="dropZone" style="border: 2px dashed #10b981; border-radius: 12px; padding: 40px 20px; text-align: center; background: linear-gradient(135deg, #f0fdf4, #ecfdf5); cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;">
                        <input type="file" id="fileInput" name="files[]" multiple accept="*" style="display: none;">
                        
                        <div id="uploadPrompt">
                            <i class='bx bxs-cloud-upload' style="font-size: 48px; color: #10b981; margin-bottom: 16px; display: block;"></i>
                            <h3 style="margin: 0 0 8px 0; color: #065f46; font-size: 18px; font-weight: 600;">Drop files here or click to browse</h3>
                            <p style="margin: 0; color: #059669; font-size: 14px; opacity: 0.8;">Support for PDF, DOC, XLS, PPT, Images and more</p>
                            <div style="margin-top: 16px; display: inline-flex; align-items: center; background: rgba(16, 185, 129, 0.1); padding: 8px 16px; border-radius: 20px; font-size: 12px; color: #065f46; font-weight: 500;">
                                <i class='bx bx-info-circle' style="margin-right: 4px; margin-top: 0px;"></i>
                                Max file size: 50MB per file
                            </div>
                        </div>
                        
                        <div id="filePreview" style="display: none; text-align: left;"></div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div id="uploadProgress" style="display: none; margin-top: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 14px; font-weight: 500; color: #374151;">Uploading files...</span>
                            <span id="progressPercent" style="font-size: 14px; font-weight: 500; color: #10b981;">0%</span>
                        </div>
                        <div style="width: 100%; background: #f3f4f6; border-radius: 8px; height: 8px; overflow: hidden;">
                            <div id="progressBar" style="width: 0%; background: linear-gradient(90deg, #10b981, #059669); height: 100%; border-radius: 8px; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                </div>

                <!-- File Description -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-note' style="color: #10b981; margin-right: 8px;"></i>
                        Description (Optional)
                    </label>
                    <textarea id="fileDescription" name="description" placeholder="Add a description for your files..." style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; resize: vertical; min-height: 80px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16, 185, 129, 0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'"></textarea>
                </div>

                <!-- File Tags -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-tag' style="color: #10b981; margin-right: 8px;"></i>
                        Tags
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                        <button type="button" onclick="addTag('Curriculum')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Curriculum</button>
                        <button type="button" onclick="addTag('Research')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Research</button>
                        <button type="button" onclick="addTag('Guidelines')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Guidelines</button>
                        <button type="button" onclick="addTag('Reports')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Reports</button>
                    </div>
                    <div id="selectedTags" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;"></div>
                    <input type="text" id="customTag" placeholder="Add custom tag..." style="width: 100%; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'">
                    <input type="hidden" id="tagsInput" name="tags" value="">
                </div>

                <!-- Modal Footer -->
                <div style="padding: 24px; background: #f9fafb; border-radius: 0 0 16px 16px;">
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="closeUploadModal()" style="background: #f3f4f6; color: #374151; border: none; border-radius: 8px; padding: 12px 24px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                            Cancel
                        </button>
                        <button type="submit" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 8px; padding: 12px 24px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class='bx bxs-cloud-upload' style="margin-right: 8px;"></i>
                            Upload Files
                        </button>
                    </div>
                    
                    <div style="margin-top: 16px; padding: 12px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border-left: 4px solid #10b981;">
                        <p style="margin: 0; font-size: 12px; color: #065f46; line-height: 1.4;">
                            <i class='bx bx-shield-check' style="margin-right: 4px;"></i>
                            Your files will be securely stored and organized in your department for easy access.
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <?php include 'components/navbar.html'; ?>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Department Folders</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="dashboard.php">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="" href="#">Department Folders</a>
                        </li>
                    </ul>
                </div>
                <button onclick="openUploadModal()" class="upload-btn">
                    <i class='bx bxs-cloud-upload'></i>
                    <span class="text">Upload File</span>
                </button>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="search-bar">
                    <input type="text" placeholder="Search in your department..." id="departmentSearch">
                    <i class='bx bx-search'></i>
                </div>
            </div>
            <!-- Department Tree with Categories -->
            <div class="department-tree">
                <?php if (!empty($departments)): ?>
                    <?php foreach ($departments as $dept): 
                        $config = $departmentConfig[$dept['department_code']] ?? $departmentConfig['OTHR'];
                    ?>
                        <div class="department-item" data-department="<?php echo $dept['id']; ?>">
                            <div class="department-header" onclick="toggleDepartment('<?php echo $dept['id']; ?>')">
                                <div class="department-icon" style="background-color: <?php echo $config['color']; ?>">
                                    <i class='bx <?php echo $config['icon']; ?>'></i>
                                </div>
                                <div class="department-info">
                                    <div class="department-name"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                    <div class="department-code"><?php echo $dept['department_code']; ?></div>
                                </div>
                                <i class='bx bx-chevron-right expand-icon' id="icon-<?php echo $dept['id']; ?>"></i>
                            </div>
                            
                            <div class="department-content" id="content-<?php echo $dept['id']; ?>">
                                <!-- Categories Grid -->
                                <div class="categories-grid">
                                    <?php foreach ($fileCategories as $categoryKey => $category): ?>
                                        <div class="category-item" data-category="<?php echo $categoryKey; ?>" onclick="toggleCategory('<?php echo $dept['id']; ?>', '<?php echo $categoryKey; ?>')">
                                            <div class="category-header">
                                                <div class="category-icon" style="background-color: <?php echo $category['color']; ?>">
                                                    <i class='bx <?php echo $category['icon']; ?>'></i>
                                                </div>
                                                <div class="category-info">
                                                    <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                                    <div class="category-count">0 files</div>
                                                </div>
                                                <i class='bx bx-chevron-right expand-icon' id="icon-<?php echo $dept['id']; ?>-<?php echo $categoryKey; ?>"></i>
                                            </div>
                                            
                                            <!-- Semester Content for this Category -->
                                            <div class="category-semester-content" id="category-content-<?php echo $dept['id']; ?>-<?php echo $categoryKey; ?>">
                                                <div class="semester-tabs">
                                                    <button class="semester-tab active" onclick="showCategorySemester('<?php echo $dept['id']; ?>', '<?php echo $categoryKey; ?>', 'first')">
                                                        <i class='bx bxs-folder'></i> First Semester
                                                    </button>
                                                    <button class="semester-tab" onclick="showCategorySemester('<?php echo $dept['id']; ?>', '<?php echo $categoryKey; ?>', 'second')">
                                                        <i class='bx bxs-folder'></i> Second Semester
                                                    </button>
                                                </div>
                                                
                                                <div class="files-grid" id="files-<?php echo $dept['id']; ?>-<?php echo $categoryKey; ?>-first">
                                                    <div class="empty-state">
                                                        <i class='bx bx-folder-open empty-icon'></i>
                                                        <p>No files in First Semester</p>
                                                        <small>Files uploaded to this category and semester will appear here</small>
                                                    </div>
                                                </div>
                                                
                                                <div class="files-grid" id="files-<?php echo $dept['id']; ?>-<?php echo $categoryKey; ?>-second" style="display: none;">
                                                    <div class="empty-state">
                                                        <i class='bx bx-folder-open empty-icon'></i>
                                                        <p>No files in Second Semester</p>
                                                        <small>Files uploaded to this category and semester will appear here</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 60px 20px;">
                        <i class='bx bx-error-circle empty-icon' style="color: #ef4444;"></i>
                        <h3 style="color: #374151; margin-bottom: 8px;">No Department Assigned</h3>
                        <p>Please contact your administrator to assign you to a department.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <script>
        window.userDepartmentId = <?php echo json_encode($userDepartmentId); ?>;
        window.fileCategories = <?php echo json_encode($fileCategories); ?>;
    </script>
    <script src="assets/js/folders.js"></script>
</body>
</html>