<?php
// tender_master_delete.php - Deletes a single tender master entry or ALL entries from SQLite
require_once __DIR__ . '/auth.php';
requireNavAccess('tender_master');
require_once __DIR__ . '/db.php';

$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$all = isset($_GET['all']) ? (int)$_GET['all'] : 0;

if ($all === 1) {
    // Delete all records from tender_master
    $pdo->exec("DELETE FROM tender_master");
    try {
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name='tender_master'");
    } catch (Exception $e) {
        // Ignore sequence error if table does not use autoincrement sequence
    }
    logActivity('delete_all', 'tender_master', 'Deleted all tender records');
    header('Location: tender_master.php?deleted_all=1');
    exit;
}

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM tender_master WHERE id = :id");
    $stmt->execute([':id' => $id]);
    logActivity('delete', 'tender_master', "Deleted tender #$id");
    header('Location: tender_master.php?deleted=1');
    exit;
}

header('Location: tender_master.php');
exit;
