<?php
if (!isset($page_title)) $page_title = '';
?>
<div class="topbar">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <div class="user-info" onclick="toggleDropdown()">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
        <span><?php echo $_SESSION['full_name']; ?></span>
        <div class="user-dropdown" id="userDropdown">
            <a href="profile.php">👤 Profile Settings</a>
            <a href="../logout.php">🚪 Logout</a>
        </div>
    </div>
</div>
