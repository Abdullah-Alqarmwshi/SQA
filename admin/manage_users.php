<?php
require_once '../config/session.php';
checkRole('admin');
require_once '../config/database.php';

$message = '';
$error = '';

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $message = 'User deleted successfully!';
    } else {
        $error = 'Failed to delete user';
    }
    $stmt->close();
}

// Handle user role update
if (isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("si", $new_role, $user_id);
    if ($stmt->execute()) {
        $message = 'User role updated successfully!';
    } else {
        $error = 'Failed to update user role';
    }
    $stmt->close();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if ($search !== '') {
    $query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($role_filter !== '') {
    $query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='teacher'")->fetch_assoc()['count'];
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='student'")->fetch_assoc()['count'];
$total_admins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='admin'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - ClassConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .search-filter-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
        }

        .search-filter-grid {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-text);
        }

        .search-input {
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .filter-select {
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 200px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .users-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
        }

        .users-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .users-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .users-table tbody tr {
            transition: all 0.2s ease;
        }

        .users-table tbody tr:hover {
            background: var(--lighter-bg);
        }

        .user-role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-admin {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .badge-teacher {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .badge-student {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-small {
            padding: 8px 14px;
            font-size: 13px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-view {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            border-left: 4px solid;
        }

        .stat-box.total {
            border-left-color: var(--primary-color);
        }

        .stat-box.teachers {
            border-left-color: #3b82f6;
        }

        .stat-box.students {
            border-left-color: #10b981;
        }

        .stat-box.admins {
            border-left-color: #ef4444;
        }

        .stat-box h4 {
            font-size: 14px;
            color: var(--light-text);
            margin-bottom: 8px;
        }

        .stat-box .value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark-text);
        }

        .modal {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal.active {
            opacity: 1;
            visibility: visible;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            padding: 32px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9) translateY(-20px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal.active .modal-content {
            transform: scale(1) translateY(0);
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .btn-cancel {
            background: var(--border-color);
            color: var(--dark-text);
        }

        .btn-cancel:hover {
            background: var(--light-text);
        }

        .user-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .user-detail-item {
            padding: 16px;
            background: var(--lighter-bg);
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }

        .user-detail-item label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--light-text);
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .user-detail-item .value {
            font-size: 15px;
            font-weight: 500;
            color: var(--dark-text);
            word-break: break-all;
        }

        .user-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 32px;
            margin: 0 auto 20px;
            box-shadow: 0 4px 16px rgba(30, 64, 175, 0.3);
        }

        .modal-content.view-modal {
            max-width: 600px;
        }

        @media (max-width: 768px) {
            .search-filter-grid {
                grid-template-columns: 1fr;
            }

            .users-table-container {
                overflow-x: auto;
            }

            .stats-row {
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
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="register_teacher.php">Register Teacher</a></li>
                <li><a href="manage_users.php" class="active">Manage Users</a></li>
                <li><a href="announcements_messages.php">Manage Announcements</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="topbar">
                <h1>Manage Users</h1>
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
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="stats-row">
                <div class="stat-box total">
                    <h4>Total Users</h4>
                    <div class="value"><?php echo $total_users; ?></div>
                </div>
                <div class="stat-box teachers">
                    <h4>Teachers</h4>
                    <div class="value"><?php echo $total_teachers; ?></div>
                </div>
                <div class="stat-box students">
                    <h4>Students</h4>
                    <div class="value"><?php echo $total_students; ?></div>
                </div>
                <div class="stat-box admins">
                    <h4>Admins</h4>
                    <div class="value"><?php echo $total_admins; ?></div>
                </div>
            </div>

            <div class="search-filter-section">
                <form method="GET" action="">
                    <div class="search-filter-grid">
                        <div class="filter-group">
                            <label for="search">🔍 Search Users</label>
                            <input type="text" id="search" name="search" class="search-input" 
                                   placeholder="Search by username, name, or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="role">👥 Filter by Role</label>
                            <select id="role" name="role" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>

            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="user-role-badge badge-<?php echo $user['role']; ?>">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-small btn-view view-user-btn" 
                                                    data-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                    data-role="<?php echo $user['role']; ?>"
                                                    data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                                    data-address="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                                                    data-created="<?php echo $user['created_at']; ?>">View</button>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>')" 
                                                        class="btn-small btn-edit">Edit Role</button>
                                                <button onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>')" 
                                                        class="btn-small btn-delete">Delete</button>
                                            <?php else: ?>
                                                <span style="color: var(--light-text); font-size: 12px;">Protected</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--light-text);">
                                    No users found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User Role</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label>User: <strong id="edit_user_name"></strong></label>
                </div>
                <div class="form-group">
                    <label for="new_role">New Role</label>
                    <select name="new_role" id="new_role" class="form-control" required>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                    </select>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-small btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_role" class="btn btn-small btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete User</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="delete_user_id">
                <p>Are you sure you want to delete <strong id="delete_user_name"></strong>? This action cannot be undone.</p>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-small btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-small btn-delete">Delete User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content view-modal">
            <div class="modal-header">
                <h3>User Information</h3>
            </div>
            <div class="user-avatar-large" id="view_user_avatar"></div>
            <div class="user-detail-grid">
                <div class="user-detail-item">
                    <label>User ID</label>
                    <div class="value" id="view_user_id"></div>
                </div>
                <div class="user-detail-item">
                    <label>Username</label>
                    <div class="value" id="view_username"></div>
                </div>
                <div class="user-detail-item">
                    <label>Full Name</label>
                    <div class="value" id="view_full_name"></div>
                </div>
                <div class="user-detail-item">
                    <label>Role</label>
                    <div class="value" id="view_role"></div>
                </div>
                <div class="user-detail-item" style="grid-column: 1 / -1;">
                    <label>Email Address</label>
                    <div class="value" id="view_email"></div>
                </div>
                <div class="user-detail-item">
                    <label>Phone Number</label>
                    <div class="value" id="view_phone"></div>
                </div>
                <div class="user-detail-item">
                    <label>Created Date</label>
                    <div class="value" id="view_created"></div>
                </div>
                <div class="user-detail-item" style="grid-column: 1 / -1;">
                    <label>Address</label>
                    <div class="value" id="view_address"></div>
                </div>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-small btn-primary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
                </table>
            </div>
        </main>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User Role</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label>User: <strong id="edit_user_name"></strong></label>
                </div>
                <div class="form-group">
                    <label for="new_role">New Role</label>
                    <select name="new_role" id="new_role" class="form-control" required>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                    </select>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-small btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_role" class="btn btn-small btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete User</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="delete_user_id">
                <p>Are you sure you want to delete <strong id="delete_user_name"></strong>? This action cannot be undone.</p>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-small btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-small btn-delete">Delete User</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function openEditModal(userId, currentRole, userName) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_user_name').textContent = userName;
            document.getElementById('new_role').value = currentRole;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function openDeleteModal(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        // View user functionality with data attributes
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-user-btn');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    const fullName = this.getAttribute('data-fullname');
                    const email = this.getAttribute('data-email');
                    const role = this.getAttribute('data-role');
                    const phone = this.getAttribute('data-phone');
                    const address = this.getAttribute('data-address');
                    const createdAt = this.getAttribute('data-created');
                    
                    document.getElementById('view_user_avatar').textContent = fullName.charAt(0).toUpperCase();
                    document.getElementById('view_user_id').textContent = id;
                    document.getElementById('view_username').textContent = username;
                    document.getElementById('view_full_name').textContent = fullName;
                    document.getElementById('view_role').innerHTML = '<span class="user-role-badge badge-' + role + '">' + role.toUpperCase() + '</span>';
                    document.getElementById('view_email').textContent = email;
                    document.getElementById('view_phone').textContent = phone || 'Not provided';
                    document.getElementById('view_address').textContent = address || 'Not provided';
                    
                    const date = new Date(createdAt);
                    document.getElementById('view_created').textContent = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    document.getElementById('viewModal').classList.add('active');
                });
            });
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
