<?php
// ej_print_layout.php - Live Interactive Visual Layout & Granular Column Slider Customizer for Electronic Journal (EJ)
require_once __DIR__ . '/auth.php';
requireNavAccess('ej_layout');
require_once __DIR__ . '/ej_db.php';
$currentPage = 'ej_layout';


$saveMessage = '';
$saveError = '';

// Handle Fallback Form Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $newSettings = [
        'paper_size'               => in_array($_POST['paper_size'] ?? '', ['a4', 'letter']) ? $_POST['paper_size'] : 'a4',
        'sheet_margin'             => (float)($_POST['sheet_margin'] ?? 0.30),
        'top_title_margin_top'        => (int)($_POST['top_title_margin_top'] ?? 25),
        'bottom_title_margin_bottom' => (int)($_POST['bottom_title_margin_bottom'] ?? 16),
        'top_title_font_size'        => (int)($_POST['top_title_font_size'] ?? 16),
        'meta_gap'                 => (int)($_POST['meta_gap'] ?? 65),
        'meta_font_size'           => (int)($_POST['meta_font_size'] ?? 14),
        'char_width'               => (int)($_POST['char_width'] ?? 54),
        'font_size'                => (int)($_POST['font_size'] ?? 15),
        'line_height'              => (float)($_POST['line_height'] ?? 1.36),
        'receipt_padding_x'        => (int)($_POST['receipt_padding_x'] ?? 20),
        'receipt_padding_y'        => (int)($_POST['receipt_padding_y'] ?? 6),
        'watermark_text'           => trim((string)($_POST['watermark_text'] ?? 'Electronic Journal Copy')),
        'watermark_font_size'      => (int)($_POST['watermark_font_size'] ?? 60),
        'watermark_top'            => (int)($_POST['watermark_top'] ?? 75),
        'watermark_left'           => (int)($_POST['watermark_left'] ?? 50),
        'watermark_opacity'        => (float)($_POST['watermark_opacity'] ?? 0.16),
        'watermark_letter_spacing' => (float)($_POST['watermark_letter_spacing'] ?? 3),

        // Granular Column & Variable Offsets
        'col_sku_width'            => (int)($_POST['col_sku_width'] ?? 16),
        'col_desc_gap'             => (int)($_POST['col_desc_gap'] ?? 3),
        'col_price_width'          => (int)($_POST['col_price_width'] ?? 16),
        'col_qty_width'            => (int)($_POST['col_qty_width'] ?? 6),
        'col_amt_width'            => (int)($_POST['col_amt_width'] ?? 20),

        'col_subtotal_label'       => (int)($_POST['col_subtotal_label'] ?? 28),
        'col_subtotal_amt'         => (int)($_POST['col_subtotal_amt'] ?? 20),
        'col_tax_label'            => (int)($_POST['col_tax_label'] ?? 22),
        'col_tax_amt'              => (int)($_POST['col_tax_amt'] ?? 26),
        'col_total_label'          => (int)($_POST['col_total_label'] ?? 24),
        'col_total_amt'            => (int)($_POST['col_total_amt'] ?? 24),
        'col_tender_label'         => (int)($_POST['col_tender_label'] ?? 23),
        'col_tender_amt'           => (int)($_POST['col_tender_amt'] ?? 25),
        'col_change_label'         => (int)($_POST['col_change_label'] ?? 25),
        'col_change_amt'           => (int)($_POST['col_change_amt'] ?? 18),

        'col_bir_vat_amt'          => (int)($_POST['col_bir_vat_amt'] ?? 20),
        'col_vatable_amt'          => (int)($_POST['col_vatable_amt'] ?? 22),
        'col_vat_amt'              => (int)($_POST['col_vat_amt'] ?? 22),
        'col_nonvat_amt'           => (int)($_POST['col_nonvat_amt'] ?? 18),

        // Custom Text & Notes
        'custom_header_text'       => trim((string)($_POST['custom_header_text'] ?? '')),
        'custom_header_align'      => in_array($_POST['custom_header_align'] ?? '', ['left', 'center', 'right']) ? $_POST['custom_header_align'] : 'center',
        'custom_mid_text'          => trim((string)($_POST['custom_mid_text'] ?? '')),
        'custom_mid_align'         => in_array($_POST['custom_mid_align'] ?? '', ['left', 'center', 'right']) ? $_POST['custom_mid_align'] : 'center',
        'custom_footer_text'       => trim((string)($_POST['custom_footer_text'] ?? 'THANK YOU FOR SHOPPING!')),
        'custom_footer_align'      => in_array($_POST['custom_footer_align'] ?? '', ['left', 'center', 'right']) ? $_POST['custom_footer_align'] : 'center',

        // Top Header Divider & Line Sliders
        'top_divider_type'         => in_array($_POST['top_divider_type'] ?? '', ['double', 'dash', 'asterisk', 'none', 'custom']) ? $_POST['top_divider_type'] : 'double',
        'top_divider_custom'       => trim((string)($_POST['top_divider_custom'] ?? '')),
        'header_name_offset'       => (int)($_POST['header_name_offset'] ?? 0),
        'header_company_offset'    => (int)($_POST['header_company_offset'] ?? 0),
        'header_tin_offset'        => (int)($_POST['header_tin_offset'] ?? 0),
        'header_addr_offset'       => (int)($_POST['header_addr_offset'] ?? 0),
        'header_tel_offset'        => (int)($_POST['header_tel_offset'] ?? 0),
        'header_acc_offset'        => (int)($_POST['header_acc_offset'] ?? 0),
        'header_serial_offset'     => (int)($_POST['header_serial_offset'] ?? 0),
        'header_min_offset'        => (int)($_POST['header_min_offset'] ?? 0),
        'header_permit_offset'     => (int)($_POST['header_permit_offset'] ?? 0),

        // VAT Breakdown Dividers & Spacing
        'vat_top_divider_type'     => in_array($_POST['vat_top_divider_type'] ?? '', ['double', 'dash', 'asterisk', 'none', 'custom']) ? $_POST['vat_top_divider_type'] : 'dash',
        'vat_top_divider_custom'   => trim((string)($_POST['vat_top_divider_custom'] ?? '')),
        'vat_bottom_divider_type'  => in_array($_POST['vat_bottom_divider_type'] ?? '', ['double', 'dash', 'asterisk', 'none', 'custom']) ? $_POST['vat_bottom_divider_type'] : 'dash',
        'vat_bottom_divider_custom'=> trim((string)($_POST['vat_bottom_divider_custom'] ?? '')),
        'vat_bottom_space_before'  => max(0, min(10, (int)($_POST['vat_bottom_space_before'] ?? 0))),
        'vat_bottom_space_after'   => max(0, min(10, (int)($_POST['vat_bottom_space_after'] ?? 1)))
    ];

    if (saveEjPrintSettings($newSettings)) {
        $saveMessage = '✓ EJ Print Layout Settings saved successfully!';
    } else {
        $saveError = 'Failed to save settings to ej_print_settings.json';
    }
}

$settings = getEjPrintSettings();

// Load sample entry
$sampleStmt = $ejPdo->query("SELECT * FROM ej_entries ORDER BY id DESC LIMIT 1");
$sampleEntry = $sampleStmt ? $sampleStmt->fetch(PDO::FETCH_ASSOC) : null;

$sampleDate = $sampleEntry['entry_date'] ?? date('Y-m-d');
$sampleTime = $sampleEntry['entry_time'] ?? date('H:i:s');
$sampleDateTimestamp = strtotime($sampleDate);
$sampleHeaderDate = $sampleDateTimestamp ? date('n j Y', $sampleDateTimestamp) : '1 31 2019';
$sampleTrxDateStr = $sampleDateTimestamp ? date('n/j/y', $sampleDateTimestamp) : '1/31/19';
$sampleTimeFormatted = $sampleTime ? date('H:i', strtotime($sampleTime)) : '10:07';

$sampleStore = $sampleEntry['store_code'] ?? '10005';
$sampleReg   = $sampleEntry['register_number'] ?? '104';
$sampleTill  = $sampleEntry['till_number'] ?? '2454';
$sampleTrx   = $sampleEntry['transaction_number'] ?? '1521';
$sampleTrxFormatted = number_format((float)$sampleTrx, 0);
$sampleRawTrx = preg_replace('/[^\d]/', '', (string)$sampleTrx);
if ($sampleRawTrx === '') $sampleRawTrx = '1521';

$sampleMember = $sampleEntry['member_number'] ?? '6000325461';
$sampleName   = $sampleEntry['customer_name'] ?? 'NOYNAY, MEDELLINO';
$sampleAssoc  = $sampleEntry['sales_associate'] ?? 'GENESIS R.';
$sampleAssocId= $sampleEntry['associate_id'] ?? '88019';
$sampleInvoice= !empty($sampleEntry['invoice_number']) ? str_pad(ltrim($sampleEntry['invoice_number'], '0'), 8, '0', STR_PAD_LEFT) : '00412850';

$sampleItems = json_decode($sampleEntry['items'] ?? '[]', true);
if (empty($sampleItems)) {
    $sampleItems = [
        ['sku' => '102030', 'description' => 'MOGU MOGU LYCHEE 320ML', 'quantity' => 2, 'unit_price' => 45.00, 'amount' => 90.00],
        ['sku' => '204060', 'description' => 'SAN MARINO CORNED TUNA 180G', 'quantity' => 3, 'unit_price' => 38.50, 'amount' => 115.50],
        ['sku' => '305080', 'description' => 'KOPICO BLANCA 30G X 10S', 'quantity' => 1, 'unit_price' => 72.00, 'amount' => 72.00]
    ];
}
$sampleSubtotal = 0;
foreach ($sampleItems as $si) {
    $sampleSubtotal += (float)($si['amount'] ?? 0);
}
$sampleVatRate = (float)($sampleEntry['vat_rate'] ?? 12.0);
$sampleVatableSales = round($sampleSubtotal / 1.12, 2);
$sampleTotalVat = round($sampleSubtotal - $sampleVatableSales, 2);
$sampleNonVat = 0.00;
$sampleTotal = $sampleSubtotal;
$sampleTenderCash = ceil($sampleTotal / 100) * 100;
$sampleChange = $sampleTenderCash - $sampleTotal;

// Load Store Master & Register Master for sample header
$sampleStoreMaster = null;
if (!empty($sampleStore)) {
    $smStmt = $pdo->prepare("SELECT * FROM store_master WHERE store_code = :code OR store_name = :code ORDER BY id DESC LIMIT 1");
    $smStmt->execute([':code' => $sampleStore]);
    $sampleStoreMaster = $smStmt->fetch(PDO::FETCH_ASSOC);
}
if (!$sampleStoreMaster) {
    $sampleStoreMaster = getStoreMaster();
}

