<?php
// raw_print_service.php - Service for sending raw ESC/POS commands directly to Windows thermal printers

/**
 * Returns a list of installed Windows printers.
 * Cached in the session for 5 minutes to avoid running PowerShell on every page load.
 */
function getAvailableRawPrinters() {
    // Ensure session is started before using $_SESSION
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $cacheKey = '_printer_list_cache';
    $ttlKey   = '_printer_list_ts';
    $ttl      = 300; // 5 minutes

    // Return cached list if still fresh
    if (
        isset($_SESSION[$cacheKey], $_SESSION[$ttlKey]) &&
        (time() - $_SESSION[$ttlKey]) < $ttl
    ) {
        return $_SESSION[$cacheKey];
    }

    // Run PowerShell and cache the result
    $cmd = 'powershell -Command "Get-Printer | Select-Object -ExpandProperty Name"';
    $output = [];
    exec($cmd, $output);
    $printers = array_values(array_filter(array_map('trim', $output)));

    $_SESSION[$cacheKey] = $printers;
    $_SESSION[$ttlKey]   = time();

    return $printers;
}

/**
 * Returns the best auto-detected thermal printer name.
 * Accepts an optional pre-fetched printer list to avoid a duplicate PowerShell call.
 */
function getPreferredThermalPrinter($printers = null) {
    if ($printers === null) {
        $printers = getAvailableRawPrinters();
    }

    // Check in order of priority
    $priorityNames = [
        'EPSON TM-T82 Receipt',
        'EPSON TM-T82-42C ReceiptSA4',
        'Generic / Text Only',
        'Generic / Text Only (Copy 1)'
    ];

    foreach ($priorityNames as $target) {
        foreach ($printers as $p) {
            if (strcasecmp($p, $target) === 0) {
                return $p;
            }
        }
    }

    // Secondary fallback: search for TM-T, POS, Thermal, Receipt
    foreach ($printers as $p) {
        if (preg_match('/(TM-T|POS|Thermal|Receipt|Generic)/i', $p)) {
            return $p;
        }
    }

    return !empty($printers) ? $printers[0] : 'EPSON TM-T82 Receipt';
}

/**
 * Formats an array of receipt lines into a pure ESC/POS byte sequence
 */
function buildEscPosReceiptFromLines(array $lines, array $settings) {
    // ESC/POS Commands
    $ESC = "\x1B";
    $GS  = "\x1D";

    $bin = "";
    // Initialize Printer
    $bin .= $ESC . "@";
    // Character Code Table: PC437 (USA / Standard Europe)
    $bin .= $ESC . "t" . "\x00";
    // Character Font: Font A (12x24 dots, standard 42-column font)
    $bin .= $ESC . "M" . "\x00";

    // Set dynamic line spacing based on line_height from receipt_print_settings.json
    $lineHeight = (float)($settings['line_height'] ?? 1.25);
    if ($lineHeight > 0) {
        // Standard Font A is 24 dots high. Line height multiplier converts to vertical dots (e.g. 1.8 * 24 = ~43 dots, 2.5 * 24 = 60 dots)
        $dots = max(18, min(96, (int)round(24 * $lineHeight)));
        $bin .= $ESC . "3" . chr($dots);
    } else {
        $bin .= $ESC . "2"; // default 1/6 inch
    }

    $W = max(30, (int)($settings['char_width'] ?? 42));

    foreach ($lines as $line) {
        // __FEED__: Send 1-dot all-white bit-image graphic + CRLF.
        // Because this is a graphics raster object, the EPSON driver/firmware Paper-Saving filter cannot suppress it.
        if ($line === '__FEED__') {
            $bin .= $ESC . "*" . "\x00" . "\x01\x00" . "\x00" . "\r\n";
            continue;
        }
        // Strip any UTF-8 non-breaking spaces \u00A0 and convert to regular space
        $cleanLine = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $line);
        $bin .= $cleanLine . "\r\n";
    }

    // Cut Feed (Feed lines past print head to tear bar)
    $cutMargin = max(1, min(10, (int)($settings['cut_margin_bottom'] ?? 4)));
    $bin .= $ESC . "d" . chr($cutMargin);

    // Partial Cut (GS V 1)
    $bin .= $GS . "V" . "\x01";

    return $bin;
}

/**
 * Sends raw bytes directly to a Windows printer spooler via raw_printer_helper.ps1
 */
function sendRawEscPosJob($rawBytes, $printerName = null) {
    if (empty($printerName)) {
        $printerName = getPreferredThermalPrinter();
    }

    // Direct Network Thermal Printer via TCP Socket (e.g. 10.33.55.100 or 10.33.55.100:9100)
    $cleanTarget = trim($printerName);
    if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?::(\d+))?$/', $cleanTarget, $matches)) {
        $ip = $matches[1];
        $port = !empty($matches[2]) ? (int)$matches[2] : 9100;
        
        $fp = @fsockopen($ip, $port, $errno, $errstr, 4);
        if (!$fp) {
            return [
                'success' => false,
                'printer' => $cleanTarget,
                'error'   => "Cannot connect to network thermal printer at $ip:$port ($errstr)"
            ];
        }
        stream_set_timeout($fp, 6);
        fwrite($fp, $rawBytes);
        fflush($fp);
        fclose($fp);
        return [
            'success' => true,
            'printer' => "Network Printer ($ip:$port)",
            'bytes'   => strlen($rawBytes)
        ];
    }

    $psScript = __DIR__ . DIRECTORY_SEPARATOR . 'raw_printer_helper.ps1';
    if (!file_exists($psScript)) {
        return ['success' => false, 'error' => 'Helper script raw_printer_helper.ps1 not found.'];
    }

    $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'raw_receipt_' . uniqid() . '.bin';
    if (file_put_contents($tempFile, $rawBytes) === false) {
        return ['success' => false, 'error' => 'Failed to create temporary print payload.'];
    }

    $escapedPrinter = escapeshellarg($printerName);
    $escapedFile    = escapeshellarg($tempFile);
    $escapedScript  = escapeshellarg($psScript);

    $cmd = "powershell -ExecutionPolicy Bypass -File $escapedScript -PrinterName $escapedPrinter -FilePath $escapedFile 2>&1";

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    @unlink($tempFile);

    $outputStr = trim(implode("\n", $output));

    if ($exitCode === 0 && stripos($outputStr, 'SUCCESS') !== false) {
        return [
            'success' => true,
            'printer' => $printerName,
            'bytes'   => strlen($rawBytes)
        ];
    } else {
        return [
            'success' => false,
            'printer' => $printerName,
            'error'   => !empty($outputStr) ? $outputStr : 'Unknown spooler error (Exit code ' . $exitCode . ')'
        ];
    }
}
