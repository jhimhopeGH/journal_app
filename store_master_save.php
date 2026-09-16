<?php
// store_master_save.php - Validates and saves a store master entry to SQLite
require_once __DIR__ . '/auth.php';
requireNavAccess('store_master');
require_once __DIR__ . '/db.php';

function fieldValue($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

$errors = [];

$store_code     = fieldValue('store_code');
$store_name     = fieldValue('store_name');
$header         = fieldValue('header');
$footer         = fieldValue('footer');
$accreditation  = fieldValue('accreditation');
$edit_id        = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

if ($store_code === '') $errors[] = 'Store Code is required.';
if ($store_name === '') $errors[] = 'Store Name is required.';

if (!empty($errors)) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <title>Error</title><link rel="stylesheet" href="style.css"></head><body>
          <div class="container">
            <h1>Could not save store entry</h1>
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
    'store_code'     => $store_code,
    'store_name'     => $store_name,
    'serial_number'  => '',
    'min_number'     => '',
    'permit_number'  => '',
    'header'         => $header,
    'footer'         => $footer,
    'accreditation'  => $accreditation
];

if ($edit_id > 0) {
    // Update existing record
    $stmt = $pdo->prepare("
        UPDATE store_master
        SET store_code = :store_code,
            store_name = :store_name,
            header = :header,
            footer = :footer,
            accreditation = :accreditation,
            updated_at = datetime('now', 'localtime')
        WHERE id = :id
    ");
    $stmt->execute([
        ':store_code'    => $store_code,
        ':store_name'    => $store_name,
        ':header'        => $header,
        ':footer'        => $footer,
        ':accreditation' => $accreditation,
        ':id'            => $edit_id
    ]);
    $newId = $edit_id;
    logActivity('edit', 'store_master', "Updated store #$edit_id: $store_code - $store_name");
} else {
    // Insert new record
    $newId = saveStoreMaster($data);
    logActivity('create', 'store_master', "Created store #$newId: $store_code - $store_name");
}

header('Location: store_master.php?saved=' . $newId);
exit;
