<?php
// import_cashier_ajax.php - Handles EMPMST.TPS import via AJAX (calls Python script)
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$storeCode = isset($_POST['store_code']) ? trim($_POST['store_code']) : '';
$dsnName   = isset($_POST['dsn_name'])   ? trim($_POST['dsn_name'])   : 'Salessum';

if ($storeCode === '') {
    echo json_encode(['success' => false, 'error' => 'Store Code is required.']);
    exit;
}

$pythonCandidates = [
    'C:\\python32\\python.exe',
    'C:\\Program Files\\Python312\\python.exe',
    'python'
];
$pythonExe = null;
foreach ($pythonCandidates as $cand) {
    if ($cand === 'python' || file_exists($cand)) {
        $pythonExe = $cand;
        break;
    }
}

$scriptPath = __DIR__ . '/scripts/import_empmst.py';
$dbFile     = __DIR__ . '/journal.db';

if (!$pythonExe) {
    echo json_encode(['success' => false, 'error' => 'Python executable not found.']);
    exit;
}

if (!file_exists($scriptPath)) {
    echo json_encode(['success' => false, 'error' => 'Import script not found: ' . $scriptPath]);
    exit;
}

// Build command — args: store_code  dsn_name  db_path
$cmd = escapeshellarg($pythonExe) . ' '
     . escapeshellarg($scriptPath) . ' '
     . escapeshellarg($storeCode)  . ' '
     . escapeshellarg($dsnName)    . ' '
     . escapeshellarg($dbFile);

$output    = [];
$returnVar = 0;
exec($cmd . ' 2>&1', $output, $returnVar);

$outputStr = implode("\n", $output);
$json      = json_decode($outputStr, true);

if (is_array($json)) {
    if (($json['success'] ?? false) === true) {
        $ins = $json['inserted'] ?? 0;
        $upd = $json['updated']  ?? 0;
        $msg = "Store: $storeCode | DSN: $dsnName | Inserted: $ins, Updated: $upd";
        logActivity('import', 'cashier_master', $msg);
    } else {
        $errMsg = $json['error'] ?? $json['message'] ?? 'Import failed';
        logActivity('import', 'cashier_master', "FAILED Store: $storeCode | $errMsg");
    }
    echo json_encode($json);
} else {
    logActivity('import', 'cashier_master', "FAILED Store: $storeCode | Script execution error");
    echo json_encode([
        'success'    => false,
        'error'      => 'Error executing import script.',
        'raw_output' => $outputStr
    ]);
}
