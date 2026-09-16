<?php
// import_regset_ajax.php - PHP endpoint to trigger REGSET.TPS import via Python
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$store_code = trim($_POST['store_code'] ?? '');
if ($store_code === '') {
    echo json_encode(['success' => false, 'error' => 'Store code is required before importing.']);
    exit;
}

$dbPath     = realpath(__DIR__ . '/journal.db');
$scriptPath = realpath(__DIR__ . '/scripts/import_regset.py');
$python     = 'C:\\python32\\python.exe';

if (!file_exists($python)) {
    echo json_encode(['success' => false, 'error' => '32-bit Python not found at C:\\python32\\python.exe']);
    exit;
}
if (!$scriptPath) {
    echo json_encode(['success' => false, 'error' => 'Import script not found.']);
    exit;
}

$cmd = escapeshellarg($python)
     . ' ' . escapeshellarg($scriptPath)
     . ' ' . escapeshellarg($store_code)
     . ' ' . escapeshellarg($dbPath)
     . ' 2>&1';

$output = shell_exec($cmd);
$output = trim($output ?? '');

// Try to decode JSON from the last line of output
$lines = array_filter(explode("\n", $output), fn($l) => trim($l) !== '');
$lastLine = trim(end($lines) ?: '');
$decoded = json_decode($lastLine, true);

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
