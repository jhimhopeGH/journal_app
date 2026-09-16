<?php
// receipt_print_batch.php - Batch 80mm Thermal Receipt Reprint View
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/ej_db.php';
require_once __DIR__ . '/raw_print_service.php';

$idsParam = isset($_GET['ids']) ? trim($_GET['ids']) : (isset($_POST['ids']) ? (is_array($_POST['ids']) ? implode(',', $_POST['ids']) : trim($_POST['ids'])) : '');
$idList = array_filter(array_map('intval', explode(',', $idsParam)));

if (empty($idList)) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="style.css"></head><body>
          <div class="container"><h1>No entries selected for batch printing</h1>
          <a class="btn" href="receipt_reprint.php">Back to Receipt Reprint</a></div></body></html>';
    exit;
}

// Load available printers for direct raw printing
$rawPrinters      = getAvailableRawPrinters();
$preferredPrinter = getPreferredThermalPrinter();

$inClause = implode(',', $idList);
$stmt = $ejPdo->query("SELECT * FROM ej_entries WHERE id IN ($inClause) ORDER BY entry_date ASC, id ASC");
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tenderNameMap = getTenderNameMap();

// Load settings
$settings = getReceiptPrintSettings();
$W              = (int)($settings['char_width']        ?? 42);
$fontSize       = (float)($settings['font_size']       ?? 10);
$lineHeight     = (float)($settings['line_height']     ?? 1.3);
$paddingX       = (int)($settings['receipt_padding_x'] ?? 10);
$paddingY       = (int)($settings['receipt_padding_y'] ?? 4);
$colQtyW        = (int)($settings['col_qty_width']     ?? 4);
$colSkuW        = (int)($settings['col_sku_width']     ?? 10);
$colPriceW      = (int)($settings['col_price_width']   ?? 8);
$colAmtW        = (int)($settings['col_amt_width']     ?? 9);
$colSubLabel    = (int)($settings['col_subtotal_label']?? 26);
$colSubAmt      = (int)($settings['col_subtotal_amt']  ?? 14);
$colTotalLabel  = (int)($settings['col_total_label']   ?? 26);
$colTotalAmt    = (int)($settings['col_total_amt']     ?? 14);
$colTenderLabel = (int)($settings['col_tender_label']  ?? 26);
$colTenderAmt   = (int)($settings['col_tender_amt']    ?? 14);
$colChangeLabel = (int)($settings['col_change_label']  ?? 16);
$colChangeAmt   = (int)($settings['col_change_amt']    ?? 14);
$colVatLabel    = (int)($settings['col_vat_label']     ?? 17);
$colVatAmt      = (int)($settings['col_vat_amt']       ?? 14);

function rcPadBatch($str, $width) {
    $len = mb_strlen($str, 'UTF-8');
    if ($len >= $width) return mb_substr($str, 0, $width, 'UTF-8');
    $l = (int)(($width - $len) / 2);
    return str_repeat(' ', $l) . $str . str_repeat(' ', $width - $len - $l);
}

function rcBlankLineBatch($width) {
    $l = (int)(($width - 1) / 2);
    return str_repeat(' ', $l) . "\xC2\xA0" . str_repeat(' ', max(0, $width - 1 - $l));
}

