<?php
// entry.php - Journal entry input form
$today = date('Y-m-d');
<<<<<<< Updated upstream
=======

// Load all tenders for dropdown (ordered by name ascending for usability)
$tenders = getAllTenderMasters();
// Sort ascending by tender_name for the dropdown
usort($tenders, fn($a, $b) => strcmp($a['tender_name'], $b['tender_name']));

// If editing, load existing record
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$entry = [
    'store_number'      => '',
    'register_number'   => '',
    'transaction_number'=> '',
    'entry_date'        => $today,
    'zread_number'      => '',
    'till_number'       => '',
    'last_trx_number'   => '',
    'tender'            => '',
    'total_vat'         => '0.00',
    'total_non_vat'     => '0.00',
    'daily_sales'       => '0.00',
    'old_grand_total'   => '0.00',
    'new_grand_total'   => '0.00',
    'entry_time'        => date('H:i')
];

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM entries WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $entry = $existing;
        // Parse date from MM/DD/YY to YYYY-MM-DD for date input
        if (preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $entry['entry_date'])) {
            $dateObj = DateTime::createFromFormat('m/d/y', $entry['entry_date']);
            if ($dateObj) {
                $entry['entry_date'] = $dateObj->format('Y-m-d');
            }
        }
    }
}

$isEdit = $editId > 0 && isset($existing) && $existing;
$pageTitle = $isEdit ? 'Edit Journal Entry' : 'New Journal Entry';

// Decode existing tender JSON for edit pre-population
$existingTenders = [];
if ($entry['tender'] !== '') {
    $decoded = json_decode($entry['tender'], true);
    if (is_array($decoded)) {
        $existingTenders = $decoded;   // [{name, amount}, ...]
    } else {
        // Legacy plain-text value — wrap as single row
        $existingTenders = [['name' => $entry['tender'], 'amount' => '']];
    }
}
if (empty($existingTenders)) {
    $existingTenders = [['name' => '', 'amount' => '']]; // one blank starter row
}

