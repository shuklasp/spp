<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;
use SPP\SPPEvent;
use SPP\EventParams;

/**
 * ModuleManager
 * Discovers, boots, and manages app-level and project-level extensions for SPPDocs.
 * Provides lifecycle hook dispatching (`invokeAll`, `invokeAlter`) and Dual Event Bus integration.
 */
class ModuleManager
{
    private static array $bootedProjects = [];
    private static array $loadedInstances = [];

    /**
     * Get system/app modules directory.
     */
    public static function getAppModulesDir(): string
    {
        return dirname(__DIR__) . '/modules';
    }

    /**
     * Get project-specific modules directory.
     */
    public static function getProjectModulesDir(string $projectId): string
    {
        return dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/modules';
    }

    /**
     * Get the active modules configuration file path for a project.
     */
    public static function getActiveModulesFile(string $projectId): string
    {
        return dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/modules.yml';
    }

    /**
     * Discover all available modules across app-level and project-level folders.
     */
    public static function listAvailableModules(string $projectId): array
    {
        $modules = [];

        // 1. App-level modules (src/SPPDocs/modules/*)
        $appDir = self::getAppModulesDir();
        if (is_dir($appDir)) {
            foreach (glob($appDir . '/*', GLOB_ONLYDIR) as $dir) {
                $mod = self::parseModuleManifest($dir, 'app');
                if ($mod) {
                    $modules[$mod['id']] = $mod;
                }
            }
        }

        // 2. Project-level modules (docs/<project>/modules/*)
        $projDir = self::getProjectModulesDir($projectId);
        if (is_dir($projDir)) {
            foreach (glob($projDir . '/*', GLOB_ONLYDIR) as $dir) {
                $mod = self::parseModuleManifest($dir, 'project');
                if ($mod) {
                    $modules[$mod['id']] = $mod;
                }
            }
        }

        $active = self::getActiveModules($projectId);
        foreach ($modules as $id => &$m) {
            $m['enabled'] = in_array($id, $active, true);
        }

        ksort($modules);
        return $modules;
    }

