import pyodbc

print("Connecting to Salesumm DSN...")
try:
    conn = pyodbc.connect('DSN=Salesumm', autocommit=True)
    cursor = conn.cursor()
    print("Connected successfully!\n")

    # List all tables
    print("=== TABLES FOUND ===")
    tables = cursor.tables()
    table_list = []
    for row in tables:
        if row.table_name:
            table_list.append(row.table_name)
            print(f"  Table: {row.table_name}  (Type: {row.table_type})")

    if not table_list:
        print("  No tables found.")

    # Try to show columns and sample data for each table
    print("\n=== TABLE DETAILS ===")
    for tname in table_list[:10]:  # limit to first 10 tables
        print(f"\n-- {tname} --")
        try:
            cols = cursor.columns(table=tname)
            col_names = [c.column_name for c in cols]
            print(f"  Columns: {col_names}")

            cursor.execute(f"SELECT TOP 3 * FROM [{tname}]")
            rows = cursor.fetchall()
            for r in rows:
                print(f"  Row: {list(r)}")
        except Exception as e:
            print(f"  Error reading {tname}: {e}")

    conn.close()

except Exception as e:
    print(f"Connection FAILED: {e}")
