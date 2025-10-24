<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Check if user role matches the required role for this page
function checkRole($required_role) {
    if ($_SESSION['role'] != $required_role) {
        header('Location: ../index.php');
        exit;
    }
}
?>
