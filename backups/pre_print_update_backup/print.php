<?php
// print.php - Print-friendly view of a single journal entry
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/raw_print_service.php';
$rawPrinters = getAvailableRawPrinters();
$preferredPrinter = getPreferredThermalPrinter();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM entries WHERE id = :id");
$stmt->execute([':id' => $id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="style.css"></head><body>
          <div class="container"><h1>Entry not found</h1>
          <a class="btn" href="records.php">Back to Records</a></div></body></html>';
    exit;
}

// ---------------------------------------------------------------------
// STORE MASTER & PRINT LAYOUT 
// ---------------------------------------------------------------------
$storeMaster = null;
if (!empty($entry['store_number'])) {
    $smStmt = $pdo->prepare("SELECT * FROM store_master WHERE store_code = :code OR store_name = :code ORDER BY id DESC LIMIT 1");
    $smStmt->execute([':code' => $entry['store_number']]);
    $storeMaster = $smStmt->fetch(PDO::FETCH_ASSOC);
}
if (!$storeMaster) {
    $storeMaster = getStoreMaster();
}

$printLayout = getPrintLayout();
$settings = getPrintSettings();
// Tender name remapping: maps old TPS bucket names to canonical tender_master names
$tenderNameMap = getTenderNameMap(); // e.g. ['card' => 'BPI', 'others' => 'RNB CARD']

// Load register master record for this entry (Serial, MIN, Permit)
$registerRecord = null;
if (!empty($entry['store_number']) && !empty($entry['register_number'])) {
    $regStmt = $pdo->prepare("
        SELECT * FROM register_master
        WHERE store_code = :sc AND reg_no = :rn
        ORDER BY id DESC LIMIT 1
    ");
    $regStmt->execute([':sc' => $entry['store_number'], ':rn' => $entry['register_number']]);
    $registerRecord = $regStmt->fetch(PDO::FETCH_ASSOC);
}

// Support URL override or saved settings
$lineWidth    = isset($_GET['char_width'])    ? (int)$_GET['char_width']    : (int)($settings['char_width']    ?? 32);
$fontSize     = isset($_GET['font_size'])     ? (int)$_GET['font_size']     : (int)($settings['font_size']     ?? 11);
$receiptWidth = isset($_GET['receipt_width']) ? (int)$_GET['receipt_width'] : (int)($settings['receipt_width'] ?? 300);

if (isset($_GET['save_settings'])) {
    $settings['char_width']    = $lineWidth;
    $settings['font_size']     = $fontSize;
    $settings['receipt_width'] = $receiptWidth;
    savePrintSettings($settings);
}

// Helpers for exact character alignment
function padCenter($str, $width = 28) {
    $len = strlen($str);
    if ($len >= $width) return substr($str, 0, $width);
    $leftPad = (int)(($width - $len) / 2);
    $rightPad = $width - $len - $leftPad;
    return str_repeat(' ', $leftPad) . $str . str_repeat(' ', $rightPad);
}

function padTwoCol($left, $right, $width = 28, $ml = 0, $mr = 0) {
    // Convert pixel margins to character space counts (approx 8-10px per char)
    $spacesL = $ml > 0 ? (int)round($ml / 6) : 0;
    $spacesR = $mr > 0 ? (int)round($mr / 6) : 0;

    $leftStr = str_repeat(' ', $spacesL) . $left;
    $rightStr = $right . str_repeat(' ', $spacesR);

    $lenL = strlen($leftStr);
    $lenR = strlen($rightStr);
    if ($lenL + $lenR >= $width) {
        $space = 1;
    } else {
        $space = $width - $lenL - $lenR;
    }
    return $leftStr . str_repeat(' ', max(1, $space)) . $rightStr;
}

function padFourCol($p1, $p2, $p3, $p4, $width = 28) {
    $line = $p1 . ' ' . $p2 . ' ' . $p3 . ' ' . $p4;
    $len = strlen($line);
    if ($len >= $width) {
        return $line;
    }
    $extra = $width - $len;
    $g1 = 1 + (int)($extra / 3);
    $g2 = 1 + (int)($extra / 3);
    $g3 = $width - strlen($p1) - $g1 - strlen($p2) - $g2 - strlen($p3) - strlen($p4);
    return $p1 . str_repeat(' ', $g1) . $p2 . str_repeat(' ', $g2) . $p3 . str_repeat(' ', max(1, $g3)) . $p4;
}

function getFieldText($field, $entry, $isTopHeader = false) {
    if (strpos($field['key'], '_custom_') === 0) {
        return trim($field['label'] ?? '');
    }
    if (preg_match('/^tender_(\d+)$/', $field['key'], $m)) {
        $tSlot = (int)$m[1] - 1;
        $decoded = json_decode($entry['tender'] ?? '', true);
        if (is_array($decoded) && isset($decoded[$tSlot]) && !empty($decoded[$tSlot]['name'])) {
            $cleanVal = (float)str_replace(',', '', (string)($decoded[$tSlot]['amount'] ?? 0));
            $amtStr = ($cleanVal < 0 ? 'P-' . number_format(abs($cleanVal), 2, '.', ',') : 'P' . number_format($cleanVal, 2, '.', ','));
            return $decoded[$tSlot]['name'] . '  ' . $amtStr;
        }
        return '';
    }
    $val = $entry[$field['key']] ?? '';
    if ($field['key'] === 'entry_date' && !empty($val)) {
        // Convert any date format to mm/dd/yy
        $timeTs = strtotime($val);
        if ($timeTs !== false) {
            $val = date('m/d/y', $timeTs);
        }
    } elseif ($field['key'] === 'entry_time' && !empty($val)) {
        // Format time as HH:MM
        $timeTs = strtotime($val);
        if ($timeTs !== false) {
            $val = date('H:i', $timeTs);
        }
    } elseif ($field['key'] === 'register_number' && $val !== '') {
        if ($isTopHeader && is_numeric($val) && strlen((string)$val) < 5) {
            $val = str_pad((string)$val, 5, '0', STR_PAD_LEFT);
        }
    } elseif (in_array($field['key'], ['zread_number', 'last_trx_number', 'transaction_number']) && $val !== '') {
        // Pad with leading zeros to 8 digits if purely numeric or has numbers
        if (is_numeric($val)) {
            $val = str_pad((string)$val, 8, '0', STR_PAD_LEFT);
        }
    } elseif (in_array($field['key'], ['total_vat','total_non_vat','daily_sales','old_grand_total','new_grand_total'])) {
        $val = 'P' . number_format((float)$val, 2, '.', ',');
    }
    $lbl = trim($field['label'] ?? '');
    if ($lbl !== '') {
        return $lbl . ' ' . $val;
    }
    return (string)$val;
}

// Generate the lines
$receiptLines = [];

// Top feed blank lines (essential for Generic / Text Only drivers and physical thermal roll feed)
$topFeedLines = (int)($settings['header_top_margin'] ?? 2);
for ($i = 0; $i < $topFeedLines; $i++) {
    $receiptLines[] = '';
}

// Store header lines
if ($storeMaster) {
    $hText = trim($storeMaster['header'] ?? '');
    if ($hText !== '') {
        $hLines = preg_split('/\r\n|\r|\n/', $hText);
        foreach ($hLines as $hl) {
            if (trim($hl) !== '') {
                $receiptLines[] = padCenter(trim($hl), $lineWidth);
            }
        }
    } elseif (!empty($storeMaster['store_name'])) {
        $receiptLines[] = padCenter(trim($storeMaster['store_name']), $lineWidth);
    }

    // Append register-level Serial#, MIN#, Permit# after store header
    if ($registerRecord) {
        if (!empty($registerRecord['serial_number'])) {
            $receiptLines[] = padCenter('SERIAL#' . trim($registerRecord['serial_number']), $lineWidth);
        }
        if (!empty($registerRecord['min_number'])) {
            $receiptLines[] = padCenter(trim($registerRecord['min_number']), $lineWidth);
        }
        if (!empty($registerRecord['permit_number'])) {
            $receiptLines[] = padCenter(str_replace('-', '', trim($registerRecord['permit_number'])), $lineWidth);
        }
    } elseif ($storeMaster) {
        // Fallback to store_master if no register record found
        if (!empty($storeMaster['serial_number']) && (empty($storeMaster['header']) || stripos($storeMaster['header'], $storeMaster['serial_number']) === false)) {
            $receiptLines[] = padCenter('SERIAL#' . trim($storeMaster['serial_number']), $lineWidth);
        }
        if (!empty($storeMaster['min_number']) && (empty($storeMaster['header']) || stripos($storeMaster['header'], $storeMaster['min_number']) === false)) {
            $receiptLines[] = padCenter(trim($storeMaster['min_number']), $lineWidth);
        }
        if (!empty($storeMaster['permit_number']) && (empty($storeMaster['header']) || stripos($storeMaster['header'], $storeMaster['permit_number']) === false)) {
            $receiptLines[] = padCenter(str_replace('-', '', trim($storeMaster['permit_number'])), $lineWidth);
        }
    }
}

// Layout rows
$totalRowsCount = count($printLayout);

// Find the last row that contains a tender field
$lastTenderRowIndex = -1;
foreach ($printLayout as $rIdx => $r) {
    foreach ($r as $f) {
        if ($f['key'] === 'tender' || strpos($f['key'], 'tender_') === 0) {
            $lastTenderRowIndex = $rIdx;
        }
    }
}
$printedTenderIndices = [];
$decodedTenders = normalizeAndSortTenders($entry['tender'] ?? '');
if (!is_array($decodedTenders)) {
    $decodedTenders = [];
}

foreach ($printLayout as $rowIndex => $row) {
    $isTopHeader = $rowIndex < ceil($totalRowsCount / 2);

    // Row-level spacing: blank lines above/below from first field
    $firstField = $row[0] ?? [];
    $spacingTop    = max(0, (int)($firstField['spacing_top']    ?? 0));
    $spacingBottom = max(0, (int)($firstField['spacing_bottom'] ?? 0));
    for ($s = 0; $s < $spacingTop; $s++) { $receiptLines[] = ''; }

    if (count($row) === 1 && strpos($row[0]['key'], '_custom_') === 0) {
        $item = $row[0];
        $lbl = trim($item['label'] ?? '');
        $align = $item['align'] ?? 'center';

        if ($lbl === '' || ctype_space($lbl)) {
            $receiptLines[] = str_repeat(' ', $lineWidth);
        } elseif (preg_match('/^(=+|-+|\*+)$/', $lbl, $dm)) {
            $char = substr($lbl, 0, 1);
            $receiptLines[] = str_repeat($char, $lineWidth);
        } else {
            if ($align === 'center') {
                $receiptLines[] = padCenter($lbl, $lineWidth);
            } elseif ($align === 'right') {
                $receiptLines[] = str_pad($lbl, $lineWidth, ' ', STR_PAD_LEFT);
            } else {
                $receiptLines[] = str_pad($lbl, $lineWidth, ' ', STR_PAD_RIGHT);
            }
        }
    } elseif (count($row) === 1) {
        $item = $row[0];
        $ml = (int)($item['margin_left'] ?? 0);
        $mr = (int)($item['margin_right'] ?? 0);

        if (preg_match('/^tender_(\d+)$/', $item['key'], $m)) {
            $tSlot = (int)$m[1] - 1;
            if (isset($decodedTenders[$tSlot]) && !empty($decodedTenders[$tSlot]['name'])) {
                $name = trim($decodedTenders[$tSlot]['name']);
                $name = $tenderNameMap[strtolower($name)] ?? $name; // remap legacy bucket names
                $cleanVal = (float)str_replace(',', '', (string)($decodedTenders[$tSlot]['amount'] ?? 0));
                $amt = ($cleanVal < 0 ? 'P-' . number_format(abs($cleanVal), 2, '.', ',') : 'P' . number_format($cleanVal, 2, '.', ','));
                $receiptLines[] = padTwoCol($name, $amt, $lineWidth, $ml, $mr);
                $printedTenderIndices[] = $tSlot;
            } elseif (!empty($item['label']) && !preg_match('/^Tender \d+$/i', $item['label'])) {
                foreach ($decodedTenders as $idx => $t) {
                    if (!in_array($idx, $printedTenderIndices) && !empty($t['name']) && strcasecmp(trim($t['name']), trim($item['label'])) === 0) {
                        $name = trim($t['name']);
                        $name = $tenderNameMap[strtolower($name)] ?? $name;
                        $cleanVal = (float)str_replace(',', '', (string)($t['amount'] ?? 0));
                        $amt = ($cleanVal < 0 ? 'P-' . number_format(abs($cleanVal), 2, '.', ',') : 'P' . number_format($cleanVal, 2, '.', ','));
                        $receiptLines[] = padTwoCol($name, $amt, $lineWidth, $ml, $mr);
                        $printedTenderIndices[] = $idx;
                        break;
                    }
                }
            }
        } elseif ($item['key'] === 'tender') {
            foreach ($decodedTenders as $idx => $t) {
                if (!empty($t['name'])) {
                    $tName = trim($t['name']);
                    $tName = $tenderNameMap[strtolower($tName)] ?? $tName; // remap legacy bucket names
                    $cleanVal = (float)str_replace(',', '', (string)($t['amount'] ?? 0));
                    $amt = ($cleanVal < 0 ? 'P-' . number_format(abs($cleanVal), 2, '.', ',') : 'P' . number_format($cleanVal, 2, '.', ','));
                    $receiptLines[] = padTwoCol($tName, $amt, $lineWidth, $ml, $mr);
                    $printedTenderIndices[] = $idx;
                }
            }
        } else {
            $lbl = trim($item['label'] ?? '');
            $val = $entry[$item['key']] ?? '';
            if ($item['key'] === 'register_number' && $isTopHeader && is_numeric($val) && strlen((string)$val) < 5) {
                $val = str_pad((string)$val, 5, '0', STR_PAD_LEFT);
            } elseif (in_array($item['key'], ['total_vat','total_non_vat','daily_sales','old_grand_total','new_grand_total'])) {
                $val = 'P' . number_format((float)$val, 2, '.', ',');
            }
            $align = $item['align'] ?? 'left';
            $ml = (int)($item['margin_left'] ?? 0);
            $mr = (int)($item['margin_right'] ?? 0);

            if ($lbl !== '' && $val !== '') {
                $receiptLines[] = padTwoCol($lbl, (string)$val, $lineWidth, $ml, $mr);
            } else {
                $text = trim($lbl . ' ' . $val);
                if ($align === 'center') {
                    $receiptLines[] = padCenter($text, $lineWidth);
                } elseif ($align === 'right') {
                    $receiptLines[] = str_pad($text, $lineWidth - max(0, (int)round($mr/6)), ' ', STR_PAD_LEFT);
                } else {
                    $receiptLines[] = str_repeat(' ', max(0, (int)round($ml/6))) . str_pad($text, $lineWidth, ' ', STR_PAD_RIGHT);
                }
            }
        }
    } else {
        // Multi-column rows
        if (count($row) === 2) {
            $leftStr = getFieldText($row[0], $entry, $isTopHeader);
            $rightStr = getFieldText($row[1], $entry, $isTopHeader);
            $ml = (int)($row[0]['margin_left'] ?? 0);
            $mr = (int)($row[1]['margin_right'] ?? $row[0]['margin_right'] ?? 0);
            $receiptLines[] = padTwoCol($leftStr, $rightStr, $lineWidth, $ml, $mr);
        } elseif (count($row) === 4) {
            $c1 = getFieldText($row[0], $entry, $isTopHeader);
            $c2 = getFieldText($row[1], $entry, $isTopHeader);
            $c3 = getFieldText($row[2], $entry, $isTopHeader);
            $c4 = getFieldText($row[3], $entry, $isTopHeader);
            $receiptLines[] = padFourCol($c1, $c2, $c3, $c4, $lineWidth);
        } else {
            $parts = [];
            foreach ($row as $f) {
                $parts[] = getFieldText($f, $entry, $isTopHeader);
            }
            $receiptLines[] = implode('  ', $parts);
        }
    }

    // If this is the last tender row in the layout, ensure any remaining tenders in the entry (e.g. 4th, 5th, 6th tender) are printed
    if ($rowIndex === $lastTenderRowIndex) {
        $lastField = $row[0] ?? [];
        $tMl = (int)($lastField['margin_left'] ?? 0);
        $tMr = (int)($lastField['margin_right'] ?? 0);
        foreach ($decodedTenders as $idx => $t) {
            if (!in_array($idx, $printedTenderIndices) && !empty($t['name'])) {
                $tName = trim($t['name']);
                $tName = $tenderNameMap[strtolower($tName)] ?? $tName;
                $cleanVal = (float)str_replace(',', '', (string)($t['amount'] ?? 0));
                $amt = ($cleanVal < 0 ? 'P-' . number_format(abs($cleanVal), 2, '.', ',') : 'P' . number_format($cleanVal, 2, '.', ','));
                $receiptLines[] = padTwoCol($tName, $amt, $lineWidth, $tMl, $tMr);
                $printedTenderIndices[] = $idx;
            }
        }
    }

    // Row-level spacing: blank lines below this row
    for ($s = 0; $s < $spacingBottom; $s++) { $receiptLines[] = ''; }
}

// Ensure any unprinted tenders are printed even if no tender rows were present in layout
if (count($printedTenderIndices) < count($decodedTenders)) {
    foreach ($decodedTenders as $idx => $t) {
        if (!in_array($idx, $printedTenderIndices) && !empty($t['name'])) {
            $tName = trim($t['name']);
            $tName = $tenderNameMap[strtolower($tName)] ?? $tName;
            $cleanVal = (float)str_replace(',', '', (string)($t['amount'] ?? 0));
            $amt = ($cleanVal < 0 ? 'P-' . number_format(abs($cleanVal), 2, '.', ',') : 'P' . number_format($cleanVal, 2, '.', ','));
            $receiptLines[] = padTwoCol($tName, $amt, $lineWidth, 0, 0);
            $printedTenderIndices[] = $idx;
        }
    }
}

// Bottom feed lines before paper cut (0 for clean immediate cut)
$bottomFeedLines = (int)($settings['cut_margin_bottom'] ?? 0);
for ($i = 0; $i < $bottomFeedLines; $i++) {
    $receiptLines[] = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reprint - Entry #<?php echo (int)$entry['id']; ?></title>
<link rel="stylesheet" href="style.css">
<style>
  body {
      font-family: 'Courier New', Courier, monospace;
      color: #000;
      background: #f0f2f5;
      margin: 0;
      padding: 0;
  }
  pre.receipt-sheet {
      display: block;
      width: 100%;
      max-width: <?php echo $receiptWidth; ?>px;
      margin: 15px auto;
      padding: <?php echo (int)($settings['receipt_padding_y'] ?? 10); ?>px 8px;
      background: #fff;
      font-family: 'Courier New', Courier, monospace !important;
      font-size: <?php echo $fontSize; ?>px !important;
      line-height: <?php echo (float)($settings['line_height'] ?? 1.3); ?> !important;
      white-space: pre !important;
      color: #000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      box-sizing: border-box;
      overflow: hidden;
      letter-spacing: -0.2px;
  }
  @media print {
      @page {
          margin: 0mm !important;
          size: auto;
      }
      html, body {
          background: #fff !important;
          color: #000 !important;
          margin: 0 !important;
          padding: 0 !important;
          width: 100% !important;
          height: auto !important;
          min-height: 0 !important;
          overflow: visible !important;
      }
      .no-print {
          display: none !important;
      }
      pre.receipt-sheet {
          box-shadow: none !important;
          border: none !important;
          margin: 0 !important;
          width: 100% !important;
          max-width: 100% !important;
          padding: 0 !important;
          font-family: 'Courier New', Courier, monospace !important;
          font-size: <?php echo $fontSize; ?>px !important;
          line-height: 1.15 !important;
          letter-spacing: -0.3px !important;
          white-space: pre !important;
          page-break-inside: avoid !important;
          break-inside: avoid !important;
          page-break-before: avoid !important;
          break-before: avoid !important;
          page-break-after: avoid !important;
          break-after: avoid !important;
      }
  }
</style>
</head>
<body>

<div class="no-print" style="background:#0f172a; color:#fff; padding:14px 18px; margin:15px auto; border-radius:8px; max-width:380px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
    <div style="font-weight:bold; font-size:14px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
        <span>🖨️ Thermal Roll Width Setup</span>
    </div>
    
    <div style="display:flex; gap:10px; margin-bottom:10px;">
        <div style="flex:1;">
            <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:3px;">Characters / Line:</label>
            <select id="sel_char_width" onchange="applyAdjustments()" style="width:100%; padding:6px; font-size:12px; border-radius:4px; background:#fff; color:#000; font-weight:bold;">
                <option value="26" <?php if ($lineWidth == 26) echo 'selected'; ?>>26 chars (Narrow 58mm)</option>
                <option value="28" <?php if ($lineWidth == 28) echo 'selected'; ?>>28 chars (Standard 58mm)</option>
                <option value="30" <?php if ($lineWidth == 30) echo 'selected'; ?>>30 chars (Wide 58mm)</option>
                <option value="32" <?php if ($lineWidth == 32) echo 'selected'; ?>>32 chars</option>
                <option value="36" <?php if ($lineWidth == 36) echo 'selected'; ?>>36 chars (80mm)</option>
                <option value="40" <?php if ($lineWidth == 40) echo 'selected'; ?>>40 chars (80mm)</option>
            </select>
        </div>
        <div style="flex:1;">
            <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:3px;">Font Size:</label>
            <select id="sel_font_size" onchange="applyAdjustments()" style="width:100%; padding:6px; font-size:12px; border-radius:4px; background:#fff; color:#000; font-weight:bold;">
                <option value="9" <?php if ($fontSize == 9) echo 'selected'; ?>>9px (Smallest)</option>
                <option value="10" <?php if ($fontSize == 10) echo 'selected'; ?>>10px (Compact)</option>
                <option value="11" <?php if ($fontSize == 11) echo 'selected'; ?>>11px (Normal)</option>
                <option value="12" <?php if ($fontSize == 12) echo 'selected'; ?>>12px (Large)</option>
            </select>
        </div>
    </div>

    <div style="font-size:11px; color:#facc15; background:#422006; padding:6px 8px; border-radius:4px; margin-bottom:12px; line-height:1.4;">
        ⚠️ <strong>Printer Margin Notice:</strong> In the browser print popup, select <strong>Margins: None</strong> to prevent page overflow.
    </div>

    <div style="display:flex; gap:10px;">
        <button onclick="window.print()" style="flex:1; padding:8px 12px; background:#2563eb; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Print Now</button>
        <button id="btn-direct-print" onclick="doZreadDirectPrint()" style="flex:1; padding:8px 12px; background:#16a34a; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">&#x26A1; Direct Print</button>
        <a class="btn btn-secondary" href="records.php" style="padding:8px 14px; text-decoration:none; color:#fff; background:#334155; border-radius:4px; font-size:12px; display:inline-flex; align-items:center;">Back</a>
    </div>
    <div style="margin-top:10px; display:flex; align-items:center; gap:8px;">
        <label style="font-size:11px; color:#94a3b8; white-space:nowrap;">&#x1F5A8;&#xFE0F; Direct Printer:</label>
        <select id="direct_printer_select_print" style="flex:1; padding:5px 8px; font-size:12px; border-radius:4px; background:#1e293b; color:#fff; border:1px solid #475569; font-weight:bold;">
            <?php if (empty($rawPrinters)): ?>
                <option value="EPSON TM-T82 Receipt">&#x1F5A8;&#xFE0F; EPSON TM-T82 Receipt (Default)</option>
            <?php else: ?>
                <?php foreach ($rawPrinters as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>" <?php if ($p === $preferredPrinter) echo 'selected'; ?>>&#x1F5A8;&#xFE0F; <?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
</div>

<pre class="receipt-sheet"><?php echo htmlspecialchars(implode("\r\n", $receiptLines)); ?></pre>

<script>
    function applyAdjustments() {
        const cw = document.getElementById('sel_char_width').value;
        const fs = document.getElementById('sel_font_size').value;
        window.location.href = 'print.php?id=<?php echo $id; ?>&char_width=' + cw + '&font_size=' + fs + '&save_settings=1';
    }
    async function doZreadDirectPrint() {
        const btn = document.getElementById('btn-direct-print');
        const printer = document.getElementById('direct_printer_select_print')?.value || '';
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '&#x23F3; Printing...';
        try {
            const resp = await fetch('zread_direct_print_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: <?php echo $id; ?>, printer: printer })
            });
            const data = await resp.json();
            if (data.success) {
                btn.innerHTML = '&#x2713; Sent!';
                setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 2500);
            } else {
                btn.disabled = false; btn.innerHTML = orig;
                alert('&#x26A1; Direct Print Error: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            btn.disabled = false; btn.innerHTML = orig;
            alert('&#x26A1; Direct Print Error: ' + err.message);
        }
    }
</script>

</body>
</html>
