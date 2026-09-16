<?php
// register_master.php - Lists register master records, supports search
require_once __DIR__ . '/auth.php';
requireNavAccess('register_master');
require_once __DIR__ . '/db.php';
$currentPage = 'register_master';


$search      = isset($_GET['q']) ? trim($_GET['q']) : '';
$storeFilter = isset($_GET['store']) ? trim($_GET['store']) : '';
$saved       = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;
$page        = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage     = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [25, 50, 100, 200]) ? (int)$_GET['per_page'] : 25;

$totalRows  = getRegisterMastersCount($search, $storeFilter);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$rows   = getAllRegisterMasters($search, $perPage, $offset, $storeFilter);
$stores = getAllStoreMasters();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register Master - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
/* Import Modal */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: #1e2130;
    border: 1px solid #2d3555;
    border-radius: 12px;
    padding: 32px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
    position: relative;
}
.modal-box h2 { margin: 0 0 8px; font-size: 17px; color: #e8ecf4; }
.modal-box p  { margin: 0 0 18px; font-size: 13px; color: #8892a4; }
.modal-box label { font-size: 12px; color: #8892a4; margin-bottom: 4px; display: block; }
.modal-box select,
.modal-box input[type=text] {
    width: 100%;
    padding: 9px 12px;
    border-radius: 7px;
    border: 1px solid #2d3555;
    background: #141824;
    color: #e8ecf4;
    font-size: 13px;
    margin-bottom: 10px;
    box-sizing: border-box;
}
.modal-box .modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.modal-box .modal-actions button { flex: 1; padding: 10px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.18s ease; }
.btn-import-go  { background: linear-gradient(135deg, #4f8ef7, #2d6cdf); color: #fff; }
.btn-cancel     { background: #252a3d; color: #8892a4; }
.modal-close {
    position: absolute; top: 12px; right: 16px;
    background: none; border: none; color: #8892a4;
    font-size: 20px; cursor: pointer; line-height: 1;
}
#importStatus {
    margin-top: 14px; font-size: 13px; display: none;
    padding: 10px 14px; border-radius: 8px;
}
#importStatus.success { background: #1a3a28; color: #3fca7f; border: 1px solid #2d5a3f; }
#importStatus.error   { background: #3a1a1a; color: #f07070; border: 1px solid #5a2d2d; }
#importSpinner { display: none; text-align: center; padding: 12px 0; color: #8892a4; font-size: 13px; }

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
            <a href="register_master_entry.php">+ New Entry</a>
            <a href="register_master.php">View Records</a>
            <button class="btn" onclick="openImportModal()" style="background: linear-gradient(135deg,#a855f7,#7c3aed); color:#fff; border:none; padding:8px 18px; border-radius:20px; cursor:pointer; font-size:13px; font-weight:600; margin-top:0;">
                ⬆ Import TopSpeed
            </button>
        </div>

        <div class="page-header">
            <h1>Register Master</h1>
            <p>Manage register / POS machine details by store.</p>
        </div>

        <?php if ($saved): ?>
            <div class="success">Register entry saved successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div class="success">Register entry deleted successfully.</div>
        <?php endif; ?>

        <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
            <form class="search-bar" method="GET" action="register_master.php" style="margin-bottom:0; flex:1; display:flex; gap:8px; flex-wrap:wrap; max-width:none;">
                <input type="text" name="q" placeholder="Search by store code, register no., serial, permit, or MIN..."
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

                <?php if ($perPage !== 25): ?>
                    <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                <?php endif; ?>
                <button type="submit" style="margin-top:0; padding:8px 20px; border-radius:20px; font-weight:600;">Filter</button>
                <?php if ($search !== '' || $storeFilter !== ''): ?>
                    <a href="register_master.php<?php echo $perPage !== 25 ? '?per_page=' . $perPage : ''; ?>" class="btn btn-secondary" style="margin-top:0; padding:8px 18px; border-radius:20px; text-decoration:none; font-size:13px; display:inline-flex; align-items:center;">Clear</a>
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
                <th style="width: 60px;">ID</th>
                <th style="width: 120px;">Store Code</th>
                <th style="width: 110px;">Reg No.</th>
                <th style="width: 150px;">Serial No.</th>
                <th style="width: 190px;">Permit No.</th>
                <th style="width: 190px;">MIN No.</th>
                <th style="width: 160px;">Updated</th>
                <th style="width: 120px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" style="text-align:center; padding:20px; color:#888;">No register records found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><strong style="color: #2d6cdf;"><?php echo htmlspecialchars($row['store_code']); ?></strong></td>
                    <td><strong><?php echo htmlspecialchars($row['reg_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['serial_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['permit_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['min_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                    <td style="white-space: nowrap;">
                        <a class="btn" style="padding: 4px 8px; font-size: 11px;" href="register_master_entry.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                        <a class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; background: #dc3545;" href="register_master_delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Are you sure you want to delete this register?');">Delete</a>
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
            if ($storeFilter !== '') $queryParams['store'] = $storeFilter;
            if ($perPage !== 25) $queryParams['per_page'] = $perPage;
            
            function pageUrl($p, $params) {
                $params['page'] = $p;
                return 'register_master.php?' . http_build_query($params);
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

<!-- Import TopSpeed Modal -->
<div class="modal-overlay" id="importModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeImportModal()">✕</button>
        <h2>⬆ Import from TopSpeed</h2>
        <p>Select or type the Store Code to associate with the imported REGSET.TPS records.</p>

        <label for="importStoreSelect">Choose Store Code</label>
        <?php if (!empty($stores)): ?>
            <select id="importStoreSelect" onchange="document.getElementById('importStoreCode').value = this.value;">
                <option value="">-- Select from Store Master --</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['store_code']); ?>">
                        <?php echo htmlspecialchars($s['store_code'] . ' - ' . $s['store_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <label for="importStoreCode">Or type Store Code manually</label>
        <input type="text" id="importStoreCode" placeholder="e.g. 12027" />

        <div id="importSpinner">⏳ Importing... please wait.</div>
        <div id="importStatus"></div>

        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeImportModal()">Cancel</button>
            <button class="btn-import-go" onclick="runImport()">Import Now</button>
        </div>
    </div>
</div>

<script>
function openImportModal() {
    document.getElementById('importModal').classList.add('active');
    document.getElementById('importStatus').style.display = 'none';
    document.getElementById('importStatus').textContent = '';
    document.getElementById('importSpinner').style.display = 'none';
    const sel = document.getElementById('importStoreSelect');
    if (sel) { sel.value = ''; }
    document.getElementById('importStoreCode').value = '';
}

function closeImportModal() {
    document.getElementById('importModal').classList.remove('active');
}

document.getElementById('importModal').addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});

function runImport() {
    const storeCode = document.getElementById('importStoreCode').value.trim();
    if (!storeCode) {
        showStatus('Please enter or select a Store Code before importing.', false);
        return;
    }

    document.getElementById('importSpinner').style.display = 'block';
    document.getElementById('importStatus').style.display = 'none';

    const fd = new FormData();
    fd.append('store_code', storeCode);

    fetch('import_regset_ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            document.getElementById('importSpinner').style.display = 'none';
            if (data.success) {
                const errNote = data.errors && data.errors.length > 0
                    ? ` (${data.errors.length} error(s))` : '';
                showStatus(`✅ Done! ${data.inserted} record(s) imported / updated from ${data.total} rows.${errNote}`, true);
                setTimeout(() => { window.location.reload(); }, 2000);
            } else {
                showStatus('❌ Import failed: ' + (data.error || 'Unknown error.'), false);
            }
        })
        .catch(err => {
            document.getElementById('importSpinner').style.display = 'none';
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
