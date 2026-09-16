import sqlite3, json

conn = sqlite3.connect(r'c:\xampp\htdocs\journal_app\journal.db')

print("=== Entries with actual AR tender name ===")
rows = conn.execute("SELECT id, store_number, entry_date, tender FROM entries").fetchall()
found = 0
for r in rows:
    try:
        tenders = json.loads(r[3]) if r[3] else []
        for t in tenders:
            name = (t.get('name') or '').upper()
            if name == 'AR' or 'HOUSE ACCOUNT CHARGE' in name or 'HOUSE CHARGE' in name or 'A/R' in name:
                print(f"  Entry {r[0]} ({r[1]}, {r[2]}): {t}")
                found += 1
                break
    except:
        pass

print(f"\nTotal found: {found}")
conn.close()
