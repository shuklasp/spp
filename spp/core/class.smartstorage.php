<?php

namespace SPP\Core;

use Symfony\Component\Yaml\Yaml;

/**
 * Class SmartStorage
 * 
 * Intent-based declarative storage manager. Dynamically categorizes data and routes
 * operations to the optimal storage driver (redis_shared, file_shared, local, flysystem)
 * automatically based on intent, file extensions, key prefixes, or custom application rules.
 */
class SmartStorage
{
    /** @var array */
    private static array $customRules = [];

    /** @var bool */
    private static bool $rulesLoaded = false;

    /**
     * Register a custom routing rule dynamically.
     */
    public static function registerRule(string $category, string $diskName, array $matchPrefixes = [], array $matchExtensions = []): void
    {
        self::$customRules[$category] = [
            'disk' => $diskName,
            'match_prefix' => $matchPrefixes,
            'match_extension' => $matchExtensions
        ];
    }

    /**
     * Save rules configuration directly to the active application's etc directory (storage_rules.yml).
     */
    public static function saveRulesConfig(array $rules): bool
    {
        if (!class_exists('\SPP\App')) {
            return false;
        }
        $app = \SPP\App::getApp();
        $etcDir = $app->getAppConfDir();
        if (!is_dir($etcDir)) {
            @mkdir($etcDir, 0777, true);
        }
        $filePath = $etcDir . DIRECTORY_SEPARATOR . 'storage_rules.yml';
        $yamlContent = Yaml::dump(['smart_storage' => ['rules' => $rules]], 4, 2);
        $result = file_put_contents($filePath, $yamlContent);
        if ($result !== false) {
            self::$customRules = array_merge(self::$customRules, $rules);
            self::$rulesLoaded = true;
            return true;
        }
        return false;
    }

