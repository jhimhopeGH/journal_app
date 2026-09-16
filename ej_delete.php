<?php
// ej_delete.php - Deletes single or all EJ records from ej.db
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/ej_db.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'delete_all' || isset($_POST['delete_all'])) {
    deleteAllEjEntries();
    logActivity('delete_all', 'ej', 'Deleted all EJ entries');
    header('Location: ej_printing.php?deleted_all=1');
    exit;
}

if ($id > 0) {
    deleteEjEntry($id);
    logActivity('delete', 'ej', "EJ Entry #$id deleted");
    header('Location: ej_printing.php?deleted=1');
    exit;
}

header('Location: ej_printing.php');
exit;
