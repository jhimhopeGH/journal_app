<?php
// zread_direct_print_ajax.php - Direct 1-Click ESC/POS Printing for Z-Read Receipts (journal.db)
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/auth.php";
requireAjaxAuth();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/raw_print_service.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$printer = !empty($input["printer"]) ? trim($input["printer"]) : getPreferredThermalPrinter();
$settings = getPrintSettings();
$printLayout = getPrintLayout();
$tenderNameMap = getTenderNameMap();

if (!function_exists("padCenterZ")) {
    function padCenterZ($str, $width = 32) {
        $len = strlen($str);
        if ($len >= $width) return substr($str, 0, $width);
        $leftPad = (int)(($width - $len) / 2);
        $rightPad = $width - $len - $leftPad;
        return str_repeat(" ", $leftPad) . $str . str_repeat(" ", $rightPad);
    }
}

if (!function_exists("padTwoColZ")) {
    function padTwoColZ($left, $right, $width = 32, $ml = 0, $mr = 0) {
        $spacesL = $ml > 0 ? (int)round($ml / 6) : 0;
        $spacesR = $mr > 0 ? (int)round($mr / 6) : 0;

        $leftStr = str_repeat(" ", $spacesL) . $left;
        $rightStr = $right . str_repeat(" ", $spacesR);

        $lenL = strlen($leftStr);
        $lenR = strlen($rightStr);
        if ($lenL + $lenR >= $width) {
            $space = 1;
        } else {
            $space = $width - $lenL - $lenR;
        }
        return $leftStr . str_repeat(" ", max(1, $space)) . $rightStr;
    }
}

if (!function_exists("padFourColZ")) {
    function padFourColZ($p1, $p2, $p3, $p4, $width = 32) {
        $line = $p1 . " " . $p2 . " " . $p3 . " " . $p4;
        $len = strlen($line);
        if ($len >= $width) return $line;
        $extra = $width - $len;
        $g1 = 1 + (int)($extra / 3);
        $g2 = 1 + (int)($extra / 3);
        $g3 = $width - strlen($p1) - $g1 - strlen($p2) - $g2 - strlen($p3) - strlen($p4);
        return $p1 . str_repeat(" ", $g1) . $p2 . str_repeat(" ", $g2) . $p3 . str_repeat(" ", max(1, $g3)) . $p4;
    }
}

if (!function_exists("getFieldTextZ")) {
    function getFieldTextZ($field, $entry, $isTopHeader = false) {
        if (strpos($field["key"], "_custom_") === 0) {
            return trim($field["label"] ?? "");
        }
        if (preg_match("/^tender_(\d+)$/", $field["key"], $m)) {
            $tSlot = (int)$m[1] - 1;
            $decoded = json_decode($entry["tender"] ?? "", true);
            if (is_array($decoded) && isset($decoded[$tSlot]) && !empty($decoded[$tSlot]["name"])) {
                $cleanVal = (float)str_replace(',', '', (string)($decoded[$tSlot]["amount"] ?? 0));
                $amtStr = ($cleanVal < 0 ? "P-" . number_format(abs($cleanVal), 2, ".", ",") : "P" . number_format($cleanVal, 2, ".", ","));
                return $decoded[$tSlot]["name"] . "  " . $amtStr;
            }
            return "";
        }
        $val = $entry[$field["key"]] ?? "";
        if ($field["key"] === "entry_date" && !empty($val)) {
            $timeTs = strtotime($val);
            if ($timeTs !== false) $val = date("m/d/y", $timeTs);
        } elseif ($field["key"] === "entry_time" && !empty($val)) {
            $timeTs = strtotime($val);
            if ($timeTs !== false) $val = date("H:i", $timeTs);
        } elseif ($field["key"] === "register_number" && $val !== "") {
            if ($isTopHeader && is_numeric($val) && strlen((string)$val) < 5) {
                $val = str_pad((string)$val, 5, "0", STR_PAD_LEFT);
            }
        } elseif (in_array($field["key"], ["zread_number", "last_trx_number", "transaction_number"]) && $val !== "") {
            if (is_numeric($val)) $val = str_pad((string)$val, 8, "0", STR_PAD_LEFT);
        } elseif (in_array($field["key"], ["total_vat","total_non_vat","daily_sales","old_grand_total","new_grand_total"])) {
            $val = "P" . number_format((float)$val, 2, ".", ",");
        }
        $lbl = trim($field["label"] ?? "");
        if ($lbl !== "") return $lbl . " " . $val;
        return (string)$val;
    }
}

