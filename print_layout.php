<?php
// print_layout.php - 2D grid layout with arrow-button controls
require_once __DIR__ . '/db.php';
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
                            'key' => $key,
                            'label' => $label,
                            'flex' => (int)($field['flex'] ?? 1),
                            'align' => $field['align'] ?? 'left',
                            'margin_left' => (int)($field['margin_left'] ?? 0),
                            'margin_right' => (int)($field['margin_right'] ?? 0),
                            'font_size' => (int)($field['font_size'] ?? 13)
                        ];
                    }
                }
                if (!empty($newRow)) {
                    $newLayout[] = $newRow;
                }
            }
            savePrintLayout($newLayout);
            
            // Also save spacing settings!
            if (isset($_POST['line_spacing']) && isset($_POST['field_spacing']) && isset($_POST['receipt_width'])) {
                $newSettings = [
                    'line_spacing' => (int)$_POST['line_spacing'],
                    'field_spacing' => (int)$_POST['field_spacing'],
                    'receipt_width' => (int)$_POST['receipt_width']
                ];
                savePrintSettings($newSettings);
            }
            
            $layoutSaved = true;
        }
    }
}
$currentLayout = getPrintLayout();
$settings = getPrintSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Print Layout - Electronic Journal</title>
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
      padding: 8px 12px;
      border-radius: 4px;
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
</style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Print Layout</h1>
            <p>Customize the format of the printed Z-Read receipts.</p>
        </div>

        <?php if (isset($layoutSaved)): ?>
            <div class="success">Print layout saved successfully.</div>
        <?php endif; ?>

        <div class="panel">
            <p style="font-size: 14px; color: #444; margin-top: 0; margin-bottom: 15px;">
                Drag and drop fields to arrange them. Drag a field to another row to place it side-by-side, or drop it between rows to create a new row.
            </p>
            
            <form method="POST" action="print_layout.php" id="layout-form">
                <input type="hidden" name="action" value="save_layout">
                <input type="hidden" name="layout_data" id="layout_data" value="">
                
                <div id="layout-grid" class="layout-grid">
                    <?php foreach ($currentLayout as $rowIndex => $rowItems): ?>
                        <div class="layout-row">
                            <div class="row-label">Row <?php echo $rowIndex + 1; ?></div>
                            <div class="row-items">
                                <?php foreach ($rowItems as $field): ?>
                                    <div class="layout-field">
                                        <?php 
                                          $fieldObj = [
                                              'key' => $field['key'],
                                              'label' => $field['label'],
                                              'flex' => (int)($field['flex'] ?? 1),
                                              'align' => $field['align'] ?? 'left',
                                              'margin_left' => (int)($field['margin_left'] ?? 0),
                                              'margin_right' => (int)($field['margin_right'] ?? 0),
                                              'font_size' => (int)($field['font_size'] ?? 13)
                                          ];
                                          // Use JSON_HEX_APOS|JSON_HEX_TAG to safely embed in a single-quoted HTML attribute
                                          $jsonVal = json_encode($fieldObj, JSON_HEX_APOS | JSON_HEX_TAG);
                                        ?>
                                        <?php if (strpos($field['key'], '_custom_') === 0): ?>
                                            <input type="text" class="custom-input" value="<?php echo htmlspecialchars($field['label']); ?>" oninput="updateHidden(this)">
                                            <input type="hidden" class="field-data" value='<?php echo $jsonVal; ?>'>
                                        <?php else: ?>
                                            <span class="field-label"><?php echo htmlspecialchars($field['label']); ?></span>
                                            <input type="hidden" class="field-data" value='<?php echo $jsonVal; ?>'>
                                        <?php endif; ?>
                                        <button type="button" class="remove-field-btn" onclick="removeField(this)" title="Remove">✖</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Field settings panel: initially hidden -->
                <div id="field-settings-panel" style="display: none; margin-top: 25px; padding: 18px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc;">
                    <h3 style="margin-top: 0; font-size: 14px; color: #1e293b; font-weight: bold; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 15px;">Field Settings: <span id="settings-field-name" style="color: #3b82f6;">None</span></h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                        <div style="flex: 1; min-width: 140px;">
                            <label style="margin-top: 0; font-size: 12px; color: #475569; font-weight: bold; display: block; margin-bottom: 6px;">Alignment</label>
                            <select id="field-align" onchange="updateSelectedFieldSetting()" style="width:100%; padding: 8px; border: 1px solid #cbd5e1; border-radius:4px; font-size: 13px; background: white;">
                                <option value="left">Left</option>
                                <option value="center">Center</option>
                                <option value="right">Right</option>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label style="margin-top: 0; font-size: 12px; color: #475569; font-weight: bold; display: block; margin-bottom: 6px;">Flex Width (Weight): <span id="field-flex-val">1</span></label>
                            <input type="range" id="field-flex" min="1" max="5" value="1" oninput="updateSelectedFieldSetting()" style="width: 100%;">
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label style="margin-top: 0; font-size: 12px; color: #475569; font-weight: bold; display: block; margin-bottom: 6px;">Font Size: <span id="field-font-size-val">13</span>px</label>
                            <input type="range" id="field-font-size" min="10" max="24" value="13" oninput="updateSelectedFieldSetting()" style="width: 100%;">
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label style="margin-top: 0; font-size: 12px; color: #475569; font-weight: bold; display: block; margin-bottom: 6px;">Margin Left: <span id="field-margin-left-val">0</span>px</label>
                            <input type="range" id="field-margin-left" min="-40" max="40" value="0" oninput="updateSelectedFieldSetting()" style="width: 100%;">
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label style="margin-top: 0; font-size: 12px; color: #475569; font-weight: bold; display: block; margin-bottom: 6px;">Margin Right: <span id="field-margin-right-val">0</span>px</label>
                            <input type="range" id="field-margin-right" min="-40" max="40" value="0" oninput="updateSelectedFieldSetting()" style="width: 100%;">
                        </div>
                    </div>
                </div>
                
                <div class="settings-section" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 20px;">
                    <div style="flex: 1; min-width: 180px;">
                        <label for="line_spacing" style="margin-top: 0; color: #475569; font-size: 13px; font-weight: bold;">Line Spacing (Vertical): <span id="line_spacing_val"><?php echo htmlspecialchars($settings['line_spacing']); ?></span>px</label>
                        <input type="range" id="line_spacing" name="line_spacing" min="-20" max="20" value="<?php echo htmlspecialchars($settings['line_spacing']); ?>" oninput="updateSpacingVal('line_spacing')" style="width: 100%;">
                    </div>
                    <div style="flex: 1; min-width: 180px;">
                        <label for="field_spacing" style="margin-top: 0; color: #475569; font-size: 13px; font-weight: bold;">Column Spacing (Horizontal): <span id="field_spacing_val"><?php echo htmlspecialchars($settings['field_spacing']); ?></span>px</label>
                        <input type="range" id="field_spacing" name="field_spacing" min="-30" max="30" value="<?php echo htmlspecialchars($settings['field_spacing']); ?>" oninput="updateSpacingVal('field_spacing')" style="width: 100%;">
                    </div>
                    <div style="flex: 1; min-width: 180px;">
                        <label for="receipt_width" style="margin-top: 0; color: #475569; font-size: 13px; font-weight: bold;">Receipt Width: <span id="receipt_width_val"><?php echo htmlspecialchars($settings['receipt_width']); ?></span>px</label>
                        <input type="range" id="receipt_width" name="receipt_width" min="250" max="500" value="<?php echo htmlspecialchars($settings['receipt_width']); ?>" oninput="updateSpacingVal('receipt_width')" style="width: 100%;">
                    </div>
                </div>
                
                <div class="layout-actions" style="margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <button type="button" class="btn-secondary" onclick="addCustomLine()">+ Add Custom Text</button>
                    
                    <div style="display: inline-flex; align-items: center; gap: 6px;">
                        <select id="db-field-select" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; background: white; height: 36px; box-sizing: border-box;">
                            <option value="">-- Add Database Field --</option>
                            <option value="store_number:Store No.">Store No.</option>
                            <option value="register_number:Register No.">Register No.</option>
                            <option value="transaction_number:Transaction No.">Transaction No.</option>
                            <option value="last_trx_number:Last Trx#">Last Trx#</option>
                            <option value="entry_date:Date">Date</option>
                            <option value="entry_time:Time">Time</option>
                            <option value="zread_number:Z-Read No.">Z-Read No.</option>
                            <option value="till_number:Till No. (Cashier)">Till No. (Cashier)</option>
                            <option value="tender:Tender">Tender</option>
                            <option value="total_vat:Total VAT">Total VAT</option>
                            <option value="total_non_vat:Total Non-VAT">Total Non-VAT</option>
                            <option value="daily_sales:Daily Sales">Daily Sales</option>
                            <option value="old_grand_total:Old Grand Total">Old Grand Total</option>
                            <option value="new_grand_total:New Grand Total">New Grand Total</option>
                            <option value="created_at:Saved On">Saved On</option>
                        </select>
                        <button type="button" class="btn-secondary" onclick="addDbField()" style="height: 36px;">+ Add Field</button>
                    </div>

                    <button type="button" class="btn-secondary" onclick="undoChanges()">Undo</button>
                    <button type="button" class="btn-secondary" onclick="previewLayout()">Preview</button>
                    <button type="submit" style="font-weight: bold; height: 36px;">Save Print Layout</button>
                </div>
            </form>
     <script>
