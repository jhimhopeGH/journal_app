<?php
// backup_download.php - Generate and download/save a complete ZIP backup of the Journal App
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ej_db.php';


$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
}

$timestamp = date('Y-m-d_His');
$zipFileName = "journal_app_backup_{$timestamp}.zip";
$zipFilePath = $backupDir . '/' . $zipFileName;

// Check if ZipArchive extension is available
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $sourcePath = realpath(__DIR__);
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourcePath) + 1);
                
                // Exclude the backups folder itself and git folder to save space
                if (strpos($relativePath, 'backups') === 0 || strpos($relativePath, '.git') === 0) {
                    continue;
                }

                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
    }
}

// Action: Download or Save
$action = $_GET['action'] ?? 'download';

if ($action === 'download' && file_exists($zipFilePath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zipFilePath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($zipFilePath));
    readfile($zipFilePath);
    exit;
} else {
    // Redirect back with notification
    header('Location: admin.php?backup_created=' . urlencode($zipFileName));
    exit;
}
