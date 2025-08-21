// post-composer.js
// Module for handling post creation and composition

class PostComposer {
    constructor(core) {
        this.core = core;
    }

    // Create a new post
    async createPost(event) {
        event.preventDefault();
        
        const contentInput = document.getElementById('postContent');
        if (!contentInput) return;
        
        const content = contentInput.value.trim();
        if (!content) {
            window.utils.showNotification('Please enter some content for your post', 'error');
            return;
        }
        
        const formData = this.buildFormData(content);
        await this.submitPost(formData);
    }

    // Build form data for post submission
    buildFormData(content) {
        const formData = new FormData();
        formData.append('content', content);
        
        // Add visibility and priority
        const visibilityInput = document.getElementById('postVisibility');
        const priorityInput = document.getElementById('postPriority');
        
        formData.append('visibility', visibilityInput ? visibilityInput.value : 'public');
        formData.append('priority', priorityInput ? priorityInput.value : 'normal');
        
        // Add media files
        this.addMediaToFormData(formData);
        
        return formData;
    }

    // Add media files to form data
    addMediaToFormData(formData) {
        // Handle image upload
        const imageFile = document.getElementById('imageInput');
        if (imageFile && imageFile.files[0]) {
            formData.append('image', imageFile.files[0]);
        }
        
        // Handle file attachment
        const attachFile = document.getElementById('fileInput');
        if (attachFile && attachFile.files[0]) {
            formData.append('file', attachFile.files[0]);
        }
    }

    // Submit post to server
    async submitPost(formData) {
        const button = document.getElementById('postBtn');
        
        try {
            this.setButtonLoading(button, true);
            
            const response = await fetch('api/create_post.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.handlePostSuccess();
            } else {
                window.utils.showNotification(result.message || 'Error creating post', 'error');
            }
        } catch (error) {
            console.error('Error creating post:', error);
            window.utils.showNotification('Error creating post', 'error');
        } finally {
            this.setButtonLoading(button, false);
        }
    }

    // Handle successful post creation
    async handlePostSuccess() {
        this.resetForm();
        await this.core.refreshFeed();
        window.utils.showNotification('Post created successfully!', 'success');
    }

    // Set button loading state
    setButtonLoading(button, isLoading) {
        if (!button) return;
        
        if (isLoading) {
            button.disabled = true;
            button.innerHTML = '<div class="spinner"></div> Posting...';
        } else {
            button.disabled = false;
            button.innerHTML = '<i class="bx bx-send"></i> Post';
        }
    }

    // Reset the form after successful post
    resetForm() {
        const elements = {
            contentInput: document.getElementById('postContent'),
            imageFile: document.getElementById('imageInput'),
            attachFile: document.getElementById('fileInput')
        };

        if (elements.contentInput) elements.contentInput.value = '';
        if (elements.imageFile) elements.imageFile.value = '';
        if (elements.attachFile) elements.attachFile.value = '';
        
        // Remove any image previews
        document.querySelectorAll('.image-preview').forEach(preview => preview.remove());
    }

    // Handle image upload
    handleImageUpload(input) {
        const file = input.files[0];
        if (!file) return;

        // Remove existing preview
        const existingPreview = document.querySelector('.image-preview');
        if (existingPreview) {
            existingPreview.remove();
        }
        
        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            this.createImagePreview(e.target.result);
        };
        reader.readAsDataURL(file);
        
        console.log('Image selected:', file.name);
    }

    // Create image preview element
    createImagePreview(imageSrc) {
        const preview = document.createElement('img');
        preview.src = imageSrc;
        preview.className = 'image-preview';
        preview.style.cssText = 'max-width: 200px; max-height: 200px; border-radius: 8px; margin: 10px 0; display: block;';
        
        const composerActions = document.querySelector('.composer-actions');
        if (composerActions) {
            composerActions.parentNode.insertBefore(preview, composerActions);
        }
    }

    // Handle file upload
    handleFileUpload(input) {
        const file = input.files[0];
        if (file) {
            console.log('File selected:', file.name);
            window.utils.showNotification(`File selected: ${file.name}`, 'info');
        }
    }

    // Toggle link input
    toggleLinkInput() {
        const url = prompt('Enter URL:');
        if (url && this.isValidUrl(url)) {
            console.log('URL added:', url);
            window.utils.showNotification('URL added to post', 'success');
            // TODO: Add logic to handle link preview
        } else if (url) {
            window.utils.showNotification('Please enter a valid URL', 'error');
        }
    }

    // Toggle priority selector
    togglePrioritySelector() {
        const priority = prompt('Set priority (normal, high, urgent):', 'normal');
        if (['normal', 'high', 'urgent'].includes(priority)) {
            const priorityInput = document.getElementById('postPriority');
            if (priorityInput) {
                priorityInput.value = priority;
                window.utils.showNotification(`Priority set to: ${priority}`, 'success');
            }
        } else if (priority) {
            window.utils.showNotification('Invalid priority. Use: normal, high, or urgent', 'error');
        }
    }

    // Validate URL
    isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
}

// Create global instance
window.postComposer = new PostComposer(window.socialFeedCore);