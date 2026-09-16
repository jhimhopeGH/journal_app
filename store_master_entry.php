<?php
// store_master_entry.php - New / Edit store master entry form
require_once __DIR__ . '/auth.php';
requireNavAccess('store_master');
require_once __DIR__ . '/db.php';
$currentPage = 'store_master';


$isNew  = isset($_GET['new']) && $_GET['new'] == '1';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$store = [
    'store_code' => '',
    'store_name' => '',
    'serial_number' => '',
    'min_number' => '',
    'permit_number' => '',
    'header' => '',
    'footer' => '',
    'accreditation' => ''
];

if ($editId > 0) {
    $existing = getStoreMasterById($editId);
    if ($existing) {
        $store = $existing;
    }
} elseif (!$isNew) {
    // If no specific ID was passed, load the primary store master record
    $existing = getStoreMaster();
    if ($existing && !empty($existing['id'])) {
        $store = $existing;
        $editId = (int)$existing['id'];
    }
}

$isEdit = $editId > 0 && isset($existing) && $existing && !$isNew;
$pageTitle = $isEdit ? 'Edit Store Entry' : 'New Store Entry';
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
            <a href="store_master_entry.php?new=1">+ New Entry</a>
            <a href="store_master.php">View Records</a>
        </div>

        <div class="page-header">
            <h1><?php echo $pageTitle; ?></h1>
            <p>Fill in the store details below and save.</p>
        </div>

        <div class="panel" style="max-width: 820px;">
        <form action="store_master_save.php" method="POST" id="storeMasterForm">
            <?php if ($isEdit): ?>
                <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                <div>
                    <label for="store_code">Store Code</label>
                    <input type="text" id="store_code" name="store_code"
                           value="<?php echo htmlspecialchars($store['store_code']); ?>" required
                           placeholder="e.g. STR-001">
                    <p class="hint">Short code identifying the store.</p>
                </div>
                <div>
                    <label for="store_name">Store Name</label>
                    <input type="text" id="store_name" name="store_name"
                           value="<?php echo htmlspecialchars($store['store_name']); ?>" required
                           placeholder="e.g. Main Branch">
                    <p class="hint">Full display name for the store.</p>
                </div>
            </div>

            <label for="header" style="margin-top: 18px;">Header</label>
            <textarea id="header" name="header" rows="6" style="width: 100%; min-height: 120px; font-family: Consolas, 'Courier New', monospace; font-size: 13px;"
                      placeholder="Receipt header text..."><?php echo htmlspecialchars($store['header']); ?></textarea>
            <p class="hint">Text printed at the top of receipts.</p>

            <label for="footer" style="margin-top: 18px;">Footer</label>
            <textarea id="footer" name="footer" rows="4" style="width: 100%; min-height: 90px; font-family: Consolas, 'Courier New', monospace; font-size: 13px;"
                      placeholder="Receipt footer text..."><?php echo htmlspecialchars($store['footer'] ?? ''); ?></textarea>
            <p class="hint">Text printed at the bottom of receipts.</p>

            <label for="accreditation" style="margin-top: 18px;">Accreditation</label>
            <textarea id="accreditation" name="accreditation" rows="4" style="width: 100%; min-height: 90px; font-family: Consolas, 'Courier New', monospace; font-size: 13px;"
                      placeholder="Accreditation details / text..."><?php echo htmlspecialchars($store['accreditation'] ?? ''); ?></textarea>
            <p class="hint">Accreditation information or BIR details.</p>

            <div style="margin-top: 20px;">
                <button type="submit"><?php echo $isEdit ? 'Update Store' : 'Save Store'; ?></button>
            </div>
        </form>
        </div>
    </main>
</div>
</body>
</html>
