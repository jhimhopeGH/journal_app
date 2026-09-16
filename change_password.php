<?php
// change_password.php - Standalone Self-Service Password Change Page for all users
require_once __DIR__ . '/auth.php';
requireLogin();
$currentPage = 'change_password';

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_change_pwd'])) {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $result = changeUserPassword((int)$user['id'], $currentPassword, $newPassword, $confirmPassword);
    if ($result['success']) {
        $success = $result['message'] ?? 'Your password has been changed successfully!';
    } else {
        $error = $result['error'] ?? 'Could not change password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
    .change-pwd-container {
        max-width: 540px;
        margin: 20px auto 40px;
    }
    .pwd-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 28px 32px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
    }
    .pwd-field {
        margin-bottom: 18px;
        position: relative;
    }
    .pwd-field label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 6px;
    }
    .pwd-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }
    .pwd-input-wrap input {
        width: 100%;
        padding: 10px 42px 10px 12px;
        font-size: 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        box-sizing: border-box;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .pwd-input-wrap input:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
        outline: none;
    }
    .pwd-toggle-btn {
        position: absolute;
        right: 10px;
        background: none;
        border: none;
        cursor: pointer;
        color: #94a3b8;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 !important;
        box-shadow: none !important;
        transform: none !important;
    }
    .pwd-toggle-btn:hover {
        color: #334155;
        background: none !important;
        box-shadow: none !important;
    }
    .req-list {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 12px;
        color: #64748b;
        margin-bottom: 22px;
        line-height: 1.6;
    }
    .req-list strong {
        color: #334155;
    }
    .req-list ul {
        margin: 6px 0 0 16px;
        padding: 0;
    }
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>🔑 Change Password</h1>
            <p>Update your personal account login credentials.</p>
        </div>

        <div class="change-pwd-container">
            <?php if ($success): ?>
                <div class="success" style="margin-bottom: 18px; padding: 14px 18px; font-weight: 600;">
                    ✓ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error" style="margin-bottom: 18px; padding: 14px 18px; font-weight: 600;">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="pwd-card">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid #f1f5f9;">
                    <div style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px;">
                        <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-size: 15px; font-weight: 800; color: #0f172a;">
                            <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                        </div>
                        <div style="font-size: 12px; color: #64748b;">
                            Username: <strong><?php echo htmlspecialchars($user['username']); ?></strong> &bull; Role: <span style="text-transform: uppercase; font-weight: 600; color: #0284c7;"><?php echo htmlspecialchars($user['role'] ?? 'user'); ?></span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="change_password.php">
                    <input type="hidden" name="submit_change_pwd" value="1">

                    <div class="pwd-field">
                        <label for="current_password">Current Password <span style="color: #ef4444;">*</span></label>
                        <div class="pwd-input-wrap">
                            <input type="password" id="current_password" name="current_password" required placeholder="Enter current password" autocomplete="current-password">
                            <button type="button" class="pwd-toggle-btn" onclick="togglePwdVisibility('current_password', this)" title="Show/Hide Password">👁️</button>
                        </div>
                    </div>

                    <div class="pwd-field">
                        <label for="new_password">New Password <span style="color: #ef4444;">*</span></label>
                        <div class="pwd-input-wrap">
                            <input type="password" id="new_password" name="new_password" required placeholder="Enter new password" autocomplete="new-password">
                            <button type="button" class="pwd-toggle-btn" onclick="togglePwdVisibility('new_password', this)" title="Show/Hide Password">👁️</button>
                        </div>
                    </div>

                    <div class="pwd-field">
                        <label for="confirm_password">Confirm New Password <span style="color: #ef4444;">*</span></label>
                        <div class="pwd-input-wrap">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-type new password" autocomplete="new-password">
                            <button type="button" class="pwd-toggle-btn" onclick="togglePwdVisibility('confirm_password', this)" title="Show/Hide Password">👁️</button>
                        </div>
                    </div>

                    <div class="req-list">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li>Minimum 8 characters in length</li>
                            <li>At least one uppercase letter (A-Z) & one lowercase letter (a-z)</li>
                            <li>At least one numeric digit (0-9)</li>
                            <li>At least one special symbol (e.g. <code>!@#$%^&*()_+-=</code>)</li>
                        </ul>
                    </div>

                    <div style="display: flex; gap: 12px; align-items: center;">
                        <button type="submit" class="btn" style="background: #0284c7; padding: 10px 24px; font-weight: 700; margin: 0;">
                            🔒 Update Password
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary" style="margin: 0; padding: 10px 18px;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function togglePwdVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.style.opacity = '1';
    } else {
        input.type = 'password';
        btn.style.opacity = '0.6';
    }
}
</script>
</body>
</html>
