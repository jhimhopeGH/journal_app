<?php
$python = 'C:\\python32\\python.exe';
$logFile = __DIR__ . '/tender_rows.txt';
if (file_exists($logFile)) @unlink($logFile);

$script = <<<'PY'
import os
import pyodbc

out_file = os.path.join(os.path.dirname(__file__), "tender_rows.txt")

with open(out_file, "w", encoding="utf-8") as f:
    conn = pyodbc.connect("DSN=Windss", autocommit=True)
    cur = conn.cursor()
    
    cur.execute("SELECT FUNDCODE, DESCRIPTION, STATUS FROM CREDTYPE")
    f.write("=== CREDTYPE in Windss ===\n")
    for r in cur.fetchall():
        f.write(f"Code: '{r[0].strip()}' | Name: '{r[1].strip()}' | Status: '{r[2]}'\n")
    
    conn.close()
PY;

$pyFile = __DIR__ . '/get_tenders.py';
file_put_contents($pyFile, $script);

$cmd = '"' . $python . '" "' . $pyFile . '" 2>&1';
$output = [];
$ret = 0;
exec($cmd, $output, $ret);

$logContent = file_exists($logFile) ? file_get_contents($logFile) : 'LOG NOT FOUND';
echo "<pre>Ret: $ret\n" . htmlspecialchars($logContent) . "</pre>";
