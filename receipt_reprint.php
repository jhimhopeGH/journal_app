<?php
// receipt_reprint.php - Receipt Reprint Records Management, Search, Filter & Printing
require_once __DIR__ . '/auth.php';
requireNavAccess('receipt_reprint');
require_once __DIR__ . '/ej_db.php';
require_once __DIR__ . '/raw_print_service.php';
$currentPage = 'receipt_reprint';


$rawPrinters      = getAvailableRawPrinters();
$preferredPrinter = getPreferredThermalPrinter($rawPrinters);

$search      = isset($_GET['q']) ? trim($_GET['q']) : '';
$storeFilter = isset($_GET['store']) ? trim($_GET['store']) : '';
$regFilter   = isset($_GET['reg']) ? trim($_GET['reg']) : '';
$from        = isset($_GET['from']) ? trim($_GET['from']) : '';
$to          = isset($_GET['to']) ? trim($_GET['to']) : '';
$page        = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage     = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [25, 50, 100, 200]) ? (int)$_GET['per_page'] : 25;

$totalRows  = countEjEntries($search, $storeFilter, $regFilter, $from, $to);
$totalPages = max(1, ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;
$rows       = getEjEntries($search, $storeFilter, $regFilter, $from, $to, $perPage, $offset);

$stores          = getAllStoreMasters();
$uniqueRegisters = getUniqueEjRegisters();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt Reprint - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
    .top-nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .top-nav .btn, .top-nav a.btn, .top-nav button.btn {
        padding: 8px 16px; font-size: 13px; font-weight: 600; text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px; color: #ffffff !important;
        margin-top: 0; background: #0284c7;
    }
    .top-nav .btn-secondary, .top-nav a.btn-secondary { background: #475569 !important; color: #ffffff !important; }
    .top-nav .btn:hover { opacity: 0.92; color: #ffffff !important; }
    .badge-pill { display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 9999px; background: #f1f5f9; color: #334155; }
    .badge-tender { background: #ede9fe; color: #6d28d9; }
    .badge-member { background: #e0e7ff; color: #4338ca; }
    .items-tooltip { max-width: 260px; white-space: normal; font-size: 12px; color: #475569; line-height: 1.3; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.72); backdrop-filter: blur(8px); z-index: 9999; justify-content: center; align-items: center; padding: 16px; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #ffffff; border-radius: 20px; width: 580px; max-width: 95vw; box-shadow: 0 25px 60px -15px rgba(15,23,42,0.45), 0 0 0 1px rgba(0,0,0,0.05); overflow: hidden; animation: as400Pop 0.22s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes as400Pop {
        from { opacity: 0; transform: scale(0.96) translateY(8px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .as400-modal-header { padding: 20px 24px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0369a1 100%); color: #ffffff; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .as400-header-title-group { display: flex; align-items: center; gap: 14px; }
    .as400-icon-badge { width: 44px; height: 44px; background: rgba(2,132,199,0.22); border: 1px solid rgba(56,189,248,0.45); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #38bdf8; box-shadow: 0 0 16px rgba(56,189,248,0.25); flex-shrink: 0; }
    .as400-title { margin: 0; font-size: 18px; font-weight: 700; color: #ffffff; letter-spacing: -0.2px; }
    .as400-subtitle { margin: 3px 0 0; font-size: 12px; color: #94a3b8; }
    .as400-subtitle code { background: rgba(255,255,255,0.12); padding: 1px 6px; border-radius: 4px; color: #38bdf8; font-size: 11px; }
    .as400-close-btn { width: 34px; height: 34px; background: rgba(255,255,255,0.1); border: none; color: #94a3b8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; cursor: pointer; transition: all 0.18s ease; line-height: 1; }
    .as400-close-btn:hover { background: rgba(255,255,255,0.2); color: #ffffff; transform: rotate(90deg); }
    .as400-modal-body { padding: 24px; background: #ffffff; }
    .as400-form-group { margin-bottom: 16px; }
    .as400-form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
    .as400-label { display: block; margin-bottom: 6px; }
    .as400-label-text { font-size: 12.5px; font-weight: 600; color: #334155; display: flex; align-items: center; gap: 6px; }
    .req-star { color: #ef4444; font-weight: bold; }
    .as400-input-wrapper { position: relative; }
    .as400-select, .as400-input { width: 100%; height: 42px; padding: 0 12px; font-size: 13.5px; color: #0f172a; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 10px; box-sizing: border-box; transition: all 0.2s ease; font-family: inherit; }
    .as400-select:hover, .as400-input:hover { border-color: #94a3b8; background: #ffffff; }
    .as400-select:focus, .as400-input:focus { outline: none; border-color: #0284c7; background: #ffffff; box-shadow: 0 0 0 3.5px rgba(2,132,199,0.15); }
    .as400-smart-box { background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 14px; padding: 14px 16px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(2,132,199,0.04); }
    .as400-smart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .as400-pill-badge { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 9999px; border: 1px solid #7dd3fc; }
    .as400-input-highlight { background: #ffffff !important; border-color: #7dd3fc !important; }
    .as400-input-highlight:focus { border-color: #0284c7 !important; box-shadow: 0 0 0 3.5px rgba(2,132,199,0.2) !important; }
    .as400-smart-tip { display: flex; align-items: flex-start; gap: 8px; margin-top: 8px; font-size: 11.5px; color: #0369a1; line-height: 1.45; }
    .as400-tip-icon { font-size: 14px; line-height: 1; margin-top: 1px; }
    .as400-tip-text code { background: rgba(2,132,199,0.12); padding: 1px 5px; border-radius: 4px; font-weight: 600; color: #0284c7; }
    .as400-modal-footer { display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding-top: 6px; }
    .as400-btn-cancel { padding: 10px 18px; font-size: 13px; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: all 0.18s ease; }
    .as400-btn-cancel:hover { background: #e2e8f0; color: #334155; }
    .as400-btn-primary { padding: 10px 22px; font-size: 13.5px; font-weight: 700; color: #ffffff !important; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border: none; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(2,132,199,0.35); transition: all 0.2s ease; }
    .as400-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(2,132,199,0.45); background: linear-gradient(135deg, #0369a1 0%, #075985 100%); }
    .as400-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
    .as400-spinner-pulse { width: 14px; height: 14px; border: 2.5px solid #0284c7; border-top-color: transparent; border-radius: 50%; animation: as400Spin 0.8s linear infinite; display: inline-block; margin-right: 6px; }
    @keyframes as400Spin { to { transform: rotate(360deg); } }
    .log-terminal { background: #090d16; color: #38bdf8; font-family: 'Consolas', 'Courier New', monospace; font-size: 11.5px; padding: 12px 14px; border-radius: 10px; height: 130px; overflow-y: auto; white-space: pre-wrap; margin-top: 14px; display: none; border: 1px solid #1e293b; box-shadow: inset 0 2px 6px rgba(0,0,0,0.4); line-height: 1.5; }

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
    tbody tr {
        cursor: pointer;
        transition: background 0.12s ease;
    }
    tbody tr:hover {
        background-color: #dbeafe !important;
    }
    tbody tr.row-selected {
        background-color: #bfdbfe !important;
        outline: 2px solid #2563eb;
        outline-offset: -2px;
    }
    tbody tr.row-selected td {
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
            <a href="receipt_reprint.php" class="btn btn-secondary">View Records</a>
            <a href="receipt_print_layout.php" class="btn btn-secondary" style="background:#6d28d9 !important;">⚙️ Layout &amp; Cut Setup</a>
            <button type="button" class="btn" id="btn_open_as400" style="background:#0284c7;">⚡ Import AS400</button>
            <button type="button" class="btn" id="btn_batch_print" style="background:#7c3aed;">🧾 Batch Receipt Print</button>
        </div>

        <div class="page-header">
            <h1>Receipt Reprint</h1>
            <p>Browse and reprint Electronic Journal transactions as 80mm thermal receipts (Database: <code>ej.db</code>).</p>
        </div>

        <div class="panel">
            <!-- Filter Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                <form class="search-bar" method="GET" action="receipt_reprint.php" style="margin-bottom:0; flex:1; display:flex; gap:8px; flex-wrap:wrap; max-width:none; align-items:center;">
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
                    <?php if ($search !== '' || $storeFilter !== '' || $regFilter !== '' || $from !== '' || $to !== ''): ?>
                        <a href="receipt_reprint.php<?php echo $perPage !== 25 ? '?per_page=' . $perPage : ''; ?>" class="btn btn-secondary" style="margin-top:0; padding:8px 18px; border-radius:20px; text-decoration:none; font-size:13px; display:inline-flex; align-items:center;">Clear</a>
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

            <form id="batchPrintForm" method="GET" action="receipt_print_batch.php" target="_blank">
                <input type="hidden" name="ids" id="batch_ids_input" value="">
            </form>

            <!-- Table -->
            <?php if (empty($rows)): ?>
                <p class="empty-state">No EJ entries found. Create a <a href="ej_entry.php">New Entry</a> or click <strong>Import AS400</strong>.</p>
            <?php else: ?>
                <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 14px;">
                <table style="font-size:13px; margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width:32px;text-align:center;"><input type="checkbox" id="select_all_checkbox" title="Select All"></th>
                            <th style="width:60px;">ID</th>
                            <th style="width:100px;">Date &amp; Time</th>
                            <th style="width:70px;">Store</th>
                            <th style="width:60px;">Reg</th>
                            <th>Invoice / Trx #</th>
                            <th>Member / Customer</th>
                            <th style="width:130px;">Cashier</th>
                            <th>Items</th>
                            <th style="text-align:right;width:110px;">Total (₱)</th>
                            <th style="width:130px;">Tender</th>
                            <th style="width:160px;text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <?php
                            $itemsDecoded   = json_decode($row['items'] ?? '[]', true);
                            $itemCount      = is_array($itemsDecoded) ? count($itemsDecoded) : 0;
                            $firstItemDesc  = ($itemCount > 0 && isset($itemsDecoded[0]['description'])) ? $itemsDecoded[0]['description'] : ($itemCount > 0 ? ($itemsDecoded[0]['sku'] ?? '') : 'No items');
                            $tendersDecoded = json_decode($row['tenders'] ?? '[]', true);
                            $tenderParts    = [];
                            if (is_array($tendersDecoded)) {
                                foreach ($tendersDecoded as $td) {
                                    if (!empty($td['name']) && (float)($td['amount'] ?? 0) >= 0) {
                                        $tenderParts[] = $td['name'];
                                    }
                                }
                            }
                            if (empty($tenderParts)) $tenderParts = ['Cash'];
                            $tShown  = array_slice($tenderParts, 0, 2);
                            $tHidden = array_slice($tenderParts, 2);
                            $tAllTip = implode(' | ', $tenderParts);
                        ?>
                        <tr>
                            <td style="text-align:center;"><input type="checkbox" class="row-checkbox" value="<?php echo (int)$row['id']; ?>"></td>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($row['entry_date']); ?></div>
                                <div style="font-size:11px;color:#64748b;"><?php echo htmlspecialchars($row['entry_time']); ?></div>
                            </td>
                            <td><span class="badge-pill"><?php echo htmlspecialchars($row['store_code']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($row['register_number']); ?></strong></td>
                            <td>
                                <div style="font-weight:600;color:#0284c7;"><?php echo htmlspecialchars($row['invoice_number'] ?: '—'); ?></div>
                                <div style="font-size:11px;color:#64748b;">Trx: <?php echo htmlspecialchars($row['transaction_number'] ?: '—'); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($row['member_number'])): ?>
                                    <div><span class="badge-pill badge-member"><?php echo htmlspecialchars($row['member_number']); ?></span></div>
                                <?php endif; ?>
                                <div style="font-weight:500;"><?php echo htmlspecialchars($row['customer_name'] ?: 'Walk-in'); ?></div>
                            </td>
                            <td>
                                <strong style="color:#0f172a;"><?php echo htmlspecialchars($row['sales_associate'] ?: '—'); ?></strong>
                                <?php if (!empty($row['associate_id']) && $row['associate_id'] !== $row['sales_associate']): ?>
                                    <div style="font-size:11px;color:#64748b;font-family:monospace;">ID: <?php echo htmlspecialchars($row['associate_id']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="items-tooltip" title="<?php echo htmlspecialchars($firstItemDesc); ?>">
                                    <span class="badge-pill"><?php echo $itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?></span>
                                    <?php if ($itemCount > 0): ?>
                                        <div style="font-size:11px;color:#475569;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;"><?php echo htmlspecialchars($firstItemDesc); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align:right;font-weight:bold;color:#0f172a;">₱<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                            <td style="width:160px;max-width:160px;overflow:hidden;">
                                <div style="display:flex;flex-wrap:wrap;gap:2px;overflow:hidden;">
                                <?php foreach ($tShown as $tn): ?>
                                    <span class="badge-pill badge-tender" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;display:inline-block;"><?php echo htmlspecialchars($tn); ?></span>
                                <?php endforeach;
                                if (count($tHidden) > 0): ?>
                                    <span title="<?php echo htmlspecialchars($tAllTip); ?>" style="background:#f1f5f9;color:#475569;border-radius:9999px;padding:2px 7px;font-size:11px;font-weight:700;cursor:help;white-space:nowrap;display:inline-block;">+<?php echo count($tHidden); ?> more</span>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align:center;white-space:nowrap;vertical-align:middle;">
                                <div style="display:inline-flex;gap:6px;justify-content:center;align-items:center;">
                                    <a class="btn" style="padding:4px 12px;font-size:11px;background:#7c3aed;border-radius:20px;margin-top:0;text-decoration:none;" href="receipt_print.php?id=<?php echo (int)$row['id']; ?>">Preview</a>
                                    <a class="btn btn-secondary" style="padding:4px 12px;font-size:11px;border-radius:20px;margin-top:0;text-decoration:none;" href="ej_entry.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
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
                        return 'receipt_reprint.php?' . http_build_query($params);
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

<!-- AS400 Import Modal -->
<div class="modal-overlay" id="as400_modal">
    <div class="modal-box as400-modal-card">
        <!-- Modern Header -->
        <div class="as400-modal-header">
            <div class="as400-header-title-group">
                <div class="as400-icon-badge">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </div>
                <div>
                    <h3 class="as400-title">Import from AS400</h3>
                    <p class="as400-subtitle">IBM iSeries Database • DSN: <code>mms_as400</code> • <code>MMLTSLIB</code></p>
                </div>
            </div>
            <button type="button" class="as400-close-btn" id="modal_close_as400" title="Close dialog">&times;</button>
        </div>

        <div class="as400-modal-body">
            <form id="as400ImportForm">
                
                <!-- Store Code Field -->
                <div class="as400-form-group">
                    <label class="as400-label">
                        <span class="as400-label-text">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            Store Code <span class="req-star">*</span>
                        </span>
                    </label>
                    <div class="as400-input-wrapper">
                        <select name="store_code" required class="as400-select">
                            <option value="">-- Select Target Store --</option>
                            <?php foreach ($stores as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['store_code']); ?>">
                                    <?php echo htmlspecialchars($s['store_code'] . ' — ' . $s['store_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 2-Col Grid: Date & Register Number -->
                <div class="as400-form-row-2">
                    <div class="as400-form-group" style="margin-bottom:0;">
                        <label class="as400-label">
                            <span class="as400-label-text">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                Transaction Date <span class="req-star">*</span>
                            </span>
                        </label>
                        <div class="as400-input-wrapper">
                            <input type="date" name="date_from" value="<?php echo date('Y-m-d'); ?>" required class="as400-input">
                        </div>
                    </div>

                    <div class="as400-form-group" style="margin-bottom:0;">
                        <label class="as400-label">
                            <span class="as400-label-text">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                Register Number <span class="req-star">*</span>
                            </span>
                        </label>
                        <div class="as400-input-wrapper">
                            <input type="text" name="reg_no" required placeholder="e.g. 104" class="as400-input">
                        </div>
                    </div>
                </div>

                <!-- Transaction Number Card (Featured Smart Field) -->
                <div class="as400-smart-box">
                    <div class="as400-smart-header">
                        <label class="as400-label" style="margin-bottom:0;">
                            <span class="as400-label-text" style="font-weight:700; color:#0369a1;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                                Transaction Number
                            </span>
                        </label>
                        <span class="as400-pill-badge">Optional • Auto-Detect</span>
                    </div>

                    <div class="as400-input-wrapper" style="margin-top:6px;">
                        <input type="text" name="traxn_no" placeholder="Leave empty to auto-fetch the last transaction" class="as400-input as400-input-highlight">
                    </div>

                    <div class="as400-smart-tip">
                        <span class="as400-tip-icon">💡</span>
                        <div class="as400-tip-text">
                            <strong>Smart Auto-Fetch:</strong> If left blank, the system automatically pulls the latest completed transaction (<code>csttyp = 01</code>) from <code>MMLTSLIB.cshhdr</code>.
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="as400-modal-footer">
                    <button type="button" class="as400-btn-cancel" onclick="document.getElementById('as400_modal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="as400-btn-primary" id="btn_run_import">
                        <span>⚡ Start AS400 Import</span>
                    </button>
                </div>

                <!-- Live Sync Status & Terminal -->
                <div id="import_spinner_wrap" style="display:none; margin-top:14px;">
                    <div class="as400-live-bar" style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px 14px; border-radius:10px; display:flex; align-items:center; gap:10px; font-size:12.5px; font-weight:600; color:#166534;">
                        <span class="as400-spinner-pulse"></span>
                        <span id="import_spinner">Connecting to AS400 server and fetching transaction...</span>
                    </div>
                </div>

                <div class="log-terminal" id="as400_terminal"></div>
            </form>
        </div>
    </div>
</div>

<!-- AS400 Import Summary Modal -->
<div class="modal-overlay" id="as400_summary_modal">
    <div class="modal-box as400-modal-card" style="width:500px;">
        <div class="as400-modal-header as400-sum-header" style="background:linear-gradient(135deg, #065f46 0%, #059669 100%) !important;">
            <div class="as400-header-title-group">
                <div class="as400-icon-badge" style="background:rgba(255,255,255,0.2); border-color:rgba(255,255,255,0.4); color:#fff; box-shadow:0 0 16px rgba(16,185,129,0.3);">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div>
                    <h3 class="as400-title" style="color:#fff;">AS400 Import Summary</h3>
                    <p class="as400-subtitle" style="color:#a7f3d0;">Transaction synced successfully to <code>ej.db</code></p>
                </div>
            </div>
            <button type="button" class="as400-close-btn" id="modal_close_as400_summary" style="color:#e2e8f0;">&times;</button>
        </div>

        <div class="as400-modal-body" style="padding:22px 24px;">
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:12px 16px; margin-bottom:18px; display:flex; align-items:center; gap:12px;">
                <span style="font-size:24px; line-height:1;">🎉</span>
                <div>
                    <div style="font-size:14px; color:#166534; font-weight:700;">Transaction Imported Successfully!</div>
                    <div style="font-size:12px; color:#15803d; margin-top:1px;" id="as400_summary_submsg">Ready for thermal receipt reprint or EJ browsing.</div>
                </div>
            </div>

            <div class="as400-sum-card" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:20px;">
                <table class="as400-sum-table" style="width:100%; border-collapse:collapse; margin:0; border:none;">
                    <tbody>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:11px 16px; color:#64748b; font-weight:600; width:44%; background:#f8fafc; border:none;">Store Code</td>
                            <td style="padding:11px 16px; color:#0f172a; font-weight:700; background:#ffffff; border:none;">
                                <span class="badge-pill" id="sum_store_code" style="background:#e0f2fe; color:#0369a1; font-weight:700;">—</span>
                            </td>
                        </tr>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:11px 16px; color:#64748b; font-weight:600; background:#f8fafc; border:none;">Date</td>
                            <td style="padding:11px 16px; color:#0f172a; font-weight:700; background:#ffffff; border:none;" id="sum_date">—</td>
                        </tr>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:11px 16px; color:#64748b; font-weight:600; background:#f8fafc; border:none;">Register Number</td>
                            <td style="padding:11px 16px; color:#0f172a; font-weight:700; background:#ffffff; border:none;" id="sum_register_number">—</td>
                        </tr>
                        <tr>
                            <td style="padding:11px 16px; color:#64748b; font-weight:600; background:#f8fafc; border:none;">Transaction Number</td>
                            <td style="padding:11px 16px; color:#0284c7; font-weight:800; font-size:15px; background:#ffffff; border:none;" id="sum_transaction_number">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <a href="#" id="sum_btn_preview" target="_blank" class="btn" style="background:linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); display:none; align-items:center; gap:6px; padding:10px 20px; border-radius:10px; text-decoration:none; font-size:13px; font-weight:700; color:#fff; box-shadow:0 4px 12px rgba(124, 58, 237, 0.3);">
                    <span>🧾 Preview Receipt</span>
                </a>
                <button type="button" id="sum_btn_done" class="btn" style="background:linear-gradient(135deg, #059669 0%, #047857 100%); padding:10px 24px; border-radius:10px; font-size:13px; font-weight:700; color:#fff; box-shadow:0 4px 12px rgba(5, 150, 105, 0.3);">
                    <span>Done</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectAll     = document.getElementById('select_all_checkbox');
    const checkboxes    = document.querySelectorAll('.row-checkbox');
    const btnBatch      = document.getElementById('btn_batch_print');
    const batchForm     = document.getElementById('batchPrintForm');
    const batchInput    = document.getElementById('batch_ids_input');

    const updateRowHighlights = () => {
        checkboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (tr) tr.classList.toggle('row-selected', cb.checked);
        });
    };

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateRowHighlights();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateRowHighlights);
    });

    document.querySelectorAll('tbody tr').forEach(tr => {
        tr.addEventListener('click', e => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) return;
            const cb = tr.querySelector('.row-checkbox');
            if (cb) {
                cb.checked = !cb.checked;
                updateRowHighlights();
            }
        });
    });

    btnBatch.addEventListener('click', () => {
        const ids = [];
        checkboxes.forEach(cb => { if (cb.checked) ids.push(cb.value); });
        if (!ids.length) { alert('Please select at least one record to print.'); return; }
        batchInput.value = ids.join(',');
        batchForm.submit();
    });

    // Modal helpers
    const openModal  = id => document.getElementById(id)?.classList.add('active');
    const closeModal = id => document.getElementById(id)?.classList.remove('active');
    const onOverlay  = (id) => document.getElementById(id)?.addEventListener('click', e => { if (e.target.id === id) closeModal(id); });

    document.getElementById('btn_open_as400')?.addEventListener('click', () => openModal('as400_modal'));
    document.getElementById('modal_close_as400')?.addEventListener('click', () => closeModal('as400_modal'));
    onOverlay('as400_modal');

    // AS400 import
    document.getElementById('as400ImportForm')?.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('btn_run_import');
        const spinnerWrap = document.getElementById('import_spinner_wrap');
        const terminal = document.getElementById('as400_terminal');
        btn.disabled = true;
        if (spinnerWrap) spinnerWrap.style.display = 'block';
        terminal.style.display = 'block';
        terminal.textContent = 'Connecting to IBM iSeries AS400 (DSN: mms_as400)...\n';
        try {
            const resp = await fetch('import_as400_ajax.php', { method: 'POST', body: new FormData(e.target) });
            const data = await resp.json();
            if (data.status === 'success') {
                terminal.textContent += `\n✓ ${data.message}\nImported: ${data.imported}\nUpdated: ${data.updated}\nSkipped: ${data.skipped}\n`;

                // Set summary data
                document.getElementById('sum_store_code').textContent = data.store_code || '—';
                document.getElementById('sum_date').textContent = data.date || '—';
                document.getElementById('sum_register_number').textContent = data.register_number || '—';
                document.getElementById('sum_transaction_number').textContent = data.transaction_number || '—';
                if (data.message) {
                    document.getElementById('as400_summary_submsg').textContent = data.message;
                }

                const previewBtn = document.getElementById('sum_btn_preview');
                if (data.entry_id) {
                    previewBtn.href = 'receipt_print.php?id=' + encodeURIComponent(data.entry_id);
                    previewBtn.style.display = 'inline-flex';
                } else {
                    previewBtn.style.display = 'none';
                }

                // Close the import modal and open the summary popup
                closeModal('as400_modal');
                openModal('as400_summary_modal');
            } else {
                terminal.textContent += `\n${data.status === 'not_found' ? '⚠️' : '❌'} ${data.message}\n`;
            }
        } catch(err) {
            terminal.textContent += `\n❌ ${err.message}\n`;
        } finally {
            btn.disabled = false;
            if (spinnerWrap) spinnerWrap.style.display = 'none';
        }
    });

    const closeSummaryAndReload = () => {
        closeModal('as400_summary_modal');
        window.location.reload();
    };
    document.getElementById('modal_close_as400_summary')?.addEventListener('click', closeSummaryAndReload);
    document.getElementById('sum_btn_done')?.addEventListener('click', closeSummaryAndReload);
    document.getElementById('as400_summary_modal')?.addEventListener('click', e => {
        if (e.target.id === 'as400_summary_modal') closeSummaryAndReload();
    });
});

function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>
</body>
</html>
