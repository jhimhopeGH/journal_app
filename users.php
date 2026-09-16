<?php
// users.php - User Management (Admin Only)
require_once __DIR__ . '/auth.php';
requireNavAccess('users');

$currentPage = 'users';
$currentUser = getCurrentUser();

$success = '';
$error = '';

/**
 * Validate password complexity:
 * - At least 8 characters
 * - At least one uppercase letter (A-Z)
 * - At least one lowercase letter (a-z)
 * - At least one number (0-9)
 * - At least one special symbol (!@#$%^&*(),.?":{}|<>_-~+=)
 */
if (!function_exists('checkPasswordComplexity')) {
    function checkPasswordComplexity($pwd) {
        if (strlen($pwd) < 8) {
            return 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/[A-Z]/', $pwd)) {
            return 'Password must contain at least one uppercase letter (A-Z).';
        }
        if (!preg_match('/[a-z]/', $pwd)) {
            return 'Password must contain at least one lowercase letter (a-z).';
        }
        if (!preg_match('/[0-9]/', $pwd)) {
            return 'Password must contain at least one number (0-9).';
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-~+=]/', $pwd)) {
            return 'Password must contain at least one special symbol (e.g. !@#$%^&*).';
        }
        return null;
    }
}

// Handle actions: add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username   = trim($_POST['username'] ?? '');
        $fullName   = trim($_POST['full_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $role       = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
        $password   = $_POST['password'] ?? '';
        $isActive   = isset($_POST['is_active']) ? 1 : 0;
        $navPerms   = isset($_POST['nav_permissions']) && is_array($_POST['nav_permissions'])
            ? array_values($_POST['nav_permissions'])
            : ($role === 'admin' ? '*' : ['dashboard', 'journal', 'ej', 'receipt_reprint']);

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } elseif ($pwdErr = checkPasswordComplexity($password)) {
            $error = $pwdErr;
        } elseif (getUserByUsername($username)) {
            $error = "Username '$username' already exists. Please choose another.";
        } else {
            createUser($username, $password, $fullName, $role, $isActive, $navPerms, $department);
            logActivity('create', 'users', "Created user '$username' (Role: $role, Full Name: $fullName)");
            $success = "User '$username' created successfully.";
        }
    } elseif ($action === 'edit') {
        $id         = (int)($_POST['user_id'] ?? 0);
        $fullName   = trim($_POST['full_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $role       = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
        $password   = trim($_POST['password'] ?? '');
        $isActive   = isset($_POST['is_active']) ? 1 : 0;
        $navPerms   = isset($_POST['nav_permissions']) && is_array($_POST['nav_permissions'])
            ? array_values($_POST['nav_permissions'])
            : [];

        $targetUser = getUserById($id);
        if (!$targetUser) {
            $error = 'Target user not found.';
        } elseif ($targetUser['id'] == $currentUser['id'] && $isActive === 0) {
            $error = 'You cannot deactivate your own account.';
        } elseif ($password !== '' && ($pwdErr = checkPasswordComplexity($password))) {
            $error = $pwdErr;
        } else {
            // If editing self, preserve admin role
            if ($targetUser['id'] == $currentUser['id']) {
                $role = 'admin';
            }
            // Primary default admin always retains full access
            if ($targetUser['username'] === 'admin') {
                $role = 'admin';
                $navPerms = '*';
            }
            updateUser($id, $fullName, $role, $isActive, $password !== '' ? $password : null, $navPerms, $department);
            $statusStr = $isActive ? 'active' : 'inactive';
            $pwdChanged = $password !== '' ? ' (password updated)' : '';
            logActivity('edit', 'users', "Updated user '{$targetUser['username']}' (Role: $role, Status: $statusStr)$pwdChanged");
            $success = "User '{$targetUser['username']}' updated successfully.";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        $targetUser = getUserById($id);

        if (!$targetUser) {
            $error = 'User not found.';
        } elseif ($targetUser['id'] == $currentUser['id']) {
            $error = 'You cannot delete your own logged-in account.';
        } elseif ($targetUser['username'] === 'admin') {
            $error = 'The primary default admin account cannot be deleted.';
        } else {
            deleteUser($id);
            logActivity('delete', 'users', "Deleted user '{$targetUser['username']}' (ID: $id)");
            $success = "User '{$targetUser['username']}' deleted successfully.";
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['user_id'] ?? 0);
        $targetUser = getUserById($id);

        if (!$targetUser) {
            $error = 'User not found.';
        } elseif ($targetUser['id'] == $currentUser['id']) {
            $error = 'You cannot disable your own logged-in account.';
        } elseif ($targetUser['username'] === 'admin') {
            $error = 'The primary default admin account cannot be disabled.';
        } else {
            $newStatus = empty($targetUser['is_active']) ? 1 : 0;
            setUserActiveStatus($id, $newStatus);
            $actionLabel = $newStatus ? 'enabled' : 'disabled';
            logActivity('edit', 'users', "User '{$targetUser['username']}' $actionLabel");
            $success = "User '{$targetUser['username']}' has been {$actionLabel} successfully.";
        }
    }
}

$search = trim($_GET['q'] ?? '');
$users = getAllUsers($search);

// Metrics
$totalUsers = count($users);
$adminCount = 0;
$activeCount = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') $adminCount++;
    if ($u['is_active']) $activeCount++;
}
$disabledCount = $totalUsers - $activeCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
    .badge-role {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-admin {
        background: #ede9fe;
        color: #6d28d9;
        border: 1px solid #ddd6fe;
    }
    .badge-user {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-active {
        background: #dcfce7;
        color: #166534;
    }
    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    /* Modal styles */
    .modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .modal-card {
        background: #ffffff;
        border-radius: 20px;
        width: 540px;
        max-width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 26px 24px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        box-sizing: border-box;
        position: relative;
    }
    .nav-perms-box {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 12px 14px;
        max-height: 210px;
        overflow-y: auto;
    }
    .nav-perm-group-title {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        margin: 10px 0 5px;
        padding-bottom: 3px;
        border-bottom: 1px solid #e2e8f0;
    }
    .nav-perm-group-title:first-child {
        margin-top: 0;
    }
    .nav-perm-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 5px 12px;
    }
    .nav-perm-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #1e293b;
        cursor: pointer;
        margin: 0 !important;
        padding: 4px 6px;
        border-radius: 6px;
        transition: background 0.12s ease;
        text-transform: none !important;
        font-weight: 500 !important;
    }
    .nav-perm-item:hover {
        background: #e2e8f0;
    }
    .nav-perm-item input[type="checkbox"] {
        width: 15px;
        height: 15px;
        cursor: pointer;
        margin: 0 !important;
        flex-shrink: 0;
    }
    .nav-perm-quick-btn {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .nav-perm-quick-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 12px;
    }
    .modal-title {
        font-size: 17px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }
    .modal-close-btn {
        background: none;
        border: none;
        font-size: 18px;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px;
        margin: 0 !important;
        box-shadow: none !important;
    }
    .modal-close-btn:hover {
        color: #334155;
        transform: none;
    }
    .form-group {
        margin-bottom: 14px;
    }
    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 5px;
    }
    .form-group input[type="text"],
    .form-group input[type="password"],
    .form-group select {
        width: 100%;
        padding: 9px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        font-size: 13px;
        background: #fff;
        box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group select:focus {
        border-color: #7c3aed;
        outline: none;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
    }
    .pwd-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }
    .pwd-input-wrap input {
        padding-right: 40px !important;
    }
    .pwd-modal-toggle {
        position: absolute;
        right: 10px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px 6px;
        font-size: 15px;
        color: #64748b;
        margin: 0 !important;
        box-shadow: none !important;
        line-height: 1;
    }
    .pwd-modal-toggle:hover {
        color: #0f172a;
        background: none;
        transform: none;
    }
    .pwd-rules-container {
        margin-top: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 12px;
    }
    .pwd-meter-bar {
        height: 5px;
        background: #e2e8f0;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 8px;
    }
    .pwd-meter-fill {
        height: 100%;
        width: 0%;
        background: #ef4444;
        transition: width 0.25s ease, background 0.25s ease;
        border-radius: 5px;
    }
    .pwd-rules-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5px 8px;
        font-size: 11px;
    }
    .pwd-rule-item {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #94a3b8;
        font-weight: 500;
        transition: color 0.18s ease;
    }
    .pwd-rule-item.valid {
        color: #16a34a;
        font-weight: 700;
    }
    .pwd-rule-item .rule-icon {
        font-size: 11px;
        font-weight: bold;
    }
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
            <div>
                <h1>User Management</h1>
                <p>Control access privileges, user roles, and account security.</p>
            </div>
            <button type="button" class="btn" onclick="openAddModal()" style="background: #7c3aed; margin-top: 0; padding: 8px 20px; font-weight: 700;">
                + Add New User
            </button>
        </div>

        <?php if ($success !== ''): ?>
            <div class="success" style="margin-bottom: 16px;">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="danger" style="margin-bottom: 16px; background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:10px 14px; border-radius:8px;">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-grid" style="margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #7c3aed;"><?php echo number_format($adminCount); ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #16a34a;"><?php echo number_format($activeCount); ?></div>
                <div class="stat-label">Active Accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ea580c;"><?php echo number_format($disabledCount); ?></div>
                <div class="stat-label">Disabled Accounts</div>
            </div>
        </div>

        <div class="panel">
            <!-- Search toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <form method="GET" action="users.php" style="display: flex; gap: 8px; flex: 1; max-width: 360px;">
                    <input type="text" name="q" placeholder="Search by username, name or department..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 20px; font-size: 13px;">
                    <button type="submit" class="btn" style="margin-top: 0; padding: 6px 16px;">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="users.php" class="btn btn-secondary" style="margin-top: 0; padding: 6px 14px;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Users table -->
            <?php
            $navCatalog = getNavItemsCatalog();
            $totalNavItems = count($navCatalog);
            ?>
            <div style="overflow-x: auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 14px;">
            <table style="margin-top: 0; border: none;">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Nav Access</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="text-align: right; width: 195px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="9" style="text-align: center; color: #888; padding: 24px;">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong>#<?php echo (int)$u['id']; ?></strong></td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($u['username']); ?></div>
                                    <?php if ($u['id'] == $currentUser['id']): ?>
                                        <span style="font-size: 11px; color: #7c3aed; font-weight: 600;">(You)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($u['full_name'] ?: '—'); ?></td>
                                <td>
                                    <?php if (!empty($u['department'])): ?>
                                        <span style="background:#e0f2fe;color:#0369a1;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:600;"><?php echo htmlspecialchars($u['department']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge-role badge-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="badge-role badge-user">Standard User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $uPerms = getUserNavPermissions($u);
                                    $permCount = count($uPerms);
                                    $isFull = ($u['username'] === 'admin' || $permCount >= $totalNavItems);
                                    $permTitles = array_map(function($k) use ($navCatalog) {
                                        return $navCatalog[$k]['label'] ?? $k;
                                    }, $uPerms);
                                    ?>
                                    <?php if ($isFull): ?>
                                        <span class="badge-role" style="background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;" title="Full access to all <?php echo $totalNavItems; ?> navigation links">
                                            Full Access (<?php echo $totalNavItems; ?>/<?php echo $totalNavItems; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-role" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;" title="Allowed: <?php echo htmlspecialchars(implode(', ', $permTitles)); ?>">
                                            <?php echo $permCount; ?> / <?php echo $totalNavItems; ?> Tabs
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge-status status-active">● Active</span>
                                    <?php else: ?>
                                        <span class="badge-status status-inactive">○ Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($u['created_at']); ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn" style="padding: 4px 10px; font-size: 11px; margin-top: 0;"
                                            onclick='openEditModal(<?php echo json_encode($u); ?>)'>
                                        Edit
                                    </button>

                                    <?php if ($u['id'] == $currentUser['id']): ?>
                                        <button type="button" class="btn" disabled
                                                style="padding: 4px 9px; font-size: 11px; margin-top: 0; opacity: 0.45; cursor: not-allowed; background: #e2e8f0; color: #64748b;"
                                                title="You cannot disable your own logged-in account">
                                            Disable
                                        </button>
                                    <?php elseif ($u['username'] === 'admin'): ?>
                                        <button type="button" class="btn" disabled
                                                style="padding: 4px 9px; font-size: 11px; margin-top: 0; opacity: 0.45; cursor: not-allowed; background: #e2e8f0; color: #64748b;"
                                                title="Primary default admin cannot be disabled">
                                            Disable
                                        </button>
                                    <?php elseif ($u['is_active']): ?>
                                        <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to disable user [<?php echo htmlspecialchars($u['username']); ?>]? They will be blocked from logging in.');">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 4px 9px; font-size: 11px; margin-top: 0; background: #ea580c; color: #ffffff;" title="Disable this user account">
                                                Disable
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to enable user [<?php echo htmlspecialchars($u['username']); ?>]?');">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 4px 9px; font-size: 11px; margin-top: 0; background: #16a34a; color: #ffffff;" title="Enable this user account">
                                                Enable
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($u['id'] != $currentUser['id'] && $u['username'] !== 'admin'): ?>
                                        <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete user [<?php echo htmlspecialchars($u['username']); ?>]?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 4px 9px; font-size: 11px; margin-top: 0;">
                                                Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-danger" disabled
                                                style="padding: 4px 9px; font-size: 11px; margin-top: 0; opacity: 0.45; cursor: not-allowed;"
                                                title="<?php echo $u['id'] == $currentUser['id'] ? 'Cannot delete your own logged-in account' : 'Primary admin account cannot be deleted'; ?>">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </main>
</div>

<!-- Add User Modal -->
<div id="addModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">➕ Create New User</h3>
            <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
        </div>
        <form method="POST" action="users.php" autocomplete="off">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label for="add_username">Username *</label>
                <input type="text" id="add_username" name="username" required placeholder="e.g. cashier1" autocomplete="new-username">
            </div>

            <div class="form-group">
                <label for="add_full_name">Full Name</label>
                <input type="text" id="add_full_name" name="full_name" placeholder="e.g. Juan Dela Cruz">
            </div>

            <div class="form-group">
                <label for="add_department">Department</label>
                <input type="text" id="add_department" name="department" placeholder="e.g. IT, Finance, Operations">
            </div>

            <div class="form-group">
                <label for="add_role">Access Role *</label>
                <select id="add_role" name="role" required>
                    <option value="user" selected>Standard User (Custom Navigation)</option>
                    <option value="admin">Administrator (Full Access)</option>
                </select>
            </div>

            <div class="form-group" style="margin-top: 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <label style="margin: 0; font-weight: 700; color: #1e293b; font-size: 12px;">Navigation Tabs Access *</label>
                    <div style="display: flex; gap: 5px;">
                        <button type="button" class="nav-perm-quick-btn" onclick="setNavPermsSelection('add', 'all')">All</button>
                        <button type="button" class="nav-perm-quick-btn" onclick="setNavPermsSelection('add', 'default_user')">Default</button>
                        <button type="button" class="nav-perm-quick-btn" onclick="setNavPermsSelection('add', 'none')">None</button>
                    </div>
                </div>
                <div class="nav-perms-box">
                    <div class="nav-perm-group-title">Main</div>
                    <div class="nav-perm-grid">
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="dashboard" id="add_perm_dashboard">
                            <span>Dashboard</span>
                        </label>
                    </div>

                    <div class="nav-perm-group-title">Reprint</div>
                    <div class="nav-perm-grid">
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="journal" id="add_perm_journal">
                            <span>Zread Reprint</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="ej" id="add_perm_ej">
                            <span>EJ Printing</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="receipt_reprint" id="add_perm_receipt_reprint">
                            <span>Receipt Reprint</span>
                        </label>
                    </div>

                    <div class="nav-perm-group-title">Administration</div>
                    <div class="nav-perm-grid">
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="users" id="add_perm_users">
                            <span>User Management</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="admin" id="add_perm_admin">
                            <span>Database</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="store_master" id="add_perm_store_master">
                            <span>Store Master</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="register_master" id="add_perm_register_master">
                            <span>Register Master</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="tender_master" id="add_perm_tender_master">
                            <span>Tender Master</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="cashier_master" id="add_perm_cashier_master">
                            <span>Cashier Master</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="layout" id="add_perm_layout">
                            <span>Zread Layout</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="ej_layout" id="add_perm_ej_layout">
                            <span>EJ Layout</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="receipt_layout" id="add_perm_receipt_layout">
                            <span>Receipt Layout</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="activity_log" id="add_perm_activity_log">
                            <span>Activity Log</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="add_password">Password *</label>
                <div class="pwd-input-wrap">
                    <input type="password" id="add_password" name="password" required placeholder="Create a secure password..." autocomplete="new-password" oninput="validateAddPassword()">
                    <button type="button" class="pwd-modal-toggle" onclick="toggleModalPwd('add_password', this)" title="Toggle visibility">👁️</button>
                </div>
                
                <div class="pwd-rules-container">
                    <div class="pwd-meter-bar">
                        <div id="add_pwd_fill" class="pwd-meter-fill"></div>
                    </div>
                    <div class="pwd-rules-grid">
                        <div id="add_rule_len" class="pwd-rule-item">
                            <span class="rule-icon">○</span> 8+ characters
                        </div>
                        <div id="add_rule_upper" class="pwd-rule-item">
                            <span class="rule-icon">○</span> 1 uppercase (A-Z)
                        </div>
                        <div id="add_rule_lower" class="pwd-rule-item">
                            <span class="rule-icon">○</span> 1 lowercase (a-z)
                        </div>
                        <div id="add_rule_num" class="pwd-rule-item">
                            <span class="rule-icon">○</span> 1 number (0-9)
                        </div>
                        <div id="add_rule_sym" class="pwd-rule-item" style="grid-column: span 2;">
                            <span class="rule-icon">○</span> 1 special symbol (!@#$%^&*...)
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top: 14px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                    <input type="checkbox" name="is_active" value="1" checked style="width: auto;">
                    <span>Account is Active &amp; Allowed to Log In</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()" style="margin-top: 0; padding: 8px 18px;">Cancel</button>
                <button type="submit" id="btn_submit_add" class="btn" style="background: #7c3aed; margin-top: 0; padding: 8px 22px;">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit User: <span id="edit_title_username" style="color: #7c3aed;"></span></h3>
            <button type="button" class="modal-close-btn" onclick="closeEditModal()">✕</button>
        </div>
        <form method="POST" action="users.php" autocomplete="off" onsubmit="return validateEditForm()">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id" value="">

            <div class="form-group">
                <label>Username</label>
                <input type="text" id="edit_username" disabled style="background: #f1f5f9; color: #64748b;">
            </div>

            <div class="form-group">
                <label for="edit_full_name">Full Name</label>
                <input type="text" id="edit_full_name" name="full_name" placeholder="Full name">
            </div>

            <div class="form-group">
                <label for="edit_department">Department</label>
                <input type="text" id="edit_department" name="department" placeholder="e.g. IT, Finance, Operations">
            </div>

            <div class="form-group">
                <label for="edit_role">Access Role *</label>
                <select id="edit_role" name="role" required>
                    <option value="user">Standard User (Custom Navigation)</option>
                    <option value="admin">Administrator (Full Access)</option>
                </select>
            </div>

            <div class="form-group" style="margin-top: 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <label style="margin: 0; font-weight: 700; color: #1e293b; font-size: 12px;">Navigation Tabs Access *</label>
                    <div id="edit_perm_actions" style="display: flex; gap: 5px;">
                        <button type="button" class="nav-perm-quick-btn" onclick="setNavPermsSelection('edit', 'all')">All</button>
                        <button type="button" class="nav-perm-quick-btn" onclick="setNavPermsSelection('edit', 'default_user')">Default</button>
                        <button type="button" class="nav-perm-quick-btn" onclick="setNavPermsSelection('edit', 'none')">None</button>
                    </div>
                </div>
                <div class="nav-perms-box">
                    <div class="nav-perm-group-title">Main</div>
                    <div class="nav-perm-grid">
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="dashboard" id="edit_perm_dashboard">
                            <span>Dashboard</span>
                        </label>
                    </div>

                    <div class="nav-perm-group-title">Reprint</div>
                    <div class="nav-perm-grid">
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="journal" id="edit_perm_journal">
                            <span>Zread Reprint</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="ej" id="edit_perm_ej">
                            <span>EJ Printing</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="receipt_reprint" id="edit_perm_receipt_reprint">
                            <span>Receipt Reprint</span>
                        </label>
                    </div>

                    <div class="nav-perm-group-title">Administration</div>
                    <div class="nav-perm-grid">
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="users" id="edit_perm_users">
                            <span>User Management</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="admin" id="edit_perm_admin">
                            <span>Database</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="store_master" id="edit_perm_store_master">
                            <span>Store Master</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="register_master" id="edit_perm_register_master">
                            <span>Register Master</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="tender_master" id="edit_perm_tender_master">
                            <span>Tender Master</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="cashier_master" id="edit_perm_cashier_master">
                            <span>Cashier Master</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="layout" id="edit_perm_layout">
                            <span>Zread Layout</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="ej_layout" id="edit_perm_ej_layout">
                            <span>EJ Layout</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="receipt_layout" id="edit_perm_receipt_layout">
                            <span>Receipt Layout</span>
                        </label>
                        <label class="nav-perm-item">
                            <input type="checkbox" name="nav_permissions[]" value="activity_log" id="edit_perm_activity_log">
                            <span>Activity Log</span>
                        </label>
                    </div>
                </div>
                <div id="edit_admin_note" style="display: none; font-size: 11px; color: #6d28d9; margin-top: 6px; font-weight: 600;">
                    🛡️ The primary default admin account always maintains full access to all system tabs.
                </div>
            </div>

            <div class="form-group">
                <label for="edit_password">Reset Password (leave blank to keep current)</label>
                <div class="pwd-input-wrap">
                    <input type="password" id="edit_password" name="password" placeholder="Leave empty to retain existing password..." autocomplete="new-password" oninput="validateEditPassword()">
                    <button type="button" class="pwd-modal-toggle" onclick="toggleModalPwd('edit_password', this)" title="Toggle visibility">👁️</button>
                </div>

                <div id="edit_pwd_container" class="pwd-rules-container" style="display: none;">
                    <div class="pwd-meter-bar">
                        <div id="edit_pwd_fill" class="pwd-meter-fill"></div>
                    </div>
                    <div class="pwd-rules-grid">
                        <div id="edit_rule_len" class="pwd-rule-item">
                            <span class="rule-icon">○</span> 8+ characters
                        </div>
                        <div id="edit_rule_upper" class="pwd-rule-item">
                            <span class="rule-icon">○</span> 1 uppercase (A-Z)
                        </div>
                        <div id="edit_rule_lower" class="pwd-rule-item">
                            <span class="rule-icon">○</span> 1 lowercase (a-z)
                        </div>
                        <div id="edit_rule_num" class="pwd-rule-item">
                            <span class="rule-icon">○</span> 1 number (0-9)
                        </div>
                        <div id="edit_rule_sym" class="pwd-rule-item" style="grid-column: span 2;">
                            <span class="rule-icon">○</span> 1 special symbol (!@#$%^&*...)
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top: 14px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" style="width: auto;">
                    <span>Account is Active &amp; Allowed to Log In</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="margin-top: 0; padding: 8px 18px;">Cancel</button>
                <button type="submit" id="btn_submit_edit" class="btn" style="background: #7c3aed; margin-top: 0; padding: 8px 22px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function testComplexity(pwd) {
    return {
        len: pwd.length >= 8,
        upper: /[A-Z]/.test(pwd),
        lower: /[a-z]/.test(pwd),
        num: /[0-9]/.test(pwd),
        sym: /[!@#$%^&*(),.?":{}|<>_\-~+=]/.test(pwd)
    };
}

function updateChecklistUI(prefix, res) {
    let score = 0;
    const rules = ['len', 'upper', 'lower', 'num', 'sym'];
    rules.forEach(r => {
        const el = document.getElementById(`${prefix}_rule_${r}`);
        if (el) {
            if (res[r]) {
                el.classList.add('valid');
                el.querySelector('.rule-icon').textContent = '✓';
                score++;
            } else {
                el.classList.remove('valid');
                el.querySelector('.rule-icon').textContent = '○';
            }
        }
    });

    const fill = document.getElementById(`${prefix}_pwd_fill`);
    if (fill) {
        const pct = (score / 5) * 100;
        fill.style.width = pct + '%';
        if (score <= 2) {
            fill.style.background = '#ef4444'; // red
        } else if (score <= 4) {
            fill.style.background = '#f59e0b'; // amber
        } else {
            fill.style.background = '#10b981'; // green
        }
    }
    return score === 5;
}

function validateAddPassword() {
    const val = document.getElementById('add_password').value;
    const res = testComplexity(val);
    return updateChecklistUI('add', res);
}

function validateEditPassword() {
    const input = document.getElementById('edit_password');
    const container = document.getElementById('edit_pwd_container');
    const val = input.value;
    if (val.length > 0) {
        container.style.display = 'block';
        const res = testComplexity(val);
        return updateChecklistUI('edit', res);
    } else {
        container.style.display = 'none';
        return true;
    }
}

function validateEditForm() {
    const val = document.getElementById('edit_password').value;
    if (val.length > 0) {
        const res = testComplexity(val);
        const valid = updateChecklistUI('edit', res);
        if (!valid) {
            alert('Password does not meet the complexity requirements (must be 8+ chars, uppercase, lowercase, number, symbol).');
            return false;
        }
    }
    return true;
}

// Add user form validation
document.querySelector('#addModal form').addEventListener('submit', function(e) {
    const valid = validateAddPassword();
    if (!valid) {
        e.preventDefault();
        alert('Password does not meet the complexity requirements (must be 8+ chars, uppercase, lowercase, number, symbol).');
        document.getElementById('add_password').focus();
    }
});

function toggleModalPwd(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}

const ALL_NAV_KEYS = [
    'dashboard', 'journal', 'ej', 'receipt_reprint',
    'users', 'admin', 'store_master', 'register_master',
    'tender_master', 'cashier_master', 'layout', 'ej_layout', 'receipt_layout',
    'activity_log'
];
const DEFAULT_USER_NAV_KEYS = ['dashboard', 'journal', 'ej', 'receipt_reprint'];

function setNavPermsSelection(prefix, mode) {
    ALL_NAV_KEYS.forEach(k => {
        const el = document.getElementById(`${prefix}_perm_${k}`);
        if (!el || el.disabled) return;
        if (mode === 'all') {
            el.checked = true;
        } else if (mode === 'none') {
            el.checked = false;
        } else if (mode === 'default_user') {
            el.checked = DEFAULT_USER_NAV_KEYS.includes(k);
        }
    });
}

// Auto-switch permissions when role select changes
document.getElementById('add_role').addEventListener('change', function() {
    if (this.value === 'admin') {
        setNavPermsSelection('add', 'all');
    } else {
        setNavPermsSelection('add', 'default_user');
    }
});

document.getElementById('edit_role').addEventListener('change', function() {
    if (this.value === 'admin') {
        setNavPermsSelection('edit', 'all');
    } else {
        setNavPermsSelection('edit', 'default_user');
    }
});

function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
    document.getElementById('add_password').value = '';
    document.getElementById('add_role').value = 'user';
    setNavPermsSelection('add', 'default_user');
    validateAddPassword();
    document.getElementById('add_username').focus();
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_title_username').textContent = user.username;
    document.getElementById('edit_full_name').value = user.full_name || '';
    document.getElementById('edit_department').value = user.department || '';
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_is_active').checked = parseInt(user.is_active) === 1;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_pwd_container').style.display = 'none';

    const isPrimaryAdmin = (user.username === 'admin');
    const isSelf = (parseInt(user.id) === <?php echo (int)$currentUser['id']; ?>);
    const roleSelect = document.getElementById('edit_role');
    const adminNote = document.getElementById('edit_admin_note');
    const actionsWrap = document.getElementById('edit_perm_actions');
    const editIsActive = document.getElementById('edit_is_active');

    if (isPrimaryAdmin) {
        roleSelect.disabled = true;
        if (adminNote) adminNote.style.display = 'block';
        if (actionsWrap) actionsWrap.style.display = 'none';
    } else {
        roleSelect.disabled = false;
        if (adminNote) adminNote.style.display = 'none';
        if (actionsWrap) actionsWrap.style.display = 'flex';
    }

    if (isPrimaryAdmin || isSelf) {
        editIsActive.disabled = true;
        editIsActive.parentElement.title = isSelf ? 'You cannot deactivate your own logged-in account' : 'Primary admin cannot be deactivated';
    } else {
        editIsActive.disabled = false;
        editIsActive.parentElement.title = '';
    }

    // Parse user nav permissions
    let perms = [];
    if (isPrimaryAdmin) {
        perms = ALL_NAV_KEYS;
    } else if (!user.nav_permissions || user.nav_permissions === '*' || user.nav_permissions === 'all') {
        perms = (user.role === 'admin') ? ALL_NAV_KEYS : DEFAULT_USER_NAV_KEYS;
    } else {
        try {
            const parsed = JSON.parse(user.nav_permissions);
            if (Array.isArray(parsed)) {
                perms = parsed;
            } else {
                perms = String(user.nav_permissions).split(',').map(s => s.trim());
            }
        } catch(e) {
            perms = String(user.nav_permissions).split(',').map(s => s.trim());
        }
    }

    ALL_NAV_KEYS.forEach(k => {
        const el = document.getElementById(`edit_perm_${k}`);
        if (el) {
            el.checked = perms.includes(k);
            el.disabled = isPrimaryAdmin;
        }
    });

    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modals when clicking backdrop
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        closeAddModal();
        closeEditModal();
    }
});
</script>

</body>
</html>
