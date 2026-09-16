import datetime

# Clarion standard date format: number of days since December 28, 1800 (or Dec 30, 1899 in some versions)
# Clarion standard: Day 1 = January 1, 1801 (or Dec 28, 1800 base)
# Let's test Clarion date 80327:
base_date = datetime.date(1800, 12, 28)
clarion_val = 80327
converted = base_date + datetime.timedelta(days=clarion_val)
print(f"Clarion date {clarion_val} -> {converted.strftime('%Y-%m-%d')}")
