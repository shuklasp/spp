<?php
namespace SPP\CLI\Commands;

class LintCommand extends \SPP\CLI\Command
{
    public function getName(): string
    {
        return 'lint';
    }

    public function getDescription(): string
    {
        return 'Run SPP native linter on a file';
    }

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $filePath = null;
        $json = false;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--file=')) {
                $filePath = substr($arg, 7);
            } elseif ($arg === '--json') {
                $json = true;
            }
        }

        if (!$filePath || !file_exists($filePath)) {
            echo "Error: File not found.\n";
            return;
        }

        $code = file_get_contents($filePath);
        $diagnostics = [];

        // 6. CDN Asset & 7. SPP-UX Linter (applies to HTML as well)
        if (str_ends_with(strtolower($filePath), '.html') || str_ends_with(strtolower($filePath), '.stub')) {
            $this->checkHtmlCode($code, $diagnostics);
        }

        // For PHP files, use native token parsing
        if (str_ends_with(strtolower($filePath), '.php')) {
            $this->checkPhpTokens($code, $filePath, $diagnostics);
            $this->checkHtmlCode($code, $diagnostics); // also run HTML checks on PHP files since they may contain templates
        }

        if ($json) {
            echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            foreach ($diagnostics as $d) {
                echo "[{$d['severity']}] {$d['message']} at line {$d['line']}\n";
            }
        }
    }

    private function checkHtmlCode(string $code, array &$diagnostics): void
    {
        // 6. CDN Asset Linter
        $lines = explode("\n", $code);
        foreach ($lines as $idx => $line) {
            if (preg_match('/<script\s+src=[\'"]https?:\/\/[^\'"]*(?:htmx|turbo)[^\'"]*\.js[\'"]\s*><\/script>/i', $line)) {
                $diagnostics[] = [
                    'line' => $idx + 1,
                    'code' => 'spp_cdn_asset',
                    'message' => 'SPP Rule Violation: Local Standalone Client Assets required. Do NOT use external CDNs for HTMX/Turbo.',
                    'severity' => 'Error'
                ];
            }
            // 7. SPP-UX Anti-Bypass
            if (preg_match('/\s(on(?:click|mousedown|mouseup|pointerdown|pointerup|change|submit|input))\s*=/i', $line, $matches)) {
                $diagnostics[] = [
                    'line' => $idx + 1,
                    'code' => 'spp_anti_bypass',
                    'message' => 'SPP Integrity Rule Violation: Do NOT use native inline DOM events (' . $matches[1] . '). Use SPP-UX synthetic event directives (e.g., @' . substr($matches[1], 2) . ') instead.',
                    'severity' => 'Error'
                ];
            }
        }
    }

    private function checkPhpTokens(string $code, string $filePath, array &$diagnostics): void
    {
        $tokens = token_get_all($code);
        $hasIsCliOnly = false;
        $isClass = false;
        $isCommandClass = str_contains(strtolower($filePath), 'command');
        $hasFireEvent = false;
        $hasTriggerHook = false;
        
        $hasAcquireLock = false;
        $hasReleaseLock = false;
        $isDeployCommand = str_contains(strtolower($filePath), 'deploy');

        $hasTransition = false;
        $hasGuard = false;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tId = $token[0];
                $tStr = $token[1];
                $tLine = $token[2];

                if ($tId === T_CLASS) {
                    $isClass = true;
                }
                
                if ($tId === T_STRING) {
                    if (strtolower($tStr) === 'isclionly') $hasIsCliOnly = true;
                    if (strtolower($tStr) === 'fireevent') $hasFireEvent = true;
                    if (strtolower($tStr) === 'triggerhook') $hasTriggerHook = true;
                    if (strtolower($tStr) === 'acquiredeploymentlock') $hasAcquireLock = true;
                    if (strtolower($tStr) === 'releasedeploymentlock') $hasReleaseLock = true;
                    if (strtolower($tStr) === 'transitionentity' || strtolower($tStr) === 'applytransition') $hasTransition = true;
                    if (strtolower($tStr) === 'sppworkflowguardvalidator' || strtolower($tStr) === 'spp_validator_workflowguardvalidator') $hasGuard = true;
                }

                // 1. Zero Inline HTML
                if ($tId === T_CONSTANT_ENCAPSED_STRING) {
                    if (preg_match('/<\/?(?:div|span|p|a|table|form|input|button|ul|li)[^>]*>/i', $tStr)) {
                        $diagnostics[] = [
                            'line' => $tLine,
                            'code' => 'spp_zero_inline_html',
                            'message' => 'SPP Rule Violation: Zero Inline HTML Literals allowed. Use $this->renderPartial() instead.',
                            'severity' => 'Error'
                        ];
                    }
                    
                    // Also check for deploy commands in strings
                    if (preg_match('/deploy:\w+/i', $tStr)) {
                        $isDeployCommand = true;
                    }
                }

                // 3. DDL Security Linter
                if ($tId === T_CONSTANT_ENCAPSED_STRING || $tId === T_ENCAPSED_AND_WHITESPACE) {
                    // It's harder with tokens since variables interpolate in double quotes, making them multiple tokens
                    // We'll fall back to a simple string check on the full code for DDL
                }
            }
        }
        
        // 3. DDL Check on raw string
        $lines = explode("\n", $code);
        foreach ($lines as $idx => $line) {
            if (preg_match('/"(?:CREATE|ALTER|DROP)\s+TABLE\s+[^"]*\$\w+[^"]*"/i', $line)) {
                $diagnostics[] = [
                    'line' => $idx + 1,
                    'code' => 'spp_ddl_security',
                    'message' => 'SPP Security Rule Violation: DDL Identifier Sanitization required. Do NOT interpolate raw variables into DDL statements.',
                    'severity' => 'Error'
                ];
            }
        }

        // 2. CLI Guard
        if ($isClass && $isCommandClass && !$hasIsCliOnly) {
            $diagnostics[] = [
                'line' => 1,
                'code' => 'spp_missing_cli_guard',
                'message' => 'SPP Security Rule Violation: CLI commands MUST override `public function isCLIOnly(): bool { return true; }`.',
                'severity' => 'Error'
            ];
        }

        // 4. Dual Event Bus
        if ($hasFireEvent && !$hasTriggerHook) {
            $diagnostics[] = [
                'line' => 1,
                'code' => 'spp_dual_event_bus',
                'message' => 'SPP Rule Violation: Dual Event Bus Firing required. Missing corresponding `triggerHook()`.',
                'severity' => 'Warning'
            ];
        }
        
        // 5. Workflow Form Guard
        if (($hasTransition || str_contains(strtolower($filePath), 'form')) && !$hasGuard) {
            // Wait, this applies if it's a Form or does transitionEntity
            if ($isClass && str_contains($code, 'Validator')) {
                $diagnostics[] = [
                    'line' => 1,
                    'code' => 'spp_missing_form_guard',
                    'message' => 'SPP Rule Violation: Workflow-managed entities MUST include `SPPWorkflowGuardValidator` in validation rules.',
                    'severity' => 'Warning'
                ];
            }
        }
        
        // 8. Deploy Command Mutex
        if ($isClass && $isDeployCommand && (!$hasAcquireLock || !$hasReleaseLock)) {
            $diagnostics[] = [
                'line' => 1,
                'code' => 'spp_missing_deploy_mutex',
                'message' => 'SPP Deploy Rule Violation: Deployment orchestration commands MUST prevent concurrent execution race conditions by wrapping their core logic in `acquireDeploymentLock()` and `releaseDeploymentLock()`.',
                'severity' => 'Error'
            ];
        }
    }
}
