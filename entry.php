<?php
// entry.php - New / Edit journal entry input form
require_once __DIR__ . '/auth.php';
requireNavAccess('journal');
require_once __DIR__ . '/db.php';
$today = date('Y-m-d');


// Load all tenders for dropdown (ordered by name ascending for usability)
$tenders = getAllTenderMasters();
usort($tenders, fn($a, $b) => strcmp($a['tender_name'], $b['tender_name']));

// Determine default Cash tender name from tender_master
$defaultCashName = 'CASH';
foreach ($tenders as $t) {
    if (strtoupper(trim($t['tender_code'])) === 'CA' || strcasecmp(trim($t['tender_name']), 'CASH') === 0) {
        $defaultCashName = $t['tender_name'];
        break;
    }
}
if (empty($defaultCashName) && !empty($tenders)) {
    foreach ($tenders as $t) {
        if (stripos($t['tender_name'], 'cash') !== false) {
            $defaultCashName = $t['tender_name'];
            break;
        }
    }
    if (empty($defaultCashName)) {
        $defaultCashName = $tenders[0]['tender_name'];
    }
}

// Canonical tender name lookup map
$knownTenderNames = [];
$tenderCodeMap = [];
foreach ($tenders as $t) {
    $tName = trim($t['tender_name']);
    $tCode = strtoupper(trim($t['tender_code']));
    $knownTenderNames[strtolower($tName)] = $tName;
    if ($tCode !== '') {
        $tenderCodeMap[strtolower($tCode)] = $tName;
    }
}

$knownAliases = [
    'cash'                  => $knownTenderNames['cash'] ?? 'CASH',
    'check'                 => $knownTenderNames['check/bank deposit'] ?? ($knownTenderNames['check'] ?? 'CHECK/BANK DEPOSIT'),
    'cheque'                => $knownTenderNames['check/bank deposit'] ?? ($knownTenderNames['check'] ?? 'CHECK/BANK DEPOSIT'),
    'bpi'                   => $knownTenderNames['bpi credit'] ?? ($knownTenderNames['bpi eps'] ?? 'BPI CREDIT'),
    'card'                  => $knownTenderNames['bpi credit'] ?? ($knownTenderNames['bpi eps'] ?? 'BPI CREDIT'),
    'cards'                 => $knownTenderNames['bpi credit'] ?? ($knownTenderNames['bpi eps'] ?? 'BPI CREDIT'),
    'gift cert'             => $knownTenderNames['gift cert redeem #'] ?? ($knownTenderNames['gift cert redeem'] ?? 'Gift Cert Redeem #'),
    'gift cert redeem'      => $knownTenderNames['gift cert redeem #'] ?? ($knownTenderNames['gift cert redeem'] ?? 'Gift Cert Redeem #'),
    'gift certificate'      => $knownTenderNames['gift cert redeem #'] ?? ($knownTenderNames['gift cert redeem'] ?? 'Gift Cert Redeem #'),
    'gc'                    => $knownTenderNames['gift cert redeem #'] ?? ($knownTenderNames['gift cert redeem'] ?? 'Gift Cert Redeem #'),
    'ar'                    => 'AR',
    'a/r'                   => 'AR',
    'house account charge'  => 'AR',
    'house charge tender'   => 'AR',
    'accounts receivable'   => 'AR',
    'account receivable'    => 'AR',
    'rnb card'              => $knownTenderNames['rnb card'] ?? 'RNB CARD',
    'others'                => $knownTenderNames['rnb card'] ?? 'RNB CARD',
    'other'                 => $knownTenderNames['rnb card'] ?? 'RNB CARD',
];

$resolveCanonicalName = function($rawName) use ($knownTenderNames, $tenderCodeMap, $knownAliases) {
    $clean = trim((string)$rawName);
    if ($clean === '') return '';
    $lower = strtolower($clean);

    if (isset($knownTenderNames[$lower])) {
        return $knownTenderNames[$lower];
    }
    if (isset($tenderCodeMap[$lower])) {
        return $tenderCodeMap[$lower];
    }
    if (isset($knownAliases[$lower])) {
        return $knownAliases[$lower];
    }
    // Prefix / startswith match
    foreach ($knownTenderNames as $kLower => $canon) {
        if (strpos($kLower, $lower) === 0 || strpos($lower, $kLower) === 0) {
            return $canon;
        }
    }
    return $clean;
};

