<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $file_name   = '';

    if (!empty($_FILES['file']['name'])) {

        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp  = $_FILES['file']['tmp_name'];
        $file_orig = $_FILES['file']['name'];

        // Allow ALL file types – just sanitise the name
        $file_name   = time() . '_' . preg_replace("/[^A-Za-z0-9._-]/", "_", $file_orig);
        $target_path = $upload_dir . $file_name;

        // Upload
        if (!move_uploaded_file($file_tmp, $target_path)) {
            die("❌ File upload failed. The file may be too large or there was a server error.");
        }
    }

    // INSERT into DB
    $sql = "INSERT INTO lessons (teacher_id, title, description, content, created_at)
            VALUES (?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("SQL prepare failed: " . $conn->error . "<br>Query: " . $sql);
    }

    $stmt->bind_param("isss", $user_id, $title, $description, $file_name);
    $stmt->execute();
    $stmt->close();

    header("Location: mylesson.php?added=1");
    exit;
}
?>
