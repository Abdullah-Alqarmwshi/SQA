<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is a student
checkRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

if (!isset($_POST['assignment_id']) || empty($_FILES['submission_file'])) {
    header('Location: ../student/assignments.php?error=missing_data');
    exit;
}

$assignment_id = $_POST['assignment_id'];
$student_id = $_SESSION['user_id'];
$is_resubmission = isset($_POST['resubmit']) && $_POST['resubmit'] == '1';

// Check if assignment exists
$stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header('Location: ../student/assignments.php?error=invalid_assignment');
    exit;
}

// Handle file upload
$file = $_FILES['submission_file'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_ext = array('pdf', 'doc', 'docx', 'zip');

if (!in_array($file_ext, $allowed_ext)) {
    header('Location: ../student/view_assignment.php?id=' . $assignment_id . '&error=invalid_file');
    exit;
}

$upload_dir = '../uploads/submissions/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Keep original filename but add unique prefix
$original_name = pathinfo($file['name'], PATHINFO_FILENAME);
$file_name = time() . '_' . sanitize_filename($original_name) . '.' . $file_ext;
$file_path = $upload_dir . $file_name;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    header('Location: ../student/view_assignment.php?id=' . $assignment_id . '&error=upload_failed');
    exit;
}

// Store relative path from project root (uploads/submissions/filename.pdf)
$relative_db_path = 'uploads/submissions/' . $file_name;

// Function to sanitize filename
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $filename = preg_replace('/\.+/', '.', $filename);
    $filename = trim($filename, '.');
    return $filename ?: 'file';
}

// Update database
if ($is_resubmission) {
    // Get previous submission to delete old file
    $stmt = $conn->prepare("SELECT file_path FROM submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1");
    $stmt->bind_param("ii", $assignment_id, $student_id);
    $stmt->execute();
    $old_submission = $stmt->get_result()->fetch_assoc();
    
    if ($old_submission && !empty($old_submission['file_path'])) {
        // Try to delete old file - handle both relative and absolute paths
        $old_file = $old_submission['file_path'];
        // If path starts with '../', prepend './' to make it relative from this file's location
        if (strpos($old_file, '../') === 0) {
            $old_file = './' . $old_file;
        }
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }
    
    // Update submission record
    $stmt = $conn->prepare("UPDATE submissions SET file_path = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sii", $relative_db_path, $assignment_id, $student_id);
} else {
    // Create new submission record
    $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("iis", $assignment_id, $student_id, $relative_db_path);
}

if ($stmt->execute()) {
    header('Location: ../student/view_assignment.php?id=' . $assignment_id . '&success=submitted');
} else {
    unlink($file_path); // Delete uploaded file if database insert fails
    die("Error executing statement: " . $stmt->error);
}
?>