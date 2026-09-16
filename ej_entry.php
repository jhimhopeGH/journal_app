<?php
// ej_entry.php - New / Edit EJ entry input form
require_once __DIR__ . '/auth.php';
requireNavAccess('ej');
require_once __DIR__ . '/ej_db.php';
$currentPage = 'ej';


$today = date('Y-m-d');
$nowTime = date('H:i');

// Load stores, registers, tenders for dropdowns
$stores = getAllStoreMasters();
$registers = getAllRegisterMasters();
$tenders = getAllTenderMasters();
usort($tenders, fn($a, $b) => strcmp($a['tender_name'], $b['tender_name']));

// Load AS400 config
$as400CfgFile = __DIR__ . '/as400_config.json';
$as400Config = ['dsn_name' => 'mms_as400', 'username' => '', 'password' => '', 'library' => 'MMLTSLIB'];
if (file_exists($as400CfgFile)) {
    $c = json_decode(file_get_contents($as400CfgFile), true);
    if (is_array($c)) $as400Config = array_merge($as400Config, $c);
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$entry = [
    'id'                 => 0,
    'entry_date'         => $today,
    'entry_time'         => $nowTime,
    'store_code'         => !empty($stores) ? $stores[0]['store_code'] : '',
    'register_number'    => '',
    'transaction_number' => '',
    'invoice_number'     => '',
    'member_number'      => '',
    'customer_name'      => '',
    'sales_associate'    => '',
    'associate_id'       => '',
    'till_number'        => '0000',
    'items'              => '[]',
    'tenders'            => '[]',
    'subtotal'           => '0.00',
    'vatable_sales'      => '0.00',
    'vat_rate'           => '12',
    'total_vat'          => '0.00',
    'total_non_vat'      => '0.00',
    'total_amount'       => '0.00'
];

if ($editId > 0) {
    $existing = getEjEntryById($editId);
    if ($existing) {
        $entry = $existing;
        if (preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $entry['entry_date'])) {
            $dateObj = DateTime::createFromFormat('m/d/y', $entry['entry_date']);
            if ($dateObj) {
                $entry['entry_date'] = $dateObj->format('Y-m-d');
            }
        }
    }
}
if (empty($entry['sales_associate'])) {
    $entry['sales_associate'] = getRandomCashierName($entry['store_code']);
}

$isEdit = $editId > 0 && isset($existing) && $existing;
$pageTitle = $isEdit ? 'Edit EJ Entry' : 'New EJ Entry';

// Decode items
$existingItems = [];
if (!empty($entry['items'])) {
    $decoded = json_decode($entry['items'], true);
    if (is_array($decoded) && !empty($decoded)) {
        $existingItems = $decoded;
    }
}
if (empty($existingItems)) {
    $existingItems = [['sku' => '', 'description' => '', 'quantity' => 1, 'unit_price' => '', 'amount' => '']];
}

// Decode tenders
$existingTenders = [];
if (!empty($entry['tenders'])) {
    $decoded = json_decode($entry['tenders'], true);
    if (is_array($decoded) && !empty($decoded)) {
        $existingTenders = $decoded;
    }
}
if (empty($existingTenders)) {
    $existingTenders = [['name' => '', 'amount' => '']];
}