$sampleRegisterRecord = null;
if (!empty($sampleStore) && !empty($sampleReg)) {
    $regStmt = $pdo->prepare("
        SELECT * FROM register_master
        WHERE store_code = :sc AND reg_no = :rn
        ORDER BY id DESC LIMIT 1
    ");
    $regStmt->execute([':sc' => $sampleStore, ':rn' => $sampleReg]);
    $sampleRegisterRecord = $regStmt->fetch(PDO::FETCH_ASSOC);
}

$sampleHeaderLines = [];
if ($sampleStoreMaster) {
    $hText = trim($sampleStoreMaster['header'] ?? '');
    if ($hText !== '') {
        $hLines = preg_split('/\r\n|\r|\n/', $hText);
        foreach ($hLines as $hl) {
            if (trim($hl) !== '') {
                $sampleHeaderLines[] = trim($hl);
            }
        }
    } elseif (!empty($sampleStoreMaster['store_name'])) {
        $sampleHeaderLines[] = trim($sampleStoreMaster['store_name']);
    }

    if ($sampleRegisterRecord) {
        if (!empty($sampleRegisterRecord['serial_number'])) {
            $sampleHeaderLines[] = 'SERIAL#' . trim($sampleRegisterRecord['serial_number']);
        }
        if (!empty($sampleRegisterRecord['min_number'])) {
            $sampleHeaderLines[] = trim($sampleRegisterRecord['min_number']);
        }
        if (!empty($sampleRegisterRecord['permit_number'])) {
            $sampleHeaderLines[] = str_replace('-', '', trim($sampleRegisterRecord['permit_number']));
        }
    } elseif ($sampleStoreMaster) {
        if (!empty($sampleStoreMaster['serial_number']) && (empty($sampleStoreMaster['header']) || stripos($sampleStoreMaster['header'], $sampleStoreMaster['serial_number']) === false)) {
            $sampleHeaderLines[] = 'SERIAL#' . trim($sampleStoreMaster['serial_number']);
        }
        if (!empty($sampleStoreMaster['min_number']) && (empty($sampleStoreMaster['header']) || stripos($sampleStoreMaster['header'], $sampleStoreMaster['min_number']) === false)) {
            $sampleHeaderLines[] = trim($sampleStoreMaster['min_number']);
        }
        if (!empty($sampleStoreMaster['permit_number']) && (empty($sampleStoreMaster['header']) || stripos($sampleStoreMaster['header'], $sampleStoreMaster['permit_number']) === false)) {
            $sampleHeaderLines[] = str_replace('-', '', trim($sampleStoreMaster['permit_number']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EJ Print Layout Designer - Granular Sliders & Direct Drag</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
  body {
      background: #f8fafc;
      color: #1e293b;
  }
  
  .layout-designer-grid {
      display: grid;
      grid-template-columns: 470px 1fr;
      gap: 24px;
      align-items: start;
  }
  
  @media (max-width: 1200px) {
      .layout-designer-grid {
          grid-template-columns: 1fr;
      }
  }

  /* Controls Panel */
  .controls-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.05);
      padding: 20px;
      position: sticky;
      top: 20px;
      max-height: calc(100vh - 40px);
      overflow-y: auto;
  }

  /* Tabs for grouping sliders */
  .tab-nav {
      display: flex;
      gap: 6px;
      border-bottom: 2px solid #e2e8f0;
      margin-bottom: 16px;
      overflow-x: auto;
      padding-bottom: 2px;
  }
  .tab-btn {
      background: none;
      border: none;
      padding: 8px 12px;
      font-weight: 700;
      font-size: 12px;
      color: #64748b;
      cursor: pointer;
      border-radius: 6px 6px 0 0;
      white-space: nowrap;
      transition: all 0.15s ease;
  }
  .tab-btn:hover {
      color: #0f172a;
      background: #f1f5f9;
  }
  .tab-btn.active {
      color: #0284c7;
      border-bottom: 2px solid #0284c7;
      background: #e0f2fe;
  }

  .tab-pane {
      display: none;
  }
  .tab-pane.active {
      display: block;
  }

  .control-section {
      margin-bottom: 18px;
      padding-bottom: 14px;
      border-bottom: 1px solid #f1f5f9;
  }
  .control-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
  }
  .section-title {
      font-size: 13px;
      font-weight: 700;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
  }

  /* Slider Group */
  .slider-group {
      margin-bottom: 12px;
  }
  .slider-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      font-weight: 600;
      color: #475569;
      margin-bottom: 5px;
  }
  .slider-val-badge {
      font-family: 'Courier New', Courier, monospace;
      background: #f1f5f9;
      color: #0284c7;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 4px;
      font-size: 11.5px;
      border: 1px solid #e2e8f0;
  }
  .slider-input-row {
      display: flex;
      align-items: center;
      gap: 10px;
  }
  input[type="range"] {
      flex: 1;
      height: 6px;
      border-radius: 3px;
      background: #cbd5e1;
      outline: none;
      cursor: pointer;
      accent-color: #0284c7;
  }

  .paper-toggle-group {
      display: flex;
      gap: 10px;
      margin-bottom: 12px;
  }
  .paper-toggle-btn {
      flex: 1;
      padding: 8px 12px;
      border: 2px solid #cbd5e1;
      background: #f8fafc;
      border-radius: 6px;
      font-weight: 700;
      font-size: 12px;
      color: #475569;
      cursor: pointer;
      text-align: center;
      transition: all 0.15s ease;
  }
  .paper-toggle-btn.active {
      border-color: #0284c7;
      background: #e0f2fe;
      color: #0369a1;
  }

  /* Live Preview Canvas */
  .preview-wrapper {
      background: #334155;
      padding: 20px 16px 80px 16px;
      border-radius: 10px;
      box-shadow: inset 0 2px 10px rgba(0,0,0,0.25);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
      min-height: 800px;
  }

  .preview-toolbar {
      width: 100%;
      max-width: 820px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #1e293b;
      color: #fff;
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }
  .preview-toolbar button {
      background: #475569;
      color: #fff;
      border: none;
      padding: 5px 12px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 11px;
      font-weight: 600;
      transition: background 0.15s ease;
  }
  .preview-toolbar button:hover {
      background: #64748b;
  }

  .interaction-hint-bar {
      background: #0284c7;
      color: #ffffff;
      font-size: 12px;
      font-weight: 600;
      padding: 6px 14px;
      border-radius: 20px;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  }

  /* WYSIWYG Sheet Preview */
  .sheet-preview {
      background: #ffffff;
      color: #000000;
      box-shadow: 0 10px 35px rgba(0,0,0,0.35);
      border-radius: 2px;
      position: relative;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: width 0.2s ease, min-height 0.2s ease;
      overflow: hidden;
      user-select: none;
  }
  
  .sheet-preview.size-a4 {
      width: 210mm;
      min-height: 297mm;
  }
  .sheet-preview.size-letter {
      width: 8.5in;
      min-height: 11in;
  }

  /* Printable Margin Guide */
  .sheet-margin-guide {
      position: absolute;
      border: 1px dashed #94a3b8;
      pointer-events: none;
      z-index: 0;
      transition: all 0.15s ease;
  }
  .sheet-margin-guide::before {
      content: attr(data-label);
      position: absolute;
      top: -16px;
      left: 0;
      font-family: system-ui, sans-serif;
      font-size: 10px;
      color: #94a3b8;
      font-weight: bold;
  }

  /* Interactive Elements On Canvas */
  .canvas-interactive {
      cursor: grab;
      position: relative;
      transition: outline 0.15s ease, background 0.15s ease;
      border-radius: 4px;
  }
  .canvas-interactive:hover {
      outline: 1.5px dashed #0284c7;
      background: rgba(2, 132, 199, 0.04);
  }
  .canvas-interactive.active-drag {
      cursor: grabbing !important;
      outline: 2px solid #0284c7 !important;
      background: rgba(2, 132, 199, 0.08) !important;
  }
  .canvas-interactive.selected-element {
      outline: 2px solid #0284c7 !important;
  }

  .canvas-interactive::after {
      content: attr(data-hint);
      position: absolute;
      top: -24px;
      left: 50%;
      transform: translateX(-50%);
      background: #0f172a;
      color: #ffffff;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 700;
      white-space: nowrap;
      pointer-events: none;
      opacity: 0;
      transition: opacity 0.2s ease;
      z-index: 100;
  }
  .canvas-interactive:hover::after {
      opacity: 1;
  }

  .preview-receipt-container {
      width: 100%;
      max-width: 100%;
      margin: 0;
      position: relative;
      z-index: 1;
  }

  .preview-header-title {
      text-align: center;
      font-weight: 800;
      color: #000000;
      letter-spacing: 0.5px;
      margin-bottom: 16px;
  }

  .preview-footer-title {
      text-align: center;
      font-weight: 800;
      margin-top: auto;
      margin-bottom: 0;
      padding-top: 16px;
      color: #000000;
      letter-spacing: 0.5px;
      position: relative;
      z-index: 1;
      width: 100%;
  }

  .preview-meta-bar {
      display: flex;
      justify-content: center;
      align-items: center;
      color: #000000;
      padding-bottom: 6px;
      border-bottom: 1.5px solid #000000;
      width: 100%;
  }
  .preview-meta-bar .meta-item span.label {
      font-weight: 800;
  }
  .preview-meta-bar .meta-item span.val {
      font-weight: 700;
  }

  .preview-body-wrapper {
      position: relative;
      margin-top: 10px;
      padding-bottom: 8px;
      border-bottom: 1.5px solid #000000;
      width: 100%;
      min-height: 420px;
  }

  .preview-watermark {
      position: absolute;
      transform: translate(-50%, -50%) rotate(-90deg);
      transform-origin: center center;
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 300;
      white-space: nowrap;
      z-index: 1;
      user-select: none;
      cursor: move !important;
  }

  pre.preview-content {
      position: relative;
      z-index: 2;
      font-family: 'Courier New', Courier, 'Lucida Console', monospace;
      font-weight: 400;
      color: #000000;
      margin: 0;
      white-space: pre;
      cursor: ew-resize;
  }

  .nudge-toolbar {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%);
      background: #0f172a;
      color: #ffffff;
      padding: 8px 16px;
      border-radius: 30px;
      display: none;
      align-items: center;
      gap: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
      z-index: 9000;
      font-size: 12px;
      font-weight: 600;
  }
  .nudge-toolbar.visible {
      display: flex;
  }
  .nudge-btn {
      background: #334155;
      color: #ffffff;
      border: 1px solid #475569;
      padding: 4px 10px;
      border-radius: 16px;
      cursor: pointer;
      font-size: 11px;
      font-weight: bold;
      transition: all 0.15s ease;
  }
  .nudge-btn:hover {
      background: #0284c7;
      border-color: #0284c7;
  }

  .save-toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: #16a34a;
      color: #ffffff;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.25);
      z-index: 9999;
      opacity: 0;
      transform: translateY(20px);
      transition: all 0.3s ease;
      pointer-events: none;
  }
  .save-toast.show {
      opacity: 1;
      transform: translateY(0);
  }
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1>EJ Print Layout Designer</h1>
                <p>Granular sliders & live WYSIWYG preview to maneuver every receipt column and variable.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="ej_print.php?id=<?php echo (int)($sampleEntry['id'] ?? 1); ?>" target="_blank" class="btn btn-secondary">🖨️ View ej_print.php</a>
                <button type="button" id="btn_save_top" class="btn" style="padding: 9px 22px; font-weight: 700;">💾 Save Layout</button>
            </div>
        </div>

        <?php if ($saveMessage): ?>
            <div class="success"><?php echo htmlspecialchars($saveMessage); ?></div>
        <?php endif; ?>
        <?php if ($saveError): ?>
            <div class="danger"><?php echo htmlspecialchars($saveError); ?></div>
        <?php endif; ?>

        <div class="layout-designer-grid">
            
            <!-- Left: Sliders Controls Panel with Category Tabs -->
            <div class="controls-card">
                <!-- Tabs -->
                <div class="tab-nav">
                    <button type="button" class="tab-btn active" data-tab="tab-general">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        General & Sheet
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-header">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><path d="M3 3h18v4H3z"></path><path d="M3 7l2 12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2l2-12"></path></svg>
                        Store Header
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-items">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        Item Columns
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-totals">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        Totals & Tenders
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-vat">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><line x1="19" y1="5" x2="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>
                        VAT & Taxes
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-wm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>
                        Watermark
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-custom">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><polyline points="4 7 4 4 20 4 20 7"></polyline><line x1="9" y1="20" x2="15" y2="20"></line><line x1="12" y1="4" x2="12" y2="20"></line></svg>
                        Custom Text
                    </button>
                </div>

                <form id="ejSettingsForm" method="POST" action="ej_print_layout.php">
                    <input type="hidden" name="action" value="save_settings">

                    <!-- TAB 1: General & Sheet -->
                    <div class="tab-pane active" id="tab-general">
                        <div class="control-section">
                            <div class="section-title">📄 Paper & Sheet Margins</div>
                            
                            <div class="paper-toggle-group">
                                <input type="hidden" name="paper_size" id="paper_size" value="<?php echo htmlspecialchars($settings['paper_size'] ?? 'a4'); ?>">
                                <button type="button" class="paper-toggle-btn <?php echo ($settings['paper_size'] ?? 'a4') === 'a4' ? 'active' : ''; ?>" data-paper="a4">A4 (210 × 297mm)</button>
                                <button type="button" class="paper-toggle-btn <?php echo ($settings['paper_size'] ?? 'a4') === 'letter' ? 'active' : ''; ?>" data-paper="letter">US Letter (8.5" × 11")</button>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Sheet Margin (in):</span>
                                    <span class="slider-val-badge" id="val_sheet_margin"><?php echo number_format((float)($settings['sheet_margin'] ?? 0.30), 2); ?> in</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="sheet_margin" id="sheet_margin" min="0.00" max="0.75" step="0.01" value="<?php echo (float)($settings['sheet_margin'] ?? 0.30); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="control-section">
                            <div class="section-title">🏷️ Header & Footer Title</div>
                            
                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Top Title Margin Top (Offset):</span>
                                    <span class="slider-val-badge" id="val_top_title_margin_top"><?php echo (int)($settings['top_title_margin_top'] ?? 25); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="top_title_margin_top" id="top_title_margin_top" min="0" max="70" step="1" value="<?php echo (int)($settings['top_title_margin_top'] ?? 25); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Bottom Title Margin Bottom (Offset):</span>
                                    <span class="slider-val-badge" id="val_bottom_title_margin_bottom"><?php echo (int)($settings['bottom_title_margin_bottom'] ?? 16); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="bottom_title_margin_bottom" id="bottom_title_margin_bottom" min="0" max="70" step="1" value="<?php echo (int)($settings['bottom_title_margin_bottom'] ?? 16); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Title Font Size:</span>
                                    <span class="slider-val-badge" id="val_top_title_font_size"><?php echo (int)($settings['top_title_font_size'] ?? 16); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="top_title_font_size" id="top_title_font_size" min="0" max="36" step="1" value="<?php echo (int)($settings['top_title_font_size'] ?? 16); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="control-section">
                            <div class="section-title">📊 Metadata Bar (Date/Store/Reg/Trx)</div>
                            
                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Items Spacing (Gap):</span>
                                    <span class="slider-val-badge" id="val_meta_gap"><?php echo (int)($settings['meta_gap'] ?? 65); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="meta_gap" id="meta_gap" min="0" max="150" step="1" value="<?php echo (int)($settings['meta_gap'] ?? 65); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Meta Font Size:</span>
                                    <span class="slider-val-badge" id="val_meta_font_size"><?php echo (int)($settings['meta_font_size'] ?? 14); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="meta_font_size" id="meta_font_size" min="0" max="24" step="1" value="<?php echo (int)($settings['meta_font_size'] ?? 14); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="control-section">
                            <div class="section-title">🧾 Receipt Layout & Indentation</div>
                            
                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Horizontal Indent (Padding Left/Right):</span>
                                    <span class="slider-val-badge" id="val_receipt_padding_x"><?php echo (int)($settings['receipt_padding_x'] ?? 20); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="receipt_padding_x" id="receipt_padding_x" min="0" max="80" step="1" value="<?php echo (int)($settings['receipt_padding_x'] ?? 20); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Monospace Font Size:</span>
                                    <span class="slider-val-badge" id="val_font_size"><?php echo (int)($settings['font_size'] ?? 15); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="font_size" id="font_size" min="0" max="25" step="0.5" value="<?php echo (float)($settings['font_size'] ?? 15); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Character Column Width ($W):</span>
                                    <span class="slider-val-badge" id="val_char_width"><?php echo (int)($settings['char_width'] ?? 54); ?> chars</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="char_width" id="char_width" min="0" max="80" step="1" value="<?php echo (int)($settings['char_width'] ?? 54); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Line Height:</span>
                                    <span class="slider-val-badge" id="val_line_height"><?php echo number_format((float)($settings['line_height'] ?? 1.36), 2); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="line_height" id="line_height" min="0.00" max="2.20" step="0.02" value="<?php echo (float)($settings['line_height'] ?? 1.36); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Vertical Padding (Padding Y):</span>
                                    <span class="slider-val-badge" id="val_receipt_padding_y"><?php echo (int)($settings['receipt_padding_y'] ?? 6); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="receipt_padding_y" id="receipt_padding_y" min="0" max="40" step="1" value="<?php echo (int)($settings['receipt_padding_y'] ?? 6); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: Store Header & Top Divider -->
                    <div class="tab-pane" id="tab-header">
                        <div class="control-section">
                            <div class="section-title">➖ Top Divider Line (Above Store Header)</div>
                            <p style="font-size: 11px; color: #64748b; margin-top: 0; margin-bottom: 8px;">
                                Choose the divider line style or type custom characters to replace the "====" line above the store name.
                            </p>

                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Divider Style:</label>
                                <select name="top_divider_type" id="top_divider_type" style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                    <option value="double" <?php echo ($settings['top_divider_type'] ?? 'double') === 'double' ? 'selected' : ''; ?>>================ (Double Lines)</option>
                                    <option value="dash" <?php echo ($settings['top_divider_type'] ?? 'double') === 'dash' ? 'selected' : ''; ?>>---------------- (Single Dashed)</option>
                                    <option value="asterisk" <?php echo ($settings['top_divider_type'] ?? 'double') === 'asterisk' ? 'selected' : ''; ?>>**************** (Asterisks)</option>
                                    <option value="none" <?php echo ($settings['top_divider_type'] ?? 'double') === 'none' ? 'selected' : ''; ?>>None (No Line / Blank)</option>
                                    <option value="custom" <?php echo ($settings['top_divider_type'] ?? 'double') === 'custom' ? 'selected' : ''; ?>>Custom Text / Divider</option>
                                </select>
                            </div>

                            <div id="top_divider_custom_wrap" style="margin-bottom: 12px; <?php echo ($settings['top_divider_type'] ?? 'double') === 'custom' ? '' : 'display: none;'; ?>">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Custom Top Divider Text:</label>
                                <input type="text" name="top_divider_custom" id="top_divider_custom" value="<?php echo htmlspecialchars($settings['top_divider_custom'] ?? ''); ?>" placeholder="e.g. ~~~~~~~~~~~~~~~~~~~~" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 13px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </div>
                        </div>

                        <div class="control-section" style="margin-top: 14px;">
                            <div class="section-title">🏢 Store Header Text Position Sliders</div>
                            <p style="font-size: 11px; color: #64748b; margin-top: 0; margin-bottom: 8px;">
                                Drag slider to position each text line (<strong>0</strong> = flush left / 0 spaces).
                            </p>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Store Name ("CHOICEMART by NCCC"):</span>
                                    <span class="slider-val-badge" id="val_header_name_offset"><?php echo (int)($settings['header_name_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_name_offset" id="header_name_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_name_offset'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Company Name ("LTS RETAIL..."):</span>
                                    <span class="slider-val-badge" id="val_header_company_offset"><?php echo (int)($settings['header_company_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_company_offset" id="header_company_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_company_offset'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>TIN Line ("TIN 006-171-689-014 VAT"):</span>
                                    <span class="slider-val-badge" id="val_header_tin_offset"><?php echo (int)($settings['header_tin_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_tin_offset" id="header_tin_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_tin_offset'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Address / Building Lines:</span>
                                    <span class="slider-val-badge" id="val_header_addr_offset"><?php echo (int)($settings['header_addr_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_addr_offset" id="header_addr_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_addr_offset'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Telephone Line:</span>
                                    <span class="slider-val-badge" id="val_header_tel_offset"><?php echo (int)($settings['header_tel_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_tel_offset" id="header_tel_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_tel_offset'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Account Number (Acc#) Line:</span>
                                    <span class="slider-val-badge" id="val_header_acc_offset"><?php echo (int)($settings['header_acc_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_acc_offset" id="header_acc_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_acc_offset'] ?? 0); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="control-section" style="margin-top: 14px;">
                            <div class="section-title">📟 Register Info Sliders (Serial, MIN, Permit)</div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Serial Number ("SERIAL#..."):</span>
                                    <span class="slider-val-badge" id="val_header_serial_offset"><?php echo (int)($settings['header_serial_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_serial_offset" id="header_serial_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_serial_offset'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>MIN Number:</span>
                                    <span class="slider-val-badge" id="val_header_min_offset"><?php echo (int)($settings['header_min_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_min_offset" id="header_min_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_min_offset'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Permit Number:</span>
                                    <span class="slider-val-badge" id="val_header_permit_offset"><?php echo (int)($settings['header_permit_offset'] ?? 0); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="header_permit_offset" id="header_permit_offset" min="0" max="40" step="1" value="<?php echo (int)($settings['header_permit_offset'] ?? 0); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: Item Columns Spacing -->
                    <div class="tab-pane" id="tab-items">
                        <div class="control-section">
                            <div class="section-title">📦 Line 1: SKU & Description Position</div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>SKU Column Width:</span>
                                    <span class="slider-val-badge" id="val_col_sku_width"><?php echo (int)($settings['col_sku_width'] ?? 16); ?> chars</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_sku_width" id="col_sku_width" min="0" max="35" step="1" value="<?php echo (int)($settings['col_sku_width'] ?? 16); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Space Gap before Description:</span>
                                    <span class="slider-val-badge" id="val_col_desc_gap"><?php echo (int)($settings['col_desc_gap'] ?? 3); ?> spaces</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_desc_gap" id="col_desc_gap" min="0" max="20" step="1" value="<?php echo (int)($settings['col_desc_gap'] ?? 3); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="control-section">
                            <div class="section-title">💰 Line 2: Price, Qty & Amount Position</div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Unit Price Column Position:</span>
                                    <span class="slider-val-badge" id="val_col_price_width"><?php echo (int)($settings['col_price_width'] ?? 16); ?> chars</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_price_width" id="col_price_width" min="0" max="35" step="1" value="<?php echo (int)($settings['col_price_width'] ?? 16); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Quantity Column Position:</span>
                                    <span class="slider-val-badge" id="val_col_qty_width"><?php echo (int)($settings['col_qty_width'] ?? 6); ?> chars</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_qty_width" id="col_qty_width" min="0" max="20" step="1" value="<?php echo (int)($settings['col_qty_width'] ?? 6); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Extended Amount ('V') Column Position:</span>
                                    <span class="slider-val-badge" id="val_col_amt_width"><?php echo (int)($settings['col_amt_width'] ?? 20); ?> chars</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_amt_width" id="col_amt_width" min="0" max="40" step="1" value="<?php echo (int)($settings['col_amt_width'] ?? 20); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: Totals & Tenders Alignment -->
                    <div class="tab-pane" id="tab-totals">
                        <div class="control-section">
                            <div class="section-title">💵 Subtotal, Tax & Total Alignment</div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>"Sub Total" Label Offset:</span>
                                    <span class="slider-val-badge" id="val_col_subtotal_label"><?php echo (int)($settings['col_subtotal_label'] ?? 28); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_subtotal_label" id="col_subtotal_label" min="0" max="50" step="1" value="<?php echo (int)($settings['col_subtotal_label'] ?? 28); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Subtotal Amount Spacing:</span>
                                    <span class="slider-val-badge" id="val_col_subtotal_amt"><?php echo (int)($settings['col_subtotal_amt'] ?? 20); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_subtotal_amt" id="col_subtotal_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_subtotal_amt'] ?? 20); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>"Tax" Label Offset:</span>
                                    <span class="slider-val-badge" id="val_col_tax_label"><?php echo (int)($settings['col_tax_label'] ?? 22); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_tax_label" id="col_tax_label" min="0" max="50" step="1" value="<?php echo (int)($settings['col_tax_label'] ?? 22); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Tax Amount Spacing:</span>
                                    <span class="slider-val-badge" id="val_col_tax_amt"><?php echo (int)($settings['col_tax_amt'] ?? 26); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_tax_amt" id="col_tax_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_tax_amt'] ?? 26); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>"Total" Label Offset:</span>
                                    <span class="slider-val-badge" id="val_col_total_label"><?php echo (int)($settings['col_total_label'] ?? 24); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_total_label" id="col_total_label" min="0" max="50" step="1" value="<?php echo (int)($settings['col_total_label'] ?? 24); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Total Amount Spacing:</span>
                                    <span class="slider-val-badge" id="val_col_total_amt"><?php echo (int)($settings['col_total_amt'] ?? 24); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_total_amt" id="col_total_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_total_amt'] ?? 24); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="control-section">
                            <div class="section-title">💳 Tenders & Change Alignment</div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>"Cash/Tender" Label Offset:</span>
                                    <span class="slider-val-badge" id="val_col_tender_label"><?php echo (int)($settings['col_tender_label'] ?? 23); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_tender_label" id="col_tender_label" min="0" max="50" step="1" value="<?php echo (int)($settings['col_tender_label'] ?? 23); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Cash Tender Amount Spacing:</span>
                                    <span class="slider-val-badge" id="val_col_tender_amt"><?php echo (int)($settings['col_tender_amt'] ?? 25); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_tender_amt" id="col_tender_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_tender_amt'] ?? 25); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>"CHANGE" Label Offset:</span>
                                    <span class="slider-val-badge" id="val_col_change_label"><?php echo (int)($settings['col_change_label'] ?? 25); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_change_label" id="col_change_label" min="0" max="50" step="1" value="<?php echo (int)($settings['col_change_label'] ?? 25); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>CHANGE Amount Spacing:</span>
                                    <span class="slider-val-badge" id="val_col_change_amt"><?php echo (int)($settings['col_change_amt'] ?? 18); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_change_amt" id="col_change_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_change_amt'] ?? 18); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: VAT & Taxes -->
                    <div class="tab-pane" id="tab-vat">
                        <!-- Top Divider of VAT Breakdown -->
                        <div class="control-section">
                            <div class="section-title">➖ Top Divider (Above VAT Breakdown)</div>
                            <p style="font-size: 11px; color: #64748b; margin-top: 0; margin-bottom: 8px;">
                                Divider line between Invoice line and VATable Sales.
                            </p>

                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Top Divider Style:</label>
                                <select name="vat_top_divider_type" id="vat_top_divider_type" style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                    <option value="dash" <?php echo ($settings['vat_top_divider_type'] ?? 'dash') === 'dash' ? 'selected' : ''; ?>>---------------- (Single Dashed)</option>
                                    <option value="double" <?php echo ($settings['vat_top_divider_type'] ?? 'dash') === 'double' ? 'selected' : ''; ?>>================ (Double Lines)</option>
                                    <option value="asterisk" <?php echo ($settings['vat_top_divider_type'] ?? 'dash') === 'asterisk' ? 'selected' : ''; ?>>**************** (Asterisks)</option>
                                    <option value="none" <?php echo ($settings['vat_top_divider_type'] ?? 'dash') === 'none' ? 'selected' : ''; ?>>None (No Line / Blank)</option>
                                    <option value="custom" <?php echo ($settings['vat_top_divider_type'] ?? 'dash') === 'custom' ? 'selected' : ''; ?>>Custom Text / Divider</option>
                                </select>
                            </div>

                            <div id="vat_top_divider_custom_wrap" style="margin-bottom: 12px; <?php echo ($settings['vat_top_divider_type'] ?? 'dash') === 'custom' ? '' : 'display: none;'; ?>">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Custom Top Divider Text:</label>
                                <input type="text" name="vat_top_divider_custom" id="vat_top_divider_custom" value="<?php echo htmlspecialchars($settings['vat_top_divider_custom'] ?? ''); ?>" placeholder="e.g. --------------------" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 13px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </div>
                        </div>

                        <!-- VAT Breakdown Column Alignment -->
                        <div class="control-section" style="margin-top: 14px;">
                            <div class="section-title">🏛️ BIR VAT & VAT Breakdown Spacing</div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>BIR VAT Rate/Amount Distance:</span>
                                    <span class="slider-val-badge" id="val_col_bir_vat_amt"><?php echo (int)($settings['col_bir_vat_amt'] ?? 20); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_bir_vat_amt" id="col_bir_vat_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_bir_vat_amt'] ?? 20); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>"VATable Sales" Amount Distance:</span>
                                    <span class="slider-val-badge" id="val_col_vatable_amt"><?php echo (int)($settings['col_vatable_amt'] ?? 22); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_vatable_amt" id="col_vatable_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_vatable_amt'] ?? 22); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>"VAT" Amount Distance:</span>
                                    <span class="slider-val-badge" id="val_col_vat_amt"><?php echo (int)($settings['col_vat_amt'] ?? 22); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_vat_amt" id="col_vat_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_vat_amt'] ?? 22); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>"Non-VATable Sales" Amount Distance:</span>
                                    <span class="slider-val-badge" id="val_col_nonvat_amt"><?php echo (int)($settings['col_nonvat_amt'] ?? 18); ?></span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="col_nonvat_amt" id="col_nonvat_amt" min="0" max="50" step="1" value="<?php echo (int)($settings['col_nonvat_amt'] ?? 18); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Divider of VAT Breakdown -->
                        <div class="control-section" style="margin-top: 14px;">
                            <div class="section-title">➖ Bottom Divider (Below VAT Breakdown / Above Footer)</div>
                            <p style="font-size: 11px; color: #64748b; margin-top: 0; margin-bottom: 8px;">
                                Divider line between Non-VATable Sales and the Footer text.
                            </p>

                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Bottom Divider Style:</label>
                                <select name="vat_bottom_divider_type" id="vat_bottom_divider_type" style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                    <option value="dash" <?php echo ($settings['vat_bottom_divider_type'] ?? 'dash') === 'dash' ? 'selected' : ''; ?>>---------------- (Single Dashed)</option>
                                    <option value="double" <?php echo ($settings['vat_bottom_divider_type'] ?? 'dash') === 'double' ? 'selected' : ''; ?>>================ (Double Lines)</option>
                                    <option value="asterisk" <?php echo ($settings['vat_bottom_divider_type'] ?? 'dash') === 'asterisk' ? 'selected' : ''; ?>>**************** (Asterisks)</option>
                                    <option value="none" <?php echo ($settings['vat_bottom_divider_type'] ?? 'dash') === 'none' ? 'selected' : ''; ?>>None (No Line / Blank)</option>
                                    <option value="custom" <?php echo ($settings['vat_bottom_divider_type'] ?? 'dash') === 'custom' ? 'selected' : ''; ?>>Custom Text / Divider</option>
                                </select>
                            </div>

                            <div id="vat_bottom_divider_custom_wrap" style="margin-bottom: 12px; <?php echo ($settings['vat_bottom_divider_type'] ?? 'dash') === 'custom' ? '' : 'display: none;'; ?>">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Custom Bottom Divider Text:</label>
                                <input type="text" name="vat_bottom_divider_custom" id="vat_bottom_divider_custom" value="<?php echo htmlspecialchars($settings['vat_bottom_divider_custom'] ?? ''); ?>" placeholder="e.g. --------------------" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 13px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </div>

                            <div class="slider-group" style="margin-top: 12px;">
                                <div class="slider-header">
                                    <span>Space Above Bottom Divider (Blank Lines):</span>
                                    <span class="slider-val-badge" id="val_vat_bottom_space_before"><?php echo (int)($settings['vat_bottom_space_before'] ?? 0); ?> lines</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="vat_bottom_space_before" id="vat_bottom_space_before" min="0" max="5" step="1" value="<?php echo (int)($settings['vat_bottom_space_before'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Space Below Divider / Above Footer (Blank Lines):</span>
                                    <span class="slider-val-badge" id="val_vat_bottom_space_after"><?php echo (int)($settings['vat_bottom_space_after'] ?? 1); ?> lines</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="vat_bottom_space_after" id="vat_bottom_space_after" min="0" max="5" step="1" value="<?php echo (int)($settings['vat_bottom_space_after'] ?? 1); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: Watermark -->
                    <div class="tab-pane" id="tab-wm">
                        <div class="control-section">
                            <div class="section-title">💧 Watermark Settings</div>
                            
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Watermark Text:</label>
                                <input type="text" name="watermark_text" id="watermark_text" value="<?php echo htmlspecialchars($settings['watermark_text'] ?? 'Electronic Journal Copy'); ?>" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Horizontal Position (Left %):</span>
                                    <span class="slider-val-badge" id="val_watermark_left"><?php echo (int)($settings['watermark_left'] ?? 50); ?>%</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="watermark_left" id="watermark_left" min="0" max="100" step="1" value="<?php echo (int)($settings['watermark_left'] ?? 50); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Vertical Position (Top %):</span>
                                    <span class="slider-val-badge" id="val_watermark_top"><?php echo (int)($settings['watermark_top'] ?? 75); ?>%</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="watermark_top" id="watermark_top" min="0" max="100" step="1" value="<?php echo (int)($settings['watermark_top'] ?? 75); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Watermark Font Size:</span>
                                    <span class="slider-val-badge" id="val_watermark_font_size"><?php echo (int)($settings['watermark_font_size'] ?? 60); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="watermark_font_size" id="watermark_font_size" min="0" max="120" step="1" value="<?php echo (int)($settings['watermark_font_size'] ?? 60); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Opacity / Transparency:</span>
                                    <span class="slider-val-badge" id="val_watermark_opacity"><?php echo round(((float)($settings['watermark_opacity'] ?? 0.16)) * 100); ?>%</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="watermark_opacity" id="watermark_opacity" min="0.00" max="1.00" step="0.01" value="<?php echo (float)($settings['watermark_opacity'] ?? 0.16); ?>">
                                </div>
                            </div>

                            <div class="slider-group">
                                <div class="slider-header">
                                    <span>Letter Spacing:</span>
                                    <span class="slider-val-badge" id="val_watermark_letter_spacing"><?php echo (float)($settings['watermark_letter_spacing'] ?? 3); ?> px</span>
                                </div>
                                <div class="slider-input-row">
                                    <input type="range" name="watermark_letter_spacing" id="watermark_letter_spacing" min="0" max="30" step="0.5" value="<?php echo (float)($settings['watermark_letter_spacing'] ?? 3); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 6: Custom Text & Notes -->
                    <div class="tab-pane" id="tab-custom">
                        <div class="control-section">
                            <div class="section-title">✏️ Header Notice / Message</div>
                            <p style="font-size: 11px; color: #64748b; margin-top: 0; margin-bottom: 8px;">
                                Displays below the store header and register serial/MIN/permit info.
                            </p>
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Header Message (Multi-line supported):</label>
                                <textarea name="custom_header_text" id="custom_header_text" rows="2" placeholder="e.g. WELCOME TO OUR STORE" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 13px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px;"><?php echo htmlspecialchars($settings['custom_header_text'] ?? ''); ?></textarea>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Header Alignment:</label>
                                <select name="custom_header_align" id="custom_header_align" style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                    <option value="center" <?php echo ($settings['custom_header_align'] ?? 'center') === 'center' ? 'selected' : ''; ?>>Center</option>
                                    <option value="left" <?php echo ($settings['custom_header_align'] ?? 'center') === 'left' ? 'selected' : ''; ?>>Left</option>
                                    <option value="right" <?php echo ($settings['custom_header_align'] ?? 'center') === 'right' ? 'selected' : ''; ?>>Right</option>
                                </select>
                            </div>
                        </div>

                        <div class="control-section" style="margin-top: 14px;">
                            <div class="section-title">📌 Mid-Receipt Notice / Promo</div>
                            <p style="font-size: 11px; color: #64748b; margin-top: 0; margin-bottom: 8px;">
                                Displays above the items list / below Member & Customer details.
                            </p>
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Mid-Receipt Message:</label>
                                <textarea name="custom_mid_text" id="custom_mid_text" rows="2" placeholder="e.g. *** PROMO POINTS EARNED: 25 ***" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 13px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px;"><?php echo htmlspecialchars($settings['custom_mid_text'] ?? ''); ?></textarea>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Mid Alignment:</label>
                                <select name="custom_mid_align" id="custom_mid_align" style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                    <option value="center" <?php echo ($settings['custom_mid_align'] ?? 'center') === 'center' ? 'selected' : ''; ?>>Center</option>
                                    <option value="left" <?php echo ($settings['custom_mid_align'] ?? 'center') === 'left' ? 'selected' : ''; ?>>Left</option>
                                    <option value="right" <?php echo ($settings['custom_mid_align'] ?? 'center') === 'right' ? 'selected' : ''; ?>>Right</option>
                                </select>
                            </div>
                        </div>

                        <div class="control-section" style="margin-top: 14px;">
                            <div class="section-title">💬 Footer Notes & Policy</div>
                            <p style="font-size: 11px; color: #64748b; margin-top: 0; margin-bottom: 8px;">
                                Displays at the bottom of the receipt below the VAT breakdown.
                            </p>
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Footer Note / Return Policy (Multi-line):</label>
                                <textarea name="custom_footer_text" id="custom_footer_text" rows="3" placeholder="THANK YOU FOR SHOPPING!&#10;Please keep receipt for returns." style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 13px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px;"><?php echo htmlspecialchars($settings['custom_footer_text'] ?? 'THANK YOU FOR SHOPPING!'); ?></textarea>
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Footer Alignment:</label>
                                <select name="custom_footer_align" id="custom_footer_align" style="width: 100%; padding: 6px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                    <option value="center" <?php echo ($settings['custom_footer_align'] ?? 'center') === 'center' ? 'selected' : ''; ?>>Center</option>
                                    <option value="left" <?php echo ($settings['custom_footer_align'] ?? 'center') === 'left' ? 'selected' : ''; ?>>Left</option>
                                    <option value="right" <?php echo ($settings['custom_footer_align'] ?? 'center') === 'right' ? 'selected' : ''; ?>>Right</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 8px;">
                        <button type="submit" class="btn" id="btn_save_bottom" style="width: 100%; padding: 12px; font-weight: 700; font-size: 14px;">💾 Save All Changes</button>
                        <button type="button" id="btn_reset_defaults" class="btn btn-secondary" style="width: 100%; padding: 8px; font-size: 12px;">🔄 Reset to Defaults</button>
                    </div>
                </form>
            </div>

            <!-- Right: Live WYSIWYG Sheet Preview Canvas with Direct Drag & Drop -->
            <div class="preview-wrapper">
                <div class="preview-toolbar">
                    <span id="preview_badge">📄 A4 Paper: 210mm × 297mm | Margins: 0.30" All Sides</span>
                    <button type="button" id="toggle_guide_btn">📐 Toggle Margins</button>
                </div>

                <div class="interaction-hint-bar">
                    <span>💡 Tip: Click & drag on the preview or use the tabs & sliders on the left to move any column/variable!</span>
                </div>

                <!-- Sheet -->
                <div id="liveSheet" class="sheet-preview size-<?php echo htmlspecialchars($settings['paper_size'] ?? 'a4'); ?>">
                    <div id="liveMarginGuide" class="sheet-margin-guide" data-label="0.30in Margin"></div>

                    <div class="preview-receipt-container">
                        <!-- Top Title (Interactive Drag Up/Down) -->
                        <div id="liveHeaderTitle" class="preview-header-title canvas-interactive" data-hint="↕ Click & Drag Up/Down" data-element="header-title" tabindex="0">*** Electronic Journal Copy ***</div>

                        <!-- Meta Bar (Interactive Drag Left/Right for Spacing) -->
                        <div id="liveMetaBar" class="preview-meta-bar canvas-interactive" data-hint="↔ Click & Drag Left/Right to adjust item gap" data-element="meta-bar" tabindex="0">
                            <div class="meta-item"><span class="label">Date:</span> <span class="val" id="meta_date"><?php echo htmlspecialchars($sampleHeaderDate); ?></span></div>
                            <div class="meta-item"><span class="label">Store:</span> <span class="val" id="meta_store"><?php echo htmlspecialchars($sampleStore); ?></span></div>
                            <div class="meta-item"><span class="label">Register:</span> <span class="val" id="meta_reg"><?php echo htmlspecialchars($sampleReg); ?></span></div>
                            <div class="meta-item"><span class="label">Transaction:</span> <span class="val" id="meta_trx"><?php echo htmlspecialchars($sampleTrxFormatted); ?></span></div>
                        </div>

                        <!-- Receipt Body with Watermark -->
                        <div class="preview-body-wrapper">
                            <!-- Watermark (Interactive Drag Left/Right & Up/Down) -->
                            <div id="liveWatermark" class="preview-watermark canvas-interactive" data-hint="✜ Click & Drag Watermark anywhere" data-element="watermark" tabindex="0"><?php echo htmlspecialchars($settings['watermark_text'] ?? 'Electronic Journal Copy'); ?></div>
                            
                            <!-- Receipt Content (Interactive Drag Left/Right for Indent) -->
                            <pre id="liveReceiptText" class="preview-content canvas-interactive" data-hint="↔ Click & Drag Left/Right to adjust text margin/indent" data-element="receipt-text" tabindex="0"></pre>
                        </div>
                    </div>

                    <!-- Bottom Pinned Title -->
                    <div id="liveFooterTitle" class="preview-footer-title">*** Electronic Journal Copy ***</div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Floating Nudge Toolbar -->
<div id="nudgeToolbar" class="nudge-toolbar">
    <span id="selectedElementName" style="color: #38bdf8;">Selected: Receipt Text</span>
    <button type="button" class="nudge-btn" id="nudgeLeft">◀ Move Left</button>
    <button type="button" class="nudge-btn" id="nudgeRight">Move Right ▶</button>
    <button type="button" class="nudge-btn" id="nudgeDeselect" style="background: transparent; border-color: #64748b; color: #94a3b8;">✕ Close</button>
</div>

<div id="saveToast" class="save-toast">✓ EJ Print Layout Settings saved successfully!</div>

<script>
// Tab Switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        const target = document.getElementById(btn.getAttribute('data-tab'));
        if (target) target.classList.add('active');
    });
});

// Sample transaction data for live preview rendering
const sampleData = {
    member: <?php echo json_encode($sampleMember); ?>,
    name: <?php echo json_encode($sampleName); ?>,
    items: <?php echo json_encode($sampleItems); ?>,
    subtotal: <?php echo (float)$sampleSubtotal; ?>,
    vatableSales: <?php echo (float)$sampleVatableSales; ?>,
    totalVat: <?php echo (float)$sampleTotalVat; ?>,
    totalNonVat: <?php echo (float)$sampleNonVat; ?>,
    totalAmount: <?php echo (float)$sampleTotal; ?>,
    tenderCash: <?php echo (float)$sampleTenderCash; ?>,
    change: <?php echo (float)$sampleChange; ?>,
    vatRate: <?php echo (float)$sampleVatRate; ?>,
    salesAssoc: <?php echo json_encode($sampleAssoc); ?>,
    assocId: <?php echo json_encode($sampleAssocId); ?>,
    trxSummary: <?php echo json_encode("Trx {$sampleRawTrx} S{$sampleStore} Reg{$sampleReg}/{$sampleTill} {$sampleTrxDateStr}{$sampleTimeFormatted}"); ?>,
    invoice: <?php echo json_encode($sampleInvoice); ?>,
    headerLines: <?php echo json_encode($sampleHeaderLines); ?>
};

// Recommended default settings
const defaultSettings = {
    paper_size: 'a4',
    sheet_margin: 0.30,
    top_title_margin_top: 25,
    bottom_title_margin_bottom: 16,
    top_title_font_size: 16,
    meta_gap: 65,
    meta_font_size: 14,
    char_width: 54,
    font_size: 15,
    line_height: 1.36,
    receipt_padding_x: 20,
    receipt_padding_y: 6,
    watermark_text: 'Electronic Journal Copy',
    watermark_font_size: 60,
    watermark_top: 75,
    watermark_left: 50,
    watermark_opacity: 0.16,
    watermark_letter_spacing: 3,

    col_sku_width: 16,
    col_desc_gap: 3,
    col_price_width: 16,
    col_qty_width: 6,
    col_amt_width: 20,

    col_subtotal_label: 28,
    col_subtotal_amt: 20,
    col_tax_label: 22,
    col_tax_amt: 26,
    col_total_label: 24,
    col_total_amt: 24,
    col_tender_label: 23,
    col_tender_amt: 25,
    col_change_label: 25,
    col_change_amt: 18,

    col_bir_vat_amt: 20,
    col_vatable_amt: 22,
    col_vat_amt: 22,
    col_nonvat_amt: 18,

    custom_header_text: '',
    custom_header_align: 'center',
    custom_mid_text: '',
    custom_mid_align: 'center',
    custom_footer_text: 'THANK YOU FOR SHOPPING!',
    custom_footer_align: 'center',

    top_divider_type: 'double',
    top_divider_custom: '',
    header_name_offset: 0,
    header_company_offset: 0,
    header_tin_offset: 0,
    header_addr_offset: 0,
    header_tel_offset: 0,
    header_acc_offset: 0,
    header_serial_offset: 0,
    header_min_offset: 0,
    header_permit_offset: 0,

    vat_top_divider_type: 'dash',
    vat_top_divider_custom: '',
    vat_bottom_divider_type: 'dash',
    vat_bottom_divider_custom: '',
    vat_bottom_space_before: 0,
    vat_bottom_space_after: 1
};

// DOM elements mapping
const el = {
    form: document.getElementById('ejSettingsForm'),
    paperSize: document.getElementById('paper_size'),
    sheetMargin: document.getElementById('sheet_margin'),
    topTitleMarginTop: document.getElementById('top_title_margin_top'),
    bottomTitleMarginBottom: document.getElementById('bottom_title_margin_bottom'),
    topTitleFontSize: document.getElementById('top_title_font_size'),
    metaGap: document.getElementById('meta_gap'),
    metaFontSize: document.getElementById('meta_font_size'),
    charWidth: document.getElementById('char_width'),
    fontSize: document.getElementById('font_size'),
    lineHeight: document.getElementById('line_height'),
    receiptPaddingX: document.getElementById('receipt_padding_x'),
    receiptPaddingY: document.getElementById('receipt_padding_y'),
    watermarkText: document.getElementById('watermark_text'),
    watermarkFontSize: document.getElementById('watermark_font_size'),
    watermarkTop: document.getElementById('watermark_top'),
    watermarkLeft: document.getElementById('watermark_left'),
    watermarkOpacity: document.getElementById('watermark_opacity'),
    watermarkLetterSpacing: document.getElementById('watermark_letter_spacing'),

    colSkuWidth: document.getElementById('col_sku_width'),
    colDescGap: document.getElementById('col_desc_gap'),
    colPriceWidth: document.getElementById('col_price_width'),
    colQtyWidth: document.getElementById('col_qty_width'),
    colAmtWidth: document.getElementById('col_amt_width'),

    colSubtotalLabel: document.getElementById('col_subtotal_label'),
    colSubtotalAmt: document.getElementById('col_subtotal_amt'),
    colTaxLabel: document.getElementById('col_tax_label'),
    colTaxAmt: document.getElementById('col_tax_amt'),
    colTotalLabel: document.getElementById('col_total_label'),
    colTotalAmt: document.getElementById('col_total_amt'),
    colTenderLabel: document.getElementById('col_tender_label'),
    colTenderAmt: document.getElementById('col_tender_amt'),
    colChangeLabel: document.getElementById('col_change_label'),
    colChangeAmt: document.getElementById('col_change_amt'),

    colBirVatAmt: document.getElementById('col_bir_vat_amt'),
    colVatableAmt: document.getElementById('col_vatable_amt'),
    colVatAmt: document.getElementById('col_vat_amt'),
    colNonVatAmt: document.getElementById('col_nonvat_amt'),

    customHeaderText: document.getElementById('custom_header_text'),
    customHeaderAlign: document.getElementById('custom_header_align'),
    customMidText: document.getElementById('custom_mid_text'),
    customMidAlign: document.getElementById('custom_mid_align'),
    customFooterText: document.getElementById('custom_footer_text'),
    customFooterAlign: document.getElementById('custom_footer_align'),

    // Top Divider & Header Sliders
    topDividerType: document.getElementById('top_divider_type'),
    topDividerCustom: document.getElementById('top_divider_custom'),
    topDividerCustomWrap: document.getElementById('top_divider_custom_wrap'),
    headerNameOffset: document.getElementById('header_name_offset'),
    headerCompanyOffset: document.getElementById('header_company_offset'),
    headerTinOffset: document.getElementById('header_tin_offset'),
    headerAddrOffset: document.getElementById('header_addr_offset'),
    headerTelOffset: document.getElementById('header_tel_offset'),
    headerAccOffset: document.getElementById('header_acc_offset'),
    headerSerialOffset: document.getElementById('header_serial_offset'),
    headerMinOffset: document.getElementById('header_min_offset'),
    headerPermitOffset: document.getElementById('header_permit_offset'),

    // VAT Breakdown Dividers & Spacing
    vatTopDividerType: document.getElementById('vat_top_divider_type'),
    vatTopDividerCustom: document.getElementById('vat_top_divider_custom'),
    vatTopDividerCustomWrap: document.getElementById('vat_top_divider_custom_wrap'),
    vatBottomDividerType: document.getElementById('vat_bottom_divider_type'),
    vatBottomDividerCustom: document.getElementById('vat_bottom_divider_custom'),
    vatBottomDividerCustomWrap: document.getElementById('vat_bottom_divider_custom_wrap'),
    vatBottomSpaceBefore: document.getElementById('vat_bottom_space_before'),
    vatBottomSpaceAfter: document.getElementById('vat_bottom_space_after'),

    // Live preview elements
    liveSheet: document.getElementById('liveSheet'),
    liveMarginGuide: document.getElementById('liveMarginGuide'),
    liveHeaderTitle: document.getElementById('liveHeaderTitle'),
    liveFooterTitle: document.getElementById('liveFooterTitle'),
    liveMetaBar: document.getElementById('liveMetaBar'),
    liveWatermark: document.getElementById('liveWatermark'),
    liveReceiptText: document.getElementById('liveReceiptText'),
    previewBadge: document.getElementById('preview_badge'),
    saveToast: document.getElementById('saveToast'),
    nudgeToolbar: document.getElementById('nudgeToolbar'),
    selectedElementName: document.getElementById('selectedElementName')
};

function padCenter(str, width) {
    if (str.length >= width) return str.substr(0, width);
    let leftPad = Math.floor((width - str.length) / 2);
    let rightPad = width - str.length - leftPad;
    return ' '.repeat(leftPad) + str + ' '.repeat(rightPad);
}

function getDividerLine(type, custom, width, defaultStyle = 'dash') {
    if (type === 'none') return '';
    if (type === 'dash') return '-'.repeat(width);
    if (type === 'double') return '='.repeat(width);
    if (type === 'asterisk') return '*'.repeat(width);
    if (type === 'custom') return custom || (defaultStyle === 'double' ? '='.repeat(width) : '-'.repeat(width));
    return defaultStyle === 'double' ? '='.repeat(width) : '-'.repeat(width);
}

function getTopDivider(type, custom, width) {
    return getDividerLine(type, custom, width, 'double');
}

function formatHeaderLine(text, offset, width) {
    text = String(text || '').trim();
    if (!text) return '';
    let off = parseInt(offset);
    if (isNaN(off)) off = 0;
    return ' '.repeat(Math.max(0, off)) + text;
}

function formatCustomLine(text, align, width) {
    text = String(text || '');
    if (text.length >= width) return text.substr(0, width);
    if (align === 'left') {
        return text.padEnd(width, ' ');
    } else if (align === 'right') {
        return text.padStart(width, ' ');
    } else {
        return padCenter(text, width);
    }
}

function appendCustomLines(lines, rawText, align, width) {
    if (!rawText || !rawText.trim()) return;
    let lArr = rawText.trim().split(/\r\n|\r|\n/);
    lArr.forEach(line => {
        if (line.trim() !== '') {
            lines.push(formatCustomLine(line.trim(), align, width));
        }
    });
}

// Generate Monospace Receipt Text dynamically based on all granular column positions
function generateMonospaceText(W) {
    const skuWidth    = el.colSkuWidth    ? (parseInt(el.colSkuWidth.value)    ?? 16)  : 16;
    const descGap     = el.colDescGap     ? (parseInt(el.colDescGap.value)     ?? 3)   : 3;
    const priceWidth  = el.colPriceWidth  ? (parseInt(el.colPriceWidth.value)  ?? 16)  : 16;
    const qtyWidth    = el.colQtyWidth    ? (parseInt(el.colQtyWidth.value)    ?? 6)   : 6;
    const amtWidth    = el.colAmtWidth    ? (parseInt(el.colAmtWidth.value)    ?? 20)  : 20;

    const subtotalLabel = el.colSubtotalLabel ? (parseInt(el.colSubtotalLabel.value) ?? 28) : 28;
    const subtotalAmt   = el.colSubtotalAmt   ? (parseInt(el.colSubtotalAmt.value)   ?? 20) : 20;
    const totalLabel    = el.colTotalLabel    ? (parseInt(el.colTotalLabel.value)    ?? 24) : 24;
    const totalAmt      = el.colTotalAmt      ? (parseInt(el.colTotalAmt.value)      ?? 24) : 24;
    const tenderLabel   = el.colTenderLabel   ? (parseInt(el.colTenderLabel.value)   ?? 23) : 23;
    const tenderAmt     = el.colTenderAmt     ? (parseInt(el.colTenderAmt.value)     ?? 25) : 25;
    const changeLabel   = el.colChangeLabel   ? (parseInt(el.colChangeLabel.value)   ?? 25) : 25;
    const changeAmt     = el.colChangeAmt     ? (parseInt(el.colChangeAmt.value)     ?? 18) : 18;

    const birVatAmt  = el.colBirVatAmt  ? (parseInt(el.colBirVatAmt.value)  ?? 20) : 20;
    const vatableAmt = el.colVatableAmt ? (parseInt(el.colVatableAmt.value) ?? 22) : 22;
    const vatAmt     = el.colVatAmt     ? (parseInt(el.colVatAmt.value)     ?? 22) : 22;
    const nonVatAmt  = el.colNonVatAmt  ? (parseInt(el.colNonVatAmt.value)  ?? 18) : 18;

    const divDouble = '='.repeat(W);
    const divDash   = '-'.repeat(W);
    let lines = [];

    // Top Header Divider (above store header)
    const topDivType = el.topDividerType ? el.topDividerType.value : 'double';
    const topDivCustom = el.topDividerCustom ? el.topDividerCustom.value : '';
    const topDivider = getTopDivider(topDivType, topDivCustom, W);
    if (topDivider) {
        lines.push(topDivider);
    }

    if (sampleData.headerLines && sampleData.headerLines.length > 0) {
        const nameOffset = el.headerNameOffset ? parseInt(el.headerNameOffset.value || 0) : 0;
        const compOffset = el.headerCompanyOffset ? parseInt(el.headerCompanyOffset.value || 0) : 0;
        const tinOffset  = el.headerTinOffset ? parseInt(el.headerTinOffset.value || 0) : 0;
        const addrOffset = el.headerAddrOffset ? parseInt(el.headerAddrOffset.value || 0) : 0;
        const telOffset  = el.headerTelOffset ? parseInt(el.headerTelOffset.value || 0) : 0;
        const accOffset  = el.headerAccOffset ? parseInt(el.headerAccOffset.value || 0) : 0;
        const serOffset  = el.headerSerialOffset ? parseInt(el.headerSerialOffset.value || 0) : 0;
        const minOffset  = el.headerMinOffset ? parseInt(el.headerMinOffset.value || 0) : 0;
        const perOffset  = el.headerPermitOffset ? parseInt(el.headerPermitOffset.value || 0) : 0;

        sampleData.headerLines.forEach((hl, idx) => {
            let trimmed = String(hl || '').trim();
            if (!trimmed) return;

            let offset = 0;
            if (trimmed.startsWith('SERIAL#')) {
                offset = serOffset;
            } else if (/^\d{15,25}$/.test(trimmed)) {
                offset = minOffset;
            } else if (/^[A-Z0-9]{15,30}$/.test(trimmed) && trimmed.length > 18) {
                offset = perOffset;
            } else if (idx === 0) {
                offset = nameOffset;
            } else if (idx === 1) {
                offset = compOffset;
            } else if (trimmed.includes('TIN')) {
                offset = tinOffset;
            } else if (trimmed.includes('Tel')) {
                offset = telOffset;
            } else if (trimmed.includes('Acc#')) {
                offset = accOffset;
            } else {
                offset = addrOffset;
            }
            lines.push(formatHeaderLine(trimmed, offset, W));
        });
    }

    const headerCustom = el.customHeaderText ? el.customHeaderText.value : '';
    const headerAlign  = el.customHeaderAlign ? el.customHeaderAlign.value : 'center';
    appendCustomLines(lines, headerCustom, headerAlign, W);

    lines.push('Member #:      ' + (sampleData.member ? sampleData.member : '              '));
    lines.push('Name     : ' + (sampleData.name ? sampleData.name : ''));
    lines.push(divDouble);

    const midCustom = el.customMidText ? el.customMidText.value : '';
    const midAlign  = el.customMidAlign ? el.customMidAlign.value : 'center';
    appendCustomLines(lines, midCustom, midAlign, W);

    // Line items (2-line format with granular column positions)
    sampleData.items.forEach(itm => {
        let rawSku = String(itm.sku || '').trim().padStart(9, '0');
        let skuCol = rawSku.padEnd(skuWidth, ' ');
        let desc = itm.description || '';
        lines.push('  ' + skuCol + ' '.repeat(descGap) + desc);

        let qty = Number(itm.quantity || 1);
        let amt = Number(itm.amount || 0);
        let uPrice = Number(itm.unit_price || (qty > 0 ? amt / qty : amt));
        let uPriceStr = 'P' + uPrice.toFixed(2);
        let amtStr    = 'P' + amt.toFixed(2) + 'V';

        let line2 = uPriceStr.padStart(priceWidth, ' ') + 
                    qty.toFixed(0).padStart(qtyWidth, ' ') + 
                    amtStr.padStart(amtWidth, ' ');
        lines.push(line2);
    });

    const taxLabel = el.colTaxLabel ? (parseInt(el.colTaxLabel.value) ?? 22) : 22;
    const taxAmt   = el.colTaxAmt   ? (parseInt(el.colTaxAmt.value)   ?? 26) : 26;

    // Helper: insert exactly N spaces before amount (works at 0 too)
    const sp = (n) => ' '.repeat(Math.max(0, n));

    lines.push('Sub Total'.padStart(subtotalLabel, ' ') + sp(subtotalAmt) + 'P' + sampleData.subtotal.toFixed(2));
    lines.push('Tax'.padStart(taxLabel, ' ')            + sp(taxAmt)      + 'P0.00');
    lines.push('');
    lines.push('Total'.padStart(totalLabel, ' ')        + sp(totalAmt)    + 'P' + sampleData.totalAmount.toFixed(2));
    lines.push('Cash'.padStart(tenderLabel, ' ')        + sp(tenderAmt)   + 'P' + sampleData.tenderCash.toFixed(2));

    let changeStr = 'P-' + Math.abs(sampleData.change).toFixed(2);
    lines.push('CHANGE'.padStart(changeLabel, ' ')      + sp(changeAmt)   + changeStr);

    let vatRateStr = sampleData.vatRate.toFixed(4);
    let vatAmtStr  = 'P' + sampleData.totalVat.toFixed(2);
    lines.push('     BIR    VAT   @ ' + vatRateStr      + sp(birVatAmt)   + vatAmtStr);

    lines.push('Sales Associate:' + sampleData.salesAssoc);
    lines.push('Associate Id:    ' + sampleData.assocId);
    lines.push(sampleData.trxSummary);
    lines.push('Invoice#:' + sampleData.invoice);

    // VAT Breakdown Top Divider
    const vatTopDivType = el.vatTopDividerType ? el.vatTopDividerType.value : 'dash';
    const vatTopDivCustom = el.vatTopDividerCustom ? el.vatTopDividerCustom.value : '';
    const vatTopDivider = getDividerLine(vatTopDivType, vatTopDivCustom, W, 'dash');
    if (vatTopDivider) {
        lines.push(vatTopDivider);
    }

    lines.push('VATable Sales:'      + sp(vatableAmt) + 'P' + sampleData.vatableSales.toFixed(2));
    lines.push('VAT:'                + sp(vatAmt)     + 'P' + sampleData.totalVat.toFixed(2));
    lines.push('Non-VATable Sales:' + sp(nonVatAmt)   + 'P' + sampleData.totalNonVat.toFixed(2));

    // VAT Breakdown Bottom Divider & Footer Custom Text
    const footerCustom = el.customFooterText ? el.customFooterText.value : 'THANK YOU FOR SHOPPING!';
    const footerAlign  = el.customFooterAlign ? el.customFooterAlign.value : 'center';
    const vatBotDivType = el.vatBottomDividerType ? el.vatBottomDividerType.value : 'dash';
    const vatBotDivCustom = el.vatBottomDividerCustom ? el.vatBottomDividerCustom.value : '';
    const vatBotDivider = getDividerLine(vatBotDivType, vatBotDivCustom, W, 'dash');
    const vatBotSpaceBefore = parseInt(el.vatBottomSpaceBefore?.value || 0);
    const vatBotSpaceAfter  = parseInt(el.vatBottomSpaceAfter?.value  || 1);

    for (let i = 0; i < vatBotSpaceBefore; i++) { lines.push(''); }
    if (vatBotDivider) { lines.push(vatBotDivider); }
    for (let i = 0; i < vatBotSpaceAfter; i++) { lines.push(''); }

    if (footerCustom && footerCustom.trim() !== '') {
        appendCustomLines(lines, footerCustom, footerAlign, W);
    }

    return lines.join('\n');
}

// Live update function triggered on every slider movement or direct drag
function updateLivePreview() {
    const paper = el.paperSize ? el.paperSize.value : 'a4';
    const margin = parseFloat(el.sheetMargin?.value || 0.30);
    const topMarginTop = parseInt(el.topTitleMarginTop?.value || 25);
    const bottomMarginBottom = parseInt(el.bottomTitleMarginBottom?.value || 16);
    const topFontSize = parseInt(el.topTitleFontSize?.value || 16);
    const metaGap = parseInt(el.metaGap?.value || 65);
    const metaFontSize = parseInt(el.metaFontSize?.value || 14);
    const charW = parseInt(el.charWidth?.value || 54);
    const fontSize = parseFloat(el.fontSize?.value || 15);
    const lineHeight = parseFloat(el.lineHeight?.value || 1.36);
    const padX = parseInt(el.receiptPaddingX?.value || 20);
    const padY = parseInt(el.receiptPaddingY?.value || 6);
    const wmText = el.watermarkText?.value || 'Electronic Journal Copy';
    const wmSize = parseInt(el.watermarkFontSize?.value || 60);
    const wmTop = parseInt(el.watermarkTop?.value || 75);
    const wmLeft = parseInt(el.watermarkLeft?.value || 50);
    const wmOpacity = parseFloat(el.watermarkOpacity?.value || 0.16);
    const wmSpacing = parseFloat(el.watermarkLetterSpacing?.value || 3);

    // Update badges
    if (document.getElementById('val_sheet_margin')) document.getElementById('val_sheet_margin').textContent = margin.toFixed(2) + ' in';
    if (document.getElementById('val_top_title_margin_top')) document.getElementById('val_top_title_margin_top').textContent = topMarginTop + ' px';
    if (document.getElementById('val_bottom_title_margin_bottom')) document.getElementById('val_bottom_title_margin_bottom').textContent = bottomMarginBottom + ' px';
    if (document.getElementById('val_top_title_font_size')) document.getElementById('val_top_title_font_size').textContent = topFontSize + ' px';
    if (document.getElementById('val_meta_gap')) document.getElementById('val_meta_gap').textContent = metaGap + ' px';
    if (document.getElementById('val_meta_font_size')) document.getElementById('val_meta_font_size').textContent = metaFontSize + ' px';
    if (document.getElementById('val_char_width')) document.getElementById('val_char_width').textContent = charW + ' chars';
    if (document.getElementById('val_font_size')) document.getElementById('val_font_size').textContent = fontSize + ' px';
    if (document.getElementById('val_line_height')) document.getElementById('val_line_height').textContent = lineHeight.toFixed(2);
    if (document.getElementById('val_receipt_padding_x')) document.getElementById('val_receipt_padding_x').textContent = padX + ' px';
    if (document.getElementById('val_receipt_padding_y')) document.getElementById('val_receipt_padding_y').textContent = padY + ' px';
    if (document.getElementById('val_watermark_font_size')) document.getElementById('val_watermark_font_size').textContent = wmSize + ' px';
    if (document.getElementById('val_watermark_top')) document.getElementById('val_watermark_top').textContent = wmTop + '%';
    if (document.getElementById('val_watermark_left')) document.getElementById('val_watermark_left').textContent = wmLeft + '%';
    if (document.getElementById('val_watermark_opacity')) document.getElementById('val_watermark_opacity').textContent = Math.round(wmOpacity * 100) + '%';
    if (document.getElementById('val_watermark_letter_spacing')) document.getElementById('val_watermark_letter_spacing').textContent = wmSpacing + ' px';

    if (document.getElementById('val_col_sku_width') && el.colSkuWidth) document.getElementById('val_col_sku_width').textContent = el.colSkuWidth.value + ' chars';
    if (document.getElementById('val_col_desc_gap') && el.colDescGap) document.getElementById('val_col_desc_gap').textContent = el.colDescGap.value + ' spaces';
    if (document.getElementById('val_col_price_width') && el.colPriceWidth) document.getElementById('val_col_price_width').textContent = el.colPriceWidth.value + ' chars';
    if (document.getElementById('val_col_qty_width') && el.colQtyWidth) document.getElementById('val_col_qty_width').textContent = el.colQtyWidth.value + ' chars';
    if (document.getElementById('val_col_amt_width') && el.colAmtWidth) document.getElementById('val_col_amt_width').textContent = el.colAmtWidth.value + ' chars';

    if (document.getElementById('val_col_subtotal_label') && el.colSubtotalLabel) document.getElementById('val_col_subtotal_label').textContent = el.colSubtotalLabel.value;
    if (document.getElementById('val_col_subtotal_amt') && el.colSubtotalAmt) document.getElementById('val_col_subtotal_amt').textContent = el.colSubtotalAmt.value;
    if (document.getElementById('val_col_tax_label') && el.colTaxLabel) document.getElementById('val_col_tax_label').textContent = el.colTaxLabel.value;
    if (document.getElementById('val_col_tax_amt') && el.colTaxAmt) document.getElementById('val_col_tax_amt').textContent = el.colTaxAmt.value;
    if (document.getElementById('val_col_total_label') && el.colTotalLabel) document.getElementById('val_col_total_label').textContent = el.colTotalLabel.value;
    if (document.getElementById('val_col_total_amt') && el.colTotalAmt) document.getElementById('val_col_total_amt').textContent = el.colTotalAmt.value;
    if (document.getElementById('val_col_tender_label') && el.colTenderLabel) document.getElementById('val_col_tender_label').textContent = el.colTenderLabel.value;
    if (document.getElementById('val_col_tender_amt') && el.colTenderAmt) document.getElementById('val_col_tender_amt').textContent = el.colTenderAmt.value;
    if (document.getElementById('val_col_change_label') && el.colChangeLabel) document.getElementById('val_col_change_label').textContent = el.colChangeLabel.value;
    if (document.getElementById('val_col_change_amt') && el.colChangeAmt) document.getElementById('val_col_change_amt').textContent = el.colChangeAmt.value;

    if (document.getElementById('val_col_bir_vat_amt') && el.colBirVatAmt) document.getElementById('val_col_bir_vat_amt').textContent = el.colBirVatAmt.value;
    if (document.getElementById('val_col_vatable_amt') && el.colVatableAmt) document.getElementById('val_col_vatable_amt').textContent = el.colVatableAmt.value;
    if (document.getElementById('val_col_vat_amt') && el.colVatAmt) document.getElementById('val_col_vat_amt').textContent = el.colVatAmt.value;
    if (document.getElementById('val_col_nonvat_amt') && el.colNonVatAmt) document.getElementById('val_col_nonvat_amt').textContent = el.colNonVatAmt.value;

    // Top divider custom wrap visibility
    if (el.topDividerType && el.topDividerCustomWrap) {
        el.topDividerCustomWrap.style.display = el.topDividerType.value === 'custom' ? 'block' : 'none';
    }

    // VAT dividers custom wrap visibility
    if (el.vatTopDividerType && el.vatTopDividerCustomWrap) {
        el.vatTopDividerCustomWrap.style.display = el.vatTopDividerType.value === 'custom' ? 'block' : 'none';
    }
    if (el.vatBottomDividerType && el.vatBottomDividerCustomWrap) {
        el.vatBottomDividerCustomWrap.style.display = el.vatBottomDividerType.value === 'custom' ? 'block' : 'none';
    }

    // VAT bottom spacing badges
    if (document.getElementById('val_vat_bottom_space_before') && el.vatBottomSpaceBefore)
        document.getElementById('val_vat_bottom_space_before').textContent = (parseInt(el.vatBottomSpaceBefore.value) || 0) + ' lines';
    if (document.getElementById('val_vat_bottom_space_after') && el.vatBottomSpaceAfter)
        document.getElementById('val_vat_bottom_space_after').textContent = (parseInt(el.vatBottomSpaceAfter.value) || 0) + ' lines';

    // Update Header Line Offset Badges
    const formatOffsetBadge = (val) => (parseInt(val) || 0) + ' spaces';
    if (document.getElementById('val_header_name_offset') && el.headerNameOffset) document.getElementById('val_header_name_offset').textContent = formatOffsetBadge(el.headerNameOffset.value);
    if (document.getElementById('val_header_company_offset') && el.headerCompanyOffset) document.getElementById('val_header_company_offset').textContent = formatOffsetBadge(el.headerCompanyOffset.value);
    if (document.getElementById('val_header_tin_offset') && el.headerTinOffset) document.getElementById('val_header_tin_offset').textContent = formatOffsetBadge(el.headerTinOffset.value);
    if (document.getElementById('val_header_addr_offset') && el.headerAddrOffset) document.getElementById('val_header_addr_offset').textContent = formatOffsetBadge(el.headerAddrOffset.value);
    if (document.getElementById('val_header_tel_offset') && el.headerTelOffset) document.getElementById('val_header_tel_offset').textContent = formatOffsetBadge(el.headerTelOffset.value);
    if (document.getElementById('val_header_acc_offset') && el.headerAccOffset) document.getElementById('val_header_acc_offset').textContent = formatOffsetBadge(el.headerAccOffset.value);
    if (document.getElementById('val_header_serial_offset') && el.headerSerialOffset) document.getElementById('val_header_serial_offset').textContent = formatOffsetBadge(el.headerSerialOffset.value);
    if (document.getElementById('val_header_min_offset') && el.headerMinOffset) document.getElementById('val_header_min_offset').textContent = formatOffsetBadge(el.headerMinOffset.value);
    if (document.getElementById('val_header_permit_offset') && el.headerPermitOffset) document.getElementById('val_header_permit_offset').textContent = formatOffsetBadge(el.headerPermitOffset.value);

    // Update Paper size class
    if (el.liveSheet) el.liveSheet.className = 'sheet-preview size-' + paper;
    const paperLabel = paper === 'a4' ? 'A4 Paper: 210mm × 297mm' : 'US Letter: 8.5" × 11.0"';
    if (el.previewBadge) el.previewBadge.textContent = '📄 ' + paperLabel + ' | Margins: ' + margin.toFixed(2) + '" All Sides';

    // Update sheet padding & margin guide
    if (el.liveSheet) el.liveSheet.style.padding = margin + 'in';
    if (el.liveMarginGuide) {
        el.liveMarginGuide.style.top = margin + 'in';
        el.liveMarginGuide.style.left = margin + 'in';
        el.liveMarginGuide.style.right = margin + 'in';
        el.liveMarginGuide.style.bottom = margin + 'in';
        el.liveMarginGuide.setAttribute('data-label', margin.toFixed(2) + 'in Margin');
    }

    // Update Top & Bottom Titles
    if (el.liveHeaderTitle) {
        el.liveHeaderTitle.style.marginTop = topMarginTop + 'px';
        el.liveHeaderTitle.style.fontSize = topFontSize + 'px';
    }
    if (el.liveFooterTitle) {
        el.liveFooterTitle.style.fontSize = topFontSize + 'px';
        el.liveFooterTitle.style.marginBottom = bottomMarginBottom + 'px';
        el.liveFooterTitle.style.paddingTop = '0px';
    }

    // Update Meta Bar
    if (el.liveMetaBar) {
        el.liveMetaBar.style.gap = metaGap + 'px';
        el.liveMetaBar.style.fontSize = metaFontSize + 'px';
    }

    // Update Watermark
    if (el.liveWatermark) {
        el.liveWatermark.textContent = wmText;
        el.liveWatermark.style.fontSize = wmSize + 'px';
        el.liveWatermark.style.top = wmTop + '%';
        el.liveWatermark.style.left = wmLeft + '%';
        el.liveWatermark.style.color = `rgba(6, 182, 212, ${wmOpacity})`;
        el.liveWatermark.style.letterSpacing = wmSpacing + 'px';
    }

    // Update Monospace receipt
    if (el.liveReceiptText) {
        el.liveReceiptText.style.fontSize = fontSize + 'px';
        el.liveReceiptText.style.lineHeight = lineHeight;
        el.liveReceiptText.style.padding = `${padY}px ${padX}px`;
        el.liveReceiptText.textContent = generateMonospaceText(charW);
    }
}

// Paper toggle button listeners
document.querySelectorAll('.paper-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.paper-toggle-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (el.paperSize) el.paperSize.value = btn.getAttribute('data-paper');
        updateLivePreview();
    });
});

// Attach input listener to all range and text inputs
const allInputs = [
    el.sheetMargin, el.topTitleMarginTop, el.bottomTitleMarginBottom, el.topTitleFontSize,
    el.metaGap, el.metaFontSize, el.charWidth, el.fontSize,
    el.lineHeight, el.receiptPaddingX, el.receiptPaddingY,
    el.watermarkText, el.watermarkFontSize, el.watermarkTop,
    el.watermarkLeft, el.watermarkOpacity, el.watermarkLetterSpacing,
    el.colSkuWidth, el.colDescGap, el.colPriceWidth, el.colQtyWidth, el.colAmtWidth,
    el.colSubtotalLabel, el.colSubtotalAmt, el.colTaxLabel, el.colTaxAmt,
    el.colTotalLabel, el.colTotalAmt, el.colTenderLabel, el.colTenderAmt,
    el.colChangeLabel, el.colChangeAmt,
    el.colBirVatAmt, el.colVatableAmt, el.colVatAmt, el.colNonVatAmt,
    el.customHeaderText, el.customHeaderAlign,
    el.customMidText, el.customMidAlign,
    el.customFooterText, el.customFooterAlign,
    el.topDividerType, el.topDividerCustom,
    el.headerNameOffset, el.headerCompanyOffset, el.headerTinOffset,
    el.headerAddrOffset, el.headerTelOffset, el.headerAccOffset,
    el.headerSerialOffset, el.headerMinOffset, el.headerPermitOffset,
    el.vatTopDividerType, el.vatTopDividerCustom,
    el.vatBottomDividerType, el.vatBottomDividerCustom,
    el.vatBottomSpaceBefore, el.vatBottomSpaceAfter
];
allInputs.forEach(input => {
    if (input) {
        input.addEventListener('input', updateLivePreview);
        input.addEventListener('change', updateLivePreview);
    }
});

// Toggle Margin Guide button
const toggleGuideBtn = document.getElementById('toggle_guide_btn');
if (toggleGuideBtn && el.liveMarginGuide) {
    toggleGuideBtn.addEventListener('click', () => {
        const isHidden = el.liveMarginGuide.style.display === 'none';
        el.liveMarginGuide.style.display = isHidden ? 'block' : 'none';
    });
}

// Reset to Defaults
const resetDefaultsBtn = document.getElementById('btn_reset_defaults');
if (resetDefaultsBtn) {
    resetDefaultsBtn.addEventListener('click', () => {
        if (!confirm('Reset all layout settings to recommended defaults?')) return;
        
        if (el.paperSize) el.paperSize.value = defaultSettings.paper_size;
        document.querySelectorAll('.paper-toggle-btn').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-paper') === defaultSettings.paper_size);
        });

        if (el.sheetMargin) el.sheetMargin.value = defaultSettings.sheet_margin;
        if (el.topTitleMarginTop) el.topTitleMarginTop.value = defaultSettings.top_title_margin_top;
        if (el.bottomTitleMarginBottom) el.bottomTitleMarginBottom.value = defaultSettings.bottom_title_margin_bottom;
        if (el.topTitleFontSize) el.topTitleFontSize.value = defaultSettings.top_title_font_size;
        if (el.metaGap) el.metaGap.value = defaultSettings.meta_gap;
        if (el.metaFontSize) el.metaFontSize.value = defaultSettings.meta_font_size;
        if (el.charWidth) el.charWidth.value = defaultSettings.char_width;
        if (el.fontSize) el.fontSize.value = defaultSettings.font_size;
        if (el.lineHeight) el.lineHeight.value = defaultSettings.line_height;
        if (el.receiptPaddingX) el.receiptPaddingX.value = defaultSettings.receipt_padding_x;
        if (el.receiptPaddingY) el.receiptPaddingY.value = defaultSettings.receipt_padding_y;
        if (el.watermarkText) el.watermarkText.value = defaultSettings.watermark_text;
        if (el.watermarkFontSize) el.watermarkFontSize.value = defaultSettings.watermark_font_size;
        if (el.watermarkTop) el.watermarkTop.value = defaultSettings.watermark_top;
        if (el.watermarkLeft) el.watermarkLeft.value = defaultSettings.watermark_left;
        if (el.watermarkOpacity) el.watermarkOpacity.value = defaultSettings.watermark_opacity;
        if (el.watermarkLetterSpacing) el.watermarkLetterSpacing.value = defaultSettings.watermark_letter_spacing;

        if (el.colSkuWidth) el.colSkuWidth.value = defaultSettings.col_sku_width;
        if (el.colDescGap) el.colDescGap.value = defaultSettings.col_desc_gap;
        if (el.colPriceWidth) el.colPriceWidth.value = defaultSettings.col_price_width;
        if (el.colQtyWidth) el.colQtyWidth.value = defaultSettings.col_qty_width;
        if (el.colAmtWidth) el.colAmtWidth.value = defaultSettings.col_amt_width;

        if (el.colSubtotalLabel) el.colSubtotalLabel.value = defaultSettings.col_subtotal_label;
        if (el.colSubtotalAmt) el.colSubtotalAmt.value = defaultSettings.col_subtotal_amt;
        if (el.colTaxLabel) el.colTaxLabel.value = defaultSettings.col_tax_label;
        if (el.colTaxAmt) el.colTaxAmt.value = defaultSettings.col_tax_amt;
        if (el.colTotalLabel) el.colTotalLabel.value = defaultSettings.col_total_label;
        if (el.colTotalAmt) el.colTotalAmt.value = defaultSettings.col_total_amt;
        if (el.colTenderLabel) el.colTenderLabel.value = defaultSettings.col_tender_label;
        if (el.colTenderAmt) el.colTenderAmt.value = defaultSettings.col_tender_amt;
        if (el.colChangeLabel) el.colChangeLabel.value = defaultSettings.col_change_label;
        if (el.colChangeAmt) el.colChangeAmt.value = defaultSettings.col_change_amt;

        if (el.colBirVatAmt) el.colBirVatAmt.value = defaultSettings.col_bir_vat_amt;
        if (el.colVatableAmt) el.colVatableAmt.value = defaultSettings.col_vatable_amt;
        if (el.colVatAmt) el.colVatAmt.value = defaultSettings.col_vat_amt;
        if (el.colNonVatAmt) el.colNonVatAmt.value = defaultSettings.col_nonvat_amt;

        if (el.customHeaderText) el.customHeaderText.value = defaultSettings.custom_header_text;
        if (el.customHeaderAlign) el.customHeaderAlign.value = defaultSettings.custom_header_align;
        if (el.customMidText) el.customMidText.value = defaultSettings.custom_mid_text;
        if (el.customMidAlign) el.customMidAlign.value = defaultSettings.custom_mid_align;
        if (el.customFooterText) el.customFooterText.value = defaultSettings.custom_footer_text;
        if (el.customFooterAlign) el.customFooterAlign.value = defaultSettings.custom_footer_align;

        if (el.topDividerType) el.topDividerType.value = defaultSettings.top_divider_type;
        if (el.topDividerCustom) el.topDividerCustom.value = defaultSettings.top_divider_custom;
        if (el.headerNameOffset) el.headerNameOffset.value = defaultSettings.header_name_offset;
        if (el.headerCompanyOffset) el.headerCompanyOffset.value = defaultSettings.header_company_offset;
        if (el.headerTinOffset) el.headerTinOffset.value = defaultSettings.header_tin_offset;
        if (el.headerAddrOffset) el.headerAddrOffset.value = defaultSettings.header_addr_offset;
        if (el.headerTelOffset) el.headerTelOffset.value = defaultSettings.header_tel_offset;
        if (el.headerAccOffset) el.headerAccOffset.value = defaultSettings.header_acc_offset;
        if (el.headerSerialOffset) el.headerSerialOffset.value = defaultSettings.header_serial_offset;
        if (el.headerMinOffset) el.headerMinOffset.value = defaultSettings.header_min_offset;
        if (el.headerPermitOffset) el.headerPermitOffset.value = defaultSettings.header_permit_offset;

        if (el.vatTopDividerType) el.vatTopDividerType.value = defaultSettings.vat_top_divider_type;
        if (el.vatTopDividerCustom) el.vatTopDividerCustom.value = defaultSettings.vat_top_divider_custom;
        if (el.vatBottomDividerType) el.vatBottomDividerType.value = defaultSettings.vat_bottom_divider_type;
        if (el.vatBottomDividerCustom) el.vatBottomDividerCustom.value = defaultSettings.vat_bottom_divider_custom;
        if (el.vatBottomSpaceBefore) el.vatBottomSpaceBefore.value = defaultSettings.vat_bottom_space_before;
        if (el.vatBottomSpaceAfter)  el.vatBottomSpaceAfter.value  = defaultSettings.vat_bottom_space_after;

        updateLivePreview();
    });
}

// ==========================================
// DIRECT ON-CANVAS DRAG & DROP LOGIC
// ==========================================
let activeDrag = null;
let startX = 0;
let startY = 0;
let initialVal = 0;
let initialValY = 0;
let selectedElement = null;

function setSelectedElement(elemKey, elemName) {
    selectedElement = elemKey;
    document.querySelectorAll('.canvas-interactive').forEach(node => {
        node.classList.toggle('selected-element', node.getAttribute('data-element') === elemKey);
    });
    if (elemKey && el.nudgeToolbar) {
        if (el.selectedElementName) el.selectedElementName.textContent = 'Selected: ' + elemName;
        el.nudgeToolbar.classList.add('visible');
    } else if (el.nudgeToolbar) {
        el.nudgeToolbar.classList.remove('visible');
    }
}

// 1. Receipt Text Drag (Adjusts receipt_padding_x / horizontal indent)
if (el.liveReceiptText) {
    el.liveReceiptText.addEventListener('mousedown', (e) => {
        activeDrag = 'receipt-text';
        startX = e.clientX;
        initialVal = parseInt(el.receiptPaddingX?.value || 20);
        el.liveReceiptText.classList.add('active-drag');
        setSelectedElement('receipt-text', 'Receipt Body Text (Indent)');
        e.preventDefault();
    });
}

// 2. Meta Bar Drag (Adjusts meta_gap / spacing between items)
if (el.liveMetaBar) {
    el.liveMetaBar.addEventListener('mousedown', (e) => {
        activeDrag = 'meta-bar';
        startX = e.clientX;
        initialVal = parseInt(el.metaGap?.value || 65);
        el.liveMetaBar.classList.add('active-drag');
        setSelectedElement('meta-bar', 'Metadata Bar Items (Gap)');
        e.preventDefault();
    });
}

// 3. Top Title Drag (Adjusts top_title_margin_top up/down)
if (el.liveHeaderTitle) {
    el.liveHeaderTitle.addEventListener('mousedown', (e) => {
        activeDrag = 'header-title';
        startY = e.clientY;
        initialVal = parseInt(el.topTitleMarginTop?.value || 25);
        el.liveHeaderTitle.classList.add('active-drag');
        setSelectedElement('header-title', 'Top Title (Vertical Offset)');
        e.preventDefault();
    });
}

// 4. Watermark Drag (Adjusts watermark_left & watermark_top)
if (el.liveWatermark) {
    el.liveWatermark.addEventListener('mousedown', (e) => {
        activeDrag = 'watermark';
        startX = e.clientX;
        startY = e.clientY;
        initialVal = parseInt(el.watermarkLeft?.value || 50);
        initialValY = parseInt(el.watermarkTop?.value || 75);
        el.liveWatermark.classList.add('active-drag');
        setSelectedElement('watermark', 'Watermark (Left & Top Position)');
        e.preventDefault();
    });
}

// Window mouse move listener for smooth dragging
window.addEventListener('mousemove', (e) => {
    if (!activeDrag) return;

    if (activeDrag === 'receipt-text' && el.receiptPaddingX) {
        const deltaX = Math.round((e.clientX - startX) / 2);
        let newVal = initialVal + deltaX;
        newVal = Math.max(0, Math.min(60, newVal));
        el.receiptPaddingX.value = newVal;
        updateLivePreview();
    } else if (activeDrag === 'meta-bar' && el.metaGap) {
        const deltaX = Math.round((e.clientX - startX) / 2);
        let newVal = initialVal + deltaX;
        newVal = Math.max(15, Math.min(110, newVal));
        el.metaGap.value = newVal;
        updateLivePreview();
    } else if (activeDrag === 'header-title' && el.topTitleMarginTop) {
        const deltaY = Math.round((e.clientY - startY) / 2);
        let newVal = initialVal + deltaY;
        newVal = Math.max(0, Math.min(70, newVal));
        el.topTitleMarginTop.value = newVal;
        updateLivePreview();
    } else if (activeDrag === 'watermark' && el.watermarkLeft && el.watermarkTop) {
        const deltaX = Math.round((e.clientX - startX) / 5);
        const deltaY = Math.round((e.clientY - startY) / 5);
        let newLeft = Math.max(15, Math.min(85, initialVal + deltaX));
        let newTop = Math.max(20, Math.min(95, initialValY + deltaY));
        el.watermarkLeft.value = newLeft;
        el.watermarkTop.value = newTop;
        updateLivePreview();
    }
});

window.addEventListener('mouseup', () => {
    if (activeDrag) {
        document.querySelectorAll('.canvas-interactive').forEach(node => node.classList.remove('active-drag'));
        activeDrag = null;
    }
});

// Nudge button controls
const nudgeLeftBtn = document.getElementById('nudgeLeft');
if (nudgeLeftBtn) {
    nudgeLeftBtn.addEventListener('click', () => {
        if (selectedElement === 'receipt-text' && el.receiptPaddingX) {
            el.receiptPaddingX.value = Math.max(0, parseInt(el.receiptPaddingX.value) - 1);
        } else if (selectedElement === 'meta-bar' && el.metaGap) {
            el.metaGap.value = Math.max(15, parseInt(el.metaGap.value) - 2);
        } else if (selectedElement === 'watermark' && el.watermarkLeft) {
            el.watermarkLeft.value = Math.max(15, parseInt(el.watermarkLeft.value) - 1);
        }
        updateLivePreview();
    });
}

const nudgeRightBtn = document.getElementById('nudgeRight');
if (nudgeRightBtn) {
    nudgeRightBtn.addEventListener('click', () => {
        if (selectedElement === 'receipt-text' && el.receiptPaddingX) {
            el.receiptPaddingX.value = Math.min(60, parseInt(el.receiptPaddingX.value) + 1);
        } else if (selectedElement === 'meta-bar' && el.metaGap) {
            el.metaGap.value = Math.min(110, parseInt(el.metaGap.value) + 2);
        } else if (selectedElement === 'watermark' && el.watermarkLeft) {
            el.watermarkLeft.value = Math.min(85, parseInt(el.watermarkLeft.value) + 1);
        }
        updateLivePreview();
    });
}

const nudgeDeselectBtn = document.getElementById('nudgeDeselect');
if (nudgeDeselectBtn) {
    nudgeDeselectBtn.addEventListener('click', () => {
        setSelectedElement(null, '');
    });
}

// Keyboard arrow keys nudge listener
window.addEventListener('keydown', (e) => {
    if (!selectedElement) return;
    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
        if (e.key === 'ArrowLeft' && nudgeLeftBtn) {
            nudgeLeftBtn.click();
        } else if (e.key === 'ArrowRight' && nudgeRightBtn) {
            nudgeRightBtn.click();
        } else if (e.key === 'ArrowUp') {
            if (selectedElement === 'header-title' && el.topTitleMarginTop) {
                el.topTitleMarginTop.value = Math.max(0, parseInt(el.topTitleMarginTop.value) - 1);
                updateLivePreview();
            } else if (selectedElement === 'watermark' && el.watermarkTop) {
                el.watermarkTop.value = Math.max(20, parseInt(el.watermarkTop.value) - 1);
                updateLivePreview();
            }
        } else if (e.key === 'ArrowDown') {
            if (selectedElement === 'header-title' && el.topTitleMarginTop) {
                el.topTitleMarginTop.value = Math.min(70, parseInt(el.topTitleMarginTop.value) + 1);
                updateLivePreview();
            } else if (selectedElement === 'watermark' && el.watermarkTop) {
                el.watermarkTop.value = Math.min(95, parseInt(el.watermarkTop.value) + 1);
                updateLivePreview();
            }
        }
        e.preventDefault();
    }
});

// Instant Dedicated AJAX Save
function saveSettingsViaAjax(e) {
    if (e) e.preventDefault();

    const btnTop = document.getElementById('btn_save_top');
    const btnBottom = document.getElementById('btn_save_bottom');
    const origTopText = btnTop ? btnTop.innerHTML : '';
    const origBottomText = btnBottom ? btnBottom.innerHTML : '';

    if (btnTop) { btnTop.disabled = true; btnTop.innerHTML = '⏳ Saving...'; }
    if (btnBottom) { btnBottom.disabled = true; btnBottom.innerHTML = '⏳ Saving...'; }

    try {
        const payload = {
            action: 'save_settings',
            paper_size: el.paperSize ? el.paperSize.value : 'a4',
            sheet_margin: parseFloat(el.sheetMargin?.value || 0.30),
            top_title_margin_top: parseInt(el.topTitleMarginTop?.value || 25),
            bottom_title_margin_bottom: parseInt(el.bottomTitleMarginBottom?.value || 16),
            top_title_font_size: parseInt(el.topTitleFontSize?.value || 16),
            meta_gap: parseInt(el.metaGap?.value || 65),
            meta_font_size: parseInt(el.metaFontSize?.value || 14),
            char_width: parseInt(el.charWidth?.value || 54),
            font_size: parseFloat(el.fontSize?.value || 15),
            line_height: parseFloat(el.lineHeight?.value || 1.36),
            receipt_padding_x: parseInt(el.receiptPaddingX?.value || 20),
            receipt_padding_y: parseInt(el.receiptPaddingY?.value || 6),
            watermark_text: el.watermarkText?.value || 'Electronic Journal Copy',
            watermark_font_size: parseInt(el.watermarkFontSize?.value || 60),
            watermark_top: parseInt(el.watermarkTop?.value || 75),
            watermark_left: parseInt(el.watermarkLeft?.value || 50),
            watermark_opacity: parseFloat(el.watermarkOpacity?.value || 0.16),
            watermark_letter_spacing: parseFloat(el.watermarkLetterSpacing?.value || 3),

            col_sku_width: parseInt(el.colSkuWidth?.value || 16),
            col_desc_gap: parseInt(el.colDescGap?.value || 3),
            col_price_width: parseInt(el.colPriceWidth?.value || 16),
            col_qty_width: parseInt(el.colQtyWidth?.value || 6),
            col_amt_width: parseInt(el.colAmtWidth?.value || 20),

            col_subtotal_label: parseInt(el.colSubtotalLabel?.value || 28),
            col_subtotal_amt: parseInt(el.colSubtotalAmt?.value || 20),
            col_tax_label: parseInt(el.colTaxLabel?.value || 22),
            col_tax_amt: parseInt(el.colTaxAmt?.value || 26),
            col_total_label: parseInt(el.colTotalLabel?.value || 24),
            col_total_amt: parseInt(el.colTotalAmt?.value || 24),
            col_tender_label: parseInt(el.colTenderLabel?.value || 23),
            col_tender_amt: parseInt(el.colTenderAmt?.value || 25),
            col_change_label: parseInt(el.colChangeLabel?.value || 25),
            col_change_amt: parseInt(el.colChangeAmt?.value || 18),

            col_bir_vat_amt: parseInt(el.colBirVatAmt?.value || 20),
            col_vatable_amt: parseInt(el.colVatableAmt?.value || 22),
            col_vat_amt: parseInt(el.colVatAmt?.value || 22),
            col_nonvat_amt: parseInt(el.colNonVatAmt?.value || 18),

            custom_header_text: el.customHeaderText ? el.customHeaderText.value.trim() : '',
            custom_header_align: el.customHeaderAlign ? el.customHeaderAlign.value : 'center',
            custom_mid_text: el.customMidText ? el.customMidText.value.trim() : '',
            custom_mid_align: el.customMidAlign ? el.customMidAlign.value : 'center',
            custom_footer_text: el.customFooterText ? el.customFooterText.value.trim() : 'THANK YOU FOR SHOPPING!',
            custom_footer_align: el.customFooterAlign ? el.customFooterAlign.value : 'center',

            top_divider_type: el.topDividerType ? el.topDividerType.value : 'double',
            top_divider_custom: el.topDividerCustom ? el.topDividerCustom.value.trim() : '',
            header_name_offset: parseInt(el.headerNameOffset?.value || 0),
            header_company_offset: parseInt(el.headerCompanyOffset?.value || 0),
            header_tin_offset: parseInt(el.headerTinOffset?.value || 0),
            header_addr_offset: parseInt(el.headerAddrOffset?.value || 0),
            header_tel_offset: parseInt(el.headerTelOffset?.value || 0),
            header_acc_offset: parseInt(el.headerAccOffset?.value || 0),
            header_serial_offset: parseInt(el.headerSerialOffset?.value || 0),
            header_min_offset: parseInt(el.headerMinOffset?.value || 0),
            header_permit_offset: parseInt(el.headerPermitOffset?.value || 0),

            vat_top_divider_type: el.vatTopDividerType ? el.vatTopDividerType.value : 'dash',
            vat_top_divider_custom: el.vatTopDividerCustom ? el.vatTopDividerCustom.value.trim() : '',
            vat_bottom_divider_type: el.vatBottomDividerType ? el.vatBottomDividerType.value : 'dash',
            vat_bottom_divider_custom: el.vatBottomDividerCustom ? el.vatBottomDividerCustom.value.trim() : '',
            vat_bottom_space_before: parseInt(el.vatBottomSpaceBefore?.value || 0),
            vat_bottom_space_after:  parseInt(el.vatBottomSpaceAfter?.value  || 1)
        };

        fetch('ej_save_layout_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            const toast = el.saveToast;
            if (toast) {
                toast.textContent = data.message || '✓ Layout Settings Saved!';
                toast.style.background = data.status === 'success' ? '#16a34a' : '#dc2626';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 2800);
            }

            if (btnTop) {
                btnTop.innerHTML = data.status === 'success' ? '✓ Saved!' : '❌ Error';
                btnTop.style.background = data.status === 'success' ? '#16a34a' : '#dc2626';
                setTimeout(() => {
                    btnTop.disabled = false;
                    btnTop.innerHTML = origTopText;
                    btnTop.style.background = '';
                }, 2000);
            }
            if (btnBottom) {
                btnBottom.innerHTML = data.status === 'success' ? '✓ Saved!' : '❌ Error';
                btnBottom.style.background = data.status === 'success' ? '#16a34a' : '#dc2626';
                setTimeout(() => {
                    btnBottom.disabled = false;
                    btnBottom.innerHTML = origBottomText;
                    btnBottom.style.background = '';
                }, 2000);
            }
        })
        .catch(err => {
            console.error('Save AJAX error:', err);
            if (btnTop) { btnTop.disabled = false; btnTop.innerHTML = origTopText; }
            if (btnBottom) { btnBottom.disabled = false; btnBottom.innerHTML = origBottomText; }
            if (el.form) el.form.submit();
        });
    } catch(err) {
        console.error('Payload error:', err);
        if (btnTop) { btnTop.disabled = false; btnTop.innerHTML = origTopText; }
        if (btnBottom) { btnBottom.disabled = false; btnBottom.innerHTML = origBottomText; }
        if (el.form) el.form.submit();
    }
}

if (el.form) el.form.addEventListener('submit', saveSettingsViaAjax);
const btnSaveTop = document.getElementById('btn_save_top');
if (btnSaveTop) btnSaveTop.addEventListener('click', () => saveSettingsViaAjax());

// Initialize live preview on page load
updateLivePreview();
</script>
</body>
</html>