// If editing, load existing record
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$entry = [
    'store_number'      => '',
    'register_number'   => '',
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
        if (preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $entry['entry_date'])) {
            $dateObj = DateTime::createFromFormat('m/d/y', $entry['entry_date']);
            if ($dateObj) {
                $entry['entry_date'] = $dateObj->format('Y-m-d');
            }
        }
        // Ensure daily_sales is the exact sum of VAT + Non-VAT, preserving daily_sales if VAT/Non-VAT not yet split
        $sumVatNonVat = (float)$entry['total_vat'] + (float)$entry['total_non_vat'];
        $rawDailySales = (float)($entry['daily_sales'] ?? 0);
        if ($sumVatNonVat > 0) {
            $entry['daily_sales'] = number_format($sumVatNonVat, 2, '.', '');
        } elseif ($rawDailySales > 0) {
            $entry['total_vat'] = number_format($rawDailySales, 2, '.', '');
            $entry['daily_sales'] = number_format($rawDailySales, 2, '.', '');
        } else {
            $entry['daily_sales'] = '0.00';
        }
        $entry['new_grand_total'] = number_format((float)$entry['old_grand_total'] + (float)$entry['daily_sales'], 2, '.', '');
    }
}

$isEdit = $editId > 0 && isset($existing) && $existing;
$pageTitle = $isEdit ? 'Edit Journal Entry' : 'New Journal Entry';

// Decode existing tender JSON for edit pre-population
$existingTenders = [];
if ($entry['tender'] !== '') {
    $decoded = json_decode($entry['tender'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $tItem) {
            if (is_array($tItem)) {
                $rawN = isset($tItem['name']) ? trim($tItem['name']) : '';
                $rawA = isset($tItem['amount']) ? trim((string)$tItem['amount']) : '';
                if ($rawN !== '' || $rawA !== '') {
                    $canon = $resolveCanonicalName($rawN);
                    $existingTenders[] = [
                        'name'   => $canon ?: $rawN,
                        'amount' => $rawA
                    ];
                }
            }
        }
    } else {
        $rawN = trim($entry['tender']);
        $canon = $resolveCanonicalName($rawN);
        $existingTenders = [['name' => $canon ?: $rawN, 'amount' => '']];
    }
    $existingTenders = normalizeAndSortTenders($existingTenders);
}

$dailySalesVal = (float)$entry['daily_sales'];
if ($dailySalesVal <= 0) {
    $dailySalesVal = (float)$entry['total_vat'] + (float)$entry['total_non_vat'];
}

// When editing: automatically fill in tenders if empty or amount missing
if ($isEdit) {
    if (empty($existingTenders)) {
        // Automatically fill in default Cash tender with the daily sales amount
        $existingTenders = [[
            'name'   => $defaultCashName,
            'amount' => number_format($dailySalesVal, 2, '.', '')
        ]];
    } else if (count($existingTenders) === 1) {
        // If single row has empty or zero amount and daily sales is positive, fill it in
        $cleanAmt = (float)str_replace(',', '', (string)$existingTenders[0]['amount']);
        if ($cleanAmt <= 0 && $dailySalesVal > 0) {
            if (empty($existingTenders[0]['name'])) {
                $existingTenders[0]['name'] = $defaultCashName;
            }
            $existingTenders[0]['amount'] = number_format($dailySalesVal, 2, '.', '');
        }
    }
} else {
    // New Entry mode: start with a default Cash tender row ready to be populated
    if (empty($existingTenders)) {
        $existingTenders = [['name' => $defaultCashName, 'amount' => '']];
    }
}

// Gather any custom/unlisted tender names from existing tenders so they appear in <select>
$extraTenderOptions = [];
foreach ($existingTenders as $et) {
    if (!empty($et['name'])) {
        $found = false;
        foreach ($tenders as $t) {
            if (strcasecmp($t['tender_name'], $et['name']) === 0) {
                $found = true;
                break;
            }
        }
        if (!$found && !in_array($et['name'], $extraTenderOptions)) {
            $extraTenderOptions[] = $et['name'];
        }
    }
}