// Build tender options HTML for dynamic rows
$tenderOptionsHtml = '<option value="">-- Select Tender --</option>';
foreach ($tenders as $t) {
    $tenderOptionsHtml .= '<option value="' . htmlspecialchars($t['tender_name']) . '" data-code="' . htmlspecialchars($t['tender_code']) . '">' .
        htmlspecialchars($t['tender_name'] . ' (' . $t['tender_code'] . ')') .
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
<style>
    .top-nav {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .top-nav .btn, .top-nav a.btn, .top-nav button.btn {
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #ffffff !important;
        margin-top: 0;
        background: #0284c7;
    }
    .top-nav .btn-secondary, .top-nav a.btn-secondary {
        background: #475569 !important;
        color: #ffffff !important;
    }
    .top-nav .btn:hover {
        opacity: 0.92;
        color: #ffffff !important;
    }
    .section-title {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
        margin-top: 0;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .badge-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        background: #e0f2fe;
        color: #0369a1;
        border-radius: 6px;
        font-size: 12px;
        font-weight: bold;
    }
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .items-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        text-align: left;
    }
    .items-table td {
        padding: 6px 8px;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    .items-table input {
        margin: 0;
        width: 100%;
        box-sizing: border-box;
        font-size: 13px;
        padding: 6px 8px;
    }
    .btn-remove-row {
        background: #fee2e2;
        color: #dc2626;
        border: none;
        border-radius: 4px;
        padding: 6px 10px;
        cursor: pointer;
        font-weight: bold;
        font-size: 13px;
        transition: background 0.15s;
    }
    .btn-remove-row:hover {
        background: #fca5a5;
    }
    .btn-add-row {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px dashed #cbd5e1;
        border-radius: 6px;
        padding: 8px 14px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s;
    }
    .btn-add-row:hover {
        background: #e2e8f0;
        border-color: #94a3b8;
    }
    .summary-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        margin-top: 16px;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 13px;
        color: #475569;
    }
    .summary-row.total-row {
        font-size: 16px;
        font-weight: bold;
        color: #0f172a;
        padding-top: 8px;
        border-top: 1px solid #cbd5e1;
        margin-top: 8px;
        margin-bottom: 0;
    }
    .tender-row {
        display: grid;
        grid-template-columns: 2fr 1.5fr auto;
        gap: 10px;
        align-items: center;
        margin-bottom: 8px;
    }
    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(2px);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal-box {
        background: #ffffff;
        border-radius: 10px;
        width: 480px;
        max-width: 95vw;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }
    .modal-header {
        padding: 16px 20px;
        background: #1e293b;
        color: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-header h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
    }
    .modal-close {
        background: none;
        border: none;
        color: #ffffff;
        font-size: 20px;
        cursor: pointer;
        line-height: 1;
    }
    .modal-body {
        padding: 20px;
    }
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <a href="ej_entry.php" class="btn">+ New Entry</a>
            <a href="ej_printing.php" class="btn btn-secondary">View Records</a>
        </div>

        <div class="page-header">
            <h1><?php echo $pageTitle; ?></h1>
            <p>Enter or update Electronic Journal transaction line items, tenders, and totals.</p>
        </div>

        <form method="post" action="ej_save.php" id="ejForm">
            <input type="hidden" name="id" value="<?php echo (int)$entry['id']; ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                
                <!-- Panel 1: Transaction & Store Details -->
                <div class="panel">
                    <h3 class="section-title"><span class="badge-icon">1</span> Store & Transaction Information</h3>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <label>
                            Store Code <span style="color:#e11d48;">*</span>
                            <select name="store_code" id="store_code" required>
                                <option value="">-- Select Store --</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['store_code']); ?>"
                                        <?php echo ($entry['store_code'] === $s['store_code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['store_code'] . ' - ' . $s['store_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Register Number <span style="color:#e11d48;">*</span>
                            <input type="text" name="register_number" id="register_number" required
                                   value="<?php echo htmlspecialchars($entry['register_number']); ?>"
                                   placeholder="e.g. 101">
                        </label>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 10px;">
                        <label>
                            Date <span style="color:#e11d48;">*</span>
                            <input type="date" name="entry_date" required
                                   value="<?php echo htmlspecialchars($entry['entry_date']); ?>">
                        </label>

                        <label>
                            Time <span style="color:#e11d48;">*</span>
                            <input type="time" name="entry_time" required
                                   value="<?php echo htmlspecialchars($entry['entry_time']); ?>">
                        </label>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 10px;">
                        <label>
                            Invoice Number <span style="color:#e11d48;">*</span>
                            <input type="text" name="invoice_number" required
                                   value="<?php echo htmlspecialchars($entry['invoice_number']); ?>"
                                   placeholder="e.g. INV-2026-0001">
                        </label>

                        <label>
                            Transaction Number <span style="color:#e11d48;">*</span>
                            <input type="text" name="transaction_number" required
                                   value="<?php echo htmlspecialchars($entry['transaction_number']); ?>"
                                   placeholder="e.g. TRX-10023">
                        </label>
                    </div>

                    <div style="margin-top: 10px;">
                        <label>
                            Till Number
                            <input type="text" name="till_number"
                                   value="<?php echo htmlspecialchars($entry['till_number']); ?>"
                                   placeholder="e.g. 0001">
                        </label>
                    </div>
                </div>

                <!-- Panel 2: Customer & Cashier Details -->
                <div class="panel">
                    <h3 class="section-title"><span class="badge-icon">2</span> Customer & Associate Information</h3>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <label>
                            Member Number
                            <input type="text" name="member_number"
                                   value="<?php echo htmlspecialchars($entry['member_number']); ?>"
                                   placeholder="e.g. MBR-88992">
                        </label>

                        <label>
                            Customer / Member Name
                            <input type="text" name="customer_name"
                                   value="<?php echo htmlspecialchars($entry['customer_name']); ?>"
                                   placeholder="e.g. Juan Dela Cruz">
                        </label>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 10px;">
                        <label>
                            Customer Address
                            <input type="text" name="customer_address"
                                   value="<?php echo htmlspecialchars($entry['customer_address'] ?? ''); ?>"
                                   placeholder="e.g. 123 Rizal St., Davao City">
                        </label>

                        <label>
                            Customer TIN
                            <input type="text" name="customer_tin"
                                   value="<?php echo htmlspecialchars($entry['customer_tin'] ?? ''); ?>"
                                   placeholder="e.g. 123-456-789-000">
                        </label>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 10px;">
                        <label>
                            Sales Associate (Cashier)
                            <input type="text" name="sales_associate"
                                   value="<?php echo htmlspecialchars($entry['sales_associate']); ?>"
                                   placeholder="e.g. Maria Santos">
                        </label>

                        <label>
                            Associate ID
                            <input type="text" name="associate_id"
                                   value="<?php echo htmlspecialchars($entry['associate_id']); ?>"
                                   placeholder="e.g. EMP-1042">
                        </label>
                    </div>

                    <!-- Summary box -->
                    <div class="summary-box">
                        <div class="summary-row">
                            <span>Items Subtotal:</span>
                            <strong id="display_subtotal">₱0.00</strong>
                        </div>
                        <div class="summary-row">
                            <span>VATable Sales:</span>
                            <strong id="display_vatable_sales" style="color: #7c3aed;">₱0.00</strong>
                        </div>
                        <div class="summary-row">
                            <span>Total VAT:</span>
                            <strong id="display_vat">₱0.00</strong>
                        </div>
                        <div class="summary-row">
                            <span>Total Non-VAT:</span>
                            <strong id="display_non_vat">₱0.00</strong>
                        </div>
                        <div class="summary-row total-row">
                            <span>TOTAL AMOUNT:</span>
                            <span id="display_total" style="color: #0284c7;">₱0.00</span>
                        </div>
                        <div class="summary-row" style="margin-top: 6px; font-size: 12px;">
                            <span>Tender Total:</span>
                            <strong id="display_tender_total">₱0.00</strong>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Panel 3: Dynamic Multi-SKU Items -->
            <div class="panel" style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <h3 class="section-title" style="margin-bottom: 4px; border: none;"><span class="badge-icon">3</span> Line Items (SKUs)</h3>
                        <span style="font-size: 11px; color: #0284c7; font-weight: 500;">⚡ Type SKU and press Enter/Tab to auto-fetch description from AS400 (<strong>MMLTSLIB.INVMST</strong>)</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn-add-row" id="btn_fetch_all_skus" style="background: #e0f2fe; color: #0369a1; border-color: #7dd3fc;" title="Fetch descriptions for all rows from AS400">⚡ Fetch All Descriptions from AS400</button>
                        <button type="button" class="btn-add-row" id="btn_add_item">+ Add Item Row</button>
                    </div>
                </div>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 200px;">SKU / Barcode</th>
                            <th>Description</th>
                            <th style="width: 100px; text-align: right;">Qty</th>
                            <th style="width: 130px; text-align: right;">Unit Price (₱)</th>
                            <th style="width: 130px; text-align: right;">Amount (₱)</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="items_tbody">
                        <?php foreach ($existingItems as $idx => $item): ?>
                        <tr class="item-row">
                            <td>
                                <div style="display: flex; gap: 4px; align-items: center;">
                                    <input type="text" name="item_sku[]" class="item-sku-input" value="<?php echo htmlspecialchars($item['sku'] ?? ''); ?>" placeholder="e.g. 10000001">
                                    <button type="button" class="btn_lookup_sku_btn" style="padding: 5px 8px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 4px; cursor: pointer; font-size: 12px; line-height: 1;" title="Fetch Description from MMLTSLIB.INVMST">🔍</button>
                                </div>
                            </td>
                            <td>
                                <input type="text" name="item_desc[]" class="item-desc-input" value="<?php echo htmlspecialchars($item['description'] ?? ''); ?>" placeholder="Item name / description">
                            </td>
                            <td>
                                <input type="number" step="any" min="0" name="item_qty[]" class="item-qty" style="text-align: right;" value="<?php echo htmlspecialchars($item['quantity'] ?? 1); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="item_price[]" class="item-price" style="text-align: right;" value="<?php echo htmlspecialchars($item['unit_price'] ?? ''); ?>" placeholder="0.00">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="item_amount[]" class="item-amount" style="text-align: right; background: #f8fafc; font-weight: 600;" value="<?php echo htmlspecialchars($item['amount'] ?? ''); ?>" placeholder="0.00">
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-remove-row btn_remove_item" title="Remove Item">✕</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Panel 4: Payment Tenders & Taxes -->
            <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px; margin-bottom: 24px;">
                
                <!-- Tenders -->
                <div class="panel">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <h3 class="section-title" style="margin-bottom: 0; border: none;"><span class="badge-icon">4</span> Payment Tenders</h3>
                        <button type="button" class="btn-add-row" id="btn_add_tender">+ Add Tender</button>
                    </div>

                    <div id="tenders_container">
                        <?php foreach ($existingTenders as $tIdx => $tItem): ?>
                        <div class="tender-row">
                            <select name="tender_name[]" class="tender-select">
                                <option value="">-- Select Tender --</option>
                                <?php 
                                    $matchedAny = false;
                                    foreach ($tenders as $t):
                                    // Match by name OR code for backward compatibility, including doc # suffix
                                    $savedName = $tItem['name'] ?? '';
                                    $savedCode = $tItem['code'] ?? '';
                                    $isSelected = ($savedName !== '' && (
                                        $savedName === $t['tender_name'] ||
                                        $savedName === $t['tender_code'] ||
                                        strcasecmp($savedName, $t['tender_name']) === 0 ||
                                        strcasecmp($savedName, $t['tender_code']) === 0 ||
                                        stripos($savedName, $t['tender_name']) === 0 ||
                                        ($savedCode !== '' && (
                                            strcasecmp($savedCode, $t['tender_code']) === 0 ||
                                            strcasecmp($savedCode, $t['tender_name']) === 0
                                        ))
                                    ));
                                    if ($isSelected) $matchedAny = true;
                                ?>
                                    <option value="<?php echo htmlspecialchars($t['tender_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($t['tender_code']); ?>"
                                        <?php echo $isSelected ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['tender_name'] . ' (' . $t['tender_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!$matchedAny && !empty($tItem['name'])): ?>
                                    <option value="<?php echo htmlspecialchars($tItem['name']); ?>" selected>
                                        <?php echo htmlspecialchars($tItem['name'] . (!empty($tItem['code']) ? ' (' . $tItem['code'] . ')' : '')); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <input type="number" step="0.01" name="tender_amount[]" class="tender-amount" placeholder="Amount (₱)"
                                   value="<?php echo htmlspecialchars($tItem['amount'] ?? ''); ?>" style="margin: 0;">
                            <button type="button" class="btn-remove-row btn_remove_tender" title="Remove Tender">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tax & Financial Totals Breakdown -->
                <div class="panel">
                    <h3 class="section-title"><span class="badge-icon">5</span> Financial Breakdown</h3>

                    <label>
                        Subtotal (₱)
                        <input type="number" step="0.01" min="0" name="subtotal" id="input_subtotal"
                               value="<?php echo htmlspecialchars($entry['subtotal']); ?>" placeholder="0.00">
                    </label>

                    <div style="margin-top: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <label style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #0f172a;">
                                VAT Rate (%):
                                <input type="number" step="0.01" min="0" max="100" name="vat_rate" id="input_vat_rate"
                                       style="width: 80px; margin: 0; text-align: center; font-weight: 700; color: #0369a1; border-color: #7dd3fc;"
                                       value="<?php echo htmlspecialchars($entry['vat_rate'] ?? '12'); ?>" placeholder="12">
                            </label>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" onclick="setVatRate(12)" style="padding: 4px 10px; border-radius: 4px; border: 1px solid #cbd5e1; background:#e0f2fe; color:#0369a1; font-size:12px; font-weight:600; cursor:pointer;">12%</button>
                                <button type="button" onclick="setVatRate(0)"  style="padding: 4px 10px; border-radius: 4px; border: 1px solid #cbd5e1; background:#fef9c3; color:#854d0e; font-size:12px; font-weight:600; cursor:pointer;">0% (No VAT)</button>
                            </div>
                            <span style="font-size:11px; color:#64748b; margin-left: auto;">Dept. stores: set to 0%</span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                        <label>
                            <span style="color:#7c3aed; font-weight:600;">VATable Sales (₱)</span>
                            <small style="display:block; font-size:10px; color:#94a3b8; margin-bottom:2px;">Auto: Total Amount − Total VAT</small>
                            <input type="number" step="0.01" name="vatable_sales" id="input_vatable_sales"
                                   readonly
                                   style="border-color: #a78bfa; background: #f5f3ff; color: #6d28d9; font-weight:600; cursor:default;"
                                   value="<?php echo htmlspecialchars($entry['vatable_sales'] ?? '0.00'); ?>" placeholder="0.00">
                        </label>

                        <label>
                            <span style="color:#0369a1; font-weight:600;">Total VAT (₱)</span>
                            <small style="display:block; font-size:10px; color:#94a3b8; margin-bottom:2px;">Auto: Total × Rate ÷ (100 + Rate)</small>
                            <input type="number" step="0.01" name="total_vat" id="input_total_vat"
                                   readonly
                                   style="background: #f0f9ff; color: #0369a1; font-weight:600; cursor:default; border-color: #7dd3fc;"
                                   value="<?php echo htmlspecialchars($entry['total_vat']); ?>" placeholder="0.00">
                        </label>
                    </div>

                    <div style="margin-top: 10px;">
                        <label>
                            Total Non-VAT (₱)
                            <input type="number" step="0.01" min="0" name="total_non_vat" id="input_total_non_vat"
                                   value="<?php echo htmlspecialchars($entry['total_non_vat']); ?>" placeholder="0.00">
                        </label>
                    </div>

                    <div style="margin-top: 10px;">
                        <label>
                            Total Amount (₱)
                            <input type="number" step="0.01" min="0" name="total_amount" id="input_total_amount"
                                   style="font-size: 16px; font-weight: bold; color: #0284c7;"
                                   value="<?php echo htmlspecialchars($entry['total_amount']); ?>" placeholder="0.00">
                        </label>
                    </div>
                </div>

            </div>

            <!-- Submit Buttons -->
            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn" style="padding: 12px 28px; font-size: 15px; font-weight: bold;">
                    💾 <?php echo $isEdit ? 'Update EJ Entry' : 'Save EJ Entry'; ?>
                </button>
                <a href="ej_printing.php" class="btn btn-secondary" style="padding: 12px 20px;">Cancel</a>
            </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const itemsTbody = document.getElementById('items_tbody');
    const btnAddItem = document.getElementById('btn_add_item');
    const tendersContainer = document.getElementById('tenders_container');
    const btnAddTender = document.getElementById('btn_add_tender');

    const inputSubtotal = document.getElementById('input_subtotal');
    const inputVatRate = document.getElementById('input_vat_rate');
    const inputVatableSales = document.getElementById('input_vatable_sales');
    const inputVat = document.getElementById('input_total_vat');
    const inputNonVat = document.getElementById('input_total_non_vat');
    const inputTotalAmount = document.getElementById('input_total_amount');

    const displaySubtotal = document.getElementById('display_subtotal');
    const displayVatableSales = document.getElementById('display_vatable_sales');
    const displayVat = document.getElementById('display_vat');
    const displayNonVat = document.getElementById('display_non_vat');
    const displayTotal = document.getElementById('display_total');
    const displayTenderTotal = document.getElementById('display_tender_total');

    const tenderOptionsHtml = `<?php echo addslashes($tenderOptionsHtml); ?>`;

    function recalculate() {
        let totalItemsAmt = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const amtInput = row.querySelector('.item-amount');
            
            // If user typed price or qty, update amount
            if (qty > 0 && price >= 0) {
                const rowAmt = Math.round((qty * price) * 100) / 100;
                amtInput.value = rowAmt.toFixed(2);
                totalItemsAmt += rowAmt;
            } else {
                totalItemsAmt += parseFloat(amtInput.value) || 0;
            }
        });

        // Update subtotal if items exist
        if (totalItemsAmt > 0 || document.querySelectorAll('.item-row').length > 0) {
            inputSubtotal.value = totalItemsAmt.toFixed(2);
            displaySubtotal.textContent = '₱' + totalItemsAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        const subtotal = parseFloat(inputSubtotal.value) || 0;
        const nonVat = parseFloat(inputNonVat.value) || 0;

        let total = subtotal;
        if (nonVat > 0 && subtotal === 0) total = nonVat;

        // Auto-calculate Total VAT = total * rate / (100 + rate)
        const vatRate = Math.max(0, parseFloat(inputVatRate.value) || 0);
        const computedVat = vatRate > 0
            ? Math.round((total * vatRate / (100 + vatRate)) * 100) / 100
            : 0;
        inputVat.value = computedVat.toFixed(2);

        // Auto-calculate VATable Sales = Total Amount - Total VAT
        const vatableSales = Math.max(0, Math.round((total - computedVat) * 100) / 100);
        inputVatableSales.value = vatableSales.toFixed(2);

        inputTotalAmount.value = total.toFixed(2);
        displayVatableSales.textContent = '₱' + vatableSales.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        displayVat.textContent = '₱' + computedVat.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        displayNonVat.textContent = '₱' + nonVat.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        displayTotal.textContent = '₱' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Calculate tenders total
        let tenderSum = 0;
        document.querySelectorAll('.tender-amount').forEach(inp => {
            tenderSum += parseFloat(inp.value) || 0;
        });
        displayTenderTotal.textContent = '₱' + tenderSum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        if (total > 0 && Math.abs(tenderSum - total) < 0.01) {
            displayTenderTotal.style.color = '#16a34a';
        } else {
            displayTenderTotal.style.color = '#dc2626';
        }
    }

    // Add Item Row
    btnAddItem.addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
            <td>
                <div style="display: flex; gap: 4px; align-items: center;">
                    <input type="text" name="item_sku[]" class="item-sku-input" placeholder="e.g. 10000001">
                    <button type="button" class="btn_lookup_sku_btn" style="padding: 5px 8px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 4px; cursor: pointer; font-size: 12px; line-height: 1;" title="Fetch Description from MMLTSLIB.INVMST">🔍</button>
                </div>
            </td>
            <td><input type="text" name="item_desc[]" class="item-desc-input" placeholder="Item name / description"></td>
            <td><input type="number" step="any" min="0" name="item_qty[]" class="item-qty" style="text-align: right;" value="1"></td>
            <td><input type="number" step="0.01" min="0" name="item_price[]" class="item-price" style="text-align: right;" placeholder="0.00"></td>
            <td><input type="number" step="0.01" min="0" name="item_amount[]" class="item-amount" style="text-align: right; background: #f8fafc; font-weight: 600;" placeholder="0.00"></td>
            <td style="text-align: center;"><button type="button" class="btn-remove-row btn_remove_item" title="Remove Item">✕</button></td>
        `;
        itemsTbody.appendChild(tr);
        recalculate();
    });

    // Remove Item Row
    itemsTbody.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn_remove_item')) {
            if (itemsTbody.querySelectorAll('.item-row').length > 1) {
                e.target.closest('tr').remove();
                recalculate();
            } else {
                // Clear row
                const row = e.target.closest('tr');
                row.querySelectorAll('input').forEach(i => i.value = '');
                row.querySelector('.item-qty').value = '1';
                recalculate();
            }
        } else if (e.target.classList.contains('btn_lookup_sku_btn') || e.target.closest('.btn_lookup_sku_btn')) {
            const row = e.target.closest('.item-row');
            if (row) {
                const skuInput = row.querySelector('.item-sku-input');
                if (skuInput) lookupSku(skuInput);
            }
        }
    });

    // AS400 SKU Description Auto-Lookup
    async function lookupSku(skuInput) {
        const sku = skuInput.value.trim();
        if (!sku) return;

        const row = skuInput.closest('.item-row');
        if (!row) return;

        const descInput = row.querySelector('.item-desc-input');
        const priceInput = row.querySelector('.item-price');
        if (!descInput) return;

        const originalPlaceholder = descInput.placeholder;
        descInput.placeholder = '⏳ Fetching from MMLTSLIB.INVMST...';
        descInput.style.backgroundColor = '#f0f9ff';

        try {
            const resp = await fetch('lookup_sku_ajax.php?sku=' + encodeURIComponent(sku));
            const data = await resp.json();

            if (data.status === 'success' && data.found && data.description) {
                descInput.value = data.description;
                descInput.style.backgroundColor = '#f0fdf4';
                descInput.style.borderColor = '#16a34a';

                if (priceInput && (!priceInput.value || parseFloat(priceInput.value) === 0) && data.price && data.price > 0) {
                    priceInput.value = parseFloat(data.price).toFixed(2);
                }
                recalculate();

                setTimeout(() => {
                    descInput.style.backgroundColor = '';
                    descInput.style.borderColor = '';
                    descInput.placeholder = 'Item name / description';
                }, 1500);
            } else if (data.status === 'error' && data.message && (data.message.includes('User ID is invalid') || data.message.includes('Connection failed'))) {
                descInput.style.backgroundColor = '#fff1f2';
                descInput.placeholder = 'AS400 login required';
                alert('⚠️ AS400 credentials required to look up SKU descriptions.\n\nPlease check "⚙️ AS400 Connection Settings" in the top bar to verify your credentials.');
                as400ConfigModal.classList.add('active');
            } else {
                descInput.style.backgroundColor = '';
                descInput.placeholder = originalPlaceholder;
            }
        } catch (err) {
            console.error('SKU lookup error:', err);
            descInput.style.backgroundColor = '';
            descInput.placeholder = originalPlaceholder;
        }
    }

    // Fetch All SKUs button
    const btnFetchAllSkus = document.getElementById('btn_fetch_all_skus');
    if (btnFetchAllSkus) {
        btnFetchAllSkus.addEventListener('click', async () => {
            const skuInputs = Array.from(document.querySelectorAll('.item-sku-input')).filter(inp => inp.value.trim() !== '');
            if (skuInputs.length === 0) {
                alert('Please enter at least one SKU first.');
                return;
            }
            btnFetchAllSkus.disabled = true;
            btnFetchAllSkus.textContent = `⏳ Fetching ${skuInputs.length} SKU(s)...`;
            for (const input of skuInputs) {
                await lookupSku(input);
            }
            btnFetchAllSkus.disabled = false;
            btnFetchAllSkus.textContent = '⚡ Fetch All Descriptions from AS400';
        });
    }

    // Trigger SKU lookup on Enter key, tab, change or blur
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.classList.contains('item-sku-input')) {
            e.preventDefault();
            lookupSku(e.target);
            const row = e.target.closest('.item-row');
            if (row) {
                const qtyInput = row.querySelector('.item-qty');
                if (qtyInput) qtyInput.focus();
            }
        }
    });

    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('item-sku-input')) {
            lookupSku(e.target);
        }
    });

    document.addEventListener('blur', (e) => {
        if (e.target.classList && e.target.classList.contains('item-sku-input')) {
            lookupSku(e.target);
        }
    }, true);

    // Add Tender Row
    btnAddTender.addEventListener('click', () => {
        const div = document.createElement('div');
        div.className = 'tender-row';
        div.innerHTML = `
            <select name="tender_name[]" class="tender-select">${tenderOptionsHtml}</select>
            <input type="number" step="0.01" name="tender_amount[]" class="tender-amount" placeholder="Amount (₱)" style="margin: 0;">
            <button type="button" class="btn-remove-row btn_remove_tender" title="Remove Tender">✕</button>
        `;
        tendersContainer.appendChild(div);
        recalculate();
    });

    // Remove Tender Row
    tendersContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn_remove_tender')) {
            if (tendersContainer.querySelectorAll('.tender-row').length > 1) {
                e.target.closest('.tender-row').remove();
                recalculate();
            } else {
                const row = e.target.closest('.tender-row');
                row.querySelector('.tender-select').value = '';
                row.querySelector('.tender-amount').value = '';
                recalculate();
            }
        }
    });

    // Event listeners on inputs for auto calculation
    document.addEventListener('input', (e) => {
        if (e.target.classList.contains('item-qty') ||
            e.target.classList.contains('item-price') ||
            e.target.classList.contains('item-amount') ||
            e.target.classList.contains('tender-amount') ||
            e.target.id === 'input_subtotal' ||
            e.target.id === 'input_vat_rate' ||
            e.target.id === 'input_total_non_vat' ||
            e.target.id === 'input_total_amount') {
            recalculate();
        }
    });

    // Quick-set VAT rate buttons
    window.setVatRate = function(rate) {
        inputVatRate.value = rate;
        recalculate();
    };

    recalculate();
});
</script>
</body>
</html>
