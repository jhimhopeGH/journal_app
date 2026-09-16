import sqlite3, json

conn = sqlite3.connect(r'c:\xampp\htdocs\journal_app\journal.db')

CANONICAL_NAME = 'House Charge'

# 1. Update tender_master
conn.execute("UPDATE tender_master SET tender_name=? WHERE tender_code='AR'", [CANONICAL_NAME])
print(f"Updated tender_master AR -> '{CANONICAL_NAME}'")

# 2. Migrate all entries: rename 'AR' (and any remaining variants) to 'House Charge'
rows = conn.execute("SELECT id, tender FROM entries").fetchall()
updated = 0
errors = 0

for r in rows:
    try:
        if not r[1]:
            continue
        tenders = json.loads(r[1])
        if not isinstance(tenders, list):
            continue

        changed = False
        for t in tenders:
            name = (t.get('name') or '').strip()
            upper = name.upper()
            if (upper == 'AR' or upper == 'A/R' or
                upper == 'HOUSE ACCOUNT CHARGE' or
                upper == 'HOUSE CHARGE' or
                'HOUSE ACCOUNT CHARGE' in upper or
                'ACCOUNTS RECEIVABLE' in upper or
                'ACCOUNT RECEIVABLE' in upper):
                if t['name'] != CANONICAL_NAME:
                    t['name'] = CANONICAL_NAME
                    changed = True

        if changed:
            conn.execute("UPDATE entries SET tender=? WHERE id=?",
                         [json.dumps(tenders), r[0]])
            updated += 1

    except Exception as e:
        errors += 1
        print(f"Error on entry {r[0]}: {e}")

conn.commit()
conn.close()
print(f"Done. Entries updated: {updated}, Errors: {errors}")
