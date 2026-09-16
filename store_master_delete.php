<?php
// store_master_delete.php - Deletes a store master entry from SQLite
require_once __DIR__ . '/auth.php';
requireNavAccess('store_master');
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM store_master WHERE id = :id");
    $stmt->execute([':id' => $id]);
    logActivity('delete', 'store_master', "Deleted store #$id");
}

header('Location: store_master.php?deleted=1');
exit;
