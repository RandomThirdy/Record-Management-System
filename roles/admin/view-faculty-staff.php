<<<<<<< HEAD
<?php include 'script/faculty-staff.php'; ?>
=======
<?php
    require_once '../../includes/config.php';

    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    // Get current admin information with department details
    $currentAdmin = getCurrentUser($pdo);

    if (!$currentAdmin) {
        header('Location: logout.php');
        exit();
    }

    // Debug: Check what data we're getting (remove this after testing)
    // echo "<pre>"; print_r($currentAdmin); echo "</pre>";

    // Get the admin's department ID - handle cases where it might not be set
    $adminDepartmentId = $currentAdmin['department_id'] ?? null;

    // If department_id isn't set, try to get it directly from database
    if (!$adminDepartmentId) {
        try {
            $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
            $stmt->execute([$currentAdmin['id']]);
            $result = $stmt->fetch();
            $adminDepartmentId = $result['department_id'] ?? null;
        } catch (Exception $e) {
            error_log("Error fetching department: " . $e->getMessage());
        }
    }

    // If we still don't have a department, show error
    if (!$adminDepartmentId) {
        die("Error: Your account is not assigned to any department. Please contact the system administrator.");
    }

    // Now get department details
    try {
        $stmt = $pdo->prepare("SELECT department_name, department_code FROM departments WHERE id = ?");
        $stmt->execute([$adminDepartmentId]);
        $department = $stmt->fetch();
        
        // Add department info to currentAdmin array
        $currentAdmin['department_name'] = $department['department_name'] ?? 'Department';
        $currentAdmin['department_code'] = $department['department_code'] ?? 'DEPT';
    } catch (Exception $e) {
        error_log("Error fetching department details: " . $e->getMessage());
        $currentAdmin['department_name'] = 'Department';
        $currentAdmin['department_code'] = 'DEPT';
    }
    // Get faculty staff from the same department
    $facultyStaff = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id, 
                u.username, 
                u.email, 
                u.name, 
                u.mi, 
                u.surname, 
                CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) AS full_name,
                u.position,
                u.profile_image,
                u.last_login,
                u.is_approved,
                d.department_name,
                d.department_code
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.role = 'user' 
            AND u.department_id = ?
            AND u.is_approved = 1
            ORDER BY u.surname, u.name
        ");
        $stmt->execute([$adminDepartmentId]);
        $facultyStaff = $stmt->fetchAll();
    } catch(Exception $e) {
        error_log("Error fetching faculty staff: " . $e->getMessage());
    }

// Function to get document submission stats for a faculty member
function getFacultySubmissionStats($pdo, $userId) {
    $stats = [
        'total_required' => 0,
        'total_submitted' => 0,
        'completion_rate' => 0,
        'latest_submission' => null
    ];

    try {
        // Get current year and semester
        $currentYear = date('Y');
        $currentSemester = (date('n') >= 6 && date('n') <= 11) ? '1st Semester' : '2nd Semester';

        // Count total required documents
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT document_type) as total_required
            FROM document_requirements
            WHERE academic_year = ? AND semester = ?
            AND is_required = 1
        ");
        $stmt->execute([$currentYear, $currentSemester]);
        $required = $stmt->fetch();
        $stats['total_required'] = $required['total_required'];

        // Count submitted documents
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT document_type) as total_submitted, 
                   MAX(submitted_at) as latest_submission
            FROM faculty_document_submissions
            WHERE faculty_id = ?
            AND academic_year = ?
            AND semester = ?
        ");
        $stmt->execute([$userId, $currentYear, $currentSemester]);
        $submitted = $stmt->fetch();
        $stats['total_submitted'] = $submitted['total_submitted'];
        $stats['latest_submission'] = $submitted['latest_submission'];

        // Calculate completion rate
        if ($stats['total_required'] > 0) {
            $stats['completion_rate'] = round(($stats['total_submitted'] / $stats['total_required']) * 100, 1);
        }
    } catch(Exception $e) {
        error_log("Error getting faculty stats: " . $e->getMessage());
    }

    return $stats;
}
?>
>>>>>>> b411beba4dc1d4f99bef406dd07a7c968a0e04ee

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Faculty Staff - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
<<<<<<< HEAD
    <link rel="stylesheet" href="assets/css/style1.css">
=======
    <link rel="stylesheet" href="../../assets/css/style.css">
