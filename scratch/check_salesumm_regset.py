import pyodbc

# Try REGSET in Salessum DSN (production)
for dsn in ["Salessum"]:
    print(f"== DSN: {dsn} ==")
    try:
        conn = pyodbc.connect(f"DSN={dsn}", autocommit=True)
        cur = conn.cursor()
        # List all tables first
        tables = [r[2] for r in cur.tables().fetchall() if r[2]]
        print(f"Tables in {dsn}: {tables}")
        # Try REGSET
        try:
            cur.execute("SELECT * FROM REGSET")
            cols = [d[0] for d in cur.description]
            rows = cur.fetchall()
            print(f"\nREGSET Columns: {cols}")
            print(f"REGSET Row count: {len(rows)}")
            for r in rows[:10]:
                print(" ", dict(zip(cols, r)))
        except Exception as e:
            print(f"REGSET error: {e}")
        conn.close()
    except Exception as e:
        print(f"Connection error: {e}")
