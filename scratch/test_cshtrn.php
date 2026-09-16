<?php
$python = 'C:\\python32\\python.exe';
$script = <<<'PY'
import sys, os, pyodbc, json

cfg_file = r"c:\xampp\htdocs\journal_app\as400_config.json"
with open(cfg_file) as f:
    cfg = json.load(f)

dsn = cfg.get("dsn_name", "mms_as400")
uid = cfg.get("username", "maindss")
pwd = cfg.get("password", "windss")
lib = cfg.get("library", "MMLTSLIB")

conn_str = f"DSN={dsn};UID={uid};PWD={pwd}"
print("Connecting with:", conn_str)

conn = pyodbc.connect(conn_str, autocommit=True)
print("Connected successfully!")
cur = conn.cursor()
cur.execute(f"SELECT CSTCOD, CSTDSC FROM {lib}.CSHTRN")
rows = cur.fetchall()
print(f"Fetched {len(rows)} rows from {lib}.CSHTRN:")
for r in rows[:10]:
    print(r)
conn.close()
PY;

$pyFile = __DIR__ . '/test_cshtrn.py';
file_put_contents($pyFile, $script);
$out = shell_exec('"' . $python . '" "' . $pyFile . '" 2>&1');
echo "<pre>" . htmlspecialchars($out) . "</pre>";
unlink($pyFile);
