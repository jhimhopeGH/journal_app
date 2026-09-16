<?php
$python = 'C:\\python32\\python.exe';
$script = <<<'PY'
import pyodbc

conn = pyodbc.connect("DSN=mms_as400;UID=maindss;PWD=windss", autocommit=True)
cur = conn.cursor()
cur.execute("SELECT INUMBR, ICHECK, IDESCR, INLRTL FROM MMLTSLIB.INVMST WHERE INUMBR IN (10000001, 10000002, 10000010, 10000022, 10000023)")
for r in cur.fetchall():
    print(f"INUMBR={r[0]}, ICHECK={r[1]}, IDESCR={r[2].strip()}, INLRTL={r[3]}")
conn.close()
PY;

$pyFile = __DIR__ . '/test_prices.py';
file_put_contents($pyFile, $script);
$cmd = '"' . $python . '" "' . $pyFile . '" 2>&1';
$output = shell_exec($cmd);
echo "<pre>" . htmlspecialchars($output) . "</pre>";
