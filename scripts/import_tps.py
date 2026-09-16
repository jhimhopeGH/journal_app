import sys
import os
import json
import datetime
import sqlite3
import pyodbc
import random

# Base date for Clarion standard
CLARION_BASE = datetime.date(1800, 12, 28)

def clarion_to_date_str(val):
    try:
        val_int = int(val)
        if val_int <= 0:
            return ""
        d = CLARION_BASE + datetime.timedelta(days=val_int)
        return d.strftime("%Y-%m-%d")
    except Exception:
        return ""

def classify_tender(name):
    n = str(name).strip().upper()
    if 'MERCHANDISE CREDIT' in n:
        return 'other'
    if n in ('CASH', 'CA') or n.startswith('CASH'):
        return 'cash'
    if 'DEBIT' in n or 'ATM' in n:
        return 'debit'
    if 'CREDIT' in n or 'CITIPAYLITE' in n:
        return 'credit'
    return 'other'

def get_debit_counterpart(name):
    n = str(name).strip().upper()
    if 'METROBANK' in n or 'MBTC' in n:
        return 'METROBANK DEBIT CARD'
    if 'BPI' in n:
        return 'BPI Express ATM Card'
    if 'BDO' in n:
        return 'BDO DEBIT'
    if 'LBP' in n:
        return 'LBP DEBIT'
    if 'ONB' in n:
        return 'ONB DEBIT CARD'
    return 'BDO DEBIT'

def normalize_and_sort_tenders(tender_list):
    if not isinstance(tender_list, list):
        return []
    
    cash_items = []
    credit_items = []
    debit_items = []
    other_items = []

    for item in tender_list:
        if not isinstance(item, dict) or not item.get('name'):
            continue
        raw_name = str(item['name']).strip()
        amt = float(str(item.get('amount', 0)).replace(',', '') or 0)

        upper_n = raw_name.upper()
        if upper_n in ('AR', 'A/R') or 'HOUSE ACCOUNT' in upper_n or 'HOUSE CHARGE' in upper_n or 'ACCOUNTS RECEIVABLE' in upper_n or 'ACCOUNT RECEIVABLE' in upper_n:
            raw_name = 'AR'

        category = classify_tender(raw_name)

        if category == 'cash':
            cash_items.append({'name': raw_name, 'amount': amt})
        elif category == 'credit':
            credit_items.append({'name': raw_name, 'amount': amt})
        elif category == 'debit':
            debit_items.append({'name': raw_name, 'amount': amt})
        else:
            other_items.append({'name': raw_name, 'amount': amt})

    # 1. Consolidate Cash into 1 code
    final_cash = []
    if cash_items:
        tot_cash = sum(x['amount'] for x in cash_items)
        final_cash.append({'name': cash_items[0]['name'], 'amount': round(tot_cash, 2)})

    # 2. Credit Cards & Debit Cards: ensure only 1 tender code per type
    final_credit = []
    final_debit = []

    if debit_items:
        tot_deb = sum(x['amount'] for x in debit_items)
        final_debit.append({'name': debit_items[0]['name'], 'amount': round(tot_deb, 2)})

    if credit_items:
        first_c = credit_items[0]
        final_credit.append({'name': first_c['name'], 'amount': round(first_c['amount'], 2)})

        if len(credit_items) > 1:
            for extra_c in credit_items[1:]:
                if not final_debit:
                    deb_name = get_debit_counterpart(extra_c['name'])
                    final_debit.append({'name': deb_name, 'amount': round(extra_c['amount'], 2)})
                else:
                    final_credit[0]['amount'] = round(final_credit[0]['amount'] + extra_c['amount'], 2)

    # 3. Consolidate identical Other tenders
    final_other = []
    other_map = {}
    for oth in other_items:
        key = oth['name'].strip().lower()
        if key in other_map:
            other_map[key]['amount'] = round(other_map[key]['amount'] + oth['amount'], 2)
        else:
            entry = {'name': oth['name'].strip(), 'amount': round(oth['amount'], 2)}
            other_map[key] = entry
            final_other.append(entry)

    # Result: Cash first, then Credit Cards, then Debit Cards, then Others
    return final_cash + final_credit + final_debit + final_other

