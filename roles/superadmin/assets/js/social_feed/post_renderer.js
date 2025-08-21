// post-renderer.js
// Module for rendering posts and their components

class PostRenderer {
    constructor(core) {
        this.core = core;
    }

    // Create post element
    createPostElement(post) {
        const postDiv = document.createElement('div');
        postDiv.className = 'post';
        postDiv.dataset.postId = post.id;
        
        postDiv.innerHTML = this.buildPostHTML(post);
        return postDiv;
    }

    // Build complete post HTML
    buildPostHTML(post) {
        const timeAgo = window.utils.getTimeAgo(post.created_at);
        const priorityBadge = this.createPriorityBadge(post.priority);
        const visibilityBadge = this.createVisibilityBadge(post.visibility);
        const editedText = this.createEditedText(post.is_edited);
        const actionMenu = this.createActionMenu(post);

        return `
            ${this.createPostHeader(post, timeAgo, editedText, priorityBadge, visibilityBadge, actionMenu)}
            ${this.createPostContent(post)}
            ${this.createPostMediaHTML(post)}
            ${this.createPostEngagement(post)}
            ${this.createPostActions(post)}
            ${this.createCommentSection(post)}
        `;
    }

    // Create post header section
    createPostHeader(post, timeAgo, editedText, priorityBadge, visibilityBadge, actionMenu) {
        return `
            <div class="post-header">
                <img src="${post.profile_image || 'assets/img/default-avatar.png'}" alt="${post.author_full_name}" class="post-avatar">
                <div class="post-author-info">
                    <div class="post-author-name">${post.author_full_name}</div>
                    <div class="post-meta">
                        <span>${post.position || 'Staff'}</span>
                        <span>•</span>
                        <span>${post.department_code || 'N/A'}</span>
                        <span>•</span>
                        <span>${timeAgo}</span>
                        ${editedText}
                        ${priorityBadge}
                        ${visibilityBadge}
                    </div>
                </div>
                ${actionMenu}
            </div>
        `;
    }

    // Create post content section
    createPostContent(post) {
        return `<div class="post-content">${window.utils.escapeHtml(post.content)}</div>`;
    }

    // Create post engagement section
    createPostEngagement(post) {
        const stats = [];
        
        if (post.like_count > 0) {
            stats.push(`<span><i class='bx bx-heart'></i> ${post.like_count}</span>`);
        }
        if (post.comment_count > 0) {
            stats.push(`<span><i class='bx bx-message'></i> ${post.comment_count}</span>`);
        }
        if (post.view_count > 0) {
            stats.push(`<span><i class='bx bx-show'></i> ${post.view_count}</span>`);
        }

        return `
            <div class="post-engagement">
                <div class="post-stats">
                    ${stats.join('')}
                </div>
            </div>
        `;
    }

    // Create post actions section
    createPostActions(post) {
        return `
            <div class="post-actions">
                <div style="position: relative;">
                    <button class="post-action ${post.user_liked ? 'liked' : ''}" 
                            onmouseenter="showReactionPicker(${post.id})" 
                            onmouseleave="hideReactionPicker(${post.id})"
                            onclick="toggleLike(${post.id})">
                        <i class='bx ${post.user_liked ? 'bxs-heart' : 'bx-heart'}'></i>
                        <span>${post.user_liked ? 'Liked' : 'Like'}</span>
                    </button>
                    ${this.createReactionPicker(post.id)}
                </div>
                
                <button class="post-action" onclick="toggleComments(${post.id})">
                    <i class='bx bx-message'></i>
                    <span>Comment</span>
                </button>
                
                <button class="post-action" onclick="sharePost(${post.id})">
                    <i class='bx bx-share'></i>
                    <span>Share</span>
                </button>
            </div>
        `;
    }

    // Create comment section
    createCommentSection(post) {
        const currentUser = this.core.getCurrentUser();
        const avatar = currentUser?.profile_image || 'assets/img/default-avatar.png';

        return `
            <div class="comment-section" id="comments${post.id}" style="display: none;">
                <div class="comments-loading" id="commentsLoading${post.id}" style="display: none;">
                    <div class="spinner"></div>
                </div>
                <div class="comments-container" id="commentsContainer${post.id}">
                    <!-- Comments will be loaded here -->
                </div>
                <div class="comment-form">
                    <img src="${avatar}" alt="Your avatar" class="comment-avatar">
                    <input type="text" class="comment-input" placeholder="Write a comment..." 
                           onkeypress="handleCommentKeyPress(event, ${post.id})">
                    <button class="comment-submit" onclick="addComment(${post.id})">
                        <i class='bx bx-send'></i>
                    </button>
                </div>
            </div>
        `;
    }

