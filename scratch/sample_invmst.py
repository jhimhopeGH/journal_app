import os
import pyodbc
import json

out_file = os.path.join(os.path.dirname(__file__), "invmst_sample.txt")

with open(out_file, "w", encoding="utf-8") as f:
    try:
        conn = pyodbc.connect("DSN=mms_as400;UID=maindss;PWD=windss", autocommit=True)
        cur = conn.cursor()
        
        cur.execute("SELECT * FROM MMLTSLIB.INVMST FETCH FIRST 5 ROWS ONLY")
        cols = [d[0] for d in cur.description]
        f.write(f"Columns in INVMST: {cols}\n\n")
        
        rows = cur.fetchall()
        for i, r in enumerate(rows, 1):
            r_dict = dict(zip(cols, r))
            f.write(f"--- Sample Row {i} ---\n")
            for k in cols[:30]:
                f.write(f"  {k}: {r_dict.get(k)}\n")
        conn.close()
    except Exception as e:
        f.write(f"Error: {e}\n")