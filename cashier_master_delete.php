<?php
// cashier_master_delete.php - Handles single record and bulk delete for cashier master
require_once __DIR__ . '/auth.php';
requireNavAccess('cashier_master');
require_once __DIR__ . '/db.php';

if (isset($_GET['all'])) {
    // Delete ALL cashier records
    $pdo->exec("DELETE FROM cashier_master");
    logActivity('delete', 'cashier_master', 'Deleted ALL cashier master records');
    header('Location: cashier_master.php?deleted_all=1');
    exit;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $row = getCashierMasterById($id);
        deleteCashierMaster($id);
        $label = $row ? $row['name'] . ' (' . $row['emp_no'] . ')' : "ID $id";
        logActivity('delete', 'cashier_master', "Deleted cashier: $label");
    }
    header('Location: cashier_master.php?deleted=1');
    exit;
}

header('Location: cashier_master.php');
exit;
