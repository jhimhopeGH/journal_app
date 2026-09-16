<?php
// print_batch.php - Prints multiple selected journal entries in sequence
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/raw_print_service.php';
$rawPrinters = getAvailableRawPrinters();
$preferredPrinter = getPreferredThermalPrinter();

$idsParam = isset($_GET['ids']) ? trim($_GET['ids']) : '';
$ids = [];
if ($idsParam !== '') {
    foreach (explode(',', $idsParam) as $idPart) {
        $cleanId = (int)trim($idPart);
        if ($cleanId > 0) {
            $ids[] = $cleanId;
        }
    }
}

// Or batch print by store and register filter
$filterStore = isset($_GET['store']) ? trim($_GET['store']) : '';
$filterReg   = isset($_GET['reg'])   ? trim($_GET['reg'])   : '';

if (empty($ids) && ($filterStore !== '' || $filterReg !== '')) {
    $where = [];
    $params = [];
    if ($filterStore !== '') {
        $where[] = "store_number = :store";
        $params[':store'] = $filterStore;
    }
    if ($filterReg !== '') {
        $where[] = "register_number = :reg";
        $params[':reg'] = $filterReg;
    }
    $sql = "SELECT id FROM entries WHERE " . implode(" AND ", $where) . " ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($ids)) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="style.css"></head><body>
          <div class="container"><h1>No entries selected for batch print</h1>
          <p>Please select at least one record from the records list.</p>
          <a class="btn" href="records.php">Back to Records</a></div></body></html>';
    exit;
}

// Fetch entries in order
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM entries WHERE id IN ($placeholders) ORDER BY id ASC");
$stmt->execute($ids);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load Store Master, Register Master cache, and Print Layout
$allStores = getAllStoreMasters();
$storeMap = [];
foreach ($allStores as $sm) {
    $storeMap[$sm['store_code']] = $sm;
}
$defaultStoreMaster = getStoreMaster();

$allRegs = getAllRegisterMasters();
$regMap = [];
foreach ($allRegs as $rm) {
    $key = trim($rm['store_code']) . '_' . trim($rm['reg_no']);
    $regMap[$key] = $rm;
}

$printLayout = getPrintLayout();
$settings    = getPrintSettings();
// Tender name remapping: maps old TPS bucket names to canonical tender_master names
$tenderNameMap = getTenderNameMap(); // e.g. ['card' => 'BPI', 'others' => 'RNB CARD']

$lineWidth    = isset($_GET['char_width'])    ? (int)$_GET['char_width']    : (int)($settings['char_width']    ?? 32);
$fontSize     = isset($_GET['font_size'])     ? (int)$_GET['font_size']     : (int)($settings['font_size']     ?? 11);
$receiptWidth = isset($_GET['receipt_width']) ? (int)$_GET['receipt_width'] : (int)($settings['receipt_width'] ?? 300);

// Helpers
function padCenter($str, $width = 28) {
    $len = strlen($str);
    if ($len >= $width) return substr($str, 0, $width);
    $leftPad = (int)(($width - $len) / 2);
    $rightPad = $width - $len - $leftPad;
    return str_repeat(' ', $leftPad) . $str . str_repeat(' ', $rightPad);
}

function padTwoCol($left, $right, $width = 28, $ml = 0, $mr = 0) {
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
    if ($len >= $width) return $line;
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
        $timeTs = strtotime($val);
        if ($timeTs !== false) $val = date('m/d/y', $timeTs);
    } elseif ($field['key'] === 'entry_time' && !empty($val)) {
        $timeTs = strtotime($val);
        if ($timeTs !== false) $val = date('H:i', $timeTs);
    } elseif ($field['key'] === 'register_number' && $val !== '') {
        if ($isTopHeader && is_numeric($val) && strlen((string)$val) < 5) {
            $val = str_pad((string)$val, 5, '0', STR_PAD_LEFT);
        }
    } elseif (in_array($field['key'], ['zread_number', 'last_trx_number', 'transaction_number']) && $val !== '') {
        if (is_numeric($val)) $val = str_pad((string)$val, 8, '0', STR_PAD_LEFT);
    } elseif (in_array($field['key'], ['total_vat','total_non_vat','daily_sales','old_grand_total','new_grand_total'])) {
        $val = 'P' . number_format((float)$val, 2, '.', ',');
    }
    $lbl = trim($field['label'] ?? '');
    if ($lbl !== '') return $lbl . ' ' . $val;
    return (string)$val;
}

