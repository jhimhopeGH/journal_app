$source = @"
using System;
using System.Runtime.InteropServices;

public class WinSpool {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Auto)]
    public struct FORM_INFO_1 {
        public uint Flags;
        public string pName;
        public SIZEL Size;
        public RECTL ImageableArea;
    }

    [StructLayout(LayoutKind.Sequential)]
    public struct SIZEL {
        public int cx;
        public int cy;
    }

    [StructLayout(LayoutKind.Sequential)]
    public struct RECTL {
        public int left;
        public int top;
        public int right;
        public int bottom;
    }

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool EnumForms(IntPtr hPrinter, uint Level, IntPtr pForm, uint cbBuf, out uint pcbNeeded, out uint pcReturned);
}
"@

Add-Type -TypeDefinition $source

$hPrinter = [IntPtr]::Zero
if ([WinSpool]::OpenPrinter($null, [ref]$hPrinter, [IntPtr]::Zero)) {
    $needed = 0
    $returned = 0
    [WinSpool]::EnumForms($hPrinter, 1, [IntPtr]::Zero, 0, [ref]$needed, [ref]$returned)
    if ($needed -gt 0) {
        $pBuf = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($needed)
        if ([WinSpool]::EnumForms($hPrinter, 1, $pBuf, $needed, [ref]$needed, [ref]$returned)) {
            $structSize = [System.Runtime.InteropServices.Marshal]::SizeOf([type][WinSpool+FORM_INFO_1])
            Write-Host "Total Server Forms: $returned"
            for ($i = 0; $i -lt $returned; $i++) {
                $ptr = [IntPtr]([long]$pBuf + ($i * $structSize))
                $form = [System.Runtime.InteropServices.Marshal]::PtrToStructure($ptr, [type][WinSpool+FORM_INFO_1])
                if ($form.pName -match 'Reciept|Receipt|Thermal|80|Roll') {
                    $w_mm = $form.Size.cx / 1000.0
                    $h_mm = $form.Size.cy / 1000.0
                    $w_in = [math]::Round($w_mm / 25.4, 2)
                    $h_in = [math]::Round($h_mm / 25.4, 2)
                    Write-Host "Form Name: '$($form.pName)'"
                    Write-Host "  Width : $w_mm mm ($w_in in)"
                    Write-Host "  Height: $h_mm mm ($h_in in)"
                    Write-Host "  Imageable Area: Left=$($form.ImageableArea.left/1000)mm, Top=$($form.ImageableArea.top/1000)mm, Right=$($form.ImageableArea.right/1000)mm, Bottom=$($form.ImageableArea.bottom/1000)mm"
                    Write-Host ""
                }
            }
        }
        [System.Runtime.InteropServices.Marshal]::FreeHGlobal($pBuf)
    }
    [WinSpool]::ClosePrinter($hPrinter)
}
