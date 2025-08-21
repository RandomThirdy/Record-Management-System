<?php

require_once __DIR__ . '/../../../../../includes/config.php';
require_once __DIR__ . '/../../../../../includes/auth_check.php';

class CommentManager
{
    /**
     * Get post comments with pagination - FIXED VERSION
     */
    public static function getPostComments($pdo, $postId, $limit = 20, $offset = 0)
    {
        try {
            // Debug logging
            error_log("getPostComments called with postId: $postId, limit: $limit, offset: $offset");

            // Ensure parameters are integers
            $postId = (int) $postId;
            $limit = (int) $limit;
            $offset = (int) $offset;

            // Simplified query first - let's get basic comments working
            $query = "
                SELECT c.id, c.post_id, c.user_id, c.parent_comment_id, c.content,
                       c.is_edited, c.edited_at, c.is_deleted, c.deleted_at, c.deleted_by,
                       c.created_at, c.updated_at, c.like_count,
                       u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                       u.profile_image, u.position, 
                       d.department_code,
                       deleter.username as deleted_by_username
                FROM post_comments c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN users deleter ON c.deleted_by = deleter.id
                WHERE c.post_id = ? AND c.is_deleted = 0 AND c.parent_comment_id IS NULL
                ORDER BY c.created_at ASC
                LIMIT $limit OFFSET $offset
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$postId]);

            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Found " . count($comments) . " comments for post $postId");

            // Get replies for each comment (simplified)
            foreach ($comments as &$comment) {
                $comment['replies'] = self::getCommentReplies($pdo, $comment['id'], 5);
                $comment['reply_count'] = count($comment['replies']);
                error_log("Comment {$comment['id']} has " . count($comment['replies']) . " replies");
            }

            return $comments;
        } catch (Exception $e) {
            error_log("Get post comments error: " . $e->getMessage());
            error_log("SQL Query: $query");
            error_log("Parameters: postId=$postId, limit=$limit, offset=$offset");
            return [];
        }
    }

    /**
     * Get comment replies - SIMPLIFIED
     */
    public static function getCommentReplies($pdo, $parentCommentId, $limit = 5)
    {
        try {
            $parentCommentId = (int) $parentCommentId;
            $limit = (int) $limit;

            $query = "
                SELECT c.*, u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                       u.profile_image, u.position
                FROM post_comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.parent_comment_id = ? AND c.is_deleted = 0
                ORDER BY c.created_at ASC
                LIMIT $limit
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$parentCommentId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get comment replies error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update comment
     */
    public static function updateComment($pdo, $commentId, $userId, $content, $userRole = 'user')
    {
        try {
            // Check if user can edit this comment
            $stmt = $pdo->prepare("SELECT user_id FROM post_comments WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            if (!$comment || ($comment['user_id'] != $userId && !in_array($userRole, ['admin', 'super_admin']))) {
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE post_comments 
                SET content = ?, is_edited = 1, edited_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");

            $result = $stmt->execute([$content, $commentId]);

            if ($result) {
                logActivity($pdo, $userId, 'edit_comment', 'comment', $commentId, 'Edited comment');
            }

            return $result;
        } catch (Exception $e) {
            error_log("Update comment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete comment
     */
    public static function deleteComment($pdo, $commentId, $userId, $userRole = 'user')
    {
        try {
            // Check if user can delete this comment
            $stmt = $pdo->prepare("SELECT user_id, post_id FROM post_comments WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            if (!$comment || ($comment['user_id'] != $userId && !in_array($userRole, ['admin', 'super_admin']))) {
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE post_comments 
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([$userId, $commentId]);

            if ($result) {
                logActivity($pdo, $userId, 'delete_comment', 'comment', $commentId, 'Deleted comment');
            }

            return $result;
        } catch (Exception $e) {
            error_log("Delete comment error: " . $e->getMessage());
            return false;
        }
    }
}

?>