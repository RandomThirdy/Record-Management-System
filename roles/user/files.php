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

// Get user's department ID
$userDepartmentId = null;
$departmentImage = null;
$departmentCode = 'OTHR';

if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.department_code, d.department_name, d.department_image 
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
            $departmentCode = $userDept['department_code'] ?? 'OTHR';
            $departmentImage = $userDept['department_image'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

if (!$userDepartmentId) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=no_department_assigned');
    exit();
}

// File categories
$fileCategories = [
    'ipcr_accomplishment' => ['name' => 'IPCR Accomplishment', 'icon' => 'bxs-trophy', 'color' => '#f59e0b'],
    'ipcr_target' => ['name' => 'IPCR Target', 'icon' => 'bxs-bullseye', 'color' => '#ef4444'],
    'workload' => ['name' => 'Workload', 'icon' => 'bxs-briefcase', 'color' => '#8b5cf6'],
    'course_syllabus' => ['name' => 'Course Syllabus', 'icon' => 'bxs-book-content', 'color' => '#06b6d4'],
    'syllabus_acceptance' => ['name' => 'Course Syllabus Acceptance Form', 'icon' => 'bxs-check-circle', 'color' => '#10b981'],
    'exam' => ['name' => 'Exam', 'icon' => 'bxs-file-doc', 'color' => '#dc2626'],
    'tos' => ['name' => 'TOS', 'icon' => 'bxs-spreadsheet', 'color' => '#059669'],
    'class_record' => ['name' => 'Class Record', 'icon' => 'bxs-data', 'color' => '#7c3aed'],
    'grading_sheet' => ['name' => 'Grading Sheet', 'icon' => 'bxs-calculator', 'color' => '#ea580c'],
    'attendance_sheet' => ['name' => 'Attendance Sheet', 'icon' => 'bxs-user-check', 'color' => '#0284c7'],
    'stakeholder_feedback' => ['name' => 'Stakeholder\'s Feedback Form w/ Summary', 'icon' => 'bxs-comment-detail', 'color' => '#9333ea'],
    'consultation' => ['name' => 'Consultation', 'icon' => 'bxs-chat', 'color' => '#0d9488'],
    'lecture' => ['name' => 'Lecture', 'icon' => 'bxs-chalkboard', 'color' => '#7c2d12'],
    'activities' => ['name' => 'Activities', 'icon' => 'bxs-game', 'color' => '#be185d'],
    'exam_acknowledgement' => ['name' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form', 'icon' => 'bxs-receipt', 'color' => '#1e40af'],
    'consultation_log' => ['name' => 'Consultation Log Sheet Form', 'icon' => 'bxs-notepad', 'color' => '#374151']
];

// Get user's department
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

// Get all files for user's department with pagination and filters
function getUserFiles($pdo, $userId, $userDepartmentId, $search = '', $category = '', $sortBy = 'uploaded_at', $sortOrder = 'DESC', $limit = 20, $offset = 0) {
    try {
        $whereClause = "WHERE fo.department_id = ?";
        $params = [$userDepartmentId];
        
        if ($search) {
            $whereClause .= " AND (f.file_name LIKE ? OR f.original_name LIKE ? OR f.description LIKE ? OR fo.folder_name LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if ($category) {
            $whereClause .= " AND fo.category = ?";
            $params[] = $category;
        }
        
        $validSortColumns = ['uploaded_at', 'file_name', 'file_size', 'download_count', 'original_name'];
        $sortBy = in_array($sortBy, $validSortColumns) ? $sortBy : 'uploaded_at';
        $sortOrder = ($sortOrder === 'ASC') ? 'ASC' : 'DESC';
        
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
                f.is_favorite,
                f.thumbnail_path,
                fo.folder_name,
                fo.category,
                fo.id as folder_id,
                u.name as uploader_name,
                u.misurname as uploader_surname,
                u.username as uploader_username,
                d.department_code,
                d.department_name
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            LEFT JOIN users u ON f.uploaded_by = u.id
            LEFT JOIN departments d ON fo.department_id = d.id
            {$whereClause}
            AND f.is_deleted = 0 
            AND fo.is_deleted = 0
            ORDER BY f.{$sortBy} {$sortOrder}
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process files to format data properly
        foreach ($files as &$file) {
            // Parse tags if they exist
            if (!empty($file['tags'])) {
                $tagsData = json_decode($file['tags'], true);
                $file['tags'] = is_array($tagsData) ? $tagsData : explode(',', $file['tags']);
            } else {
                $file['tags'] = [];
            }
            
            // Format uploader name
            if ($file['uploader_name'] && $file['uploader_surname']) {
                $file['uploader_full_name'] = $file['uploader_name'] . ' ' . $file['uploader_surname'];
            } elseif ($file['uploader_name']) {
                $file['uploader_full_name'] = $file['uploader_name'];
            } else {
                $file['uploader_full_name'] = $file['uploader_username'] ?? 'Unknown User';
            }
            
            // Ensure download count is not null
            $file['download_count'] = $file['download_count'] ?? 0;
        }
        
        return $files;
        
    } catch(Exception $e) {
        error_log("Error fetching user files: " . $e->getMessage());
        return [];
    }
}

// Get total file count for pagination
function getTotalFileCount($pdo, $userId, $userDepartmentId, $search = '', $category = '') {
    try {
        $whereClause = "WHERE fo.department_id = ?";
        $params = [$userDepartmentId];
        
        if ($search) {
            $whereClause .= " AND (f.file_name LIKE ? OR f.original_name LIKE ? OR f.description LIKE ? OR fo.folder_name LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if ($category) {
            $whereClause .= " AND fo.category = ?";
            $params[] = $category;
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            {$whereClause}
            AND f.is_deleted = 0 
            AND fo.is_deleted = 0
        ");
        
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'];
        
    } catch(Exception $e) {
        error_log("Error counting files: " . $e->getMessage());
        return 0;
    }
}

// Get file statistics
function getFileStats($pdo, $userId, $userDepartmentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_files,
                SUM(f.file_size) as total_size,
                SUM(f.download_count) as total_downloads,
                COUNT(DISTINCT fo.category) as categories_count
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            WHERE fo.department_id = ?
            AND f.is_deleted = 0 
            AND fo.is_deleted = 0
        ");
        
        $stmt->execute([$userDepartmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(Exception $e) {
        error_log("Error fetching file stats: " . $e->getMessage());
        return ['total_files' => 0, 'total_size' => 0, 'total_downloads' => 0, 'categories_count' => 0];
    }
}

// Helper functions
function formatFileSize($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

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

function getUserInitials($fullName) {
    $names = explode(' ', trim($fullName));
    if (count($names) >= 2) {
        return strtoupper(substr($names[0], 0, 1) . substr($names[1], 0, 1));
    } else {
        return strtoupper(substr($fullName, 0, 2));
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    return date('M j, Y', strtotime($datetime));
}

// Get URL parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'uploaded_at';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$userDepartment = getUserDepartment($pdo, $userDepartmentId);
$files = getUserFiles($pdo, $currentUser['id'], $userDepartmentId, $search, $category, $sortBy, $sortOrder, $limit, $offset);
$totalFiles = getTotalFileCount($pdo, $currentUser['id'], $userDepartmentId, $search, $category);
$fileStats = getFileStats($pdo, $currentUser['id'], $userDepartmentId);
$totalPages = ceil($totalFiles / $limit);

// Update department code and image from userDepartment if available
if ($userDepartment) {
    $departmentCode = $userDepartment['department_code'] ?? 'OTHR';
    if (!$departmentImage && isset($userDepartment['department_image'])) {
        $departmentImage = $userDepartment['department_image'];
    }
}

// Department configuration
$departmentConfig = [
    'TED' => ['icon' => 'bxs-graduation', 'color' => '#f59e0b'],
    'MD' => ['icon' => 'bxs-business', 'color' => '#1e40af'],
    'FASD' => ['icon' => 'bx bx-water', 'color' => '#0284c7'],
    'ASD' => ['icon' => 'bxs-palette', 'color' => '#d946ef'],
    'ITD' => ['icon' => 'bxs-chip', 'color' => '#0f766e'],
    'NSTP' => ['icon' => 'bxs-user-check', 'color' => '#22c55e'],
    'OTHR' => ['icon' => 'bxs-file', 'color' => '#6b7280']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Files - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/files.css">

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
    <?php include 'components/sidebar.html'; ?>

    

    <!-- Content -->
    <section id="content">
        <?php include 'components/navbar.html'; ?>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>My Files</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="dashboard.php">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="" href="#">My Files</a>
                        </li>
                    </ul>
                </div>
                <button onclick="window.location.href='folders.php'" class="upload-btn">
                    <i class='bx bxs-cloud-upload'></i>
                    <span class="text">Upload Files</span>
                </button>
            </div>

            <!-- File Statistics -->
            <div class="stats-overview">
                <div class="stat-card files">
                    <div class="stat-icon">
                        <i class='bx bxs-file'></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($fileStats['total_files']); ?></div>
                        <div class="stat-label">Total Files</div>
                        <div class="stat-trend neutral">
                            <i class='bx bx-files'></i>
                            <span>In your department</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card size">
                    <div class="stat-icon">
                        <i class='bx bxs-hdd'></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo formatFileSize($fileStats['total_size']); ?></div>
                        <div class="stat-label">Total Storage Used</div>
                        <div class="stat-trend neutral">
                            <i class='bx bx-trending-up'></i>
                            <span>Department files</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card downloads">
                    <div class="stat-icon">
                        <i class='bx bxs-download'></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($fileStats['total_downloads']); ?></div>
                        <div class="stat-label">Total Downloads</div>
                        <div class="stat-trend positive">
                            <i class='bx bx-trending-up'></i>
                            <span>All time</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card categories">
                    <div class="stat-icon">
                        <i class='bx bxs-category'></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $fileStats['categories_count']; ?></div>
                        <div class="stat-label">File Categories</div>
                        <div class="stat-trend neutral">
                            <i class='bx bx-grid-alt'></i>
                            <span>Different types</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="filter-section">
                <div class="search-and-actions">
                    <div class="search-box">
                        <i class='bx bx-search'></i>
                        <input type="text" placeholder="Search files..." id="fileSearch" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="view-toggles">
                        <button class="view-toggle active" data-view="grid">
                            <i class='bx bx-grid-alt'></i>
                        </button>
                        <button class="view-toggle" data-view="list">
                            <i class='bx bx-list-ul'></i>
                        </button>
                    </div>
                </div>
                
                <div class="filter-controls">
                    <select id="categoryFilter" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($fileCategories as $key => $cat): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($category === $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="sortFilter" class="filter-select">
                        <option value="uploaded_at-DESC" <?php echo ($sortBy === 'uploaded_at' && $sortOrder === 'DESC') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="uploaded_at-ASC" <?php echo ($sortBy === 'uploaded_at' && $sortOrder === 'ASC') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="file_name-ASC" <?php echo ($sortBy === 'file_name' && $sortOrder === 'ASC') ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="file_name-DESC" <?php echo ($sortBy === 'file_name' && $sortOrder === 'DESC') ? 'selected' : ''; ?>>Name Z-A</option>
                        <option value="file_size-DESC" <?php echo ($sortBy === 'file_size' && $sortOrder === 'DESC') ? 'selected' : ''; ?>>Largest First</option>
                        <option value="file_size-ASC" <?php echo ($sortBy === 'file_size' && $sortOrder === 'ASC') ? 'selected' : ''; ?>>Smallest First</option>
                        <option value="download_count-DESC" <?php echo ($sortBy === 'download_count' && $sortOrder === 'DESC') ? 'selected' : ''; ?>>Most Downloaded</option>
                    </select>
                    
                    <button class="filter-btn" onclick="clearFilters()">
                        <i class='bx bx-x'></i>
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Files Grid/List -->
            <div class="files-container">
                <?php if (empty($files)): ?>
                    <div class="empty-state">
                        <i class='bx bx-folder-open empty-icon'></i>
                        <h3>No files found</h3>
                        <?php if ($search || $category): ?>
                            <p>No files match your current search criteria.</p>
                            <button onclick="clearFilters()" class="btn-clear">Clear Filters</button>
                        <?php else: ?>
                            <p>No files have been uploaded to your department yet.</p>
                            <button onclick="window.location.href='folders.php'" class="btn-upload">
                                <i class='bx bx-upload'></i>
                                Upload Your First File
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="files-grid" id="filesGrid">
                        <?php foreach ($files as $file): ?>
                            <div class="file-card" data-file-id="<?php echo $file['id']; ?>">
                                <div class="file-header">
                                    <div class="file-icon" style="background-color: <?php echo isset($fileCategories[$file['category']]) ? $fileCategories[$file['category']]['color'] : '#6b7280'; ?>">
                                        <i class='bx <?php echo getFileIcon($file['original_name'] ?: $file['file_name']); ?>'></i>
                                    </div>
                                    <div class="file-actions">
                                        <button class="action-btn" onclick="downloadFile(<?php echo $file['id']; ?>)" title="Download">
                                            <i class='bx bx-download'></i>
                                        </button>
                                        <button class="action-btn" onclick="viewFileDetails(<?php echo $file['id']; ?>)" title="View Details">
                                            <i class='bx bx-info-circle'></i>
                                        </button>
                                        <div class="dropdown">
                                            <button class="action-btn dropdown-toggle" onclick="toggleDropdown(this)">
                                                <i class='bx bx-dots-vertical-rounded'></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="#" onclick="shareFile(<?php echo $file['id']; ?>)">
                                                    <i class='bx bx-share'></i>
                                                    Share
                                                </a>
                                                <a href="#" onclick="copyFileLink(<?php echo $file['id']; ?>)">
                                                    <i class='bx bx-link'></i>
                                                    Copy Link
                                                </a>
                                                <a href="#" onclick="favoriteFile(<?php echo $file['id']; ?>)">
                                                    <i class='bx <?php echo $file['is_favorite'] ? 'bxs-heart' : 'bx-heart'; ?>'></i>
                                                    <?php echo $file['is_favorite'] ? 'Remove Favorite' : 'Add Favorite'; ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="file-content">
                                    <div class="file-name" title="<?php echo htmlspecialchars($file['original_name'] ?: $file['file_name']); ?>">
                                        <?php echo htmlspecialchars($file['original_name'] ?: $file['file_name']); ?>
                                    </div>
                                    
                                    <div class="file-meta">
                                        <div class="file-size"><?php echo formatFileSize($file['file_size']); ?></div>
                                        <div class="file-extension"><?php echo strtoupper($file['file_extension'] ?: pathinfo($file['file_name'], PATHINFO_EXTENSION)); ?></div>
                                    </div>
                                    
                                    <div class="file-category">
                                        <span class="category-tag" style="background-color: <?php echo isset($fileCategories[$file['category']]) ? $fileCategories[$file['category']]['color'] : '#6b7280'; ?>20; color: <?php echo isset($fileCategories[$file['category']]) ? $fileCategories[$file['category']]['color'] : '#6b7280'; ?>;">
                                            <?php echo isset($fileCategories[$file['category']]) ? htmlspecialchars($fileCategories[$file['category']]['name']) : 'Uncategorized'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="file-folder">
                                        <i class='bx bx-folder'></i>
                                        <span><?php echo htmlspecialchars($file['folder_name']); ?></span>
                                    </div>
                                    
                                    <div class="file-stats">
                                        <div class="stat-item">
                                            <i class='bx bx-download'></i>
                                            <span><?php echo $file['download_count']; ?></span>
                                        </div>
                                        <div class="upload-date">
                                            <?php echo timeAgo($file['uploaded_at']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="file-uploader">
                                        <div class="uploader-info">
                                            <div class="uploader-avatar">
                                                <?php echo getUserInitials($file['uploader_full_name']); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($file['uploader_full_name']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($file['description'])): ?>
                                        <div class="file-description">
                                            <?php echo htmlspecialchars($file['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($file['tags'])): ?>
                                        <div class="file-tags">
                                            <?php foreach ($file['tags'] as $tag): ?>
                                                <span class="file-tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                                    <i class='bx bx-chevron-left'></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="page-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-btn <?php echo ($i === $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="page-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="page-btn"><?php echo $totalPages; ?></a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                                    <i class='bx bx-chevron-right'></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <!-- File Details Modal -->
    <div id="fileDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class='bx bx-info-circle'></i> File Details</h3>
                <button class="close-btn" onclick="closeFileDetails()">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="modal-body" id="fileDetailsContent">
                <!-- File details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        window.userDepartmentId = <?php echo json_encode($userDepartmentId); ?>;
        window.fileCategories = <?php echo json_encode($fileCategories); ?>;
        window.currentPage = <?php echo $page; ?>;
        window.totalPages = <?php echo $totalPages; ?>;
        window.currentFilters = {
            search: '<?php echo addslashes($search); ?>',
            category: '<?php echo addslashes($category); ?>',
            sort: '<?php echo addslashes($sortBy); ?>',
            order: '<?php echo addslashes($sortOrder); ?>'
        };
    </script>
    <script src="assets/js/files.js"></script>
</body>
</html>