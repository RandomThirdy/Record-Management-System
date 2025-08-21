<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'myd_db');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // Password must be at least 8 characters, contain uppercase, lowercase, and number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function logActivity($pdo, $userId, $action, $resourceType, $resourceId = null, $description = null, $metadata = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $description,
            $metadata ? json_encode($metadata) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch(Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has specific role
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'] ?? '', $roles);
}

// Get current user info
function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM v_users_detailed WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>