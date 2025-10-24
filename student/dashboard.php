<?php
require_once '../config/session.php';
checkRole('student');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Get statistics
$lessons_count = $conn->query("SELECT COUNT(*) as count FROM lessons")->fetch_assoc()['count'];
$my_submissions = $conn->query("SELECT COUNT(*) as count FROM submissions WHERE student_id=$user_id")->fetch_assoc()['count'];
$pending_assignments = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE id NOT IN (SELECT assignment_id FROM submissions WHERE student_id=$user_id)")->fetch_assoc()['count'];

// Get recent lessons
$recent_lessons = $conn->query("SELECT l.*, u.full_name as teacher_name FROM lessons l JOIN users u ON l.teacher_id = u.id ORDER BY l.created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ClassConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ClassConnect</h2>
                <p>Student Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="lessons.php">Browse Lessons</a></li>
                <li><a href="assignments.php">Assignments</a></li>
                <li><a href="submissions.php">My Submissions</a></li>
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
                    <h4>Available Lessons</h4>
                    <div class="stat-value"><?php echo $lessons_count; ?></div>
                </div>
                <div class="stat-card">
                    <h4>My Submissions</h4>
                    <div class="stat-value"><?php echo $my_submissions; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Pending Assignments</h4>
                    <div class="stat-value"><?php echo $pending_assignments; ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Recent Lessons</h3>
                </div>
                <?php if ($recent_lessons->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Teacher</th>
                            <th>Description</th>
                            <th>Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($lesson = $recent_lessons->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                            <td><?php echo htmlspecialchars($lesson['teacher_name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($lesson['description'], 0, 40)) . '...'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($lesson['created_at'])); ?></td>
                            <td>
                                <a href="lessons.php?view=<?php echo $lesson['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px;">View</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; padding: 20px; color: #666;">No lessons available yet.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
<?php $conn->close(); ?>
