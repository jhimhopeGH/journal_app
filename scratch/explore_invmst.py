import pyodbc
import json

dsn_name = 'mms_as400'
print(f"Connecting to DSN: {dsn_name} ...")

try:
    conn = pyodbc.connect(f'DSN={dsn_name}', autocommit=True)
    cursor = conn.cursor()
    print("Connected successfully!\n")

    print("=== QUERYING MMLTSLIB.INVMST COLUMNS ===")
    try:
        cursor.execute("SELECT * FROM MMLTSLIB.INVMST FETCH FIRST 1 ROWS ONLY")
        col_names = [desc[0] for desc in cursor.description]
        print(f"Columns in MMLTSLIB.INVMST ({len(col_names)} cols):")
        print(col_names)
        
        row = cursor.fetchone()
        if row:
            print("\nSample Row:")
            row_dict = dict(zip(col_names, row))
            for k in col_names[:25]:
                print(f"  {k}: {row_dict.get(k)}")
    except Exception as e:
        print(f"Error querying MMLTSLIB.INVMST: {e}")

    conn.close()
except Exception as e:
    print(f"Connection error: {e}")
