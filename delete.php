<?php
// delete.php - Deletes a journal entry from SQLite
require_once __DIR__ . '/auth.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$store = isset($_GET['store']) ? trim($_GET['store']) : '';

if ($action === 'delete_all') {
    if ($store !== '') {
        $stmt = $pdo->prepare("DELETE FROM entries WHERE store_number = :s");
        $stmt->execute([':s' => $store]);
        logActivity('delete_all', 'journal', "All entries deleted for store: $store");
    } else {
        $pdo->exec("DELETE FROM entries");
        logActivity('delete_all', 'journal', 'All journal entries deleted (no store filter)');
    }
    header('Location: records.php?deleted_all=1');
    exit;
}

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM entries WHERE id = :id");
    $stmt->execute([':id' => $id]);
    logActivity('delete', 'journal', "Entry #$id deleted");
}

header('Location: records.php?deleted=' . $id);
exit;
?>
