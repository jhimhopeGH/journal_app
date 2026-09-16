import pyodbc
import json

dsn_name = 'mms_as400'
print(f"Connecting to DSN: {dsn_name} ...")

try:
    conn = pyodbc.connect(f'DSN={dsn_name}', autocommit=True)
    cursor = conn.cursor()
    print("Connected successfully!\n")

    print("=== TABLES IN DSN ===")
    try:
        tables = cursor.tables().fetchall()
        print(f"Found {len(tables)} tables/objects.")
        for r in tables[:30]:
            print("  Table:", [str(item) for item in r])
    except Exception as e:
        print("Error listing tables:", e)

    conn.close()
except Exception as e:
    print(f"Connection error: {e}")
