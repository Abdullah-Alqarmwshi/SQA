<?php
require_once '../config/session.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $file_name = '';

    // File upload
    if (!empty($_FILES['file']['name'])) {
        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['file']['name']);
        $target_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
            die("File upload failed!");
        }
    }

    // ✅ Add error reporting for debugging
    $sql = "INSERT INTO lessons (teacher_id, title, category, description, file_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("SQL prepare failed: " . $conn->error . "<br>Query: " . $sql);
    }

    $stmt->bind_param("issss", $user_id, $title, $category, $description, $file_name);
    $stmt->execute();
    $stmt->close();

    header("Location: mylesson.php");
    exit;
}
?>

