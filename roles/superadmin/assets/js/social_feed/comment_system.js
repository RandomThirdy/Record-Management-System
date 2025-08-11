// comment-system.js
// Module for handling comments functionality

class CommentSystem {
    constructor(core) {
        this.core = core;
    }

    // Toggle comments section visibility
    async toggleComments(postId) {
        const commentsSection = document.getElementById(`comments${postId}`);
        
        if (!commentsSection) return;

        if (commentsSection.style.display === 'none') {
            commentsSection.style.display = 'block';
            await this.loadComments(postId);
        } else {
            commentsSection.style.display = 'none';
        }
    }

    // Load comments for a post
    async loadComments(postId) {
        const loadingDiv = document.getElementById(`commentsLoading${postId}`);
        const container = document.getElementById(`commentsContainer${postId}`);
        
        if (loadingDiv) loadingDiv.style.display = 'block';
        
        try {
            const response = await fetch(`api/get_comments.php?post_id=${postId}`);
            const data = await response.json();
            
            if (data.success && container) {
                this.renderComments(container, data.comments);
            } else {
                this.showCommentsError(container);
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            this.showCommentsError(container);
        } finally {
            if (loadingDiv) loadingDiv.style.display = 'none';
        }
    }

    // Render comments in container
    renderComments(container, comments) {
        container.innerHTML = '';
        
        if (comments.length === 0) {
            container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 20px;">No comments yet</p>';
        } else {
            comments.forEach(comment => {
                container.appendChild(this.createCommentElement(comment));
            });
        }
    }

    // Show comments error
    showCommentsError(container) {
        if (container) {
            container.innerHTML = '<p style="color: var(--red-primary); text-align: center; padding: 20px;">Error loading comments</p>';
        }
    }

    // Create comment element
    createCommentElement(comment) {
        const commentDiv = document.createElement('div');
        commentDiv.className = 'comment';
        commentDiv.dataset.commentId = comment.id;
        
        const timeAgo = window.utils.getTimeAgo(comment.created_at);
        const editedText = this.createEditedText(comment.is_edited);
        const actions = this.createCommentActions(comment);
        
        commentDiv.innerHTML = `
            <img src="${comment.profile_image || 'assets/img/default-avatar.png'}" 
                 alt="${comment.commenter_full_name}" class="comment-avatar">
            <div class="comment-content">
                <div class="comment-header">
                    <span class="comment-author">${comment.commenter_full_name}</span>
                    <span class="comment-time">${timeAgo}</span>
                    ${editedText}
                </div>
                <div class="comment-text">${window.utils.escapeHtml(comment.content)}</div>
                <div class="comment-actions">
                    ${actions}
                </div>
            </div>
        `;
        
        return commentDiv;
    }

    // Create edited text for comment
    createEditedText(isEdited) {
        return isEdited ? 
            '<span style="color: var(--text-secondary); font-size: 11px;">(edited)</span>' : '';
    }

    // Create comment actions
    createCommentActions(comment) {
        const actions = [`<span class="comment-action" onclick="replyToComment(${comment.id})">Reply</span>`];
        
        if (this.core.canEditComment(comment)) {
            actions.push(`<span class="comment-action" onclick="editComment(${comment.id})">Edit</span>`);
            actions.push(`<span class="comment-action" onclick="deleteComment(${comment.id})">Delete</span>`);
        }
        
        const likeText = comment.user_liked ? 'Unlike' : 'Like';
        const likeCount = comment.like_count > 0 ? `(${comment.like_count})` : '';
        actions.push(`<span class="comment-action" onclick="toggleCommentLike(${comment.id})">${likeText} ${likeCount}</span>`);
        
        return actions.join('');
    }

    // Add new comment
    async addComment(postId) {
        const input = document.querySelector(`#comments${postId} .comment-input`);
        if (!input) return;
        
        const content = input.value.trim();
        if (!content) return;
        
        try {
            const response = await fetch('api/add_comment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId, content: content })
            });
            
            const result = await response.json();
            
            if (result.success) {
                input.value = '';
                await this.loadComments(postId);
                this.updateCommentCount(postId);
            } else {
                window.utils.showNotification(result.message || 'Error adding comment', 'error');
            }
        } catch (error) {
            console.error('Error adding comment:', error);
            window.utils.showNotification('Error adding comment', 'error');
        }
    }

    // Update comment count in post
    updateCommentCount(postId) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return;

        const statsDiv = postElement.querySelector('.post-stats');
        if (!statsDiv) return;

        let commentCountElement = null;
        
        // Find existing comment count element
        const spans = statsDiv.querySelectorAll('span');
        spans.forEach(span => {
            if (span.innerHTML.includes('bx-message')) {
                commentCountElement = span;
            }
        });
        
        if (commentCountElement) {
            const currentCount = parseInt(commentCountElement.textContent.match(/\d+/)?.[0] || 0);
            commentCountElement.innerHTML = `<i class='bx bx-message'></i> ${currentCount + 1}`;
        } else {
            statsDiv.insertAdjacentHTML('beforeend', `<span><i class='bx bx-message'></i> 1</span>`);
        }
    }

    // Handle comment keypress
    handleCommentKeyPress(event, postId) {
        if (event.key === 'Enter') {
            this.addComment(postId);
        }
    }

    // Reply to comment (placeholder)
    replyToComment(commentId) {
        console.log('Reply to comment:', commentId);
        window.utils.showNotification('Reply feature coming soon!', 'info');
    }

    // Edit comment (placeholder)
    editComment(commentId) {
        console.log('Edit comment:', commentId);
        window.utils.showNotification('Edit comment feature coming soon!', 'info');
    }

    // Delete comment (placeholder)
    deleteComment(commentId) {
        console.log('Delete comment:', commentId);
        window.utils.showNotification('Delete comment feature coming soon!', 'info');
    }

    // Toggle comment like (placeholder)
    toggleCommentLike(commentId) {
        console.log('Toggle comment like:', commentId);
        window.utils.showNotification('Comment like feature coming soon!', 'info');
    }
}

// Create global instance
window.commentSystem = new CommentSystem(window.socialFeedCore);