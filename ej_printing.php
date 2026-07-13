<?php
// ej_printing.php - Browse the full journal log by date range for reprinting
require_once __DIR__ . '/db.php';
$currentPage = 'ej';

$today = date('Y-m-d');
$from = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : $today;
$to   = isset($_GET['to'])   && $_GET['to']   !== '' ? $_GET['to']   : $today;

$stmt = $pdo->prepare("
    SELECT * FROM entries
    WHERE CASE
        WHEN entry_date LIKE '__/__/__' THEN
            substr(entry_date,7,2) || '-' || substr(entry_date,1,2) || '-' || substr(entry_date,4,2)
        ELSE entry_date
    END BETWEEN :from AND :to
    ORDER BY CASE
        WHEN entry_date LIKE '__/__/__' THEN
            substr(entry_date,7,2) || '-' || substr(entry_date,1,2) || '-' || substr(entry_date,4,2)
        ELSE entry_date
    END ASC, id ASC
");
$stmt->execute([':from' => $from, ':to' => $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EJ Printing - Electronic Journal</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>EJ Printing</h1>
            <p>Browse the electronic journal log by date range and reprint any entry.</p>
        </div>

        <div class="panel">
            <form class="search-bar" method="GET" action="ej_printing.php">
                <label style="margin:0; font-weight: normal; font-size: 13px; color:#555;">
                    From
                    <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
                </label>
                <label style="margin:0; font-weight: normal; font-size: 13px; color:#555;">
                    To
                    <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
                </label>
                <button type="submit">Filter</button>
            </form>

            <?php if (empty($rows)): ?>
                <p class="empty-state">No journal entries between <?php echo htmlspecialchars($from); ?> and <?php echo htmlspecialchars($to); ?>.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Store</th>
                            <th>Register</th>
                            <th>Transaction</th>
                            <th>Z-Read</th>
                            <th>Till</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['entry_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['store_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['register_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['transaction_number']); ?></td>
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
