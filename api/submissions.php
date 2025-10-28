<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

header('Content-Type: application/json');

// GET request - Fetch submissions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $assignment_id = isset($_GET['assignment_id']) ? $_GET['assignment_id'] : null;
    
    if (!$assignment_id) {
        http_response_code(400);
        exit('Missing assignment ID');
    }
    
    // For teachers: get all submissions
    // For students: get only their own submissions
    $query = "SELECT s.*, u.full_name as student_name 
              FROM submissions s 
              JOIN users u ON s.student_id = u.id 
              WHERE s.assignment_id = ?";
              
    if ($_SESSION['role'] === 'student') {
        $query .= " AND s.student_id = ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($_SESSION['role'] === 'student') {
        $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
    } else {
        $stmt->bind_param("i", $assignment_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    
    echo json_encode($submissions);
}

// POST request - Submit assignment
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_SESSION['role'] !== 'student') {
        http_response_code(403);
        exit('Only students can submit assignments');
    }
    
    $assignment_id = $_POST['assignment_id'];
    $student_id = $_SESSION['user_id'];
    
    // Check if assignment exists and is not past due
    $stmt = $conn->prepare("SELECT due_date FROM assignments WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        exit('Assignment not found');
    }
    
    $assignment = $result->fetch_assoc();
    if (strtotime($assignment['due_date']) < time()) {
        http_response_code(400);
        exit('Assignment is past due');
    }
    
    // Handle file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit('No file uploaded');
    }
    
    $upload_dir = '../uploads/submissions/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . '_' . $_FILES['file']['name'];
    $file_path = 'uploads/submissions/' . $file_name;
    
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $file_name)) {
        http_response_code(500);
        exit('Failed to upload file');
    }
    
    // Check if student has already submitted
    $stmt = $conn->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assignment_id, $student_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Update existing submission
        $stmt = $conn->prepare("UPDATE submissions SET file_path = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?");
        $stmt->bind_param("sii", $file_path, $assignment_id, $student_id);
    } else {
        // Create new submission
        $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $assignment_id, $student_id, $file_path);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $conn->error]);
    }
}

$conn->close();
?>