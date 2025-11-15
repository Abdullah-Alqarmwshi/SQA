<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $category    = isset($_POST['category']) ? trim($_POST['category']) : '';
    $description = trim($_POST['description']);
    $file_name   = '';

    // Allowed file types (videos + images + pdf + documents)
    $allowed_extensions = ['mp4','mov','avi','mkv','webm','pdf','jpg','jpeg','png','doc','docx','ppt','pptx'];

    if (!empty($_FILES['file']['name'])) {

        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp  = $_FILES['file']['tmp_name'];
        $file_orig = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($file_orig, PATHINFO_EXTENSION));

        // Validate extension
        if (!in_array($ext, $allowed_extensions)) {
            die("❌ This file type is not allowed. Allowed: mp4, mov, avi, mkv, webm, pdf, jpg, png, doc, ppt");
        }

        // NEW FILE NAME
        $file_name = time() . '_' . preg_replace("/[^A-Za-z0-9._-]/", "_", $file_orig);
        $target_path = $upload_dir . $file_name;

        // Upload
        if (!move_uploaded_file($file_tmp, $target_path)) {
            die("❌ File upload failed. The file may be too large.");
        }
    }

    // INSERT into DB
    $sql = "INSERT INTO lessons (teacher_id, title, category, description, file_path, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("SQL prepare failed: " . $conn->error . "<br>Query: " . $sql);
    }

    $stmt->bind_param("issss", $user_id, $title, $category, $description, $file_name);
    $stmt->execute();
    $stmt->close();

    header("Location: mylesson.php?added=1");
    exit;
}
?>
