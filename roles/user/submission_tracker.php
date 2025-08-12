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

// Define required document types
$requiredDocuments = [
    'IPCR Accomplishment' => 'Individual Performance Commitment and Review',
    'IPCR Target' => 'Future performance goals',
    'Workload' => 'Faculty workload document',
    'Course Syllabus' => 'Full course outline',
    'Course Syllabus Acceptance Form' => 'Admin-signed confirmation form',
    'Exam' => 'Major exam papers',
    'Table of Specifications (TOS)' => 'Breakdown of exam coverage',
    'Class Record' => 'Official student performance tracking',
    'Grading Sheets' => 'Final grades submission',
    'Attendance Sheet' => 'Class attendance log',
    'Stakeholder\'s Feedback Summary' => 'Feedback + summary sheet',
    'Consultation Log' => 'Logs of student consultations',
    'Lecture Materials' => 'Slide decks, lesson plans',
    'Activities' => 'Classwork and assessments',
    'CEIT-QF-03 Form' => 'Exam Discussion Acknowledgement Form',
    'Others' => 'Additional documents'
];

// Get user's uploaded documents grouped by document type
$uploadedDocs = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT f.document_type, COUNT(*) as count, MAX(f.uploaded_at) as latest_upload
        FROM files f 
        JOIN folders fo ON f.folder_id = fo.id 
        WHERE f.uploaded_by = ? AND f.is_deleted = 0 AND f.document_type IS NOT NULL AND f.document_type != ''
        GROUP BY f.document_type
    ");
    $stmt->execute([$currentUser['id']]);
    $results = $stmt->fetchAll();
    
    foreach ($results as $row) {
        $uploadedDocs[$row['document_type']] = [
            'count' => $row['count'],
            'latest_upload' => $row['latest_upload']
        ];
    }
} catch(Exception $e) {
    error_log("Submission tracker error: " . $e->getMessage());
}

// Calculate statistics
$totalRequired = count($requiredDocuments);
$totalUploaded = count($uploadedDocs);
$completionRate = $totalRequired > 0 ? round(($totalUploaded / $totalRequired) * 100, 1) : 0;

