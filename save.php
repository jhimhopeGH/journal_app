<?php
// save.php - Validates and saves a journal entry to SQLite
require_once __DIR__ . '/auth.php';
requireNavAccess('journal');
require_once __DIR__ . '/db.php';


function fieldValue($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

$errors = [];

$store_number = fieldValue('store_number');
$register_number = fieldValue('register_number');
$transaction_number = ''; // Transaction number is no longer collected from the form
$last_trx_number = fieldValue('last_trx_number');
$entry_date = fieldValue('entry_date');
$entry_time = fieldValue('entry_time');
$zread_number = fieldValue('zread_number');
$till_number = fieldValue('till_number');

// Process tender array from post
$tender = '';
if (isset($_POST['tender']) && is_array($_POST['tender'])) {
    $tenderData = [];
    foreach ($_POST['tender'] as $tRow) {
        $name = isset($tRow['name']) ? trim($tRow['name']) : '';
        $amountRaw = isset($tRow['amount']) ? str_replace(',', '', trim($tRow['amount'])) : '';
        if ($name !== '') {
            $tenderData[] = [
                'name' => $name,
                'amount' => $amountRaw !== '' ? number_format((float)$amountRaw, 2, '.', '') : '0.00'
            ];
        }
    }
    if (!empty($tenderData)) {
        $tenderData = normalizeAndSortTenders($tenderData);
        $tender = json_encode($tenderData);
    }
}

$total_vat = (float)str_replace(',', '', fieldValue('total_vat'));
$total_non_vat = (float)str_replace(',', '', fieldValue('total_non_vat'));
$old_grand_total = (float)str_replace(',', '', fieldValue('old_grand_total'));

// Server-side calculations
$daily_sales = $total_vat + $total_non_vat;
$new_grand_total = $old_grand_total + $daily_sales;

if ($store_number === '') $errors[] = 'Store Number is required.';
if ($register_number === '') $errors[] = 'Register Number is required.';
if ($last_trx_number === '') $errors[] = 'Last Transaction Number is required.';
if ($zread_number === '') $errors[] = 'Z-Read Number is required.';
if ($tender === '') $errors[] = 'Tender is required.';
if ($entry_time === '') $errors[] = 'Time is required.';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry_date)) {
    $errors[] = 'Date is invalid.';
}

if (!preg_match('/^\d{2}:\d{2}$/', $entry_time) && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $entry_time)) {
    $errors[] = 'Time format is invalid. Must be HH:MM or HH:MM:SS.';
}

// Default till_number if empty
if ($till_number === '') {
    $till_number = '0000';
}

// Ensure sum of Tenders equals Daily Sales (VAT + Non-VAT)
$totalTenders = 0;
if (isset($tenderData) && is_array($tenderData)) {
    foreach ($tenderData as $t) {
        $totalTenders += (float)$t['amount'];
    }
}
if (abs($daily_sales - $totalTenders) > 0.01) {
    $errors[] = 'Total of VAT and Non-VAT (' . number_format($daily_sales, 2) . ') must equal the sum of Tenders (' . number_format($totalTenders, 2) . ').';
}

if (!empty($errors)) {
    // Send the user back to the form with the errors listed
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <title>Error</title><link rel="stylesheet" href="style.css"></head><body>
          <div class="container" style="padding: 20px; max-width: 500px; margin: 40px auto; background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08);">
            <h1>Could not save entry</h1>
            <div class="error" style="color: #ef4444; margin-bottom: 20px;"><ul>';
    foreach ($errors as $err) {
        echo '<li>' . htmlspecialchars($err) . '</li>';
    }
    echo '</ul></div>
            <a class="btn btn-secondary" href="javascript:history.back()">Go Back</a>
          </div></body></html>';
    exit;
}

// Convert HTML date format (YYYY-MM-DD) to journal format (MM/DD/YY)
$dateObj = DateTime::createFromFormat('Y-m-d', $entry_date);
$formatted_date = $dateObj ? $dateObj->format('m/d/y') : $entry_date;

$edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

if ($edit_id > 0) {
    $stmt = $pdo->prepare("
        UPDATE entries
        SET store_number = :store_number,
            register_number = :register_number,
            transaction_number = :transaction_number,
            last_trx_number = :last_trx_number,
            entry_date = :entry_date,
            entry_time = :entry_time,
            zread_number = :zread_number,
            till_number = :till_number,
            tender = :tender,
            total_vat = :total_vat,
            total_non_vat = :total_non_vat,
            daily_sales = :daily_sales,
            old_grand_total = :old_grand_total,
            new_grand_total = :new_grand_total
        WHERE id = :id
    ");
    $stmt->execute([
        ':store_number'       => $store_number,
        ':register_number'    => $register_number,
        ':transaction_number' => $transaction_number,
        ':last_trx_number'    => $last_trx_number,
        ':entry_date'         => $formatted_date,
        ':entry_time'         => $entry_time,
        ':zread_number'       => $zread_number,
        ':till_number'        => $till_number,
        ':tender'             => $tender,
        ':total_vat'          => $total_vat,
        ':total_non_vat'      => $total_non_vat,
        ':daily_sales'        => $daily_sales,
        ':old_grand_total'    => $old_grand_total,
        ':new_grand_total'    => $new_grand_total,
        ':id'                 => $edit_id
    ]);
    $newId = $edit_id;
    logActivity('edit', 'journal', "Entry #$edit_id updated (Store: $store_number, Date: $formatted_date)");
} else {
    $stmt = $pdo->prepare("
        INSERT INTO entries
            (store_number, register_number, transaction_number, last_trx_number, entry_date, entry_time, zread_number, till_number, tender, total_vat, total_non_vat, daily_sales, old_grand_total, new_grand_total)
        VALUES
            (:store_number, :register_number, :transaction_number, :last_trx_number, :entry_date, :entry_time, :zread_number, :till_number, :tender, :total_vat, :total_non_vat, :daily_sales, :old_grand_total, :new_grand_total)
    ");
    $stmt->execute([
        ':store_number'       => $store_number,
        ':register_number'    => $register_number,
        ':transaction_number' => $transaction_number,
        ':last_trx_number'    => $last_trx_number,
        ':entry_date'         => $formatted_date,
        ':entry_time'         => $entry_time,
        ':zread_number'       => $zread_number,
        ':till_number'        => $till_number,
        ':tender'             => $tender,
        ':total_vat'          => $total_vat,
        ':total_non_vat'      => $total_non_vat,
        ':daily_sales'        => $daily_sales,
        ':old_grand_total'    => $old_grand_total,
        ':new_grand_total'    => $new_grand_total
    ]);
    $newId = $pdo->lastInsertId();
    logActivity('create', 'journal', "Entry #$newId created (Store: $store_number, Date: $formatted_date)");
}

header('Location: records.php?saved=' . $newId);
exit;
?>
