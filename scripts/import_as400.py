#!/usr/bin/env python3
"""
import_as400.py - Imports EJ transactions from IBM AS400 via ODBC into ej.db (SQLite).

Usage:
    python import_as400.py <store_code> [dsn] [user] [password] [date_from] [reg_no] [trxn_no] [db_file] [library]

Libraries queried:
    1. <library>.cshhdr  - Transaction header (CSSTOR, CSDATE, CSTRAN, CSREG, CSTIL, CSTIME, CSCSH, CSCUST)
    2. <library>.cshdet  - Transaction detail (CSSKU, CSRETL, CSQTY)
    3. <library>.cshtnd  - Transaction tenders (CSDAMT, CSDTYP)

Date format (AS400): YYMMDD (6-digit integer, e.g. 260826)
Time format (AS400): HHMMSS (integer, e.g. 143000)
"""
import sys
import os
import json
import sqlite3


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def date_to_yymmdd(date_str):
    """Convert 'YYYY-MM-DD' -> 6-digit integer YYMMDD (e.g. '2026-08-26' -> 260826)."""
    try:
        parts = str(date_str).strip().split('-')
        if len(parts) == 3:
            y, m, d = parts[0].zfill(4), parts[1].zfill(2), parts[2].zfill(2)
            return int(f"{y[2:4]}{m}{d}")
    except Exception:
        pass
    try:
        return int(str(date_str).replace('-', '').strip())
    except Exception:
        return 0


def normalize_date(raw, fallback_date=""):
    """Convert AS400 YYMMDD (6-digit integer/string) -> 'YYYY-MM-DD'."""
    try:
        s = str(int(raw)).strip().zfill(6)
        if len(s) == 6:
            yy = int(s[0:2])
            prefix = "20" if yy <= 69 else "19"
            return f"{prefix}{s[0:2]}-{s[2:4]}-{s[4:6]}"
        elif len(s) == 8:
            return f"{s[0:4]}-{s[4:6]}-{s[6:8]}"
    except Exception:
        pass
    return fallback_date if fallback_date else str(raw).strip()


def normalize_time(raw):
    """Convert HHMMSS integer/string to 'HH:MM:SS'. Returns '' on failure."""
    try:
        s = str(int(raw)).zfill(6)
        if len(s) >= 6:
            return f"{s[0:2]}:{s[2:4]}:{s[4:6]}"
    except Exception:
        pass
    return str(raw).strip() if raw else ''


def get_field(row, *keys):
    """Case-insensitive field lookup. Returns '' if not found."""
    row_upper = {k.upper(): v for k, v in row.items()}
    for key in keys:
        val = row_upper.get(key.upper())
        if val is not None:
            return str(val).strip() if val is not None else ''
    return ''


