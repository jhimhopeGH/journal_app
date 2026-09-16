<?php
require_once __DIR__ . '/../ej_db.php';

$stmt = $ejPdo->query("SELECT id, store_code, register_number, transaction_number, invoice_number, items FROM ej_entries ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Sample ej_entries items ===\n";
foreach ($rows as $r) {
    echo "ID: {$r['id']}, Store: {$r['store_code']}, Reg: {$r['register_number']}, Trx: {$r['transaction_number']}, Inv: {$r['invoice_number']}\n";
    echo "Items JSON: {$r['items']}\n\n";
}