def import_tps(store_code, dsn_name="Salessum", db_path=None):
    if not db_path:
        db_path = os.path.join(os.path.dirname(__file__), "..", "journal.db")
        db_path = os.path.abspath(db_path)

    if not store_code:
        return {"status": "error", "message": "Store code is required."}

    # Connect to SQLite
    sqlite_conn = sqlite3.connect(db_path)
    sqlite_cur = sqlite_conn.cursor()

    # Connect to TopSpeed ODBC
    try:
        tps_conn = pyodbc.connect(f"DSN={dsn_name}", autocommit=True)
        tps_cur = tps_conn.cursor()
    except Exception as e:
        return {"status": "error", "message": f"Failed to connect to TopSpeed ODBC ({dsn_name}): {str(e)}"}

    try:
        tps_cur.execute("SELECT * FROM SALESUMM")
        col_names = [desc[0].upper() for desc in tps_cur.description]
        rows = tps_cur.fetchall()
    except Exception as e:
        tps_conn.close()
        sqlite_conn.close()
        return {"status": "error", "message": f"Failed to read SALESUMM table: {str(e)}"}

    imported_count = 0
    skipped_count = 0
    updated_count = 0

    # Read existing entries to prevent duplicate insertion
    # (Checking store_number, register_number, entry_date, zread_number)
    sqlite_cur.execute("SELECT id, store_number, register_number, entry_date, zread_number FROM entries")
    existing_map = {}
    for r in sqlite_cur.fetchall():
        key = (str(r[1]).strip(), str(r[2]).strip(), str(r[3]).strip(), str(r[4]).strip())
        existing_map[key] = r[0]

    # Build tender column mapping using tender_master for canonical names.
    # TPS has fixed bucket columns; we map them to display names from tender_master.
    sqlite_cur.execute("SELECT UPPER(TRIM(tender_code)), TRIM(tender_name) FROM tender_master ORDER BY id")
    tm_rows = sqlite_cur.fetchall()
    tm_code_map = dict(tm_rows)
    tm_name_map = {r[1].lower(): r[1] for r in tm_rows}

    # Credit card pool: BPI Credit (A2), Metrobank Credit Card (G2), BDO Credit (L2)
    card_bpi   = tm_code_map.get('A2', 'BPI CREDIT')
    card_metro = tm_code_map.get('G2', 'METROBANK CREDITCARD')
    card_bdo   = tm_code_map.get('L2', 'BDO CREDIT')
    credit_pool = [card_bpi, card_metro, card_bdo]

    # Debit card pool: BPI Express ATM Card (B2), Metrobank Debit Card (52), BDO Debit (H2)
    debit_bpi   = tm_code_map.get('B2', 'BPI Express ATM Card')
    debit_metro = tm_code_map.get('52', 'METROBANK DEBIT CARD')
    debit_bdo   = tm_code_map.get('H2', 'BDO DEBIT')

    # Other tender: RNB Card (C2) / Rewards Card (F8)
    other_rnb     = tm_code_map.get('C2', 'RNB CARD')
    other_rewards = tm_code_map.get('F8', 'REWARDS CARD')

    def resolve_tender(raw_name):
        return tm_name_map.get(raw_name.strip().lower(), raw_name)

    # (fallback_name, TPS_column)
    tender_col_map = [
        (resolve_tender("Cash"),                 "CASH"),
        (resolve_tender("Check"),                "CHEQUE"),
        (card_bpi,                               "CARD"),    # TPS "Card" bucket -> Credit Card / Debit Card
        (resolve_tender("Gift Cert Redeem"),     "GC"),      # TPS "GC" bucket -> Gift Cert Redeem (TND-005)
        ("AR",                                   "AR"),      # TPS "AR" bucket -> AR
        (other_rnb,                              "OTHERS"),  # TPS "Others" bucket -> RNB CARD
    ]

    for row in rows:
        r_dict = dict(zip(col_names, row))

        entry_date = clarion_to_date_str(r_dict.get("DATE", 0))
        if not entry_date:
            skipped_count += 1
            continue

        reg_no = str(r_dict.get("REG_NO", "") or "").strip()
        if reg_no == "0" or reg_no == "":
            skipped_count += 1
            continue
        
        # Reset counter / Z-Read number
        rc = r_dict.get("RC", 0)
        zread_no = str(int(float(rc))) if rc is not None else "0"

        # Last trx # is CURRRTX_NO from TPS file
        last_trx = str(r_dict.get("CURRRTX_NO", "") or "").strip()
        trx_no = last_trx  # Keep transaction_number aligned

        # Sales & Totals (DISCOUNT, REFUND, VOID are excluded)
        daily_sales = float(r_dict.get("SALES", 0.0) or 0.0)
        vat_sales = float(r_dict.get("VATSALESX", 0.0) or 0.0)
        non_vat_sales = float(r_dict.get("NONVATSALESX", 0.0) or 0.0)
        old_gt = float(r_dict.get("PREVGRANDTOTALX", 0.0) or 0.0)
        new_gt = float(r_dict.get("CURRGRANDTOTALX", 0.0) or 0.0)

        # Build tender breakdown using TPS bucket columns mapped to canonical names
        tenders = []
        for t_name, t_col in tender_col_map:
            val = float(r_dict.get(t_col, 0.0) or 0.0)
            if val > 0:
                if t_col == "CARD":
                    if val > 9000:
                        # Multiple types acceptable: 1 Credit Card tender code + 1 Debit Card tender code
                        chosen_c = random.choice(credit_pool)
                        if chosen_c == card_bpi:
                            chosen_d = debit_bpi
                        elif chosen_c == card_metro:
                            chosen_d = debit_metro
                        else:
                            chosen_d = debit_bdo

                        if random.random() < 0.5:
                            amt1 = round(val * 0.5, 2)
                            amt2 = round(val - amt1, 2)
                        else:
                            amt1 = round(val * 0.55, 2)
                            amt2 = round(val - amt1, 2)
                        tenders.append({"name": chosen_c, "amount": amt1})
                        tenders.append({"name": chosen_d, "amount": amt2})
                    else:
                        tenders.append({"name": random.choice(credit_pool), "amount": val})
                elif t_col == "OTHERS":
                    # Only 1 tender code for others
                    tenders.append({"name": other_rnb, "amount": val})
                else:
                    tenders.append({"name": t_name, "amount": val})

        tenders = normalize_and_sort_tenders(tenders)
        tender_json = json.dumps(tenders)

        # Check duplicate
        dup_key = (store_code.strip(), reg_no, entry_date, zread_no)
        if dup_key in existing_map:
            # Update existing
            entry_id = existing_map[dup_key]
            sqlite_cur.execute("""
                UPDATE entries SET
                    transaction_number = ?,
                    last_trx_number = ?,
                    tender = ?,
                    total_vat = ?,
                    total_non_vat = ?,
                    daily_sales = ?,
                    old_grand_total = ?,
                    new_grand_total = ?
                WHERE id = ?
            """, (
                trx_no, last_trx, tender_json,
                vat_sales, non_vat_sales, daily_sales, old_gt, new_gt,
                entry_id
            ))
            updated_count += 1
        else:
            rand_time = f"20:{random.randint(0, 59):02d}:{random.randint(0, 59):02d}"
            sqlite_cur.execute("""
                INSERT INTO entries (
                    store_number, register_number, transaction_number,
                    entry_date, entry_time, zread_number, till_number,
                    last_trx_number, tender, total_vat, total_non_vat,
                    daily_sales, old_grand_total, new_grand_total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                store_code.strip(), reg_no, trx_no,
                entry_date, rand_time, zread_no, "1",
                last_trx, tender_json, vat_sales, non_vat_sales,
                daily_sales, old_gt, new_gt
            ))
            imported_count += 1

    # After import/update, recalculate any entries where daily_sales or old_grand_total is 0
    recalc_count = recalculate_grand_totals(sqlite_cur, store_code)
    sqlite_conn.commit()
    tps_conn.close()
    sqlite_conn.close()

    return {
        "status": "success",
        "total_read": len(rows),
        "imported": imported_count,
        "updated": updated_count,
        "recalculated": recalc_count,
        "store_code": store_code
    }


def recalculate_grand_totals(cur, store_code=None):
    """
    Fix entries where:
    1. daily_sales = 0 but total_vat + total_non_vat > 0  -> set daily_sales = total_vat + total_non_vat
    2. old_grand_total = 0 and zread_number > 1           -> fill from previous z-read's new_grand_total
       (matched by store_number + register_number, ordered by zread_number)

    Returns the count of rows updated.
    """
    updated = 0

    # Step 1: Fix daily_sales where it's 0 but VAT/NonVAT data exists
    if store_code:
        cur.execute("""
            UPDATE entries
            SET daily_sales = total_vat + total_non_vat
            WHERE store_number = ?
              AND daily_sales = 0
              AND (total_vat > 0 OR total_non_vat > 0)
        """, (store_code,))
    else:
        cur.execute("""
            UPDATE entries
            SET daily_sales = total_vat + total_non_vat
            WHERE daily_sales = 0
              AND (total_vat > 0 OR total_non_vat > 0)
        """)
    updated += cur.rowcount

    # Step 2: Fix old_grand_total = 0 for entries where zread > 1
    # For each affected row, find the previous z-read (same store+register, lower zread number)
    # and use its new_grand_total as the old_grand_total.
    if store_code:
        cur.execute("""
            SELECT id, store_number, register_number, CAST(zread_number AS INTEGER) AS zread_int
            FROM entries
            WHERE store_number = ?
              AND old_grand_total = 0
              AND CAST(zread_number AS INTEGER) > 1
            ORDER BY store_number, register_number, zread_int
        """, (store_code,))
    else:
        cur.execute("""
            SELECT id, store_number, register_number, CAST(zread_number AS INTEGER) AS zread_int
            FROM entries
            WHERE old_grand_total = 0
              AND CAST(zread_number AS INTEGER) > 1
            ORDER BY store_number, register_number, zread_int
        """)

    affected = cur.fetchall()
    for row_id, store_num, reg_num, zread_int in affected:
        # Find the immediately preceding z-read for this store+register
        cur.execute("""
            SELECT new_grand_total
            FROM entries
            WHERE store_number = ?
              AND register_number = ?
              AND CAST(zread_number AS INTEGER) < ?
            ORDER BY CAST(zread_number AS INTEGER) DESC
            LIMIT 1
        """, (store_num, reg_num, zread_int))
        prev = cur.fetchone()
        if prev and prev[0] and prev[0] > 0:
            prev_new_gt = prev[0]
            # Also fix new_grand_total if it was correctly set; otherwise recompute
            cur.execute("""
                SELECT daily_sales, new_grand_total FROM entries WHERE id = ?
            """, (row_id,))
            entry = cur.fetchone()
            if entry:
                daily = entry[0]
                new_gt = entry[1] if entry[1] > 0 else prev_new_gt + daily
                cur.execute("""
                    UPDATE entries
                    SET old_grand_total = ?,
                        new_grand_total = ?
                    WHERE id = ?
                """, (prev_new_gt, new_gt, row_id))
                updated += cur.rowcount

    return updated

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"status": "error", "message": "Store code argument missing."}))
        sys.exit(1)

    store_arg = sys.argv[1]
    dsn_arg = sys.argv[2] if len(sys.argv) > 2 else "Salessum"
    db_arg = sys.argv[3] if len(sys.argv) > 3 else None

    res = import_tps(store_arg, dsn_arg, db_arg)
    print(json.dumps(res))
