import sqlite3
import json

conn = sqlite3.connect(r'c:\xampp\htdocs\journal_app\journal.db')
cur = conn.cursor()

cur.execute("""
    SELECT id, store_number, register_number, entry_date, zread_number, 
           old_grand_total, new_grand_total, daily_sales, total_vat, total_non_vat, tender 
    FROM entries 
    ORDER BY id ASC 
    LIMIT 3
""")

rows = cur.fetchall()
print("Sample Database Entries (first 3):")
for r in rows:
    print(f"\nID: {r[0]}")
    print(f"  Store Number:     {r[1]}")
    print(f"  Register Number:  {r[2]}")
    print(f"  Date:             {r[3]}")
    print(f"  Z-Read (from RC): {r[4]}")
    print(f"  Old Grand Total:  P{r[5]:,.2f} (from PREVGRANDTOTALX)")
    print(f"  New Grand Total:  P{r[6]:,.2f} (from CURRGRANDTOTALX)")
    print(f"  Daily Sales:      P{r[7]:,.2f} (from SALES)")
    print(f"  Total VAT:        P{r[8]:,.2f} (from VATSALESX)")
    print(f"  Total Non-VAT:    P{r[9]:,.2f} (from NONVATSALESX)")
    print(f"  Tender Breakdown: {r[10]}")

conn.close()
