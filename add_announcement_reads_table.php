<?php
/**
 * Database Migration: Add announcement_reads table
 * This table tracks which announcements have been read by which students
 * 
 * Run this script once by navigating to:
 * http://localhost/SQA/add_announcement_reads_table.php
 */

require_once 'config/database.php';

$success = false;
$error_message = '';

try {
    // Check if table already exists
    $check = $conn->query("SHOW TABLES LIKE 'announcement_reads'");
    
    if ($check->num_rows > 0) {
        $error_message = "Table 'announcement_reads' already exists. No action needed.";
    } else {
        // Create announcement_reads table
        $sql = "CREATE TABLE announcement_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            announcement_id INT NOT NULL,
            user_id INT NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_read (announcement_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($conn->query($sql) === TRUE) {
            $success = true;
        } else {
            $error_message = "Error creating table: " . $conn->error;
        }
    }
} catch (Exception $e) {
    $error_message = "Exception: " . $e->getMessage();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - Announcement Reads</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .message {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: left;
        }
        .message.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .message.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .message.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: left;
            font-size: 14px;
            color: #666;
        }
        .details strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="icon success">✅</div>
            <h1>Migration Successful!</h1>
            <div class="message success">
                <strong>Success:</strong> The 'announcement_reads' table has been created successfully.
            </div>
            <div class="details">
                <strong>What was created:</strong><br>
                • Table: <code>announcement_reads</code><br>
                • Columns: id, announcement_id, user_id, read_at<br>
                • Foreign keys to announcements and users tables<br>
                • Unique constraint to prevent duplicate reads<br><br>
                <strong>Features enabled:</strong><br>
                • Track which announcements students have read<br>
                • Unread announcement badges<br>
                • "Mark as Read" functionality<br>
                • "Mark All Read" feature<br>
                • Unread filters
            </div>
        <?php elseif ($error_message && strpos($error_message, 'already exists') !== false): ?>
            <div class="icon warning">⚠️</div>
            <h1>Already Migrated</h1>
            <div class="message warning">
                <strong>Notice:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
            <div class="details">
                The announcement read tracking system is already set up. No changes were made to your database.
            </div>
        <?php else: ?>
            <div class="icon error">❌</div>
            <h1>Migration Failed</h1>
            <div class="message error">
                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
            <div class="details">
                <strong>Troubleshooting:</strong><br>
                • Make sure your database connection is working<br>
                • Verify that the 'announcements' and 'users' tables exist<br>
                • Check that your database user has CREATE TABLE permissions<br>
                • Review the error message above for specific details
            </div>
        <?php endif; ?>
        
        <a href="login.php" class="btn">← Back to Login</a>
    </div>
</body>
</html>

