<?php
require_once 'config/database.php';

// Fix all submissions with incorrect file paths
$stmt = $conn->prepare("SELECT id, file_path FROM submissions WHERE file_path LIKE '../%'");
$stmt->execute();
$result = $stmt->get_result();

$count = 0;
while ($row = $result->fetch_assoc()) {
    // Remove '../' prefix and adjust path
    $old_path = $row['file_path'];
    $new_path = 'uploads/submissions/' . basename($old_path);
    
    $update_stmt = $conn->prepare("UPDATE submissions SET file_path = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_path, $row['id']);
    if ($update_stmt->execute()) {
        $count++;
    }
}

echo "Updated $count submissions with corrected file paths.";
?>
