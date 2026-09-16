#!/usr/bin/env python
# import_empmst.py - Imports EMPMST.TPS from Salessum ODBC DSN into cashier_master SQLite table
# Usage: python import_empmst.py <store_code> [dsn_name] [sqlite_db_path]

import sys
import os
import pyodbc
import sqlite3
import json


def clean_str(val):
    """Strip trailing/leading whitespace and control characters from strings."""
    if val is None:
        return ''
    return str(val).strip().rstrip('\x1f\x00\r\n\t').strip()


def find_col(col_map, candidates):
    """Find the first matching column name from candidates (case-insensitive)."""
    for c in candidates:
        if c.upper() in col_map:
            return col_map[c.upper()]
    # Partial match fallback
    for c in candidates:
        for k in col_map:
            if c.upper() in k:
                return col_map[k]
    return None


def main():
    if len(sys.argv) < 2:
        result = {"success": False, "error": "Usage: import_empmst.py <store_code> [dsn_name] [sqlite_db_path]"}
        print(json.dumps(result))
        sys.exit(1)

    store_code = sys.argv[1].strip()
    if not store_code:
        print(json.dumps({"success": False, "error": "Store code is required."}))
        sys.exit(1)

    dsn_name = sys.argv[2].strip() if len(sys.argv) > 2 and sys.argv[2].strip() else "Salessum"

    script_dir = os.path.dirname(os.path.abspath(__file__))
    app_dir    = os.path.abspath(os.path.join(script_dir, ".."))
    db_path    = sys.argv[3].strip() if len(sys.argv) > 3 and sys.argv[3].strip() else os.path.join(app_dir, "journal.db")

    if not os.path.exists(db_path):
        print(json.dumps({"success": False, "error": f"Database file not found: {db_path}"}))
        sys.exit(1)

    # 1. Connect to TopSpeed via ODBC
    try:
        conn = pyodbc.connect(f"DSN={dsn_name}", autocommit=True)
    except Exception as e:
        print(json.dumps({"success": False, "error": f"TopSpeed ODBC connection failed (DSN='{dsn_name}'): {str(e)}"}))
        sys.exit(1)

    # 2. Query EMPMST table
    cur = conn.cursor()
    rows = []
    col_names = []
    query_errors = []

    table_candidates = ["EMPMST", "EMPMST.TPS", "\"EMPMST\"", "empmst"]
    for tbl in table_candidates:
        try:
            cur.execute(f"SELECT * FROM {tbl}")
            rows = cur.fetchall()
            col_names = [d[0].upper().strip() for d in cur.description]
            break
        except Exception as e:
            query_errors.append(f"{tbl}: {str(e)}")

    if not col_names and not rows:
        # Try inspecting table names from ODBC metadata
        try:
            available_tables = [t.table_name for t in cur.tables()]
            for tbl in available_tables:
                if "EMP" in tbl.upper():
                    cur.execute(f"SELECT * FROM {tbl}")
                    rows = cur.fetchall()
                    col_names = [d[0].upper().strip() for d in cur.description]
                    break
        except Exception:
            pass

    conn.close()

    if not col_names:
        err_detail = " | ".join(query_errors) if query_errors else "Table EMPMST not found in DSN."
        print(json.dumps({"success": False, "error": f"Failed to query EMPMST from DSN '{dsn_name}': {err_detail}"}))
        sys.exit(1)

    if not rows:
        print(json.dumps({
            "success": True,
            "store_code": store_code,
            "dsn": dsn_name,
            "inserted": 0,
            "updated": 0,
            "skipped": 0,
            "total": 0,
            "message": "EMPMST table is empty."
        }))
        sys.exit(0)

    # Map column names to index
    col_map = {name: idx for idx, name in enumerate(col_names)}

    # Resolve column indexes for Name, Job, and Status (do NOT import EMP NO as requested)
    name_idx   = find_col(col_map, ["NAME", "EMP_NAME", "EMPNAME", "FULLNAME", "CASHIERNAME", "DESCRIPTION", "DESC"])
    job_idx    = find_col(col_map, ["JOB", "JOB_TITLE", "JOBTITLE", "POSITION", "POS", "TITLE", "ROLE"])
    status_idx = find_col(col_map, ["STATUS", "STAT", "ACTIVE", "STATE"])

    # 3. Connect to SQLite (journal.db)
    try:
        db = sqlite3.connect(db_path)
        db.row_factory = sqlite3.Row
    except Exception as e:
        print(json.dumps({"success": False, "error": f"SQLite connection failed: {str(e)}"}))
        sys.exit(1)

    # Ensure table and indexes exist
    db.execute("""
        CREATE TABLE IF NOT EXISTS cashier_master (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            store_code TEXT    NOT NULL DEFAULT '',
            emp_no     TEXT    NOT NULL DEFAULT '',
            name       TEXT    NOT NULL DEFAULT '',
            job        TEXT    NOT NULL DEFAULT '',
            status     TEXT    NOT NULL DEFAULT '',
            updated_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    """)
    db.execute("CREATE INDEX IF NOT EXISTS idx_cashier_store_emp ON cashier_master (store_code, emp_no)")
    db.execute("CREATE INDEX IF NOT EXISTS idx_cashier_store_name ON cashier_master (store_code, name)")

    inserted = 0
    updated  = 0
    skipped  = 0
    errors   = []

    for row in rows:
        job    = clean_str(row[job_idx]) if job_idx is not None else ''
        
        # Filter: only import records where job code is "CSH"
        if job.upper() != 'CSH':
            skipped += 1
            continue

        name   = clean_str(row[name_idx]) if name_idx is not None else ''
        status = clean_str(row[status_idx]) if status_idx is not None else ''
        emp_no = ''  # Do NOT import EMP NO

        # Skip rows with no identifying name
        if not name:
            skipped += 1
            continue

        # Check existing record for same store_code and name
        existing = db.execute(
            "SELECT id FROM cashier_master WHERE store_code = ? AND name = ?",
            (store_code, name)
        ).fetchone()

        if existing:
            try:
                db.execute("""
                    UPDATE cashier_master
                    SET job        = ?,
                        status     = ?,
                        updated_at = datetime('now', 'localtime')
                    WHERE id = ?
                """, (job, status, existing['id']))
                updated += 1
            except Exception as e:
                errors.append(f"Update failed for {name}: {str(e)}")
        else:
            try:
                db.execute("""
                    INSERT INTO cashier_master (store_code, emp_no, name, job, status, updated_at)
                    VALUES (?, '', ?, ?, ?, datetime('now', 'localtime'))
                """, (store_code, name, job, status))
                inserted += 1
            except Exception as e:
                errors.append(f"Insert failed for {name}: {str(e)}")

    db.commit()
    db.close()

    result = {
        "success":    True,
        "store_code": store_code,
        "dsn":        dsn_name,
        "columns":    col_names,
        "inserted":   inserted,
        "updated":    updated,
        "skipped":    skipped,
        "total":      len(rows),
        "errors":     errors
    }
    print(json.dumps(result))


if __name__ == '__main__':
    main()
