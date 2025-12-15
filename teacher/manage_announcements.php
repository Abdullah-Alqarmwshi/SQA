<?php
require_once '../config/session.php';
checkRole('teacher');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Create/Update/Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create' || $action === 'update') {
        $title = $conn->real_escape_string($_POST['title']);
        $content = $conn->real_escape_string($_POST['content']);
        $category = $conn->real_escape_string($_POST['category']);
        $type = $conn->real_escape_string($_POST['type']);
        $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : NULL;
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
        $target_audience = $conn->real_escape_string($_POST['target_audience']);

        if (empty($title) || empty($content)) {
            $error = 'Title and content are required.';
        } else {
            if ($action === 'create') {
                $sql = "INSERT INTO announcements (user_id, title, content, category, type, event_date, expiry_date, target_audience)
                        VALUES ($user_id, '$title', '$content', '$category', '$type', " .
                        ($event_date ? "'$event_date'" : "NULL") . ", " .
                        ($expiry_date ? "'$expiry_date'" : "NULL") . ", '$target_audience')";

                if ($conn->query($sql) === TRUE) {
                    $message = 'Announcement created successfully!';
                } else {
                    $error = 'Error creating announcement: ' . $conn->error;
                }
            } else {
                $announcement_id = intval($_POST['announcement_id']);
                $sql = "UPDATE announcements SET title='$title', content='$content', category='$category',
                        type='$type', event_date=" . ($event_date ? "'$event_date'" : "NULL") . ",
                        expiry_date=" . ($expiry_date ? "'$expiry_date'" : "NULL") . ",
                        target_audience='$target_audience', updated_at=NOW()
                        WHERE id=$announcement_id AND user_id=$user_id";

                if ($conn->query($sql) === TRUE) {
                    $message = 'Announcement updated successfully!';
                } else {
                    $error = 'Error updating announcement: ' . $conn->error;
                }
            }
        }
    } elseif ($action === 'delete') {
        $announcement_id = intval($_POST['announcement_id']);
        $sql = "DELETE FROM announcements WHERE id=$announcement_id AND user_id=$user_id";

        if ($conn->query($sql) === TRUE) {
            $message = 'Announcement deleted successfully!';
        } else {
            $error = 'Error deleting announcement: ' . $conn->error;
        }
    }
}

// Get search and filter parameters for announcements
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';

// Build announcements query with filters
$announcements_sql = "SELECT a.*, u.full_name 
                     FROM announcements a
                     JOIN users u ON a.user_id = u.id
                     WHERE a.user_id = $user_id";

// Add search filter
if (!empty($search_query)) {
    $announcements_sql .= " AND (a.title LIKE '%$search_query%' OR a.content LIKE '%$search_query%')";
}

// Add category filter
if (!empty($category_filter)) {
    $announcements_sql .= " AND a.category = '$category_filter'";
}

// Add sorting
$allowed_sorts = ['created_at', 'event_date', 'title'];
$sort_column = in_array($sort_by, $allowed_sorts) ? $sort_by : 'created_at';
$announcements_sql .= " ORDER BY a.$sort_column DESC";

$announcements = $conn->query($announcements_sql);

