<?php
// print_layout.php - 2D grid layout with arrow-button controls
require_once __DIR__ . '/auth.php';
requireNavAccess('layout');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/raw_print_service.php';

$rawPrinters = getAvailableRawPrinters();
$preferredPrinter = getPreferredThermalPrinter();
$currentPage = 'layout';

// Check if we are saving the layout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_layout') {
    if (isset($_POST['layout_data'])) {
        $postedLayout = json_decode($_POST['layout_data'], true);
        if (is_array($postedLayout)) {
            $newLayout = [];
            $customCounter = 1;
            foreach ($postedLayout as $row) {
                $newRow = [];
                foreach ($row as $field) {
                    if (is_array($field) && isset($field['key'])) {
                        $key = $field['key'];
                        $label = $field['label'] ?? '';
                        if (strpos($key, '_custom_') === 0) {
                            $key = '_custom_' . $customCounter;
                            $customCounter++;
                        }
                        $newRow[] = [
                            'key'            => $key,
                            'label'          => $label,
                            'flex'           => (int)($field['flex'] ?? 1),
                            'align'          => $field['align'] ?? 'left',
                            'margin_left'    => (int)($field['margin_left'] ?? 0),
                            'margin_right'   => (int)($field['margin_right'] ?? 0),
                            'font_size'      => (int)($field['font_size'] ?? 13),
                            'spacing_top'    => max(0, (int)($field['spacing_top'] ?? 0)),
                            'spacing_bottom' => max(0, (int)($field['spacing_bottom'] ?? 0)),
                        ];
                    }
                }
                if (!empty($newRow)) {
                    $newLayout[] = $newRow;
                }
            }
            savePrintLayout($newLayout);
            
            // Also save spacing and thermal roll settings!
            if (isset($_POST['receipt_width'])) {
                $newSettings = [
                    'line_spacing'      => (int)($_POST['line_spacing'] ?? 4),
                    'field_spacing'     => (int)($_POST['field_spacing'] ?? 9),
                    'receipt_width'     => (int)($_POST['receipt_width'] ?? 300),
                    'char_width'        => (int)($_POST['char_width'] ?? 32),
                    'font_size'         => (int)($_POST['font_size'] ?? 11),
                    'header_top_margin' => (int)($_POST['header_top_margin'] ?? 2),
                    'cut_margin_bottom' => (int)($_POST['cut_margin_bottom'] ?? 0),
                    'line_height'       => max(0.9, min(3.0, (float)($_POST['line_height'] ?? 1.3))),
                    'receipt_padding_y' => max(0, min(80, (int)($_POST['receipt_padding_y'] ?? 10)))
                ];
                savePrintSettings($newSettings);
            }
            
            if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
                exit;
            }

            $layoutSaved = true;
        }
    }
}
$currentLayout = getPrintLayout();
$settings = getPrintSettings();
$allActiveTenders = getUniqueTendersFromEntries(); // Tenders actually used in entries records

$stmt = $pdo->query("SELECT * FROM entries ORDER BY id DESC LIMIT 1");
$latestEntry = $stmt->fetch(PDO::FETCH_ASSOC);

