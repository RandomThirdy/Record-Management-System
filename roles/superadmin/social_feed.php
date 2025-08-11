<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Feed - CVSU NAIC</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/social_feed.css">
</head>

<body>
    <!-- Sidebar -->
    <section id="sidebar">
        <a href="#" class="brand">
            <i class='bx bxs-dashboard'></i>
            <span class="text">ODCI Admin</span>
        </a>

        <ul class="side-menu top">
            <li>
                <a href="dashboard.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php">
                    <i class='bx bxs-group'></i>
                    <span class="text">Users</span>
                </a>
            </li>
            <li>
                <a href="departments.php">
                    <i class='bx bxs-buildings'></i>
                    <span class="text">Departments</span>
                </a>
            </li>
            <li>
                <a href="files.php">
                    <i class='bx bxs-file'></i>
                    <span class="text">Files</span>
                </a>
            </li>
            <li>
                <a href="folders.php">
                    <i class='bx bxs-folder'></i>
                    <span class="text">Folders</span>
                </a>
            </li>
            <li class="active">
                <a href="social_feed.php">
                    <i class='bx bx-news'></i>
                    <span class="text">Social Feed</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class='bx bxs-report'></i>
                    <span class="text">Reports</span>
                </a>
            </li>
        </ul>

        <ul class="side-menu">
            <li>
                <a href="settings.php">
                    <i class='bx bxs-cog'></i>
                    <span class="text">System Settings</span>
                </a>
            </li>
            <li>
                <a href="activity_logs.php">
                    <i class='bx bxs-time'></i>
                    <span class="text">Activity Logs</span>
                </a>
            </li>
            <li>
                <a href="../../logout.php" class="logout">
                    <i class='bx bxs-log-out-circle'></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>

    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <nav>
            <i class='bx bx-menu'></i>
            <a href="#" class="nav-link">Social Feed</a>

            <form action="#" onsubmit="searchPosts(event)">
                <div class="form-input">
                    <input type="search" id="searchInput" placeholder="Search posts...">
                    <button type="submit" class="search-btn">
                        <i class='bx bx-search'></i>
                    </button>
                </div>
            </form>

            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>

            <a href="#" class="notification">
                <i class='bx bxs-bell'></i>
                <span class="num" id="notificationCount">0</span>
            </a>

            <a href="#" class="profile">
                <img src="assets/img/default-avatar.png" alt="Profile" id="userAvatar">
            </a>
        </nav>

        <!-- Main Content -->
        <main>
            <div class="feed-container">
                <!-- Feed Header -->
                <div class="feed-header">
                    <h2><i class='bx bx-news'></i> Social Feed</h2>
                    <p>Connect, share, and collaborate with your colleagues</p>
                </div>

                <!-- Feed Tabs -->
                <div class="feed-tabs">
                    <button class="feed-tab active" data-filter="all">
                        <i class='bx bx-home'></i> All Posts
                    </button>
                    <button class="feed-tab" data-filter="my_posts">
                        <i class='bx bx-user'></i> My Posts
                    </button>
                    <button class="feed-tab" data-filter="department">
                        <i class='bx bx-buildings'></i> Department
                    </button>
                    <button class="feed-tab" data-filter="pinned">
                        <i class='bx bx-pin'></i> Pinned
                    </button>
                    <button class="feed-tab" data-filter="trending">
                        <i class='bx bx-trending-up'></i> Trending
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
</body>

</html>