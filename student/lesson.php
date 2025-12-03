<?php
require_once '../config/session.php';
checkRole('student');
require_once '../config/database.php';

// Get filters
$search        = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter_type   = isset($_GET['type']) ? trim($_GET['type']) : '';

// BASE QUERY FOR LIST
$query = "
    SELECT l.*, u.full_name AS teacher_name 
    FROM lessons l
    JOIN users u ON l.teacher_id = u.id
    WHERE 1
";

// Search
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $query .= " AND (l.title LIKE '%$safe%' OR l.description LIKE '%$safe%' OR u.full_name LIKE '%$safe%')";
}

// Filter by Type (file extension)
if ($filter_type !== '') {
    if ($filter_type === "video") {
        $query .= " AND (l.content LIKE '%.mp4' OR l.content LIKE '%.mov' OR l.content LIKE '%.avi' OR l.content LIKE '%.mkv' OR l.content LIKE '%.webm')";
    } elseif ($filter_type === "document") {
        $query .= " AND (l.content LIKE '%.pdf' OR l.content LIKE '%.doc%' OR l.content LIKE '%.ppt%')";
    }
}

$query .= " ORDER BY l.created_at DESC";
$lessons = $conn->query($query);

// SINGLE LESSON VIEW (WHEN ?view=ID)
$viewLesson = null;
if (isset($_GET['view'])) {
    $viewId = intval($_GET['view']);
    $res = $conn->query("
        SELECT l.*, u.full_name AS teacher_name 
        FROM lessons l
        JOIN users u ON l.teacher_id = u.id
        WHERE l.id = $viewId
        LIMIT 1
    ");
    if ($res && $res->num_rows === 1) {
        $viewLesson = $res->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lesson Materials</title>
<link rel="stylesheet" href="../assets/css/style.css">

<style>
.main-title {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 5px;
}
.subtitle {
    color: #6b7280;
    margin-bottom: 20px;
}

/* Detail view card */
.lesson-detail {
    background: #ffffff;
    border-radius: 18px;
    padding: 24px 30px;
    margin-bottom: 26px;
    box-shadow: 0 8px 24px rgba(15,23,42,0.08);
}
.lesson-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
}
.lesson-detail-title {
    font-size: 24px;
    font-weight: 600;
    margin: 0 0 6px;
}
.lesson-detail-meta {
    font-size: 13px;
    color: #6b7280;
}
.badge-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    font-size: 12px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    margin-left: 6px;
}
.btn-back {
    font-size: 13px;
    text-decoration: none;
    color: #4b5563;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
}
.btn-back:hover {
    background: #e5e7eb;
}
.lesson-detail-body {
    display: grid;
    grid-template-columns: minmax(0, 2.1fr) minmax(0, 1.6fr);
    gap: 24px;
}
@media (max-width: 900px) {
    .lesson-detail-body {
        grid-template-columns: 1fr;
    }
}
.lesson-detail-description {
    font-size: 14px;
    color: #374151;
    line-height: 1.6;
    white-space: pre-wrap;
}
.lesson-media {
    background: #f9fafb;
    border-radius: 14px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.lesson-media-title {
    font-size: 13px;
    font-weight: 500;
    color: #4b5563;
}
.lesson-media video,
.lesson-media iframe {
    width: 100%;
    max-height: 420px;
    border-radius: 12px;
    border: none;
    background: #000;
}
.attachment-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    padding: 8px 14px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #0369a1;
    text-decoration: none;
}
.attachment-link:hover {
    background: #bae6fd;
}

/* Search / filters + cards */
.filter-box {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.search-bar {
    flex: 1 1 230px;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #ddd;
}
.filter-box select {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #ddd;
}
.lesson-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.lesson-card {
    background: white;
    padding: 18px 18px 16px;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(15,23,42,0.06);
    transition: 0.18s;
}
.lesson-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(15,23,42,0.14);
}
.lesson-type {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: #3b82f6;
    margin-bottom: 6px;
}
.lesson-title {
    font-size: 17px;
    font-weight: 600;
    margin: 0 0 6px;
}
.lesson-desc {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 10px;
    min-height: 40px;
}
.lesson-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
}
.lesson-teacher {
    color: #4b5563;
}
.btn-view {
    padding: 6px 12px;
    background: #4f46e5;
    color: #fff;
    border-radius: 999px;
    text-decoration: none;
    font-size: 13px;
}
.btn-view:hover {
    background: #4338ca;
}

