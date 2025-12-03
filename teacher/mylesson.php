<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$lessons = $conn->query("SELECT * FROM lessons WHERE teacher_id=$user_id ORDER BY created_at DESC");

$popupMessage = '';
if (isset($_GET['updated'])) $popupMessage = 'Lesson updated successfully!';
if (isset($_GET['deleted'])) $popupMessage = 'Lesson deleted successfully!';
if (isset($_GET['added']))   $popupMessage = 'Lesson added successfully!';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Lessons - ClassConnect</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

/* PAGE SPACING */
.main-content {
    padding: 35px 50px;
}

/* PAGE HEADER */
.page-title {
    font-size: 26px;
    font-weight: 600;
    margin-bottom: 20px;
}

/* CREATE BUTTON */
.btn-create {
    background: #5b5de6;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 500;
}
.btn-create:hover {
    background: #4748d6;
}

/* LESSON CARD */
.lesson-card {
    background: #ffffff;
    border-radius: 15px;
    padding: 20px 25px;
    margin: 20px auto;
    max-width: 900px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.lesson-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
}

.lesson-desc {
    font-size: 15px;
    color: #777;
    margin-top: 5px;
}

/* BUTTON SPACING */
.lesson-card .btn {
    margin-left: 8px;
}

/* MODAL FIX */
.modal-content {
    border-radius: 15px;
}
.modal-body label {
    font-weight: 500;
    margin-bottom: 5px;
}

.modal-body input,
.modal-body textarea,
.modal-body select {
    background: #f8f9fc;
    border: 1px solid #dcdfe6;
    border-radius: 8px;
}

/* POPUP MESSAGE */
.center-popup {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: #28a745;
    color: #fff;
    padding: 16px 28px;
    border-radius: 10px;
    font-weight: 500;
    opacity: 0;
    z-index: 2000;
    animation: fadeInOut 3s forwards;
}
@keyframes fadeInOut {
    0% { opacity: 0; transform: translate(-50%, -55%); }
    10% { opacity: 1; transform: translate(-50%, -50%); }
    80% { opacity: 1; }
    100% { opacity: 0; transform: translate(-50%, -45%); }
}
</style>
</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR (exact from dashboard.php) -->
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

        <!-- TOPBAR (same as dashboard) -->
        <div class="topbar">
            <h1>My Lessons</h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                <span><?php echo $_SESSION['full_name']; ?></span>
            </div>
        </div>

        <!-- POPUP -->
        <?php if (!empty($popupMessage)): ?>
            <div class="center-popup"><?php echo $popupMessage; ?></div>
        <?php endif; ?>

        <!-- HEADER + BUTTON -->
        <div class="d-flex justify-content-end align-items-center mt-4 mb-3">
    <button class="btn-create" data-bs-toggle="modal" data-bs-target="#addLessonModal">
        + Create Lesson
    </button>
</div>


        <!-- LESSON LIST -->
        <?php if ($lessons->num_rows > 0): ?>
            <?php while ($lesson = $lessons->fetch_assoc()): ?>
                
                <div class="lesson-card">

                    <div class="lesson-title">
                        <?= htmlspecialchars($lesson['title']); ?>
                    </div>

                    <div class="lesson-desc">
                        <?= htmlspecialchars(substr($lesson['description'], 0, 120)); ?>...
                    </div>

                    <small class="text-muted d-block mt-1 mb-3">
                        <?= date("M d, Y", strtotime($lesson['created_at'])); ?>
                    </small>

                    <div>
                        <?php if (!empty($lesson['file_path'])): ?>
                            <a href="../uploads/<?= $lesson['file_path']; ?>" 
                               class="btn btn-sm btn-outline-primary" 
                               target="_blank">View</a>
                        <?php endif; ?>

                        <a href="edit_lesson.php?id=<?= $lesson['id']; ?>" 
                           class="btn btn-sm btn-outline-success">Edit</a>

                        <form action="delete_lesson.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('Delete this lesson?')">
                            <input type="hidden" name="id" value="<?= $lesson['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </div>

                </div>

            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted">No lessons found.</p>
        <?php endif; ?>

    </main>
</div>


<!-- ADD LESSON MODAL -->
<div class="modal fade" id="addLessonModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content p-3">

      <form action="add_lesson.php" method="POST" enctype="multipart/form-data">

        <div class="modal-header">
          <h4>Add Lesson</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="mb-3">
            <label>Title</label>
            <input type="text" name="title" class="form-control" required>
          </div>

          <div class="mb-3">
            <label>Category</label>
            <select name="category" class="form-control" required>
              <option value="" disabled selected>Select category</option>
              <option value="Science">Science</option>
              <option value="Mathematics">Mathematics</option>
              <option value="English">English</option>
              <option value="History">History</option>
              <option value="Geography">Geography</option>
              <option value="ICT">ICT</option>
              <option value="Others">Others</option>
            </select>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3" required></textarea>
          </div>

          <div class="mb-3">
            <label>Attachment (optional)</label>
            <input type="file" name="file" class="form-control">
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Lesson</button>
        </div>

      </form>

    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
