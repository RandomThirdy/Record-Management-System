<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../assets/script/social_feed-script';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $content = trim($_POST['content'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';
    $priority = $_POST['priority'] ?? 'normal';
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Content is required']);
        exit;
    }
    
    // Validate inputs
    $allowedVisibility = ['public', 'department', 'custom'];
    $allowedPriority = ['normal', 'high', 'urgent'];
    
    if (!in_array($visibility, $allowedVisibility)) {
        $visibility = 'public';
    }
    
    if (!in_array($priority, $allowedPriority)) {
        $priority = 'normal';
    }
    
    // Get user's department for department posts
    $targetDepartments = null;
    if ($visibility === 'department') {
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userDept = $stmt->fetch();
        if ($userDept && $userDept['department_id']) {
            $targetDepartments = [$userDept['department_id']];
        }
    }
    
    // Create post
    $postId = createPost($pdo, $userId, $content, 'text', $visibility, $targetDepartments, null, $priority);
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Failed to create post']);
        exit;
    }
    
    // Handle file uploads
    $uploadDir = '../../../uploads/posts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (in_array($image['type'], $allowedTypes)) {
            $fileName = uniqid() . '_' . basename($image['name']);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($image['tmp_name'], $filePath)) {
                addPostMedia($pdo, $postId, 'image', $filePath, $fileName, $image['name'], $image['size'], $image['type']);
            }
        }
    }
    
    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($file['size'] <= $maxSize) {
            $fileName = uniqid() . '_' . basename($file['name']);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                addPostMedia($pdo, $postId, 'file', $filePath, $fileName, $file['name'], $file['size'], $file['type']);
            }
        }
    }
    
    // Handle link
    if (isset($_POST['link']) && !empty($_POST['link'])) {
        $link = filter_var($_POST['link'], FILTER_VALIDATE_URL);
        if ($link) {
            // You can implement link preview fetching here
            addPostMedia($pdo, $postId, 'link', null, null, null, null, null, $link);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Post created successfully',
        'post_id' => $postId
    ]);
    
} catch(Exception $e) {
    error_log("Create post error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating post: ' . $e->getMessage()
    ]);
}
?>