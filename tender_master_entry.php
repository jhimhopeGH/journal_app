<?php
// tender_master_entry.php - New / Edit tender master entry form
require_once __DIR__ . '/db.php';
$currentPage = 'tender_master';

// If editing, load existing record
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tender = [
    'tender_code' => '',
    'tender_name' => ''
];

if ($editId > 0) {
    $existing = getTenderMasterById($editId);
    if ($existing) {
        $tender = $existing;
    }
}

$isEdit = $editId > 0 && isset($existing) && $existing;
$pageTitle = $isEdit ? 'Edit Tender Entry' : 'New Tender Entry';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $pageTitle; ?> - Electronic Journal</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <a href="tender_master_entry.php">+ New Entry</a>
            <a href="tender_master.php">View Records</a>
        </div>

        <div class="page-header">
            <h1><?php echo $pageTitle; ?></h1>
            <p>Fill in the tender details below and save.</p>
        </div>

        <div class="panel" style="max-width: 560px;">
        <form action="tender_master_save.php" method="POST" id="tenderMasterForm">
            <?php if ($isEdit): ?>
                <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
            <?php endif; ?>

            <label for="tender_code">Tender Code</label>
            <input type="text" id="tender_code" name="tender_code"
                   value="<?php echo htmlspecialchars($tender['tender_code']); ?>" required
                   placeholder="e.g. CASH, VISA, GCASH">
            <p class="hint">Short code identifying the payment tender type.</p>

            <label for="tender_name">Tender Name</label>
            <input type="text" id="tender_name" name="tender_name"
                   value="<?php echo htmlspecialchars($tender['tender_name']); ?>" required
                   placeholder="e.g. Cash, Visa Credit Card, GCash E-Wallet">
            <p class="hint">Full display name for the payment method.</p>

            <button type="submit"><?php echo $isEdit ? 'Update Tender' : 'Save Tender'; ?></button>
        </form>
        </div>
    </main>
</div>
</body>
</html>
