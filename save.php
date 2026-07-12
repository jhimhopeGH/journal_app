<?php
// save.php - Validates and saves a journal entry to SQLite
require_once __DIR__ . '/db.php';

function fieldValue($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

$errors = [];

$store_number       = fieldValue('store_number');
$register_number    = fieldValue('register_number');
$transaction_number = fieldValue('transaction_number');
$entry_date         = fieldValue('entry_date');
$zread_number       = fieldValue('zread_number');
$till_number        = fieldValue('till_number');

if ($store_number === '')       $errors[] = 'Store Number is required.';
if ($register_number === '')    $errors[] = 'Register Number is required.';
if ($transaction_number === '') $errors[] = 'Transaction Number is required.';
if ($zread_number === '')       $errors[] = 'Z-Read Number is required.';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry_date)) {
    $errors[] = 'Date is invalid.';
}

if (!preg_match('/^\d{4}$/', $till_number)) {
    $errors[] = 'Till Number must be exactly 4 digits.';
}

if (!empty($errors)) {
    // Send the user back to the form with the errors listed
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <title>Error</title><link rel="stylesheet" href="style.css"></head><body>
          <div class="container">
            <h1>Could not save entry</h1>
            <div class="error"><ul>';
    foreach ($errors as $err) {
        echo '<li>' . htmlspecialchars($err) . '</li>';
    }
    echo '</ul></div>
            <a class="btn btn-secondary" href="javascript:history.back()">Go Back</a>
          </div></body></html>';
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO entries
        (store_number, register_number, transaction_number, entry_date, zread_number, till_number)
    VALUES
        (:store_number, :register_number, :transaction_number, :entry_date, :zread_number, :till_number)
");

$stmt->execute([
    ':store_number'       => $store_number,
    ':register_number'    => $register_number,
    ':transaction_number' => $transaction_number,
    ':entry_date'         => $entry_date,
    ':zread_number'       => $zread_number,
    ':till_number'        => $till_number,
]);

$newId = $pdo->lastInsertId();

header('Location: records.php?saved=' . $newId);
exit;