function generateReceiptLines($entry, $tenderNameMap, $settings, $pdo) {
    ensureEjEntryCashier($entry);
    $W              = (int)($settings['char_width']        ?? 42);
    $colQtyW        = (int)($settings['col_qty_width']     ?? 4);
    $colSkuW        = (int)($settings['col_sku_width']     ?? 10);
    $colPriceW      = (int)($settings['col_price_width']   ?? 8);
    $colAmtW        = (int)($settings['col_amt_width']     ?? 9);
    $colSubLabel    = (int)($settings['col_subtotal_label']?? 26);
    $colSubAmt      = (int)($settings['col_subtotal_amt']  ?? 14);
    $colTotalLabel  = (int)($settings['col_total_label']   ?? 26);
    $colTotalAmt    = (int)($settings['col_total_amt']     ?? 14);
    $colTenderLabel = (int)($settings['col_tender_label']  ?? 26);
    $colTenderAmt   = (int)($settings['col_tender_amt']    ?? 14);
    $colChangeLabel = (int)($settings['col_change_label']  ?? 16);
    $colChangeAmt   = (int)($settings['col_change_amt']    ?? 14);
    $colVatLabel    = (int)($settings['col_vat_label']     ?? 17);
    $colVatAmt      = (int)($settings['col_vat_amt']       ?? 14);

    // Load Store Master
    $storeMaster = null;
    if (!empty($entry['store_code'])) {
        $smStmt = $pdo->prepare("SELECT * FROM store_master WHERE store_code = :sc ORDER BY id DESC LIMIT 1");
        $smStmt->execute([':sc' => $entry['store_code']]);
        $storeMaster = $smStmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$storeMaster) $storeMaster = getStoreMaster();

    // Load Register Master
    $registerRecord = null;
    if (!empty($entry['store_code']) && !empty($entry['register_number'])) {
        $regStmt = $pdo->prepare("SELECT * FROM register_master WHERE store_code = :sc AND reg_no = :rn ORDER BY id DESC LIMIT 1");
        $regStmt->execute([':sc' => $entry['store_code'], ':rn' => $entry['register_number']]);
        $registerRecord = $regStmt->fetch(PDO::FETCH_ASSOC);
    }

    $rawDate       = $entry['entry_date'] ?? '';
    $dateTimestamp = strtotime($rawDate);
    $trxDateStr    = $dateTimestamp ? date('n/j/y', $dateTimestamp)  : $rawDate;
    $dateLong      = $dateTimestamp ? date('F j, Y', $dateTimestamp) : $rawDate;
    $rawTime       = $entry['entry_time'] ?? '';
    $timeFormatted = $rawTime ? date('H:i', strtotime($rawTime)) : '';

    ensureEntryItemDescriptions($entry);

    $decodedItems   = json_decode($entry['items']   ?? '[]', true);
    if (!is_array($decodedItems))   $decodedItems   = [];
    $decodedTenders = json_decode($entry['tenders'] ?? '[]', true);
    if (!is_array($decodedTenders)) $decodedTenders = [];

    $subtotal    = (float)($entry['subtotal']      ?? 0);
    $vatableSales= (float)($entry['vatable_sales'] ?? 0);
    $totalVat    = (float)($entry['total_vat']     ?? 0);
    $totalNonVat = (float)($entry['total_non_vat'] ?? 0);
    $totalAmount = (float)($entry['total_amount']  ?? 0);
    $vatRate     = (float)($entry['vat_rate']      ?? 12.0);
    if ($vatRate <= 0) $vatRate = 12.0;

    if ($subtotal <= 0 && !empty($decodedItems))
        $subtotal = array_sum(array_map(fn($i) => (float)($i['amount'] ?? 0), $decodedItems));
    if ($totalAmount <= 0) $totalAmount = $subtotal;

    if ($vatableSales <= 0 && $totalVat <= 0 && $totalAmount > 0) {
        $vatableSales = round($totalAmount / (1 + ($vatRate / 100)), 2);
        $totalVat     = round($totalAmount - $vatableSales, 2);
    }

    // Separate positive from negative tenders
    $positiveTenders   = [];
    $negativeChangeSum = 0.0;
    foreach ($decodedTenders as $t) {
        $amt = (float)($t['amount'] ?? 0);
        if ($amt < 0) {
            $negativeChangeSum += abs($amt);
        } else {
            $positiveTenders[] = $t;
        }
    }
    // CASH last
    usort($positiveTenders, function($a, $b) {
        $aIsCash = strtolower(trim($a['name'] ?? '')) === 'cash';
        $bIsCash = strtolower(trim($b['name'] ?? '')) === 'cash';
        return $aIsCash === $bIsCash ? 0 : ($aIsCash ? 1 : -1);
    });
    $totalTendered = array_sum(array_map(fn($t) => (float)($t['amount'] ?? 0), $positiveTenders));
    if (empty($decodedTenders)) $totalTendered = $totalAmount;
    $change = ($negativeChangeSum > 0) ? $negativeChangeSum : max(0, $totalTendered - $totalAmount);

    $invoiceFormatted     = !empty($entry['invoice_number']) ? str_pad(ltrim($entry['invoice_number'], '0'), 8, '0', STR_PAD_LEFT) : '00000000';
    $rawTrx               = preg_replace('/[^\d]/', '', (string)$entry['transaction_number']);
    $storeCode            = trim($entry['store_code']      ?? '');
    $regNo                = trim($entry['register_number'] ?? '');
    $tillNo               = trim($entry['till_number']     ?? '0000');
    $totalQty             = array_sum(array_map(fn($i) => (int)abs((float)($i['quantity'] ?? 1)), $decodedItems));

    $fpNumber   = trim($registerRecord['permit_number'] ?? '');
    $serialNum  = trim($registerRecord['serial_number'] ?? ($storeMaster['serial_number'] ?? ''));
    $minNum     = trim($registerRecord['min_number']    ?? ($storeMaster['min_number']    ?? ''));

    $salesAssoc = trim($entry['sales_associate'] ?? '');
    $assocId    = trim($entry['associate_id']    ?? '');
    if ($assocId === '' && ctype_digit($salesAssoc)) { $assocId = $salesAssoc; $salesAssoc = ''; }
    $cashierDisplay = ($salesAssoc === '' && $assocId !== '') ? $assocId : $salesAssoc;

    $divAster = str_repeat('*', $W);
    $divDash  = str_repeat('-', $W);
    $divDbl   = str_repeat('=', $W);

    $lines = [];

    // Header Top Spacing
    $headerTopSpace = max(0, min(10, (int)($settings['header_top_space'] ?? 1)));
    for ($i = 0; $i < $headerTopSpace; $i++) {
        $lines[] = rcBlankLineBatch($W);
    }

    // Header
    $lines[] = $divAster;
    if ($storeMaster) {
        $hText = trim($storeMaster['header'] ?? '');
        if ($hText !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $hText) as $hl) {
                $trimmed = trim($hl);
                if ($trimmed !== '') $lines[] = rcPadBatch($trimmed, $W);
            }
        } elseif (!empty($storeMaster['store_name'])) {
            $lines[] = rcPadBatch(trim($storeMaster['store_name']), $W);
        }
        if (!empty($serialNum)) $lines[] = rcPadBatch('SERIAL#' . $serialNum, $W);
        if (!empty($minNum))    $lines[] = rcPadBatch('MIN' . $minNum, $W);
    }
    $lines[] = $divAster;

    // Reprint Banner
    $reprintLineCount     = max(1, min(3,  (int)($settings['reprint_line_count'] ?? 2)));
    $reprintTopSpace      = max(0, min(10, (int)($settings['reprint_top_space'] ?? 1)));
    $reprintBetweenSpace  = max(0, min(10, (int)($settings['reprint_between_space'] ?? 0)));
    $reprintBottomSpace   = max(0, min(10, (int)($settings['reprint_bottom_space'] ?? 1)));
    $reprintAsteriskSpace = max(0, min(8,  (int)($settings['reprint_asterisk_space'] ?? 1)));
    $reprintCharSpace     = max(0, min(4,  (int)($settings['reprint_char_space'] ?? 1)));

    $reprintChars = ['R', 'E', 'P', 'R', 'I', 'N', 'T'];
    $reprintInner = implode(str_repeat(' ', $reprintCharSpace), $reprintChars);
    $reprintBanner = '***' . str_repeat(' ', $reprintAsteriskSpace) . $reprintInner . str_repeat(' ', $reprintAsteriskSpace) . '***';

    for ($i = 0; $i < $reprintTopSpace; $i++) {
        $lines[] = rcBlankLineBatch($W);
    }

    for ($r = 0; $r < $reprintLineCount; $r++) {
        if ($r > 0) {
            for ($i = 0; $i < $reprintBetweenSpace; $i++) {
                $lines[] = rcBlankLineBatch($W);
            }
        }
        $lines[] = rcPadBatch($reprintBanner, $W);
    }

    for ($i = 0; $i < $reprintBottomSpace; $i++) {
        $lines[] = rcBlankLineBatch($W);
    }

    // Customer
    $memberNo = trim($entry['member_number'] ?? ''); if ($memberNo === '0') $memberNo = '';
    $custName = trim($entry['customer_name'] ?? ''); if ($custName === '0') $custName = '';
    $custAddr = trim($entry['customer_address'] ?? ''); if ($custAddr === '0') $custAddr = '';
    $custTin  = trim($entry['customer_tin']  ?? ''); if ($custTin  === '0') $custTin  = '';
    $lines[] = 'Member # : ' . $memberNo;
    $lines[] = 'Name     : ' . $custName;
    $lines[] = 'Address  : ' . $custAddr;
    $lines[] = 'TIN      : ' . $custTin;
    $lines[] = rcBlankLineBatch($W);

    // Items header
    $lines[] = str_pad('QTY', $colQtyW, ' ', STR_PAD_LEFT)
             . '  ' . str_pad('ITEM', $colSkuW, ' ', STR_PAD_RIGHT)
             . '  ' . str_pad('PRICE', $colPriceW, ' ', STR_PAD_LEFT)
             . '  ' . str_pad('TOTAL', $colAmtW, ' ', STR_PAD_LEFT);
    $lines[] = str_pad(str_repeat('-', min($colQtyW, 3)), $colQtyW, ' ', STR_PAD_LEFT)
             . '  ' . str_pad(str_repeat('-', min($colSkuW, 5)), $colSkuW, ' ', STR_PAD_RIGHT)
             . '  ' . str_pad(str_repeat('-', min($colPriceW, 5)), $colPriceW, ' ', STR_PAD_LEFT)
             . '  ' . str_pad(str_repeat('-', min($colAmtW, 5)), $colAmtW, ' ', STR_PAD_LEFT);

    foreach ($decodedItems as $itm) {
        $qty    = (float)($itm['quantity']  ?? 1);
        $amt    = (float)($itm['amount']    ?? 0);
        $uPrice = (float)($itm['unit_price'] ?? ($qty > 0 ? $amt / $qty : $amt));
        $skuRaw = str_pad(trim($itm['sku'] ?? ''), 9, '0', STR_PAD_LEFT);
        $desc   = mb_substr(trim($itm['description'] ?? ''), 0, $W - 4);
        $amtV   = number_format($amt, 2, '.', ',') . 'V';

        $lines[] = str_pad((int)$qty, $colQtyW, ' ', STR_PAD_LEFT)
                 . '  ' . str_pad($skuRaw, $colSkuW, ' ', STR_PAD_RIGHT)
                 . '  ' . str_pad(number_format($uPrice, 2, '.', ','), $colPriceW, ' ', STR_PAD_LEFT)
                 . '  ' . str_pad($amtV, $colAmtW, ' ', STR_PAD_LEFT);
        $lines[] = '     ' . $desc;
    }

    // Totals
    $lines[] = str_pad('Sub Total', $colSubLabel, ' ', STR_PAD_LEFT) . str_pad('P' . number_format($subtotal, 2, '.', ','), $colSubAmt, ' ', STR_PAD_LEFT);
    $lines[] = str_pad('Total',     $colTotalLabel,' ', STR_PAD_LEFT) . str_pad('P' . number_format($totalAmount, 2, '.', ','), $colTotalAmt, ' ', STR_PAD_LEFT);

    // Tenders
    if (empty($positiveTenders)) {
        $lines[] = str_pad('Cash', $colTenderLabel, ' ', STR_PAD_LEFT) . str_pad('P' . number_format($totalAmount, 2, '.', ','), $colTenderAmt, ' ', STR_PAD_LEFT);
    } else {
        foreach ($positiveTenders as $t) {
            $tName = trim($t['name'] ?? 'Cash');
            $tName = $tenderNameMap[strtolower($tName)] ?? $tName;
            $tAmt  = (float)($t['amount'] ?? 0);
            $lines[] = str_pad($tName, $colTenderLabel, ' ', STR_PAD_LEFT) . str_pad('P' . number_format($tAmt, 2, '.', ','), $colTenderAmt, ' ', STR_PAD_LEFT);
        }
    }

    // Change with arrow
    $changeFormatted = ($change > 0) ? 'P-' . number_format($change, 2, '.', ',') : 'P' . number_format(abs($change), 2, '.', ',');
    $lines[] = str_pad('CHANGE', $colChangeLabel, ' ', STR_PAD_LEFT) . '  ====>' . str_pad($changeFormatted, $colChangeAmt, ' ', STR_PAD_LEFT);

    // VAT Breakdown
    $vatExempt   = 0.0;
    $zeroRated   = 0.0;
    $lines[] = str_pad('VATable Sales  :', $colVatLabel, ' ', STR_PAD_RIGHT) . str_pad('P' . number_format($vatableSales, 2, '.', ','), $colVatAmt, ' ', STR_PAD_LEFT);
    $lines[] = str_pad('VAT            :', $colVatLabel, ' ', STR_PAD_RIGHT) . str_pad('P' . number_format($totalVat, 2, '.', ','),     $colVatAmt, ' ', STR_PAD_LEFT);
    $lines[] = str_pad('VAT Exempt Sale:', $colVatLabel, ' ', STR_PAD_RIGHT) . str_pad('P' . number_format($vatExempt, 2, '.', ','),    $colVatAmt, ' ', STR_PAD_LEFT);
    $lines[] = str_pad('Zero Rated Sale:', $colVatLabel, ' ', STR_PAD_RIGHT) . str_pad('P' . number_format($zeroRated, 2, '.', ','),    $colVatAmt, ' ', STR_PAD_LEFT);
    $lines[] = 'Cashier: ' . $cashierDisplay;

    // Dash divider before footer
    $lines[] = $divDash;

    // Store Footer
    $footerText = trim($storeMaster['footer'] ?? '');
    if ($footerText !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $footerText) as $fl) {
            $trimmed = trim($fl);
            if ($trimmed !== '') $lines[] = $trimmed;
        }
    }

    // Double divider
    $lines[] = $divDbl;

    // Transaction summary
    $lines[] = "T#{$rawTrx} S{$storeCode} Reg{$regNo}/{$tillNo} {$trxDateStr} {$timeFormatted}";
    $lines[] = 'Invoice #: ' . $invoiceFormatted . str_repeat(' ', 4) . "TQty:==>   {$totalQty}";

    // Accreditation info (printed below Invoice - always appears regardless of store code)
    $accredText = trim($storeMaster['accreditation'] ?? '');
    if ($accredText === '') {
        $stmtAcc = $pdo->query("SELECT accreditation FROM store_master WHERE accreditation != '' ORDER BY id DESC LIMIT 1");
        $globalAcc = $stmtAcc ? $stmtAcc->fetchColumn() : '';
        if ($globalAcc && trim($globalAcc) !== '') {
            $accredText = trim($globalAcc);
        }
    }
    if ($accredText !== '') {
        $aLines = preg_split('/\r\n|\r|\n/', $accredText);
        foreach ($aLines as $al) {
            $t = trim($al);
            if ($t === '') continue;
            if (stripos($t, '1234567895431') !== false) continue;
            $lines[] = $t;
        }
    }

    // Feed lines for auto-cutter clearance past print head
    $bottomFeedLines = (int)($settings['cut_margin_bottom'] ?? 4);
    for ($i = 0; $i < $bottomFeedLines; $i++) {
        $lines[] = rcBlankLineBatch($W);
    }

    return $lines;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Batch Receipt Reprint (<?php echo count($entries); ?> Records)</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
  @page { size: 80mm auto; margin: 0; }
  * { box-sizing: border-box; }
  body {
      font-family: Arial, Helvetica, sans-serif;
      background: #f1f5f9;
      margin: 0; padding: 0;
  }
  .controls-bar {
      position: sticky; top: 0;
      background: #4c1d95;
      color: #fff;
      padding: 8px 18px;
      display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      z-index: 1000;
  }
  .controls-bar a, .controls-bar button, .controls-bar select {
      margin: 0 !important;
      margin-top: 0 !important;
      height: 36px;
      box-sizing: border-box;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      vertical-align: middle;
      line-height: 1;
  }
  .controls-bar a, .controls-bar button {
      background: #7c3aed; color: #fff; border: none;
      padding: 0 16px; border-radius: 20px; font-weight: 600;
      cursor: pointer; text-decoration: none; font-size: 13px;
      gap: 6px;
      white-space: nowrap;
      transition: all 0.18s ease;
  }
  .controls-bar a:hover, .controls-bar button:hover { background: #6d28d9; transform: translateY(-1px); }
  .controls-bar button.btn-print { background: #059669; font-size: 13px; }
  .controls-bar button.btn-print:hover { background: #047857; }
  .controls-bar a.btn-back { background: #475569; }
  .controls-bar a.btn-back:hover { background: #334155; }
  .controls-bar select {
      border-radius: 18px;
      padding: 0 12px;
  }
  .page-viewport { padding: 24px 15px 60px; display: flex; flex-direction: column; align-items: center; gap: 24px; overflow-x: auto; max-width: 100%; }
  .receipt-sheet {
      width: fit-content;
      min-width: 80mm;
      max-width: 100%;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
      padding: 8px 0;
      box-sizing: border-box;
      page-break-after: always;
      break-after: page;
  }
  .receipt-sheet:last-child {
      page-break-after: auto;
      break-after: auto;
  }
  .receipt-info-badge {
      font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600;
      color: #6d28d9; background: #ede9fe;
      padding: 4px 12px; border-radius: 12px;
  }
  pre.receipt-content {
      font-family: 'Courier New', Courier, 'Lucida Console', monospace;
      font-size: <?php echo $fontSize; ?>px;
      line-height: <?php echo $lineHeight; ?>;
      letter-spacing: -0.2px;
      color: #000000;
      margin: 0;
      padding: <?php echo $paddingY; ?>px <?php echo $paddingX; ?>px;
      white-space: pre;
      box-sizing: border-box;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
  }
  .modal-overlay {
      display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15,23,42,0.7); backdrop-filter: blur(2px);
      z-index: 9999; justify-content: center; align-items: center;
      padding: 16px;
      overflow-y: auto;
      box-sizing: border-box;
  }
  .modal-overlay.active { display: flex; }
  .modal-box {
      background: #fff; border-radius: 10px; width: 620px; max-width: min(620px, 95vw);
      max-height: calc(100vh - 48px);
      display: flex; flex-direction: column;
      box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25); overflow: hidden;
      margin: auto;
  }
  .modal-header {
      padding: 14px 20px; background: #4c1d95; color: #fff;
      display: flex; justify-content: space-between; align-items: center;
      flex-shrink: 0;
  }
  .modal-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
  .modal-close { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; }
  .modal-body {
      padding: 20px; font-size: 13px; color: #334155; line-height: 1.6;
      overflow-y: auto;
      flex: 1 1 auto;
  }
  .step-box {
      background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
      padding: 12px 14px; margin-bottom: 12px;
  }
  .step-box strong { color: #0f172a; }
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
      }
      .controls-bar, .page-viewport > .receipt-info-badge, .modal-overlay, #imgPrintSpinner {
          display: none !important;
      }
      .page-viewport {
          padding: 0 !important;
          margin: 0 !important;
          display: block !important;
          width: 100% !important;
          gap: 0 !important;
      }
      .receipt-sheet {
          width: 100% !important;
          max-width: 100% !important;
          border: none !important;
          box-shadow: none !important;
          border-radius: 0 !important;
          padding: 0 !important;
          margin: 0 !important;
          background: #fff !important;
          page-break-after: always !important;
          break-after: page !important;
      }
      .receipt-sheet:last-child {
          page-break-after: auto !important;
          break-after: auto !important;
      }
      pre.receipt-content {
          font-family: 'Courier New', Courier, monospace !important;
          font-size: <?php echo $fontSize; ?>px !important;
          line-height: <?php echo $lineHeight; ?> !important;
          padding: 0 !important;
          margin: 0 !important;
          white-space: pre !important;
          word-wrap: normal !important;
          overflow: hidden !important;
          color: #000 !important;
          letter-spacing: -0.2px !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
      }
  }
</style>
</head>
<body>
<div class="controls-bar">
    <button class="btn-print" id="btnDirectBatchPrint" onclick="sendDirectBatchPrint()" style="background:#16a34a;">
        ⚡ Direct Batch Print (<?php echo count($entries); ?> Receipts)
    </button>
    <select id="direct_printer_select" style="background:#334155;color:#fff;border:1px solid #475569;font-size:12px;font-weight:600;outline:none;cursor:pointer;">
        <?php foreach ($rawPrinters as $p): ?>
            <option value="<?php echo htmlspecialchars($p); ?>" <?php if ($p === $preferredPrinter) echo 'selected'; ?>>🖨️ <?php echo htmlspecialchars($p); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn-print" onclick="window.print()" style="background:#475569;">📄 Browser Print (Ctrl+P)</button>
    <a class="btn-back" href="receipt_reprint.php">← Back to Receipt Reprint</a>
    <a href="receipt_print_layout.php" style="background:#0284c7;">⚙️ Layout &amp; Font Size Settings</a>
    <button type="button" onclick="document.getElementById('cut_help_modal').classList.add('active')" style="background:#d97706; margin-left:auto !important;">✂️ Auto-Cut Help</button>
</div>

<!-- Floating Toast Notification -->
<div id="printToast" style="display:none;position:fixed;bottom:25px;right:25px;background:#0f172a;color:#fff;padding:12px 20px;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.3);font-family:system-ui,sans-serif;font-size:14px;font-weight:600;z-index:99999;transition:all 0.3s ease;display:none;align-items:center;gap:10px;">
    <span id="printToastIcon">✓</span>
    <span id="printToastMsg">Printed successfully</span>
</div>

<div class="page-viewport">
    <div class="receipt-info-badge">🧾 Batch Receipt Print | <?php echo count($entries); ?> Records Selected | 80mm Thermal Paper</div>
    <?php foreach ($entries as $idx => $entry): ?>
        <?php $lines = generateReceiptLines($entry, $tenderNameMap, $settings, $pdo); ?>
        <div class="receipt-sheet">
            <pre class="receipt-content"><?php echo htmlspecialchars(implode("\n", $lines)); ?></pre>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Auto-Cut Instructions -->
<div class="modal-overlay" id="cut_help_modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>✂️ How to Enable Auto-Cut for "Generic / Text Only" Driver</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('cut_help_modal').classList.remove('active')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-top:0;">Because you are using the Windows <strong>Generic / Text Only</strong> driver, the printer needs the hardware cut command defined in <strong>Printer Properties &rarr; Printer Commands</strong>:</p>

            <div class="step-box" style="border-left: 4px solid #7c3aed;">
                <strong>Step 1: Open Printer Properties</strong><br>
                Press <kbd>Win + R</kbd>, type <code>control printers</code> and press Enter.<br>
                Right-click your <strong>Generic / Text Only</strong> printer &rarr; choose <strong>Printer properties</strong> (<em>not Printing Preferences</em>).
            </div>

            <div class="step-box" style="border-left: 4px solid #7c3aed;">
                <strong>Step 2: Go to "Printer Commands" Tab</strong><br>
                Click on the <strong>Printer Commands</strong> tab at the top of the properties window.
            </div>

            <div class="step-box" style="border-left: 4px solid #059669; background: #f0fdf4;">
                <strong style="color: #15803d;">Step 3: Enter the Cut Code in "End Print Job"</strong><br>
                In the <strong>End Print Job</strong> text box, paste one of the following codes:<br>
                <div style="margin-top: 6px; display: flex; gap: 8px; align-items: center;">
                    <code style="background: #1e293b; color: #38bdf8; padding: 4px 8px; border-radius: 4px; font-size: 13px; font-weight: bold;">&lt;1D&gt;V&lt;01&gt;</code>
                    <span style="font-size: 11px; color: #64748b;">(Standard Partial Cut - Recommended)</span>
                </div>
                <div style="margin-top: 4px; display: flex; gap: 8px; align-items: center;">
                    <code style="background: #1e293b; color: #38bdf8; padding: 4px 8px; border-radius: 4px; font-size: 13px; font-weight: bold;">&lt;1B&gt;d&lt;04&gt;&lt;1D&gt;V&lt;01&gt;</code>
                    <span style="font-size: 11px; color: #64748b;">(Feed 4 lines then partial cut)</span>
                </div>
                <div style="margin-top: 4px; display: flex; gap: 8px; align-items: center;">
                    <code style="background: #1e293b; color: #38bdf8; padding: 4px 8px; border-radius: 4px; font-size: 13px; font-weight: bold;">&lt;1D&gt;V&lt;00&gt;</code>
                    <span style="font-size: 11px; color: #64748b;">(Full Cut)</span>
                </div>
            </div>

            <div class="step-box" style="border-left: 4px solid #7c3aed;">
                <strong>Step 4: Save &amp; Print</strong><br>
                Click <strong>Apply</strong> &rarr; <strong>OK</strong>. Then click <strong>Batch Print</strong> again.
            </div>

            <button type="button" class="btn" style="background:#4c1d95;color:#fff;width:100%;padding:9px;border:none;border-radius:6px;font-weight:bold;cursor:pointer;" onclick="document.getElementById('cut_help_modal').classList.remove('active')">Got it!</button>
        </div>
    </div>
</div>

<script>
document.getElementById('cut_help_modal')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});

