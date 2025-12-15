<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Admin cannot delete their account
if ($_SESSION['role'] == 'admin') {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    $password = $_POST['password'];
    
    // Verify password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (password_verify($password, $user['password'])) {
        // Delete user account (CASCADE will delete related data)
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            session_destroy();
            header('Location: ../index.php?deleted=1');
            exit;
        } else {
            $error = 'Failed to delete account';
        }
        $stmt->close();
    } else {
        $error = 'Incorrect password';
    }
}

$role = $_SESSION['role'];
$panel_name = ucfirst($role) . ' Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account - ClassConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ClassConnect</h2>
                <p><?php echo $panel_name; ?></p>
            </div>
        <script>
            // Confirm deletion
            function confirmDelete() {
                return confirm('Are you sure you want to permanently delete your account? This action cannot be undone.');
            }
        </script>
        <script src="../assets/js/main.js"></script>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php if ($role == 'teacher'): ?>
                    </div>
                    <li><a href="assignments.php">Assignments</a></li>
                <?php else: ?>
                    <li><a href="lesson.php">Lesson</a></li>
                    <li><a href="assignments.php">Assignment</a></li>
                <?php endif; ?>
                <li><a href="announcements_messages.php">Announcement</a></li>
                <!-- profile and logout moved to topbar dropdown -->
            </ul>
        </aside>
        
        <main class="main-content">
            <?php $page_title = 'Delete Account'; require_once __DIR__ . '/../includes/topbar.php'; ?>
            
            <div class="card" style="border-left: 4px solid #f44336;">
                <div class="card-header">
                    <h3 style="color: #f44336;"> Warning: This action is permanent!</h3>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <h4 style="color: #856404; margin-bottom: 10px;">Before you delete your account:</h4>
                    <ul style="color: #856404; margin-left: 20px;">
                        <li>All your personal data will be permanently deleted</li>
                        <?php if ($role == 'teacher'): ?>
                            <li>All lessons and assignments you created will be deleted</li>
                            <li>Student submissions related to your assignments will be removed</li>
                        <?php else: ?>
                            <li>All your assignment submissions will be deleted</li>
                            <li>Your grades and feedback will be lost</li>
                        <?php endif; ?>
                        <li>This action cannot be undone</li>
                    </ul>
                </div>
                
                <form method="POST" action="" onsubmit="return confirm('Are you absolutely sure? This cannot be undone!');">
                    <div class="form-group">
                        <label for="password">Enter your password to confirm deletion:</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="confirm_delete" class="btn btn-danger">Yes, Delete My Account</button>
                        <a href="profile.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>
