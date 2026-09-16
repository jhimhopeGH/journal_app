<?php
// printer_setup.php - Printer Network Sharing Setup for Generic/Text Only Font
require_once __DIR__ . '/auth.php';
requireLogin();
if (!canAccessNav('printer_setup') && !canAccessNav('receipt_reprint')) {
    requireNavAccess('printer_setup');
}
require_once __DIR__ . '/raw_print_service.php';
$currentPage = 'printer_setup';

// Server info
$serverHost = php_uname('n');  // hostname
$serverIPs  = [];
// Try getting IPs from server network interfaces
if (function_exists('net_get_interfaces')) {
    foreach (@net_get_interfaces() as $iface => $info) {
        if (!empty($info['unicast'])) {
            foreach ($info['unicast'] as $addr) {
                $ip = $addr['address'] ?? '';
                if ($ip && !str_starts_with($ip, '127.') && !str_starts_with($ip, '::')) {
                    $serverIPs[] = $ip;
                }
            }
        }
    }
}
if (empty($serverIPs)) {
    $serverIPs = ['10.33.55.52']; // fallback from known config
}
$primaryIP = $serverIPs[0] ?? '10.33.55.52';

$shareName   = 'GnrcText';
$printerName = 'Generic / Text Only';
$uncPath     = "\\\\{$serverHost}\\{$shareName}";
$uncPathIP   = "\\\\{$primaryIP}\\{$shareName}";

// Check if printer is already shared
function isPrinterShared($printerName) {
    $regPath = "HKLM:\\SYSTEM\\CurrentControlSet\\Control\\Print\\Printers\\" . $printerName;
    $cmd = 'powershell -Command "(Get-ItemProperty \'' . str_replace("'", '', $regPath) . '\' -ErrorAction SilentlyContinue).\'Share Name\'"';
    $output = trim(shell_exec($cmd) ?? '');
    return !empty($output) && strlen($output) > 1;
}

$isShared = isPrinterShared($printerName);

// Handle Share Action (server-side)
$shareResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'share_printer') {
    // Use valid printui command
    $cmd1 = 'rundll32 printui.dll,PrintUIEntry /Xs /n "Generic / Text Only" sharename "GnrcText" attributes +shared 2>&1';
    $out1 = shell_exec($cmd1);
    
    // Also enable firewall
    $cmd2 = 'netsh advfirewall firewall set rule group="File and Printer Sharing" new enable=Yes 2>&1';
    $out2 = shell_exec($cmd2);
    
    $isShared = isPrinterShared($printerName);
    $shareResult = [
        'success' => $isShared,
        'out1' => $out1,
        'out2' => $out2
    ];
}

// Generate client install script on the fly
if (isset($_GET['download']) && $_GET['download'] === 'client_bat') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="install_receipt_printer.bat"');
    echo "@echo off\r\n";
    echo "setlocal enabledelayedexpansion\r\n";
    echo "set PRINTER_PATH=\\\\{$serverHost}\\{$shareName}\r\n";
    echo "set PRINTER_IP=\\\\{$primaryIP}\\{$shareName}\r\n";
    echo "echo Installing Generic/Text receipt printer from server...\r\n";
    echo "ping -n 1 -w 1000 {$serverHost} >nul 2>&1\r\n";
    echo "if %errorlevel% == 0 ( set UNC=!PRINTER_PATH! ) else ( set UNC=!PRINTER_IP! )\r\n";
    echo "rundll32 printui.dll,PrintUIEntry /in /n \"!UNC!\"\r\n";
    echo "if %errorlevel% == 0 (\r\n";
    echo "    echo [OK] Printer installed: !UNC!\r\n";
    echo ") else (\r\n";
    echo "    echo [FAIL] Could not auto-install. Manually add: !PRINTER_PATH!\r\n";
    echo ")\r\n";
    echo "echo.\r\n";
    echo "echo HOW TO USE:\r\n";
    echo "echo   1. In Chrome or Edge, open Receipt Reprint\r\n";
    echo "echo   2. Press Ctrl+P (or click Browser Print)\r\n";
    echo "echo   3. Select printer: !PRINTER_PATH!\r\n";
    echo "echo   4. Paper size: Reciept Paper (or Custom 80mm)\r\n";
    echo "echo   5. Margins: None - then click Print\r\n";
    echo "pause\r\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Printer Network Setup - Generic/Text Only Font</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<script src="js/web_hardware_print.js"></script>
