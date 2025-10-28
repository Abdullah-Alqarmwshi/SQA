<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = $_POST['submission_id'];
    $grade = isset($_POST['grade']) ? $_POST['grade'] : null;
    $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : null;
    
    // Update submission with grade and feedback
    $stmt = $conn->prepare("UPDATE submissions SET grade = ?, feedback = ? WHERE id = ?");
    $stmt->bind_param("ssi", $grade, $feedback, $submission_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $conn->error]);
    }
}

$conn->close();
?>