<?php

/**
 * Database Migration Runner
 * Run this script to automatically apply database migrations
 * 
 * Usage: php database/migrate.php
 * Or visit: http://localhost/mywebsite/database/migrate.php
 */

require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set execution time limit
set_time_limit(300);

class MigrationRunner
{
    private $conn;
    private $migrationsPath;

    public function __construct($connection)
    {
        $this->conn = $connection;
        $this->migrationsPath = __DIR__ . '/migrations/';
    }

    /**
     * Initialize migrations table if it doesn't exist
     */
    private function initMigrationsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) NOT NULL,
            `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        return $this->conn->query($sql);
    }

    /**
     * Get list of executed migrations
     */
    private function getExecutedMigrations()
    {
        $executed = [];
        $result = $this->conn->query("SELECT migration FROM migrations ORDER BY id");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $executed[] = $row['migration'];
            }
        }

        return $executed;
    }

    /**
     * Get list of pending migrations
     */
    private function getPendingMigrations()
    {
        $allMigrations = glob($this->migrationsPath . '*.sql');
        $executed = $this->getExecutedMigrations();
        $pending = [];

        foreach ($allMigrations as $file) {
            $filename = basename($file);
            if (!in_array($filename, $executed)) {
                $pending[] = $file;
            }
        }

        sort($pending); // Ensure migrations run in order
        return $pending;
    }

    /**
     * Execute a migration file
     */
    private function executeMigration($filepath)
    {
        $filename = basename($filepath);
        $sql = file_get_contents($filepath);

        if (empty($sql)) {
            throw new Exception("Migration file is empty: $filename");
        }

        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($stmt) {
                return !empty($stmt) &&
                    strpos($stmt, '--') !== 0 &&
                    strpos($stmt, '/*') !== 0;
            }
        );

        // Execute each statement
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

        // Record migration as executed
        $stmt = $this->conn->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->bind_param("s", $filename);

        if (!$stmt->execute()) {
            throw new Exception("Failed to record migration: " . $this->conn->error);
        }

        return true;
    }

    /**
     * Run all pending migrations
     */
    public function run()
    {
        $output = [];
        $output[] = "=== Database Migration Runner ===\n";

        try {
            // Initialize migrations table
            $this->initMigrationsTable();
            $output[] = "✓ Migrations table initialized\n";

            // Get pending migrations
            $pending = $this->getPendingMigrations();

            if (empty($pending)) {
                $output[] = "✓ No pending migrations. Database is up to date!\n";
                return $output;
            }

            $output[] = "Found " . count($pending) . " pending migration(s):\n";

            // Execute each migration
            foreach ($pending as $migration) {
                $filename = basename($migration);
                $output[] = "\nExecuting: $filename";

                try {
                    $this->executeMigration($migration);
                    $output[] = "  ✓ Success\n";
                } catch (Exception $e) {
                    $output[] = "  ✗ Failed: " . $e->getMessage() . "\n";
                    throw $e; // Stop on first error
                }
            }

            $output[] = "\n=== All migrations completed successfully! ===\n";
        } catch (Exception $e) {
            $output[] = "\n✗ Migration failed: " . $e->getMessage() . "\n";
            $output[] = "Please fix the error and run migrations again.\n";
        }

        return $output;
    }

    /**
     * Show migration status
     */
    public function status()
    {
        $output = [];
        $output[] = "=== Migration Status ===\n";

        try {
            $this->initMigrationsTable();

            $executed = $this->getExecutedMigrations();
            $pending = $this->getPendingMigrations();

            $output[] = "\nExecuted migrations (" . count($executed) . "):";
            foreach ($executed as $migration) {
                $output[] = "  ✓ $migration";
            }

            $output[] = "\nPending migrations (" . count($pending) . "):";
            if (empty($pending)) {
                $output[] = "  (none)";
            } else {
                foreach ($pending as $migration) {
                    $output[] = "  ○ " . basename($migration);
                }
            }
        } catch (Exception $e) {
            $output[] = "Error: " . $e->getMessage();
        }

        return $output;
    }
}

// Run migrations
$runner = new MigrationRunner($conn);

// Check if status flag is set
$action = isset($_GET['action']) ? $_GET['action'] : (isset($argv[1]) ? $argv[1] : 'run');

if ($action === 'status') {
    $output = $runner->status();
} else {
    $output = $runner->run();
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
    <title>Database Migrations</title>
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
            border-left: 4px solid #007bff;
            overflow-x: auto;
            line-height: 1.6;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
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
        <h1>Database Migration System</h1>
        <pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>
        <div class='actions'>
            <a href='migrate.php'>Run Migrations</a>
            <a href='migrate.php?action=status'>Check Status</a>
            <a href='../index.php'>Back to Home</a>
        </div>
    </div>
</body>
</html>";
}
