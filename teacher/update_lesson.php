<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = intval($_POST['id']);
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $file_name   = '';

    // Get old file name stored in "content"
    $res = $conn->query("SELECT content FROM lessons WHERE id=$id");
    $old = $res && $res->num_rows > 0 ? $res->fetch_assoc()['content'] : '';

    if (!empty($_FILES['file']['name'])) {
        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name   = time() . '_' . basename($_FILES['file']['name']);
        $target_path = $upload_dir . $file_name;
        move_uploaded_file($_FILES['file']['tmp_name'], $target_path);

        // remove old file
        if (!empty($old) && file_exists("../uploads/$old")) {
            unlink("../uploads/$old");
        }
    } else {
        $file_name = $old;
    }

    $stmt = $conn->prepare("UPDATE lessons SET title=?, description=?, content=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("sssi", $title, $description, $file_name, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: mylesson.php?updated=1");
    exit;
}
