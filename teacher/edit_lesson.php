<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: mylesson.php");
    exit;
}
$id = intval($_GET['id']);
$lesson = $conn->query("SELECT * FROM lessons WHERE id=$id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Lesson</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h2>Edit Lesson</h2>
    <form action="update_lesson.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?php echo $lesson['id']; ?>">
      <div class="mb-3">
        <label>Title</label>
        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
      </div>
      <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($lesson['description']); ?></textarea>
      </div>
      <div class="mb-3">
        <label>Replace File (optional)</label>
        <input type="file" name="file" class="form-control">
        <?php if ($lesson['file_path']): ?>
          <p>Current file: <a href="../uploads/<?php echo $lesson['file_path']; ?>" target="_blank"><?php echo $lesson['file_path']; ?></a></p>
        <?php endif; ?>
      </div>
      <button type="submit" class="btn btn-success">Update Lesson</button>
      <a href="mylesson.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</body>
</html>
