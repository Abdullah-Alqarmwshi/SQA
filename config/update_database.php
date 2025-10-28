<?php
require_once 'database.php';

// Read and execute the SQL file
$sql = file_get_contents(__DIR__ . '/update_schema.sql');

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

// Execute each statement
$success = true;
foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            if (!$conn->query($statement)) {
                $success = false;
                echo "Error executing statement: " . $conn->error . "\n";
                echo "Statement: " . $statement . "\n\n";
            }
        } catch (Exception $e) {
            $success = false;
            echo "Error: " . $e->getMessage() . "\n";
            echo "Statement: " . $statement . "\n\n";
        }
    }
}

if ($success) {
    echo "Database schema updated successfully!\n";
} else {
    echo "Some errors occurred while updating the database schema.\n";
}
?>