<?php
// ej_print.php - Exact Letter-format reproduction of Electronic Journal Copy
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/ej_db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$entry = getEjEntryById($id);

if (!$entry) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="style.css"></head><body>
          <div class="container"><h1>EJ Entry not found</h1>
          <a class="btn" href="ej_printing.php">Back to EJ Records</a></div></body></html>';
    exit;
}

// Load Store Master
$storeMaster = null;
if (!empty($entry['store_code'])) {
    $smStmt = $pdo->prepare("SELECT * FROM store_master WHERE store_code = :code OR store_name = :code ORDER BY id DESC LIMIT 1");
    $smStmt->execute([':code' => $entry['store_code']]);
    $storeMaster = $smStmt->fetch(PDO::FETCH_ASSOC);
}
if (!$storeMaster) {
    $storeMaster = getStoreMaster();
}

// Load Register Master for Serial, MIN, Permit
$registerRecord = null;
if (!empty($entry['store_code']) && !empty($entry['register_number'])) {
    $regStmt = $pdo->prepare("
        SELECT * FROM register_master
        WHERE store_code = :sc AND reg_no = :rn
        ORDER BY id DESC LIMIT 1
    ");
    $regStmt->execute([':sc' => $entry['store_code'], ':rn' => $entry['register_number']]);
    $registerRecord = $regStmt->fetch(PDO::FETCH_ASSOC);
}

$tenderNameMap = getTenderNameMap();

// Format Date for Header: e.g. "1/31/2019" or "8/19/2026" (no leading zero on month)
$rawDate = trim($entry['entry_date'] ?? '');
$dateTimestamp = strtotime($rawDate);
if (!$dateTimestamp && preg_match('/^(\d{1,2})[\s\-\/](\d{1,2})[\s\-\/](\d{2,4})$/', $rawDate, $dm)) {
    $dateTimestamp = strtotime("{$dm[1]}/{$dm[2]}/{$dm[3]}");
}
if ($dateTimestamp) {
    $headerDate = date('n/j/Y', $dateTimestamp);
    $trxDateStr = date('n/j/y', $dateTimestamp);
} else {
    $cleanDate = preg_replace('/\s+/', '/', $rawDate);
    $headerDate = ltrim($cleanDate, '0');
    $trxDateStr = $rawDate;
}

$rawTime = $entry['entry_time'] ?? '';
$timeFormatted = $rawTime ? date('H:i', strtotime($rawTime)) : '';

// Ensure SKU descriptions are populated (resolves from AS400 MMLTSLIB.INVMST if blank)
ensureEntryItemDescriptions($entry);

// Decode items and tenders
$decodedItems = json_decode($entry['items'] ?? '[]', true);
if (!is_array($decodedItems)) $decodedItems = [];

$decodedTenders = json_decode($entry['tenders'] ?? '[]', true);
if (!is_array($decodedTenders)) $decodedTenders = [];

// Values & Totals
$subtotal     = (float)($entry['subtotal'] ?? 0);
$vatableSales = (float)($entry['vatable_sales'] ?? 0);
$totalVat     = (float)($entry['total_vat'] ?? 0);
$totalNonVat  = (float)($entry['total_non_vat'] ?? 0);
$totalAmount  = (float)($entry['total_amount'] ?? 0);
$vatRate      = (float)($entry['vat_rate'] ?? 12.0);
if ($vatRate <= 0) $vatRate = 12.0;

// If subtotal is not stored, compute it from line items
if ($subtotal <= 0 && !empty($decodedItems)) {
    $subtotal = array_sum(array_map(fn($i) => (float)($i['amount'] ?? 0), $decodedItems));
}
// If total_amount is also missing, fall back to subtotal
if ($totalAmount <= 0) {
    $totalAmount = $subtotal;
}

// Auto-calculate VATable Sales & Total VAT if not stored or 0
if ($vatableSales <= 0 && $totalVat <= 0 && $totalAmount > 0) {
    $vatableBase  = max(0, $totalAmount - $totalNonVat);
    $vatableSales = round($vatableBase / (1 + ($vatRate / 100)), 2);
    $totalVat     = round($vatableBase - $vatableSales, 2);
}

// Separate positive tenders from negative (negative amounts = change stored as a tender line)
$positiveTenders = [];
$negativeChangeSum = 0;
foreach ($decodedTenders as $t) {
    $tAmt = (float)($t['amount'] ?? 0);
    if ($tAmt < 0) {
        $negativeChangeSum += abs($tAmt);
    } else {
        $positiveTenders[] = $t;
    }
}

// Sort: CASH-type tenders go last (after GC and other tenders)
usort($positiveTenders, function($a, $b) {
    $aIsCash = (strtolower(trim($a['name'] ?? '')) === 'cash');
    $bIsCash = (strtolower(trim($b['name'] ?? '')) === 'cash');
    if ($aIsCash && !$bIsCash) return 1;
    if (!$aIsCash && $bIsCash) return -1;
    return 0;
});

// Total tendered = sum of positive tenders only
$totalTendered = 0;
foreach ($positiveTenders as $t) {
    $totalTendered += (float)($t['amount'] ?? 0);
}
if (empty($decodedTenders)) {
    $totalTendered = $totalAmount;
}
// Use negative-tender sum as change if present; otherwise compute from totals
$change = ($negativeChangeSum > 0) ? $negativeChangeSum : max(0, $totalTendered - $totalAmount);

// Invoice number formatted
$invoiceFormatted = !empty($entry['invoice_number']) ? str_pad(ltrim($entry['invoice_number'], '0'), 8, '0', STR_PAD_LEFT) : '00000000';
$transactionFormatted = number_format((float)($entry['transaction_number'] ?? 0), 0);
$rawTrx = preg_replace('/[^\d]/', '', (string)$entry['transaction_number']);

$settings = getEjPrintSettings();
$paperSize = $settings['paper_size'] ?? 'a4';
$sheetMargin = (float)($settings['sheet_margin'] ?? 0.30);
$topTitleMarginTop = (int)($settings['top_title_margin_top'] ?? 25);
$topTitleFontSize = (int)($settings['top_title_font_size'] ?? 16);
$metaGap = (int)($settings['meta_gap'] ?? 65);
$metaFontSize = (int)($settings['meta_font_size'] ?? 14);
$W = (int)($settings['char_width'] ?? 54);
$fontSize = (float)($settings['font_size'] ?? 15);
$lineHeight = (float)($settings['line_height'] ?? 1.36);
$receiptPaddingX = (int)($settings['receipt_padding_x'] ?? 20);
$receiptPaddingY = (int)($settings['receipt_padding_y'] ?? 6);
$wmText = $settings['watermark_text'] ?? 'Electronic Journal Copy';
$wmFontSize = (int)($settings['watermark_font_size'] ?? 60);
$wmTop = (int)($settings['watermark_top'] ?? 75);
$wmLeft = (int)($settings['watermark_left'] ?? 50);
$wmOpacity = (float)($settings['watermark_opacity'] ?? 0.16);
$wmLetterSpacing = (float)($settings['watermark_letter_spacing'] ?? 3);

$colSkuWidth = (int)($settings['col_sku_width'] ?? 16);
$colDescGap = (int)($settings['col_desc_gap'] ?? 3);
$colPriceWidth = (int)($settings['col_price_width'] ?? 16);
$colQtyWidth = (int)($settings['col_qty_width'] ?? 6);
$colAmtWidth = (int)($settings['col_amt_width'] ?? 20);

$colSubtotalLabel = (int)($settings['col_subtotal_label'] ?? 28);
$colSubtotalAmt = (int)($settings['col_subtotal_amt'] ?? 20);
$colTaxLabel = (int)($settings['col_tax_label'] ?? 22);
$colTaxAmt = (int)($settings['col_tax_amt'] ?? 26);
$colTotalLabel = (int)($settings['col_total_label'] ?? 24);
$colTotalAmt = (int)($settings['col_total_amt'] ?? 24);
$colTenderLabel = (int)($settings['col_tender_label'] ?? 23);
$colTenderAmt = (int)($settings['col_tender_amt'] ?? 25);
$colChangeLabel = (int)($settings['col_change_label'] ?? 25);
$colChangeAmt = (int)($settings['col_change_amt'] ?? 18);

$colBirVatAmt = (int)($settings['col_bir_vat_amt'] ?? 20);
$colVatableAmt = (int)($settings['col_vatable_amt'] ?? 22);
$colVatAmt = (int)($settings['col_vat_amt'] ?? 22);
$colNonVatAmt = (int)($settings['col_nonvat_amt'] ?? 18);

function padCenter($str, $width = 54) {
    $len = strlen($str);
    if ($len >= $width) return substr($str, 0, $width);
    $leftPad = (int)(($width - $len) / 2);
    $rightPad = $width - $len - $leftPad;
    return str_repeat(' ', $leftPad) . $str . str_repeat(' ', $rightPad);
}

function formatCustomLine($text, $align = 'center', $width = 54) {
    $text = (string)$text;
    $len = strlen($text);
    if ($len >= $width) return substr($text, 0, $width);
    if ($align === 'left') {
        return str_pad($text, $width, ' ', STR_PAD_RIGHT);
    } elseif ($align === 'right') {
        return str_pad($text, $width, ' ', STR_PAD_LEFT);
    } else {
        return padCenter($text, $width);
    }
}

function appendCustomLines(&$lines, $rawText, $align = 'center', $width = 54) {
    if (trim($rawText) === '') return;
    $lArr = preg_split('/\r\n|\r|\n/', trim($rawText));
    foreach ($lArr as $line) {
        if (trim($line) !== '') {
            $lines[] = formatCustomLine(trim($line), $align, $width);
        }
    }
}

function getDividerLine($type, $custom, $width = 54, $default = 'dash') {
    if ($type === 'none') return '';
    if ($type === 'dash') return str_repeat('-', $width);
    if ($type === 'double') return str_repeat('=', $width);
    if ($type === 'asterisk') return str_repeat('*', $width);
    if ($type === 'custom') return $custom !== '' ? $custom : ($default === 'double' ? str_repeat('=', $width) : str_repeat('-', $width));
    return $default === 'double' ? str_repeat('=', $width) : str_repeat('-', $width);
}

function getTopDivider($type, $custom, $width = 54) {
    return getDividerLine($type, $custom, $width, 'double');
}

function formatHeaderLine($text, $offset = 0, $width = 54) {
    $text = trim((string)$text);
    if ($text === '') return '';
    $off = (int)$offset;
    return str_repeat(' ', max(0, $off)) . $text;
}

$divDouble = str_repeat('=', $W);
$divDash   = str_repeat('-', $W);

$lines = [];

// Top Header Divider (above store header)
$topDivType   = $settings['top_divider_type'] ?? 'double';
$topDivCustom = $settings['top_divider_custom'] ?? '';
$topDivider   = getDividerLine($topDivType, $topDivCustom, $W, 'double');
if ($topDivider !== '') {
    $lines[] = $topDivider;
}

// Store Header lines & Register Serial#, MIN#, Permit# with granular offsets
if ($storeMaster) {
    $hText = trim($storeMaster['header'] ?? '');
    if ($hText !== '') {
        $hLines = preg_split('/\r\n|\r|\n/', $hText);
        foreach ($hLines as $idx => $hl) {
            $trimmed = trim($hl);
            if ($trimmed === '') continue;

            $offset = 0;
            if ($idx === 0) {
                $offset = (int)($settings['header_name_offset'] ?? 0);
            } elseif ($idx === 1) {
                $offset = (int)($settings['header_company_offset'] ?? 0);
            } elseif (stripos($trimmed, 'TIN') !== false) {
                $offset = (int)($settings['header_tin_offset'] ?? 0);
            } elseif (stripos($trimmed, 'Tel') !== false) {
                $offset = (int)($settings['header_tel_offset'] ?? 0);
            } elseif (stripos($trimmed, 'Acc#') !== false) {
                $offset = (int)($settings['header_acc_offset'] ?? 0);
            } else {
                $offset = (int)($settings['header_addr_offset'] ?? 0);
            }
            $lines[] = formatHeaderLine($trimmed, $offset, $W);
        }
    } elseif (!empty($storeMaster['store_name'])) {
        $offset = (int)($settings['header_name_offset'] ?? 0);
        $lines[] = formatHeaderLine(trim($storeMaster['store_name']), $offset, $W);
    }

    // Append register-level Serial#, MIN#, Permit#
    $serOffset = (int)($settings['header_serial_offset'] ?? 0);
    $minOffset = (int)($settings['header_min_offset'] ?? 0);
    $perOffset = (int)($settings['header_permit_offset'] ?? 0);

    if ($registerRecord) {
        if (!empty($registerRecord['serial_number'])) {
            $lines[] = formatHeaderLine('SERIAL#' . trim($registerRecord['serial_number']), $serOffset, $W);
        }
        if (!empty($registerRecord['min_number'])) {
            $lines[] = formatHeaderLine('MIN' . trim($registerRecord['min_number']), $minOffset, $W);
        }
        if (!empty($registerRecord['permit_number'])) {
            $lines[] = formatHeaderLine(str_replace('-', '', trim($registerRecord['permit_number'])), $perOffset, $W);
        }
    } elseif ($storeMaster) {
        // Fallback to store_master if no register record found
        if (!empty($storeMaster['serial_number']) && (empty($storeMaster['header']) || stripos($storeMaster['header'], $storeMaster['serial_number']) === false)) {
            $lines[] = formatHeaderLine('SERIAL#' . trim($storeMaster['serial_number']), $serOffset, $W);
        }
        if (!empty($storeMaster['min_number']) && (empty($storeMaster['header']) || stripos($storeMaster['header'], $storeMaster['min_number']) === false)) {
            $lines[] = formatHeaderLine('MIN' . trim($storeMaster['min_number']), $minOffset, $W);
        }
        if (!empty($storeMaster['permit_number']) && (empty($storeMaster['header']) || stripos($storeMaster['header'], $storeMaster['permit_number']) === false)) {
            $lines[] = formatHeaderLine(str_replace('-', '', trim($storeMaster['permit_number'])), $perOffset, $W);
        }
    }
}

// Custom Header Note (if configured in layout settings)
appendCustomLines($lines, $settings['custom_header_text'] ?? '', $settings['custom_header_align'] ?? 'center', $W);

// Member # and Customer Name
$memberNo = trim($entry['member_number'] ?? '');
if ($memberNo === '0') $memberNo = '';
$lines[] = 'Member # : ' . $memberNo;

$custName = trim($entry['customer_name'] ?? '');
if ($custName === '0') $custName = '';
$lines[] = 'Name     : ' . $custName;

// Customer Address & TIN (always printed)
$custAddr = trim($entry['customer_address'] ?? '');
if ($custAddr === '0') $custAddr = '';
$lines[] = 'Address  : ' . $custAddr;

$custTin  = trim($entry['customer_tin'] ?? '');
if ($custTin === '0') $custTin = '';
$lines[] = 'TIN      : ' . $custTin;

$lines[] = $divDouble;

// Custom Mid-Receipt Note (if configured in layout settings)
appendCustomLines($lines, $settings['custom_mid_text'] ?? '', $settings['custom_mid_align'] ?? 'center', $W);

// Line Items
if (empty($decodedItems)) {
    $lines[] = '  (No Line Items)';
} else {
    foreach ($decodedItems as $itm) {
        $skuRaw = str_pad(trim($itm['sku'] ?? ''), 9, '0', STR_PAD_LEFT);
        $sku = str_pad($skuRaw, $colSkuWidth, ' ', STR_PAD_RIGHT);
        $desc = mb_substr(trim($itm['description'] ?? ''), 0, 18);
        $lines[] = '  ' . $sku . str_repeat(' ', $colDescGap) . $desc;

        $qty = (float)($itm['quantity'] ?? 1);
        $amt = (float)($itm['amount'] ?? 0);
        $uPrice = (float)($itm['unit_price'] ?? ($qty > 0 ? $amt / $qty : $amt));

        $uPriceStr = 'P' . number_format($uPrice, 2, '.', ',');
        $amtStr    = 'P' . number_format($amt, 2, '.', ',') . 'V';

        // Column alignment
        $line2 = str_pad($uPriceStr, $colPriceWidth, ' ', STR_PAD_LEFT) . 
                 str_pad(number_format($qty, 0), $colQtyWidth, ' ', STR_PAD_LEFT) . 
                 str_pad($amtStr, $colAmtWidth, ' ', STR_PAD_LEFT);
        $lines[] = $line2;
    }
}

// Subtotal & Tax
$lines[] = str_pad('Sub Total', $colSubtotalLabel, ' ', STR_PAD_LEFT) . str_repeat(' ', max(0, $colSubtotalAmt)) . 'P' . number_format($subtotal, 2, '.', ',');
$lines[] = str_pad('Tax', $colTaxLabel, ' ', STR_PAD_LEFT) . str_repeat(' ', max(0, $colTaxAmt)) . 'P0.00';
$lines[] = '';

// Total
$lines[] = str_pad('Total', $colTotalLabel, ' ', STR_PAD_LEFT) . str_repeat(' ', max(0, $colTotalAmt)) . 'P' . number_format($totalAmount, 2, '.', ',');

// Tenders Breakdown (positive tenders only; negative tenders are treated as change)
if (empty($positiveTenders)) {
    $lines[] = str_pad('Cash', $colTenderLabel, ' ', STR_PAD_LEFT) . str_repeat(' ', max(0, $colTenderAmt)) . 'P' . number_format($totalAmount, 2, '.', ',');
} else {
    foreach ($positiveTenders as $t) {
        $rawTName = trim($t['name'] ?? 'Cash');
        $tName    = $tenderNameMap[strtolower($rawTName)] ?? $rawTName;
        $tAmt     = (float)($t['amount'] ?? 0);
        $tCode    = strtoupper(trim($t['code'] ?? ''));

        // Extract document number if present in doc_no or within name
        $docNo = trim((string)($t['doc_no'] ?? ''));
        if ($docNo === '' && preg_match('/(?:GC\s*)?#\s*([0-9a-zA-Z]+)/i', $rawTName, $m)) {
            $docNo = $m[1];
        }

        $cleanDoc = '';
        if ($docNo !== '') {
            $cleanDoc = ltrim($docNo, '# ');
            if (stripos($cleanDoc, 'GC#') === 0) {
                $cleanDoc = substr($cleanDoc, 3);
            } elseif (stripos($cleanDoc, 'GC') === 0) {
                $cleanDoc = substr($cleanDoc, 2);
            }
            $cleanDoc = ltrim($cleanDoc, '# ');
        }

        $isGc = (
            in_array($tCode, ['GC', 'EG', 'GIFT']) ||
            stripos($tName, 'gift') !== false ||
            stripos($tName, 'cert') !== false ||
            stripos($rawTName, 'gift') !== false ||
            stripos($rawTName, 'cert') !== false ||
            stripos($rawTName, 'gc#') !== false ||
            ($cleanDoc !== '' && in_array($tCode, ['GC', 'EG', 'GIFT', '']))
        );

        if ($isGc) {
            // Line 1: Gift Cert Redeem #     GC#<number>
            if ($cleanDoc !== '' && $cleanDoc !== '0') {
                $lines[] = 'Gift Cert Redeem #     GC#' . $cleanDoc;
            } else {
                $lines[] = 'Gift Cert Redeem';
            }
            // Line 2: Exchange rate & currency conversion line (* aligned with #)
            $lines[] = '       P0.00 PHP *     1.00000000  P' . number_format($tAmt, 2, '.', ',') . ' PHP';
        } else {
            if (!empty($t['doc_no']) && stripos($tName, (string)$t['doc_no']) === false) {
                $tName .= ' #' . $t['doc_no'];
            }
            $lines[] = str_pad($tName, $colTenderLabel, ' ', STR_PAD_LEFT) . str_repeat(' ', max(0, $colTenderAmt)) . 'P' . number_format($tAmt, 2, '.', ',');
        }
    }
}

// Change: cash (tender) is always positive; change returned to customer is shown as negative
$changeFormatted = ($change > 0)
    ? 'P-' . number_format($change, 2, '.', ',')
    : 'P'  . number_format(abs($change), 2, '.', ',');
$lines[] = str_pad('CHANGE', $colChangeLabel, ' ', STR_PAD_LEFT) . str_repeat(' ', max(0, $colChangeAmt)) . $changeFormatted;

// BIR VAT Line
$vatRateStr = number_format($vatRate, 4, '.', '');
$vatAmtStr = 'P' . number_format($totalVat, 2, '.', ',');
$lines[] = '     BIR    VAT   @ ' . $vatRateStr . str_repeat(' ', max(0, $colBirVatAmt)) . $vatAmtStr;

// Cashier Info
$salesAssoc = trim($entry['sales_associate'] ?? '');
$assocId    = trim($entry['associate_id'] ?? '');

// If associate_id is empty but sales_associate is numeric (e.g. '90121'), display as Associate Id
if ($assocId === '' && ctype_digit($salesAssoc)) {
    $assocId = $salesAssoc;
    $salesAssoc = '';
}

// Ensure associate ID is 7 digits with leading zeros
if ($assocId !== '') {
    $assocId = str_pad($assocId, 7, '0', STR_PAD_LEFT);
}

$lines[] = 'Sales Associate: ' . $salesAssoc;
$lines[] = 'Associate Id:    ' . $assocId;

// Trx Summary Line: Trx 1521 S10005 Reg104/2454 1/31/1910:07
$storeCode = trim($entry['store_code'] ?? '');
$regNo     = trim($entry['register_number'] ?? '');
$tillNo    = trim($entry['till_number'] ?? '0000');
$trxSummary = "Trx {$rawTrx} S{$storeCode} Reg{$regNo}/{$tillNo} {$trxDateStr}{$timeFormatted}";
$lines[] = $trxSummary;

// Invoice Line
$lines[] = 'Invoice#:' . $invoiceFormatted;

// VAT Breakdown
$vatTopDivType   = $settings['vat_top_divider_type'] ?? 'dash';
$vatTopDivCustom = $settings['vat_top_divider_custom'] ?? '';
$vatTopDivider   = getDividerLine($vatTopDivType, $vatTopDivCustom, $W, 'dash');
if ($vatTopDivider !== '') {
    $lines[] = $vatTopDivider;
}

$lines[] = 'VATable Sales:' . str_repeat(' ', max(0, $colVatableAmt)) . 'P' . number_format($vatableSales, 2, '.', ',');
$lines[] = 'VAT:'           . str_repeat(' ', max(0, $colVatAmt))     . 'P' . number_format($totalVat, 2, '.', ',');
$lines[] = 'Non-VATable Sales:' . str_repeat(' ', max(0, $colNonVatAmt)) . 'P' . number_format($totalNonVat, 2, '.', ',');

// Custom Footer Note & Bottom Divider Spacing
$vatBotSpaceBefore = (int)($settings['vat_bottom_space_before'] ?? 0);
for ($i = 0; $i < $vatBotSpaceBefore; $i++) {
    $lines[] = '';
}

$customFooter = $settings['custom_footer_text'] ?? 'THANK YOU FOR SHOPPING!';
$vatBotDivType   = $settings['vat_bottom_divider_type'] ?? 'dash';
$vatBotDivCustom = $settings['vat_bottom_divider_custom'] ?? '';
$vatBotDivider   = getDividerLine($vatBotDivType, $vatBotDivCustom, $W, 'dash');

if ($vatBotDivider !== '') {
    $lines[] = $vatBotDivider;
}

$vatBotSpaceAfter = (int)($settings['vat_bottom_space_after'] ?? 1);
for ($i = 0; $i < $vatBotSpaceAfter; $i++) {
    $lines[] = '';
}

if (trim($customFooter) !== '') {
    appendCustomLines($lines, $customFooter, $settings['custom_footer_align'] ?? 'center', $W);
}
?>
<?php
// Compute pagination chunks so that overflowing items create new pages
$linesPerPage = (int)($settings['lines_per_page'] ?? ($paperSize === 'letter' ? 39 : 42));
if ($linesPerPage <= 0) {
    $linesPerPage = $paperSize === 'letter' ? 39 : 42;
}
$pageChunks = array_chunk($lines, $linesPerPage);
if (empty($pageChunks)) {
    $pageChunks = [[]];
}
$totalPages = count($pageChunks);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Electronic Journal Copy - Trx #<?php echo htmlspecialchars($entry['transaction_number']); ?></title>
<link rel="stylesheet" href="style.css">
<style>
  @page {
      size: <?php echo $paperSize === 'letter' ? 'letter portrait' : 'A4 portrait'; ?>;
      margin: 0;
  }
  * {
      box-sizing: border-box;
  }
  body {
      font-family: Arial, Helvetica, sans-serif;
      color: #000000;
      background: #f1f5f9;
      margin: 0;
      padding: 0;
  }
  .controls-bar {
      position: sticky;
      top: 0;
      background: #0f172a;
      color: #ffffff;
      padding: 12px 24px;
      display: flex;
      gap: 12px;
      align-items: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 1000;
  }
  .controls-bar a, .controls-bar button {
      background: #0284c7;
      color: #ffffff;
      border: none;
      height: 38px;
      padding: 0 18px;
      margin: 0;
      margin-top: 0 !important;
      margin-bottom: 0 !important;
      border-radius: 20px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      line-height: 1;
      white-space: nowrap;
      box-sizing: border-box;
      vertical-align: middle;
      transition: all 0.18s ease;
  }
  .controls-bar a:hover, .controls-bar button:hover {
      background: #0369a1;
      transform: translateY(-1px);
  }
  .controls-bar button.btn-print {
      background: #16a34a;
      font-size: 13px;
      padding: 0 20px;
  }
  .controls-bar button.btn-print:hover {
      background: #15803d;
  }
  .controls-bar a.btn-back {
      background: #475569;
  }
  .controls-bar a.btn-back:hover {
      background: #334155;
  }

  /* Document Sheet Layout */
  .page-viewport {
      padding: 30px 15px 80px 15px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 24px;
  }
  .paper-info-badge {
      font-family: system-ui, -apple-system, sans-serif;
      font-size: 12px;
      font-weight: 600;
      color: #64748b;
      background: #e2e8f0;
      padding: 4px 12px;
      border-radius: 12px;
  }
  .letter-sheet {
      width: <?php echo $paperSize === 'letter' ? '8.5in' : '210mm'; ?>;
      height: <?php echo $paperSize === 'letter' ? '11in' : '297mm'; ?>;
      min-height: <?php echo $paperSize === 'letter' ? '11in' : '297mm'; ?>;
      max-height: <?php echo $paperSize === 'letter' ? '11in' : '297mm'; ?>;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 2px;
      box-shadow: 0 10px 35px rgba(0,0,0,0.14);
      padding: <?php echo $sheetMargin; ?>in;
      position: relative;
      box-sizing: border-box;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      page-break-after: always;
      break-after: page;
      page-break-inside: avoid;
      break-inside: avoid;
  }
  .letter-sheet:last-child {
      page-break-after: auto;
      break-after: auto;
  }

  /* Printable area outline (Visible Margin Guide) */
  .margin-guide {
      position: absolute;
      top: <?php echo $sheetMargin; ?>in;
      left: <?php echo $sheetMargin; ?>in;
      right: <?php echo $sheetMargin; ?>in;
      bottom: <?php echo $sheetMargin; ?>in;
      border: 1px dashed #cbd5e1;
      pointer-events: none;
      z-index: 0;
  }
  .margin-guide::before {
      content: '<?php echo $sheetMargin; ?>in Margin';
      position: absolute;
      top: -16px;
      left: 0;
      font-family: system-ui, sans-serif;
      font-size: 10px;
      color: #94a3b8;
  }

  /* Left-aligned & Full-width Receipt Block */
  .receipt-container {
      width: 100%;
      max-width: 100%;
      margin: 0;
      position: relative;
      z-index: 1;
  }

  /* Title Sections */
  .ej-header-title {
      text-align: center;
      font-size: <?php echo $topTitleFontSize; ?>px;
      font-weight: 800;
      margin-top: <?php echo $topTitleMarginTop; ?>px;
      margin-bottom: 12px;
      color: #000000;
      letter-spacing: 0.5px;
  }
  .ej-footer-title {
      text-align: center;
      font-size: <?php echo $topTitleFontSize; ?>px;
      font-weight: 800;
      margin-top: auto;
      margin-bottom: 0;
      padding-top: 10px;
      padding-bottom: 0;
      color: #000000;
      letter-spacing: 0.5px;
      position: relative;
      z-index: 1;
      width: 100%;
  }
  .ej-meta-bar {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: <?php echo $metaGap; ?>px;
      font-size: <?php echo $metaFontSize; ?>px;
      font-weight: 700;
      color: #000000;
      padding-bottom: 6px;
      border-bottom: 1.5px solid #000000;
      width: 100%;
  }
  .ej-meta-bar .meta-item span.label {
      font-weight: 800;
  }
  .ej-meta-bar .meta-item span.val {
      font-weight: 700;
  }

  /* Receipt Body with Watermark */
  .receipt-body-wrapper {
      position: relative;
      margin-top: 8px;
      padding-bottom: 6px;
      border-bottom: 1.5px solid #000000;
      width: 100%;
      min-height: 200px;
  }
  
  /* Vertical Watermark */
  .watermark {
      position: absolute;
      top: <?php echo $wmTop; ?>%;
      left: <?php echo $wmLeft; ?>%;
      transform: translate(-50%, -50%) rotate(-90deg);
      transform-origin: center center;
      font-family: Arial, Helvetica, sans-serif;
      font-size: <?php echo $wmFontSize; ?>px;
      font-weight: 300;
      color: rgba(6, 182, 212, <?php echo $wmOpacity; ?>);
      white-space: nowrap;
      pointer-events: none;
      z-index: 1;
      letter-spacing: <?php echo $wmLetterSpacing; ?>px;
      user-select: none;
  }

  /* Monospace Receipt Lines */
  pre.receipt-content {
      position: relative;
      z-index: 2;
      font-family: 'Courier New', Courier, monospace;
      font-size: <?php echo $fontSize; ?>px;
      font-weight: 400;
      line-height: <?php echo $lineHeight; ?>;
      color: #000000;
      margin: 0;
      padding: <?php echo $receiptPaddingY; ?>px <?php echo $receiptPaddingX; ?>px;
      white-space: pre;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
  }

  @media print {
      @page {
          size: <?php echo $paperSize === 'letter' ? 'letter portrait' : 'A4 portrait'; ?>;
          margin: 0;
      }
      html, body {
          background: #ffffff !important;
          margin: 0 !important;
          padding: 0 !important;
          width: 100% !important;
          color: #000000 !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
      }
      .controls-bar, .paper-info-badge, .margin-guide, .margin-guide::before, .margin-guide::after {
          display: none !important;
          visibility: hidden !important;
          content: none !important;
          border: none !important;
          height: 0 !important;
          width: 0 !important;
      }
      .page-viewport {
          padding: 0 !important;
          margin: 0 !important;
          display: block !important;
          gap: 0 !important;
      }
      .letter-sheet {
          width: <?php echo $paperSize === 'letter' ? '8.5in' : '210mm'; ?> !important;
          min-height: <?php echo $paperSize === 'letter' ? '11in' : '297mm'; ?> !important;
          max-height: <?php echo $paperSize === 'letter' ? '11in' : '297mm'; ?> !important;
          height: <?php echo $paperSize === 'letter' ? '11in' : '297mm'; ?> !important;
          max-width: 100% !important;
          padding: <?php echo $sheetMargin; ?>in !important;
          margin: 0 !important;
          border: none !important;
          box-shadow: none !important;
          border-radius: 0 !important;
          display: flex !important;
          flex-direction: column !important;
          justify-content: space-between !important;
          box-sizing: border-box !important;
          page-break-after: always !important;
          break-after: page !important;
          page-break-inside: avoid !important;
          break-inside: avoid !important;
          overflow: hidden !important;
      }
      .letter-sheet:last-child {
          page-break-after: auto !important;
          break-after: auto !important;
      }
      .receipt-container {
          width: 100% !important;
          max-width: 100% !important;
          margin: 0 !important;
      }
      .watermark {
          color: rgba(6, 182, 212, <?php echo $wmOpacity; ?>) !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
      }
      /* Keep EXACT same font settings as screen — no unit conversion, no scaling */
      pre.receipt-content {
          font-family: 'Courier New', Courier, monospace !important;
          font-size: <?php echo $fontSize; ?>px !important;
          line-height: <?php echo $lineHeight; ?> !important;
          padding: <?php echo $receiptPaddingY; ?>px <?php echo $receiptPaddingX; ?>px !important;
          margin: 0 !important;
          white-space: pre !important;
          color: #000 !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
      }
      .ej-footer-title {
          margin-top: auto !important;
          margin-bottom: 0 !important;
          padding-top: 10px !important;
          padding-bottom: 0 !important;
      }
  }
</style>
</head>
<body>

<div class="controls-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Print (<?php echo strtoupper($paperSize); ?><?php echo $totalPages > 1 ? " - {$totalPages} Pages" : ''; ?>)</button>
    <a class="btn-back" href="ej_printing.php">← Back to EJ Records</a>
    <a class="btn" href="ej_entry.php?id=<?php echo (int)$entry['id']; ?>">✏️ Edit Entry</a>
    <button type="button" onclick="document.querySelectorAll('.margin-guide').forEach(el => el.style.display = el.style.display === 'none' ? 'block' : 'none')" style="background: #334155; font-size: 12px; margin-left: auto;">📐 Toggle Margin Guides</button>
</div>

<div class="page-viewport">
    <div class="paper-info-badge">
        📄 <?php echo strtoupper($paperSize); ?> Paper (<?php echo $totalPages; ?> <?php echo $totalPages > 1 ? 'Pages' : 'Page'; ?>) | Margins: <?php echo number_format($sheetMargin, 2); ?>" All Sides
    </div>

    <?php foreach ($pageChunks as $pageIdx => $chunkLines): ?>
    <div class="letter-sheet">
        <div class="margin-guide"></div>
        
        <!-- Pinned Page Watermark (Consistent across all pages) -->
        <div class="watermark"><?php echo htmlspecialchars($wmText); ?></div>

        <!-- Top Receipt Container -->
        <div class="receipt-container">
            <!-- Top Title -->
            <div class="ej-header-title">*** Electronic Journal Copy ***</div>

            <!-- Meta Bar: Only on first page -->
            <?php if ($pageIdx === 0): ?>
            <div class="ej-meta-bar">
                <div class="meta-item"><span class="label">Date:</span> <span class="val"><?php echo htmlspecialchars($headerDate); ?></span></div>
                <div class="meta-item"><span class="label">Store:</span> <span class="val"><?php echo htmlspecialchars($entry['store_code']); ?></span></div>
                <div class="meta-item"><span class="label">Register:</span> <span class="val"><?php echo htmlspecialchars($entry['register_number']); ?></span></div>
                <div class="meta-item"><span class="label">Transaction:</span> <span class="val"><?php echo htmlspecialchars($transactionFormatted); ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Receipt Area (Bottom border only on final page) -->
            <div class="receipt-body-wrapper" <?php echo ($pageIdx < $totalPages - 1) ? 'style="border-bottom: none;"' : ''; ?>>
                <pre class="receipt-content"><?php echo htmlspecialchars(implode("\n", $chunkLines)); ?></pre>
            </div>
        </div>

        <!-- Bottom Title: Fixed at bottom of page above margin -->
        <div class="ej-footer-title">*** Electronic Journal Copy ***</div>
    </div>
    <?php endforeach; ?>
</div>

</body>
</html>