const batchIds = <?php echo json_encode(array_values($idList)); ?>;

let toastTimer = null;
function showToast(msg, isError = false) {
    const toast = document.getElementById('printToast');
    const toastMsg = document.getElementById('printToastMsg');
    const toastIcon = document.getElementById('printToastIcon');
    if (!toast) return;

    if (toastTimer) clearTimeout(toastTimer);
    toast.style.background = isError ? '#dc2626' : '#16a34a';
    toastIcon.textContent = isError ? '❌' : '✓';
    toastMsg.textContent = msg;
    toast.style.display = 'flex';

    toastTimer = setTimeout(() => {
        toast.style.display = 'none';
    }, 4500);
}

async function sendDirectBatchPrint() {
    const btn = document.getElementById('btnDirectBatchPrint');
    const printer = document.getElementById('direct_printer_select')?.value || '';
    const origHtml = btn.innerHTML;
    btn.innerHTML = '⏳ Sending to Printer...';
    btn.disabled = true;

    try {
        const res = await fetch('receipt_direct_print_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ids: batchIds,
                printer: printer
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Batch printed directly to thermal printer!');
        } else {
            showToast(data.error || 'Direct batch print failed', true);
        }
    } catch (err) {
        showToast('Network error: ' + err.message, true);
    } finally {
        btn.innerHTML = origHtml;
        btn.disabled = false;
    }
}
</script>
</body>
</html>