>>>>>>> b411beba4dc1d4f99bef406dd07a7c968a0e04ee
    <style>
        .faculty-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            transition: transform 0.2s ease;
        }
        
        .faculty-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .faculty-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid #e0e6ed;
        }
        
        .faculty-details {
            flex: 1;
        }
        
        .faculty-name {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .faculty-position {
            color: #666;
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .faculty-stats {
            display: flex;
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-weight: 600;
            font-size: 16px;
            color: var(--blue);
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        
        .last-login {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-view, .btn-upload {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .btn-view {
            background-color: var(--blue);
            color: white;
            border: none;
        }
        
        .btn-view:hover {
            background-color: #0056b3;
        }
        
        .btn-upload {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .btn-upload:hover {
            background-color: #218838;
        }
        
        .department-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .department-icon {
            width: 50px;
            height: 50px;
            background-color: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .department-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        
        .department-code {
            color: #666;
            font-size: 14px;
            margin: 5px 0 0 0;
        }
        
        .no-faculty {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-faculty i {
            font-size: 50px;
            margin-bottom: 15px;
            display: block;
            color: #ccc;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--blue);
        }
    </style>
</head>
<body>
<<<<<<< HEAD
    <?php include 'components/sidebar.html'; ?>

    <section id="content">
        <?php include 'components/navbar.html'; ?>
=======
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
                <a href="social-feed.php">
                    <i class='bx bxs-message-rounded-dots'></i>
                    <span class="text">Social Feed</span>
                </a>
            </li>
            <li>
                <a href="document-tracker.php">
                    <i class='bx bxs-file-find'></i>
                    <span class="text">Document Tracker</span>
                </a>
            </li>
            <li class="active">
                <a href="view-faculty-staff.php">
                    <i class='bx bxs-user-voice'></i>
                    <span class="text">View Faculty Staff</span>
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
                <a href="shared.php">
                    <i class='bx bxs-share'></i>
                    <span class="text">Shared Files</span>
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
                <a href="profile.php">
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

    <section id="content">
        <nav>
            <i class='bx bx-menu'></i>
            <form action="#">
                <div class="form-input">
                    <input type="search" placeholder="Search faculty..." class="search-input" id="facultySearch">
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="#" class="notification">
                <i class='bx bxs-bell'></i>
                <span class="num">8</span>
            </a>
            <a href="#" class="profile">
                <img src="../../img/default-avatar.png" alt="Profile">
            </a>
        </nav>
>>>>>>> b411beba4dc1d4f99bef406dd07a7c968a0e04ee

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Faculty Staff</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="dashboard.php">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="#">View Faculty Staff</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="department-header">
                <div class="department-icon">
                    <?php echo substr($currentAdmin['department_name'] ?? 'DEPT', 0, 2); ?>
                </div>
                <div>
                    <h2 class="department-title"><?php echo htmlspecialchars($currentAdmin['department_name'] ?? 'Department'); ?></h2>
                    <p class="department-code"><?php echo htmlspecialchars($currentAdmin['department_code'] ?? 'DEPT'); ?></p>
                </div>
            </div>

            <?php if (!empty($facultyStaff)): ?>
                <div class="faculty-list">
                    <?php foreach ($facultyStaff as $faculty): ?>
                        <?php 
                        $stats = getFacultySubmissionStats($pdo, $faculty['id']);
                        $avatarSrc = $faculty['profile_image'] ? '../../' . $faculty['profile_image'] : '../../img/default-avatar.png';
                        ?>
                        <div class="faculty-card" data-name="<?php echo htmlspecialchars(strtolower($faculty['full_name'])); ?>">
                            <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="<?php echo htmlspecialchars($faculty['full_name']); ?>" class="faculty-avatar">
                            <div class="faculty-details">
                                <h3 class="faculty-name"><?php echo htmlspecialchars($faculty['full_name']); ?></h3>
                                <p class="faculty-position"><?php echo htmlspecialchars($faculty['position'] ?? 'Faculty Member'); ?></p>
                                
                                <div class="faculty-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $stats['total_submitted']; ?>/<?php echo $stats['total_required']; ?></div>
                                        <div class="stat-label">Documents</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $stats['completion_rate']; ?>%</div>
                                        <div class="stat-label">Complete</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $stats['completion_rate']; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($faculty['last_login']): ?>
                                    <p class="last-login">Last active: <?php echo date('M j, Y g:i A', strtotime($faculty['last_login'])); ?></p>
                                <?php else: ?>
                                    <p class="last-login">Never logged in</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="submission_tracker.php?user_id=<?php echo $faculty['id']; ?>" class="btn-view">
                                    <i class='bx bx-show'></i> View Submissions
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-faculty">
                    <i class='bx bx-user-x'></i>
                    <h3>No Faculty Staff Found</h3>
                    <p>There are currently no faculty members in your department.</p>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script>
        // Faculty search functionality
        const facultySearch = document.getElementById('facultySearch');
        const facultyCards = document.querySelectorAll('.faculty-card');
        
        facultySearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            facultyCards.forEach(card => {
                const facultyName = card.getAttribute('data-name');
                if (facultyName.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
<<<<<<< HEAD
=======

        // Toggle sidebar
        const menuBar = document.querySelector('#content nav .bx.bx-menu');
        const sidebar = document.getElementById('sidebar');
        menuBar.addEventListener('click', function() {
            sidebar.classList.toggle('hide');
        });

        // Dark mode toggle
        const switchMode = document.getElementById('switch-mode');
        switchMode.addEventListener('change', function() {
            document.body.classList.toggle('dark', this.checked);
        });
>>>>>>> b411beba4dc1d4f99bef406dd07a7c968a0e04ee
    </script>
</body>
</html>