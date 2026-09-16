#!/usr/bin/env python
# import_tender.py - Imports Tender Master from AS400 MMLTSLIB.CSHTRN into tender_master SQLite table
# Usage: python import_tender.py [dsn_name] [sqlite_db_path] [clear_first] [username] [password] [library]

import sys
import os
import pyodbc
import sqlite3
import json


def clean_str(val):
    """Strip trailing/leading whitespace and control characters."""
    if val is None:
        return ''
    return str(val).strip().rstrip('\x1f\x00\r\n\t').strip()


def main():
    script_dir  = os.path.dirname(os.path.abspath(__file__))
    app_dir     = os.path.abspath(os.path.join(script_dir, ".."))

    dsn_name    = sys.argv[1].strip() if len(sys.argv) > 1 and sys.argv[1].strip() else "mms_as400"
    db_path     = sys.argv[2].strip() if len(sys.argv) > 2 and sys.argv[2].strip() else os.path.join(app_dir, "journal.db")
    clear_first = (sys.argv[3].strip() in ['1', 'true', 'True']) if len(sys.argv) > 3 else True
    username    = sys.argv[4].strip() if len(sys.argv) > 4 and sys.argv[4].strip() else ""
    password    = sys.argv[5].strip() if len(sys.argv) > 5 and sys.argv[5].strip() else ""
    library     = sys.argv[6].strip() if len(sys.argv) > 6 and sys.argv[6].strip() else "MMLTSLIB"

    # Load credentials from as400_config.json if not provided
    config_file = os.path.join(app_dir, "as400_config.json")
    if os.path.exists(config_file):
        try:
            with open(config_file, "r", encoding="utf-8") as f:
                cfg = json.load(f)
                if not username and cfg.get("username"):
                    username = cfg.get("username", "")
                if not password and cfg.get("password"):
                    password = cfg.get("password", "")
                if dsn_name == "mms_as400" and cfg.get("dsn_name"):
                    dsn_name = cfg.get("dsn_name", "mms_as400")
                if library == "MMLTSLIB" and cfg.get("library"):
                    library = cfg.get("library", "MMLTSLIB")
        except Exception:
            pass

    if not os.path.exists(db_path):
        print(json.dumps({"success": False, "error": f"Database file not found: {db_path}"}))
        sys.exit(1)

    # 1. Connect to AS400 via ODBC
    conn_str = f"DSN={dsn_name}"
    if username:
        conn_str += f";UID={username}"
    if password:
        conn_str += f";PWD={password}"

    try:
        conn = pyodbc.connect(conn_str, autocommit=True)
    except Exception as e:
        print(json.dumps({"success": False, "error": f"AS400 ODBC connection failed (DSN='{dsn_name}'): {str(e)}"}))
        sys.exit(1)

    # 2. Query MMLTSLIB.CSHTRN — fetch CSTCOD and CSTDSC
    rows = []
    try:
        cur = conn.cursor()
        table = f"{library}.CSHTRN"
        cur.execute(f"SELECT CSTCOD, CSTDSC FROM {table}")
        rows = cur.fetchall()
        conn.close()
    except Exception as e:
        conn.close()
        print(json.dumps({"success": False, "error": f"Failed to query {library}.CSHTRN: {str(e)}"}))
        sys.exit(1)

    if not rows:
        print(json.dumps({"success": False, "error": f"No records found in {library}.CSHTRN."}))
        sys.exit(1)

    # 3. Connect to SQLite (journal.db)
    try:
        db = sqlite3.connect(db_path)
        db.row_factory = sqlite3.Row
    except Exception as e:
        print(json.dumps({"success": False, "error": f"SQLite connection failed: {str(e)}"}))
        sys.exit(1)

    # Ensure table exists
    db.execute("""
        CREATE TABLE IF NOT EXISTS tender_master (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tender_code TEXT NOT NULL DEFAULT '',
            tender_name TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    """)

    # Always delete all existing tender records before reimport
    if clear_first:
        db.execute("DELETE FROM tender_master")
        try:
            db.execute("DELETE FROM sqlite_sequence WHERE name='tender_master'")
        except Exception:
            pass

    inserted = 0
    skipped  = 0
    errors   = []

    for row in rows:
        t_code = clean_str(row[0])   # CSTCOD
        t_name = clean_str(row[1])   # CSTDSC

        if not t_code and not t_name:
            skipped += 1
            continue

        if not t_code:
            t_code = t_name
        if not t_name:
            t_name = t_code

        try:
            db.execute("""
                INSERT INTO tender_master (tender_code, tender_name, updated_at)
                VALUES (?, ?, datetime('now', 'localtime'))
            """, (t_code, t_name))
            inserted += 1
        except Exception as e:
            errors.append(f"Insert failed for tender_code={t_code}: {str(e)}")

    db.commit()
    db.close()

    result = {
        "success": True,
        "dsn":      dsn_name,
        "library":  library,
        "inserted": inserted,
        "skipped":  skipped,
        "total":    len(rows),
        "errors":   errors
    }
    print(json.dumps(result))


if __name__ == '__main__':
    main()
