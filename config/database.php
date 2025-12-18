<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'classconnect');
define('DB_PORT', '3306');

// Create connection directly to the database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    // If connection failed, try to create the database
    $temp_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    
    if ($temp_conn->connect_error) {
        die("Connection failed: " . $temp_conn->connect_error);
    }
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    if ($temp_conn->query($sql) === FALSE) {
        die("Error creating database: " . $temp_conn->error);
    }
    
    $temp_conn->close();
    
    // Reconnect to the new database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
