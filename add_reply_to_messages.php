<?php
/**
 * Migration Script: Add reply_to field to messages table
 * This allows messages to be threaded/replied to
 */

require_once 'config/database.php';

$success = [];
$errors = [];

// Check if reply_to column already exists
$check_column = $conn->query("SHOW COLUMNS FROM messages LIKE 'reply_to'");

if ($check_column->num_rows == 0) {
    // Add reply_to column
    $sql = "ALTER TABLE messages ADD COLUMN reply_to INT NULL AFTER recipient_id,
            ADD FOREIGN KEY (reply_to) REFERENCES messages(id) ON DELETE SET NULL";
    
    if ($conn->query($sql) === TRUE) {
        $success[] = "Successfully added 'reply_to' column to messages table";
    } else {
        $errors[] = "Error adding reply_to column: " . $conn->error;
    }
} else {
    $success[] = "'reply_to' column already exists in messages table";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - ClassConnect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .success-box, .error-box {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .success-box h3, .error-box h3 {
            margin-bottom: 10px;
            font-size: 16px;
        }
        ul {
            list-style: none;
            padding-left: 0;
        }
        li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        li:last-child {
            border-bottom: none;
        }
        li:before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
        }
        .error-box li:before {
            content: "✗ ";
            color: #dc3545;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            color: #014361;
        }
        .info strong {
            display: block;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Database Migration Complete</h1>
        <p class="subtitle">Message Reply Feature Added</p>
        
        <?php if (!empty($success)): ?>
            <div class="success-box">
                <h3>✅ Success</h3>
                <ul>
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <h3>❌ Errors</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <strong>What's New:</strong>
            Messages can now be replied to! Users can:
            <ul style="margin-top: 10px;">
                <li>Reply to received messages</li>
                <li>View message threads</li>
                <li>See conversation history</li>
            </ul>
        </div>
        
        <a href="index.php" class="btn">← Back to Login</a>
    </div>
</body>
</html>

