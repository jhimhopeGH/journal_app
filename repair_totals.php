<?php
// repair_totals.php - Recalculates daily_sales and old_grand_total for all entries
// or for a specific store if ?store=XXXXX is passed
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();
require_once __DIR__ . '/db.php';

$store = isset($_GET['store']) ? trim($_GET['store']) : '';

$updated = 0;

// Step 1: Fix daily_sales = 0 where vat/nonvat data exists
if ($store !== '') {
    $stmt = $pdo->prepare("UPDATE entries SET daily_sales = total_vat + total_non_vat WHERE store_number = ? AND daily_sales = 0 AND (total_vat > 0 OR total_non_vat > 0)");
    $stmt->execute([$store]);
} else {
    $pdo->exec("UPDATE entries SET daily_sales = total_vat + total_non_vat WHERE daily_sales = 0 AND (total_vat > 0 OR total_non_vat > 0)");
}
$step1 = $pdo->query("SELECT changes()")->fetchColumn();
$updated += (int)$step1;

// Step 2: Fix old_grand_total = 0 where zread > 1
// Fetch all affected rows ordered by store, register, zread
if ($store !== '') {
    $stmt = $pdo->prepare("
        SELECT id, store_number, register_number, CAST(zread_number AS INTEGER) AS zread_int
        FROM entries
        WHERE store_number = ?
          AND old_grand_total = 0
          AND CAST(zread_number AS INTEGER) > 1
        ORDER BY store_number, register_number, zread_int
    ");
    $stmt->execute([$store]);
} else {
    $stmt = $pdo->query("
        SELECT id, store_number, register_number, CAST(zread_number AS INTEGER) AS zread_int
        FROM entries
        WHERE old_grand_total = 0
          AND CAST(zread_number AS INTEGER) > 1
        ORDER BY store_number, register_number, zread_int
    ");
}
$affected = $stmt->fetchAll(PDO::FETCH_ASSOC);

$prevStmt = $pdo->prepare("
    SELECT new_grand_total
    FROM entries
    WHERE store_number = ?
      AND register_number = ?
      AND CAST(zread_number AS INTEGER) < ?
    ORDER BY CAST(zread_number AS INTEGER) DESC
    LIMIT 1
");
$entryStmt = $pdo->prepare("SELECT daily_sales, new_grand_total FROM entries WHERE id = ?");
$updateStmt = $pdo->prepare("UPDATE entries SET old_grand_total = ?, new_grand_total = ? WHERE id = ?");

$step2 = 0;
foreach ($affected as $row) {
    $prevStmt->execute([$row['store_number'], $row['register_number'], $row['zread_int']]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
    if ($prev && (float)$prev['new_grand_total'] > 0) {
        $prevNewGt = (float)$prev['new_grand_total'];
        $entryStmt->execute([$row['id']]);
        $entry = $entryStmt->fetch(PDO::FETCH_ASSOC);
        if ($entry) {
            $daily  = (float)$entry['daily_sales'];
            $newGt  = (float)$entry['new_grand_total'] > 0 ? (float)$entry['new_grand_total'] : $prevNewGt + $daily;
            $updateStmt->execute([$prevNewGt, $newGt, $row['id']]);
            $step2 += $pdo->query("SELECT changes()")->fetchColumn();
        }
    }
}
$updated += $step2;

echo json_encode([
    'status'         => 'success',
    'daily_sales_fixed'    => (int)$step1,
    'old_grand_total_fixed' => $step2,
    'total_repaired' => $updated,
    'store'          => $store ?: 'all',
]);
