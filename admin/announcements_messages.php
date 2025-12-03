<?php
require_once '../config/session.php';
checkRole('admin');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'announcements';

// ============================================================================
// ANNOUNCEMENTS HANDLING
// ============================================================================

// Handle Create/Update Announcement
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
                        WHERE id=$announcement_id AND target_audience IN ('All Students', 'All Teachers')";

                if ($conn->query($sql) === TRUE) {
                    $message = 'Announcement updated successfully!';
                } else {
                    $error = 'Error updating announcement: ' . $conn->error;
                }
            }
        }
    } elseif ($action === 'delete') {
        $announcement_id = intval($_POST['announcement_id']);
        $sql = "DELETE FROM announcements WHERE id=$announcement_id AND target_audience IN ('All Students', 'All Teachers')";

        if ($conn->query($sql) === TRUE) {
            $message = 'Announcement deleted successfully!';
        } else {
            $error = 'Error deleting announcement: ' . $conn->error;
        }
    } elseif ($action === 'send_message') {
        // Handle Send Message
        $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
        $subject = isset($_POST['subject']) ? $conn->real_escape_string($_POST['subject']) : '';
        $message_text = isset($_POST['message']) ? $conn->real_escape_string($_POST['message']) : '';
        $reply_to = isset($_POST['reply_to']) && !empty($_POST['reply_to']) ? intval($_POST['reply_to']) : NULL;

        if (empty($recipient_id) || empty($subject) || empty($message_text)) {
            $error = 'Recipient, subject and message are required.';
        } else {
            if ($reply_to) {
                $sql = "INSERT INTO messages (sender_id, recipient_id, reply_to, subject, message)
                        VALUES ($user_id, $recipient_id, $reply_to, '$subject', '$message_text')";
            } else {
                $sql = "INSERT INTO messages (sender_id, recipient_id, subject, message)
                        VALUES ($user_id, $recipient_id, '$subject', '$message_text')";
            }

            if ($conn->query($sql) === TRUE) {
                $message = 'Message sent successfully!';
            } else {
                $error = 'Error sending message: ' . $conn->error;
            }
        }
    } elseif ($action === 'mark_read') {
        $message_id = intval($_POST['message_id']);
        $sql = "UPDATE messages SET is_read=TRUE WHERE id=$message_id AND recipient_id=$user_id";
        $conn->query($sql);
    } elseif ($action === 'delete_message') {
        $message_id = intval($_POST['message_id']);
        $sql = "DELETE FROM messages WHERE id=$message_id AND sender_id=$user_id";

        if ($conn->query($sql) === TRUE) {
            $message = 'Message deleted successfully!';
        } else {
            $error = 'Error deleting message: ' . $conn->error;
        }
    } elseif ($action === 'update_message') {
        $message_id = intval($_POST['message_id']);
        $subject = $conn->real_escape_string($_POST['subject']);
        $message_text = $conn->real_escape_string($_POST['message']);

        if (empty($subject) || empty($message_text)) {
            $error = 'Subject and message are required.';
        } else {
            $sql = "UPDATE messages SET subject='$subject', message='$message_text', sent_at=NOW()
                    WHERE id=$message_id AND sender_id=$user_id";

            if ($conn->query($sql) === TRUE) {
                $message = 'Message updated successfully!';
            } else {
                $error = 'Error updating message: ' . $conn->error;
            }
        }
    }
}

// Get search and filter parameters for announcements
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';

// Build announcements query with filters (admin can see all, but respect target audience)
$announcements_sql = "SELECT a.*, u.full_name FROM announcements a
                      JOIN users u ON a.user_id = u.id
                      WHERE a.target_audience IN ('All Students', 'All Teachers') OR u.role = 'teacher'";

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
$total_announcements = $conn->query("SELECT COUNT(*) as count FROM announcements")->fetch_assoc()['count'];

// Get announcement to edit if requested
$edit_announcement = null;
if (isset($_GET['edit'])) {
    $announcement_id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM announcements WHERE id=$announcement_id");
    if ($result->num_rows > 0) {
        $edit_announcement = $result->fetch_assoc();
    }
}

// Get responses for announcements
$responses_query = "SELECT ar.*, u.full_name, a.title as announcement_title FROM announcement_responses ar
                    JOIN users u ON ar.user_id = u.id
                    JOIN announcements a ON ar.announcement_id = a.id
                    ORDER BY ar.responded_at DESC LIMIT 15";
$recent_responses = $conn->query($responses_query);

// ============================================================================
// MESSAGES HANDLING
// ============================================================================

