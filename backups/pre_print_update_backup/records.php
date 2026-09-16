<?php
// records.php - Lists saved entries, supports simple search, links to reprint
require_once __DIR__ . '/auth.php';
requireNavAccess('journal');
require_once __DIR__ . '/raw_print_service.php';

$currentPage = 'journal';
$isAdminUser = isAdmin();
$rawPrinters      = getAvailableRawPrinters();
$preferredPrinter = getPreferredThermalPrinter($rawPrinters);

$search      = isset($_GET['q']) ? trim($_GET['q']) : '';
$storeFilter = isset($_GET['store']) ? trim($_GET['store']) : '';
$regFilter   = isset($_GET['reg']) ? trim($_GET['reg']) : '';
$saved       = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;
$deleted     = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;
$deletedAll  = isset($_GET['deleted_all']) ? 1 : 0;
$page        = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage     = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [25, 50, 100, 200]) ? (int)$_GET['per_page'] : 25;

// Build query conditions
$whereClauses = [];
$queryParams = [];

if ($search !== '') {
    $whereClauses[] = "(store_number LIKE :q OR register_number LIKE :q OR entry_date LIKE :q OR zread_number LIKE :q OR last_trx_number LIKE :q)";
    $queryParams[':q'] = '%' . $search . '%';
}
if ($storeFilter !== '') {
    $whereClauses[] = "store_number = :store";
    $queryParams[':store'] = $storeFilter;
}
if ($regFilter !== '') {
    $whereClauses[] = "register_number = :reg";
    $queryParams[':reg'] = $regFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Count matches
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM entries $whereSql");
$countStmt->execute($queryParams);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM entries $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($queryParams as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stores = getAllStoreMasters();

// Get unique registers for filter dropdown
$uniqueRegsStmt = $pdo->query("SELECT DISTINCT register_number FROM entries WHERE register_number != '' ORDER BY register_number ASC");
$uniqueRegisters = $uniqueRegsStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Records</title>
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
        padding: 8px 16px; font-size: 13px; font-weight: 600; text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px; color: #ffffff !important;
        margin-top: 0; background: #0284c7; border-radius: 20px; border: none; cursor: pointer;
        transition: all 0.18s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .top-nav .btn-secondary, .top-nav a.btn-secondary { background: #475569 !important; color: #ffffff !important; }
    .top-nav .btn:hover { opacity: 0.88; transform: translateY(-1px); color: #ffffff !important; }
    .top-nav .divider {
        color: #cbd5e1;
        font-size: 14px;
        margin: 0 4px;
        user-select: none;
    }
    .top-nav .action-group {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-left: auto;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
    }
    .top-nav .action-group.visible {
        opacity: 1;
        pointer-events: auto;
    }
    .top-nav .action-group .selected-label {
        font-size: 12px;
        color: #475569;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        padding: 4px 12px;
        border-radius: 16px;
        white-space: nowrap;
    }
    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: none;
        white-space: nowrap;
        transition: all 0.18s ease;
        margin-top: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.18);
    }
    .action-btn:active {
        transform: translateY(0);
    }
    .action-btn.action-btn-reprint { background: #2d6cdf; color: #fff !important; }
    .action-btn.action-btn-reprint:hover { background: #1f57bd; }
    .action-btn.action-btn-direct { background: #16a34a; color: #fff !important; }
    .action-btn.action-btn-direct:hover { background: #15803d; }
    .action-btn.action-btn-direct:disabled { background: #86efac; cursor: not-allowed; }
    .action-btn.action-btn-edit { background: #6c757d; color: #fff !important; }
    .action-btn.action-btn-edit:hover { background: #565e64; }
    .action-btn.action-btn-delete { background: #ef4444; color: #fff !important; }
    .action-btn.action-btn-delete:hover { background: #dc2626; }
    .action-btn.action-btn-clear { background: none; border: 1px solid #cbd5e1; color: #64748b !important; border-radius: 20px; }
    .action-btn.action-btn-clear:hover { background: #f1f5f9; color: #334155 !important; }

    /* Table & Cell Beautification with Alternating Row Colors */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        background: #ffffff;
    }
    th {
        background: #e2e8f0 !important;
        color: #0f172a;
        font-weight: 700;
        border: 1px solid #cbd5e1 !important;
        padding: 10px 12px !important;
        font-size: 12px;
        letter-spacing: 0.3px;
    }
    td {
        border: 1px solid #cbd5e1 !important;
        padding: 9px 12px !important;
        color: #1e293b;
        font-size: 13px;
    }
    /* Alternating row colors (white & defined light grey) */
    tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }
    tbody tr:nth-child(even) {
        background-color: #e9ecef;
    }
    tbody tr {
        cursor: pointer;
        transition: background 0.12s ease;
    }
    tbody tr:hover {
        background-color: #dbeafe !important;
    }
    tbody tr.selected-row {
        background-color: #bfdbfe !important;
        outline: 2px solid #2563eb;
        outline-offset: -2px;
    }
    tbody tr.selected-row td {
        font-weight: 600;
        color: #1e3a8a;
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
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <a href="entry.php" class="btn">+ New Entry</a>
                <a href="records.php" class="btn btn-secondary">View Records</a>
                <?php if ($isAdminUser): ?>
                    <span class="divider">|</span>
                    <button type="button" onclick="openImportModal()" style="margin:0; padding:5px 12px; font-size:12px; background:#0ea5e9; color:#fff; border-radius:18px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 5px rgba(14,165,233,0.25);">📥 Import Topspeed (.tps)</button>
                    <span class="divider">|</span>
                    <button type="button" id="btn-recalc-totals" onclick="recalculateAllTotals()" style="margin:0; padding:5px 12px; font-size:12px; background:#6366f1; color:#fff; border-radius:18px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 5px rgba(99,102,241,0.25);">🔄 Recalculate Totals</button>
                    <span class="divider">|</span>
                    <button type="button" onclick="confirmDeleteAll()" style="margin:0; padding:5px 12px; font-size:12px; background:#ef4444; color:#fff; border-radius:18px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 5px rgba(239,68,68,0.25);">🗑️ Delete All Records</button>
                <?php endif; ?>
                <label style="margin:0; font-size:12px; font-weight:600; color:#334155;">🖨️ Direct Printer:</label>
                <select id="zread_direct_printer_select" style="padding:5px 10px; font-size:12px; border:1px solid #cbd5e1; border-radius:18px; background:#fff; cursor:pointer;">
                    <?php if (empty($rawPrinters)): ?>
                        <option value="EPSON TM-T82 Receipt">🖨️ EPSON TM-T82 Receipt (Default)</option>
                    <?php else: ?>
                        <?php foreach ($rawPrinters as $p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>" <?php if ($p === $preferredPrinter) echo 'selected'; ?>>🖨️ <?php echo htmlspecialchars($p); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Action buttons — appear when a row is selected or multiple checked -->
            <div class="action-group" id="action-group">
                <span class="selected-label" id="selected-label">—</span>
                <button id="btn-batch-print" type="button" onclick="batchPrintSelected()" class="action-btn action-btn-reprint" style="display:none; background:#7c3aed;">🖨️ Batch Print (<span id="checked-count">0</span>)</button>
                <button id="btn-batch-direct" type="button" onclick="batchDirectPrintSelected()" class="action-btn action-btn-direct" style="display:none;">⚡ Direct (<span id="checked-count-direct">0</span>)</button>
                <a id="btn-reprint" href="#" onclick="openReprint(event)" class="action-btn action-btn-reprint">🖨️ Reprint</a>
                <button id="btn-direct" type="button" onclick="quickZreadDirectPrint(selectedId, this)" class="action-btn action-btn-direct" style="display:none;">⚡ Direct</button>
                <a id="btn-edit" href="#" class="action-btn action-btn-edit">✏️ Edit</a>
                <?php if ($isAdminUser): ?>
                    <button id="btn-delete" class="action-btn action-btn-delete" onclick="confirmDelete()">🗑️ Delete</button>
                <?php endif; ?>
                <button class="action-btn action-btn-clear" onclick="clearSelection()">✕ Deselect</button>
            </div>
        </div>

        <!-- Topspeed Import Modal -->
        <div id="import-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:8px; width:440px; max-width:90%; padding:24px; box-shadow:0 10px 25px rgba(0,0,0,0.2); position:relative;">
                <h2 style="margin-top:0; font-size:18px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    📥 Import TopSpeed Database
                </h2>
                <p style="font-size:13px; color:#64748b; margin-top:0; margin-bottom:16px;">
                    Since the TopSpeed <code>SALESUMM.tps</code> file doesn't have a store code, please select or specify the Store Code to assign to the imported records:
                </p>

                <form id="import-tps-form" onsubmit="submitTpsImport(event)">
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12px; font-weight:bold; color:#334155; margin-bottom:4px;">Store Code / Store Master *</label>
                        <select id="modal-store-select" onchange="onStoreSelectChange(this)" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; margin-bottom:6px; background:#fff;">
                            <option value="">-- Select from Store Master --</option>
                            <?php foreach ($stores as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['store_code']); ?>" data-name="<?php echo htmlspecialchars($s['store_name']); ?>">
                                    <?php echo htmlspecialchars($s['store_code'] . ' - ' . $s['store_name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__custom__">-- Or enter custom store code --</option>
                        </select>
                        <input type="text" id="modal-store-code" placeholder="e.g. 12027" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; box-sizing:border-box;">
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-size:12px; font-weight:bold; color:#334155; margin-bottom:4px;">ODBC Data Source Name (DSN)</label>
                        <input type="text" id="modal-dsn-name" value="Salessum" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; box-sizing:border-box;">
                        <div style="font-size:11px; color:#64748b; margin-top:3px;">Configured TopSpeed 32-bit ODBC DSN (default: <code>Salessum</code>).</div>
                    </div>

                    <div id="import-status-msg" style="display:none; font-size:13px; padding:10px 12px; border-radius:4px; margin-bottom:14px;"></div>

                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" class="btn-secondary" onclick="closeImportModal()" style="margin:0; padding:8px 18px; font-size:13px; border-radius:20px;">Cancel</button>
                        <button type="submit" id="btn-start-import" style="margin:0; padding:8px 20px; font-size:13px; font-weight:bold; background:#0ea5e9; border-radius:20px;">Start Import</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="page-header">
            <h1>Journal Records</h1>
            <p>Filter by store or register, select entries with checkboxes, or click any row to reprint.</p>
        </div>

        <?php if ($saved): ?>
            <div class="success">Entry #<?php echo $saved; ?> saved successfully.</div>
        <?php endif; ?>
        
        <?php if ($deleted): ?>
            <div class="success" style="background: #fdecea; color: #a33; border: 1px solid #fca5a5;">Entry #<?php echo $deleted; ?> deleted successfully.</div>
        <?php endif; ?>

        <?php if ($deletedAll): ?>
            <div class="success" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; font-weight: 500;">✓ All journal records have been deleted successfully.</div>
        <?php endif; ?>

        <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
            <form class="search-bar" method="GET" action="records.php" style="margin-bottom:0; flex:1; display:flex; gap:8px; flex-wrap:wrap; max-width:none;">
                <input type="text" name="q" placeholder="Search by date, Z-read, last trx..."
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
                    <a href="records.php<?php echo $perPage !== 25 ? '?per_page=' . $perPage : ''; ?>" class="btn btn-secondary" style="margin-top:0; padding:8px 18px; border-radius:20px; text-decoration:none; font-size:13px; display:inline-flex; align-items:center;">Clear</a>
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

        <div style="overflow-x: auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 14px;">
        <table style="margin-top: 0; border: none;">
        <thead>
            <tr>
                <th style="width:36px; text-align:center;"><input type="checkbox" id="check-all" onclick="toggleCheckAll(this)" title="Select All on Page" style="cursor:pointer;"></th>
                <th>ID</th>
                <th>Store</th>
                <th>Register</th>
                <th>Date</th>
                <th>Time</th>
                <th>Z-Read</th>
                <th>Last Trx #</th>
                <th>Tenders</th>
                <th>Total VAT</th>
                <th>Total Non-VAT</th>
                <th>Daily Sales</th>
                <th>Old Grand Total</th>
                <th>New Grand Total</th>
            </tr>
        </thead>
        <tbody id="records-tbody">
            <?php if (empty($rows)): ?>
                <tr><td colspan="14" style="text-align:center; padding:20px; color:#888;">No entries found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr
                    data-id="<?php echo (int)$row['id']; ?>"
                    data-label="Entry #<?php echo (int)$row['id']; ?> — <?php echo htmlspecialchars($row['store_number']); ?> / <?php echo htmlspecialchars($row['entry_date']); ?>"
                    onclick="onRowClick(event, this)"
                >
                    <td style="text-align:center;" onclick="event.stopPropagation();">
                        <input type="checkbox" class="row-checkbox" value="<?php echo (int)$row['id']; ?>" onchange="onCheckboxChange()" style="cursor:pointer;">
                    </td>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['store_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['register_number']); ?></td>
                    <td>
                        <?php 
                        $dStr = $row['entry_date'];
                        $ts = strtotime($dStr);
                        echo htmlspecialchars($ts !== false ? date('m/d/y', $ts) : $dStr); 
                        ?>
                    </td>
                    <td>
                        <?php 
                        $tStr = trim($row['entry_time'] ?? '');
                        if ($tStr === '' || $tStr === '00:00:00' || $tStr === '00:00') {
                            $min = (int)(abs(crc32($row['id'] . '_m')) % 60);
                            $sec = (int)(abs(crc32($row['id'] . '_s')) % 60);
                            $tStr = sprintf('20:%02d:%02d', $min, $sec);
                        }
                        $ts = strtotime($tStr);
                        $formatted24 = ($ts !== false) ? date('H:i', $ts) : $tStr;
                        $formatted12 = ($ts !== false) ? date('g:i A', $ts) : '';
                        ?>
                        <span title="<?php echo htmlspecialchars($formatted12); ?>"><?php echo htmlspecialchars($formatted24); ?></span>
                    </td>
                    <td>
                        <?php 
                        $zVal = trim($row['zread_number']);
                        echo htmlspecialchars(is_numeric($zVal) ? str_pad($zVal, 8, '0', STR_PAD_LEFT) : $zVal);
                        ?>
                    </td>
                    <td>
                        <?php 
                        $lVal = trim($row['last_trx_number']);
                        echo htmlspecialchars(is_numeric($lVal) ? str_pad($lVal, 8, '0', STR_PAD_LEFT) : $lVal);
                        ?>
                    </td>
                    <td style="width:180px;max-width:180px;overflow:hidden;">
                        <?php 
                        $decoded = normalizeAndSortTenders($row['tender'] ?? '');
                        $tParts = [];
                        if (is_array($decoded)) {
                            foreach ($decoded as $tRow) {
                                if (isset($tRow['name']) && $tRow['name'] !== '') {
                                    $tParts[] = htmlspecialchars($tRow['name']) . ': ' . number_format((float)($tRow['amount'] ?? 0), 2);
                                }
                            }
                        } else {
                            $tParts = $row['tender'] !== '' ? [htmlspecialchars($row['tender'])] : [];
                        }
                        $maxShow = 2;
                        $shown   = array_slice($tParts, 0, $maxShow);
                        $hidden  = array_slice($tParts, $maxShow);
                        $allTip  = implode(' | ', $tParts);
                        ?>
                        <div style="display:flex;flex-wrap:wrap;gap:2px;overflow:hidden;">
                        <?php foreach ($shown as $tp): ?>
                            <span style="background:#ede9fe;color:#5b21b6;border-radius:4px;padding:1px 6px;font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;display:inline-block;"><?php echo $tp; ?></span>
                        <?php endforeach;
                        if (count($hidden) > 0): ?>
                            <span title="<?php echo htmlspecialchars($allTip); ?>" style="background:#f1f5f9;color:#475569;border-radius:4px;padding:1px 6px;font-size:11px;font-weight:700;cursor:help;white-space:nowrap;display:inline-block;">+<?php echo count($hidden); ?> more</span>
                        <?php endif; ?>
                        </div>
                    </td>
                    <td style="text-align: right; white-space: nowrap;"><?php echo number_format((float)$row['total_vat'], 2); ?></td>
                    <td style="text-align: right; white-space: nowrap;"><?php echo number_format((float)$row['total_non_vat'], 2); ?></td>
                    <td style="text-align: right; white-space: nowrap; font-weight: bold;"><?php echo number_format((float)$row['daily_sales'], 2); ?></td>
                    <td style="text-align: right; white-space: nowrap;"><?php echo number_format((float)$row['old_grand_total'], 2); ?></td>
                    <td style="text-align: right; white-space: nowrap; font-weight: bold;"><?php echo number_format((float)$row['new_grand_total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
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
            if ($perPage !== 25) $queryParams['per_page'] = $perPage;
            
            function pageUrl($p, $params) {
                $params['page'] = $p;
                return 'records.php?' . http_build_query($params);
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

        </div>
    </main>
</div>

<script>
let selectedId = null;

function openReprint(e) {
    e.preventDefault();
    const url = document.getElementById('btn-reprint').dataset.printUrl;
    if (!url) return;
    window.location.href = url;
}

function getCheckedIds() {
    const checked = [];
    document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
        checked.push(parseInt(cb.value));
    });
    return checked;
}

function updateActionToolbar() {
    const checked = getCheckedIds();
    const batchBtn = document.getElementById('btn-batch-print');
    const checkedCountEl = document.getElementById('checked-count');
    const reprintBtn = document.getElementById('btn-reprint');
    const editBtn = document.getElementById('btn-edit');
    const deleteBtn = document.getElementById('btn-delete');   // null for non-admin
    const selectedLabel = document.getElementById('selected-label');
    const actionGroup = document.getElementById('action-group');

    const batchDirectBtn = document.getElementById('btn-batch-direct');
    const directBtn = document.getElementById('btn-direct');
    const checkedCountDirectEl = document.getElementById('checked-count-direct');

    if (checked.length > 1) {
        // Multi-select batch mode
        actionGroup.classList.add('visible');
        batchBtn.style.display = 'inline-flex';
        batchDirectBtn.style.display = 'inline-flex';
        checkedCountEl.textContent = checked.length;
        checkedCountDirectEl.textContent = checked.length;
        reprintBtn.style.display = 'none';
        directBtn.style.display = 'none';
        editBtn.style.display = 'none';
        if (deleteBtn) deleteBtn.style.display = 'none';
        selectedLabel.textContent = checked.length + ' entries selected';
    } else if (checked.length === 1) {
        // Exactly 1 checked
        actionGroup.classList.add('visible');
        batchBtn.style.display = 'inline-flex';
        batchDirectBtn.style.display = 'none';
        checkedCountEl.textContent = '1';
        reprintBtn.style.display = 'inline-flex';
        reprintBtn.dataset.printUrl = 'print.php?id=' + checked[0];
        directBtn.style.display = 'inline-flex';
        editBtn.style.display = 'inline-flex';
        editBtn.href = 'entry.php?id=' + checked[0];
        if (deleteBtn) deleteBtn.style.display = 'inline-flex';
        selectedId = checked[0];
        selectedLabel.textContent = 'Entry #' + checked[0];
    } else if (selectedId) {
        // Single row clicked (no checkboxes)
        actionGroup.classList.add('visible');
        batchBtn.style.display = 'none';
        batchDirectBtn.style.display = 'none';
        directBtn.style.display = 'inline-flex';
        reprintBtn.style.display = 'inline-flex';
        editBtn.style.display = 'inline-flex';
        if (deleteBtn) deleteBtn.style.display = 'inline-flex';
    } else {
        actionGroup.classList.remove('visible');
    }
}

function onCheckboxChange() {
    // Uncheck or sync master checkbox
    const allCbs = document.querySelectorAll('.row-checkbox');
    const checkedCbs = document.querySelectorAll('.row-checkbox:checked');
    const checkAll = document.getElementById('check-all');
    if (checkAll) {
        checkAll.checked = allCbs.length > 0 && allCbs.length === checkedCbs.length;
    }

    // Highlight checked rows
    allCbs.forEach(cb => {
        const tr = cb.closest('tr');
        if (cb.checked) {
            tr.classList.add('selected-row');
        } else if (selectedId !== parseInt(tr.dataset.id)) {
            tr.classList.remove('selected-row');
        }
    });

    updateActionToolbar();
}

function toggleCheckAll(master) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = master.checked;
        const tr = cb.closest('tr');
        if (master.checked) {
            tr.classList.add('selected-row');
        } else {
            tr.classList.remove('selected-row');
        }
    });
    updateActionToolbar();
}

function onRowClick(e, tr) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
        return;
    }

    const cb = tr.querySelector('.row-checkbox');
    if (cb) {
        cb.checked = !cb.checked;
        onCheckboxChange();
        return;
    }

    selectRow(tr);
}

function selectRow(tr) {
    document.querySelectorAll('#records-tbody tr.selected-row').forEach(r => r.classList.remove('selected-row'));

    if (selectedId === parseInt(tr.dataset.id)) {
        clearSelection();
        return;
    }

    tr.classList.add('selected-row');
    selectedId = parseInt(tr.dataset.id);

    const label = tr.dataset.label;
    document.getElementById('selected-label').textContent = label;
    document.getElementById('btn-reprint').dataset.printUrl = 'print.php?id=' + selectedId;
    document.getElementById('btn-edit').href = 'entry.php?id=' + selectedId;
    updateActionToolbar();
}

function clearSelection() {
    selectedId = null;
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    const checkAll = document.getElementById('check-all');
    if (checkAll) checkAll.checked = false;
    document.querySelectorAll('#records-tbody tr.selected-row').forEach(r => r.classList.remove('selected-row'));
    document.getElementById('action-group').classList.remove('visible');
    document.getElementById('selected-label').textContent = '—';
    document.getElementById('btn-reprint').dataset.printUrl = '';
    document.getElementById('btn-reprint').href = '#';
    document.getElementById('btn-edit').href = '#';
    document.getElementById('btn-batch-print').style.display = 'none';
}

function batchPrintSelected() {
    const checked = getCheckedIds();
    if (checked.length === 0) {
        alert('Please check at least one record to batch print.');
        return;
    }
    const url = 'print_batch.php?ids=' + checked.join(',');
    // Reuse the same named window so repeated batch prints don't pile up tabs
    const pw = window.open(url, 'receipt_print');
    if (pw) pw.focus();
}

async function quickZreadDirectPrint(id, btn) {
    if (!id) return;
    const printer = document.getElementById('zread_direct_printer_select')?.value || '';
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Printing...'; }
    try {
        const resp = await fetch('zread_direct_print_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, printer: printer })
        });
        const data = await resp.json();
        if (data.success) {
            if (btn) { btn.innerHTML = '✓ Sent!'; }
            setTimeout(() => { if (btn) { btn.disabled = false; btn.innerHTML = orig; } }, 2200);
        } else {
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            alert('⚡ Direct Print Error: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        alert('⚡ Direct Print Error: ' + err.message);
    }
}

async function batchDirectPrintSelected() {
    const checked = getCheckedIds();
    if (checked.length === 0) { alert('Please check at least one record for direct print.'); return; }
    const btn = document.getElementById('btn-batch-direct');
    const printer = document.getElementById('zread_direct_printer_select')?.value || '';
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Printing...'; }
    try {
        const resp = await fetch('zread_direct_print_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: checked, printer: printer })
        });
        const data = await resp.json();
        if (data.success) {
            if (btn) { btn.innerHTML = '✓ ' + data.count + ' Sent!'; }
            setTimeout(() => { if (btn) { btn.disabled = false; btn.innerHTML = orig; } }, 2500);
        } else {
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            alert('⚡ Batch Direct Print Error: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        alert('⚡ Batch Direct Print Error: ' + err.message);
    }
}

function confirmDelete() {
    if (!selectedId) return;
    if (confirm('Are you sure you want to delete Entry #' + selectedId + '? This cannot be undone.')) {
        window.location.href = 'delete.php?id=' + selectedId;
    }
}

function confirmDeleteAll() {
    if (confirm('⚠️ WARNING: Are you sure you want to DELETE ALL journal records?\n\nThis will erase all imported entries and cannot be undone!')) {
        if (confirm('Final confirmation: Click OK to completely wipe all records.')) {
            window.location.href = 'delete.php?action=delete_all';
        }
    }
}

// Modal handling
function openImportModal() {
    const modal = document.getElementById('import-modal');
    modal.style.display = 'flex';
    document.getElementById('import-status-msg').style.display = 'none';
    
    // Auto prefill from first store if available
    const select = document.getElementById('modal-store-select');
    if (select.options.length > 1 && !document.getElementById('modal-store-code').value) {
        select.selectedIndex = 1;
        document.getElementById('modal-store-code').value = select.value;
    }
}

function closeImportModal() {
    document.getElementById('import-modal').style.display = 'none';
}

function onStoreSelectChange(select) {
    const codeInput = document.getElementById('modal-store-code');
    if (select.value === '__custom__') {
        codeInput.value = '';
        codeInput.focus();
    } else if (select.value) {
        codeInput.value = select.value;
    }
}

function submitTpsImport(e) {
    e.preventDefault();
    const storeCode = document.getElementById('modal-store-code').value.trim();
    const dsnName = document.getElementById('modal-dsn-name').value.trim() || 'Salessum';
    const btn = document.getElementById('btn-start-import');
    const msg = document.getElementById('import-status-msg');

    if (!storeCode) {
        alert('Please enter or select a Store Code.');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Importing... Please wait';
    msg.style.display = 'block';
    msg.style.background = '#eff6ff';
    msg.style.color = '#1d4ed8';
    msg.style.border = '1px solid #bfdbfe';
    msg.textContent = 'Connecting to TopSpeed (' + dsnName + ') and importing records...';

    const formData = new FormData();
    formData.append('store_code', storeCode);
    formData.append('dsn_name', dsnName);

    fetch('import_tps_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Start Import';

        if (data.status === 'success') {
            msg.style.background = '#f0fdf4';
            msg.style.color = '#15803d';
            msg.style.border = '1px solid #bbf7d0';
            msg.innerHTML = '<strong>✓ Import Completed!</strong><br>' +
                            'Total read: ' + data.total_read + ' records<br>' +
                            'New imported: ' + data.imported + '<br>' +
                            'Updated: ' + data.updated + '<br>' +
                            'Assigned Store Code: <strong>' + data.store_code + '</strong>.<br><br>' +
                            '<small>Reloading records in 2 seconds...</small>';
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            msg.style.background = '#fef2f2';
            msg.style.color = '#b91c1c';
            msg.style.border = '1px solid #fecaca';
            msg.innerHTML = '<strong>✖ Import Failed:</strong> ' + (data.message || 'Unknown error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Start Import';
        msg.style.background = '#fef2f2';
        msg.style.color = '#b91c1c';
        msg.style.border = '1px solid #fecaca';
        msg.innerHTML = '<strong>✖ Request Error:</strong> ' + err.message;
    });
}

function recalculateAllTotals() {
    const btn = document.getElementById('btn-recalc-totals');
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = '⏳ Recalculating...';

    fetch('repair_totals.php')
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = '🔄 Recalculate Totals';
            if (data.status === 'success') {
                alert('✓ Totals Recalculation Complete!\n\n' +
                      '• Daily Sales fixed: ' + data.daily_sales_fixed + '\n' +
                      '• Old Grand Totals linked: ' + data.old_grand_total_fixed + '\n' +
                      '• Total entries updated: ' + data.total_repaired);
                window.location.reload();
            } else {
                alert('✖ Recalculation failed.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = '🔄 Recalculate Totals';
            alert('✖ Error during recalculation: ' + err.message);
        });
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
