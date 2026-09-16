@echo off
:: client_install_printer.bat  
:: ==========================================
:: Run this on each CASHIER WORKSTATION (no admin rights needed)
:: to connect to the server's Generic/Text Only shared printer.
::
:: Double-click to run, or place on the Desktop for cashiers.
:: ==========================================

setlocal enabledelayedexpansion

:: Try hostname first, then fallback to IP
set SERVER_HOST=NHQPHLICT37
set SERVER_IP=10.33.55.52
set SHARE_NAME=GnrcText

echo.
echo ============================================================
echo   Installing Generic/Text Printer from Server
echo ============================================================
echo.

:: Test network connectivity
ping -n 1 -w 1000 %SERVER_HOST% >nul 2>&1
if %errorlevel% == 0 (
    set PRINTER_PATH=\\%SERVER_HOST%\%SHARE_NAME%
    echo [OK] Server found by hostname: %SERVER_HOST%
) else (
    ping -n 1 -w 1000 %SERVER_IP% >nul 2>&1
    if %errorlevel% == 0 (
        set PRINTER_PATH=\\%SERVER_IP%\%SHARE_NAME%
        echo [OK] Server found by IP: %SERVER_IP%
    ) else (
        echo [ERROR] Cannot reach server. Check LAN connection.
        pause
        exit /b 1
    )
)

echo Connecting to printer: !PRINTER_PATH! ...
echo.

:: Primary method: add network printer connection
rundll32 printui.dll,PrintUIEntry /in /n "!PRINTER_PATH!"
if %errorlevel% == 0 (
    echo [OK] Printer connected successfully!
    goto :done
)

:: Fallback method
net use "!PRINTER_PATH!" >nul 2>&1
cscript //NoLogo "%SystemRoot%\System32\Printing_Admin_Scripts\en-US\prnmngr.vbs" -a -p "!PRINTER_PATH!" -m "Generic / Text Only"
if %errorlevel% == 0 (
    echo [OK] Printer connected via fallback method.
    goto :done
)

echo.
echo [WARN] Automatic connection may have partially worked.
echo If printer does not appear, connect manually:
echo   1. Open Settings ^> Printers ^& scanners
echo   2. Click "Add a printer or scanner"
echo   3. Click "The printer that I want isn't listed"
echo   4. Select "Select a shared printer by name"  
echo   5. Type: !PRINTER_PATH!
echo   6. Click Next, then Finish

:done
echo.
echo ============================================================
echo   DONE! How to print receipts with Generic/Text font:
echo.
echo   1. Open Receipt Reprint in Chrome or Edge
echo   2. Click [Browser Print] or press Ctrl+P
echo   3. In the print dialog, change the printer to:
echo         "!PRINTER_PATH!"
echo      (look for "GnrcText" or "%SERVER_HOST%" in the list)
echo   4. Set Paper Size: "Reciept Paper"  (or Custom 80mm x Auto)
echo   5. Set Margins: None
echo   6. Click Print
echo.
echo   The receipt will use the same hardware ROM font as
echo   the server's Generic/Text Only direct print.
echo ============================================================
echo.
pause
