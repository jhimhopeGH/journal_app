<?php
// ej_printing.php - Electronic Journal Records Management, Search, Filter & Printing
require_once __DIR__ . '/auth.php';
requireNavAccess('ej');
require_once __DIR__ . '/ej_db.php';
$currentPage = 'ej';
$isAdminUser = isAdmin();

$search      = isset($_GET['q']) ? trim($_GET['q']) : '';
$storeFilter = isset($_GET['store']) ? trim($_GET['store']) : '';
$regFilter   = isset($_GET['reg']) ? trim($_GET['reg']) : '';
$from        = isset($_GET['from']) ? trim($_GET['from']) : '';
$to          = isset($_GET['to']) ? trim($_GET['to']) : '';
$saved       = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;
$deleted     = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;
$deletedAll  = isset($_GET['deleted_all']) ? 1 : 0;
$page        = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage     = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [25, 50, 100, 200]) ? (int)$_GET['per_page'] : 25;

// Get counts and paginated rows
$totalRows = countEjEntries($search, $storeFilter, $regFilter, $from, $to);
$totalPages = max(1, ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rows = getEjEntries($search, $storeFilter, $regFilter, $from, $to, $perPage, $offset);

$stores = getAllStoreMasters();
$uniqueRegisters = getUniqueEjRegisters();


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EJ Printing - Electronic Journal</title>
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
        height: 36px;
        box-sizing: border-box;
        padding: 0 16px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        color: #ffffff !important;
        margin: 0 !important;
        line-height: 1;
        vertical-align: middle;
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
    .badge-pill {
        display: inline-block;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 9999px;
        background: #f1f5f9;
        color: #334155;
    }
    .badge-tender {
        background: #dcfce7;
        color: #15803d;
    }
    .badge-member {
        background: #e0e7ff;
        color: #4338ca;
    }
    .items-tooltip {
        max-width: 260px;
        white-space: normal;
        font-size: 12px;
        color: #475569;
        line-height: 1.3;
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
        width: 520px;
        max-width: 95vw;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }
    .modal-header {
        padding: 16px 20px;
        background: #0284c7;
        color: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-header h3 {
        margin: 0;
        font-size: 16px;
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
    .log-terminal {
        background: #0f172a;
        color: #38bdf8;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        padding: 12px;
        border-radius: 6px;
        height: 140px;
        overflow-y: auto;
        white-space: pre-wrap;
        margin-top: 14px;
        display: none;
    }

    /* Pagination button styles */
    .page-nav-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 10px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 16px;
        text-decoration: none;
        box-sizing: border-box;
        transition: all 0.18s;
    }
    .page-nav-btn:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
        transform: translateY(-1px);
    }
    .page-nav-btn.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
        font-weight: bold;
        cursor: default;
    }
    .btn-inactive {
        opacity: 0.4;
        cursor: default !important;
    }
    .btn-inactive:hover {
        opacity: 0.4;
    }
    tr.ej-row {
        cursor: pointer;
        transition: background 0.12s ease;
    }
    tr.ej-row:hover {
        background: #dbeafe !important;
    }
    tr.ej-row.row-selected {
        background: #bfdbfe !important;
        outline: 2px solid #2563eb;
        outline-offset: -2px;
    }
    tr.ej-row.row-selected td {
        font-weight: 600;
        color: #1e3a8a;
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
            <button type="button" class="btn" id="btn_batch_print" style="background: #16a34a;">🖨️ Batch Print Selected</button>
            <button type="button" class="btn btn-inactive" id="btn_print_single" onclick="ejPrintSelected()" style="background: #0ea5e9;" title="Click a row first">🖨️ Print</button>
            <button type="button" class="btn btn-inactive" id="btn_edit_single" onclick="ejEditSelected()" style="background: #7c3aed;" title="Click a row first">✏️ Edit</button>
            <?php if ($isAdminUser): ?>
                <button type="button" class="btn btn-inactive" id="btn_delete_single" onclick="ejDeleteSelected()" style="background: #dc2626;" title="Click a row first">🗑️ Delete</button>
                <button type="button" class="btn" id="btn_open_as400" style="background: #0284c7;">⚡ Import AS400</button>
                <button type="button" class="btn" id="btn_delete_all" style="background: #dc2626; margin-left: auto;">🗑️ Delete All EJ</button>
            <?php endif; ?>
            <span id="selected-row-label" style="font-size:12px; color:#0284c7; font-weight:600;"></span>
        </div>

        <div class="page-header">
            <h1>EJ Printing</h1>
            <p>Browse, search, edit, and reprint Electronic Journal transactions (Database: <code>ej.db</code>).</p>
        </div>

        <?php if ($saved): ?>
            <div class="success">✓ EJ Record #<?php echo $saved; ?> saved successfully.</div>
        <?php endif; ?>
        <?php if ($deleted): ?>
            <div class="success">✓ EJ Record deleted successfully.</div>
        <?php endif; ?>
        <?php if ($deletedAll): ?>
            <div class="success">✓ All Electronic Journal records have been deleted.</div>
        <?php endif; ?>

        <div class="panel">
            <!-- Filter Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                <form class="search-bar" method="GET" action="ej_printing.php" style="margin-bottom:0; flex:1; display:flex; gap:8px; flex-wrap:wrap; max-width:none; align-items:center;">
                    <input type="text" name="q" placeholder="Search by Invoice, Trx, Member, Name, SKU..."
                           value="<?php echo htmlspecialchars($search); ?>" style="flex:2; min-width:180px;">

                    <!-- Store Filter -->
                    <select name="store" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; background:#fff; flex:1; min-width:140px;">
                        <option value="">-- All Stores --</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?php echo htmlspecialchars($s['store_code']); ?>" <?php if ($storeFilter === $s['store_code']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['store_code'] . ' - ' . $s['store_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Register Filter -->
                    <select name="reg" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; background:#fff; flex:1; min-width:120px;">
                        <option value="">-- All Regs --</option>
                        <?php foreach ($uniqueRegisters as $uReg): ?>
                            <option value="<?php echo htmlspecialchars($uReg); ?>" <?php if ($regFilter === $uReg) echo 'selected'; ?>>
                                Reg <?php echo htmlspecialchars($uReg); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($perPage !== 25): ?>
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                    <?php endif; ?>
                    <button type="submit" style="margin-top:0; padding:8px 20px; border-radius:20px; font-weight:600;">Filter</button>
                    <?php if ($search !== '' || $storeFilter !== '' || $regFilter !== ''): ?>
                        <a href="ej_printing.php<?php echo $perPage !== 25 ? '?per_page=' . $perPage : ''; ?>" class="btn btn-secondary" style="margin-top:0; padding:8px 18px; border-radius:20px; text-decoration:none; font-size:13px; display:inline-flex; align-items:center;">Clear</a>
                    <?php endif; ?>
                </form>

                <div style="display:flex; align-items:center; gap:8px; font-size:13px; color:#64748b;">
                    <span>Show:</span>
                    <select onchange="changePerPage(this.value)" style="padding:5px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; background:#fff;">
                        <option value="25" <?php if ($perPage === 25) echo 'selected'; ?>>25</option>
                        <option value="50" <?php if ($perPage === 50) echo 'selected'; ?>>50</option>
                        <option value="100" <?php if ($perPage === 100) echo 'selected'; ?>>100</option>
                        <option value="200" <?php if ($perPage === 200) echo 'selected'; ?>>200</option>
                    </select>
                    <span>entries</span>
                </div>
            </div>

            <form id="batchPrintForm" method="GET" action="ej_print_batch.php">
                <input type="hidden" name="ids" id="batch_ids_input" value="">
            </form>

            <!-- Table -->
            <?php if (empty($rows)): ?>
                <p class="empty-state">No Electronic Journal entries found. Create a <a href="ej_entry.php">New Entry</a> or click <strong>Import AS400</strong>.</p>
            <?php else: ?>
                <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 14px;">
                <table style="font-size: 13px; margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 32px; text-align: center;">
                                <input type="checkbox" id="select_all_checkbox" title="Select All">
                            </th>
                            <th style="width: 60px;">ID</th>
                            <th style="width: 100px;">Date & Time</th>
                            <th style="width: 70px;">Store</th>
                            <th style="width: 60px;">Reg</th>
                            <th>Invoice / Trx #</th>
                            <th>Member / Customer</th>
                            <th style="width: 130px;">Cashier</th>
                            <th>Items</th>
                            <th style="text-align: right; width: 110px;">Total (₱)</th>
                            <th style="width: 180px;">Tender</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <?php
                            $itemsDecoded = json_decode($row['items'] ?? '[]', true);
                            $itemCount = is_array($itemsDecoded) ? count($itemsDecoded) : 0;
                            $firstItemDesc = ($itemCount > 0 && isset($itemsDecoded[0]['description'])) ? $itemsDecoded[0]['description'] : ($itemCount > 0 ? $itemsDecoded[0]['sku'] : 'No items');
                            
                            $tendersDecoded = json_decode($row['tenders'] ?? '[]', true);
                            $tenderSummary = [];
                            if (is_array($tendersDecoded)) {
                                foreach ($tendersDecoded as $td) {
                                    if (!empty($td['name'])) {
                                        $tLabel = $td['name'];
                                        $tCode = strtoupper(trim($td['code'] ?? ''));
                                        $docNo = trim((string)($td['doc_no'] ?? ''));
                                        if ($docNo !== '') {
                                            $cleanDoc = ltrim($docNo, '# ');
                                            if (stripos($cleanDoc, 'GC#') === 0) $cleanDoc = substr($cleanDoc, 3);
                                            elseif (stripos($cleanDoc, 'GC') === 0) $cleanDoc = substr($cleanDoc, 2);
                                            $cleanDoc = ltrim($cleanDoc, '# ');
                                            if ($cleanDoc !== '' && $cleanDoc !== '0') {
                                                if (in_array($tCode, ['GC', 'EG', 'GIFT']) || stripos($tLabel, 'gift') !== false || stripos($tLabel, 'cert') !== false || stripos($tLabel, 'gc') !== false) {
                                                    $tLabel = 'Gift Cert Redeem #     GC#' . $cleanDoc;
                                                } elseif (stripos($tLabel, $cleanDoc) === false) {
                                                    $tLabel .= ' #' . $cleanDoc;
                                                }
                                            }
                                        }
                                        $tenderSummary[] = $tLabel;
                                    }
                                }
                            }
                            $tShown  = array_slice($tenderSummary, 0, 2);
                            $tHidden = array_slice($tenderSummary, 2);
                            $tAllTip = implode(' | ', $tenderSummary);
                            if (empty($tenderSummary)) { $tShown = ['Cash']; $tHidden = []; $tAllTip = 'Cash'; }
                        ?>
                        <tr class="ej-row" data-id="<?php echo (int)$row['id']; ?>" style="cursor:pointer;" onclick="selectEjRow(<?php echo (int)$row['id']; ?>, this)">
                        <td style="text-align: center;" onclick="event.stopPropagation();">
                                <input type="checkbox" class="row-checkbox" value="<?php echo (int)$row['id']; ?>"
                                    onchange="selectEjRow(<?php echo (int)$row['id']; ?>, this.closest('tr'))">
                            </td>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($row['entry_date']); ?></div>
                                <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($row['entry_time']); ?></div>
                            </td>
                            <td><span class="badge-pill"><?php echo htmlspecialchars($row['store_code']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($row['register_number']); ?></strong></td>
                            <td>
                                <div style="font-weight: 600; color: #0284c7;"><?php echo htmlspecialchars($row['invoice_number'] ?: '—'); ?></div>
                                <div style="font-size: 11px; color: #64748b;">Trx: <?php echo htmlspecialchars($row['transaction_number'] ?: '—'); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($row['member_number'])): ?>
                                    <div><span class="badge-pill badge-member"><?php echo htmlspecialchars($row['member_number']); ?></span></div>
                                <?php endif; ?>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($row['customer_name'] ?: 'Walk-in'); ?></div>
                            </td>
                            <td>
                                <strong style="color: #0f172a;"><?php echo htmlspecialchars($row['sales_associate'] ?: '—'); ?></strong>
                                <?php if (!empty($row['associate_id']) && $row['associate_id'] !== $row['sales_associate']): ?>
                                    <div style="font-size: 11px; color: #64748b; font-family: monospace;">ID: <?php echo htmlspecialchars($row['associate_id']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="items-tooltip" title="<?php echo htmlspecialchars($firstItemDesc); ?>">
                                    <span class="badge-pill"><?php echo $itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?></span>
                                    <?php if ($itemCount > 0): ?>
                                        <div style="font-size: 11px; color: #475569; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px;">
                                            <?php echo htmlspecialchars($firstItemDesc); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: bold; color: #0f172a;">
                                ₱<?php echo number_format((float)$row['total_amount'], 2); ?>
                            </td>
                            <td style="width:180px;max-width:180px;overflow:hidden;">
                                <div style="display:flex;flex-wrap:wrap;gap:2px;overflow:hidden;">
                                <?php foreach ($tShown as $tn): ?>
                                    <span class="badge-pill badge-tender" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;display:inline-block;"><?php echo htmlspecialchars($tn); ?></span>
                                <?php endforeach;
                                if (count($tHidden) > 0): ?>
                                    <span title="<?php echo htmlspecialchars($tAllTip); ?>" style="background:#f1f5f9;color:#475569;border-radius:9999px;padding:2px 7px;font-size:11px;font-weight:700;cursor:help;white-space:nowrap;display:inline-block;">+<?php echo count($tHidden); ?> more</span>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($totalRows > 0): ?>
                    <?php
                    $startItem = $offset + 1;
                    $endItem = min($totalRows, $offset + $perPage);
                    $queryParams = [];
                    if ($search !== '') $queryParams['q'] = $search;
                    if ($storeFilter !== '') $queryParams['store'] = $storeFilter;
                    if ($regFilter !== '') $queryParams['reg'] = $regFilter;
                    if ($from !== '') $queryParams['from'] = $from;
                    if ($to !== '') $queryParams['to'] = $to;
                    if ($perPage !== 25) $queryParams['per_page'] = $perPage;
                    
                    function pageUrl($p, $params) {
                        $params['page'] = $p;
                        return 'ej_printing.php?' . http_build_query($params);
                    }
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:18px; padding-top:14px; border-top:1px solid #e2e8f0; font-size:13px; color:#64748b;">
                        <div>
                            Showing <strong><?php echo number_format($startItem); ?></strong> to <strong><?php echo number_format($endItem); ?></strong> of <strong><?php echo number_format($totalRows); ?></strong> total entries
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <div style="display:flex; align-items:center; gap:4px;">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo pageUrl(1, $queryParams); ?>" class="page-nav-btn" title="First Page">« First</a>
                                    <a href="<?php echo pageUrl($page - 1, $queryParams); ?>" class="page-nav-btn">‹ Prev</a>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                if ($startPage > 1) echo '<span style="padding:0 4px;">...</span>';
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <?php if ($i === $page): ?>
                                        <span class="page-nav-btn active"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo pageUrl($i, $queryParams); ?>" class="page-nav-btn"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($endPage < $totalPages) echo '<span style="padding:0 4px;">...</span>'; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?php echo pageUrl($page + 1, $queryParams); ?>" class="page-nav-btn">Next ›</a>
                                    <a href="<?php echo pageUrl($totalPages, $queryParams); ?>" class="page-nav-btn" title="Last Page">Last »</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal: AS400 Import -->
<div class="modal-overlay" id="as400_modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>⚡ Import from AS400 (ODBC DSN: mms_as400)</h3>
            <button type="button" class="modal-close" id="modal_close_as400">&times;</button>
        </div>
        <div class="modal-body">
            <form id="as400ImportForm">
                <div style="margin-bottom: 12px;">
                    <label style="font-size: 12px; display: block;">
                        Store Code:
                        <select name="store_code" required style="width: 100%;">
                            <option value="">-- Select Store --</option>
                            <?php foreach ($stores as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['store_code']); ?>">
                                    <?php echo htmlspecialchars($s['store_code'] . ' - ' . $s['store_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <label style="font-size: 12px; font-weight: 600;">
                        Date <span style="color: #ef4444;">*</span>:
                        <input type="date" name="date_from" required style="width: 100%; padding: 6px 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    </label>

                    <label style="font-size: 12px; font-weight: 600;">
                        Reg. No # <span style="color: #ef4444;">*</span>:
                        <input type="text" name="reg_no" required placeholder="e.g. 104" style="width: 100%; padding: 6px 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    </label>

                    <label style="font-size: 12px; font-weight: 600;">
                        Traxn No. <span style="color: #ef4444;">*</span>:
                        <input type="text" name="traxn_no" required placeholder="e.g. 1521" style="width: 100%; padding: 6px 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    </label>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn" id="btn_run_import" style="padding: 9px 20px; font-weight: bold; background: #0284c7;">
                        🚀 Start AS400 Import
                    </button>
                    <span id="import_spinner" style="display: none; font-size: 13px; color: #0284c7;">⏳ Connecting to AS400...</span>
                </div>

                <div class="log-terminal" id="as400_terminal"></div>
            </form>
        </div>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', () => {
    // Select All Checkbox
    const selectAllCheckbox = document.getElementById('select_all_checkbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const btnBatchPrint = document.getElementById('btn_batch_print');
    const batchPrintForm = document.getElementById('batchPrintForm');
    const batchIdsInput = document.getElementById('batch_ids_input');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
            rowCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
        });
    }

    // Batch Print Action
    if (btnBatchPrint) {
        btnBatchPrint.addEventListener('click', () => {
            const selectedIds = [];
            rowCheckboxes.forEach(cb => { if (cb.checked) selectedIds.push(cb.value); });
            if (selectedIds.length === 0) {
                alert('Please select at least one EJ record to print.');
                return;
            }
            batchIdsInput.value = selectedIds.join(',');
            batchPrintForm.submit();
        });
    }

    // Delete All EJ Action
    const btnDeleteAll = document.getElementById('btn_delete_all');
    if (btnDeleteAll) {
        btnDeleteAll.addEventListener('click', () => {
            if (confirm('⚠️ WARNING: Are you sure you want to DELETE ALL Electronic Journal records in ej.db?\n\nThis action cannot be undone!')) {
                window.location.href = 'ej_delete.php?action=delete_all';
            }
        });
    }

    // AS400 Modal Handling
    const as400Modal = document.getElementById('as400_modal');
    const btnOpenAs400 = document.getElementById('btn_open_as400');
    const modalCloseAs400 = document.getElementById('modal_close_as400');
    const as400Form = document.getElementById('as400ImportForm');
    const as400Terminal = document.getElementById('as400_terminal');
    const importSpinner = document.getElementById('import_spinner');
    const btnRunImport = document.getElementById('btn_run_import');

    if (btnOpenAs400) btnOpenAs400.addEventListener('click', () => as400Modal.classList.add('active'));
    if (modalCloseAs400) modalCloseAs400.addEventListener('click', () => as400Modal.classList.remove('active'));
    if (as400Modal) as400Modal.addEventListener('click', (e) => { if (e.target === as400Modal) as400Modal.classList.remove('active'); });



    // AS400 Form AJAX submit
    if (as400Form) {
        as400Form.addEventListener('submit', async (e) => {
            e.preventDefault();
            btnRunImport.disabled = true;
            importSpinner.style.display = 'inline-block';
            as400Terminal.style.display = 'block';
            as400Terminal.textContent = 'Connecting to AS400 DSN and querying transactions...\n';
            const formData = new FormData(as400Form);
            try {
                const resp = await fetch('import_as400_ajax.php', { method: 'POST', body: formData });
                const data = await resp.json();
                if (data.status === 'success') {
                    as400Terminal.textContent += `\n✓ ${data.message}\nImported: ${data.imported}\nUpdated: ${data.updated}\nSkipped: ${data.skipped}\n`;
                    setTimeout(() => window.location.reload(), 1800);
                } else if (data.status === 'not_found') {
                    as400Terminal.textContent += `\n⚠️ No matching record found:\n${data.message}\n\nPlease verify Store Code, Date, Register #, and Transaction # match the record on AS400.\n`;
                } else {
                    as400Terminal.textContent += `\n❌ Error: ${data.message}\n`;
                    if (data.errors && data.errors.length) as400Terminal.textContent += `\nDiagnostic Details:\n- ` + data.errors.join('\n- ') + `\n`;
                }
            } catch (err) {
                as400Terminal.textContent += `\n❌ Network / Server Error: ${err.message}\n`;
            } finally {
                btnRunImport.disabled = false;
                importSpinner.style.display = 'none';
            }
        });
    }
});

