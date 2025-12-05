<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Get statistics
$lessons_count = $conn->query("SELECT COUNT(*) as count FROM lessons WHERE teacher_id=$user_id")->fetch_assoc()['count'];
$assignments_count = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE teacher_id=$user_id")->fetch_assoc()['count'];
$students_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='student'")->fetch_assoc()['count'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - ClassConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .user-info {
            position: relative;
            cursor: pointer;
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            min-width: 200px;
            display: none;
            z-index: 1000;
        }
        
        .user-dropdown.active {
            display: block;
        }
        
        .user-dropdown a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .user-dropdown a:hover {
            background: #f5f5f5;
        }
        
        .user-dropdown a:first-child {
            border-radius: 8px 8px 0 0;
        }
        
        .user-dropdown a:last-child {
            border-radius: 0 0 8px 8px;
            color: #dc3545;
        }
        
        .user-dropdown hr {
            margin: 0;
            border: none;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ClassConnect</h2>
                <p>Teacher Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="mylesson.php">My Lessons</a></li>
                <li><a href="assignments.php">Assignments</a></li>
                <li><a href="announcements_messages.php">Announcements</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="topbar">
                <h1>Dashboard</h1>
                <div class="user-info" onclick="toggleDropdown()">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                    <span><?php echo $_SESSION['full_name']; ?></span>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php">👤 Profile Settings</a>
                        <hr>
                        <a href="../logout.php">🚪 Logout</a>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>My Lessons</h4>
                    <div class="stat-value"><?php echo $lessons_count; ?></div>
                </div>
                <div class="stat-card">
                    <h4>My Assignments</h4>
                    <div class="stat-value"><?php echo $assignments_count; ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Students</h4>
                    <div class="stat-value"><?php echo $students_count; ?></div>
                </div>
            </div>

            <!-- You can optionally add some welcome text here -->
            <!--
            <div class="card">
                <div class="card-header">
                    <h3>Welcome back!</h3>
                </div>
                <p style="padding: 20px; color: #666;">
                    Use the sidebar to manage your lessons, assignments and announcements.
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
</body>
</html>
<?php $conn->close(); ?>
