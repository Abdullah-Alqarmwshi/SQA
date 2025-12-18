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

// Check if assignment is overdue
$is_overdue = strtotime($assignment['due_date']) < time();

// Check if student has already submitted and get submission details including grade and feedback
$stmt = $conn->prepare("SELECT s.*, DATE_FORMAT(s.submitted_at, '%M %d, %Y %h:%i %p') as formatted_date 
                       FROM submissions s 
                       WHERE s.assignment_id = ? AND s.student_id = ?
                       ORDER BY s.submitted_at DESC
                       LIMIT 1");
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
    <style>
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 50%, #e8e8e8 100%);
            min-height: 100vh;
        }

        .breadcrumb-nav {
            margin-bottom: 24px;
        }

        .breadcrumb-nav a {
            color: #1e40af;
            text-decoration: none;
            font-weight: 500;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .breadcrumb-nav a:hover {
            color: #153e75;
        }

        .card-white {
            background: white;
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .card-white h2 {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
        }

        .card-white h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .meta-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }

        .meta-field {
            padding: 8px 0;
        }

        .meta-label {
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 13px;
            color: #1f2937;
            font-weight: 500;
        }

        .content-text {
            font-size: 13px;
            color: #374151;
            line-height: 1.6;
            margin: 16px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-graded {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status-submitted {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 12px;
            line-height: 1.5;
            margin: 12px 0;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .alert-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }

        .grade-display {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin: 12px 0;
        }

        .grade-number {
            font-size: 32px;
            font-weight: 700;
            color: #10b981;
        }

        .grade-label {
            font-size: 12px;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
        }

        .feedback-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            border-radius: 4px;
            margin: 12px 0;
            font-size: 12px;
            color: #166534;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
            display: block;
        }

        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 13px;
            width: 100%;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1);
        }

        .form-hint {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #1e40af;
            color: white;
        }

        .btn-primary:hover {
            background: #153e75;
        }

        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: white;
            color: #1f2937;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #1e40af;
            color: #1e40af;
        }

        .download-link {
            color: #1e40af;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .download-link:hover {
            color: #153e75;
        }

        .modal-content {
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .modal-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 24px;
        }

        .modal-title {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 12px 16px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                <li><a href="lesson.php">Lesson</a></li>
                <li><a href="assignments.php">Assignment</a></li>
                <li><a href="announcements_messages.php">Announcements</a></li>
                <!-- profile and logout moved to topbar dropdown -->
            </ul>
        </aside>

        <main class="main-content">
            <?php $page_title = 'Assignment Details'; require_once __DIR__ . '/../includes/topbar.php'; ?>
            <div class="breadcrumb-nav">
                <a href="assignments.php">
                    <i class="fas fa-arrow-left"></i> Back to Assignments
                </a>
            </div>

            <div class="card-white" style="border-top: 3px solid #1e40af;">
                <h2><?php echo htmlspecialchars($assignment['title']); ?></h2>
                
                <div class="meta-row">
                    <div class="meta-field">
                        <div class="meta-label">Instructor</div>
                        <div class="meta-value"><?php echo htmlspecialchars($assignment['teacher_name']); ?></div>
                    </div>
                    <div class="meta-field">
                        <div class="meta-label">Due Date</div>
                        <div class="meta-value"><?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></div>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div>
                    <div class="card-white">
                        <h3>Assignment Description</h3>
                        <div class="content-text">
                            <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                        </div>
                        
                        <?php if (!empty($assignment['file_path'])): ?>
                            <?php
                            $file_name = basename($assignment['file_path']);
                            $download_path = '../' . $assignment['file_path'];

                    if (file_exists(__DIR__ . '/../' . $assignment['file_path'])):
                    ?>
                        <div style="margin-top: 16px;">
                            <div class="meta-label">Attached File</div>
                            <a href="<?php echo htmlspecialchars($download_path); ?>" 
                               class="download-link"
                               download="<?php echo htmlspecialchars($file_name); ?>">
                                <i class="fas fa-download"></i>
                                <?php echo htmlspecialchars($file_name); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert-box alert-warning" style="margin-top: 16px;">
                            <i class="fas fa-exclamation-circle"></i> File not found
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                    </div>
                </div>

                <aside>
                    <div class="card-white">
                        <h3>Submission Status</h3>
                        
                        <?php if ($submission): ?>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            
                            if ($submission['grade'] !== null):
                                $status_class = 'status-graded';
                                $status_text = 'GRADED';
                            else:
                                $status_class = 'status-submitted';
                                $status_text = 'SUBMITTED';
                            endif;
                            ?>
                            <div style="margin-bottom: 16px;">
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                            
                            <div class="meta-field">
                                <div class="meta-label">Submitted On</div>
                                <div class="meta-value" style="font-size: 12px;"><?php echo date('M j, Y', strtotime($submission['submitted_at'])); ?></div>
                            </div>
                            
                            <?php if (!empty($submission['file_path'])): ?>
                                <?php
                                $submission_file = basename($submission['file_path']);
                                $download_url = '../api/download_file.php?type=submission&id=' . $submission['id'];
                                ?>
                                <div style="margin-top: 12px;">
                                    <div class="meta-label">Submitted File</div>
                                    <a href="<?php echo htmlspecialchars($download_url); ?>" 
                                       class="download-link"
                                       download="<?php echo htmlspecialchars($submission_file); ?>">
                                        <i class="fas fa-download"></i> <?php echo htmlspecialchars($submission_file); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($submission['grade'] !== null): ?>
                                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                                    <div class="meta-label">Your Grade</div>
                                    <div class="grade-display" style="margin: 8px 0;">
                                        <div class="grade-number" style="font-size: 28px;"><?php echo $submission['grade']; ?></div>
                                        <div class="grade-label" style="font-size: 11px;">/ 100</div>
                                    </div>
                                    
                                    <?php if (!empty($submission['feedback'])): ?>
                                        <div style="margin-top: 12px;">
                                            <div class="meta-label">Feedback</div>
                                            <div class="feedback-box" style="font-size: 11px; padding: 8px 12px; margin: 8px 0;">
                                                <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert-box alert-info" style="margin-top: 12px; font-size: 11px;">
                                    <i class="fas fa-hourglass-half"></i> Waiting for grade
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <span class="status-badge status-pending">NOT SUBMITTED</span>
                            <div class="alert-box alert-warning" style="margin-top: 12px; font-size: 11px;">
                                <i class="fas fa-info-circle"></i> Not submitted yet
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>

            <?php
            // Determine if submission is allowed
            $can_submit = true;
            
            if ($is_overdue):
                $can_submit = false;
            elseif ($submission && $submission['grade'] !== null):
                $can_submit = false;
            endif;
            ?>
            
            <?php if ($can_submit): ?>
                <div class="card-white">
                    <h3><?php echo $submission ? 'Resubmit Assignment' : 'Submit Assignment'; ?></h3>
                    <form id="submitForm" action="../api/submit_assignment.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                        
                        <div class="form-group">
                            <label for="submission_file" class="form-label">Select File</label>
                            <input type="file" class="form-control" id="submission_file" name="submission_file" required>
                            <div class="form-hint">PDF, DOC, DOCX, or ZIP (Max 50MB)</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> 
                            <?php echo $submission ? 'Submit New Version' : 'Submit Assignment'; ?>
                        </button>
                    </form>
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
    <script src="../assets/js/main.js"></script>
</body>
</html>