#!/usr/bin/env python3
"""
lookup_sku.py - Queries MMLTSLIB.INVMST on AS400 (via mms_as400 ODBC) for item description (IDESCR)
"""
import sys
import json
import os

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"status": "error", "message": "Usage: lookup_sku.py <sku> [dsn_name] [username] [password] [library]"}))
        sys.exit(1)

    raw_sku = sys.argv[1].strip()
    if not raw_sku:
        print(json.dumps({"status": "error", "message": "SKU cannot be empty."}))
        sys.exit(1)

    dsn_name = sys.argv[2].strip() if len(sys.argv) > 2 and sys.argv[2].strip() else "mms_as400"
    username = sys.argv[3].strip() if len(sys.argv) > 3 and sys.argv[3].strip() else ""
    password = sys.argv[4].strip() if len(sys.argv) > 4 and sys.argv[4].strip() else ""
    library  = sys.argv[5].strip() if len(sys.argv) > 5 and sys.argv[5].strip() else "MMLTSLIB"

    # If username/password not passed in args, check as400_config.json
    script_dir = os.path.dirname(os.path.abspath(__file__))
    app_dir = os.path.abspath(os.path.join(script_dir, ".."))
    config_file = os.path.join(app_dir, "as400_config.json")
    if os.path.exists(config_file):
        try:
            with open(config_file, "r", encoding="utf-8") as f:
                cfg = json.load(f)
                if not username and cfg.get("username"):
                    username = cfg.get("username", "")
                if not password and cfg.get("password"):
                    password = cfg.get("password", "")
                if not dsn_name or dsn_name == "mms_as400":
                    dsn_name = cfg.get("dsn_name", "mms_as400")
                if not library or library == "MMLTSLIB":
                    library = cfg.get("library", "MMLTSLIB")
        except Exception:
            pass

    try:
        import pyodbc
    except ImportError:
        print(json.dumps({"status": "error", "message": "pyodbc module not found in Python environment."}))
        sys.exit(1)

    conn_str = f"DSN={dsn_name}"
    if username:
        conn_str += f";UID={username}"
    if password:
        conn_str += f";PWD={password}"

    try:
        conn = pyodbc.connect(conn_str, autocommit=True)
        cursor = conn.cursor()
    except Exception as e:
        print(json.dumps({
            "status": "error",
            "message": f"Connection failed to AS400 DSN '{dsn_name}': {str(e)}"
        }))
        sys.exit(1)

    try:
        table_invmst = f"{library}.INVMST"
        
        found = False
        desc = ""
        price = 0.0

        candidates = [raw_sku]
        # Strip leading zeros
        stripped_sku = raw_sku.lstrip('0')
        if stripped_sku and stripped_sku not in candidates:
            candidates.append(stripped_sku)
        # If 9 digits (8 digits INUMBR + 1 check digit)
        if len(raw_sku) == 9 and raw_sku.isdigit():
            candidates.append(raw_sku[:8])
        if len(stripped_sku) == 9 and stripped_sku.isdigit():
            candidates.append(stripped_sku[:8])

        for c_sku in candidates:
            if found:
                break

            # 1. Numeric lookup on INUMBR
            if c_sku.isdigit():
                try:
                    sku_num = int(c_sku)
                    cursor.execute(f"SELECT IDESCR, INLRTL FROM {table_invmst} WHERE INUMBR = ? FETCH FIRST 1 ROWS ONLY", (sku_num,))
                    row = cursor.fetchone()
                    if row and row[0]:
                        desc = str(row[0]).strip()
                        try:
                            price = float(row[1] or 0)
                        except Exception:
                            price = 0.0
                        found = True
                        break
                except Exception:
                    pass

            # 2. String lookup on INUMBR / IVNDPN
            if not found:
                try:
                    cursor.execute(f"SELECT IDESCR, INLRTL FROM {table_invmst} WHERE TRIM(CHAR(INUMBR)) = ? OR IVNDPN = ? FETCH FIRST 1 ROWS ONLY", (c_sku, c_sku))
                    row = cursor.fetchone()
                    if row and row[0]:
                        desc = str(row[0]).strip()
                        try:
                            price = float(row[1] or 0)
                        except Exception:
                            price = 0.0
                        found = True
                        break
                except Exception:
                    pass

        # 3. Fallback: check INVUPC table if available in the library
        if not found:
            try:
                table_invupc = f"{library}.INVUPC"
                cursor.execute(f"""
                    SELECT m.IDESCR, m.INLRTL 
                    FROM {table_invupc} u 
                    JOIN {table_invmst} m ON u.INUMBR = m.INUMBR 
                    WHERE u.IUPC = ? OR TRIM(CHAR(u.IUPC)) = ?
                    FETCH FIRST 1 ROWS ONLY
                """, (raw_sku, raw_sku))
                row = cursor.fetchone()
                if row and row[0]:
                    desc = str(row[0]).strip()
                    try:
                        price = float(row[1] or 0)
                    except Exception:
                        price = 0.0
                    found = True
            except Exception:
                pass

        conn.close()

        if found:
            print(json.dumps({
                "status": "success",
                "found": True,
                "sku": raw_sku,
                "description": desc,
                "price": price
            }))
        else:
            print(json.dumps({
                "status": "success",
                "found": False,
                "sku": raw_sku,
                "message": f"SKU '{raw_sku}' not found in {table_invmst}."
            }))

    except Exception as e:
        print(json.dumps({
            "status": "error",
            "message": f"Query error on {table_invmst}: {str(e)}"
        }))

if __name__ == "__main__":
    main()
