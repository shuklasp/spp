<?php

namespace SPPMod\SPPAI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPAI\SPPAI;

/**
 * RefactorEnterpriseCommand
 * AI-powered CLI daemon that automatically scans legacy PHP files and refactors them
 * to comply with strict enterprise workspace rules (zero inline HTML literals, W3C Trace Context spans).
 */
class RefactorEnterpriseCommand extends Command
{
    protected string $name = 'ai:refactor:enterprise';
    protected string $description = 'AI-powered automated refactoring daemon to modernize legacy code into strict SPP enterprise compliance';

    /**
     * Mandatory CLI SAPI Guarding to ensure zero-trust security execution.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "\033[32mINFO:\033[0m Starting SPP AI-Powered Enterprise Refactoring Assistant...\n\n";

        $targetPath = 'src/App/Controllers';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--path=')) {
                $targetPath = substr($arg, 7);
            }
        }

        $absolutePath = \SPP\App::getApp()->getBasePath() . '/' . ltrim($targetPath, '/');
        if (!is_dir($absolutePath) && !is_file($absolutePath)) {
            echo "\033[33mWARNING:\033[0m Target path does not exist: {$targetPath}\n";
            return;
        }

        echo "Scanning Path: \033[36m{$targetPath}\033[0m\n";
        echo "--------------------------------------------------------------------------------\n";
        echo sprintf("%-50s | %-25s\n", "Target File", "Refactoring Status");
        echo "--------------------------------------------------------------------------------\n";

        $files = is_file($absolutePath) ? [$absolutePath] : $this->findPhpFiles($absolutePath);
        $count = 0;

        foreach ($files as $file) {
            $relativePath = str_replace(\SPP\App::getApp()->getBasePath() . '/', '', $file);
            $content = file_get_contents($file);

            // Check if file needs refactoring (e.g., contains inline HTML literals or lacks W3CTraceContext)
            $needsRefactor = false;
            if (preg_match('/<\/?(?:div|span|table|html|body|p|a|ul|li|button|input|form)[^>]*>/i', $content)) {
                $needsRefactor = true; // Contains inline HTML literals
            }
            if (!str_contains($content, 'W3CTraceContext::startSpan')) {
                $needsRefactor = true; // Lacks enterprise telemetry tracking
            }

            if ($needsRefactor) {
                echo sprintf("%-50s | \033[33m%s\033[0m\n", $relativePath, "REFACTORING (AI Calling...)");

                $prompt = "You are an expert enterprise SPP AI refactoring engine. Rewrite the following PHP file to strictly adhere to SPP workspace rules: 1. Remove all inline HTML string literals and replace them with \$this->renderPartial('partials/name.html', \$data). 2. Add W3CTraceContext::startSpan('class.method', \$attributes) at the beginning of public actions. Return only the refactored clean PHP code.";
                
                // Invoke SPPAI tool call (Ollama / App Config Provider)
                $refactoredCode = SPPAI::callTool($prompt, ['code' => $content]);
                if (!empty($refactoredCode) && str_starts_with(trim($refactoredCode), '<?php')) {
                    file_put_contents($file, $refactoredCode);
                    echo sprintf("%-50s | \033[32m%s\033[0m\n", $relativePath, "SUCCESS (Refactored)");
                    $count++;
                } else {
                    echo sprintf("%-50s | \033[31m%s\033[0m\n", $relativePath, "FAILED (AI Provider Unreachable)");
                }
            } else {
                echo sprintf("%-50s | \033[32m%s\033[0m\n", $relativePath, "PASSED (Already Compliant)");
            }
        }

        echo "--------------------------------------------------------------------------------\n";
        echo "\033[32mSUCCESS:\033[0m AI Enterprise Refactoring complete. Modernized: {$count} files.\n";
    }

    private function findPhpFiles(string $dir): array
    {
        $results = [];
        $items = @scandir($dir);
        if ($items === false) {
            return [];
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $results = array_merge($results, $this->findPhpFiles($path));
            } elseif (str_ends_with($item, '.php')) {
                $results[] = $path;
            }
        }
        return $results;
    }
}
