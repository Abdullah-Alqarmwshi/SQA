<?php
session_start();
require_once 'config/database.php';

// Check if database tables exist, if not redirect to setup
$tables_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($tables_check->num_rows == 0) {
    header('Location: setup.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'teacher':
                    header('Location: teacher/dashboard.php');
                    break;
                case 'student':
                    header('Location: student/dashboard.php');
                    break;
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Invalid username or password';
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
    <title>Login - ClassConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo">
                <h1>ClassConnect</h1>
                <p>Digital Learning Management System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <div class="text-right mb-3">
                    <a href="forgot_password.php" style="color: #667eea; text-decoration: none; font-size: 14px;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary">Login</button>
            </form>

            <div class="text-center mt-3">
                <p>Don't have an account? <a href="register.php">Register as Student</a></p>
            </div>
        </div>
    </div>
</body>

</html>