def to_num_or_str(val):
    """Converts a value to int if all digits, else returns trimmed string."""
    if val is None:
        return val
    s = str(val).strip()
    return int(s) if s.isdigit() else s


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    if len(sys.argv) < 2:
        print(json.dumps({
            "status": "error",
            "message": "Usage: import_as400.py <store_code> [dsn] [user] [password] [date_from] [reg_no] [trxn_no] [db_file] [library]"
        }))
        sys.exit(1)

    store_code = sys.argv[1].replace(",", "").strip() if len(sys.argv) > 1 else ""
    dsn_name   = sys.argv[2].strip() if len(sys.argv) > 2 and sys.argv[2].strip() else "mms_as400"
    username   = sys.argv[3].strip() if len(sys.argv) > 3 and sys.argv[3].strip() else ""
    password   = sys.argv[4].strip() if len(sys.argv) > 4 and sys.argv[4].strip() else ""
    date_from  = sys.argv[5].strip() if len(sys.argv) > 5 and sys.argv[5].strip() else ""
    reg_no     = "".join(c for c in sys.argv[6] if c.isdigit()) if len(sys.argv) > 6 else ""
    trxn_no    = "".join(c for c in sys.argv[7] if c.isdigit()) if len(sys.argv) > 7 else ""

    script_dir = os.path.dirname(os.path.abspath(__file__))
    app_dir    = os.path.abspath(os.path.join(script_dir, ".."))
    db_file    = sys.argv[8].strip() if len(sys.argv) > 8 and sys.argv[8].strip() else os.path.join(app_dir, "ej.db")
    library    = sys.argv[9].strip() if len(sys.argv) > 9 and sys.argv[9].strip() else "MMLTSLIB"

    # Validation: store_code, date_from, reg_no are required; trxn_no is optional
    missing_fields = []
    if not store_code: missing_fields.append("store_code")
    if not date_from: missing_fields.append("date_from (Date)")
    if not reg_no: missing_fields.append("reg_no (Register #)")

    if missing_fields:
        print(json.dumps({
            "status": "error",
            "message": f"Required parameters missing: {', '.join(missing_fields)}"
        }))
        sys.exit(1)

    yymmdd_int = date_to_yymmdd(date_from)

    # -----------------------------------------------------------------------
    # Connect to SQLite ej.db
    # -----------------------------------------------------------------------
    try:
        sqlite_conn = sqlite3.connect(db_file)
        sqlite_cur  = sqlite_conn.cursor()

        sqlite_cur.execute("""
            CREATE TABLE IF NOT EXISTS ej_entries (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_date         TEXT    NOT NULL DEFAULT '',
                entry_time         TEXT    NOT NULL DEFAULT '',
                store_code         TEXT    NOT NULL DEFAULT '',
                register_number    TEXT    NOT NULL DEFAULT '',
                transaction_number TEXT    NOT NULL DEFAULT '',
                invoice_number     TEXT    NOT NULL DEFAULT '',
                member_number      TEXT    NOT NULL DEFAULT '',
                customer_name      TEXT    NOT NULL DEFAULT '',
                sales_associate    TEXT    NOT NULL DEFAULT '',
                associate_id       TEXT    NOT NULL DEFAULT '',
                till_number        TEXT    NOT NULL DEFAULT '',
                items              TEXT    NOT NULL DEFAULT '[]',
                tenders            TEXT    NOT NULL DEFAULT '[]',
                subtotal           REAL    NOT NULL DEFAULT 0.0,
                vatable_sales      REAL    NOT NULL DEFAULT 0.0,
                vat_rate           REAL    NOT NULL DEFAULT 12.0,
                total_vat          REAL    NOT NULL DEFAULT 0.0,
                total_non_vat      REAL    NOT NULL DEFAULT 0.0,
                total_amount       REAL    NOT NULL DEFAULT 0.0,
                created_at         TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
                updated_at         TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
            )
        """)
        sqlite_conn.commit()
    except Exception as e:
        print(json.dumps({"status": "error", "message": f"SQLite connection failed ({db_file}): {e}"}))
        sys.exit(1)

    # -----------------------------------------------------------------------
    # Import pyodbc
    # -----------------------------------------------------------------------
    try:
        import pyodbc
    except ImportError:
        print(json.dumps({"status": "error", "message": "pyodbc is not installed. Run: pip install pyodbc"}))
        sys.exit(1)

    # Fallback to as400_config.json if credentials are empty
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

    # -----------------------------------------------------------------------
    # Connect to AS400 via ODBC
    # -----------------------------------------------------------------------
    conn_str = f"DSN={dsn_name}"
    if username:
        conn_str += f";UID={username}"
    if password:
        conn_str += f";PWD={password}"

    try:
        as400_conn = pyodbc.connect(conn_str, autocommit=True)
        as400_cur  = as400_conn.cursor()
    except Exception as e:
        print(json.dumps({"status": "error", "message": f"AS400 connection failed (DSN='{dsn_name}', UID='{username}'): {e}"}))
        sys.exit(1)

    table_cshhdr = f"{library}.cshhdr"

    # If Transaction number is blank, fetch the last transaction where csttyp = 01 in <library>.cshhdr
    if not trxn_no:
        last_tran_found = None

        # Strategy 1: Direct SQL query filtering CSTTYP = '01' with ORDER BY CSTRAN DESC FETCH FIRST 1 ROWS ONLY
        for test_typ in ['01', 1]:
            if last_tran_found:
                break
            try:
                q_last = (
                    f"SELECT CSTRAN, CSTTYP FROM {table_cshhdr} "
                    f"WHERE CSSTOR = ? AND CSDATE = ? AND CSREG = ? AND CSTTYP = ? "
                    f"ORDER BY CSTRAN DESC FETCH FIRST 1 ROWS ONLY"
                )
                as400_cur.execute(q_last, [to_num_or_str(store_code), yymmdd_int, to_num_or_str(reg_no), test_typ])
                row_last = as400_cur.fetchone()
                if row_last and row_last[0] is not None:
                    last_tran_found = str(row_last[0]).strip()
                    break
            except Exception:
                try:
                    as400_cur.execute(q_last, [str(store_code).strip(), yymmdd_int, str(reg_no).strip(), test_typ])
                    row_last = as400_cur.fetchone()
                    if row_last and row_last[0] is not None:
                        last_tran_found = str(row_last[0]).strip()
                        break
                except Exception:
                    pass

        # Strategy 2: If not found or error, query all transactions for store/date/reg and filter CSTTYP in Python
        if not last_tran_found:
            try:
                q_all = (
                    f"SELECT CSTRAN, CSTTYP FROM {table_cshhdr} "
                    f"WHERE CSSTOR = ? AND CSDATE = ? AND CSREG = ?"
                )
                try:
                    as400_cur.execute(q_all, [to_num_or_str(store_code), yymmdd_int, to_num_or_str(reg_no)])
                except Exception:
                    as400_cur.execute(q_all, [str(store_code).strip(), yymmdd_int, str(reg_no).strip()])

                desc_cols = [d[0].upper() for d in as400_cur.description]
                matching_txns = []
                for row_all in as400_cur.fetchall():
                    r_dict = dict(zip(desc_cols, row_all))
                    raw_csttyp = str(get_field(r_dict, 'CSTTYP')).strip()
                    if raw_csttyp in ('01', '1') or raw_csttyp.zfill(2) == '01':
                        raw_cst = str(get_field(r_dict, 'CSTRAN')).strip()
                        if raw_cst:
                            num_val = int(raw_cst) if raw_cst.isdigit() else 0
                            matching_txns.append((num_val, raw_cst))
                if matching_txns:
                    matching_txns.sort(key=lambda x: x[0], reverse=True)
                    last_tran_found = matching_txns[0][1]
            except Exception as e_all:
                pass

        if not last_tran_found:
            print(json.dumps({
                "status": "not_found",
                "message": f"No transaction found with csttyp = 01 for Store: '{store_code}', Date: '{date_from}' (AS400 YYMMDD: {yymmdd_int}), Reg: '{reg_no}' in {table_cshhdr}."
            }))
            try:
                as400_conn.close()
                sqlite_conn.close()
            except Exception:
                pass
            sys.exit(0)

        trxn_no = last_tran_found

    # -----------------------------------------------------------------------
    # Build existing-entry map for deduplication
    # Key = (store_code, register_number, entry_date, transaction_number)
    # -----------------------------------------------------------------------
    sqlite_cur.execute(
        "SELECT id, store_code, register_number, entry_date, transaction_number FROM ej_entries"
    )
    existing_map = {}
    for r in sqlite_cur.fetchall():
        key = (str(r[1]).strip(), str(r[2]).strip(), str(r[3]).strip(), str(r[4]).strip())
        existing_map[key] = r[0]

    imported_count = 0
    updated_count  = 0
    skipped_count  = 0
    errors         = []

    # -----------------------------------------------------------------------
    # LIBRARY 1: <library>.cshhdr  -- Transaction Header
    # -----------------------------------------------------------------------
    try:
        where_parts = ["CSSTOR = ?", "CSDATE = ?", "CSREG = ?", "CSTRAN = ?"]
        query_hdr = (
            f"SELECT CSSTOR, CSDATE, CSTRAN, CSREG, CSTIL, CSTIME, CSCSH, CSCUST"
            f" FROM {table_cshhdr}"
            f" WHERE " + " AND ".join(where_parts)
        )

        params_hdr = [to_num_or_str(store_code), yymmdd_int, to_num_or_str(reg_no), to_num_or_str(trxn_no)]
        
        rows = []
        col_headers = []
        try:
            as400_cur.execute(query_hdr, params_hdr)
            rows = as400_cur.fetchall()
            col_headers = [desc[0].upper() for desc in as400_cur.description]
        except Exception as e_hdr1:
            # Fallback with string-based parameters
            try:
                str_params_hdr = [str(store_code).strip(), yymmdd_int, str(reg_no).strip(), str(trxn_no).strip()]
                as400_cur.execute(query_hdr, str_params_hdr)
                rows = as400_cur.fetchall()
                col_headers = [desc[0].upper() for desc in as400_cur.description]
            except Exception as e_hdr2:
                errors.append(f"cshhdr query error: {e_hdr2}")

        # Collect unique customer numbers for CRMCUS lookup
        unique_cust_nos = set()
        for row in rows:
            r = dict(zip(col_headers, row))
            cust_no = get_field(r, 'CSCUST').strip()
            if cust_no and cust_no != '0':
                unique_cust_nos.add(cust_no)

        # Bulk lookup customer names from CRMCUS
        cust_name_map = {}
        table_crmcus = f"{library}.CRMCUS"
        if unique_cust_nos:
            try:
                num_custs = [int(c) for c in unique_cust_nos if c.isdigit()]
                if num_custs:
                    placeholders = ",".join(["?"] * len(num_custs))
                    as400_cur.execute(
                        f"SELECT CUSTOMERNUMBER, CUSTOMERLASTNAME, CUSTOMERFIRSTNAME FROM {table_crmcus}"
                        f" WHERE CUSTOMERNUMBER IN ({placeholders})",
                        num_custs
                    )
                    for cr in as400_cur.fetchall():
                        cnum  = str(cr[0]).strip()
                        lname = str(cr[1]).strip() if cr[1] else ''
                        fname = str(cr[2]).strip() if cr[2] else ''
                        if lname or fname:
                            cname = f"{lname}, {fname}".strip(', ') if lname and fname else (lname or fname)
                            cust_name_map[cnum] = cname
            except Exception as e_cust:
                errors.append(f"CRMCUS lookup warning: {e_cust}")

        for row in rows:
            r = dict(zip(col_headers, row))

            s_code   = get_field(r, 'CSSTOR') or store_code
            e_date   = normalize_date(get_field(r, 'CSDATE'), date_from)
            trx_no   = get_field(r, 'CSTRAN')
            reg      = get_field(r, 'CSREG')
            till     = get_field(r, 'CSTIL')
            e_time   = normalize_time(get_field(r, 'CSTIME'))
            cashier  = get_field(r, 'CSCSH')
            cust_no  = get_field(r, 'CSCUST').strip()
            # Resolve full customer name from CRMCUS; fallback to raw cust_no
            customer = cust_name_map.get(cust_no, cust_name_map.get(str(int(cust_no)) if cust_no.isdigit() else cust_no, cust_no if cust_no != '0' else ''))

            dup_key = (s_code, reg, e_date, trx_no)

            if dup_key in existing_map:
                existing_id = existing_map[dup_key]
                sqlite_cur.execute("""
                    UPDATE ej_entries SET
                        entry_time      = ?,
                        member_number   = ?,
                        customer_name   = ?,
                        associate_id    = ?,
                        till_number     = ?,
                        updated_at      = datetime('now', 'localtime')
                    WHERE id = ?
                """, (e_time, cust_no, customer, cashier, till, existing_id))
                updated_count += 1
            else:
                sqlite_cur.execute("""
                    INSERT INTO ej_entries (
                        entry_date, entry_time, store_code, register_number,
                        transaction_number, till_number, member_number, customer_name, associate_id,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        datetime('now', 'localtime'), datetime('now', 'localtime')
                    )
                """, (e_date, e_time, s_code, reg, trx_no, till, cust_no, customer, cashier))
                existing_map[dup_key] = sqlite_cur.lastrowid
                imported_count += 1

        sqlite_conn.commit()

    except Exception as e:
        errors.append(f"cshhdr: {e}")

    # -----------------------------------------------------------------------
    # LIBRARY 2: <library>.cshdet  -- Transaction Detail (Line Items)
    # -----------------------------------------------------------------------
    table_cshdet = f"{library}.cshdet"
    try:
        det_rows = []
        det_headers = []

        params_det = [to_num_or_str(store_code), yymmdd_int, to_num_or_str(reg_no), to_num_or_str(trxn_no)]

        # Try querying with CSTRAN first for exact transaction matching
        try:
            q_det = (
                f"SELECT CSSTOR, CSDATE, CSREG, CSTRAN, CSSKU, CSRETL, CSQTY"
                f" FROM {table_cshdet}"
                f" WHERE CSSTOR = ? AND CSDATE = ? AND CSREG = ? AND CSTRAN = ?"
            )
            as400_cur.execute(q_det, params_det)
            det_rows = as400_cur.fetchall()
            det_headers = [desc[0].upper() for desc in as400_cur.description]
        except Exception:
            try:
                # Fallback without CSTRAN column in cshdet
                q_det = (
                    f"SELECT CSSTOR, CSDATE, CSREG, CSSKU, CSRETL, CSQTY"
                    f" FROM {table_cshdet}"
                    f" WHERE CSSTOR = ? AND CSDATE = ? AND CSREG = ?"
                )
                as400_cur.execute(q_det, [to_num_or_str(store_code), yymmdd_int, to_num_or_str(reg_no)])
                det_rows = as400_cur.fetchall()
                det_headers = [desc[0].upper() for desc in as400_cur.description]
            except Exception as e_det:
                errors.append(f"cshdet query error: {e_det}")

        # Rebuild existing_map keyed by (store_code, register_number, entry_date)
        sqlite_cur.execute(
            "SELECT id, store_code, register_number, entry_date, items FROM ej_entries"
        )
        hdr_map = {}
        for row in sqlite_cur.fetchall():
            rid, s, reg, dt, _ = row
            key = (str(s).strip(), str(reg).strip(), str(dt).strip())
            if key not in hdr_map:
                hdr_map[key] = []
            hdr_map[key].append(rid)

        # Collect unique SKUs to fetch descriptions in bulk from INVMST
        # Convert rows to dicts first so get_field works correctly
        det_row_dicts = [dict(zip(det_headers, row)) for row in det_rows]
        unique_skus = set(str(get_field(r, 'CSSKU')).strip() for r in det_row_dicts if get_field(r, 'CSSKU'))
        sku_desc_map = {}
        if unique_skus:
            try:
                table_invmst = f"{library}.INVMST"
                sku_list = list(unique_skus)
                for i in range(0, len(sku_list), 50):
                    chunk = sku_list[i:i+50]
                    num_chunk = [int(s) for s in chunk if str(s).strip().isdigit()]
                    if num_chunk:
                        placeholders = ",".join(["?"] * len(num_chunk))
                        try:
                            as400_cur.execute(f"SELECT INUMBR, IDESCR FROM {table_invmst} WHERE INUMBR IN ({placeholders})", num_chunk)
                            for inv_row in as400_cur.fetchall():
                                inum = str(inv_row[0]).strip()
                                idesc = str(inv_row[1]).strip() if inv_row[1] else ""
                                sku_desc_map[inum] = idesc
                        except Exception:
                            pass
                    for s in chunk:
                        s_str = str(s).strip()
                        if s_str not in sku_desc_map and (not s_str.isdigit() or str(int(s_str)) not in sku_desc_map):
                            try:
                                as400_cur.execute(f"SELECT IDESCR FROM {table_invmst} WHERE TRIM(CHAR(INUMBR)) = ? OR IVNDPN = ? FETCH FIRST 1 ROWS ONLY", (s_str, s_str))
                                r_desc = as400_cur.fetchone()
                                if r_desc and r_desc[0]:
                                    sku_desc_map[s_str] = str(r_desc[0]).strip()
                            except Exception:
                                pass
            except Exception as e_desc:
                errors.append(f"INVMST bulk lookup warning: {e_desc}")

        # Accumulate items per entry id
        new_items = {}
        for r in det_row_dicts:

            s_code  = get_field(r, 'CSSTOR') or store_code
            e_date  = normalize_date(get_field(r, 'CSDATE'), date_from)
            reg     = get_field(r, 'CSREG')
            sku     = get_field(r, 'CSSKU')
            try:
                price = float(get_field(r, 'CSRETL') or 0)
            except ValueError:
                price = 0.0
            try:
                qty = float(get_field(r, 'CSQTY') or 0)
            except ValueError:
                qty = 0.0

            hdr_key = (s_code, reg, e_date)
            matched_ids = hdr_map.get(hdr_key, [])

            s_clean = str(sku).strip()
            num_clean = str(int(s_clean)) if s_clean.isdigit() else s_clean
            item_desc = sku_desc_map.get(s_clean, sku_desc_map.get(num_clean, ""))

            item = {
                "sku":         sku,
                "description": item_desc,
                "quantity":    qty,
                "unit_price":  price,
                "amount":      round(price * qty, 2)
            }

            for entry_id in matched_ids:
                if entry_id not in new_items:
                    new_items[entry_id] = []
                new_items[entry_id].append(item)

        items_updated = 0
        for entry_id, item_list in new_items.items():
            sqlite_cur.execute("""
                UPDATE ej_entries SET
                    items      = ?,
                    updated_at = datetime('now', 'localtime')
                WHERE id = ?
            """, (json.dumps(item_list), entry_id))
            items_updated += 1

        sqlite_conn.commit()
        if items_updated > 0:
            errors.insert(0, f"cshdet: updated items for {items_updated} entries (OK)")

    except Exception as e:
        errors.append(f"cshdet: {e}")

    # -----------------------------------------------------------------------
    # LIBRARY 3: <library>.cshtnd  -- Tender / Payment Detail
    # -----------------------------------------------------------------------
    table_cshtnd = f"{library}.cshtnd"
    try:
        tnd_rows = []
        tnd_headers = []

        params_tnd = [to_num_or_str(store_code), yymmdd_int, to_num_or_str(reg_no), to_num_or_str(trxn_no)]
        query_tnd = (
            f"SELECT CSTRAN, CSSTOR, CSDATE, CSREG, CSDAMT, CSDTYP, CSTDOC"
            f" FROM {table_cshtnd}"
            f" WHERE CSSTOR = ? AND CSDATE = ? AND CSREG = ? AND CSTRAN = ?"
        )
        query_tnd_fallback = (
            f"SELECT CSTRAN, CSSTOR, CSDATE, CSREG, CSDAMT, CSDTYP"
            f" FROM {table_cshtnd}"
            f" WHERE CSSTOR = ? AND CSDATE = ? AND CSREG = ? AND CSTRAN = ?"
        )

        try:
            as400_cur.execute(query_tnd, params_tnd)
            tnd_rows = as400_cur.fetchall()
            tnd_headers = [desc[0].upper() for desc in as400_cur.description]
        except Exception as e_tnd1:
            try:
                str_params_tnd = [str(store_code).strip(), yymmdd_int, str(reg_no).strip(), str(trxn_no).strip()]
                as400_cur.execute(query_tnd, str_params_tnd)
                tnd_rows = as400_cur.fetchall()
                tnd_headers = [desc[0].upper() for desc in as400_cur.description]
            except Exception as e_tnd2:
                # Fallback to query without CSTDOC in case column is missing
                try:
                    as400_cur.execute(query_tnd_fallback, params_tnd)
                    tnd_rows = as400_cur.fetchall()
                    tnd_headers = [desc[0].upper() for desc in as400_cur.description]
                except Exception as e_tnd3:
                    try:
                        str_params_tnd = [str(store_code).strip(), yymmdd_int, str(reg_no).strip(), str(trxn_no).strip()]
                        as400_cur.execute(query_tnd_fallback, str_params_tnd)
                        tnd_rows = as400_cur.fetchall()
                        tnd_headers = [desc[0].upper() for desc in as400_cur.description]
                    except Exception as e_tnd4:
                        errors.append(f"cshtnd query error: {e_tnd4}")

        # Load tender code -> name mapping from tender_master
        tender_code_map = {}
        try:
            journal_db_path = os.path.join(app_dir, "journal.db")
            if os.path.exists(journal_db_path):
                j_conn = sqlite3.connect(journal_db_path)
                j_cur = j_conn.cursor()
                j_cur.execute("SELECT tender_code, tender_name FROM tender_master")
                for tc, tn in j_cur.fetchall():
                    if tc and tn:
                        tender_code_map[str(tc).strip().upper()] = str(tn).strip()
                j_conn.close()
        except Exception:
            pass

        sqlite_cur.execute(
            "SELECT id, store_code, register_number, entry_date, transaction_number FROM ej_entries"
        )
        trx_by_hdr = {}
        for row in sqlite_cur.fetchall():
            rid, s, reg, dt, trx = row
            hk = (str(s).strip(), str(reg).strip(), str(dt).strip(), str(trx).strip())
            trx_by_hdr[hk] = rid

        new_tenders = {}
        new_totals  = {}

        for row in tnd_rows:
            r = dict(zip(tnd_headers, row))

            trx_no_t = get_field(r, 'CSTRAN')
            s_code   = get_field(r, 'CSSTOR') or store_code
            e_date   = normalize_date(get_field(r, 'CSDATE'), date_from)
            reg_t    = get_field(r, 'CSREG')

            try:
                amt = float(get_field(r, 'CSDAMT') or 0)
            except ValueError:
                amt = 0.0
            raw_typ = get_field(r, 'CSDTYP').strip()
            # Map code (e.g. 'CA', 'B2', 'GC') to full tender name from tender_master (e.g. 'CASH', 'BPI EPS', 'Gift Cert Redeem')
            typ_name = tender_code_map.get(raw_typ.upper(), raw_typ if raw_typ else 'Cash')

            # Extract document number from CSTDOC
            doc_raw = get_field(r, 'CSTDOC')
            doc_no = ''
            if doc_raw is not None:
                doc_str = str(doc_raw).rstrip('\x00\r\n\t ').strip()
                if doc_str.endswith('.0'):
                    doc_str = doc_str[:-2]
                doc_no = doc_str.strip()

            is_gc = (
                raw_typ.upper() in ['GC', 'EG', 'GIFT'] or 
                'gift' in typ_name.lower() or 
                'cert' in typ_name.lower()
            )

            tender_name = typ_name
            if is_gc and doc_no and doc_no != '0':
                clean_doc = doc_no.lstrip('#').strip()
                if clean_doc.upper().startswith('GC#'):
                    clean_doc = clean_doc[3:].strip()
                elif clean_doc.upper().startswith('GC'):
                    clean_doc = clean_doc[2:].strip()
                clean_doc = clean_doc.lstrip('#').strip()
                tender_name = f"Gift Cert Redeem #     GC#{clean_doc}"

            entry_key = (s_code, reg_t, e_date, trx_no_t)
            entry_id  = trx_by_hdr.get(entry_key)

            if entry_id is None:
                skipped_count += 1
                continue

            tender = {"code": raw_typ, "name": tender_name, "amount": round(amt, 2)}
            if doc_no and doc_no != '0':
                tender["doc_no"] = doc_no

            if entry_id not in new_tenders:
                new_tenders[entry_id] = []
                new_totals[entry_id]  = 0.0
            new_tenders[entry_id].append(tender)
            new_totals[entry_id] = round(new_totals[entry_id] + amt, 2)

        tnd_updated = 0
        for entry_id, tnd_list in new_tenders.items():
            sqlite_cur.execute("""
                UPDATE ej_entries SET
                    tenders      = ?,
                    total_amount = ?,
                    updated_at   = datetime('now', 'localtime')
                WHERE id = ?
            """, (json.dumps(tnd_list), new_totals[entry_id], entry_id))
            tnd_updated += 1

        sqlite_conn.commit()
        if tnd_updated > 0:
            errors.insert(0, f"cshtnd: updated tenders for {tnd_updated} entries (OK)")

    except Exception as e:
        errors.append(f"cshtnd: {e}")

    # -----------------------------------------------------------------------
    # Cleanup
    # -----------------------------------------------------------------------
    try:
        as400_conn.close()
    except Exception:
        pass
    try:
        sqlite_conn.close()
    except Exception:
        pass

    # -----------------------------------------------------------------------
    # Output
    # -----------------------------------------------------------------------
    info_msgs   = [e for e in errors if ("(OK)" in e)]
    real_errors = [e for e in errors if e not in info_msgs]

    if real_errors:
        status = "partial" if imported_count + updated_count > 0 else "error"
        message = (
            f"Processed {imported_count + updated_count} records. Errors: " + "; ".join(real_errors)
        )
    elif imported_count + updated_count == 0:
        status = "not_found"
        message = (
            f"0 records found matching Store: '{store_code}', Date: '{date_from}' (AS400 YYMMDD: {yymmdd_int}), "
            f"Reg: '{reg_no}', Trxn: '{trxn_no}' in {table_cshhdr}."
        )
    else:
        status = "success"
        message = (
            f"Successfully processed {imported_count + updated_count} header records from {table_cshhdr} "
            f"({imported_count} new, {updated_count} updated). "
            f"Line items from {table_cshdet}. Tenders from {table_cshtnd}."
        )

    if info_msgs and status != "not_found":
        message += " " + "; ".join(info_msgs)

    # Find the SQLite entry ID for the imported record
    resolved_entry_id = None
    try:
        sqlite_cur.execute(
            "SELECT id FROM ej_entries WHERE store_code = ? AND register_number = ? AND transaction_number = ? ORDER BY id DESC LIMIT 1",
            (str(store_code).strip(), str(reg_no).strip(), str(trxn_no).strip())
        )
        row_eid = sqlite_cur.fetchone()
        if row_eid:
            resolved_entry_id = row_eid[0]
    except Exception:
        pass

    print(json.dumps({
        "status":             status,
        "imported":           imported_count,
        "updated":            updated_count,
        "skipped":            skipped_count,
        "store_code":         store_code,
        "date":               date_from,
        "register_number":    reg_no,
        "transaction_number": trxn_no,
        "entry_id":           resolved_entry_id,
        "libraries":          [table_cshhdr, table_cshdet, table_cshtnd],
        "message":            message,
        "errors":             real_errors
    }))


if __name__ == "__main__":
    main()


