import pyodbc

# Let's inspect available DSNs and check if regset.tps is in any DSN or in Salessum / Windss DSNs
sources = pyodbc.dataSources()
print("Available DSNs:", list(sources.keys()))

for dsn in ["Salessum", "Windss", "Windss_New", "DOOR", "Reprtlog", "DPT"]:
    if dsn in sources:
        try:
            conn = pyodbc.connect(f"DSN={dsn}", autocommit=True)
            cur = conn.cursor()
            tables = [r[2] for r in cur.tables().fetchall() if r[2]]
            print(f"\nDSN '{dsn}' Tables: {tables}")
            conn.close()
        except Exception as e:
            print(f"DSN '{dsn}' error: {e}")
