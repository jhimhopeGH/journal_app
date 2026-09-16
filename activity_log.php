<?php
// activity_log.php - User Activity Log Viewer (Admin Only)
require_once __DIR__ . '/auth.php';
requireNavAccess('activity_log');
require_once __DIR__ . '/db.php';

$currentPage = 'activity_log';
$currentUser = getCurrentUser();
$isAdminUser = isAdmin();

$success = '';
$error = '';

// Handle log cleanup (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    if (!$isAdminUser) {
        $error = 'Permission denied: Only administrators can clear activity logs.';
    } else {
        $olderThan = (int)($_POST['older_than_days'] ?? 30);
        try {
            if ($olderThan === 0) {
                $pdo->exec("DELETE FROM activity_logs");
                try {
                    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='activity_logs'");
                } catch (Exception $e) {}
                logActivity('delete_all', 'activity_log', 'Cleared all activity logs');
                $success = 'All activity logs have been successfully wiped.';
            } else {
                $cutoffDate = date('Y-m-d 00:00:00', strtotime("-$olderThan days"));
                $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < :cutoff");
                $stmt->execute([':cutoff' => $cutoffDate]);
                $countDeleted = $stmt->rowCount();
                logActivity('delete', 'activity_log', "Cleared $countDeleted logs older than $olderThan days");
                $success = "Successfully removed $countDeleted logs older than $olderThan days (before $cutoffDate).";
            }
        } catch (Exception $e) {
            $error = 'Database error while clearing logs: ' . $e->getMessage();
        }
    }
}

// Filter parameters
$search    = trim($_GET['q'] ?? '');
$fAction   = trim($_GET['action_filter'] ?? '');
$fModule   = trim($_GET['module_filter'] ?? '');
$fUser     = trim($_GET['user_filter'] ?? '');
$fDateFrom = trim($_GET['date_from'] ?? '');
$fDateTo   = trim($_GET['date_to'] ?? '');
$rawLimit = (int)($_GET['limit'] ?? 25);
$perPage  = in_array($rawLimit, [25, 50, 100, 200], true) ? $rawLimit : 25;
$page      = max(1, (int)($_GET['p'] ?? 1));
$offset    = ($page - 1) * $perPage;

// Build query conditions
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(username LIKE :search OR detail LIKE :search OR ip_address LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($fAction !== '') {
    $where[] = "action = :fAction";
    $params[':fAction'] = $fAction;
}
if ($fModule !== '') {
    $where[] = "module = :fModule";
    $params[':fModule'] = $fModule;
}
if ($fUser !== '') {
    $where[] = "username = :fUser";
    $params[':fUser'] = $fUser;
}
if ($fDateFrom !== '') {
    $where[] = "created_at >= :dateFrom";
    $params[':dateFrom'] = $fDateFrom . ' 00:00:00';
}
if ($fDateTo !== '') {
    $where[] = "created_at <= :dateTo";
    $params[':dateTo'] = $fDateTo . ' 23:59:59';
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// CSV Export handling
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = "SELECT * FROM activity_logs $whereSql ORDER BY id DESC";
    $stmtExp = $pdo->prepare($exportSql);
    $stmtExp->execute($params);
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Ymd_His') . '.csv"');
    
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 display
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID', 'Date & Time', 'User ID', 'Username', 'Action', 'Module', 'Detail', 'IP Address']);
    
    while ($row = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['id'],
            $row['created_at'],
            $row['user_id'],
            $row['username'],
            $row['action'],
            $row['module'],
            $row['detail'],
            $row['ip_address']
        ]);
    }
    fclose($out);
    exit;
}

