import pyodbc

dsn = "Windss_New"
conn = pyodbc.connect(f"DSN={dsn}", autocommit=True)
cur = conn.cursor()

# Check all REG-related tables and their row counts + columns
for tname in ["REGSET", "REGMST", "NET_REGS", "NET_REGS_old", "REGSERV"]:
    try:
        cur.execute(f"SELECT * FROM {tname}")
        cols = [d[0] for d in cur.description]
        rows = cur.fetchall()
        print(f"\nTable: {tname} | Rows: {len(rows)} | Cols: {cols}")
        for r in rows[:10]:
            print(" ", dict(zip(cols, r)))
    except Exception as e:
        print(f"Table {tname} error: {e}")

conn.close()
