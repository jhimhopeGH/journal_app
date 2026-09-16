<?php
// ej_db.php - Dedicated SQLite connection and helper functions for Electronic Journal (EJ)
require_once __DIR__ . '/db.php'; // Gives access to Master tables (Store, Register, Tender)

$ejDbFile = __DIR__ . '/ej.db';

try {
    $ejPdo = new PDO('sqlite:' . $ejDbFile);
    $ejPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create ej_entries table if it doesn't exist yet
    $ejPdo->exec("
        CREATE TABLE IF NOT EXISTS ej_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_date TEXT NOT NULL DEFAULT '',
            entry_time TEXT NOT NULL DEFAULT '',
            store_code TEXT NOT NULL DEFAULT '',
            register_number TEXT NOT NULL DEFAULT '',
            transaction_number TEXT NOT NULL DEFAULT '',
            invoice_number TEXT NOT NULL DEFAULT '',
            member_number TEXT NOT NULL DEFAULT '',
            customer_name TEXT NOT NULL DEFAULT '',
            sales_associate TEXT NOT NULL DEFAULT '',
            associate_id TEXT NOT NULL DEFAULT '',
            till_number TEXT NOT NULL DEFAULT '',
            items TEXT NOT NULL DEFAULT '[]',
            tenders TEXT NOT NULL DEFAULT '[]',
            subtotal REAL NOT NULL DEFAULT 0.0,
            vatable_sales REAL NOT NULL DEFAULT 0.0,
            vat_rate REAL NOT NULL DEFAULT 12.0,
            total_vat REAL NOT NULL DEFAULT 0.0,
            total_non_vat REAL NOT NULL DEFAULT 0.0,
            total_amount REAL NOT NULL DEFAULT 0.0,
            created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Dynamically add missing columns (for existing databases)
    $cols = $ejPdo->query("PRAGMA table_info(ej_entries)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('vatable_sales', $colNames)) {
        $ejPdo->exec("ALTER TABLE ej_entries ADD COLUMN vatable_sales REAL NOT NULL DEFAULT 0.0");
    }
    if (!in_array('vat_rate', $colNames)) {
        $ejPdo->exec("ALTER TABLE ej_entries ADD COLUMN vat_rate REAL NOT NULL DEFAULT 12.0");
    }
    if (!in_array('customer_address', $colNames)) {
        $ejPdo->exec("ALTER TABLE ej_entries ADD COLUMN customer_address TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('customer_tin', $colNames)) {
        $ejPdo->exec("ALTER TABLE ej_entries ADD COLUMN customer_tin TEXT NOT NULL DEFAULT ''");
    }

    // Indexes for speed
    $ejPdo->exec("CREATE INDEX IF NOT EXISTS idx_ej_date ON ej_entries(entry_date)");
    $ejPdo->exec("CREATE INDEX IF NOT EXISTS idx_ej_store_reg ON ej_entries(store_code, register_number)");
    $ejPdo->exec("CREATE INDEX IF NOT EXISTS idx_ej_invoice ON ej_entries(invoice_number)");
    $ejPdo->exec("CREATE INDEX IF NOT EXISTS idx_ej_trx ON ej_entries(transaction_number)");

    // SKU Description Cache table for instant receipt printing
    $ejPdo->exec("
        CREATE TABLE IF NOT EXISTS sku_cache (
            sku TEXT PRIMARY KEY,
            description TEXT NOT NULL DEFAULT '',
            price REAL NOT NULL DEFAULT 0.0,
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

} catch (PDOException $e) {
    die('EJ Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

/**
 * Loads the EJ print layout configuration from ej_print_layout.json
 */
function getEjPrintLayout() {
    $configFile = __DIR__ . '/ej_print_layout.json';
    if (!file_exists($configFile)) {
        return [
            [['key' => '_custom_1', 'label' => '=========================================', 'flex' => 1, 'align' => 'center', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13]],
            [['key' => '_custom_2', 'label' => 'ELECTRONIC JOURNAL RECEIPT', 'flex' => 1, 'align' => 'center', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13]],
            [['key' => 'store_code', 'label' => 'Store:', 'flex' => 1, 'align' => 'left'], ['key' => 'register_number', 'label' => 'Reg:', 'flex' => 1, 'align' => 'right']],
            [['key' => 'invoice_number', 'label' => 'Invoice #:', 'flex' => 1, 'align' => 'left'], ['key' => 'transaction_number', 'label' => 'Trx #:', 'flex' => 1, 'align' => 'right']],
            [['key' => 'entry_date', 'label' => 'Date:', 'flex' => 1, 'align' => 'left'], ['key' => 'entry_time', 'label' => 'Time:', 'flex' => 1, 'align' => 'right']],
            [['key' => 'member_number', 'label' => 'Member #:', 'flex' => 1, 'align' => 'left'], ['key' => 'customer_name', 'label' => 'Name:', 'flex' => 1, 'align' => 'right']],
            [['key' => 'till_number', 'label' => 'Till #:', 'flex' => 1, 'align' => 'left'], ['key' => 'sales_associate', 'label' => 'Cashier:', 'flex' => 1, 'align' => 'right']],
            [['key' => '_custom_3', 'label' => '-----------------------------------------', 'flex' => 1, 'align' => 'center']],
            [['key' => 'items_list', 'label' => 'Items Breakdown', 'flex' => 1, 'align' => 'left']],
            [['key' => '_custom_4', 'label' => '-----------------------------------------', 'flex' => 1, 'align' => 'center']],
            [['key' => 'subtotal', 'label' => 'Subtotal:', 'flex' => 1, 'align' => 'left', 'margin_left' => 5, 'margin_right' => 7]],
            [['key' => 'total_vat', 'label' => 'Total VAT:', 'flex' => 1, 'align' => 'left', 'margin_left' => 5, 'margin_right' => 7]],
            [['key' => 'total_non_vat', 'label' => 'Total Non-VAT:', 'flex' => 1, 'align' => 'left', 'margin_left' => 5, 'margin_right' => 7]],
            [['key' => 'total_amount', 'label' => 'TOTAL AMOUNT:', 'flex' => 1, 'align' => 'left', 'margin_left' => 5, 'margin_right' => 7, 'font_size' => 13]],
            [['key' => '_custom_5', 'label' => '-----------------------------------------', 'flex' => 1, 'align' => 'center']],
            [['key' => 'tenders_list', 'label' => 'Payment Tender Breakdown', 'flex' => 1, 'align' => 'left']],
            [['key' => '_custom_6', 'label' => '=========================================', 'flex' => 1, 'align' => 'center', 'font_size' => 13]],
            [['key' => 'associate_id', 'label' => 'Associate ID:', 'flex' => 1, 'align' => 'left']],
            [['key' => '_custom_7', 'label' => 'THANK YOU FOR SHOPPING!', 'flex' => 1, 'align' => 'center']]
        ];
    }
    $json = file_get_contents($configFile);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Saves EJ print layout configuration to ej_print_layout.json
 */
function saveEjPrintLayout($layout) {
    $configFile = __DIR__ . '/ej_print_layout.json';
    return file_put_contents($configFile, json_encode($layout, JSON_PRETTY_PRINT));
}

/**
 * Loads EJ print settings from ej_print_settings.json
 */
function getEjPrintSettings() {
    $file = __DIR__ . '/ej_print_settings.json';
    $defaults = [
        'paper_size'               => 'a4',
        'sheet_margin'             => 0.30,
        'top_title_margin_top'     => 25,
        'bottom_title_margin_bottom' => 16,
        'top_title_font_size'      => 16,
        'meta_gap'                 => 65,
        'meta_font_size'           => 14,
        'char_width'               => 54,
        'font_size'                => 15,
        'line_height'              => 1.36,
        'receipt_padding_x'        => 20,
        'receipt_padding_y'        => 6,
        'watermark_text'           => 'Electronic Journal Copy',
        'watermark_font_size'      => 60,
        'watermark_top'            => 75,
        'watermark_left'           => 50,
        'watermark_opacity'        => 0.16,
        'watermark_letter_spacing' => 3,

        // Granular Column & Variable Offsets
        'col_sku_width'            => 16,
        'col_desc_gap'             => 3,
        'col_price_width'          => 16,
        'col_qty_width'            => 6,
        'col_amt_width'            => 20,

        'col_subtotal_label'       => 28,
        'col_subtotal_amt'         => 20,
        'col_tax_label'            => 22,
        'col_tax_amt'              => 26,
        'col_total_label'          => 24,
        'col_total_amt'            => 24,
        'col_tender_label'         => 23,
        'col_tender_amt'           => 25,
        'col_change_label'         => 25,
        'col_change_amt'           => 18,

        'col_bir_vat_amt'          => 20,
        'col_vatable_amt'          => 22,
        'col_vat_amt'              => 22,
        'col_nonvat_amt'           => 18
    ];
    if (!file_exists($file)) {
        return $defaults;
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

/**
 * Saves EJ print settings to ej_print_settings.json
 */
function saveEjPrintSettings($settings) {
    $file = __DIR__ . '/ej_print_settings.json';
    return file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT));
}

/**
 * Loads Thermal Receipt print settings from receipt_print_settings.json
 */
function getReceiptPrintSettings() {
    $file = __DIR__ . '/receipt_print_settings.json';
    $defaults = [
        'char_width'         => 42,
        'font_size'          => 12.5,
        'line_height'        => 1.25,
        'receipt_padding_x'  => 10,
        'receipt_padding_y'      => 4,
        'header_top_space'       => 1,
        'cut_margin_bottom'      => 4,
        'reprint_line_count'     => 2,
        'reprint_top_space'      => 1,
        'reprint_between_space'  => 0,
        'reprint_bottom_space'   => 1,
        'reprint_asterisk_space' => 1,
        'reprint_char_space'     => 1,
        'col_qty_width'          => 4,
        'col_sku_width'      => 10,
        'col_price_width'    => 8,
        'col_amt_width'      => 9,
        'col_subtotal_label' => 26,
        'col_subtotal_amt'   => 14,
        'col_total_label'    => 26,
        'col_total_amt'      => 14,
        'col_tender_label'   => 26,
        'col_tender_amt'     => 14,
        'col_change_label'   => 16,
        'col_change_amt'     => 14,
        'font_family'        => 'generic_text',
        'font_weight'        => 'bold',
        'col_vat_label'      => 17,
        'col_vat_amt'        => 14
    ];
    if (!file_exists($file)) {
        return $defaults;
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

/**
 * Returns the CSS font-family string for a given font family key.
 */
function getReceiptFontCss($familyKey = 'generic_text') {
    switch ($familyKey) {
        case 'consolas':
        case 'thermal':
            return "'FontA11', 'FontA', 'EPSON Thermal', 'Consolas', 'Lucida Console', monospace";
        case 'lucida':
            return "'Lucida Console', Monaco, 'Courier New', monospace";
        case 'courier':
            return "'Courier New', Courier, monospace";
        case 'generic_text':
        default:
            return "'GenericTextOnly', 'VT323', 'Font 10 cpi', 'Font 12 cpi', 'Draft 10cpi', monospace";
    }
}

/**
 * Saves Thermal Receipt print settings to receipt_print_settings.json
 */
function saveReceiptPrintSettings($settings) {
    $file = __DIR__ . '/receipt_print_settings.json';
    return file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT));
}

/**
 * Select a random cashier name from cashier_master in journal.db.
 * If $storeCode is provided, first tries to select from that store.
 * If none found for that store, falls back to any cashier in cashier_master.
 */
function getRandomCashierName($storeCode = '') {
    global $pdo;
    if (!$pdo) return '';

    try {
        $storeCode = trim((string)$storeCode);
        if ($storeCode !== '') {
            $stmt = $pdo->prepare("
                SELECT name FROM cashier_master 
                WHERE store_code = :store AND name != '' AND TRIM(name) != '' 
                ORDER BY RANDOM() LIMIT 1
            ");
            $stmt->execute([':store' => $storeCode]);
            $name = $stmt->fetchColumn();
            if ($name && trim($name) !== '') {
                return trim($name);
            }
        }

        // Fallback to any cashier across all stores
        $stmt = $pdo->query("
            SELECT name FROM cashier_master 
            WHERE name != '' AND TRIM(name) != '' 
            ORDER BY RANDOM() LIMIT 1
        ");
        $name = $stmt->fetchColumn();
        return ($name && trim($name) !== '') ? trim($name) : '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Ensures an EJ entry has a cashier name from cashier_master.
 * If sales_associate is empty (or pure numeric digits), randomly selects one and updates the database.
 */
function ensureEjEntryCashier(&$entry) {
    global $ejPdo;
    if (empty($entry) || !is_array($entry)) return '';

    $salesAssoc = trim($entry['sales_associate'] ?? '');
    // If empty or purely numeric digits (which was an associate ID or till, not a cashier name)
    if ($salesAssoc === '' || ctype_digit($salesAssoc)) {
        $storeCode = trim($entry['store_code'] ?? '');
        $randomCashier = getRandomCashierName($storeCode);
        if ($randomCashier !== '') {
            $entry['sales_associate'] = $randomCashier;
            if (!empty($entry['id']) && $ejPdo) {
                try {
                    $upStmt = $ejPdo->prepare("
                        UPDATE ej_entries 
                        SET sales_associate = :csh, updated_at = datetime('now', 'localtime') 
                        WHERE id = :id
                    ");
                    $upStmt->execute([':csh' => $randomCashier, ':id' => (int)$entry['id']]);
                } catch (Exception $e) {}
            }
        }
    }
    return $entry['sales_associate'] ?? '';
}

/**
 * Fetch EJ entry by ID
 */
function getEjEntryById($id) {
    global $ejPdo;
    $stmt = $ejPdo->prepare("SELECT * FROM ej_entries WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($entry) {
        ensureEjEntryCashier($entry);
    }
    return $entry;
}

/**
 * Build WHERE SQL & params for EJ entries filtering
 */
function buildEjFilterQuery($search = '', $store = '', $reg = '', $from = '', $to = '') {
    $clauses = [];
    $params = [];

    if ($search !== '') {
        $clauses[] = "(store_code LIKE :q OR register_number LIKE :q OR invoice_number LIKE :q OR transaction_number LIKE :q OR member_number LIKE :q OR customer_name LIKE :q OR sales_associate LIKE :q OR associate_id LIKE :q OR items LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }
    if ($store !== '') {
        $clauses[] = "store_code = :store";
        $params[':store'] = $store;
    }
    if ($reg !== '') {
        $clauses[] = "register_number = :reg";
        $params[':reg'] = $reg;
    }
    if ($from !== '' && $to !== '') {
        $clauses[] = "entry_date BETWEEN :from AND :to";
        $params[':from'] = $from;
        $params[':to'] = $to;
    } elseif ($from !== '') {
        $clauses[] = "entry_date >= :from";
        $params[':from'] = $from;
    } elseif ($to !== '') {
        $clauses[] = "entry_date <= :to";
        $params[':to'] = $to;
    }

    $whereSql = !empty($clauses) ? "WHERE " . implode(" AND ", $clauses) : "";
    return ['sql' => $whereSql, 'params' => $params];
}

/**
 * Count total matching EJ entries
 */
function countEjEntries($search = '', $store = '', $reg = '', $from = '', $to = '') {
    global $ejPdo;
    $filter = buildEjFilterQuery($search, $store, $reg, $from, $to);
    $stmt = $ejPdo->prepare("SELECT COUNT(*) FROM ej_entries {$filter['sql']}");
    $stmt->execute($filter['params']);
    return (int)$stmt->fetchColumn();
}

/**
 * Get paginated EJ entries
 */
function getEjEntries($search = '', $store = '', $reg = '', $from = '', $to = '', $limit = 50, $offset = 0) {
    global $ejPdo;
    $filter = buildEjFilterQuery($search, $store, $reg, $from, $to);
    $sql = "SELECT * FROM ej_entries {$filter['sql']} ORDER BY entry_date DESC, id DESC LIMIT :limit OFFSET :offset";
    $stmt = $ejPdo->prepare($sql);
    foreach ($filter['params'] as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        ensureEjEntryCashier($r);
    }
    unset($r);
    return $rows;
}

/**
 * Get list of unique register numbers in ej_entries for filter dropdown
 */
function getUniqueEjRegisters() {
    global $ejPdo;
    $stmt = $ejPdo->query("SELECT DISTINCT register_number FROM ej_entries WHERE register_number != '' ORDER BY register_number ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Delete a single EJ entry by ID
 */
function deleteEjEntry($id) {
    global $ejPdo;
    $stmt = $ejPdo->prepare("DELETE FROM ej_entries WHERE id = :id");
    return $stmt->execute([':id' => (int)$id]);
}

/**
 * Delete all EJ entries
 */
function deleteAllEjEntries() {
    global $ejPdo;
    return $ejPdo->exec("DELETE FROM ej_entries");
}

/**
 * Resolves SKU description, checking local SQLite cache first, then AS400 INVMST.
 */
function resolveSkuDescription($sku) {
    global $ejPdo;
    $sku = trim((string)$sku);
    if ($sku === '') return '';

    // 1. Check local cache in ej.db
    $stripped = ltrim($sku, '0');
    if ($stripped === '') $stripped = '0';
    $numStr = ctype_digit($sku) ? (string)(int)$sku : $sku;

    $stmt = $ejPdo->prepare("SELECT description FROM sku_cache WHERE sku = :sku OR sku = :num OR sku = :stripped LIMIT 1");
    $stmt->execute([
        ':sku' => $sku,
        ':num' => $numStr,
        ':stripped' => $stripped
    ]);
    $cached = $stmt->fetchColumn();
    if ($cached && trim($cached) !== '') {
        return trim($cached);
    }

    // 2. Query AS400 via lookup_sku.py
    $desc = fetchSkuDescriptionFromAs400($sku);
    if ($desc !== '') {
        try {
            $saveStmt = $ejPdo->prepare("
                INSERT INTO sku_cache (sku, description, updated_at) 
                VALUES (:sku, :desc, datetime('now', 'localtime'))
                ON CONFLICT(sku) DO UPDATE SET description = :desc2, updated_at = datetime('now', 'localtime')
            ");
            $saveStmt->execute([':sku' => $sku, ':desc' => $desc, ':desc2' => $desc]);
        } catch (Exception $e) {}
        return $desc;
    }

    return '';
}

function fetchSkuDescriptionFromAs400($sku) {
    $sku = trim((string)$sku);
    if ($sku === '') return '';

    $pythonExe = 'C:\\python32\\python.exe';
    if (!file_exists($pythonExe)) $pythonExe = 'python';
    $scriptPath = __DIR__ . '/scripts/lookup_sku.py';
    if (!file_exists($scriptPath)) return '';

    $configFile = __DIR__ . '/as400_config.json';
    $dsn = 'mms_as400';
    $user = '';
    $pwd = '';
    $lib = 'MMLTSLIB';
    if (file_exists($configFile)) {
        $c = json_decode(file_get_contents($configFile), true);
        if (is_array($c)) {
            $dsn = $c['dsn_name'] ?? $dsn;
            $user = $c['username'] ?? $user;
            $pwd = $c['password'] ?? $pwd;
            $lib = $c['library'] ?? $lib;
        }
    }

    $cmd = escapeshellarg($pythonExe) . ' ' .
           escapeshellarg($scriptPath) . ' ' .
           escapeshellarg($sku) . ' ' .
           escapeshellarg($dsn) . ' ' .
           escapeshellarg($user) . ' ' .
           escapeshellarg($pwd) . ' ' .
           escapeshellarg($lib) . ' 2>&1';

    $output = [];
    exec($cmd, $output);
    $outStr = implode("\n", $output);
    $json = json_decode($outStr, true);
    if (is_array($json) && !empty($json['found']) && !empty($json['description'])) {
        return trim($json['description']);
    }
    return '';
}

/**
 * Ensures all items in an entry have descriptions resolved and updates database if needed.
 */
function ensureEntryItemDescriptions(&$entry) {
    global $ejPdo;
    if (empty($entry) || empty($entry['items'])) return;

    $items = json_decode($entry['items'], true);
    if (!is_array($items)) return;

    $changed = false;
    foreach ($items as &$itm) {
        if (empty(trim($itm['description'] ?? '')) && !empty(trim($itm['sku'] ?? ''))) {
            $desc = resolveSkuDescription(trim($itm['sku']));
            if ($desc !== '') {
                $itm['description'] = $desc;
                $changed = true;
            }
        }
    }
    unset($itm);

    if ($changed) {
        $entry['items'] = json_encode($items);
        if (!empty($entry['id'])) {
            try {
                $stmt = $ejPdo->prepare("UPDATE ej_entries SET items = :items, updated_at = datetime('now', 'localtime') WHERE id = :id");
                $stmt->execute([':items' => $entry['items'], ':id' => (int)$entry['id']]);
            } catch (Exception $e) {}
        }
    }
}

