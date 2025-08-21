<<<<<<< HEAD
<?php include 'script/tracker.php'; ?>
=======
<?php
    require_once '../../includes/config.php';
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    $currentUser = getCurrentUser($pdo);
    if (!$currentUser || !$currentUser['is_approved']) {
        header('Location: logout.php');
        exit();
    }

    if ($currentUser['role'] !== 'admin') {
        header('Location: ../admin/dashboard.php');
        exit();
    }

    $admin_department_id = null;
    $admin_department_name = 'Unknown Department';

    try {
        if (isset($currentUser['department_id']) && !empty($currentUser['department_id'])) {
            $admin_department_id = $currentUser['department_id'];
        }

        if (!$admin_department_id) {
            $stmt = $pdo->prepare("
                SELECT u.department_id, d.department_name, d.department_code
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.id = ? AND u.role = 'admin'
            ");
            $stmt->execute([$currentUser['id']]);
            $user_dept = $stmt->fetch();
            
            if ($user_dept && $user_dept['department_id']) {
                $admin_department_id = $user_dept['department_id'];
                $admin_department_name = $user_dept['department_name'] ?? 'Department ' . $admin_department_id;
            }
        } else {
            $stmt = $pdo->prepare("SELECT department_name, department_code FROM departments WHERE id = ?");
            $stmt->execute([$admin_department_id]);
            $dept = $stmt->fetch();
            if ($dept) {
                $admin_department_name = $dept['department_name'];
            }
        }

        if (!$admin_department_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admin_count = $stmt->fetch()['admin_count'];

            if ($admin_count == 1) {
                $admin_department_name = 'System Administrator (All Departments)';
            } else {
                die("
                    <div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>
                        <h2 style='color: #dc3545;'>Department Assignment Required</h2>
                        <p>Your admin account is not assigned to any department.</p>
                        <p>Please contact the system administrator to assign your account to a department.</p>
                        <a href='logout.php' style='color: #007bff; text-decoration: none;'>← Logout and Contact Admin</a>
                    </div>
                ");
            }
        }
        
    } catch (Exception $e) {
        error_log("Department ID Error for Admin " . $currentUser['id'] . ": " . $e->getMessage());
        die("
            <div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>
                <h2 style='color: #dc3545;'>System Error</h2>
                <p>Unable to determine department assignment. Please contact system administrator.</p>
                <p><small>Error ID: " . uniqid() . "</small></p>
                <a href='logout.php' style='color: #007bff; text-decoration: none;'>← Logout</a>
            </div>
        ");
    }

    // Check if parent_id column exists in departments table
    $hasParentId = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM departments LIKE 'parent_id'");
        $hasParentId = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error checking parent_id column: " . $e->getMessage());
        $hasParentId = false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_file_details') {
        header('Content-Type: application/json');
        
        try {
            $faculty_id = $_GET['faculty_id'] ?? 0;
            $doc_type = $_GET['document_type'] ?? '';
            $semester = $_GET['semester'] ?? '';
            $academic_year = $_GET['academic_year'] ?? date('Y');
            
            // Convert semester format if needed (from UI format to DB format)
            if (strpos($semester, 'AY') !== false) {
                $semesterMap = [
                    '1st sem AY 2024-2025' => '1st Semester',
                    '2nd sem AY 2024-2025' => '2nd Semester',
                    '1st sem AY 2025-2026' => '1st Semester',
                    '2nd sem AY 2025-2026' => '2nd Semester'
                ];
                $semester = $semesterMap[$semester] ?? '2nd Semester';
            } else {
                $faculty_check_query = "
                    SELECT u.id, u.department_id, d.department_name, NULL as parent_id
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.id = ? AND u.role = 'user'
                ";
            }

            $stmt = $pdo->prepare($faculty_check_query);
            $stmt->execute([$faculty_id]);
            $faculty_dept = $stmt->fetch();
            
            if (!$faculty_dept) {
                throw new Exception('Faculty member not found');
            }

            $can_view = false;
            if (!$admin_department_id) {
                $can_view = true;
            } else {
                if ($faculty_dept['department_id'] == $admin_department_id || 
                    ($hasParentId && $faculty_dept['parent_id'] == $admin_department_id)) {
                    $can_view = true;
                }
            }
            
            if (!$can_view) {
                throw new Exception('Access denied: Faculty not in your department');
            }
            
            // UPDATED QUERY - Now uses the same structure as user system
            $stmt = $pdo->prepare("
                SELECT 
                    df.id, df.file_name, df.file_path, df.file_size, 
                    df.uploaded_at, df.description,
                    fds.document_type, fds.semester, fds.academic_year,
                    CONCAT(u.name, ' ', u.surname) as uploader_name,
                    u.employee_id
                FROM document_files df
                INNER JOIN faculty_document_submissions fds ON df.submission_id = fds.id
                INNER JOIN users u ON fds.faculty_id = u.id
                WHERE fds.faculty_id = ? 
                AND fds.document_type = ?
                AND fds.semester = ?
                AND fds.academic_year = ?
                ORDER BY df.uploaded_at DESC
            ");
            $stmt->execute([$faculty_id, $doc_type, $semester, $academic_year]);
            $files = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'files' => $files]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    $semester = $_GET['semester'] ?? '2nd sem AY 2024-2025';
    $department_filter = $_GET['department'] ?? '';
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? ''; 

    $document_types = [
        'IPCR Accomplishment',
        'IPCR Target',
        'Workload',
        'Course Syllabus',
        'Course Syllabus Acceptance Form',
        'Exam',
        'TOS',
        'Class Record',
        'Grading Sheets',
        'Attendance Sheet',
        "Stakeholder's Feedback Form w/ Summary",
        'Consultation',
        'Lecture',
        'Activities',
        'CEIT-QF-03 Discussion Form',
        'Others'
    ];

    $faculty_conditions = ["u.role = 'user'", "u.is_approved = 1"];
    $faculty_params = [];

    if ($admin_department_id) {
        if (!empty($department_filter)) {
            // Check if the filtered department is allowed for this admin
            if ($hasParentId) {
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE (id = ? OR parent_id = ?) AND id = ? AND is_active = 1
                ");
                $stmt->execute([$admin_department_id, $admin_department_id, $department_filter]);
                $allowed_dept = $stmt->fetch();
                
                if ($allowed_dept) {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $department_filter;
                } else {
                    // Restrict to admin's department and sub-departments
                    $faculty_conditions[] = "(u.department_id = ? OR d.parent_id = ?)";
                    $faculty_params[] = $admin_department_id;
                    $faculty_params[] = $admin_department_id;
                }
            } else {
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE id = ? AND id = ? AND is_active = 1
                ");
                $stmt->execute([$admin_department_id, $department_filter]);
                $allowed_dept = $stmt->fetch();
                
                if ($allowed_dept) {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $department_filter;
                } else {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $admin_department_id;
                }
            }
        } else {
            // Show admin's department and sub-departments
            if ($hasParentId) {
                $faculty_conditions[] = "(u.department_id = ? OR d.parent_id = ?)";
                $faculty_params[] = $admin_department_id;
                $faculty_params[] = $admin_department_id;
            } else {
                $faculty_conditions[] = "u.department_id = ?";
                $faculty_params[] = $admin_department_id;
            }
        }
    } else {
        if (!empty($department_filter)) {
            $faculty_conditions[] = "u.department_id = ?";
            $faculty_params[] = $department_filter;
        }
    }

    if (!empty($search)) {
        $faculty_conditions[] = "(CONCAT(u.name, ' ', u.surname) LIKE ? OR u.employee_id LIKE ? OR u.email LIKE ?)";
        $search_term = '%' . $search . '%';
        $faculty_params[] = $search_term;
        $faculty_params[] = $search_term;
        $faculty_params[] = $search_term;
    }

    $faculty_query = "
        SELECT u.id, u.name, u.mi, u.surname, u.employee_id, u.email, u.position,
            d.department_name, d.department_code, d.id as dept_id
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE " . implode(' AND ', $faculty_conditions) . "
        ORDER BY d.department_name, u.surname, u.name
    ";

    $stmt = $pdo->prepare($faculty_query);
    $stmt->execute($faculty_params);
    $faculty = $stmt->fetchAll();

    $file_submissions = [];
    if (!empty($faculty)) {
        $faculty_ids = array_column($faculty, 'id');
        $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
        
        // UPDATED QUERY - Now compatible with user upload system
        $file_query = "
            SELECT 
                fds.faculty_id, 
                fds.document_type, 
                COUNT(df.id) as file_count,
                MAX(df.uploaded_at) as latest_upload,
                MIN(df.uploaded_at) as first_upload,
                SUM(df.file_size) as total_size,
                fds.academic_year,
                fds.semester
            FROM faculty_document_submissions fds
            LEFT JOIN document_files df ON fds.id = df.submission_id 
            WHERE fds.faculty_id IN ($placeholders)
            AND (df.id IS NOT NULL OR fds.id IS NOT NULL)
            GROUP BY fds.faculty_id, fds.document_type, fds.academic_year, fds.semester
        ";
        
        $stmt = $pdo->prepare($file_query);
        $stmt->execute($faculty_ids);
        
        while ($row = $stmt->fetch()) {
            // Create a unique key that includes academic period
            $key = $row['faculty_id'] . '_' . $row['academic_year'] . '_' . $row['semester'];
            
            $file_submissions[$row['faculty_id']][$row['document_type']] = [
                'file_count' => $row['file_count'] ?: 0,
                'latest_upload' => $row['latest_upload'],
                'first_upload' => $row['first_upload'],
                'total_size' => $row['total_size'] ?: 0,
                'status' => $row['file_count'] > 0 ? 'submitted' : 'pending',
                'academic_year' => $row['academic_year'],
                'semester' => $row['semester']
            ];
        }
    }

    // Apply status filter
    if (!empty($status_filter)) {
        $filtered_faculty = [];
        foreach ($faculty as $f) {
            $submitted_count = 0;
            foreach ($document_types as $dt) {
                if (isset($file_submissions[$f['id']][$dt])) {
                    $submitted_count++;
                }
            }
            
            $completion_percentage = count($document_types) > 0 ? ($submitted_count / count($document_types)) * 100 : 0;
            
            $include = false;
            switch ($status_filter) {
                case 'complete':
                    $include = $completion_percentage == 100;
                    break;
                case 'partial':
                    $include = $completion_percentage > 0 && $completion_percentage < 100;
                    break;
                case 'none':
                    $include = $completion_percentage == 0;
                    break;
                default:
                    $include = true;
            }
            
            if ($include) {
                $filtered_faculty[] = $f;
            }
        }
        $faculty = $filtered_faculty;
    }

    // Calculate statistics
    $total_faculty = count($faculty);
    $total_possible = $total_faculty * count($document_types);
    $submitted_count = 0;
    $complete_faculty = 0;

    foreach ($faculty as $f) {
        $faculty_submitted = 0;
        foreach ($document_types as $dt) {
            if (isset($file_submissions[$f['id']][$dt])) {
                $submitted_count++;
                $faculty_submitted++;
            }
        }
        if ($faculty_submitted == count($document_types)) {
            $complete_faculty++;
        }
    }

    $completion_rate = $total_possible > 0 ? round(($submitted_count / $total_possible) * 100, 1) : 0;
    $faculty_completion_rate = $total_faculty > 0 ? round(($complete_faculty / $total_faculty) * 100, 1) : 0;

    // Get departments for filter - handle parent_id conditionally
    $dept_conditions = ["is_active = 1"];
    $dept_params = [];

    if ($admin_department_id) {
        if ($hasParentId) {
            $dept_conditions[] = "(id = ? OR parent_id = ?)";
            $dept_params[] = $admin_department_id;
            $dept_params[] = $admin_department_id;
        } else {
            $dept_conditions[] = "id = ?";
            $dept_params[] = $admin_department_id;
        }
    }

    $dept_query = "SELECT * FROM departments WHERE " . implode(' AND ', $dept_conditions) . " ORDER BY department_name";
    $stmt = $pdo->prepare($dept_query);
    $stmt->execute($dept_params);
    $departments = $stmt->fetchAll();

    $semester_options = [
        '1st sem AY 2024-2025' => '1st Semester AY 2024-2025',
        '2nd sem AY 2024-2025' => '2nd Semester AY 2024-2025',
        '1st sem AY 2025-2026' => '1st Semester AY 2025-2026',
        '2nd sem AY 2025-2026' => '2nd Semester AY 2025-2026'
    ];

    function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }

    // Add semester/year filter support
    $selectedYear = $_GET['year'] ?? date('Y');
    $selectedSemester = $_GET['semester'] ?? '2nd sem AY 2024-2025';

    // Convert semester format for compatibility
    $semesterMap = [
        '2nd sem AY 2024-2025' => '2nd Semester',
        '1st sem AY 2024-2025' => '1st Semester',
        '2nd sem AY 2025-2026' => '2nd Semester',
        '1st sem AY 2025-2026' => '1st Semester'
    ];

    $normalizedSemester = $semesterMap[$selectedSemester] ?? '2nd Semester';

    // Extract year from semester string if not provided separately
    if (strpos($selectedSemester, 'AY') !== false) {
        preg_match('/AY (\d{4})-\d{4}/', $selectedSemester, $matches);
        if (!empty($matches[1])) {
            $selectedYear = (int)$matches[1];
        }
    }

    // Updated file query with year/semester filtering
    $file_submissions = [];
    if (!empty($faculty)) {
        $faculty_ids = array_column($faculty, 'id');
        $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
        
        // Build the base query
        $file_query = "
            SELECT 
                fds.faculty_id, 
                fds.document_type, 
                COUNT(df.id) as file_count,
                MAX(df.uploaded_at) as latest_upload,
                MIN(df.uploaded_at) as first_upload,
                SUM(df.file_size) as total_size,
                fds.academic_year,
                fds.semester
            FROM faculty_document_submissions fds
            LEFT JOIN document_files df ON fds.id = df.submission_id 
            WHERE fds.faculty_id IN ($placeholders)
            AND fds.academic_year = ?
            AND fds.semester = ?
            GROUP BY fds.faculty_id, fds.document_type, fds.academic_year, fds.semester
        ";
        
        $stmt = $pdo->prepare($file_query);
        
        // Combine parameters: faculty_ids first, then year and semester
        $params = array_merge($faculty_ids, [$selectedYear, $normalizedSemester]);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch()) {
            $file_submissions[$row['faculty_id']][$row['document_type']] = [
                'file_count' => $row['file_count'] ?: 0,
                'latest_upload' => $row['latest_upload'],
                'first_upload' => $row['first_upload'],
                'total_size' => $row['total_size'] ?: 0,
                'status' => $row['file_count'] > 0 ? 'submitted' : 'pending',
                'academic_year' => $row['academic_year'],
                'semester' => $row['semester']
            ];
        }
    }

?>
>>>>>>> b411beba4dc1d4f99bef406dd07a7c968a0e04ee

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracker - <?= htmlspecialchars($admin_department_name) ?></title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
<<<<<<< HEAD
    <link rel="stylesheet" href="assets/css/style1.css">
=======
    <link rel="stylesheet" href="assets/css/style.css">
>>>>>>> b411beba4dc1d4f99bef406dd07a7c968a0e04ee
    <link rel="stylesheet" href="assets/css/doc-track.css">
    <style>
        .department-info {
            background: linear-gradient(135deg, var(--blue), var(--light-blue));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .error-alert {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success-alert {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-advanced {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .faculty-actions {
            margin-top: 10px;
        }
        .faculty-actions button {
            margin-right: 5px;
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-message {
            background: var(--blue);
            color: white;
        }
        .btn-view {
            background: var(--light);
            color: var(--dark);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
<<<<<<< HEAD
    <?php include 'components/sidebar.html'; ?>
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
            <li class="active">
                <a href="document-tracker.php">
                    <i class='bx bxs-file-find'></i>
                    <span class="text">Document Tracker</span>
                </a>
            </li>
            <li>
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
>>>>>>> b411beba4dc1d4f99bef406dd07a7c968a0e04ee

    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
<<<<<<< HEAD
        <?php include 'components/navbar.html'; ?>
=======
        <nav>
            <i class='bx bx-menu'></i>
            <a href="#" class="nav-link">Categories</a>
            <form action="#" onsubmit="searchFaculty(event)">
                <div class="form-input">
                    <input type="search" placeholder="Search faculty..." value="<?= htmlspecialchars($search) ?>" name="search">
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="#" class="notification">
                <i class='bx bxs-bell'></i>
                <span class="num">8</span>
            </a>
            <a href="profile.php" class="profile">
                <img src="../../img/default-avatar.png" alt="Profile">
            </a>
        </nav>
>>>>>>> b411beba4dc1d4f99bef406dd07a7c968a0e04ee

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Document Submission Tracker</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Admin</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Document Tracker</a></li>
                    </ul>
                </div>
                <div class="head-actions">
                    <button class="btn btn-success" onclick="exportTable()">
                        <i class='bx bx-export'></i> Export CSV
                    </button>
                    <button class="btn btn-info" onclick="generateReport()">
                        <i class='bx bx-bar-chart'></i> Generate Report
                    </button>
                    <button class="btn btn-primary" onclick="refreshData()">
                        <i class='bx bx-refresh'></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Department Info -->
            <div class="department-info">
                <h2><?= htmlspecialchars($admin_department_name) ?></h2>
                <p>Document Submission Tracking System</p>
                <?php if ($admin_department_id): ?>
                    <small>Department ID: <?= htmlspecialchars($admin_department_id) ?></small>
                <?php endif; ?>
                <?php if (!$hasParentId): ?>
                    <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 5px; margin-top: 10px;">
                        <small><i class='bx bx-info-circle'></i> Hierarchical departments not enabled</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= $total_faculty ?></h3>
                    <p>Total Faculty</p>
                    <small><?= $admin_department_id ? 'In Department' : 'System Wide' ?></small>
                </div>
                <div class="stat-card">
                    <h3><?= $submitted_count ?></h3>
                    <p>Documents Submitted</p>
                    <small>Out of <?= $total_possible ?> required</small>
                </div>
                <div class="stat-card">
                    <h3><?= $complete_faculty ?></h3>
                    <p>Complete Submissions</p>
                    <small><?= $faculty_completion_rate ?>% of faculty</small>
                </div>
                <div class="stat-card completion">
                    <h3><?= $completion_rate ?>%</h3>
                    <p>Overall Completion</p>
                    <small>Document submission rate</small>
                </div>
            </div>

            <!-- Advanced Filters -->
            <div class="filter-advanced" style="margin-bottom: 20px;">

            <!-- <div class="filter-group">
                    <label><strong>Academic Year:</strong></label>
                    <select name="year" class="form-control">
                        <option value="2023" ' . ($selectedYear == 2023 ? 'selected' : '') . '>2023</option>
                        <option value="2024" ' . ($selectedYear == 2024 ? 'selected' : '') . '>2024</option>
                        <option value="2025" ' . ($selectedYear == 2025 ? 'selected' : '') . '>2025</option>
                    </select>
                </div> -->
                
                <?php
                    echo '
                    <div class="filter-advanced" style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 15px;"><i class="bx bx-filter-alt"></i> Period Filter</h4>
                        <form method="GET" style="display: flex; gap: 15px; align-items: center;">
                            
                            
                            <div class="filter-group">
                                <label><strong>Semester:</strong></label>
                                <select name="semester" class="form-control">
                                    <option value="1st sem AY 2024-2025" ' . ($selectedSemester == '1st sem AY 2024-2025' ? 'selected' : '') . '>1st Semester AY 2024-2025</option>
                                    <option value="2nd sem AY 2024-2025" ' . ($selectedSemester == '2nd sem AY 2024-2025' ? 'selected' : '') . '>2nd Semester AY 2024-2025</option>
                                    <option value="1st sem AY 2025-2026" ' . ($selectedSemester == '1st sem AY 2025-2026' ? 'selected' : '') . '>1st Semester AY 2025-2026</option>
                                    <option value="2nd sem AY 2025-2026" ' . ($selectedSemester == '2nd sem AY 2025-2026' ? 'selected' : '') . '>2nd Semester AY 2025-2026</option>
                                </select>
                            </div>
                            
                            <!-- Preserve other filters -->
                            <input type="hidden" name="department" value="' . htmlspecialchars($department_filter) . '">
                            <input type="hidden" name="search" value="' . htmlspecialchars($search) . '">
                            <input type="hidden" name="status" value="' . htmlspecialchars($status_filter) . '">
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-filter"></i> Apply Filter
                            </button>
                        </form>
                    </div>';
                ?>
            </div>


            <!-- Document Table -->
            <div class="table-container">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: var(--dark);">CAVITE STATE UNIVERSITY - NAIC CAMPUS</h2>
                    <p style="margin: 5px 0; color: var(--blue); font-weight: 600;">
                        <?= htmlspecialchars($admin_department_name) ?> - Document Submission Tracker
                    </p>
                    <p style="margin: 5px 0; color: var(--blue);"><?= htmlspecialchars($semester_options[$semester] ?? $semester) ?></p>
                    
                    <?php if (!empty($search) || !empty($department_filter) || !empty($status_filter)): ?>
                        <div style="margin: 10px 0; padding: 10px; background: #e3f2fd; border-radius: 5px;">
                            <small><strong>Active Filters:</strong>
                                <?php if (!empty($search)): ?>
                                    Search: "<?= htmlspecialchars($search) ?>"
                                <?php endif; ?>
                                <?php if (!empty($department_filter)): ?>
                                    <?php 
                                    $filtered_dept = array_filter($departments, function($d) use ($department_filter) {
                                        return $d['id'] == $department_filter;
                                    });
                                    $dept_name = !empty($filtered_dept) ? array_values($filtered_dept)[0]['department_name'] : 'Unknown';
                                    ?>
                                    | Department: "<?= htmlspecialchars($dept_name) ?>"
                                <?php endif; ?>
                                <?php if (!empty($status_filter)): ?>
                                    | Status: "<?= htmlspecialchars(ucfirst($status_filter)) ?>"
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($faculty)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                        <i class='bx bx-user-x' style="font-size: 64px; display: block; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3>No Faculty Members Found</h3>
                        <?php if (!empty($search) || !empty($department_filter) || !empty($status_filter)): ?>
                            <p>No faculty members match your current filters.</p>
                            <a href="document-tracker.php" class="btn btn-primary">Clear Filters</a>
                        <?php else: ?>
                            <p>No faculty members are assigned to your department: <strong><?= htmlspecialchars($admin_department_name) ?></strong></p>
                            <?php if ($admin_department_id): ?>
                                <p><small>Department ID: <?= htmlspecialchars($admin_department_id) ?></small></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 15px; text-align: right;">
                        <small style="color: #6c757d;">
                            Showing <?= count($faculty) ?> faculty member(s) | 
                            Last updated: <?= date('M d, Y h:i A') ?>
                        </small>
                    </div>
                    
                    <table class="doc-table" id="documentTable">
                        <thead>
                            <tr>
                                <th rowspan="2" class="faculty-cell" style="min-width: 200px;">
                                    FACULTY STAFF
                                    <small style="display: block; font-weight: normal;">Click for details</small>
                                </th>
                                <th colspan="<?= count($document_types) ?>">DOCUMENTS (Auto-tracked by File Uploads)</th>
                                <th rowspan="2" style="min-width: 100px;">PROGRESS</th>
                            </tr>
                            <tr>
                                <?php foreach ($document_types as $doc_type): ?>
                                    <th title="<?= htmlspecialchars($doc_type) ?>" style="writing-mode: vertical-lr; text-orientation: mixed; min-width: 40px;">
                                        <span style="writing-mode: horizontal-tb;">
                                            <?= strlen($doc_type) > 15 ? substr($doc_type, 0, 15) . '...' : $doc_type ?>
                                        </span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faculty as $staff): 
                                $staff_submitted = 0;
                                foreach ($document_types as $dt) {
                                    if (isset($file_submissions[$staff['id']][$dt])) {
                                        $staff_submitted++;
                                    }
                                }
                                $staff_progress = count($document_types) > 0 ? round(($staff_submitted / count($document_types)) * 100) : 0;
                            ?>
                            <tr data-faculty-id="<?= $staff['id'] ?>" data-progress="<?= $staff_progress ?>">
                                    <td class="faculty-cell" onclick="viewFacultyDetails(<?= $staff['id'] ?>)" style="cursor: pointer;">
                                        <div style="padding: 10px;">
                                            <strong><?= htmlspecialchars($staff['surname'] . ', ' . $staff['name']) ?>
                                            <?= !empty($staff['mi']) ? ' ' . htmlspecialchars($staff['mi']) . '.' : '' ?></strong>
                                            
                                            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                                                <div><i class='bx bx-building'></i> <?= htmlspecialchars($staff['department_name'] ?? 'No Department') ?></div>
                                                
                                                <?php if (!empty($staff['employee_id'])): ?>
                                                    <div><i class='bx bx-id-card'></i> ID: <?= htmlspecialchars($staff['employee_id']) ?></div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($staff['position'])): ?>
                                                    <div><i class='bx bx-user-circle'></i> <?= htmlspecialchars($staff['position']) ?></div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($staff['email'])): ?>
                                                    <div><i class='bx bx-envelope'></i> <?= htmlspecialchars($staff['email']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="faculty-actions">
                                                <button class="btn-message" onclick="event.stopPropagation(); sendMessage(<?= $staff['id'] ?>)" title="Send Message">
                                                    <i class='bx bx-message'></i>
                                                </button>
                                                <button class="btn-view" onclick="event.stopPropagation(); viewAllSubmissions(<?= $staff['id'] ?>)" title="View All Files">
                                                    <i class='bx bx-file'></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <?php foreach ($document_types as $doc_type): ?>
                                    <?php 
                                        $file_submission = $file_submissions[$staff['id']][$doc_type] ?? null;
                                        $has_files = !empty($file_submission);
                                        
                                        $cell_class = 'status-cell';
                                        if ($has_files) {
                                            $cell_class .= ' submitted';
                                        } else {
                                            $cell_class .= ' not-submitted';
                                    }
                                    ?>
                                    <td class="<?= $cell_class ?>" 
                                        onclick="<?= $has_files ? 
                                            'viewDetails('.$staff['id'].',\''.htmlspecialchars($doc_type, ENT_QUOTES).'\',\''.htmlspecialchars($normalizedSemester, ENT_QUOTES).'\','.$selectedYear.')' : 
                                            'showNotSubmittedDetails('.$staff['id'].',\''.htmlspecialchars($doc_type, ENT_QUOTES).'\',\''.htmlspecialchars($normalizedSemester, ENT_QUOTES).'\','.$selectedYear.')' 
                                        ?>"
                                        style="cursor: pointer; text-align: center; padding: 8px;">
                                        
                                        <?php if ($has_files): ?>
                                            <div class="file-count-badge" title="<?= $file_submission['file_count'] ?> file(s) uploaded">
                                                <?= $file_submission['file_count'] ?>
                                            </div>
                                            
                                            <div class="status-indicator submitted">
                                                <i class='bx bx-check'></i>
                                            </div>
                                            
                                            <div class="submission-info">
                                                <small title="Latest upload: <?= date('M d, Y h:i A', strtotime($file_submission['latest_upload'])) ?>">
                                                    <?= date('m/d/Y', strtotime($file_submission['latest_upload'])) ?>
                                                </small>
                                                <?php if ($file_submission['total_size'] > 0): ?>
                                                    <small style="display: block;"><?= formatFileSize($file_submission['total_size']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="status-indicator not-submitted">
                                                <i class='bx bx-x'></i>
                                            </div>
                                            
                                            <div class="submission-info">
                                                <small>Not Submitted</small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                    
                                    <td style="text-align: center; padding: 10px;">
                                        <div class="progress-circle" data-progress="<?= $staff_progress ?>">
                                            <svg width="50" height="50">
                                                <circle cx="25" cy="25" r="20" fill="none" stroke="#e0e0e0" stroke-width="4"/>
                                                <circle cx="25" cy="25" r="20" fill="none" 
                                                        stroke="<?= $staff_progress == 100 ? '#28a745' : ($staff_progress >= 50 ? '#ffc107' : '#dc3545') ?>" 
                                                        stroke-width="4" 
                                                        stroke-dasharray="<?= 2 * M_PI * 20 ?>" 
                                                        stroke-dashoffset="<?= 2 * M_PI * 20 * (1 - $staff_progress / 100) ?>"
                                                        transform="rotate(-90 25 25)"/>
                                                <text x="25" y="25" text-anchor="middle" dy="0.3em" font-size="10" font-weight="bold">
                                                    <?= $staff_progress ?>%
                                                </text>
                                            </svg>
                                        </div>
                                        <small><?= $staff_submitted ?>/<?= count($document_types) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <!-- File Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="detailsModalTitle">Document Files</h3>
                <button class="close-btn" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="detailsContent"></div>
            </div>
        </div>
    </div>

    <!-- Not Submitted Details Modal -->
    <div id="notSubmittedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Document Submission Details</h3>
                <button class="close-btn" onclick="closeNotSubmittedModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="notSubmittedContent"></div>
            </div>
        </div>
    </div>

    <!-- Faculty Details Modal -->
    <div id="facultyModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Faculty Details</h3>
                <button class="close-btn" onclick="closeFacultyModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="facultyContent"></div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div id="bulkModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bulk Actions</h3>
                <button class="close-btn" onclick="closeBulkModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="bulkContent"></div>
            </div>
        </div>
    </div>

    <script>
        // JavaScript functions (keeping most of the original JavaScript)
        function viewDetails(facultyId, docType, semester) {
            const modal = document.getElementById('detailsModal');
            const title = document.getElementById('detailsModalTitle');
            const content = document.getElementById('detailsContent');
            
            title.textContent = `Files for ${docType}`;
            content.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class='bx bx-loader-alt bx-spin' style="font-size: 24px;"></i>
                    <p>Loading file details...</p>
                </div>
            `;
            
            modal.style.display = 'block';
            
            fetch(`?action=get_file_details&faculty_id=${facultyId}&document_type=${encodeURIComponent(docType)}&semester=${encodeURIComponent(semester)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.files && data.files.length > 0) {
                    let html = '<div class="file-list">';
                    data.files.forEach(file => {
                        html += `
                            <div class="file-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px;">
                                <h4><i class='bx bx-file'></i> ${file.file_name}</h4>
                                <p><strong>Size:</strong> ${formatFileSize(file.file_size || 0)}</p>
                                <p><strong>Uploaded:</strong> ${new Date(file.uploaded_at).toLocaleString()}</p>
                                <p><strong>Type:</strong> ${file.document_type}</p>
                                <div style="margin-top: 10px;">
                                    <a href="handler/download_file.php?id=${file.id}" class="btn btn-primary" style="margin-right: 10px;">
                                        <i class='bx bx-download'></i> Download
                                    </a>
                                    <button class="btn btn-info" onclick="previewFile(${file.id})">
                                        <i class='bx bx-show'></i> Preview
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p class="error">No files found or error loading files.</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<p class="error">Network error occurred</p>';
            });
        }

        function downloadFile(fileId) {
            window.open(`handler/download_file.php?id=${fileId}`, '_blank');
        }

        function showNotSubmittedDetails(facultyId, docType, semester, academicYear = null) {
            const modal = document.getElementById('notSubmittedModal');
            const content = document.getElementById('notSubmittedContent');
            
            if (!academicYear) {
                academicYear = new Date().getFullYear();
            }
            
            content.innerHTML = `
                <div class="not-submitted-info">
                    <h4><i class='bx bx-x-circle' style="color: #dc3545;"></i> Document Not Submitted</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <p style="margin: 5px 0;"><strong>Document Type:</strong> ${docType}</p>
                        <p style="margin: 5px 0;"><strong>Academic Year:</strong> ${academicYear}</p>
                        <p style="margin: 5px 0;"><strong>Semester:</strong> ${semester}</p>
                    </div>
                    <p style="color: #6c757d;">This faculty member has not uploaded any files for this document type in the specified period.</p>
                    
                    <div class="action-buttons" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="btn btn-warning" onclick="sendReminder(${facultyId}, '${docType}', '${semester}', ${academicYear})" style="padding: 8px 15px;">
                            <i class='bx bx-bell'></i> Send Reminder
                        </button>
                        <button class="btn btn-info" onclick="addNote(${facultyId}, '${docType}', '${semester}', ${academicYear})" style="padding: 8px 15px;">
                            <i class='bx bx-note'></i> Add Note
                        </button>
                        <button class="btn btn-secondary" onclick="setDeadline(${facultyId}, '${docType}', '${semester}', ${academicYear})" style="padding: 8px 15px;">
                            <i class='bx bx-calendar'></i> Set Deadline
                        </button>
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        function closeNotSubmittedModal() {
            document.getElementById('notSubmittedModal').style.display = 'none';
        }

        function closeFacultyModal() {
            document.getElementById('facultyModal').style.display = 'none';
        }

        function closeBulkModal() {
            document.getElementById('bulkModal').style.display = 'none';
        }

        function searchFaculty(event) {
            event.preventDefault();
            const searchTerm = event.target.search.value;
            const url = new URL(window.location);
            if (searchTerm.trim()) {
                url.searchParams.set('search', searchTerm);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }

        function exportTable() {
            const table = document.getElementById('documentTable');
            if (!table) {
                alert('No table found to export');
                return;
            }
            
            const departmentName = '<?= htmlspecialchars($admin_department_name) ?>';
            const semester = '<?= htmlspecialchars($semester) ?>';
            
            let csv = 'CAVITE STATE UNIVERSITY - NAIC CAMPUS\n';
            csv += `${departmentName} - Document Submission Tracker\n`;
            csv += `${semester}\n\n`;
            
            const headers = ['Faculty Name', 'Department', 'Employee ID', 'Email'];
            const documentTypes = <?= json_encode($document_types) ?>;
            documentTypes.forEach(docType => headers.push(docType));
            headers.push('Progress %', 'Completed Count');
            csv += headers.map(h => `"${h}"`).join(',') + '\n';

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [];
                
                const facultyText = cells[0].textContent.trim();
                const lines = facultyText.split('\n').map(line => line.trim()).filter(line => line);
                const name = lines[0] || 'Unknown';
                const department = lines.find(line => line.includes('Department') || line.includes('CEIT')) || '';
                const employeeId = lines.find(line => line.includes('ID:')) || '';
                const email = lines.find(line => line.includes('@')) || '';  
                
                rowData.push(name, department.replace(/[^\w\s-]/g, ''), employeeId.replace('ID:', '').trim(), email);
                
                const statusCells = Array.from(cells).slice(1, -1); 
                statusCells.forEach(cell => {
                    const isSubmitted = cell.classList.contains('submitted');
                    const fileCount = cell.querySelector('.file-count-badge')?.textContent || '0';
                    rowData.push(isSubmitted ? `SUBMITTED (${fileCount})` : 'NOT SUBMITTED');
                });
                
                const progress = row.getAttribute('data-progress') || '0';
                const completedCount = statusCells.filter(cell => cell.classList.contains('submitted')).length;
                rowData.push(progress + '%', completedCount);
                
                csv += rowData.map(data => `"${String(data).replace(/"/g, '""')}"`).join(',') + '\n';
            });

            csv += '\n"SUMMARY"\n';
            csv += `"Total Faculty","<?= $total_faculty ?>"\n`;
            csv += `"Documents Submitted","<?= $submitted_count ?>"\n`;
            csv += `"Total Required","<?= $total_possible ?>"\n`;
            csv += `"Overall Completion Rate","<?= $completion_rate ?>%"\n`;
            csv += `"Faculty with Complete Submissions","<?= $complete_faculty ?>"\n`;
            csv += `"Faculty Completion Rate","<?= $faculty_completion_rate ?>%"\n`;

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            
            const filename = `document_tracker_${departmentName.replace(/[^\w\s-]/g, '').replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.csv`;
            link.setAttribute('download', filename);
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function generateReport() {
            window.open(`generate_report.php?department=${<?= json_encode($admin_department_id) ?>}&semester=${encodeURIComponent(<?= json_encode($semester) ?>)}`, '_blank');
        }

        function refreshData() {
            window.location.reload();
        }

        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else if (bytes > 1) {
                return bytes + ' bytes';
            } else if (bytes == 1) {
                return '1 byte';
            } else {
                return '0 bytes';
            }
        }

        // Additional JavaScript functions
        function viewFacultyDetails(facultyId) {
            const modal = document.getElementById('facultyModal');
            const content = document.getElementById('facultyContent');
            
            content.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class='bx bx-loader-alt bx-spin' style="font-size: 24px;"></i>
                    <p>Loading faculty details...</p>
                </div>
            `;
            
            modal.style.display = 'block';
            
            // You can add API call here to fetch faculty details
            setTimeout(() => {
                content.innerHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <p>Faculty details functionality can be implemented here.</p>
                        <p>Faculty ID: ${facultyId}</p>
                    </div>
                `;
            }, 1000);
        }

        function sendMessage(facultyId) {
            const message = prompt('Enter message to send to faculty:');
            if (!message) return;
            
            // Implementation for sending message
            alert('Message functionality to be implemented');
        }

        function viewAllSubmissions(facultyId) {
            // Implementation for viewing all submissions
            alert('View all submissions functionality to be implemented');
        }

        function sendReminder(facultyId, docType, semester, academicYear) {
            if (!confirm(`Send reminder about ${docType} for ${academicYear} - ${semester}?`)) return;
            
            // You can implement this to send actual reminders
            fetch('send_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    faculty_id: facultyId,
                    document_type: docType,
                    semester: semester,
                    academic_year: academicYear,
                    action: 'send_reminder'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reminder sent successfully!');
                    closeNotSubmittedModal();
                } else {
                    alert('Failed to send reminder: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred while sending reminder');
            });
        }

        function addNote(facultyId, docType, semester, academicYear) {
            const note = prompt('Enter note:');
            if (!note || note.trim() === '') return;
            alert('Add note functionality to be implemented');
        }

        function setDeadline(facultyId, docType, semester, academicYear) {
            const deadline = prompt('Enter deadline (YYYY-MM-DD):');
            if (!deadline) return;
            
            // Validate date format
            const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            if (!dateRegex.test(deadline)) {
                alert('Please enter date in YYYY-MM-DD format');
                return;
            }
            
            // Implement deadline functionality
            alert('Set deadline functionality to be implemented');
        }

        function previewFile(fileId) {
            alert('File preview functionality to be implemented');
        }

        function saveFilters() {
            const formData = new FormData(document.getElementById('filterForm'));
            const filters = {};
            for (let [key, value] of formData.entries()) {
                filters[key] = value;
            }
            localStorage.setItem('documentTrackerFilters', JSON.stringify(filters));
            alert('Filters saved successfully');
        }

        // Event listeners
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeDetailsModal();
                closeNotSubmittedModal();
                closeFacultyModal();
                closeBulkModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDetailsModal();
                closeNotSubmittedModal();
                closeFacultyModal();
                closeBulkModal();
            }
        });
    </script>
</body>
</html>