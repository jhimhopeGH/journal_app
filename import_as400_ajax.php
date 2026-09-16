<?php
// import_as400_ajax.php - Handles AS400 import via AJAX
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();
require_once __DIR__ . '/ej_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$storeCode = trim(str_replace(',', '', $_POST['store_code'] ?? ''));
$dsnName   = trim($_POST['dsn_name'] ?? '');
$username  = trim($_POST['username'] ?? '');
$password  = trim($_POST['password'] ?? '');
$dateFrom  = trim($_POST['date_from'] ?? '');
$regNo     = preg_replace('/[^\d]/', '', trim($_POST['reg_no'] ?? ''));
$traxnNo   = preg_replace('/[^\d]/', '', trim($_POST['traxn_no'] ?? ''));

if (empty($storeCode) || empty($dateFrom) || empty($regNo)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Store Code, Date, and Register Number are required to fetch AS400 details.'
    ]);
    exit;
}

// Load saved config if available
$configFile = __DIR__ . '/as400_config.json';
$library = 'MMLTSLIB';
if (file_exists($configFile)) {
    $cfg = json_decode(file_get_contents($configFile), true);
    if (is_array($cfg)) {
        if ($username === '' && !empty($cfg['username'])) $username = $cfg['username'];
        if ($password === '' && !empty($cfg['password'])) $password = $cfg['password'];
        if ($dsnName === '' && !empty($cfg['dsn_name'])) $dsnName = $cfg['dsn_name'];
        if (!empty($cfg['library'])) $library = $cfg['library'];
    }
}

if ($dsnName === '') $dsnName = 'mms_as400';

$pythonExe = 'C:\\python32\\python.exe';
if (!file_exists($pythonExe)) {
    $pythonExe = 'python';
}

$scriptPath = __DIR__ . '/scripts/import_as400.py';
$dbFile = __DIR__ . '/ej.db';

if (!file_exists($scriptPath)) {
    echo json_encode(['status' => 'error', 'message' => 'AS400 import script not found: ' . $scriptPath]);
    exit;
}

$cmd = escapeshellarg($pythonExe) . ' ' .
       escapeshellarg($scriptPath) . ' ' .
       escapeshellarg($storeCode) . ' ' .
       escapeshellarg($dsnName) . ' ' .
       escapeshellarg($username) . ' ' .
       escapeshellarg($password) . ' ' .
       escapeshellarg($dateFrom) . ' ' .
       escapeshellarg($regNo) . ' ' .
       escapeshellarg($traxnNo) . ' ' .
       escapeshellarg($dbFile) . ' ' .
       escapeshellarg($library);

$output = [];
$returnVar = 0;
exec($cmd . ' 2>&1', $output, $returnVar);

$outputStr = implode("\n", $output);
$json = json_decode($outputStr, true);

if (is_array($json)) {
    if (empty($json['store_code'])) $json['store_code'] = $storeCode;
    if (empty($json['date'])) $json['date'] = $dateFrom;
    if (empty($json['register_number'])) $json['register_number'] = $regNo;
    if (empty($json['transaction_number']) && !empty($traxnNo)) $json['transaction_number'] = $traxnNo;

    $finalTxn = $json['transaction_number'] ?? $traxnNo;
    if (($json['status'] ?? '') === 'success') {
        // Auto-assign random cashier name from cashier_master for imported records with empty cashier
        try {
            $checkStmt = $ejPdo->prepare("
                SELECT id, store_code FROM ej_entries 
                WHERE (sales_associate IS NULL OR TRIM(sales_associate) = '' OR sales_associate GLOB '[0-9]*')
                  AND store_code = :store
            ");
            $checkStmt->execute([':store' => $storeCode]);
            $unassigned = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($unassigned)) {
                $upStmt = $ejPdo->prepare("UPDATE ej_entries SET sales_associate = :csh, updated_at = datetime('now', 'localtime') WHERE id = :id");
                foreach ($unassigned as $uRow) {
                    $cshName = getRandomCashierName($uRow['store_code'] ?: $storeCode);
                    if ($cshName !== '') {
                        $upStmt->execute([':csh' => $cshName, ':id' => (int)$uRow['id']]);
                    }
                }
            }
        } catch (Exception $e) {
            // non-blocking
        }

        $msg = $json['message'] ?? 'Import successful';
        logActivity('import', 'as400', "Store: {$json['store_code']}, Reg: {$json['register_number']}, Txn: $finalTxn, Date: {$json['date']} | $msg");
    } else {
        $errMsg = $json['message'] ?? 'Import failed';
        logActivity('import', 'as400', "FAILED Store: $storeCode, Reg: $regNo, Txn: $finalTxn | $errMsg");
    }
    echo json_encode($json);
} else {
    logActivity('import', 'as400', "FAILED Store: $storeCode, Reg: $regNo, Txn: $traxnNo | Script execution error");
    echo json_encode([
        'status' => 'error',
        'message' => 'Error executing AS400 import script.',
        'raw_output' => $outputStr
    ]);
}

