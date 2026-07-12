<?php
// entry.php - Journal entry input form
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>New Journal Entry</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <?php $currentPage = ''; require __DIR__ . '/partials/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>New Journal Entry</h1>
            <p>Fill in the transaction details below and save.</p>
        </div>

        <div class="panel" style="max-width: 480px;">
        <form action="save.php" method="POST" id="journalForm">

            <label for="store_number">Store Number</label>
            <input type="text" id="store_number" name="store_number" required>

            <label for="register_number">Register Number</label>
            <input type="text" id="register_number" name="register_number" required>

            <label for="transaction_number">Transaction Number</label>
            <input type="text" id="transaction_number" name="transaction_number" required>

            <label for="entry_date">Date</label>
            <input type="date" id="entry_date" name="entry_date" value="<?php echo $today; ?>" required>

            <label for="zread_number">Z-Read Number</label>
            <input type="text" id="zread_number" name="zread_number" required>

            <label for="till_number">Till Number (Cashier, 4 digits only)</label>
            <input type="text" id="till_number" name="till_number"
                   maxlength="4" minlength="4" pattern="[0-9]{4}"
                   inputmode="numeric" title="Must be exactly 4 digits"
                   required>
            <p class="hint">Exactly 4 digits, e.g. 0007</p>

            <button type="submit">Save Entry</button>
        </form>
        </div>
    </main>
</div>

<script>
// Extra safety net so only digits can be typed in the till number field
document.getElementById('till_number').addEventListener('input', function (e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
});
</script>
</body>
</html>
