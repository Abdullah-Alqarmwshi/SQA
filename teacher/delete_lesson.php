<?php
require_once '../config/session.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $lesson_id = intval($_POST['id']);

    // Check if file exists and delete it
    $result = $conn->query("SELECT file_path FROM lessons WHERE id=$lesson_id");
    if ($result && $result->num_rows > 0) {
        $file = $result->fetch_assoc();
        if (!empty($file['file_path']) && file_exists("../uploads/" . $file['file_path'])) {
            unlink("../uploads/" . $file['file_path']);
        }
    }

    // Delete from DB
    $conn->query("DELETE FROM lessons WHERE id=$lesson_id");

    // Redirect with success flag
    header("Location: mylesson.php?deleted=1");
    exit;
}

// Fallback redirect
header("Location: mylesson.php");
exit;
?>

