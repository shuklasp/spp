<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class CoreVerifyChecksumsCommand
 *
 * Verifies the integrity of core SPP framework files against a cryptographic SHA-256 manifest.
 * Detects tampering, accidental modifications, malware injections, or incomplete deployments.
 *
 * @package SPP\CLI\Commands
 * @author Satya Prakash Shukla
 */
class CoreVerifyChecksumsCommand extends Command
{
    protected string $name = 'core:verify-checksums';
    protected string $description = 'Verify integrity of core SPP framework files using SHA-256 checksums';

    /**
     * Strict CLI SAPI guard to prevent execution from web contexts.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $generate = $this->hasFlag($args, 'generate');
        $strict = $this->hasFlag($args, 'strict');
        $isJson = $this->hasFlag($args, 'json');

        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $manifestFile = $baseDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'checksums.json';

        if (!$isJson) {
            $this->line("================================================================================");
            $this->line(" 🔍  SPP Core Framework Integrity & Checksum Verifier");
            $this->line("================================================================================");
        }

        // 1. Gather all core files
        $coreFiles = [];
        $scanRoots = [
            $baseDir . DIRECTORY_SEPARATOR . 'core',
            $baseDir . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'spp',
        ];

        // Standalone root files
        $standalone = [
            $baseDir . DIRECTORY_SEPARATOR . 'sppinit.php',
            $baseDir . DIRECTORY_SEPARATOR . 'spp.php',
        ];

        foreach ($standalone as $sf) {
            if (file_exists($sf)) {
                $rel = ltrim(str_replace('\\', '/', substr($sf, strlen($baseDir))), '/');
                $coreFiles[$rel] = $sf;
            }
        }

        foreach ($scanRoots as $root) {
            if (is_dir($root)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $item) {
                    if ($item->isFile() && $item->getExtension() === 'php') {
                        $fullPath = $item->getPathname();
                        $rel = ltrim(str_replace('\\', '/', substr($fullPath, strlen($baseDir))), '/');
                        $coreFiles[$rel] = $fullPath;
                    }
                }
            }
        }

        // Handle --generate flag
        if ($generate) {
            $manifest = [
                'version' => defined('SPP_VER') ? SPP_VER : '1.0.0',
                'generated_at' => date('c'),
                'algorithm' => 'sha256',
                'files' => []
            ];

            foreach ($coreFiles as $rel => $fullPath) {
                $manifest['files'][$rel] = hash_file('sha256', $fullPath);
            }

            if (!is_dir(dirname($manifestFile))) {
                mkdir(dirname($manifestFile), 0755, true);
            }

            file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($isJson) {
                $this->json(['success' => true, 'count' => count($manifest['files']), 'file' => $manifestFile]);
            } else {
                $this->info("✅ Manifest successfully generated with " . count($manifest['files']) . " core file signatures.");
                $this->line("Saved to: " . $manifestFile);
            }
            return;
        }

        // Handle verification mode
        if (!file_exists($manifestFile)) {
            $this->warn("No existing checksum manifest found at {$manifestFile}.");
            $this->line("Run `php spp.php core:verify-checksums --generate` to baseline the current installation.");
            return;
        }

        $manifestData = json_decode(file_get_contents($manifestFile), true);
        if (!$manifestData || empty($manifestData['files'])) {
            $this->error("Invalid or empty checksum manifest format.");
            exit(1);
        }

        $recorded = $manifestData['files'];
        $results = [];
        $matched = 0;
        $modified = 0;
        $missing = 0;
        $untracked = 0;

        // Check tracked files
        foreach ($recorded as $rel => $expectedHash) {
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!file_exists($fullPath)) {
                $missing++;
                $results[] = [
                    'file' => $rel,
                    'status' => 'MISSING',
                    'detail' => 'Tracked core file is missing from filesystem',
                ];
            } else {
                $actualHash = hash_file('sha256', $fullPath);
                if ($actualHash !== $expectedHash) {
                    $modified++;
                    $results[] = [
                        'file' => $rel,
                        'status' => 'MODIFIED',
                        'detail' => 'File content does not match recorded SHA-256 signature',
                    ];
                } else {
                    $matched++;
                }
            }
        }

        // Check untracked files
        foreach ($coreFiles as $rel => $fullPath) {
            if (!isset($recorded[$rel])) {
                $untracked++;
                $results[] = [
                    'file' => $rel,
                    'status' => 'UNTRACKED',
                    'detail' => 'New file present in core directory but not recorded in manifest',
                ];
            }
        }

        if ($isJson) {
            $this->json([
                'matched' => $matched,
                'modified' => $modified,
                'missing' => $missing,
                'untracked' => $untracked,
                'issues' => $results
            ]);
            return;
        }

        $this->line("Baseline Manifest: " . ($manifestData['generated_at'] ?? 'Unknown date'));
        $this->line("Total Files Scanned: " . count($coreFiles) . "\n");

        if (empty($results)) {
            $this->info("✅ All {$matched} core framework files perfectly match their cryptographic signatures!");
            $this->line("Integrity verified: Framework is unmodified and authentic.\n");
            return;
        }

        foreach ($results as $res) {
            $badge = match ($res['status']) {
                'MODIFIED' => "\033[31m[MODIFIED]\033[0m",
                'MISSING' => "\033[31m[MISSING]\033[0m",
                'UNTRACKED' => "\033[33m[UNTRACKED]\033[0m",
                default => "\033[36m[INFO]\033[0m",
            };
            $this->line(sprintf(" %-18s %s", $badge, $res['file']));
            $this->line("    ↳ " . $res['detail']);
        }

        $this->line("\n--------------------------------------------------------------------------------");
        $this->line("Summary: {$matched} Matched, {$modified} Modified, {$missing} Missing, {$untracked} Untracked");
        $this->line("================================================================================\n");

        if ($modified > 0 || $missing > 0 || ($strict && $untracked > 0)) {
            $this->error("Framework Integrity Check FAILED! Modifications or missing files detected.");
            exit(1);
        } else {
            $this->warn("Integrity Check completed with warnings (untracked files detected).");
        }
    }
}
