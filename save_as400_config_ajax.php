<?php
// save_as400_config_ajax.php - Saves AS400 connection credentials and tests connectivity
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$dsnName  = trim($_POST['dsn_name'] ?? 'mms_as400');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$library  = trim($_POST['library'] ?? 'MMLTSLIB');

$config = [
    'dsn_name' => $dsnName !== '' ? $dsnName : 'mms_as400',
    'username' => $username,
    'password' => $password,
    'library'  => $library !== '' ? $library : 'MMLTSLIB'
];

$configFile = __DIR__ . '/as400_config.json';
file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

// Test connection if username provided
$testOutput = '';
$testSuccess = false;

if ($username !== '') {
    $pythonExe = 'C:\\python32\\python.exe';
    if (!file_exists($pythonExe)) $pythonExe = 'python';
    
    $scriptPath = __DIR__ . '/scripts/lookup_sku.py';
    $cmd = escapeshellarg($pythonExe) . ' ' .
           escapeshellarg($scriptPath) . ' ' .
           escapeshellarg('TEST_CONNECTION_CHECK') . ' ' .
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
        if ($json['status'] === 'success') {
            $testSuccess = true;
            $testOutput = '✓ Connected to AS400 successfully!';
        } else {
            $testOutput = $json['message'] ?? 'Connection error.';
        }
    } else {
        $testOutput = $outputStr;
    }
}

echo json_encode([
    'status'       => 'success',
    'message'      => 'AS400 configuration saved successfully.',
    'test_success' => $testSuccess,
    'test_message' => $testOutput
]);