// Format file size function (if needed)
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Tracker - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
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
  
        .submission-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-uploaded {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-not-uploaded {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .document-count {
            background-color: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
        }
        
        .submission-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .submission-table th,
        .submission-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .submission-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .submission-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .upload-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .upload-link:hover {
            text-decoration: underline;
        }
        
        .last-upload {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            margin: 10px 0 5px 0;
            font-size: 1.8em;
        }
        
        .stat-card p {
            color: #666;
            margin: 0;
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
            <li>
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
            <li class="active">
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
                    <h1>Document Submission Tracker</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="dashboard.php">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="#">Submission Tracker</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class='bx bxs-file-doc' style="color: #007bff;"></i>
                    <h3><?php echo $totalRequired; ?></h3>
                    <p>Required Documents</p>
                </div>
                <div class="stat-card">
                    <i class='bx bxs-check-circle' style="color: #28a745;"></i>
                    <h3><?php echo $totalUploaded; ?></h3>
                    <p>Documents Uploaded</p>
                </div>
                <div class="stat-card">
                    <i class='bx bxs-x-circle' style="color: #dc3545;"></i>
                    <h3><?php echo $totalRequired - $totalUploaded; ?></h3>
                    <p>Missing Documents</p>
                </div>
                <div class="stat-card">
                    <i class='bx bxs-bar-chart-alt-2' style="color: #ffc107;"></i>
                    <h3><?php echo $completionRate; ?>%</h3>
                    <p>Completion Rate</p>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Overall Progress</h3>
                        <i class='bx bx-trending-up'></i>
                    </div>
                    <div style="padding: 20px;">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $completionRate; ?>%;">
                                <?php echo $completionRate; ?>% Complete
                            </div>
                        </div>
                        <p style="text-align: center; margin-top: 10px; color: #666;">
                            <?php echo $totalUploaded; ?> of <?php echo $totalRequired; ?> required documents submitted
                        </p>
                    </div>
                </div>
            </div>

            <!-- Document Submission Status Table -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Document Submission Status</h3>
                        <div>
                            <i class='bx bx-filter'></i>
                            <i class='bx bx-refresh'></i>
                        </div>
                    </div>
                    <table class="submission-table">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Files Uploaded</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requiredDocuments as $docType => $description): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($docType); ?></strong>
                                    </td>
                                    <td>
                                        <span style="color: #666;"><?php echo htmlspecialchars($description); ?></span>
                                    </td>
                                    <td>
                                        <?php if (isset($uploadedDocs[$docType])): ?>
                                            <span class="submission-status status-uploaded">
                                                <i class='bx bx-check'></i> Uploaded
                                            </span>
                                            <?php if ($uploadedDocs[$docType]['latest_upload']): ?>
                                                <div class="last-upload">
                                                    Last upload: <?php echo date('M j, Y g:i A', strtotime($uploadedDocs[$docType]['latest_upload'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="submission-status status-not-uploaded">
                                                <i class='bx bx-x'></i> Not Uploaded
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($uploadedDocs[$docType])): ?>
                                            <span class="document-count"><?php echo $uploadedDocs[$docType]['count']; ?> file(s)</span>
                                        <?php else: ?>
                                            <span style="color: #999;">0 files</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($uploadedDocs[$docType])): ?>
                                            <a href="files.php?filter=<?php echo urlencode($docType); ?>" class="upload-link">
                                                <i class='bx bx-show'></i> View Files
                                            </a>
                                        <?php else: ?>
                                            <a href="upload.php?doc_type=<?php echo urlencode($docType); ?>" class="upload-link">
                                                <i class='bx bx-upload'></i> Upload Now
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions for Missing Documents -->
             
            <?php 
            $missingDocs = array_diff_key($requiredDocuments, $uploadedDocs);
            if (!empty($missingDocs)): 
            ?>
            <div class="table-data">
                <div class="todo">
                    <div class="head">
                        <h3>Missing Documents</h3>
                        <i class='bx bx-error-circle' style="color: #dc3545;"></i>
                    </div>
                    <ul class="todo-list">
                        <?php foreach (array_slice($missingDocs, 0, 5) as $docType => $description): ?>
                        <li class="not-completed">
                            <div>
                                <strong><?php echo htmlspecialchars($docType); ?></strong>
                                <p style="font-size: 12px; color: #666; margin: 4px 0 0 0;">
                                    <?php echo htmlspecialchars($description); ?>
                                </p>
                            </div>
                            <a href="upload.php?doc_type=<?php echo urlencode($docType); ?>">
                                <i class='bx bx-upload'></i>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        
                        <?php if (count($missingDocs) > 5): ?>
                        <li style="text-align: center; padding: 10px;">
                            <a href="#" style="color: #007bff; text-decoration: none;">
                                View <?php echo count($missingDocs) - 5; ?> more missing documents
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Legend -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Status Legend</h3>
                        <i class='bx bx-info-circle'></i>
                    </div>
                    <div style="padding: 20px;">
                        <div style="display: flex; gap: 30px; align-items: center; flex-wrap: wrap; justify-content: center;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="submission-status status-uploaded">
                                    <i class='bx bx-check'></i> Uploaded
                                </span>
                                <span style="color: #666;">Document has been submitted</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="submission-status status-not-uploaded">
                                    <i class='bx bx-x'></i> Not Uploaded
                                </span>
                                <span style="color: #666;">Document still needs to be submitted</span>
                            </div>
                        </div>
                    </div>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                            <p style="color: #666; margin: 0; font-size: 14px; text-align: center;">
                                <strong>Note:</strong> Make sure to upload all required documents to maintain compliance. 
                                Click "Upload Now" to submit missing documents or "View Files" to see uploaded documents.
                            </p>
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

        // Search documents in table
        const searchInput = document.querySelector('#content nav form .form-input input');
        const tableRows = document.querySelectorAll('.submission-table tbody tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            tableRows.forEach(row => {
                const docType = row.cells[0].textContent.toLowerCase();
                const description = row.cells[1].textContent.toLowerCase();
                
                if (docType.includes(searchTerm) || description.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

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

        // Auto-refresh page every 5 minutes to update latest uploads
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes

        // Add click event to refresh button
        const refreshBtn = document.querySelector('.head i.bx-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                location.reload();
            });
        }

        // Filter functionality
        const filterBtn = document.querySelector('.head i.bx-filter');
        if (filterBtn) {
            filterBtn.addEventListener('click', function() {
                const filterOptions = ['All', 'Uploaded', 'Not Uploaded'];
                const selectedFilter = prompt('Filter documents by:\n\n1. All\n2. Uploaded\n3. Not Uploaded\n\nEnter option number (1-3):');
                
                if (selectedFilter && selectedFilter >= 1 && selectedFilter <= 3) {
                    filterDocuments(filterOptions[selectedFilter - 1]);
                }
            });
        }

        function filterDocuments(filter) {
            tableRows.forEach(row => {
                const statusCell = row.cells[2];
                const isUploaded = statusCell.textContent.includes('Uploaded') && !statusCell.textContent.includes('Not Uploaded');
                
                switch(filter) {
                    case 'All':
                        row.style.display = '';
                        break;
                    case 'Uploaded':
                        row.style.display = isUploaded ? '' : 'none';
                        break;
                    case 'Not Uploaded':
                        row.style.display = !isUploaded ? '' : 'none';
                        break;
                }
            });
        }
    </script>
</body>
</html>