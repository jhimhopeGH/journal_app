<?php
// tender_master_delete.php - Deletes a tender master entry from SQLite
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM tender_master WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

header('Location: tender_master.php?deleted=1');
exit;