// Build the tender options HTML once (reused in JS template)
$tenderOptionsHtml = '<option value="">-- Select Tender --</option>';
foreach ($tenders as $t) {
    $tenderOptionsHtml .= '<option value="' . htmlspecialchars($t['tender_name']) . '">'
        . htmlspecialchars($t['tender_name']) . ' (' . htmlspecialchars($t['tender_code']) . ')'
        . '</option>';
}
>>>>>>> Stashed changes
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<<<<<<< Updated upstream
<title>New Journal Entry</title>
=======
<title><?php echo $pageTitle; ?> - Electronic Journal</title>
>>>>>>> Stashed changes
<link rel="stylesheet" href="style.css">
<style>
.tender-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
.tender-table th { font-size: 12px; color: #64748b; font-weight: 600;
    text-align: left; padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
.tender-table td { padding: 5px 6px; vertical-align: middle; }
.tender-table select { width: 100%; padding: 6px 8px; border: 1px solid #ccc;
    border-radius: 4px; font-size: 13px; background: white; box-sizing: border-box; }
.tender-table input[type=number] { width: 100%; padding: 6px 8px; border: 1px solid #ccc;
    border-radius: 4px; font-size: 13px; box-sizing: border-box; text-align: right; }
.tender-table .col-tender { width: 65%; }
.tender-table .col-amount { width: 28%; }
.tender-table .col-action { width: 7%; text-align: center; }
.remove-tender-btn { background: none; border: none; color: #ef4444; cursor: pointer;
    font-size: 16px; line-height: 1; padding: 2px 4px; }
.remove-tender-btn:hover { color: #b91c1c; }
.add-tender-btn { margin-top: 8px; font-size: 13px; color: #2d6cdf; background: none;
    border: 1px dashed #2d6cdf; border-radius: 4px; padding: 5px 12px;
    cursor: pointer; width: 100%; }
.add-tender-btn:hover { background: #eff6ff; }
</style>
</head>
<body>
<div class="app-shell">
    <?php $currentPage = ''; require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>New Journal Entry</h1>
            <p>Fill in the transaction details below and save.</p>
        </div>

        <div class="panel" style="max-width: 540px;">
        <form action="save.php" method="POST" id="journalForm">

<<<<<<< Updated upstream
            <label for="store_number">Store Number</label>
            <input type="text" id="store_number" name="store_number" required>

            <label for="register_number">Register Number</label>
            <input type="text" id="register_number" name="register_number" required>

            <label for="transaction_number">Transaction Number</label>
            <input type="text" id="transaction_number" name="transaction_number" required>

            <label for="entry_date">Date</label>
            <input type="date" id="entry_date" name="entry_date" value="<?php echo $today; ?>" required>

            <label for="zread_number">Z-Read Number</label>
            <input type="text" id="zread_number" name="zread_number" required>

            <label for="till_number">Till Number (Cashier, 4 digits only)</label>
            <input type="text" id="till_number" name="till_number"
                   maxlength="4" minlength="4" pattern="[0-9]{4}"
                   inputmode="numeric" title="Must be exactly 4 digits"
                   required>
            <p class="hint">Exactly 4 digits, e.g. 0007</p>
=======
            <!-- Hidden field that receives the JSON-encoded tender array on submit -->
            <input type="hidden" name="tender" id="tender_json">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label for="store_number">Store Number</label>
                    <input type="text" id="store_number" name="store_number"
                           value="<?php echo htmlspecialchars($entry['store_number']); ?>" required>
                </div>
                <div>
                    <label for="register_number">Register Number</label>
                    <input type="text" id="register_number" name="register_number"
                           value="<?php echo htmlspecialchars($entry['register_number']); ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px;">
                <div>
                    <label for="transaction_number">Transaction Number</label>
                    <input type="text" id="transaction_number" name="transaction_number"
                           value="<?php echo htmlspecialchars($entry['transaction_number']); ?>" required>
                </div>
                <div>
                    <label for="last_trx_number">Last Trx#</label>
                    <input type="text" id="last_trx_number" name="last_trx_number"
                           value="<?php echo htmlspecialchars($entry['last_trx_number']); ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px;">
                <div>
                    <label for="entry_date">Date</label>
                    <input type="date" id="entry_date" name="entry_date"
                           value="<?php echo htmlspecialchars($entry['entry_date']); ?>" required>
                </div>
                <div>
                    <label for="entry_time">Time (24-Hour)</label>
                    <input type="time" id="entry_time" name="entry_time" step="60"
                           value="<?php echo htmlspecialchars($entry['entry_time']); ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px;">
                <div>
                    <label for="zread_number">Z-Read Number</label>
                    <input type="text" id="zread_number" name="zread_number"
                           value="<?php echo htmlspecialchars($entry['zread_number']); ?>" required>
                </div>
                <div>
                    <label for="till_number">Till Number (Cashier)</label>
                    <input type="text" id="till_number" name="till_number"
                           value="<?php echo htmlspecialchars($entry['till_number']); ?>"
                           maxlength="4" minlength="4" pattern="[0-9]{4}"
                           inputmode="numeric" title="Must be exactly 4 digits" required>
                </div>
            </div>

            <!-- ── Multi-Tender Section ── -->
            <div style="margin-top: 16px;">
                <label style="margin-bottom: 6px; display: block;">Tender(s)</label>
                <table class="tender-table" id="tender-table">
                    <thead>
                        <tr>
                            <th class="col-tender">Tender Type</th>
                            <th class="col-amount">Amount</th>
                            <th class="col-action"></th>
                        </tr>
                    </thead>
                    <tbody id="tender-body">
                        <?php foreach ($existingTenders as $row): ?>
                        <tr class="tender-row">
                            <td class="col-tender">
                                <select class="tender-select">
                                    <?php echo $tenderOptionsHtml; ?>
                                </select>
                            </td>
                            <td class="col-amount">
                                <input type="number" class="tender-amount" step="0.01" min="0"
                                       placeholder="0.00"
                                       value="<?php echo htmlspecialchars($row['amount'] ?? ''); ?>">
                            </td>
                            <td class="col-action">
                                <button type="button" class="remove-tender-btn" title="Remove">✖</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="add-tender-btn" id="add-tender-btn">+ Add Tender</button>
            </div>

            <!-- ── Financial Fields ── -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                <div>
                    <label for="total_vat">Total VAT</label>
                    <input type="number" id="total_vat" name="total_vat" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($entry['total_vat']); ?>" required>
                </div>
                <div>
                    <label for="total_non_vat">Total Non-VAT</label>
                    <input type="number" id="total_non_vat" name="total_non_vat" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($entry['total_non_vat']); ?>" required>
                </div>
            </div>

            <div style="margin-top: 12px;">
                <label for="daily_sales">Daily Sales (VAT + Non-VAT)</label>
                <input type="number" id="daily_sales" name="daily_sales" step="0.01" readonly
                       value="<?php echo htmlspecialchars($entry['daily_sales']); ?>"
                       style="background: #f1f5f9; color: #475569; font-weight: bold;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px; margin-bottom: 20px;">
                <div>
                    <label for="old_grand_total">Old Grand Total</label>
                    <input type="number" id="old_grand_total" name="old_grand_total" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($entry['old_grand_total']); ?>" required>
                </div>
                <div>
                    <label for="new_grand_total">New Grand Total</label>
                    <input type="number" id="new_grand_total" name="new_grand_total" step="0.01" readonly
                           value="<?php echo htmlspecialchars($entry['new_grand_total']); ?>"
                           style="background: #f1f5f9; color: #475569; font-weight: bold;">
                </div>
            </div>
>>>>>>> Stashed changes

            <button type="submit">Save Entry</button>
        </form>
        </div>
    </main>
</div>

<script>
// ── Tender option HTML string (injected from PHP) ──────────────────────────
const TENDER_OPTIONS = <?php echo json_encode($tenderOptionsHtml); ?>;

// ── Existing tender data for pre-population in edit mode ───────────────────
const EXISTING_TENDERS = <?php echo json_encode($existingTenders); ?>;

// ── Helper: create one tender row element ─────────────────────────────────
function makeTenderRow(name = '', amount = '') {
    const tr = document.createElement('tr');
    tr.className = 'tender-row';
    tr.innerHTML = `
        <td class="col-tender">
            <select class="tender-select">${TENDER_OPTIONS}</select>
        </td>
        <td class="col-amount">
            <input type="number" class="tender-amount" step="0.01" min="0"
                   placeholder="0.00" value="${amount}">
        </td>
        <td class="col-action">
            <button type="button" class="remove-tender-btn" title="Remove">✖</button>
        </td>`;
    if (name) {
        const opt = tr.querySelector('.tender-select');
        opt.value = name;
    }
    tr.querySelector('.remove-tender-btn').addEventListener('click', () => {
        if (document.querySelectorAll('.tender-row').length > 1) {
            tr.remove();
        } else {
            // Keep at least one row — just clear it
            tr.querySelector('.tender-select').value = '';
            tr.querySelector('.tender-amount').value = '';
        }
    });
    return tr;
}

// ── Pre-populate existing tender rows in edit mode ─────────────────────────
const tbody = document.getElementById('tender-body');

// Clear the PHP-rendered rows and re-create via JS so event listeners work
tbody.innerHTML = '';
EXISTING_TENDERS.forEach(t => {
    tbody.appendChild(makeTenderRow(t.name || '', t.amount || ''));
});
// Ensure at least one row
if (tbody.querySelectorAll('.tender-row').length === 0) {
    tbody.appendChild(makeTenderRow());
}

// ── Add-Tender button ──────────────────────────────────────────────────────
document.getElementById('add-tender-btn').addEventListener('click', () => {
    tbody.appendChild(makeTenderRow());
});

// ── Serialize tenders to JSON before submit ────────────────────────────────
document.getElementById('journalForm').addEventListener('submit', function (e) {
    const rows = document.querySelectorAll('.tender-row');
    const tenderData = [];
    let valid = false;
    rows.forEach(row => {
        const name   = row.querySelector('.tender-select').value.trim();
        const amount = parseFloat(row.querySelector('.tender-amount').value) || 0;
        if (name) {
            tenderData.push({ name, amount: amount.toFixed(2) });
            valid = true;
        }
    });
    if (!valid) {
        e.preventDefault();
        alert('Please add at least one tender type.');
        return;
    }
    document.getElementById('tender_json').value = JSON.stringify(tenderData);
});

// ── Digit-only Till Number ─────────────────────────────────────────────────
document.getElementById('till_number').addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
});

// ── Dynamic calculations ───────────────────────────────────────────────────
const totalVatInput      = document.getElementById('total_vat');
const totalNonVatInput   = document.getElementById('total_non_vat');
const dailySalesInput    = document.getElementById('daily_sales');
const oldGrandTotalInput = document.getElementById('old_grand_total');
const newGrandTotalInput = document.getElementById('new_grand_total');

function calculateTotals() {
    const vat    = parseFloat(totalVatInput.value)    || 0;
    const nonVat = parseFloat(totalNonVatInput.value) || 0;
    const daily  = vat + nonVat;
    dailySalesInput.value = daily.toFixed(2);

    const oldGrand = parseFloat(oldGrandTotalInput.value) || 0;
    newGrandTotalInput.value = (oldGrand + daily).toFixed(2);
}

totalVatInput.addEventListener('input', calculateTotals);
totalNonVatInput.addEventListener('input', calculateTotals);
oldGrandTotalInput.addEventListener('input', calculateTotals);
</script>
</body>
</html>
