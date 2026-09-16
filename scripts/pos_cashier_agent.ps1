# pos_cashier_agent.ps1 - Local Direct ESC/POS Print Agent for Cashier PCs
# Allows cashier web browser to print pure raw ESC/POS bytes directly to local USB/COM receipt printer without image conversion.

[Console]::Title = "Cashier Direct Thermal Print Agent"
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  CASHIER DIRECT THERMAL POS PRINT AGENT (RAW ESC/POS)" -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan

# Add native Windows Spooler Raw Printer API
Add-Type -TypeDefinition @"
using System;
using System.IO;
using System.Runtime.InteropServices;

public class RawPrinterHelper {
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Ansi)]
    public class DOCINFOA {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }
    [DllImport("winspool.Drv", EntryPoint="OpenPrinterA", SetLastError=true, CharSet=CharSet.Ansi, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr)] string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint="ClosePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint="StartDocPrinterA", SetLastError=true, CharSet=CharSet.Ansi, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint="EndDocPrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint="StartPagePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint="EndPagePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint="WritePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static bool SendBytesToPrinter(string szPrinterName, byte[] bytes) {
        if (bytes == null || bytes.Length == 0) return false;
        IntPtr hPrinter = new IntPtr(0);
        DOCINFOA di = new DOCINFOA();
        bool bSuccess = false;
        di.pDocName = "Cashier Receipt Direct RAW";
        di.pDataType = "RAW";

        if (OpenPrinter(szPrinterName.Normalize(), out hPrinter, IntPtr.Zero)) {
            if (StartDocPrinter(hPrinter, 1, di)) {
                if (StartPagePrinter(hPrinter)) {
                    IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
                    Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);
                    int dwWritten = 0;
                    bSuccess = WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out dwWritten);
                    Marshal.FreeCoTaskMem(pUnmanagedBytes);
                    EndPagePrinter(hPrinter);
                }
                EndDocPrinter(hPrinter);
            }
            ClosePrinter(hPrinter);
        }
        return bSuccess;
    }
}
"@

function Get-LocalThermalPrinters {
    $printers = @()
    try {
        $printers = Get-Printer | Select-Object -ExpandProperty Name
    } catch {
        $printers = Get-WmiObject Win32_Printer | Select-Object -ExpandProperty Name
    }
    return $printers
}

function Get-BestDefaultPrinter($printers) {
    $priority = @('EPSON TM-T82 Receipt', 'EPSON TM-T82', 'Generic / Text Only', 'POS-80', 'POS-58', 'Thermal')
    foreach ($p in $priority) {
        foreach ($inst in $printers) {
            if ($inst -like "*$p*") { return $inst }
        }
    }
    if ($printers.Count -gt 0) { return $printers[0] }
    return "EPSON TM-T82 Receipt"
}

$localPrinters = Get-LocalThermalPrinters
$defaultThermal = Get-BestDefaultPrinter $localPrinters

Write-Host "  Default Detected Printer : $defaultThermal" -ForegroundColor Green
Write-Host "  Available Local Printers : $($localPrinters -join ', ')" -ForegroundColor Gray

# Start HTTP Listener on localhost
$port = 12111
$listener = New-Object System.Net.HttpListener
$prefix = "http://127.0.0.1:$port/"
$listener.Prefixes.Add($prefix)

try {
    $listener.Start()
} catch {
    Write-Host "[ERROR] Could not bind to $prefix. Is another instance already running?" -ForegroundColor Red
    pause
    exit 1
}

Write-Host ""
Write-Host "  [READY] Listening for print requests from browser on $prefix" -ForegroundColor Cyan
Write-Host "  You can minimize this window. Do NOT close it while cashiering." -ForegroundColor Yellow
Write-Host "------------------------------------------------------------" -ForegroundColor DarkGray

while ($listener.IsListening) {
    try {
        $context = $listener.GetContext()
        $request = $context.Request
        $response = $context.Response

        # CORS Headers for Web App access
        $response.AddHeader("Access-Control-Allow-Origin", "*")
        $response.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $response.AddHeader("Access-Control-Allow-Headers", "Content-Type, X-Requested-With")

        if ($request.HttpMethod -eq "OPTIONS") {
            $response.StatusCode = 200
            $response.Close()
            continue
        }

        if ($request.HttpMethod -eq "GET") {
            # Status check
            $resObj = @{
                status   = "ready"
                version  = "1.0"
                default  = $defaultThermal
                printers = (Get-LocalThermalPrinters)
            }
            $jsonStr = $resObj | ConvertTo-Json -Compress
            $buffer = [System.Text.Encoding]::UTF8.GetBytes($jsonStr)
            $response.ContentType = "application/json; charset=utf-8"
            $response.ContentLength64 = $buffer.Length
            $response.OutputStream.Write($buffer, 0, $buffer.Length)
            $response.Close()
            continue
        }

        if ($request.HttpMethod -eq "POST") {
            $reader = New-Object System.IO.StreamReader($request.InputStream, $request.ContentEncoding)
            $body = $reader.ReadToEnd()
            $reader.Close()

            $job = $body | ConvertFrom-Json
            $b64 = $job.base64
            $targetPrinter = if (![string]::IsNullOrWhiteSpace($job.printer)) { $job.printer } else { $defaultThermal }

            if ([string]::IsNullOrWhiteSpace($b64)) {
                $errObj = @{ success = $false; error = "Empty payload base64" }
                $buffer = [System.Text.Encoding]::UTF8.GetBytes(($errObj | ConvertTo-Json))
                $response.StatusCode = 400
                $response.ContentType = "application/json"
                $response.OutputStream.Write($buffer, 0, $buffer.Length)
                $response.Close()
                continue
            }

            $rawBytes = [System.Convert]::FromBase64String($b64)
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Printing $($rawBytes.Length) bytes directly to [$targetPrinter]... " -NoNewline

            $ok = [RawPrinterHelper]::SendBytesToPrinter($targetPrinter, $rawBytes)

            if ($ok) {
                Write-Host "SUCCESS ✓ (Hardware ROM Font)" -ForegroundColor Green
                $resObj = @{
                    success = $true
                    printer = $targetPrinter
                    bytes   = $rawBytes.Length
                    message = "Printed directly to $targetPrinter with Generic/Text hardware font!"
                }
            } else {
                Write-Host "FAILED ✗" -ForegroundColor Red
                $resObj = @{
                    success = $false
                    printer = $targetPrinter
                    error   = "Failed to send raw bytes to printer [$targetPrinter]. Check if printer is on and connected."
                }
            }

            $jsonStr = $resObj | ConvertTo-Json -Compress
            $buffer = [System.Text.Encoding]::UTF8.GetBytes($jsonStr)
            $response.ContentType = "application/json; charset=utf-8"
            $response.ContentLength64 = $buffer.Length
            $response.OutputStream.Write($buffer, 0, $buffer.Length)
            $response.Close()
        }
    } catch {
        Write-Host "`n[ERROR] Request handler exception: $_" -ForegroundColor Red
    }
}
