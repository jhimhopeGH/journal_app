import sys
import os
import pyodbc

out_file = os.path.join(os.path.dirname(__file__), "log.txt")

with open(out_file, "w", encoding="utf-8") as f:
    try:
        sources = pyodbc.dataSources()
        f.write(f"DSNs: {list(sources.keys())}\n")
        
        for dsn in ["Salessum", "Windss", "Windss_New", "DOOR", "Reprtlog", "DPT", "TENDER", "TND"]:
            if dsn in sources:
                f.write(f"\n--- Checking DSN '{dsn}' ---\n")
                try:
                    conn = pyodbc.connect(f"DSN={dsn}", autocommit=True)
                    cur = conn.cursor()
                    tables = [r[2] for r in cur.tables().fetchall() if r[2]]
                    f.write(f"Tables in {dsn}: {tables}\n")
                    for t in tables:
                        try:
                            cur.execute(f"SELECT * FROM {t}")
                            cols = [d[0] for d in cur.description]
                            f.write(f"  Table: {t} -> Columns: {cols}\n")
                            rows = cur.fetchmany(5)
                            for r in rows:
                                f.write(f"    Sample: {[str(x).strip() if x is not None else '' for x in r]}\n")
                        except Exception as ex:
                            f.write(f"    Error reading table {t}: {ex}\n")
                    conn.close()
                except Exception as e:
                    f.write(f"Connect error for {dsn}: {e}\n")
    except Exception as e:
        f.write(f"Fatal error: {e}\n")