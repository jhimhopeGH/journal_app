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
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Layout helper functions
function getPrintLayout() {
    $configFile = __DIR__ . '/print_layout.json';
    
    // Default is now a 2D array (array of rows, where each row is an array of fields)
    $defaultLayout = [
        [ ['key' => 'register_number', 'label' => 'Register No.'] ],
        [ ['key' => 'till_number', 'label' => 'Till No. (Cashier)'] ],
        [ ['key' => 'store_number', 'label' => 'Store No.'] ],
        [ ['key' => 'transaction_number', 'label' => 'Transaction No.'] ],
        [ ['key' => 'entry_date', 'label' => 'Date'] ],
        [ ['key' => 'zread_number', 'label' => 'Z-Read No.'] ],
        [ ['key' => 'created_at', 'label' => 'Saved On'] ]
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
