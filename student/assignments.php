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
        :root {
            --primary-color: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --light-bg: #f8fafc;
            --lighter-bg: #f1f5f9;
            --dark-text: #1e293b;
            --light-text: #64748b;
            --border-color: #e2e8f0;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 50%, #e8e8e8 100%);
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 30px;
            animation: slideDown 0.5s ease-out;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
            letter-spacing: 0.2px;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .search-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #d1d5db;
        }

        .search-box input {
            width: 100%;
            padding: 11px 12px 11px 36px;
            border: none;
            font-size: 13px;
            background: white;
            transition: all 0.3s ease;
            color: #1f2937;
        }

        .search-box input::placeholder {
            color: #9ca3af;
        }

        .search-box input:focus {
            outline: none;
            background: #f9fafb;
        }

        .search-box i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 13px;
        }

        .filter-wrapper {
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #d1d5db;
            overflow: hidden;
        }

        .filter-select {
            padding: 11px 12px;
            border: none;
            background: white;
            width: 220px;
            font-weight: 500;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%231f2937' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
            font-size: 13px;
        }

        .filter-select:focus {
            outline: none;
            background-color: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .badge.bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .assignments-list {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            background: white;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--primary-color);
            opacity: 1;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
            border-color: var(--primary-color);
        }

        .card-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 600;
            line-height: 1.4;
            color: #1f2937;
            margin-bottom: 12px;
            transition: color 0.3s ease;
            letter-spacing: 0.3px;
        }

        .card:hover .card-title {
            color: var(--primary-color);
        }

        .card-text {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
            line-height: 1.6;
            flex-grow: 1;
        }

        .assignment-meta {
            font-size: 12px;
            margin-bottom: 16px;
            padding: 12px 0;
            border-top: 1px solid #f3f4f6;
            border-bottom: 1px solid #f3f4f6;
        }

        .assignment-meta div {
            color: #6b7280;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .assignment-meta i {
            width: 16px;
            color: #9ca3af;
            font-weight: 400;
        }

        .grade-feedback {
            background: #f9fafb;
            border-left: 3px solid var(--primary-color);
            padding: 14px 12px;
            border-radius: 6px;
            margin: 16px 0;
            border: 1px solid #e5e7eb;
        }

        .grade-feedback div {
            margin-bottom: 8px;
            font-size: 12px;
            color: #374151;
        }

        .grade-feedback strong {
            color: #1f2937;
            font-weight: 600;
        }

        .grade-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .btn {
            border: none;
            border-radius: 6px;
            padding: 10px 16px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            margin-top: auto;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 6px rgba(30, 64, 175, 0.25);
        }

        .btn-primary:hover {
            background: #153e75;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(30, 64, 175, 0.35);
            color: white;
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .empty-state i {
            font-size: 3.5rem;
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #6b7280;
            font-size: 13px;
        }

        /* Status Indicators */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            font-size: 10px;
            font-weight: 700;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #e5e7eb;
            color: #374151;
        }

        .status-submitted {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-graded {
            background: #d1fae5;
            color: #065f46;
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
                <li><a href="announcements_messages.php">Announcement</a></li>
                <!-- profile and logout moved to topbar dropdown -->
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <?php $page_title = 'Assignments'; require_once __DIR__ . '/../includes/topbar.php'; ?>

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
                        <?php foreach ($assignments as $assignment): 
                            $status_class = 'status-' . $assignment['submission_status'];
                            $is_past_due = strtotime($assignment['due_date']) < time();
                            $is_due_soon = !$is_past_due && (strtotime($assignment['due_date']) <= (time() + 7 * 24 * 60 * 60));
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <div class="card-header" style="background: white; border-bottom: 2px solid var(--primary-color); padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                                        <h5 class="card-title" style="margin: 0; flex: 1;"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($assignment['submission_status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <?php 
                                            $desc = htmlspecialchars($assignment['description']);
                                            echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                            ?>
                                        </p>
                                        
                                        <div class="assignment-meta">
                                            <div>
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($assignment['teacher_name']); ?></span>
                                            </div>
                                            <div>
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></span>
                                            </div>
                                            <?php if ($is_past_due): ?>
                                            <div>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-exclamation-circle" style="margin-right: 4px;"></i>
                                                    Past Due
                                                </span>
                                            </div>
                                            <?php elseif ($is_due_soon): ?>
                                            <div>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock" style="margin-right: 4px;"></i>
                                                    Due Soon
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-eye"></i> View Details
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
    <script src="../assets/js/main.js"></script>
</body>
</html>