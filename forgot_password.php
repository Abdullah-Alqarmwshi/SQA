<?php
session_start();
require_once 'config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT id, username, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database (you'll need to create this table)
        // For now, we'll store it in session for demo purposes
        $_SESSION['reset_token'] = $token;
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['reset_expiry'] = $expiry;

        // In production, you would send an email here
        // For demo, we'll just show a link
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

        $message = "Password reset instructions have been sent to your email.<br><br>
                    <strong>Demo Mode:</strong> Click here to reset your password:<br>
                    <a href='reset_password.php?token=$token' class='btn btn-primary' style='display:inline-block; margin-top:10px;'>Reset Password</a>";
    } else {
        $error = 'No account found with that email address.';
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ClassConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo">
                <h1>ClassConnect</h1>
                <p>Reset Your Password</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php else: ?>
                <p style="text-align: center; color: #666; margin-bottom: 20px;">
                    Enter your email address and we'll send you instructions to reset your password.
                </p>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Send Reset Link</button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <p><a href="index.php">Back to Login</a></p>
            </div>
        </div>
    </div>
</body>

</html>