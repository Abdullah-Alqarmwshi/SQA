<?php
require_once '../config/session.php';
checkRole('student');
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'announcements';

// ============================================================================
// ANNOUNCEMENTS SECTION
// ============================================================================

// Handle Response to Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'respond') {
        $announcement_id = intval($_POST['announcement_id']);
        $response_type = $conn->real_escape_string($_POST['response_type']);
        $response_text = $conn->real_escape_string($_POST['response_text']);
        
        $check = $conn->query("SELECT id FROM announcement_responses WHERE announcement_id=$announcement_id AND user_id=$user_id");
        
        if ($check->num_rows > 0) {
            $sql = "UPDATE announcement_responses SET response_type='$response_type', response_text='$response_text', responded_at=NOW() 
                    WHERE announcement_id=$announcement_id AND user_id=$user_id";
            $message = 'Response updated successfully!';
        } else {
            $sql = "INSERT INTO announcement_responses (announcement_id, user_id, response_type, response_text) 
                    VALUES ($announcement_id, $user_id, '$response_type', '$response_text')";
            $message = 'Response submitted successfully!';
        }
        
        if ($conn->query($sql) === TRUE) {
            $current_tab = 'announcements';
        } else {
            $error = 'Error submitting response: ' . $conn->error;
        }
    }
    
    // ============================================================================
    // MESSAGING SECTION
    // ============================================================================
    
    elseif ($action === 'send') {
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
                $current_tab = 'messages';
            } else {
                $error = 'Error sending message: ' . $conn->error;
            }
        }
    } elseif ($action === 'mark_read') {
        $message_id = intval($_POST['message_id']);
        $sql = "UPDATE messages SET is_read=TRUE WHERE id=$message_id AND recipient_id=$user_id";
        $conn->query($sql);
    } elseif ($action === 'mark_announcement_read') {
        $announcement_id = intval($_POST['announcement_id']);
        $sql = "INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES ($announcement_id, $user_id)";
        $conn->query($sql);
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'mark_all_read') {
        // Mark all current announcements as read
        $conn->query("INSERT IGNORE INTO announcement_reads (announcement_id, user_id)
                      SELECT a.id, $user_id FROM announcements a
                      WHERE (a.target_audience = 'All Students' OR a.target_audience = 'Specific')");
        $message = 'All announcements marked as read!';
        $current_tab = 'announcements';
    }
}

// Get announcements - ONLY show announcements targeted to students
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$quick_filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where_clause = "WHERE (a.target_audience = 'All Students' OR a.target_audience = 'Specific')";
if ($category_filter) {
    $category_filter = $conn->real_escape_string($category_filter);
    $where_clause .= " AND a.category='$category_filter'";
}

// Search functionality
if ($search_query) {
    $search_query = $conn->real_escape_string($search_query);
    $where_clause .= " AND (a.title LIKE '%$search_query%' OR a.content LIKE '%$search_query%')";
}

// Quick filters
if ($quick_filter === 'unread') {
    $where_clause .= " AND NOT EXISTS (SELECT 1 FROM announcement_reads WHERE announcement_id=a.id AND user_id=$user_id)";
} elseif ($quick_filter === 'urgent') {
    $where_clause .= " AND a.type='urgent'";
} elseif ($quick_filter === 'need_response') {
    $where_clause .= " AND NOT EXISTS (SELECT 1 FROM announcement_responses WHERE announcement_id=a.id AND user_id=$user_id)";
}

$order_by = "a.created_at DESC";
if ($sort_by === 'event_date') {
    $order_by = "a.event_date DESC";
}

// Get total count for pagination
$count_result = $conn->query("SELECT COUNT(*) as total FROM announcements a $where_clause");
$total_announcements = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_announcements / $per_page);

