<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: assignments.php");
    exit;
}

$assignment_id = intval($_GET['id']);
$teacher_id = $_SESSION['user_id'];

// Fetch assignment details
$stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $assignment_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: assignments.php");
    exit;
}

$assignment = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = trim($_POST['due_date']);
    $file_path = $assignment['file_path'];

    if (!empty($_FILES['assignment_file']['name'])) {
        $file = $_FILES['assignment_file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = array('pdf', 'doc', 'docx', 'zip', 'txt', 'ppt', 'pptx');

        if (!in_array($file_ext, $allowed_ext)) {
            die("Invalid file type. Allowed: PDF, DOC, DOCX, ZIP, TXT, PPT, PPTX");
        }

        $upload_dir = '../uploads/assignments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . sanitize_filename(pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $file_ext;
        $file_path_new = $upload_dir . $file_name;

        if (!move_uploaded_file($file['tmp_name'], $file_path_new)) {
            die("File upload failed");
        }

        // Delete old file if it exists
        if (!empty($assignment['file_path']) && file_exists($assignment['file_path'])) {
            unlink($assignment['file_path']);
        }

        $file_path = 'uploads/assignments/' . $file_name;
    }

    // Update assignment
    $stmt = $conn->prepare("UPDATE assignments SET title = ?, description = ?, due_date = ?, file_path = ? WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ssssii", $title, $description, $due_date, $file_path, $assignment_id, $teacher_id);

    if ($stmt->execute()) {
        header("Location: view_assignment.php?id=" . $assignment_id . "&success=updated");
        exit;
    } else {
        die("Error updating assignment: " . $stmt->error);
    }
}

// Function to sanitize filename
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $filename = preg_replace('/\.+/', '.', $filename);
    $filename = trim($filename, '.');
    return $filename ?: 'assignment';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment - ClassConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .edit-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 30px auto;
            max-width: 700px;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .form-control, .form-control:focus {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            padding: 10px 12px;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-primary {
            background: #1e40af;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: #1e3a8a;
        }

        .btn-secondary {
            background: #6b7280;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
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
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="mylesson.php">My Lessons</a></li>
                <li><a href="assignments.php" class="active">Assignments</a></li>
                <li><a href="announcements_messages.php">Announcements</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <?php $page_title = 'Edit Assignment'; require_once __DIR__ . '/../includes/topbar.php'; ?>

            <div class="edit-container">
                <div class="mb-4">
                    <a href="view_assignment.php?id=<?php echo $assignment_id; ?>" class="btn btn-link px-0">
                        <i class="fas fa-arrow-left"></i> Back to Assignment
                    </a>
                </div>

                <h2 class="mb-4">Edit Assignment</h2>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="title" class="form-label">Assignment Title</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($assignment['title']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($assignment['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="due_date" class="form-label">Due Date & Time</label>
                        <input type="datetime-local" class="form-control" id="due_date" name="due_date" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="assignment_file" class="form-label">Assignment File (Optional)</label>
                        <input type="file" class="form-control" id="assignment_file" name="assignment_file">
                        <small class="text-muted">Accepted formats: PDF, DOC, DOCX, ZIP, TXT, PPT, PPTX</small>
                        <?php if (!empty($assignment['file_path'])): ?>
                            <div class="mt-2">
                                <span class="badge bg-info">Current file: <?php echo basename($assignment['file_path']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="view_assignment.php?id=<?php echo $assignment_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
