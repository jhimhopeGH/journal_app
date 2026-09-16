import sqlite3
import json

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

def main():
    db_path = 'journal.db'
    conn = sqlite3.connect(db_path)
    cur = conn.cursor()

    cur.execute("SELECT id, tender FROM entries WHERE tender != '' AND tender IS NOT NULL")
    rows = cur.fetchall()
    print(f"Total entries to inspect: {len(rows)}")

    updated_count = 0
    batch = []

    for r_id, raw_tender in rows:
        try:
            data = json.loads(raw_tender)
            if not isinstance(data, list):
                continue
            normalized = normalize_and_sort_tenders(data)
            new_json = json.dumps(normalized)

            if new_json != raw_tender:
                # Sanity check: sums match
                orig_sum = round(sum(float(str(x.get('amount', 0)).replace(',', '') or 0) for x in data if isinstance(x, dict)), 2)
                norm_sum = round(sum(x['amount'] for x in normalized), 2)
                if abs(orig_sum - norm_sum) > 0.05:
                    print(f"WARNING: Sum mismatch on ID {r_id}: {orig_sum} vs {norm_sum}")
                    continue

                batch.append((new_json, r_id))
                updated_count += 1

                if len(batch) >= 1000:
                    cur.executemany("UPDATE entries SET tender = ? WHERE id = ?", batch)
                    conn.commit()
                    batch = []
        except Exception as e:
            print(f"Error on ID {r_id}: {e}")

    if batch:
        cur.executemany("UPDATE entries SET tender = ? WHERE id = ?", batch)
        conn.commit()

    print(f"Migration finished. Total records updated: {updated_count}")
    conn.close()

if __name__ == '__main__':
    main()
