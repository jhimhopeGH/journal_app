<?php
// print.php - Print-friendly view of a single journal entry
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM entries WHERE id = :id");
$stmt->execute([':id' => $id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="style.css"></head><body>
          <div class="container"><h1>Entry not found</h1>
          <a class="btn" href="records.php">Back to Records</a></div></body></html>';
    exit;
}

// ---------------------------------------------------------------------
// PRINT LAYOUT 
// The layout is now managed via the Admin page and stored dynamically.
// ---------------------------------------------------------------------
$printLayout = getPrintLayout();
$settings = getPrintSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reprint - Entry #<?php echo (int)$entry['id']; ?></title>
<link rel="stylesheet" href="style.css">
<style>
  .print-sheet {
      max-width: <?php echo (int)$settings['receipt_width']; ?>px !important;
  }
  .print-row {
      padding: <?php echo (int)$settings['line_spacing']; ?>px 0 !important;
      gap: <?php echo (int)$settings['field_spacing']; ?>px !important;
  }
</style>
</head>
<body>

<div class="print-sheet">
    <h2>JOURNAL RECEIPT</h2>
    <p style="text-align:center; margin-top:0;">Entry #<?php echo (int)$entry['id']; ?> (Reprint)</p>

    <?php foreach ($printLayout as $row): ?>
        <?php 
        $lineSpacing = (int)($settings['line_spacing'] ?? 4);
        ?>
        <?php if (count($row) === 1 && strpos($row[0]['key'], '_custom_') === 0): ?>
            <!-- Single custom text line: render centered, full-width -->
            <?php 
            $item = $row[0];
            $align = $item['align'] ?? 'center';
            $flex = (int)($item['flex'] ?? 1);
            $fontSize = (int)($item['font_size'] ?? 13);
            $marginLeft = (int)($item['margin_left'] ?? 0);
            $marginRight = (int)($item['margin_right'] ?? 0);
            ?>
            <div class="print-row" style="justify-content: center; border-bottom: none; text-align: <?php echo htmlspecialchars($align); ?>; padding: <?php echo $lineSpacing; ?>px 0;">
                <span style="font-size: <?php echo $fontSize; ?>px; flex: <?php echo $flex; ?>; margin-left: <?php echo $marginLeft; ?>px; margin-right: <?php echo $marginRight; ?>px;">
                    <?php echo htmlspecialchars($item['label']); ?>
                </span>
            </div>
        <?php elseif (count($row) === 1): ?>
            <!-- Single field: standard label:value row -->
            <?php 
            $item = $row[0];
            $fontSize = (int)($item['font_size'] ?? 13);
            $marginLeft = (int)($item['margin_left'] ?? 0);
            $marginRight = (int)($item['margin_right'] ?? 0);
            ?>
            <div class="print-row" style="padding: <?php echo $lineSpacing; ?>px 0;">
                <span style="font-size: <?php echo $fontSize; ?>px; margin-left: <?php echo $marginLeft; ?>px;">
                    <?php echo htmlspecialchars($item['label']); ?>
                </span>
                <span style="font-size: <?php echo $fontSize; ?>px; margin-right: <?php echo $marginRight; ?>px;">
                    <?php echo htmlspecialchars($entry[$item['key']] ?? ''); ?>
                </span>
            </div>
        <?php else: ?>
            <!-- Multiple fields side-by-side -->
            <?php 
            $fieldSpacing = (int)($settings['field_spacing'] ?? 10);
            ?>
            <div class="print-row" style="display: flex; gap: <?php echo $fieldSpacing; ?>px; justify-content: space-between; padding: <?php echo $lineSpacing; ?>px 0;">
                <?php foreach ($row as $field): ?>
                    <?php 
                    $flex = (int)($field['flex'] ?? 1);
                    $align = $field['align'] ?? 'left';
                    $fontSize = (int)($field['font_size'] ?? 13);
                    $marginLeft = (int)($field['margin_left'] ?? 0);
                    $marginRight = (int)($field['margin_right'] ?? 0);
                    ?>
                    <?php if (strpos($field['key'], '_custom_') === 0): ?>
                        <span style="flex: <?php echo $flex; ?>; text-align: <?php echo htmlspecialchars($align); ?>; font-size: <?php echo $fontSize; ?>px; margin-left: <?php echo $marginLeft; ?>px; margin-right: <?php echo $marginRight; ?>px;">
                            <?php echo htmlspecialchars($field['label']); ?>
                        </span>
                    <?php else: ?>
                        <span style="flex: <?php echo $flex; ?>; text-align: <?php echo htmlspecialchars($align); ?>; font-size: <?php echo $fontSize; ?>px; margin-left: <?php echo $marginLeft; ?>px; margin-right: <?php echo $marginRight; ?>px;">
                            <strong><?php echo htmlspecialchars($field['label']); ?>:</strong> <?php echo htmlspecialchars($entry[$field['key']] ?? ''); ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="no-print">
    <button onclick="window.print()">Print</button>
    <a class="btn btn-secondary" href="records.php">Back to Records</a>
</div>

<script>
    // Auto-open the print dialog when the page loads
    window.onload = function () {
        window.print();
    };
</script>

</body>
</html>
