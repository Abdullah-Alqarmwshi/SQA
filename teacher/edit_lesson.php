<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: mylesson.php");
    exit;
}

$id      = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$res = $conn->query("SELECT * FROM lessons WHERE id=$id AND teacher_id=$user_id");
if (!$res || $res->num_rows === 0) {
    header("Location: mylesson.php");
    exit;
}
$lesson = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Lesson</title>

<link rel="stylesheet" href="../assets/css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f4f6fa;
    }

    /* Floating Card */
    .edit-modal-card {
        background: #fff;
        width: 60%;
        max-width: 750px;
        margin: 50px auto;
        padding: 30px 40px;
        border-radius: 18px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        animation: fadeIn 0.3s ease-out;
    }

    /* Fade In animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Form beautification */
    label {
        font-weight: 600;
        margin-bottom: 6px;
    }

    .form-control, select {
        border-radius: 10px;
        background: #f8f9fc;
        padding: 12px;
        border: 1px solid #dcdfe6;
    }

    .modal-header-title {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 15px;
    }

    .close-btn {
        float: right;
        font-size: 26px;
        color: #777;
        cursor: pointer;
        margin-top: -10px;
    }
    .close-btn:hover {
        color: #000;
    }

    .btn-success, .btn-secondary {
        padding: 10px 20px;
        border-radius: 8px;
    }
</style>

</head>
<body>

<div class="dashboard">

    <!-- SIDEBAR (same as dashboard.php) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>ClassConnect</h2>
            <p>Teacher Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="mylesson.php" class="active">My Lessons</a></li>
            <li><a href="assignments.php">Assignments</a></li>
            <li><a href="announcements_messages.php">Announcements</a></li>
            <li><a href="profile.php">Profile Settings</a></li>
            <li><a href="../logout.php">Logout</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOPBAR -->
        <div class="topbar">
            <h1>Edit Lesson</h1>
            <div class="user-info" onclick="toggleDropdown()">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <span><?= $_SESSION['full_name']; ?></span>
                <div class="user-dropdown" id="userDropdown">
                    <a href="profile.php">👤 Profile Settings</a>
                    <a href="../logout.php">🚪 Logout</a>
                </div>
            </div>
        </div>

        <!-- FLOATING EDIT CARD -->
        <div class="edit-modal-card">

            <span class="close-btn" onclick="window.location='mylesson.php'">&times;</span>

            <div class="modal-header-title">Edit Lesson</div>

            <form action="update_lesson.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $lesson['id']; ?>">

                <!-- Title -->
                <div class="mb-3">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" 
                        value="<?= htmlspecialchars($lesson['title']); ?>" required>
                </div>

                <!-- CATEGORY REMOVED -->

                <!-- Description -->
                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4" required><?= 
                        htmlspecialchars($lesson['description']); 
                    ?></textarea>
                </div>

                <!-- File -->
                <div class="mb-3">
                    <label>File</label>
                    <input type="file" name="file" class="form-control" required>

                    <?php if (!empty($lesson['content'])): ?>
                        <p class="mt-2">
                            Current file:
                            <a href="../uploads/<?= htmlspecialchars($lesson['content']); ?>" target="_blank">
                                <?= htmlspecialchars($lesson['content']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Buttons -->
                <div class="mt-4 text-end">
                    <a href="mylesson.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success">Update Lesson</button>
                </div>

            </form>

        </div>

    </main>

</div>

</body>
</html>
