# share_generic_text_printer.ps1
# Run this ONCE on the SERVER as Administrator to share the Generic/Text Only printer.
# This lets client cashier PCs install it as a network printer and get the exact hardware ROM font.
#
# Usage (as Administrator on the server):
#   powershell -ExecutionPolicy Bypass -File share_generic_text_printer.ps1

param(
    [string]$PrinterName  = "Generic / Text Only",
    [string]$ShareName    = "GenericText"
)

$ErrorActionPreference = "Stop"

Write-Host "=== Generic/Text Only Printer Network Share Setup ===" -ForegroundColor Cyan
Write-Host ""

# 1. Check printer exists
$printerKey = "HKLM:\SYSTEM\CurrentControlSet\Control\Print\Printers\$PrinterName"
if (-not (Test-Path $printerKey)) {
    Write-Host "ERROR: Printer '$PrinterName' not found in registry." -ForegroundColor Red
    Write-Host "Available printers:" -ForegroundColor Yellow
    Get-ItemProperty "HKLM:\SYSTEM\CurrentControlSet\Control\Print\Printers\*" | Select-Object PSChildName
    exit 1
}

Write-Host "Found printer: $PrinterName" -ForegroundColor Green

# 2. Share the printer
try {
    Set-Printer -Name $PrinterName -Shared $true -ShareName $ShareName -ErrorAction Stop
    Write-Host "Shared via Set-Printer successfully." -ForegroundColor Green
} catch {
    $prnScript = "$env:SystemRoot\System32\Printing_Admin_Scripts\en-US\prncnfg.vbs"
    if (Test-Path $prnScript) {
        $result = cscript //NoLogo $prnScript -t -p "$PrinterName" -h "$ShareName" -s . +shared
        Write-Host $result -ForegroundColor Gray
    } else {
        # Fallback: direct registry share
        Write-Host "Using registry method to share printer..." -ForegroundColor Yellow
        Set-ItemProperty -Path $printerKey -Name "Share Name" -Value $ShareName
        # Set Shared attribute bit (0x08 = shared)
        $currentAttr = (Get-ItemProperty $printerKey -Name "Attributes").Attributes
        Set-ItemProperty -Path $printerKey -Name "Attributes" -Value ($currentAttr -bor 0x0008)
    }
}

# 3. Enable Windows File and Printer Sharing firewall rules
Write-Host "Enabling File and Printer Sharing firewall rules..." -ForegroundColor Yellow
netsh advfirewall firewall set rule group="File and Printer Sharing" new enable=Yes | Out-Null

# 4. Restart print spooler to apply changes
Write-Host "Restarting Print Spooler..." -ForegroundColor Yellow
Restart-Service -Name Spooler -Force
Start-Sleep -Seconds 2

# 5. Verify share is active
$regAttr = (Get-ItemProperty $printerKey -Name "Attributes" -ErrorAction SilentlyContinue).Attributes
$regShare = (Get-ItemProperty $printerKey -Name "Share Name" -ErrorAction SilentlyContinue)."Share Name"
if ($regShare -eq $ShareName) {
    Write-Host ""
    Write-Host "SUCCESS! Printer is now shared." -ForegroundColor Green
    Write-Host "  Share Name : \\$(hostname)\$ShareName" -ForegroundColor Cyan
    Write-Host "  IP Path    : \\$($(Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike '127.*'} | Select-Object -First 1 -ExpandProperty IPAddress))\$ShareName" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Clients can now install this printer using:" -ForegroundColor Yellow
    Write-Host "  client_install_printer.bat  (in the scripts/ folder)" -ForegroundColor Yellow
} else {
    Write-Host "WARNING: Share may not have applied. Please manually share via:" -ForegroundColor Red
    Write-Host "  Control Panel -> Devices and Printers -> Right-click 'Generic / Text Only' -> Printer Properties -> Sharing Tab -> Enable Sharing" -ForegroundColor Yellow
}
