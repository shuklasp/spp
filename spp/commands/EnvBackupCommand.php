<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EnvBackupCommand extends Command
{
    protected string $name = 'env:backup';
    protected string $description = 'Backup all environment configurations';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $backupDir = SPP_BASE_DIR . '/var/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $timestamp = date('Ymd_His');
        $zipFile = $backupDir . "/env_backup_{$timestamp}.zip";
        
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            echo "Error: Cannot create zip file at {$zipFile}\n";
            return;
        }

        $filesToBackup = [];
        
        // 1. Global config
        $globalEtc = SPP_BASE_DIR . '/etc';
        if (is_dir($globalEtc)) {
            $files = glob($globalEtc . '/*.{yml,yaml,json}', GLOB_BRACE);
            foreach ($files as $file) {
                $filesToBackup[] = [
                    'source' => $file,
                    'dest' => 'global_etc/' . basename($file)
                ];
            }
        }

        // 2. Apps config
        $appsDir = SPP_APP_DIR;
        if (is_dir($appsDir)) {
            $dirs = array_filter(glob($appsDir . '/*'), 'is_dir');
            foreach ($dirs as $appDir) {
                $appName = basename($appDir);
                $appEtc = $appDir . '/etc';
                if (is_dir($appEtc)) {
                    $files = glob($appEtc . '/*.{yml,yaml,json}', GLOB_BRACE);
                    foreach ($files as $file) {
                        $filesToBackup[] = [
                            'source' => $file,
                            'dest' => "apps/{$appName}/etc/" . basename($file)
                        ];
                    }
                }
            }
        }

        $count = 0;
        foreach ($filesToBackup as $f) {
            $zip->addFile($f['source'], $f['dest']);
            $count++;
        }

        $zip->close();
        
        echo "Environment backup created successfully.\n";
        echo "Backed up {$count} files to: {$zipFile}\n";
    }
}
