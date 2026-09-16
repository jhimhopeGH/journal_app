<?php
// receipt_print_layout.php - Live Interactive Visual Layout & Column Slider Customizer for 80mm Thermal Receipt Reprint
require_once __DIR__ . '/auth.php';
requireNavAccess('receipt_layout');
require_once __DIR__ . '/ej_db.php';
$currentPage = 'receipt_layout';


$saveMessage = '';
$saveError = '';

// Handle Form Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $newSettings = [
        'char_width'         => max(30, min(80, (int)($_POST['char_width'] ?? 42))),
        'font_size'          => max(7, min(20, (float)($_POST['font_size'] ?? 10))),
        'line_height'        => max(0.9, min(3.5, (float)($_POST['line_height'] ?? 1.3))),
        'font_family'        => in_array($_POST['font_family'] ?? '', ['generic_text', 'thermal', 'consolas', 'lucida', 'courier']) ? $_POST['font_family'] : 'generic_text',
        'font_weight'        => in_array($_POST['font_weight'] ?? '', ['bold', 'normal']) ? $_POST['font_weight'] : 'bold',
        'receipt_padding_x'  => max(0, min(80, (int)($_POST['receipt_padding_x'] ?? 10))),
        'receipt_padding_y'  => max(0, min(80, (int)($_POST['receipt_padding_y'] ?? 4))),
        'header_top_space'   => max(0, min(10, (int)($_POST['header_top_space'] ?? 1))),
        'cut_margin_bottom'  => max(0, min(15, (int)($_POST['cut_margin_bottom'] ?? 4))),

        'reprint_line_count'     => max(1, min(3,  (int)($_POST['reprint_line_count'] ?? 2))),
        'reprint_top_space'      => max(0, min(10, (int)($_POST['reprint_top_space'] ?? 1))),
        'reprint_between_space'  => max(0, min(10, (int)($_POST['reprint_between_space'] ?? 0))),
        'reprint_bottom_space'   => max(0, min(10, (int)($_POST['reprint_bottom_space'] ?? 1))),
        'reprint_asterisk_space' => max(0, min(10, (int)($_POST['reprint_asterisk_space'] ?? 1))),
        'reprint_char_space'     => max(0, min(8,  (int)($_POST['reprint_char_space'] ?? 1))),

        'col_qty_width'      => max(2, min(10, (int)($_POST['col_qty_width'] ?? 4))),
        'col_sku_width'      => max(6, min(20, (int)($_POST['col_sku_width'] ?? 10))),
        'col_price_width'    => max(4, min(15, (int)($_POST['col_price_width'] ?? 8))),
        'col_amt_width'      => max(5, min(16, (int)($_POST['col_amt_width'] ?? 9))),

        'col_subtotal_label' => max(10, min(35, (int)($_POST['col_subtotal_label'] ?? 26))),
        'col_subtotal_amt'   => max(6, min(25, (int)($_POST['col_subtotal_amt'] ?? 14))),
        'col_total_label'    => max(10, min(35, (int)($_POST['col_total_label'] ?? 26))),
        'col_total_amt'      => max(6, min(25, (int)($_POST['col_total_amt'] ?? 14))),
        'col_tender_label'   => max(10, min(35, (int)($_POST['col_tender_label'] ?? 26))),
        'col_tender_amt'     => max(6, min(25, (int)($_POST['col_tender_amt'] ?? 14))),
        'col_change_label'   => max(8, min(25, (int)($_POST['col_change_label'] ?? 16))),
        'col_change_amt'     => max(6, min(25, (int)($_POST['col_change_amt'] ?? 14))),

        'col_vat_label'      => max(10, min(30, (int)($_POST['col_vat_label'] ?? 17))),
        'col_vat_amt'        => max(6, min(25, (int)($_POST['col_vat_amt'] ?? 14)))
    ];

    if (saveReceiptPrintSettings($newSettings)) {
        $saveMessage = '✓ Receipt Print Layout Settings saved successfully!';
    } else {
        $saveError = 'Failed to save settings to receipt_print_settings.json';
    }
}

$settings = getReceiptPrintSettings();