function buildReceiptLines($entry, $storeMap, $defaultStoreMaster, $regMap, $printLayout, $lineWidth, $settings = [], $tenderNameMap = []) {
    $receiptLines = [];
    
    // Top feed blank lines (essential for Generic / Text Only drivers and physical paper feeds)
    $topFeedLines = (int)($settings['header_top_margin'] ?? 2);
    for ($i = 0; $i < $topFeedLines; $i++) {
        $receiptLines[] = '';
    }
    $storeCode = $entry['store_number'] ?? '';
    $regNo = $entry['register_number'] ?? '';

    $storeMaster = $storeMap[$storeCode] ?? $defaultStoreMaster;
    $regKey = trim($storeCode) . '_' . trim($regNo);
    $registerRecord = $regMap[$regKey] ?? null;

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
                    $amt = 'P' . number_format((float)($decodedTenders[$tSlot]['amount'] ?? 0), 2, '.', ',');
                    $receiptLines[] = padTwoCol($name, $amt, $lineWidth, $ml, $mr);
                    $printedTenderIndices[] = $tSlot;
                } elseif (!empty($item['label']) && !preg_match('/^Tender \d+$/i', $item['label'])) {
                    foreach ($decodedTenders as $idx => $t) {
                        if (!in_array($idx, $printedTenderIndices) && !empty($t['name']) && strcasecmp(trim($t['name']), trim($item['label'])) === 0) {
                            $name = trim($t['name']);
                            $name = $tenderNameMap[strtolower($name)] ?? $name;
                            $amt = 'P' . number_format((float)($t['amount'] ?? 0), 2, '.', ',');
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
                        $amt = 'P' . number_format((float)($t['amount'] ?? 0), 2, '.', ',');
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

        // If this is the last tender row in the layout, ensure any remaining tenders in the entry (e.g. 4th, 5th tender) are printed
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

    return $receiptLines;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Batch Print - <?php echo count($entries); ?> Entries</title>
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
      page-break-after: always;
      break-after: page;
  }
  pre.receipt-sheet:last-child {
      page-break-after: auto;
      break-after: auto;
  }
  .receipt-separator {
      text-align: center;
      margin: 12px auto;
      max-width: <?php echo $receiptWidth; ?>px;
      color: #94a3b8;
      font-size: 11px;
      font-family: sans-serif;
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
          page-break-after: always !important;
          break-after: page !important;
      }
      pre.receipt-sheet:last-child {
          page-break-after: auto !important;
          break-after: auto !important;
      }
      .receipt-separator {
          display: none !important;
      }
  }
</style>
</head>
<body>

<div class="no-print" style="background:#0f172a; color:#fff; padding:16px 20px; margin:15px auto; border-radius:8px; max-width:460px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
    <div style="font-weight:bold; font-size:14px; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between;">
        <span>🖨️ Batch Printing (<?php echo count($entries); ?> Receipts)</span>
    </div>
    <div style="font-size:12px; color:#94a3b8; margin-bottom:14px; line-height:1.4;">
        Paper cut spacers & page breaks are added after each transaction. Choose how you want to send to your printer:
    </div>

    <div style="display:flex; flex-direction:column; gap:8px;">
        <button onclick="window.print()" style="padding:10px 14px; background:#2563eb; color:#fff; border:none; border-radius:5px; font-weight:bold; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; gap:6px;">
            🖨️ Print Batch (All in 1 Spool with Cut Breaks)
        </button>
        <button onclick="printSequentially()" style="padding:9px 14px; background:#7c3aed; color:#fff; border:none; border-radius:5px; font-weight:bold; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; gap:6px;">
            ✂️ Print One-by-One (Trigger Physical Cutter)
        </button>
        <button id="btn-direct-batch" onclick="doZreadDirectBatch()" style="padding:9px 14px; background:#16a34a; color:#fff; border:none; border-radius:5px; font-weight:bold; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; gap:6px;">
            &#x26A1; Direct Print All (<?php echo count($entries); ?> Receipts)
        </button>
        <div style="display:flex; align-items:center; gap:8px;">
            <label style="font-size:11px; color:#94a3b8; white-space:nowrap;">&#x1F5A8;&#xFE0F; Direct Printer:</label>
            <select id="direct_printer_select_batch" style="flex:1; padding:5px 8px; font-size:12px; border-radius:4px; background:#1e293b; color:#fff; border:1px solid #475569; font-weight:bold;">
                <?php if (empty($rawPrinters)): ?>
                    <option value="EPSON TM-T82 Receipt">&#x1F5A8;&#xFE0F; EPSON TM-T82 Receipt (Default)</option>
                <?php else: ?>
                    <?php foreach ($rawPrinters as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>" <?php if ($p === $preferredPrinter) echo 'selected'; ?>>&#x1F5A8;&#xFE0F; <?php echo htmlspecialchars($p); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <a class="btn btn-secondary" href="records.php" style="padding:8px 14px; text-decoration:none; color:#cbd5e1; background:#334155; border-radius:5px; font-size:12px; text-align:center;">← Back to Records</a>
    </div>
</div>

<?php foreach ($entries as $idx => $entry): ?>
    <?php $lines = buildReceiptLines($entry, $storeMap, $defaultStoreMaster, $regMap, $printLayout, $lineWidth, $settings, $tenderNameMap); ?>
    <pre class="receipt-sheet" id="receipt-sheet-<?php echo $idx; ?>"><?php echo htmlspecialchars(implode("\r\n", $lines)); ?></pre>
    <?php if ($idx < count($entries) - 1): ?>
        <div class="receipt-separator no-print">──────── ✂ Cut Line / End of Receipt #<?php echo (int)$entry['id']; ?> ────────</div>
    <?php endif; ?>
<?php endforeach; ?>

<iframe id="print-iframe" style="display:none; width:0; height:0; border:none;"></iframe>

<script>
// Print each receipt individually in sequence so the printer physically triggers its auto-cutter between jobs
const entryIds = <?php echo json_encode(array_column($entries, 'id')); ?>;

function printSequentially() {
    if (!entryIds || entryIds.length === 0) return;
    let idx = 0;
    
    function printNext() {
        if (idx >= entryIds.length) {
            alert('\u2713 All ' + entryIds.length + ' receipts have been sent to the printer!');
            return;
        }
        const id = entryIds[idx];
        idx++;
        const printWindow = window.open('print.php?id=' + id, '_blank');
        if (printWindow) {
            printWindow.focus();
        }
    }
    
    printNext();
}

async function doZreadDirectBatch() {
    const btn = document.getElementById('btn-direct-batch');
    const printer = document.getElementById('direct_printer_select_batch')?.value || '';
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '&#x23F3; Printing...';
    try {
        const resp = await fetch('zread_direct_print_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: entryIds, printer: printer })
        });
        const data = await resp.json();
        if (data.success) {
            btn.innerHTML = '&#x2713; ' + data.count + ' Sent!';
            setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 3000);
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
