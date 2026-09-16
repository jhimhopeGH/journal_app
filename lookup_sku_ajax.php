<?php
// lookup_sku_ajax.php - Handles SKU description lookup from MMLTSLIB.INVMST via AJAX
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAuth();

$sku      = trim($_REQUEST['sku'] ?? '');
$dsnName  = trim($_REQUEST['dsn_name'] ?? '');
$username = trim($_REQUEST['username'] ?? '');
$password = trim($_REQUEST['password'] ?? '');
$library  = trim($_REQUEST['library'] ?? '');

// Load saved config if available
$configFile = __DIR__ . '/as400_config.json';
if (file_exists($configFile)) {
    $cfg = json_decode(file_get_contents($configFile), true);
    if (is_array($cfg)) {
        if ($username === '' && !empty($cfg['username'])) $username = $cfg['username'];
        if ($password === '' && !empty($cfg['password'])) $password = $cfg['password'];
        if ($dsnName === '' && !empty($cfg['dsn_name'])) $dsnName = $cfg['dsn_name'];
        if ($library === '' && !empty($cfg['library'])) $library = $cfg['library'];
    }
}

if ($dsnName === '') $dsnName = 'mms_as400';
if ($library === '') $library = 'MMLTSLIB';

if ($sku === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please provide a SKU.']);
    exit;
}

$pythonExe = 'C:\\python32\\python.exe';
if (!file_exists($pythonExe)) {
    $pythonExe = 'python';
}

$scriptPath = __DIR__ . '/scripts/lookup_sku.py';

if (!file_exists($scriptPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Lookup script not found: ' . $scriptPath]);
    exit;
}

$cmd = escapeshellarg($pythonExe) . ' ' .
       escapeshellarg($scriptPath) . ' ' .
       escapeshellarg($sku) . ' ' .
       escapeshellarg($dsnName) . ' ' .
       escapeshellarg($username) . ' ' .
       escapeshellarg($password) . ' ' .
       escapeshellarg($library);

$output = [];
$returnVar = 0;
exec($cmd . ' 2>&1', $output, $returnVar);

$outputStr = implode("\n", $output);
$json = json_decode($outputStr, true);

if (is_array($json)) {
    echo json_encode($json);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error querying SKU from AS400.',
        'raw_output' => $outputStr
    ]);
}
