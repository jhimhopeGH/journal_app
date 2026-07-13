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
<<<<<<< Updated upstream
=======

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
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Create tender_master table if it doesn't exist yet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tender_master (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tender_code TEXT NOT NULL DEFAULT '',
            tender_name TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Seed default tenders if empty
    $tenderCount = (int)$pdo->query("SELECT COUNT(*) FROM tender_master")->fetchColumn();
    if ($tenderCount === 0) {
        $defaultTenders = [
            "Gift Cert Sale",
            "House Account Charge",
            "Cash",
            "CHECK/BANK DEPOSIT",
            "Gift Cert Redeem",
            "MasterCard",
            "Visa",
            "GC CLEARING",
            "Deposits/Payments",
            "Mdse Credit Redeem",
            "Mdse Credit Issue",
            "Hse Acct Pymnt",
            "JCB Card",
            "Corporate Check",
            "Vendor Coupon",
            "Debit Card",
            "Upton",
            "Gift Card Redeem",
            "Merchandise EGC",
            "BPI",
            "BPI Express ATM Card",
            "OPD Medicine",
            "Eastwest ZIP",
            "REWARDS",
            "BDO",
            "SB Credit",
            "Coupon",
            "SUPPLIERS COUPON",
            "SB ZIP",
            "BDO ZIP",
            "BPI ZIP",
            "Eastwest Credit",
            "METROBANK CREDITCARD",
            "MBTC ZIP",
            "BDO DEBIT",
            "RNB CARD",
            "ONB DEBIT CARD",
            "GCASH",
            "COD-GCASH",
            "COUPON-PEDIATRICA",
            "REWARDS TO GRAB",
            "Coupon-LivEver",
            "RBSC APP",
            "NC Credit-MBTC",
            "Paymaya",
            "AEON Credit",
            "ShopeePay",
            "M2Cash",
            "CITI-CitiPaylite",
            "BDO QRPay",
            "MBTC QRPay",
            "MBTC QRPH",
            "SALMON",
            "Skyro",
            "COD MBTC Debit",
            "LBP-Credit",
            "LBP-Debit"
        ];
        $stmt = $pdo->prepare("INSERT INTO tender_master (tender_code, tender_name) VALUES (:code, :name)");
        foreach ($defaultTenders as $idx => $name) {
            $code = 'TND-' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            $stmt->execute([':code' => $code, ':name' => $name]);
        }
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
>>>>>>> Stashed changes
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
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
            ['key' => 'transaction_number', 'label' => 'Trx No.', 'flex' => 1, 'align' => 'left', 'margin_left' => 0, 'margin_right' => 0, 'font_size' => 13],
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
        'line_spacing' => 4,
        'field_spacing' => 10,
        'receipt_width' => 400
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
<<<<<<< Updated upstream
=======

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
        'footer' => ''
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
        INSERT INTO store_master (store_code, store_name, serial_number, min_number, permit_number, header, footer, updated_at)
        VALUES (:store_code, :store_name, :serial_number, :min_number, :permit_number, :header, :footer, datetime('now', 'localtime'))
    ");
    $stmt->execute([
        ':store_code'     => $data['store_code'] ?? '',
        ':store_name'     => $data['store_name'] ?? '',
        ':serial_number'  => $data['serial_number'] ?? '',
        ':min_number'     => $data['min_number'] ?? '',
        ':permit_number'  => $data['permit_number'] ?? '',
        ':header'         => $data['header'] ?? '',
        ':footer'         => $data['footer'] ?? ''
    ]);
    return $pdo->lastInsertId();
}

// Tender Master helper functions
function getAllTenderMasters($search = '') {
    global $pdo;
    if ($search !== '') {
        $stmt = $pdo->prepare("
            SELECT * FROM tender_master
            WHERE tender_code LIKE :q
               OR tender_name LIKE :q
            ORDER BY id DESC
        ");
        $stmt->execute([':q' => '%' . $search . '%']);
    } else {
        $stmt = $pdo->query("SELECT * FROM tender_master ORDER BY id DESC");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

>>>>>>> Stashed changes
