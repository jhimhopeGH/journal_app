# Electronic Journal (SQLite + PHP)

A simple local web app to record and reprint transaction journal entries.
No MySQL/phpMyAdmin needed — it uses SQLite, which is a single file database
built into PHP (via the PDO SQLite driver, enabled by default in XAMPP).

## Fields captured
- Store Number
- Register Number
- Transaction Number
- Date
- Z-Read Number
- Till Number of Cashier (must be exactly 4 digits)

## Setup on XAMPP

1. Copy the whole `journal_app` folder into your XAMPP `htdocs` directory, e.g.:
   - Windows: `C:\xampp\htdocs\journal_app`
   - macOS/Linux: `/Applications/XAMPP/htdocs/journal_app` (or wherever htdocs is)

2. Open the XAMPP Control Panel and start **Apache** only.
   (MySQL is not required for this version — SQLite doesn't need a server.)

3. Make sure PHP's `pdo_sqlite` extension is enabled. It is enabled by
   default in standard XAMPP installs, so normally there's nothing to do.
   If you get a "could not find driver" error, open
   `xampp/php/php.ini`, find the line `;extension=pdo_sqlite`, remove the
   leading `;`, save, and restart Apache.

4. Open your browser and go to:
   `http://localhost/journal_app/`

   This redirects to the entry form.

5. Fill in the form and click **Save Entry**. You'll be taken to the
   Records page.

6. On the Records page you can search past entries and click **Reprint**
   on any row to open a print-ready view of that entry (it will also
   automatically open the browser's print dialog).

## Where the data is stored

A file called `journal.db` will be created automatically inside the
`journal_app` folder the first time you save an entry. This file holds
all your journal entries. Back it up (copy the file) if you want to keep
a snapshot, or move it elsewhere if you want a fresh empty journal.

## Notes

- All database queries use prepared statements (PDO), so it's protected
  against SQL injection.
- The Till Number field is restricted to exactly 4 digits both in the
  browser (as you type) and on the server (before saving), so bad data
  can't sneak in.
- Want MySQL/phpMyAdmin instead of SQLite later? Only `db.php` needs to
  change (swap the PDO DSN and connection); the rest of the app doesn't
  need to change since it goes through the same `$pdo` object.
