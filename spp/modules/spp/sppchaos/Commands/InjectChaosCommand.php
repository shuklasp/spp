<?php

namespace SPPMod\SPPChaos\Commands;

use SPP\CLI\Command;
use SPPMod\SPPChaos\ChaosMonkey;

/**
 * InjectChaosCommand
 * CLI daemon to configure ChaosMonkey parameters and trigger immediate chaos testing scenarios.
 */
class InjectChaosCommand extends Command
{
    protected string $name = 'chaos:inject';
    protected string $description = 'Configure ChaosMonkey parameters and trigger resilience testing fault injections in staging environments';

    /**
     * Mandatory CLI SAPI Guarding to ensure zero-trust security execution.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "\033[32mINFO:\033[0m SPP Enterprise Chaos Engineering & Resilience Injector\n\n";

        $enable = null;
        $rate = null;
        $testFault = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--enable=')) {
                $enable = filter_var(substr($arg, 9), FILTER_VALIDATE_BOOLEAN);
            } elseif (str_starts_with($arg, '--rate=')) {
                $rate = (int)substr($arg, 7);
            } elseif ($arg === '--test') {
                $testFault = true;
            }
        }

        if ($enable !== null) {
            $rateVal = $rate ?? 5;
            ChaosMonkey::configure($enable, $rateVal);
            echo "\033[32mSUCCESS:\033[0m ChaosMonkey configuration updated. Enabled: " . ($enable ? 'true' : 'false') . ", Injection Rate: {$rateVal}%\n\n";
        }

        $config = ChaosMonkey::getConfig();
        echo "Active ChaosMonkey Configuration:\n";
        echo "--------------------------------------------------------------------------------\n";
        echo sprintf("%-20s : %s\n", "Status", $config['enabled'] ? "\033[32mENABLED\033[0m" : "\033[31mDISABLED\033[0m");
        echo sprintf("%-20s : %s%%\n", "Injection Rate", $config['injection_rate_percentage']);
        echo sprintf("%-20s : %s\n", "Available Faults", implode(', ', $config['fault_types']));
        echo "--------------------------------------------------------------------------------\n";

        if ($testFault) {
            echo "\n\033[33mWARNING:\033[0m Initiating direct test fault injection simulation...\n";
            try {
                // Force injection by configuring 100% rate temporarily
                ChaosMonkey::configure(true, 100);
                ChaosMonkey::injectChaos('cli_resilience_test');
            } catch (\Exception $e) {
                echo "\033[31m[Simulated Fault Caught]\033[0m: " . $e->getMessage() . "\n";
                echo "\033[32mSUCCESS:\033[0m Resilience recovery paths fully engaged.\n";
            }
        }
    }
}