    /**
     * Parse module.yml manifest from a directory.
     */
    private static function parseModuleManifest(string $dir, string $scope): ?array
    {
        $manifestFile = $dir . '/module.yml';
        if (!file_exists($manifestFile)) {
            return null;
        }

        $id = basename($dir);
        try {
            $parsed = Yaml::parseFile($manifestFile) ?: [];
            return [
                'id' => $parsed['id'] ?? $id,
                'name' => $parsed['name'] ?? ucfirst(str_replace(['_', '-'], ' ', $id)),
                'description' => $parsed['description'] ?? '',
                'version' => $parsed['version'] ?? '1.0.0',
                'category' => $parsed['category'] ?? 'General',
                'author' => $parsed['author'] ?? 'SPPDocs Contributor',
                'dependencies' => (array)($parsed['dependencies'] ?? []),
                'scope' => $scope,
                'path' => $dir,
                'init_file' => file_exists($dir . '/modinit.php') ? $dir . '/modinit.php' : (file_exists($dir . '/module.php') ? $dir . '/module.php' : null),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get list of active module IDs for a project.
     */
    public static function getActiveModules(string $projectId): array
    {
        $file = self::getActiveModulesFile($projectId);
        if (file_exists($file)) {
            try {
                $parsed = Yaml::parseFile($file);
                if (!empty($parsed['active_modules']) && is_array($parsed['active_modules'])) {
                    return array_values(array_unique(array_filter($parsed['active_modules'])));
                }
            } catch (\Throwable $e) {}
        }

        // Fallback default modules for project
        return ['reading_time', 'slack_webhook'];
    }

    /**
     * Check if a module is currently enabled for a project.
     */
    public static function isModuleEnabled(string $projectId, string $moduleId): bool
    {
        $active = self::getActiveModules($projectId);
        return in_array($moduleId, $active, true);
    }

    /**
     * Enable a module for a project.
     */
    public static function enableModule(string $projectId, string $moduleId): bool
    {
        $active = self::getActiveModules($projectId);
        if (!in_array($moduleId, $active, true)) {
            $active[] = $moduleId;
            return self::saveActiveModules($projectId, $active);
        }
        return true;
    }

    /**
     * Disable a module for a project.
     */
    public static function disableModule(string $projectId, string $moduleId): bool
    {
        $active = self::getActiveModules($projectId);
        $key = array_search($moduleId, $active, true);
        if ($key !== false) {
            unset($active[$key]);
            return self::saveActiveModules($projectId, array_values($active));
        }
        return true;
    }

    /**
     * Persist active modules configuration to YAML.
     */
    private static function saveActiveModules(string $projectId, array $active): bool
    {
        $file = self::getActiveModulesFile($projectId);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = [
            'project' => $projectId,
            'active_modules' => array_values(array_unique($active)),
            'updated_at' => time(),
        ];

        return (bool)@file_put_contents($file, Yaml::dump($payload, 4, 2), LOCK_EX);
    }

    /**
     * Boot all active modules for a project.
     */
    public static function bootModules(string $projectId): void
    {
        if (isset(self::$bootedProjects[$projectId])) {
            return;
        }
        self::$bootedProjects[$projectId] = true;

        $available = self::listAvailableModules($projectId);
        $activeIds = self::getActiveModules($projectId);

        foreach ($activeIds as $modId) {
            if (isset($available[$modId])) {
                $mod = $available[$modId];
                if (!empty($mod['init_file']) && file_exists($mod['init_file'])) {
                    try {
                        $returned = require_once $mod['init_file'];
                        if (is_object($returned)) {
                            self::$loadedInstances[$projectId][$modId] = $returned;
                        }
                    } catch (\Throwable $e) {
                        error_log("[SPPDocs Module Boot Error] ({$modId}): " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Register a runtime module instance for hooks.
     */
    public static function registerModuleInstance(string $projectId, string $moduleId, object $instance): void
    {
        self::$loadedInstances[$projectId][$moduleId] = $instance;
    }

    /**
     * Invoke a hook across all active modules.
     * Integrates with core \SPP\Hook kernel bus.
     */
    public static function invokeAll(string $projectId, string $hook, array $args = []): array
    {
        self::bootModules($projectId);
        $results = [];

        // 1. Core Framework Kernel Hook Bus
        if (class_exists(\SPP\Hook::class)) {
            $hookArgs = array_merge(['project_id' => $projectId], $args);
            $coreResults = \SPP\Hook::invokeAll("sppdocs.{$hook}", $hookArgs);
            if (!empty($coreResults)) {
                $results = array_merge($results, $coreResults);
            }
        } elseif (class_exists(SPPEvent::class)) {
            try {
                $eventParams = new EventParams(array_merge(['project_id' => $projectId], $args));
                SPPEvent::fireEvent("sppdocs.{$hook}", $eventParams);
                SPPEvent::triggerHook("sppdocs:{$hook}", $args);
            } catch (\Throwable $e) {}
        }

        // 2. Invoke method on loaded module instances
        $instances = self::$loadedInstances[$projectId] ?? [];
        $method = 'hook_' . $hook;

        $callArgs = (!empty($args) && array_keys($args) !== range(0, count($args) - 1))
            ? [$args]
            : array_values($args);

        foreach ($instances as $modId => $inst) {
            if (method_exists($inst, $method)) {
                try {
                    $res = call_user_func_array([$inst, $method], $callArgs);
                    if ($res !== null) {
                        $results[$modId] = $res;
                    }
                } catch (\Throwable $e) {
                    error_log("[SPPDocs Hook Exception] ({$modId}::{$method}): " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Alter data through all active modules.
     * Integrates with core \SPP\Hook kernel bus.
     */
    public static function invokeAlter(string $projectId, string $hook, mixed &$data, mixed $context = null): void
    {
        self::bootModules($projectId);

        // 1. Invoke on active module instances
        $instances = self::$loadedInstances[$projectId] ?? [];
        $method = 'hook_' . $hook . '_alter';

        foreach ($instances as $modId => $inst) {
            if (method_exists($inst, $method)) {
                try {
                    $inst->$method($data, $context);
                } catch (\Throwable $e) {
                    error_log("[SPPDocs Alter Exception] ({$modId}::{$method}): " . $e->getMessage());
                }
            }
        }

        // 2. Core Framework Kernel Hook Bus
        if (class_exists(\SPP\Hook::class)) {
            $alterContext = ['project_id' => $projectId, 'context' => $context];
            \SPP\Hook::invokeAlter("sppdocs.{$hook}", $data, $alterContext);
        }
    }
}