function buildZreadLinesForEntry($entry, $printLayout, $settings, $tenderNameMap, $pdo) {
    $W = (int)($settings["char_width"] ?? 32);

    $storeMaster = null;
    if (!empty($entry["store_number"])) {
        $smStmt = $pdo->prepare("SELECT * FROM store_master WHERE store_code = :code OR store_name = :code ORDER BY id DESC LIMIT 1");
        $smStmt->execute([":code" => $entry["store_number"]]);
        $storeMaster = $smStmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$storeMaster) $storeMaster = getStoreMaster();

    $registerRecord = null;
    if (!empty($entry["store_number"]) && !empty($entry["register_number"])) {
        $regStmt = $pdo->prepare("SELECT * FROM register_master WHERE store_code = :sc AND reg_no = :rn ORDER BY id DESC LIMIT 1");
        $regStmt->execute([":sc" => $entry["store_number"], ":rn" => $entry["register_number"]]);
        $registerRecord = $regStmt->fetch(PDO::FETCH_ASSOC);
    }

    $lines = [];

    // Header Top Feed
    $topFeedLines = max(0, min(10, (int)($settings["header_top_margin"] ?? 2)));
    for ($i = 0; $i < $topFeedLines; $i++) {
        $lines[] = "__FEED__";
    }

    // Store header lines
    if ($storeMaster) {
        $hText = trim($storeMaster["header"] ?? "");
        if ($hText !== "") {
            foreach (preg_split("/\r\n|\r|\n/", $hText) as $hl) {
                if (trim($hl) !== "") $lines[] = padCenterZ(trim($hl), $W);
            }
        } elseif (!empty($storeMaster["store_name"])) {
            $lines[] = padCenterZ(trim($storeMaster["store_name"]), $W);
        }

        if ($registerRecord) {
            if (!empty($registerRecord["serial_number"])) {
                $lines[] = padCenterZ("SERIAL#" . trim($registerRecord["serial_number"]), $W);
            }
            if (!empty($registerRecord["min_number"])) {
                $lines[] = padCenterZ(trim($registerRecord["min_number"]), $W);
            }
            if (!empty($registerRecord["permit_number"])) {
                $lines[] = padCenterZ(str_replace("-", "", trim($registerRecord["permit_number"])), $W);
            }
        } elseif ($storeMaster) {
            if (!empty($storeMaster["serial_number"]) && (empty($storeMaster["header"]) || stripos($storeMaster["header"], $storeMaster["serial_number"]) === false)) {
                $lines[] = padCenterZ("SERIAL#" . trim($storeMaster["serial_number"]), $W);
            }
            if (!empty($storeMaster["min_number"]) && (empty($storeMaster["header"]) || stripos($storeMaster["header"], $storeMaster["min_number"]) === false)) {
                $lines[] = padCenterZ(trim($storeMaster["min_number"]), $W);
            }
            if (!empty($storeMaster["permit_number"]) && (empty($storeMaster["header"]) || stripos($storeMaster["header"], $storeMaster["permit_number"]) === false)) {
                $lines[] = padCenterZ(str_replace("-", "", trim($storeMaster["permit_number"])), $W);
            }
        }
    }

    $totalRowsCount = count($printLayout);
    // Find the last row that contains a tender field
    $lastTenderRowIndex = -1;
    foreach ($printLayout as $rIdx => $r) {
        foreach ($r as $f) {
            if ($f["key"] === "tender" || strpos($f["key"], "tender_") === 0) {
                $lastTenderRowIndex = $rIdx;
            }
        }
    }
    $printedTenderIndices = [];
    $decodedTenders = normalizeAndSortTenders($entry["tender"] ?? "");
    if (!is_array($decodedTenders)) $decodedTenders = [];

    foreach ($printLayout as $rowIndex => $row) {
        $isTopHeader = $rowIndex < ceil($totalRowsCount / 2);

        // Row-level spacing: blank feed lines above/below from first field
        $firstField   = $row[0] ?? [];
        $spacingTop    = max(0, (int)($firstField['spacing_top']    ?? 0));
        $spacingBottom = max(0, (int)($firstField['spacing_bottom'] ?? 0));
        for ($s = 0; $s < $spacingTop; $s++) { $lines[] = '__FEED__'; }

        if (count($row) === 1 && strpos($row[0]["key"], "_custom_") === 0) {
            $item = $row[0];
            $lbl = trim($item["label"] ?? "");
            $align = $item["align"] ?? "center";

            if ($lbl === "" || ctype_space($lbl)) {
                $lines[] = "__FEED__";
            } elseif (preg_match("/^(=+|-+|\*+)$/", $lbl, $dm)) {
                $char = substr($lbl, 0, 1);
                $lines[] = str_repeat($char, $W);
            } else {
                if ($align === "center") {
                    $lines[] = padCenterZ($lbl, $W);
                } elseif ($align === "right") {
                    $lines[] = str_pad($lbl, $W, " ", STR_PAD_LEFT);
                } else {
                    $lines[] = str_pad($lbl, $W, " ", STR_PAD_RIGHT);
                }
            }
        } elseif (count($row) === 1) {
            $item = $row[0];
            $ml = (int)($item["margin_left"] ?? 0);
            $mr = (int)($item["margin_right"] ?? 0);

            if (preg_match("/^tender_(\d+)$/", $item["key"], $m)) {
                $tSlot = (int)$m[1] - 1;
                if (isset($decodedTenders[$tSlot]) && !empty($decodedTenders[$tSlot]["name"])) {
                    $name = trim($decodedTenders[$tSlot]["name"]);
                    $name = $tenderNameMap[strtolower($name)] ?? $name;
                    $cleanVal = (float)str_replace(',', '', (string)($decodedTenders[$tSlot]["amount"] ?? 0));
                    $amt = ($cleanVal < 0 ? "P-" . number_format(abs($cleanVal), 2, ".", ",") : "P" . number_format($cleanVal, 2, ".", ","));
                    $lines[] = padTwoColZ($name, $amt, $W, $ml, $mr);
                    $printedTenderIndices[] = $tSlot;
                } elseif (!empty($item["label"]) && !preg_match("/^Tender \d+$/i", $item["label"])) {
                    foreach ($decodedTenders as $idx => $t) {
                        if (!in_array($idx, $printedTenderIndices) && !empty($t["name"]) && strcasecmp(trim($t["name"]), trim($item["label"])) === 0) {
                            $name = trim($t["name"]);
                            $name = $tenderNameMap[strtolower($name)] ?? $name;
                            $cleanVal = (float)str_replace(',', '', (string)($t["amount"] ?? 0));
                            $amt = ($cleanVal < 0 ? "P-" . number_format(abs($cleanVal), 2, ".", ",") : "P" . number_format($cleanVal, 2, ".", ","));
                            $lines[] = padTwoColZ($name, $amt, $W, $ml, $mr);
                            $printedTenderIndices[] = $idx;
                            break;
                        }
                    }
                }
            } elseif ($item["key"] === "tender") {
                foreach ($decodedTenders as $idx => $t) {
                    if (!empty($t["name"])) {
                        $tName = trim($t["name"]);
                        $tName = $tenderNameMap[strtolower($tName)] ?? $tName;
                        $cleanVal = (float)str_replace(',', '', (string)($t["amount"] ?? 0));
                        $amt = ($cleanVal < 0 ? "P-" . number_format(abs($cleanVal), 2, ".", ",") : "P" . number_format($cleanVal, 2, ".", ","));
                        $lines[] = padTwoColZ($tName, $amt, $W, $ml, $mr);
                        $printedTenderIndices[] = $idx;
                    }
                }
            } else {
                $lbl = trim($item["label"] ?? "");
                $val = $entry[$item["key"]] ?? "";
                if ($item["key"] === "register_number" && $isTopHeader && is_numeric($val) && strlen((string)$val) < 5) {
                    $val = str_pad((string)$val, 5, "0", STR_PAD_LEFT);
                } elseif (in_array($item["key"], ["total_vat","total_non_vat","daily_sales","old_grand_total","new_grand_total"])) {
                    $val = "P" . number_format((float)$val, 2, ".", ",");
                }
                $align = $item["align"] ?? "left";
                $ml = (int)($item["margin_left"] ?? 0);
                $mr = (int)($item["margin_right"] ?? 0);

                if ($lbl !== "" && $val !== "") {
                    $lines[] = padTwoColZ($lbl, (string)$val, $W, $ml, $mr);
                } else {
                    $text = trim($lbl . " " . $val);
                    if ($align === "center") {
                        $lines[] = padCenterZ($text, $W);
                    } elseif ($align === "right") {
                        $lines[] = str_pad($text, $W - max(0, (int)round($mr/6)), " ", STR_PAD_LEFT);
                    } else {
                        $lines[] = str_repeat(" ", max(0, (int)round($ml/6))) . str_pad($text, $W, " ", STR_PAD_RIGHT);
                    }
                }
            }
        } else {
            // Multi-column row
            if (count($row) === 2) {
                $leftStr = getFieldTextZ($row[0], $entry, $isTopHeader);
                $rightStr = getFieldTextZ($row[1], $entry, $isTopHeader);
                $ml = (int)($row[0]["margin_left"] ?? 0);
                $mr = (int)($row[1]["margin_right"] ?? $row[0]["margin_right"] ?? 0);
                $lines[] = padTwoColZ($leftStr, $rightStr, $W, $ml, $mr);
            } elseif (count($row) === 4) {
                $c1 = getFieldTextZ($row[0], $entry, $isTopHeader);
                $c2 = getFieldTextZ($row[1], $entry, $isTopHeader);
                $c3 = getFieldTextZ($row[2], $entry, $isTopHeader);
                $c4 = getFieldTextZ($row[3], $entry, $isTopHeader);
                $lines[] = padFourColZ($c1, $c2, $c3, $c4, $W);
            } else {
                $parts = [];
                foreach ($row as $f) {
                    $parts[] = getFieldTextZ($f, $entry, $isTopHeader);
                }
                $lines[] = implode("  ", array_filter($parts));
            }
        }

        // If this is the last tender row in the layout, ensure any remaining tenders in the entry (e.g. 4th, 5th tender) are printed
        if ($rowIndex === $lastTenderRowIndex) {
            $lastField = $row[0] ?? [];
            $tMl = (int)($lastField['margin_left'] ?? 0);
            $tMr = (int)($lastField['margin_right'] ?? 0);
            foreach ($decodedTenders as $idx => $t) {
                if (!in_array($idx, $printedTenderIndices) && !empty($t['name'])) {
                    $tName = trim($t['name']);
                    $tName = $tenderNameMap[strtolower($tName)] ?? $tName;
                    $cleanVal = (float)str_replace(',', '', (string)($t['amount'] ?? 0));
                    $amt = ($cleanVal < 0 ? "P-" . number_format(abs($cleanVal), 2, ".", ",") : "P" . number_format($cleanVal, 2, ".", ","));
                    $lines[] = padTwoColZ($tName, $amt, $W, $tMl, $tMr);
                    $printedTenderIndices[] = $idx;
                }
            }
        }

        // Row-level spacing: blank feed lines below this row
        for ($s = 0; $s < $spacingBottom; $s++) { $lines[] = '__FEED__'; }
    }

    // Ensure any unprinted tenders are printed even if no tender rows were present in layout
    if (count($printedTenderIndices) < count($decodedTenders)) {
        foreach ($decodedTenders as $idx => $t) {
            if (!in_array($idx, $printedTenderIndices) && !empty($t['name'])) {
                $tName = trim($t['name']);
                $tName = $tenderNameMap[strtolower($tName)] ?? $tName;
                $cleanVal = (float)str_replace(',', '', (string)($t['amount'] ?? 0));
                $amt = ($cleanVal < 0 ? "P-" . number_format(abs($cleanVal), 2, ".", ",") : "P" . number_format($cleanVal, 2, ".", ","));
                $lines[] = padTwoColZ($tName, $amt, $W, 0, 0);
                $printedTenderIndices[] = $idx;
            }
        }
    }

    return $lines;
}