// Order active tenders so those in the latest entry come first to align indexing
$latestTenders = [];
if ($latestEntry && $latestEntry['tender'] !== '') {
    $decoded = json_decode($latestEntry['tender'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $t) {
            if (isset($t['name']) && trim($t['name']) !== '') {
                $latestTenders[] = trim($t['name']);
            }
        }
    }
}
$masterTenders = getAllTenderMasters();
$masterTenderNames = array_map(fn($t) => $t['tender_name'], $masterTenders);
$activeTenders = array_values(array_unique(array_filter(array_merge($latestTenders, $allActiveTenders, $masterTenderNames))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Zread Print Layout - Electronic Journal</title>
<link rel="icon" type="image/png" href="logo.png">
<link rel="stylesheet" href="style.css">
<style>
  .layout-row {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 6px;
      padding: 10px 14px;
      margin-bottom: 8px;
      cursor: grab;
      transition: border-color 0.2s, background-color 0.2s;
  }
  .layout-row.dragging {
      opacity: 0.4;
      border-color: #3b82f6;
  }
  .layout-row.selected {
      outline: 2px solid #3b82f6;
      background: #eff6ff;
  }
  .row-label {
      font-size: 12px;
      font-weight: bold;
      color: #64748b;
      min-width: 60px;
      user-select: none;
  }
  .row-items {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      flex: 1;
      min-height: 40px;
      align-items: center;
  }
  .layout-field {
      background: #fff;
      border: 1px solid #e2e8f0;
      padding: 6px 12px;
      border-radius: 18px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: grab;
      transition: background-color 0.2s, box-shadow 0.2s;
      white-space: nowrap;
      user-select: none;
  }
  .layout-field.dragging {
      opacity: 0.4;
  }
  .layout-field.selected {
      outline: 2px solid #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  }
  .layout-field:hover {
      background: #f1f5f9;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  }
  
  .remove-field-btn {
      background: none;
      border: none;
      color: #ef4444;
      cursor: pointer;
      font-size: 14px;
      padding: 0 4px;
      margin-left: 4px;
      line-height: 1;
      display: inline-flex;
      align-items: center;
  }
  .remove-field-btn:hover {
      color: #b91c1c;
  }
  #preview-pane .print-row span {
      cursor: pointer;
      padding: 2px 4px;
      border-radius: 3px;
      transition: outline 0.12s, background 0.12s;
  }
  #preview-pane .print-row span:hover {
      outline: 1px dashed #94a3b8;
      background: rgba(100,100,255,0.04);
  }
  #preview-pane .print-row span.preview-selected {
      outline: 2px solid #3b82f6 !important;
      background: rgba(59,130,246,0.10) !important;
  }
  #preview-field-panel {
      margin-top: 14px;
      padding: 16px 20px;
      border: 1.5px solid #3b82f6;
      border-radius: 8px;
      background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%);
      animation: slideIn 0.18s ease;
  }
  @keyframes slideIn {
      from { opacity: 0; transform: translateY(-6px); }
      to   { opacity: 1; transform: translateY(0); }
  }
  #preview-field-panel h4 {
      margin: 0 0 14px 0;
      font-size: 13px;
      color: #1e40af;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 6px;
  }
  .pfp-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 14px;
  }
  .pfp-ctrl label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      color: #475569;
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
  }
  .pfp-ctrl input[type=range] { width: 100%; accent-color: #3b82f6; }
  .pfp-ctrl select {
      width: 100%;
      padding: 6px 8px;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      font-size: 13px;
      background: white;
  }
  .pfp-val {
      display: inline-block;
      font-weight: 700;
      color: #1d4ed8;
      min-width: 30px;
      text-align: right;
  }
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
      grid-template-columns: 1fr 140px 65px;
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
      white-space: nowrap;
  }
  .preview-pane-sticky {
      position: sticky;
      top: 20px;
      background: #ffffff;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      padding: 20px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      display: flex;
      flex-direction: column;
      align-items: center;
      max-height: calc(100vh - 40px);
      overflow-y: auto;
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
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Zread Print Layout</h1>
            <p>Customize the thermal paper settings and field format for printed Z-Read receipts.</p>
        </div>

        <?php if (isset($layoutSaved)): ?>
            <div class="success" style="margin-bottom: 16px;">✓ Print layout saved successfully.</div>
        <?php endif; ?>

        <form method="POST" action="print_layout.php" id="layout-form" onsubmit="saveLayoutForm(event)">
            <input type="hidden" name="action" value="save_layout">
            <input type="hidden" name="layout_data" id="layout_data" value="">
            
            <div class="layout-grid">
                <!-- Left: Sliders Panel (460px) -->
                <div class="panel-sliders">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h2 style="font-size:16px; margin:0; color:#0f172a;">🧱 Row &amp; Field Arrangement</h2>
                        <div style="display:flex; gap:6px;">
                            <button type="button" onclick="undoChanges()" class="btn-secondary" style="padding:6px 14px; font-size:12px; border-radius:20px; margin-top:0;">↩ Undo</button>
                            <button type="button" id="btn-save-layout-top" onclick="saveLayoutForm(event)" class="btn" style="background:#7c3aed; color:#fff; padding:6px 16px; font-weight:700; border:none; border-radius:20px; cursor:pointer; font-size:12px; margin-top:0;">💾 Save Layout</button>
                        </div>
                    </div>

                    <!-- Add Fields Section -->
                    <div class="slider-group" style="margin-bottom:14px; padding-bottom:12px;">
                        <div class="group-title">➕ Add Fields to Receipt</div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                            <button type="button" class="btn-secondary" onclick="addCustomLine()" style="border-radius:20px; padding:7px 16px; font-size:12px; margin-top:0;">+ Custom Text</button>
                            <select id="db-field-select" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 20px; font-size: 13px; background: white; flex: 1; min-width: 170px; height: 34px;">
                                <option value="">-- Add DB Field --</option>
                                <option value="store_number:Store:">Store No.</option>
                                <option value="register_number:Reg:">Register No.</option>
                                <option value="last_trx_number:Last Trx #:">Last Trx#</option>
                                <option value="entry_date:Date">Date</option>
                                <option value="entry_time:Time">Time</option>
                                <option value="zread_number:Z No:">Z-Read No.</option>
                                <option value="till_number:Till No.">Till No. (Cashier)</option>
                                
                                <optgroup label="Tenders">
                                    <option value="tender:Tenders (All in Entry)">Tenders (All in Entry)</option>
                                    <?php foreach ($activeTenders as $index => $tName): ?>
                                        <?php $num = $index + 1; ?>
                                        <option value="tender_<?php echo $num; ?>:<?php echo htmlspecialchars($tName); ?>"><?php echo htmlspecialchars($tName); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <optgroup label="Sales &amp; VAT">
                                    <option value="total_vat:Total VAT">Total VAT</option>
                                    <option value="total_non_vat:Total Non-VAT">Total Non-VAT</option>
                                    <option value="daily_sales:Daily Sales">Daily Sales</option>
                                </optgroup>
                                
                                <optgroup label="Grand Totals">
                                    <option value="old_grand_total:Old Grand Total:">Old Grand Total</option>
                                    <option value="new_grand_total:New Grand Total:">New Grand Total</option>
                                    <option value="created_at:Saved On">Saved On</option>
                                </optgroup>
                            </select>
                            <button type="button" class="btn-secondary" onclick="addDbField()" style="border-radius:20px; padding:7px 16px; font-size:12px; margin-top:0;">+ Add Field</button>
                        </div>
                    </div>

                    <!-- Row Grid Arrangement (Now on Top) -->
                    <div class="slider-group">
                        <div class="group-title">🧱 Row Order &amp; Columns (Drag to Reorder)</div>
                        <div id="layout-grid" style="max-height: 520px; overflow-y: auto; padding-right: 4px; display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach ($currentLayout as $rowIndex => $rowItems): ?>
                                <div class="layout-row">
                                    <div class="row-label">Row <?php echo $rowIndex + 1; ?></div>
                                    <div class="row-items">
                                        <?php foreach ($rowItems as $field): ?>
                                            <div class="layout-field">
                                                <?php 
                                                  $displayLabel = $field['label'];
                                                  if (preg_match('/^tender_(\d+)$/', $field['key'], $m)) {
                                                      $tSlot = (int)$m[1] - 1;
                                                      if (empty($displayLabel) || preg_match('/^Tender \d+$/i', $displayLabel)) {
                                                          if (isset($activeTenders[$tSlot])) {
                                                              $displayLabel = $activeTenders[$tSlot];
                                                          }
                                                      }
                                                  }
                                                  $fieldObj = [
                                                      'key' => $field['key'],
                                                      'label' => $displayLabel,
                                                      'flex' => (int)($field['flex'] ?? 1),
                                                      'align' => $field['align'] ?? 'left',
                                                      'margin_left' => (int)($field['margin_left'] ?? 0),
                                                      'margin_right' => (int)($field['margin_right'] ?? 0),
                                                      'font_size' => (int)($field['font_size'] ?? 13),
                                                      'spacing_top' => (int)($field['spacing_top'] ?? 0),
                                                      'spacing_bottom' => (int)($field['spacing_bottom'] ?? 0)
                                                  ];
                                                  $jsonVal = json_encode($fieldObj, JSON_HEX_APOS | JSON_HEX_TAG);
                                                ?>
                                                <?php if (strpos($field['key'], '_custom_') === 0): ?>
                                                    <input type="text" class="custom-input" value="<?php echo htmlspecialchars($field['label']); ?>" oninput="updateHidden(this)">
                                                    <input type="hidden" class="field-data" value='<?php echo $jsonVal; ?>'>
                                                <?php else: ?>
                                                    <span class="field-label"><?php echo htmlspecialchars($displayLabel); ?></span>
                                                    <input type="hidden" class="field-data" value='<?php echo $jsonVal; ?>'>
                                                <?php endif; ?>
                                                <button type="button" class="remove-field-btn" onclick="removeField(this)" title="Remove">✕</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Field settings panel: initially hidden -->
                        <div id="field-settings-panel" style="display: none; margin-top: 16px; padding: 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc;">
                            <h3 style="margin-top: 0; font-size: 13px; color: #1e293b; font-weight: bold; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 10px;">Field Settings: <span id="settings-field-name" style="color: #3b82f6;">None</span></h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="font-size: 11px; color: #475569; font-weight: bold; display: block; margin-bottom: 4px;">Alignment</label>
                                    <select id="field-align" onchange="updateSelectedFieldSetting()" style="width:100%; padding: 6px; border: 1px solid #cbd5e1; border-radius:4px; font-size: 12px; background: white;">
                                        <option value="left">Left</option>
                                        <option value="center">Center</option>
                                        <option value="right">Right</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 11px; color: #475569; font-weight: bold; display: block; margin-bottom: 4px;">Flex Width: <span id="field-flex-val">1</span></label>
                                    <input type="range" id="field-flex" min="1" max="5" value="1" oninput="updateSelectedFieldSetting()" style="width: 100%;">
                                </div>
                                <div>
                                    <label style="font-size: 11px; color: #475569; font-weight: bold; display: block; margin-bottom: 4px;">Font Size: <span id="field-font-size-val">13</span>px</label>
                                    <input type="range" id="field-font-size" min="10" max="24" value="13" oninput="updateSelectedFieldSetting()" style="width: 100%;">
                                </div>
                                <div>
                                    <label style="font-size: 11px; color: #475569; font-weight: bold; display: block; margin-bottom: 4px;">Margin Left: <span id="field-margin-left-val">0</span>px</label>
                                    <input type="range" id="field-margin-left" min="-40" max="40" value="0" oninput="updateSelectedFieldSetting()" style="width: 100%;">
                                </div>
                                <div style="grid-column: 1 / -1;">
                                    <label style="font-size: 11px; color: #475569; font-weight: bold; display: block; margin-bottom: 4px;">Margin Right: <span id="field-margin-right-val">0</span>px</label>
                                    <input type="range" id="field-margin-right" min="-40" max="40" value="0" oninput="updateSelectedFieldSetting()" style="width: 100%;">
                                </div>
                                <!-- Row-level spacing (applies to entire row) -->
                                <div style="grid-column: 1 / -1; border-top: 1px solid #e2e8f0; margin-top: 6px; padding-top: 10px;">
                                    <div style="font-size: 11px; font-weight: 700; color: #7c3aed; margin-bottom: 8px; letter-spacing: 0.04em; text-transform: uppercase;">↕ Row Spacing (blank lines)</div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div>
                                            <label style="font-size: 11px; color: #475569; font-weight: bold; display: block; margin-bottom: 4px;">Space Above Row: <span id="field-spacing-top-val">0</span></label>
                                            <input type="range" id="field-spacing-top" min="0" max="5" value="0" oninput="updateSelectedFieldSetting()" style="width: 100%; accent-color: #7c3aed;">
                                        </div>
                                        <div>
                                            <label style="font-size: 11px; color: #475569; font-weight: bold; display: block; margin-bottom: 4px;">Space Below Row: <span id="field-spacing-bottom-val">0</span></label>
                                            <input type="range" id="field-spacing-bottom" min="0" max="5" value="0" oninput="updateSelectedFieldSetting()" style="width: 100%; accent-color: #7c3aed;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- End slider-group -->

                    <!-- Inline preview field control panel (moved to left) -->
                    <div id="preview-field-panel" style="display:none; margin-top: 16px; padding: 16px 20px; border: 1.5px solid #3b82f6; border-radius: 8px; background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%); animation: slideIn 0.18s ease;">
                        <h4>&#9881; Preview Field Settings: <span id="pfp-field-name" style="color:#1d4ed8;">—</span>
                            <button type="button" onclick="closePfp()" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:16px;color:#64748b;" title="Close">✕</button>
                        </h4>
                        <div class="pfp-grid">
                            <div class="pfp-ctrl" style="grid-column: 1 / -1;">
                                <label>Label / Display Text</label>
                                <input type="text" id="pfp-label"
                                       placeholder="Enter field label..."
                                       oninput="syncPfp()"
                                       style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:5px; font-size:13px; box-sizing:border-box; font-family:inherit;">
                            </div>
                            <div class="pfp-ctrl">
                                <label>Position Left &nbsp;<span class="pfp-val" id="pfp-ml-val">0</span>px</label>
                                <input type="range" id="pfp-ml" min="-100" max="100" value="0" oninput="syncPfp()">
                            </div>
                            <div class="pfp-ctrl">
                                <label>Position Right &nbsp;<span class="pfp-val" id="pfp-mr-val">0</span>px</label>
                                <input type="range" id="pfp-mr" min="-100" max="100" value="0" oninput="syncPfp()">
                            </div>
                            <div class="pfp-ctrl">
                                <label>Font Size &nbsp;<span class="pfp-val" id="pfp-fs-val">13</span>px</label>
                                <input type="range" id="pfp-fs" min="8" max="28" value="13" oninput="syncPfp()">
                            </div>
                            <div class="pfp-ctrl">
                                <label>Flex Width &nbsp;<span class="pfp-val" id="pfp-flex-val">1</span></label>
                                <input type="range" id="pfp-flex" min="1" max="6" value="1" oninput="syncPfp()">
                            </div>
                            <div class="pfp-ctrl">
                                <label>Text Align</label>
                                <select id="pfp-align" onchange="syncPfp()">
                                    <option value="left">Left</option>
                                    <option value="center">Center</option>
                                    <option value="right">Right</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Roll & Typography Settings (Now Below Row Arrangement) -->
                    <div class="slider-group" style="margin-top: 16px;">
                        <div class="group-title">🖨️ Thermal Roll &amp; Spacing Controls</div>

                        <div class="slider-row">
                            <label for="char_width">Roll Characters per Line</label>
                            <input type="range" id="char_width" name="char_width" min="28" max="44" value="<?php echo htmlspecialchars($settings['char_width'] ?? 32); ?>" oninput="updateSpacingVal('char_width')">
                            <span class="slider-val-badge" id="char_width_val"><?php echo htmlspecialchars($settings['char_width'] ?? 32); ?> chars</span>
                        </div>

                        <div class="slider-row">
                            <label for="font_size">Font Size</label>
                            <input type="range" id="font_size" name="font_size" min="9" max="18" value="<?php echo htmlspecialchars($settings['font_size'] ?? 11); ?>" oninput="updateSpacingVal('font_size')">
                            <span class="slider-val-badge" id="font_size_val"><?php echo htmlspecialchars($settings['font_size'] ?? 11); ?>px</span>
                        </div>

                        <div class="slider-row">
                            <label for="line_height">Line Height</label>
                            <input type="range" id="line_height" name="line_height" min="1.0" max="3.0" step="0.05" value="<?php echo (float)($settings['line_height'] ?? 1.3); ?>" oninput="updateSpacingVal('line_height')">
                            <span class="slider-val-badge" id="line_height_val"><?php echo (float)($settings['line_height'] ?? 1.3); ?></span>
                        </div>

                        <div class="slider-row">
                            <label for="receipt_padding_y">Vertical Padding (px)</label>
                            <input type="range" id="receipt_padding_y" name="receipt_padding_y" min="0" max="80" value="<?php echo (int)($settings['receipt_padding_y'] ?? 10); ?>" oninput="updateSpacingVal('receipt_padding_y')">
                            <span class="slider-val-badge" id="receipt_padding_y_val"><?php echo (int)($settings['receipt_padding_y'] ?? 10); ?>px</span>
                        </div>

                        <div class="slider-row">
                            <label for="receipt_width">Preview Width</label>
                            <input type="range" id="receipt_width" name="receipt_width" min="200" max="450" value="<?php echo htmlspecialchars($settings['receipt_width'] ?? 340); ?>" oninput="updateSpacingVal('receipt_width')">
                            <span class="slider-val-badge" id="receipt_width_val"><?php echo htmlspecialchars($settings['receipt_width'] ?? 340); ?>px</span>
                        </div>

                        <div class="slider-row">
                            <label for="header_top_margin">Header Top Feed</label>
                            <input type="range" id="header_top_margin" name="header_top_margin" min="0" max="10" value="<?php echo htmlspecialchars($settings['header_top_margin'] ?? 2); ?>" oninput="updateSpacingVal('header_top_margin')">
                            <span class="slider-val-badge" id="header_top_margin_val"><?php echo htmlspecialchars($settings['header_top_margin'] ?? 2); ?> lines</span>
                        </div>

                        <div class="slider-row">
                            <label for="cut_margin_bottom">Paper Cut Feed</label>
                            <input type="range" id="cut_margin_bottom" name="cut_margin_bottom" min="0" max="10" value="<?php echo htmlspecialchars($settings['cut_margin_bottom'] ?? 0); ?>" oninput="updateSpacingVal('cut_margin_bottom')">
                            <span class="slider-val-badge" id="cut_margin_bottom_val"><?php echo htmlspecialchars($settings['cut_margin_bottom'] ?? 0); ?> lines</span>
                        </div>
                    </div>

                    <!-- Save & Undo Actions -->
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="button" id="btn-save-layout" onclick="saveLayoutForm(event)" class="btn" style="background:#7c3aed; color:#fff; padding: 10px 24px; font-weight: 700; border: none; border-radius: 20px; flex: 1; cursor: pointer; margin-top:0;">💾 Save Print Layout</button>
                        <button type="button" class="btn-secondary" onclick="undoChanges()" style="padding: 10px 20px; border-radius: 20px; margin-top:0;">↩ Undo</button>
                    </div>
                </div> <!-- End Left Column: panel-sliders -->

                <!-- Right Column: Sticky Real-time Preview Pane -->
                <div class="preview-pane-sticky">
                    <div class="preview-header">
                        <div style="font-weight: 700; font-size: 14px; color: #0f172a;">
                            🧾 Real-Time Z-Read Preview
                        </div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <select id="layout_direct_printer" style="padding:6px 12px; font-size:12px; border:1px solid #cbd5e1; border-radius:18px; background:#1e293b; color:#fff; font-weight:600; cursor:pointer;">
                                <?php if (empty($rawPrinters)): ?>
                                    <option value="EPSON TM-T82 Receipt">🖨️ EPSON TM-T82 (Default)</option>
                                <?php else: ?>
                                    <?php foreach ($rawPrinters as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p); ?>" <?php if ($p === $preferredPrinter) echo 'selected'; ?>>🖨️ <?php echo htmlspecialchars($p); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <button type="button" id="btn-layout-direct" onclick="doZreadLayoutDirectPrint(this)" style="padding:6px 14px; font-size:12px; background:#16a34a; color:#fff; border:none; border-radius:20px; font-weight:700; cursor:pointer; margin-top:0;">⚡ Direct Test</button>
                            <button type="button" class="btn-secondary" onclick="previewLayout()" style="padding: 6px 14px; font-size: 12px; border-radius:20px; margin-top:0;">🔄 Refresh</button>
                        </div>
                    </div>

                    <div id="preview-pane" class="print-sheet" style="box-shadow: 0 8px 24px rgba(0,0,0,0.10); border: 1px solid #cbd5e1; border-radius: 2px;"></div>

                </div>
            </div>
        </form>
    </main>
</div>

<script>
const STORE_MASTER = <?php echo json_encode(getStoreMaster()); ?>;
const TENDER_NAMES = <?php echo json_encode($activeTenders); ?>;
const LATEST_ENTRY = <?php echo json_encode($latestEntry ?: null); ?>;

let selectedFieldEl = null;
let pfpSelected = null;

function loadFieldSettings(fieldEl) {
    selectedFieldEl = fieldEl;
    const hidden = fieldEl.querySelector('.field-data');
    if (!hidden) return;
    
    let data = {};
    try {
        data = JSON.parse(hidden.value);
    } catch(e) {
        const parts = hidden.value.split(':');
        data = {
            key: parts[0],
            label: parts[1] || '',
            flex: 1,
            align: 'left',
            margin_left: 0,
            margin_right: 0,
            font_size: 13
        };
    }
    
    document.getElementById('settings-field-name').textContent = data.label || data.key;
    document.getElementById('field-align').value = data.align || 'left';
    
    document.getElementById('field-flex').value = data.flex || 1;
    document.getElementById('field-flex-val').textContent = data.flex || 1;
    
    document.getElementById('field-font-size').value = data.font_size || 13;
    document.getElementById('field-font-size-val').textContent = data.font_size || 13;
    
    document.getElementById('field-margin-left').value = data.margin_left || 0;
    document.getElementById('field-margin-left-val').textContent = data.margin_left || 0;
    
    document.getElementById('field-margin-right').value = data.margin_right || 0;
    document.getElementById('field-margin-right-val').textContent = data.margin_right || 0;

    // Load row-level spacing from first field of row
    const rowEl2 = fieldEl.closest('.layout-row');
    let firstFieldData = data;
    if (rowEl2) {
        const firstHidden = rowEl2.querySelector('.field-data');
        if (firstHidden) { try { firstFieldData = JSON.parse(firstHidden.value); } catch(e) {} }
    }
    document.getElementById('field-spacing-top').value = firstFieldData.spacing_top || 0;
    document.getElementById('field-spacing-top-val').textContent = firstFieldData.spacing_top || 0;
    document.getElementById('field-spacing-bottom').value = firstFieldData.spacing_bottom || 0;
    document.getElementById('field-spacing-bottom-val').textContent = firstFieldData.spacing_bottom || 0;
    
    document.getElementById('field-settings-panel').style.display = 'block';

    // Synchronize highlight to right preview pane
    const rowEl = fieldEl.closest('.layout-row');
    if (rowEl) {
        const allRows = Array.from(document.querySelectorAll('.layout-row'));
        const rIdx = allRows.indexOf(rowEl);
        const rowFields = Array.from(rowEl.querySelectorAll('.layout-field'));
        const fIdx = rowFields.indexOf(fieldEl);
        if (rIdx >= 0 && fIdx >= 0) {
            document.querySelectorAll('#preview-pane .preview-selected').forEach(el => el.classList.remove('preview-selected'));
            const span = document.querySelector(`#preview-pane span[data-row-index="${rIdx}"][data-field-index="${fIdx}"]`);
            if (span) {
                span.classList.add('preview-selected');
                pfpSelected = { rowIndex: rIdx, fieldIndex: fIdx };
                span.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }
}

function updateSelectedFieldSetting() {
    if (!selectedFieldEl) return;
    const hidden = selectedFieldEl.querySelector('.field-data');
    if (!hidden) return;
    
    let data = {};
    try {
        data = JSON.parse(hidden.value);
    } catch(e) {
        const parts = hidden.value.split(':');
        data = { key: parts[0], label: parts[1] || '' };
    }
    
    data.align = document.getElementById('field-align').value;
    
    data.flex = parseInt(document.getElementById('field-flex').value) || 1;
    document.getElementById('field-flex-val').textContent = data.flex;
    
    data.font_size = parseInt(document.getElementById('field-font-size').value) || 13;
    document.getElementById('field-font-size-val').textContent = data.font_size;
    
    data.margin_left = parseInt(document.getElementById('field-margin-left').value) || 0;
    document.getElementById('field-margin-left-val').textContent = data.margin_left;
    
    data.margin_right = parseInt(document.getElementById('field-margin-right').value) || 0;
    document.getElementById('field-margin-right-val').textContent = data.margin_right;

    // Write spacing onto first field of this row
    const rowEl3 = selectedFieldEl.closest('.layout-row');
    const spacingTop = parseInt(document.getElementById('field-spacing-top').value) || 0;
    const spacingBottom = parseInt(document.getElementById('field-spacing-bottom').value) || 0;
    document.getElementById('field-spacing-top-val').textContent = spacingTop;
    document.getElementById('field-spacing-bottom-val').textContent = spacingBottom;
    if (rowEl3) {
        rowEl3.querySelectorAll('.field-data').forEach((fh, idx) => {
            try {
                const fd = JSON.parse(fh.value);
                if (idx === 0) {
                    fd.spacing_top = spacingTop;
                    fd.spacing_bottom = spacingBottom;
                } else {
                    delete fd.spacing_top;
                    delete fd.spacing_bottom;
                }
                fh.value = JSON.stringify(fd);
            } catch(e) {}
        });
    }
    
    hidden.value = JSON.stringify(data);
    previewLayout();
}

function updateHidden(inputEl) {
    const parent = inputEl.closest('.layout-field');
    const hidden = parent.querySelector('.field-data');
    
    let data = {};
    try {
        data = JSON.parse(hidden.value);
    } catch(e) {
        const parts = hidden.value.split(':');
        data = { key: parts[0], label: parts[1] || '' };
    }
    
    data.label = inputEl.value;
    hidden.value = JSON.stringify(data);
    
    if (parent.classList.contains('selected')) {
        document.getElementById('settings-field-name').textContent = data.label;
    }
    
    saveState();
    previewLayout();
}

function refreshRowLabels() {
    document.querySelectorAll('.layout-row').forEach((row, i) => {
        row.querySelector('.row-label').textContent = 'Row ' + (i + 1);
    });
}

function createEmptyRow() {
    const row = document.createElement('div');
    row.className = 'layout-row';
    row.innerHTML = `<div class="row-label">Row</div><div class="row-items"></div>`;
    return row;
}

function removeField(btn) {
    const field = btn.closest('.layout-field');
    const currentRow = field.closest('.layout-row');
    field.remove();
    const items = currentRow.querySelector('.row-items');
    if (items.querySelectorAll('.layout-field').length === 0) {
        currentRow.remove();
    }
    refreshRowLabels();
    saveState();
    previewLayout();
}

function addCustomLine() {
    const grid = document.getElementById('layout-grid');
    const newRow = createEmptyRow();
    const field = document.createElement('div');
    field.className = 'layout-field';
    const initialData = {
        key: '_custom_text',
        label: '',
        flex: 1,
        align: 'center',
        margin_left: 0,
        margin_right: 0,
        font_size: 13
    };
    field.innerHTML = `
        <input type="text" class="custom-input" placeholder="Enter text or separator..." oninput="updateHidden(this)"/>
        <input type="hidden" class="field-data" value='${JSON.stringify(initialData)}'/>
        <button type="button" class="remove-field-btn" onclick="removeField(this)" title="Remove">âœ–</button>
    `;
    newRow.querySelector('.row-items').appendChild(field);
    grid.appendChild(newRow);
    
    initDragAndDrop(newRow);
    setupRowDrop(newRow);
    initDragAndDrop(field);
    
    refreshRowLabels();
    saveState();
    previewLayout();
}

function addDbField() {
    const select = document.getElementById('db-field-select');
    if (!select.value) return;
    const [key, label] = select.value.split(':');
    
    const grid = document.getElementById('layout-grid');
    const newRow = createEmptyRow();
    const field = document.createElement('div');
    field.className = 'layout-field';
    const initialData = {
        key: key,
        label: label,
        flex: 1,
        align: 'left',
        margin_left: 0,
        margin_right: 0,
        font_size: 13
    };
    field.innerHTML = `
        <span class="field-label">${label}</span>
        <input type="hidden" class="field-data" value='${JSON.stringify(initialData)}'>
        <button type="button" class="remove-field-btn" onclick="removeField(this)" title="Remove">✕</button>
    `;
    newRow.querySelector('.row-items').appendChild(field);
    grid.appendChild(newRow);
    
    initDragAndDrop(newRow);
    setupRowDrop(newRow);
    initDragAndDrop(field);
    
    refreshRowLabels();
    saveState();
    previewLayout();
}


// Form submission — AJAX so the preview stays visible
async function saveLayoutForm(e) {
    if (e) e.preventDefault();

    const saveBtns = [document.getElementById('btn-save-layout'), document.getElementById('btn-save-layout-top')].filter(Boolean);
    const origTexts = saveBtns.map(b => b.innerHTML);
    saveBtns.forEach(b => { b.disabled = true; b.innerHTML = '⏳ Saving...'; });

    const data = [];
    document.querySelectorAll('.layout-row').forEach(row => {
        const rowData = [];
        row.querySelectorAll('.field-data').forEach(hidden => {
            try {
                rowData.push(JSON.parse(hidden.value));
            } catch(err) {
                const parts = hidden.value.split(':');
                rowData.push({
                    key: parts[0],
                    label: parts[1] || '',
                    flex: 1,
                    align: 'left',
                    margin_left: 0,
                    margin_right: 0,
                    font_size: 13
                });
            }
        });
        if (rowData.length > 0) data.push(rowData);
    });

    const body = new URLSearchParams();
    body.append('action', 'save_layout');
    body.append('ajax', '1');
    body.append('layout_data', JSON.stringify(data));
    body.append('receipt_width',     document.getElementById('receipt_width')?.value     || 340);
    body.append('char_width',        document.getElementById('char_width')?.value        || 32);
    body.append('font_size',         document.getElementById('font_size')?.value         || 11);
    body.append('header_top_margin', document.getElementById('header_top_margin')?.value ?? 2);
    body.append('cut_margin_bottom', document.getElementById('cut_margin_bottom')?.value ?? 0);
    body.append('line_height',       document.getElementById('line_height')?.value       || 1.3);
    body.append('receipt_padding_y', document.getElementById('receipt_padding_y')?.value ?? 10);

    try {
        const r = await fetch('print_layout.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: body
        });
        const res = await r.json();

        saveBtns.forEach(b => { b.innerHTML = '✓ Saved!'; });
        setTimeout(() => {
            saveBtns.forEach((b, i) => {
                b.disabled = false;
                b.innerHTML = origTexts[i];
            });
        }, 1800);

        // Show a non-intrusive toast
        let toast = document.getElementById('save-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'save-toast';
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#16a34a;color:#fff;padding:12px 24px;border-radius:6px;font-size:14px;font-weight:bold;box-shadow:0 4px 16px rgba(0,0,0,0.2);z-index:9999;transition:opacity 0.4s;';
            document.body.appendChild(toast);
        }
        toast.textContent = '✓ Print layout & settings saved!';
        toast.style.opacity = '1';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 2500);

    } catch(err) {
        saveBtns.forEach((b, i) => {
            b.disabled = false;
            b.innerHTML = origTexts[i];
        });
        alert('Save error: ' + err.message);
    }
}


/* Undo & Preview functionality */
let undoStack = [];
function saveState() {
    const data = [];
    document.querySelectorAll('.layout-row').forEach(row => {
        const rowData = [];
        row.querySelectorAll('.field-data').forEach(hidden => {
            try {
                rowData.push(JSON.parse(hidden.value));
            } catch(e) {
                const parts = hidden.value.split(':');
                rowData.push({
                    key: parts[0],
                    label: parts[1] || '',
                    flex: 1,
                    align: 'left',
                    margin_left: 0,
                    margin_right: 0,
                    font_size: 13
                });
            }
        });
        if (rowData.length > 0) data.push(rowData);
    });
    const serialized = JSON.stringify(data);
    if (undoStack.length === 0 || undoStack[undoStack.length - 1] !== serialized) {
        undoStack.push(serialized);
    }
}

function undoChanges() {
    if (undoStack.length <= 1) {
        alert('Nothing to undo.');
        return;
    }
    undoStack.pop();
    const previous = undoStack[undoStack.length - 1];
    const grid = document.getElementById('layout-grid');
    if (!previous) {
        grid.innerHTML = '';
        return;
    }
    const layout = JSON.parse(previous);
    renderLayout(layout);
}

// Render layout helper (used by preview and undo)
function renderLayout(layout) {
    const grid = document.getElementById('layout-grid');
    grid.innerHTML = '';
    layout.forEach((rowItems, rowIndex) => {
        const row = document.createElement('div');
        row.className = 'layout-row';
        row.innerHTML = `
            <div class="row-label">Row ${rowIndex + 1}</div>
            <div class="row-items"></div>
        `;
        const itemsContainer = row.querySelector('.row-items');
        rowItems.forEach(item => {
            let fieldData = {};
            if (typeof item === 'object') {
                fieldData = {
                    key: item.key,
                    label: item.label || '',
                    flex: item.flex || 1,
                    align: item.align || 'left',
                    margin_left: item.margin_left || 0,
                    margin_right: item.margin_right || 0,
                    font_size: item.font_size || 13
                };
            } else {
                const parts = item.split(':');
                fieldData = {
                    key: parts[0],
                    label: parts[1] || '',
                    flex: 1,
                    align: 'left',
                    margin_left: 0,
                    margin_right: 0,
                    font_size: 13
                };
            }
            
            const field = document.createElement('div');
            field.className = 'layout-field';
            const jsonVal = JSON.stringify(fieldData);
            
            field.innerHTML = `
                ${fieldData.key.startsWith('_custom_') ?
                    `<input type="text" class="custom-input" value="${fieldData.label}" oninput="updateHidden(this)">`
                    :
                    `<span class="field-label">${fieldData.label}</span>`}
                <input type="hidden" class="field-data" value='${jsonVal}'>
                <button type="button" class="remove-field-btn" onclick="removeField(this)" title="Remove">âœ–</button>
            `;
            itemsContainer.appendChild(field);
        });
        grid.appendChild(row);
    });
    refreshRowLabels();
    initAllDrag();
    
    document.getElementById('field-settings-panel').style.display = 'none';
    selectedFieldEl = null;
    previewLayout();
}

function updateSpacingVal(id) {
    const el = document.getElementById(id);
    const valEl = document.getElementById(id + '_val');
    if (el && valEl) {
        if (id === 'char_width') valEl.textContent = el.value + ' chars';
        else if (id === 'font_size' || id === 'receipt_width' || id === 'receipt_padding_y') valEl.textContent = el.value + 'px';
        else if (id === 'header_top_margin' || id === 'cut_margin_bottom') valEl.textContent = el.value + ' lines';
        else valEl.textContent = el.value;
    }
    previewLayout();
}

function formatPreviewDate(str) {
    if (!str) return '';
    // Handle YYYY-MM-DD
    const m1 = str.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (m1) {
        return m1[2] + '/' + m1[3] + '/' + m1[1].slice(-2);
    }
    // Handle MM/DD/YYYY
    const m2 = str.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (m2) {
        return m2[1] + '/' + m2[2] + '/' + m2[3].slice(-2);
    }
    return str;
}

function formatPreviewTime(str) {
    if (!str) return '';
    // If string has HH:MM:SS or HH:MM, return HH:MM
    const m = str.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (m) {
        const hh = m[1].padStart(2, '0');
        const mm = m[2];
        return hh + ':' + mm;
    }
    return str;
}

function formatPreview8Digits(val) {
    if (val === undefined || val === null || val === '') return '';
    const str = String(val).trim();
    if (/^\d+$/.test(str)) {
        return str.padStart(8, '0');
    }
    return str;
}

function formatPreviewReg(val, isTopHeader) {
    if (val === undefined || val === null || val === '') return '';
    const str = String(val).trim();
    if (isTopHeader && /^\d+$/.test(str) && str.length < 5) {
        return str.padStart(5, '0');
    }
    return str;
}

function previewLayout() {
    const data = [];
    document.querySelectorAll('.layout-row').forEach(row => {
        const rowData = [];
        row.querySelectorAll('.field-data').forEach(hidden => {
            try {
                rowData.push(JSON.parse(hidden.value));
            } catch(e) {
                const parts = hidden.value.split(':');
                rowData.push({
                    key: parts[0],
                    label: parts[1] || '',
                    flex: 1,
                    align: 'left',
                    margin_left: 0,
                    margin_right: 0,
                    font_size: 13
                });
            }
        });
        if (rowData.length > 0) data.push(rowData);
    });

    const preview = document.getElementById('preview-pane');
    const receiptWidth = parseInt(document.getElementById('receipt_width')?.value) || 300;
    const charWidth = parseInt(document.getElementById('char_width')?.value) || 32;
    const fontSize = parseInt(document.getElementById('font_size')?.value) || 11;
    const lineHeight = parseFloat(document.getElementById('line_height')?.value) || 1.3;
    const paddingY = parseInt(document.getElementById('receipt_padding_y')?.value) ?? 10;
    const headerTopMargin = parseInt(document.getElementById('header_top_margin')?.value) ?? 2;
    const cutMarginBottom = parseInt(document.getElementById('cut_margin_bottom')?.value) ?? 0;

    preview.style.maxWidth = receiptWidth + 'px';
    preview.style.paddingTop = paddingY + 'px';
    preview.style.paddingBottom = paddingY + 'px';
    const lineSpacing = Math.max(1, Math.round(fontSize * 0.2));
    const fieldSpacing = Math.max(4, Math.round(fontSize * 0.5));
    
    let headerHtml = '';
    for (let i = 0; i < headerTopMargin; i++) {
        headerHtml += `<div class="print-row" style="padding:${lineSpacing}px 0; min-height:${fontSize + 2}px;">&nbsp;</div>`;
    }
    if (STORE_MASTER) {
        let lines = [];
        if (STORE_MASTER.header && STORE_MASTER.header.trim()) {
            lines = STORE_MASTER.header.trim().split(/\r\n|\r|\n/);
        } else if (STORE_MASTER.store_name) {
            lines.push(STORE_MASTER.store_name);
        }
        if (STORE_MASTER.serial_number && (!STORE_MASTER.header || !STORE_MASTER.header.includes(STORE_MASTER.serial_number))) {
            lines.push('SERIAL#' + STORE_MASTER.serial_number);
        }
        if (STORE_MASTER.min_number && (!STORE_MASTER.header || !STORE_MASTER.header.includes(STORE_MASTER.min_number))) {
            lines.push(STORE_MASTER.min_number);
        }
        if (STORE_MASTER.permit_number && (!STORE_MASTER.header || !STORE_MASTER.header.includes(STORE_MASTER.permit_number))) {
            lines.push(STORE_MASTER.permit_number);
        }
        if (lines.length > 0) {
            headerHtml += `<div class="store-header" style="text-align:center;margin-bottom:8px;font-size:13px;line-height:${lineHeight};">`+
                lines.filter(l => l.trim() !== '').map(l => '<div>' + l.trim() + '</div>').join('') +
                '</div>';
        }
    }
    preview.innerHTML = headerHtml;

    // Find the last row that contains a tender field
    let lastTenderRowIndex = -1;
    data.forEach((rowItems, rIdx) => {
        rowItems.forEach(f => {
            if (f.key === 'tender' || f.key.startsWith('tender_')) {
                lastTenderRowIndex = rIdx;
            }
        });
    });
    const previewPrintedTenderIndices = [];

    data.forEach((rowItems, rowIndex) => {
        const row = document.createElement('div');
        row.className = 'print-row';
        row.style.padding = lineSpacing + 'px 0';
        row.style.lineHeight = lineHeight;
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.alignItems = 'center';

        const makeSpan = (item, fieldIndex) => {
            const span = document.createElement('span');
            span.dataset.rowIndex = rowIndex;
            span.dataset.fieldIndex = fieldIndex;
            span.style.flex = item.flex;
            span.style.fontSize = item.font_size + 'px';
            span.style.textAlign = item.align;
            span.style.marginLeft = item.margin_left + 'px';
            span.style.marginRight = item.margin_right + 'px';
            span.style.cursor = 'pointer';
            span.title = 'Click to edit this field';
            
            const isTopReg = rowIndex < Math.ceil(data.length / 2);

            if (item.key.startsWith('_custom_')) {
                span.textContent = item.label || 'Custom Text';
            } else if (/^tender_(\d+)$/.test(item.key)) {
                const tIdx = parseInt(item.key.split('_')[1]) - 1;
                let displayLabel = item.label;
                if (!displayLabel || /^Tender \d+$/i.test(displayLabel)) {
                    displayLabel = TENDER_NAMES[tIdx] || ('Tender ' + (tIdx + 1));
                }
                let displayAmt = '[Amt]';
                if (LATEST_ENTRY && LATEST_ENTRY.tender) {
                    try {
                        const decoded = JSON.parse(LATEST_ENTRY.tender);
                        if (Array.isArray(decoded) && decoded[tIdx]) {
                            displayLabel = decoded[tIdx].name;
                            displayAmt = 'P' + parseFloat(decoded[tIdx].amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            previewPrintedTenderIndices.push(tIdx);
                        }
                    } catch(e) {}
                }
                span.innerHTML = `<strong>${displayLabel}</strong> <em style="color:#555">${displayAmt}</em>`;
            } else {
                let val = '[Value]';
                if (LATEST_ENTRY && LATEST_ENTRY[item.key] !== undefined && LATEST_ENTRY[item.key] !== '') {
                    val = LATEST_ENTRY[item.key];
                    if (item.key === 'entry_date') {
                        val = formatPreviewDate(val);
                    } else if (item.key === 'entry_time') {
                        val = formatPreviewTime(val);
                    } else if (item.key === 'register_number') {
                        val = formatPreviewReg(val, isTopReg);
                    } else if (['zread_number', 'last_trx_number', 'transaction_number'].includes(item.key)) {
                        val = formatPreview8Digits(val);
                    } else if (['total_vat', 'total_non_vat', 'daily_sales', 'old_grand_total', 'new_grand_total'].includes(item.key)) {
                        val = 'P' + parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
                span.innerHTML = `<strong>${item.label}</strong> <em style="color:#555">${val}</em>`;
            }
            // Re-apply selected highlight after re-render
            if (pfpSelected && pfpSelected.rowIndex === rowIndex && pfpSelected.fieldIndex === fieldIndex) {
                span.classList.add('preview-selected');
            }
            span.addEventListener('click', (e) => {
                e.stopPropagation();
                selectPreviewField(rowIndex, fieldIndex);
            });
            return span;
        };

        if (rowItems.length === 1 && rowItems[0].key.startsWith('_custom_')) {
            row.style.justifyContent = 'center';
            row.style.overflow = 'hidden';
            const item = rowItems[0];
            const span = makeSpan(item, 0);
            span.style.display = 'block';
            span.style.width = '100%';
            span.style.whiteSpace = 'nowrap';
            span.style.overflow = 'hidden';
            row.appendChild(span);
        } else if (rowItems.length === 1) {
            const item = rowItems[0];
            const isTopReg = rowIndex < Math.ceil(data.length / 2);
            
            let val = '[Value]';
            let displayLabel = item.label;
            if (/^tender_(\d+)$/.test(item.key)) {
                const tIdx = parseInt(item.key.split('_')[1]) - 1;
                if (!displayLabel || /^Tender \d+$/i.test(displayLabel)) {
                    displayLabel = TENDER_NAMES[tIdx] || ('Tender ' + (tIdx + 1));
                }
                val = '[Amt]';
                if (LATEST_ENTRY && LATEST_ENTRY.tender) {
                    try {
                        const decoded = JSON.parse(LATEST_ENTRY.tender);
                        if (Array.isArray(decoded) && decoded[tIdx]) {
                            displayLabel = decoded[tIdx].name;
                            val = 'P' + parseFloat(decoded[tIdx].amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            previewPrintedTenderIndices.push(tIdx);
                        }
                    } catch(e) {}
                }
            } else {
                if (LATEST_ENTRY && LATEST_ENTRY[item.key] !== undefined && LATEST_ENTRY[item.key] !== '') {
                    val = LATEST_ENTRY[item.key];
                    if (item.key === 'entry_date') {
                        val = formatPreviewDate(val);
                    } else if (item.key === 'entry_time') {
                        val = formatPreviewTime(val);
                    } else if (item.key === 'register_number') {
                        val = formatPreviewReg(val, isTopReg);
                    } else if (['zread_number', 'last_trx_number', 'transaction_number'].includes(item.key)) {
                        val = formatPreview8Digits(val);
                    } else if (['total_vat', 'total_non_vat', 'daily_sales', 'old_grand_total', 'new_grand_total'].includes(item.key)) {
                        val = 'P' + parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
            }
            
            const span = document.createElement('span');
            span.dataset.rowIndex = rowIndex;
            span.dataset.fieldIndex = 0;
            span.style.fontSize = item.font_size + 'px';
            span.style.marginLeft = item.margin_left + 'px';
            span.style.marginRight = item.margin_right + 'px';
            span.style.cursor = 'pointer';
            span.style.display = 'flex';
            span.style.justifyContent = 'space-between';
            span.style.width = '100%';
            span.title = 'Click to edit this field';
            
            span.innerHTML = `<strong>${displayLabel}</strong><span style="color:#555">${val}</span>`;
            
            if (pfpSelected && pfpSelected.rowIndex === rowIndex && pfpSelected.fieldIndex === 0) {
                span.classList.add('preview-selected');
            }
            span.addEventListener('click', (e) => {
                e.stopPropagation();
                selectPreviewField(rowIndex, 0);
            });
            row.appendChild(span);
        } else {
            row.style.gap = fieldSpacing + 'px';
            rowItems.forEach((item, fieldIndex) => {
                row.appendChild(makeSpan(item, fieldIndex));
            });
        }

        // Row spacing: inject blank divs above/below based on first field's spacing_top/spacing_bottom
        const firstItem = rowItems[0];
        const spacingTop = parseInt(firstItem.spacing_top) || 0;
        const spacingBottom = parseInt(firstItem.spacing_bottom) || 0;
        const makeBlankRow = () => {
            const blank = document.createElement('div');
            blank.className = 'print-row';
            blank.style.padding = lineSpacing + 'px 0';
            blank.style.minHeight = (fontSize + 2) + 'px';
            blank.innerHTML = '&nbsp;';
            return blank;
        };
        for (let s = 0; s < spacingTop; s++) preview.appendChild(makeBlankRow());
        preview.appendChild(row);
        for (let s = 0; s < spacingBottom; s++) preview.appendChild(makeBlankRow());

        // If this is the last tender row in the layout, render any remaining unprinted tenders in LATEST_ENTRY
        if (rowIndex === lastTenderRowIndex && LATEST_ENTRY && LATEST_ENTRY.tender) {
            try {
                const decoded = JSON.parse(LATEST_ENTRY.tender);
                if (Array.isArray(decoded)) {
                    decoded.forEach((t, tIdx) => {
                        if (!previewPrintedTenderIndices.includes(tIdx) && t.name) {
                            const extraRow = document.createElement('div');
                            extraRow.className = 'print-row';
                            extraRow.style.padding = lineSpacing + 'px 0';
                            extraRow.style.display = 'flex';
                            extraRow.style.justifyContent = 'space-between';
                            extraRow.style.alignItems = 'center';
                            const extraSpan = document.createElement('span');
                            extraSpan.style.display = 'flex';
                            extraSpan.style.justifyContent = 'space-between';
                            extraSpan.style.width = '100%';
                            extraSpan.style.fontSize = fontSize + 'px';
                            const amtVal = parseFloat(String(t.amount || 0).replace(/,/g, '')) || 0;
                            const formattedAmt = amtVal < 0
                                ? 'P-' + Math.abs(amtVal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                : 'P' + amtVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            extraSpan.innerHTML = `<strong>${t.name}</strong><span style="color:#555">${formattedAmt}</span>`;
                            extraRow.appendChild(extraSpan);
                            preview.appendChild(extraRow);
                            previewPrintedTenderIndices.push(tIdx);
                        }
                    });
                }
            } catch(e) {}
        }
    });

    // Feed lines before paper cut (if any configured)
    for (let i = 0; i < cutMarginBottom; i++) {
        const blankRow = document.createElement('div');
        blankRow.className = 'print-row';
        blankRow.style.padding = lineSpacing + 'px 0';
        blankRow.innerHTML = '&nbsp;';
        preview.appendChild(blankRow);
    }

    // Realistic Paper Cut marker at the bottom of the preview
    const cutMarker = document.createElement('div');
    cutMarker.className = 'preview-cut-marker';
    cutMarker.style.marginTop = '6px';
    cutMarker.style.paddingTop = '6px';
    cutMarker.style.borderTop = '1.5px dashed #94a3b8';
    cutMarker.style.textAlign = 'center';
    cutMarker.style.color = '#64748b';
    cutMarker.style.fontSize = '11px';
    cutMarker.style.letterSpacing = '1px';
    cutMarker.style.userSelect = 'none';
    cutMarker.innerHTML = '✂ ─── Paper Cut Line ─── ✂';
    preview.appendChild(cutMarker);
}

// Select a field in the preview and load its data into the pfp panel
function selectPreviewField(rowIndex, fieldIndex) {
    // Deselect previous
    document.querySelectorAll('#preview-pane .preview-selected').forEach(el => el.classList.remove('preview-selected'));

    // Find and highlight the clicked span
    const span = document.querySelector(`#preview-pane span[data-row-index="${rowIndex}"][data-field-index="${fieldIndex}"]`);
    if (!span) return;
    span.classList.add('preview-selected');
    pfpSelected = { rowIndex, fieldIndex };

    // Get the current data from the editor's hidden input
    const layoutRows = document.querySelectorAll('.layout-row');
    if (rowIndex >= layoutRows.length) return;
    const hiddenInputs = layoutRows[rowIndex].querySelectorAll('.field-data');
    if (fieldIndex >= hiddenInputs.length) return;

    // Highlight and scroll the corresponding field pill on the left editor
    document.querySelectorAll('.layout-field.selected, .layout-row.selected').forEach(el => el.classList.remove('selected'));
    const targetFieldPill = layoutRows[rowIndex].querySelectorAll('.layout-field')[fieldIndex];
    if (targetFieldPill) {
        targetFieldPill.classList.add('selected');
        targetFieldPill.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        loadFieldSettings(targetFieldPill);
    }

    let data = {};
    try {
        data = JSON.parse(hiddenInputs[fieldIndex].value);
    } catch(e) { return; }

    // Populate the panel
    document.getElementById('pfp-field-name').textContent = data.label || data.key || 'Field';
    document.getElementById('pfp-label').value             = data.label || '';
    document.getElementById('pfp-ml').value    = data.margin_left  || 0;
    document.getElementById('pfp-ml-val').textContent = data.margin_left  || 0;
    document.getElementById('pfp-mr').value    = data.margin_right || 0;
    document.getElementById('pfp-mr-val').textContent = data.margin_right || 0;
    document.getElementById('pfp-fs').value    = data.font_size   || 13;
    document.getElementById('pfp-fs-val').textContent = data.font_size   || 13;
    document.getElementById('pfp-flex').value  = data.flex        || 1;
    document.getElementById('pfp-flex-val').textContent = data.flex || 1;
    document.getElementById('pfp-align').value = data.align       || 'left';

    document.getElementById('preview-field-panel').style.display = 'block';
    document.getElementById('preview-field-panel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Sync pfp panel values back to the editor and re-render
function syncPfp() {
    if (!pfpSelected) return;
    const { rowIndex, fieldIndex } = pfpSelected;

    const layoutRows = document.querySelectorAll('.layout-row');
    if (rowIndex >= layoutRows.length) return;
    const hiddenInputs = layoutRows[rowIndex].querySelectorAll('.field-data');
    if (fieldIndex >= hiddenInputs.length) return;

    let data = {};
    try {
        data = JSON.parse(hiddenInputs[fieldIndex].value);
    } catch(e) { return; }

    const ml    = parseInt(document.getElementById('pfp-ml').value)   || 0;
    const mr    = parseInt(document.getElementById('pfp-mr').value)   || 0;
    const fs    = parseInt(document.getElementById('pfp-fs').value)   || 13;
    const flex  = parseInt(document.getElementById('pfp-flex').value) || 1;
    const align = document.getElementById('pfp-align').value || 'left';
    const label = document.getElementById('pfp-label').value;

    data.margin_left  = ml;
    data.margin_right = mr;
    data.font_size    = fs;
    data.flex         = flex;
    data.align        = align;
    data.label        = label;

    // Update live display values
    document.getElementById('pfp-ml-val').textContent   = ml;
    document.getElementById('pfp-mr-val').textContent   = mr;
    document.getElementById('pfp-fs-val').textContent   = fs;
    document.getElementById('pfp-flex-val').textContent = flex;
    document.getElementById('pfp-field-name').textContent = label || data.key || 'Field';

    // Write back to editor's hidden input
    hiddenInputs[fieldIndex].value = JSON.stringify(data);

    // Sync label back to the editor pill (the visible span or custom-input in the layout grid)
    const layoutField = layoutRows[rowIndex].querySelectorAll('.layout-field')[fieldIndex];
    if (layoutField) {
        const labelSpan = layoutField.querySelector('.field-label');
        const customInput = layoutField.querySelector('.custom-input');
        if (labelSpan) labelSpan.textContent = label;
        if (customInput) customInput.value = label;
    }

    // Also sync to the left-panel field-settings-panel if the same field is selected there
    if (selectedFieldEl) {
        const selectedHidden = selectedFieldEl.querySelector('.field-data');
        if (selectedHidden && selectedHidden === hiddenInputs[fieldIndex]) {
            document.getElementById('field-margin-left').value  = ml;
            document.getElementById('field-margin-left-val').textContent  = ml;
            document.getElementById('field-margin-right').value = mr;
            document.getElementById('field-margin-right-val').textContent = mr;
            document.getElementById('field-font-size').value   = fs;
            document.getElementById('field-font-size-val').textContent   = fs;
            document.getElementById('field-flex').value        = flex;
            document.getElementById('field-flex-val').textContent        = flex;
            document.getElementById('field-align').value       = align;
        }
    }

    saveState();
    previewLayout();
}

function closePfp() {
    document.getElementById('preview-field-panel').style.display = 'none';
    document.querySelectorAll('#preview-pane .preview-selected').forEach(el => el.classList.remove('preview-selected'));
    pfpSelected = null;
}

// Deselect when clicking outside preview
document.addEventListener('click', (e) => {
    if (!e.target.closest('#preview-pane') && !e.target.closest('#preview-field-panel')) {
        closePfp();
    }
});


function clearSelection() {
    document.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));
}

function selectElement(el) {
    clearSelection();
    el.classList.add('selected');
}

document.getElementById('layout-grid').addEventListener('click', function(e) {
    const field = e.target.closest('.layout-field');
    const row = e.target.closest('.layout-row');
    if (field) {
        selectElement(field);
        loadFieldSettings(field);
    } else if (row) {
        selectElement(row);
        document.getElementById('field-settings-panel').style.display = 'none';
        selectedFieldEl = null;
    }
});


// Drag-and-drop functionality
let draggedEl = null;
function initDragAndDrop(el) {
    el.setAttribute('draggable', true);
    el.addEventListener('dragstart', dragStart);
    el.addEventListener('dragend', dragEnd);
}

function dragStart(e) {
    if (e.target.tagName.toLowerCase() === 'input') {
        e.preventDefault();
        return;
    }
    draggedEl = e.currentTarget;
    draggedEl.classList.add('dragging');
    e.stopPropagation();
}

function dragEnd(e) {
    if (draggedEl) {
        draggedEl.classList.remove('dragging');
    }
    draggedEl = null;
}

function setupRowDrop(row) {
    const items = row.querySelector('.row-items');
    items.addEventListener('dragover', e => {
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'move';
    });
    items.addEventListener('drop', handleFieldDrop);
}

function handleFieldDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    if (!draggedEl) return;
    if (draggedEl.classList.contains('layout-field')) {
        const targetField = e.target.closest('.layout-field');
        const container = e.currentTarget;
        const oldRow = draggedEl.closest('.layout-row');
        
        if (targetField && targetField !== draggedEl) {
            const rect = targetField.getBoundingClientRect();
            if (e.clientX < rect.left + rect.width / 2) {
                container.insertBefore(draggedEl, targetField);
            } else {
                container.insertBefore(draggedEl, targetField.nextSibling);
            }
        } else {
            container.appendChild(draggedEl);
        }
        
        // Clean up old row if empty
        const newRow = container.closest('.layout-row');
        if (oldRow && oldRow !== newRow) {
            const items = oldRow.querySelector('.row-items');
            if (items && items.querySelectorAll('.layout-field').length === 0) {
                oldRow.remove();
            }
        }
        
        refreshRowLabels();
        saveState();
        previewLayout();
    }
}

function setupGridDrop() {
    const grid = document.getElementById('layout-grid');
    grid.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });
    grid.addEventListener('drop', handleRowDrop);
}

function handleRowDrop(e) {
    e.preventDefault();
    if (!draggedEl) return;
    const grid = document.getElementById('layout-grid');
    
    if (draggedEl.classList.contains('layout-row')) {
        const targetRow = e.target.closest('.layout-row');
        if (targetRow && targetRow !== draggedEl) {
            const rect = targetRow.getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                grid.insertBefore(draggedEl, targetRow);
            } else {
                grid.insertBefore(draggedEl, targetRow.nextSibling);
            }
            refreshRowLabels();
            saveState();
            previewLayout();
        }
    } else if (draggedEl.classList.contains('layout-field')) {
        const targetRow = e.target.closest('.layout-row');
        const oldRow = draggedEl.closest('.layout-row');
        const newRow = createEmptyRow();
        newRow.querySelector('.row-items').appendChild(draggedEl);
        
        if (targetRow) {
            const rect = targetRow.getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                grid.insertBefore(newRow, targetRow);
            } else {
                grid.insertBefore(newRow, targetRow.nextSibling);
            }
        } else {
            grid.appendChild(newRow);
        }
        
        // Clean up old row if empty
        if (oldRow && oldRow !== newRow) {
            const items = oldRow.querySelector('.row-items');
            if (items && items.querySelectorAll('.layout-field').length === 0) {
                oldRow.remove();
            }
        }
        
        initDragAndDrop(newRow);
        setupRowDrop(newRow);
        refreshRowLabels();
        saveState();
        previewLayout();
    }
}

function initAllDrag() {
    setupGridDrop();
    document.querySelectorAll('.layout-row').forEach(row => {
        initDragAndDrop(row);
        setupRowDrop(row);
        row.querySelectorAll('.layout-field').forEach(f => {
            initDragAndDrop(f);
        });
    });
}

// Initialize after DOM loaded
document.addEventListener('DOMContentLoaded', () => {
    initAllDrag();
    saveState();
    updateSpacingVal('char_width');
    updateSpacingVal('font_size');
    updateSpacingVal('line_height');
    updateSpacingVal('receipt_padding_y');
    updateSpacingVal('receipt_width');
    updateSpacingVal('header_top_margin');
    updateSpacingVal('cut_margin_bottom');
    previewLayout();
});

async function doZreadLayoutDirectPrint(btn) {
    const printer = document.getElementById('layout_direct_printer')?.value || '';
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '&#x23F3; Sending...'; }
    try {
        const resp = await fetch('zread_direct_print_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sample: true, printer: printer })
        });
        const data = await resp.json();
        if (data.success) {
            if (btn) { btn.innerHTML = '&#x2713; Sent to ' + (data.printer || 'Printer') + '!'; }
            setTimeout(() => { if (btn) { btn.disabled = false; btn.innerHTML = orig; } }, 3000);
        } else {
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            alert('&#x26A1; Direct Print Error: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        alert('&#x26A1; Direct Print Error: ' + err.message);
    }
}
</script>
</body>
</html>
