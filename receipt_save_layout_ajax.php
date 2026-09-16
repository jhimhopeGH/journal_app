<?php
// receipt_save_layout_ajax.php - Dedicated AJAX endpoint to save Receipt Print Layout settings in real time
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();
require_once __DIR__ . '/ej_db.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true);

if (!is_array($postData)) {
    $postData = $_POST;
}

if (!is_array($postData) || empty($postData)) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

$newSettings = [
    'char_width'             => max(30, min(80, (int)($postData['char_width'] ?? 42))),
    'font_size'              => max(7, min(24, (float)($postData['font_size'] ?? 10))),
    'line_height'            => max(0.9, min(3.5, (float)($postData['line_height'] ?? 1.3))),
    'font_family'            => in_array($postData['font_family'] ?? '', ['generic_text', 'thermal', 'consolas', 'lucida', 'courier']) ? $postData['font_family'] : 'generic_text',
    'font_weight'            => in_array($postData['font_weight'] ?? '', ['bold', 'normal']) ? $postData['font_weight'] : 'bold',
    'receipt_padding_x'      => max(0, min(80, (int)($postData['receipt_padding_x'] ?? 10))),
    'receipt_padding_y'      => max(0, min(80, (int)($postData['receipt_padding_y'] ?? 4))),
    'header_top_space'       => max(0, min(10, (int)($postData['header_top_space'] ?? 1))),
    'cut_margin_bottom'      => max(0, min(15, (int)($postData['cut_margin_bottom'] ?? 4))),

    'reprint_line_count'     => max(1, min(3,  (int)($postData['reprint_line_count'] ?? 2))),
    'reprint_top_space'      => max(0, min(10, (int)($postData['reprint_top_space'] ?? 1))),
    'reprint_between_space'  => max(0, min(10, (int)($postData['reprint_between_space'] ?? 0))),
    'reprint_bottom_space'   => max(0, min(10, (int)($postData['reprint_bottom_space'] ?? 1))),
    'reprint_asterisk_space' => max(0, min(10, (int)($postData['reprint_asterisk_space'] ?? 1))),
    'reprint_char_space'     => max(0, min(8,  (int)($postData['reprint_char_space'] ?? 1))),

    'col_qty_width'          => max(2, min(10, (int)($postData['col_qty_width'] ?? 4))),
    'col_sku_width'          => max(6, min(20, (int)($postData['col_sku_width'] ?? 10))),
    'col_price_width'        => max(4, min(15, (int)($postData['col_price_width'] ?? 8))),
    'col_amt_width'          => max(5, min(16, (int)($postData['col_amt_width'] ?? 9))),

    'col_subtotal_label'     => max(10, min(35, (int)($postData['col_subtotal_label'] ?? 26))),
    'col_subtotal_amt'       => max(6, min(25, (int)($postData['col_subtotal_amt'] ?? 14))),
    'col_total_label'        => max(10, min(35, (int)($postData['col_total_label'] ?? 26))),
    'col_total_amt'          => max(6, min(25, (int)($postData['col_total_amt'] ?? 14))),
    'col_tender_label'       => max(10, min(35, (int)($postData['col_tender_label'] ?? 26))),
    'col_tender_amt'         => max(6, min(25, (int)($postData['col_tender_amt'] ?? 14))),
    'col_change_label'       => max(8, min(25, (int)($postData['col_change_label'] ?? 16))),
    'col_change_amt'         => max(6, min(25, (int)($postData['col_change_amt'] ?? 14))),

    'col_vat_label'          => max(10, min(30, (int)($postData['col_vat_label'] ?? 17))),
    'col_vat_amt'            => max(6, min(25, (int)($postData['col_vat_amt'] ?? 14)))
];

$result = saveReceiptPrintSettings($newSettings);

if ($result !== false) {
    echo json_encode(['status' => 'success', 'message' => '✓ Receipt Print Settings Saved!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not save to receipt_print_settings.json']);
}
exit;
