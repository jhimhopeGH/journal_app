#!/usr/bin/env python
# import_regset.py - Imports REGSET.TPS from Salessum DSN into register_master SQLite table
# Usage: python import_regset.py <store_code> <sqlite_db_path>

import sys
import os
import pyodbc
import sqlite3
import json

def clean_str(val):
    """Strip trailing/leading whitespace and control characters (e.g. \x1f) from TPS strings."""
    if val is None:
        return ''
    return str(val).strip().rstrip('\x1f').strip()

def main():
    if len(sys.argv) < 3:
        result = {"success": False, "error": "Usage: import_regset.py <store_code> <db_path>"}
        print(json.dumps(result))
        sys.exit(1)

    store_code = sys.argv[1].strip()
    db_path    = sys.argv[2].strip()

    if not store_code:
        print(json.dumps({"success": False, "error": "Store code is required."}))
        sys.exit(1)

    if not os.path.exists(db_path):
        print(json.dumps({"success": False, "error": f"Database file not found: {db_path}"}))
        sys.exit(1)

    # Connect to TopSpeed via ODBC (Salessum DSN holds REGSET.TPS)
    try:
        conn = pyodbc.connect("DSN=Salessum", autocommit=True)
    except Exception as e:
        print(json.dumps({"success": False, "error": f"ODBC connection failed: {str(e)}"}))
        sys.exit(1)

    try:
        cur = conn.cursor()
        cur.execute("SELECT REG_NO, SERIALNO, PERMITNO, MINNO FROM REGSET")
        rows = cur.fetchall()
        cols = [d[0] for d in cur.description]
        conn.close()
    except Exception as e:
        conn.close()
        print(json.dumps({"success": False, "error": f"Failed to read REGSET: {str(e)}"}))
        sys.exit(1)

    if not rows:
        print(json.dumps({"success": False, "error": "No records found in REGSET table."}))
        sys.exit(1)

    # Connect to SQLite
    try:
        db = sqlite3.connect(db_path)
        db.row_factory = sqlite3.Row
    except Exception as e:
        print(json.dumps({"success": False, "error": f"SQLite connection failed: {str(e)}"}))
        sys.exit(1)

    inserted = 0
    skipped  = 0
    errors   = []

    for row in rows:
        reg_no       = clean_str(row[0])
        serial_no    = clean_str(row[1])
        permit_no    = clean_str(row[2])
        min_no       = clean_str(row[3])

        if not reg_no:
            skipped += 1
            continue

        # Check for duplicate (same store_code + reg_no)
        check = db.execute(
            "SELECT id FROM register_master WHERE store_code = ? AND reg_no = ?",
            (store_code, reg_no)
        ).fetchone()

        if check:
            # Update existing
            try:
                db.execute("""
                    UPDATE register_master
                    SET serial_number = ?,
                        permit_number = ?,
                        min_number    = ?,
                        updated_at    = datetime('now', 'localtime')
                    WHERE store_code = ? AND reg_no = ?
                """, (serial_no, permit_no, min_no, store_code, reg_no))
                inserted += 1
            except Exception as e:
                errors.append(f"Update failed for reg_no={reg_no}: {str(e)}")
        else:
            # Insert new
            try:
                db.execute("""
                    INSERT INTO register_master (store_code, reg_no, serial_number, permit_number, min_number, updated_at)
                    VALUES (?, ?, ?, ?, ?, datetime('now', 'localtime'))
                """, (store_code, reg_no, serial_no, permit_no, min_no))
                inserted += 1
            except Exception as e:
                errors.append(f"Insert failed for reg_no={reg_no}: {str(e)}")

    db.commit()
    db.close()

    result = {
        "success": True,
        "inserted": inserted,
        "skipped": skipped,
        "total": len(rows),
        "errors": errors
    }
    print(json.dumps(result))

if __name__ == '__main__':
    main()
