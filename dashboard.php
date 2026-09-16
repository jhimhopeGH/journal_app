<?php
// dashboard.php - Overview page
require_once __DIR__ . '/auth.php';
requireNavAccess('dashboard');

$currentPage = 'dashboard';

$totalEntries = (int)$pdo->query("SELECT COUNT(*) FROM entries")->fetchColumn();

$todayYMD  = date('Y-m-d');      // for legacy rows stored as YYYY-MM-DD
$todayMDY  = date('m/d/y');      // for new rows stored as MM/DD/YY
$todayStmt = $pdo->prepare("SELECT COUNT(*) FROM entries WHERE entry_date = :ymd OR entry_date = :mdy");
$todayStmt->execute([':ymd' => $todayYMD, ':mdy' => $todayMDY]);
$todayCount = (int)$todayStmt->fetchColumn();

$storeCount = (int)$pdo->query("SELECT COUNT(DISTINCT store_number) FROM entries")->fetchColumn();

$latest = $pdo->query("SELECT * FROM entries ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                <span>🛑</span>
                <span>Access Denied: That page requires Administrator privileges.</span>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Overview of your electronic journal activity.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalEntries); ?></div>
                <div class="stat-label">Total Entries</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($todayCount); ?></div>
                <div class="stat-label">Entries Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($storeCount); ?></div>
                <div class="stat-label">Stores Recorded</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Recent Entries</h2>
                <a href="records.php" class="btn btn-secondary">View All</a>
            </div>

            <?php if (empty($latest)): ?>
                <p class="empty-state">No entries yet. <a href="entry.php">Add your first entry</a>.</p>
            <?php else: ?>
                <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 14px;">
                <table style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Store</th>
                            <th>Register</th>
                            <th>Date</th>
                            <th>Z-Read</th>
                            <th>Till</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latest as $row): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['store_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['register_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['entry_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['zread_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['till_number']); ?></td>
                            <td><a class="btn" href="print.php?id=<?php echo (int)$row['id']; ?>" target="_blank" style="margin-top: 0; padding: 5px 14px; font-size: 12px; border-radius: 16px;">Reprint</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
