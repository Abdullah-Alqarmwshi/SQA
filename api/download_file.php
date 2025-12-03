<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

if (!isset($_GET['file_path']) && !isset($_GET['type'])) {
    http_response_code(400);
    exit('Missing parameters');
}

// New method: using type and id
if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    $file_path = null;
    
    if ($type === 'assignment') {
        // Fetch assignment file
        $stmt = $conn->prepare("SELECT file_path FROM assignments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            exit('File not found');
        }
        
        $assignment = $result->fetch_assoc();
        $file_path = $assignment['file_path'];
        
    } elseif ($type === 'submission') {
        // Fetch submission file
        $stmt = $conn->prepare("SELECT file_path FROM submissions WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            exit('File not found');
        }
        
        $submission = $result->fetch_assoc();
        $file_path = $submission['file_path'];
        
    } else {
        http_response_code(400);
        exit('Invalid type');
    }
    
    if (empty($file_path)) {
        http_response_code(404);
        exit('No file attached');
    }
    
    // Build the full file path
    $full_path = __DIR__ . '/../' . $file_path;
    
    // Verify file exists and is within uploads directory
    if (!file_exists($full_path) || !is_file($full_path)) {
        http_response_code(404);
        exit('File not found on server');
    }
    
    // Security: ensure the file is within the uploads directory
    $realpath = realpath($full_path);
    $upload_base = realpath(__DIR__ . '/../uploads');
    
    if ($realpath === false || strpos($realpath, $upload_base) !== 0) {
        http_response_code(403);
        exit('Access denied');
    }
    
} else {
    // Old method: using file_path parameter (for backwards compatibility)
    $requested_file = $_GET['file_path'];

    // Sanitize the file path to prevent directory traversal attacks
    $requested_file = str_replace(['../', '..\\'], '', $requested_file);
    $requested_file = ltrim($requested_file, '/\\');

    // Remove leading 'uploads/' if present
    if (strpos($requested_file, 'uploads/') === 0 || strpos($requested_file, 'uploads\\') === 0) {
        $requested_file = substr($requested_file, 8);
    }

    // Build the full file path - files are in uploads/submissions/
    $full_path = dirname(dirname(__FILE__)) . '/uploads/submissions/' . $requested_file;

    // Verify file exists
    if (!file_exists($full_path)) {
        http_response_code(404);
        exit('File not found');
    }

    // Verify user has permission to download
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // Extract submission_id from the file name to validate permissions
    $file_name = basename($full_path);
    if (!preg_match('/^\d+_(\d+)_(\d+)\..+$/', $file_name, $matches)) {
        http_response_code(400);
        exit('Invalid file format');
    }

    $file_student_id = intval($matches[1]);
    $assignment_id = intval($matches[2]);

    if ($user_role === 'student') {
        // Student can only download their own submissions
        if ($file_student_id != $user_id) {
            http_response_code(403);
            exit('Access denied');
        }
    } elseif ($user_role === 'teacher') {
        // Teacher can download student submissions for their assignments
        $stmt = $conn->prepare("SELECT id FROM assignments WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $assignment_id, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            http_response_code(403);
            exit('Access denied');
        }
    } else {
        http_response_code(403);
        exit('Access denied');
    }
}

// Get file info
$filename = basename($full_path);
$filesize = filesize($full_path);
$mime = mime_content_type($full_path);

// Set headers for download
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($full_path);
exit;
?>
