<?php
// ej_save.php - Validates and saves an EJ entry into ej.db
require_once __DIR__ . '/auth.php';
requireNavAccess('ej');
require_once __DIR__ . '/ej_db.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ej_printing.php');
    exit;
}

$id                 = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$entry_date         = trim($_POST['entry_date'] ?? '');
$entry_time         = trim($_POST['entry_time'] ?? date('H:i'));
$store_code         = trim($_POST['store_code'] ?? '');
$register_number    = trim($_POST['register_number'] ?? '');
$transaction_number = trim($_POST['transaction_number'] ?? '');
$invoice_number     = trim($_POST['invoice_number'] ?? '');
$member_number      = trim($_POST['member_number'] ?? '');
$customer_name      = trim($_POST['customer_name'] ?? '');
$customer_address   = trim($_POST['customer_address'] ?? '');
$customer_tin       = trim($_POST['customer_tin'] ?? '');
$sales_associate    = trim($_POST['sales_associate'] ?? '');
if ($sales_associate === '' || ctype_digit($sales_associate)) {
    $sales_associate = getRandomCashierName($store_code);
}
$associate_id       = trim($_POST['associate_id'] ?? '');
$till_number        = trim($_POST['till_number'] ?? '');

// Process Multi-SKU Items
$itemSkus        = $_POST['item_sku'] ?? [];
$itemDescs       = $_POST['item_desc'] ?? [];
$itemQtys        = $_POST['item_qty'] ?? [];
$itemPrices      = $_POST['item_price'] ?? [];
$itemAmounts     = $_POST['item_amount'] ?? [];

$items = [];
$computedSubtotal = 0.0;

if (is_array($itemSkus)) {
    for ($i = 0; $i < count($itemSkus); $i++) {
        $sku   = trim($itemSkus[$i] ?? '');
        $desc  = trim($itemDescs[$i] ?? '');
        $qty   = (float)($itemQtys[$i] ?? 1);
        $price = (float)($itemPrices[$i] ?? 0);
        $amt   = (float)($itemAmounts[$i] ?? ($qty * $price));

        if ($sku !== '' || $desc !== '' || $amt > 0) {
            $items[] = [
                'sku'         => $sku,
                'description' => $desc,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'amount'      => $amt
            ];
            $computedSubtotal += $amt;
        }
    }
}

// Process Tenders
$tenderNames   = $_POST['tender_name'] ?? [];
$tenderAmounts = $_POST['tender_amount'] ?? [];

$tenders = [];
$computedTenderTotal = 0.0;

if (is_array($tenderNames)) {
    for ($j = 0; $j < count($tenderNames); $j++) {
        $tName = trim($tenderNames[$j] ?? '');
        $tAmt  = (float)($tenderAmounts[$j] ?? 0);

        if ($tName !== '' || $tAmt != 0.0) {
            $tenders[] = [
                'name'   => $tName !== '' ? $tName : ($tAmt < 0 ? 'Change' : 'Cash'),
                'amount' => $tAmt
            ];
            $computedTenderTotal += $tAmt;
        }
    }
}

// Totals
$subtotal      = isset($_POST['subtotal']) && $_POST['subtotal'] !== '' ? (float)$_POST['subtotal'] : $computedSubtotal;
$vat_rate      = isset($_POST['vat_rate']) && $_POST['vat_rate'] !== '' ? (float)$_POST['vat_rate'] : 12.0;
if ($vat_rate <= 0) $vat_rate = 12.0;
$vatable_sales = isset($_POST['vatable_sales']) ? (float)$_POST['vatable_sales'] : 0.0;
$total_vat     = isset($_POST['total_vat']) ? (float)$_POST['total_vat'] : 0.0;
$total_non_vat = isset($_POST['total_non_vat']) ? (float)$_POST['total_non_vat'] : 0.0;
$total_amount  = isset($_POST['total_amount']) && $_POST['total_amount'] !== '' ? (float)$_POST['total_amount'] : ($subtotal > 0 ? $subtotal : $computedTenderTotal);

// Auto-calculate VATable Sales & Total VAT if not explicitly provided
if ($vatable_sales <= 0 && $total_vat <= 0 && $total_amount > 0) {
    $vatableBase   = max(0, $total_amount - $total_non_vat);
    $vatable_sales = round($vatableBase / (1 + ($vat_rate / 100)), 2);
    $total_vat     = round($vatableBase - $vatable_sales, 2);
}

