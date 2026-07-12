<?php
// admin.php - Basic system info / admin overview
require_once __DIR__ . '/db.php';
$currentPage = 'admin';

// Check if we are saving the layout (Removed, moved to print_layout.php)

$totalEntries = (int)$pdo->query("SELECT COUNT(*) FROM entries")->fetchColumn();
$dbFile = __DIR__ . '/journal.db';
$dbSizeKb = file_exists($dbFile) ? round(filesize($dbFile) / 1024, 1) : 0;
$firstEntry = $pdo->query("SELECT MIN(entry_date) FROM entries")->fetchColumn();
$lastEntry  = $pdo->query("SELECT MAX(entry_date) FROM entries")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Electronic Journal</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Admin</h1>
            <p>System information for this journal installation.</p>
        </div>



        <div class="panel" style="margin-bottom: 20px;">
            <h2>Database</h2>
            <div class="info-row"><span>Storage type</span><span>SQLite</span></div>
            <div class="info-row"><span>Database file</span><span><?php echo htmlspecialchars($dbFile); ?></span></div>
            <div class="info-row"><span>File size</span><span><?php echo $dbSizeKb; ?> KB</span></div>
            <div class="info-row"><span>Total entries</span><span><?php echo $totalEntries; ?></span></div>
            <div class="info-row"><span>Earliest entry date</span><span><?php echo htmlspecialchars($firstEntry ?: '—'); ?></span></div>
            <div class="info-row"><span>Latest entry date</span><span><?php echo htmlspecialchars($lastEntry ?: '—'); ?></span></div>
        </div>

        <div class="panel">
            <h2>Backing up your journal</h2>
            <p style="font-size: 14px; color: #444; line-height: 1.5;">
                All journal data lives in a single file: <code>journal.db</code>,
                inside the <code>journal_app</code> folder. To back it up, copy
                that file somewhere safe (e.g. a dated folder or external drive)
                while Apache is stopped, so nothing is being written to it at
                the same time.
            </p>
        </div>
    </main>
</div>
</body>
</html>
