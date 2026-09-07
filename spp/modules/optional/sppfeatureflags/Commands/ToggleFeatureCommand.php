<?php

namespace SPPMod\SPPFeatureFlags\Commands;

use SPP\CLI\Command;
use SPPMod\SPPFeatureFlags\FeatureManager;

/**
 * ToggleFeatureCommand
 * CLI daemon to inspect and dynamically modify advanced feature flags, canary rollout percentages,
 * and Telemetry Kill Switch thresholds.
 */
class ToggleFeatureCommand extends Command
{
    protected string $name = 'feature:toggle';
    protected string $description = 'Manage advanced feature flags, canary rollout percentages, and evaluate Telemetry Kill Switch status';

    /**
     * Mandatory CLI SAPI Guarding to ensure zero-trust security execution.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "\033[32mINFO:\033[0m SPP Advanced Feature Flags & Canary Experimentation Manager\n\n";

        $flagName = null;
        $enable = null;
        $canary = null;

        $jsonOutput = false;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--flag=')) {
                $flagName = substr($arg, 7);
            } elseif (str_starts_with($arg, '--enable=')) {
                $enable = filter_var(substr($arg, 9), FILTER_VALIDATE_BOOLEAN);
            } elseif (str_starts_with($arg, '--canary=')) {
                $canary = (int)substr($arg, 9);
            } elseif ($arg === '--json') {
                $jsonOutput = true;
            }
        }

        if ($flagName !== null && $enable !== null) {
            $canaryVal = $canary ?? 0;
            FeatureManager::setFlag($flagName, $enable, $canaryVal, ['enterprise_admins']);
            if (!$jsonOutput) {
                echo "\033[32mSUCCESS:\033[0m Feature flag '{$flagName}' updated. Enabled: " . ($enable ? 'true' : 'false') . ", Canary: {$canaryVal}%\n\n";
            }
        }

        $flags = FeatureManager::listFlags();
        $outputFlags = [];
        foreach ($flags as $name => $meta) {
            $errors = \SPPMod\SPPReport\Services\OpenTelemetryExporter::getErrorCount($name);
            $isTriggered = ($errors >= $meta['kill_switch_threshold_errors']);
            
            $outputFlags[] = [
                'name' => $name,
                'enabled' => $meta['enabled'],
                'canary' => $meta['canary_percentage'],
                'errors' => $errors,
                'threshold' => $meta['kill_switch_threshold_errors'],
                'killSwitchTriggered' => $isTriggered
            ];
        }

        if ($jsonOutput) {
            echo json_encode(['success' => true, 'flags' => $outputFlags], JSON_PRETTY_PRINT);
            return;
        }

        echo "Current Enterprise Feature Flags Status:\n";
        echo "--------------------------------------------------------------------------------\n";
        echo sprintf("%-30s | %-10s | %-12s | %-20s\n", "Feature Flag Name", "Enabled", "Canary %", "Telemetry Kill Switch");
        echo "--------------------------------------------------------------------------------\n";

        foreach ($outputFlags as $meta) {
            $enabledStr = $meta['enabled'] ? "\033[32mTRUE\033[0m" : "\033[31mFALSE\033[0m";
            $killSwitchStr = $meta['killSwitchTriggered'] ? "\033[31mTRIGGERED\033[0m" : "\033[32mACTIVE ({$meta['errors']}/{$meta['threshold']})\033[0m";

            echo sprintf("%-30s | %-19s | %-12s | %-20s\n", $meta['name'], $enabledStr, $meta['canary'] . '%', $killSwitchStr);
        }

        echo "--------------------------------------------------------------------------------\n";
    }
}
