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