<?php
// db.php - SQLite connection + auto table creation
// The .db file will be created automatically on first run, next to this file.

$dbFile = __DIR__ . '/journal.db';

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_number TEXT NOT NULL,
            register_number TEXT NOT NULL,
            transaction_number TEXT NOT NULL,
            entry_date TEXT NOT NULL,
            zread_number TEXT NOT NULL,
            till_number TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Create store_master table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS store_master (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_code TEXT NOT NULL DEFAULT '',
            store_name TEXT NOT NULL DEFAULT '',
            serial_number TEXT NOT NULL DEFAULT '',
            min_number TEXT NOT NULL DEFAULT '',
            permit_number TEXT NOT NULL DEFAULT '',
            header TEXT NOT NULL DEFAULT '',
            footer TEXT NOT NULL DEFAULT '',
            accreditation TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Add columns dynamically to store_master table if they do not exist
    $smColsToAdd = [
        'accreditation' => "TEXT NOT NULL DEFAULT ''"
    ];
    $smStmt = $pdo->query("PRAGMA table_info(store_master)");
    $smExistingCols = [];
    while ($col = $smStmt->fetch(PDO::FETCH_ASSOC)) {
        $smExistingCols[] = $col['name'];
    }
    foreach ($smColsToAdd as $colName => $colDef) {
        if (!in_array($colName, $smExistingCols)) {
            $pdo->exec("ALTER TABLE store_master ADD COLUMN $colName $colDef");
        }
    }

    // Create tender_master table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tender_master (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tender_code TEXT NOT NULL DEFAULT '',
            tender_name TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Create register_master table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS register_master (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_code TEXT NOT NULL DEFAULT '',
            reg_no TEXT NOT NULL DEFAULT '',
            serial_number TEXT NOT NULL DEFAULT '',
            permit_number TEXT NOT NULL DEFAULT '',
            min_number TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_regmaster_store_reg ON register_master (store_code, reg_no)");

    // Create cashier_master table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cashier_master (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            store_code TEXT    NOT NULL DEFAULT '',
            emp_no     TEXT    NOT NULL DEFAULT '',
            name       TEXT    NOT NULL DEFAULT '',
            job        TEXT    NOT NULL DEFAULT '',
            status     TEXT    NOT NULL DEFAULT '',
            updated_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cashier_store_emp ON cashier_master (store_code, emp_no)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cashier_store_name ON cashier_master (store_code, name)");

    // Create users table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL DEFAULT '',
            role TEXT NOT NULL DEFAULT 'user',
            is_active INTEGER NOT NULL DEFAULT 1,
            nav_permissions TEXT NOT NULL DEFAULT '*',
            created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Migration: Add missing columns to users table if not present
    try {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($cols, 'name');
        if (!in_array('nav_permissions', $colNames)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN nav_permissions TEXT NOT NULL DEFAULT '*'");
        }
        if (!in_array('department', $colNames)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN department TEXT NOT NULL DEFAULT ''");
        }
    } catch (Exception $e) {
        // Silently continue if pragma/alter fails
    }

    // Seed default admin user if users table is empty
    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount === 0) {
        $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
        $seedUserStmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, role, is_active, nav_permissions)
            VALUES ('admin', :hash, 'System Administrator', 'admin', 1, '*')
        ");
        $seedUserStmt->execute([':hash' => $adminHash]);
    }

    // Seed default store master if empty
    $storeCount = (int)$pdo->query("SELECT COUNT(*) FROM store_master")->fetchColumn();
    if ($storeCount === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO store_master (store_code, store_name, serial_number, min_number, permit_number, header, footer, accreditation)
            VALUES (:store_code, :store_name, :serial_number, :min_number, :permit_number, :header, :footer, :accreditation)
        ");
        $defaultHeader = "CHOICEMART by NCCC\nLTS RETAIL SPECIALISTS, INC.\nTIN 006-171-689-014 VAT\nUnit 2 Sapphire Bldg Damosa Complex\nJ. P. Laurel Ave., Davao City\nTel#(082) 227-1083\nAcc#11300598992500049139053";
        $stmt->execute([
            ':store_code'     => '12027',
            ':store_name'     => 'CHOICEMART by NCCC',
            ':serial_number'  => '12NPEOCGXD3',
            ':min_number'     => '23032210300170375',
            ':permit_number'  => 'FP032023127037666900014',
            ':header'         => $defaultHeader,
            ':footer'         => '',
            ':accreditation'  => $defaultHeader
        ]);
    }

    // Add columns dynamically to entries table if they do not exist
    $columnsToAdd = [
        'last_trx_number' => "TEXT NOT NULL DEFAULT ''",
        'tender' => "TEXT NOT NULL DEFAULT ''",
        'total_vat' => "REAL NOT NULL DEFAULT 0.0",
        'total_non_vat' => "REAL NOT NULL DEFAULT 0.0",
        'daily_sales' => "REAL NOT NULL DEFAULT 0.0",
        'old_grand_total' => "REAL NOT NULL DEFAULT 0.0",
        'new_grand_total' => "REAL NOT NULL DEFAULT 0.0",
        'entry_time' => "TEXT NOT NULL DEFAULT ''"
    ];

    $stmt = $pdo->query("PRAGMA table_info(entries)");
    $existingColumns = [];
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $col['name'];
    }

    foreach ($columnsToAdd as $colName => $colDef) {
        if (!in_array($colName, $existingColumns)) {
            $pdo->exec("ALTER TABLE entries ADD COLUMN $colName $colDef");
        }
    }

    // Performance Indexes for large datasets
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entries_date ON entries(entry_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entries_store_reg ON entries(store_number, register_number)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entries_zread ON entries(zread_number)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entries_last_trx ON entries(last_trx_number)");

    // Activity Logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL DEFAULT 0,
            username   TEXT    NOT NULL DEFAULT '',
            action     TEXT    NOT NULL DEFAULT '',
            module     TEXT    NOT NULL DEFAULT '',
            detail     TEXT    NOT NULL DEFAULT '',
            ip_address TEXT    NOT NULL DEFAULT '',
            created_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_actlog_created  ON activity_logs(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_actlog_username ON activity_logs(username)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_actlog_module   ON activity_logs(module)");

} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

/**
 * Log a user activity to the activity_logs table.
 * Silently fails on any error so it never disrupts existing workflows.
 *
 * @param string $action  Short action identifier (e.g. 'login', 'create', 'delete', 'print')
 * @param string $module  Module/area (e.g. 'journal', 'ej', 'auth', 'users')
 * @param string $detail  Optional human-readable detail string
 */
function logActivity(string $action, string $module, string $detail = ''): void {
    global $pdo;
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId   = (int)($_SESSION['user']['id']       ?? 0);
        $username = (string)($_SESSION['user']['username'] ?? 'guest');

        // Best-effort IP detection (handles reverse proxy)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, username, action, module, detail, ip_address)
            VALUES (:uid, :uname, :action, :module, :detail, :ip)
        ");
        $stmt->execute([
            ':uid'    => $userId,
            ':uname'  => $username,
            ':action' => $action,
            ':module' => $module,
            ':detail' => $detail,
            ':ip'     => $ip,
        ]);
    } catch (Throwable $e) {
        // Silently swallow — logging must never break the main workflow
    }
}