// Get inbox messages
$inbox = $conn->query("SELECT m.*, u.full_name FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       WHERE m.recipient_id=$user_id
                       ORDER BY m.sent_at DESC");

// Get sent messages
$sent = $conn->query("SELECT m.*, u.full_name FROM messages m
                      JOIN users u ON m.recipient_id = u.id
                      WHERE m.sender_id=$user_id
                      ORDER BY m.sent_at DESC");

// Get unread count
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE recipient_id=$user_id AND is_read=FALSE")->fetch_assoc()['count'];

// Get list of users for sending messages - exclude current user
$recipients = $conn->query("SELECT id, full_name, role FROM users WHERE role IN ('teacher', 'student', 'admin') AND id != $user_id ORDER BY role DESC, full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements & Messages - ClassConnect</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            /* Original ClassConnect Color Scheme */
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #4CAF50;
            --danger-color: #f44336;
            --warning-color: #ff9800;
            --info-color: #2196F3;
            --light-bg: #f5f7fa;
            --dark-text: #333;
            --light-text: #666;
            --border-color: #ddd;

            /* Gradients using original colors */
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .main-tabs {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
        }
        .tabs-container {
            display: flex;
            gap: 20px;
        }
        .main-tab {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: var(--text-medium);
            transition: all 0.3s;
            position: relative;
            margin-bottom: -12px;
        }
        .main-tab:hover {
            color: var(--primary-color);
        }
        .main-tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .main-tab-content { display: none; }
        .main-tab-content.active { display: block; }
        .unread-badge {
            display: inline-block;
            background: var(--accent-gradient);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 5px;
            box-shadow: 0 2px 6px rgba(255, 107, 107, 0.3);
        }

        /* Announcement Modal Styles (Custom) */
        #announcementModal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        #announcementModal .modal-content { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 2% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 700px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); animation: slideDown 0.3s; }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        #announcementModal .modal-header { background: rgba(255,255,255,0.95); padding: 25px 30px; border-radius: 15px 15px 0 0; border-bottom: 3px solid #667eea; }
        #announcementModal .modal-header h2 { margin: 0; color: #667eea; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        #announcementModal .modal-header h2::before { content: '📢'; font-size: 28px; }
        #announcementModal .modal-body { background: white; padding: 30px; max-height: 70vh; overflow-y: auto; }
        #announcementModal .modal-footer {
            background: white;
            padding: 20px 35px;
            border-radius: 0 0 15px 15px;
            border-top: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        #announcementModal .modal-footer .btn {
            flex: 1;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        #announcementModal .close { color: #999; float: right; font-size: 32px; font-weight: bold; cursor: pointer; transition: color 0.3s; line-height: 1; }
        #announcementModal .close:hover { color: #667eea; }

        /* Form Styles */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px; }
        .form-group label .required { color: #dc3545; margin-left: 3px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s; font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .form-group select { cursor: pointer; background: white; }

        /* Button Styles */
        .btn-group { display: flex; gap: 10px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        .btn-success { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-success:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
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
            white-space: nowrap;
        }
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-create::before { content: '➕'; }

        /* Card Styles */
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

        .card-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }

        /* Announcement Card Styles */
        .announcement-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 5px solid #667eea;
        }

        .announcement-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .announcement-card.urgent {
            border-left-color: #dc3545;
        }

        .announcement-card.event {
            border-left-color: #7b1fa2;
        }

        .announcement-title {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            margin: 15px 0;
            line-height: 1.4;
        }

        .announcement-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .badge-custom {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-academic { background: #e3f2fd; color: #1976d2; }
        .badge-event { background: #e8f5e9; color: #2e7d32; }
        .badge-generalnotice { background: #e8f5e9; color: #388e3c; }
        .badge-administrative { background: #fff3e0; color: #f57c00; }
        .badge-reminder { background: #fce4ec; color: #c2185b; }
        .badge-urgent { background: #ffebee; color: #c62828; font-weight: 700; }
        .badge-general { background: #f5f5f5; color: #666; }
        .badge-response-required {
            background: #fff3e0;
            color: #e65100;
        }

        .announcement-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 15px 0;
            padding: 12px 0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #555;
        }

        .meta-item i {
            color: #667eea;
            font-size: 16px;
        }

        .meta-item strong { color: #333; font-weight: 600; }

        .announcement-content {
            margin: 15px 0;
            line-height: 1.8;
            color: #333;
            font-size: 15px;
        }

        /* Announcement Footer */
        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .author-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .author-details {
            display: flex;
            flex-direction: column;
        }

        .author-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .author-role {
            font-size: 12px;
            color: #666;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-edit,
        .btn-delete {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        .announcement-actions { display: flex; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; }

        .response-item { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 3px solid #667eea; }
        .response-item strong { color: #667eea; }

        /* Search Section */
        .search-section {
            background: var(--bg-white);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(74, 144, 226, 0.08);
            border: 2px solid var(--border-color);
        }
        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .search-input-wrapper {
            position: relative;
            flex: 1;
        }
        .search-input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 18px;
        }
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 45px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        .search-clear {
            position: absolute;
            right: 15px;
            color: #999;
            font-size: 18px;
            cursor: pointer;
            transition: color 0.3s;
        }
        .search-clear:hover {
            color: var(--danger-color);
        }
        /* Search input styles */
        .search-input {
            padding: 12px 40px 12px 40px !important;
        }

        /* Filter Section */
        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            background: var(--bg-white);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(74, 144, 226, 0.08);
            border: 2px solid var(--border-color);
        }
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-group label {
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .filter-select {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            background: white;
        }
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .btn-refresh {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .announcement-stats {
            font-size: 14px;
            color: var(--text-medium);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-indicator {
            background: var(--bg-light);
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .message-list { background: #f9f9f9; border-radius: 4px; }
        .message-item {
            padding: 12px; border-bottom: 1px solid #ddd; cursor: pointer;
            transition: background 0.2s;
        }
        .message-item:hover { background: #f0f0f0; }
        .message-item.unread { background: #e3f2fd; font-weight: bold; }
        .message-item h5 { margin: 0 0 5px 0; }
        .message-item p { margin: 0; font-size: 0.9em; color: #666; }
        .message-item small { color: #999; }

        /* Message Tabs - Pill Style */
        .message-tabs-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .tabs {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border-radius: 12px;
            background: var(--bg-white);
            color: var(--text-medium);
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 2px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.08);
        }

        .tab.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 6px 18px rgba(74, 144, 226, 0.35);
            transform: translateY(-2px);
        }

        .tab:hover:not(.active) {
            background: linear-gradient(135deg, #E8F4FD 0%, #FFFFFF 100%);
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.2);
        }

        .tab-badge {
            background: var(--accent-gradient);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4);
            animation: pulse 2s infinite;
        }

        .tab.active .tab-badge {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 255, 255, 0.2);
        }

        /* Message Filters */
        .message-filters {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin: 0;
        }

        .filter-select {
            padding: 8px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #007bff;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 15px 8px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #007bff;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .view-toggle {
            display: flex;
            gap: 5px;
        }

        .view-toggle button {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
        }

        .view-toggle button:first-child {
            border-radius: 8px 0 0 8px;
        }

        .view-toggle button:last-child {
            border-radius: 0 8px 8px 0;
        }

        .view-toggle button.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .view-toggle button:hover:not(.active) {
            background: #f5f5f5;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Message Item Styles */
        .message-item {
            background: white;
            border-radius: 10px;
            padding: 18px;
            margin: 12px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #667eea;
        }
        .message-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        .message-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .message-actions {
            display: flex;
            gap: 8px;
        }
        .btn-edit-message,
        .btn-delete-message {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .message-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn-reply-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-reply-message:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .btn-edit-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-edit-message:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-delete-message {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .btn-delete-message:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        /* Message Modal Styles */
        .message-modal-content { border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 15px 50px rgba(0,0,0,0.2); }
        .message-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 25px 30px;
        }
        .message-modal-header .modal-title {
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .message-modal-body {
            padding: 35px;
            background: #f8f9fa;
        }
        .message-modal-footer {
            background: white;
            border-top: 2px solid #e9ecef;
            padding: 20px 35px;
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        .message-modal-footer .btn {
            flex: 1;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .message-modal-footer .btn-secondary {
            background: #6c757d;
            border: none;
        }
        .message-modal-footer .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        /* View Message Styles */
        .view-message-content .form-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .view-message-field {
            background: white;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            font-size: 15px;
            color: #212529;
        }
        .view-message-body {
            min-height: 150px;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
        }
        .btn-view-message {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-view-message:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
        }

        .btn-compose-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            margin-left: auto;
            white-space: nowrap;
            min-width: 200px;
            justify-content: center;
        }
        .btn-compose-message:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
            box-shadow: 0 6px 18px rgba(102, 126, 234, 0.4);
        }

        .btn-send-message {
            background: var(--primary-gradient) !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3) !important;
        }
        .btn-send-message:hover {
            background: linear-gradient(135deg, #357ABD 0%, #2A5F99 100%) !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(74, 144, 226, 0.4) !important;
        }

        #composeMessageModal .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            font-size: 15px;
        }

        #composeMessageModal .form-control,
        #composeMessageModal .form-select {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 12px 16px;
            transition: all 0.3s;
            font-size: 15px;
        }

        #composeMessageModal .form-control:focus,
        #composeMessageModal .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
        }

        #composeMessageModal textarea.form-control {
            resize: vertical;
            min-height: 180px;
        }

        #composeMessageModal .form-text {
            color: #6c757d;
            font-size: 13px;
            margin-top: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .message-modal-body { padding: 25px 20px; }
            .message-modal-footer {
                padding: 15px 20px;
                flex-direction: column;
            }
            .message-modal-footer .btn {
                width: 100%;
            }
            #announcementModal .modal-footer {
                padding: 15px 20px;
                flex-direction: column;
            }
            #announcementModal .modal-footer .btn {
                width: 100%;
            }
            .btn-compose-message { width: 100%; justify-content: center; }
            .form-row { grid-template-columns: 1fr; }
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
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="announcements_messages.php" class="active">Manage Announcements <?php if ($unread_count > 0) echo '<span class="unread-badge">' . $unread_count . '</span>'; ?></a></li>
                <li><a href="profile.php">Profile Settings</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="topbar">
                <h1>Manage Announcements</h1>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                    <span><?php echo $_SESSION['full_name']; ?></span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- MAIN TABS -->
            <div class="card">
                <div class="main-tabs">
                    <div class="tabs-container">
                        <div class="main-tab <?php echo $current_tab === 'announcements' ? 'active' : ''; ?>" onclick="switchMainTab('announcements', event)">
                            📢 Manage Announcements
                        </div>
                        <div class="main-tab <?php echo $current_tab === 'messages' ? 'active' : ''; ?>" onclick="switchMainTab('messages', event)">
                            💬 Messages <?php if ($unread_count > 0) echo '<span class="unread-badge">' . $unread_count . '</span>'; ?>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button onclick="window.location.reload()" class="btn-refresh" title="Refresh announcements">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- ============================================================================ -->
                <!-- ANNOUNCEMENTS TAB -->
                <!-- ============================================================================ -->
            <div id="announcements" class="main-tab-content <?php echo $current_tab === 'announcements' ? 'active' : ''; ?>">
                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-group">
                        <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="hidden" name="tab" value="announcements">
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
                        <input type="hidden" name="tab" value="announcements">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" name="search" placeholder="Search announcements by title or content... (Press Enter)"
                                   value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
                            <?php if ($search_query): ?>
                                <a href="?tab=announcements" class="search-clear" title="Clear search">
                                    <i class="bi bi-x-circle-fill"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>📋 All Announcements</h3>
                        <div class="announcement-stats">
                            Showing <?php echo $announcements->num_rows; ?> of <?php echo $total_announcements; ?> announcements
                            <?php if ($search_query): ?>
                                <span class="search-indicator">
                                    <i class="bi bi-search"></i> Search: "<?php echo htmlspecialchars($search_query); ?>"
                                </span>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-create" onclick="openAnnouncementModal()">Create New Announcement</button>
                    </div>
                    <?php if ($announcements->num_rows > 0): ?>
                        <?php while ($ann = $announcements->fetch_assoc()): ?>
                            <div class="announcement-card <?php echo $ann['type']; ?>">
                                <!-- Badges Section -->
                                <div class="announcement-badges">
                                    <span class="badge-custom badge-<?php echo strtolower(str_replace(' ', '', $ann['category'])); ?>">
                                        <?php echo strtoupper($ann['category']); ?>
                                    </span>
                                    <?php if ($ann['type'] === 'urgent'): ?>
                                        <span class="badge-custom" style="background: #ffebee; color: #c62828;">
                                            <i class="bi bi-exclamation-triangle"></i> URGENT
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge-custom" style="background: #e3f2fd; color: #1976d2;">
                                        <?php echo date('d/m/Y', strtotime($ann['created_at'])); ?>
                                    </span>
                                </div>

                                <!-- Title -->
                                <h3 class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></h3>

                                <!-- Content -->
                                <div class="announcement-content">
                                    <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                                </div>

                                <!-- Event Date (if applicable) -->
                                <?php if ($ann['event_date']): ?>
                                    <div class="announcement-meta">
                                        <div class="meta-item">
                                            <i class="bi bi-calendar-event"></i>
                                            <strong>Event Date:</strong> <?php echo date('M d, Y, h:i A', strtotime($ann['event_date'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Footer with Author Info and Actions -->
                                <div class="announcement-footer">
                                    <div class="author-info">
                                        <div class="author-avatar">
                                            <?php echo strtoupper(substr($ann['full_name'], 0, 1)); ?>
                                        </div>
                                        <div class="author-details">
                                            <div class="author-name">By <?php echo htmlspecialchars($ann['full_name']); ?></div>
                                            <div class="author-role">Target: <?php echo htmlspecialchars($ann['target_audience']); ?></div>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($ann); ?>)'>
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to delete this announcement?');">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; padding: 40px; color: #999;">
                            📢 No announcements yet. Click "Create Announcement" to get started!
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($recent_responses->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Responses</h3>
                    </div>
                    <?php while ($response = $recent_responses->fetch_assoc()): ?>
                        <div class="response-item">
                            <strong><?php echo htmlspecialchars($response['full_name']); ?></strong> -
                            <span class="badge"><?php echo ucfirst($response['response_type']); ?></span>
                            <p><em><?php echo htmlspecialchars($response['announcement_title']); ?></em></p>
                            <p><?php echo htmlspecialchars($response['response_text']); ?></p>
                            <small><?php echo date('M d, Y H:i', strtotime($response['responded_at'])); ?></small>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
                </div>

                <!-- ============================================================================ -->
                <!-- MESSAGES TAB -->
                <!-- ============================================================================ -->
                <div id="messages" class="main-tab-content <?php echo $current_tab === 'messages' ? 'active' : ''; ?>">
                    <!-- Message Tabs and Compose Button -->
                    <div class="message-tabs-container">
                        <div class="tabs">
                            <button class="tab active" onclick="switchTab('inbox', event)">
                                <i class="bi bi-inbox-fill"></i> Inbox
                                <?php if ($unread_count > 0) echo '<span class="tab-badge">' . $unread_count . '</span>'; ?>
                            </button>
                            <button class="tab" onclick="switchTab('sent', event)">
                                <i class="bi bi-send-fill"></i> Sent
                            </button>
                        </div>
                        <!-- Compose Message Button -->
                        <button type="button" class="btn-compose-message" data-bs-toggle="modal" data-bs-target="#composeMessageModal">
                            <i class="bi bi-envelope-plus"></i> Compose New Message
                        </button>
                    </div>

                    <!-- Message Filters -->
                    <div class="message-filters">
                        <div class="filter-group">
                            <label>Filter:</label>
                            <select class="filter-select" id="messageFilter" onchange="filterMessages()">
                                <option value="all">All Messages</option>
                                <option value="unread">Unread Only</option>
                                <option value="read">Read Only</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Sort by:</label>
                            <select class="filter-select" id="messageSort" onchange="sortMessages()">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="sender">By Sender</option>
                            </select>
                        </div>

                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" id="messageSearch" placeholder="Search messages..." onkeyup="searchMessages()">
                        </div>

                        <div class="view-toggle">
                            <button class="active" onclick="toggleView('list')" title="List View">
                                <i class="bi bi-list-ul"></i>
                            </button>
                            <button onclick="toggleView('grid')" title="Grid View">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </button>
                        </div>
                    </div>

                    <div id="inbox" class="tab-content active">
                        <h3>Inbox</h3>
                        <?php if ($inbox->num_rows > 0): ?>
                            <div class="message-list">
                                <?php while ($msg = $inbox->fetch_assoc()): ?>
                                    <div class="message-item <?php echo !$msg['is_read'] ? 'unread' : ''; ?>" data-message-id="<?php echo $msg['id']; ?>">
                                        <div class="message-header-row">
                                            <div>
                                                <h5>From: <?php echo htmlspecialchars($msg['full_name']); ?></h5>
                                                <p><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></p>
                                            </div>
                                            <div class="message-actions">
                                                <button class="btn-view-message"
                                                    data-person="<?php echo htmlspecialchars($msg['full_name']); ?>"
                                                    data-subject="<?php echo htmlspecialchars($msg['subject']); ?>"
                                                    data-message="<?php echo htmlspecialchars($msg['message']); ?>"
                                                    data-date="<?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?>"
                                                    data-direction="From"
                                                    data-message-id="<?php echo $msg['id']; ?>"
                                                    data-is-unread="<?php echo !$msg['is_read'] ? 'true' : 'false'; ?>"
                                                    onclick="viewMessageFromData(this)"
                                                    title="View Full Message">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <button class="btn-reply-message"
                                                    data-sender-id="<?php echo $msg['sender_id']; ?>"
                                                    data-sender-name="<?php echo htmlspecialchars($msg['full_name']); ?>"
                                                    data-subject="<?php echo htmlspecialchars($msg['subject']); ?>"
                                                    data-message-id="<?php echo $msg['id']; ?>"
                                                    onclick="replyToMessageFromData(this)"
                                                    title="Reply to Message">
                                                    <i class="bi bi-reply-fill"></i> Reply
                                                </button>
                                            </div>
                                        </div>
                                        <p><?php echo htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : ''); ?></p>
                                        <small><?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?></small>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p>No messages in inbox.</p>
                        <?php endif; ?>
                    </div>

                    <div id="sent" class="tab-content">
                        <h3>Sent Messages</h3>
                        <?php if ($sent->num_rows > 0): ?>
                            <div class="message-list">
                                <?php while ($msg = $sent->fetch_assoc()): ?>
                                    <div class="message-item">
                                        <div class="message-header-row">
                                            <div>
                                                <h5>To: <?php echo htmlspecialchars($msg['full_name']); ?></h5>
                                                <p><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></p>
                                            </div>
                                            <div class="message-actions">
                                                <button class="btn-view-message"
                                                    data-person="<?php echo htmlspecialchars($msg['full_name']); ?>"
                                                    data-subject="<?php echo htmlspecialchars($msg['subject']); ?>"
                                                    data-message="<?php echo htmlspecialchars($msg['message']); ?>"
                                                    data-date="<?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?>"
                                                    data-direction="To"
                                                    onclick="viewMessageFromData(this)"
                                                    title="View Full Message">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <button class="btn-edit-message"
                                                    data-message-id="<?php echo $msg['id']; ?>"
                                                    data-subject="<?php echo htmlspecialchars($msg['subject']); ?>"
                                                    data-message="<?php echo htmlspecialchars($msg['message']); ?>"
                                                    data-recipient-id="<?php echo $msg['recipient_id']; ?>"
                                                    onclick="openEditMessageModalFromData(this)"
                                                    title="Edit Message">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                <button class="btn-delete-message" onclick="deleteMessage(<?php echo $msg['id']; ?>)" title="Delete Message">
                                                    <i class="bi bi-trash3"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                        <p><?php echo htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : ''); ?></p>
                                        <small><?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?></small>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p>No sent messages.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Create Announcement</h2>
                <span class="close" onclick="closeAnnouncementModal()">&times;</span>
            </div>
            <form method="POST" id="announcementForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="announcement_id" id="announcementId">

                    <div class="form-group full-width">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" id="title" required placeholder="Enter announcement title">
                    </div>

                    <div class="form-group full-width">
                        <label>Content <span class="required">*</span></label>
                        <textarea name="content" id="content" required placeholder="Enter announcement content"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" id="category">
                                <option value="Academic">📚 Academic</option>
                                <option value="Event">🎉 Event</option>
                                <option value="General Notice" selected>📢 General Notice</option>
                                <option value="Administrative">📋 Administrative</option>
                                <option value="Reminder">⏰ Reminder</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Type</label>
                            <select name="type" id="type">
                                <option value="general" selected>General</option>
                                <option value="urgent">🚨 Urgent</option>
                                <option value="event">🎊 Event</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Event Date (Optional)</label>
                            <input type="datetime-local" name="event_date" id="event_date">
                        </div>

                        <div class="form-group">
                            <label>Expiry Date (Optional)</label>
                            <input type="datetime-local" name="expiry_date" id="expiry_date">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Target Audience</label>
                        <select name="target_audience" id="target_audience">
                            <option value="All Students" selected>👨‍🎓 All Students</option>
                            <option value="All Teachers">👨‍🏫 All Teachers</option>
                            <option value="Specific">🎯 Specific</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAnnouncementModal()">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="submitBtn">
                        <i class="bi bi-megaphone"></i> Create Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Message Modal -->
    <div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content message-modal-content">
                <div class="modal-header message-modal-header">
                    <h5 class="modal-title" id="viewMessageModalLabel">
                        <i class="bi bi-envelope-open"></i> Message Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body message-modal-body">
                    <div class="view-message-content">
                        <div class="mb-3">
                            <label class="form-label text-muted"><i class="bi bi-person-circle"></i> <span id="viewMessageDirection">From</span></label>
                            <div class="view-message-field" id="viewMessagePerson"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted"><i class="bi bi-tag"></i> Subject</label>
                            <div class="view-message-field" id="viewMessageSubject"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted"><i class="bi bi-calendar3"></i> Date</label>
                            <div class="view-message-field" id="viewMessageDate"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted"><i class="bi bi-chat-left-text"></i> Message</label>
                            <div class="view-message-field view-message-body" id="viewMessageBody"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer message-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeMessageModal" tabindex="-1" aria-labelledby="composeMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content message-modal-content">
                <div class="modal-header message-modal-header">
                    <h5 class="modal-title" id="composeMessageModalLabel">
                        <i class="bi bi-envelope-heart"></i> New Message
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="messageForm">
                    <div class="modal-body message-modal-body">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="reply_to" id="reply_to" value="">

                        <!-- Reply Info Banner (hidden by default) -->
                        <div id="replyInfoBanner" class="alert alert-info" style="display: none; margin-bottom: 20px; padding: 12px; background: #e7f3ff; border-left: 4px solid #2196F3; border-radius: 8px;">
                            <i class="bi bi-reply-fill"></i> <strong>Replying to:</strong> <span id="replyToName"></span>
                            <button type="button" class="btn-close float-end" onclick="cancelReply()" style="font-size: 12px;"></button>
                        </div>

                        <div class="mb-4">
                            <label for="recipient_id" class="form-label">
                                <i class="bi bi-person-circle"></i> To
                            </label>
                            <select name="recipient_id" id="recipient_id" class="form-select form-select-lg" required>
                                <option value="">Choose a recipient...</option>
                                <?php
                                $recipients->data_seek(0);
                                while ($recipient = $recipients->fetch_assoc()): ?>
                                    <option value="<?php echo $recipient['id']; ?>">
                                        <?php echo htmlspecialchars($recipient['full_name']) . ' (' . ucfirst($recipient['role']) . ')'; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="subject" class="form-label">
                                <i class="bi bi-tag"></i> Subject
                            </label>
                            <input type="text" name="subject" id="subject" class="form-control form-control-lg"
                                   placeholder="What's this about?" required>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">
                                <i class="bi bi-chat-left-text"></i> Message
                            </label>
                            <textarea name="message" id="message" class="form-control" rows="8"
                                      placeholder="Write your message here..." required></textarea>
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> Be clear and concise in your message
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer message-modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-send-message">
                            <i class="bi bi-send-fill"></i> Send Message
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

        // Modal Functions
        function openAnnouncementModal() {
            document.getElementById('announcementModal').style.display = 'block';
            document.getElementById('modalTitle').innerHTML = '📢 Create New Announcement';
            document.getElementById('formAction').value = 'create';
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-megaphone"></i> Create Announcement';
            document.getElementById('announcementForm').reset();
            document.getElementById('announcementId').value = '';
        }

        function openEditModal(announcement) {
            document.getElementById('announcementModal').style.display = 'block';
            document.getElementById('modalTitle').innerHTML = '✏️ Edit Announcement';
            document.getElementById('formAction').value = 'update';
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle"></i> Update Announcement';

            // Fill form with announcement data
            document.getElementById('announcementId').value = announcement.id;
            document.getElementById('title').value = announcement.title;
            document.getElementById('content').value = announcement.content;
            document.getElementById('category').value = announcement.category;
            document.getElementById('type').value = announcement.type;
            document.getElementById('target_audience').value = announcement.target_audience;

            // Handle dates
            if (announcement.event_date) {
                const eventDate = new Date(announcement.event_date);
                document.getElementById('event_date').value = eventDate.toISOString().slice(0, 16);
            }
            if (announcement.expiry_date) {
                const expiryDate = new Date(announcement.expiry_date);
                document.getElementById('expiry_date').value = expiryDate.toISOString().slice(0, 16);
            }
        }

        function closeAnnouncementModal() {
            document.getElementById('announcementModal').style.display = 'none';
        }

        // View Message Modal
        function viewMessage(person, subject, message, date, direction, messageId, isUnread) {
            document.getElementById('viewMessageDirection').textContent = direction;
            document.getElementById('viewMessagePerson').textContent = person;
            document.getElementById('viewMessageSubject').textContent = subject;
            document.getElementById('viewMessageDate').textContent = date;
            document.getElementById('viewMessageBody').textContent = message;

            const viewModal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
            viewModal.show();

            // Mark message as read if it's unread and it's an inbox message
            if (isUnread && direction === 'From' && messageId) {
                markMessageAsRead(messageId);
            }
        }

        // Helper function to view message from data attributes
        function viewMessageFromData(button) {
            const person = button.getAttribute('data-person');
            const subject = button.getAttribute('data-subject');
            const message = button.getAttribute('data-message');
            const date = button.getAttribute('data-date');
            const direction = button.getAttribute('data-direction');
            const messageId = button.getAttribute('data-message-id');
            const isUnread = button.getAttribute('data-is-unread') === 'true';
            viewMessage(person, subject, message, date, direction, messageId, isUnread);
        }

        // Mark message as read
        function markMessageAsRead(messageId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_read&message_id=' + messageId
            })
            .then(response => response.text())
            .then(data => {
                // Remove unread class from the message item
                const messageItem = document.querySelector(`.message-item[data-message-id="${messageId}"]`);
                if (messageItem) {
                    messageItem.classList.remove('unread');
                }

                // Update unread badge count
                const badge = document.querySelector('.tab-badge');
                if (badge) {
                    let count = parseInt(badge.textContent) - 1;
                    if (count > 0) {
                        badge.textContent = count;
                    } else {
                        badge.remove();
                    }
                }
            })
            .catch(error => console.error('Error marking message as read:', error));
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('announcementModal');
            if (event.target == modal) {
                closeAnnouncementModal();
            }
        }

        // Tab switching functions
        function switchMainTab(tabName, event) {
            event.preventDefault();
            document.querySelectorAll('.main-tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.main-tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.closest('.main-tab').classList.add('active');
            window.history.pushState({}, '', '?tab=' + tabName);
        }

        function switchTab(tabName, event) {
            event.preventDefault();
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.closest('.tab').classList.add('active');
        }

        // Reply to Message
        function replyToMessage(senderId, senderName, originalSubject, messageId) {
            // Set reply_to hidden field
            document.getElementById('reply_to').value = messageId;

            // Pre-select recipient (don't disable, just make it readonly visually)
            document.getElementById('recipient_id').value = senderId;

            // Pre-fill subject with "Re: "
            const subjectField = document.getElementById('subject');
            if (!originalSubject.startsWith('Re: ')) {
                subjectField.value = 'Re: ' + originalSubject;
            } else {
                subjectField.value = originalSubject;
            }

            // Show reply info banner
            document.getElementById('replyInfoBanner').style.display = 'block';
            document.getElementById('replyToName').textContent = senderName;

            // Update modal title
            document.getElementById('composeMessageModalLabel').innerHTML = '<i class="bi bi-reply-fill"></i> Reply to Message';

            // Open compose modal
            const composeModal = new bootstrap.Modal(document.getElementById('composeMessageModal'));
            composeModal.show();
        }

        // Helper function to reply from data attributes
        function replyToMessageFromData(button) {
            const senderId = button.getAttribute('data-sender-id');
            const senderName = button.getAttribute('data-sender-name');
            const subject = button.getAttribute('data-subject');
            const messageId = button.getAttribute('data-message-id');
            replyToMessage(senderId, senderName, subject, messageId);
        }

        // Edit Message Modal
        function openEditMessageModal(messageId, subject, message, recipientId) {
            document.getElementById('edit_message_id').value = messageId;
            document.getElementById('edit_subject').value = subject;
            document.getElementById('edit_message').value = message;
            document.getElementById('edit_recipient_id').value = recipientId;

            const editModal = new bootstrap.Modal(document.getElementById('editMessageModal'));
            editModal.show();
        }

        // Helper function to open edit modal from data attributes
        function openEditMessageModalFromData(button) {
            const messageId = button.getAttribute('data-message-id');
            const subject = button.getAttribute('data-subject');
            const message = button.getAttribute('data-message');
            const recipientId = button.getAttribute('data-recipient-id');
            openEditMessageModal(messageId, subject, message, recipientId);
        }

        // Cancel Reply
        function cancelReply() {
            document.getElementById('reply_to').value = '';
            document.getElementById('recipient_id').value = '';
            document.getElementById('subject').value = '';
            document.getElementById('replyInfoBanner').style.display = 'none';
            document.getElementById('composeMessageModalLabel').innerHTML = '<i class="bi bi-envelope-heart"></i> New Message';
        }

        // Reset form when modal is closed
        document.getElementById('composeMessageModal').addEventListener('hidden.bs.modal', function () {
            if (document.getElementById('reply_to').value) {
                cancelReply();
            }
            document.getElementById('messageForm').reset();
        });

        // Message Filter, Sort, Search, and View Toggle Functions
        function filterMessages() {
            const filterValue = document.getElementById('messageFilter').value;
            const activeTab = document.querySelector('.tab-content.active');
            const messages = activeTab.querySelectorAll('.message-item');

            messages.forEach(message => {
                const isUnread = message.classList.contains('unread');

                if (filterValue === 'all') {
                    message.style.display = '';
                } else if (filterValue === 'unread' && isUnread) {
                    message.style.display = '';
                } else if (filterValue === 'read' && !isUnread) {
                    message.style.display = '';
                } else {
                    message.style.display = 'none';
                }
            });
        }

        function sortMessages() {
            const sortValue = document.getElementById('messageSort').value;
            const activeTab = document.querySelector('.tab-content.active');
            const messageList = activeTab.querySelector('.message-list');
            if (!messageList) return;

            const messages = Array.from(messageList.querySelectorAll('.message-item'));

            messages.sort((a, b) => {
                if (sortValue === 'newest') {
                    const dateA = new Date(a.querySelector('small').textContent);
                    const dateB = new Date(b.querySelector('small').textContent);
                    return dateB - dateA;
                } else if (sortValue === 'oldest') {
                    const dateA = new Date(a.querySelector('small').textContent);
                    const dateB = new Date(b.querySelector('small').textContent);
                    return dateA - dateB;
                } else if (sortValue === 'sender') {
                    const senderA = a.querySelector('h5').textContent.toLowerCase();
                    const senderB = b.querySelector('h5').textContent.toLowerCase();
                    return senderA.localeCompare(senderB);
                }
            });

            messages.forEach(message => messageList.appendChild(message));
        }

        function searchMessages() {
            const searchValue = document.getElementById('messageSearch').value.toLowerCase();
            const activeTab = document.querySelector('.tab-content.active');
            const messages = activeTab.querySelectorAll('.message-item');

            messages.forEach(message => {
                const sender = message.querySelector('h5').textContent.toLowerCase();
                const subject = message.querySelector('strong').textContent.toLowerCase();
                const content = message.querySelector('p').textContent.toLowerCase();

                if (sender.includes(searchValue) || subject.includes(searchValue) || content.includes(searchValue)) {
                    message.style.display = '';
                } else {
                    message.style.display = 'none';
                }
            });
        }

        function toggleView(viewType) {
            const buttons = document.querySelectorAll('.view-toggle button');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.closest('button').classList.add('active');

            const activeTab = document.querySelector('.tab-content.active');
            const messageList = activeTab.querySelector('.message-list');

            if (viewType === 'grid') {
                messageList.style.display = 'grid';
                messageList.style.gridTemplateColumns = 'repeat(auto-fill, minmax(350px, 1fr))';
                messageList.style.gap = '20px';
            } else {
                messageList.style.display = 'block';
                messageList.style.gridTemplateColumns = '';
                messageList.style.gap = '';
            }
        }

        // Auto-open edit modal if edit parameter exists
        <?php if ($edit_announcement): ?>
            window.onload = function() {
                openEditModal(<?php echo json_encode($edit_announcement); ?>);
            };
        <?php endif; ?>
    </script>
</body>
</html>

