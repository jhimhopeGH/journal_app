<?php
// register_master_entry.php - New / Edit register master entry form
require_once __DIR__ . '/auth.php';
requireNavAccess('register_master');
require_once __DIR__ . '/db.php';
$currentPage = 'register_master';

$stores = getAllStoreMasters();

// If editing, load existing record
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$register = [
    'store_code'    => '',
    'reg_no'        => '',
    'serial_number' => '',
    'permit_number' => '',
    'min_number'    => ''
];

if ($editId > 0) {
    $existing = getRegisterMasterById($editId);
    if ($existing) {
        $register = $existing;
    }
}

$isEdit = $editId > 0 && isset($existing) && $existing;
$pageTitle = $isEdit ? 'Edit Register Entry' : 'New Register Entry';
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
            <a href="register_master_entry.php">+ New Entry</a>
            <a href="register_master.php">View Records</a>
        </div>

        <div class="page-header">
            <h1><?php echo $pageTitle; ?></h1>
            <p>Fill in the register / POS machine details below and save.</p>
        </div>

        <div class="panel" style="max-width: 560px;">
        <form action="register_master_save.php" method="POST" id="registerMasterForm">
            <?php if ($isEdit): ?>
                <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
            <?php endif; ?>

            <label for="store_code">1. Store Code</label>
            <?php if (!empty($stores)): ?>
                <select id="store_code_select" onchange="document.getElementById('store_code').value = this.value;" style="margin-bottom: 6px;">
                    <option value="">-- Choose from existing Store Master --</option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['store_code']); ?>" <?php if ($register['store_code'] === $s['store_code']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($s['store_code'] . ' - ' . $s['store_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <input type="text" id="store_code" name="store_code"
                   value="<?php echo htmlspecialchars($register['store_code']); ?>" required
                   placeholder="e.g. 12027">
            <p class="hint">Code identifying the parent store.</p>

            <label for="reg_no">2. Reg No</label>
            <input type="text" id="reg_no" name="reg_no"
                   value="<?php echo htmlspecialchars($register['reg_no']); ?>" required
                   placeholder="e.g. 101 or 1">
            <p class="hint">Register or POS machine number.</p>

            <label for="serial_number">3. Serial No</label>
            <input type="text" id="serial_number" name="serial_number"
                   value="<?php echo htmlspecialchars($register['serial_number']); ?>"
                   placeholder="e.g. 12NPEOCGXD3">
            <p class="hint">Machine or printer serial number.</p>

            <label for="permit_number">4. Permit No</label>
            <input type="text" id="permit_number" name="permit_number"
                   value="<?php echo htmlspecialchars($register['permit_number']); ?>"
                   placeholder="e.g. FP032023127037666900014">
            <p class="hint">BIR Permit to Use (PTU) number.</p>

            <label for="min_number">5. Min No</label>
            <input type="text" id="min_number" name="min_number"
                   value="<?php echo htmlspecialchars($register['min_number']); ?>"
                   placeholder="e.g. 23032210300170375">
            <p class="hint">Machine Identification Number (MIN).</p>

            <button type="submit"><?php echo $isEdit ? 'Update Register' : 'Save Register'; ?></button>
        </form>
        </div>
    </main>
</div>
</body>
</html>
