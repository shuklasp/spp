<?php
declare(strict_types=1);

namespace SPPMod\SPPStorage;

use Symfony\Component\Yaml\Yaml;

/**
 * Class FileIntegritySigner
 *
 * Cryptographic checksum and HMAC-SHA256 signature verification engine driven by __spp_file_props.yml.
 * Provides zero-trust code integrity verification, detecting file tampering, unauthorized modifications,
 * or bit rot across the SPP workspace.
 */
class FileIntegritySigner
{
    /**
     * Computes the SHA-256 checksum of a file.
     */
    public static function computeChecksum(string $filePath): ?string
    {
        if (!file_exists($filePath) || is_dir($filePath)) {
            return null;
        }
        return hash_file('sha256', $filePath) ?: null;
    }

    /**
     * Computes the HMAC-SHA256 signature of a file using a private secret key.
     */
    public static function computeSignature(string $filePath, string $secretKey): ?string
    {
        if (!file_exists($filePath) || is_dir($filePath)) {
            return null;
        }
        return hash_hmac_file('sha256', $filePath, $secretKey) ?: null;
    }

    /**
     * Generates and writes the checksum and optional HMAC signature for a single file into its manifest.
     */
    public static function signFile(string $filePath, ?string $secretKey = null): bool
    {
        if (!file_exists($filePath) || is_dir($filePath)) {
            return false;
        }

        $checksum = self::computeChecksum($filePath);
        if (!$checksum) {
            return false;
        }

        $updates = ['checksum' => $checksum];
        if (!empty($secretKey)) {
            $updates['signature'] = self::computeSignature($filePath, $secretKey);
        }

        return FilePropsManager::setFileProps($filePath, $updates);
    }

    /**
     * Signs all files in a directory tree, updating their __spp_file_props.yml manifests.
     *
     * @param string $dir Target directory
     * @param string|null $secretKey Optional secret key for HMAC signature
     * @param bool $recursive Whether to traverse subdirectories
     * @return array Summary of signed files
     */
    public static function signDirectory(string $dir, ?string $secretKey = null, bool $recursive = true): array
    {
        if (!is_dir($dir)) {
            return ['signed' => 0, 'skipped' => 0, 'files' => []];
        }

        $signed = 0;
        $skipped = 0;
        $files = [];

        $walker = function (string $currentDir) use (&$walker, &$signed, &$skipped, &$files, $secretKey, $recursive) {
            $manifestPath = $currentDir . DIRECTORY_SEPARATOR . '__spp_file_props.yml';
            $manifest = file_exists($manifestPath) ? (Yaml::parseFile($manifestPath) ?? []) : ['files' => []];
            $modified = false;

            $entries = @scandir($currentDir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === '__spp_file_props.yml' || $entry === '.git' || $entry === 'vendor' || $entry === 'var') {
                    continue;
                }

                $fullPath = $currentDir . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($fullPath)) {
                    if ($recursive) {
                        $walker($fullPath);
                    }
                    continue;
                }

                $checksum = self::computeChecksum($fullPath);
                if (!$checksum) {
                    $skipped++;
                    continue;
                }

                $fileMeta = $manifest['files'][$entry] ?? [
                    'description' => "File {$entry}",
                    'type' => 'source',
                    'stability' => 'stable'
                ];

                $fileMeta['checksum'] = $checksum;
                if (!empty($secretKey)) {
                    $fileMeta['signature'] = self::computeSignature($fullPath, $secretKey);
                }

                $manifest['files'][$entry] = $fileMeta;
                $modified = true;
                $signed++;
                $files[] = $fullPath;
            }

