<?php
include 'config/database.php';

// Add missing target_audience column to announcements table
$sql = "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_audience ENUM('All Teachers', 'All Students', 'Specific') DEFAULT 'All Students'";

if ($conn->query($sql) === TRUE) {
    echo "✓ Successfully added target_audience column to announcements table\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Create announcement_responses table if it doesn't exist
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
    echo "✓ Successfully created announcement_responses table\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Create announcement_reads table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS announcement_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (announcement_id, user_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ Successfully created announcement_reads table\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Create messages table if it doesn't exist
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
    echo "✓ Successfully created messages table\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>
