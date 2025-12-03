<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is a teacher
checkRole('teacher');

// Get teacher's information
$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assignments - ClassConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>        
        .main {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
            background: var(--light-bg);
            position: relative;
            width: calc(100% - 260px);
        }

        .main {
            width: calc(100% - 280px);
            margin-left: 280px;
            min-height: 100vh;
            padding: 2rem;
            background: #f9fafb;
            position: relative;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: white;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-link i {
            width: 1.5rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
        }

        .search-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .search-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-grow: 1;
            max-width: 800px;
        }

        .search-box {
            position: relative;
            flex-grow: 1;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .btn-create {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            text-decoration: none;
        }

        .btn-create:hover {
            background: var(--primary-dark);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: #fff;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
        }

        .empty-state i {
            font-size: 3rem;
            color: #9ca3af;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
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
                <li><a href="profile.php">Profile Settings</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>

    <!-- Main Content -->
    <main class="main">
        <header class="header">
            <div>
                <h1 class="title mb-2">Assignments</h1>
                <p class="subtitle">Create and manage your class assignments</p>
            </div>
        </header>

        <div class="controls-wrapper bg-white p-4 rounded-lg shadow-sm mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex gap-3 flex-grow-1">
                    <div class="search-container flex-grow-1">
                        <i class="fas fa-search"></i>
                        <input type="text" id="search" class="form-control search-input" placeholder="Search assignments...">
                    </div>
                    <select id="filter" class="form-select" style="width: auto;">
                        <option value="">All Assignments</option>
                        <option value="active">Active</option>
                        <option value="due-soon">Due Soon</option>
                        <option value="past">Past Due</option>
                    </select>
                </div>
                <button id="btnCreate" class="btn btn-primary btn-icon">
                    <i class="fa-solid fa-plus"></i>
                    <span>Create Assignment</span>
                </button>
            </div>
        </div>

        <div class="assignments-grid" id="assignmentsList">
            <!-- Assignments will be loaded here -->
        </div>
    </main>

    <!-- Create/Edit Modal -->
    <div class="modal fade" id="modalCreate" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 id="modalCreateTitle" class="modal-title fw-bold">Create Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCreate" method="post" enctype="multipart/form-data">
                        <input type="hidden" id="assignId" name="assignment_id">
                        
                        <div class="mb-4">
                            <label class="form-label fw-medium">Assignment Title</label>
                            <input type="text" id="inpTitle" name="title" 
                                   class="form-control form-control-lg" 
                                   placeholder="Enter assignment title"
                                   required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-medium">Description</label>
                            <textarea id="inpDesc" name="description" 
                                      class="form-control" rows="4"
                                      placeholder="Enter assignment description, requirements, and instructions"></textarea>
                        </div>
                        
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Due Date</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="far fa-calendar-alt"></i>
                                    </span>
                                    <input type="date" id="inpDue" name="due_date" 
                                           class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Attachment</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="far fa-file-alt"></i>
                                    </span>
                                    <input type="file" id="inpFile" name="file" 
                                           class="form-control" 
                                           accept=".pdf,.doc,.docx,.ppt,.pptx,.txt">
                                </div>
                                <small class="text-muted">Optional: Upload instructions or resources</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i> Save Assignment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="modalView" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="viewTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="assignment-details">
                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted mb-2">Description</h6>
                            <p id="viewDesc" class="mb-0"></p>
                        </div>
                        
                        <div class="assignment-meta mb-4">
                            <h6 class="text-uppercase text-muted mb-3">Details</h6>
                            <div class="d-flex align-items-center mb-2">
                                <i class="far fa-calendar-alt text-primary me-2"></i>
                                <div>
                                    <small class="text-muted">Due Date</small>
                                    <div id="viewDue" class="fw-medium"></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="far fa-file-alt text-primary me-2"></i>
                                <div>
                                    <small class="text-muted">Attached File</small>
                                    <div id="viewFile" class="fw-medium">—</div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="viewSubmissionStats" class="submission-stats bg-light rounded p-3">
                            <h6 class="text-uppercase text-muted mb-3">Submission Statistics</h6>
                            <div class="d-flex gap-4">
                                <div>
                                    <small class="text-muted">Total Submissions</small>
                                    <div class="h4 mb-0" id="statsTotal">0</div>
                                </div>
                                <div>
                                    <small class="text-muted">Graded</small>
                                    <div class="h4 mb-0" id="statsGraded">0</div>
                                </div>
                                <div>
                                    <small class="text-muted">Pending</small>
                                    <div class="h4 mb-0" id="statsPending">0</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="viewSubmissions(currentAssignmentId)">
                        <i class="fas fa-list me-2"></i> View Submissions
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Submissions Modal -->
    <div class="modal fade" id="modalSubmissions" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light border-bottom">
                    <div>
                        <h5 class="modal-title fw-bold mb-1">Student Submissions</h5>
                        <div class="text-muted small" id="submissionTitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="submission-filters p-3 bg-light border-bottom">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="search-container">
                                    <i class="fas fa-search"></i>
                                    <input type="text" class="form-control search-input" 
                                           placeholder="Search students..." id="searchSubmissions">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="filterSubmissions">
                                    <option value="">All Submissions</option>
                                    <option value="graded">Graded</option>
                                    <option value="pending">Pending Grade</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-3">
                        <div id="submissionsList" class="submission-cards">
                            <!-- Submissions will be loaded here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const teacherId = '<?php echo $teacher_id; ?>';
        const teacherName = '<?php echo $teacher_name; ?>';
    </script>
    <script src="../assets/js/assignments.js"></script>
</body>
</html>