import sqlite3

conn = sqlite3.connect(r'c:\xampp\htdocs\journal_app\journal.db')

# Check current AR entries
print("=== Current AR-related entries in tender_master ===")
rows = conn.execute("SELECT * FROM tender_master WHERE tender_code='AR' OR tender_name LIKE '%AR%' OR tender_name LIKE '%House Account%' OR tender_name LIKE '%House Charge%'").fetchall()
for r in rows:
    print(r)

# Check if 'AR' code exists
ar_exists = conn.execute("SELECT COUNT(*) FROM tender_master WHERE tender_code='AR'").fetchone()[0]
print(f"\nAR code exists: {ar_exists}")

if ar_exists == 0:
    # Insert AR into tender_master
    conn.execute("INSERT INTO tender_master (tender_code, tender_name) VALUES ('AR', 'AR')")
    conn.commit()
    print("Inserted AR into tender_master")
else:
    # Update existing to 'AR'
    conn.execute("UPDATE tender_master SET tender_name='AR' WHERE tender_code='AR'")
    conn.commit()
    print("Updated AR tender_name to 'AR'")

print("\n=== Updated AR entry ===")
rows = conn.execute("SELECT * FROM tender_master WHERE tender_code='AR'").fetchall()
for r in rows:
    print(r)

# Also check entries for any AR tenders
print("\n=== Entries with AR tender ===")
rows2 = conn.execute("SELECT id, store_number, entry_date, tender FROM entries WHERE tender LIKE '%AR%' LIMIT 10").fetchall()
for r in rows2:
    print(r)

conn.close()
print("\nDone.")