// Get total count for stats
$total_announcements = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE user_id=$user_id")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage My Announcements - ClassConnect</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ... [keeping same styles as before] ... */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 2px solid #f0f0f0;
        }

        .btn-create {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            font-size: 14px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        /* Search input styling */
        .search-input-wrapper {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-input-wrapper i.bi-search {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 16px;
            pointer-events: none;
            z-index: 2;
        }

        .search-input {
            width: 100%;
            padding: 12px 40px 12px 40px !important;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-clear {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            text-decoration: none;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s;
        }

        .search-clear:hover {
            color: #dc3545;
        }

        /* Filter section layout */
        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Consistent action buttons */
        .btn, .btn-view, .btn-edit, .btn-delete {
            -webkit-appearance: none;
            appearance: none;
            padding: 10px 14px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-view, .btn-edit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; box-shadow: 0 4px 12px rgba(102,126,234,0.25);} 
        .btn-view:hover, .btn-edit:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(102,126,234,0.28); }
        .btn-delete { background: linear-gradient(135deg, #ff6b6b 0%, #dc3545 100%); color: white; box-shadow: 0 4px 12px rgba(220,53,69,0.2);} 
        .btn-delete:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(220,53,69,0.28);} 

        /* Stronger anchor rule for Manage link while present in other pages */
        a.btn-create.no-icon, a.btn-create.no-icon:link, a.btn-create.no-icon:visited { text-decoration: none !important; color: white !important; display: inline-flex; align-items: center; }


        .announcement-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 5px solid #667eea;
        }

        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(74, 144, 226, 0.08);
            border: 2px solid #ddd;
        }

        .filter-select {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            background: white;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 45px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        /* Announcement modal custom styles */
        .announcement-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            border-bottom: none;
            padding: 18px 24px;
        }
        .announcement-modal-header .modal-title i {
            margin-right: 8px;
            font-size: 1.05rem;
        }
        
        /* Modal form styles */
        #announcementModal .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        #announcementModal .form-control,
        #announcementModal .form-select {
            border-radius: 8px;
            padding: 12px 16px;
            border: 1px solid #e1e5eb;
            transition: all 0.3s ease;
        }
        
        #announcementModal .form-control:focus,
        #announcementModal .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        /* Modal footer buttons */
        .announcement-modal-footer {
            display: flex;
            gap: 12px;
            padding: 16px;
            border-top: 1px solid #e1e5eb;
        }

        .modal-btn-cancel {
            flex: 1;
            background: #6B7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
            min-width: 160px;
        }
        
        .modal-btn-cancel:hover {
            background: #4B5563;
        }

        .modal-btn-create {
            flex: 1;
            background: #6366F1;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
            min-width: 160px;
        }
        
        .modal-btn-create:hover {
            background: #4F46E5;
        }
        
        .announcement-modal-footer i {
            font-size: 16px;
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
                <li><a href="assignments.php">Assignments</a></li>
                <li><a href="announcements_messages.php" class="active">Announcements</a></li>
                <li><a href="profile.php">Profile Settings</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="topbar">
                <h1>Manage Announcements</h1>
                <div class="user-info" onclick="toggleDropdown()">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                    <span><?php echo $_SESSION['full_name']; ?></span>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php">👤 Profile Settings</a>
                        <a href="../logout.php">🚪 Logout</a>
                    </div>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-group">
                    <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">

                        <label><i class="bi bi-funnel"></i> Category:</label>
                        <select name="category" onchange="this.form.submit()" class="filter-select">
                            <option value="">All Categories</option>
                            <option value="Academic" <?php echo $category_filter === 'Academic' ? 'selected' : ''; ?>>📚 Academic</option>
                            <option value="Event" <?php echo $category_filter === 'Event' ? 'selected' : ''; ?>>🎉 Event</option>
                            <option value="General Notice" <?php echo $category_filter === 'General Notice' ? 'selected' : ''; ?>>📢 General Notice</option>
                            <option value="Administrative" <?php echo $category_filter === 'Administrative' ? 'selected' : ''; ?>>📋 Administrative</option>
                            <option value="Reminder" <?php echo $category_filter === 'Reminder' ? 'selected' : ''; ?>>⏰ Reminder</option>
                        </select>

                        <label><i class="bi bi-sort-down"></i> Sort by:</label>
                        <select name="sort" onchange="this.form.submit()" class="filter-select">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Posted Date</option>
                            <option value="event_date" <?php echo $sort_by === 'event_date' ? 'selected' : ''; ?>>Event Date</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title</option>
                        </select>
                    </form>
                </div>
                <!-- Search Bar -->
                <form method="GET" class="search-form" id="searchForm">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <div class="search-input-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text" 
                               name="search" 
                               placeholder="Search announcements by..." 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               class="search-input">
                        <?php if ($search_query): ?>
                            <a href="?" class="search-clear" title="Clear search">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="main-tab <?php echo $current_tab === 'announcements' ? 'active' : ''; ?>" onclick="switchMainTab('announcements', event)">
                        <i class="bi bi-megaphone-fill"></i> My Announcements
                    </div>
                    <div class="announcement-stats">
                        Showing <?php echo $announcements->num_rows; ?> of <?php echo $total_announcements; ?> announcements
                        <?php if ($search_query): ?>
                            <span class="search-indicator">
                                <i class="bi bi-search"></i> Search: "<?php echo htmlspecialchars($search_query); ?>"
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <a href="announcements_messages.php" class="btn btn-outline-secondary" title="Back to Announcements">
                            <i class="bi bi-arrow-left"></i> Back to Announcements
                        </a>
                        <button class="btn-create" onclick="openAnnouncementModal()">
                            <i class="bi bi-plus-circle"></i> Create New Announcement
                        </button>
                    </div>
                </div>

                <?php if ($announcements->num_rows > 0): ?>
                    <?php while ($ann = $announcements->fetch_assoc()): ?>
                        <div class="announcement-card <?php echo $ann['type']; ?>">
                            <div class="announcement-badges">
                                <span class="badge-custom badge-<?php echo strtolower(str_replace(' ', '', $ann['category'])); ?>">
                                    <?php echo strtoupper($ann['category']); ?>
                                </span>
                                <?php if ($ann['type'] === 'urgent'): ?>
                                    <span class="badge-custom badge-urgent">
                                        <i class="bi bi-exclamation-triangle"></i> URGENT
                                    </span>
                                <?php endif; ?>
                                <span class="badge-custom" style="background: #e3f2fd; color: #1976d2;">
                                    <?php echo date('d/m/Y', strtotime($ann['created_at'])); ?>
                                </span>
                            </div>

                            <h3 class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></h3>
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                            </div>

                            <?php if ($ann['event_date']): ?>
                                <div class="announcement-meta">
                                    <div class="meta-item">
                                        <i class="bi bi-calendar-event"></i>
                                        <strong>Event Date:</strong> <?php echo date('M d, Y, h:i A', strtotime($ann['event_date'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="announcement-footer">
                                <div class="author-info">
                                    <div class="author-avatar">
                                        <?php echo strtoupper(substr($ann['full_name'], 0, 1)); ?>
                                    </div>
                                    <div class="author-details">
                                        <div class="author-name">Target: <?php echo htmlspecialchars($ann['target_audience']); ?></div>
                                        <div class="author-role">Created: <?php echo date('M d, Y H:i', strtotime($ann['created_at'])); ?></div>
                                    </div>
                                </div>

                                <div class="action-buttons">
                                    <button class="btn btn-edit" onclick='openEditModal(<?php echo json_encode($ann); ?>)'>
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                        <button type="submit" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this announcement?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 40px; color: #999;">
                        📢 No announcements yet. Click "Create New Announcement" to get started!
                    </p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Announcement Modal -->
        <!-- Announcement Modal (Bootstrap) -->
        <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header announcement-modal-header">
                        <h5 class="modal-title" id="announcementModalLabel"><i class="bi bi-megaphone-fill"></i> <span id="modalTitleText">Create New Announcement</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="announcementForm">
                        <div class="modal-body">
                            <input type="hidden" name="action" id="formAction" value="create">
                            <input type="hidden" name="announcement_id" id="announcementId">

                            <div class="mb-3">
                                <label class="form-label">Title <span class="required">*</span></label>
                                <input type="text" name="title" id="title" required class="form-control" placeholder="Enter announcement title">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Content <span class="required">*</span></label>
                                <textarea name="content" id="content" required class="form-control" rows="5" placeholder="Enter announcement content"></textarea>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select name="category" id="category" class="form-select">
                                        <option value="Academic">📚 Academic</option>
                                        <option value="Event">🎉 Event</option>
                                        <option value="General Notice" selected>📢 General Notice</option>
                                        <option value="Administrative">📋 Administrative</option>
                                        <option value="Reminder">⏰ Reminder</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Type</label>
                                    <select name="type" id="type" class="form-select">
                                        <option value="general" selected>General</option>
                                        <option value="urgent">🚨 Urgent</option>
                                        <option value="event">🎊 Event</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Event Date (Optional)</label>
                                    <input type="datetime-local" name="event_date" id="event_date" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expiry Date (Optional)</label>
                                    <input type="datetime-local" name="expiry_date" id="expiry_date" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Target Audience</label>
                                    <select name="target_audience" id="target_audience" class="form-select">
                                        <option value="All Students" selected>👨‍🎓 All Students</option>
                                        <option value="All Teachers">👨‍🏫 All Teachers</option>
                                        <option value="Specific">🎯 Specific</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer announcement-modal-footer">
                            <button type="button" class="modal-btn-cancel" data-bs-dismiss="modal">
                                <i class="bi bi-x"></i> Cancel
                            </button>
                            <button type="submit" class="modal-btn-create" id="submitBtn">
                                <i class="bi bi-megaphone"></i> <span id="submitBtnText">Create Announcement</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Search form handling
        document.getElementById('searchForm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.submit();
            }
        });

        // Modal Functions (Bootstrap)
        function openAnnouncementModal() {
            // prepare form for create
            document.getElementById('announcementForm').reset();
            document.getElementById('formAction').value = 'create';
            document.getElementById('modalTitleText').textContent = 'Create New Announcement';
            document.getElementById('submitBtnText').textContent = 'Create Announcement';
            const modal = new bootstrap.Modal(document.getElementById('announcementModal'));
            modal.show();
        }

        function openEditModal(announcement) {
            // prepare form for update and populate fields
            document.getElementById('announcementForm').reset();
            document.getElementById('formAction').value = 'update';
            document.getElementById('modalTitleText').textContent = 'Edit Announcement';
            document.getElementById('submitBtnText').textContent = 'Update Announcement';

            document.getElementById('announcementId').value = announcement.id || '';
            document.getElementById('title').value = announcement.title || '';
            document.getElementById('content').value = announcement.content || '';
            document.getElementById('category').value = announcement.category || 'General Notice';
            document.getElementById('type').value = announcement.type || 'general';
            document.getElementById('target_audience').value = announcement.target_audience || 'All Students';

            // Handle dates (convert to input-friendly format)
            if (announcement.event_date) {
                try {
                    const eventDate = new Date(announcement.event_date);
                    if (!isNaN(eventDate)) document.getElementById('event_date').value = eventDate.toISOString().slice(0,16);
                } catch(e){}
            }
            if (announcement.expiry_date) {
                try {
                    const expiryDate = new Date(announcement.expiry_date);
                    if (!isNaN(expiryDate)) document.getElementById('expiry_date').value = expiryDate.toISOString().slice(0,16);
                } catch(e){}
            }

            const modal = new bootstrap.Modal(document.getElementById('announcementModal'));
            modal.show();
        }
    </script>
</body>
</html>