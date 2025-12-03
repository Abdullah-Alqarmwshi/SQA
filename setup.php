<?php
require_once 'config/database.php';

$tables_created = [];
$errors = [];

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    profile_picture VARCHAR(255) DEFAULT 'default-avatar.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    $tables_created[] = 'Users table';
} else {
    $errors[] = "Error creating users table: " . $conn->error;
}

// Create lessons table
$sql = "CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content TEXT,
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    $tables_created[] = 'Lessons table';
} else {
    $errors[] = "Error creating lessons table: " . $conn->error;
}

// Create assignments table
$sql = "CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    due_date DATETIME,
    max_score INT DEFAULT 100,
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    $tables_created[] = 'Assignments table';
} else {
    $errors[] = "Error creating assignments table: " . $conn->error;
}

// Create submissions table
$sql = "CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    submission_text TEXT,
    score INT,
    feedback TEXT,
    status ENUM('pending', 'graded') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    $tables_created[] = 'Submissions table';
} else {
    $errors[] = "Error creating submissions table: " . $conn->error;
}

// Create announcements table
$sql = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('Academic', 'Event', 'General Notice', 'Administrative', 'Reminder') DEFAULT 'General Notice',
    type ENUM('general', 'urgent', 'event') DEFAULT 'general',
    event_date DATETIME,
    expiry_date DATETIME,
    target_audience ENUM('All Teachers', 'All Students', 'Specific') DEFAULT 'All Students',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_category (category)
)";

if ($conn->query($sql) === TRUE) {
    $tables_created[] = 'Announcements table';
} else {
    $errors[] = "Error creating announcements table: " . $conn->error;
}

// Create announcement responses table
$sql = "CREATE TABLE IF NOT EXISTS announcement_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    response_text TEXT,
    response_type ENUM('confirmed', 'declined', 'comment') DEFAULT 'comment',
    responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_response (announcement_id, user_id)
)";

if ($conn->query($sql) === TRUE) {
    $tables_created[] = 'Announcement Responses table';
} else {
    $errors[] = "Error creating announcement responses table: " . $conn->error;
}

// Create messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    $tables_created[] = 'Messages table';
} else {
    $errors[] = "Error creating messages table: " . $conn->error;
}

// Insert default admin account
$admin_username = 'admin';
$admin_password = password_hash('admin', PASSWORD_DEFAULT);
$admin_email = 'admin@classconnect.com';
$admin_name = 'Administrator';

$check_admin = $conn->query("SELECT id FROM users WHERE username = '$admin_username'");
if ($check_admin->num_rows == 0) {
    $sql = "INSERT INTO users (username, password, full_name, email, role) 
            VALUES ('$admin_username', '$admin_password', '$admin_name', '$admin_email', 'admin')";
    
    if ($conn->query($sql) === TRUE) {
        $admin_created = true;
    } else {
        $errors[] = "Error creating admin account: " . $conn->error;
    }
} else {
    $admin_created = false;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - ClassConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="success-icon"></div>
            <h1>Database Setup Complete!</h1>
            <p>ClassConnect has been successfully configured.</p>
            
            <div class="info-box">
                <h3>Default Admin Credentials:</h3>
                <div class="credential-item">
                    <strong>Username:</strong> admin
                </div>
                <div class="credential-item">
                    <strong>Password:</strong> admin
                </div>
            </div>
            
            <div class="tables-created">
                <h3>Tables Created:</h3>
                <ul>
                    <?php foreach ($tables_created as $table): ?>
                        <li> <?php echo $table; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <h3>Errors:</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <a href="index.php" class="btn btn-primary">Go to Login Page</a>
        </div>
    </div>
</body>
</html>
