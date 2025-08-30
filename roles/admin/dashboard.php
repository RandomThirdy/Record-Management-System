<?php include 'script/dashboard.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/style1.css">
</head>
<body>

    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?>!</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="#">Overview</a>
                        </li>
                    </ul>
                </div>
                <a href="upload.php" class="btn-download">
                    <i class='bx bxs-cloud-upload'></i>
                    <span class="text">Upload File</span>
                </a>
            </div>

            <!-- Statistics Cards -->
            <ul class="box-info">
                <li>
                    <i class='bx bxs-file'></i>
                    <span class="text">
                        <h3><?php echo number_format($stats['total_files']); ?></h3>
                        <p>Total Files</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-folder'></i>
                    <span class="text">
                        <h3><?php echo number_format($stats['total_folders']); ?></h3>
                        <p>Total Folders</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-cloud'></i>
                    <span class="text">
                        <h3><?php echo formatFileSize($stats['storage_used']); ?></h3>
                        <p>Storage Used</p>
                    </span>
                </li>
            </ul>

            <!-- Recent Activity and Quick Actions -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Recent Uploads</h3>
                        <i class='bx bx-search'></i>
                        <i class='bx bx-filter'></i>
                    </div>
                    <?php if (!empty($stats['recent_uploads'])): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th>Folder</th>
                                    <th>Size</th>
                                    <th>Upload Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['recent_uploads'] as $file): ?>
                                    <tr>
                                        <td>
                                            <i class='bx bxs-file'></i>
                                            <p><?php echo htmlspecialchars($file['original_name']); ?></p>
                                        </td>
                                        <td><?php echo htmlspecialchars($file['folder_name']); ?></td>
                                        <td><?php echo formatFileSize($file['file_size']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class='bx bx-file' style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                            <p>No files uploaded yet</p>
                            <a href="upload.php" style="color: var(--blue); text-decoration: none; font-weight: 500;">Upload your first file</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="todo">
                    <div class="head">
                        <h3>Quick Actions</h3>
                        <i class='bx bx-plus'></i>
                        <i class='bx bx-filter'></i>
                    </div>
                    <ul class="todo-list">
                        <li class="not-completed">
                            <p>Upload new files</p>
                            <a href="upload.php"><i class='bx bx-upload'></i></a>
                        </li>
                        <li class="not-completed">
                            <p>Create new folder</p>
                            <a href="folders.php?action=create"><i class='bx bx-folder-plus'></i></a>
                        </li>
                        <li class="not-completed">
                            <p>Organize files</p>
                            <a href="files.php"><i class='bx bx-organize'></i></a>
                        </li>
                        <li class="not-completed">
                            <p>Share documents</p>
                            <a href="shared.php"><i class='bx bx-share'></i></a>
                        </li>
                        <li class="not-completed">
                            <p>Update profile</p>
                            <a href="profile.php"><i class='bx bx-user'></i></a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Account Information -->
            <div class="table-data" style="margin-top: 20px;">
                <div class="order" style="flex: 1;">
                    <div class="head">
                        <h3>Account Information</h3>
                        <i class='bx bx-info-circle'></i>
                    </div>
                    <div style="padding: 20px 0;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 14px;">
                            <div>
                                <strong>Department:</strong><br>
                                <span style="color: #666;"><?php echo htmlspecialchars($currentUser['department_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong>Employee ID:</strong><br>
                                <span style="color: #666;"><?php echo htmlspecialchars($currentUser['employee_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong>Position:</strong><br>
                                <span style="color: #666;"><?php echo htmlspecialchars($currentUser['position'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong>Member Since:</strong><br>
                                <span style="color: #666;"><?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></span>
                            </div>
                            <div>
                                <strong>Last Login:</strong><br>
                                <span style="color: #666;">
                                    <?php 
                                    if ($currentUser['last_login']) {
                                        echo date('M j, Y g:i A', strtotime($currentUser['last_login']));
                                    } else {
                                        echo 'First login';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div>
                                <strong>Account Status:</strong><br>
                                <span style="color: <?php echo $currentUser['is_approved'] ? '#38a169' : '#e53e3e'; ?>; font-weight: 500;">
                                    <?php echo $currentUser['is_approved'] ? 'Approved' : 'Pending'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

</body>
</html>