<?php
// auth.php - Central Authentication & Role-Based Access Control Module
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Check if a user is currently logged in.
 */
function isLoggedIn() {
    return !empty($_SESSION['user']['id']);
}

/**
 * Get the currently logged-in user data.
 */
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Check if the currently logged-in user has the admin role.
 */
function isAdmin() {
    return isLoggedIn() && (($_SESSION['user']['role'] ?? '') === 'admin');
}

/**
 * Enforce authentication on a page. Redirects unauthenticated visitors to login.php.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $redirectUrl = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        header('Location: login.php?redirect=' . urlencode($redirectUrl));
        exit;
    }
}

/**
 * Enforce admin privileges on a page. Redirects unauthorized users.
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Enforce authentication on JSON AJAX endpoints.
 */
function requireAjaxAuth() {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Please log in.']);
        exit;
    }
}

/**
 * Enforce admin role on JSON AJAX endpoints.
 */
function requireAjaxAdmin() {
    requireAjaxAuth();
    if (!isAdmin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden: Administrator privileges required.']);
        exit;
    }
}

/**
 * Attempt to authenticate a user with username and password.
 */
function loginUser($username, $password) {
    $user = getUserByUsername($username);
    if (!$user) {
        logActivity('login_failed', 'auth', "Failed login attempt for username: $username");
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    if (empty($user['is_active'])) {
        logActivity('login_failed', 'auth', "Login blocked — account inactive: $username");
        return ['success' => false, 'error' => 'This account has been deactivated. Please contact an administrator.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        logActivity('login_failed', 'auth', "Wrong password for username: $username");
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    // Authentication succeeded - Store sanitized user session
    $_SESSION['user'] = [
        'id'              => (int)$user['id'],
        'username'        => $user['username'],
        'full_name'       => $user['full_name'] ?: $user['username'],
        'role'            => $user['role'] ?: 'user',
        'nav_permissions' => $user['nav_permissions'] ?? '*'
    ];

    logActivity('login', 'auth', 'Logged in successfully');
    return ['success' => true, 'user' => $_SESSION['user']];
}

/**
 * Log out the current user and destroy the session.
 */
function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Validate password complexity:
 * - Minimum 8 characters
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

/**
 * Change the password for a logged-in user.
 */
function changeUserPassword($userId, $currentPassword, $newPassword, $confirmPassword) {
    global $pdo;

    if (empty($userId)) {
        return ['success' => false, 'error' => 'User not identified. Please log in again.'];
    }

    $user = getUserById($userId);
    if (!$user) {
        return ['success' => false, 'error' => 'User account not found.'];
    }

    if (empty($currentPassword)) {
        return ['success' => false, 'error' => 'Please enter your current password.'];
    }

    if (!password_verify($currentPassword, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    if (empty($newPassword)) {
        return ['success' => false, 'error' => 'Please enter a new password.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'error' => 'New password and confirmation do not match.'];
    }

    if ($currentPassword === $newPassword) {
        return ['success' => false, 'error' => 'New password cannot be the same as your current password.'];
    }

    $complexityErr = checkPasswordComplexity($newPassword);
    if ($complexityErr) {
        return ['success' => false, 'error' => $complexityErr];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
    $ok = $stmt->execute([':hash' => $newHash, ':id' => (int)$userId]);

    if (!$ok) {
        return ['success' => false, 'error' => 'Database error: Could not update password.'];
    }

    return ['success' => true, 'message' => 'Your password has been changed successfully!'];
}

/**
 * Returns the catalog of manageable navigation links/features.
 */
function getNavItemsCatalog() {
    return [
        'dashboard' => [
            'label'       => 'Dashboard',
            'group'       => 'Main',
            'description' => 'View journal statistics, activity overview, and system summary'
        ],
        'journal' => [
            'label'       => 'Zread Reprint',
            'group'       => 'Reprint',
            'description' => 'View, filter, and reprint Zread journal entries'
        ],
        'ej' => [
            'label'       => 'EJ Printing',
            'group'       => 'Reprint',
            'description' => 'Electronic Journal batch and continuous thermal printing'
        ],
        'receipt_reprint' => [
            'label'       => 'Receipt Reprint',
            'group'       => 'Reprint',
            'description' => 'Search, preview, and reprint individual POS receipts'
        ],
        'users' => [
            'label'       => 'User Management',
            'group'       => 'Admin',
            'description' => 'Create, edit, reset passwords, and manage navigation access'
        ],
        'admin' => [
            'label'       => 'Database & Maintenance',
            'group'       => 'Admin',
            'description' => 'Database backups, restore, and AS400 integration setup'
        ],
        'store_master' => [
            'label'       => 'Store Master',
            'group'       => 'Admin',
            'description' => 'Manage store branches, BIR permit info, headers, and footers'
        ],
        'register_master' => [
            'label'       => 'Register Master',
            'group'       => 'Admin',
            'description' => 'Manage POS registers, machine identification numbers (MIN), and serials'
        ],
        'tender_master' => [
            'label'       => 'Tender Master',
            'group'       => 'Admin',
            'description' => 'Manage payment methods, tender codes, and descriptions'
        ],
        'layout' => [
            'label'       => 'Zread Print Layout',
            'group'       => 'Admin',
            'description' => 'Customize print layout settings for Zread printouts'
        ],
        'ej_layout' => [
            'label'       => 'EJ Print Layout',
            'group'       => 'Admin',
            'description' => 'Visual slider layout designer for Electronic Journal prints'
        ],
        'receipt_layout' => [
            'label'       => 'Receipt Print Layout',
            'group'       => 'Admin',
            'description' => 'Customize visual receipt layout and printable sections'
        ],
        'as400_config' => [
            'label'       => 'AS400 Settings',
            'group'       => 'Admin',
            'description' => 'IBM iSeries AS400 ODBC connection credentials and library settings'
        ],
        'activity_log' => [
            'label'       => 'Activity Log',
            'group'       => 'Admin',
            'description' => 'Audit trail of user actions, logins, prints, imports, and system changes'
        ],
        'cashier_master' => [
            'label'       => 'Cashier Master',
            'group'       => 'Admin',
            'description' => 'Manage POS cashier employees — import from EMPMST.TPS'
        ]
    ];
}

/**
 * Get active navigation permissions for a user.
 */
function getUserNavPermissions($user = null) {
    if ($user === null) {
        $user = getCurrentUser();
    }
    if (!$user) {
        return [];
    }

    $catalog = getNavItemsCatalog();
    $allKeys = array_keys($catalog);

    // Primary admin user always has access to all items
    if (($user['username'] ?? '') === 'admin') {
        return $allKeys;
    }

    $raw = null;
    if (!empty($user['id'])) {
        // Query fresh from DB so edits take effect immediately
        try {
            $fresh = getUserById($user['id']);
            if ($fresh && isset($fresh['nav_permissions'])) {
                $raw = $fresh['nav_permissions'];
            }
        } catch (Exception $e) {
            // Fallback to session
        }
    }

    if ($raw === null) {
        $raw = $user['nav_permissions'] ?? '*';
    }

    // Default wildcard or empty
    if ($raw === '*' || $raw === 'all' || trim($raw) === '') {
        if (($user['role'] ?? '') === 'admin') {
            return $allKeys;
        } else {
            // Default user access: Dashboard + Reprint group
            return ['dashboard', 'journal', 'ej', 'receipt_reprint'];
        }
    }

    // Attempt JSON decoding
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return array_values($decoded);
    }

    // Fallback: comma-separated list
    $exploded = array_map('trim', explode(',', $raw));
    return array_values(array_filter($exploded));
}

/**
 * Check if the user is authorized to access a given navigation item.
 */
function canAccessNav($navKey, $user = null) {
    if ($user === null) {
        $user = getCurrentUser();
    }
    if (!$user) {
        return false;
    }
    if (($user['username'] ?? '') === 'admin') {
        return true;
    }
    $allowed = getUserNavPermissions($user);
    return in_array($navKey, $allowed);
}

/**
 * Determine the user's primary default landing page based on their permitted navigation tabs.
 */
function getDefaultUserLandingPage($user = null) {
    $perms = getUserNavPermissions($user);
    $map = [
        'dashboard'       => 'dashboard.php',
        'journal'         => 'records.php',
        'ej'              => 'ej_printing.php',
        'receipt_reprint' => 'receipt_reprint.php',
        'users'           => 'users.php',
        'admin'           => 'admin.php',
        'store_master'    => 'store_master.php',
        'register_master' => 'register_master.php',
        'tender_master'   => 'tender_master.php',
        'layout'          => 'print_layout.php',
        'ej_layout'       => 'ej_print_layout.php',
        'receipt_layout'  => 'receipt_print_layout.php',
        'activity_log'    => 'activity_log.php',
        'cashier_master'  => 'cashier_master.php'
    ];
    foreach ($map as $k => $page) {
        if (in_array($k, $perms)) {
            return $page;
        }
    }
    return 'dashboard.php';
}

/**
 * Enforce navigation access on a page.
 */
function requireNavAccess($navKey) {
    requireLogin();
    if (!canAccessNav($navKey)) {
        $landing = getDefaultUserLandingPage();
        $currentScript = basename($_SERVER['PHP_SELF'] ?? '');
        if ($landing !== $currentScript) {
            header('Location: ' . $landing . '?error=access_denied');
        } else {
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title><link rel="stylesheet" href="style.css"></head><body style="display:flex;align-items:center;justify-content:center;height:100vh;background:#f8fafc;font-family:sans-serif;"><div style="background:#fff;padding:32px;border-radius:16px;box-shadow:0 10px 25px rgba(0,0,0,0.1);text-align:center;max-width:420px;"><h2 style="color:#ef4444;margin-top:0;">Access Restricted</h2><p style="color:#64748b;font-size:14px;">Your user account does not have permission to access this module. Please contact an administrator.</p><a href="logout.php" class="btn btn-secondary" style="margin-top:16px;display:inline-block;">Log Out</a></div></body></html>';
        }
        exit;
    }
}
