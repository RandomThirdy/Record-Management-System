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

// Get departments from database
function getDepartments($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

$departments = getDepartments($pdo);

// Department configuration with colors and icons
$departmentConfig = [
    'TED' => ['icon' => 'bxs-graduation', 'color' => '#f59e0b'],
    'MD' => ['icon' => 'bxs-business', 'color' => '#1e40af'],
    'FASD' => ['icon' => 'bx bx-water', 'color' => '#0284c7'],
    'ASD' => ['icon' => 'bxs-palette', 'color' => '#d946ef'],
    'ITD' => ['icon' => 'bxs-chip', 'color' => '#0f766e'],
    'NSTP' => ['icon' => 'bxs-user-check', 'color' => '#22c55e'],
    'Others' => ['icon' => 'bxs-file', 'color' => '#6b7280']
];

// Get department information for profile image - SAFE ACCESS
$departmentImage = null;
$departmentCode = null;

// Check if department_id exists and is not null
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    try {
        $stmt = $pdo->prepare("SELECT department_code FROM departments WHERE id = ?");
        $stmt->execute([$currentUser['department_id']]);
        $department = $stmt->fetch();
        
        if ($department) {
            $departmentCode = $department['department_code'];
            $departmentImage = "../../img/{$departmentCode}.jpg";
        }
    } catch(Exception $e) {
        error_log("Department image error: " . $e->getMessage());
    }
}

