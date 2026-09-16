<?php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user'] = ['id' => 1, 'username' => 'admin', 'role' => 'admin', 'nav_permissions' => '*'];

ob_start();
include __DIR__ . '/../receipt_reprint.php';
$html = ob_get_clean();

echo "Contains 'AS400 Settings': " . (strpos($html, 'AS400 Settings') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'btn_open_as400_config': " . (strpos($html, 'btn_open_as400_config') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'as400_config_modal': " . (strpos($html, 'as400_config_modal') !== false ? 'YES' : 'NO') . "\n";
echo "Length of output: " . strlen($html) . "\n";
