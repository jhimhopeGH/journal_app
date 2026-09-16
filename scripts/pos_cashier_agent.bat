<# :
@echo off
:: ============================================================================
:: CASHIER DIRECT HARDWARE POS PRINT AGENT
:: 100% Standalone - No installation or external dependencies required
:: Double-click to run on Cashier PC. Keep minimized in background.
:: ============================================================================
title Cashier POS Direct Hardware Print Agent
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Invoke-Expression ([System.IO.File]::ReadAllText('%~f0'))"
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Agent stopped or port 12111 is already in use.
    pause
)
exit /b
#>

[Console]::Title = "Cashier POS Direct Print Agent (Port 12111)"
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  CASHIER DIRECT HARDWARE POS PRINT AGENT (RAW ESC/POS)" -ForegroundColor Yellow
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Transmits pure raw ESC/POS directly to local thermal printer" -ForegroundColor Gray
Write-Host "  Bypasses Windows graphics engine - 100% Generic/Text ROM font" -ForegroundColor Gray
Write-Host ""

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
        di.pDocName = "Cashier Direct ESC/POS RAW";
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
    $priority = @('EPSON TM-T82 Receipt', 'EPSON TM-T82', 'EPSON', 'Generic / Text Only', 'POS-80', 'POS-58', 'Thermal')
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

Write-Host "  Detected Local Printer   : $defaultThermal" -ForegroundColor Green
Write-Host "  Installed Printers       : $($localPrinters -join ', ')" -ForegroundColor Gray

$port = 12111
$listener = New-Object System.Net.HttpListener
$prefix = "http://127.0.0.1:$port/"
$listener.Prefixes.Add($prefix)

try {
    $listener.Start()
} catch {
    Write-Host ""
    Write-Host "[ERROR] Could not bind to $prefix" -ForegroundColor Red
    Write-Host "Another instance may already be running. Check your taskbar." -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host ""
Write-Host "  [ACTIVE] Agent listening on $prefix" -ForegroundColor Cyan
Write-Host "  -> You can MINIMIZE this window." -ForegroundColor Yellow
Write-Host "  -> Do NOT close this window while operating POS." -ForegroundColor Yellow
Write-Host "------------------------------------------------------------" -ForegroundColor DarkGray

while ($listener.IsListening) {
    try {
        $context = $listener.GetContext()
        $request = $context.Request
        $response = $context.Response

        # CORS Headers for Web App
        $response.AddHeader("Access-Control-Allow-Origin", "*")
        $response.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $response.AddHeader("Access-Control-Allow-Headers", "Content-Type, X-Requested-With")

        if ($request.HttpMethod -eq "OPTIONS") {
            $response.StatusCode = 200
            $response.Close()
            continue
        }

        if ($request.HttpMethod -eq "GET") {
            $resObj = @{
                status   = "ready"
                version  = "2.0"
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
                $errObj = @{ success = $false; error = "Empty payload" }
                $buffer = [System.Text.Encoding]::UTF8.GetBytes(($errObj | ConvertTo-Json))
                $response.StatusCode = 400
                $response.ContentType = "application/json"
                $response.OutputStream.Write($buffer, 0, $buffer.Length)
                $response.Close()
                continue
            }

            $rawBytes = [System.Convert]::FromBase64String($b64)
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Raw print job ($($rawBytes.Length) bytes) -> [$targetPrinter]... " -NoNewline

            $ok = [RawPrinterHelper]::SendBytesToPrinter($targetPrinter, $rawBytes)

            if ($ok) {
                Write-Host "SUCCESS (Hardware Generic/Text ROM Font)" -ForegroundColor Green
                $resObj = @{
                    success = $true
                    printer = $targetPrinter
                    bytes   = $rawBytes.Length
                    message = "Printed directly to $targetPrinter with Generic/Text hardware font!"
                }
            } else {
                Write-Host "FAILED" -ForegroundColor Red
                $resObj = @{
                    success = $false
                    printer = $targetPrinter
                    error   = "Failed to write raw bytes to [$targetPrinter]. Verify printer is connected and turned on."
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
        Write-Host "`n[ERROR] Request error: $_" -ForegroundColor Red
    }
}
