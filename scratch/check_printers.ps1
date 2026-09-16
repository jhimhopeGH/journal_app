[System.Reflection.Assembly]::LoadWithPartialName('System.Drawing') | Out-Null
Write-Host "=== INSTALLED PRINTERS & RELEVANT PAPER SIZES ==="
foreach ($p in [System.Drawing.Printing.PrinterSettings]::InstalledPrinters) {
    $ps = New-Object System.Drawing.Printing.PrinterSettings
    $ps.PrinterName = $p
    Write-Host "`nPRINTER: $p"
    Write-Host "Default Paper: $($ps.DefaultPageSettings.PaperSize.PaperName) ($([math]::Round($ps.DefaultPageSettings.PaperSize.Width*0.254, 1))mm x $([math]::Round($ps.DefaultPageSettings.PaperSize.Height*0.254, 1))mm)"
    Write-Host "Available Forms:"
    foreach ($paper in $ps.PaperSizes) {
        $wMM = [math]::Round($paper.Width * 0.254, 1)
        $hMM = [math]::Round($paper.Height * 0.254, 1)
        Write-Host "  - Name: '$($paper.PaperName)' | Width: $wMM mm ($($paper.Width/100) in) | Height: $hMM mm ($($paper.Height/100) in) | Kind: $($paper.Kind)"
    }
}

Write-Host "`n=== SYSTEM PRINT SERVER FORMS IN REGISTRY ==="
Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Control\Print\Forms\*' -ErrorAction SilentlyContinue | ForEach-Object {
    $w = [math]::Round($_.cx / 1000, 1)
    $h = [math]::Round($_.cy / 1000, 1)
    Write-Host "Registry Form: '$($_.PSChildName)' -> ${w}mm x ${h}mm"
}
