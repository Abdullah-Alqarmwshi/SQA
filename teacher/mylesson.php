<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$lessons = $conn->query("SELECT * FROM lessons WHERE teacher_id=$user_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Lessons | ClassConnect</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      background-color: #f7f9fc;
      font-family: 'Poppins', sans-serif;
      display: flex;
    }
    .sidebar {
      width: 230px;
      height: 100vh;
      background: linear-gradient(to bottom, #5b5de6, #8f65e7);
      color: #fff;
      padding: 25px 20px;
      position: fixed;
    }
    .sidebar h4 {
      font-weight: 700;
      margin-bottom: 40px;
    }
    .sidebar a {
      display: block;
      text-decoration: none;
      color: #dcdcff;
      padding: 10px 15px;
      border-radius: 6px;
      margin-bottom: 10px;
      transition: 0.2s;
    }
    .sidebar a.active, .sidebar a:hover {
      background-color: #ffffff33;
      color: #fff;
    }
    .main {
      margin-left: 250px;
      padding: 40px;
      width: calc(100% - 250px);
    }
    .btn-create {
      background-color: #5b5de6;
      color: white;
      border: none;
      padding: 10px 18px;
      border-radius: 8px;
      font-weight: 500;
      transition: 0.2s;
    }
    .btn-create:hover {
      background-color: #4748d6;
    }
    .lesson-card {
      background-color: #fff;
      border-radius: 12px;
      padding: 20px 25px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      margin-bottom: 20px;
    }
    .lesson-card h5 {
      margin: 0;
      font-weight: 600;
      color: #333;
    }
    .lesson-card p {
      margin: 4px 0 8px;
      color: #777;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <h4>ClassConnect</h4>
    <a href="dashboard.php">Dashboard</a>
    <a href="mylesson.php" class="active">Lesson</a>
    <a href="assignments.php">Assignment</a>
    <a href="announcements.php">Announcement</a>
    <a href="profile.php">Profile</a>
    <a href="../logout.php">Logout</a>
  </div>

  <!-- Main -->
  <div class="main">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>My Lessons</h2>
      <button class="btn-create" data-bs-toggle="modal" data-bs-target="#addLessonModal">+ Create New Lesson</button>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['added'])): ?>
      <div class="alert alert-success">✅ Lesson added successfully!</div>
    <?php elseif (isset($_GET['updated'])): ?>
      <div class="alert alert-info">✏️ Lesson updated successfully!</div>
    <?php elseif (isset($_GET['deleted'])): ?>
      <div class="alert alert-success">🗑️ Lesson deleted successfully!</div>
    <?php endif; ?>

    <!-- Lessons -->
    <?php if ($lessons->num_rows > 0): ?>
      <?php while ($lesson = $lessons->fetch_assoc()): ?>
        <div class="lesson-card">
          <h5><?php echo htmlspecialchars($lesson['title']); ?></h5>
          <p><?php echo htmlspecialchars(substr($lesson['description'], 0, 120)); ?>...</p>
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted"><?php echo date('M d, Y', strtotime($lesson['created_at'])); ?></span>
            <div>
              <?php if (!empty($lesson['file_path'])): ?>
                <a href="../uploads/<?php echo htmlspecialchars($lesson['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
              <?php endif; ?>
              <a href="edit_lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-outline-success">Edit</a>
              <form action="delete_lesson.php" method="POST" style="display:inline;" onsubmit="return confirmDelete();">
                <input type="hidden" name="id" value="<?php echo $lesson['id']; ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="text-muted mt-3 text-center">No lessons yet. Create your first one!</p>
    <?php endif; ?>
  </div>

  <!-- Add Lesson Modal -->
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

  <script>
  function confirmDelete() {
    return confirm("⚠️ Are you sure you want to delete this lesson?");
  }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>