/* small spacing under topbar */
.page-header {
    margin-top: 20px;
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
            <li><a href="lesson.php" class="active">Lesson</a></li>
            <li><a href="assignments.php">Assignment</a></li>
            <li><a href="submissions.php">Submissions</a></li>
            <li><a href="announcements_messages.php">Announcement</a></li>
            <li><a href="profile.php">Profile Settings</a></li>
            <li><a href="../logout.php">Logout</a></li>
    </aside>

    <!-- Main -->
    <main class="main-content">

        <!-- Topbar to match dashboard.php -->
        <div class="topbar">
            <h1>Lessons</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <span><?php echo $_SESSION['full_name']; ?></span>
            </div>
        </div>

        <!-- Page heading under the topbar -->
        <div class="page-header">
            <p class="subtitle">Browse, open, and study lesson materials shared by your teachers.</p>
        </div>

        <!-- DETAIL VIEW (when ?view=ID) -->
        <?php if ($viewLesson): ?>
            <?php
                $fileUrl = '';
                $ext = '';
                if (!empty($viewLesson['file_path'])) {
                    $fileUrl = '../uploads/' . htmlspecialchars($viewLesson['file_path']);
                    $ext = strtolower(pathinfo($viewLesson['file_path'], PATHINFO_EXTENSION));
                }
            ?>
            <section class="lesson-detail">
                <div class="lesson-detail-header">
                    <div>
                        <h2 class="lesson-detail-title">
                            <?php echo htmlspecialchars($viewLesson['title']); ?>
                        </h2>
                        <div class="lesson-detail-meta">
                            <?php echo htmlspecialchars($viewLesson['teacher_name']); ?>
                            · <?php echo date('M d, Y', strtotime($viewLesson['created_at'])); ?>
                            <?php if (!empty($viewLesson['category'])): ?>
                                <span class="badge-pill">
                                    <?php echo htmlspecialchars($viewLesson['category']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="lesson.php" class="btn-back">Back to all lessons</a>
                </div>

                <div class="lesson-detail-body">
                    <div class="lesson-detail-description">
                        <?php
                        $desc = trim($viewLesson['description']);
                        echo $desc === ''
                            ? 'No description provided.'
                            : nl2br(htmlspecialchars($desc));
                        ?>
                    </div>

                    <?php if ($fileUrl): ?>
                        <div class="lesson-media">
                            <div class="lesson-media-title">Attached Material</div>

                            <?php if (in_array($ext, ['mp4','mov','webm','mkv','avi'])): ?>
                                <video controls>
                                    <source src="<?php echo $fileUrl; ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php elseif ($ext === 'pdf'): ?>
                                <iframe src="<?php echo $fileUrl; ?>"></iframe>
                            <?php else: ?>
                                <a href="<?php echo $fileUrl; ?>" target="_blank" class="attachment-link">
                                    Open attachment
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        <?php else: ?>
            <!-- ONLY SHOW THESE WHEN *NOT* VIEWING A SINGLE LESSON -->

            <!-- Search + Filters -->
            <form method="GET" class="filter-box">
                <input type="text" name="q" class="search-bar" placeholder="Search lessons..."
                       value="<?php echo htmlspecialchars($search); ?>">

                <select name="type">
                    <option value="">Filter by Type</option>
                    <option value="video" <?php if ($filter_type=="video") echo "selected"; ?>>Video</option>
                    <option value="document" <?php if ($filter_type=="document") echo "selected"; ?>>Document</option>
                </select>

                <button style="display:none;"></button>
            </form>

            <!-- Lesson Cards Grid -->
            <div class="lesson-grid">
                <?php if ($lessons->num_rows > 0): ?>
                    <?php while ($lesson = $lessons->fetch_assoc()): ?>
                        <?php
                            $type_label = "Material";
                            if ($lesson['file_path']) {
                                $extCard = strtolower(pathinfo($lesson['file_path'], PATHINFO_EXTENSION));
                                if (in_array($extCard, ['mp4','mov','avi','mkv','webm'])) {
                                    $type_label = "Video";
                                } elseif (in_array($extCard, ['pdf','ppt','pptx','doc','docx'])) {
                                    $type_label = "Document";
                                }
                            }

                            $desc = trim($lesson['description']);
                            if ($desc === '') {
                                $shortDesc = 'No description.';
                            } else {
                                $shortDesc = mb_substr($desc, 0, 80);
                                if (mb_strlen($desc) > 80) {
                                    $shortDesc .= '...';
                                }
                            }
                        ?>
                        <div class="lesson-card">
                            <div class="lesson-type"><?php echo $type_label; ?></div>

                            <h3 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h3>

                            <p class="lesson-desc">
                                <?php echo htmlspecialchars($shortDesc); ?>
                            </p>

                            <div class="lesson-footer">
                                <span class="lesson-teacher">
                                    <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                                </span>

                                <a href="lesson.php?view=<?php echo $lesson['id']; ?>" class="btn-view">
                                    View
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#6b7280;">No lessons found.</p>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </main>
</div>
</body>
</html>

<?php $conn->close(); ?>
