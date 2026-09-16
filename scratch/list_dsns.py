import pyodbc

print("=== AVAILABLE ODBC SOURCES (32-bit) ===")
sources = pyodbc.dataSources()
for name, driver in sources.items():
    print(f"DSN: '{name}' -> Driver: '{driver}'")

print("\n=== AVAILABLE ODBC DRIVERS (32-bit) ===")
drivers = pyodbc.drivers()
for d in drivers:
    print(f"Driver: '{d}'")
