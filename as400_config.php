<?php
// as400_config.php - Manage IBM iSeries AS400 ODBC connection credentials
require_once __DIR__ . '/auth.php';
requireNavAccess('as400_config');
$currentPage = 'as400_config';

$configFile = __DIR__ . '/as400_config.json';
$as400Config = [
    'dsn_name' => 'mms_as400',
    'username' => '',
    'password' => '',
    'library'  => 'MMLTSLIB'
];

if (file_exists($configFile)) {
    $loaded = json_decode(file_get_contents($configFile), true);
    if (is_array($loaded)) {
        $as400Config = array_merge($as400Config, $loaded);
    }
}

$saved = false;
$testMsg = '';
$testSuccess = false;

// Handle direct form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_as400'])) {
    $dsnName  = trim($_POST['dsn_name'] ?? 'mms_as400');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $library  = trim($_POST['library'] ?? 'MMLTSLIB');

    $as400Config = [
        'dsn_name' => $dsnName !== '' ? $dsnName : 'mms_as400',
        'username' => $username,
        'password' => $password,
        'library'  => $library !== '' ? $library : 'MMLTSLIB'
    ];

    file_put_contents($configFile, json_encode($as400Config, JSON_PRETTY_PRINT));
    $saved = true;

    // Test connection if requested
    if (isset($_POST['test_connection']) && $username !== '') {
        $pythonExe = 'C:\\python32\\python.exe';
        if (!file_exists($pythonExe)) $pythonExe = 'python';

        $scriptPath = __DIR__ . '/scripts/lookup_sku.py';
        $cmd = escapeshellarg($pythonExe) . ' ' .
               escapeshellarg($scriptPath) . ' ' .
               escapeshellarg('TEST_CONNECTION_CHECK') . ' ' .
               escapeshellarg($as400Config['dsn_name']) . ' ' .
               escapeshellarg($as400Config['username']) . ' ' .
               escapeshellarg($as400Config['password']) . ' ' .
               escapeshellarg($as400Config['library']);

        $output = [];
        $returnVar = 0;
        exec($cmd . ' 2>&1', $output, $returnVar);
        $outputStr = implode("\n", $output);
        $json = json_decode($outputStr, true);

        if (is_array($json)) {
            if (($json['status'] ?? '') === 'success') {
                $testSuccess = true;
                $testMsg = '✓ Connected to AS400 successfully!';
            } else {
                $testMsg = $json['message'] ?? 'Connection error.';
            }
        } else {
            $testMsg = $outputStr ?: 'No response from Python AS400 connector.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AS400 Settings - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
    .config-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 24px 28px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        margin-bottom: 24px;
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 6px;
    }
    .form-group input {
        width: 100%;
        padding: 10px 12px;
        font-size: 14px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-sizing: border-box;
        transition: border-color 0.15s ease;
    }
    .form-group input:focus {
        border-color: #0284c7;
        outline: none;
    }
    .form-help {
        font-size: 12px;
        color: #64748b;
        margin-top: 4px;
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }
    .status-badge.success {
        background: #dcfce7;
        color: #166534;
    }
    .status-badge.error {
        background: #ffe4e6;
        color: #9f1239;
    }
    .status-badge.pending {
        background: #e0f2fe;
        color: #0369a1;
    }
    .btn-action-group {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-top: 10px;
    }
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>⚙️ AS400 Connection Settings</h1>
            <p>Configure IBM iSeries AS400 ODBC connection credentials for automatic SKU lookups and Electronic Journal batch imports.</p>
        </div>

        <?php if ($saved && !$testMsg): ?>
            <div class="success">✓ AS400 configuration saved successfully!</div>
        <?php endif; ?>

        <?php if ($testMsg): ?>
            <?php if ($testSuccess): ?>
                <div class="success"><?php echo htmlspecialchars($testMsg); ?></div>
            <?php else: ?>
                <div class="error">⚠️ <?php echo htmlspecialchars($testMsg); ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="config-card">
            <h2 style="font-size: 16px; margin-top: 0; margin-bottom: 8px; color: #0f172a;">ODBC Connection Credentials</h2>
            <p style="font-size: 13px; color: #64748b; margin-top: 0; margin-bottom: 20px;">
                Credentials configured here are stored securely in <code>as400_config.json</code> and used by Python 32-bit ODBC background scripts to connect to <code><?php echo htmlspecialchars($as400Config['dsn_name'] ?: 'mms_as400'); ?></code>.
            </p>

            <form method="POST" action="as400_config.php" id="as400ConfigForm">
                <input type="hidden" name="save_as400" value="1">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="dsn_name">ODBC DSN Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="dsn_name" name="dsn_name" value="<?php echo htmlspecialchars($as400Config['dsn_name']); ?>" required placeholder="e.g. mms_as400">
                        <div class="form-help">System DSN configured in Windows 32-bit ODBC Data Source Administrator (<code>odbcad32.exe</code>).</div>
                    </div>

                    <div class="form-group">
                        <label for="library">Default Library <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="library" name="library" value="<?php echo htmlspecialchars($as400Config['library']); ?>" required placeholder="e.g. MMLTSLIB">
                        <div class="form-help">AS400 database library containing <code>INVMST</code> (Item Master) and transaction tables.</div>
                    </div>

                    <div class="form-group">
                        <label for="username">AS400 User ID <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($as400Config['username']); ?>" required placeholder="e.g. maindss / QSECOFR">
                        <div class="form-help">User account with read permission to AS400 library.</div>
                    </div>

                    <div class="form-group">
                        <label for="password">AS400 Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($as400Config['password']); ?>" required placeholder="Enter password">
                        <div class="form-help">Password for the AS400 user account.</div>
                    </div>
                </div>

                <div class="btn-action-group">
                    <button type="submit" name="test_connection" value="1" class="btn" style="background: #0284c7; margin: 0; padding: 10px 22px; font-weight: 700;">
                        💾 Save & Test Connection
                    </button>
                    <button type="button" id="btn_ajax_test" class="btn btn-secondary" style="margin: 0; padding: 10px 18px;">
                        ⚡ Quick Test Connection
                    </button>
                    <span id="ajax_status" style="font-size: 13px; font-weight: 600;"></span>
                </div>
            </form>
        </div>

        <div class="config-card">
            <h2 style="font-size: 16px; margin-top: 0; margin-bottom: 12px; color: #0f172a;">ℹ️ About AS400 Integration</h2>
            <div style="font-size: 13.5px; color: #334155; line-height: 1.6;">
                <p style="margin-top: 0;">
                    The Electronic Journal application integrates with IBM iSeries / AS400 in two main workflows:
                </p>
                <ul style="margin: 0 0 14px 20px; padding: 0;">
                    <li style="margin-bottom: 6px;">
                        <strong>Automatic SKU Lookup:</strong> When an Electronic Journal entry or receipt lacks SKU item descriptions, the app queries <code><?php echo htmlspecialchars($as400Config['library']); ?>.INVMST</code> (Item Master) to resolve the product description automatically.
                    </li>
                    <li style="margin-bottom: 6px;">
                        <strong>Direct AS400 Import:</strong> In <strong>EJ Printing</strong>, administrators can import transactions directly from AS400 daily transaction tables (<code>CSHTRN</code> / <code>CSHDET</code>) by store, register, and date.
                    </li>
                    <li>
                        <strong>Requirements:</strong> Ensure the 32-bit IBM i Access ODBC driver is installed on the server and Python 32-bit (<code>C:\python32\python.exe</code>) has <code>pyodbc</code> installed.
                    </li>
                </ul>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnAjaxTest = document.getElementById('btn_ajax_test');
    const ajaxStatus = document.getElementById('ajax_status');
    const form = document.getElementById('as400ConfigForm');

    if (btnAjaxTest && form) {
        btnAjaxTest.addEventListener('click', async () => {
            btnAjaxTest.disabled = true;
            ajaxStatus.textContent = '⏳ Testing connection to AS400...';
            ajaxStatus.style.color = '#0284c7';

            const formData = new FormData(form);
            try {
                const resp = await fetch('save_as400_config_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                if (data.status === 'success') {
                    if (data.test_success) {
                        ajaxStatus.textContent = '✓ Saved & Connected successfully to AS400!';
                        ajaxStatus.style.color = '#16a34a';
                    } else {
                        ajaxStatus.textContent = '⚠️ Saved, but connection test failed: ' + (data.test_message || 'Check credentials');
                        ajaxStatus.style.color = '#e11d48';
                    }
                } else {
                    ajaxStatus.textContent = '❌ Error: ' + (data.message || 'Could not save configuration');
                    ajaxStatus.style.color = '#e11d48';
                }
            } catch (err) {
                ajaxStatus.textContent = '❌ Network error: ' + err.message;
                ajaxStatus.style.color = '#e11d48';
            } finally {
                btnAjaxTest.disabled = false;
            }
        });
    }
});
</script>
</body>
</html>
