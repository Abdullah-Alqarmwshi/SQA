<?php
require_once 'config/database.php';

$username = 'admin';
$password = 'admin';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$full_name = 'Administrator';
$email = 'admin@classconnect.com';
$role = 'admin';

$stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);

if ($stmt->execute()) {
    echo "Admin user created successfully!";
} else {
    echo "Error creating admin user: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>