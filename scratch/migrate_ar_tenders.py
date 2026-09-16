import sqlite3, json

conn = sqlite3.connect(r'c:\xampp\htdocs\journal_app\journal.db')

# Get all entries with AR variants
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
                'HOUSE ACCOUNT CHARGE' in upper or
                'HOUSE CHARGE' in upper or
                'ACCOUNTS RECEIVABLE' in upper or
                'ACCOUNT RECEIVABLE' in upper):
                if t['name'] != 'AR':
                    t['name'] = 'AR'
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
print(f"Done. Updated: {updated}, Errors: {errors}")