// Build the tender options HTML once (reused in JS template)
$tenderOptionsHtml = '<option value="">-- Select Tender --</option>';
foreach ($tenders as $t) {
    $tenderOptionsHtml .= '<option value="' . htmlspecialchars($t['tender_name']) . '">' .
        htmlspecialchars($t['tender_name']) . ' (' . htmlspecialchars($t['tender_code']) . ')' .
        '</option>';
}
foreach ($extraTenderOptions as $extName) {
    $tenderOptionsHtml .= '<option value="' . htmlspecialchars($extName) . '">' .
        htmlspecialchars($extName) . ' (Custom)' .
        '</option>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $pageTitle; ?> - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php $currentPage = 'journal'; require_once __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <a href="entry.php">+ New Entry</a>
            <a href="records.php">View Records</a>
        </div>

        <div class="page-header">
            <h1><?php echo $pageTitle; ?></h1>
            <p>Fill in the transaction details below and save.</p>
        </div>

        <form method="post" action="save.php" id="journalForm">
            <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
            <input type="hidden" name="till_number" value="<?php echo htmlspecialchars($entry['till_number'] !== '' ? $entry['till_number'] : '0000'); ?>">

            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                
                <!-- Left panel: Transaction Details -->
                <div class="panel">
                    <h2>Transaction Details</h2>
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div>
                            <label for="store_number" style="margin-top: 0;">Store</label>
                            <input type="text" id="store_number" name="store_number" value="<?php echo htmlspecialchars($entry['store_number']); ?>" required>
                        </div>
                        <div>
                            <label for="register_number" style="margin-top: 0;">Reg</label>
                            <input type="text" id="register_number" name="register_number" value="<?php echo htmlspecialchars($entry['register_number']); ?>" required>
                        </div>
                        <div>
                            <label for="entry_date" style="margin-top: 0;">Date</label>
                            <input type="date" id="entry_date" name="entry_date" value="<?php echo htmlspecialchars($entry['entry_date']); ?>" required>
                        </div>
                        <div>
                            <label for="entry_time" style="margin-top: 0;">Time</label>
                            <input type="time" id="entry_time" name="entry_time" value="<?php echo htmlspecialchars($entry['entry_time']); ?>" required>
                        </div>
                        <div>
                            <label for="last_trx_number" style="margin-top: 0;">Last Trx #</label>
                            <input type="text" id="last_trx_number" name="last_trx_number" value="<?php echo htmlspecialchars($entry['last_trx_number']); ?>" required>
                        </div>
                        <div>
                            <label for="zread_number" style="margin-top: 0;">Z No.</label>
                            <input type="text" id="zread_number" name="zread_number" value="<?php echo htmlspecialchars($entry['zread_number']); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Right panel: Financial Values -->
                <div class="panel">
                    <h2>Financials</h2>
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div>
                            <label for="old_grand_total" style="margin-top: 0;">Old Grand Total</label>
                            <input type="text" inputmode="decimal" id="old_grand_total" name="old_grand_total" value="<?php echo number_format((float)$entry['old_grand_total'], 2); ?>" required style="text-align: right; font-weight: 500;">
                        </div>
                        <div>
                            <label for="total_vat" style="margin-top: 0;">Total VAT</label>
                            <input type="text" inputmode="decimal" id="total_vat" name="total_vat" value="<?php echo number_format((float)$entry['total_vat'], 2); ?>" required style="text-align: right; font-weight: 500;">
                        </div>
                        <div>
                            <label for="total_non_vat" style="margin-top: 0;">Total Non-VAT</label>
                            <input type="text" inputmode="decimal" id="total_non_vat" name="total_non_vat" value="<?php echo number_format((float)$entry['total_non_vat'], 2); ?>" required style="text-align: right; font-weight: 500;">
                        </div>
                        <div>
                            <label for="daily_sales" style="margin-top: 0; display: flex; justify-content: space-between; align-items: center;">
                                <span>Daily Sales</span>
                                <span style="font-size: 11px; font-weight: normal; color: #0284c7; background: #e0f2fe; padding: 2px 6px; border-radius: 4px;">VAT + Non-VAT</span>
                            </label>
                            <input type="text" id="daily_sales" name="daily_sales" value="<?php echo number_format((float)$entry['daily_sales'], 2); ?>" readonly tabindex="-1" style="background: #f8fafc; color: #0f172a; font-weight: bold; text-align: right; border-color: #cbd5e1; cursor: not-allowed;">
                        </div>
                        <div>
                            <label for="new_grand_total" style="margin-top: 0; display: flex; justify-content: space-between; align-items: center;">
                                <span>New Grand Total</span>
                                <span style="font-size: 11px; font-weight: normal; color: #0284c7; background: #e0f2fe; padding: 2px 6px; border-radius: 4px;">Old + Daily Sales</span>
                            </label>
                            <input type="text" id="new_grand_total" name="new_grand_total" value="<?php echo number_format((float)$entry['new_grand_total'], 2); ?>" readonly tabindex="-1" style="background: #f8fafc; color: #0f172a; font-weight: bold; text-align: right; border-color: #cbd5e1; cursor: not-allowed;">
                        </div>
                        <div id="tender-balance-notice" style="margin-top: 4px; font-size: 12px; line-height: 1.4; padding: 8px 12px; border-radius: 6px; display: none;"></div>
                    </div>
                </div>
            </div>

            <!-- Bottom panel: Tender Breakdown -->
            <div class="panel" style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <h2 style="margin: 0;">Tender Breakdown</h2>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span id="tender-match-badge" style="display: none;"></span>
                        <button type="button" id="auto-balance-btn" onclick="autoBalanceTenders()" class="btn btn-secondary" style="margin: 0; padding: 4px 12px; font-size: 11px; border-radius: 14px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; cursor: pointer;" title="Automatically balance tenders with Daily Sales">⚡ Auto-Fill to Cash</button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                        <thead>
                            <tr>
                                <th style="width: 60%; text-align: left; padding: 8px 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 13px; color: #64748b;">Tender Type</th>
                                <th style="width: 32%; text-align: right; padding: 8px 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 13px; color: #64748b;">Amount</th>
                                <th style="width: 8%; text-align: center; padding: 8px 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 13px; color: #64748b;"></th>
                            </tr>
                        </thead>
                        <tbody id="tender-body">
                            <!-- Dynamic tender rows -->
                        </tbody>
                        <tfoot>
                            <tr style="border-top: 2px solid #cbd5e1; background: #f8fafc; font-weight: bold;">
                                <td style="padding: 10px; text-align: right; color: #475569;">Total Tenders:</td>
                                <td id="total-tender-amount" style="padding: 10px; text-align: right; color: #0f172a; font-size: 14px;">₱0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                <button type="button" id="add-tender-btn" class="btn btn-secondary" style="width: 100%; margin-top: 12px; background: none; border: 1px dashed #2d6cdf; color: #2d6cdf; padding: 10px; border-radius: 20px; cursor: pointer; font-weight: 600;">+ Add Tender Row</button>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="records.php" class="btn btn-secondary" style="margin-top: 0;">Cancel</a>
                <button type="submit" class="btn" style="margin-top: 0; background-color: #2d6cdf;">Save Entry</button>
            </div>
        </form>
    </main>
</div>

<script>
const TENDER_OPTIONS = <?php echo json_encode($tenderOptionsHtml); ?>;
const EXISTING_TENDERS = <?php echo json_encode($existingTenders); ?>;
const DEFAULT_CASH_NAME = <?php echo json_encode($defaultCashName); ?>;

function makeTenderRow(name = '', amount = '', isAutoFilled = false) {
    const tr = document.createElement('tr');
    tr.className = 'tender-row';
    
    // Unique indexing helper
    const getIndex = () => document.querySelectorAll('#tender-body .tender-row').length;
    const idx = getIndex();
    
    // Tender selection input column
    const tdSelect = document.createElement('td');
    tdSelect.style.padding = '8px 10px';
    const select = document.createElement('select');
    select.name = `tender[${idx}][name]`;
    select.required = true;
    select.innerHTML = TENDER_OPTIONS;

    const trimmedName = (name || '').trim();
    if (trimmedName) {
        select.value = trimmedName;
        // If exact match failed, try case-insensitive match across options
        if (select.value !== trimmedName) {
            const lower = trimmedName.toLowerCase();
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value.toLowerCase().trim() === lower) {
                    select.selectedIndex = i;
                    break;
                }
            }
        }
        // If still not matched, add custom option so tender is never blank
        if (select.selectedIndex <= 0) {
            const opt = document.createElement('option');
            opt.value = trimmedName;
            opt.textContent = trimmedName;
            opt.selected = true;
            select.appendChild(opt);
        }
    }
    tdSelect.appendChild(select);
    
    // Amount input column
    const tdAmount = document.createElement('td');
    tdAmount.style.padding = '8px 10px';
    const amountInput = document.createElement('input');
    amountInput.type = 'text';
    amountInput.inputMode = 'decimal';
    amountInput.name = `tender[${idx}][amount]`;
    amountInput.placeholder = '0.00';
    amountInput.style.textAlign = 'right';
    amountInput.style.fontWeight = '500';
    amountInput.value = amount ? formatWithCommas(parseNum(amount), 2) : '';
    if (isAutoFilled) {
        amountInput.dataset.autoFilled = 'true';
    }
    
    amountInput.addEventListener('input', function() {
        delete this.dataset.autoFilled;
    });

    ['input', 'change', 'keyup', 'paste'].forEach(evt => {
        amountInput.addEventListener(evt, calculateDaily);
    });
    amountInput.addEventListener('focus', function() {
        this.select();
    });
    amountInput.addEventListener('blur', function() {
        if (this.value.trim() !== '') {
            this.value = formatWithCommas(parseNum(this.value), 2);
        }
        calculateDaily();
    });
    
    tdAmount.appendChild(amountInput);
    
    // Remove button column
    const tdRemove = document.createElement('td');
    tdRemove.style.padding = '8px 10px';
    tdRemove.style.textAlign = 'center';
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.innerHTML = '✖';
    removeBtn.style.background = 'none';
    removeBtn.style.border = 'none';
    removeBtn.style.color = '#ef4444';
    removeBtn.style.cursor = 'pointer';
    removeBtn.style.fontSize = '16px';
    removeBtn.title = 'Remove Row';
    removeBtn.addEventListener('click', () => {
        if (document.querySelectorAll('#tender-body .tender-row').length > 1) {
            tr.remove();
            reindexTenders();
            calculateDaily();
        } else {
            select.value = DEFAULT_CASH_NAME;
            amountInput.value = '';
            calculateDaily();
        }
    });
    tdRemove.appendChild(removeBtn);
    
    tr.appendChild(tdSelect);
    tr.appendChild(tdAmount);
    tr.appendChild(tdRemove);
    
    return tr;
}

