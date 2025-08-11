<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../assets/script/social_feed-script';

header('Content-Type: application/json');

try {
    $postId = intval($_GET['post_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Post ID required']);
        exit;
    }
    
    $comments = getPostComments($pdo, $postId, $limit, $offset);
    
    // Add user_liked field for each comment
    foreach ($comments as &$comment) {
        $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$comment['id'], $userId]);
        $comment['user_liked'] = $stmt->fetch() ? true : false;
        
        // Add user_liked field for replies
        if (isset($comment['replies'])) {
            foreach ($comment['replies'] as &$reply) {
                $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?");
                $stmt->execute([$reply['id'], $userId]);
                $reply['user_liked'] = $stmt->fetch() ? true : false;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    
} catch(Exception $e) {
    error_log("Get comments error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading comments: ' . $e->getMessage()
    ]);
}
?>