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
    <link rel="stylesheet" href="assets/css/submission_tracker.css">
    <style>
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
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <?php include 'components/navbar.html'; ?>

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

    <script src="assets/js/submission_tracker.js"></script>
</body>
</html>