// Total count for pagination
$countSql = "SELECT COUNT(*) FROM activity_logs $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages   = max(1, (int)ceil($totalRecords / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch records
$dataSql = "SELECT * FROM activity_logs $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $dataStmt->bindValue($k, $v);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$logs = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics calculation
$metricTotalLogs    = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$metricTodayLogs    = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE date(created_at) = date('now', 'localtime')")->fetchColumn();
$metricFailedToday  = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed' AND date(created_at) = date('now', 'localtime')")->fetchColumn();
$metricActiveUsers  = (int)$pdo->query("SELECT COUNT(DISTINCT username) FROM activity_logs WHERE date(created_at) = date('now', 'localtime') AND username != 'guest'")->fetchColumn();

// Fetch distinct usernames and modules for filter dropdowns
$availableUsers = $pdo->query("SELECT DISTINCT username FROM activity_logs WHERE username != '' ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
$availableModules = $pdo->query("SELECT DISTINCT module FROM activity_logs WHERE module != '' ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);

// Module friendly names
$moduleLabels = [
    'auth'            => 'Authentication',
    'journal'         => 'Zread Reprint',
    'ej'              => 'EJ Printing',
    'users'           => 'User Management',
    'store_master'    => 'Store Master',
    'register_master' => 'Register Master',
    'tender_master'   => 'Tender Master',
    'tps'             => 'TPS Import',
    'as400'           => 'AS400 Import',
    'activity_log'    => 'Activity Log'
];

function getActionBadgeInfo($action) {
    switch ($action) {
        case 'login':
            return ['bg' => '#e0f2fe', 'color' => '#0369a1', 'border' => '#bae6fd', 'label' => 'Login', 'icon' => '🔑'];
        case 'login_failed':
            return ['bg' => '#fee2e2', 'color' => '#b91c1c', 'border' => '#fca5a5', 'label' => 'Failed Login', 'icon' => '🚫'];
        case 'logout':
            return ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1', 'label' => 'Logout', 'icon' => '🚪'];
        case 'create':
            return ['bg' => '#dcfce7', 'color' => '#15803d', 'border' => '#86efac', 'label' => 'Create', 'icon' => '➕'];
        case 'edit':
            return ['bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fde68a', 'label' => 'Edit', 'icon' => '✏️'];
        case 'delete':
            return ['bg' => '#ffe4e6', 'color' => '#be123c', 'border' => '#fecdd3', 'label' => 'Delete', 'icon' => '🗑️'];
        case 'delete_all':
            return ['bg' => '#fecaca', 'color' => '#7f1d1d', 'border' => '#f87171', 'label' => 'Delete All', 'icon' => '💥'];
        case 'import':
            return ['bg' => '#ccfbf1', 'color' => '#0f766e', 'border' => '#99f6e4', 'label' => 'Import', 'icon' => '📥'];
        case 'print':
            return ['bg' => '#ede9fe', 'color' => '#6d28d9', 'border' => '#ddd6fe', 'label' => 'Direct Print', 'icon' => '🖨️'];
        default:
            return ['bg' => '#f3f4f6', 'color' => '#374151', 'border' => '#e5e7eb', 'label' => ucfirst($action), 'icon' => '⚡'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
    .log-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .log-stat-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 16px;
        padding: 18px 22px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .log-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(0,0,0,0.08);
    }
    .log-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .log-stat-val {
        font-size: 26px;
        font-weight: 800;
        line-height: 1.1;
        color: #0f172a;
    }
    .log-stat-lbl {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 3px;
    }

    /* Action & Module Badges */
    .badge-action {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .badge-module {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .ip-tag {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        color: #64748b;
        background: #f8fafc;
        padding: 2px 7px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        display: inline-block;
    }
    .user-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .user-avatar-sm {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .user-avatar-guest {
        background: #94a3b8;
    }
    .detail-text {
        max-width: 440px;
        word-break: break-word;
        line-height: 1.4;
        font-size: 12.5px;
        color: #1e293b;
    }
    .time-primary {
        font-weight: 600;
        color: #0f172a;
        font-size: 12.5px;
        white-space: nowrap;
    }
    .time-secondary {
        font-size: 11px;
        color: #94a3b8;
        white-space: nowrap;
    }

    /* Filter Form Toolbar */
    .filter-panel {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 16px 20px;
        margin-bottom: 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .filter-group label {
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .filter-group input,
    .filter-group select {
        padding: 7px 11px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        font-size: 12.5px;
        background: #fff;
        outline: none;
    }
    .filter-group input:focus,
    .filter-group select:focus {
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
    }

    /* Pagination */
    .pagination-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 18px;
        background: #f8fafc;
        border-top: 1px solid #cbd5e1;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }
    .page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 9px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        text-decoration: none;
        transition: all 0.12s ease;
    }
    .page-btn:hover {
        background: #ede9fe;
        color: #6d28d9;
        border-color: #ddd6fe;
    }
    .page-btn.active {
        background: #7c3aed;
        color: #fff;
        border-color: #7c3aed;
    }
    .page-btn.disabled {
        opacity: 0.4;
        pointer-events: none;
    }

    /* Modal */
    .modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .modal-box {
        background: #ffffff;
        border-radius: 18px;
        padding: 24px 28px;
        width: 440px;
        max-width: 92%;
        box-shadow: 0 20px 35px rgba(0,0,0,0.25);
    }
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
            <div>
                <h1>User Activity Log</h1>
                <p>Complete audit trail of user sessions, authentication events, prints, records changes, and imports.</p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php
                // Build current query params for CSV export link
                $expParams = $_GET;
                $expParams['export'] = 'csv';
                $csvUrl = 'activity_log.php?' . http_build_query($expParams);
                ?>
                <a href="<?php echo htmlspecialchars($csvUrl); ?>" class="btn btn-secondary" style="margin-top: 0; padding: 7px 15px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Export CSV
                </a>
                <?php if ($isAdminUser): ?>
                    <button type="button" class="btn btn-danger" onclick="openClearModal()" style="margin-top: 0; padding: 7px 15px; font-weight: 600; background: #dc2626; display: inline-flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Clear Logs
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="success" style="margin-bottom: 16px;">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="danger" style="margin-bottom: 16px; background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:10px 14px; border-radius:8px;">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Activity Metrics -->
        <div class="log-stats-grid">
            <div class="log-stat-card">
                <div class="log-stat-icon" style="background: #ede9fe; color: #7c3aed;">📋</div>
                <div>
                    <div class="log-stat-val"><?php echo number_format($metricTotalLogs); ?></div>
                    <div class="log-stat-lbl">Total Log Entries</div>
                </div>
            </div>
            <div class="log-stat-card">
                <div class="log-stat-icon" style="background: #e0f2fe; color: #0284c7;">⚡</div>
                <div>
                    <div class="log-stat-val"><?php echo number_format($metricTodayLogs); ?></div>
                    <div class="log-stat-lbl">Today's Activities</div>
                </div>
            </div>
            <div class="log-stat-card">
                <div class="log-stat-icon" style="background: #fee2e2; color: #dc2626;">🛡️</div>
                <div>
                    <div class="log-stat-val" style="color: <?php echo $metricFailedToday > 0 ? '#dc2626' : '#0f172a'; ?>;"><?php echo number_format($metricFailedToday); ?></div>
                    <div class="log-stat-lbl">Failed Logins (Today)</div>
                </div>
            </div>
            <div class="log-stat-card">
                <div class="log-stat-icon" style="background: #dcfce7; color: #16a34a;">👥</div>
                <div>
                    <div class="log-stat-val"><?php echo number_format($metricActiveUsers); ?></div>
                    <div class="log-stat-lbl">Active Users Today</div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="filter-panel">
            <form method="GET" action="activity_log.php">
                <div class="filter-row">
                    <div class="filter-group" style="flex: 2; min-width: 200px;">
                        <label>Search Keyword</label>
                        <input type="text" name="q" placeholder="Username, detail, or IP..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group" style="min-width: 140px;">
                        <label>Action</label>
                        <select name="action_filter">
                            <option value="">All Actions</option>
                            <option value="login" <?php echo $fAction === 'login' ? 'selected' : ''; ?>>Login</option>
                            <option value="login_failed" <?php echo $fAction === 'login_failed' ? 'selected' : ''; ?>>Failed Login</option>
                            <option value="logout" <?php echo $fAction === 'logout' ? 'selected' : ''; ?>>Logout</option>
                            <option value="create" <?php echo $fAction === 'create' ? 'selected' : ''; ?>>Create</option>
                            <option value="edit" <?php echo $fAction === 'edit' ? 'selected' : ''; ?>>Edit</option>
                            <option value="delete" <?php echo $fAction === 'delete' ? 'selected' : ''; ?>>Delete</option>
                            <option value="delete_all" <?php echo $fAction === 'delete_all' ? 'selected' : ''; ?>>Delete All</option>
                            <option value="print" <?php echo $fAction === 'print' ? 'selected' : ''; ?>>Print</option>
                            <option value="import" <?php echo $fAction === 'import' ? 'selected' : ''; ?>>Import</option>
                        </select>
                    </div>

                    <div class="filter-group" style="min-width: 140px;">
                        <label>Module</label>
                        <select name="module_filter">
                            <option value="">All Modules</option>
                            <?php foreach ($availableModules as $mod): ?>
                                <option value="<?php echo htmlspecialchars($mod); ?>" <?php echo $fModule === $mod ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($moduleLabels[$mod] ?? ucfirst($mod)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group" style="min-width: 130px;">
                        <label>User</label>
                        <select name="user_filter">
                            <option value="">All Users</option>
                            <?php foreach ($availableUsers as $u): ?>
                                <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $fUser === $u ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group" style="min-width: 130px;">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($fDateFrom); ?>">
                    </div>

                    <div class="filter-group" style="min-width: 130px;">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($fDateTo); ?>">
                    </div>

                    <div class="filter-group" style="min-width: 90px;">
                        <label>Per Page</label>
                        <select name="limit">
                            <option value="25" <?php echo $perPage === 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $perPage === 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 6px;">
                        <button type="submit" class="btn" style="margin-top: 0; padding: 7px 16px; font-weight: 700;">Filter</button>
                        <?php if ($search !== '' || $fAction !== '' || $fModule !== '' || $fUser !== '' || $fDateFrom !== '' || $fDateTo !== '' || $perPage !== 25): ?>
                            <a href="activity_log.php" class="btn btn-secondary" style="margin-top: 0; padding: 7px 12px;">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <table style="margin-top: 0; border: none; width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 150px;">Date & Time</th>
                        <th style="width: 140px;">User</th>
                        <th style="width: 110px;">Action</th>
                        <th style="width: 125px;">Module</th>
                        <th>Activity Details</th>
                        <th style="width: 120px; text-align: right;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #94a3b8; padding: 36px 16px; font-size: 14px;">
                                <div style="font-size: 28px; margin-bottom: 8px;">🔍</div>
                                No activity logs found matching the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $badge = getActionBadgeInfo($log['action']);
                            $isGuest = ($log['username'] === 'guest' || empty($log['username']));
                            $init = strtoupper(substr($log['username'] ?: 'G', 0, 1));
                            $modName = $moduleLabels[$log['module']] ?? ucfirst($log['module']);
                            ?>
                            <tr>
                                <td style="color: #94a3b8; font-size: 11px;">#<?php echo (int)$log['id']; ?></td>
                                <td>
                                    <div class="time-primary"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                    <div class="time-secondary"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="user-pill">
                                        <div class="user-avatar-sm <?php echo $isGuest ? 'user-avatar-guest' : ''; ?>">
                                            <?php echo htmlspecialchars($init); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: #0f172a; font-size: 12.5px;">
                                                <?php echo htmlspecialchars($log['username'] ?: 'guest'); ?>
                                            </div>
                                            <?php if ((int)$log['user_id'] > 0): ?>
                                                <div style="font-size: 10.5px; color: #94a3b8;">UID: <?php echo (int)$log['user_id']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-action" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>; border-color: <?php echo $badge['border']; ?>;">
                                        <span><?php echo $badge['icon']; ?></span>
                                        <span><?php echo htmlspecialchars($badge['label']); ?></span>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-module"><?php echo htmlspecialchars($modName); ?></span>
                                </td>
                                <td>
                                    <div class="detail-text"><?php echo htmlspecialchars($log['detail'] ?: '—'); ?></div>
                                </td>
                                <td style="text-align: right;">
                                    <span class="ip-tag"><?php echo htmlspecialchars($log['ip_address'] ?: '127.0.0.1'); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination Toolbar -->
            <div class="pagination-bar">
                <div style="font-size: 12.5px; color: #64748b;">
                    Showing <strong><?php echo $totalRecords > 0 ? $offset + 1 : 0; ?></strong> to <strong><?php echo min($offset + $perPage, $totalRecords); ?></strong> of <strong><?php echo number_format($totalRecords); ?></strong> records
                </div>

                <?php if ($totalPages > 1): ?>
                    <div style="display: flex; gap: 4px; align-items: center;">
                        <?php
                        $baseParams = $_GET;
                        $makePageUrl = function($p) use ($baseParams) {
                            $baseParams['p'] = $p;
                            return 'activity_log.php?' . http_build_query($baseParams);
                        };
                        ?>
                        <a href="<?php echo htmlspecialchars($makePageUrl(1)); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" title="First Page">&laquo;</a>
                        <a href="<?php echo htmlspecialchars($makePageUrl($page - 1)); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" title="Previous">&lsaquo;</a>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="<?php echo htmlspecialchars($makePageUrl($i)); ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <a href="<?php echo htmlspecialchars($makePageUrl($page + 1)); ?>" class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" title="Next">&rsaquo;</a>
                        <a href="<?php echo htmlspecialchars($makePageUrl($totalPages)); ?>" class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" title="Last Page">&raquo;</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Clear Logs Modal (Admin Only) -->
<?php if ($isAdminUser): ?>
<div id="clearModal" class="modal-backdrop">
    <div class="modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; color: #b91c1c; display: flex; align-items: center; gap: 8px;">
                <span>⚠️</span> Clear Activity Logs
            </h3>
            <button type="button" onclick="closeClearModal()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; padding: 0;">&times;</button>
        </div>

        <p style="font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 16px;">
            Clearing activity logs will permanently remove audit records from the system database. Please select your retention policy:
        </p>

        <form method="POST" action="activity_log.php">
            <input type="hidden" name="action" value="clear_logs">

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 8px;">Retention Option</label>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 9px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="radio" name="older_than_days" value="30" checked>
                        <span>Delete logs older than <strong>30 days</strong></span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 9px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="radio" name="older_than_days" value="60">
                        <span>Delete logs older than <strong>60 days</strong></span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 9px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="radio" name="older_than_days" value="90">
                        <span>Delete logs older than <strong>90 days</strong></span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 9px; font-size: 13px; color: #b91c1c; cursor: pointer; padding-top: 6px; border-top: 1px dashed #fca5a5;">
                        <input type="radio" name="older_than_days" value="0">
                        <span><strong>Delete ALL logs</strong> (Complete purge)</span>
                    </label>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-secondary" onclick="closeClearModal()" style="margin-top: 0; padding: 7px 16px;">Cancel</button>
                <button type="submit" class="btn btn-danger" style="margin-top: 0; padding: 7px 18px; background: #dc2626; font-weight: 700;">Confirm & Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openClearModal() {
    document.getElementById('clearModal').style.display = 'flex';
}
function closeClearModal() {
    document.getElementById('clearModal').style.display = 'none';
}
window.addEventListener('click', function(e) {
    var modal = document.getElementById('clearModal');
    if (e.target === modal) {
        closeClearModal();
    }
});
</script>
<?php endif; ?>

</body>
</html>
