import pyodbc
import os

# Check Salessum DSN registry path
import winreg

def get_dsn_dbq(dsn_name):
    for hive in [winreg.HKEY_CURRENT_USER, winreg.HKEY_LOCAL_MACHINE]:
        for subkey in [
            f"SOFTWARE\\ODBC\\ODBC.INI\\{dsn_name}",
            f"SOFTWARE\\WOW6432Node\\ODBC\\ODBC.INI\\{dsn_name}"
        ]:
            try:
                key = winreg.OpenKey(hive, subkey)
                val, _ = winreg.QueryValueEx(key, "DBQ")
                winreg.CloseKey(key)
                return val
            except:
                continue
    return None

for dsn in ["Salessum", "Windss", "Windss_New"]:
    path = get_dsn_dbq(dsn)
    print(f"DSN '{dsn}' -> DBQ: {path}")
    if path:
        # Check if REGSET.TPS exists in that folder
        tps_path = os.path.join(path, "REGSET.TPS")
        print(f"  REGSET.TPS exists: {os.path.exists(tps_path)} ({tps_path})")

# Also enumerate all .TPS files in D:\Salesumm
print("\nFiles in D:\\Salesumm matching REG*:")
salesumm = "D:\\Salesumm"
if os.path.exists(salesumm):
    for f in os.listdir(salesumm):
        if f.upper().startswith("REG"):
            print(f"  {f}")
