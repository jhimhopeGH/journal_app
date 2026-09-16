<?php
// cashier_master_entry.php - New / Edit cashier master entry form
require_once __DIR__ . '/auth.php';
requireNavAccess('cashier_master');
require_once __DIR__ . '/db.php';
$currentPage = 'cashier_master';

$editId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cashier = [
    'store_code' => '',
    'emp_no'     => '',
    'name'       => '',
    'job'        => '',
    'status'     => '',
];

if ($editId > 0) {
    $existing = getCashierMasterById($editId);
    if ($existing) {
        $cashier = $existing;
    }
}

$isEdit    = $editId > 0 && isset($existing) && $existing;
$pageTitle = $isEdit ? 'Edit Cashier Entry' : 'New Cashier Entry';

$stores = getAllStoreMasters();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $pageTitle; ?> - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
.top-nav { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.top-nav a {
    border:none; padding:8px 18px; border-radius:20px; cursor:pointer;
    font-size:13px; font-weight:600; text-decoration:none;
    display:inline-flex; align-items:center; gap:6px;
    background:#e2e8f0; color:#1e293b; transition:all 0.18s ease;
}
.top-nav a:hover { background:#cbd5e1; color:#0f172a; transform:translateY(-1px); }
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <a href="cashier_master_entry.php">+ New Entry</a>
            <a href="cashier_master.php">View Records</a>
        </div>

        <div class="page-header">
            <h1><?php echo $pageTitle; ?></h1>
            <p>Fill in the cashier details below and save.</p>
        </div>

        <div class="panel" style="max-width:600px;">
        <form action="cashier_master_save.php" method="POST" id="cashierMasterForm">
            <?php if ($isEdit): ?>
                <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
            <?php endif; ?>

            <label for="store_code">Store Code <span style="color:#ef4444;">*</span></label>
            <select id="store_code" name="store_code" required>
                <option value="">-- Select Store --</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['store_code']); ?>"
                            <?php if ($cashier['store_code'] === $s['store_code']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($s['store_code'] . ' — ' . $s['store_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="hint">The store branch this cashier is assigned to.</p>

            <label for="emp_no">Employee Number</label>
            <input type="text" id="emp_no" name="emp_no"
                   value="<?php echo htmlspecialchars($cashier['emp_no']); ?>"
                   placeholder="e.g. EMP-001">
            <p class="hint">Employee number or ID from the payroll/TPS system.</p>

            <label for="name">Full Name <span style="color:#ef4444;">*</span></label>
            <input type="text" id="name" name="name" required
                   value="<?php echo htmlspecialchars($cashier['name']); ?>"
                   placeholder="e.g. Juan Dela Cruz">
            <p class="hint">Full name of the cashier employee.</p>

            <label for="job">Job / Position</label>
            <input type="text" id="job" name="job"
                   value="<?php echo htmlspecialchars($cashier['job']); ?>"
                   placeholder="e.g. CASHIER, SUPERVISOR">
            <p class="hint">Job title or position code from the TPS system.</p>

            <label for="status">Status</label>
            <select id="status" name="status">
                <?php
                $statOpts = ['Active', 'Inactive', ''];
                $statVal  = $cashier['status'];
                // If a non-standard status comes from TPS, keep it editable
                if (!in_array($statVal, ['Active', 'Inactive', ''])) {
                    echo '<option value="' . htmlspecialchars($statVal) . '" selected>' . htmlspecialchars($statVal) . '</option>';
                }
                ?>
                <option value="Active"   <?php if ($statVal === 'Active')   echo 'selected'; ?>>Active</option>
                <option value="Inactive" <?php if ($statVal === 'Inactive') echo 'selected'; ?>>Inactive</option>
                <option value=""         <?php if ($statVal === '')         echo 'selected'; ?>>— Unset —</option>
            </select>
            <p class="hint">Employment status of the cashier.</p>

            <button type="submit"><?php echo $isEdit ? 'Update Cashier' : 'Save Cashier'; ?></button>
            <a href="cashier_master.php" style="margin-left:12px; font-size:13px; color:#64748b; text-decoration:underline;">Cancel</a>
        </form>
        </div>
    </main>
</div>
</body>
</html>