<style>
.setup-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 28px 32px;
    margin-bottom: 24px;
    border: 1px solid #e2e8f0;
}
.step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px; height: 32px;
    background: #7c3aed;
    color: #fff;
    border-radius: 50%;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
    margin-right: 10px;
}
.step-row { display: flex; align-items: flex-start; gap: 0; margin-bottom: 20px; }
.step-body { flex: 1; }
.step-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.step-desc { font-size: 13px; color: #475569; line-height: 1.6; }
.unc-box {
    background: #0f172a;
    color: #38bdf8;
    font-family: monospace;
    font-size: 14px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 6px;
    display: inline-block;
    margin: 4px 0;
    user-select: all;
    cursor: text;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.status-badge.shared { background: #dcfce7; color: #15803d; }
.status-badge.not-shared { background: #fef2f2; color: #dc2626; }
.alert-info {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 12px 16px;
    border-radius: 4px;
    font-size: 13px;
    color: #1e40af;
    line-height: 1.6;
    margin-bottom: 12px;
}
.alert-warn {
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
    padding: 12px 16px;
    border-radius: 4px;
    font-size: 13px;
    color: #92400e;
    line-height: 1.6;
    margin-bottom: 12px;
}
.alert-success {
    background: #f0fdf4;
    border-left: 4px solid #22c55e;
    padding: 12px 16px;
    border-radius: 4px;
    font-size: 13px;
    color: #15803d;
    line-height: 1.6;
    margin-bottom: 12px;
}
code { background: #f1f5f9; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
kbd { background: #334155; color: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-family: monospace; }
.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.dl-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(124,58,237,0.3);
    transition: all 0.2s;
}
.dl-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(124,58,237,0.4); }
.share-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(5,150,105,0.3);
    transition: all 0.2s;
}
.share-btn:hover { transform: translateY(-1px); }
.copy-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #334155;
    color: #fff;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    vertical-align: middle;
    transition: all 0.15s;
}
.copy-btn:hover { background: #0f172a; }
.img-preview {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    max-width: 400px;
    background: #f8fafc;
}
</style>
</head>
<body>
<?php require __DIR__ . '/partials/nav.php'; ?>
<main class="main-content">
    <div class="top-nav">
        <a href="receipt_reprint.php" class="btn btn-secondary">← Back to Receipt Reprint</a>
        <a href="receipt_print_layout.php" class="btn btn-secondary" style="background:#6d28d9 !important;">⚙️ Layout Settings</a>
    </div>

    <div class="page-header">
        <h1>🖨️ Generic/Text Font Printer Network Setup</h1>
        <p>Share the server's <strong>Generic/Text Only</strong> printer so every cashier workstation can print with the exact same hardware ROM font character set.</p>
    </div>

    <?php if ($shareResult !== null): ?>
        <?php if ($shareResult['success']): ?>
            <div class="alert-success" style="margin-bottom:20px; border-radius:8px; padding:14px 18px;">
                ✅ <strong>Printer shared successfully!</strong> Clients can now connect to <code><?php echo $uncPath; ?></code>.
            </div>
        <?php else: ?>
            <div class="alert-warn" style="margin-bottom:20px; border-radius:8px; padding:14px 18px;">
                ⚠️ <strong>Automatic sharing may not have applied.</strong> Please use the manual method (Step 1B) below.
                <details style="margin-top:8px;"><summary style="cursor:pointer; font-size:12px; color:#78716c;">Show debug output</summary>
                <pre style="font-size:11px; margin-top:6px; background:#fef9c3; padding:8px; border-radius:4px; white-space:pre-wrap;"><?php echo htmlspecialchars($shareResult['out1'] . "\n" . $shareResult['out2']); ?></pre>
                </details>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="panel" style="max-width:900px;">

        <!-- === CASHIER DIRECT WEBSERIAL / WEBUSB (RECOMMENDED METHOD) === -->
        <div class="setup-card" style="border: 2px solid #7c3aed; background: #faf5ff;">
            <div class="section-title" style="color: #5b21b6; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span>⚡ Method 1: Cashier USB/Serial Direct Print (Recommended)</span>
                </div>
                <span id="webserial-badge" class="status-badge" style="background:#e0e7ff; color:#3730a3;">Detecting browser...</span>
            </div>
            <p style="font-size:13px; color:#475569; margin-top:6px; line-height:1.6;">
                Prints raw ESC/POS bytes straight down the cashier's USB or Virtual COM cable. 
                <strong>100% authentic Generic/Text ROM font, fastest speed, instant auto-cut, zero network sharing required.</strong>
            </p>

            <div style="background:#fff; border:1px solid #ddd6fe; border-radius:8px; padding:16px; margin:14px 0;">
                <div style="font-weight:700; font-size:14px; color:#1e1b4b; margin-bottom:10px;">🔌 1-Click Printer Connection &amp; Test</div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="btn" onclick="window.WebHardwarePrint.openModal()" style="background:#7c3aed; color:#fff; font-weight:700; padding:9px 18px; border-radius:6px; border:none; cursor:pointer;">
                        ⚙️ Configure &amp; Pair USB/COM Port
                    </button>
                    <button type="button" class="btn" id="btnHwTestPrint" onclick="runHardwareTestPrint()" style="background:#059669; color:#fff; font-weight:700; padding:9px 18px; border-radius:6px; border:none; cursor:pointer;">
                        🖨️ Print Hardware Test Receipt
                    </button>
                </div>
                <div id="hwTestStatus" style="display:none; margin-top:10px; padding:10px 14px; border-radius:6px; font-size:13px; font-weight:600;"></div>
            </div>

            <div style="font-size:12px; color:#6b7280; line-height:1.5;">
                💡 <em>Supported in Google Chrome, Microsoft Edge, and Chromium-based browsers. Once paired, the browser remembers the USB/Serial printer for 1-click receipt printing from Receipt Reprint.</em>
            </div>
        </div>

        <!-- === SERVER NETWORK SHARING SECTION === -->
        <div class="setup-card">
            <div class="section-title">
                🖥️ Method 2 — Share Printer across Windows Network (Optional)
                <span class="status-badge <?php echo $isShared ? 'shared' : 'not-shared'; ?>">
                    <?php echo $isShared ? '✓ Already Shared' : '○ Not Yet Shared'; ?>
                </span>
            </div>

            <?php if (!$isShared): ?>
            <div class="alert-warn">
                ⚠️ The printer is <strong>not currently shared</strong>. Choose one of the methods below to share it. This only needs to be done <strong>once on the server</strong>.
            </div>

            <div class="step-row">
                <span class="step-num">A</span>
                <div class="step-body">
                    <div class="step-title">Auto-Share via this Page (Try First)</div>
                    <div class="step-desc">Click the button below. The server will attempt to share the printer automatically.</div>
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="action" value="share_printer">
                        <button type="submit" class="share-btn">🔗 Share Printer Now (Auto)</button>
                    </form>
                </div>
            </div>

            <div class="step-row" style="margin-top:16px;">
                <span class="step-num">B</span>
                <div class="step-body">
                    <div class="step-title">Manual Share via Windows (If Auto-Share Fails)</div>
                    <div class="step-desc">
                        <ol style="margin: 8px 0 0 0; padding-left:18px; line-height:1.8;">
                            <li>Press <kbd>Win+R</kbd> → type <code>control printers</code> → Enter</li>
                            <li>Right-click <strong>Generic / Text Only</strong> → <em>Printer properties</em> (not Printing Preferences)</li>
                            <li>Click the <strong>Sharing</strong> tab</li>
                            <li>Check <strong>Share this printer</strong></li>
                            <li>Set Share name to: <strong>GnrcText</strong></li>
                            <li>Click <strong>Apply</strong> → <strong>OK</strong></li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="step-row" style="margin-top:16px;">
                <span class="step-num">C</span>
                <div class="step-body">
                    <div class="step-title">Download & Run the Share Script (As Administrator)</div>
                    <div class="step-desc">
                        Download this script, right-click → <em>"Run as administrator"</em> on the server:
                    </div>
                    <div style="margin-top:8px;">
                        <a href="scripts/share_printer_server.bat" download class="dl-btn">⬇️ Download share_printer_server.bat</a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <div class="alert-success">
                ✅ <strong>Printer is already shared!</strong> The <strong>Generic / Text Only</strong> printer is shared on this server. Proceed to Step 2 to deploy to cashier workstations.
            </div>
            <?php endif; ?>

            <div style="margin-top: 20px; padding: 14px 16px; background:#f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px;">Network Printer Paths</div>
                <div style="display:flex; flex-wrap:wrap; gap:16px;">
                    <div>
                        <div style="font-size:11px; color:#94a3b8; font-weight:600; margin-bottom:3px;">By Hostname</div>
                        <code class="unc-box"><?php echo htmlspecialchars($uncPath); ?></code>
                        <button class="copy-btn" onclick="navigator.clipboard.writeText('<?php echo addslashes($uncPath); ?>'); this.textContent='✓ Copied'">📋 Copy</button>
                    </div>
                    <div>
                        <div style="font-size:11px; color:#94a3b8; font-weight:600; margin-bottom:3px;">By IP Address</div>
                        <code class="unc-box"><?php echo htmlspecialchars($uncPathIP); ?></code>
                        <button class="copy-btn" onclick="navigator.clipboard.writeText('<?php echo addslashes($uncPathIP); ?>'); this.textContent='✓ Copied'">📋 Copy</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- === CLIENT SECTION === -->
        <div class="setup-card">
            <div class="section-title">💻 Step 2 — Install on Cashier Workstations</div>

            <div class="alert-info">
                📡 Do this <strong>once per cashier PC</strong>. After installing the network printer, the cashier will get the exact same hardware ROM font as the server's Generic/Text Only direct print.
            </div>

            <div class="step-row">
                <span class="step-num">1</span>
                <div class="step-body">
                    <div class="step-title">Download the Client Install Script</div>
                    <div class="step-desc">Download this file, copy it to the cashier PC (USB or network share), and double-click it.</div>
                    <div style="margin-top:10px;">
                        <a href="?download=client_bat" class="dl-btn">⬇️ Download install_receipt_printer.bat</a>
                        <a href="scripts/client_install_printer.bat" download class="dl-btn" style="margin-left:8px; background:linear-gradient(135deg,#0284c7 0%,#0369a1 100%);">⬇️ Alternate Script</a>
                    </div>
                </div>
            </div>

            <div class="step-row" style="margin-top:16px;">
                <span class="step-num">2</span>
                <div class="step-body">
                    <div class="step-title">Manual Install (If Script Doesn't Work)</div>
                    <div class="step-desc">
                        On the cashier PC:
                        <ol style="margin: 8px 0 0 0; padding-left:18px; line-height:1.8;">
                            <li>Press <kbd>Win+R</kbd> → type the network path below → press Enter:</li>
                        </ol>
                        <div style="margin-top: 6px;">
                            <code class="unc-box"><?php echo htmlspecialchars($uncPath); ?></code>
                            <button class="copy-btn" onclick="navigator.clipboard.writeText('<?php echo addslashes($uncPath); ?>'); this.textContent='✓ Copied'">📋 Copy</button>
                        </div>
                        <ol style="margin: 8px 0 0 0; padding-left:18px; line-height:1.8;" start="2">
                            <li>Windows will connect and prompt to install — click <strong>Install driver</strong></li>
                            <li>The printer will appear in <em>Printers &amp; scanners</em> as <strong>GnrcText on <?php echo htmlspecialchars($serverHost); ?></strong></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- === PRINTING SECTION === -->
        <div class="setup-card">
            <div class="section-title">🖨️ Step 3 — How Cashiers Print After Setup</div>

            <div class="step-row">
                <span class="step-num">1</span>
                <div class="step-body">
                    <div class="step-title">Open Receipt Reprint in Chrome or Edge</div>
                    <div class="step-desc">Navigate to the Receipt Reprint page and find the transaction to print.</div>
                </div>
            </div>

            <div class="step-row">
                <span class="step-num">2</span>
                <div class="step-body">
                    <div class="step-title">Click "Browser Print" or press Ctrl+P</div>
                    <div class="step-desc">This opens the system print dialog.</div>
                </div>
            </div>

            <div class="step-row">
                <span class="step-num">3</span>
                <div class="step-body">
                    <div class="step-title">Select the Network Printer</div>
                    <div class="step-desc">
                        In the printer dropdown, select:
                        <code class="unc-box" style="display:block; margin:6px 0; font-size:13px;"><?php echo htmlspecialchars($uncPath); ?></code>
                        (or search for <code>GnrcText</code> or <code><?php echo htmlspecialchars($serverHost); ?></code>)
                    </div>
                </div>
            </div>

            <div class="step-row">
                <span class="step-num">4</span>
                <div class="step-body">
                    <div class="step-title">Set Paper Size & Margins</div>
                    <div class="step-desc">
                        <ul style="margin: 4px 0 0 0; padding-left:18px; line-height:1.8;">
                            <li>Click <strong>More settings</strong></li>
                            <li><strong>Paper size</strong>: Select <code>Reciept Paper</code> (if available) or <code>Custom</code> → 80mm wide</li>
                            <li><strong>Margins</strong>: None</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="step-row">
                <span class="step-num">5</span>
                <div class="step-body">
                    <div class="step-title">Click Print ✓</div>
                    <div class="step-desc">
                        The job routes through Windows → <strong>Generic/Text Only driver</strong> on the server → printer's hardware ROM character generator.<br>
                        <strong>You will get the exact same dot-matrix ROM font as direct server printing.</strong>
                    </div>
                </div>
            </div>

            <div class="alert-info" style="margin-top:16px;">
                💡 <strong>Why this works:</strong> When you select <code><?php echo htmlspecialchars($uncPath); ?></code>, Windows sends the print job through the server's Generic/Text Only driver which outputs pure ASCII text to the printer's built-in character ROM — not a rasterized graphic. This is exactly what the original server-side direct print did.
            </div>
        </div>

        <!-- === WHY SECTION === -->
        <div class="setup-card" style="background: #fafafa;">
            <div class="section-title" style="font-size:15px;">❓ Why is the font different without this setup?</div>
            <div style="font-size:13px; color:#475569; line-height:1.7;">
                <p style="margin-top:0;"><strong>Server direct print (RAW mode):</strong> The Windows spooler sends raw ASCII bytes directly to the printer's USB port. The printer's internal microchip interprets each byte and fires the physical pins using its <em>built-in character ROM</em> (the classic condensed dot-matrix font you recognize).</p>
                <p><strong>Browser window.print():</strong> Chrome rasterizes the web page into a bitmap image (picture of text) and sends it to the printer driver as graphics. Even with the correct monospace font on screen, the printer receives pixels — not ASCII characters — so the hardware ROM is never triggered.</p>
                <p style="margin-bottom:0;"><strong>Network Generic/Text printer (this setup):</strong> Because the print job goes through the server's Generic/Text Only driver, Windows strips out all formatting and sends pure ASCII text → hardware ROM. <strong>Result: identical font.</strong></p>
            </div>
        </div>

    </div><!-- end panel -->
</main>

<script>
// Allow copy buttons to reset after 2 seconds
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const orig = btn.textContent;
        setTimeout(() => btn.textContent = orig, 2000);
    });
});

