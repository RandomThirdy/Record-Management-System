<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../social_feed-script.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    $postId = intval($input['post_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    $parentCommentId = intval($input['parent_comment_id'] ?? 0) ?: null;
    
    if (!$postId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Post ID and content are required']);
        exit;
    }
    
    // Verify post exists
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    
    // Create comment
    $stmt = $pdo->prepare("
        INSERT INTO post_comments (post_id, user_id, parent_comment_id, content) 
        VALUES (?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([$postId, $userId, $parentCommentId, $content]);
    
    if ($result) {
        $commentId = $pdo->lastInsertId();
        
        // Update post comment count
        $stmt = $pdo->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?");
        $stmt->execute([$postId]);
        
        // Log activity
        logActivity($pdo, $userId, 'add_comment', 'comment', $commentId, 'Added comment to post', [
            'post_id' => $postId,
            'parent_comment_id' => $parentCommentId
        ]);
        
        // Send notification to post author (if not commenting on own post)
        if ($post['user_id'] != $userId) {
            sendCommentNotification($pdo, $postId, $commentId, $userId, $post['user_id']);
        }
        
        // If replying to a comment, notify the parent comment author
        if ($parentCommentId) {
            $stmt = $pdo->prepare("SELECT user_id FROM post_comments WHERE id = ?");
            $stmt->execute([$parentCommentId]);
            $parentComment = $stmt->fetch();
            
            if ($parentComment && $parentComment['user_id'] != $userId) {
                sendReplyNotification($pdo, $postId, $commentId, $userId, $parentComment['user_id']);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully',
            'comment_id' => $commentId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
    }
    
} catch(Exception $e) {
    error_log("Add comment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error adding comment: ' . $e->getMessage()
    ]);
}
?>