<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is a teacher
checkRole('teacher');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: assignments.php');
    exit;
}

$assignment_id = $_GET['id'];
$teacher_id = $_SESSION['user_id'];

// Fetch assignment details
$stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $assignment_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: assignments.php');
    exit;
}

$assignment = $result->fetch_assoc();

// Fetch submissions for this assignment - only the latest from each student
$submissions_query = "SELECT s.*, u.full_name as student_name 
                     FROM submissions s 
                     JOIN users u ON s.student_id = u.id 
                     WHERE s.assignment_id = ? AND s.id IN (
                        SELECT MAX(id) FROM submissions 
                        WHERE assignment_id = ? 
                        GROUP BY student_id
                     )
                     ORDER BY s.submitted_at DESC";
$stmt = $conn->prepare($submissions_query);
$stmt->bind_param("ii", $assignment_id, $assignment_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
                <p>Teacher Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="mylesson.php">My Lessons</a></li>
                <li><a href="assignments.php" class="active">Assignments</a></li>
                <li><a href="announcements_messages.php">Announcements</a></li>
                <!-- profile and logout moved to topbar dropdown -->
            </ul>
        </aside>

        <main class="main-content">
            <?php $page_title = 'Assignment Details'; require_once __DIR__ . '/../includes/topbar.php'; ?>
            <div class="mb-4">
                <a href="assignments.php" class="btn btn-link px-0">
                    <i class="fas fa-arrow-left"></i> Back to Assignments
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Assignment Details</h5>
                </div>
                <div class="card-body">
                    <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                    <div class="text-muted mb-3">
                        Due: <?php echo date('F j, Y g:i A', strtotime($assignment['due_date'])); ?>
                    </div>
                    <div class="mb-4">
                        <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                    </div>
                    <?php if (!empty($assignment['file_path'])): ?>
                        <div class="mt-3">
                            <strong>Attached File:</strong>
                            <a href="../api/download_file.php?type=assignment&id=<?php echo $assignment['id']; ?>" class="btn btn-link">
                                <i class="fas fa-download"></i> Download Assignment File
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Student Submissions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($submissions)): ?>
                        <p class="text-muted text-center py-4">No submissions yet</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Submitted</th>
                                        <th>File</th>
                                        <th>Grade</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $submission): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></td>
                                            <td>
                                                <?php if (!empty($submission['file_path'])): ?>
                                                    <a href="../api/download_file.php?type=submission&id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-link">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">No file</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($submission['grade'])): ?>
                                                    <?php echo $submission['grade']; ?>%
                                                <?php else: ?>
                                                    Not graded
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-primary btn-sm"
                                                        onclick="gradeSubmission(<?php echo $submission['id']; ?>)">
                                                    <i class="fas fa-star"></i> Grade
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Grade Modal -->
    <div class="modal fade" id="gradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Grade Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="gradeForm">
                    <div class="modal-body">
                        <input type="hidden" id="submissionId" name="submission_id">
                        <div class="mb-3">
                            <label for="grade" class="form-label">Grade (0-100)</label>
                            <input type="number" class="form-control" id="grade" name="grade" 
                                   min="0" max="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Feedback</label>
                            <textarea class="form-control" id="feedback" name="feedback" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const gradeModal = new bootstrap.Modal(document.getElementById('gradeModal'));
        
        function gradeSubmission(submissionId) {
            document.getElementById('submissionId').value = submissionId;
            gradeModal.show();
        }

        document.getElementById('gradeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const response = await fetch('../api/grade_submission.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    gradeModal.hide();
                    location.reload();
                } else {
                    alert('Error submitting grade. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error submitting grade. Please try again.');
            }
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>