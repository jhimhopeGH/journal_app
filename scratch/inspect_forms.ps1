Write-Host "=== PRINTERS IN DETAIL ==="
Get-Printer | Select-Object Name, DriverName, PortName, PrintProcessor | Format-Table -AutoSize

Write-Host "`n=== SPECIFIC CUSTOM FORMS IN REGISTRY (Print Server Forms) ==="
Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Control\Print\Forms\*' | Where-Object { 
    $_.PSChildName -match 'Reciept|Receipt|Thermal|80' 
} | ForEach-Object {
    $name = $_.PSChildName
    # cx and cy in registry are in 1/1000 millimeter units (microns)
    $w_mm = $_.cx / 1000
    $h_mm = $_.cy / 1000
    $w_in = [math]::Round($w_mm / 25.4, 2)
    $h_in = [math]::Round($h_mm / 25.4, 2)
    Write-Host "Form Name : '$name'"
    Write-Host "  Raw cx  : $($_.cx) ($w_mm mm / $w_in in)"
    Write-Host "  Raw cy  : $($_.cy) ($h_mm mm / $h_in in)"
    Write-Host ""
}

Write-Host "=== GENERIC / TEXT ONLY PRINTER CONFIG & PROPERTIES ==="
Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Control\Print\Printers\*' | ForEach-Object {
    $pname = $_.PSChildName
    $driver = $_.PrinterDriver
    Write-Host "Printer: $pname (Driver: $driver)"
}