function reindexTenders() {
    const rows = document.querySelectorAll('#tender-body .tender-row');
    rows.forEach((row, idx) => {
        const select = row.querySelector('select');
        const input = row.querySelector('input[name*="[amount]"]');
        if (select) select.name = `tender[${idx}][name]`;
        if (input) input.name = `tender[${idx}][amount]`;
    });
}

const tbody = document.getElementById('tender-body');
EXISTING_TENDERS.forEach(t => {
    tbody.appendChild(makeTenderRow(t.name || '', t.amount || '', true));
});

if (tbody.children.length === 0) {
    tbody.appendChild(makeTenderRow(DEFAULT_CASH_NAME, '', true));
}

document.getElementById('add-tender-btn').addEventListener('click', () => {
    // Check remaining balance to automatically prefill
    const vat = parseNum(document.getElementById('total_vat').value);
    const nonVat = parseNum(document.getElementById('total_non_vat').value);
    const daily = vat + nonVat;
    let currentSum = 0;
    document.querySelectorAll('#tender-body input[name*="[amount]"]').forEach(inp => {
        currentSum += parseNum(inp.value);
    });
    const remaining = Math.max(0, daily - currentSum);
    tbody.appendChild(makeTenderRow(DEFAULT_CASH_NAME, remaining > 0.009 ? remaining : '', true));
    calculateDaily();
});

