<?php
// dashboard.php - Overview page
require_once __DIR__ . '/db.php';
$currentPage = 'dashboard';

$totalEntries = (int)$pdo->query("SELECT COUNT(*) FROM entries")->fetchColumn();

$todayStmt = $pdo->prepare("SELECT COUNT(*) FROM entries WHERE entry_date = :today");
$todayStmt->execute([':today' => date('Y-m-d')]);
$todayCount = (int)$todayStmt->fetchColumn();

$storeCount = (int)$pdo->query("SELECT COUNT(DISTINCT store_number) FROM entries")->fetchColumn();

$latest = $pdo->query("SELECT * FROM entries ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Electronic Journal</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Overview of your electronic journal activity.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalEntries; ?></div>
                <div class="stat-label">Total Entries</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $todayCount; ?></div>
                <div class="stat-label">Entries Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $storeCount; ?></div>
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
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Store</th>
                            <th>Transaction</th>
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
                            <td><?php echo htmlspecialchars($row['transaction_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['entry_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['zread_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['till_number']); ?></td>
                            <td><a class="btn" href="print.php?id=<?php echo (int)$row['id']; ?>" target="_blank">Reprint</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
