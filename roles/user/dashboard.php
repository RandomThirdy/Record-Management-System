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

// Get comprehensive statistics for the dashboard
$stats = [
    'total_files' => 0,
    'total_folders' => 0,
    'storage_used' => 0,
    'recent_uploads' => [],
    'public_files' => 0,
    'favorite_files' => 0,
    'department_files' => 0,
    'downloads_today' => 0
];

try {
    // Get user's file count and storage - Updated query to handle files without folders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as file_count, 
               COALESCE(SUM(file_size), 0) as total_size,
               SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) as public_count,
               SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END) as favorite_count
        FROM files f 
        WHERE f.uploaded_by = ? AND f.is_deleted = 0
    ");
    $stmt->execute([$currentUser['id']]);
    $fileStats = $stmt->fetch();
    
    $stats['total_files'] = $fileStats['file_count'];
    $stats['storage_used'] = $fileStats['total_size'];
    $stats['public_files'] = $fileStats['public_count'] ?? 0;
    $stats['favorite_files'] = $fileStats['favorite_count'] ?? 0;
    
    // Get user's folder count
    $stmt = $pdo->prepare("SELECT COUNT(*) as folder_count FROM folders WHERE created_by = ? AND is_deleted = 0");
    $stmt->execute([$currentUser['id']]);
    $folderStats = $stmt->fetch();
    
    $stats['total_folders'] = $folderStats['folder_count'];
    
    // Get department files count
    if ($currentUser['department_id']) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as dept_files 
            FROM files f 
            LEFT JOIN folders fo ON f.folder_id = fo.id 
            WHERE (fo.department_id = ? OR f.uploaded_by IN (
                SELECT id FROM users WHERE department_id = ?
            )) AND f.is_deleted = 0
        ");
        $stmt->execute([$currentUser['department_id'], $currentUser['department_id']]);
        $deptStats = $stmt->fetch();
        $stats['department_files'] = $deptStats['dept_files'];
    }
    
    // Get downloads today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as downloads_today
        FROM files f
        WHERE f.uploaded_by = ? 
        AND DATE(f.last_downloaded) = CURDATE()
        AND f.is_deleted = 0
    ");
    $stmt->execute([$currentUser['id']]);
    $downloadStats = $stmt->fetch();
    $stats['downloads_today'] = $downloadStats['downloads_today'] ?? 0;
    
    // Get recent uploads with more details - LEFT JOIN to handle files without folders
    $stmt = $pdo->prepare("
        SELECT f.original_name, f.file_size, f.uploaded_at, 
               COALESCE(fo.folder_name, 'Uncategorized') as folder_name, 
               f.file_type, f.file_extension, 
               COALESCE(f.download_count, 0) as download_count, 
               COALESCE(f.is_public, 0) as is_public, 
               COALESCE(f.is_favorite, 0) as is_favorite
        FROM files f
        LEFT JOIN folders fo ON f.folder_id = fo.id
        WHERE f.uploaded_by = ? AND f.is_deleted = 0
        ORDER BY f.uploaded_at DESC
        LIMIT 8
    ");
    $stmt->execute([$currentUser['id']]);
    $stats['recent_uploads'] = $stmt->fetchAll();
    
} catch(Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Add debug information
    error_log("SQL Error details: " . print_r($pdo->errorInfo(), true));
}

// Format file size function
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get user's initials for fallback
function getUserInitials($fullName) {
    $names = explode(' ', trim($fullName));
    $initials = '';
    foreach ($names as $name) {
        if (!empty($name)) {
            $initials .= strtoupper($name[0]);
        }
    }
    return $initials ?: 'U';
}

