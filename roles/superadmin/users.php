<?php include 'assets/script/users-script.php' ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CVSU NAIC</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .user-card {
            background: var(--light);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            border: 2px solid transparent;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            border-color: var(--blue);
        }
        
        .user-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--blue), var(--light-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        .user-info h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .user-info p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #666;
        }
        
        .user-details {
            font-size: 13px;
            line-height: 1.6;
        }
        
        .user-details div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .user-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-restricted { background: #f8d7da; color: #721c24; }
        
        .user-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--grey);
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        
        .btn-primary { background: var(--blue); color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { opacity: 0.8; transform: translateY(-1px); }
        
        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-tabs {
            display: flex;
            background: var(--grey);
            border-radius: 8px;
            padding: 4px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .filter-tab.active {
            background: var(--blue);
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: var(--light);
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            margin: 50px auto;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--blue);
        }
        
        .form-group.checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group.checkbox input {
            width: auto;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark);
            border: 1px solid #ddd;
        }
        
        .pagination a:hover {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
        }
        
        .pagination .current {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
        }
        
        @media (max-width: 768px) {
            .user-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
            <li class="active">
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
            <li>
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
                <a href="logout.php" class="logout">
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
            <a href="#" class="nav-link">User Management</a>
            <form action="" method="GET">
                <div class="form-input">
                    <input type="search" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
                <?php if ($filter !== 'all'): ?>
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <?php endif; ?>
                <?php if ($department_filter): ?>
                    <input type="hidden" name="department" value="<?php echo htmlspecialchars($department_filter); ?>">
                <?php endif; ?>
                <?php if ($role_filter): ?>
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_filter); ?>">
                <?php endif; ?>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="notifications.php" class="notification">
                <i class='bx bxs-bell'></i>
                <?php if ($counts['pending'] > 0): ?>
                    <span class="num"><?php echo $counts['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="profile">
                <img src="<?php echo $currentUser['profile_image'] ?? 'assets/img/default-avatar.png'; ?>" alt="Profile">
            </a>
        </nav>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>User Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Users</a></li>
                    </ul>
                </div>
                <button onclick="openModal('createUserModal')" class="btn-download">
                    <i class='bx bx-user-plus'></i>
                    <span class="text">Add User</span>
                </button>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle'></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-tabs">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter' => 'all', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All (<?php echo $counts['all']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter' => 'pending', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                        Pending (<?php echo $counts['pending']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter' => 'approved', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                        Approved (<?php echo $counts['approved']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter' => 'restricted', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $filter === 'restricted' ? 'active' : ''; ?>">
                        Restricted (<?php echo $counts['restricted']; ?>)
                    </a>
                </div>
                
                <div class="filter-group">
                    <label for="department-filter">Department:</label>
                    <select id="department-filter" onchange="filterUsers()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="role-filter">Role:</label>
                    <select id="role-filter" onchange="filterUsers()">
                        <option value="">All Roles</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="super_admin" <?php echo $role_filter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                </div>
            </div>

            <!-- User Grid -->
            <div class="user-grid">
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['name'], 0, 1) . substr($user['surname'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <h3><?php echo htmlspecialchars($user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname']); ?></h3>
                                <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                            </div>
                        </div>
                        
                        <div class="user-details">
                            <div>
                                <span>Email:</span>
                                <span><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div>
                                <span>Employee ID:</span>
                                <span><?php echo htmlspecialchars($user['employee_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span>Department:</span>
                                <span><?php echo htmlspecialchars($user['department_code'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span>Position:</span>
                                <span><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span>Role:</span>
                                <span style="text-transform: capitalize;"><?php echo htmlspecialchars($user['role']); ?></span>
                            </div>
                            <div>
                                <span>Status:</span>
                                <span class="user-status <?php 
                                    if (!$user['is_approved']) echo 'status-pending';
                                    elseif ($user['is_restricted']) echo 'status-restricted';
                                    else echo 'status-approved';
                                ?>">
                                    <?php 
                                    if (!$user['is_approved']) echo 'Pending';
                                    elseif ($user['is_restricted']) echo 'Restricted';
                                    else echo 'Approved';
                                    ?>
                                </span>
                            </div>
                            <div>
                                <span>Joined:</span>
                                <span><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                            <?php if ($user['last_login']): ?>
                                <div>
                                    <span>Last Login:</span>
                                    <span><?php echo date('M j, Y', strtotime($user['last_login'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-actions">
                            <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn btn-primary">
                                <i class='bx bx-edit'></i> Edit
                            </button>
                            
                            <?php if (!$user['is_approved']): ?>
                                <button onclick="approveUser(<?php echo $user['id']; ?>)" class="btn btn-success">
                                    <i class='bx bx-check'></i> Approve
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="resetPassword(<?php echo $user['id']; ?>)" class="btn btn-warning">
                                <i class='bx bx-key'></i> Reset
                            </button>
                            
                            <?php if ($user['role'] !== 'super_admin'): ?>
                                <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?>')" class="btn btn-danger">
                                    <i class='bx bx-trash'></i> Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($users)): ?>
                <div style="text-align: center; padding: 60px; color: #666;">
                    <i class='bx bx-user' style="font-size: 64px; margin-bottom: 20px; display: block;"></i>
                    <h3>No users found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button type="button" class="close" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <form method="POST" action="?action=create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                    <small style="color: #666;">Must be at least 8 characters with uppercase, lowercase, and number</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">First Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="mi">M.I.</label>
                        <input type="text" id="mi" name="mi" maxlength="5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="surname">Last Name *</label>
                    <input type="text" id="surname" name="surname" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="employee_id">Employee ID</label>
                        <input type="text" id="employee_id" name="employee_id">
                    </div>
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('createUserModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button type="button" class="close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form method="POST" action="?action=update" id="editUserForm">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">First Name *</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_mi">M.I.</label>
                        <input type="text" id="edit_mi" name="mi" maxlength="5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_surname">Last Name *</label>
                    <input type="text" id="edit_surname" name="surname" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_employee_id">Employee ID</label>
                        <input type="text" id="edit_employee_id" name="employee_id">
                    </div>
                    <div class="form-group">
                        <label for="edit_position">Position</label>
                        <input type="text" id="edit_position" name="position">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department_id">Department</label>
                        <select id="edit_department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Role *</label>
                        <select id="edit_role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="tel" id="edit_phone" name="phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_address">Address</label>
                    <textarea id="edit_address" name="address" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; margin: 20px 0;">
                    <div class="form-group checkbox">
                        <input type="checkbox" id="edit_is_approved" name="is_approved">
                        <label for="edit_is_approved">Account Approved</label>
                    </div>
                    <div class="form-group checkbox">
                        <input type="checkbox" id="edit_is_restricted" name="is_restricted">
                        <label for="edit_is_restricted">Account Restricted</label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('editUserModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <button type="button" class="close" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            <form method="POST" action="?action=reset_password">
                <input type="hidden" id="reset_user_id" name="user_id">
                
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small style="color: #666;">Must be at least 8 characters with uppercase, lowercase, and number</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('resetPasswordModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script src="assets/js/users.js"> </script>
</body>
</html>