<?php
// create_backup.php - Immediate backup runner
require_once __DIR__ . '/auth.php';
requireAdmin();
$sourcePath = realpath(__DIR__);
$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

$timestamp = date('Y-m-d_His');
$zipFileName = "journal_app_backup_{$timestamp}.zip";
$zipFilePath = $backupDir . '/' . $zipFileName;

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $fileCount = 0;
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourcePath) + 1);
                
                if (strpos($relativePath, 'backups') === 0 || strpos($relativePath, '.git') === 0) {
                    continue;
                }

                $zip->addFile($filePath, $relativePath);
                $fileCount++;
            }
        }
        $zip->close();
        echo "SUCCESS: Created backup archive with {$fileCount} files at:\n{$zipFilePath}\n";
    } else {
        echo "ERROR: Failed to create ZIP archive.\n";
    }
} else {
    // Copy to folder backup
    $folderBackup = $backupDir . "/journal_app_backup_{$timestamp}";
    mkdir($folderBackup, 0777, true);
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($files as $file) {
        $target = $folderBackup . '/' . substr($file->getRealPath(), strlen($sourcePath) + 1);
        if ($file->isDir()) {
            if (!is_dir($target)) mkdir($target, 0777, true);
        } else {
            $rel = substr($file->getRealPath(), strlen($sourcePath) + 1);
            if (strpos($rel, 'backups') === 0 || strpos($rel, '.git') === 0) continue;
            copy($file->getRealPath(), $target);
        }
    }
    echo "SUCCESS: Created folder backup at:\n{$folderBackup}\n";
}