try {
    $allPayload = "";
    $count = 0;

    if (!empty($input["sample"])) {
        $stmt = $pdo->query("SELECT * FROM entries ORDER BY id DESC LIMIT 1");
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            $entry = [
                "store_number" => "10001",
                "register_number" => "00001",
                "entry_date" => date("Y-m-d"),
                "entry_time" => date("H:i:s"),
                "zread_number" => "00000001",
                "last_trx_number" => "00000001",
                "till_number" => "0001",
                "daily_sales" => 12500.00,
                "total_vat" => 1339.29,
                "total_non_vat" => 0.00,
                "old_grand_total" => 50000.00,
                "new_grand_total" => 62500.00,
                "tender" => json_encode([["name"=>"CASH","amount"=>10000.00],["name"=>"GC","amount"=>2500.00]])
            ];
        }
        $lines = buildZreadLinesForEntry($entry, $printLayout, $settings, $tenderNameMap, $pdo);
        $allPayload = buildEscPosReceiptFromLines($lines, $settings);
        $count = 1;

    } elseif (!empty($input["id"])) {
        $stmt = $pdo->prepare("SELECT * FROM entries WHERE id = :id");
        $stmt->execute([":id" => (int)$input["id"]]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            echo json_encode(["success" => false, "error" => "Entry not found"]);
            exit;
        }

        $lines = buildZreadLinesForEntry($entry, $printLayout, $settings, $tenderNameMap, $pdo);
        $allPayload = buildEscPosReceiptFromLines($lines, $settings);
        $count = 1;

    } elseif (!empty($input["ids"]) && is_array($input["ids"])) {
        $ids = array_map("intval", $input["ids"]);
        if (empty($ids)) {
            echo json_encode(["success" => false, "error" => "No valid IDs provided"]);
            exit;
        }

        $inClause = implode(",", array_fill(0, count($ids), "?"));
        $stmt = $pdo->prepare("SELECT * FROM entries WHERE id IN ($inClause) ORDER BY id ASC");
        $stmt->execute($ids);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($entries as $entry) {
            $lines = buildZreadLinesForEntry($entry, $printLayout, $settings, $tenderNameMap, $pdo);
            $allPayload .= buildEscPosReceiptFromLines($lines, $settings);
            $count++;
        }
    } else {
        echo json_encode(["success" => false, "error" => "No transaction ID or sample mode specified"]);
        exit;
    }

    $action = $input['action'] ?? 'print_server';

    // If client requested the raw payload for the local cashier agent
    if ($action === 'get_raw_payload') {
        echo json_encode([
            'success'        => true,
            'payload_base64' => base64_encode($allPayload),
            'count'          => $count,
            'bytes'          => strlen($allPayload)
        ]);
        exit;
    }

    // Default: send to server-attached printer
    $res = sendRawEscPosJob($allPayload, $printer);

    if ($res["success"]) {
        logActivity('print', 'journal', "Printed $count Z-Read receipt(s) to [{$res["printer"]}]");
        echo json_encode([
            "success" => true,
            "message" => "Successfully printed $count Z-Read receipt(s) directly to [{$res["printer"]}]!",
            "printer" => $res["printer"],
            "count"   => $count
        ]);
    } else {
        $printErr = $res["error"] ?? "Print job failed";
        logActivity('print', 'journal', "FAILED Z-Read print to [$printer]: $printErr");
        echo json_encode([
            "success" => false,
            "error"   => $printErr,
            "printer" => $printer
        ]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

