<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPSwarm\SwarmHub;
use SPPMod\SPPAI\SPPAI;

/**
 * Class DoctorCommand
 * 
 * Diagnoses the health of the WebOS architecture (VFS, VirtualPDO, Swarm) 
 * and provides plain-English troubleshooting, falling back to basic heuristics if AI is disabled.
 */
class DoctorCommand extends Command
{
    protected string $name = 'doctor';
    protected string $description = 'Diagnose the health of the WebOS architecture';

    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $this->info("SPP WebOS Diagnostic Doctor");
        $this->info("===========================");

        $diagnostics = [];
        
        // 1. Check AI Status
        $aiEnabled = SwarmHub::isAiEnabled();
        $diagnostics[] = "Swarm AI Status: " . ($aiEnabled ? "ONLINE" : "OFFLINE (Deterministic Fallback)");

        // 2. Check XDB Indexer existence
        if (class_exists('\SPPMod\SPPStorage\XdbBinaryIndexer')) {
            $diagnostics[] = "XDB Master Sink: ONLINE";
        } else {
            $diagnostics[] = "XDB Master Sink: WARNING - Indexer class not found.";
        }

        // 3. Check Pre-Compiled Kernel Cache
        $cacheFile = __DIR__ . '/../modules/spp/kernel.cache.php';
        if (file_exists($cacheFile)) {
            $diagnostics[] = "FastCGI Kernel Cache: COMPILED (" . filesize($cacheFile) . " bytes)";
        } else {
            $diagnostics[] = "FastCGI Kernel Cache: MISSING (Boot overhead will be high)";
        }

        // Output raw diagnostics
        foreach ($diagnostics as $line) {
            $this->info("- $line");
        }

        $this->info("\nAnalysis:");
        
        // If AI is disabled or fails, use heuristic fallback
        if (!$aiEnabled) {
            $this->info("AI is disabled. Please review the above metrics manually. If FastCGI Cache is missing, your system will suffer from boot overhead on shared hosting.");
            return;
        }

        // AI-Powered explanation
        $prompt = "You are the SPP WebOS Doctor. Analyze this diagnostic report and explain any architectural issues in 2 sentences. Report: " . implode(" | ", $diagnostics);
        try {
            $analysis = SPPAI::callTool($prompt, []);
            $this->info($analysis);
        } catch (\Throwable $e) {
            $this->info("AI Doctor is unreachable. Please review metrics manually.");
        }
    }
}
