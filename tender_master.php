<?php
// tender_master.php - Lists tender master records, supports search
require_once __DIR__ . '/db.php';
$currentPage = 'tender_master';

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$saved  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

$rows = getAllTenderMasters($search);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tender Master - Electronic Journal</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <a href="tender_master_entry.php">+ New Entry</a>
            <a href="tender_master.php">View Records</a>
        </div>

        <div class="page-header">
            <h1>Tender Master</h1>
            <p>Manage payment tenders accepted by the system.</p>
        </div>

        <?php if ($saved): ?>
            <div class="success">Tender saved successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div class="success">Tender deleted successfully.</div>
        <?php endif; ?>

        <div class="panel">
        <form class="search-bar" method="GET" action="tender_master.php">
            <input type="text" name="q" placeholder="Search by tender code or name..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
        </form>

        <table>
        <thead>
            <tr>
                <th style="width: 80px;">ID</th>
                <th style="width: 150px;">Tender Code</th>
                <th>Tender Name</th>
                <th style="width: 200px;">Updated</th>
                <th style="width: 120px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5">No tender records found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><strong style="color: #2d6cdf;"><?php echo htmlspecialchars($row['tender_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['tender_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                    <td style="white-space: nowrap;">
                        <a class="btn" style="padding: 4px 8px; font-size: 11px;" href="tender_master_entry.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                        <a class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; background: #dc3545;" href="tender_master_delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Are you sure you want to delete this tender?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        </table>
        </div>
    </main>
</div>
</body>
</html>
