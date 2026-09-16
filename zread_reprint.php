<?php
// zread_reprint.php - Search and reprint entries by Z-Read number
require_once __DIR__ . '/auth.php';
requireNavAccess('journal');
require_once __DIR__ . '/db.php';
$currentPage = 'journal';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM entries WHERE zread_number LIKE :q ORDER BY id DESC");
    $stmt->execute([':q' => '%' . $q . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM entries ORDER BY id DESC LIMIT 20");
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Zread Reprint - Electronic Journal</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Zread Reprint</h1>
            <p>Look up an entry by its Z-Read number and reprint it.</p>
        </div>

        <div class="panel">
            <form class="search-bar" method="GET" action="zread_reprint.php">
                <input type="text" name="q" placeholder="Enter Z-Read number..."
                       value="<?php echo htmlspecialchars($q); ?>">
                <button type="submit">Search</button>
            </form>

            <?php if (empty($rows)): ?>
                <p class="empty-state">No entries found<?php echo $q !== '' ? ' for that Z-Read number' : ''; ?>.</p>
            <?php else: ?>
                <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 14px;">
                <table style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Z-Read No.</th>
                            <th>Store</th>
                            <th>Register</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['zread_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['store_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['register_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['entry_date']); ?></td>
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
