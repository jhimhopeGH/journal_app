@echo off
:: share_printer_server.bat
:: ==========================================
:: Run this AS ADMINISTRATOR on the SERVER (NHQPHLICT37) 
:: to share the Generic/Text Only printer over the LAN.
:: 
:: Right-click this file -> "Run as administrator"
:: ==========================================

setlocal enabledelayedexpansion

set PRINTER_NAME=Generic / Text Only
set SHARE_NAME=GnrcText
set SERVER=NHQPHLICT37

echo.
echo ============================================================
echo   STEP 1: Sharing "%PRINTER_NAME%" as \\%SERVER%\%SHARE_NAME%
echo ============================================================
echo.

:: Enable File and Printer Sharing in Firewall
netsh advfirewall firewall set rule group="File and Printer Sharing" new enable=Yes >nul 2>&1
if %errorlevel% == 0 (
    echo [OK] Firewall: File and Printer Sharing enabled.
)

:: Method 1: PowerShell Set-Printer
echo [1/4] Attempting share via PowerShell Set-Printer...
powershell -Command "Set-Printer -Name 'Generic / Text Only' -Shared $true -ShareName 'GnrcText' -ErrorAction Stop" >nul 2>&1
if %errorlevel% == 0 goto :check_share

:: Method 2: PrintUIEntry with valid syntax (attributes +shared sharename "GnrcText")
echo [2/4] Attempting share via PrintUIEntry...
rundll32 printui.dll,PrintUIEntry /Xs /n "Generic / Text Only" sharename "GnrcText" attributes +shared >nul 2>&1
if %errorlevel% == 0 goto :check_share

:: Method 3: prncnfg.vbs with valid flags (-h for share name)
echo [3/4] Attempting share via prncnfg.vbs...
cscript //NoLogo "%SystemRoot%\System32\Printing_Admin_Scripts\en-US\prncnfg.vbs" -t -p "Generic / Text Only" +shared -h "GnrcText" >nul 2>&1
if %errorlevel% == 0 goto :check_share

:: Method 4: Direct Registry + Spooler Restart
echo [4/4] Attempting share via Registry...
reg add "HKLM\SYSTEM\CurrentControlSet\Control\Print\Printers\Generic / Text Only" /v "Share Name" /t REG_SZ /d "GnrcText" /f >nul 2>&1
reg add "HKLM\SYSTEM\CurrentControlSet\Control\Print\Printers\Generic / Text Only" /v "Attributes" /t REG_DWORD /d 584 /f >nul 2>&1
net stop spooler >nul 2>&1
net start spooler >nul 2>&1

:check_share
:: Verify if shared
for /f "tokens=3" %%A in ('reg query "HKLM\SYSTEM\CurrentControlSet\Control\Print\Printers\Generic / Text Only" /v "Share Name" 2^>nul') do (
    if not "%%A"=="" (
        set SHARE_NAME=%%A
        goto :success
    )
)

echo.
echo ======================================================
echo  Automatic sharing failed or required manual confirmation.
echo  Please share MANUALLY via Windows (takes 10 seconds):
echo.
echo  1. Press Win+R, type: control printers, press Enter
echo  2. Right-click "Generic / Text Only"
echo  3. Click "Printer properties" (not Printing Preferences)
echo  4. Click the "Sharing" tab
echo  5. Check "Share this printer"
echo  6. Set Share name to: GnrcText
echo  7. Click Apply, then OK
echo ======================================================
echo.
pause
exit /b 1

:success
echo.
echo ============================================================
echo   SUCCESS! Printer is now shared.
echo.
echo   Network path: \\%SERVER%\%SHARE_NAME%
echo   Backup IP path: \\10.33.55.52\%SHARE_NAME%
echo.
echo   NEXT STEP:
echo   On each cashier workstation, run:
echo     client_install_printer.bat
echo   (found in the same scripts folder)
echo ============================================================
echo.
pause