// Auto balance button logic
function autoBalanceTenders() {
    const vat = parseNum(document.getElementById('total_vat').value);
    const nonVat = parseNum(document.getElementById('total_non_vat').value);
    const daily = vat + nonVat;
    const rows = document.querySelectorAll('#tender-body .tender-row');
    
    if (rows.length <= 1) {
        if (rows.length === 0) {
            tbody.appendChild(makeTenderRow(DEFAULT_CASH_NAME, daily, true));
        } else {
            const sel = rows[0].querySelector('select');
            const inp = rows[0].querySelector('input[name*="[amount]"]');
            if (sel && (!sel.value || sel.selectedIndex <= 0)) {
                sel.value = DEFAULT_CASH_NAME;
            }
            if (inp) {
                inp.value = formatWithCommas(daily, 2);
                inp.dataset.autoFilled = 'true';
            }
        }
    } else {
        // Multi-row: adjust the first row to balance the total
        let otherSum = 0;
        rows.forEach((r, idx) => {
            if (idx > 0) {
                otherSum += parseNum(r.querySelector('input[name*="[amount]"]')?.value);
            }
        });
        const targetFirst = Math.max(0, daily - otherSum);
        const firstInp = rows[0].querySelector('input[name*="[amount]"]');
        if (firstInp) {
            firstInp.value = formatWithCommas(targetFirst, 2);
            firstInp.dataset.autoFilled = 'true';
        }
    }
    calculateDaily();
}

