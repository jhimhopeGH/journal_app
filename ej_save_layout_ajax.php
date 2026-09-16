<?php
// ej_save_layout_ajax.php - Ultra-fast dedicated JSON save endpoint for EJ Print Settings
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
requireAjaxAdmin();

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
    'paper_size'               => in_array($postData['paper_size'] ?? '', ['a4', 'letter']) ? $postData['paper_size'] : 'a4',
    'sheet_margin'             => (float)($postData['sheet_margin'] ?? 0.30),
    'top_title_margin_top'     => (int)($postData['top_title_margin_top'] ?? 25),
    'bottom_title_margin_bottom' => (int)($postData['bottom_title_margin_bottom'] ?? 16),
    'top_title_font_size'      => (int)($postData['top_title_font_size'] ?? 16),
    'meta_gap'                 => (int)($postData['meta_gap'] ?? 65),
    'meta_font_size'           => (int)($postData['meta_font_size'] ?? 14),
    'char_width'               => (int)($postData['char_width'] ?? 54),
    'font_size'                => (int)($postData['font_size'] ?? 15),
    'line_height'              => (float)($postData['line_height'] ?? 1.36),
    'receipt_padding_x'        => (int)($postData['receipt_padding_x'] ?? 20),
    'receipt_padding_y'        => (int)($postData['receipt_padding_y'] ?? 6),
    'watermark_text'           => trim((string)($postData['watermark_text'] ?? 'Electronic Journal Copy')),
    'watermark_font_size'      => (int)($postData['watermark_font_size'] ?? 60),
    'watermark_top'            => (int)($postData['watermark_top'] ?? 75),
    'watermark_left'           => (int)($postData['watermark_left'] ?? 50),
    'watermark_opacity'        => (float)($postData['watermark_opacity'] ?? 0.16),
    'watermark_letter_spacing' => (float)($postData['watermark_letter_spacing'] ?? 3),

    // Column & Variable Offsets
    'col_sku_width'            => (int)($postData['col_sku_width'] ?? 16),
    'col_desc_gap'             => (int)($postData['col_desc_gap'] ?? 3),
    'col_price_width'          => (int)($postData['col_price_width'] ?? 16),
    'col_qty_width'            => (int)($postData['col_qty_width'] ?? 6),
    'col_amt_width'            => (int)($postData['col_amt_width'] ?? 20),

    'col_subtotal_label'       => (int)($postData['col_subtotal_label'] ?? 28),
    'col_subtotal_amt'         => (int)($postData['col_subtotal_amt'] ?? 20),
    'col_tax_label'            => (int)($postData['col_tax_label'] ?? 22),
    'col_tax_amt'              => (int)($postData['col_tax_amt'] ?? 26),
    'col_total_label'          => (int)($postData['col_total_label'] ?? 24),
    'col_total_amt'            => (int)($postData['col_total_amt'] ?? 24),
    'col_tender_label'         => (int)($postData['col_tender_label'] ?? 23),
    'col_tender_amt'           => (int)($postData['col_tender_amt'] ?? 25),
    'col_change_label'         => (int)($postData['col_change_label'] ?? 25),
    'col_change_amt'           => (int)($postData['col_change_amt'] ?? 18),

    'col_bir_vat_amt'          => (int)($postData['col_bir_vat_amt'] ?? 20),
    'col_vatable_amt'          => (int)($postData['col_vatable_amt'] ?? 22),
    'col_vat_amt'              => (int)($postData['col_vat_amt'] ?? 22),
    'col_nonvat_amt'           => (int)($postData['col_nonvat_amt'] ?? 18),

    // Custom Text & Notes
    'custom_header_text'       => trim((string)($postData['custom_header_text'] ?? '')),
    'custom_header_align'      => in_array($postData['custom_header_align'] ?? '', ['left', 'center', 'right']) ? $postData['custom_header_align'] : 'center',
    'custom_mid_text'          => trim((string)($postData['custom_mid_text'] ?? '')),
    'custom_mid_align'         => in_array($postData['custom_mid_align'] ?? '', ['left', 'center', 'right']) ? $postData['custom_mid_align'] : 'center',
    'custom_footer_text'       => trim((string)($postData['custom_footer_text'] ?? 'THANK YOU FOR SHOPPING!')),
    'custom_footer_align'      => in_array($postData['custom_footer_align'] ?? '', ['left', 'center', 'right']) ? $postData['custom_footer_align'] : 'center',

    // Top Header Divider & Line Sliders
    'top_divider_type'         => in_array($postData['top_divider_type'] ?? '', ['double', 'dash', 'asterisk', 'none', 'custom']) ? $postData['top_divider_type'] : 'double',
    'top_divider_custom'       => trim((string)($postData['top_divider_custom'] ?? '')),
    'header_name_offset'       => (int)($postData['header_name_offset'] ?? 0),
    'header_company_offset'    => (int)($postData['header_company_offset'] ?? 0),
    'header_tin_offset'        => (int)($postData['header_tin_offset'] ?? 0),
    'header_addr_offset'       => (int)($postData['header_addr_offset'] ?? 0),
    'header_tel_offset'        => (int)($postData['header_tel_offset'] ?? 0),
    'header_acc_offset'        => (int)($postData['header_acc_offset'] ?? 0),
    'header_serial_offset'     => (int)($postData['header_serial_offset'] ?? 0),
    'header_min_offset'        => (int)($postData['header_min_offset'] ?? 0),
    'header_permit_offset'     => (int)($postData['header_permit_offset'] ?? 0),

    // VAT Breakdown Dividers & Spacing
    'vat_top_divider_type'     => in_array($postData['vat_top_divider_type'] ?? '', ['double', 'dash', 'asterisk', 'none', 'custom']) ? $postData['vat_top_divider_type'] : 'dash',
    'vat_top_divider_custom'   => trim((string)($postData['vat_top_divider_custom'] ?? '')),
    'vat_bottom_divider_type'  => in_array($postData['vat_bottom_divider_type'] ?? '', ['double', 'dash', 'asterisk', 'none', 'custom']) ? $postData['vat_bottom_divider_type'] : 'dash',
    'vat_bottom_divider_custom'=> trim((string)($postData['vat_bottom_divider_custom'] ?? '')),
    'vat_bottom_space_before'  => max(0, min(10, (int)($postData['vat_bottom_space_before'] ?? 0))),
    'vat_bottom_space_after'   => max(0, min(10, (int)($postData['vat_bottom_space_after'] ?? 1)))
];

$file = __DIR__ . '/ej_print_settings.json';
$result = file_put_contents($file, json_encode($newSettings, JSON_PRETTY_PRINT));

if ($result !== false) {
    echo json_encode(['status' => 'success', 'message' => '✓ Layout Settings Saved!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not write to ej_print_settings.json']);
}
exit;
