<?php
// tender_master.php - Lists tender master records, supports search, TopSpeed import & bulk delete
require_once __DIR__ . '/auth.php';
requireNavAccess('tender_master');
require_once __DIR__ . '/db.php';
$currentPage = 'tender_master';


$search     = isset($_GET['q']) ? trim($_GET['q']) : '';
$saved      = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;
$deleted    = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;
$deletedAll = isset($_GET['deleted_all']) ? 1 : 0;
$page       = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage    = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [25, 50, 100, 200]) ? (int)$_GET['per_page'] : 25;

$totalRows  = getTenderMastersCount($search);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$rows = getAllTenderMasters($search, $perPage, $offset);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tender Master - Electronic Journal</title>
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
.top-nav .btn-danger {
    background: #dc2626 !important;
    color: #ffffff !important;
}
.top-nav .btn-danger:hover {
    background: #b91c1c !important;
}
.top-nav .btn-purple {
    background: linear-gradient(135deg, #a855f7, #7c3aed) !important;
    color: #ffffff !important;
}
.top-nav .btn-purple:hover {
    opacity: 0.92;
}
.top-nav a.btn, .top-nav button.btn {
    border: none;
    padding: 8px 18px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #e2e8f0;
    color: #1e293b;
    transition: all 0.18s ease;
}
.top-nav a.btn:hover, .top-nav button.btn:hover {
    background: #cbd5e1;
    color: #0f172a;
    transform: translateY(-1px);
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

/* Import Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(2px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: #ffffff;
    border-radius: 12px;
    padding: 28px;
    max-width: 460px;
    width: 92%;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25);
    position: relative;
}
.modal-box h2 {
    margin: 0 0 6px;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}
.modal-box p {
    margin: 0 0 16px;
    font-size: 13px;
    color: #64748b;
    line-height: 1.4;
}
.modal-box label {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
    display: block;
}
.modal-box select,
.modal-box input[type=text] {
    width: 100%;
    padding: 9px 12px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #1e293b;
    font-size: 13px;
    margin-bottom: 12px;
    box-sizing: border-box;
}
.modal-box .checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #334155;
    cursor: pointer;
    margin-bottom: 14px;
    font-weight: 500;
}
.modal-box .modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}
.modal-box .modal-actions button {
    flex: 1;
    padding: 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.18s ease;
}
.btn-import-go {
    background: linear-gradient(135deg, #a855f7, #7c3aed);
    color: #ffffff;
}
.btn-cancel {
    background: #e2e8f0;
    color: #475569;
}
.modal-close {
    position: absolute;
    top: 14px;
    right: 16px;
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
}
.modal-close:hover {
    color: #475569;
}
#importStatus {
    margin-top: 14px;
    font-size: 13px;
    display: none;
    padding: 10px 14px;
    border-radius: 6px;
    line-height: 1.4;
}
#importStatus.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
#importStatus.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
#importSpinner {
    display: none;
    text-align: center;
    padding: 12px 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
}
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="top-nav">
            <a class="btn btn-secondary" href="tender_master_entry.php">+ New Entry</a>
            <a class="btn btn-secondary" href="tender_master.php">View Records</a>
            <button class="btn btn-purple" onclick="openImportModal()">
                ⬆ Import AS400
            </button>
            <?php if ($totalRows > 0): ?>
            <a class="btn btn-danger" href="tender_master_delete.php?all=1" onclick="return confirm('⚠️ ARE YOU SURE?\n\nThis will permanently delete ALL tender records in Tender Master.\nThis action cannot be undone.');">
                🗑 Delete All
            </a>
            <?php endif; ?>
        </div>

        <div class="page-header">
            <h1>Tender Master</h1>
            <p>Manage payment tenders imported from AS400 (<strong>MMLTSLIB.CSHTRN</strong>).</p>
        </div>

        <?php if ($saved): ?>
            <div class="success">✓ Tender record saved successfully.</div>
        <?php endif; ?>
        <?php if ($deleted): ?>
            <div class="success">✓ Tender record deleted successfully.</div>
        <?php endif; ?>
        <?php if ($deletedAll): ?>
            <div class="success">✓ All tender records have been deleted successfully.</div>
        <?php endif; ?>

        <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
            <form class="search-bar" method="GET" action="tender_master.php" style="margin-bottom:0; flex:1; display:flex; gap:8px; flex-wrap:wrap; max-width:none;">
                <input type="text" name="q" placeholder="Search by tender code or name..."
                       value="<?php echo htmlspecialchars($search); ?>" style="flex:2; min-width:180px;">
                <?php if ($perPage !== 25): ?>
                    <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                <?php endif; ?>
                <button type="submit" style="margin-top:0; padding:8px 20px; border-radius:20px; font-weight:600;">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="tender_master.php<?php echo $perPage !== 25 ? '?per_page=' . $perPage : ''; ?>" class="btn btn-secondary" style="margin-top:0; padding:8px 18px; border-radius:20px; text-decoration:none; font-size:13px; display:inline-flex; align-items:center;">Clear</a>
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

        <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 14px;">
        <table style="margin-top: 0; border: none;">
        <thead>
            <tr>
                <th style="width: 70px;">ID</th>
                <th style="width: 140px;">Tender Code</th>
                <th>Tender Name</th>
                <th style="width: 200px;">Updated</th>
                <th style="width: 130px; text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding: 28px; color: #8892a4;">
                        No tender records found. Click <strong>⬆ Import AS400</strong> to load tenders from AS400 (<strong>MMLTSLIB.CSHTRN</strong>) or <strong>+ New Entry</strong> to create one.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><strong style="color: #0284c7; font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($row['tender_code']); ?></strong></td>
                    <td><strong style="color: #1e293b;"><?php echo htmlspecialchars($row['tender_name']); ?></strong></td>
                    <td style="color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($row['updated_at']); ?></td>
                    <td style="white-space: nowrap; text-align: right;">
                        <a class="btn" style="padding: 4px 10px; font-size: 12px;" href="tender_master_entry.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                        <a class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; background: #dc2626; color:#fff !important;" href="tender_master_delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Are you sure you want to delete tender [<?php echo htmlspecialchars($row['tender_code']); ?>] <?php echo htmlspecialchars($row['tender_name']); ?>?');">Delete</a>
                    </td>
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
            if ($perPage !== 25) $queryParams['per_page'] = $perPage;
            
            function pageUrl($p, $params) {
                $params['page'] = $p;
                return 'tender_master.php?' . http_build_query($params);
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

<!-- Import AS400 Modal -->
<div class="modal-overlay" id="importModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeImportModal()">✕</button>
        <h2>⬆ Import from AS400</h2>
        <p>This will <strong>delete all existing tender records</strong> and reimport from AS400 (<strong>MMLTSLIB.CSHTRN</strong>) using the saved AS400 connection.</p>

        <div id="importSpinner">⏳ Connecting to AS400 &amp; importing CSHTRN... please wait.</div>
        <div id="importStatus"></div>

        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeImportModal()">Cancel</button>
            <button type="button" class="btn-import-go" id="btnRunImport" onclick="runImport()">Import Now</button>
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
    const btn = document.getElementById('btnRunImport');

    btn.disabled = true;
    document.getElementById('importSpinner').style.display = 'block';
    document.getElementById('importStatus').style.display = 'none';

    fetch('import_tender_ajax.php', { method: 'POST', body: new FormData() })
        .then(r => r.json())
        .then(data => {
            document.getElementById('importSpinner').style.display = 'none';
            btn.disabled = false;
            if (data.success) {
                const errNote = data.errors && data.errors.length > 0
                    ? ` (${data.errors.length} error(s))` : '';
                showStatus(`✅ Success! Imported ${data.inserted} tender(s) from AS400 ${data.library}.CSHTRN via DSN '${data.dsn}'.${errNote}`, true);
                setTimeout(() => { window.location.href = 'tender_master.php'; }, 1600);
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

function showStatus(msg, success) {
    const el = document.getElementById('importStatus');
    el.textContent = msg;
    el.className = success ? 'success' : 'error';
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
