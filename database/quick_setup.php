<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Database Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .status {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-family: monospace;
            white-space: pre-wrap;
        }
        .error { color: #dc3545; }
        .success { color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Setup</h1>
        <div class="status">
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'classconnect';

try {
    echo "Connecting to MySQL...\n";
    $conn = new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "✓ Connected to MySQL\n\n";
    
    echo "Dropping old database...\n";
    $conn->query("DROP DATABASE IF EXISTS `$database`");
    echo "✓ Old database dropped\n\n";
    
    echo "Creating new database...\n";
    if (!$conn->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        throw new Exception("Failed to create database: " . $conn->error);
    }
    echo "✓ Database created\n\n";
    
    echo "Selecting database...\n";
    $conn->select_db($database);
    echo "✓ Database selected\n\n";
    
    echo "Reading migration file...\n";
    $sqlFile = __DIR__ . '/migrations/001_initial_schema.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "✓ Migration file loaded (" . strlen($sql) . " bytes)\n\n";
    
    echo "Executing SQL statements...\n";
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql);
    $count = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || 
            strpos($statement, '--') === 0 || 
            strpos($statement, '/*') === 0) {
            continue;
        }
        
        if (!$conn->query($statement)) {
            echo "✗ Error executing statement:\n";
            echo substr($statement, 0, 200) . "...\n";
            echo "Error: " . $conn->error . "\n";
        } else {
            $count++;
        }
    }
    
    echo "✓ Executed $count SQL statements\n\n";
    
    // Verify tables were created
    echo "Verifying tables...\n";
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
        echo "  ✓ " . $row[0] . "\n";
    }
    
    if (count($tables) === 0) {
        throw new Exception("No tables were created!");
    }
    
    echo "\n✓✓✓ DATABASE SETUP COMPLETE! ✓✓✓\n\n";
    echo "You can now login with:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n\n";
    
    echo '<a href="../index.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;">Go to Login Page</a>';
    
} catch (Exception $e) {
    echo "\n✗✗✗ ERROR ✗✗✗\n";
    echo $e->getMessage() . "\n";
}
?>
        </div>
    </div>
</body>
</html>
