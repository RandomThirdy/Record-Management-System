<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    header('Location: ../../logout.php');
    exit();
}

try {
    // Get file ID from URL parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        die('Invalid file ID');
    }
    
    $fileId = (int)$_GET['id'];
    
    // Get file information from database
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            fo.department_id,
            fo.folder_name,
            d.department_name,
            u.name as uploader_name
        FROM files f
        JOIN folders fo ON f.folder_id = fo.id
        JOIN departments d ON fo.department_id = d.id
        JOIN users u ON f.uploaded_by = u.id
        WHERE f.id = ? AND f.is_deleted = 0 AND fo.is_deleted = 0
    ");
    
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        die('File not found');
    }
    
    // Check if user has permission to download this file
    // For now, all logged-in approved users can download
    // Add more specific permission logic here if needed
    
    // Construct full file path
    $filePath = '../../' . $file['file_path'];
    
    // Check if physical file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('Physical file not found');
    }
    
    // Update download count and last downloaded info
    $updateStmt = $pdo->prepare("
        UPDATE files 
        SET 
            download_count = download_count + 1,
            last_downloaded = NOW(),
            last_downloaded_by = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$currentUser['id'], $fileId]);
    
    // Set headers for file download
    $fileSize = filesize($filePath);
    $fileName = $file['original_name'] ?: $file['file_name'];
    
    // Clean the output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set appropriate headers
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    header('Content-Length: ' . $fileSize);
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Prevent any output before file
    flush();
    
    // Read and output the file
    if ($fileSize > 8192) {
        // For larger files, read in chunks to avoid memory issues
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            die('Cannot open file');
        }
        
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    } else {
        // For smaller files, read all at once
        readfile($filePath);
    }
    
    exit();
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    die('An error occurred during download');
}
?>