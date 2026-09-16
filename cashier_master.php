<?php
// cashier_master.php - Lists cashier master records, supports search, EMPMST TPS import & delete
require_once __DIR__ . '/auth.php';
requireNavAccess('cashier_master');
require_once __DIR__ . '/db.php';
$currentPage = 'cashier_master';

$search      = isset($_GET['q'])       ? trim($_GET['q'])       : '';
$storeFilter = isset($_GET['store'])   ? trim($_GET['store'])   : '';
$saved       = isset($_GET['saved'])   ? (int)$_GET['saved']   : 0;
$deleted     = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;
$deletedAll  = isset($_GET['deleted_all']) ? 1 : 0;
$page        = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage     = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [25, 50, 100, 200]) ? (int)$_GET['per_page'] : 25;

$totalRows  = getCashierMastersCount($search, $storeFilter);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$rows   = getAllCashierMasters($search, $perPage, $offset, $storeFilter);
$stores = getAllStoreMasters();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cashier Master - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
.top-nav { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.top-nav .btn-danger { background:#dc2626 !important; color:#fff !important; }
.top-nav .btn-danger:hover { background:#b91c1c !important; }
.top-nav .btn-teal  { background:linear-gradient(135deg,#0d9488,#0f766e) !important; color:#fff !important; }
.top-nav .btn-teal:hover { opacity:0.92; }
.top-nav a.btn, .top-nav button.btn {
    border:none; padding:8px 18px; border-radius:20px; cursor:pointer;
    font-size:13px; font-weight:600; text-decoration:none;
    display:inline-flex; align-items:center; gap:6px;
    background:#e2e8f0; color:#1e293b; transition:all 0.18s ease;
}
.top-nav a.btn:hover, .top-nav button.btn:hover { background:#cbd5e1; color:#0f172a; transform:translateY(-1px); }
.page-nav-btn {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:32px; height:32px; padding:0 10px; font-size:13px; font-weight:600;
    color:#334155; background:#fff; border:1px solid #cbd5e1; border-radius:16px;
    text-decoration:none; box-sizing:border-box; transition:all 0.18s;
}
.page-nav-btn:hover { background:#f1f5f9; border-color:#94a3b8; color:#0f172a; transform:translateY(-1px); }
.page-nav-btn.active { background:#2563eb; border-color:#2563eb; color:#fff; font-weight:bold; cursor:default; }
.badge-store           { display:inline-block; padding:2px 9px; border-radius:9999px; font-size:11px; font-weight:700; background:#ede9fe; color:#5b21b6; }
.badge-status-active   { display:inline-block; padding:2px 9px; border-radius:9999px; font-size:11px; font-weight:700; background:#dcfce7; color:#166534; }
.badge-status-inactive { display:inline-block; padding:2px 9px; border-radius:9999px; font-size:11px; font-weight:700; background:#fee2e2; color:#991b1b; }
.badge-status-other    { display:inline-block; padding:2px 9px; border-radius:9999px; font-size:11px; font-weight:700; background:#f1f5f9; color:#475569; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); backdrop-filter:blur(2px); z-index:1000; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { background:#fff; border-radius:12px; padding:28px; max-width:480px; width:94%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.25); position:relative; }
.modal-box h2 { margin:0 0 6px; font-size:18px; font-weight:700; color:#1e293b; }
.modal-box p  { margin:0 0 16px; font-size:13px; color:#64748b; line-height:1.4; }
.modal-box label { font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; display:block; }
.modal-box select, .modal-box input[type=text] {
    width:100%; padding:9px 12px; border-radius:6px; border:1px solid #cbd5e1;
    background:#f8fafc; color:#1e293b; font-size:13px; margin-bottom:12px; box-sizing:border-box;
}
.modal-box .modal-actions { display:flex; gap:10px; margin-top:18px; }
.modal-box .modal-actions button { flex:1; padding:10px; border-radius:20px; font-size:13px; font-weight:600; cursor:pointer; border:none; transition:all 0.18s; }
.btn-import-go { background:linear-gradient(135deg,#0d9488,#0f766e); color:#fff; }
.btn-cancel    { background:#e2e8f0; color:#475569; }
.modal-close { position:absolute; top:14px; right:16px; background:none; border:none; color:#94a3b8; font-size:20px; cursor:pointer; }
.modal-close:hover { color:#475569; }
#importStatus { margin-top:14px; font-size:13px; display:none; padding:10px 14px; border-radius:6px; line-height:1.4; }
#importStatus.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
#importStatus.error   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
#importSpinner { display:none; text-align:center; padding:12px 0; color:#64748b; font-size:13px; font-weight:500; }
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>
    <main class="main-content">
        <div class="top-nav">
            <a class="btn btn-secondary" href="cashier_master_entry.php">+ New Entry</a>
            <a class="btn btn-secondary" href="cashier_master.php">View Records</a>
            <button class="btn btn-teal" onclick="openImportModal()">⬆ Import TPS</button>
            <?php if ($totalRows > 0): ?>
            <a class="btn btn-danger" href="cashier_master_delete.php?all=1"
               onclick="return confirm('ARE YOU SURE?\n\nThis will permanently delete ALL cashier records.\nThis cannot be undone.');">
                🗑 Delete All
            </a>
            <?php endif; ?>
        </div>

        <div class="page-header">
            <h1>Cashier Master</h1>
            <p>Manage POS cashier employees imported from <strong>EMPMST.TPS</strong> (Salessum ODBC DSN).</p>
        </div>

        <?php if ($saved):      ?><div class="success">✓ Cashier record saved successfully.</div><?php endif; ?>
        <?php if ($deleted):    ?><div class="success">✓ Cashier record deleted successfully.</div><?php endif; ?>
        <?php if ($deletedAll): ?><div class="success">✓ All cashier records have been deleted.</div><?php endif; ?>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                <form class="search-bar" method="GET" action="cashier_master.php"
                      style="margin-bottom:0; flex:1; display:flex; gap:8px; flex-wrap:wrap; max-width:none; align-items:center;">
                    <input type="text" name="q" placeholder="Search by name, emp#, job, status..."
                           value="<?php echo htmlspecialchars($search); ?>" style="flex:2; min-width:180px;">
                    <select name="store" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; background:#fff; flex:1; min-width:150px;">
                        <option value="">-- All Stores --</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?php echo htmlspecialchars($s['store_code']); ?>"
                                    <?php if ($storeFilter === $s['store_code']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['store_code'] . ' - ' . $s['store_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($perPage !== 25): ?>
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                    <?php endif; ?>
                    <button type="submit" style="margin-top:0; padding:8px 20px; border-radius:20px; font-weight:600;">Search</button>
                    <?php if ($search !== '' || $storeFilter !== ''): ?>
                        <a href="cashier_master.php<?php echo $perPage !== 25 ? '?per_page=' . $perPage : ''; ?>"
                           class="btn btn-secondary" style="margin-top:0; padding:8px 18px; border-radius:20px; text-decoration:none; font-size:13px; display:inline-flex; align-items:center;">Clear</a>
                    <?php endif; ?>
                </form>
                <div style="display:flex; align-items:center; gap:8px; font-size:13px; color:#64748b;">
                    <span>Show:</span>
                    <select onchange="changePerPage(this.value)" style="padding:5px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; background:#fff;">
                        <option value="25"  <?php if ($perPage === 25)  echo 'selected'; ?>>25</option>
                        <option value="50"  <?php if ($perPage === 50)  echo 'selected'; ?>>50</option>
                        <option value="100" <?php if ($perPage === 100) echo 'selected'; ?>>100</option>
                        <option value="200" <?php if ($perPage === 200) echo 'selected'; ?>>200</option>
                    </select>
                    <span>entries</span>
                </div>
            </div>

            <div style="overflow-x:auto; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-top:14px;">
            <table style="margin-top:0; border:none; font-size:13px;">
            <thead>
                <tr>
                    <th style="width:55px;">ID</th>
                    <th style="width:110px;">Store</th>
                    <th style="width:110px;">Emp No.</th>
                    <th>Name</th>
                    <th style="width:170px;">Job</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:155px;">Updated</th>
                    <th style="width:130px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:28px; color:#8892a4;">
                            No cashier records found. Click <strong>⬆ Import TPS</strong> to load from
                            <strong>EMPMST.TPS</strong> or <strong>+ New Entry</strong> to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <?php
                        $st    = strtolower(trim($row['status'] ?? ''));
                        $badge = 'badge-status-other';
                        if (in_array($st, ['active',   '1', 'a'])) $badge = 'badge-status-active';
                        if (in_array($st, ['inactive', '0', 'i'])) $badge = 'badge-status-inactive';
                    ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><span class="badge-store"><?php echo htmlspecialchars($row['store_code']); ?></span></td>
                        <td style="font-family:monospace; color:#0284c7; font-weight:600;"><?php echo htmlspecialchars($row['emp_no']); ?></td>
                        <td><strong style="color:#1e293b;"><?php echo htmlspecialchars($row['name']); ?></strong></td>
                        <td style="color:#475569;"><?php echo htmlspecialchars($row['job']); ?></td>
                        <td><span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td style="color:#64748b; font-size:12px;"><?php echo htmlspecialchars($row['updated_at']); ?></td>
                        <td style="white-space:nowrap; text-align:right;">
                            <a class="btn" style="padding:4px 10px; font-size:12px;"
                               href="cashier_master_entry.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                            <a class="btn btn-secondary" style="padding:4px 10px; font-size:12px; background:#dc2626; color:#fff !important;"
                               href="cashier_master_delete.php?id=<?php echo (int)$row['id']; ?>"
                               onclick="return confirm('Delete cashier [<?php echo htmlspecialchars($row['emp_no']); ?>] <?php echo htmlspecialchars(addslashes($row['name'])); ?>?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            </table>
            </div>

            <?php if ($totalRows > 0): ?>
                <?php
                $startItem = $offset + 1;
                $endItem   = min($totalRows, $offset + $perPage);
                $qp = [];
                if ($search !== '')      $qp['q']        = $search;
                if ($storeFilter !== '') $qp['store']     = $storeFilter;
                if ($perPage !== 25)     $qp['per_page']  = $perPage;
                function cashierPageUrl($p, $params) {
                    $params['page'] = $p;
                    return 'cashier_master.php?' . http_build_query($params);
                }
                ?>
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:18px; padding-top:14px; border-top:1px solid #e2e8f0; font-size:13px; color:#64748b;">
                    <div>
                        Showing <strong><?php echo number_format($startItem); ?></strong> to
                        <strong><?php echo number_format($endItem); ?></strong> of
                        <strong><?php echo number_format($totalRows); ?></strong> total entries
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo cashierPageUrl(1, $qp); ?>" class="page-nav-btn">« First</a>
                            <a href="<?php echo cashierPageUrl($page - 1, $qp); ?>" class="page-nav-btn">‹ Prev</a>
                        <?php endif; ?>
                        <?php
                        $sp = max(1, $page - 2); $ep = min($totalPages, $page + 2);
                        if ($sp > 1) echo '<span style="padding:0 4px;">...</span>';
                        for ($i = $sp; $i <= $ep; $i++):
                        ?>
                            <?php if ($i === $page): ?>
                                <span class="page-nav-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo cashierPageUrl($i, $qp); ?>" class="page-nav-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($ep < $totalPages) echo '<span style="padding:0 4px;">...</span>'; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo cashierPageUrl($page + 1, $qp); ?>" class="page-nav-btn">Next ›</a>
                            <a href="<?php echo cashierPageUrl($totalPages, $qp); ?>" class="page-nav-btn">Last »</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Import TPS Modal -->
<div class="modal-overlay" id="importModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeImportModal()">✕</button>
        <h2>⬆ Import from TPS (EMPMST)</h2>
        <p>Select the <strong>Store Code</strong> to associate the imported cashier records with.<br>
        Reads <strong>EMPMST.TPS</strong> via the <em>Salessum</em> ODBC DSN and imports records with Job code <strong>CSH</strong> (Name, Job, and Status).</p>

        <label for="import_store_code">Store Code <span style="color:#ef4444;">*</span></label>
        <select id="import_store_code">
            <option value="">-- Select Store --</option>
            <?php foreach ($stores as $s): ?>
                <option value="<?php echo htmlspecialchars($s['store_code']); ?>">
                    <?php echo htmlspecialchars($s['store_code'] . ' — ' . $s['store_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="import_dsn">ODBC DSN Name</label>
        <input type="text" id="import_dsn" value="Salessum" placeholder="Salessum">

        <div id="importSpinner">⏳ Connecting to ODBC &amp; importing EMPMST... please wait.</div>
        <div id="importStatus"></div>

        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeImportModal()">Cancel</button>
            <button type="button" class="btn-import-go" id="btnRunImport" onclick="runImport()">⬆ Import Now</button>
        </div>
    </div>
</div>

<script>
function openImportModal() {
    document.getElementById('importModal').classList.add('active');
    document.getElementById('importStatus').style.display = 'none';
    document.getElementById('importStatus').textContent = '';
    document.getElementById('importSpinner').style.display = 'none';
    document.getElementById('btnRunImport').disabled = false;
}
function closeImportModal() {
    document.getElementById('importModal').classList.remove('active');
}
document.getElementById('importModal').addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});

function runImport() {
    const storeCode = document.getElementById('import_store_code').value.trim();
    const dsnName   = document.getElementById('import_dsn').value.trim() || 'Salessum';
    if (!storeCode) { alert('Please select a Store Code before importing.'); return; }

    const btn = document.getElementById('btnRunImport');
    btn.disabled = true;
    document.getElementById('importSpinner').style.display = 'block';
    document.getElementById('importStatus').style.display  = 'none';

    const fd = new FormData();
    fd.append('store_code', storeCode);
    fd.append('dsn_name', dsnName);

    fetch('import_cashier_ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            document.getElementById('importSpinner').style.display = 'none';
            btn.disabled = false;
            if (data.success) {
                const w = (data.errors && data.errors.length) ? ` (${data.errors.length} warning(s))` : '';
                showStatus(`✅ Done! Inserted: ${data.inserted ?? 0}, Updated: ${data.updated ?? 0}, Skipped: ${data.skipped ?? 0} of ${data.total ?? 0}.${w}`, true);
                setTimeout(() => { window.location.href = 'cashier_master.php'; }, 1800);
            } else {
                showStatus('❌ Import failed: ' + (data.error || data.message || 'Unknown error.'), false);
            }
        })
        .catch(err => {
            document.getElementById('importSpinner').style.display = 'none';
            btn.disabled = false;
            showStatus('❌ Network error: ' + err.message, false);
        });
}

function showStatus(msg, ok) {
    const el = document.getElementById('importStatus');
    el.textContent = msg;
    el.className   = ok ? 'success' : 'error';
    el.style.display = 'block';
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
