<?php
// register_master_delete.php - Deletes a register master entry from SQLite
require_once __DIR__ . '/auth.php';
requireNavAccess('register_master');
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM register_master WHERE id = :id");
    $stmt->execute([':id' => $id]);
    logActivity('delete', 'register_master', "Deleted register #$id");
}

header('Location: register_master.php?deleted=1');
exit;
