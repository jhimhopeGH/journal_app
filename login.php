<?php
// login.php - Secure User Login Page
require_once __DIR__ . '/auth.php';

// If already logged in, go straight to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$message = '';
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : (isset($_POST['redirect']) ? trim($_POST['redirect']) : 'dashboard.php');

// Prevent open redirect vulnerabilities
if ($redirect === '' || strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
    $redirect = 'dashboard.php';
}

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $message = 'You have been safely logged out.';
}

// Check for company logo file
$logoCandidates = [
    'logo.png', 'logo.svg', 'logo.jpg', 'logo.jpeg', 'logo.webp',
    'assets/logo.png', 'assets/logo.svg', 'assets/logo.jpg', 'assets/logo.webp'
];
$companyLogo = 'logo.png';
foreach ($logoCandidates as $cand) {
    if (file_exists(__DIR__ . '/' . $cand)) {
        $companyLogo = $cand;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $loginResult = loginUser($username, $password);
        if ($loginResult['success']) {
            if ($redirect === 'dashboard.php') {
                $redirect = getDefaultUserLandingPage($loginResult['user']);
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $loginResult['error'] ?? 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
    body {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    }
    .login-wrapper {
        width: 100%;
        max-width: 420px;
        padding: 20px;
        box-sizing: border-box;
    }
    .login-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 36px 32px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25), 0 1px 3px rgba(0, 0, 0, 0.1);
        box-sizing: border-box;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .login-brand {
        text-align: center;
        margin-bottom: 24px;
    }
    .brand-company-logo {
        max-width: 170px;
        max-height: 85px;
        width: auto;
        height: auto;
        object-fit: contain;
        margin: 0 auto 14px auto;
        display: block;
        filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.08));
    }
    .brand-logo-badge {
        width: 54px;
        height: 54px;
        background: linear-gradient(135deg, #7c3aed, #4f46e5);
        color: #fff;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 20px;
        letter-spacing: -0.5px;
        box-shadow: 0 8px 16px rgba(124, 58, 237, 0.35);
        margin-bottom: 12px;
    }
    .login-title {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 6px 0;
    }
    .login-subtitle {
        font-size: 13px;
        color: #64748b;
        margin: 0;
    }
    .form-group {
        margin-bottom: 18px;
    }
    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-wrapper input {
        width: 100%;
        padding: 12px 18px;
        border: 1.5px solid #cbd5e1;
        border-radius: 20px;
        font-size: 14px;
        background: #f8fafc;
        color: #0f172a;
        transition: all 0.2s ease;
        outline: none;
        box-sizing: border-box;
    }
    .input-wrapper input:focus {
        border-color: #7c3aed;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15);
    }
    .pwd-toggle-btn {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px;
        font-size: 16px;
        color: #64748b;
        margin-top: 0 !important;
        box-shadow: none !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pwd-toggle-btn:hover {
        color: #1e293b;
        background: none;
        transform: none;
    }
    .btn-login {
        width: 100%;
        padding: 12px 20px;
        background: linear-gradient(135deg, #7c3aed, #4f46e5);
        color: #ffffff;
        border: none;
        border-radius: 20px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-top: 10px;
        box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-login:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(124, 58, 237, 0.45);
        background: linear-gradient(135deg, #6d28d9, #4338ca);
    }
    .btn-login:active {
        transform: translateY(0);
    }
    .alert-box {
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 13px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 8px;
        line-height: 1.4;
    }
    .alert-error {
        background: #fee2e2;
        border: 1px solid #fca5a5;
        color: #991b1b;
    }
    .alert-success {
        background: #dcfce7;
        border: 1px solid #86efac;
        color: #166534;
    }
    .login-footer {
        text-align: center;
        margin-top: 20px;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
        letter-spacing: 0.03em;
        font-weight: 500;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
    }
</style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-brand">
            <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Company Logo" class="brand-company-logo" onerror="this.style.display='none'; var f=document.getElementById('fallback-logo-badge'); if(f) f.style.display='inline-flex';">
            <div id="fallback-logo-badge" class="brand-logo-badge" style="<?php echo file_exists(__DIR__ . '/' . $companyLogo) ? 'display:none;' : ''; ?>">EJ</div>
            <h1 class="login-title">Electronic Journal</h1>
            <p class="login-subtitle">Sign in to access your journal &amp; receipt records</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert-box alert-error">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="alert-box alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

            <!-- Prevent aggressive browser password autofill (e.g. saved AS400 maindss / windss credentials) -->
            <input type="text" name="prevent_autofill_user" style="display:none;" tabindex="-1" autocomplete="off">
            <input type="password" name="prevent_autofill_pwd" style="display:none;" tabindex="-1" autocomplete="new-password">

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" placeholder="Enter username..." required autofocus autocomplete="off" readonly onfocus="this.removeAttribute('readonly');" value="">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter password..." required autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" value="">
                    <button type="button" class="pwd-toggle-btn" onclick="togglePasswordVisibility()" title="Toggle visibility">
                        <span id="pwd-icon">👁️</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <span>Sign In</span>
                <span>→</span>
            </button>
        </form>
    </div>
    <div class="login-footer">
        Copyright 2026 | NCCC | All Rights Reserved
    </div>
</div>

<script>
// Clear any aggressive browser-cached or autofilled credentials on page load
function clearAutofill() {
    const u = document.getElementById('username');
    const p = document.getElementById('password');
    if (u) {
        u.value = '';
        u.removeAttribute('readonly');
    }
    if (p) {
        p.value = '';
        p.removeAttribute('readonly');
    }
}

window.addEventListener('DOMContentLoaded', clearAutofill);
window.addEventListener('load', function() {
    clearAutofill();
    setTimeout(clearAutofill, 50);
    setTimeout(clearAutofill, 200);
});

function togglePasswordVisibility() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('pwd-icon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.textContent = '🙈';
    } else {
        pwd.type = 'password';
        icon.textContent = '👁️';
    }
}
</script>

</body>
</html>
