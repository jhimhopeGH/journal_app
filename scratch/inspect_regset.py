import pyodbc

# Let's inspect REGSET and REGMST table schema and sample data in Windss / Windss_New
for dsn in ["Windss", "Windss_New", "Salessum"]:
    print(f"\n====================== DSN: {dsn} ======================")
    try:
        conn = pyodbc.connect(f"DSN={dsn}", autocommit=True)
        cur = conn.cursor()
        for tname in ["REGSET", "REGMST"]:
            try:
                cur.execute(f"SELECT * FROM {tname}")
                cols = [d[0] for d in cur.description]
                print(f"\nTable {tname} Columns: {cols}")
                rows = cur.fetchmany(5)
                print(f"Sample Rows ({len(rows)}):")
                for r in rows:
                    print(" ", dict(zip(cols, r)))
            except Exception as e:
                print(f"Table {tname} error: {e}")
        conn.close()
    except Exception as e:
        print(f"DSN error: {e}")
