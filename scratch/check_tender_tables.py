import sys
import os
import pyodbc

out_file = os.path.join(os.path.dirname(__file__), "tender_check_log.txt")

with open(out_file, "w", encoding="utf-8") as f:
    sources = pyodbc.dataSources()
    f.write(f"DSNs: {list(sources.keys())}\n")
    
    for dsn in ["Windss", "Windss_New", "Salessum"]:
        if dsn in sources:
            f.write(f"\n=================== DSN '{dsn}' ===================\n")
            try:
                conn = pyodbc.connect(f"DSN={dsn}", autocommit=True)
                cur = conn.cursor()
                tables = [r[2] for r in cur.tables().fetchall() if r[2]]
                
                # Check tables related to tenders, payment, comnd, fund
                target_tables = [t for t in tables if any(k in t.upper() for k in ['TEND', 'TND', 'FUND', 'CARD', 'CRED', 'COMND', 'PAY', 'DNM', 'MMS'])]
                f.write(f"Target Tables in {dsn}: {target_tables}\n")
                
                for t in target_tables:
                    try:
                        cur.execute(f"SELECT * FROM {t}")
                        cols = [d[0] for d in cur.description]
                        f.write(f"\n--- Table: {t} (Columns: {cols}) ---\n")
                        rows = cur.fetchall()
                        f.write(f"Total Rows: {len(rows)}\n")
                        for r in rows[:15]:
                            f.write(f"  {[str(x).strip() if x is not None else '' for x in r]}\n")
                    except Exception as ex:
                        f.write(f"  Error querying {t}: {ex}\n")
                conn.close()
            except Exception as e:
                f.write(f"DSN {dsn} error: {e}\n")