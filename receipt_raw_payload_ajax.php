<?php
// receipt_raw_payload_ajax.php - Returns base64-encoded raw ESC/POS binary data for Web Serial / WebUSB client printing
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
requireAjaxAuth();
require_once __DIR__ . '/ej_db.php';
require_once __DIR__ . '/raw_print_service.php';

// If buildReceiptLinesForEntry is in receipt_direct_print_ajax.php, load it safely
if (!function_exists('buildReceiptLinesForEntry')) {
    // Include helper definition
    require_once __DIR__ . '/receipt_builder_helper.php';
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ids = isset($_GET['ids']) ? trim($_GET['ids']) : '';

if (!$id && empty($ids) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        if (!empty($input['id'])) $id = (int)$input['id'];
        if (!empty($input['ids'])) $ids = is_array($input['ids']) ? implode(',', $input['ids']) : trim($input['ids']);
    }
}

$settings = getReceiptPrintSettings();
$tenderNameMap = getTenderNameMap();

try {
    $allPayload = "";
    $count = 0;

    if (!empty($_GET['test'])) {
        $W = (int)($settings['char_width'] ?? 42);
        $testLines = [
            str_repeat('*', $W),
            padLineCenter('GENERIC / TEXT ONLY TEST', $W),
            padLineCenter('HARDWARE ROM CHARACTER SET', $W),
            str_repeat('*', $W),
            padLineCenter('*** REPRINT ***', $W),
            '',
            'Date: ' . date('Y-m-d H:i:s'),
            'Mode: Cashier WebSerial / WebUSB',
            'Font: Hardware Font A (12x24 dots)',
            str_repeat('-', $W),
            str_pad('1', 4, ' ', STR_PAD_LEFT) . '  ' . str_pad('000123456', 10, ' ', STR_PAD_RIGHT) . '  ' . str_pad('150.00', 8, ' ', STR_PAD_LEFT) . '  ' . str_pad('150.00V', 9, ' ', STR_PAD_LEFT),
            '     TEST ITEM SAMPLE PRODUCT',
            str_repeat('-', $W),
            str_pad('Sub Total', 26, ' ', STR_PAD_LEFT) . str_pad('P150.00', 14, ' ', STR_PAD_LEFT),
            str_pad('Total', 26, ' ', STR_PAD_LEFT) . str_pad('P150.00', 14, ' ', STR_PAD_LEFT),
            str_pad('Cash', 26, ' ', STR_PAD_LEFT) . str_pad('P150.00', 14, ' ', STR_PAD_LEFT),
            str_pad('CHANGE', 16, ' ', STR_PAD_LEFT) . '  ====>' . str_pad('P0.00', 14, ' ', STR_PAD_LEFT),
            str_repeat('=', $W),
            padLineCenter('TEST COMPLETED SUCCESSFULLY', $W),
            padLineCenter('GENERIC TEXT FONT OK', $W),
            str_repeat('=', $W),
        ];
        $allPayload = buildEscPosReceiptFromLines($testLines, $settings);
        $count = 1;
    } elseif ($id > 0) {
        $stmt = $ejPdo->prepare("SELECT * FROM ej_entries WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            echo json_encode(['success' => false, 'error' => 'Receipt record not found']);
            exit;
        }

        $lines = buildReceiptLinesForEntry($entry, $tenderNameMap, $settings, $pdo);
        $allPayload = buildEscPosReceiptFromLines($lines, $settings);
        $count = 1;
    } elseif (!empty($ids)) {
        $idArr = array_filter(array_map('intval', explode(',', $ids)));
        if (empty($idArr)) {
            echo json_encode(['success' => false, 'error' => 'No valid IDs provided']);
            exit;
        }

        $inClause = implode(',', array_fill(0, count($idArr), '?'));
        $stmt = $ejPdo->prepare("SELECT * FROM ej_entries WHERE id IN ($inClause) ORDER BY id ASC");
        $stmt->execute($idArr);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($entries as $entry) {
            $lines = buildReceiptLinesForEntry($entry, $tenderNameMap, $settings, $pdo);
            $allPayload .= buildEscPosReceiptFromLines($lines, $settings);
            $count++;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No transaction ID or IDs specified']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'count'   => $count,
        'bytes'   => strlen($allPayload),
        'base64'  => base64_encode($allPayload)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