    /**
     * Load static rules configuration from the active application's etc directory or SPPConfig.
     */
    public static function loadConfigRules(): void
    {
        if (self::$rulesLoaded) {
            return;
        }

        if (class_exists('\SPP\App')) {
            $app = \SPP\App::getApp();
            $etcDir = $app->getAppConfDir();
            $filePath = $etcDir . DIRECTORY_SEPARATOR . 'storage_rules.yml';
            if (file_exists($filePath)) {
                try {
                    $parsed = Yaml::parseFile($filePath);
                    if (isset($parsed['smart_storage']['rules']) && is_array($parsed['smart_storage']['rules'])) {
                        foreach ($parsed['smart_storage']['rules'] as $cat => $rule) {
                            self::$customRules[$cat] = $rule;
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore parse errors
                }
            }
        }

        if (class_exists('\SPP\SPPConfig', false)) {
            $configRules = \SPP\SPPConfig::get('smart_storage.rules');
            if (is_array($configRules)) {
                foreach ($configRules as $cat => $rule) {
                    self::$customRules[$cat] = $rule;
                }
            }
        }

        self::$rulesLoaded = true;
    }

    /**
     * Determine the optimal storage disk based on category, key prefix, or file extension.
     */
    public static function getDiskForCategory(?string $category, string $key): DiskInterface
    {
        self::loadConfigRules();

        // 1. Check if an explicit category matches custom rules
        if ($category !== null && isset(self::$customRules[$category])) {
            return Storage::disk(self::$customRules[$category]['disk']);
        }

        // 2. Check if key matches custom rule prefixes or extensions
        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        foreach (self::$customRules as $cat => $rule) {
            if (!empty($rule['match_prefix'])) {
                foreach ($rule['match_prefix'] as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        return Storage::disk($rule['disk']);
                    }
                }
            }
            if (!empty($rule['match_extension']) && $extension !== '') {
                if (in_array($extension, $rule['match_extension'], true)) {
                    return Storage::disk($rule['disk']);
                }
            }
        }

        // 3. Default core routing rules table
        if ($category === 'ephemeral') {
            return Storage::disk('redis_shared');
        }
        if ($category === 'shared_config') {
            return Storage::disk('file_shared');
        }
        if ($category === 'media') {
            return Storage::disk('local');
        }
        if ($category === 'archive') {
            return Storage::disk('flysystem');
        }

        // 4. Fallback auto-inference by prefix / extension
        $ephemeralPrefixes = ['sess_', 'temp_', 'cache_', 'tok_'];
        foreach ($ephemeralPrefixes as $p) {
            if (str_starts_with($key, $p)) {
                return Storage::disk('redis_shared');
            }
        }

        if (str_starts_with($key, 'manifest')) {
            return Storage::disk('file_shared');
        }
        if (str_starts_with($key, 'backup')) {
            return Storage::disk('flysystem');
        }

        if ($extension !== '') {
            if (in_array($extension, ['json', 'yml', 'yaml', 'ini'], true)) {
                return Storage::disk('file_shared');
            }
            if (in_array($extension, ['jpg', 'png', 'pdf', 'mp4', 'csv', 'txt'], true)) {
                return Storage::disk('local');
            }
            if (in_array($extension, ['zip', 'tar', 'gz', 'sql'], true)) {
                return Storage::disk('flysystem');
            }
        }

        // Default fallback to local disk
        return Storage::disk('local');
    }

    public static function put(string $key, string $contents, ?string $category = null): bool
    {
        $disk = self::getDiskForCategory($category, $key);
        return $disk->put($key, $contents);
    }

    public static function get(string $key, ?string $category = null): ?string
    {
        // Primary lookup via expected disk
        $disk = self::getDiskForCategory($category, $key);
        if ($disk->exists($key)) {
            return $disk->get($key);
        }

        // Multi-disk fallback lookup if not found in primary disk
        $disks = ['local', 'file_shared', 'redis_shared', 'flysystem'];
        foreach ($disks as $d) {
            try {
                $fallbackDisk = Storage::disk($d);
                if ($fallbackDisk->exists($key)) {
                    return $fallbackDisk->get($key);
                }
            } catch (\Throwable $e) {
                // Skip disks that are unavailable (e.g. flysystem if not configured)
            }
        }

        return null;
    }

    public static function exists(string $key, ?string $category = null): bool
    {
        $disk = self::getDiskForCategory($category, $key);
        if ($disk->exists($key)) {
            return true;
        }

        $disks = ['local', 'file_shared', 'redis_shared', 'flysystem'];
        foreach ($disks as $d) {
            try {
                if (Storage::disk($d)->exists($key)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Skip
            }
        }

        return false;
    }

    public static function delete(string $key, ?string $category = null): bool
    {
        $disk = self::getDiskForCategory($category, $key);
        if ($disk->exists($key)) {
            return $disk->delete($key);
        }

        $disks = ['local', 'file_shared', 'redis_shared', 'flysystem'];
        foreach ($disks as $d) {
            try {
                $fallbackDisk = Storage::disk($d);
                if ($fallbackDisk->exists($key)) {
                    return $fallbackDisk->delete($key);
                }
            } catch (\Throwable $e) {
                // Skip
            }
        }

        return false;
    }

    public static function readStream(string $key, ?string $category = null)
    {
        $disk = self::getDiskForCategory($category, $key);
        if ($disk->exists($key)) {
            return $disk->readStream($key);
        }

        $disks = ['local', 'file_shared', 'redis_shared', 'flysystem'];
        foreach ($disks as $d) {
            try {
                $fallbackDisk = Storage::disk($d);
                if ($fallbackDisk->exists($key)) {
                    return $fallbackDisk->readStream($key);
                }
            } catch (\Throwable $e) {
                // Skip
            }
        }

        return null;
    }

    public static function writeStream(string $key, $resource, ?string $category = null): bool
    {
        $disk = self::getDiskForCategory($category, $key);
        return $disk->writeStream($key, $resource);
    }
}