$announcements = $conn->query("SELECT a.*, u.full_name,
                               (SELECT COUNT(*) FROM announcement_responses WHERE announcement_id=a.id) as response_count,
                               (SELECT response_type FROM announcement_responses WHERE announcement_id=a.id AND user_id=$user_id LIMIT 1) as my_response,
                               (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id=a.id AND user_id=$user_id) as is_read
                               FROM announcements a
                               JOIN users u ON a.user_id = u.id
                               $where_clause
                               ORDER BY $order_by
                               LIMIT $per_page OFFSET $offset");

$my_responses = $conn->query("SELECT ar.*, a.title FROM announcement_responses ar
                              JOIN announcements a ON ar.announcement_id = a.id
                              WHERE ar.user_id=$user_id
                              ORDER BY ar.responded_at DESC");

// Get counts for sidebar badges
$unread_announcements_count = $conn->query("SELECT COUNT(*) as count FROM announcements a
                                            WHERE (a.target_audience = 'All Students' OR a.target_audience = 'Specific')
                                            AND NOT EXISTS (SELECT 1 FROM announcement_reads WHERE announcement_id=a.id AND user_id=$user_id)")->fetch_assoc()['count'];

$need_response_count = $conn->query("SELECT COUNT(*) as count FROM announcements a
                                     WHERE (a.target_audience = 'All Students' OR a.target_audience = 'Specific')
                                     AND NOT EXISTS (SELECT 1 FROM announcement_responses WHERE announcement_id=a.id AND user_id=$user_id)")->fetch_assoc()['count'];

// ============================================================================
// MESSAGES HANDLING
// ============================================================================

// Get inbox messages - student can see all messages sent to them
$inbox = $conn->query("SELECT m.*, u.full_name FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       WHERE m.recipient_id=$user_id
                       ORDER BY m.sent_at DESC");

// Get sent messages - student can see all messages they sent
$sent = $conn->query("SELECT m.*, u.full_name FROM messages m
                      JOIN users u ON m.recipient_id = u.id
                      WHERE m.sender_id=$user_id
                      ORDER BY m.sent_at DESC");

// Get unread count
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE recipient_id=$user_id AND is_read=FALSE")->fetch_assoc()['count'];

// Get recipients - exclude current user, only show teachers and admins
$recipients = $conn->query("SELECT id, full_name, role FROM users WHERE role IN ('teacher', 'admin') AND id != $user_id ORDER BY role DESC, full_name");
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
            padding-bottom: 0;
        }
        .tabs-container {
            display: flex;
            gap: 0;
        }
        .main-tab {
            padding: 12px 30px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: var(--text-medium);
            transition: all 0.3s;
            position: relative;
        }
        .main-tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }
        .main-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-color);
        }
        .main-tab:hover {
            color: var(--primary-color);
        }
        .action-buttons {
            padding-bottom: 2px;
        }
        .btn-refresh {
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
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25);
        }
        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
            background: linear-gradient(135deg, #5d73e1 0%, #6a4392 100%);
        }
        .btn-refresh i {
            font-size: 16px;
        }
        .main-tab-content { display: none; }
        .main-tab-content.active { display: block; }

        .filter-section {
            background: var(--bg-white);
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.08);
        }
        .filter-group { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-group select {
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-weight: 500;
            color: var(--text-dark);
            transition: all 0.3s;
        }
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        /* Announcement Card Styles - Enhanced */
        .announcement-card {
            background: var(--bg-white);
            border-radius: 12px;
            padding: 25px;
            margin: 15px 0;
            box-shadow: 0 2px 12px rgba(74, 144, 226, 0.1);
            border-left: 5px solid var(--primary-color);
            transition: all 0.3s;
            cursor: pointer;
        }
        .announcement-card:hover {
            box-shadow: 0 8px 24px rgba(74, 144, 226, 0.15);
            transform: translateY(-3px);
        }
        .announcement-card.urgent {
            border-left-color: var(--danger-color);
        }
        .announcement-card.event {
            border-left-color: var(--purple-color);
        }
        .announcement-card.unread {
            background: linear-gradient(to right, #E8F4FD 0%, white 100%);
            border-left-width: 6px;
            box-shadow: 0 2px 12px rgba(74, 144, 226, 0.2);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .announcement-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0 0 12px 0;
            line-height: 1.4;
        }
        .announcement-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-custom {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-academic {
            background: #E3F2FD;
            color: #1976D2;
            border: 1px solid #BBDEFB;
        }
        .badge-event {
            background: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
        }
        .badge-generalnotice {
            background: #E8F5E9;
            color: #388E3C;
            border: 1px solid #C8E6C9;
        }
        .badge-administrative {
            background: #FFF3E0;
            color: #F57C00;
            border: 1px solid #FFE0B2;
        }
        .badge-reminder {
            background: #FCE4EC;
            color: #C2185B;
            border: 1px solid #F8BBD0;
        }
        .badge-urgent {
            background: #FFEBEE;
            color: #C62828;
            font-weight: 700;
            border: 1px solid #FFCDD2;
            animation: pulse 2s infinite;
        }
        .badge-general {
            background: #F5F5F5;
            color: #666;
            border: 1px solid #E0E0E0;
        }
        .badge-response-required {
            background: #FFF3E0;
            color: #E65100;
            border: 1px solid #FFE0B2;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .announcement-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 15px 0;
            padding: 15px;
            background: linear-gradient(135deg, #F8FAFB 0%, #FFFFFF 100%);
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-medium);
            font-weight: 500;
        }
        .meta-item strong {
            color: var(--text-dark);
            font-weight: 600;
        }
        .meta-item i {
            color: var(--primary-color);
            font-size: 16px;
        }
        .meta-icon {
            font-size: 16px;
        }

        .announcement-content {
            color: var(--text-medium);
            line-height: 1.8;
            margin: 15px 0;
            font-size: 15px;
        }

        /* Announcement Footer */
        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            margin-top: 15px;
        }

        .author-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.3);
        }

        .author-details {
            font-size: 13px;
        }

        .author-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .author-role {
            color: var(--text-light);
            font-weight: 500;
        }

        .btn-respond {
            background: var(--secondary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-respond:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(80, 200, 120, 0.4);
        }

        .response-form {
            background: linear-gradient(135deg, #E8F8F0 0%, #F0FDF4 100%);
            padding: 25px;
            margin-top: 15px;
            border-radius: 12px;
            border: 2px solid var(--secondary-color);
            box-shadow: 0 4px 12px rgba(80, 200, 120, 0.1);
        }
        .response-form h4 {
            margin: 0 0 18px 0;
            color: var(--secondary-color);
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .response-badge {
            background: linear-gradient(135deg, #D4EDDA 0%, #C3E6CB 100%);
            color: #155724;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            border: 1px solid #B1DFBB;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(74, 144, 226, 0.4);
        }

        .message-list {
            background: var(--bg-white);
            border-radius: 12px;
            padding: 10px;
        }
        .message-item {
            padding: 18px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .message-item:hover {
            background: linear-gradient(135deg, #F8FAFB 0%, #FFFFFF 100%);
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.1);
        }
        .message-item.unread {
            background: linear-gradient(135deg, #E8F4FD 0%, #F0F8FF 100%);
            font-weight: 600;
            border-left: 4px solid var(--primary-color);
        }
        .message-item h5 {
            margin: 0 0 8px 0;
            color: var(--text-dark);
            font-weight: 600;
        }
        .message-item p {
            margin: 0;
            font-size: 14px;
            color: var(--text-medium);
            line-height: 1.6;
        }
        .message-item small {
            color: var(--text-light);
            font-weight: 500;
        }
        .message-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .message-actions {
            display: flex;
            gap: 10px;
        }
        .unread-badge {
            display: inline-block;
            background: var(--accent-gradient);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(255, 107, 107, 0.3);
        }

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
            gap: 12px;
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

        .tab i {
            font-size: 16px;
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

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Message Filters */
        .message-filters {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            background: var(--bg-white);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(74, 144, 226, 0.08);
            border: 2px solid var(--border-color);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-dark);
            margin: 0;
        }

        .filter-select {
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: var(--bg-white);
            cursor: pointer;
            transition: all 0.3s;
            min-width: 160px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 16px 10px 42px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 16px;
        }

        .view-toggle {
            display: flex;
            gap: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.1);
        }

        .view-toggle button {
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            background: var(--bg-white);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
            color: var(--text-medium);
        }

        .view-toggle button:first-child {
            border-radius: 8px 0 0 8px;
        }

        .view-toggle button:last-child {
            border-radius: 0 8px 8px 0;
        }

        .view-toggle button.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.4);
        }

        .view-toggle button:hover:not(.active) {
            background: linear-gradient(135deg, #E8F4FD 0%, #FFFFFF 100%);
            border-color: var(--primary-color);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Message Modal Styles */
        .message-modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(74, 144, 226, 0.25);
        }
        .message-modal-header {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 28px 35px;
        }
        .message-modal-header .modal-title {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .message-modal-body {
            padding: 35px;
            background: var(--bg-light);
        }
        .message-modal-footer {
            background: var(--bg-white);
            border-top: 2px solid var(--border-color);
            padding: 22px 35px;
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
        }
        .btn-compose-message:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5d73e1 0%, #6a4392 100%);
        }

        .message-actions {
            display: flex;
            gap: 10px;
            align-items: center;
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

        /* Search Section */
        .search-section {
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-input-wrapper {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
        }
        .search-input-wrapper i.bi-search {
            position: absolute;
            left: 15px;
            color: #999;
            font-size: 18px;
        }
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            color: #dc3545;
        }
        .btn-search {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Quick Filters */
        .quick-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .quick-filters-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 10px 20px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #666;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            position: relative;
            white-space: nowrap;
        }
        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
            text-decoration: none;
        }
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        .filter-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }
        .filter-btn.active .filter-badge {
            background: white;
            color: #667eea;
        }
        .btn-mark-all-read {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.25);
        }
        .btn-mark-all-read:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.35);
            background: linear-gradient(135deg, #229c3e 0%, #1db38a 100%);
        }

        /* Filter Section Updates */
        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filter-select {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-mark-all-read, .btn-refresh {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-mark-all-read {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .btn-mark-all-read:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }
        .btn-refresh {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.4);
        }

        /* Announcement Stats */
        .announcement-stats {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .search-indicator {
            background: #fff3cd;
            padding: 4px 12px;
            border-radius: 12px;
            color: #856404;
            font-weight: 600;
            margin-left: 10px;
        }

        /* Unread Announcement Styles */
        .announcement-card.unread {
            background: linear-gradient(to right, #f8f9ff 0%, white 100%);
            border-left-width: 6px;
        }
        .unread-indicator {
            color: #667eea;
            font-size: 12px;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
            padding: 20px;
        }
        .pagination-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .pagination-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .pagination-numbers {
            display: flex;
            gap: 5px;
        }
        .pagination-number {
            padding: 8px 14px;
            background: white;
            border: 2px solid #e0e0e0;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .pagination-number:hover {
            border-color: #667eea;
            color: #667eea;
        }
        .pagination-number.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
        }
        .pagination-ellipsis {
            padding: 8px 14px;
            color: #999;
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
            .btn-compose-message { width: 100%; justify-content: center; }
            .form-row { grid-template-columns: 1fr; }
            .search-form {
                flex-direction: column;
            }
            .btn-search {
                width: 100%;
            }
            .quick-filters {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }
            .action-buttons {
                width: 100%;
                flex-direction: column;
            }
            .btn-mark-all-read, .btn-refresh {
                width: 100%;
                justify-content: center;
            }
            .pagination {
                flex-wrap: wrap;
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
                <li><a href="lessons.php">Browse Lessons</a></li>
                <li><a href="assignments.php">Assignments</a></li>
                <li>
                    <a href="announcements_messages.php" class="active">
                        Announcements & Messages
                        <?php if ($unread_announcements_count > 0 || $unread_count > 0): ?>
                            <span class="unread-badge"><?php echo ($unread_announcements_count + $unread_count); ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($unread_announcements_count > 0 || $need_response_count > 0): ?>
                        <div style="font-size: 11px; color: #999; margin-left: 20px; margin-top: 5px;">
                            <?php if ($unread_announcements_count > 0): ?>
                                <span style="color: #667eea;">● <?php echo $unread_announcements_count; ?> Unread</span>
                            <?php endif; ?>
                            <?php if ($need_response_count > 0): ?>
                                <span style="color: #ffc107; margin-left: 10px;">● <?php echo $need_response_count; ?> Need Response</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
                <li><a href="submissions.php">My Submissions</a></li>
                <li><a href="profile.php">Profile Settings</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="topbar">
                <h1>Announcements & Messages</h1>
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
            
            <div class="card">
                <!-- MAIN TABS: Announcements vs Messages -->
                <div class="main-tabs">
                    <div class="tabs-container">
                        <div class="main-tab <?php echo $current_tab === 'announcements' ? 'active' : ''; ?>" onclick="switchMainTab('announcements', event)">
                            📢 Announcements
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
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($quick_filter); ?>">

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
                                </select>
                            </form>
                        </div>
                        <!-- Search Bar -->
                        <form method="GET" class="search-form">
                            <input type="hidden" name="tab" value="announcements">
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($quick_filter); ?>">
                            <div class="search-input-wrapper">
                                <i class="bi bi-search"></i>
                                <input type="text" name="search" placeholder="Search announcements by title or content..."
                                       value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
                                <?php if ($search_query): ?>
                                    <a href="?tab=announcements" class="search-clear" title="Clear search">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </a>
                                <?php endif; ?>
                            </div>                        </form>
                    </div>

                    <div class="card-header">
                        <h3>📢 Active Announcements</h3>
                        <div class="announcement-stats">
                            Showing <?php echo $announcements->num_rows; ?> of <?php echo $total_announcements; ?> announcements
                            <?php if ($search_query): ?>
                                <span class="search-indicator">
                                    <i class="bi bi-search"></i> Search: "<?php echo htmlspecialchars($search_query); ?>"
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Filters -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
                            <div class="quick-filters-container" style="display: flex; gap: 10px;">
                                <a href="?tab=announcements&filter=" class="filter-btn <?php echo $quick_filter === '' ? 'active' : ''; ?>">
                                    <i class="bi bi-grid-3x3-gap"></i> All
                                </a>
                                <a href="?tab=announcements&filter=unread" class="filter-btn <?php echo $quick_filter === 'unread' ? 'active' : ''; ?>">
                                    <i class="bi bi-envelope"></i> Unread
                                    <?php if ($unread_announcements_count > 0): ?>
                                        <span class="filter-badge"><?php echo $unread_announcements_count; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="?tab=announcements&filter=urgent" class="filter-btn <?php echo $quick_filter === 'urgent' ? 'active' : ''; ?>">
                                    <i class="bi bi-exclamation-triangle"></i> Urgent
                                </a>
                                <a href="?tab=announcements&filter=need_response" class="filter-btn <?php echo $quick_filter === 'need_response' ? 'active' : ''; ?>">
                                    <i class="bi bi-chat-left-dots"></i> Need Response
                                    <?php if ($need_response_count > 0): ?>
                                        <span class="filter-badge"><?php echo $need_response_count; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="action-buttons" style="display: flex; gap: 10px;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_all_read">
                                    <button type="submit" class="btn-mark-all-read" title="Mark all announcements as read">
                                        <i class="bi bi-check-all"></i> Mark All Read
                                    </button>
                                </form>
                            </div>
                        </div>                    <?php if ($announcements->num_rows > 0): ?>
                        <?php while ($ann = $announcements->fetch_assoc()): ?>
                            <div class="announcement-card <?php echo $ann['type']; ?> <?php echo $ann['is_read'] == 0 ? 'unread' : ''; ?>"
                                 data-announcement-id="<?php echo $ann['id']; ?>">

                                <!-- Badges Section -->
                                <div class="announcement-badges">
                                    <?php if ($ann['is_read'] == 0): ?>
                                        <span class="badge-custom" style="background: #667eea; color: white;">
                                            <i class="bi bi-envelope"></i> UNREAD
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge-custom badge-<?php echo strtolower(str_replace(' ', '', $ann['category'])); ?>">
                                        <?php echo strtoupper($ann['category']); ?>
                                    </span>
                                    <?php if ($ann['type'] === 'urgent'): ?>
                                        <span class="badge-custom" style="background: #ffebee; color: #c62828;">
                                            <i class="bi bi-exclamation-triangle"></i> URGENT
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($ann['my_response']): ?>
                                        <span class="badge-custom" style="background: #e8f5e9; color: #2e7d32;">
                                            <i class="bi bi-check-circle"></i> Responded
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-custom badge-response-required">
                                            Response Required
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
                                            <div class="author-role">Target: All Students</div>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <button class="btn-respond" onclick="toggleResponseForm(<?php echo $ann['id']; ?>); markAsRead(<?php echo $ann['id']; ?>);">
                                            <i class="bi bi-chat-left-text"></i> Respond
                                        </button>
                                    </div>
                                </div>


                                <!-- Response Form (Hidden by default, shown on button click) -->
                                <div class="response-form" id="response-form-<?php echo $ann['id']; ?>" style="display: none; margin-top: 20px;">
                                    <h4><i class="bi bi-chat-left-text"></i> Respond to this Announcement</h4>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="respond">
                                        <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">

                                        <div class="form-group">
                                            <label><i class="bi bi-check-circle"></i> Response Type:</label>
                                            <select name="response_type" required>
                                                <option value="">-- Select Response --</option>
                                                <option value="attending" <?php echo ($ann['my_response'] === 'attending') ? 'selected' : ''; ?>>✅ Attending</option>
                                                <option value="not_attending" <?php echo ($ann['my_response'] === 'not_attending') ? 'selected' : ''; ?>>❌ Not Attending</option>
                                                <option value="maybe" <?php echo ($ann['my_response'] === 'maybe') ? 'selected' : ''; ?>>🤔 Maybe</option>
                                                <option value="acknowledged" <?php echo ($ann['my_response'] === 'acknowledged') ? 'selected' : ''; ?>>👍 Acknowledged</option>
                                                <option value="comment" <?php echo ($ann['my_response'] === 'comment') ? 'selected' : ''; ?>>💬 Comment</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label><i class="bi bi-chat-dots"></i> Additional Comments (Optional):</label>
                                            <textarea name="response_text" placeholder="Add any comments or questions..."></textarea>
                                        </div>

                                        <div style="display: flex; gap: 10px;">
                                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                                <i class="bi bi-send"></i> <?php echo $ann['my_response'] ? 'Update Response' : 'Submit Response'; ?>
                                            </button>
                                            <button type="button" class="btn" style="background: #6c757d; color: white; flex: 1;" onclick="toggleResponseForm(<?php echo $ann['id']; ?>)">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?tab=announcements&page=<?php echo ($page - 1); ?>&category=<?php echo urlencode($category_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&search=<?php echo urlencode($search_query); ?>&filter=<?php echo urlencode($quick_filter); ?>"
                                       class="pagination-btn">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>

                                <div class="pagination-numbers">
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);

                                    if ($start_page > 1): ?>
                                        <a href="?tab=announcements&page=1&category=<?php echo urlencode($category_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&search=<?php echo urlencode($search_query); ?>&filter=<?php echo urlencode($quick_filter); ?>"
                                           class="pagination-number">1</a>
                                        <?php if ($start_page > 2): ?>
                                            <span class="pagination-ellipsis">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <a href="?tab=announcements&page=<?php echo $i; ?>&category=<?php echo urlencode($category_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&search=<?php echo urlencode($search_query); ?>&filter=<?php echo urlencode($quick_filter); ?>"
                                           class="pagination-number <?php echo $i === $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <span class="pagination-ellipsis">...</span>
                                        <?php endif; ?>
                                        <a href="?tab=announcements&page=<?php echo $total_pages; ?>&category=<?php echo urlencode($category_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&search=<?php echo urlencode($search_query); ?>&filter=<?php echo urlencode($quick_filter); ?>"
                                           class="pagination-number"><?php echo $total_pages; ?></a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?tab=announcements&page=<?php echo ($page + 1); ?>&category=<?php echo urlencode($category_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&search=<?php echo urlencode($search_query); ?>&filter=<?php echo urlencode($quick_filter); ?>"
                                       class="pagination-btn">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="text-align: center; padding: 40px; color: #999;">
                            <?php if ($search_query): ?>
                                <i class="bi bi-search"></i> No announcements found matching "<?php echo htmlspecialchars($search_query); ?>"
                            <?php elseif ($quick_filter): ?>
                                <i class="bi bi-inbox"></i> No <?php echo htmlspecialchars($quick_filter); ?> announcements at this time.
                            <?php else: ?>
                                📢 No announcements available at this time.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($my_responses->num_rows > 0): ?>
                    <div style="margin-top: 30px;">
                        <div class="card-header">
                            <h3>My Responses</h3>
                        </div>
                        <?php while ($response = $my_responses->fetch_assoc()): ?>
                            <div class="announcement-item">
                                <h4><?php echo htmlspecialchars($response['title']); ?></h4>
                                <div class="announcement-meta">
                                    <span class="badge"><?php echo ucfirst($response['response_type']); ?></span>
                                    <span>Responded: <?php echo date('M d, Y H:i', strtotime($response['responded_at'])); ?></span>
                                </div>
                                <p><?php echo htmlspecialchars($response['response_text']); ?></p>
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
                            <button class="tab active" onclick="switchTab('inbox')">
                                <i class="bi bi-inbox-fill"></i> Inbox
                                <?php if ($unread_count > 0) echo '<span class="tab-badge">' . $unread_count . '</span>'; ?>
                            </button>
                            <button class="tab" onclick="switchTab('sent')">
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
                                                <button class="btn-view-message" onclick="viewMessage('<?php echo htmlspecialchars($msg['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($msg['subject'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($msg['message'], ENT_QUOTES); ?>', '<?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?>', 'From', <?php echo $msg['id']; ?>, <?php echo !$msg['is_read'] ? 'true' : 'false'; ?>)" title="View Full Message">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <button class="btn-reply-message" onclick="replyToMessage(<?php echo $msg['sender_id']; ?>, '<?php echo htmlspecialchars($msg['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($msg['subject'], ENT_QUOTES); ?>', <?php echo $msg['id']; ?>)" title="Reply to Message">
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
                                            <button class="btn-view-message" onclick="viewMessage('<?php echo htmlspecialchars($msg['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($msg['subject'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($msg['message'], ENT_QUOTES); ?>', '<?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?>', 'To')" title="View Full Message">
                                                <i class="bi bi-eye"></i> View
                                            </button>
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
                        <input type="hidden" name="action" value="send">
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

        function switchMainTab(tabName) {
            document.querySelectorAll('.main-tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.main-tab').forEach(el => el.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));

            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
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

        // Toggle response form
        function toggleResponseForm(announcementId) {
            const form = document.getElementById('response-form-' + announcementId);
            if (form) {
                if (form.style.display === 'none') {
                    form.style.display = 'block';
                } else {
                    form.style.display = 'none';
                }
            }
        }

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

        // Mark announcement as read
        function markAsRead(announcementId) {
            // Send AJAX request to mark as read
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_announcement_read&announcement_id=' + announcementId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove unread styling
                    const card = document.querySelector('[data-announcement-id="' + announcementId + '"]');
                    if (card) {
                        card.classList.remove('unread');
                        // Remove unread badge
                        const unreadBadge = card.querySelector('.badge-custom:has(.bi-envelope)');
                        if (unreadBadge) {
                            unreadBadge.remove();
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Refresh announcements
        function refreshAnnouncements() {
            const btn = document.querySelector('.btn-refresh');
            const icon = btn.querySelector('i');

            // Add spinning animation
            icon.style.animation = 'spin 1s linear infinite';

            // Reload the page
            setTimeout(() => {
                window.location.href = window.location.href;
            }, 500);
        }

        // Add spin animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>

