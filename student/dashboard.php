<?php
require_once '../config/session.php';
checkRole('student');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Get statistics
$lessons_count = $conn->query("SELECT COUNT(*) as count FROM lessons")->fetch_assoc()['count'];
$my_submissions = $conn->query("SELECT COUNT(DISTINCT assignment_id) as count FROM submissions WHERE student_id=$user_id")->fetch_assoc()['count'];
$pending_assignments = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE id NOT IN (SELECT DISTINCT assignment_id FROM submissions WHERE student_id=$user_id)")->fetch_assoc()['count'];

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
                <li><a href="lesson.php">Lesson</a></li>
                <li><a href="assignments.php">Assignment</a></li>
                <li><a href="announcements_messages.php">Announcement</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <?php $page_title = 'Dashboard'; require_once __DIR__ . '/../includes/topbar.php'; ?>
            
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

            <!-- Optional: simple welcome card instead of Recent Lessons -->
            <!--
            <div class="card">
                <div class="card-header">
                    <h3>Welcome!</h3>
                </div>
                <p style="padding: 20px; color: #666;">
                    Use the sidebar to browse lessons, view assignments, and check your submissions.
                </p>
            </div>
            -->
        </main>
    </div>
    <script>
        function toggleDropdown() {
            document.getElementById('userDropdown').classList.toggle('active');
        }
        
        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            if (!e.target.closest('.user-info')) {
                document.getElementById('userDropdown').classList.remove('active');
            }
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>
