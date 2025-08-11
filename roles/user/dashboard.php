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

// Get some basic statistics for the dashboard
$stats = [
    'total_files' => 0,
    'total_folders' => 0,
    'storage_used' => 0,
    'recent_uploads' => []
];

try {
    // Get user's file count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as file_count, COALESCE(SUM(file_size), 0) as total_size
        FROM files f 
        JOIN folders fo ON f.folder_id = fo.id 
        WHERE f.uploaded_by = ? AND f.is_deleted = 0
    ");
    $stmt->execute([$currentUser['id']]);
    $fileStats = $stmt->fetch();
    
    $stats['total_files'] = $fileStats['file_count'];
    $stats['storage_used'] = $fileStats['total_size'];
    
    // Get user's folder count
    $stmt = $pdo->prepare("SELECT COUNT(*) as folder_count FROM folders WHERE created_by = ? AND is_deleted = 0");
    $stmt->execute([$currentUser['id']]);
    $folderStats = $stmt->fetch();
    
    $stats['total_folders'] = $folderStats['folder_count'];
    
    // Get recent uploads
    $stmt = $pdo->prepare("
        SELECT f.original_name, f.file_size, f.uploaded_at, fo.folder_name
        FROM files f
        JOIN folders fo ON f.folder_id = fo.id
        WHERE f.uploaded_by = ? AND f.is_deleted = 0
        ORDER BY f.uploaded_at DESC
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $stats['recent_uploads'] = $stmt->fetchAll();
    
} catch(Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
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
        /* Additional styles for profile image */
        .profile img {
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e6ed;
            transition: border-color 0.3s ease;
        }
        
        .profile:hover img {
            border-color: var(--blue);
        }
        
        /* Profile icon for users without department image */
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
        
        /* Department indicator */
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
                    <span class="text">All Files</span>
                </a>
            </li>
            <li>
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
            </div>

            <!-- Statistics Cards -->
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
            </ul>

            <!-- Recent Activity and Quick Actions -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Recent Uploads</h3>
                        <i class='bx bx-search'></i>
                        <i class='bx bx-filter'></i>
                    </div>
                    <?php if (!empty($stats['recent_uploads'])): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th>Folder</th>
                                    <th>Size</th>
                                    <th>Upload Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['recent_uploads'] as $file): ?>
                                    <tr>
                                        <td>
                                            <i class='bx bxs-file'></i>
                                            <p><?php echo htmlspecialchars($file['original_name']); ?></p>
                                        </td>
                                        <td><?php echo htmlspecialchars($file['folder_name']); ?></td>
                                        <td><?php echo formatFileSize($file['file_size']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class='bx bx-file' style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                            <p>No files uploaded yet</p>
                            <a href="upload.php" style="color: var(--blue); text-decoration: none; font-weight: 500;">Upload your first file</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="todo">
                    <div class="head">
                        <h3>Quick Actions</h3>
                        <i class='bx bx-plus'></i>
                        <i class='bx bx-filter'></i>
                    </div>
                    <ul class="todo-list">
                        <li class="not-completed">
                            <p>Upload new files</p>
                            <a href="upload.php"><i class='bx bx-upload'></i></a>
                        </li>
                        <li class="not-completed">
                            <p>Create new folder</p>
                            <a href="folders.php?action=create"><i class='bx bx-folder-plus'></i></a>
                        </li>
                        <li class="not-completed">
                            <p>Organize files</p>
                            <a href="files.php"><i class='bx bx-organize'></i></a>
                        </li>
                        <li class="not-completed">
                            <p>Share documents</p>
                            <a href="shared.php"><i class='bx bx-share'></i></a>
                        </li>
                        <li class="not-completed">
                            <p>Update profile</p>
                            <a href="profile.php"><i class='bx bx-user'></i></a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Account Information -->
            <div class="table-data" style="margin-top: 20px;">
                <div class="order" style="flex: 1;">
                    <div class="head">
                        <h3>Account Information</h3>
                        <i class='bx bx-info-circle'></i>
                    </div>
                    <div style="padding: 20px 0;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 14px;">
                            <div>
                                <strong>Department:</strong><br>
                                <span style="color: #666;"><?php echo htmlspecialchars($currentUser['department_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong>Employee ID:</strong><br>
                                <span style="color: #666;"><?php echo htmlspecialchars($currentUser['employee_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong>Position:</strong><br>
                                <span style="color: #666;"><?php echo htmlspecialchars($currentUser['position'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong>Member Since:</strong><br>
                                <span style="color: #666;"><?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></span>
                            </div>
                            <div>
                                <strong>Last Login:</strong><br>
                                <span style="color: #666;">
                                    <?php 
                                    if ($currentUser['last_login']) {
                                        echo date('M j, Y g:i A', strtotime($currentUser['last_login']));
                                    } else {
                                        echo 'First login';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div>
                                <strong>Account Status:</strong><br>
                                <span style="color: <?php echo $currentUser['is_approved'] ? '#38a169' : '#e53e3e'; ?>; font-weight: 500;">
                                    <?php echo $currentUser['is_approved'] ? 'Approved' : 'Pending'; ?>
                                </span>
                            </div>
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
    </script>
</body>
</html>