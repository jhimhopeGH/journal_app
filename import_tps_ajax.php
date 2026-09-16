<?php
// import_tps_ajax.php - Handles Topspeed import via AJAX
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$storeCode = isset($_POST['store_code']) ? trim($_POST['store_code']) : '';
$dsnName   = isset($_POST['dsn_name']) ? trim($_POST['dsn_name']) : 'Salessum';

if ($storeCode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please specify or select a Store Code.']);
    exit;
}

$pythonExe = 'C:\\python32\\python.exe';
$scriptPath = __DIR__ . '/scripts/import_tps.py';
$dbFile = __DIR__ . '/journal.db';

if (!file_exists($pythonExe)) {
    echo json_encode(['status' => 'error', 'message' => '32-bit Python executable not found at C:\\python32\\python.exe.']);
    exit;
}

if (!file_exists($scriptPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Import script not found: ' . $scriptPath]);
    exit;
}

// Escape arguments
$cmd = escapeshellarg($pythonExe) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($storeCode) . ' ' . escapeshellarg($dsnName) . ' ' . escapeshellarg($dbFile);

$output = [];
$returnVar = 0;
exec($cmd . ' 2>&1', $output, $returnVar);

$outputStr = implode("\n", $output);
$json = json_decode($outputStr, true);

if (is_array($json)) {
    if (($json['status'] ?? '') === 'success') {
        $imp = $json['imported'] ?? ($json['records_imported'] ?? 0);
        $upd = $json['updated'] ?? 0;
        $msg = $json['message'] ?? "Imported: $imp, Updated: $upd";
        logActivity('import', 'tps', "Store: $storeCode | DSN: $dsnName | $msg");
    } else {
        $errMsg = $json['message'] ?? 'Import failed';
        logActivity('import', 'tps', "FAILED Store: $storeCode | $errMsg");
    }
    echo json_encode($json);
} else {
    logActivity('import', 'tps', "FAILED Store: $storeCode | Execution error");
    echo json_encode([
        'status' => 'error',
        'message' => 'Error executing import script.',
        'raw_output' => $outputStr
    ]);
}

