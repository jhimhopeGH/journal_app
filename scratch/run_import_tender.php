<?php
// Execute import_tender.py to import all AS400 tenders
$python = 'C:\\python32\\python.exe';
$script = realpath(__DIR__ . '/../scripts/import_tender.py');
$db     = realpath(__DIR__ . '/../journal.db');

$cmd = '"' . $python . '" "' . $script . '" "mms_as400" "' . $db . '" "1" "maindss" "windss" "MMLTSLIB" 2>&1';
$output = shell_exec($cmd);
echo "Output: " . $output . "\n\n";

require_once __DIR__ . '/../db.php';
$stmt = $pdo->query("SELECT COUNT(*) FROM tender_master");
echo "Total tenders in DB: " . $stmt->fetchColumn() . "\n";

$stmt2 = $pdo->query("SELECT * FROM tender_master ORDER BY id ASC LIMIT 10");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID: {$r['id']} | Code: {$r['tender_code']} | Name: {$r['tender_name']}\n";
}
