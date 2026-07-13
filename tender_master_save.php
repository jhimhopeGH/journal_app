<?php
// tender_master_save.php - Validates and saves a tender master entry to SQLite
require_once __DIR__ . '/db.php';

function fieldValue($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

$errors = [];

$tender_code = fieldValue('tender_code');
$tender_name = fieldValue('tender_name');
$edit_id     = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

if ($tender_code === '') $errors[] = 'Tender Code is required.';
if ($tender_name === '') $errors[] = 'Tender Name is required.';

if (!empty($errors)) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <title>Error</title><link rel="stylesheet" href="style.css"></head><body>
          <div class="container">
            <h1>Could not save tender entry</h1>
            <div class="error"><ul>';
    foreach ($errors as $err) {
        echo '<li>' . htmlspecialchars($err) . '</li>';
    }
    echo '</ul></div>
            <a class="btn btn-secondary" href="javascript:history.back()">Go Back</a>
          </div></body></html>';
    exit;
}

$data = [
    'tender_code' => $tender_code,
    'tender_name' => $tender_name
];

if ($edit_id > 0) {
    // Update existing record
    $stmt = $pdo->prepare("
        UPDATE tender_master
        SET tender_code = :tender_code,
            tender_name = :tender_name,
            updated_at = datetime('now', 'localtime')
        WHERE id = :id
    ");
    $stmt->execute([
        ':tender_code' => $tender_code,
        ':tender_name' => $tender_name,
        ':id'          => $edit_id
    ]);
    $newId = $edit_id;
} else {
    // Insert new record
    $newId = saveTenderMaster($data);
}

header('Location: tender_master.php?saved=' . $newId);
exit;