// Get file type icon
function getFileTypeIcon($fileType) {
    if (empty($fileType)) {
        return 'bxs-file';
    }
    
    // Convert to lowercase for comparison
    $fileType = strtolower($fileType);
    
    $icons = [
        // Documents
        'pdf' => 'bxs-file-pdf',
        'doc' => 'bxs-file-doc',
        'docx' => 'bxs-file-doc',
        'txt' => 'bxs-file-txt',
        'rtf' => 'bxs-file-doc',
        
        // Spreadsheets
        'xls' => 'bxs-spreadsheet',
        'xlsx' => 'bxs-spreadsheet',
        'csv' => 'bxs-spreadsheet',
        
        // Presentations
        'ppt' => 'bxs-file-doc',
        'pptx' => 'bxs-file-doc',
        
        // Images
        'jpg' => 'bxs-image',
        'jpeg' => 'bxs-image',
        'png' => 'bxs-image',
        'gif' => 'bxs-image',
        'bmp' => 'bxs-image',
        'svg' => 'bxs-image',
        'webp' => 'bxs-image',
        
        // Videos
        'mp4' => 'bxs-videos',
        'avi' => 'bxs-videos',
        'mov' => 'bxs-videos',
        'wmv' => 'bxs-videos',
        'flv' => 'bxs-videos',
        'webm' => 'bxs-videos',
        
        // Audio
        'mp3' => 'bxs-music',
        'wav' => 'bxs-music',
        'flac' => 'bxs-music',
        'aac' => 'bxs-music',
        
        // Archives
        'zip' => 'bxs-file-archive',
        'rar' => 'bxs-file-archive',
        '7z' => 'bxs-file-archive',
        'tar' => 'bxs-file-archive',
        'gz' => 'bxs-file-archive',
        
        // Code
        'html' => 'bxs-file-html',
        'css' => 'bxs-file-css',
        'js' => 'bxs-file-js',
        'php' => 'bxs-file-doc',
        'py' => 'bxs-file-doc',
        'java' => 'bxs-file-doc',
        'cpp' => 'bxs-file-doc',
        'c' => 'bxs-file-doc'
    ];
    
    return $icons[$fileType] ?? 'bxs-file';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        /* Enhanced Dashboard Styles */
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
        
        <?php if ($departmentCode): ?>
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
        <?php endif; ?>

        /* Enhanced Statistics Cards */
        .box-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 36px;
        }

        .box-info li {
            background: var(--light);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .box-info li:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
        }

        .box-info li::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--blue), var(--dark-blue));
        }

        .box-info li i {
            font-size: 32px;
            color: var(--blue);
            margin-bottom: 12px;
            padding: 16px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 12px;
            width: fit-content;
        }

        .box-info li .text h3 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .box-info li .text p {
            color: var(--dark-grey);
            font-weight: 500;
            font-size: 14px;
        }

        /* Enhanced Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .dashboard-card {
            background: var(--light);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.06);
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .card-actions {
            display: flex;
            gap: 8px;
        }

        .card-actions i {
            padding: 8px;
            background: rgba(0, 0, 0, 0.04);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--dark-grey);
        }

        .card-actions i:hover {
            background: var(--blue);
            color: white;
        }

        /* Enhanced Recent Files Table */
        .files-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .files-table th {
            background: rgba(59, 130, 246, 0.08);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(59, 130, 246, 0.1);
        }

        .files-table td {
            padding: 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            vertical-align: middle;
        }

        .files-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.04);
            cursor: pointer;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--blue);
            flex-shrink: 0;
        }

        .file-details {
            flex-grow: 1;
            min-width: 0;
        }

        .file-details p {
            margin: 0;
            font-weight: 500;
            color: var(--dark);
            font-size: 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-meta {
            font-size: 12px;
            color: var(--dark-grey);
            margin-top: 2px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .status-public {
            background: rgba(34, 197, 94, 0.1);
            color: #059669;
        }

        .status-private {
            background: rgba(107, 114, 128, 0.1);
            color: #374151;
        }

        .status-favorite {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        /* Responsive table */
        @media screen and (max-width: 768px) {
            .files-table {
                font-size: 12px;
            }
            
            .files-table th,
            .files-table td {
                padding: 8px 4px;
            }
            
            .file-info {
                gap: 8px;
            }
            
            .file-icon {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .files-table th:nth-child(3),
            .files-table td:nth-child(3) {
                display: none; /* Hide size column on mobile */
            }
        }

        /* Enhanced Quick Actions */
        .quick-actions {
            display: grid;
            gap: 12px;
        }

        .action-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .action-item:hover {
            background: rgba(59, 130, 246, 0.04);
            border-color: rgba(59, 130, 246, 0.2);
            transform: translateX(4px);
        }

        .action-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .action-icon {
            width: 40px;
            height: 40px;
            background: var(--blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .action-item p {
            margin: 0;
            font-weight: 500;
            color: var(--dark);
        }

        .action-arrow {
            color: var(--dark-grey);
            transition: all 0.2s ease;
        }

        .action-item:hover .action-arrow {
            color: var(--blue);
            transform: translateX(4px);
        }

        /* Enhanced Account Info */
        .account-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 16px;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.6);
            padding: 16px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--dark-grey);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-value {
            font-weight: 500;
            color: var(--dark);
            font-size: 14px;
        }

        .upload-cta {
            background: var(--blue);
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .upload-cta:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--dark-grey);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state p {
            margin-bottom: 20px;
            font-size: 16px;
        }

        .empty-state a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
            padding: 12px 24px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .empty-state a:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .box-info {
                grid-template-columns: 1fr;
            }
            
            .account-info {
                grid-template-columns: 1fr;
            }
            
            .welcome-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <section id="sidebar">
        <a href="#" class="brand">
            <img src="../../img/cvsu-logo.png" alt="Logo" style="width: 30px; height: 30px;">
            <span class="text">ODCI</span>
        </a>
        <ul class="side-menu top">
            <li class="active">
                <a href="dashboard.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="files.php">
                    <i class='bx bxs-file'></i>
                    <span class="text">My Files</span>
                </a>
            </li>
            <li>
                <a href="folders.php">
                    <i class='bx bxs-folder'></i>
                    <span class="text">My Folders</span>
                </a>
            </li>
            <li>
                <a href="submission_tracker.php">
                    <i class='bx bxs-check-square'></i>
                    <span class="text">Submission Tracker</span>
                </a>
            </li>
             <li>
                <a href="reports.php">
                    <i class='bx bxs-report'></i>
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
            <a href="#" class="profile" title="<?php echo htmlspecialchars($currentUser['full_name'] . ($departmentCode ? ' - ' . $departmentCode : '')); ?>">
                <?php if ($departmentImage && file_exists($departmentImage)): ?>
                    <img src="<?php echo htmlspecialchars($departmentImage); ?>" alt="<?php echo htmlspecialchars($departmentCode . ' Profile'); ?>" style="width: 36px; height: 36px;">
                <?php else: ?>
                    <div class="profile-icon">
                        <?php echo getUserInitials($currentUser['full_name']); ?>
                    </div>
                <?php endif; ?>
            </a>
        </nav>

        <!-- Main Content -->
        <main>
            <!-- Welcome Section -->
          <div class="head-title">
                <div class="left">
                    <h1>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?>!</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="#">Overview</a>
                        </li>
                    </ul>
                </div>
                <a href="upload.php" class="btn-download">
                    <i class='bx bxs-cloud-upload'></i>
                    <span class="text">Upload File</span>
                </a>
            </div>

            <!-- Debug Section - Remove this after confirming files are showing -->
            <?php if (empty($stats['recent_uploads'])): ?>
            <div class="dashboard-card" style="margin-bottom: 24px; border: 2px solid #f59e0b;">
                <div class="card-header">
                    <h3 style="color: #f59e0b;">🔍 Debug Information</h3>
                </div>
                <div style="padding: 16px; font-size: 14px; background: rgba(245, 158, 11, 0.05);">
                    <?php
                    // Check if files exist for this user
                    try {
                        $debugStmt = $pdo->prepare("SELECT COUNT(*) as total FROM files WHERE uploaded_by = ?");
                        $debugStmt->execute([$currentUser['id']]);
                        $totalFiles = $debugStmt->fetch()['total'];
                        
                        $debugStmt = $pdo->prepare("SELECT COUNT(*) as deleted FROM files WHERE uploaded_by = ? AND is_deleted = 1");
                        $debugStmt->execute([$currentUser['id']]);
                        $deletedFiles = $debugStmt->fetch()['deleted'];
                        
                        $debugStmt = $pdo->prepare("SELECT COUNT(*) as no_folder FROM files WHERE uploaded_by = ? AND folder_id IS NULL");
                        $debugStmt->execute([$currentUser['id']]);
                        $noFolderFiles = $debugStmt->fetch()['no_folder'];
                        
                        echo "<p><strong>Total files for user {$currentUser['id']}:</strong> $totalFiles</p>";
                        echo "<p><strong>Deleted files:</strong> $deletedFiles</p>";
                        echo "<p><strong>Files without folder:</strong> $noFolderFiles</p>";
                        
                        if ($totalFiles > 0) {
                            $debugStmt = $pdo->prepare("SELECT id, original_name, folder_id, is_deleted, uploaded_at FROM files WHERE uploaded_by = ? LIMIT 3");
                            $debugStmt->execute([$currentUser['id']]);
                            $sampleFiles = $debugStmt->fetchAll();
                            
                            echo "<p><strong>Sample files:</strong></p><ul>";
                            foreach ($sampleFiles as $file) {
                                $status = $file['is_deleted'] ? 'DELETED' : 'ACTIVE';
                                echo "<li>ID: {$file['id']}, Name: {$file['original_name']}, Folder: {$file['folder_id']}, Status: $status</li>";
                            }
                            echo "</ul>";
                        }
                    } catch (Exception $e) {
                        echo "<p style='color: red;'>Debug error: " . $e->getMessage() . "</p>";
                    }
                    ?>
                    <p style="margin-top: 16px;"><em>This debug section will be removed once files are displaying correctly.</em></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Enhanced Statistics Cards -->
            <ul class="box-info">
                <li>
                    <i class='bx bxs-file'></i>
                    <span class="text">
                        <h3><?php echo number_format($stats['total_files']); ?></h3>
                        <p>Total Files</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-folder'></i>
                    <span class="text">
                        <h3><?php echo number_format($stats['total_folders']); ?></h3>
                        <p>Total Folders</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-cloud'></i>
                    <span class="text">
                        <h3><?php echo formatFileSize($stats['storage_used']); ?></h3>
                        <p>Storage Used</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-download'></i>
                    <span class="text">
                        <h3><?php echo number_format($stats['downloads_today']); ?></h3>
                        <p>Downloads Today</p>
                    </span>
                </li>
            </ul>

            <!-- Dashboard Grid Layout -->
            <div class="dashboard-grid">
                <!-- Recent Files -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Recent Files</h3>
                        <div class="card-actions">
                            <i class='bx bx-search' title="Search Files"></i>
                            <i class='bx bx-filter' title="Filter Files"></i>
                            <i class='bx bx-refresh' title="Refresh"></i>
                        </div>
                    </div>
                    
                    <?php if (!empty($stats['recent_uploads'])): ?>
                        <div style="overflow-x: auto;">
                            <table class="files-table">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Folder</th>
                                        <th>Size</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($stats['recent_uploads'], 0, 6) as $file): ?>
                                        <tr>
                                            <td>
                                                <div class="file-info">
                                                    <div class="file-icon">
                                                        <i class='bx <?php echo getFileTypeIcon($file['file_type'] ?? $file['file_extension'] ?? pathinfo($file['original_name'], PATHINFO_EXTENSION)); ?>'></i>
                                                    </div>
                                                    <div class="file-details">
                                                        <p><?php echo htmlspecialchars(strlen($file['original_name']) > 30 ? substr($file['original_name'], 0, 30) . '...' : $file['original_name']); ?></p>
                                                        <div class="file-meta"><?php echo number_format($file['download_count'] ?? 0); ?> downloads</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($file['folder_name']); ?></td>
                                            <td><?php echo formatFileSize($file['file_size']); ?></td>
                                            <td>
                                                <?php if (isset($file['is_favorite']) && $file['is_favorite']): ?>
                                                    <span class="status-badge status-favorite">★ Favorite</span>
                                                <?php elseif (isset($file['is_public']) && $file['is_public']): ?>
                                                    <span class="status-badge status-public">🌐 Public</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-private">🔒 Private</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($stats['recent_uploads']) > 6): ?>
                            <div style="text-align: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.06);">
                                <a href="files.php" style="color: var(--blue); text-decoration: none; font-weight: 500; font-size: 14px;">
                                    View All Files (<?php echo count($stats['recent_uploads']); ?> total) →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-file'></i>
                            <p>No files uploaded yet</p>
                            <a href="upload.php">Upload your first file</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions & Info -->
                <div>
                    <!-- Quick Actions -->
                    <div class="dashboard-card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="quick-actions">
                            <a href="upload.php" class="action-item">
                                <div class="action-content">
                                    <div class="action-icon">
                                        <i class='bx bx-upload'></i>
                                    </div>
                                    <p>Upload Files</p>
                                </div>
                                <i class='bx bx-chevron-right action-arrow'></i>
                            </a>
                            
                            <a href="folders.php?action=create" class="action-item">
                                <div class="action-content">
                                    <div class="action-icon">
                                        <i class='bx bx-folder-plus'></i>
                                    </div>
                                    <p>Create Folder</p>
                                </div>
                                <i class='bx bx-chevron-right action-arrow'></i>
                            </a>
                            
                            <a href="files.php" class="action-item">
                                <div class="action-content">
                                    <div class="action-icon">
                                    <i class='bx bx-folder-open'></i>
                                    </div>
                                    <p>Organize Files</p>
                                </div>
                                <i class='bx bx-chevron-right action-arrow'></i>
                            </a>
                            
                            <a href="shared.php" class="action-item">
                                <div class="action-content">
                                    <div class="action-icon">
                                        <i class='bx bx-share'></i>
                                    </div>
                                    <p>Share Documents</p>
                                </div>
                                <i class='bx bx-chevron-right action-arrow'></i>
                            </a>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Account Information</h3>
                            <div class="card-actions">
                                <i class='bx bx-edit' title="Edit Profile"></i>
                            </div>
                        </div>
                        <div class="account-info">
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo htmlspecialchars($currentUser['department_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Employee ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($currentUser['employee_id'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Position</div>
                                <div class="info-value"><?php echo htmlspecialchars($currentUser['position'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Last Login</div>
                                <div class="info-value">
                                    <?php 
                                    if ($currentUser['last_login']) {
                                        echo date('M j, Y g:i A', strtotime($currentUser['last_login']));
                                    } else {
                                        echo 'First login';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Account Status</div>
                                <div class="info-value" style="color: <?php echo $currentUser['is_approved'] ? '#059669' : '#dc2626'; ?>; font-weight: 600;">
                                    <?php echo $currentUser['is_approved'] ? '✓ Approved' : '⏳ Pending'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Statistics Row -->
            <div class="dashboard-grid" style="margin-top: 24px;">
                <!-- Storage & Activity -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Storage & Activity Overview</h3>
                        <div class="card-actions">
                            <i class='bx bx-bar-chart' title="View Analytics"></i>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 16px;">
                        <div class="info-item">
                            <div class="info-label">Public Files</div>
                            <div class="info-value" style="color: #059669;"><?php echo number_format($stats['public_files']); ?> files</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Favorite Files</div>
                            <div class="info-value" style="color: #d97706;"><?php echo number_format($stats['favorite_files']); ?> files</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Department Files</div>
                            <div class="info-value" style="color: var(--blue);"><?php echo number_format($stats['department_files']); ?> files</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Recent Activity</div>
                            <div class="info-value">
                                <?php 
                                $recentCount = count($stats['recent_uploads']);
                                echo $recentCount > 0 ? "$recentCount uploads this week" : "No recent activity";
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>System Status</h3>
                        <div class="card-actions">
                            <i class='bx bx-info-circle' title="System Information"></i>
                        </div>
                    </div>
                    <div class="account-info">
                        <div class="info-item">
                            <div class="info-label">Server Status</div>
                            <div class="info-value" style="color: #059669;">🟢 Online</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Backup Status</div>
                            <div class="info-value" style="color: #059669;">✓ Up to date</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Storage Health</div>
                            <div class="info-value" style="color: #059669;">Optimal</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Sync</div>
                            <div class="info-value"><?php echo date('g:i A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script>
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

        // Add some interactive animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate statistics cards on load
            const statCards = document.querySelectorAll('.box-info li');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add click animations to action items
            const actionItems = document.querySelectorAll('.action-item');
            actionItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });

            // Refresh button functionality
            const refreshBtn = document.querySelector('.card-actions i[title="Refresh"]');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    this.style.transform = 'rotate(360deg)';
                    this.style.transition = 'transform 0.6s ease';
                    setTimeout(() => {
                        this.style.transform = '';
                        // Here you could add actual refresh functionality
                        console.log('Refreshing dashboard data...');
                    }, 600);
                });
            }
        });
    </script>
</body>
</html>