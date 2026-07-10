<?php

namespace SPPMod\SPPFeatureFlags;

use SPPMod\SPPReport\Services\OpenTelemetryExporter;

/**
 * FeatureManager
 * Advanced Feature Flags and Canary Experimentation Engine. Supports percentage-based canary rollouts,
 * user group targeting (sppgroup), and automated Telemetry Kill Switch integration.
 */
class FeatureManager
{
    private static array $flags = [
        'new_checkout_flow' => [
            'enabled' => true,
            'canary_percentage' => 25, // 25% of traffic
            'target_groups' => ['beta_testers', 'enterprise_admins'],
            'kill_switch_threshold_errors' => 5 // Disable if more than 5 errors recorded in current telemetry window
        ],
        'multi_region_active_sync' => [
            'enabled' => true,
            'canary_percentage' => 10,
            'target_groups' => ['enterprise_admins'],
            'kill_switch_threshold_errors' => 3
        ]
    ];

    public static function isEnabled(string $flagName, ?string $userId = null, array $userGroups = []): bool
    {
        if (!isset(self::$flags[$flagName])) {
            return false;
        }

        $flag = self::$flags[$flagName];
        if (!$flag['enabled']) {
            return false;
        }

        // 1. Evaluate Automated Telemetry Kill Switch
        if (class_exists('\\SPPMod\\SPPReport\\Services\\OpenTelemetryExporter')) {
            $recentErrors = OpenTelemetryExporter::getErrorCount($flagName);
            if ($recentErrors >= $flag['kill_switch_threshold_errors']) {
                // Telemetry Kill Switch Triggered! Automatically disable feature to protect production
                self::$flags[$flagName]['enabled'] = false;
                return false;
            }
        }

        // 2. Evaluate Target Groups
        if (!empty($flag['target_groups'])) {
            foreach ($userGroups as $group) {
                if (in_array($group, $flag['target_groups'], true)) {
                    return true;
                }
            }
        }

        // 3. Evaluate Canary Percentage Rollout using deterministic hashing
        if ($flag['canary_percentage'] > 0 && $userId !== null) {
            // Hash user ID to get a deterministic value between 0 and 99
            $hashValue = abs(crc32($flagName . '_' . $userId)) % 100;
            if ($hashValue < $flag['canary_percentage']) {
                return true;
            }
        }

        return false;
    }

    public static function setFlag(string $flagName, bool $enabled, int $canaryPercentage = 0, array $targetGroups = [], int $killSwitchThreshold = 5): void
    {
        self::$flags[$flagName] = [
            'enabled' => $enabled,
            'canary_percentage' => $canaryPercentage,
            'target_groups' => $targetGroups,
            'kill_switch_threshold_errors' => $killSwitchThreshold
        ];
    }

    public static function listFlags(): array
    {
        return self::$flags;
    }
}
