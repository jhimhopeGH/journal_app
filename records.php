<?php
// records.php - Lists saved entries, supports simple search, links to reprint
require_once __DIR__ . '/db.php';
$currentPage = 'journal';

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$saved  = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM entries
        WHERE store_number LIKE :q
           OR transaction_number LIKE :q
           OR entry_date LIKE :q
           OR zread_number LIKE :q
        ORDER BY id DESC
    ");
    $stmt->execute([':q' => '%' . $search . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM entries ORDER BY id DESC");
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Records</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <a href="entry.php">+ New Entry</a>
            <a href="records.php">View Records</a>
        </div>

        <div class="page-header">
            <h1>Journal Records</h1>
            <p>Search and reprint saved entries.</p>
        </div>

        <?php if ($saved): ?>
            <div class="success">Entry #<?php echo $saved; ?> saved successfully.</div>
        <?php endif; ?>

        <div class="panel">
        <form class="search-bar" method="GET" action="records.php">
            <input type="text" name="q" placeholder="Search by store, transaction, date, or Z-read..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
        </form>

        <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Store</th>
                <th>Register</th>
                <th>Transaction</th>
                <th>Date</th>
                <th>Z-Read</th>
                <th>Till</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8">No entries found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['store_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['register_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['transaction_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['entry_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['zread_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['till_number']); ?></td>
                    <td><a class="btn" href="print.php?id=<?php echo (int)$row['id']; ?>" target="_blank">Reprint</a></td>
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
