<?php
/**
 * Database Reset Script
 * This will drop and recreate the database, then run all migrations
 * 
 * WARNING: This will DELETE ALL DATA!
 * 
 * Usage: php database/reset_database.php
 * Or visit: http://localhost/mywebsite/database/reset_database.php
 */

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'classconnect';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set execution time limit
set_time_limit(300);

$output = [];
$output[] = "=== Database Reset Script ===\n";

try {
    // Connect to MySQL without selecting a database
    $conn = new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $output[] = "✓ Connected to MySQL server\n";
    
    // Drop database if exists
    $output[] = "Dropping database '$database' if exists...";
    if ($conn->query("DROP DATABASE IF EXISTS `$database`")) {
        $output[] = "  ✓ Database dropped\n";
    } else {
        throw new Exception("Error dropping database: " . $conn->error);
    }
    
    // Create database
    $output[] = "Creating database '$database'...";
    if ($conn->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        $output[] = "  ✓ Database created\n";
    } else {
        throw new Exception("Error creating database: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db($database);
    
    $output[] = "\n✓ Database reset complete!";
    $output[] = "\nNow running migrations...\n";
    
    // Now run migrations directly
    require_once '../config/database.php';
    
    // Create migration runner
    class MigrationRunner {
        private $conn;
        private $migrationsPath;
        
        public function __construct($connection) {
            $this->conn = $connection;
            $this->migrationsPath = __DIR__ . '/migrations/';
        }
        
        private function initMigrationsTable() {
            $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `migration` varchar(255) NOT NULL,
                `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            return $this->conn->query($sql);
        }
        
        private function getExecutedMigrations() {
            $executed = [];
            $result = $this->conn->query("SELECT migration FROM migrations ORDER BY id");
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $executed[] = $row['migration'];
                }
            }
            
            return $executed;
        }
        
        private function getPendingMigrations() {
            $allMigrations = glob($this->migrationsPath . '*.sql');
            $executed = $this->getExecutedMigrations();
            $pending = [];
            
            foreach ($allMigrations as $file) {
                $filename = basename($file);
                if (!in_array($filename, $executed)) {
                    $pending[] = $file;
                }
            }
            
            sort($pending);
            return $pending;
        }
        
        private function executeMigration($filepath) {
            $filename = basename($filepath);
            $sql = file_get_contents($filepath);
            
            if (empty($sql)) {
                throw new Exception("Migration file is empty: $filename");
            }
            
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && 
                           strpos($stmt, '--') !== 0 && 
                           strpos($stmt, '/*') !== 0;
                }
            );
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    if (!$this->conn->query($statement)) {
                        throw new Exception(
                            "Error in migration $filename: " . $this->conn->error . 
                            "\nSQL: " . substr($statement, 0, 200)
                        );
                    }
                }
            }
            
            $stmt = $this->conn->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->bind_param("s", $filename);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to record migration: " . $this->conn->error);
            }
            
            return true;
        }
        
        public function run() {
            $result = [];
            
            $this->initMigrationsTable();
            $result[] = "✓ Migrations table initialized";
            
            $pending = $this->getPendingMigrations();
            
            if (empty($pending)) {
                $result[] = "✓ No pending migrations";
                return $result;
            }
            
            $result[] = "Found " . count($pending) . " pending migration(s)";
            
            foreach ($pending as $migration) {
                $filename = basename($migration);
                $result[] = "Executing: $filename";
                
                $this->executeMigration($migration);
                $result[] = "  ✓ Success";
            }
            
            $result[] = "\n✓ All migrations completed!";
            
            return $result;
        }
    }
    
    $runner = new MigrationRunner($conn);
    $migrationOutput = $runner->run();
    $output = array_merge($output, $migrationOutput);
    
} catch (Exception $e) {
    $output[] = "\n✗ Error: " . $e->getMessage();
}

// Output results
if (php_sapi_name() === 'cli') {
    // Command line output
    foreach ($output as $line) {
        echo $line . "\n";
    }
} else {
    // Web browser output
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Reset</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        pre {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
            overflow-x: auto;
            line-height: 1.6;
        }
        h1 {
            color: #dc3545;
            border-bottom: 3px solid #dc3545;
            padding-bottom: 10px;
        }
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            color: #856404;
        }
        .actions {
            margin-top: 20px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 5px;
        }
        .actions a {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 10px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .actions a:hover {
            background: #0056b3;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>⚠️ Database Reset Complete</h1>
        <div class='warning'>
            <strong>Warning:</strong> All previous data has been deleted and the database has been recreated from migrations.
        </div>
        <pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>
        <div class='actions'>
            <a href='../index.php'>Go to Login</a>
            <a href='migrate.php?action=status'>Check Migration Status</a>
        </div>
        <div style='margin-top: 20px; padding: 15px; background: #d1ecf1; border-radius: 5px; color: #0c5460;'>
            <strong>Default Admin Account:</strong><br>
            Username: <code>admin</code><br>
            Password: <code>admin123</code>
        </div>
    </div>
</body>
</html>";
}
?>