let selectedFieldEl = null;

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
    
    document.getElementById('field-settings-panel').style.display = 'block';
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
        <button type="button" class="remove-field-btn" onclick="removeField(this)" title="Remove">✖</button>
    `;
    newRow.querySelector('.row-items').appendChild(field);
    grid.appendChild(newRow);
    
    initDragAndDrop(newRow);
    setupRowDrop(newRow);
    initDragAndDrop(field);
    
    refreshRowLabels();
    saveState();
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
        <button type="button" class="remove-field-btn" onclick="removeField(this)" title="Remove">✖</button>
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

// Form submission
document.getElementById('layout-form').addEventListener('submit', function(e) {
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
    document.getElementById('layout_data').value = JSON.stringify(data);
});

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
                <button type="button" class="remove-field-btn" onclick="removeField(this)" title="Remove">✖</button>
            `;
            itemsContainer.appendChild(field);
        });
        grid.appendChild(row);
    });
    refreshRowLabels();
    initAllDrag();
    
    document.getElementById('field-settings-panel').style.display = 'none';
    selectedFieldEl = null;
}

// Refactored previewLayout – renders a read‑only preview without touching the editor grid
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

    let preview = document.getElementById('preview-pane');
    if (!preview) {
        preview = document.createElement('div');
        preview.id = 'preview-pane';
        preview.className = 'print-sheet'; // Styled exactly like final print
        preview.style.marginTop = '20px';
        document.querySelector('.layout-actions').insertAdjacentElement('afterend', preview);
    }

    const lineSpacing = document.getElementById('line_spacing').value;
    const fieldSpacing = document.getElementById('field_spacing').value;
    const receiptWidth = document.getElementById('receipt_width').value;
    
    preview.style.maxWidth = receiptWidth + 'px';
    preview.innerHTML = '<h2 style="text-align:center; margin-bottom: 4px;">JOURNAL RECEIPT (PREVIEW)</h2><p style="text-align:center; margin-top:0;">Real-time Spacing Preview</p>';

    data.forEach((rowItems, rowIndex) => {
        const row = document.createElement('div');
        row.className = 'print-row';
        row.style.padding = lineSpacing + 'px 0';
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.alignItems = 'center';
        
        if (rowItems.length === 1 && rowItems[0].key.startsWith('_custom_')) {
            row.style.justifyContent = 'center';
            row.style.borderBottom = 'none';
            row.style.textAlign = 'center';
            const item = rowItems[0];
            row.innerHTML = `<span style="font-size: ${item.font_size}px; text-align: ${item.align}; flex: ${item.flex}; margin-left: ${item.margin_left}px; margin-right: ${item.margin_right}px;">${item.label || 'Custom Text'}</span>`;
        } else if (rowItems.length === 1) {
            const item = rowItems[0];
            row.innerHTML = `<span style="font-size: ${item.font_size}px; text-align: left; margin-left: ${item.margin_left}px;">${item.label}</span><span style="font-size: ${item.font_size}px; text-align: right; margin-right: ${item.margin_right}px;">[Value]</span>`;
        } else {
            row.style.gap = fieldSpacing + 'px';
            rowItems.forEach(item => {
                const span = document.createElement('span');
                span.style.flex = item.flex;
                span.style.fontSize = item.font_size + 'px';
                span.style.textAlign = item.align;
                span.style.marginLeft = item.margin_left + 'px';
                span.style.marginRight = item.margin_right + 'px';
                if (item.key.startsWith('_custom_')) {
                    span.innerHTML = `${item.label || 'Custom Text'}`;
                } else {
                    span.innerHTML = `<strong>${item.label}:</strong> [Value]`;
                }
                row.appendChild(span);
            });
        }
        preview.appendChild(row);
    });
}

function updateSpacingVal(id) {
    const el = document.getElementById(id);
    if (el) {
        document.getElementById(id + '_val').textContent = el.value;
    }
    previewLayout();
}

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
        
        // Clean up old row if empty and it's not the container's row
        const newRow = container.closest('.layout-row');
        if (oldRow && oldRow !== newRow) {
            const items = oldRow.querySelector('.row-items');
            if (items && items.querySelectorAll('.layout-field').length === 0) {
                oldRow.remove();
            }
        }
        
        refreshRowLabels();
        saveState();
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
    updateSpacingVal('line_spacing');
    updateSpacingVal('field_spacing');
    updateSpacingVal('receipt_width');
});
</script>
</body>
</html>