// If getCurrentUser() doesn't include department info, get it separately
if (!isset($currentUser['department_id']) && isset($currentUser['id'])) {
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
            $currentUser['department_id'] = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
            $departmentCode = $userDept['department_code'];
            $departmentImage = "../../img/{$departmentCode}.jpg";
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

// Get or create semester folder
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
            INSERT INTO folders (folder_name, description, created_by, department_id, folder_path, folder_level, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Academic files for {$semesterName} {$academicYear}";
        $folderPath = "/departments/{$departmentId}/{$semester}";
        
        $stmt->execute([$folderName, $description, $userId, $departmentId, $folderPath, 1]);
        return $pdo->lastInsertId();
        
    } catch(Exception $e) {
        error_log("Error creating semester folder: " . $e->getMessage());
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
    <title>Department Folders - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .upload-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px 28px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }

        .upload-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .upload-btn:hover::before {
            left: 100%;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .upload-btn:hover i {
            transform: scale(1.1) rotate(5deg);
        }

        .upload-btn i {
            font-size: 20px;
            transition: transform 0.3s ease;
        }

        .department-tree {
            background: var(--grey);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .department-item {
            margin-bottom: 16px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .department-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .department-header {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: var(--light);
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .department-header:hover {
            background: #f8fafc;
        }
        
        .department-header.active {
            background: #f1f5f9;
        }
        
        .department-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            color: white;
            font-size: 20px;
        }
        
        .department-info {
            flex: 1;
        }
        
        .department-name {
            font-weight: 600;
            font-size: 16px;
            color: var(--dark);
            margin-bottom: 4px;
        }
        
        .department-code {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .expand-icon {
            font-size: 20px;
            color: #64748b;
            transition: transform 0.3s ease;
        }
        
        .expand-icon.rotated {
            transform: rotate(90deg);
        }
        
        .semester-content {
            display: none;
            background: var(--light);
            border-top: 1px solid #e5e7eb;
        }
        
        .semester-content.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 500px;
            }
        }
        
        .semester-tabs {
            display: flex;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .semester-tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            cursor: pointer;
            background: transparent;
            border: none;
            font-weight: 500;
            color: #64748b;
            transition: all 0.3s ease;
        }
        
        .semester-tab:hover {
            background: #e2e8f0;
            color: var(--dark);
        }
        
        .semester-tab.active {
            background: var(--blue);
            color: white;
        }
        
        .files-grid {
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .file-card {
            background: white;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .file-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .file-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--blue);
            color: white;
            font-size: 18px;
            margin-right: 12px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 4px;
            word-break: break-all;
        }
        
        .file-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .file-uploader {
            font-size: 11px;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            color: #cbd5e1;
        }
        
        .search-section {
            background: var(--grey);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .search-bar {
            position: relative;
            max-width: 400px;
        }
        
        .search-bar input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: var(--blue);
        }
        
        .search-bar i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 18px;
        }
        
        .profile img {
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e6ed;
            transition: border-color 0.3s ease;
        }
        
        .profile:hover img {
            border-color: var(--blue);
        }
        
        .profile-icon {
            width: 36px;
            height: 36px;
            background: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid #e0e6ed;
            transition: border-color 0.3s ease;
        }
        
        .profile:hover .profile-icon {
            border-color: var(--blue);
        }
        
        .profile {
            position: relative;
        }
        
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
        
        @media (max-width: 768px) {
            .files-grid {
                grid-template-columns: 1fr;
                padding: 16px;
            }
            
            .department-header {
                padding: 12px 16px;
            }
            
            .department-name {
                font-size: 14px;
            }
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
                            Upload Files
                        </h2>
                        <p style="margin: 0; opacity: 0.9; font-size: 14px;">Share your documents with the department</p>
                    </div>
                    <button onclick="closeUploadModal()" style="background: rgba(255, 255, 255, 0.2); border: none; border-radius: 8px; padding: 8px; color: white; cursor: pointer; transition: all 0.3s ease; font-size: 20px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <form id="uploadForm" enctype="multipart/form-data" style="padding: 0;">
                
                <!-- Department Selection -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-building' style="color: #10b981; margin-right: 8px;"></i>
                        Select Department
                    </label>
                    <select id="department" name="department" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: white; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16, 185, 129, 0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'">
                        <option value="">Choose a department...</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']) . ' (' . $dept['department_code'] . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Semester Selection -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-calendar' style="color: #10b981; margin-right: 8px;"></i>
                        Academic Semester
                    </label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <label style="display: flex; align-items: center; padding: 16px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; background: white;" onmouseover="this.style.borderColor='#10b981'; this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='white'">
                            <input type="radio" name="semester" value="first" required style="margin-right: 12px; transform: scale(1.2); accent-color: #10b981;">
                            <div>
                                <div style="font-weight: 600; color: #374151;">First Semester</div>
                                <div style="font-size: 12px; color: #6b7280;">August - December</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; padding: 16px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; background: white;" onmouseover="this.style.borderColor='#10b981'; this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='white'">
                            <input type="radio" name="semester" value="second" required style="margin-right: 12px; transform: scale(1.2); accent-color: #10b981;">
                            <div>
                                <div style="font-weight: 600; color: #374151;">Second Semester</div>
                                <div style="font-size: 12px; color: #6b7280;">January - May</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- File Upload Area -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-file-plus' style="color: #10b981; margin-right: 8px;"></i>
                        Upload Files
                    </label>
                    
                    <div id="dropZone" style="border: 2px dashed #10b981; border-radius: 12px; padding: 40px 20px; text-align: center; background: linear-gradient(135deg, #f0fdf4, #ecfdf5); cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;" onclick="document.getElementById('fileInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#059669'; this.style.background='#d1fae5';" ondragleave="this.style.borderColor='#10b981'; this.style.background='linear-gradient(135deg, #f0fdf4, #ecfdf5)';" ondrop="handleDrop(event)">
                        <input type="file" id="fileInput" name="files[]" multiple accept="*" style="display: none;" onchange="handleFileSelect(this.files)">
                        
                        <div id="uploadPrompt">
                            <i class='bx bxs-cloud-upload' style="font-size: 48px; color: #10b981; margin-bottom: 16px; display: block;"></i>
                            <h3 style="margin: 0 0 8px 0; color: #065f46; font-size: 18px; font-weight: 600;">Drop files here or click to browse</h3>
                            <p style="margin: 0; color: #059669; font-size: 14px; opacity: 0.8;">Support for PDF, DOC, XLS, PPT, Images and more</p>
                            <div style="margin-top: 16px; display: inline-flex; align-items: center; background: rgba(16, 185, 129, 0.1); padding: 8px 16px; border-radius: 20px; font-size: 12px; color: #065f46; font-weight: 500;">
                                <i class='bx bx-info-circle' style="margin-right: 6px;"></i>
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
                    <input type="text" id="customTag" placeholder="Add custom tag..." style="width: 100%; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'" onkeypress="if(event.key==='Enter') {event.preventDefault(); addCustomTag();}">
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
                            Your files will be securely stored and organized by department and semester for easy access.
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <section id="sidebar">
        <a href="#" class="brand">
            <img src="../../img/cvsu-logo.png" alt="Logo" style="width: 30px; height: 30px;">
            <span class="text">ODCI</span>
        </a>
        <ul class="side-menu top">
            <li>
                <a href="dashboard.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="folders.php">
                    <i class='bx bxs-file'></i>
                    <span class="text">All Files</span>
                </a>
            </li>
            <li class="active">
                <a href="folders.php">
                    <i class='bx bxs-folder'></i>
                    <span class="text">All Folders</span>
                </a>
            </li>
            <li>
                <a href="submission_tracker.php">
                    <i class='bx bxs-check-square'></i>
                    <span class="text">Submission Tracker</span>
                </a>
            </li>
             <li>
                <a href="folders.php">
                    <i class='bx bxs-folder'></i>
                    <span class="text">Reports</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <li>
                <a href="settings.php">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="../../logout.php" class="logout">
                    <i class='bx bxs-log-out-circle'></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>

    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <nav>
            <i class='bx bx-menu'></i>
            <form action="#">
                <div class="form-input">
                    <input type="search" placeholder="Search...">
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="#" class="notification">
                <i class='bx bxs-bell'></i>
                <span class="num">8</span>
            </a>
            <a href="#" class="profile" title="<?php echo htmlspecialchars($currentUser['name'] . ($departmentCode ? ' - ' . $departmentCode : '')); ?>">
                <?php if ($departmentImage && file_exists($departmentImage)): ?>
                    <img src="<?php echo htmlspecialchars($departmentImage); ?>" alt="<?php echo htmlspecialchars($departmentCode . ' Profile'); ?>" style="width: 36px; height: 36px;">
                <?php else: ?>
                    <div class="profile-icon">
                        <?php echo getUserInitials($currentUser['name']); ?>
                    </div>
                <?php endif; ?>
            </a>
        </nav>

        <!-- Main Content -->
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
                            <a class="active" href="#">Department Folders</a>
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
                    <input type="text" placeholder="Search across all departments..." id="departmentSearch">
                    <i class='bx bx-search'></i>
                </div>
            </div>

            <!-- Department Tree -->
            <div class="department-tree">
                <?php foreach ($departments as $dept): 
                    $config = $departmentConfig[$dept['department_code']] ?? $departmentConfig['Others'];
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
                        
                        <div class="semester-content" id="content-<?php echo $dept['id']; ?>">
                            <div class="semester-tabs">
                                <button class="semester-tab active" onclick="showSemester('<?php echo $dept['id']; ?>', 'first')">
                                    <i class='bx bxs-folder'></i> First Semester
                                </button>
                                <button class="semester-tab" onclick="showSemester('<?php echo $dept['id']; ?>', 'second')">
                                    <i class='bx bxs-folder'></i> Second Semester
                                </button>
                            </div>
                            
                            <div class="files-grid" id="files-<?php echo $dept['id']; ?>-first">
                                <div class="empty-state">
                                    <i class='bx bx-folder-open empty-icon'></i>
                                    <p>No files in First Semester</p>
                                    <small>Files uploaded to this semester will appear here</small>
                                </div>
                            </div>
                            
                            <div class="files-grid" id="files-<?php echo $dept['id']; ?>-second" style="display: none;">
                                <div class="empty-state">
                                    <i class='bx bx-folder-open empty-icon'></i>
                                    <p>No files in Second Semester</p>
                                    <small>Files uploaded to this semester will appear here</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </section>

    <script>
        // Modal functionality
        let selectedFiles = [];
        let selectedTags = [];

        // Open Modal
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

        // Close Modal
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

        // Handle file selection
        function handleFileSelect(files) {
            selectedFiles = Array.from(files);
            displayFilePreview();
        }

        // Handle drag and drop
        function handleDrop(event) {
            event.preventDefault();
            const dropZone = event.target;
            dropZone.style.borderColor = '#10b981';
            dropZone.style.background = 'linear-gradient(135deg, #f0fdf4, #ecfdf5)';
            
            const files = Array.from(event.dataTransfer.files);
            selectedFiles = files;
            displayFilePreview();
        }

        // Display file preview
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

        // Remove file
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            displayFilePreview();
        }

        // Get file icon
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
                'rar': 'bxs-file-archive',
                'mp4': 'bxs-videos',
                'avi': 'bxs-videos',
                'mp3': 'bxs-music',
                'wav': 'bxs-music'
            };
            return iconMap[ext] || 'bxs-file';
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Add tag
        function addTag(tag) {
            if (!selectedTags.includes(tag)) {
                selectedTags.push(tag);
                displaySelectedTags();
                updateTagsInput();
            }
        }

        // Add custom tag
        function addCustomTag() {
            const input = document.getElementById('customTag');
            const tag = input.value.trim();
            if (tag && !selectedTags.includes(tag)) {
                selectedTags.push(tag);
                displaySelectedTags();
                updateTagsInput();
                input.value = '';
            }
        }

        // Display selected tags
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

        // Remove tag
        function removeTag(index) {
            selectedTags.splice(index, 1);
            displaySelectedTags();
            updateTagsInput();
        }

        // Update hidden tags input
        function updateTagsInput() {
            document.getElementById('tagsInput').value = JSON.stringify(selectedTags);
        }

        // Reset form
        function resetForm() {
            selectedFiles = [];
            selectedTags = [];
            document.getElementById('uploadForm').reset();
            document.getElementById('uploadPrompt').style.display = 'block';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('selectedTags').innerHTML = '';
            document.getElementById('uploadProgress').style.display = 'none';
            updateTagsInput();
        }

        // Handle form submission
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
                formData.append('files[]', file);
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
                    // Refresh the specific department files
                    if (data.departmentId) {
                        loadDepartmentFiles(data.departmentId);
                    }
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

        // Simulate upload progress
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

        // Close modal when clicking outside
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });

        // Handle keyboard shortcuts
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

        // Department tree functionality
        function toggleDepartment(deptId) {
            const content = document.getElementById(`content-${deptId}`);
            const icon = document.getElementById(`icon-${deptId}`);
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
                loadDepartmentFiles(deptId);
            }
        }

        function showSemester(deptId, semester) {
            // Update tab active state
            const tabs = document.querySelectorAll(`#content-${deptId} .semester-tab`);
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide semester content
            const firstSemester = document.getElementById(`files-${deptId}-first`);
            const secondSemester = document.getElementById(`files-${deptId}-second`);
            
            if (semester === 'first') {
                firstSemester.style.display = 'grid';
                secondSemester.style.display = 'none';
            } else {
                firstSemester.style.display = 'none';
                secondSemester.style.display = 'grid';
            }
        }

        function loadDepartmentFiles(deptId) {
            // AJAX call to load files from database
            fetch('get_department_files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ department_id: deptId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderFiles(deptId, 'first', data.first_semester || []);
                    renderFiles(deptId, 'second', data.second_semester || []);
                } else {
                    console.error('Error loading files:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading files:', error);
            });
        }

        function renderFiles(deptId, semester, files) {
            const container = document.getElementById(`files-${deptId}-${semester}`);
            
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
                                <div class="file-name">${file.original_name || file.file_name}</div>
                            </div>
                        </div>
                        <div class="file-meta">
                            <span>${fileSize}</span>
                            <span>${uploadDate}</span>
                        </div>
                        <div class="file-uploader">
                            <i class='bx bx-user'></i> ${file.uploader_name}
                        </div>
                        ${file.description ? `<div style="font-size: 12px; color: #6b7280; margin-top: 8px; padding: 8px; background: #f8fafc; border-radius: 4px;">${file.description}</div>` : ''}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function downloadFile(fileId, fileName) {
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = `download_file.php?id=${fileId}`;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Search functionality
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
    </script>
</body>
</html>