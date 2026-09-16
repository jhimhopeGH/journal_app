<?php
$python = 'C:\\python32\\python.exe';
$script = <<<'PY'
import pyodbc
import json

conn = pyodbc.connect("DSN=mms_as400;UID=maindss;PWD=windss", autocommit=True)
cur = conn.cursor()

# CSDATE: 8/19/2026 -> 260819 (AS400 YYMMDD)
store = 40005
date = 260819
reg = 214
tran = 7615

print(f"Querying store={store}, date={date}, reg={reg}, tran={tran}")

cur.execute("SELECT * FROM MMLTSLIB.cshhdr WHERE CSSTOR = ? AND CSDATE = ? AND CSREG = ? AND CSTRAN = ?", (store, date, reg, tran))
row = cur.fetchone()
if row:
    desc = [d[0] for d in cur.description]
    print("Found in cshhdr:")
    print(dict(zip(desc, row)))
else:
    print("Not found in cshhdr!")

cur.execute("SELECT * FROM MMLTSLIB.cshdet WHERE CSSTOR = ? AND CSDATE = ? AND CSREG = ? AND CSTRAN = ?", (store, date, reg, tran))
det_rows = cur.fetchall()
if det_rows:
    desc = [d[0] for d in cur.description]
    print(f"Found {len(det_rows)} rows in cshdet:")
    for r in det_rows:
        print(dict(zip(desc, r)))
else:
    print("Not found in cshdet!")

conn.close()
PY;

$pyFile = __DIR__ . '/test_tran.py';
file_put_contents($pyFile, $script);
$cmd = '"' . $python . '" "' . $pyFile . '" 2>&1';
$output = shell_exec($cmd);
echo "<pre>" . htmlspecialchars($output) . "</pre>";
unlink($pyFile);
