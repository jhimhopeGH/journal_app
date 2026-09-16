<?php
// import_tender_ajax.php - Imports tender master from AS400 MMLTSLIB.CSHTRN
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// Load saved AS400 config
$configFile = __DIR__ . '/as400_config.json';
$dsnName  = 'mms_as400';
$username = '';
$password = '';
$library  = 'MMLTSLIB';

if (file_exists($configFile)) {
    $cfg = json_decode(file_get_contents($configFile), true);
    if (is_array($cfg)) {
        if (!empty($cfg['dsn_name'])) $dsnName  = $cfg['dsn_name'];
        if (!empty($cfg['username'])) $username  = $cfg['username'];
        if (!empty($cfg['password'])) $password  = $cfg['password'];
        if (!empty($cfg['library']))  $library   = $cfg['library'];
    }
}

// Allow POST overrides
if (!empty($_POST['dsn_name'])) $dsnName  = trim($_POST['dsn_name']);
if (!empty($_POST['username'])) $username = trim($_POST['username']);
if (!empty($_POST['password'])) $password = trim($_POST['password']);
if (!empty($_POST['library']))  $library  = trim($_POST['library']);

$dbPath     = realpath(__DIR__ . '/journal.db');
$scriptPath = realpath(__DIR__ . '/scripts/import_tender.py');
$python     = 'C:\\python32\\python.exe';

if (!file_exists($python)) {
    $python = 'python';
}
if (!$scriptPath) {
    echo json_encode(['success' => false, 'error' => 'Import script not found: scripts/import_tender.py']);
    exit;
}

$cmd = escapeshellarg($python)
     . ' ' . escapeshellarg($scriptPath)
     . ' ' . escapeshellarg($dsnName)
     . ' ' . escapeshellarg($dbPath)
     . ' 1'                               // clear_first = always true
     . ' ' . escapeshellarg($username)
     . ' ' . escapeshellarg($password)
     . ' ' . escapeshellarg($library)
     . ' 2>&1';

$output = shell_exec($cmd);
$output = trim($output ?? '');

// Try to decode JSON from the last line of output
$lines    = array_filter(explode("\n", $output), fn($l) => trim($l) !== '');
$lastLine = trim(end($lines) ?: '');
$decoded  = json_decode($lastLine, true);

if (is_array($decoded)) {
    echo json_encode($decoded);
} else {
    echo json_encode([
        'success' => false,
        'error'   => 'Import script returned unexpected output.',
        'raw'     => $output
    ]);
}
exit;
