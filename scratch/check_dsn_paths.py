import pyodbc
import os

# Check DSN paths to find where regset.tps lives
sources = pyodbc.dataSources()
for dsn_name, dsn_driver in sources.items():
    print(f"DSN: {dsn_name} -> Driver: {dsn_driver}")
