<?php
require_once '../config/session.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = '';
$success = '';

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, address=? WHERE id=?");
    $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $success = 'Profile updated successfully!';
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $error = 'Failed to update profile';
    }
    $stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($new_password != $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success = 'Password changed successfully!';
        } else {
            $error = 'Failed to change password';
        }
        $stmt->close();
    }
}

$panel_name = ucfirst($role) . ' Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - ClassConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ClassConnect</h2>
                <p><?php echo $panel_name; ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php if ($role == 'admin'): ?>
                    <li><a href="register_teacher.php">Register Teacher</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                <?php elseif ($role == 'teacher'): ?>
                    <li><a href="mylesson.php">My Lessons</a></li>
                    <li><a href="assignments.php">Assignments</a></li>
                    <li><a href="announcements_messages.php">Announcements</a></li>
                <?php else: ?>
                    <li><a href="lessons.php">Browse Lessons</a></li>
                    <li><a href="assignments.php">Assignments</a></li>
                    <li><a href="submissions.php">My Submissions</a></li>
                <?php endif; ?>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="topbar">
                <h1>Profile Settings</h1>
                <div class="user-info" onclick="toggleDropdown()">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                    <span><?php echo $_SESSION['full_name']; ?></span>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php">👤 Profile Settings</a>
                        <a href="../logout.php">🚪 Logout</a>
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="modern-alert modern-alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="modern-alert modern-alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-header-content">
                        <div class="profile-avatar-large">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
                            <p><?php echo ucfirst($role); ?> Account • <?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="profile-container">
                <div class="profile-grid">
                    <div class="modern-card">
                        <div class="modern-card-header">
                            <div class="card-icon">👤</div>
                            <h3>Personal Information</h3>
                        </div>
                        <form method="POST" action="">
                            <div class="modern-form-group">
                                <label for="username">🔒 Username (cannot be changed)</label>
                                <input type="text" id="username" class="modern-form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            </div>
                            
                            <div class="modern-form-group">
                                <label for="full_name">✏️ Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="modern-form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="modern-form-group">
                                <label for="email">📧 Email</label>
                                <input type="email" id="email" name="email" class="modern-form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="modern-form-group">
                                    <label for="phone">📱 Phone</label>
                                    <input type="tel" id="phone" name="phone" class="modern-form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                            </div>
                            
                            <div class="modern-form-group">
                                <label for="address">📍 Address</label>
                                <textarea id="address" name="address" class="modern-form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            
                            <button type="submit" name="update_profile" class="modern-btn modern-btn-primary">
                                <span>💾 Update Profile</span>
                            </button>
                        </form>
                    </div>
                    
                    <div class="modern-card">
                        <div class="modern-card-header">
                            <div class="card-icon">🔐</div>
                            <h3>Change Password</h3>
                        </div>
                        <form method="POST" action="">
                            <div class="modern-form-group">
                                <label for="current_password">🔑 Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="modern-form-control" required>
                            </div>
                            
                            <div class="modern-form-group">
                                <label for="new_password">🆕 New Password</label>
                                <input type="password" id="new_password" name="new_password" class="modern-form-control" required>
                            </div>
                            
                            <div class="modern-form-group">
                                <label for="confirm_password">✓ Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="modern-form-control" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="modern-btn modern-btn-primary">
                                <span>🔄 Change Password</span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if ($role != 'admin'): ?>
                <div class="modern-card danger-zone">
                    <div class="modern-card-header">
                        <div class="card-icon">⚠️</div>
                        <h3 style="color: var(--danger-color);">Danger Zone</h3>
                    </div>
                    <p style="margin-bottom: 20px; color: var(--light-text);">Once you delete your account, there is no going back. Please be certain.</p>
                    <a href="delete_account.php" class="modern-btn modern-btn-danger" onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                        <span>🗑️ Delete My Account</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
<script src="../assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>
