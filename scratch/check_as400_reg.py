import pyodbc
import json
import os

config_file = r'c:\xampp\htdocs\journal_app\as400_config.json'
with open(config_file, 'r') as f:
    cfg = json.load(f)

dsn = cfg.get('dsn_name', 'mms_as400')
uid = cfg.get('username', 'maindss')
pwd = cfg.get('password', 'windss')
lib = cfg.get('library', 'MMLTSLIB')

conn_str = f"DSN={dsn};UID={uid};PWD={pwd}"
print(f"Connecting to AS400: {conn_str}")

try:
    conn = pyodbc.connect(conn_str, autocommit=True)
    cur = conn.cursor()
    print("Connected successfully!\n")

    table = f"{lib}.cshhdr"
    print(f"Querying sample rows from {table}...")
    cur.execute(f"SELECT * FROM {table} FETCH FIRST 5 ROWS ONLY")
    headers = [d[0].upper() for d in cur.description]
    print(f"Columns in {table}: {headers}\n")
    rows = cur.fetchall()
    for idx, r in enumerate(rows, 1):
        rd = dict(zip(headers, r))
        print(f"--- Row {idx} ---")
        for k in ['CSSTOR', 'CSDATE', 'CSTRAN', 'CSREG', 'CSTIL', 'CSTIME', 'CSCSH', 'CSCUST']:
            print(f"  {k}: {rd.get(k)} (type: {type(rd.get(k)).__name__})")

    conn.close()
except Exception as e:
    print(f"Error: {e}")

import winreg

dsn_name = "mms_as400"
paths = [
    f"SOFTWARE\\ODBC\\ODBC.INI\\{dsn_name}",
    f"SOFTWARE\\WOW6432Node\\ODBC\\ODBC.INI\\{dsn_name}"
]

for p in paths:
    for root in [winreg.HKEY_LOCAL_MACHINE, winreg.HKEY_CURRENT_USER]:
        try:
            with winreg.OpenKey(root, p) as key:
                print(f"=== Found key at {root} -> {p} ===")
                i = 0
                while True:
                    try:
                        name, val, typ = winreg.EnumValue(key, i)
                        print(f"  {name} = {val}")
                        i += 1
                    except OSError:
                        break
        except Exception:
            pass
