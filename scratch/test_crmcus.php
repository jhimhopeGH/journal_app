<?php
$python = 'C:\\python32\\python.exe';
$script = <<<'PY'
import pyodbc, json
conn = pyodbc.connect("DSN=mms_as400;UID=maindss;PWD=windss", autocommit=True)
cur = conn.cursor()

# Check CRMCUS table structure
cur.execute("SELECT * FROM MMLTSLIB.CRMCUS FETCH FIRST 1 ROWS ONLY")
row = cur.fetchone()
if row:
    cols = [d[0] for d in cur.description]
    print("Columns:", cols)
    print("Sample:", dict(zip(cols, row)))
else:
    print("No rows found in CRMCUS")

# Check with customer 9009055 from our test transaction
cur.execute("SELECT * FROM MMLTSLIB.CRMCUS WHERE CCCUST = ? FETCH FIRST 1 ROWS ONLY", (9009055,))
row = cur.fetchone()
if row:
    cols = [d[0] for d in cur.description]
    print("\nCustomer 9009055:", dict(zip(cols, row)))
else:
    print("\nCustomer 9009055 not found, trying different column names...")
    cur.execute("SELECT column_name FROM qsys2.syscolumns WHERE table_name = 'CRMCUS' AND table_schema = 'MMLTSLIB' FETCH FIRST 20 ROWS ONLY")
    for r in cur.fetchall(): print(r)

conn.close()
PY;

$pyFile = __DIR__ . '/test_crmcus.py';
file_put_contents($pyFile, $script);
$cmd = '"' . $python . '" "' . $pyFile . '" 2>&1';
echo "<pre>" . htmlspecialchars(shell_exec($cmd)) . "</pre>";
unlink($pyFile);
