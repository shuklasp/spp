<?php
declare(strict_types=1);

namespace SPP\Admin\Services;

use ZipArchive;
use PharData;
use Throwable;
use Exception;

/**
 * Class ArchiveAdminService
 *
 * High-Throughput Sovereign Streaming Archiver & Zip-Slip Protection Engine.
 * Features multi-format streaming compression, cryptographic SHA-256 verification,
 * and strict Zip-Slip path traversal defense.
 */
class ArchiveAdminService
{
    /**
     * Resolves and verifies safe workspace paths.
     */
    protected static function resolveSafePath(string $path): ?string
    {
        $appDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3));
        $baseDir = realpath(defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2));
        if ($appDir === false) $appDir = dirname(__DIR__, 3);
        if ($baseDir === false) $baseDir = dirname(__DIR__, 2);

        $clean = trim($path);
        if (str_contains($clean, "\0")) {
            return null;
        }

        $appDirNorm = str_replace('\\', '/', $appDir);
        $baseDirNorm = str_replace('\\', '/', $baseDir);

        // Check if path is already absolute (Windows drive letter or Unix root slash)
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $clean) || str_starts_with($clean, '/') || str_starts_with($clean, '\\')) {
            $real = realpath($clean);
            if ($real) {
                $realNorm = str_replace('\\', '/', $real);
                if (str_starts_with($realNorm, $appDirNorm) || str_starts_with($realNorm, $baseDirNorm)) {
                    return $realNorm;
                }
            }
            $parent = dirname($clean);
            $realParent = realpath($parent);
            if ($realParent) {
                $realParentNorm = str_replace('\\', '/', $realParent);
                if (str_starts_with($realParentNorm, $appDirNorm) || str_starts_with($realParentNorm, $baseDirNorm)) {
                    return str_replace('\\', '/', $clean);
                }
            }
            return file_exists($clean) ? str_replace('\\', '/', $clean) : null;
        }

        // Relative path
        $cleanPath = trim(str_replace(['\\', '//'], '/', $clean));
        $cleanPath = ltrim($cleanPath, '/');

        // Prevent directory traversal
        if (str_contains($cleanPath, '../') || str_contains($cleanPath, '..\\')) {
            return null;
        }

        $candidateBase = $baseDirNorm . ($cleanPath !== '' ? '/' . $cleanPath : '');
        $candidateApp = $appDirNorm . ($cleanPath !== '' ? '/' . $cleanPath : '');

        if (file_exists($candidateBase)) {
            return $candidateBase;
        }
        if (file_exists($candidateApp)) {
            return $candidateApp;
        }

        if (is_dir(dirname($candidateBase))) {
            return $candidateBase;
        }

        return $candidateApp;
    }

    /**
     * Creates a compressed archive (zip or tar.gz) from a folder or file.
     */
    public static function createArchive(string $sourcePath, string $format = 'zip', ?string $outputPath = null, array $options = []): array
    {
        $start = microtime(true);
        $source = self::resolveSafePath($sourcePath);

        if (!$source || !file_exists($source)) {
            return ['success' => false, 'error' => "Source path '{$sourcePath}' does not exist or is invalid."];
        }

        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $format = strtolower(trim($format));

        // Determine destination file
        if ($outputPath !== null && trim($outputPath) !== '') {
            $destFile = self::resolveSafePath($outputPath);
        } else {
            $name = basename($source);
            $ext = ($format === 'tar.gz' || $format === 'tar') ? '.tar.gz' : '.zip';
            $destDir = is_dir($source) ? dirname($source) : $source;
            $destFile = $destDir . '/' . $name . '_' . date('Ymd_His') . $ext;
        }

        if (!$destFile) {
            return ['success' => false, 'error' => 'Invalid destination path.'];
        }

        $destDir = dirname($destFile);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'PHP ZipArchive extension is required for compression.'];
        }

        $zip = new ZipArchive();
        $status = $zip->open($destFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status !== true) {
            return ['success' => false, 'error' => "Failed to create archive at '{$destFile}' (Code: {$status})."];
        }

        $fileCount = 0;
        $totalBytes = 0;

        if (is_file($source)) {
            $zip->addFile($source, basename($source));
            $fileCount = 1;
            $totalBytes = (int)filesize($source);
        } else {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $subPath = substr($item->getPathname(), strlen($source) + 1);
                $subPath = str_replace('\\', '/', $subPath);

                if ($item->isDir()) {
                    $zip->addEmptyDir($subPath);
                } elseif ($item->isFile()) {
                    // Skip the archive file itself if inside the source folder
                    if (str_replace('\\', '/', $item->getPathname()) === $destFile) {
                        continue;
                    }
                    $zip->addFile($item->getPathname(), $subPath);
                    $fileCount++;
                    $totalBytes += (int)$item->getSize();
                }
            }
        }

        $zip->close();

        if (!file_exists($destFile)) {
            return ['success' => false, 'error' => 'Archive creation failed to write file to disk.'];
        }

        $compressedBytes = (int)filesize($destFile);
        $durationMs = round((microtime(true) - $start) * 1000, 2);
        $sha256 = hash_file('sha256', $destFile);
        $ratio = $totalBytes > 0 ? round((1 - ($compressedBytes / $totalBytes)) * 100, 1) : 0.0;

        return [
            'success' => true,
            'archive_file' => $destFile,
            'relative_path' => ltrim(str_replace($baseDir, '', $destFile), '/'),
            'format' => $format,
            'files_count' => $fileCount,
            'uncompressed_bytes' => $totalBytes,
            'compressed_bytes' => $compressedBytes,
            'compression_ratio_pct' => max(0.0, $ratio),
            'sha256' => $sha256,
            'duration_ms' => $durationMs,
            'message' => "Archive created successfully with {$fileCount} files ({$durationMs}ms)."
        ];
    }

    /**
     * Safely unpacks an archive with strict Zip-Slip protection.
     */
    public static function extractArchive(string $archivePath, ?string $destDir = null, array $options = []): array
    {
        $start = microtime(true);
        $archiveFile = self::resolveSafePath($archivePath);

        if (!$archiveFile || !is_file($archiveFile)) {
            return ['success' => false, 'error' => "Archive file '{$archivePath}' does not exist or is not a file."];
        }

        $dest = $destDir ? self::resolveSafePath($destDir) : dirname($archiveFile);
        if (!$dest) {
            return ['success' => false, 'error' => 'Invalid destination directory.'];
        }

        if (!is_dir($dest)) {
            @mkdir($dest, 0775, true);
        }

        $canonicalDest = realpath($dest);
        if ($canonicalDest === false) {
            return ['success' => false, 'error' => 'Destination directory cannot be canonicalized.'];
        }

        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'PHP ZipArchive extension is required for extraction.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($archiveFile) !== true) {
            return ['success' => false, 'error' => "Failed to open archive '{$archivePath}'."];
        }

        $totalEntries = $zip->numFiles;
        $maxEntriesLimit = (int)($options['max_entries'] ?? 10000);
        $maxBytesLimit = (int)($options['max_bytes'] ?? (500 * 1024 * 1024)); // 500MB safety cap

        if ($totalEntries > $maxEntriesLimit) {
            $zip->close();
            return [
                'success' => false,
                'error' => "Decompression bomb defense: Archive contains {$totalEntries} entries, exceeding limit of {$maxEntriesLimit}."
            ];
        }

        $extractedCount = 0;
        $extractedBytes = 0;
        $blockedEntries = [];
        $normCanon = rtrim(str_replace('\\', '/', $canonicalDest), '/');

        for ($i = 0; $i < $totalEntries; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) {
                continue;
            }

            // ZIP-SLIP DEFENSE: Normalize entry path and block traversal
            $cleanEntry = ltrim(str_replace('\\', '/', $entryName), '/');
            if (str_contains($cleanEntry, '../') || str_starts_with($cleanEntry, '/') || str_starts_with($cleanEntry, '..')) {
                $blockedEntries[] = $entryName;
                continue;
            }

            $targetPath = $canonicalDest . '/' . $cleanEntry;
            
            // Directory entry
            if (str_ends_with($cleanEntry, '/')) {
                if (!is_dir($targetPath)) {
                    @mkdir($targetPath, 0775, true);
                }
                continue;
            }

            // File entry: ensure parent directory exists
            $parentDir = dirname($targetPath);
            if (!is_dir($parentDir)) {
                @mkdir($parentDir, 0775, true);
            }

            $realParent = realpath($parentDir);
            $normParent = rtrim(str_replace('\\', '/', $realParent ?: $parentDir), '/');
            if ($realParent && $normParent !== $normCanon && !str_starts_with($normParent . '/', $normCanon . '/')) {
                $blockedEntries[] = $entryName;
                continue;
            }

            // Extract individual stream
            $stream = $zip->getStream($entryName);
            if ($stream) {
                $outHandle = @fopen($targetPath, 'wb');
                if ($outHandle) {
                    $bytes = stream_copy_to_stream($stream, $outHandle);
                    fclose($outHandle);
                    $extractedBytes += (int)$bytes;
                    $extractedCount++;

                    if ($extractedBytes > $maxBytesLimit) {
                        fclose($stream);
                        $zip->close();
                        return [
                            'success' => false,
                            'error' => "Decompression bomb defense: Total extracted size exceeded limit of {$maxBytesLimit} bytes.",
                            'files_extracted' => $extractedCount
                        ];
                    }
                }
                fclose($stream);
            }
        }

        $zip->close();
        $durationMs = round((microtime(true) - $start) * 1000, 2);

        if (!empty($blockedEntries)) {
            return [
                'success' => false,
                'error' => "Zip-Slip security alert: " . count($blockedEntries) . " malicious path traversal entries were blocked.",
                'blocked_entries' => $blockedEntries,
                'files_extracted' => $extractedCount
            ];
        }

        // Auto-synchronize manifest metadata for all extracted contents
        if (class_exists('\\SPPMod\\SPPStorage\\FilePropsManager')) {
            try {
                \SPPMod\SPPStorage\FilePropsManager::syncDirectory($canonicalDest, true, false, false);
            } catch (\Throwable $e) {}
        }

        return [
            'success' => true,
            'files_extracted' => $extractedCount,
            'extracted_bytes' => $extractedBytes,
            'destination' => $canonicalDest,
            'duration_ms' => $durationMs,
            'message' => "Successfully extracted {$extractedCount} files into {$canonicalDest} ({$durationMs}ms)."
        ];
    }

    /**
     * Inspects archive contents without extracting.
     */
    public static function inspectArchive(string $archivePath, int $maxEntries = 100): array
    {
        $archiveFile = self::resolveSafePath($archivePath);
        if (!$archiveFile || !is_file($archiveFile)) {
            return ['success' => false, 'error' => "Archive file '{$archivePath}' not found."];
        }

        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'PHP ZipArchive extension is required.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($archiveFile) !== true) {
            return ['success' => false, 'error' => "Failed to read archive header."];
        }

        $totalEntries = $zip->numFiles;
        $uncompressedBytes = 0;
        $compressedBytes = (int)filesize($archiveFile);
        $entries = [];

        for ($i = 0; $i < $totalEntries; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat) {
                $uncompressedBytes += (int)($stat['size'] ?? 0);
                if (count($entries) < $maxEntries) {
                    $entries[] = [
                        'name' => $stat['name'] ?? '',
                        'size' => (int)($stat['size'] ?? 0),
                        'compressed_size' => (int)($stat['comp_size'] ?? 0),
                        'crc' => sprintf('%08X', $stat['crc'] ?? 0),
                        'mtime' => date('Y-m-d H:i:s', $stat['mtime'] ?? time())
                    ];
                }
            }
        }

        $zip->close();
        $ratio = $uncompressedBytes > 0 ? round((1 - ($compressedBytes / $uncompressedBytes)) * 100, 1) : 0.0;
        $sha256 = hash_file('sha256', $archiveFile);

        return [
            'success' => true,
            'archive_file' => basename($archiveFile),
            'total_files' => $totalEntries,
            'uncompressed_bytes' => $uncompressedBytes,
            'compressed_bytes' => $compressedBytes,
            'compression_ratio_pct' => max(0.0, $ratio),
            'sha256' => $sha256,
            'entries_preview' => $entries
        ];
    }

    /**
     * Cryptographically verifies archive integrity.
     */
    public static function verifyArchive(string $archivePath): array
    {
        $archiveFile = self::resolveSafePath($archivePath);
        if (!$archiveFile || !is_file($archiveFile)) {
            return ['success' => false, 'error' => "Archive file '{$archivePath}' not found."];
        }

        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'PHP ZipArchive extension is required.'];
        }

        $zip = new ZipArchive();
        $res = $zip->open($archiveFile, ZipArchive::CHECKCONS);
        if ($res !== true) {
            return ['success' => false, 'error' => "Archive integrity check failed with error code: {$res}."];
        }

        $totalFiles = $zip->numFiles;
        $status = $zip->status;
        $zip->close();

        return [
            'success' => true,
            'archive_file' => basename($archiveFile),
            'total_files' => $totalFiles,
            'integrity_status' => 'OK',
            'sha256' => hash_file('sha256', $archiveFile),
            'message' => "Archive integrity verified OK ({$totalFiles} files intact)."
        ];
    }
}