    // Create priority badge
    createPriorityBadge(priority) {
        return priority !== 'normal' ? 
            `<span class="post-badge ${priority}">${priority.toUpperCase()}</span>` : '';
    }

    // Create visibility badge
    createVisibilityBadge(visibility) {
        return visibility !== 'public' ? 
            `<span class="post-badge ${visibility}">${visibility.toUpperCase()}</span>` : '';
    }

    // Create edited text indicator
    createEditedText(isEdited) {
        return isEdited ? 
            '<span style="color: var(--text-secondary); font-size: 12px;">(edited)</span>' : '';
    }

    // Create action menu
    createActionMenu(post) {
        if (!this.core.canEditPost(post)) return '';

        return `
            <div class="post-actions-menu">
                <button class="post-menu-btn" onclick="togglePostMenu(${post.id})">
                    <i class='bx bx-dots-horizontal-rounded'></i>
                </button>
                <div class="post-menu" id="postMenu${post.id}">
                    <div class="post-menu-item" onclick="editPost(${post.id})">
                        <i class='bx bx-edit'></i> Edit
                    </div>
                    <div class="post-menu-item danger" onclick="deletePost(${post.id})">
                        <i class='bx bx-trash'></i> Delete
                    </div>
                </div>
            </div>
        `;
    }

    // Create reaction picker
    createReactionPicker(postId) {
        const reactions = [
            { type: 'like', emoji: '❤️', title: 'Like' },
            { type: 'love', emoji: '😍', title: 'Love' },
            { type: 'laugh', emoji: '😂', title: 'Laugh' },
            { type: 'wow', emoji: '😮', title: 'Wow' },
            { type: 'sad', emoji: '😢', title: 'Sad' },
            { type: 'angry', emoji: '😠', title: 'Angry' }
        ];

        const reactionButtons = reactions.map(reaction => 
            `<button class="reaction-option" onclick="reactToPost(${postId}, '${reaction.type}')" title="${reaction.title}">${reaction.emoji}</button>`
        ).join('');

        return `
            <div class="reaction-picker" id="reactionPicker${postId}">
                ${reactionButtons}
            </div>
        `;
    }

    // Create post media HTML
    createPostMediaHTML(post) {
        if (!post.media || post.media.length === 0) return '';
        
        let mediaHTML = '<div class="post-media">';
        
        post.media.forEach(media => {
            switch(media.media_type) {
                case 'image':
                    mediaHTML += this.createImageMedia(media);
                    break;
                case 'file':
                    mediaHTML += this.createFileMedia(media);
                    break;
                case 'link':
                    mediaHTML += this.createLinkMedia(media);
                    break;
            }
        });
        
        mediaHTML += '</div>';
        return mediaHTML;
    }

    // Create image media element
    createImageMedia(media) {
        return `
            <img src="${media.file_path}" alt="Post image" class="post-image" 
                 onclick="openImageModal('${media.file_path}')">
        `;
    }

    // Create file media element
    createFileMedia(media) {
        return `
            <div class="post-file" onclick="downloadFile('${media.file_path}', '${media.original_name}')">
                <i class='bx bx-file post-file-icon'></i>
                <div class="post-file-info">
                    <div class="post-file-name">${media.original_name}</div>
                    <div class="post-file-size">${window.utils.formatFileSize(media.file_size)}</div>
                </div>
                <i class='bx bx-download'></i>
            </div>
        `;
    }

    // Create link media element
    createLinkMedia(media) {
        const linkImage = media.url_image ? 
            `<img src="${media.url_image}" alt="Link preview" class="post-link-image">` : '';
        const linkDescription = media.url_description ? 
            `<div class="post-link-description">${media.url_description}</div>` : '';

        return `
            <div class="post-link-preview" onclick="openLink('${media.url}')">
                ${linkImage}
                <div class="post-link-content">
                    <div class="post-link-title">${media.url_title || media.url}</div>
                    ${linkDescription}
                </div>
            </div>
        `;
    }
}

// Create global instance
window.postRenderer = new PostRenderer(window.socialFeedCore);