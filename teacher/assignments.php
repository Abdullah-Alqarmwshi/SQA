<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is a teacher
checkRole('teacher');

// Get teacher's information
$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];

// Build query with filter support
$query = "SELECT a.*, 
         (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count,
         (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.grade IS NOT NULL) as graded_count
         FROM assignments a 
         WHERE a.teacher_id = ?";

// Add filter conditions
if (isset($_GET['filter']) && !empty($_GET['filter'])) {
    $filter = $_GET['filter'];
    if ($filter === 'active') {
        $query .= " AND a.due_date > NOW()";
    } elseif ($filter === 'due-soon') {
        $query .= " AND a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
    } elseif ($filter === 'past') {
        $query .= " AND a.due_date < NOW()";
    }
}

$query .= " ORDER BY a.due_date ASC, a.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$assignments = [];
while($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - ClassConnect</title>
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

        .page-header {
            background: white;
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .controls-bar {
            background: white;
            border-radius: 6px;
            padding: 16px 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 12px;
        }

        .filter-select {
            width: auto;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
        }

        .btn-create {
            background: #1e40af;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-create:hover {
            background: #153e75;
            color: white;
            text-decoration: none;
        }

        .assignments-list {
            display: grid;
            gap: 16px;
        }

        .assignment-card {
            background: white;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            border-top: 3px solid #1e40af;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .assignment-card:hover {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .assignment-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .assignment-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .assignment-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
        }

        .status-active {
            color: #059669;
            background: #d1fae5;
            padding: 4px 8px;
            border-radius: 3px;
        }

        .status-due-soon {
            color: #d97706;
            background: #fef3c7;
            padding: 4px 8px;
            border-radius: 3px;
        }

        .status-past {
            color: #dc2626;
            background: #fee2e2;
            padding: 4px 8px;
            border-radius: 3px;
        }

        .assignment-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f3f4f6;
            margin-bottom: 16px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 10px;
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

        .submission-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 16px;
            padding: 12px 0;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #1e40af;
        }

        .card-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-view {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-view:hover {
            background: #93c5fd;
            color: #1e3a8a;
        }

        .btn-edit {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-edit:hover {
            background: #93c5fd;
            color: #1e3a8a;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fecaca;
            color: #991b1b;
        }

        .empty-state {
            background: white;
            border-radius: 6px;
            padding: 48px 24px;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .empty-state-icon {
            font-size: 40px;
            color: #9ca3af;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .modal-content {
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .modal-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 20px 24px;
        }

        .modal-title {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 13px;
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #1e40af;
            color: white;
        }

        .btn-primary:hover {
            background: #153e75;
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

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #991b1b;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: white;
            border-radius: 6px;
            padding: 16px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-left: 4px solid #1e40af;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 320px;
            animation: slideIn 0.3s ease-out;
        }

        .toast.success {
            border-left-color: #059669;
        }

        .toast.success .toast-icon {
            color: #059669;
        }

        .toast.error {
            border-left-color: #dc2626;
        }

        .toast.error .toast-icon {
            color: #dc2626;
        }

        .toast.warning {
            border-left-color: #d97706;
        }

        .toast.warning .toast-icon {
            color: #d97706;
        }

        .toast-icon {
            font-size: 18px;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .toast-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 13px;
        }

        .toast-message {
            color: #6b7280;
            font-size: 12px;
            margin-top: 2px;
        }

        .toast-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            flex-shrink: 0;
        }

        .toast-close:hover {
            color: #6b7280;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .toast.remove {
            animation: slideOut 0.3s ease-out forwards;
        }
    </style>
</head>
<body>
    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toastContainer"></div>

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
            <div class="topbar" style="background: white; border-radius: 6px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h1 style="font-size: 24px; font-weight: 600; color: #1f2937; margin: 0;">Assignments</h1>
                <div class="user-info" onclick="toggleDropdown()" style="display: flex; align-items: center; gap: 12px; cursor: pointer; position: relative;">
                    <div class="user-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px;">
                        <?php echo strtoupper(substr($teacher_name, 0, 1)); ?>
                    </div>
                    <span style="font-size: 14px; font-weight: 500; color: #1f2937;"><?php echo htmlspecialchars($teacher_name); ?></span>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php">👤 Profile Settings</a>
                        <a href="../logout.php">🚪 Logout</a>
                    </div>
                </div>
            </div>

            <div class="controls-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search" placeholder="Search assignments...">
                </div>
                <select id="filter" class="filter-select">
                    <option value="">All Assignments</option>
                    <option value="active" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="due-soon" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'due-soon') ? 'selected' : ''; ?>>Due Soon</option>
                    <option value="past" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'past') ? 'selected' : ''; ?>>Past Due</option>
                </select>
                <button type="button" class="btn-create" data-bs-toggle="modal" data-bs-target="#modalCreate">
                    <i class="fas fa-plus"></i> Create Assignment
                </button>
            </div>

            <div class="assignments-list" id="assignmentsList">
                <?php if (empty($assignments)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3>No Assignments Yet</h3>
                        <p>Create your first assignment to get started</p>
                        <button type="button" class="btn-create" data-bs-toggle="modal" data-bs-target="#modalCreate">
                            <i class="fas fa-plus"></i> Create Assignment
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): ?>
                        <?php
                        $due_time = strtotime($assignment['due_date']);
                        $now = time();
                        $diff = $due_time - $now;
                        
                        if ($diff < 0):
                            $status = 'past';
                            $status_text = 'PAST DUE';
                            $status_class = 'status-past';
                        elseif ($diff < 7 * 24 * 60 * 60):
                            $status = 'due-soon';
                            $status_text = 'DUE SOON';
                            $status_class = 'status-due-soon';
                        else:
                            $status = 'active';
                            $status_text = 'ACTIVE';
                            $status_class = 'status-active';
                        endif;
                        ?>
                        <div class="assignment-card">
                            <div class="assignment-card-header">
                                <h3 class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                <span class="assignment-status <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>

                            <div class="assignment-meta">
                                <div class="meta-item">
                                    <div class="meta-label">Due Date</div>
                                    <div class="meta-value"><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Created</div>
                                    <div class="meta-value"><?php echo date('M j, Y', strtotime($assignment['created_at'])); ?></div>
                                </div>
                            </div>

                            <div class="submission-stats">
                                <div class="stat-item">
                                    <div class="stat-label">Total Submissions</div>
                                    <div class="stat-value"><?php echo $assignment['submission_count']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Graded</div>
                                    <div class="stat-value" style="color: #059669;"><?php echo $assignment['graded_count']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Pending</div>
                                    <div class="stat-value" style="color: #d97706;"><?php echo ($assignment['submission_count'] - $assignment['graded_count']); ?></div>
                                </div>
                            </div>

                            <div class="card-actions">
                                <button type="button" class="btn-action btn-view" onclick="viewAssignment(<?php echo $assignment['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Submissions
                                </button>
                                <button type="button" class="btn-action btn-edit" onclick="editAssignment(<?php echo $assignment['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn-action btn-delete" onclick="deleteAssignment(<?php echo $assignment['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal fade" id="modalCreate" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCreate" method="post" enctype="multipart/form-data">
                        <input type="hidden" id="assignId" name="assignment_id">

                        <div class="form-group">
                            <label class="form-label">Assignment Title</label>
                            <input type="text" id="inpTitle" name="title" class="form-control" placeholder="Enter assignment title" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea id="inpDesc" name="description" class="form-control" rows="4" placeholder="Enter assignment description and requirements"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Due Date</label>
                            <input type="date" id="inpDue" name="due_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Attachment (Optional)</label>
                            <input type="file" id="inpFile" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.zip">
                            <div style="font-size: 11px; color: #9ca3af; margin-top: 6px;">
                                <i class="fas fa-info-circle"></i> Upload instructions or resources
                            </div>
                        </div>

                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Assignment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="modalConfirmDelete" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle" style="color: #dc2626; margin-right: 8px;"></i>
                        Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteConfirmMessage" style="color: #1f2937; margin-bottom: 12px;">
                        Are you sure you want to delete this assignment? This action cannot be undone.
                    </p>
                    <div id="deleteAssignmentTitle" style="background: #fee2e2; padding: 12px; border-radius: 4px; color: #7f1d1d; font-weight: 500; font-size: 13px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash"></i> Delete Assignment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const teacherId = '<?php echo $teacher_id; ?>';

        // Toast notification system
        function showToast(title, message, type = 'info', duration = 3000) {
            const container = document.getElementById('toastContainer');
            
            let icon = 'fas fa-info-circle';
            if (type === 'success') icon = 'fas fa-check-circle';
            if (type === 'error') icon = 'fas fa-times-circle';
            if (type === 'warning') icon = 'fas fa-exclamation-circle';
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="toast-icon ${icon}"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.closest('.toast').remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.add('remove');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }
        }

        function viewAssignment(id) {
            window.location.href = 'view_assignment.php?id=' + id;
        }

        function editAssignment(id) {
            // Fetch assignment details
            fetch(`../api/assignments.php?id=${id}`)
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data) {
                    const assignment = d.data;
                    document.getElementById('assignId').value = assignment.id;
                    document.getElementById('inpTitle').value = assignment.title;
                    document.getElementById('inpDesc').value = assignment.description;
                    document.getElementById('inpDue').value = assignment.due_date;
                    
                    // Update modal title
                    const modal = new bootstrap.Modal(document.getElementById('modalCreate'));
                    document.querySelector('#modalCreate .modal-title').textContent = 'Edit Assignment';
                    modal.show();
                } else {
                    showToast('Error', 'Failed to load assignment details', 'error');
                }
            })
            .catch(err => showToast('Error', 'An error occurred: ' + err, 'error'));
        }

        function deleteAssignment(id) {
            const card = event.target.closest('.assignment-card');
            const title = card.querySelector('.assignment-title').textContent;
            
            // Set the assignment title in the modal
            document.getElementById('deleteAssignmentTitle').textContent = `"${title}"`;
            
            // Store the ID for later use
            window.assignmentIdToDelete = id;
            
            // Show the confirmation modal
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmDelete'));
            modal.show();
        }

        // Handle the confirm delete button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            const id = window.assignmentIdToDelete;
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirmDelete'));
            modal.hide();
            
            showToast('Deleting...', 'Please wait while we delete the assignment', 'warning', 0);
            
            fetch('../api/assignments.php?id=' + id, {
                method: 'DELETE'
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    // Clear toasts
                    document.getElementById('toastContainer').innerHTML = '';
                    showToast('Success', 'Assignment deleted successfully', 'success', 2000);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast('Error', d.error || 'Failed to delete assignment', 'error');
                }
            })
            .catch(err => showToast('Error', 'An error occurred: ' + err, 'error'));
        })

        document.getElementById('formCreate').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('../api/assignments.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    location.reload();
                } else {
                    alert('Error: ' + d.error);
                }
            })
            .catch(err => alert('Error: ' + err));
        });

        document.getElementById('search').addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            const filter = document.getElementById('filter').value;
            
            fetch(`../api/assignments.php?view=teacher&search=${search}&filter=${filter}`)
            .then(r => r.json())
            .then(d => {
                if (d.data) {
                    location.href = '?search=' + search + '&filter=' + filter;
                }
            });
        });

        document.getElementById('filter').addEventListener('change', function(e) {
            const filter = e.target.value;
            const search = document.getElementById('search').value;
            location.href = '?filter=' + filter + '&search=' + search;
        });

        function editAssignment(id) {
            // Fetch assignment details
            fetch(`../api/assignments.php?id=${id}`)
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data) {
                    const assignment = d.data;
                    document.getElementById('assignId').value = assignment.id;
                    document.getElementById('inpTitle').value = assignment.title;
                    document.getElementById('inpDesc').value = assignment.description;
                    document.getElementById('inpDue').value = assignment.due_date;
                    
                    // Update modal title
                    const modal = new bootstrap.Modal(document.getElementById('modalCreate'));
                    document.querySelector('#modalCreate .modal-title').textContent = 'Edit Assignment';
                    modal.show();
                } else {
                    alert('Error loading assignment');
                }
            })
            .catch(err => alert('Error: ' + err));
        }
    </script>
</body>
</html>