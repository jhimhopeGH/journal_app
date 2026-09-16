<?php
// store_master.php - Lists store master records, supports search
require_once __DIR__ . '/auth.php';
requireNavAccess('store_master');
require_once __DIR__ . '/db.php';
$currentPage = 'store_master';


$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$saved  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

$rows = getAllStoreMasters($search);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store Master - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <a href="store_master_entry.php?new=1">+ New Entry</a>
            <a href="store_master.php">View Records</a>
        </div>

        <div class="page-header">
            <h1>Store Master</h1>
            <p>Manage store information and settings.</p>
        </div>

        <?php if ($saved): ?>
            <div class="success">Store saved successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div class="success">Store deleted successfully.</div>
        <?php endif; ?>

        <div class="panel">
        <form class="search-bar" method="GET" action="store_master.php">
            <input type="text" name="q" placeholder="Search by store code, name, serial, MIN, or permit..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
        </form>

        <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 14px;">
        <table style="margin-top: 0; border: none;">
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th style="width: 110px;">Store Code</th>
                <th>Store Name</th>
                <th style="width: 160px;">Updated</th>
                <th style="width: 120px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5">No store records found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><strong style="color: #2d6cdf;"><?php echo htmlspecialchars($row['store_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                    <td style="white-space: nowrap;">
                        <a class="btn" style="padding: 4px 8px; font-size: 11px;" href="store_master_entry.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                        <a class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; background: #dc3545;" href="store_master_delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Are you sure you want to delete this store?');">Delete</a>
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