// Helper for parsing string with commas to float number
function parseNum(val) {
    if (val === null || val === undefined) return 0;
    const clean = val.toString().replace(/,/g, '').trim();
    return parseFloat(clean) || 0;
}

// Helper for formatting float number with standard thousands commas
function formatWithCommas(val, decimals = 2) {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

// Dynamic calculations and validation logic
function calculateDaily() {
    const elOldGrand = document.getElementById('old_grand_total');
    const elVat = document.getElementById('total_vat');
    const elNonVat = document.getElementById('total_non_vat');
    const elDaily = document.getElementById('daily_sales');
    const elNewGrand = document.getElementById('new_grand_total');
    
    const oldGrand = parseNum(elOldGrand.value);
    const vat = parseNum(elVat.value);
    const nonVat = parseNum(elNonVat.value);
    const daily = vat + nonVat;
    const newGrand = oldGrand + daily;
    
    // Put comma-formatted numbers directly inside the textboxes
    elDaily.value = formatWithCommas(daily, 2);
    elNewGrand.value = formatWithCommas(newGrand, 2);
    
    // Auto-update tender amount if single row was auto-filled or empty
    const tenderRows = document.querySelectorAll('#tender-body .tender-row');
    if (tenderRows.length === 1) {
        const singleAmountInput = tenderRows[0].querySelector('input[name*="[amount]"]');
        const singleSelect = tenderRows[0].querySelector('select');
        if (singleAmountInput && daily > 0) {
            const curVal = singleAmountInput.value.trim();
            if (curVal === '' || singleAmountInput.dataset.autoFilled === 'true') {
                singleAmountInput.value = formatWithCommas(daily, 2);
                singleAmountInput.dataset.autoFilled = 'true';
                if (singleSelect && (!singleSelect.value || singleSelect.selectedIndex <= 0)) {
                    singleSelect.value = DEFAULT_CASH_NAME;
                }
            }
        }
    }
    
    // Calculate total sum of tenders
    let tenderSum = 0;
    document.querySelectorAll('#tender-body input[name*="[amount]"]').forEach(inp => {
        tenderSum += parseNum(inp.value);
    });
    
    const totalTenderEl = document.getElementById('total-tender-amount');
    if (totalTenderEl) {
        totalTenderEl.textContent = '₱' + formatWithCommas(tenderSum, 2);
    }
    
    const diff = Math.abs(daily - tenderSum);
    const isMismatch = diff > 0.009;
    
    const noticeEl = document.getElementById('tender-balance-notice');
    const badgeEl = document.getElementById('tender-match-badge');
    
    if (isMismatch) {
        // Turn VAT and Non-VAT textboxes RED
        elVat.style.borderColor = '#ef4444';
        elVat.style.backgroundColor = '#fef2f2';
        elVat.style.color = '#b91c1c';
        
        elNonVat.style.borderColor = '#ef4444';
        elNonVat.style.backgroundColor = '#fef2f2';
        elNonVat.style.color = '#b91c1c';
        
        if (noticeEl) {
            noticeEl.style.display = 'block';
            noticeEl.style.backgroundColor = '#fef2f2';
            noticeEl.style.color = '#b91c1c';
            noticeEl.style.border = '1px solid #fecaca';
            noticeEl.innerHTML = `⚠️ <strong>Total VAT + Non-VAT (₱${formatWithCommas(daily)})</strong> does not equal <strong>Total Tenders (₱${formatWithCommas(tenderSum)})</strong>. Difference: ₱${formatWithCommas(diff)}`;
        }
        
        if (badgeEl) {
            badgeEl.style.display = 'inline-block';
            badgeEl.style.backgroundColor = '#fee2e2';
            badgeEl.style.color = '#dc2626';
            badgeEl.style.border = '1px solid #fca5a5';
            badgeEl.style.padding = '2px 8px';
            badgeEl.style.borderRadius = '4px';
            badgeEl.style.fontSize = '11px';
            badgeEl.style.fontWeight = 'bold';
            badgeEl.textContent = '⚠️ Does not match VAT + Non-VAT';
        }
    } else {
        // Restore standard styling
        elVat.style.borderColor = '#cbd5e1';
        elVat.style.backgroundColor = '#ffffff';
        elVat.style.color = 'inherit';
        
        elNonVat.style.borderColor = '#cbd5e1';
        elNonVat.style.backgroundColor = '#ffffff';
        elNonVat.style.color = 'inherit';
        
        if (noticeEl) {
            noticeEl.style.display = 'block';
            noticeEl.style.backgroundColor = '#f0fdf4';
            noticeEl.style.color = '#15803d';
            noticeEl.style.border = '1px solid #bbf7d0';
            noticeEl.innerHTML = `✓ <strong>Balanced:</strong> Total Tenders (₱${formatWithCommas(tenderSum)}) equals Total VAT + Non-VAT (₱${formatWithCommas(daily)})`;
        }
        
        if (badgeEl) {
            badgeEl.style.display = 'inline-block';
            badgeEl.style.backgroundColor = '#dcfce7';
            badgeEl.style.color = '#16a34a';
            badgeEl.style.border = '1px solid #86efac';
            badgeEl.style.padding = '2px 8px';
            badgeEl.style.borderRadius = '4px';
            badgeEl.style.fontSize = '11px';
            badgeEl.style.fontWeight = 'bold';
            badgeEl.textContent = '✓ Matches VAT + Non-VAT';
        }
    }
}

const financialInputIds = ['old_grand_total', 'total_vat', 'total_non_vat'];

financialInputIds.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    
    ['input', 'change', 'keyup', 'paste'].forEach(evt => {
        el.addEventListener(evt, calculateDaily);
    });
    
    el.addEventListener('focus', function() {
        this.select();
    });
    
    el.addEventListener('blur', function() {
        if (this.value.trim() !== '') {
            this.value = formatWithCommas(parseNum(this.value), 2);
        }
        calculateDaily();
    });
});

// Form submit validation to guarantee equality
document.getElementById('journalForm').addEventListener('submit', function(e) {
    const vat = parseNum(document.getElementById('total_vat').value);
    const nonVat = parseNum(document.getElementById('total_non_vat').value);
    const daily = vat + nonVat;
    
    let tenderSum = 0;
    document.querySelectorAll('#tender-body input[name*="[amount]"]').forEach(inp => {
        tenderSum += parseNum(inp.value);
    });
    
    if (Math.abs(daily - tenderSum) > 0.009) {
        e.preventDefault();
        alert('Cannot save: Total VAT + Total Non-VAT (₱' + formatWithCommas(daily) + ') must be equal to Total Tenders (₱' + formatWithCommas(tenderSum) + ').');
        document.getElementById('total_vat').focus();
    }
});

// Compute totals on first load
calculateDaily();
</script>
</body>
</html>
