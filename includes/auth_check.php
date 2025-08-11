<?php
require_once 'config.php';

// Function to check remember me token
function checkRememberToken($pdo) {
    if (isset($_COOKIE['remember_token']) && !isLoggedIn()) {
        $token = $_COOKIE['remember_token'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE password_reset_token = ? AND is_approved = 1");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Auto-login the user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
                $_SESSION['department_id'] = $user['department_id'];
               
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Log auto-login
                logActivity($pdo, $user['id'], 'auto_login', 'user', $user['id'], 'Auto-login via remember token');
                
                return true;
            } else {
                // Invalid token, clear cookie
                setcookie('remember_token', '', time() - 3600, '/', '', false, true);
            }
        } catch(Exception $e) {
            error_log("Remember token check error: " . $e->getMessage());
        }
    }
    
    return false;
}

// Function to require authentication
function requireAuth($redirectTo = 'login.php') {
    global $pdo;
    
    // Check remember me token first
    checkRememberToken($pdo);
    
    if (!isLoggedIn()) {
        header('Location: ' . $redirectTo);
        exit();
    }
    
    // Check if user still exists and is approved
    $currentUser = getCurrentUser($pdo);
    if (!$currentUser || !$currentUser['is_approved']) {
        session_unset();
        session_destroy();
        header('Location: login.php?error=account_not_found');
        exit();
    }
    
    return $currentUser;
}

// Function to require specific role
function requireRole($roles, $redirectTo = 'dashboard.php') {
    $user = requireAuth();
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    if (!in_array($user['role'], $roles)) {
        header('Location: ' . $redirectTo . '?error=access_denied');
        exit();
    }
    
    return $user;
}

// Function to require admin access
function requireAdmin($redirectTo = 'dashboard.php') {
    return requireRole(['admin', 'super_admin'], $redirectTo);
}

// Function to require super admin access
function requireSuperAdmin($redirectTo = 'dashboard.php') {
    return requireRole(['super_admin'], $redirectTo);
}

// Function to check if current user can access resource
function canAccess($resourceType, $resourceId, $requiredPermission = 'read') {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];
    
    // Super admin can access everything
    if ($userRole === 'super_admin') {
        return true;
    }
    
    try {
        switch ($resourceType) {
            case 'file':
                // Check if user owns the file or has folder permissions
                $stmt = $pdo->prepare("
                    SELECT f.uploaded_by, fo.created_by, fo.department_id, fo.is_public
                    FROM files f 
                    JOIN folders fo ON f.folder_id = fo.id 
                    WHERE f.id = ?
                ");
                $stmt->execute([$resourceId]);
                $resource = $stmt->fetch();
                
                if (!$resource) return false;
                
                // Owner has full access
                if ($resource['uploaded_by'] == $userId) return true;
                
                // Public files can be read by anyone
                if ($resource['is_public'] && $requiredPermission === 'read') return true;
                
                // Check folder permissions
                return canAccess('folder', $resource['folder_id'], $requiredPermission);
                
            case 'folder':
                // Check if user owns the folder or has explicit permissions
                $stmt = $pdo->prepare("SELECT created_by, department_id, is_public FROM folders WHERE id = ?");
                $stmt->execute([$resourceId]);
                $resource = $stmt->fetch();
                
                if (!$resource) return false;
                
                // Owner has full access
                if ($resource['created_by'] == $userId) return true;
                
                // Public folders can be read by anyone
                if ($resource['is_public'] && $requiredPermission === 'read') return true;
                
                // Check explicit folder permissions
                $stmt = $pdo->prepare("
                    SELECT permission_type 
                    FROM folder_permissions 
                    WHERE folder_id = ? AND user_id = ? AND is_active = 1 
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->execute([$resourceId, $userId]);
                $permission = $stmt->fetch();
                
                if ($permission) {
                    $permissionLevels = ['read' => 1, 'write' => 2, 'admin' => 3];
                    $required = $permissionLevels[$requiredPermission] ?? 1;
                    $granted = $permissionLevels[$permission['permission_type']] ?? 0;
                    
                    return $granted >= $required;
                }
                
                // Check department access for admin users
                if ($userRole === 'admin' && $resource['department_id'] == $_SESSION['department_id']) {
                    return true;
                }
                
                break;
                
            case 'announcement':
                // Check if user can view announcement based on target departments/roles
                $stmt = $pdo->prepare("
                    SELECT target_departments, target_roles, created_by 
                    FROM announcements 
                    WHERE id = ? AND is_published = 1 AND is_deleted = 0
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->execute([$resourceId]);
                $announcement = $stmt->fetch();
                
                if (!$announcement) return false;
                
                // Creator has full access
                if ($announcement['created_by'] == $userId) return true;
                
                // Check if announcement targets user's department
                if ($announcement['target_departments']) {
                    $targetDepts = json_decode($announcement['target_departments'], true);
                    if (in_array($_SESSION['department_id'], $targetDepts)) return true;
                }
                
                // Check if announcement targets user's role
                if ($announcement['target_roles']) {
                    $targetRoles = json_decode($announcement['target_roles'], true);
                    if (in_array($userRole, $targetRoles)) return true;
                }
                
                // General announcements are visible to all
                return empty($announcement['target_departments']) && empty($announcement['target_roles']);
        }
    } catch(Exception $e) {
        error_log("Access check error: " . $e->getMessage());
        return false;
    }
    
    return false;
}

// Function to get user's accessible folders
function getUserFolders($pdo, $userId, $includePublic = true) {
    try {
        $query = "
            SELECT DISTINCT f.* 
            FROM folders f
            LEFT JOIN folder_permissions fp ON f.id = fp.folder_id AND fp.user_id = ? AND fp.is_active = 1
            WHERE f.is_deleted = 0 AND (
                f.created_by = ? OR
                (f.is_public = 1 AND ?) OR
                fp.id IS NOT NULL
            )
            ORDER BY f.folder_name
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $userId, $includePublic ? 1 : 0]);
        
        return $stmt->fetchAll();
    } catch(Exception $e) {
        error_log("Get user folders error: " . $e->getMessage());
        return [];
    }
}
?>