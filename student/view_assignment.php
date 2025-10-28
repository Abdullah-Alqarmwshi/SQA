<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is a student
checkRole('student');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: assignments.php');
    exit;
}

$assignment_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

// Fetch assignment details with teacher information
$query = "SELECT a.*, u.full_name as teacher_name 
          FROM assignments a 
          JOIN users u ON a.teacher_id = u.id 
          WHERE a.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: assignments.php');
    exit;
}

$assignment = $result->fetch_assoc();

// Check if student has already submitted and get submission details including grade and feedback
$stmt = $conn->prepare("SELECT s.*, DATE_FORMAT(s.submitted_at, '%M %d, %Y %h:%i %p') as formatted_date 
                       FROM submissions s 
                       WHERE s.assignment_id = ? AND s.student_id = ?");
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
$submission_result = $stmt->get_result();
$has_submitted = $submission_result->num_rows > 0;
$submission = $has_submitted ? $submission_result->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assignment - ClassConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="lessons.php">My Lessons</a></li>
                <li><a href="assignments.php" class="active">Assignments</a></li>
                <li><a href="announcements.php">Announcements</a></li>
                <li><a href="profile.php">Profile Settings</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="mb-4">
                <a href="assignments.php" class="btn btn-link px-0">
                    <i class="fas fa-arrow-left"></i> Back to Assignments
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                    


                    <div class="text-muted mb-4">
                        <div class="mb-2">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                        </div>
                        <div>
                            <i class="fas fa-calendar me-2"></i>
                            Due: <?php echo date('F j, Y', strtotime($assignment['due_date'])); ?>
                        </div>
                    </div>
                    <div class="mb-4">
                        <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                    </div>
                    <?php if (!empty($assignment['file_path'])): ?>
                        <?php
                        $file_name = basename($assignment['file_path']);
                        $download_path = '../' . $assignment['file_path'];
                        // Verify file exists
                        if (file_exists(__DIR__ . '/../' . $assignment['file_path'])):
                        ?>
                            <div class="mt-3">
                                <a href="<?php echo htmlspecialchars($download_path); ?>" 
                                   class="btn btn-outline-primary" 
                                   download="<?php echo htmlspecialchars($file_name); ?>">
                                    <i class="fas fa-download me-2"></i>
                                    Download <?php echo htmlspecialchars($file_name); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="mt-3 text-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                File not found. Please contact your teacher.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($has_submitted): ?>
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Your Submission</h5>
                        <?php if (!isset($submission['grade'])): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="showSubmitForm()">
                                <i class="fas fa-upload me-2"></i>
                                Resubmit
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="text-muted mb-2">
                                    Submitted: <?php echo date('F j, Y g:i A', strtotime($submission['submitted_at'])); ?>
                                </div>
                                <a href="<?php echo $submission['file_path']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="fas fa-download me-2"></i>
                                    Download Your Submission
                                </a>
                            </div>
                            <?php if (isset($submission['grade'])): ?>
                                <div class="text-end">
                                    <div class="h4 mb-0"><?php echo $submission['grade']; ?>%</div>
                                    <div class="small text-muted">Grade</div>
                                    <?php if (!empty($submission['feedback'])): ?>
                                        <div class="mt-3 p-3 border rounded bg-light">
                                            <div class="fw-bold mb-1">Teacher's Feedback:</div>
                                            <div><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Submit Assignment</h5>
                    </div>
                    <div class="card-body">
                        <form id="submitForm" action="../api/submit_assignment.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                            <div class="mb-3">
                                <label for="submission_file" class="form-label">Upload your work</label>
                                <input type="file" class="form-control" id="submission_file" name="submission_file" required>
                                <div class="form-text">Accepted formats: PDF, DOC, DOCX, ZIP</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>
                                Submit Assignment
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Resubmit Modal -->
    <div class="modal fade" id="resubmitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resubmit Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../api/submit_assignment.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                        <input type="hidden" name="resubmit" value="1">
                        <div class="mb-3">
                            <label for="resubmission_file" class="form-label">Upload new file</label>
                            <input type="file" class="form-control" id="resubmission_file" name="submission_file" required>
                            <div class="form-text">Accepted formats: PDF, DOC, DOCX, ZIP</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit New Version</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const resubmitModal = new bootstrap.Modal(document.getElementById('resubmitModal'));
        
        function showSubmitForm() {
            resubmitModal.show();
        }

        document.getElementById('submitForm')?.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('submission_file');
            if (fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select a file to submit.');
            }
        });
    </script>
</body>
</html>