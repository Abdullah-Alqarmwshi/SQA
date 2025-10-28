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

$file_name = time() . '_' . $student_id . '_' . $assignment_id . '.' . $file_ext;
$file_path = $upload_dir . $file_name;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    header('Location: ../student/view_assignment.php?id=' . $assignment_id . '&error=upload_failed');
    exit;
}

// Update database
if ($is_resubmission) {
    // Get previous submission to delete old file
    $stmt = $conn->prepare("SELECT file_path FROM submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assignment_id, $student_id);
    $stmt->execute();
    $old_submission = $stmt->get_result()->fetch_assoc();
    
    if ($old_submission && file_exists($old_submission['file_path'])) {
        unlink($old_submission['file_path']);
    }
    
    // Update submission record
    $stmt = $conn->prepare("UPDATE submissions SET file_path = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?");
    $stmt->bind_param("sii", $file_path, $assignment_id, $student_id);
} else {
    // Create new submission record
    $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $assignment_id, $student_id, $file_path);
}

if ($stmt->execute()) {
    header('Location: ../student/view_assignment.php?id=' . $assignment_id . '&success=submitted');
} else {
    unlink($file_path); // Delete uploaded file if database insert fails
    header('Location: ../student/view_assignment.php?id=' . $assignment_id . '&error=submission_failed');
}
?>