$itemsJson   = json_encode($items, JSON_UNESCAPED_UNICODE);
$tendersJson = json_encode($tenders, JSON_UNESCAPED_UNICODE);

if ($id > 0) {
    // Update existing record
    $stmt = $ejPdo->prepare("
        UPDATE ej_entries SET
            entry_date         = :entry_date,
            entry_time         = :entry_time,
            store_code         = :store_code,
            register_number    = :register_number,
            transaction_number = :transaction_number,
            invoice_number     = :invoice_number,
            member_number      = :member_number,
            customer_name      = :customer_name,
            customer_address   = :customer_address,
            customer_tin       = :customer_tin,
            sales_associate    = :sales_associate,
            associate_id       = :associate_id,
            till_number        = :till_number,
            items              = :items,
            tenders            = :tenders,
            subtotal           = :subtotal,
            vatable_sales      = :vatable_sales,
            vat_rate           = :vat_rate,
            total_vat          = :total_vat,
            total_non_vat      = :total_non_vat,
            total_amount       = :total_amount,
            updated_at         = datetime('now', 'localtime')
        WHERE id = :id
    ");

    $stmt->execute([
        ':entry_date'         => $entry_date,
        ':entry_time'         => $entry_time,
        ':store_code'         => $store_code,
        ':register_number'    => $register_number,
        ':transaction_number' => $transaction_number,
        ':invoice_number'     => $invoice_number,
        ':member_number'      => $member_number,
        ':customer_name'      => $customer_name,
        ':customer_address'   => $customer_address,
        ':customer_tin'       => $customer_tin,
        ':sales_associate'    => $sales_associate,
        ':associate_id'       => $associate_id,
        ':till_number'        => $till_number,
        ':items'              => $itemsJson,
        ':tenders'            => $tendersJson,
        ':subtotal'           => $subtotal,
        ':vatable_sales'      => $vatable_sales,
        ':vat_rate'           => $vat_rate,
        ':total_vat'          => $total_vat,
        ':total_non_vat'      => $total_non_vat,
        ':total_amount'       => $total_amount,
        ':id'                 => $id
    ]);
    $savedId = $id;
    logActivity('edit', 'ej', "EJ Entry #$id updated (Store: $store_code, Reg: $register_number, Txn: $transaction_number)");
} else {
    // Insert new record
    $stmt = $ejPdo->prepare("
        INSERT INTO ej_entries (
            entry_date, entry_time, store_code, register_number,
            transaction_number, invoice_number, member_number, customer_name,
            customer_address, customer_tin,
            sales_associate, associate_id, till_number, items, tenders,
            subtotal, vatable_sales, vat_rate, total_vat, total_non_vat, total_amount, created_at, updated_at
        ) VALUES (
            :entry_date, :entry_time, :store_code, :register_number,
            :transaction_number, :invoice_number, :member_number, :customer_name,
            :customer_address, :customer_tin,
            :sales_associate, :associate_id, :till_number, :items, :tenders,
            :subtotal, :vatable_sales, :vat_rate, :total_vat, :total_non_vat, :total_amount, datetime('now', 'localtime'), datetime('now', 'localtime')
        )
    ");

    $stmt->execute([
        ':entry_date'         => $entry_date,
        ':entry_time'         => $entry_time,
        ':store_code'         => $store_code,
        ':register_number'    => $register_number,
        ':transaction_number' => $transaction_number,
        ':invoice_number'     => $invoice_number,
        ':member_number'      => $member_number,
        ':customer_name'      => $customer_name,
        ':customer_address'   => $customer_address,
        ':customer_tin'       => $customer_tin,
        ':sales_associate'    => $sales_associate,
        ':associate_id'       => $associate_id,
        ':till_number'        => $till_number,
        ':items'              => $itemsJson,
        ':tenders'            => $tendersJson,
        ':subtotal'           => $subtotal,
        ':vatable_sales'      => $vatable_sales,
        ':vat_rate'           => $vat_rate,
        ':total_vat'          => $total_vat,
        ':total_non_vat'      => $total_non_vat,
        ':total_amount'       => $total_amount
    ]);
    $savedId = (int)$ejPdo->lastInsertId();
    logActivity('create', 'ej', "EJ Entry #$savedId created (Store: $store_code, Reg: $register_number, Txn: $transaction_number)");
}

header('Location: ej_printing.php?saved=' . $savedId);
exit;
