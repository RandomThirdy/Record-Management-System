// comment_system.js - Complete fixed version

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

    // Load comments for a post - FIXED: Don't update count when just loading
    async loadComments(postId) {
        const loadingDiv = document.getElementById(`commentsLoading${postId}`);
        const container = document.getElementById(`commentsContainer${postId}`);
        
        if (loadingDiv) loadingDiv.style.display = 'block';
        
        try {
            const response = await fetch(`api/get_comments.php?post_id=${postId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                throw new Error('Server returned invalid response');
            }
            
            if (data.success && container) {
                this.renderComments(container, data.comments);
                // FIXED: Don't update count when just loading comments for display
                // Only update count when we actually modify comments (add/delete)
            } else {
                this.showCommentsError(container, data.message || 'Failed to load comments');
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            this.showCommentsError(container, error.message);
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
    showCommentsError(container, message = 'Error loading comments') {
        if (container) {
            container.innerHTML = `<p style="color: var(--red-primary); text-align: center; padding: 20px;">${message}</p>`;
        }
    }

    // Create comment element
    createCommentElement(comment) {
        const commentDiv = document.createElement('div');
        commentDiv.className = 'comment';
        commentDiv.dataset.commentId = comment.id;
        
        const timeAgo = window.utils ? window.utils.getTimeAgo(comment.created_at) : 'recently';
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
                <div class="comment-text">${this.escapeHtml(comment.content)}</div>
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
        const actions = [`<span class="comment-action" onclick="window.commentSystem.replyToComment(${comment.id})">Reply</span>`];
        
        if (this.core && this.core.canEditComment(comment)) {
            actions.push(`<span class="comment-action" onclick="window.commentSystem.editComment(${comment.id})">Edit</span>`);
            actions.push(`<span class="comment-action" onclick="window.commentSystem.deleteComment(${comment.id})">Delete</span>`);
        }
        
        const likeText = comment.user_liked ? 'Unlike' : 'Like';
        const likeCount = comment.like_count > 0 ? `(${comment.like_count})` : '';
        actions.push(`<span class="comment-action" onclick="window.commentSystem.toggleCommentLike(${comment.id})">${likeText} ${likeCount}</span>`);
        
        return actions.join('');
    }

    // FIXED: Add comment with proper count management
    async addComment(postId) {
            const input = document.querySelector(`#comments${postId} .comment-input`);
            if (!input) {
                console.error('Comment input not found for post:', postId);
                return;
            }
            
            const content = input.value.trim();
            if (!content) {
                this.showNotification('Please enter a comment', 'warning');
                return;
            }
            
            // Show loading state
            const addButton = input.parentElement.querySelector('.add-comment-btn');
            const originalText = addButton ? addButton.textContent : '';
            if (addButton) {
                addButton.textContent = 'Adding...';
                addButton.disabled = true;
            }
            
            try {
                const response = await fetch('api/add_comment.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ 
                        post_id: postId, 
                        content: content 
                    })
                });
                
                const responseText = await response.text();
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${responseText}`);
                }
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    throw new Error(`Invalid JSON response`);
                }
                
                if (result.success) {
                    input.value = '';
                    
                    // FIXED: Use the actual count returned by the server
                    if (result.actual_comment_count !== undefined) {
                        this.updateCommentCountFromServer(postId, result.actual_comment_count);
                    }
                    
                    // Reload comments to show the new comment
                    await this.loadComments(postId);
                    
                    this.showNotification(result.message || 'Comment added successfully!', 'success');
                } else {
                    throw new Error(result.message || 'Failed to add comment');
                }
            } catch (error) {
                console.error('Error adding comment:', error);
                this.showNotification(error.message || 'Error adding comment', 'error');
            } finally {
                if (addButton) {
                    addButton.textContent = originalText || 'Post';
                    addButton.disabled = false;
                }
            }
        }

    // Helper function to load comments and update count in one go
    async loadCommentsAndUpdateCount(postId) {
        try {
            const response = await fetch(`api/get_comments.php?post_id=${postId}`);
            const data = await response.json();
            
            if (data.success) {
                const container = document.getElementById(`commentsContainer${postId}`);
                if (container) {
                    this.renderComments(container, data.comments);
                }
                // Update count from the same response
                this.updateCommentCountFromServer(postId, data.total_comments);
            }
        } catch (error) {
            console.error('Error loading comments and updating count:', error);
        }
    }

    // Update comment count from server response
    updateCommentCountFromServer(postId, serverCount) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return;

        const statsDiv = postElement.querySelector('.post-stats');
        if (!statsDiv) return;

        let commentCountElement = null;
        const spans = statsDiv.querySelectorAll('span');
        spans.forEach(span => {
            if (span.innerHTML.includes('bx-message')) {
                commentCountElement = span;
            }
        });

        // Always set to server count
        if (commentCountElement) {
            commentCountElement.innerHTML = `<i class='bx bx-message'></i> ${serverCount}`;
        } else if (serverCount > 0) {
            statsDiv.insertAdjacentHTML('beforeend', `<span><i class='bx bx-message'></i> ${serverCount}</span>`);
        }
    }

    // Handle comment keypress
    handleCommentKeyPress(event, postId) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.addComment(postId);
        }
    }

    // Utility function to escape HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Show notification helper
    showNotification(message, type = 'info') {
        if (window.utils && window.utils.showNotification) {
            window.utils.showNotification(message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
            alert(message);
        }
    }

    // Reply to comment (placeholder)
    replyToComment(commentId) {
        console.log('Reply to comment:', commentId);
        this.showNotification('Reply feature coming soon!', 'info');
    }

    // Edit comment (placeholder)
    editComment(commentId) {
        console.log('Edit comment:', commentId);
        this.showNotification('Edit comment feature coming soon!', 'info');
    }

    // Delete comment (placeholder)
    deleteComment(commentId) {
        console.log('Delete comment:', commentId);
        this.showNotification('Delete comment feature coming soon!', 'info');
    }

    // Toggle comment like (placeholder)
    toggleCommentLike(commentId) {
        console.log('Toggle comment like:', commentId);
        this.showNotification('Comment like feature coming soon!', 'info');
    }
}

// Create global instance
if (typeof window !== 'undefined') {
    if (window.socialFeedCore) {
        window.commentSystem = new CommentSystem(window.socialFeedCore);
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.commentSystem = new CommentSystem(window.socialFeedCore);
            }, 100);
        });
    }
}