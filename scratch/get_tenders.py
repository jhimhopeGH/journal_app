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