// ── Row selection state ───────────────────────────────────────────────────────
var _ejSelectedId = null;

function selectEjRow(id, trEl) {
    var btnPrint  = document.getElementById('btn_print_single');
    var btnEdit   = document.getElementById('btn_edit_single');
    var btnDelete = document.getElementById('btn_delete_single');
    var label     = document.getElementById('selected-row-label');
    var cb        = trEl.querySelector('.row-checkbox');

    if (_ejSelectedId !== null && String(_ejSelectedId) === String(id)) {
        // Same row clicked — deselect
        trEl.classList.remove('row-selected');
        if (cb) cb.checked = false;
        _ejSelectedId = null;
        [btnPrint, btnEdit, btnDelete].forEach(function(b) {
            if (b) { b.classList.add('btn-inactive'); b.title = 'Click a row first'; }
        });
        if (label) label.textContent = '';
    } else {
        // Deselect all other rows and uncheck their checkboxes
        document.querySelectorAll('tr.ej-row').forEach(function(r) {
            r.classList.remove('row-selected');
            var c = r.querySelector('.row-checkbox');
            if (c) c.checked = false;
        });
        // Select this row
        trEl.classList.add('row-selected');
        if (cb) cb.checked = true;
        _ejSelectedId = id;
        [btnPrint, btnEdit, btnDelete].forEach(function(b) {
            if (b) { b.classList.remove('btn-inactive'); b.title = ''; }
        });
        if (label) label.textContent = '\u2714 Row #' + id + ' selected';
    }
}

function ejPrintSelected() {
    if (!_ejSelectedId) { alert('Please click a row first.'); return; }
    window.location.href = 'ej_print.php?id=' + _ejSelectedId;
}

function ejEditSelected() {
    if (!_ejSelectedId) { alert('Please click a row first.'); return; }
    window.location.href = 'ej_entry.php?id=' + _ejSelectedId;
}

function ejDeleteSelected() {
    if (!_ejSelectedId) { alert('Please click a row first.'); return; }
    if (confirm('Are you sure you want to delete EJ entry #' + _ejSelectedId + '?')) {
        window.location.href = 'ej_delete.php?id=' + _ejSelectedId;
    }
}

function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>
</body>
</html>
