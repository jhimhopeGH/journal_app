<?php
// Test import_tender_ajax.php functionality directly with AS400
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = []; // No overrides, use as400_config.json

ob_start();
require __DIR__ . '/../import_tender_ajax.php';
$resp = ob_get_clean();

echo "Import Response:\n" . $resp . "\n\n";

require_once __DIR__ . '/../db.php';
$stmt = $pdo->query("SELECT * FROM tender_master ORDER BY id ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total tenders in DB now: " . count($rows) . "\n";
echo "Sample tenders:\n";
foreach (array_slice($rows, 0, 20) as $r) {
    echo " - ID: {$r['id']}, Code: '{$r['tender_code']}', Name: '{$r['tender_name']}'\n";
}
