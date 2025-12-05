<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Verify token
if (empty($token) || !isset($_SESSION['reset_token']) || $token !== $_SESSION['reset_token']) {
    $error = 'Invalid or expired reset link.';
} elseif (isset($_SESSION['reset_expiry']) && strtotime($_SESSION['reset_expiry']) < time()) {
    $error = 'This reset link has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_id = $_SESSION['reset_user_id'];
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Clear reset session data
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_expiry']);
            
            $success = 'Your password has been reset successfully! You can now login with your new password.';
        } else {
            $error = 'An error occurred. Please try again.';
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ClassConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo">
                <h1>ClassConnect</h1>
                <p>Create New Password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
                <div class="text-center mt-3">
                    <p><a href="forgot_password.php">Request New Reset Link</a></p>
                    <p><a href="index.php">Back to Login</a></p>
                </div>
            <?php elseif ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <a href="index.php" class="btn btn-primary">Go to Login</a>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               minlength="6" required>
                        <small style="color: #666;">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
                
                <div class="text-center mt-3">
                    <p><a href="index.php">Back to Login</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
