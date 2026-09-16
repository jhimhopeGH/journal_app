<?php
// admin.php - System overview and database statistics
require_once __DIR__ . '/auth.php';
requireNavAccess('admin');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ej_db.php';
$currentPage = 'admin';


// Zread Database Info
$totalEntries = (int)$pdo->query("SELECT COUNT(*) FROM entries")->fetchColumn();
$dbFile = __DIR__ . '/journal.db';
$dbSizeKb = file_exists($dbFile) ? round(filesize($dbFile) / 1024, 1) : 0;
$firstEntry = $pdo->query("SELECT MIN(entry_date) FROM entries")->fetchColumn();
$lastEntry  = $pdo->query("SELECT MAX(entry_date) FROM entries")->fetchColumn();

// EJ Database Info
$totalEjEntries = (int)$ejPdo->query("SELECT COUNT(*) FROM ej_entries")->fetchColumn();
$ejDbFile = __DIR__ . '/ej.db';
$ejDbSizeKb = file_exists($ejDbFile) ? round(filesize($ejDbFile) / 1024, 1) : 0;
$firstEjEntry = $ejPdo->query("SELECT MIN(entry_date) FROM ej_entries")->fetchColumn();
$lastEjEntry  = $ejPdo->query("SELECT MAX(entry_date) FROM ej_entries")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Database Administration</h1>
            <p>System information and storage metrics for Z-Read and Electronic Journal databases.</p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
            <!-- Zread DB -->
            <div class="panel">
                <h2 style="color: #0284c7; margin-top: 0;">Zread Reprint (journal.db)</h2>
                <div class="info-row"><span>Storage type</span><span>SQLite</span></div>
                <div class="info-row"><span>Database file</span><span style="font-size: 11px; word-break: break-all;"><?php echo htmlspecialchars($dbFile); ?></span></div>
                <div class="info-row"><span>File size</span><span><?php echo $dbSizeKb; ?> KB</span></div>
                <div class="info-row"><span>Total entries</span><span><strong><?php echo number_format($totalEntries); ?></strong></span></div>
                <div class="info-row"><span>Earliest entry</span><span><?php echo htmlspecialchars($firstEntry ?: '—'); ?></span></div>
                <div class="info-row"><span>Latest entry</span><span><?php echo htmlspecialchars($lastEntry ?: '—'); ?></span></div>
            </div>

            <!-- EJ DB -->
            <div class="panel">
                <h2 style="color: #16a34a; margin-top: 0;">EJ Printing (ej.db)</h2>
                <div class="info-row"><span>Storage type</span><span>SQLite</span></div>
                <div class="info-row"><span>Database file</span><span style="font-size: 11px; word-break: break-all;"><?php echo htmlspecialchars($ejDbFile); ?></span></div>
                <div class="info-row"><span>File size</span><span><?php echo $ejDbSizeKb; ?> KB</span></div>
                <div class="info-row"><span>Total entries</span><span><strong><?php echo number_format($totalEjEntries); ?></strong></span></div>
                <div class="info-row"><span>Earliest entry</span><span><?php echo htmlspecialchars($firstEjEntry ?: '—'); ?></span></div>
                <div class="info-row"><span>Latest entry</span><span><?php echo htmlspecialchars($lastEjEntry ?: '—'); ?></span></div>
            </div>
        </div>

        <!-- Backup Management Panel -->
        <div class="panel" style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h2 style="margin: 0; color: #0f172a;">📦 Project & Database Backup</h2>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Create instant full-project ZIP archives containing all source files, SQLite databases, and layout configurations.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="backup_download.php?action=save" class="btn btn-secondary">💾 Create Server Backup</a>
                    <a href="backup_download.php?action=download" class="btn" style="background: #16a34a; font-weight: 700;">⬇️ Download Full Project ZIP</a>
                </div>
            </div>

            <?php if (isset($_GET['backup_created'])): ?>
                <div class="success" style="margin-bottom: 16px;">✓ Backup archive <strong><?php echo htmlspecialchars($_GET['backup_created']); ?></strong> created successfully in <code>/backups</code> folder!</div>
            <?php endif; ?>

            <?php
            $backupList = [];
            $bDir = __DIR__ . '/backups';
            if (is_dir($bDir)) {
                $bFiles = scandir($bDir);
                foreach ($bFiles as $bf) {
                    if ($bf !== '.' && $bf !== '..') {
                        $fullBf = $bDir . '/' . $bf;
                        $backupList[] = [
                            'name' => $bf,
                            'size' => round(filesize($fullBf) / (1024 * 1024), 2),
                            'date' => date('Y-m-d H:i:s', filemtime($fullBf))
                        ];
                    }
                }
                usort($backupList, function($a, $b) { return strcmp($b['date'], $a['date']); });
            }
            ?>

            <?php if (!empty($backupList)): ?>
                <div style="font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 8px;">Existing Server Snapshots:</div>
                <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 10px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="padding: 9px 12px;">Backup Filename</th>
                            <th style="padding: 9px 12px;">Size</th>
                            <th style="padding: 9px 12px;">Date Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backupList as $bk): ?>
                            <tr>
                                <td style="padding: 9px 12px; font-family: monospace; font-weight: 600; color: #0284c7;">📁 <?php echo htmlspecialchars($bk['name']); ?></td>
                                <td style="padding: 9px 12px;"><?php echo $bk['size']; ?> MB</td>
                                <td style="padding: 9px 12px; color: #64748b;"><?php echo htmlspecialchars($bk['date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div style="font-size: 13px; color: #64748b; font-style: italic;">No snapshots created yet. Click "Create Server Backup" or "Download Full Project ZIP" above to generate your first snapshot.</div>
            <?php endif; ?>
        </div>

        <!-- AS400 Integration Panel -->
        <div class="panel" style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0; color: #0f172a;">⚡ AS400 Integration (ODBC)</h2>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Manage IBM iSeries connection parameters, user credentials, and default library for automated SKU lookups and EJ imports.</p>
                </div>
                <div>
                    <a href="as400_config.php" class="btn" style="background: #0284c7; font-weight: 700; margin: 0;">⚙️ Configure AS400 Settings</a>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2>About Journal Data Isolation</h2>
            <p style="font-size: 14px; color: #444; line-height: 1.5;">
                All journal records live in isolated SQLite database files inside the <code>journal_app</code> directory:
                <strong><code>journal.db</code></strong> (Z-Read records) and <strong><code>ej.db</code></strong> (Electronic Journal records).
                When you create a backup, all database tables, layout configurations (<code>ej_print_settings.json</code>), templates, and master lists are packaged into an archive.
            </p>
        </div>
    </main>
</div>
</body>
</html>
