<?php
require_once '../config/session.php';
checkRole('admin');
require_once '../config/database.php';

// Get statistics
$teachers_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='teacher'")->fetch_assoc()['count'];
$students_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='student'")->fetch_assoc()['count'];
$lessons_count = $conn->query("SELECT COUNT(*) as count FROM lessons")->fetch_assoc()['count'];
$assignments_count = $conn->query("SELECT COUNT(*) as count FROM assignments")->fetch_assoc()['count'];

// Get recent users
$recent_users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ClassConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ClassConnect</h2>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="register_teacher.php">Register Teacher</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="announcements.php">Announcements</a></li>
                <li><a href="profile.php">Profile Settings</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="topbar">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                    <span><?php echo $_SESSION['full_name']; ?></span>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Teachers</h4>
                    <div class="stat-value"><?php echo $teachers_count; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Students</h4>
                    <div class="stat-value"><?php echo $students_count; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Lessons</h4>
                    <div class="stat-value"><?php echo $lessons_count; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Assignments</h4>
                    <div class="stat-value"><?php echo $assignments_count; ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Recent Users</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'teacher' ? 'success' : 'warning'); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>
