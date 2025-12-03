<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is a student
checkRole('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Prepare base query with submission status
$query = "SELECT a.*, u.full_name as teacher_name,
         CASE 
            WHEN (SELECT grade FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) IS NOT NULL THEN 'graded'
            WHEN (SELECT id FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) IS NOT NULL THEN 'submitted'
            ELSE 'pending'
         END as submission_status,
         (SELECT grade FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) as grade,
         (SELECT feedback FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) as feedback
         FROM assignments a
         JOIN users u ON a.teacher_id = u.id
         WHERE 1=1";

// Add filter conditions
if (isset($_GET['filter'])) {
    if ($_GET['filter'] === 'due-soon') {
        $query .= " AND a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
    } elseif ($_GET['filter'] === 'past') {
        $query .= " AND a.due_date < NOW()";
    }
}

// Add search condition if provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
}

$query .= " ORDER BY a.due_date ASC, a.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);

// Bind parameters
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $stmt->bind_param("iiiiss", $student_id, $student_id, $student_id, $student_id, $search, $search);
} else {
    $stmt->bind_param("iiii", $student_id, $student_id, $student_id, $student_id);
}

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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assignments - ClassConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #111827;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }

        .search-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: white;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .filter-select {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: white;
            width: 160px;
            flex-shrink: 0;
        }

        .badge.due-soon {
            background-color: #ff9800;
        }

        .badge.past-due {
            background-color: #dc3545;
        }

        .assignments-list {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 500;
        }

        .assignment-meta {
            font-size: 0.875rem;
        }

        .assignment-meta i {
            width: 16px;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
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
                <p>Student Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="lesson.php">Lesson</a></li>
                <li><a href="assignments.php" class="active">Assignment</a></li>
                <li><a href="submissions.php">Submissions</a></li>
                <li><a href="announcements_messages.php">Announcement</a></li>
                <li><a href="profile.php">Profile Settings</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Assignments</h1>
                <p class="page-subtitle">View and submit your assignments</p>
            </div>

            <div class="search-section">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search" class="form-control search-input" placeholder="Search assignments...">
                </div>
                <div class="filter-wrapper">
                    <select id="filter" class="form-select filter-select">
                        <option value="all" <?php echo (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'selected' : ''; ?>>All Assignments</option>
                        <option value="due-soon" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'due-soon') ? 'selected' : ''; ?>>Due Soon (7 Days)</option>
                        <option value="past" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'past') ? 'selected' : ''; ?>>Past Due</option>
                    </select>
                </div>
            </div>

            <div class="assignments-list">
                <?php if (empty($assignments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard"></i>
                        <h3>No assignments found</h3>
                        <p>You don't have any assignments yet</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($assignments as $assignment): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title mb-2"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                        <p class="card-text text-muted small mb-3">
                                            <?php echo htmlspecialchars($assignment['description']); ?>
                                        </p>
                                        
                                        <div class="assignment-meta small text-muted mb-3">
                                            <div class="mb-1">
                                                <i class="fas fa-user me-1"></i> 
                                                <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                                            </div>
                                            <div>
                                                <i class="fas fa-calendar me-1"></i>
                                                Due: <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
                                            </div>
                                            <?php if ($assignment['grade'] !== null): ?>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <div class="mb-1">
                                                    <i class="fas fa-star me-1 text-warning"></i>
                                                    <strong>Grade: </strong><?php echo htmlspecialchars($assignment['grade']); ?>
                                                </div>
                                                <?php if (!empty($assignment['feedback'])): ?>
                                                <div>
                                                    <i class="fas fa-comment me-1 text-info"></i>
                                                    <strong>Feedback: </strong><?php echo htmlspecialchars($assignment['feedback']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" 
                                           class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-eye me-1"></i> View Assignment
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add event listeners for search and filter
        document.getElementById('search').addEventListener('input', function() {
            updateURL();
        });
        
        document.getElementById('filter').addEventListener('change', function() {
            updateURL();
        });

        // Function to update URL with search and filter parameters
        function updateURL() {
            const search = document.getElementById('search').value;
            const filter = document.getElementById('filter').value;
            
            let url = window.location.pathname;
            let params = [];
            
            // Always include search if it exists
            if (search) params.push('search=' + encodeURIComponent(search));
            
            // For filter, include it even if it's 'all' to reset the view
            params.push('filter=' + encodeURIComponent(filter));
            
            // Add the parameters to the URL
            url += '?' + params.join('&');
            
            window.location.href = url;
        }

        // Add due date badges
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                const dueDateElement = card.querySelector('.assignment-meta div:last-child');
                if (dueDateElement) {
                    const dueText = dueDateElement.textContent;
                    const dueDate = new Date(dueText.replace('Due: ', ''));
                    const now = new Date();
                    const sevenDaysFromNow = new Date();
                    sevenDaysFromNow.setDate(sevenDaysFromNow.getDate() + 7);

                    const metaDiv = card.querySelector('.assignment-meta');
                    const statusDiv = document.createElement('div');
                    statusDiv.className = 'mt-2';

                    if (dueDate < now) {
                        statusDiv.innerHTML = '<span class="badge bg-danger">Past Due</span>';
                    } else if (dueDate <= sevenDaysFromNow) {
                        statusDiv.innerHTML = '<span class="badge bg-warning">Due Soon</span>';
                    }

                    if (statusDiv.innerHTML) {
                        metaDiv.appendChild(statusDiv);
                    }
                }
            });
        });
    </script>
</body>
</html>