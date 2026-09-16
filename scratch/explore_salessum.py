import pyodbc

dsn_name = 'Salessum'
print(f"Connecting to DSN: {dsn_name} ...")

try:
    conn = pyodbc.connect(f'DSN={dsn_name}', autocommit=True)
    cursor = conn.cursor()
    print("Connected successfully!\n")

    print("=== TABLES IN DSN ===")
    tables = cursor.tables().fetchall()
    table_names = []
    for r in tables:
        print("  Row:", [item for item in r])
        # Usually r[2] is table name in ODBC table listing (Catalog, Schema, Table_Name, Table_Type)
        if len(r) > 2 and r[2]:
            table_names.append(r[2])

    print(f"\nDiscovered Table Names: {table_names}")

    for tname in table_names:
        print(f"\n==========================================")
        print(f"TABLE: {tname}")
        print(f"==========================================")
        try:
            cols = cursor.columns(table=tname).fetchall()
            print("Columns:")
            for col in cols:
                print(f"  - {col[3]} (Type: {col[5]}, Size: {col[6]})")
            
            cursor.execute(f"SELECT * FROM {tname}")
            col_headers = [desc[0] for desc in cursor.description]
            print(f"\nHeader Fields: {col_headers}")
            rows = cursor.fetchmany(5)
            print(f"\nSample Rows (showing up to 5 of total):")
            for idx, row in enumerate(rows, 1):
                row_dict = dict(zip(col_headers, row))
                print(f"\n--- Row {idx} ---")
                for k, v in row_dict.items():
                    print(f"  {k}: {v}")
        except Exception as e:
            print(f"Error querying {tname}: {e}")

    conn.close()

except Exception as e:
    print(f"Connection/Query Error: {e}")