// Layout helper functions
function getPrintLayout() {
    $configFile = __DIR__ . '/print_layout.json';
    
    // Default is now a 2D array (array of rows, where each row is an array of fields)
    $defaultLayout = [
        [ ['key' => '_custom_1', 'label' => 'Terminal Z Report', 'flex' => 1, 'align' => 'center', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [ ['key' => '_custom_2', 'label' => '===================================================', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [ ['key' => 'store_number', 'label' => 'Store No.', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [ ['key' => 'register_number', 'label' => 'Register No.', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [
            ['key' => 'entry_date', 'label' => 'Date', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13],
            ['key' => 'entry_time', 'label' => 'Time', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13]
        ],
        [ ['key' => 'zread_number', 'label' => 'Z-Read No.', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [
            ['key' => 'last_trx_number', 'label' => 'Last Trx#', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13]
        ],
        [ ['key' => '_custom_3', 'label' => '---------------------------------------------------', 'flex' => 1, 'align' => 'center', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [ ['key' => 'tender', 'label' => 'Tender', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [
            ['key' => 'total_vat', 'label' => 'Total VAT', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13],
            ['key' => 'total_non_vat', 'label' => 'Total Non-VAT', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13]
        ],
        [ ['key' => 'daily_sales', 'label' => 'Daily Sales', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [
            ['key' => 'old_grand_total', 'label' => 'Old Grand Total', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13],
            ['key' => 'new_grand_total', 'label' => 'New Grand Total', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13]
        ],
        [ ['key' => '_custom_4', 'label' => '===================================================', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ],
        [ ['key' => 'created_at', 'label' => 'Saved On', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13] ]
    ];

    if (file_exists($configFile)) {
        $json = file_get_contents($configFile);
        $data = json_decode($json, true);
        if (is_array($data) && !empty($data)) {
            // Check if it is the old 1D associative array
            $is1D = false;
            foreach ($data as $k => $v) {
                if (is_string($k) && !is_array($v)) {
                    $is1D = true; break;
                }
            }
            if ($is1D) {
                $migrated = [];
                foreach ($data as $k => $v) {
                    $migrated[] = [ ['key' => $k, 'label' => $v] ];
                }
                return $migrated;
            }
            return $data;
        }
    }
    return $defaultLayout;
}

function savePrintLayout($layoutArray) {
    $configFile = __DIR__ . '/print_layout.json';
    file_put_contents($configFile, json_encode($layoutArray, JSON_PRETTY_PRINT));
}

function getPrintSettings() {
    $settingsFile = __DIR__ . '/print_settings.json';
    $defaultSettings = [
        'line_spacing'      => 4,
        'field_spacing'     => 9,
        'receipt_width'     => 340,
        'char_width'        => 32,
        'font_size'         => 11,
        'header_top_margin' => 2,
        'cut_margin_bottom' => 0,
        'line_height'       => 1.3,
        'receipt_padding_y' => 10
    ];
    if (file_exists($settingsFile)) {
        $json = file_get_contents($settingsFile);
        $data = json_decode($json, true);
        if (is_array($data)) {
            return array_merge($defaultSettings, $data);
        }
    }
    return $defaultSettings;
}

function savePrintSettings($settings) {
    $settingsFile = __DIR__ . '/print_settings.json';
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
}

// Store Master helper functions (SQLite-backed)
function getAllStoreMasters($search = '') {
    global $pdo;
    if ($search !== '') {
        $stmt = $pdo->prepare("
            SELECT * FROM store_master
            WHERE store_code LIKE :q
               OR store_name LIKE :q
               OR serial_number LIKE :q
               OR min_number LIKE :q
               OR permit_number LIKE :q
               OR accreditation LIKE :q
            ORDER BY id DESC
        ");
        $stmt->execute([':q' => '%' . $search . '%']);
    } else {
        $stmt = $pdo->query("SELECT * FROM store_master ORDER BY id DESC");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStoreMasterById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM store_master WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStoreMaster() {
    global $pdo;
    $defaults = [
        'store_code' => '',
        'store_name' => '',
        'serial_number' => '',
        'min_number' => '',
        'permit_number' => '',
        'header' => '',
        'footer' => '',
        'accreditation' => ''
    ];

    // Try to read from database first
    $stmt = $pdo->query("SELECT * FROM store_master ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return array_merge($defaults, $row);
    }

    // Auto-migrate from old JSON file if it exists
    $jsonFile = __DIR__ . '/store_master.json';
    if (file_exists($jsonFile)) {
        $json = file_get_contents($jsonFile);
        $data = json_decode($json, true);
        if (is_array($data) && !empty(array_filter($data))) {
            saveStoreMaster(array_merge($defaults, $data));
            // Remove old JSON file after successful migration
            @rename($jsonFile, $jsonFile . '.bak');
            return array_merge($defaults, $data);
        }
    }

    return $defaults;
}

function saveStoreMaster($data) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO store_master (store_code, store_name, serial_number, min_number, permit_number, header, footer, accreditation, updated_at)
        VALUES (:store_code, :store_name, :serial_number, :min_number, :permit_number, :header, :footer, :accreditation, datetime('now', 'localtime'))
    ");
    $stmt->execute([
        ':store_code'     => $data['store_code'] ?? '',
        ':store_name'     => $data['store_name'] ?? '',
        ':serial_number'  => $data['serial_number'] ?? '',
        ':min_number'     => $data['min_number'] ?? '',
        ':permit_number'  => $data['permit_number'] ?? '',
        ':header'         => $data['header'] ?? '',
        ':footer'         => $data['footer'] ?? '',
        ':accreditation'  => $data['accreditation'] ?? ''
    ]);
    return $pdo->lastInsertId();
}

// Tender Master helper functions
function getTenderMastersCount($search = '') {
    global $pdo;
    if ($search !== '') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tender_master
            WHERE tender_code LIKE :q
               OR tender_name LIKE :q
        ");
        $stmt->execute([':q' => '%' . $search . '%']);
        return (int)$stmt->fetchColumn();
    } else {
        return (int)$pdo->query("SELECT COUNT(*) FROM tender_master")->fetchColumn();
    }
}

function getAllTenderMasters($search = '', $limit = null, $offset = null) {
    global $pdo;
    $whereSql = '';
    $params = [];
    if ($search !== '') {
        $whereSql = "WHERE tender_code LIKE :q OR tender_name LIKE :q";
        $params[':q'] = '%' . $search . '%';
    }
    $sql = "SELECT * FROM tender_master $whereSql ORDER BY id DESC";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int)$limit;
        if ($offset !== null) {
            $sql .= " OFFSET " . (int)$offset;
        }
    }
    if (!empty($params)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Returns an array mapping raw TPS bucket names to canonical tender_master names.
 * Also includes a lookup by tender_name (case-insensitive) for dynamic remapping.
 *
 * TPS bucket -> canonical tender_master name:
 *   "Card"   -> "BPI"
 *   "Others" -> "RNB CARD"
 */
function getTenderNameMap() {
    global $pdo;
    $stmt = $pdo->query("SELECT tender_code, tender_name FROM tender_master ORDER BY id");
    $codeMap = [];
    $masterNames = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $code = strtoupper(trim($row['tender_code'] ?? ''));
        $name = trim($row['tender_name'] ?? '');
        if ($code !== '') {
            $codeMap[$code] = $name;
            $codeMap[strtolower($code)] = $name;
        }
        if ($name !== '') {
            $masterNames[strtolower($name)] = $name;
        }
    }

    // Static TPS bucket & AS400 code -> canonical name mapping
    $bucketMap = array_merge($codeMap, [
        'card'                => $masterNames['bpi']                  ?? ($codeMap['A2'] ?? 'BPI'),
        'cards'               => $masterNames['bpi']                  ?? ($codeMap['A2'] ?? 'BPI'),
        'others'              => $masterNames['rnb card']             ?? ($codeMap['RNB'] ?? 'RNB CARD'),
        'other'               => $masterNames['rnb card']             ?? ($codeMap['RNB'] ?? 'RNB CARD'),
        'gift cert'           => $masterNames['gift cert redeem']     ?? ($codeMap['GC'] ?? 'Gift Cert Redeem'),
        'gift certificate'    => $masterNames['gift cert redeem']     ?? ($codeMap['GC'] ?? 'Gift Cert Redeem'),
        'gift cert redeem'    => $masterNames['gift cert redeem']     ?? ($codeMap['GC'] ?? 'Gift Cert Redeem'),
        'gc'                  => $masterNames['gift cert redeem']     ?? ($codeMap['GC'] ?? 'Gift Cert Redeem'),
        'ar'                  => 'AR',
        'a/r'                 => 'AR',
        'accounts receivable' => 'AR',
        'account receivable'  => 'AR',
        'house account charge'=> 'AR',
        'house charge tender' => 'AR',
        'cash'                => $masterNames['cash']                 ?? ($codeMap['CA'] ?? 'Cash'),
        'check'               => $masterNames['check']                ?? ($codeMap['CH'] ?? 'Check'),
        'cheque'              => $masterNames['check']                ?? ($codeMap['CH'] ?? 'Check'),
    ]);

    return $bucketMap;
}

function getTenderMasterById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM tender_master WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveTenderMaster($data) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO tender_master (tender_code, tender_name, updated_at)
        VALUES (:tender_code, :tender_name, datetime('now', 'localtime'))
    ");
    $stmt->execute([
        ':tender_code' => $data['tender_code'] ?? '',
        ':tender_name' => $data['tender_name'] ?? ''
    ]);
    return $pdo->lastInsertId();
}

function getUniqueTendersFromEntries() {
    global $pdo;
    $stmt = $pdo->query("SELECT tender FROM entries WHERE tender != ''");
    $tenders = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $decoded = json_decode($row['tender'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $t) {
                if (isset($t['name']) && trim($t['name']) !== '') {
                    $tenders[] = trim($t['name']);
                }
            }
        }
    }
    $unique = array_unique($tenders);
    sort($unique);
    return $unique;
}

/**
 * Classify a tender name into its category: 'cash', 'credit', 'debit', or 'other'.
 */
function classifyTenderCategory($name) {
    $n = strtoupper(trim((string)$name));
    if (strpos($n, 'MERCHANDISE CREDIT') !== false) {
        return 'other';
    }
    if ($n === 'CASH' || $n === 'CA' || strpos($n, 'CASH') === 0) {
        return 'cash';
    }
    if (strpos($n, 'DEBIT') !== false || strpos($n, 'ATM') !== false) {
        return 'debit';
    }
    if (strpos($n, 'CREDIT') !== false || strpos($n, 'CITIPAYLITE') !== false) {
        return 'credit';
    }
    return 'other';
}

/**
 * Return a debit card counterpart name for a given credit card name.
 */
function getDebitCardCounterpart($name) {
    $n = strtoupper(trim((string)$name));
    if (strpos($n, 'METROBANK') !== false || strpos($n, 'MBTC') !== false) {
        return 'METROBANK DEBIT CARD';
    }
    if (strpos($n, 'BPI') !== false) {
        return 'BPI Express ATM Card';
    }
    if (strpos($n, 'BDO') !== false) {
        return 'BDO DEBIT';
    }
    if (strpos($n, 'LBP') !== false) {
        return 'LBP DEBIT';
    }
    if (strpos($n, 'ONB') !== false) {
        return 'ONB DEBIT CARD';
    }
    return 'BDO DEBIT';
}

/**
 * Normalizes and sorts tenders so that:
 * 1. There is only 1 tender code per type (at most 1 Credit Card, at most 1 Debit Card, at most 1 Cash).
 * 2. Multiple types are preserved/allowed (Cash, Credit Card, Debit Card, Other tenders).
 * 3. Sorting order is strictly:
 *    - 1st: Cash
 *    - 2nd: Credit Cards
 *    - 3rd: Debit Cards
 *    - 4th: Other tenders
 */
function normalizeAndSortTenders($tenders) {
    if (is_string($tenders)) {
        $decoded = json_decode($tenders, true);
        if (!is_array($decoded)) {
            $raw = trim($tenders);
            if ($raw === '') return [];
            return [['name' => $raw, 'amount' => 0.0]];
        }
        $tenders = $decoded;
    }
    if (!is_array($tenders)) return [];

    $cashItems = [];
    $creditItems = [];
    $debitItems = [];
    $otherItems = [];

    foreach ($tenders as $item) {
        if (!is_array($item) || empty($item['name'])) continue;
        $name = trim($item['name']);
        if ($name === '') continue;
        $amount = (float)str_replace(',', '', (string)($item['amount'] ?? 0));

        // Canonicalize AR / House Account Charge variants to 'House Charge'
        $upperN = strtoupper($name);
        if ($upperN === 'AR' || $upperN === 'A/R' || strpos($upperN, 'HOUSE ACCOUNT') !== false || strpos($upperN, 'HOUSE CHARGE') !== false || strpos($upperN, 'ACCOUNTS RECEIVABLE') !== false || strpos($upperN, 'ACCOUNT RECEIVABLE') !== false) {
            $name = 'House Charge';
        }

        $cat = classifyTenderCategory($name);

        if ($cat === 'cash') {
            $cashItems[] = ['name' => $name, 'amount' => $amount];
        } elseif ($cat === 'credit') {
            $creditItems[] = ['name' => $name, 'amount' => $amount];
        } elseif ($cat === 'debit') {
            $debitItems[] = ['name' => $name, 'amount' => $amount];
        } else {
            $otherItems[] = ['name' => $name, 'amount' => $amount];
        }
    }

    // 1. Cash: combine into single tender
    $finalCash = [];
    if (!empty($cashItems)) {
        $totCash = 0.0;
        foreach ($cashItems as $c) $totCash += $c['amount'];
        $finalCash[] = ['name' => $cashItems[0]['name'], 'amount' => round($totCash, 2)];
    }

    // 2. Credit & Debit: strictly at most 1 tender code per type
    $finalCredit = [];
    $finalDebit = [];

    if (!empty($debitItems)) {
        $totDeb = 0.0;
        foreach ($debitItems as $d) $totDeb += $d['amount'];
        $finalDebit[] = ['name' => $debitItems[0]['name'], 'amount' => round($totDeb, 2)];
    }

    if (!empty($creditItems)) {
        $firstC = $creditItems[0];
        $finalCredit[] = ['name' => $firstC['name'], 'amount' => round($firstC['amount'], 2)];

        if (count($creditItems) > 1) {
            for ($i = 1; $i < count($creditItems); $i++) {
                $extraC = $creditItems[$i];
                if (empty($finalDebit)) {
                    // Convert second credit card to debit card counterpart
                    $debName = getDebitCardCounterpart($extraC['name']);
                    $finalDebit[] = ['name' => $debName, 'amount' => round($extraC['amount'], 2)];
                } else {
                    // Debit already exists; merge into primary credit card
                    $finalCredit[0]['amount'] = round($finalCredit[0]['amount'] + $extraC['amount'], 2);
                }
            }
        }
    }

    // 3. Other tenders: merge identical names
    $finalOther = [];
    $otherMap = [];
    foreach ($otherItems as $oth) {
        $key = strtolower(trim($oth['name']));
        if (isset($otherMap[$key])) {
            $idx = $otherMap[$key];
            $finalOther[$idx]['amount'] = round($finalOther[$idx]['amount'] + $oth['amount'], 2);
        } else {
            $otherMap[$key] = count($finalOther);
            $finalOther[] = ['name' => trim($oth['name']), 'amount' => round($oth['amount'], 2)];
        }
    }

    // Result: Cash first -> Credit Cards -> Debit Cards -> Other tenders
    return array_merge($finalCash, $finalCredit, $finalDebit, $finalOther);
}

// Register Master helper functions
function getRegisterMastersCount($search = '', $storeCode = '') {
    global $pdo;
    $whereClauses = [];
    $params = [];
    if ($search !== '') {
        $whereClauses[] = "(store_code LIKE :q OR reg_no LIKE :q OR serial_number LIKE :q OR permit_number LIKE :q OR min_number LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }
    if ($storeCode !== '') {
        $whereClauses[] = "store_code = :store";
        $params[':store'] = $storeCode;
    }
    $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    if (!empty($params)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM register_master $whereSql");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } else {
        return (int)$pdo->query("SELECT COUNT(*) FROM register_master")->fetchColumn();
    }
}

function getAllRegisterMasters($search = '', $limit = null, $offset = null, $storeCode = '') {
    global $pdo;
    $whereClauses = [];
    $params = [];
    if ($search !== '') {
        $whereClauses[] = "(store_code LIKE :q OR reg_no LIKE :q OR serial_number LIKE :q OR permit_number LIKE :q OR min_number LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }
    if ($storeCode !== '') {
        $whereClauses[] = "store_code = :store";
        $params[':store'] = $storeCode;
    }
    $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    $sql = "SELECT * FROM register_master $whereSql ORDER BY id DESC";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int)$limit;
        if ($offset !== null) {
            $sql .= " OFFSET " . (int)$offset;
        }
    }
    if (!empty($params)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRegisterMasterById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM register_master WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveRegisterMaster($data) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO register_master (store_code, reg_no, serial_number, permit_number, min_number, updated_at)
        VALUES (:store_code, :reg_no, :serial_number, :permit_number, :min_number, datetime('now', 'localtime'))
    ");
    $stmt->execute([
        ':store_code'     => $data['store_code'] ?? '',
        ':reg_no'         => $data['reg_no'] ?? '',
        ':serial_number'  => $data['serial_number'] ?? '',
        ':permit_number'  => $data['permit_number'] ?? '',
        ':min_number'     => $data['min_number'] ?? ''
    ]);
    return $pdo->lastInsertId();
}

// -------------------------------------------------------------
// USER MANAGEMENT FUNCTIONS
// -------------------------------------------------------------

function getAllUsers($search = '') {
    global $pdo;
    if ($search !== '') {
        $stmt = $pdo->prepare("
            SELECT id, username, full_name, department, role, is_active, nav_permissions, created_at 
            FROM users 
            WHERE username LIKE :q OR full_name LIKE :q OR department LIKE :q
            ORDER BY id ASC
        ");
        $stmt->execute([':q' => '%' . $search . '%']);
    } else {
        $stmt = $pdo->query("
            SELECT id, username, full_name, department, role, is_active, nav_permissions, created_at 
            FROM users 
            ORDER BY id ASC
        ");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByUsername($username) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(:username)");
    $stmt->execute([':username' => trim($username)]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createUser($username, $password, $fullName = '', $role = 'user', $isActive = 1, $navPermissions = '*', $department = '') {
    global $pdo;
    $username       = trim($username);
    $fullName       = trim($fullName);
    $department     = trim($department);
    $role           = in_array($role, ['admin', 'user']) ? $role : 'user';
    $isActive       = (int)$isActive;
    $navPermissions = is_array($navPermissions) ? json_encode(array_values($navPermissions)) : trim($navPermissions ?: '*');
    $hash           = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, full_name, department, role, is_active, nav_permissions)
        VALUES (:username, :hash, :full_name, :department, :role, :is_active, :nav_permissions)
    ");
    $stmt->execute([
        ':username'        => $username,
        ':hash'            => $hash,
        ':full_name'       => $fullName,
        ':department'      => $department,
        ':role'            => $role,
        ':is_active'       => $isActive,
        ':nav_permissions' => $navPermissions
    ]);
    return $pdo->lastInsertId();
}

function updateUser($id, $fullName, $role, $isActive, $password = null, $navPermissions = null, $department = null) {
    global $pdo;
    $id       = (int)$id;
    $fullName = trim($fullName);
    $role     = in_array($role, ['admin', 'user']) ? $role : 'user';
    $isActive = (int)$isActive;

    $params = [
        ':full_name' => $fullName,
        ':role'      => $role,
        ':is_active' => $isActive,
        ':id'        => $id
    ];

    $updates = [
        'full_name = :full_name',
        'role = :role',
        'is_active = :is_active'
    ];

    if (!empty($password)) {
        $updates[] = 'password_hash = :hash';
        $params[':hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($navPermissions !== null) {
        $updates[] = 'nav_permissions = :nav_permissions';
        $params[':nav_permissions'] = is_array($navPermissions) ? json_encode(array_values($navPermissions)) : trim($navPermissions ?: '*');
    }

    if ($department !== null) {
        $updates[] = 'department = :department';
        $params[':department'] = trim($department);
    }

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function deleteUser($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    return $stmt->execute([':id' => (int)$id]);
}

function setUserActiveStatus($id, $isActive) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_active = :is_active WHERE id = :id");
    return $stmt->execute([
        ':is_active' => (int)$isActive,
        ':id'        => (int)$id
    ]);
}

// -------------------------------------------------------------
// CASHIER MASTER FUNCTIONS
// -------------------------------------------------------------

function getCashierMastersCount($search = '', $storeCode = '') {
    global $pdo;
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "(emp_no LIKE :q OR name LIKE :q OR job LIKE :q OR status LIKE :q OR store_code LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }
    if ($storeCode !== '') {
        $where[] = "store_code = :store";
        $params[':store'] = $storeCode;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    if ($params) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cashier_master $whereSql");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM cashier_master")->fetchColumn();
}

function getAllCashierMasters($search = '', $limit = null, $offset = null, $storeCode = '') {
    global $pdo;
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "(emp_no LIKE :q OR name LIKE :q OR job LIKE :q OR status LIKE :q OR store_code LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }
    if ($storeCode !== '') {
        $where[] = "store_code = :store";
        $params[':store'] = $storeCode;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM cashier_master $whereSql ORDER BY store_code ASC, name ASC";
    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int)$limit;
        if ($offset !== null) $sql .= ' OFFSET ' . (int)$offset;
    }
    if ($params) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCashierMasterById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM cashier_master WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveCashierMaster($data) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO cashier_master (store_code, emp_no, name, job, status, updated_at)
        VALUES (:store_code, :emp_no, :name, :job, :status, datetime('now', 'localtime'))
    ");
    $stmt->execute([
        ':store_code' => $data['store_code'] ?? '',
        ':emp_no'     => $data['emp_no']     ?? '',
        ':name'       => $data['name']       ?? '',
        ':job'        => $data['job']        ?? '',
        ':status'     => $data['status']     ?? '',
    ]);
    return $pdo->lastInsertId();
}

function updateCashierMaster($id, $data) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE cashier_master
        SET store_code = :store_code,
            emp_no     = :emp_no,
            name       = :name,
            job        = :job,
            status     = :status,
            updated_at = datetime('now', 'localtime')
        WHERE id = :id
    ");
    return $stmt->execute([
        ':store_code' => $data['store_code'] ?? '',
        ':emp_no'     => $data['emp_no']     ?? '',
        ':name'       => $data['name']       ?? '',
        ':job'        => $data['job']        ?? '',
        ':status'     => $data['status']     ?? '',
        ':id'         => (int)$id,
    ]);
}

function deleteCashierMaster($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM cashier_master WHERE id = :id");
    return $stmt->execute([':id' => (int)$id]);
}
