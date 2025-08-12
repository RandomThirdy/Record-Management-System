<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);

if (!$currentUser) {
    // User not found, logout
    header('Location: logout.php');
    exit();
}

// Check if user is approved
if (!$currentUser['is_approved']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_not_approved');
    exit();
}

$success_message = '';
$error_message = '';

   // Get department information for profile image - SAFE ACCESS
$departmentImage = null;
$departmentCode = null;

// Check if department_id exists and is not null
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    try {
        $stmt = $pdo->prepare("SELECT department_code FROM departments WHERE id = ?");
        $stmt->execute([$currentUser['department_id']]);
        $department = $stmt->fetch();
        
        if ($department) {
            $departmentCode = $department['department_code'];
            $departmentImage = "../../img/{$departmentCode}.jpg";
        }
    } catch(Exception $e) {
        error_log("Department image error: " . $e->getMessage());
    }
}

// If getCurrentUser() doesn't include department info, get it separately
if (!isset($currentUser['department_id']) && isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.department_code, d.department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        $userDept = $stmt->fetch();
        
        if ($userDept && $userDept['department_id']) {
            $currentUser['department_id'] = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
            $departmentCode = $userDept['department_code'];
            $departmentImage = "../../img/{$departmentCode}.jpg";
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, employee_id = ?, position = ?, phone = ? WHERE id = ?");
            $stmt->execute([
                $_POST['full_name'],
                $_POST['employee_id'],
                $_POST['position'],
                $_POST['phone'],
                $currentUser['id']
            ]);
            $success_message = 'Profile updated successfully!';
            // Refresh user data
            $currentUser = getCurrentUser($pdo);
        } catch(Exception $e) {
            $error_message = 'Error updating profile: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['change_password'])) {
        // Change password
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            if (password_verify($_POST['current_password'], $currentUser['password'])) {
                try {
                    $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $currentUser['id']]);
                    $success_message = 'Password changed successfully!';
                } catch(Exception $e) {
                    $error_message = 'Error changing password: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Current password is incorrect!';
            }
        } else {
            $error_message = 'New passwords do not match!';
        }
    }
    
    if (isset($_POST['update_preferences'])) {
        // Update user preferences
        try {
            $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, dark_mode = ? WHERE id = ?");
            $stmt->execute([
                isset($_POST['email_notifications']) ? 1 : 0,
                isset($_POST['dark_mode']) ? 1 : 0,
                $currentUser['id']
            ]);
            $success_message = 'Preferences updated successfully!';
            // Refresh user data
            $currentUser = getCurrentUser($pdo);
        } catch(Exception $e) {
            $error_message = 'Error updating preferences: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['request_deletion'])) {
        // Request account deletion (requires admin approval)
        try {
            $stmt = $pdo->prepare("INSERT INTO account_deletion_requests (user_id, reason, requested_at, status) VALUES (?, ?, NOW(), 'pending')");
            $stmt->execute([$currentUser['id'], $_POST['deletion_reason']]);
            $success_message = 'Account deletion request submitted. A super admin will review your request.';
        } catch(Exception $e) {
            $error_message = 'Error submitting deletion request: ' . $e->getMessage();
        }
    }
 

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
        <style>
        /* Additional styles for profile image */
        .profile img {
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e6ed;
            transition: border-color 0.3s ease;
        }
        
        .profile:hover img {
            border-color: var(--blue);
        }
        
        /* Profile icon for users without department image */
        .profile-icon {
            width: 36px;
            height: 36px;
            background: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid #e0e6ed;
            transition: border-color 0.3s ease;
        }
        
        .profile:hover .profile-icon {
            border-color: var(--blue);
        }
        
        /* Department indicator */
        .profile {
            position: relative;
        }
        
        <?php if ($departmentCode): ?>
        .profile::after {
            content: '<?php echo $departmentCode; ?>';
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: var(--blue);
            color: white;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: 500;
            min-width: 20px;
            text-align: center;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <!-- Sidebar -->
    <section id="sidebar">
        <a href="#" class="brand">
            <img src="../../img/cvsu-logo.png" alt="Logo" style="width: 30px; height: 30px;">
            <span class="text">ODCI</span>
        </a>
        <ul class="side-menu top">
            <li class="active">
                <a href="dashboard.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="files.php">
                    <i class='bx bxs-file'></i>
                    <span class="text">My Files</span>
                </a>
            </li>
            <li>
                <a href="folders.php">
                    <i class='bx bxs-folder'></i>
                    <span class="text">My Folders</span>
                </a>
            </li>
            <li>
                <a href="submission_tracker.php">
                    <i class='bx bxs-check-square'></i>
                    <span class="text">Submission Tracker</span>
                </a>
            </li>
             <li>
                <a href="folders.php">
                    <i class='bx bxs-folder'></i>
                    <span class="text">Reports</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <li>
                <a href="settings.php">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Settings</span>
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
            <form action="#">
                <div class="form-input">
                    <input type="search" placeholder="Search...">
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="#" class="notification">
                <i class='bx bxs-bell'></i>
                <span class="num">8</span>
            </a>
            <a href="#" class="profile" title="<?php echo htmlspecialchars($currentUser['full_name'] . ($departmentCode ? ' - ' . $departmentCode : '')); ?>">
                <?php if ($departmentImage && file_exists($departmentImage)): ?>
                    <img src="<?php echo htmlspecialchars($departmentImage); ?>" alt="<?php echo htmlspecialchars($departmentCode . ' Profile'); ?>" style="width: 36px; height: 36px;">
                <?php else: ?>
                    <div class="profile-icon">
                        <?php echo getUserInitials($currentUser['full_name']); ?>
                    </div>
                <?php endif; ?>
            </a>
        </nav>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Settings</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="dashboard.php">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="">Settings</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Enhanced Alert Messages -->
            <?php if ($success_message): ?>
                <div style="padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); color: #155724; border: 1px solid #c3e6cb; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); animation: slideIn 0.3s ease-out;">
                    <i class='bx bx-check-circle' style="font-size: 18px; color: #28a745;"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div style="padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); color: #721c24; border: 1px solid #f5c6cb; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); animation: slideIn 0.3s ease-out;">
                    <i class='bx bx-error-circle' style="font-size: 18px; color: #dc3545;"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div style="display: grid; gap: 24px; max-width: 1200px; margin: 0 auto;">
                <!-- Enhanced Settings Tabs -->
                <div style="display: flex; gap: 4px; background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%); padding: 6px; border-radius: 16px; margin-bottom: 24px; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);">
                    <button class="tab-btn active" onclick="showTab('profile')" style="font-family: 'Poppins', sans-serif; padding: 14px 24px; background: linear-gradient(135deg, var(--blue) 0%, #2980b9 100%); border: none; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); color: white; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);">
                        <i class='bx bx-user' style="font-size: 16px;"></i> Profile
                    </button>
                    <button class="tab-btn" onclick="showTab('security')" style="font-family: 'Poppins', sans-serif; padding: 14px 24px; background: transparent; border: none; border-radius: 12px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); color: var(--dark); display: flex; align-items: center; gap: 8px;">
                        <i class='bx bx-shield' style="font-size: 16px;"></i> Security
                    </button>
                    <button class="tab-btn" onclick="showTab('preferences')" style="font-family: 'Poppins', sans-serif; padding: 14px 24px; background: transparent; border: none; border-radius: 12px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); color: var(--dark); display: flex; align-items: center; gap: 8px;">
                        <i class='bx bx-cog' style="font-size: 16px;"></i> Preferences
                    </button>
                    <button class="tab-btn" onclick="showTab('account')" style="font-family: 'Poppins', sans-serif; padding: 14px 24px; background: transparent; border: none; border-radius: 12px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); color: var(--dark); display: flex; align-items: center; gap: 8px;">
                        <i class='bx bx-user-circle' style="font-size: 16px;"></i> Account
                    </button>
                </div>

                <!-- Profile Settings Tab -->
                <div id="profile" class="tab-content active" style="display: block;">
                    <div style="background: linear-gradient(135deg, var(--light) 0%, #ffffff 100%); border-radius: 24px; padding: 32px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px);">
                        <h3 style="font-family: 'Poppins', sans-serif; margin: 0 0 28px 0; color: var(--dark); font-weight: 700; font-size: 24px; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, var(--blue), #2980b9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            <i class='bx bx-user' style="font-size: 28px; color: var(--blue);"></i> Profile Information
                        </h3>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <!-- Enhanced Profile Picture Section -->
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 20px; padding: 32px; border: 2px dashed var(--blue); border-radius: 20px; text-align: center; margin-bottom: 32px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.05) 0%, rgba(52, 152, 219, 0.02) 100%); transition: all 0.3s ease;">
                                <div style="position: relative;">
                                    <img src="../../img/gav.jpg" alt="Profile Picture" id="profilePreview" style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 5px solid var(--blue); box-shadow: 0 8px 24px rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
                                    <div style="position: absolute; bottom: 5px; right: 5px; background: var(--blue); border-radius: 50%; padding: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                                        <i class='bx bx-camera' style="color: white; font-size: 16px;"></i>
                                    </div>
                                </div>
                                <div>
                                    <input type="file" name="profile_picture" id="profilePicture" accept="image/*" style="display: none;">
                                    <button type="button" onclick="document.getElementById('profilePicture').click();" style="font-family: 'Poppins', sans-serif; padding: 12px 24px; border: 2px solid var(--blue); border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; background: transparent; color: var(--blue);">
                                        <i class='bx bx-upload' style="font-size: 16px;"></i> Change Photo
                                    </button>
                                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #666; font-family: 'Poppins', sans-serif;">JPG, PNG or GIF (max 2MB)</p>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="full_name" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Full Name</label>
                                    <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; background: var(--light); color: var(--dark); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);">
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="employee_id" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Employee ID</label>
                                    <input type="text" name="employee_id" id="employee_id" value="<?php echo htmlspecialchars($currentUser['employee_id'] ?? ''); ?>" style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; background: var(--light); color: var(--dark); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);">
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="position" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Position</label>
                                    <input type="text" name="position" id="position" value="<?php echo htmlspecialchars($currentUser['position'] ?? ''); ?>" style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; background: var(--light); color: var(--dark); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);">
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="phone" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Phone Number</label>
                                    <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; background: var(--light); color: var(--dark); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);">
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="email" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Email Address</label>
                                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" readonly style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); color: #6c757d; cursor: not-allowed;">
                                    <small style="color: #6c757d; font-size: 12px; font-family: 'Poppins', sans-serif; margin-top: 4px;">Email cannot be changed</small>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="department" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Department</label>
                                    <input type="text" value="<?php echo htmlspecialchars($currentUser['department_name'] ?? 'N/A'); ?>" readonly style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); color: #6c757d; cursor: not-allowed;">
                                    <small style="color: #6c757d; font-size: 12px; font-family: 'Poppins', sans-serif; margin-top: 4px;">Contact admin to change department</small>
                                </div>
                            </div>
                            
                            <div style="margin-top: 32px; display: flex; gap: 16px;">
                                <button type="submit" name="update_profile" style="font-family: 'Poppins', sans-serif; padding: 16px 32px; border: none; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, var(--blue) 0%, #2980b9 100%); color: white; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); transform: translateY(0);">
                                    <i class='bx bx-save' style="font-size: 16px;"></i> Save Changes
                                </button>
                                <button type="button" onclick="resetForm()" style="font-family: 'Poppins', sans-serif; padding: 16px 32px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 10px; background: transparent; color: var(--dark);">
                                    <i class='bx bx-reset' style="font-size: 16px;"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Settings Tab -->
                <div id="security" class="tab-content" style="display: none;">
                    <div style="background: linear-gradient(135deg, var(--light) 0%, #ffffff 100%); border-radius: 24px; padding: 32px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); margin-bottom: 24px;">
                        <h3 style="font-family: 'Poppins', sans-serif; margin: 0 0 28px 0; color: var(--dark); font-weight: 700; font-size: 24px; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, var(--blue), #2980b9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            <i class='bx bx-shield' style="font-size: 28px; color: var(--blue);"></i> Change Password
                        </h3>
                        
                        <form method="POST">
                            <div style="display: grid; grid-template-columns: 1fr; gap: 24px; max-width: 500px;">
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="current_password" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Current Password</label>
                                    <input type="password" name="current_password" id="current_password" required style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; background: var(--light); color: var(--dark); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);">
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="new_password" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">New Password</label>
                                    <input type="password" name="new_password" id="new_password" required minlength="8" style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; background: var(--light); color: var(--dark); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);">
                                    <small style="color: #6c757d; font-size: 12px; font-family: 'Poppins', sans-serif; margin-top: 4px;">Password must be at least 8 characters long</small>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label for="confirm_password" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Confirm New Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password" required minlength="8" style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; background: var(--light); color: var(--dark); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);">
                                </div>
                            </div>
                            
                            <div style="margin-top: 32px;">
                                <button type="submit" name="change_password" style="font-family: 'Poppins', sans-serif; padding: 16px 32px; border: none; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, var(--blue) 0%, #2980b9 100%); color: white; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);">
                                    <i class='bx bx-lock' style="font-size: 16px;"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Enhanced Two-Factor Authentication -->
                    <div style="background: linear-gradient(135deg, var(--light) 0%, #ffffff 100%); border-radius: 24px; padding: 32px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px);">
                        <h3 style="font-family: 'Poppins', sans-serif; margin: 0 0 28px 0; color: var(--dark); font-weight: 700; font-size: 24px; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, var(--blue), #2980b9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            <i class='bx bx-mobile' style="font-size: 28px; color: var(--blue);"></i> Two-Factor Authentication
                        </h3>
                        
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; background: linear-gradient(135deg, #f8f9fa, #ffffff); border-radius: 16px; margin-bottom: 20px; border: 1px solid #e9ecef; transition: all 0.3s ease;">
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <div style="background: linear-gradient(135deg, #28a745, #20c997); padding: 12px; border-radius: 12px;">
                                    <i class='bx bx-message' style="color: white; font-size: 20px;"></i>
                                </div>
                                <div>
                                    <h4 style="font-family: 'Poppins', sans-serif; margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: var(--dark);">SMS Authentication</h4>
                                    <p style="margin: 0; color: #6c757d; font-size: 14px; font-family: 'Poppins', sans-serif;">Receive verification codes via text message</p>
                                </div>
                            </div>
                            <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                <input type="checkbox" checked style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, var(--blue), #2980b9); transition: .4s; border-radius: 30px; box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);"></span>
                                <span style="position: absolute; content: ''; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); transform: translateX(30px);"></span>
                            </label>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; background: linear-gradient(135deg, #f8f9fa, #ffffff); border-radius: 16px; border: 1px solid #e9ecef; transition: all 0.3s ease;">
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <div style="background: linear-gradient(135deg, #6c757d, #495057); padding: 12px; border-radius: 12px;">
                                    <i class='bx bx-envelope' style="color: white; font-size: 20px;"></i>
                                </div>
                                <div>
                                    <h4 style="font-family: 'Poppins', sans-serif; margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: var(--dark);">Email Authentication</h4>
                                    <p style="margin: 0; color: #6c757d; font-size: 14px; font-family: 'Poppins', sans-serif;">Receive verification codes via email</p>
                                </div>
                            </div>
                            <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                <input type="checkbox" style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; transition: .4s; border-radius: 30px;"></span>
                                <span style="position: absolute; content: ''; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div id="preferences" class="tab-content" style="display: none;">
                    <div style="background: linear-gradient(135deg, var(--light) 0%, #ffffff 100%); border-radius: 24px; padding: 32px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px);">
                        <h3 style="font-family: 'Poppins', sans-serif; margin: 0 0 28px 0; color: var(--dark); font-weight: 700; font-size: 24px; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, var(--blue), #2980b9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            <i class='bx bx-cog' style="font-size: 28px; color: var(--blue);"></i> User Preferences
                        </h3>
                        
                        <form method="POST">
                            <div style="display: flex; flex-direction: column; gap: 32px;">
                                <!-- Enhanced Notifications Section -->
                                <div style="padding: 24px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.05), rgba(52, 152, 219, 0.02)); border-radius: 16px; border: 1px solid rgba(52, 152, 219, 0.1);">
                                    <h4 style="font-family: 'Poppins', sans-serif; margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 8px;">
                                        <i class='bx bx-bell' style="color: var(--blue); font-size: 20px;"></i> Notifications
                                    </h4>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 0; border-bottom: 1px solid #e9ecef;">
                                        <div>
                                            <label for="email_notifications" style="font-family: 'Poppins', sans-serif; font-weight: 500; color: var(--dark); font-size: 14px; cursor: pointer;">Receive email notifications</label>
                                            <p style="margin: 4px 0 0 0; font-size: 12px; color: #6c757d; font-family: 'Poppins', sans-serif;">Get notified about important updates via email</p>
                                        </div>
                                        <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                            <input type="checkbox" name="email_notifications" id="email_notifications" <?php echo ($currentUser['email_notifications'] ?? 1) ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                                            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: <?php echo ($currentUser['email_notifications'] ?? 1) ? 'linear-gradient(135deg, var(--blue), #2980b9)' : '#ccc'; ?>; transition: .4s; border-radius: 30px; box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);"></span>
                                            <span style="position: absolute; content: ''; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); transform: translateX(<?php echo ($currentUser['email_notifications'] ?? 1) ? '30px' : '0'; ?>);"></span>
                                        </label>
                                    </div>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 0;">
                                        <div>
                                            <label for="push_notifications" style="font-family: 'Poppins', sans-serif; font-weight: 500; color: var(--dark); font-size: 14px; cursor: pointer;">Receive push notifications</label>
                                            <p style="margin: 4px 0 0 0; font-size: 12px; color: #6c757d; font-family: 'Poppins', sans-serif;">Get instant notifications on your device</p>
                                        </div>
                                        <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                            <input type="checkbox" name="push_notifications" id="push_notifications" style="opacity: 0; width: 0; height: 0;">
                                            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; transition: .4s; border-radius: 30px;"></span>
                                            <span style="position: absolute; content: ''; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);"></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Enhanced Theme Section -->
                                <div style="padding: 24px; background: linear-gradient(135deg, rgba(108, 117, 125, 0.05), rgba(108, 117, 125, 0.02)); border-radius: 16px; border: 1px solid rgba(108, 117, 125, 0.1);">
                                    <h4 style="font-family: 'Poppins', sans-serif; margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 8px;">
                                        <i class='bx bx-palette' style="color: #6c757d; font-size: 20px;"></i> Appearance
                                    </h4>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 0;">
                                        <div>
                                            <label for="dark_mode" style="font-family: 'Poppins', sans-serif; font-weight: 500; color: var(--dark); font-size: 14px; cursor: pointer;">Enable dark mode</label>
                                            <p style="margin: 4px 0 0 0; font-size: 12px; color: #6c757d; font-family: 'Poppins', sans-serif;">Switch to dark theme for better night viewing</p>
                                        </div>
                                        <label style="position: relative; display: inline-block; width: 60px; height: 30px;">
                                            <input type="checkbox" name="dark_mode" id="dark_mode" <?php echo ($currentUser['dark_mode'] ?? 0) ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                                            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: <?php echo ($currentUser['dark_mode'] ?? 0) ? 'linear-gradient(135deg, #495057, #343a40)' : '#ccc'; ?>; transition: .4s; border-radius: 30px; box-shadow: 0 4px 12px rgba(73, 80, 87, 0.3);"></span>
                                            <span style="position: absolute; content: ''; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); transform: translateX(<?php echo ($currentUser['dark_mode'] ?? 0) ? '30px' : '0'; ?>);"></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Enhanced Language Section -->
                                <div style="padding: 24px; background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), rgba(40, 167, 69, 0.02)); border-radius: 16px; border: 1px solid rgba(40, 167, 69, 0.1);">
                                    <h4 style="font-family: 'Poppins', sans-serif; margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 8px;">
                                        <i class='bx bx-world' style="color: #28a745; font-size: 20px;"></i> Language & Region
                                    </h4>
                                    <div style="display: flex; flex-direction: column; gap: 8px; max-width: 300px;">
                                        <label for="language" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 4px;">Language</label>
                                        <select name="language" id="language" style="font-family: 'Poppins', sans-serif; padding: 16px 20px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; background: var(--light); color: var(--dark); box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);">
                                            <option value="en">🇺🇸 English</option>
                                            <option value="fil">🇵🇭 Filipino</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 32px;">
                                <button type="submit" name="update_preferences" style="font-family: 'Poppins', sans-serif; padding: 16px 32px; border: none; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, var(--blue) 0%, #2980b9 100%); color: white; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);">
                                    <i class='bx bx-save' style="font-size: 16px;"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Tab -->
                <div id="account" class="tab-content" style="display: none;">
                    <div style="background: linear-gradient(135deg, var(--light) 0%, #ffffff 100%); border-radius: 24px; padding: 32px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); margin-bottom: 24px;">
                        <h3 style="font-family: 'Poppins', sans-serif; margin: 0 0 28px 0; color: var(--dark); font-weight: 700; font-size: 24px; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, var(--blue), #2980b9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            <i class='bx bx-user-circle' style="font-size: 28px; color: var(--blue);"></i> Account Information
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">
                            <div style="padding: 24px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.05), rgba(52, 152, 219, 0.02)); border-radius: 16px; border: 1px solid rgba(52, 152, 219, 0.1); transition: all 0.3s ease;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                    <i class='bx bx-calendar-plus' style="font-size: 20px; color: var(--blue);"></i>
                                    <strong style="font-family: 'Poppins', sans-serif; color: var(--dark); font-size: 14px; font-weight: 600;">Account Created</strong>
                                </div>
                                <span style="color: #6c757d; font-size: 14px; font-family: 'Poppins', sans-serif; margin-left: 32px;"><?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?></span>
                            </div>
                            <div style="padding: 24px; background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), rgba(40, 167, 69, 0.02)); border-radius: 16px; border: 1px solid rgba(40, 167, 69, 0.1); transition: all 0.3s ease;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                    <i class='bx bx-time' style="font-size: 20px; color: #28a745;"></i>
                                    <strong style="font-family: 'Poppins', sans-serif; color: var(--dark); font-size: 14px; font-weight: 600;">Last Login</strong>
                                </div>
                                <span style="color: #6c757d; font-size: 14px; font-family: 'Poppins', sans-serif; margin-left: 32px;">
                                    <?php 
                                    if ($currentUser['last_login']) {
                                        echo date('F j, Y g:i A', strtotime($currentUser['last_login']));
                                    } else {
                                        echo 'First login';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div style="padding: 24px; background: linear-gradient(135deg, <?php echo $currentUser['is_approved'] ? 'rgba(40, 167, 69, 0.05), rgba(40, 167, 69, 0.02)' : 'rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02)'; ?>); border-radius: 16px; border: 1px solid <?php echo $currentUser['is_approved'] ? 'rgba(40, 167, 69, 0.1)' : 'rgba(220, 53, 69, 0.1)'; ?>; transition: all 0.3s ease;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                    <i class='bx <?php echo $currentUser['is_approved'] ? 'bx-check-circle' : 'bx-time-five'; ?>' style="font-size: 20px; color: <?php echo $currentUser['is_approved'] ? '#28a745' : '#dc3545'; ?>;"></i>
                                    <strong style="font-family: 'Poppins', sans-serif; color: var(--dark); font-size: 14px; font-weight: 600;">Account Status</strong>
                                </div>
                                <span style="color: <?php echo $currentUser['is_approved'] ? '#28a745' : '#dc3545'; ?>; font-weight: 600; font-size: 14px; font-family: 'Poppins', sans-serif; margin-left: 32px;">
                                    <?php echo $currentUser['is_approved'] ? 'Active' : 'Pending Approval'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Danger Zone with Account Deletion Request -->
                    <div style="border: 2px solid #dc3545; border-radius: 20px; padding: 32px; background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); box-shadow: 0 8px 24px rgba(220, 53, 69, 0.1);">
                        <h4 style="font-family: 'Poppins', sans-serif; color: #dc3545; margin: 0 0 16px 0; font-weight: 700; font-size: 20px; display: flex; align-items: center; gap: 8px;">
                            <i class='bx bx-error-circle' style="font-size: 24px;"></i> Danger Zone
                        </h4>
                        <p style="color: #721c24; margin: 0 0 24px 0; font-size: 14px; font-family: 'Poppins', sans-serif; line-height: 1.5;">
                            The actions below require careful consideration. Account deletion requests must be approved by a super administrator.
                        </p>
                        
                        <div style="display: grid; gap: 20px;">
                            <!-- Data Export -->
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px; background: linear-gradient(135deg, #fff3cd, #ffeaa7); border-radius: 12px; border: 1px solid #ffc107;">
                                <div>
                                    <h5 style="font-family: 'Poppins', sans-serif; margin: 0 0 4px 0; font-weight: 600; color: #856404;">Export Account Data</h5>
                                    <p style="margin: 0; font-size: 13px; color: #856404; font-family: 'Poppins', sans-serif;">Download all your personal data and files</p>
                                </div>
                                <button type="button" onclick="requestDataExport()" style="font-family: 'Poppins', sans-serif; padding: 10px 20px; border: 2px solid #ffc107; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px; background: transparent; color: #856404;">
                                    <i class='bx bx-download' style="font-size: 14px;"></i> Export Data
                                </button>
                            </div>

                            <!-- Account Deletion Request -->
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px; background: linear-gradient(135deg, #f8d7da, #f5c6cb); border-radius: 12px; border: 1px solid #dc3545;">
                                <div>
                                    <h5 style="font-family: 'Poppins', sans-serif; margin: 0 0 4px 0; font-weight: 600; color: #721c24;">Request Account Deletion</h5>
                                    <p style="margin: 0; font-size: 13px; color: #721c24; font-family: 'Poppins', sans-serif;">Submit a request for account deletion (requires admin approval)</p>
                                </div>
                                <button type="button" onclick="showDeletionModal()" style="font-family: 'Poppins', sans-serif; padding: 10px 20px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #dc3545, #c82333); color: white; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);">
                                    <i class='bx bx-user-x' style="font-size: 14px;"></i> Request Deletion
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <!-- Account Deletion Modal -->
    <div id="deletionModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px);">
        <div style="background: white; margin: 5% auto; padding: 32px; border-radius: 20px; width: 90%; max-width: 500px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3); animation: modalSlideIn 0.3s ease-out;">
            <div style="display: flex; align-items: center; justify-content: between; margin-bottom: 24px;">
                <h3 style="font-family: 'Poppins', sans-serif; margin: 0; color: #dc3545; font-weight: 700; font-size: 20px; display: flex; align-items: center; gap: 8px;">
                    <i class='bx bx-error-circle' style="font-size: 24px;"></i> Request Account Deletion
                </h3>
                <button onclick="hideDeletionModal()" style="background: none; border: none; font-size: 24px; color: #6c757d; cursor: pointer; padding: 0; margin-left: auto;">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            
            <form method="POST" id="deletionForm">
                <div style="margin-bottom: 20px;">
                    <p style="font-family: 'Poppins', sans-serif; color: #721c24; margin: 0 0 16px 0; font-size: 14px; line-height: 1.5;">
                        <strong>Important:</strong> Account deletion requests must be reviewed and approved by a super administrator. This process may take 3-5 business days.
                    </p>
                    <p style="font-family: 'Poppins', sans-serif; color: #721c24; margin: 0 0 16px 0; font-size: 14px; line-height: 1.5;">
                        Once approved, all your data will be permanently deleted and cannot be recovered.
                    </p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="deletion_reason" style="font-family: 'Poppins', sans-serif; font-weight: 600; color: var(--dark); font-size: 14px; margin-bottom: 8px; display: block;">Reason for deletion (required)</label>
                    <textarea name="deletion_reason" id="deletion_reason" required style="font-family: 'Poppins', sans-serif; width: 100%; padding: 12px 16px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; resize: vertical; min-height: 80px; box-sizing: border-box;" placeholder="Please provide a reason for requesting account deletion..."></textarea>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button type="button" onclick="hideDeletionModal()" style="font-family: 'Poppins', sans-serif; padding: 12px 20px; border: 2px solid #6c757d; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; background: transparent; color: #6c757d;">
                        Cancel
                    </button>
                    <button type="submit" name="request_deletion" style="font-family: 'Poppins', sans-serif; padding: 12px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; background: linear-gradient(135deg, #dc3545, #c82333); color: white;">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-50px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .tab-btn:not(.active):hover {
            background: rgba(52, 152, 219, 0.1);
            color: var(--blue);
            transform: translateY(-2px);
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, var(--blue) 0%, #2980b9 100%) !important;
            color: white !important;
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none !important;
            border-color: var(--blue) !important;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1) !important;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        @media (max-width: 768px) {
            .settings-tabs {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .tab-btn {
                padding: 10px 16px;
                font-size: 13px;
            }
            
            main {
                padding: 0 16px;
            }
        }
        
        .dark div[style*="background: linear-gradient(135deg, var(--light)"] {
            background: linear-gradient(135deg, var(--dark-light) 0%, #2c3e50 100%) !important;
        }
        
        .dark input, .dark select, .dark textarea {
            background: var(--dark) !important;
            border-color: var(--dark-grey) !important;
            color: var(--light) !important;
        }
        
        .dark input[readonly], .dark select[readonly] {
            background: linear-gradient(135deg, #34495e, #2c3e50) !important;
            color: #95a5a6 !important;
        }
    </style>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.style.display = 'none';
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(btn => {
                btn.classList.remove('active');
                btn.style.background = 'transparent';
                btn.style.color = 'var(--dark)';
                btn.style.boxShadow = 'none';
            });
            
            // Show selected tab content
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.style.display = 'block';
                selectedTab.classList.add('active');
            }
            
            // Add active class to clicked button
            event.target.classList.add('active');
            event.target.style.background = 'linear-gradient(135deg, var(--blue) 0%, #2980b9 100%)';
            event.target.style.color = 'white';
            event.target.style.boxShadow = '0 4px 8px rgba(52, 152, 219, 0.3)';
        }

        // Profile picture preview
        document.getElementById('profilePicture').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                // Check file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.style.borderColor = '#dc3545';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '#28a745';
            }
        });

        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strength = getPasswordStrength(password);
            
            // You can add a visual password strength indicator here
            if (password.length < 8) {
                this.style.borderColor = '#dc3545';
            } else if (strength < 3) {
                this.style.borderColor = '#ffc107';
            } else {
                this.style.borderColor = '#28a745';
            }
        });

        function getPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }

        // Modal functions
        function showDeletionModal() {
            document.getElementById('deletionModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function hideDeletionModal() {
            document.getElementById('deletionModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('deletionForm').reset();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deletionModal');
            if (event.target == modal) {
                hideDeletionModal();
            }
        }

        // Data export request
        function requestDataExport() {
            if (confirm('Do you want to export all your account data? You will receive a download link via email within 24 hours.')) {
                // Show loading state
                event.target.innerHTML = '<i class="bx bx-loader-alt" style="animation: spin 1s linear infinite;"></i> Processing...';
                event.target.disabled = true;
                
                // Simulate API call
                setTimeout(() => {
                    alert('Data export request submitted successfully! You will receive a download link via email within 24 hours.');
                    event.target.innerHTML = '<i class="bx bx-download"></i> Export Data';
                    event.target.disabled = false;
                }, 2000);
            }
        }

        // Form reset function
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                location.reload();
            }
        }

        // Toggle switches functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = document.querySelectorAll('input[type="checkbox"]');
            
            toggles.forEach(toggle => {
                if (toggle.id !== 'switch-mode') { // Exclude the main dark mode toggle
                    toggle.addEventListener('change', function() {
                        const slider = this.nextElementSibling;
                        const knob = slider.nextElementSibling;
                        
                        if (this.checked) {
                            slider.style.background = 'linear-gradient(135deg, var(--blue), #2980b9)';
                            slider.style.boxShadow = '0 4px 12px rgba(52, 152, 219, 0.3)';
                            knob.style.transform = 'translateX(30px)';
                        } else {
                            slider.style.background = '#ccc';
                            slider.style.boxShadow = 'none';
                            knob.style.transform = 'translateX(0)';
                        }
                    });
                }
            });
        });

        // Sidebar functionality (from original dashboard)
        const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

        allSideMenu.forEach(item=> {
            const li = item.parentElement;

            item.addEventListener('click', function () {
                allSideMenu.forEach(i=> {
                    i.parentElement.classList.remove('active');
                })
                li.classList.add('active');
            })
        });

        // Toggle sidebar
        const menuBar = document.querySelector('#content nav .bx.bx-menu');
        const sidebar = document.getElementById('sidebar');

        menuBar.addEventListener('click', function () {
            sidebar.classList.toggle('hide');
        })

        // Search functionality
        const searchButton = document.querySelector('#content nav form .form-input button');
        const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
        const searchForm = document.querySelector('#content nav form');

        searchButton.addEventListener('click', function (e) {
            if(window.innerWidth < 576) {
                e.preventDefault();
                searchForm.classList.toggle('show');
                if(searchForm.classList.contains('show')) {
                    searchButtonIcon.classList.replace('bx-search', 'bx-x');
                } else {
                    searchButtonIcon.classList.replace('bx-x', 'bx-search');
                }
            }
        })

        if(window.innerWidth < 768) {
            sidebar.classList.add('hide');
        } else if(window.innerWidth > 576) {
            searchButtonIcon.classList.replace('bx-x', 'bx-search');
            searchForm.classList.remove('show');
        }

        window.addEventListener('resize', function () {
            if(this.innerWidth > 576) {
                searchButtonIcon.classList.replace('bx-x', 'bx-search');
                searchForm.classList.remove('show');
            }
        })

        // Dark mode toggle
        const switchMode = document.getElementById('switch-mode');

        switchMode.addEventListener('change', function () {
            if(this.checked) {
                document.body.classList.add('dark');
            } else {
                document.body.classList.remove('dark');
            }
        })

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('[style*="animation: slideIn"]');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Smooth scrolling for form validation errors
        function scrollToError(element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            element.focus();
        }

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        if (isValid) {
                            scrollToError(field);
                            isValid = false;
                        }
                    } else {
                        field.style.borderColor = '#28a745';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        });

        // Add spin animation for loading states
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>