            if ($modified) {
                FilePropsManager::writeManifestAtomic($manifestPath, $manifest);
            }
        };

        $walker($dir);

        return [
            'signed' => $signed,
            'skipped' => $skipped,
            'files' => $files
        ];
    }

    /**
     * Verifies the integrity of a single file against its directory manifest.
     */
    public static function verifyFile(string $filePath, ?string $secretKey = null): array
    {
        if (!file_exists($filePath)) {
            return [
                'status' => 'missing_file',
                'file' => $filePath,
                'message' => 'File does not exist on disk.'
            ];
        }

        $props = FilePropsManager::getFileProps($filePath);
        if ($props === null || empty($props['has_props'])) {
            return [
                'status' => 'untracked',
                'file' => $filePath,
                'message' => 'File has no metadata entry in __spp_file_props.yml.'
            ];
        }

        $expectedChecksum = $props['checksum'] ?? ($props['attributes']['checksum'] ?? null);
        if (empty($expectedChecksum)) {
            return [
                'status' => 'missing_checksum',
                'file' => $filePath,
                'message' => 'File is tracked in manifest but has no registered checksum.'
            ];
        }

        $actualChecksum = self::computeChecksum($filePath);
        if ($actualChecksum !== $expectedChecksum) {
            return [
                'status' => 'tampered',
                'file' => $filePath,
                'expected' => $expectedChecksum,
                'actual' => $actualChecksum,
                'message' => 'Checksum mismatch: file contents have been modified.'
            ];
        }

        if (!empty($secretKey)) {
            $expectedSig = $props['signature'] ?? ($props['attributes']['signature'] ?? null);
            if (empty($expectedSig)) {
                return [
                    'status' => 'missing_signature',
                    'file' => $filePath,
                    'message' => 'Verification key provided but manifest contains no signature.'
                ];
            }
            $actualSig = self::computeSignature($filePath, $secretKey);
            if (!hash_equals($expectedSig, $actualSig)) {
                return [
                    'status' => 'invalid_signature',
                    'file' => $filePath,
                    'expected_sig' => $expectedSig,
                    'actual_sig' => $actualSig,
                    'message' => 'HMAC signature verification failed: secret key mismatch or file forged.'
                ];
            }
        }

        return [
            'status' => 'valid',
            'file' => $filePath,
            'checksum' => $actualChecksum
        ];
    }

    /**
     * Verifies the cryptographic integrity of all files in a directory tree.
     */
    public static function verifyDirectory(string $dir, ?string $secretKey = null, bool $recursive = true): array
    {
        if (!is_dir($dir)) {
            return [
                'total' => 0,
                'valid' => 0,
                'tampered' => 0,
                'missing_checksum' => 0,
                'missing_file' => 0,
                'untracked' => 0,
                'invalid_signature' => 0,
                'details' => []
            ];
        }

        $counts = [
            'valid' => 0,
            'tampered' => 0,
            'missing_checksum' => 0,
            'missing_file' => 0,
            'untracked' => 0,
            'invalid_signature' => 0,
            'missing_signature' => 0
        ];
        $details = [];

        $walker = function (string $currentDir) use (&$walker, &$counts, &$details, $secretKey, $recursive) {
            $manifestPath = $currentDir . DIRECTORY_SEPARATOR . '__spp_file_props.yml';
            $manifest = file_exists($manifestPath) ? (Yaml::parseFile($manifestPath) ?? []) : null;

            // 1. Check all registered files in the manifest for missing files or integrity
            if ($manifest && !empty($manifest['files']) && is_array($manifest['files'])) {
                foreach ($manifest['files'] as $fileName => $fileMeta) {
                    $fullPath = $currentDir . DIRECTORY_SEPARATOR . $fileName;
                    $res = self::verifyFile($fullPath, $secretKey);
                    $status = $res['status'];
                    if (isset($counts[$status])) {
                        $counts[$status]++;
                    }
                    if ($status !== 'valid') {
                        $details[] = $res;
                    }
                }
            }

            // 2. Check for untracked files in directory
            $entries = @scandir($currentDir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === '__spp_file_props.yml' || $entry === '.git' || $entry === 'vendor' || $entry === 'var') {
                    continue;
                }
                $fullPath = $currentDir . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($fullPath)) {
                    if ($recursive) {
                        $walker($fullPath);
                    }
                    continue;
                }

                if (!$manifest || !isset($manifest['files'][$entry])) {
                    $counts['untracked']++;
                    $details[] = [
                        'status' => 'untracked',
                        'file' => $fullPath,
                        'message' => 'File exists on disk but is not tracked in manifest.'
                    ];
                }
            }
        };

        $walker($dir);

        $total = array_sum($counts);

        return [
            'total' => $total,
            'valid' => $counts['valid'],
            'tampered' => $counts['tampered'],
            'missing_checksum' => $counts['missing_checksum'],
            'missing_file' => $counts['missing_file'],
            'untracked' => $counts['untracked'],
            'invalid_signature' => $counts['invalid_signature'],
            'missing_signature' => $counts['missing_signature'],
            'details' => $details
        ];
    }
}
