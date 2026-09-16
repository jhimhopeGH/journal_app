import sqlite3

conn = sqlite3.connect(r'c:\xampp\htdocs\journal_app\journal.db')
print("=== tender_master ===")
rows = conn.execute("SELECT * FROM tender_master").fetchall()
for r in rows:
    print(r)

print("\n=== Sample tenders from entries ===")
rows2 = conn.execute("SELECT tender FROM entries LIMIT 10").fetchall()
for r in rows2:
    print(r)

conn.close()
