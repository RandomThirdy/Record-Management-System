<?php include 'script/faculty-staff.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Faculty Staff - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/style1.css">
    <style>
        .faculty-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            transition: transform 0.2s ease;
        }
        
        .faculty-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .faculty-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid #e0e6ed;
        }
        
        .faculty-details {
            flex: 1;
        }
        
        .faculty-name {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .faculty-position {
            color: #666;
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .faculty-stats {
            display: flex;
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-weight: 600;
            font-size: 16px;
            color: var(--blue);
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        
        .last-login {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-view, .btn-upload {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .btn-view {
            background-color: var(--blue);
            color: white;
            border: none;
        }
        
        .btn-view:hover {
            background-color: #0056b3;
        }
        
        .btn-upload {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .btn-upload:hover {
            background-color: #218838;
        }
        
        .department-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e6ed;
        }
        
        .department-icon {
            width: 50px;
            height: 50px;
            background-color: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .department-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        
        .department-code {
            color: #666;
            font-size: 14px;
            margin: 5px 0 0 0;
        }
        
        .no-faculty {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-faculty i {
            font-size: 50px;
            margin-bottom: 15px;
            display: block;
            color: #ccc;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--blue);
        }
    </style>
</head>
<body>
    <?php include 'components/sidebar.html'; ?>

    <section id="content">
        <?php include 'components/navbar.html'; ?>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Faculty Staff</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="dashboard.php">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="#">View Faculty Staff</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="department-header">
                <div class="department-icon">
                    <?php echo substr($currentAdmin['department_name'] ?? 'DEPT', 0, 2); ?>
                </div>
                <div>
                    <h2 class="department-title"><?php echo htmlspecialchars($currentAdmin['department_name'] ?? 'Department'); ?></h2>
                    <p class="department-code"><?php echo htmlspecialchars($currentAdmin['department_code'] ?? 'DEPT'); ?></p>
                </div>
            </div>

            <?php if (!empty($facultyStaff)): ?>
                <div class="faculty-list">
                    <?php foreach ($facultyStaff as $faculty): ?>
                        <?php 
                        $stats = getFacultySubmissionStats($pdo, $faculty['id']);
                        $avatarSrc = $faculty['profile_image'] ? '../../' . $faculty['profile_image'] : '../../img/default-avatar.png';
                        ?>
                        <div class="faculty-card" data-name="<?php echo htmlspecialchars(strtolower($faculty['full_name'])); ?>">
                            <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="<?php echo htmlspecialchars($faculty['full_name']); ?>" class="faculty-avatar">
                            <div class="faculty-details">
                                <h3 class="faculty-name"><?php echo htmlspecialchars($faculty['full_name']); ?></h3>
                                <p class="faculty-position"><?php echo htmlspecialchars($faculty['position'] ?? 'Faculty Member'); ?></p>
                                
                                <div class="faculty-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $stats['total_submitted']; ?>/<?php echo $stats['total_required']; ?></div>
                                        <div class="stat-label">Documents</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $stats['completion_rate']; ?>%</div>
                                        <div class="stat-label">Complete</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $stats['completion_rate']; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($faculty['last_login']): ?>
                                    <p class="last-login">Last active: <?php echo date('M j, Y g:i A', strtotime($faculty['last_login'])); ?></p>
                                <?php else: ?>
                                    <p class="last-login">Never logged in</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="submission_tracker.php?user_id=<?php echo $faculty['id']; ?>" class="btn-view">
                                    <i class='bx bx-show'></i> View Submissions
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-faculty">
                    <i class='bx bx-user-x'></i>
                    <h3>No Faculty Staff Found</h3>
                    <p>There are currently no faculty members in your department.</p>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script>
        // Faculty search functionality
        const facultySearch = document.getElementById('facultySearch');
        const facultyCards = document.querySelectorAll('.faculty-card');
        
        facultySearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            facultyCards.forEach(card => {
                const facultyName = card.getAttribute('data-name');
                if (facultyName.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>