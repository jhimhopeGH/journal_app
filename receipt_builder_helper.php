<?php
// receipt_builder_helper.php - Reusable receipt line generator for both server spooler and raw payload delivery
require_once __DIR__ . '/ej_db.php';

if (!function_exists('padLineCenter')) {
    function padLineCenter($str, $width) {
        $len = mb_strlen($str, 'UTF-8');
        if ($len >= $width) return mb_substr($str, 0, $width, 'UTF-8');
        $l = (int)(($width - $len) / 2);
        return str_repeat(' ', $l) . $str . str_repeat(' ', max(0, $width - $len - $l));
    }
}

if (!function_exists('buildReceiptLinesForEntry')) {
    function buildReceiptLinesForEntry($entry, $tenderNameMap, $settings, $pdo) {
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

        $storeMaster = null;
        if (!empty($entry['store_code'])) {
            $smStmt = $pdo->prepare("SELECT * FROM store_master WHERE store_code = :sc ORDER BY id DESC LIMIT 1");
            $smStmt->execute([':sc' => $entry['store_code']]);
            $storeMaster = $smStmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$storeMaster) $storeMaster = getStoreMaster();

        $registerRecord = null;
        if (!empty($entry['store_code']) && !empty($entry['register_number'])) {
            $regStmt = $pdo->prepare("SELECT * FROM register_master WHERE store_code = :sc AND reg_no = :rn ORDER BY id DESC LIMIT 1");
            $regStmt->execute([':sc' => $entry['store_code'], ':rn' => $entry['register_number']]);
            $registerRecord = $regStmt->fetch(PDO::FETCH_ASSOC);
        }

        $rawDate       = $entry['entry_date'] ?? '';
        $dateTimestamp = strtotime($rawDate);
        $trxDateStr    = $dateTimestamp ? date('n/j/y', $dateTimestamp)  : $rawDate;
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

        $positiveTenders = [];
        $negativeChangeSum = 0.0;
        foreach ($decodedTenders as $t) {
            $amt = (float)($t['amount'] ?? 0);
            if ($amt < 0) {
                $negativeChangeSum += abs($amt);
            } else {
                $positiveTenders[] = $t;
            }
        }
        usort($positiveTenders, function($a, $b) {
            $aIsCash = strtolower(trim($a['name'] ?? '')) === 'cash';
            $bIsCash = strtolower(trim($b['name'] ?? '')) === 'cash';
            return $aIsCash === $bIsCash ? 0 : ($aIsCash ? 1 : -1);
        });
        $totalTendered = array_sum(array_map(fn($t) => (float)($t['amount'] ?? 0), $positiveTenders));
        if (empty($decodedTenders)) $totalTendered = $totalAmount;
        $change = ($negativeChangeSum > 0) ? $negativeChangeSum : max(0, $totalTendered - $totalAmount);

        $invoiceFormatted = !empty($entry['invoice_number']) ? str_pad(ltrim($entry['invoice_number'], '0'), 8, '0', STR_PAD_LEFT) : '00000000';
        $rawTrx           = preg_replace('/[^\d]/', '', (string)$entry['transaction_number']);
        $storeCode        = trim($entry['store_code']      ?? '');
        $regNo            = trim($entry['register_number'] ?? '');
        $tillNo           = trim($entry['till_number']     ?? '0000');
        $totalQty         = array_sum(array_map(fn($i) => (int)abs((float)($i['quantity'] ?? 1)), $decodedItems));

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

        // Header Top Spacing (Lines at very top of receipt)
        $headerTopSpace = max(0, min(10, (int)($settings['header_top_space'] ?? 1)));
        for ($i = 0; $i < $headerTopSpace; $i++) {
            $lines[] = '__FEED__';
        }

        // Header
        $lines[] = $divAster;
        if ($storeMaster) {
            $hText = trim($storeMaster['header'] ?? '');
            if ($hText !== '') {
                foreach (preg_split('/\r\n|\r|\n/', $hText) as $hl) {
                    $trimmed = trim($hl);
                    if ($trimmed !== '') $lines[] = padLineCenter($trimmed, $W);
                }
            } elseif (!empty($storeMaster['store_name'])) {
                $lines[] = padLineCenter(trim($storeMaster['store_name']), $W);
            }
            if (!empty($serialNum)) $lines[] = padLineCenter('SERIAL#' . $serialNum, $W);
            if (!empty($minNum))    $lines[] = padLineCenter('MIN' . $minNum, $W);
        }
        $lines[] = $divAster;

        // Reprint Banner
        $reprintLineCount     = max(1, min(3,  (int)($settings['reprint_line_count'] ?? 2)));
        $reprintTopSpace      = max(0, min(10, (int)($settings['reprint_top_space'] ?? 1)));
        $reprintBetweenSpace  = max(0, min(10, (int)($settings['reprint_between_space'] ?? 0)));
        $reprintBottomSpace   = max(0, min(10, (int)($settings['reprint_bottom_space'] ?? 1)));
        $reprintAsteriskSpace = max(0, min(10, (int)($settings['reprint_asterisk_space'] ?? 1)));
        $reprintCharSpace     = max(0, min(8,  (int)($settings['reprint_char_space'] ?? 1)));

        $reprintChars = ['R', 'E', 'P', 'R', 'I', 'N', 'T'];
        $reprintInner = implode(str_repeat(' ', $reprintCharSpace), $reprintChars);
        $reprintBanner = '***' . str_repeat(' ', $reprintAsteriskSpace) . $reprintInner . str_repeat(' ', $reprintAsteriskSpace) . '***';

        for ($i = 0; $i < $reprintTopSpace; $i++) {
            $lines[] = '__FEED__';
        }

        for ($r = 0; $r < $reprintLineCount; $r++) {
            if ($r > 0) {
                for ($i = 0; $i < $reprintBetweenSpace; $i++) {
                    $lines[] = '__FEED__';
                }
            }
            $lines[] = padLineCenter($reprintBanner, $W);
        }

        for ($i = 0; $i < $reprintBottomSpace; $i++) {
            $lines[] = '__FEED__';
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
        $lines[] = '';

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

        // Dash divider
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

        return $lines;
    }
}
