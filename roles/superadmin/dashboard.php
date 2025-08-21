<!-- file path: roles/superadmin/dashboard.php -->

<?php include 'assets/script/dashboard-script.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - CVSU NAIC</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: var(--light);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.users {
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .stat-icon.files {
            background: linear-gradient(45deg, #f093fb, #f5576c);
        }

        .stat-icon.folders {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
        }

        .stat-icon.departments {
            background: linear-gradient(45deg, #43e97b, #38f9d7);
        }

        .stat-icon.announcements {
            background: linear-gradient(45deg, #fa709a, #fee140);
        }

        .stat-icon.storage {
            background: linear-gradient(45deg, #a8edea, #fed6e3);
        }

        .stat-info h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        .stat-info p {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #666;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid var(--grey);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
        }

        .activity-content p {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: #666;
        }

        .activity-time {
            font-size: 12px;
            color: #999;
        }

        .quick-action {
            background: var(--light);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .quick-action:hover {
            border-color: var(--blue);
            transform: translateY(-2px);
        }

        .quick-action i {
            font-size: 28px;
            margin-bottom: 8px;
            display: block;
            color: var(--blue);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .department-progress {
            background: #f8f9fa;
            border-radius: 5px;
            height: 8px;
            margin: 5px 0;
            overflow: hidden;
        }

        .department-progress-bar {
            height: 100%;
            background: linear-gradient(45deg, var(--blue), var(--light-blue));
            transition: width 0.3s;
        }
    </style>
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
                    <h1>Super Admin Dashboard</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Overview</a></li>
                    </ul>
                </div>
                <a href="backup.php" class="btn-download">
                    <i class='bx bxs-download'></i>
                    <span class="text">Backup System</span>
                </a>
            </div>

            <!-- Alert for pending users -->
            <?php if ($stats['pending_users'] > 0): ?>
                <div class="alert warning">
                    <i class='bx bxs-error-circle'></i>
                    <div>
                        <strong>Pending Approvals:</strong> You have <?php echo $stats['pending_users']; ?> user
                        registration(s) waiting for approval.
                        <a href="users.php?filter=pending" style="color: var(--blue); margin-left: 10px;">Review Now</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- System Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class='bx bxs-group'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon files">
                        <i class='bx bxs-file'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_files']); ?></h3>
                        <p>Total Files</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon folders">
                        <i class='bx bxs-folder'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_folders']); ?></h3>
                        <p>Total Folders</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon departments">
                        <i class='bx bxs-buildings'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_departments']); ?></h3>
                        <p>Departments</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon announcements">
                        <i class='bx bxs-megaphone'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_announcements']); ?></h3>
                        <p>Announcements</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon storage">
                        <i class='bx bxs-cloud'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatFileSize($stats['storage_used']); ?></h3>
                        <p>Storage Used</p>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="table-data">
                <!-- Recent System Activities -->
                <div class="order" style="flex: 2;">
                    <div class="head">
                        <h3>Recent System Activities</h3>
                        <i class='bx bx-refresh'></i>
                        <i class='bx bx-filter'></i>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php if (!empty($stats['recent_activities'])): ?>
                            <?php foreach ($stats['recent_activities'] as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                        $icons = [
                                            'login' => 'bx-log-in',
                                            'upload_file' => 'bx-upload',
                                            'create_folder' => 'bx-folder-plus',
                                            'approve_user' => 'bx-check',
                                            'create_announcement' => 'bx-megaphone',
                                            'delete_file' => 'bx-trash'
                                        ];
                                        $icon = $icons[$activity['action']] ?? 'bx-activity';
                                        ?>
                                        <i class='bx <?php echo $icon; ?>'></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4>
                                            <?php
                                            if ($activity['name']) {
                                                echo htmlspecialchars($activity['name'] . ' ' . ($activity['mi'] ? $activity['mi'] . '. ' : '') . $activity['surname']);
                                            } else {
                                                echo 'System';
                                            }
                                            ?>
                                        </h4>
                                        <p><?php echo getActionText($activity['action']); ?></p>
                                        <?php if ($activity['description']): ?>
                                            <p style="margin-top: 3px; font-size: 11px;">
                                                <?php echo htmlspecialchars($activity['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class='bx bx-time' style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="activity_logs.php" style="color: var(--blue); text-decoration: none; font-size: 14px;">
                            View All Activities →
                        </a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="todo" style="flex: 1;">
                    <div class="head">
                        <h3>Quick Actions</h3>
                        <i class='bx bx-plus'></i>
                    </div>
                    <div style="display: grid; gap: 15px;">
                        <a href="users.php?action=create" class="quick-action">
                            <i class='bx bx-user-plus'></i>
                            <div>Add User</div>
                        </a>
                        <a href="departments.php?action=create" class="quick-action">
                            <i class='bx bx-buildings'></i>
                            <div>Add Department</div>
                        </a>
                        <a href="social_feed.php?action=create" class="quick-action">
                            <i class='bx bx-megaphone'></i>
                            <div>New Announcement</div>
                        </a>
                        <a href="reports.php" class="quick-action">
                            <i class='bx bx-bar-chart-alt'></i>
                            <div>Generate Report</div>
                        </a>
                        <a href="settings.php" class="quick-action">
                            <i class='bx bx-cog'></i>
                            <div>System Settings</div>
                        </a>
                        <a href="backup.php" class="quick-action">
                            <i class='bx bx-download'></i>
                            <div>Backup Data</div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="table-data" style="margin-top: 20px;">
                <div class="order" style="flex: 1;">
                    <div class="head">
                        <h3>Department Statistics</h3>
                        <i class='bx bx-bar-chart'></i>
                    </div>
                    <?php if (!empty($stats['department_stats'])): ?>
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="text-align: left;">Department</th>
                                    <th style="text-align: center;">Users</th>
                                    <th style="text-align: center;">Folders</th>
                                    <th style="text-align: center;">Files</th>
                                    <th style="text-align: right;">Storage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['department_stats'] as $dept): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($dept['department_code']); ?></strong>
                                                <br><small
                                                    style="color: #666;"><?php echo htmlspecialchars($dept['department_name']); ?></small>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span
                                                style="background: var(--light-blue); padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                                <?php echo $dept['user_count']; ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;"><?php echo $dept['folder_count']; ?></td>
                                        <td style="text-align: center;"><?php echo $dept['file_count']; ?></td>
                                        <td style="text-align: right;">
                                            <strong><?php echo formatFileSize($dept['total_size']); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class='bx bx-buildings' style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                            <p>No department data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Information -->
            <div class="table-data" style="margin-top: 20px;">
                <div class="order" style="flex: 1;">
                    <div class="head">
                        <h3>System Information</h3>
                        <i class='bx bx-info-circle'></i>
                    </div>
                    <div style="padding: 20px 0;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 14px;">
                            <div>
                                <strong>System Version:</strong><br>
                                <span style="color: #666;">MyDrive v2.0</span>
                            </div>
                            <div>
                                <strong>PHP Version:</strong><br>
                                <span style="color: #666;"><?php echo phpversion(); ?></span>
                            </div>
                            <div>
                                <strong>Database:</strong><br>
                                <span style="color: #666;">MySQL/MariaDB</span>
                            </div>
                            <div>
                                <strong>Last Backup:</strong><br>
                                <span style="color: #666;">Never</span>
                            </div>
                            <div>
                                <strong>Disk Usage:</strong><br>
                                <span style="color: #666;"><?php echo formatFileSize($stats['storage_used']); ?></span>
                            </div>
                            <div>
                                <strong>Server Time:</strong><br>
                                <span style="color: #666;"><?php echo date('M j, Y g:i A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="assets/js/script.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>

</html>