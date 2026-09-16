<?php
// register_master_save.php - Validates and saves a register master entry to SQLite
require_once __DIR__ . '/auth.php';
requireNavAccess('register_master');
require_once __DIR__ . '/db.php';

function fieldValue($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

$errors = [];

$store_code     = fieldValue('store_code');
$reg_no         = fieldValue('reg_no');
$serial_number  = fieldValue('serial_number');
$permit_number  = fieldValue('permit_number');
$min_number     = fieldValue('min_number');
$edit_id        = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

if ($store_code === '') $errors[] = 'Store Code is required.';
if ($reg_no === '')     $errors[] = 'Register Number (Reg No) is required.';

if (!empty($errors)) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <title>Error</title><link rel="stylesheet" href="style.css"></head><body>
          <div class="container">
            <h1>Could not save register entry</h1>
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
    'reg_no'         => $reg_no,
    'serial_number'  => $serial_number,
    'permit_number'  => $permit_number,
    'min_number'     => $min_number
];

if ($edit_id > 0) {
    // Update existing record
    $stmt = $pdo->prepare("
        UPDATE register_master
        SET store_code = :store_code,
            reg_no = :reg_no,
            serial_number = :serial_number,
            permit_number = :permit_number,
            min_number = :min_number,
            updated_at = datetime('now', 'localtime')
        WHERE id = :id
    ");
    $stmt->execute([
        ':store_code'    => $store_code,
        ':reg_no'        => $reg_no,
        ':serial_number' => $serial_number,
        ':permit_number' => $permit_number,
        ':min_number'    => $min_number,
        ':id'            => $edit_id
    ]);
    $newId = $edit_id;
    logActivity('edit', 'register_master', "Updated register #$edit_id (Store: $store_code, Reg: $reg_no)");
} else {
    // Insert new record
    $newId = saveRegisterMaster($data);
    logActivity('create', 'register_master', "Created register #$newId (Store: $store_code, Reg: $reg_no)");
}

header('Location: register_master.php?saved=' . $newId);
exit;