// Load sample entries for live test selection
$samplesListStmt = $ejPdo->query("SELECT id, entry_date, store_code, register_number, transaction_number, invoice_number, total_amount FROM ej_entries ORDER BY id DESC LIMIT 20");
$sampleOptions = $samplesListStmt ? $samplesListStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$selectedSampleId = isset($_GET['sample_id']) ? (int)$_GET['sample_id'] : ($sampleOptions[0]['id'] ?? 0);
$sampleEntry = null;
if ($selectedSampleId > 0) {
    $sampleEntry = getEjEntryById($selectedSampleId);
}
if (!$sampleEntry && !empty($sampleOptions)) {
    $sampleEntry = getEjEntryById((int)$sampleOptions[0]['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt Print Layout Customizer - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="fonts/thermal_font.css?v=<?php echo filemtime(__DIR__ . '/fonts/thermal_font.css'); ?>">
<style>
    .layout-grid {
        display: grid;
        grid-template-columns: 460px 1fr;
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 1080px) {
        .layout-grid { grid-template-columns: 1fr; }
    }
    .panel-sliders {
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .slider-group {
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }
    .slider-group:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .group-title {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6d28d9;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .slider-row {
        display: grid;
        grid-template-columns: 1fr 140px 50px;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
        font-size: 13px;
    }
    .slider-row label {
        color: #334155;
        font-weight: 500;
        margin: 0;
    }
    .slider-row input[type="range"] {
        accent-color: #7c3aed;
        cursor: pointer;
        width: 100%;
    }
    .slider-val-badge {
        font-family: monospace;
        font-size: 12px;
        font-weight: 700;
        background: #f1f5f9;
        color: #475569;
        padding: 2px 6px;
        border-radius: 4px;
        text-align: center;
    }
    /* Sticky Preview Pane */
    .preview-pane-sticky {
        position: sticky;
        top: 20px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        display: flex;
        flex-direction: column;
        align-items: center;
        overflow-x: auto;
        max-width: 100%;
    }
    .preview-header {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .thermal-receipt-preview {
        width: 80mm;
        max-width: 80mm;
        min-width: 80mm;
        background: #ffffff;
        border-left: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        border-top: none;
        border-bottom: none;
        border-radius: 0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.06), 0 16px 36px -4px rgba(0,0,0,0.14);
        padding: 0;
        box-sizing: border-box;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    @media (max-width: 340px) {
        .thermal-receipt-preview {
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }
    }
    .receipt-sheet-tear-top {
        height: 7px;
        width: 100%;
        background: linear-gradient(135deg, #f8fafc 4px, transparent 0) 0 0,
                    linear-gradient(-135deg, #f8fafc 4px, transparent 0) 0 0;
        background-size: 8px 7px;
        background-repeat: repeat-x;
    }
    .receipt-sheet-tear-bottom {
        height: 7px;
        width: 100%;
        background: linear-gradient(45deg, #f8fafc 4px, transparent 0) 0 0,
                    linear-gradient(-45deg, #f8fafc 4px, transparent 0) 0 0;
        background-size: 8px 7px;
        background-repeat: repeat-x;
    }
    #previewContent {
        font-family: <?php echo getReceiptFontCss($settings['font_family'] ?? 'thermal'); ?>;
        font-weight: <?php echo (($settings['font_family'] ?? '') === 'generic_text' || ($settings['font_weight'] ?? 'bold') === 'normal') ? 'normal' : 'bold'; ?>;
        margin: 0 auto;
        width: fit-content;
        text-align: left;
        white-space: pre;
        word-wrap: normal;
        overflow-x: hidden;
        color: #000000;
        letter-spacing: 0px;
        box-sizing: border-box;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }
    .btn-save-sticky {
        background: #7c3aed;
        color: #ffffff;
        font-weight: 700;
        padding: 10px 24px;
        border-radius: 20px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.18s ease;
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
    }
    .btn-save-sticky:hover { background: #6d28d9; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(124, 58, 237, 0.35); }
    .save-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #0f172a;
        color: #38bdf8;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: 99999;
        border-left: 4px solid #38bdf8;
    }
    .save-toast.show {
        transform: translateY(0);
        opacity: 1;
    }
</style>
</head>
<body>
<div id="saveToast" class="save-toast">✓ Receipt Print Settings saved!</div>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h1>Receipt Print Layout Customizer</h1>
                <p>Fine-tune font size, column alignments, and width parameters for 80mm thermal receipt reprints.</p>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="receipt_reprint.php" class="btn btn-secondary">🧾 View Receipts</a>
                <a href="printer_setup.php" class="btn btn-secondary" style="background:#0369a1 !important;">🖨️ LAN Printer Share</a>
                <?php if ($sampleEntry): ?>
                    <a href="receipt_print.php?id=<?php echo (int)$sampleEntry['id']; ?>" class="btn" style="background:#059669;">🖨️ View Print Output</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($saveMessage): ?>
            <div class="success"><?php echo htmlspecialchars($saveMessage); ?></div>
        <?php endif; ?>
        <?php if ($saveError): ?>
            <div class="error"><?php echo htmlspecialchars($saveError); ?></div>
        <?php endif; ?>

        <form id="receiptSettingsForm" method="POST" action="receipt_print_layout.php">
            <input type="hidden" name="action" value="save_settings">

            <div class="layout-grid">
                <!-- Left: Sliders Panel -->
                <div class="panel-sliders">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h2 style="font-size:16px;margin:0;color:#0f172a;">⚙️ Layout &amp; Spacing Controls</h2>
                        <button type="button" id="btnResetDefaults" class="btn btn-secondary" style="padding:4px 10px;font-size:11px;">↺ Reset Defaults</button>
                    </div>

                    <!-- Receipt Paper & Font -->
                    <div class="slider-group">
                        <div class="group-title">📄 Paper &amp; Typography (80mm)</div>
                        
                        <div class="slider-row">
                            <label for="char_width">Line Character Width</label>
                            <input type="range" id="char_width" name="char_width" min="32" max="60" value="<?php echo (int)$settings['char_width']; ?>">
                            <span class="slider-val-badge" id="val_char_width"><?php echo (int)$settings['char_width']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="font_size">Font Size (px)</label>
                            <input type="range" id="font_size" name="font_size" min="8" max="18" step="0.5" value="<?php echo (float)$settings['font_size']; ?>">
                            <span class="slider-val-badge" id="val_font_size"><?php echo (float)$settings['font_size']; ?>px</span>
                        </div>

                        <div class="slider-row">
                            <label for="line_height">Line Height</label>
                            <input type="range" id="line_height" name="line_height" min="1.0" max="3.0" step="0.05" value="<?php echo (float)$settings['line_height']; ?>">
                            <span class="slider-val-badge" id="val_line_height"><?php echo (float)$settings['line_height']; ?></span>
                        </div>

                        <div class="slider-row" style="margin-top:6px;">
                            <label for="font_family" style="font-weight:600;">Font Style / Family</label>
                            <select id="font_family" name="font_family" style="padding:6px 10px; border:1.5px solid #cbd5e1; border-radius:6px; font-size:12.5px; background:#fff; font-weight:600; width:58%;">
                                <option value="generic_text" <?php if (($settings['font_family'] ?? 'generic_text') === 'generic_text') echo 'selected'; ?>>🖨️ Generic / Text Only (Dot-Matrix Hardware Font - Recommended)</option>
                                <option value="thermal" <?php if (($settings['font_family'] ?? '') === 'thermal') echo 'selected'; ?>>🖨️ POS Thermal / Font A (Consolas)</option>
                                <option value="lucida" <?php if (($settings['font_family'] ?? '') === 'lucida') echo 'selected'; ?>>🖥️ Lucida Console (Classic Sans)</option>
                                <option value="courier" <?php if (($settings['font_family'] ?? '') === 'courier') echo 'selected'; ?>>📜 Courier New (Typewriter Serif)</option>
                            </select>
                        </div>

                        <div class="slider-row" style="margin-top:6px;">
                            <label for="font_weight" style="font-weight:600;">Font Weight (Contrast)</label>
                            <select id="font_weight" name="font_weight" style="padding:6px 10px; border:1.5px solid #cbd5e1; border-radius:6px; font-size:12.5px; background:#fff; font-weight:600; width:58%;">
                                <option value="bold" <?php if (($settings['font_weight'] ?? 'bold') === 'bold') echo 'selected'; ?>>Bold (Solid Black Thermal Burn - Recommended)</option>
                                <option value="normal" <?php if (($settings['font_weight'] ?? '') === 'normal') echo 'selected'; ?>>Normal / Regular</option>
                            </select>
                        </div>

                        <div class="slider-row">
                            <label for="receipt_padding_x">Horizontal Padding (px)</label>
                            <input type="range" id="receipt_padding_x" name="receipt_padding_x" min="0" max="60" value="<?php echo (int)$settings['receipt_padding_x']; ?>">
                            <span class="slider-val-badge" id="val_receipt_padding_x"><?php echo (int)$settings['receipt_padding_x']; ?>px</span>
                        </div>

                        <div class="slider-row">
                            <label for="receipt_padding_y">Vertical Padding (px)</label>
                            <input type="range" id="receipt_padding_y" name="receipt_padding_y" min="0" max="60" value="<?php echo (int)$settings['receipt_padding_y']; ?>">
                            <span class="slider-val-badge" id="val_receipt_padding_y"><?php echo (int)$settings['receipt_padding_y']; ?>px</span>
                        </div>

                        <div class="slider-row">
                            <label for="header_top_space">Header Top Spacing (Lines Above Receipt)</label>
                            <input type="range" id="header_top_space" name="header_top_space" min="0" max="10" value="<?php echo (int)($settings['header_top_space'] ?? 1); ?>">
                            <span class="slider-val-badge" id="val_header_top_space"><?php echo (int)($settings['header_top_space'] ?? 1); ?> lines</span>
                        </div>

                        <div class="slider-row">
                            <label for="cut_margin_bottom">Paper Cut Feed (Lines)</label>
                            <input type="range" id="cut_margin_bottom" name="cut_margin_bottom" min="0" max="10" value="<?php echo (int)($settings['cut_margin_bottom'] ?? 4); ?>">
                            <span class="slider-val-badge" id="val_cut_margin_bottom"><?php echo (int)($settings['cut_margin_bottom'] ?? 4); ?> lines</span>
                        </div>
                    </div>

                    <!-- Reprint Banner Spacing -->
                    <div class="slider-group">
                        <div class="group-title">🔖 *** REPRINT *** Banner Spacing</div>

                        <div class="slider-row">
                            <label for="reprint_line_count">Number of Banner Lines</label>
                            <input type="range" id="reprint_line_count" name="reprint_line_count" min="1" max="3" value="<?php echo (int)($settings['reprint_line_count'] ?? 2); ?>">
                            <span class="slider-val-badge" id="val_reprint_line_count"><?php echo (int)($settings['reprint_line_count'] ?? 2); ?> lines</span>
                        </div>

                        <div class="slider-row">
                            <label for="reprint_top_space">Top Spacing (Lines Above)</label>
                            <input type="range" id="reprint_top_space" name="reprint_top_space" min="0" max="6" value="<?php echo (int)($settings['reprint_top_space'] ?? 1); ?>">
                            <span class="slider-val-badge" id="val_reprint_top_space"><?php echo (int)($settings['reprint_top_space'] ?? 1); ?> lines</span>
                        </div>

                        <div class="slider-row">
                            <label for="reprint_between_space">Distance Between Lines</label>
                            <input type="range" id="reprint_between_space" name="reprint_between_space" min="0" max="6" value="<?php echo (int)($settings['reprint_between_space'] ?? 0); ?>">
                            <span class="slider-val-badge" id="val_reprint_between_space"><?php echo (int)($settings['reprint_between_space'] ?? 0); ?> lines</span>
                        </div>

                        <div class="slider-row">
                            <label for="reprint_bottom_space">Bottom Spacing (Lines Below)</label>
                            <input type="range" id="reprint_bottom_space" name="reprint_bottom_space" min="0" max="6" value="<?php echo (int)($settings['reprint_bottom_space'] ?? 1); ?>">
                            <span class="slider-val-badge" id="val_reprint_bottom_space"><?php echo (int)($settings['reprint_bottom_space'] ?? 1); ?> lines</span>
                        </div>

                        <div class="slider-row">
                            <label for="reprint_asterisk_space">Space around Asterisks (***)</label>
                            <input type="range" id="reprint_asterisk_space" name="reprint_asterisk_space" min="0" max="8" value="<?php echo (int)($settings['reprint_asterisk_space'] ?? 3); ?>">
                            <span class="slider-val-badge" id="val_reprint_asterisk_space"><?php echo (int)($settings['reprint_asterisk_space'] ?? 3); ?> spaces</span>
                        </div>

                        <div class="slider-row">
                            <label for="reprint_char_space">Letter Spacing (R E P R I N T)</label>
                            <input type="range" id="reprint_char_space" name="reprint_char_space" min="0" max="6" value="<?php echo (int)($settings['reprint_char_space'] ?? 3); ?>">
                            <span class="slider-val-badge" id="val_reprint_char_space"><?php echo (int)($settings['reprint_char_space'] ?? 3); ?> spaces</span>
                        </div>
                    </div>

                    <!-- Items Header & Table Columns -->
                    <div class="slider-group">
                        <div class="group-title">🛒 Line Item Columns</div>

                        <div class="slider-row">
                            <label for="col_qty_width">QTY Column Width</label>
                            <input type="range" id="col_qty_width" name="col_qty_width" min="2" max="8" value="<?php echo (int)$settings['col_qty_width']; ?>">
                            <span class="slider-val-badge" id="val_col_qty_width"><?php echo (int)$settings['col_qty_width']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_sku_width">ITEM / SKU Width</label>
                            <input type="range" id="col_sku_width" name="col_sku_width" min="6" max="16" value="<?php echo (int)$settings['col_sku_width']; ?>">
                            <span class="slider-val-badge" id="val_col_sku_width"><?php echo (int)$settings['col_sku_width']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_price_width">PRICE Column Width</label>
                            <input type="range" id="col_price_width" name="col_price_width" min="5" max="12" value="<?php echo (int)$settings['col_price_width']; ?>">
                            <span class="slider-val-badge" id="val_col_price_width"><?php echo (int)$settings['col_price_width']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_amt_width">TOTAL (Item) Width</label>
                            <input type="range" id="col_amt_width" name="col_amt_width" min="6" max="14" value="<?php echo (int)$settings['col_amt_width']; ?>">
                            <span class="slider-val-badge" id="val_col_amt_width"><?php echo (int)$settings['col_amt_width']; ?></span>
                        </div>
                    </div>

                    <!-- Subtotal, Total, Tender & Change Columns -->
                    <div class="slider-group">
                        <div class="group-title">💳 Totals &amp; Tenders Columns</div>

                        <div class="slider-row">
                            <label for="col_subtotal_label">Sub Total Label Width</label>
                            <input type="range" id="col_subtotal_label" name="col_subtotal_label" min="15" max="32" value="<?php echo (int)$settings['col_subtotal_label']; ?>">
                            <span class="slider-val-badge" id="val_col_subtotal_label"><?php echo (int)$settings['col_subtotal_label']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_subtotal_amt">Sub Total Amount Width</label>
                            <input type="range" id="col_subtotal_amt" name="col_subtotal_amt" min="8" max="20" value="<?php echo (int)$settings['col_subtotal_amt']; ?>">
                            <span class="slider-val-badge" id="val_col_subtotal_amt"><?php echo (int)$settings['col_subtotal_amt']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_total_label">Total Label Width</label>
                            <input type="range" id="col_total_label" name="col_total_label" min="15" max="32" value="<?php echo (int)$settings['col_total_label']; ?>">
                            <span class="slider-val-badge" id="val_col_total_label"><?php echo (int)$settings['col_total_label']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_total_amt">Total Amount Width</label>
                            <input type="range" id="col_total_amt" name="col_total_amt" min="8" max="20" value="<?php echo (int)$settings['col_total_amt']; ?>">
                            <span class="slider-val-badge" id="val_col_total_amt"><?php echo (int)$settings['col_total_amt']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_tender_label">Tender Name Width</label>
                            <input type="range" id="col_tender_label" name="col_tender_label" min="15" max="32" value="<?php echo (int)$settings['col_tender_label']; ?>">
                            <span class="slider-val-badge" id="val_col_tender_label"><?php echo (int)$settings['col_tender_label']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_tender_amt">Tender Amount Width</label>
                            <input type="range" id="col_tender_amt" name="col_tender_amt" min="8" max="20" value="<?php echo (int)$settings['col_tender_amt']; ?>">
                            <span class="slider-val-badge" id="val_col_tender_amt"><?php echo (int)$settings['col_tender_amt']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_change_label">CHANGE Label Width</label>
                            <input type="range" id="col_change_label" name="col_change_label" min="10" max="22" value="<?php echo (int)$settings['col_change_label']; ?>">
                            <span class="slider-val-badge" id="val_col_change_label"><?php echo (int)$settings['col_change_label']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_change_amt">CHANGE Amount Width</label>
                            <input type="range" id="col_change_amt" name="col_change_amt" min="8" max="20" value="<?php echo (int)$settings['col_change_amt']; ?>">
                            <span class="slider-val-badge" id="val_col_change_amt"><?php echo (int)$settings['col_change_amt']; ?></span>
                        </div>
                    </div>

                    <!-- VAT Breakdown Columns -->
                    <div class="slider-group">
                        <div class="group-title">📊 VAT Breakdown Columns</div>

                        <div class="slider-row">
                            <label for="col_vat_label">VAT Label Width</label>
                            <input type="range" id="col_vat_label" name="col_vat_label" min="12" max="25" value="<?php echo (int)$settings['col_vat_label']; ?>">
                            <span class="slider-val-badge" id="val_col_vat_label"><?php echo (int)$settings['col_vat_label']; ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="col_vat_amt">VAT Amount Width</label>
                            <input type="range" id="col_vat_amt" name="col_vat_amt" min="8" max="20" value="<?php echo (int)$settings['col_vat_amt']; ?>">
                            <span class="slider-val-badge" id="val_col_vat_amt"><?php echo (int)$settings['col_vat_amt']; ?></span>
                        </div>
                    </div>

                    <div style="margin-top:20px;">
                        <button type="submit" class="btn-save-sticky" style="width:100%;justify-content:center;">💾 Save Receipt Settings</button>
                    </div>

                    <!-- Auto Cut Help Box -->
                    <div style="margin-top: 24px; padding: 14px; background: #fdf4ff; border: 1px solid #e879f9; border-radius: 8px; font-size: 12px; color: #701a75;">
                        <div style="font-weight: 700; margin-bottom: 6px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                            ✂️ How to Enable Auto-Cut on "Generic / Text Only" Driver
                        </div>
                        <p style="margin: 0 0 8px 0; line-height: 1.4;">
                            Because you are using Windows <strong>Generic / Text Only</strong>, Windows needs the hardware cut code defined in <strong>Printer Properties &rarr; Printer Commands</strong>:
                        </p>
                        <ol style="margin: 0; padding-left: 18px; line-height: 1.6;">
                            <li>Press <kbd>Win + R</kbd>, type <code>control printers</code> and press Enter.</li>
                            <li>Right-click your <strong>Generic / Text Only</strong> printer &rarr; choose <strong>Printer properties</strong>.</li>
                            <li>Click the <strong>Printer Commands</strong> tab.</li>
                            <li>In the <strong>End Print Job</strong> box, enter: <code style="background:#1e293b;color:#38bdf8;padding:2px 5px;border-radius:3px;font-weight:bold;">&lt;1D&gt;V&lt;01&gt;</code> (or <code style="background:#1e293b;color:#38bdf8;padding:2px 5px;border-radius:3px;font-weight:bold;">&lt;1B&gt;d&lt;04&gt;&lt;1D&gt;V&lt;01&gt;</code>).</li>
                            <li>Click <strong>Apply</strong> and <strong>OK</strong>.</li>
                        </ol>
                    </div>
                </div>

                <!-- Right: Sticky Live Preview Panel -->
                <div class="preview-pane-sticky">
                    <div class="preview-header">
                        <div style="font-weight:700;font-size:14px;color:#0f172a;display:flex;align-items:center;gap:6px;">
                            🧾 80mm Thermal Preview
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <select id="preview_width_select" onchange="changeRollWidth(this.value)" style="padding:4px 8px;font-size:11px;font-weight:700;border-radius:4px;border:1px solid #cbd5e1;background:#fff;color:#6d28d9;cursor:pointer;">
                                <option value="80mm" selected>80mm (Standard Roll)</option>
                                <option value="76mm">76mm (Compact)</option>
                                <option value="58mm">58mm (Narrow)</option>
                                <option value="fit-content">Fit Content (Auto)</option>
                            </select>
                            <select id="sampleSelect" onchange="location.href='receipt_print_layout.php?sample_id='+this.value" style="padding:4px 8px;font-size:12px;border-radius:4px;border:1px solid #cbd5e1;">
                                <?php foreach ($sampleOptions as $so): ?>
                                    <option value="<?php echo (int)$so['id']; ?>" <?php echo ($sampleEntry && (int)$sampleEntry['id'] === (int)$so['id']) ? 'selected' : ''; ?>>
                                        Trx #<?php echo htmlspecialchars($so['transaction_number']); ?> (Store <?php echo htmlspecialchars($so['store_code']); ?> - ₱<?php echo number_format((float)$so['total_amount'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="thermal-receipt-preview" id="thermalReceiptPreview">
                        <div class="receipt-sheet-tear-top"></div>
                        <pre id="previewContent"></pre>
                        <div class="receipt-sheet-tear-bottom"></div>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<script>
function changeRollWidth(val) {
    const sheet = document.getElementById('thermalReceiptPreview');
    if (!sheet) return;
    if (val === 'fit-content') {
        sheet.style.width = 'fit-content';
        sheet.style.maxWidth = '100%';
        sheet.style.minWidth = '80mm';
    } else {
        sheet.style.width = val;
        sheet.style.maxWidth = val;
        sheet.style.minWidth = val;
    }
}

// Raw sample entry JSON data from PHP
const sampleEntryData = <?php echo json_encode($sampleEntry ?? [
    'id' => 1,
    'entry_date' => date('Y-m-d'),
    'entry_time' => '11:57:00',
    'store_code' => '10012',
    'register_number' => '115',
    'transaction_number' => '2066',
    'invoice_number' => '9008137',
    'member_number' => '',
    'customer_name' => '',
    'customer_address' => '',
    'customer_tin' => '',
    'sales_associate' => 'Danna Mae D.',
    'associate_id' => '',
    'till_number' => '6356',
    'subtotal' => 12.20,
    'total_amount' => 12.20,
    'vatable_sales' => 10.89,
    'total_vat' => 1.31,
    'total_non_vat' => 0.0,
    'vat_rate' => 12.0,
    'items' => json_encode([
        ['sku' => '10107937', 'description' => 'FUNKY FRNCH FRY SWT CHDR 25G', 'quantity' => 2, 'unit_price' => 6.10, 'amount' => 12.20]
    ]),
    'tenders' => json_encode([
        ['name' => 'Cash', 'amount' => 20.00],
        ['name' => 'Cash', 'amount' => -7.80]
    ])
]); ?>;

const storeHeaderSample = `NCCC SUPERMARKET\nLTS RETAIL SPECIALISTS, INC.\nTIN 006-171-689-065 VAT\nKELSEAN BLDG. COMARRA ST. POBLACION\nLUPON  DAVAO ORIENTAL`;
const storeFooterSample = `Please present this receipt for\nmerchandise return and exchange.\n" TO SERVE YOU BETTER, we would like\nto hear from you. Text FEEDBACK 10001\n[Your Message] to 0915-0647862"\nThis is your Official Receipt!`;
const serialSample = "50026B7380FEF0AF";
const minSample = "21092813341970174";
const fpSample = "FP092021127030197400065";

// Default settings
const defaultSettings = {
    char_width: 42,
    font_size: 12.5,
    line_height: 1.25,
    font_family: 'thermal',
    font_weight: 'bold',
    receipt_padding_x: 10,
    receipt_padding_y: 4,
    header_top_space: 1,
    cut_margin_bottom: 4,
    reprint_line_count: 2,
    reprint_top_space: 1,
    reprint_between_space: 0,
    reprint_bottom_space: 1,
    reprint_asterisk_space: 1,
    reprint_char_space: 1,
    col_qty_width: 4,
    col_sku_width: 10,
    col_price_width: 8,
    col_amt_width: 9,
    col_subtotal_label: 26,
    col_subtotal_amt: 14,
    col_total_label: 26,
    col_total_amt: 14,
    col_tender_label: 26,
    col_tender_amt: 14,
    col_change_label: 16,
    col_change_amt: 14,
    col_vat_label: 17,
    col_vat_amt: 14
};

function padCenter(str, width) {
    if (str.length >= width) return str.substring(0, width);
    const l = Math.floor((width - str.length) / 2);
    return ' '.repeat(l) + str + ' '.repeat(width - str.length - l);
}

function blankLine(width) {
    const l = Math.floor((width - 1) / 2);
    return ' '.repeat(l) + '\u00A0' + ' '.repeat(Math.max(0, width - 1 - l));
}

function padLeft(str, width) {
    str = String(str);
    if (str.length >= width) return str;
    return ' '.repeat(width - str.length) + str;
}

function padRight(str, width) {
    str = String(str);
    if (str.length >= width) return str;
    return str + ' '.repeat(width - str.length);
}

function formatNumber(num) {
    return Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderReceiptLive() {
    const W              = parseInt(document.getElementById('char_width').value) || 42;
    const fontSize       = parseFloat(document.getElementById('font_size').value) || 10;
    const lineHeight     = parseFloat(document.getElementById('line_height').value) || 1.3;
    const paddingX       = parseInt(document.getElementById('receipt_padding_x').value) || 10;
    const paddingY       = parseInt(document.getElementById('receipt_padding_y').value) || 4;

    const repLineCount   = parseInt(document.getElementById('reprint_line_count')?.value) || 2;
    const repTop         = parseInt(document.getElementById('reprint_top_space')?.value) || 0;
    const repBetween     = parseInt(document.getElementById('reprint_between_space')?.value) || 0;
    const repBottom      = parseInt(document.getElementById('reprint_bottom_space')?.value) || 0;
    const repAst         = parseInt(document.getElementById('reprint_asterisk_space')?.value) || 1;
    const repChar        = parseInt(document.getElementById('reprint_char_space')?.value) || 1;

    const repChars       = ['R', 'E', 'P', 'R', 'I', 'N', 'T'];
    const repInner       = repChars.join(' '.repeat(Math.max(0, repChar)));
    const repBanner      = '***' + ' '.repeat(Math.max(0, repAst)) + repInner + ' '.repeat(Math.max(0, repAst)) + '***';

    const colQtyW        = parseInt(document.getElementById('col_qty_width').value) || 4;
    const colSkuW        = parseInt(document.getElementById('col_sku_width').value) || 10;
    const colPriceW      = parseInt(document.getElementById('col_price_width').value) || 8;
    const colAmtW        = parseInt(document.getElementById('col_amt_width').value) || 9;
    const colSubLabel    = parseInt(document.getElementById('col_subtotal_label').value) || 26;
    const colSubAmt      = parseInt(document.getElementById('col_subtotal_amt').value) || 14;
    const colTotalLabel  = parseInt(document.getElementById('col_total_label').value) || 26;
    const colTotalAmt    = parseInt(document.getElementById('col_total_amt').value) || 14;
    const colTenderLabel = parseInt(document.getElementById('col_tender_label').value) || 26;
    const colTenderAmt   = parseInt(document.getElementById('col_tender_amt').value) || 14;
    const colChangeLabel = parseInt(document.getElementById('col_change_label').value) || 16;
    const colChangeAmt   = parseInt(document.getElementById('col_change_amt').value) || 14;
    const colVatLabel    = parseInt(document.getElementById('col_vat_label').value) || 17;
    const colVatAmt      = parseInt(document.getElementById('col_vat_amt').value) || 14;

    // Update styling calibrated to 80mm thermal receipt proportions
    const pre = document.getElementById('previewContent');
    pre.style.fontSize = fontSize + 'px';
    // Calibrate on-screen line-height to match physical 203 DPI thermal dot pitch
    const screenLineHeight = (lineHeight <= 1.3) ? (lineHeight * 0.95) : (1.15 + (lineHeight - 1.0) * 0.25);
    pre.style.lineHeight = screenLineHeight;
    pre.style.letterSpacing = '0px';
    pre.style.padding = `${paddingY}px ${paddingX}px`;

    const fontFamilyVal  = document.getElementById('font_family')?.value || 'generic_text';
    const fontWeightVal  = document.getElementById('font_weight')?.value || 'bold';
    let fontCssVal = "'GenericTextOnly', 'VT323', 'Font 10 cpi', 'Font 12 cpi', 'Draft 10cpi', monospace";
    if (fontFamilyVal === 'thermal' || fontFamilyVal === 'consolas') fontCssVal = "'FontA11', 'FontA', 'EPSON Thermal', 'Consolas', 'Lucida Console', monospace";
    else if (fontFamilyVal === 'lucida') fontCssVal = "'Lucida Console', Monaco, 'Courier New', monospace";
    else if (fontFamilyVal === 'courier') fontCssVal = "'Courier New', Courier, monospace";

    pre.style.fontFamily = fontCssVal;
    if (fontFamilyVal === 'generic_text') {
        pre.style.fontWeight = 'normal'; // VT323 is single-weight, browser faux-bold smudges pixel dots
    } else {
        pre.style.fontWeight = fontWeightVal === 'normal' ? 'normal' : 'bold';
    }

    const lines = [];
    const divAster = '*'.repeat(W);
    const divDash  = '-'.repeat(W);
    const divDbl   = '='.repeat(W);

    // Very Top Header Spacing
    const headerTop = parseInt(document.getElementById('header_top_space')?.value) || 0;
    for (let i = 0; i < headerTop; i++) {
        lines.push(blankLine(W));
    }

    // Top Header
    lines.push(divAster);
    storeHeaderSample.split('\n').forEach(l => {
        if (l.trim()) lines.push(padCenter(l.trim(), W));
    });
    lines.push(padCenter('SERIAL#' + serialSample, W));
    lines.push(padCenter('MIN' + minSample, W));
    lines.push(divAster);

    // Reprint Banner — same loop logic as PHP
    for (let i = 0; i < repTop; i++) {
        lines.push(blankLine(W));
    }
    for (let r = 0; r < repLineCount; r++) {
        if (r > 0) {
            for (let i = 0; i < repBetween; i++) {
                lines.push(blankLine(W));
            }
        }
        lines.push(padCenter(repBanner, W));
    }
    for (let i = 0; i < repBottom; i++) {
        lines.push(blankLine(W));
    }

    // Customer
    lines.push('Member # : ' + (sampleEntryData.member_number || ''));
    lines.push('Name     : ' + (sampleEntryData.customer_name || ''));
    lines.push('Address  : ' + (sampleEntryData.customer_address || ''));
    lines.push('TIN      : ' + (sampleEntryData.customer_tin || ''));
    lines.push(blankLine(W));

    // Table Header
    lines.push(padLeft('QTY', colQtyW) + '  ' + padRight('ITEM', colSkuW) + '  ' + padLeft('PRICE', colPriceW) + '  ' + padLeft('TOTAL', colAmtW));
    lines.push(padLeft('-'.repeat(Math.min(colQtyW, 3)), colQtyW) + '  ' + padRight('-'.repeat(Math.min(colSkuW, 5)), colSkuW) + '  ' + padLeft('-'.repeat(Math.min(colPriceW, 5)), colPriceW) + '  ' + padLeft('-'.repeat(Math.min(colAmtW, 5)), colAmtW));

    // Items
    let items = [];
    try { items = typeof sampleEntryData.items === 'string' ? JSON.parse(sampleEntryData.items) : sampleEntryData.items; } catch(e){}
    if (!Array.isArray(items) || items.length === 0) {
        items = [{ sku: '10107937', description: 'FUNKY FRNCH FRY SWT CHDR 25G', quantity: 2, unit_price: 6.10, amount: 12.20 }];
    }

    let totalQty = 0;
    items.forEach(itm => {
        const qty = parseFloat(itm.quantity) || 1;
        totalQty += Math.abs(qty);
        const amt = parseFloat(itm.amount) || 0;
        const uPrice = parseFloat(itm.unit_price) || (qty > 0 ? amt / qty : amt);
        const sku = padRight(String(itm.sku || '').padStart(9, '0'), colSkuW);
        const desc = (itm.description || '').substring(0, W - 4);
        const amtStr = formatNumber(amt) + 'V';

        lines.push(padLeft(Math.floor(qty), colQtyW) + '  ' + sku + '  ' + padLeft(formatNumber(uPrice), colPriceW) + '  ' + padLeft(amtStr, colAmtW));
        lines.push('     ' + desc);
    });

    // Totals
    const subtotal = parseFloat(sampleEntryData.subtotal) || 12.20;
    const totalAmount = parseFloat(sampleEntryData.total_amount) || subtotal;
    lines.push(padLeft('Sub Total', colSubLabel) + padLeft('P' + formatNumber(subtotal), colSubAmt));
    lines.push(padLeft('Total', colTotalLabel) + padLeft('P' + formatNumber(totalAmount), colTotalAmt));

    // Tenders & Change
    let tenders = [];
    try { tenders = typeof sampleEntryData.tenders === 'string' ? JSON.parse(sampleEntryData.tenders) : sampleEntryData.tenders; } catch(e){}
    let posTenders = [];
    let negChangeSum = 0;
    if (Array.isArray(tenders)) {
        tenders.forEach(t => {
            const a = parseFloat(t.amount) || 0;
            if (a < 0) negChangeSum += Math.abs(a);
            else posTenders.push(t);
        });
    }
    posTenders.sort((a, b) => {
        const aC = (a.name || '').toLowerCase() === 'cash';
        const bC = (b.name || '').toLowerCase() === 'cash';
        return aC === bC ? 0 : (aC ? 1 : -1);
    });

    if (posTenders.length === 0) {
        lines.push(padLeft('Cash', colTenderLabel) + padLeft('P' + formatNumber(totalAmount), colTenderAmt));
    } else {
        posTenders.forEach(t => {
            lines.push(padLeft(t.name || 'Cash', colTenderLabel) + padLeft('P' + formatNumber(parseFloat(t.amount) || 0), colTenderAmt));
        });
    }

    const change = negChangeSum > 0 ? negChangeSum : 0;
    const changeFormatted = change > 0 ? 'P-' + formatNumber(change) : 'P0.00';
    lines.push(padLeft('CHANGE', colChangeLabel) + '  ====>' + padLeft(changeFormatted, colChangeAmt));

    // VAT
    const vatable = parseFloat(sampleEntryData.vatable_sales) || 10.89;
    const vat = parseFloat(sampleEntryData.total_vat) || 1.31;
    lines.push(padRight('VATable Sales  :', colVatLabel) + padLeft('P' + formatNumber(vatable), colVatAmt));
    lines.push(padRight('VAT            :', colVatLabel) + padLeft('P' + formatNumber(vat), colVatAmt));
    lines.push(padRight('VAT Exempt Sale:', colVatLabel) + padLeft('P0.00', colVatAmt));
    lines.push(padRight('Zero Rated Sale:', colVatLabel) + padLeft('P0.00', colVatAmt));
    lines.push('Cashier: ' + (sampleEntryData.sales_associate || 'Danna Mae D.'));

    lines.push(divDash);
    storeFooterSample.split('\n').forEach(l => {
        if (l.trim()) lines.push(l.trim());
    });
    lines.push(divDbl);

    // Trx Summary
    const trxNo = sampleEntryData.transaction_number || '2066';
    const stCode = sampleEntryData.store_code || '10012';
    const regNo = sampleEntryData.register_number || '115';
    const tillNo = sampleEntryData.till_number || '6356';
    const invNo = String(sampleEntryData.invoice_number || '9008137').padStart(8, '0');
    lines.push(`T#${trxNo} S${stCode} Reg${regNo}/${tillNo}  06/29/26 11:57`);
    lines.push(`Invoice #: ${invNo}    TQty:==>   ${totalQty}`);
    lines.push(storeHeaderSample.split('\n')[1] || 'LTS Retail Specialists, Inc.');
    lines.push(storeHeaderSample.split('\n')[3] || 'Ramon Magsaysay Avenue, Davao City');
    lines.push(storeHeaderSample.split('\n')[2] || 'TIN 006-171-689-065');
    lines.push('Acc#113005989925000490139053');
    lines.push('December 29, 2010');
    lines.push(fpSample);

    const cutFeed = parseInt(document.getElementById('cut_margin_bottom')?.value) || 4;
    for (let i = 0; i < cutFeed; i++) {
        lines.push(blankLine(W));
    }

    pre.textContent = lines.join('\n');
}

// Toast helper
let toastTimeout = null;
function showSaveToast(msg = '✓ Receipt Print Settings saved!') {
    const toast = document.getElementById('saveToast');
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('show');
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 2000);
}

// Collect settings object from DOM
function getSettingsFromDom() {
    const data = {};
    const sliders = document.querySelectorAll('#receiptSettingsForm input[type="range"]');
    sliders.forEach(s => {
        data[s.name || s.id] = parseFloat(s.value);
    });
    const selects = document.querySelectorAll('#receiptSettingsForm select');
    selects.forEach(s => {
        data[s.name || s.id] = s.value;
    });
    return data;
}

let autoSaveTimer = null;
async function saveSettingsAjax(quiet = true) {
    const payload = getSettingsFromDom();
    try {
        const resp = await fetch('receipt_save_layout_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const res = await resp.json();
        if (res.status === 'success') {
            showSaveToast(quiet ? '✓ Auto-saved in background' : '✓ Receipt Print Settings saved!');
            return true;
        } else {
            if (!quiet) alert('Save failed: ' + (res.message || 'Unknown error'));
            return false;
        }
    } catch(err) {
        console.error('Save error:', err);
        return false;
    }
}

// Bind live slider listeners
document.addEventListener('DOMContentLoaded', () => {
    const sliders = document.querySelectorAll('input[type="range"]');
    sliders.forEach(s => {
        const valBadge = document.getElementById('val_' + s.id);
        const updateVal = () => {
            if (valBadge) {
                if (s.id === 'font_size' || s.id === 'receipt_padding_x' || s.id === 'receipt_padding_y') {
                    valBadge.textContent = s.value + 'px';
                } else if (s.id === 'cut_margin_bottom' || s.id === 'header_top_space' || s.id === 'reprint_top_space' || s.id === 'reprint_between_space' || s.id === 'reprint_bottom_space') {
                    valBadge.textContent = s.value + ' lines';
                } else if (s.id === 'reprint_asterisk_space' || s.id === 'reprint_char_space') {
                    valBadge.textContent = s.value + ' spaces';
                } else {
                    valBadge.textContent = s.value;
                }
            }
            renderReceiptLive();

            // Trigger background auto-save
            if (autoSaveTimer) clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                saveSettingsAjax(true);
            }, 500);
        };
        s.addEventListener('input', updateVal);
        s.addEventListener('change', updateVal);
    });

    // Bind select listeners (Font Style, Font Weight)
    document.querySelectorAll('#receiptSettingsForm select').forEach(sel => {
        sel.addEventListener('change', () => {
            renderReceiptLive();
            if (autoSaveTimer) clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                saveSettingsAjax(true);
            }, 500);
        });
    });

    // Form submit via AJAX
    const form = document.getElementById('receiptSettingsForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                const origText = btn.textContent;
                btn.textContent = '⏳ Saving...';
                btn.disabled = true;
                await saveSettingsAjax(false);
                btn.textContent = origText;
                btn.disabled = false;
            } else {
                await saveSettingsAjax(false);
            }
        });
    }

    // Intercept View Print Output clicks to ensure changes are written first and navigate in same tab
    document.querySelectorAll('a[href^="receipt_print.php"]').forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            const href = link.href;
            if (autoSaveTimer) clearTimeout(autoSaveTimer);
            await saveSettingsAjax(true);
            window.location.href = href;
        });
    });

    // Reset Defaults
    document.getElementById('btnResetDefaults')?.addEventListener('click', async () => {
        if (confirm('Reset all layout and spacing settings to recommended 80mm defaults?')) {
            for (const [key, val] of Object.entries(defaultSettings)) {
                const el = document.getElementById(key);
                if (el) {
                    el.value = val;
                    const valBadge = document.getElementById('val_' + key);
                    if (valBadge) {
                        if (key === 'font_size' || key === 'receipt_padding_x' || key === 'receipt_padding_y') {
                            valBadge.textContent = val + 'px';
                        } else if (key === 'cut_margin_bottom' || key === 'reprint_line_count' || key === 'reprint_top_space' || key === 'reprint_between_space' || key === 'reprint_bottom_space') {
                            valBadge.textContent = val + ' lines';
                        } else if (key === 'reprint_asterisk_space' || key === 'reprint_char_space') {
                            valBadge.textContent = val + ' spaces';
                        } else {
                            valBadge.textContent = val;
                        }
                    }
                }
            }
            renderReceiptLive();
            await saveSettingsAjax(false);
        }
    });

    renderReceiptLive();
});
</script>
</body>
</html>
