<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Feed - CVSU NAIC</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <!-- Component Stylesheets -->
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/social_feed.css">
    <!-- <link rel="stylesheet" href="assets/css/style.css"> -->
</head>

<body>
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>

            <div class="head-title">
                <div class="left">
                    <h1>Social Feed</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Overview</a></li>
                    </ul>
                </div>
            </div>

            <div class="feed-container">
                <!-- Feed Tabs -->
                <div class="feed-tabs">
                    <button class="feed-tab active" data-filter="all">
                        <i class='bx bx-home'></i> All Posts
                    </button>
                    <button class="feed-tab" data-filter="my_posts">
                        <i class='bx bx-user'></i> My Posts
                    </button>
                    <button class="feed-tab" data-filter="department">
                        <i class='bx bx-buildings'></i> Departments
                    </button>
                    <button class="feed-tab" data-filter="pinned">
                        <i class='bx bx-pin'></i> Pinned
                    </button>
                </div>

                <!-- Post Composer -->
                <div class="post-composer">
                    <form id="postForm" onsubmit="createPost(event)">
                        <div class="composer-header">
                            <img src="assets/img/default-avatar.png" alt="Your avatar" class="composer-avatar"
                                id="composerAvatar">

                            <div>
                                <strong id="composerName">Loading...</strong>

                                <div class="visibility-selector">
                                    <button type="button" class="visibility-btn" id="visibilityBtn">
                                        <i class='bx bx-globe'></i> Everyone
                                        <i class='bx bx-chevron-down'></i>
                                    </button>

                                    <div class="visibility-dropdown" id="visibilityDropdown">
                                        <div class="visibility-option" data-visibility="public">
                                            <i class='bx bx-globe'></i>
                                            <div>
                                                <strong>Everyone</strong>
                                                <div style="font-size: 12px; color: var(--text-secondary);">
                                                    Visible to all users
                                                </div>
                                            </div>
                                        </div>
                                        <div class="visibility-option" data-visibility="department">
                                            <i class='bx bx-buildings'></i>
                                            <div>
                                                <strong>Department</strong>
                                                <div style="font-size: 12px; color: var(--text-secondary);">
                                                    Only your department
                                                </div>
                                            </div>
                                        </div>
                                        <div class="visibility-option" data-visibility="custom">
                                            <i class='bx bx-group'></i>
                                            <div>
                                                <strong>Specific Users</strong>
                                                <div style="font-size: 12px; color: var(--text-secondary);">
                                                    Choose who can see
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <textarea class="composer-textarea" id="postContent"
                            placeholder="What's happening in your department?" required></textarea>

                        <div class="composer-actions">
                            <div class="composer-tools">
                                <button type="button" class="composer-tool" title="Add Image"
                                    onclick="document.getElementById('imageInput').click()">
                                    <i class='bx bx-image'></i>
                                </button>
                                <button type="button" class="composer-tool" title="Add File"
                                    onclick="document.getElementById('fileInput').click()">
                                    <i class='bx bx-paperclip'></i>
                                </button>
                                <button type="button" class="composer-tool" title="Add Link"
                                    onclick="toggleLinkInput()">
                                    <i class='bx bx-link'></i>
                                </button>
                                <button type="button" class="composer-tool" title="Set Priority"
                                    onclick="togglePrioritySelector()">
                                    <i class='bx bx-flag'></i>
                                </button>
                            </div>

                            <button type="submit" class="post-btn" id="postBtn" disabled>
                                <i class='bx bx-send'></i> Post
                            </button>
                        </div>

                        <!-- Hidden Inputs -->
                        <input type="file" id="imageInput" accept="image/*" style="display: none;"
                            onchange="handleImageUpload(this)">
                        <input type="file" id="fileInput" style="display: none;" onchange="handleFileUpload(this)">
                        <input type="hidden" id="postVisibility" value="public">
                        <input type="hidden" id="postPriority" value="normal">
                    </form>
                </div>

                <!-- Feed Content -->
                <div class="feed-content" id="feedContent">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Loading posts...</p>
                    </div>
                </div>

                <!-- Load More -->
                <div class="load-more" id="loadMoreSection" style="display: none;">
                    <button class="load-more-btn" onclick="loadMorePosts()">
                        <i class='bx bx-refresh'></i> Load More Posts
                    </button>
                </div>
            </div>
        </main>
    </section>

    <!-- Scripts - Load in order -->
    <script src="assets/js/script.js"></script>

    <!-- Social Feed Modules -->
    <script src="assets/js/social_feed/utilities.js"></script>
    <script src="assets/js/social_feed/core.js"></script>
    <script src="assets/js/social_feed/post_composer.js"></script>
    <script src="assets/js/social_feed/post_renderer.js"></script>
    <script src="assets/js/social_feed/post_actions.js"></script>
    <script src="assets/js/social_feed/comment_system.js"></script>
    <script src="assets/js/social_feed/media_handler.js"></script>
    <script src="assets/js/social_feed/main.js"></script>

    <!-- Page-specific initialization -->
    <script>
        // Set active sidebar item and page title for social feed
        document.addEventListener('DOMContentLoaded', function () {
            // Set active sidebar item
            window.AppUtils.setActiveSidebarItem('social_feed.php');

            // Update navbar title
            window.AppUtils.updateNavbarTitle('Social Feed');

            // Initialize search for social feed if needed
            const navbarSearchForm = document.getElementById('navbarSearchForm');
            if (navbarSearchForm) {
                navbarSearchForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const searchInput = document.getElementById('navbarSearchInput');
                    if (searchInput.value.trim()) {
                        searchPosts(e); // Use existing social feed search function
                    }
                });
            }
        });
    </script>
</body>

</html>