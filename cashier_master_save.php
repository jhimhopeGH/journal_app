<?php
// cashier_master_save.php - Handles save/update for cashier master records
require_once __DIR__ . '/auth.php';
requireNavAccess('cashier_master');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cashier_master.php');
    exit;
}

$editId    = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
$storeCode = trim($_POST['store_code'] ?? '');
$empNo     = trim($_POST['emp_no']     ?? '');
$name      = trim($_POST['name']       ?? '');
$job       = trim($_POST['job']        ?? '');
$status    = trim($_POST['status']     ?? '');

if ($storeCode === '' || $name === '') {
    header('Location: cashier_master_entry.php' . ($editId ? "?id=$editId" : '') . '&error=missing_fields');
    exit;
}

$data = compact('store_code', 'emp_no', 'name', 'job', 'status');
$data['store_code'] = $storeCode;
$data['emp_no']     = $empNo;
$data['name']       = $name;
$data['job']        = $job;
$data['status']     = $status;

if ($editId > 0) {
    updateCashierMaster($editId, $data);
    logActivity('update', 'cashier_master', "Updated cashier ID $editId: $name (Store: $storeCode)");
} else {
    saveCashierMaster($data);
    logActivity('create', 'cashier_master', "Created cashier: $name (Emp: $empNo, Store: $storeCode)");
}

header('Location: cashier_master.php?saved=1');
exit;