// Detect WebSerial / WebUSB capability
document.addEventListener('DOMContentLoaded', () => {
    const badge = document.getElementById('webserial-badge');
    if (!badge || !window.WebHardwarePrint) return;

    const compat = window.WebHardwarePrint.checkCompatibility();
    if (compat.hasSerial || compat.hasUsb) {
        badge.textContent = '✓ Hardware Direct Ready';
        badge.style.background = '#dcfce7';
        badge.style.color = '#15803d';
    } else {
        badge.textContent = '⚠️ WebSerial Needs 15s Setup (Click Configure)';
        badge.style.background = '#fef3c7';
        badge.style.color = '#92400e';
    }
});

async function runHardwareTestPrint() {
    const btn = document.getElementById('btnHwTestPrint');
    const status = document.getElementById('hwTestStatus');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '⏳ Transmitting Test...';
    btn.disabled = true;
    if (status) status.style.display = 'none';

    try {
        const res = await window.WebHardwarePrint.printTestReceipt();
        if (res && res.success) {
            if (status) {
                status.style.display = 'block';
                status.style.background = '#dcfce7';
                status.style.color = '#15803d';
                status.style.border = '1px solid #86efac';
                status.innerHTML = '✅ <strong>Test receipt printed successfully!</strong> Thermal printer printed using authentic Hardware Generic/Text ROM font.';
            }
        }
    } catch (err) {
        if (status && err.name !== 'NotFoundError' && err.message !== 'No serial port selected.') {
            status.style.display = 'block';
            status.style.background = '#fee2e2';
            status.style.color = '#b91c1c';
            status.style.border = '1px solid #fca5a5';
            status.innerHTML = '❌ <strong>Hardware Print Error:</strong> ' + err.message;
        }
    } finally {
        btn.innerHTML = origHtml;
        btn.disabled = false;
    }
}
</script>
</body>
</html>
