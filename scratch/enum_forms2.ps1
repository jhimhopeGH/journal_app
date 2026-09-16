Add-Type -TypeDefinition @"
using System;
using System.Text;
using System.Runtime.InteropServices;

public class WinSpoolHelper {
    [DllImport("winspool.drv", CharSet=CharSet.Auto, SetLastError=true)]
    public static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", EntryPoint="EnumFormsW", SetLastError=true)]
    public static extern bool EnumForms(IntPtr hPrinter, uint Level, byte[] pForm, uint cbBuf, ref uint pcbNeeded, ref uint pcReturned);
}
"@ -ErrorAction SilentlyContinue

function Get-PrintServerForms {
    $hPrinter = [IntPtr]::Zero
    $null = [WinSpoolHelper]::OpenPrinter([NullString]::Value, [ref]$hPrinter, [IntPtr]::Zero)
    
    $needed = [uint32]0
    $returned = [uint32]0
    $null = [WinSpoolHelper]::EnumForms($hPrinter, 1, $null, 0, [ref]$needed, [ref]$returned)
    
    if ($needed -gt 0) {
        $buf = New-Object byte[] $needed
        $ok = [WinSpoolHelper]::EnumForms($hPrinter, 1, $buf, $needed, [ref]$needed, [ref]$returned)
        
        if ($ok) {
            $ptrBuf = [System.Runtime.InteropServices.GCHandle]::Alloc($buf, 'Pinned')
            try {
                $base = $ptrBuf.AddrOfPinnedObject().ToInt64()
                # FORM_INFO_1: Flags(4) + pName ptr(8) + cx(4) + cy(4) + left(4)+top(4)+right(4)+bottom(4) = 36 bytes on 64-bit
                # pName is a pointer (8 bytes on 64-bit)
                $structSize = 40  # approximate, depends on alignment
                
                for ($i = 0; $i -lt $returned; $i++) {
                    $offset = $base + ($i * $structSize)
                    $flags = [System.BitConverter]::ToInt32($buf, $i * $structSize)
                    $namePtr = [System.Runtime.InteropServices.Marshal]::ReadIntPtr([IntPtr]($base + $i * $structSize + 4))
                    $name = [System.Runtime.InteropServices.Marshal]::PtrToStringUni($namePtr)
                    $cx = [System.BitConverter]::ToInt32($buf, $i * $structSize + 12)
                    $cy = [System.BitConverter]::ToInt32($buf, $i * $structSize + 16)
                    
                    [PSCustomObject]@{
                        Name = $name
                        Width_mm = [math]::Round($cx / 1000.0, 2)
                        Height_mm = [math]::Round($cy / 1000.0, 2)
                        Width_in = [math]::Round($cx / 25400.0, 3)
                        Height_in = [math]::Round($cy / 25400.0, 3)
                        cx_raw = $cx
                        cy_raw = $cy
                    }
                }
            } finally {
                $ptrBuf.Free()
            }
        }
    }
    [WinSpoolHelper]::ClosePrinter($hPrinter)
}

$forms = Get-PrintServerForms
Write-Host "=== ALL THERMAL / RECEIPT RELATED FORMS ==="
$forms | Where-Object { $_.Name -match 'Reciept|Receipt|Thermal|80|Roll|Custom' } | Format-Table -AutoSize

Write-Host "`n=== 'Reciept Paper' FORM DETAILS ==="
$forms | Where-Object { $_.Name -eq 'Reciept Paper' } | Format-List

Write-Host "`n=== 'Receipt Roll' FORM DETAILS ==="
$forms | Where-Object { $_.Name -eq 'Receipt Roll' } | Format-List

Write-Host "`n=== 'Thermal Paper 80cm' FORM DETAILS ==="
$forms | Where-Object { $_.Name -eq 'Thermal Paper 80cm' } | Format-List
