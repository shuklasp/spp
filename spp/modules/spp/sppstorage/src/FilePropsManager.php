<?php

namespace SPPMod\SPPStorage;

use Symfony\Component\Yaml\Yaml;
use Throwable;

if (!class_exists('Symfony\Component\Yaml\Yaml')) {
    $autoload = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/vendor/autoload.php' : dirname(__DIR__, 5) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }
}

/**
 * Class FilePropsManager
 *
 * Centralized, high-performance engine for managing per-directory manifests (__spp_file_props.yml).
 * Converts the SPP filesystem into a living, queryable semantic knowledge graph and governance ledger.
 *
 * Features:
 * - Hierarchical metadata inheritance (files inherit directory policies).
 * - Full CRUD operations with atomic YAML persistence.
 * - Auto-syncing: scans untracked files and generates manifest stubs with AST/docblock heuristics.
 * - Linting & orphan detection: verifies file existence and schema integrity.
 * - Auditing: tracks technical debt, deprecations, and expired review dates.
 * - Zero-I/O production caching: compiles manifests into array cache and SQLite binary index.
 * - Dual event bus notification (SPPEvent::fireEvent + SPPEvent::triggerHook).
 */
class FilePropsManager
{
    public const MANIFEST_FILENAME = '__spp_file_props.yml';
    public const VALID_STABILITIES = ['draft', 'experimental', 'beta', 'stable', 'deprecated', 'sunset'];
    public const DEFAULT_IGNORED_DIRS = [
        '.git', '.svn', '.hg', 'vendor', 'node_modules', 'var', 'cache',
        'tmp', 'logs', '.dist', 'dist', '.agents', '.gemini',
        'storage', 'sessions', 'scratch', '.vs', '.vscode', '.idea',
        '.phpdoc', '.VSCodeCounter', '__pycache__', '.pytest_cache',
        'bin', 'obj'
    ];

    protected static array $runtimeCache = [];
    protected static ?array $compiledCache = null;

    /**
     * Clears in-memory runtime cache for a specific manifest or entire workspace.
     */
    public static function clearRuntimeCache(?string $manifestFile = null): void
    {
        if ($manifestFile !== null) {
            unset(self::$runtimeCache[$manifestFile]);
        } else {
            self::$runtimeCache = [];
            self::$compiledCache = null;
        }
    }

    /**
     * Resolves an absolute path safely within the workspace.
     */
    public static function resolveSafePath(string $path): ?string
    {
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 5));
        if (!$baseDir) {
            return null;
        }

        // Check if path is already absolute (Windows drive letter or Unix root slash)
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            $real = realpath($path);
            if ($real && str_starts_with($real, $baseDir)) {
                return $real;
            }
            $parent = dirname($path);
            if (file_exists($parent)) {
                $realParent = realpath($parent);
                if ($realParent && str_starts_with($realParent, $baseDir)) {
                    return $path;
                }
            }
            return file_exists($path) ? $path : null;
        }

        $trimmed = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $fullPath = $baseDir . ($trimmed !== '' ? DIRECTORY_SEPARATOR . $trimmed : '');

        if (file_exists($fullPath)) {
            $real = realpath($fullPath);
            if ($real && str_starts_with($real, $baseDir)) {
                return $real;
            }
        }

        // Path might be a new file not yet created on disk
        $parent = dirname($fullPath);
        if (file_exists($parent)) {
            $realParent = realpath($parent);
            if ($realParent && str_starts_with($realParent, $baseDir)) {
                return $fullPath;
            }
        }

        return $fullPath;
    }

    /**
     * Get the relative path of any file from the workspace base directory.
     */
    public static function getRelativePath(string $path): string
    {
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 5));
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
        if ($baseDir) {
            $normalizedBase = rtrim(str_replace('\\', '/', $baseDir), '/');
            if ($normalizedPath === $normalizedBase) {
                return '.';
            }
            if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
                return substr($normalizedPath, strlen($normalizedBase) + 1);
            }
        }
        return ltrim($normalizedPath, '/');
    }

    /**
     * Retrieves the manifest file path for a given directory or parent of a file.
     */
    public static function getManifestPath(string $path): string
    {
        $safe = self::resolveSafePath($path) ?: $path;
        $dir = is_dir($safe) ? $safe : dirname($safe);
        return $dir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
    }

    /**
     * Loads and parses a directory's __spp_file_props.yml manifest.
     */
    public static function loadManifest(string $dirPath): array
    {
        $manifestPath = is_dir($dirPath)
            ? $dirPath . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME
            : $dirPath;

        if (isset(self::$runtimeCache[$manifestPath])) {
            return self::$runtimeCache[$manifestPath];
        }

        if (!file_exists($manifestPath) || !is_file($manifestPath)) {
            return [
                'version' => '1.0',
                'directory' => [
                    'description' => '',
                    'usage' => '',
                    'attributes' => ['tags' => [], 'author' => '', 'stability' => 'stable']
                ],
                'files' => [],
                'directories' => []
            ];
        }

        try {
            $content = @file_get_contents($manifestPath);
            if ($content !== false) {
                $parsed = Yaml::parse($content);
            } else {
                $parsed = Yaml::parseFile($manifestPath);
            }
            if (!is_array($parsed)) {
                $parsed = [];
            }
        } catch (Throwable $e) {
            $parsed = [];
        }

        // Normalize schema structure while strictly preserving unknown root keys
        $normalized = is_array($parsed) ? $parsed : [];
        $normalized['version'] = (string)($parsed['version'] ?? '1.0');
        if (!isset($normalized['directory']) || !is_array($normalized['directory'])) {
            $normalized['directory'] = [];
        }
        if (!isset($normalized['files']) || !is_array($normalized['files'])) {
            $normalized['files'] = [];
        }
        if (!isset($normalized['directories']) || !is_array($normalized['directories'])) {
            $normalized['directories'] = [];
        }

        // Directory metadata
        if (isset($parsed['directory']) && is_array($parsed['directory'])) {
            $normalized['directory'] = $parsed['directory'];
        } elseif (isset($parsed['description']) && is_string($parsed['description'])) {
            $normalized['directory'] = [
                'description' => $parsed['description'],
                'usage' => $parsed['usage'] ?? '',
                'attributes' => $parsed['attributes'] ?? []
            ];
        } else {
            $normalized['directory'] = [
                'description' => '',
                'usage' => '',
                'attributes' => ['tags' => [], 'author' => '', 'stability' => 'stable']
            ];
        }

        // Files
        if (isset($parsed['files']) && is_array($parsed['files'])) {
            foreach ($parsed['files'] as $fname => $fdata) {
                $normalized['files'][$fname] = self::normalizeEntry($fdata);
            }
        }

        // Directories
        if (isset($parsed['directories']) && is_array($parsed['directories'])) {
            foreach ($parsed['directories'] as $dname => $ddata) {
                $normalized['directories'][$dname] = self::normalizeEntry($ddata);
            }
        }

        // Handle legacy flat key entries (e.g. index.php at top level)
        foreach ($parsed as $key => $val) {
            if (in_array($key, ['version', 'directory', 'files', 'directories', 'description', 'usage', 'attributes'])) {
                continue;
            }
            if (is_array($val) || is_string($val)) {
                if (str_ends_with($key, '/')) {
                    $dname = rtrim($key, '/');
                    if (!isset($normalized['directories'][$dname])) {
                        $normalized['directories'][$dname] = self::normalizeEntry($val);
                    }
                } else {
                    if (!isset($normalized['files'][$key])) {
                        $normalized['files'][$key] = self::normalizeEntry($val);
                    }
                }
            }
        }

        self::$runtimeCache[$manifestPath] = $normalized;
        return $normalized;
    }

    /**
     * Recursively discovers and loads all __spp_file_props.yml manifests under a directory.
     *
     * @param string $startDir
     * @return array Map of relative directory path to parsed manifest array
     */
    public static function scanAllManifests(string $startDir): array
    {
        $manifests = [];
        $realStart = realpath($startDir);
        if ($realStart === false || !is_dir($realStart)) {
            return [];
        }

        // Check startDir root first
        $rootManifest = $realStart . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        if (file_exists($rootManifest)) {
            $relRoot = self::getRelativePath($realStart);
            $manifests[$relRoot] = self::loadManifest($realStart);
        }

        $dirIterator = new \RecursiveDirectoryIterator($realStart, \FilesystemIterator::SKIP_DOTS);
        $filterIterator = new \RecursiveCallbackFilterIterator($dirIterator, function ($current, $key, $iterator) {
            if ($current->isDir()) {
                $filename = $current->getFilename();
                if (in_array($filename, self::DEFAULT_IGNORED_DIRS, true) || str_starts_with($filename, '.')) {
                    return false;
                }
            }
            return true;
        });

        $iterator = new \RecursiveIteratorIterator($filterIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getFilename() === self::MANIFEST_FILENAME) {
                $dirPath = dirname($item->getPathname());
                $relDir = self::getRelativePath($dirPath);
                $manifests[$relDir] = self::loadManifest($dirPath);
            }
        }

        return $manifests;
    }

    /**
     * Normalizes an individual file or directory property record.
     */
    protected static function normalizeEntry(array|string|null $entry): array
    {
        if (is_string($entry)) {
            return [
                'description' => trim($entry),
                'usage' => '',
                'stability' => 'stable',
                'author' => '',
                'review_date' => '',
                'attributes' => ['tags' => []]
            ];
        }

        if (!is_array($entry)) {
            return [
                'description' => '',
                'usage' => '',
                'stability' => 'stable',
                'author' => '',
                'review_date' => '',
                'attributes' => ['tags' => []]
            ];
        }

        $desc = trim((string)($entry['description'] ?? ($entry['comment'] ?? '')));
        $usage = trim((string)($entry['usage'] ?? ''));
        $stability = strtolower(trim((string)($entry['stability'] ?? ($entry['attributes']['stability'] ?? 'stable'))));
        if (!in_array($stability, self::VALID_STABILITIES, true)) {
            $stability = 'stable';
        }
        $author = trim((string)($entry['author'] ?? ($entry['attributes']['author'] ?? '')));
        $reviewDate = trim((string)($entry['review_date'] ?? ($entry['attributes']['review_date'] ?? '')));

        $rawTags = $entry['tags'] ?? ($entry['attributes']['tags'] ?? []);
        if (is_string($rawTags)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $rawTags))));
        } elseif (is_array($rawTags)) {
            $tags = array_values(array_filter(array_map('trim', $rawTags)));
        } else {
            $tags = [];
        }

        $attributes = is_array($entry['attributes'] ?? null) ? $entry['attributes'] : [];
        $attributes['tags'] = $tags;
        if ($author && empty($attributes['author'])) {
            $attributes['author'] = $author;
        }
        if ($stability && empty($attributes['stability'])) {
            $attributes['stability'] = $stability;
        }
        if ($reviewDate && empty($attributes['review_date'])) {
            $attributes['review_date'] = $reviewDate;
        }

        $record = [
            'description' => $desc,
            'usage' => $usage,
            'stability' => $stability,
            'author' => $author,
            'review_date' => $reviewDate,
            'attributes' => $attributes
        ];

        foreach ($entry as $k => $v) {
            if (!isset($record[$k])) {
                $record[$k] = $v;
            }
        }

        return $record;
    }

    /**
     * Retrieves properties for a specific file, optionally inheriting directory-level policies.
     */
    public static function getFileProps(string $path, bool $inherit = true): array
    {
        $safe = self::resolveSafePath($path) ?: $path;
        $parentDir = dirname($safe);
        $fileName = basename($safe);
        $isDir = is_dir($safe);

        $manifest = self::loadManifest($parentDir);
        $typeKey = $isDir ? 'directories' : 'files';
        $entry = $manifest[$typeKey][$fileName] ?? null;

        if ($isDir && $entry === null) {
            $subManifestPath = $safe . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
            if (file_exists($subManifestPath)) {
                $subManifest = self::loadManifest($safe);
                if (!empty($subManifest['directory']) && (!empty($subManifest['directory']['description']) || !empty($subManifest['directory']['usage']) || !empty($subManifest['directory']['attributes']['tags']))) {
                    $entry = $subManifest['directory'];
                }
            }
        }

        $hasProps = ($entry !== null);
        $record = $entry ?: [
            'description' => '',
            'usage' => '',
            'stability' => 'stable',
            'author' => '',
            'review_date' => '',
            'attributes' => ['tags' => [], 'author' => '', 'stability' => 'stable']
        ];

        if ($inherit && $parentDir) {
            $inheritedAttrs = self::getInheritedDirectoryAttributes($parentDir);
            $recordTags = $record['attributes']['tags'] ?? [];
            $inheritedTags = $inheritedAttrs['tags'] ?? [];
            $mergedTags = array_values(array_unique(array_merge($inheritedTags, $recordTags)));

            $record['attributes'] = array_merge($inheritedAttrs, $record['attributes']);
            $record['attributes']['tags'] = $mergedTags;

            if (empty($record['author']) && !empty($record['attributes']['author'])) {
                $record['author'] = $record['attributes']['author'];
            }
            if (empty($record['stability']) || $record['stability'] === 'stable') {
                if (!empty($record['attributes']['stability'])) {
                    $record['stability'] = $record['attributes']['stability'];
                }
            }
        }

        $recordTags = $record['attributes']['tags'] ?? [];

        $res = [
            'success' => true,
            'path' => self::getRelativePath($safe),
            'name' => $fileName,
            'is_dir' => $isDir,
            'has_props' => $hasProps,
            'description' => $record['description'],
            'usage' => $record['usage'],
            'stability' => $record['stability'],
            'author' => $record['author'],
            'review_date' => $record['review_date'],
            'tags' => $recordTags,
            'attributes' => $record['attributes']
        ];

        foreach ($record as $k => $v) {
            if (!isset($res[$k])) {
                $res[$k] = $v;
            }
        }

        return $res;
    }

    /**
     * Walks up parent directories to collect inherited directory-level attributes.
     */
    public static function getInheritedDirectoryAttributes(string $dirPath): array
    {
        $baseDir = realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 5));
        $current = realpath($dirPath);
        $attributesStack = [];

        while ($current && $baseDir && str_starts_with($current, $baseDir)) {
            $manifestPath = $current . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
            if (file_exists($manifestPath)) {
                $m = self::loadManifest($current);
                $dirMeta = $m['directory'] ?? [];
                $dirAttrs = is_array($dirMeta['attributes'] ?? null) ? $dirMeta['attributes'] : [];
                if (!empty($dirMeta['author']) && empty($dirAttrs['author'])) {
                    $dirAttrs['author'] = $dirMeta['author'];
                }
                if (!empty($dirMeta['stability']) && empty($dirAttrs['stability'])) {
                    $dirAttrs['stability'] = $dirMeta['stability'];
                }
                if (!empty($dirMeta['tags'])) {
                    $existingTags = $dirAttrs['tags'] ?? [];
                    $dirAttrs['tags'] = array_values(array_unique(array_merge($existingTags, (array)$dirMeta['tags'])));
                }
                if (!empty($dirAttrs)) {
                    $attributesStack[] = $dirAttrs;
                }
            }

            if ($current === $baseDir) {
                break;
            }
            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        // Merge from root down to deepest child
        $merged = [];
        $accumulatedTags = [];
        for ($i = count($attributesStack) - 1; $i >= 0; $i--) {
            if (!empty($attributesStack[$i]['tags']) && is_array($attributesStack[$i]['tags'])) {
                $accumulatedTags = array_merge($accumulatedTags, $attributesStack[$i]['tags']);
            }
            $merged = array_merge($merged, $attributesStack[$i]);
        }
        if (!empty($accumulatedTags)) {
            $merged['tags'] = array_values(array_unique($accumulatedTags));
        }

        return $merged;
    }

    /**
     * Convenience alias for saving file properties.
     */
    public static function setFileProps(string $path, array|string $propsData): bool
    {
        $res = self::saveFileProps($path, $propsData);
        return !empty($res['success']);
    }

    /**
     * Convenience alias for deleting file properties.
     */
    public static function deleteFileProps(string $path): bool
    {
        return self::removeFileProps($path);
    }

    /**
     * Atomically writes a manifest array to a YAML file with rollback protection.
     */
    public static function writeManifestAtomic(string $manifestFile, array $manifestData): bool
    {
        $dir = dirname($manifestFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        try {
            $yaml = Yaml::dump($manifestData, 6, 2);
        } catch (Throwable $e) {
            return false;
        }

        $tmpFile = $dir . DIRECTORY_SEPARATOR . '.__tmp_props_' . bin2hex(random_bytes(6)) . '.yml';
        if (@file_put_contents($tmpFile, $yaml, LOCK_EX) === false) {
            return false;
        }
        @chmod($tmpFile, 0664);

        if (file_exists($manifestFile)) {
            // Keep a stable .bak snapshot before modifying
            @copy($manifestFile, $manifestFile . '.bak');

            $bakFile = $manifestFile . '.bak.' . uniqid('', true);
            if (!@rename($manifestFile, $bakFile)) {
                // If rename fails, try direct overwrite
                $res = @file_put_contents($manifestFile, $yaml, LOCK_EX);
                @unlink($tmpFile);
                unset(self::$runtimeCache[$manifestFile]);
                return $res !== false;
            }
            if (!@rename($tmpFile, $manifestFile)) {
                @rename($bakFile, $manifestFile);
                @unlink($tmpFile);
                return false;
            }
            @unlink($bakFile);
        } else {
            if (!@rename($tmpFile, $manifestFile)) {
                $res = @file_put_contents($manifestFile, $yaml, LOCK_EX);
                @unlink($tmpFile);
                unset(self::$runtimeCache[$manifestFile]);
                return $res !== false;
            }
        }

        unset(self::$runtimeCache[$manifestFile]);
        return true;
    }

    /**
     * Saves properties for a file or directory into its parent's __spp_file_props.yml.
     */
    public static function saveFileProps(string $path, array|string $propsData): array
    {
        $safe = self::resolveSafePath($path) ?: $path;
        $parentDir = dirname($safe);
        $name = basename($safe);
        $isDir = is_dir($safe);

        if (!is_dir($parentDir)) {
            @mkdir($parentDir, 0777, true);
        }

        $manifestFile = $parentDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        $manifest = self::loadManifest($parentDir);
        $typeKey = $isDir ? 'directories' : 'files';

        // Preserve existing custom attributes and unknown keys
        $existingEntry = $manifest[$typeKey][$name] ?? [];
        if (is_array($propsData) && is_array($existingEntry)) {
            $mergedInput = array_merge($existingEntry, $propsData);
            if (isset($existingEntry['attributes']) && is_array($existingEntry['attributes']) && isset($propsData['attributes']) && is_array($propsData['attributes'])) {
                $mergedInput['attributes'] = array_merge($existingEntry['attributes'], $propsData['attributes']);
            }
            $normalizedEntry = self::normalizeEntry($mergedInput);
        } else {
            $normalizedEntry = self::normalizeEntry($propsData);
        }

        $manifest[$typeKey][$name] = $normalizedEntry;

        try {
            $saved = self::writeManifestAtomic($manifestFile, $manifest);
            if (!$saved) {
                return ['success' => false, 'error' => 'Failed to atomically persist parent manifest: ' . $manifestFile];
            }

            if ($isDir && file_exists($safe . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME)) {
                $childManifestFile = $safe . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
                $childManifest = self::loadManifest($safe);
                $childManifest['directory'] = $normalizedEntry;
                self::writeManifestAtomic($childManifestFile, $childManifest);
            }

            $payload = [
                'file' => self::getRelativePath($safe),
                'name' => $name,
                'is_dir' => $isDir,
                'properties' => $normalizedEntry
            ];

            if (class_exists('\\SPP\\SPPEvent')) {
                \SPP\SPPEvent::fireEvent('file_props.saved', $payload);
                \SPP\SPPEvent::triggerHook('file_props:saved', $payload);
            }

            return [
                'success' => true,
                'message' => 'Properties saved successfully.',
                'manifest_file' => $manifestFile,
                'properties' => $normalizedEntry
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Failed to save properties manifest: ' . $e->getMessage()];
        }
    }

    /**
     * Saves directory-level metadata (description and attributes) to that directory's __spp_file_props.yml.
     */
    public static function saveDirectoryProps(string $dirPath, array $propsData): array
    {
        $safe = self::resolveSafePath($dirPath) ?: $dirPath;
        if (!is_dir($safe)) {
            @mkdir($safe, 0777, true);
        }

        $manifestFile = $safe . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        $manifest = self::loadManifest($safe);

        $existingDir = $manifest['directory'] ?? [];
        $desc = trim((string)($propsData['description'] ?? ($existingDir['description'] ?? '')));
        $usage = trim((string)($propsData['usage'] ?? ($existingDir['usage'] ?? '')));
        $attrs = is_array($propsData['attributes'] ?? null)
            ? array_merge((array)($existingDir['attributes'] ?? []), $propsData['attributes'])
            : ((array)($existingDir['attributes'] ?? []));

        // Preserve existing and custom directory fields (author, stability, review_date, security, etc.)
        $dirRecord = array_merge($existingDir, $propsData);
        $dirRecord['description'] = $desc;
        $dirRecord['usage'] = $usage;
        $dirRecord['attributes'] = $attrs;
        if (!empty($propsData['stability'])) {
            $dirRecord['stability'] = $propsData['stability'];
        }
        if (!empty($propsData['author'])) {
            $dirRecord['author'] = $propsData['author'];
        }
        if (!empty($propsData['review_date'])) {
            $dirRecord['review_date'] = $propsData['review_date'];
        }
        if (isset($propsData['security'])) {
            $dirRecord['security'] = $propsData['security'];
        }

        $manifest['directory'] = $dirRecord;

        try {
            $saved = self::writeManifestAtomic($manifestFile, $manifest);
            if (!$saved) {
                return ['success' => false, 'error' => 'Failed to atomically persist directory manifest: ' . $manifestFile];
            }

            // Also synchronize in parent directory manifest if tracked there
            $parentDir = dirname($safe);
            $dirName = basename($safe);
            $parentManifestFile = $parentDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
            if (file_exists($parentManifestFile)) {
                $parentManifest = self::loadManifest($parentDir);
                if (isset($parentManifest['directories'][$dirName])) {
                    $parentManifest['directories'][$dirName] = self::normalizeEntry($dirRecord);
                    self::writeManifestAtomic($parentManifestFile, $parentManifest);
                }
            }

            $payload = [
                'directory' => self::getRelativePath($safe),
                'manifest_file' => $manifestFile,
                'metadata' => $dirRecord
            ];
            if (class_exists('\\SPP\\SPPEvent')) {
                \SPP\SPPEvent::fireEvent('directory_props.saved', $payload);
                \SPP\SPPEvent::triggerHook('directory_props:saved', $payload);
            }

            return [
                'success' => true,
                'message' => 'Directory properties saved successfully.',
                'manifest_file' => $manifestFile,
                'directory' => $dirRecord
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Failed to save directory manifest: ' . $e->getMessage()];
        }
    }

    /**
     * Migrates a property entry when a file or directory is renamed.
     */
    public static function renameFileProps(string $oldRelativePath, string $newRelativePath): bool
    {
        $oldSafe = self::resolveSafePath($oldRelativePath) ?: $oldRelativePath;
        $newSafe = self::resolveSafePath($newRelativePath) ?: $newRelativePath;

        $oldParent = dirname($oldSafe);
        $newParent = dirname($newSafe);
        $oldName = basename($oldSafe);
        $newName = basename($newSafe);

        $oldManifestFile = $oldParent . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        if (!file_exists($oldManifestFile)) {
            return false;
        }

        $oldData = self::loadManifest($oldParent);
        $entry = null;
        $typeKey = 'files';

        if (isset($oldData['files'][$oldName])) {
            $entry = $oldData['files'][$oldName];
            $typeKey = 'files';
            unset($oldData['files'][$oldName]);
        } elseif (isset($oldData['directories'][$oldName])) {
            $entry = $oldData['directories'][$oldName];
            $typeKey = 'directories';
            unset($oldData['directories'][$oldName]);
        }

        if ($entry === null) {
            return false;
        }

        try {
            if ($oldParent === $newParent) {
                $oldData[$typeKey][$newName] = $entry;
                self::writeManifestAtomic($oldManifestFile, $oldData);
            } else {
                self::writeManifestAtomic($oldManifestFile, $oldData);

                $newData = self::loadManifest($newParent);
                $newData[$typeKey][$newName] = $entry;
                $newManifestFile = $newParent . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
                self::writeManifestAtomic($newManifestFile, $newData);
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Removes an entry from __spp_file_props.yml when an item is deleted.
     */
    public static function removeFileProps(string $relativePath): bool
    {
        $safe = self::resolveSafePath($relativePath) ?: $relativePath;
        $parent = dirname($safe);
        $name = basename($safe);

        $manifestFile = $parent . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        if (!file_exists($manifestFile)) {
            return false;
        }

        $data = self::loadManifest($parent);
        $changed = false;

        if (isset($data['files'][$name])) {
            unset($data['files'][$name]);
            $changed = true;
        }
        if (isset($data['directories'][$name])) {
            unset($data['directories'][$name]);
            $changed = true;
        }

        if ($changed) {
            try {
                self::writeManifestAtomic($manifestFile, $data);

                $payload = ['file' => self::getRelativePath($safe), 'name' => $name];
                if (class_exists('\\SPP\\SPPEvent')) {
                    \SPP\SPPEvent::fireEvent('file_props.removed', $payload);
                    \SPP\SPPEvent::triggerHook('file_props:removed', $payload);
                }

                return true;
            } catch (Throwable $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Checks whether a description string is empty or boilerplate generic.
     */
    public static function isGenericDescription(?string $desc, string $name = ''): bool
    {
        if (empty($desc)) {
            return true;
        }
        $desc = trim($desc);
        $nameNoExt = pathinfo($name, PATHINFO_FILENAME);
        if ($name !== '') {
            if (
                $desc === "File: {$name}" ||
                $desc === "Directory: {$name}" ||
                $desc === "Subdirectory: {$name}" ||
                $desc === "File: {$nameNoExt}" ||
                $desc === "Directory: {$nameNoExt}" ||
                $desc === "Subdirectory: {$nameNoExt}" ||
                $desc === $name ||
                $desc === $nameNoExt ||
                $desc === "Class {$nameNoExt}" ||
                $desc === "Interface {$nameNoExt}" ||
                $desc === "Trait {$nameNoExt}" ||
                strcasecmp($desc, $name) === 0 ||
                strcasecmp($desc, $nameNoExt) === 0
            ) {
                return true;
            }
        }
        if (str_starts_with($desc, 'File: ') || str_starts_with($desc, 'Directory: ') || str_starts_with($desc, 'Subdirectory: ')) {
            return true;
        }
        if (in_array(strtolower($desc), ['file', 'directory', 'folder', 'class', 'trait', 'interface', 'template', 'model', 'controller'], true)) {
            return true;
        }
        return false;
    }

    /**
     * Synchronizes a directory: discovers untracked files, prunes orphans, and registers intelligent metadata.
     */
    public static function syncDirectory(string $dirPath, bool $recursive = true, bool $pruneOrphans = true, bool $upgradeGeneric = false): array
    {
        $safe = self::resolveSafePath($dirPath) ?: $dirPath;
        if (!is_dir($safe)) {
            return ['success' => false, 'error' => "Directory not found: {$dirPath}"];
        }

        $baseName = basename($safe);
        if (in_array($baseName, self::DEFAULT_IGNORED_DIRS, true)) {
            return ['success' => true, 'directory' => self::getRelativePath($safe), 'synced' => 0, 'synced_files' => 0, 'synced_dirs' => 0, 'pruned' => 0, 'updated' => 0];
        }

        $syncedFiles = 0;
        $syncedDirs = 0;
        $prunedCount = 0;
        $updatedFiles = 0;
        $manifestPath = $safe . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        $manifest = self::loadManifest($safe);

        // Auto-populate or upgrade directory metadata
        if (empty($manifest['directory']['description']) || ($upgradeGeneric && self::isGenericDescription($manifest['directory']['description'], $baseName))) {
            $inferredDir = self::inferDirectoryMetadata($safe);
            if (!self::isGenericDescription($inferredDir['description'], $baseName)) {
                $manifest['directory']['description'] = $inferredDir['description'];
                if (empty($manifest['directory']['usage']) || $upgradeGeneric) {
                    $manifest['directory']['usage'] = $inferredDir['usage'];
                }
                $manifest['directory']['attributes'] = array_merge($inferredDir['attributes'] ?? [], $manifest['directory']['attributes'] ?? []);
                $updatedFiles++;
            }
        }

        // Prune orphan files and transient entries if requested
        if ($pruneOrphans && !empty($manifest['files'])) {
            foreach (array_keys($manifest['files']) as $fname) {
                if (!file_exists($safe . DIRECTORY_SEPARATOR . $fname)
                    || str_ends_with($fname, '.cache')
                    || str_ends_with($fname, '.log')
                    || str_ends_with($fname, '.bak')
                    || str_ends_with($fname, '.tmp')
                    || $fname === '.spp_shell_history'
                ) {
                    unset($manifest['files'][$fname]);
                    $prunedCount++;
                }
            }
        }

        // Prune orphan directories if requested
        if ($pruneOrphans && !empty($manifest['directories'])) {
            foreach (array_keys($manifest['directories']) as $dname) {
                if (!is_dir($safe . DIRECTORY_SEPARATOR . $dname)) {
                    unset($manifest['directories'][$dname]);
                    $prunedCount++;
                }
            }
        }

        $entries = @scandir($safe) ?: [];
        $today = date('Y-m-d');
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === self::MANIFEST_FILENAME) {
                continue;
            }

            // Skip default ignored directories and hidden tooling folders
            if (in_array($entry, self::DEFAULT_IGNORED_DIRS, true) || (is_dir($safe . DIRECTORY_SEPARATOR . $entry) && str_starts_with($entry, '.'))) {
                continue;
            }

            // Skip transient log, cache, backup, and shell history files
            if (str_ends_with($entry, '.cache') || str_ends_with($entry, '.log') || str_ends_with($entry, '.bak') || str_ends_with($entry, '.tmp') || $entry === '.spp_shell_history') {
                continue;
            }

            $itemPath = $safe . DIRECTORY_SEPARATOR . $entry;
            $isDir = is_dir($itemPath);

            if ($isDir) {
                if (!isset($manifest['directories'][$entry])) {
                    $inferredSub = self::inferDirectoryMetadata($itemPath);
                    $manifest['directories'][$entry] = [
                        'description' => $inferredSub['description'],
                        'usage' => $inferredSub['usage'],
                        'stability' => $inferredSub['attributes']['stability'] ?? 'stable',
                        'author' => $inferredSub['attributes']['author'] ?? 'SPP Framework',
                        'review_date' => '2027-01-01',
                        'attributes' => $inferredSub['attributes']
                    ];
                    $syncedDirs++;
                } elseif ($upgradeGeneric && self::isGenericDescription($manifest['directories'][$entry]['description'] ?? '', $entry)) {
                    $inferredSub = self::inferDirectoryMetadata($itemPath);
                    if (!self::isGenericDescription($inferredSub['description'], $entry)) {
                        $manifest['directories'][$entry]['description'] = $inferredSub['description'];
                        if (empty($manifest['directories'][$entry]['usage'])) {
                            $manifest['directories'][$entry]['usage'] = $inferredSub['usage'];
                        }
                        $manifest['directories'][$entry]['attributes'] = array_merge($inferredSub['attributes'] ?? [], $manifest['directories'][$entry]['attributes'] ?? []);
                        $updatedFiles++;
                    }
                }

                if ($recursive) {
                    $subRes = self::syncDirectory($itemPath, true, $pruneOrphans, $upgradeGeneric);
                    if (!empty($subRes['success'])) {
                        $syncedFiles += ($subRes['synced_files'] ?? 0);
                        $syncedDirs += ($subRes['synced_dirs'] ?? 0);
                        $prunedCount += ($subRes['pruned'] ?? 0);
                        $updatedFiles += ($subRes['updated'] ?? 0);
                    }
                }
            } else {
                if (!isset($manifest['files'][$entry])) {
                    $inferred = self::inferFileMetadata($itemPath);
                    $manifest['files'][$entry] = $inferred;
                    $syncedFiles++;
                } else {
                    $existing = &$manifest['files'][$entry];
                    if (is_array($existing)) {
                        $entryUpdated = false;
                        if (empty($existing['review_date']) || $existing['review_date'] < $today) {
                            $existing['review_date'] = '2027-01-01';
                            $entryUpdated = true;
                        }
                        if (!empty($existing['attributes']['review_date']) && $existing['attributes']['review_date'] < $today) {
                            $existing['attributes']['review_date'] = '2027-01-01';
                            $entryUpdated = true;
                        }
                        $needsInference = empty($existing['description'])
                            || ($upgradeGeneric && self::isGenericDescription($existing['description'], $entry))
                            || empty($existing['attributes']['layer'])
                            || (empty($existing['usage']) && $upgradeGeneric);

                        if ($needsInference) {
                            $inferred = self::inferFileMetadata($itemPath);
                            if (empty($existing['description']) || ($upgradeGeneric && self::isGenericDescription($existing['description'], $entry))) {
                                if (!self::isGenericDescription($inferred['description'], $entry)) {
                                    $existing['description'] = $inferred['description'];
                                    $entryUpdated = true;
                                }
                            }
                            if (empty($existing['usage']) || ($upgradeGeneric && empty($existing['usage']))) {
                                if (!empty($inferred['usage'])) {
                                    $existing['usage'] = $inferred['usage'];
                                    $entryUpdated = true;
                                }
                            }
                            if (empty($existing['attributes']['layer']) || $upgradeGeneric) {
                                if (!empty($inferred['attributes']['layer'])) {
                                    $existing['attributes']['layer'] = $inferred['attributes']['layer'];
                                    $entryUpdated = true;
                                }
                            }
                            $existingTags = $existing['attributes']['tags'] ?? [];
                            $inferredTags = $inferred['attributes']['tags'] ?? [];
                            $mergedTags = array_values(array_unique(array_merge($existingTags, $inferredTags)));
                            if (count($mergedTags) !== count($existingTags)) {
                                $existing['attributes']['tags'] = $mergedTags;
                                $entryUpdated = true;
                            }
                        }
                        if ($entryUpdated) {
                            $updatedFiles++;
                        }
                    }
                }
            }
        }

        if (!empty($manifest['directories'])) {
            foreach ($manifest['directories'] as &$dirEntry) {
                if (is_array($dirEntry)) {
                    if (empty($dirEntry['review_date']) || $dirEntry['review_date'] < $today) {
                        $dirEntry['review_date'] = '2027-01-01';
                        $updatedFiles++;
                    }
                }
            }
        }

        if ($syncedFiles > 0 || $syncedDirs > 0 || $prunedCount > 0 || $updatedFiles > 0 || !file_exists($manifestPath)) {
            file_put_contents($manifestPath, Yaml::dump($manifest, 4, 2));
            unset(self::$runtimeCache[$manifestPath]);
        }

        return [
            'success' => true,
            'directory' => self::getRelativePath($safe),
            'synced' => ($syncedFiles + $syncedDirs),
            'synced_files' => $syncedFiles,
            'synced_dirs' => $syncedDirs,
            'pruned' => $prunedCount,
            'updated' => $updatedFiles
        ];
    }

    /**
     * Infers directory-level metadata from its path and conventions.
     */
    protected static function inferDirectoryMetadata(string $dirPath): array
    {
        $relPath = trim(str_replace('\\', '/', self::getRelativePath($dirPath)), '/');
        $baseName = basename($dirPath);
        $tags = [strtolower($baseName)];
        $author = 'SPP Framework';
        $stability = 'stable';
        $layer = 'application';
        $usage = "";

        // Extract app name if path is inside src/{app} or etc/apps/{app} or resources/{app}
        $appName = null;
        if (preg_match('#^(?:src|etc/apps|resources|public|data)/([^/]+)#', $relPath, $am)) {
            $appName = $am[1];
            $tags[] = strtolower($appName);
        }

        $appPrefix = $appName ? "[{$appName}] " : "";

        if ($relPath === '' || $relPath === '.') {
            $desc = "SPP application and framework root directory";
            $usage = "Top-level workspace root containing framework core, applications, configuration, and documentation";
            $tags = ['workspace', 'root', 'spp'];
            $layer = 'core';
        } elseif (str_contains($relPath, 'Controllers')) {
            $desc = "{$appPrefix}HTTP and API controllers handling request routing, action execution, and view responses";
            $usage = "Dispatched via application router to handle incoming HTTP requests";
            $tags = array_merge($tags, ['controller', 'http', 'routing']);
            $layer = 'presentation';
        } elseif (str_contains($relPath, 'Services') || str_contains($relPath, 'serv')) {
            $desc = "{$appPrefix}Domain service classes providing business logic, integrations, and transactional operations";
            $usage = "Injected and invoked by controllers, commands, and background jobs";
            $tags = array_merge($tags, ['service', 'domain', 'logic']);
            $layer = 'domain';
        } elseif (str_contains($relPath, 'Models') || str_contains($relPath, 'entities') || str_contains($relPath, 'Entities')) {
            $desc = "{$appPrefix}Active Record entity models extending SPPEntity for database persistence and relationships";
            $usage = "Database persistence layer and Active Record queries";
            $tags = array_merge($tags, ['model', 'entity', 'database']);
            $layer = 'domain';
        } elseif (str_contains($relPath, 'views') || str_ends_with($relPath, 'views')) {
            $desc = "{$appPrefix}External Blade and HTML view templates, layouts, and reactive UI partials";
            $usage = "Rendered via ViewController and Drishyam template engine";
            $tags = array_merge($tags, ['views', 'templates', 'ui']);
            $layer = 'presentation';
        } elseif (str_contains($relPath, 'resources')) {
            $desc = "{$appPrefix}Application resources including views, layouts, CSS stylesheets, and client assets";
            $usage = "Loaded by view renderers and asset federation pipeline";
            $tags = array_merge($tags, ['resources', 'assets']);
            $layer = 'presentation';
        } elseif (str_contains($relPath, 'workflows')) {
            $desc = "{$appPrefix}Declarative YAML workflow definitions and state machine transition rules";
            $usage = "Loaded by WorkflowManager to orchestrate and audit entity lifecycles";
            $tags = array_merge($tags, ['workflow', 'state-machine', 'yaml']);
            $layer = 'domain';
        } elseif (str_contains($relPath, 'forms')) {
            $desc = "{$appPrefix}Declarative form definitions, field schemas, and validation manifests";
            $usage = "Loaded and rendered via FormManager / WizardController";
            $tags = array_merge($tags, ['forms', 'validation', 'ui']);
            $layer = 'presentation';
        } elseif (str_contains($relPath, 'events/defaults')) {
            $desc = "Default framework event subscriber definitions and fallback hooks";
            $usage = "Registered in SPPEvent bus for application-wide event interception";
            $tags = array_merge($tags, ['events', 'defaults', 'hooks']);
            $layer = 'domain';
        } elseif (str_contains($relPath, 'events')) {
            $desc = "{$appPrefix}Domain event handlers, lifecycle hooks, and asynchronous listeners";
            $usage = "Dispatched via SPPEvent::fireEvent() and SPPEvent::triggerHook()";
            $tags = array_merge($tags, ['events', 'event-bus', 'hooks']);
            $layer = 'domain';
        } elseif (str_contains($relPath, 'tests')) {
            $desc = "{$appPrefix}Parikshak automated test suites and regression test fixtures";
            $usage = "Executed via Parikshak test runner: php spp.php test:run";
            $tags = array_merge($tags, ['test', 'parikshak', 'qa']);
            $layer = 'test';
        } elseif (str_starts_with($relPath, 'spp/commands')) {
            $desc = "CLI console commands executable via php spp.php";
            $usage = "Registered with CommandManager and executed in CLI SAPI";
            $tags = array_merge($tags, ['cli', 'commands', 'framework']);
            $layer = 'core';
        } elseif (str_starts_with($relPath, 'spp/core')) {
            $desc = "SPP core framework kernel, autoloader, lifecycle, and router subsystems";
            $usage = "Loaded during application bootstrapping in sppinit.php";
            $tags = array_merge($tags, ['core', 'kernel', 'framework']);
            $layer = 'core';
        } elseif (str_starts_with($relPath, 'spp/modules/spp')) {
            $desc = "Core SPP framework module providing standard enterprise capabilities: {$baseName}";
            $usage = "Initialized via modinit.php by ModuleManager";
            $tags = array_merge($tags, ['module', 'framework', 'spp']);
            $layer = 'core';
        } elseif (str_starts_with($relPath, 'spp/modules/optional')) {
            $desc = "Optional high-value enterprise module: {$baseName}";
            $usage = "Loaded on-demand or registered in application configuration";
            $tags = array_merge($tags, ['module', 'optional', 'enterprise']);
            $layer = 'core';
        } elseif (str_starts_with($relPath, 'spp/admin/js/views')) {
            $desc = "SPP Admin SPA view components and reactive user interface controllers";
            $usage = "Loaded dynamically by the SPP Admin client-side router";
            $tags = array_merge($tags, ['admin', 'spa', 'views']);
            $layer = 'presentation';
        } elseif (str_starts_with($relPath, 'spp/admin/services')) {
            $desc = "SPP Admin backend API services and administration endpoints";
            $usage = "Dispatched by spp/admin/api.php for administration operations";
            $tags = array_merge($tags, ['admin', 'api', 'services']);
            $layer = 'application';
        } elseif (str_starts_with($relPath, 'spp/admin')) {
            $desc = "SPP Admin WebOS management dashboard and administration SPA";
            $usage = "Served at /school1/sppadmin/ for administrative management";
            $tags = array_merge($tags, ['admin', 'spa', 'dashboard']);
            $layer = 'presentation';
        } elseif (str_starts_with($relPath, 'spp/dev')) {
            $desc = "SPP Developer Studio diagnostic console, interactive shell, and code tools";
            $usage = "Served during local development via SPP Dev Console";
            $tags = array_merge($tags, ['dev', 'studio', 'diagnostics']);
            $layer = 'operational';
        } elseif (str_starts_with($relPath, 'docs/commands')) {
            $desc = "CLI command reference documentation and dual-format man pages";
            $usage = "Consulted for CLI command usage and syntax reference";
            $tags = array_merge($tags, ['docs', 'commands', 'manual']);
            $layer = 'operational';
        } elseif (str_starts_with($relPath, 'docs/tutorials')) {
            $desc = "Comprehensive novice-first developer tutorials and architectural guides";
            $usage = "Step-by-step guides for developers learning SPP";
            $tags = array_merge($tags, ['docs', 'tutorials', 'guides']);
            $layer = 'operational';
        } elseif (str_starts_with($relPath, 'docs') || str_starts_with($relPath, 'documentation')) {
            $desc = "Framework and application documentation guides and blueprints";
            $usage = "Documentation library for developers and system operators";
            $tags = array_merge($tags, ['docs', 'documentation']);
            $layer = 'operational';
        } elseif (str_starts_with($relPath, 'man')) {
            $desc = "Traditional Unix troff/groff manual pages (section 1)";
            $usage = "Viewable via unix man command or SPP man page reader";
            $tags = array_merge($tags, ['man', 'troff', 'unix']);
            $layer = 'operational';
        } elseif (str_starts_with($relPath, 'etc/apps') && preg_match('#etc/apps/[^/]+/modsconf/([^/]+)#', $relPath, $mcm)) {
            $desc = "Configuration settings and connection profiles for {$mcm[1]} module in {$appName}";
            $usage = "Loaded by {$mcm[1]} module during application boot";
            $tags = array_merge($tags, ['config', 'modsconf', strtolower($mcm[1])]);
            $layer = 'config';
        } elseif (str_starts_with($relPath, 'etc/apps') && $appName && $baseName === $appName) {
            $desc = "Application configuration root, entity schemas, and manifests for {$appName}";
            $usage = "Loaded during application initialization";
            $tags = array_merge($tags, ['config', 'app', strtolower($appName)]);
            $layer = 'config';
        } elseif (str_starts_with($relPath, 'etc/apps')) {
            $desc = "Application configuration manifests repository";
            $usage = "Contains per-application configurations, workflows, and entity schemas";
            $tags = array_merge($tags, ['config', 'apps']);
            $layer = 'config';
        } elseif (str_starts_with($relPath, 'etc')) {
            $desc = "Global framework and application configuration repository";
            $usage = "Loaded during system boot";
            $tags = array_merge($tags, ['config', 'global']);
            $layer = 'config';
        } elseif (str_starts_with($relPath, 'data')) {
            $desc = "{$appPrefix}Persistent SQLite databases and local data stores";
            $usage = "Accessed via PDO SQLite driver or XDB storage engine";
            $tags = array_merge($tags, ['data', 'storage', 'sqlite']);
            $layer = 'infrastructure';
        } elseif (str_starts_with($relPath, 'public')) {
            $desc = "{$appPrefix}Public web document root and static asset distribution directory";
            $usage = "Directly accessible via HTTP web server";
            $tags = array_merge($tags, ['public', 'web', 'assets']);
            $layer = 'presentation';
        } elseif (str_starts_with($relPath, 'themes')) {
            $desc = "UI theme bundle defining templates, stylesheets, and design tokens: {$baseName}";
            $usage = "Activated and managed by ThemeManager";
            $tags = array_merge($tags, ['theme', 'ui', 'styling']);
            $layer = 'presentation';
        } elseif (str_starts_with($relPath, 'src/')) {
            $readableName = ucwords(str_replace(['_', '-'], ' ', $baseName));
            $desc = "{$appPrefix}Subsystem directory housing {$readableName} components";
            $usage = "Application domain namespace classes and logic";
            $tags = array_merge($tags, ['app', strtolower($appName ?: $baseName)]);
            $layer = 'application';
        } else {
            $readableName = ucwords(str_replace(['_', '-'], ' ', $baseName));
            $desc = "Subsystem directory housing {$readableName} components and resources";
            $usage = "Modular workspace subsystem";
            $tags = array_merge($tags, ['subsystem']);
            $layer = 'infrastructure';
        }

        return [
            'description' => $desc,
            'usage' => $usage,
            'stability' => $stability,
            'author' => $author,
            'attributes' => [
                'tags' => array_values(array_unique($tags)),
                'layer' => $layer,
                'author' => $author,
                'stability' => $stability
            ]
        ];
    }

    /**
     * Infers default metadata from a file by inspecting content, docblocks, AST signatures, or extensions.
     */
    protected static function inferFileMetadata(string $filePath): array
    {
        $name = basename($filePath);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $nameWithoutExt = pathinfo($name, PATHINFO_FILENAME);
        $relPath = trim(str_replace('\\', '/', self::getRelativePath($filePath)), '/');

        $desc = "";
        $usage = "";
        $tags = [$ext];
        $stability = 'stable';
        $author = 'SPP Framework';
        $reviewDate = '2027-01-01';
        $layer = 'core';
        $attributes = ['tags' => []];

        $content = '';
        if (file_exists($filePath) && is_file($filePath)) {
            $content = @file_get_contents($filePath, false, null, 0, 8192) ?: '';
        }

        // Extract words from filename for semantic tags
        $words = preg_split('/(?=[A-Z])|_|-|\./', $nameWithoutExt, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            $lw = strtolower($word);
            if (strlen($lw) > 2 && !in_array($lw, $tags, true)) {
                $tags[] = $lw;
            }
        }

        // -------------------------------------------------------------
        // 1. Explicit Well-Known Filenames (Root, Tooling, Configs)
        // -------------------------------------------------------------
        if ($name === '.cursorrules') {
            $desc = "Cursor AI editor configuration defining system prompt guardrails and SPP framework invariants";
            $usage = "Loaded by Cursor AI IDE during code generation";
            $layer = "operational";
            $tags = array_merge($tags, ['ai', 'cursor', 'guardrails']);
        } elseif ($name === '.windsurfrules') {
            $desc = "Windsurf AI rules defining framework coding standards and architectural patterns";
            $usage = "Loaded by Windsurf AI editor";
            $layer = "operational";
            $tags = array_merge($tags, ['ai', 'windsurf', 'rules']);
        } elseif ($name === 'AGENTS.md') {
            $desc = "Autonomous agent instruction profile defining mandatory framework rules and coding invariants";
            $usage = "Consulted by AI coding assistants (Antigravity, Claude, Copilot)";
            $layer = "operational";
            $tags = array_merge($tags, ['ai', 'agent', 'architecture']);
        } elseif ($name === '.gitignore') {
            $desc = "Git version control ignore specifications excluding transient caches, vendors, and secrets";
            $usage = "Read by Git to exclude unversioned files";
            $layer = "operational";
            $tags = array_merge($tags, ['git', 'vcs', 'ignore']);
        } elseif ($name === '.gitattributes') {
            $desc = "Git path attributes defining text normalization, line endings, and binary drivers";
            $usage = "Enforces consistent Git repository line endings and diff behaviors";
            $layer = "operational";
            $tags = array_merge($tags, ['git', 'vcs', 'attributes']);
        } elseif ($name === '.htaccess') {
            $desc = "Apache HTTP server configuration defining URL rewriting, security headers, and directory protection";
            $usage = "Evaluated by Apache mod_rewrite to route all web traffic through index.php";
            $layer = "infrastructure";
            $tags = array_merge($tags, ['apache', 'security', 'routing']);
        } elseif ($name === '.php-cs-fixer.dist.php') {
            $desc = "PHP-CS-Fixer code styling rules and directory scanning configuration";
            $usage = "Executed via php-cs-fixer fix to format code";
            $layer = "operational";
            $tags = array_merge($tags, ['php-cs-fixer', 'linter', 'formatting']);
        } elseif ($name === '.sppignore') {
            $desc = "SPP deployment and packaging exclusion specifications";
            $usage = "Read by spp deploy:push and packaging routines to filter build artifacts";
            $layer = "operational";
            $tags = array_merge($tags, ['deploy', 'packaging', 'ignore']);
        } elseif ($name === 'Dockerfile') {
            $desc = "Docker container specification packaging SPP runtime, PHP extensions, and web server";
            $usage = "docker build -t spp-app .";
            $layer = "operational";
            $tags = array_merge($tags, ['docker', 'container', 'deployment']);
        } elseif ($name === 'docker-compose.yml') {
            $desc = "Multi-container Docker orchestration manifest configuring web server, database, and cache services";
            $usage = "docker-compose up -d";
            $layer = "operational";
            $tags = array_merge($tags, ['docker', 'compose', 'orchestration']);
        } elseif ($name === 'composer.json') {
            $desc = "Composer package definition declaring project dependencies, autoloader paths, and scripts";
            $usage = "composer install / composer update";
            $layer = "config";
            $tags = array_merge($tags, ['composer', 'dependencies', 'packages']);
        } elseif ($name === 'composer.lock') {
            $desc = "Composer dependency lock file pinning exact package versions and checksums";
            $usage = "Generated automatically by Composer to ensure deterministic installs";
            $layer = "config";
            $tags = array_merge($tags, ['composer', 'lock', 'dependencies']);
        } elseif ($name === 'llms.txt') {
            $desc = "Condensed AI developer quickstart and architectural cheatsheet for AI coding agents";
            $usage = "Referenced by LLM coding tools for instant framework context";
            $layer = "operational";
            $tags = array_merge($tags, ['ai', 'llm', 'cheatsheet']);
        } elseif ($name === 'llms-full.txt') {
            $desc = "Comprehensive SPP framework architecture bible and API specifications for AI knowledge ingestion";
            $usage = "Ingested by AI coding assistants for end-to-end framework reference";
            $layer = "operational";
            $tags = array_merge($tags, ['ai', 'llm', 'architecture']);
        } elseif ($name === 'LICENSE') {
            $desc = "Software license agreement defining distribution and usage rights";
            $usage = "Legal software license";
            $layer = "operational";
            $tags = array_merge($tags, ['license', 'legal']);
        } elseif ($name === 'spp.php' && ($relPath === 'spp.php' || $relPath === 'spp/spp.php')) {
            $desc = "SPP CLI console gateway and polyglot execution dispatcher";
            $usage = "php spp.php <command> [arguments] [options]";
            $layer = "core";
            $tags = array_merge($tags, ['cli', 'gateway', 'console']);
        } elseif ($name === 'sppinit.php') {
            $desc = "Framework bootstrap initialization script loading autoloader, core singletons, and module manifests";
            $usage = "require_once 'sppinit.php';";
            $layer = "core";
            $tags = array_merge($tags, ['bootstrap', 'kernel', 'init']);
        } elseif ($name === 'spp-webos-config.php') {
            $desc = "Client-side WebOS boot configuration defining desktop environment settings and app shortcuts";
            $usage = "Loaded during WebOS desktop boot";
            $layer = "presentation";
            $tags = array_merge($tags, ['webos', 'desktop', 'config']);
        } elseif ($name === 'routes.php' || $name === 'routes.json') {
            $desc = "HTTP routing table defining URL routes, controllers, HTTP methods, and middleware";
            $usage = "Parsed by DomainRouter / ViewRouter during request dispatch";
            $layer = "config";
            $tags = array_merge($tags, ['routes', 'routing', 'http']);
        } elseif ($name === 'auth.php') {
            $desc = "Authentication entry point and session verification handler";
            $usage = "Invoked during authentication challenges and login flows";
            $layer = "application";
            $tags = array_merge($tags, ['auth', 'security', 'session']);
        }

        // -------------------------------------------------------------
        // 2. Blade & HTML View Templates (CRITICAL: check before generic PHP)
        // -------------------------------------------------------------
        elseif (str_contains($name, '.blade.php') || $ext === 'blade' || $ext === 'phtml' || ($ext === 'html' && !str_contains($relPath, 'admin/'))) {
            $tags[] = 'view';
            $tags[] = 'template';
            $layer = 'presentation';
            $attributes['required_props'] = [];

            if (str_contains($relPath, 'layouts') || $nameWithoutExt === 'app' || $nameWithoutExt === 'base' || $nameWithoutExt === 'main') {
                $desc = "Base layout template: {$nameWithoutExt}, providing HTML shell, navigation, and content slots";
                $usage = "@extends('layouts.{$nameWithoutExt}')";
                $tags[] = 'layout';
            } elseif (str_contains($relPath, 'partials') || str_contains($name, 'partial') || str_contains($name, 'card') || str_contains($name, 'modal')) {
                $desc = "Reusable UI partial fragment: {$nameWithoutExt}, rendered dynamically or inlined";
                $usage = "@spppartial('partials/{$name}', \$data) or \$this->renderPartial('partials/{$name}', \$data)";
                $tags[] = 'partial';
            } elseif (str_contains($name, 'form') || str_contains($name, 'edit') || str_contains($name, 'create')) {
                $desc = "Form presentation view: {$nameWithoutExt}, providing user input fields and validation feedback";
                $usage = "Rendered by controller action via \$this->renderView('{$name}', \$data)";
                $tags[] = 'form';
            } elseif (str_contains($name, 'index') || str_contains($name, 'list')) {
                $desc = "Resource collection index view: {$nameWithoutExt}, displaying entity collection table and actions";
                $usage = "Rendered by ResourceController@index action";
                $tags[] = 'index';
            } elseif (str_contains($name, 'show') || str_contains($name, 'detail') || str_contains($name, 'view')) {
                $desc = "Resource detail view: {$nameWithoutExt}, presenting comprehensive entity record information";
                $usage = "Rendered by ResourceController@show action";
                $tags[] = 'show';
            } else {
                $desc = "Presentation template: {$name}, defining UI components and markup structure";
                $usage = "Rendered via ViewController or @spppartial('{$name}', \$data)";
            }
        }

        // -------------------------------------------------------------
        // 3. Domain Events & Listeners
        // -------------------------------------------------------------
        elseif (str_contains($relPath, 'events') && $ext === 'php') {
            $eventName = strtolower(str_replace(['_', '.php'], ['.', ''], $name));
            $desc = "Domain event definition: {$nameWithoutExt}, dispatched across the SPP Event-Mesh";
            $usage = "SPPEvent::fireEvent('{$eventName}', \$payload) or SPPEvent::triggerHook('{$eventName}', \$context)";
            $layer = "domain";
            $tags = array_merge($tags, ['event', 'sppevent', 'domain']);
        }

        // -------------------------------------------------------------
        // 4. Configuration, Schemas & Data Manifests
        // -------------------------------------------------------------
        elseif ($ext === 'yml' || $ext === 'yaml' || $ext === 'xml') {
            if (str_contains($relPath, 'workflows') || str_contains($content, 'states:') || str_contains($content, 'transitions:')) {
                $desc = "Workflow state-machine lifecycle definition for {$nameWithoutExt}, orchestrating states and transitions";
                $usage = "Loaded by WorkflowManager to validate and apply entity transitions";
                $layer = "domain";
                $tags = array_merge($tags, ['workflow', 'state-machine', 'saga']);
            } elseif (str_contains($relPath, 'entities') || str_contains($content, 'table:') || str_contains($content, 'fields:')) {
                $desc = "Declarative entity schema: {$nameWithoutExt}, declaring database columns, types, and relations";
                $usage = "Parsed by SchemaManager and SPPEntity code generators";
                $layer = "domain";
                $tags = array_merge($tags, ['entity', 'schema', 'database']);
            } elseif (str_contains($relPath, 'forms') || ($ext === 'xml' && str_contains($content, '<form'))) {
                $desc = "Declarative form manifest: {$nameWithoutExt}, declaring input fields and validation constraints";
                $usage = "Rendered and validated via FormManager / WizardController";
                $layer = "presentation";
                $tags = array_merge($tags, ['form', 'manifest', 'validation']);
            } elseif (str_contains($relPath, 'modsconf') || str_contains($relPath, 'etc/modsconf')) {
                preg_match('#modsconf/([^/]+)#', $relPath, $mcm);
                $modName = $mcm[1] ?? 'module';
                $desc = "Configuration settings and options for {$modName} module";
                $usage = "Loaded by {$modName} module during application boot";
                $layer = "config";
                $tags = array_merge($tags, ['config', 'module', strtolower($modName)]);
            } elseif ($name === 'modules.xml' || $name === 'modules.yml') {
                $desc = "Module registration manifest defining active modules, dependencies, and boot sequence";
                $usage = "Parsed during system boot by ModuleManager";
                $layer = "config";
                $tags = array_merge($tags, ['modules', 'manifest', 'registry']);
            } elseif ($name === 'pages.yml' || $name === 'pages.xml') {
                $desc = "Declarative page hierarchy and navigation routing manifest";
                $usage = "Loaded by DomainRouter / ViewRouter to build application sitemap";
                $layer = "config";
                $tags = array_merge($tags, ['navigation', 'pages', 'sitemap']);
            } else {
                $desc = "YAML/XML configuration manifest: {$nameWithoutExt}";
                $usage = "Loaded via configuration loader or ConfigManager";
                $layer = "config";
                $tags = array_merge($tags, ['config', $ext]);
            }
        }

        // -------------------------------------------------------------
        // 5. SQLite Databases, Schemas & Audit Ledgers
        // -------------------------------------------------------------
        elseif ($ext === 'sqlite' || $ext === 'sqlite3' || $ext === 'db') {
            $desc = "Persistent SQLite database storage ledger: {$name}";
            $usage = "Accessed via PDO SQLite connection or SPP Storage Engine";
            $layer = "infrastructure";
            $tags = array_merge($tags, ['sqlite', 'database', 'storage']);
        } elseif ($ext === 'sql') {
            $desc = "SQL database migration, schema definition, or dataset seed script: {$nameWithoutExt}";
            $usage = "Executed via php spp.php db:migrate or database installer";
            $layer = "infrastructure";
            $tags = array_merge($tags, ['sql', 'migration', 'database']);
        } elseif ($ext === 'jsonl') {
            $desc = str_contains($name, 'audit')
                ? "Cryptographic JSON-Lines audit chain recording tamper-evident system mutations"
                : "Structured JSON-Lines activity stream logging runtime events";
            $usage = "Appended during transactions; audited via verification commands";
            $layer = "infrastructure";
            $tags = array_merge($tags, ['jsonl', 'audit', 'logging']);
        }

        // -------------------------------------------------------------
        // 6. PHP Source Files (Controllers, Entities, Services, Tests, etc.)
        // -------------------------------------------------------------
        elseif ($ext === 'php') {
            // Check for docblock summary
            if (preg_match('/\/\*\*(.*?)\*\//s', $content, $docm)) {
                $lines = explode("\n", $docm[1]);
                $meaningfulLines = [];
                foreach ($lines as $line) {
                    $clean = trim(preg_replace('/^\s*\*\s?/', '', $line));
                    if ($clean === '' || str_starts_with($clean, '@') || str_starts_with($clean, '/*') || str_starts_with($clean, '*/')) {
                        continue;
                    }
                    if (preg_match('/^(?:Class|Interface|Trait)\s+[A-Za-z0-9_]+$/i', $clean)) {
                        continue;
                    }
                    if (strcasecmp($clean, $nameWithoutExt) === 0 || strcasecmp($clean, $name) === 0) {
                        continue;
                    }
                    $meaningfulLines[] = $clean;
                }
                if (!empty($meaningfulLines)) {
                    $candidate = implode(' ', array_slice($meaningfulLines, 0, 2));
                    if (!empty($candidate) && strlen($candidate) > 8 && !str_starts_with($candidate, '{') && !str_starts_with($candidate, '$')) {
                        $desc = $candidate;
                    }
                }
            }
            if (preg_match('/@author\s+([^\n*]+)/i', $content, $am)) {
                $author = trim($am[1]);
            }

            // Inspect class/trait/interface declaration
            $isClass = preg_match('/class\s+([A-Za-z0-9_]+)(?:\s+extends\s+([A-Za-z0-9_]+))?(?:\s+implements\s+([A-Za-z0-9_,\s]+))?/i', $content, $cm);
            $className = $cm[1] ?? $nameWithoutExt;
            $parentClass = $cm[2] ?? '';
            $interfaces = $cm[3] ?? '';

            if (str_contains($content, 'extends ViewController') || str_contains($content, 'extends ResourceController') || str_contains($content, 'extends WizardController') || str_ends_with($className, 'Controller')) {
                $layer = 'presentation';
                $tags[] = 'controller';
                $usage = 'Dispatched via application router to handle HTTP endpoints';
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "HTTP controller: {$className}, processing incoming web requests and rendering view responses";
                }
            } elseif (str_contains($content, 'extends SPPEntity') || str_contains($content, 'extends Model') || str_contains($relPath, 'Entities') || str_contains($relPath, 'Models')) {
                $layer = 'domain';
                $tags[] = 'model';
                $tags[] = 'entity';
                $usage = "{$className}::find(\$id) or {$className}::findWhere([...])";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "Active Record entity: {$className}, managing database records, relationships, and lifecycle hooks";
                }
            } elseif (str_contains($content, 'extends Command') || str_ends_with($className, 'Command')) {
                $layer = 'core';
                $tags[] = 'cli';
                $tags[] = 'command';
                if (preg_match('/protected\s+string\s+\$name\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $nm)) {
                    $usage = "php spp.php {$nm[1]}";
                } else {
                    $usage = "php spp.php " . strtolower(preg_replace('/Command$/', '', $className));
                }
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "CLI console command: {$className}, providing command-line automation and tooling";
                }
            } elseif (str_contains($content, 'extends SPPTestCase') || str_contains($content, 'extends TestCase') || str_contains($name, 'Test.php') || str_starts_with($name, 'test.')) {
                $layer = 'test';
                $tags[] = 'test';
                $tags[] = 'parikshak';
                $usage = "php spp.php test:run " . str_replace('.php', '', $name);
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "Parikshak automated test suite: {$className}, evaluating regression assertions and contracts";
                }
            } elseif (str_contains($content, 'implements MiddlewareInterface') || str_contains($className, 'Middleware') || str_contains($relPath, 'middleware')) {
                $layer = 'infrastructure';
                $tags[] = 'middleware';
                $usage = "Registered in HTTP middleware pipeline to process requests/responses";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "HTTP pipeline middleware: {$className}, intercepting requests for security, CORS, or telemetry";
                }
            } elseif (str_ends_with($className, 'Service') || str_contains($relPath, 'Services') || str_contains($relPath, 'serv')) {
                $layer = 'domain';
                $tags[] = 'service';
                $usage = "App::make({$className}::class) or injected via dependency container";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "Domain service: {$className}, encapsulating business operations and transactional routines";
                }
            } elseif (str_ends_with($className, 'Repository') || str_contains($relPath, 'Repositories')) {
                $layer = 'domain';
                $tags[] = 'repository';
                $usage = "Injected into domain services for entity querying and storage abstraction";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "Data repository: {$className}, isolating database query logic and entity retrieval";
                }
            } elseif (preg_match('/trait\s+([A-Za-z0-9_]+)/i', $content, $tm)) {
                $layer = 'application';
                $tags[] = 'trait';
                $usage = "use {$tm[1]}; within class definitions";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "Reusable PHP trait: {$tm[1]}, providing shared behavioral methods and attributes";
                }
            } elseif (preg_match('/interface\s+([A-Za-z0-9_]+)/i', $content, $im)) {
                $layer = 'domain';
                $tags[] = 'interface';
                $tags[] = 'contract';
                $usage = "Implemented by domain classes or adapters: implements {$im[1]}";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "Contract interface: {$im[1]}, establishing standardized API method signatures";
                }
            } elseif (str_contains($className, 'Job') || str_contains($content, 'implements ShouldQueue') || str_contains($relPath, 'jobs')) {
                $layer = 'application';
                $tags[] = 'job';
                $tags[] = 'queue';
                $usage = "DagJobOrchestrator::dispatch(new {$className}(\$args))";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $desc = "Asynchronous queue job: {$className}, dispatched for background pipeline execution";
                }
            } elseif (str_starts_with($relPath, 'spp/core') && str_starts_with($name, 'class.')) {
                $layer = 'core';
                $tags[] = 'core';
                $tags[] = 'kernel';
                $usage = "Loaded during framework boot via autoloader or sppinit.php";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $readable = ucwords(str_replace(['class.', '.php', '_'], ['', '', ' '], $name));
                    $desc = "SPP core framework subsystem: {$readable}, managing framework infrastructure";
                }
            } else {
                $layer = 'application';
                $usage = "Loaded via autoloader: {$className}";
                if (empty($desc) || self::isGenericDescription($desc, $name)) {
                    $readable = ucwords(str_replace(['_', '-'], ' ', $nameWithoutExt));
                    $desc = "Application class component: {$readable}, providing domain routines and utilities";
                }
            }
        }

        // -------------------------------------------------------------
        // 7. Client-side Assets & Documentation
        // -------------------------------------------------------------
        elseif ($ext === 'js' || $ext === 'mjs' || $ext === 'ts') {
            $layer = 'presentation';
            $tags[] = 'frontend';
            if (str_contains($relPath, 'views')) {
                $desc = "Client-side SPA view component: {$nameWithoutExt}, managing UI reactivity";
                $usage = "Loaded dynamically by SPA router";
            } else {
                $desc = "Client-side JavaScript module: {$nameWithoutExt}, providing frontend behavior and utilities";
                $usage = "Imported as ES module or included via script tag";
            }
        } elseif ($ext === 'css' || $ext === 'scss') {
            $layer = 'presentation';
            $tags[] = 'stylesheet';
            $desc = "Visual stylesheet definition: {$nameWithoutExt}, specifying CSS layout and component design";
            $usage = "<link rel=\"stylesheet\" href=\"{$relPath}\">";
        } elseif ($ext === 'json') {
            $layer = 'config';
            $tags[] = 'json';
            $desc = "JSON data and configuration manifest: {$nameWithoutExt}";
            $usage = "json_decode(file_get_contents('{$relPath}'), true)";
        } elseif ($ext === 'md') {
            $layer = 'operational';
            $tags[] = 'docs';
            if (preg_match('/^#\s+([^\n]+)/m', $content, $hm)) {
                $desc = trim($hm[1]);
            } else {
                $desc = "Documentation and technical reference guide: {$nameWithoutExt}";
            }
            $usage = "Consulted by developers and system operators";
        } elseif ($ext === '1') {
            $layer = 'operational';
            $tags[] = 'man';
            $desc = "Unix troff/groff manual page (section 1) for {$nameWithoutExt}";
            $usage = "man {$relPath}";
        } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp'])) {
            $layer = 'presentation';
            $tags[] = 'asset';
            $tags[] = 'graphic';
            $desc = "Visual graphic asset / image: {$name}";
            $usage = "<img src=\"{$relPath}\" alt=\"{$nameWithoutExt}\">";
        } elseif ($ext === 'neon') {
            $layer = 'operational';
            $tags[] = 'phpstan';
            $desc = "Static analysis configuration: {$nameWithoutExt}";
            $usage = "Read by PHPStan during static analysis";
        } elseif (in_array($ext, ['sh', 'bat', 'ps1'])) {
            $layer = 'operational';
            $tags[] = 'script';
            $desc = "Shell automation script: {$name}";
            $usage = "Executed in shell environment";
        } elseif ($ext === 'log') {
            $layer = 'operational';
            $tags[] = 'log';
            $desc = "Operational log file: {$name}, capturing runtime diagnostics and execution telemetry";
            $usage = "Monitored via log inspection tools";
        } elseif ($ext === 'bak') {
            $layer = 'operational';
            $tags[] = 'backup';
            $desc = "Archive backup file: {$name}, preserved previous state";
            $usage = "Disaster recovery and audit comparison";
        } elseif ($ext === 'cache') {
            $layer = 'operational';
            $tags[] = 'cache';
            $desc = "Compiler/runtime build cache: {$name}";
            $usage = "Generated by build pipeline";
        } elseif ($ext === 'tmp') {
            $layer = 'operational';
            $tags[] = 'temporary';
            $desc = "Temporary staging file: {$name}";
            $usage = "Transient working data";
        } else {
            $readable = ucwords(str_replace(['_', '-'], ' ', $nameWithoutExt));
            $desc = "Resource artifact: {$readable} ({$name})";
            $usage = "Application resource";
            $layer = 'infrastructure';
        }

        // Final sanity check: never return boilerplate
        if (empty($desc) || self::isGenericDescription($desc, $name)) {
            $readable = ucwords(str_replace(['_', '-'], ' ', $nameWithoutExt));
            $desc = "Component file: {$readable}, supporting application runtime and services";
        }

        $attributes['tags'] = array_values(array_unique($tags));
        $attributes['layer'] = $layer;

        $checksum = (file_exists($filePath) && is_file($filePath)) ? @hash_file('sha256', $filePath) : null;

        return [
            'description' => $desc,
            'usage' => $usage,
            'stability' => $stability,
            'author' => $author,
            'review_date' => $reviewDate,
            'checksum' => $checksum,
            'attributes' => $attributes
        ];
    }

    /**
     * Lints a directory's __spp_file_props.yml for YAML syntax, orphan entries, and schema validity.
     */
    public static function lintDirectory(string $dirPath, bool $recursive = true): array
    {
        $safe = self::resolveSafePath($dirPath) ?: $dirPath;
        if (!is_dir($safe)) {
            return [
                'success' => false,
                'error' => "Directory not found: {$dirPath}",
                'total_issues' => 1,
                'issues' => [
                    [
                        'type' => 'directory_not_found',
                        'file' => $dirPath,
                        'message' => "Directory not found: {$dirPath}"
                    ]
                ],
                'errors' => ["Directory not found: {$dirPath}"],
                'manifests_checked' => 0
            ];
        }

        $issues = [];
        $manifestPath = $safe . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;

        if (file_exists($manifestPath)) {
            try {
                $yamlContent = @file_get_contents($manifestPath);
                $parsed = $yamlContent !== false ? Yaml::parse($yamlContent) : Yaml::parseFile($manifestPath);
                if (!is_array($parsed)) {
                    $issues[] = [
                        'type' => 'syntax_error',
                        'file' => self::getRelativePath($manifestPath),
                        'message' => 'Manifest does not contain a valid YAML object/mapping.'
                    ];
                } else {
                    $files = $parsed['files'] ?? [];
                    if (is_array($files)) {
                        foreach ($files as $fname => $fdata) {
                            $physical = $safe . DIRECTORY_SEPARATOR . $fname;
                            if (!file_exists($physical)) {
                                $issues[] = [
                                    'type' => 'orphan_entry',
                                    'file' => self::getRelativePath($manifestPath),
                                    'target' => $fname,
                                    'message' => "Orphan entry: Manifest references file '{$fname}' which does not exist on disk."
                                ];
                            }
                            $stability = is_array($fdata) ? ($fdata['stability'] ?? 'stable') : 'stable';
                            if (!in_array($stability, self::VALID_STABILITIES, true)) {
                                $issues[] = [
                                    'type' => 'invalid_stability',
                                    'file' => self::getRelativePath($manifestPath),
                                    'target' => $fname,
                                    'message' => "Invalid stability '{$stability}'. Allowed: " . implode(', ', self::VALID_STABILITIES)
                                ];
                            }
                        }
                    }

                    $dirs = $parsed['directories'] ?? [];
                    if (is_array($dirs)) {
                        foreach ($dirs as $dname => $ddata) {
                            $physical = $safe . DIRECTORY_SEPARATOR . $dname;
                            if (!file_exists($physical)) {
                                $issues[] = [
                                    'type' => 'orphan_entry',
                                    'file' => self::getRelativePath($manifestPath),
                                    'target' => $dname,
                                    'message' => "Orphan entry: Manifest references directory '{$dname}' which does not exist on disk."
                                ];
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                $issues[] = [
                    'type' => 'parse_error',
                    'file' => self::getRelativePath($manifestPath),
                    'message' => 'YAML Parse Error: ' . $e->getMessage()
                ];
            }
        }

        if ($recursive) {
            $entries = @scandir($safe) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || in_array($entry, self::DEFAULT_IGNORED_DIRS, true) || (is_dir($safe . DIRECTORY_SEPARATOR . $entry) && str_starts_with($entry, '.'))) {
                    continue;
                }
                $sub = $safe . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($sub)) {
                    $subRes = self::lintDirectory($sub, true);
                    if (!empty($subRes['issues'])) {
                        $issues = array_merge($issues, $subRes['issues']);
                    }
                }
            }
        }

        $errorMessages = array_map(fn($i) => $i['message'], $issues);

        return [
            'success' => count($issues) === 0,
            'total_issues' => count($issues),
            'issues' => $issues,
            'errors' => $errorMessages,
            'manifests_checked' => 1
        ];
    }

    /**
     * Audits codebase health across manifests: deprecated code, expired reviews, missing tests.
     */
    public static function audit(string $dirPath, bool $recursive = true): array
    {
        $safe = self::resolveSafePath($dirPath) ?: $dirPath;
        if (!is_dir($safe)) {
            return ['success' => false, 'error' => "Directory not found: {$dirPath}"];
        }

        $allFiles = [];
        $deprecated = [];
        $expiredReviews = [];
        $missingTests = [];
        $today = date('Y-m-d');

        self::walkManifests($safe, $recursive, function (string $dir, array $manifest) use (&$allFiles, &$deprecated, &$expiredReviews, &$missingTests, $today) {
            $files = $manifest['files'] ?? [];
            foreach ($files as $fname => $fdata) {
                $relPath = self::getRelativePath($dir . DIRECTORY_SEPARATOR . $fname);
                $allFiles[] = $relPath;

                $stab = $fdata['stability'] ?? 'stable';
                if ($stab === 'deprecated' || $stab === 'sunset') {
                    $deprecated[] = [
                        'file' => $relPath,
                        'stability' => $stab,
                        'description' => $fdata['description'] ?? '',
                        'replacement' => $fdata['attributes']['replacement'] ?? null
                    ];
                }

                $rev = $fdata['review_date'] ?? '';
                if ($rev && $rev < $today) {
                    $expiredReviews[] = [
                        'file' => $relPath,
                        'review_date' => $rev,
                        'author' => $fdata['author'] ?? ''
                    ];
                }

                if (empty($fdata['attributes']['test_suite']) && !str_contains($relPath, 'test') && !str_contains($relPath, 'stubs')) {
                    $missingTests[] = $relPath;
                }
            }
        });

        $total = count($allFiles);
        $coverage = $total > 0 ? round((($total - count($missingTests)) / $total) * 100, 1) : 100.0;

        return [
            'success' => true,
            'total_files_audited' => $total,
            'deprecated_count' => count($deprecated),
            'deprecated_files' => $deprecated,
            'expired_review_count' => count($expiredReviews),
            'expired_reviews' => $expiredReviews,
            'missing_tests_count' => count($missingTests),
            'test_coverage_percentage' => $coverage,
            'summary' => [
                'total_files' => $total,
                'deprecated' => count($deprecated),
                'overdue_reviews' => count($expiredReviews),
                'test_coverage_percent' => $coverage
            ]
        ];
    }

    /**
     * Searches across manifests by query text, tag, or stability status.
     */
    public static function search(string|array $query = '', array $filters = [], ?string $startDir = null): array
    {
        if (is_array($query)) {
            $filters = $query;
            $query = '';
        } elseif (is_string($query) && is_dir($query) && empty($startDir)) {
            $startDir = $query;
            $query = '';
        }

        $baseDir = $startDir ? (self::resolveSafePath($startDir) ?: $startDir) : realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 5));
        $results = [];
        $lowerQuery = strtolower(trim((string)$query));
        if ($lowerQuery === '' && !empty($filters['query'])) {
            $lowerQuery = strtolower(trim((string)$filters['query']));
        }
        $targetTag = isset($filters['tag']) ? strtolower(trim($filters['tag'])) : null;
        $targetStability = isset($filters['stability']) ? strtolower(trim($filters['stability'])) : null;
        $targetAuthor = isset($filters['author']) ? strtolower(trim($filters['author'])) : null;

        self::walkManifests($baseDir, true, function (string $dir, array $manifest) use ($lowerQuery, $targetTag, $targetStability, $targetAuthor, &$results) {
            $files = $manifest['files'] ?? [];
            foreach ($files as $fname => $fdata) {
                $desc = strtolower($fdata['description'] ?? '');
                $usage = strtolower($fdata['usage'] ?? '');
                $stab = strtolower($fdata['stability'] ?? 'stable');
                $author = strtolower($fdata['author'] ?? ($fdata['attributes']['author'] ?? ''));
                $tags = array_map('strtolower', $fdata['attributes']['tags'] ?? []);

                if ($targetStability && $stab !== $targetStability) {
                    continue;
                }
                if ($targetTag && !in_array($targetTag, $tags, true)) {
                    continue;
                }
                if ($targetAuthor && !str_contains($author, $targetAuthor)) {
                    continue;
                }
                if ($lowerQuery !== '' && !str_contains(strtolower($fname), $lowerQuery) && !str_contains($desc, $lowerQuery) && !str_contains($usage, $lowerQuery)) {
                    continue;
                }

                $rel = self::getRelativePath($dir . DIRECTORY_SEPARATOR . $fname);
                $results[$rel] = [
                    'name' => $fname,
                    'path' => $rel,
                    'description' => $fdata['description'] ?? '',
                    'usage' => $fdata['usage'] ?? '',
                    'stability' => $stab,
                    'author' => $fdata['author'] ?? '',
                    'tags' => $fdata['attributes']['tags'] ?? []
                ];
            }
        });

        return $results;
    }

    /**
     * Compiles all workspace manifests into a single high-performance array cache and binary SQLite index.
     */
    public static function compileWorkspace(?string $rootDir = null, ?string $customCacheFile = null, ?string $customDbFile = null): array
    {
        $baseDir = $rootDir ? (self::resolveSafePath($rootDir) ?: $rootDir) : realpath(defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 5));
        $compiled = [
            'compiled_at' => date('Y-m-d H:i:s'),
            'total_manifests' => 0,
            'total_files' => 0,
            'directories' => [],
            'files' => []
        ];

        self::walkManifests($baseDir, true, function (string $dir, array $manifest) use (&$compiled) {
            $relDir = self::getRelativePath($dir);
            $compiled['total_manifests']++;
            $compiled['directories'][$relDir] = $manifest['directory'] ?? [];

            foreach ($manifest['files'] ?? [] as $fname => $fdata) {
                $relFile = self::getRelativePath($dir . DIRECTORY_SEPARATOR . $fname);
                $compiled['files'][$relFile] = $fdata;
                $compiled['total_files']++;
            }
        });

        $cacheDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/cache' : dirname(__DIR__, 5) . '/var/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $cacheFile = $customCacheFile ?: ($cacheDir . '/file_props.cache.php');
        $cacheParent = dirname($cacheFile);
        if (!is_dir($cacheParent)) {
            @mkdir($cacheParent, 0777, true);
        }
        file_put_contents($cacheFile, '<?php return ' . var_export($compiled, true) . ';');
        self::$compiledCache = $compiled;

        $dbFile = $customDbFile ?: ($cacheDir . '/file_props.db');
        $dbParent = dirname($dbFile);
        if (!is_dir($dbParent)) {
            @mkdir($dbParent, 0777, true);
        }
        try {
            $pdo = new \PDO('sqlite:' . $dbFile);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec("PRAGMA journal_mode = WAL;");
            $pdo->exec("PRAGMA synchronous = NORMAL;");
            $pdo->exec("CREATE TABLE IF NOT EXISTS file_props_index (path TEXT PRIMARY KEY, metadata TEXT)");
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO file_props_index (path, metadata) VALUES (:path, :metadata) ON CONFLICT(path) DO UPDATE SET metadata = excluded.metadata");
            foreach ($compiled['files'] as $p => $meta) {
                $stmt->execute([':path' => $p, ':metadata' => json_encode($meta, JSON_UNESCAPED_SLASHES)]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // non-blocking
        }

        return [
            'success' => true,
            'cache_file' => $cacheFile,
            'db_file' => $dbFile,
            'total_manifests' => $compiled['total_manifests'],
            'total_files' => $compiled['total_files']
        ];
    }

    /**
     * Retrieves compiled cached props in production with zero disk I/O.
     */
    public static function getCachedProps(string $filePath): ?array
    {
        if (self::$compiledCache === null) {
            $cacheDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/cache' : dirname(__DIR__, 5) . '/var/cache';
            $cacheFile = $cacheDir . '/file_props.cache.php';
            if (file_exists($cacheFile)) {
                self::$compiledCache = @include $cacheFile;
            }
        }

        if (is_array(self::$compiledCache) && isset(self::$compiledCache['files'])) {
            $rel = self::getRelativePath($filePath);
            return self::$compiledCache['files'][$rel] ?? null;
        }

        return null;
    }

    /**
     * Retrieves the reverse cache invalidation graph.
     */
    public static function getCacheGraph(?string $startDir = null): array
    {
        return ManifestCacheGraph::getGraph($startDir);
    }

    /**
     * Analyzes the transitive semantic blast radius for a given target file.
     */
    public static function getBlastRadius(string $filePath, int $maxDepth = 5, ?string $startDir = null): array
    {
        return SemanticBlastRadiusAnalyzer::analyze($filePath, $maxDepth, $startDir);
    }

    /**
     * Retrieves AI directives and system prompt attention beacon for a file.
     */
    public static function getAiDirectives(string $filePath): array
    {
        return AiDirectivesGuard::getDirectives($filePath);
    }

    /**
     * Retrieves enterprise data sovereignty and PII governance policy for a file.
     */
    public static function getDataGovernance(string $filePath): array
    {
        return DataSovereigntyAuditor::getPolicy($filePath);
    }

    /**
     * Retrieves zero-shot architectural prompt context for AI models.
     */
    public static function getAiContext(bool $full = false, ?string $appName = null): string
    {
        return AiArchitectureEngine::getContext($full, $appName);
    }

    /**
     * Introspects the framework capability blueprint for AI models.
     */
    public static function getAiBlueprint(?string $appName = null): array
    {
        return AiArchitectureEngine::getBlueprint($appName);
    }

    /**
     * Retrieves an enterprise architecture recipe for an AI model.
     */
    public static function getAiRecipe(string $feature): ?string
    {
        return AiArchitectureEngine::getRecipe($feature);
    }

    /**
     * Lints code or a file against the 10 SPP Golden Rules.
     */
    public static function lintAiCode(string $code, string $filePath = ''): array
    {
        return AiArchitectureEngine::lintCode($code, $filePath);
    }

    /**
     * Resolves graceful error fallback configuration for a component.
     */
    public static function resolveFallback(string $filePath, \Throwable $e): ?array
    {
        return ManifestContractEngine::resolveFallback($filePath, $e);
    }

    /**
     * Verifies the database contract for a component.
     */
    public static function verifyDbContract(string $filePath, ?object $pdo = null): array
    {
        return ManifestContractEngine::verifyDbContract($filePath, $pdo);
    }

    /**
     * Verifies all declared database contracts across the workspace.
     */
    public static function verifyAllDbContracts(?string $startDir = null, ?object $pdo = null): array
    {
        return ManifestContractEngine::verifyAllDbContracts($startDir, $pdo);
    }

    /**
     * Retrieves the raw YAML contents of a directory manifest for code editor viewing/editing.
     */
    public static function getManifestRaw(string $dirPath): array
    {
        $safe = self::resolveSafePath($dirPath) ?: $dirPath;
        $dir = is_dir($safe) ? $safe : dirname($safe);
        $manifestFile = $dir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;

        if (!file_exists($manifestFile)) {
            $initial = [
                'version' => '1.0',
                'directory' => [
                    'description' => 'Directory: ' . basename($dir),
                    'usage' => 'Application component directory',
                    'stability' => 'stable',
                    'author' => 'SPP Administrator',
                    'review_date' => date('Y-m-d', strtotime('+1 year')),
                    'attributes' => [
                        'tags' => ['app', 'source']
                    ]
                ],
                'files' => [],
                'directories' => []
            ];
            $raw = Yaml::dump($initial, 6, 2);
            return [
                'success' => true,
                'exists' => false,
                'path' => self::getRelativePath($dir),
                'manifest_file' => $manifestFile,
                'raw_yaml' => $raw,
                'yaml' => $raw,
                'parsed' => $initial
            ];
        }

        $raw = (string)@file_get_contents($manifestFile);
        $parsed = self::loadManifest($dir);

        return [
            'success' => true,
            'exists' => true,
            'path' => self::getRelativePath($dir),
            'manifest_file' => $manifestFile,
            'raw_yaml' => $raw,
            'yaml' => $raw,
            'parsed' => $parsed
        ];
    }

    /**
     * Validates raw YAML content against the SPP manifest schema without persisting.
     */
    public static function validateManifestYaml(string $rawYaml): array
    {
        $errors = [];
        $warnings = [];
        $parsed = null;

        if (trim($rawYaml) === '') {
            return [
                'valid' => false,
                'errors' => ['Manifest YAML cannot be empty.'],
                'warnings' => [],
                'parsed' => []
            ];
        }

        try {
            $parsed = Yaml::parse($rawYaml);
            if (!is_array($parsed)) {
                $errors[] = 'Manifest root must parse to a YAML mapping / associative array.';
            }
        } catch (Throwable $e) {
            $errors[] = 'YAML Syntax Error: ' . $e->getMessage();
            return [
                'valid' => false,
                'error' => implode('; ', $errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'parsed' => null
            ];
        }

        if (is_array($parsed)) {
            if (empty($parsed['version'])) {
                $warnings[] = "Missing 'version' tag; defaulting to '1.0'.";
            }

            if (isset($parsed['files']) && is_array($parsed['files'])) {
                foreach ($parsed['files'] as $fName => $fMeta) {
                    if (is_array($fMeta) && !empty($fMeta['stability'])) {
                        $stab = strtolower((string)$fMeta['stability']);
                        if (!in_array($stab, self::VALID_STABILITIES, true)) {
                            $warnings[] = "File '{$fName}' has non-standard stability '{$stab}'. Valid stabilities: [" . implode(', ', self::VALID_STABILITIES) . "].";
                        }
                    }
                }
            }

            if (isset($parsed['directory']) && is_array($parsed['directory'])) {
                if (!empty($parsed['directory']['stability'])) {
                    $dirStab = strtolower((string)$parsed['directory']['stability']);
                    if (!in_array($dirStab, self::VALID_STABILITIES, true)) {
                        $warnings[] = "Directory stability '{$dirStab}' is non-standard. Valid stabilities: [" . implode(', ', self::VALID_STABILITIES) . "].";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'error' => !empty($errors) ? implode('; ', $errors) : null,
            'errors' => $errors,
            'warnings' => $warnings,
            'parsed' => $parsed
        ];
    }

    /**
     * Saves raw manifest YAML atomically after strict syntax and schema verification.
     */
    public static function saveManifestRaw(string $dirPath, string $rawYaml): array
    {
        $validation = self::validateManifestYaml($rawYaml);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => implode('; ', $validation['errors']),
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings']
            ];
        }

        $safe = self::resolveSafePath($dirPath) ?: $dirPath;
        $dir = is_dir($safe) ? $safe : dirname($safe);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $manifestFile = $dir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        $tmpFile = $dir . DIRECTORY_SEPARATOR . '.__tmp_raw_props_' . bin2hex(random_bytes(6)) . '.yml';

        if (@file_put_contents($tmpFile, $rawYaml, LOCK_EX) === false) {
            return ['success' => false, 'error' => "Failed to write temporary manifest to {$tmpFile}."];
        }
        @chmod($tmpFile, 0664);

        if (file_exists($manifestFile)) {
            $bakFile = $manifestFile . '.bak.' . uniqid('', true);
            if (!@rename($manifestFile, $bakFile)) {
                $res = @file_put_contents($manifestFile, $rawYaml, LOCK_EX);
                @unlink($tmpFile);
                unset(self::$runtimeCache[$manifestFile]);
                if ($res === false) {
                    return ['success' => false, 'error' => "Failed to overwrite manifest {$manifestFile}."];
                }
            } else {
                if (!@rename($tmpFile, $manifestFile)) {
                    @rename($bakFile, $manifestFile);
                    @unlink($tmpFile);
                    return ['success' => false, 'error' => "Failed to atomically swap manifest {$manifestFile}."];
                }
                @unlink($bakFile);
            }
        } else {
            if (!@rename($tmpFile, $manifestFile)) {
                $res = @file_put_contents($manifestFile, $rawYaml, LOCK_EX);
                @unlink($tmpFile);
                if ($res === false) {
                    return ['success' => false, 'error' => "Failed to create manifest {$manifestFile}."];
                }
            }
        }

        unset(self::$runtimeCache[$manifestFile]);

        $payload = [
            'directory' => self::getRelativePath($dir),
            'manifest_file' => $manifestFile
        ];
        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('file_props.manifest_saved', $payload);
            \SPP\SPPEvent::triggerHook('file_props:manifest_saved', $payload);
        }

        return [
            'success' => true,
            'message' => 'Manifest YAML validated and saved successfully.',
            'manifest_file' => $manifestFile,
            'warnings' => $validation['warnings'],
            'parsed' => $validation['parsed']
        ];
    }

    /**
     * Computes a line-by-line unified diff between the current disk manifest and new YAML text.
     */
    public static function diffManifest(string $dirPath, string $newYaml): array
    {
        $rawCurrent = self::getManifestRaw($dirPath);
        $oldLines = explode("\n", str_replace("\r\n", "\n", $rawCurrent['raw_yaml'] ?? ''));
        $newLines = explode("\n", str_replace("\r\n", "\n", $newYaml));

        $diff = [];
        $additions = 0;
        $deletions = 0;

        // Simple line diff
        $max = max(count($oldLines), count($newLines));
        for ($i = 0; $i < $max; $i++) {
            $oldLine = $oldLines[$i] ?? null;
            $newLine = $newLines[$i] ?? null;

            if ($oldLine === $newLine) {
                $diff[] = ['type' => 'unchanged', 'line' => $oldLine, 'line_number' => $i + 1];
            } else {
                if ($oldLine !== null) {
                    $diff[] = ['type' => 'removed', 'line' => $oldLine, 'line_number' => $i + 1];
                    $deletions++;
                }
                if ($newLine !== null) {
                    $diff[] = ['type' => 'added', 'line' => $newLine, 'line_number' => $i + 1];
                    $additions++;
                }
            }
        }

        $diffText = '';
        foreach ($diff as $d) {
            $prefix = ($d['type'] === 'added') ? '+ ' : (($d['type'] === 'removed') ? '- ' : '  ');
            $diffText .= $prefix . $d['line'] . "\n";
        }

        return [
            'success' => true,
            'diff' => $diff,
            'diff_text' => $diffText,
            'additions' => $additions,
            'deletions' => $deletions,
            'identical' => ($additions === 0 && $deletions === 0)
        ];
    }

    /**
     * Evaluates comprehensive Manifest OS health across a directory or entire workspace.
     */
    public static function getManifestHealth(string $startDir, bool $recursive = true, bool $verifyIntegrity = false): array
    {
        $safe = self::resolveSafePath($startDir) ?: $startDir;
        if (!is_dir($safe)) {
            return ['success' => false, 'error' => "Directory not found: {$startDir}"];
        }

        $scannedDirs = 0;
        $manifestsDiscovered = 0;
        $validManifests = 0;
        $missingManifests = 0;
        $invalidYaml = 0;
        $untrackedFiles = 0;
        $orphanedEntries = 0;
        $staleEntries = 0;
        $integrityVerified = 0;
        $integrityTampered = 0;
        $capabilityDeclarations = 0;
        $details = [];

        $today = date('Y-m-d');

        $walk = function (string $currentDir) use (
            &$walk, &$scannedDirs, &$manifestsDiscovered, &$validManifests,
            &$missingManifests, &$invalidYaml, &$untrackedFiles, &$orphanedEntries,
            &$staleEntries, &$integrityVerified, &$integrityTampered,
            &$capabilityDeclarations, &$details, $recursive, $verifyIntegrity, $today
        ) {
            $baseName = basename($currentDir);
            if (in_array($baseName, self::DEFAULT_IGNORED_DIRS, true)) {
                return;
            }

            $scannedDirs++;
            $manifestFile = $currentDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
            $relDir = self::getRelativePath($currentDir);

            $manifestFiles = [];
            if (!file_exists($manifestFile)) {
                $missingManifests++;
                if (count($details) < 200) {
                    $details[] = [
                        'type' => 'missing_manifest',
                        'severity' => 'info',
                        'directory' => $relDir,
                        'message' => "Directory '{$relDir}' has no __spp_file_props.yml manifest."
                    ];
                }
            } else {
                $manifestsDiscovered++;
                $raw = @file_get_contents($manifestFile);
                $parsed = null;
                try {
                    $parsed = Yaml::parse((string)$raw);
                    if (!is_array($parsed)) {
                        $invalidYaml++;
                        if (count($details) < 200) {
                            $details[] = [
                                'type' => 'invalid_yaml',
                                'severity' => 'error',
                                'directory' => $relDir,
                                'message' => "Manifest in '{$relDir}' is not a valid YAML mapping."
                            ];
                        }
                        $parsed = null;
                    } else {
                        $validManifests++;
                    }
                } catch (Throwable $e) {
                    $invalidYaml++;
                    if (count($details) < 200) {
                        $details[] = [
                            'type' => 'invalid_yaml',
                            'severity' => 'error',
                            'directory' => $relDir,
                            'message' => "YAML parse exception in '{$relDir}': " . $e->getMessage()
                        ];
                    }
                    $parsed = null;
                }

                if (is_array($parsed)) {
                    // Check security capabilities
                    if (!empty($parsed['directory']['security']) || !empty($parsed['security'])) {
                        $capabilityDeclarations++;
                    }

                    // Check files in manifest against disk
                    $manifestFiles = $parsed['files'] ?? [];
                    if (is_array($manifestFiles)) {
                        foreach ($manifestFiles as $fName => $fMeta) {
                            $realPath = $currentDir . DIRECTORY_SEPARATOR . $fName;
                            if (!file_exists($realPath)) {
                                $orphanedEntries++;
                                if (count($details) < 200) {
                                    $details[] = [
                                        'type' => 'orphaned_file',
                                        'severity' => 'warning',
                                        'directory' => $relDir,
                                        'file' => $fName,
                                        'message' => "File '{$fName}' is tracked in manifest but missing on disk."
                                    ];
                                }
                            } else {
                                // Check stale review date
                                $revDate = $fMeta['review_date'] ?? ($fMeta['attributes']['review_date'] ?? null);
                                if (!empty($revDate) && $revDate < $today) {
                                    $staleEntries++;
                                    if (count($details) < 200) {
                                        $details[] = [
                                            'type' => 'stale_review',
                                            'severity' => 'info',
                                            'directory' => $relDir,
                                            'file' => $fName,
                                            'review_date' => $revDate,
                                            'message' => "File '{$fName}' review date ({$revDate}) has passed."
                                        ];
                                    }
                                }

                                // Check integrity if checksum declared
                                $checksum = $fMeta['checksum'] ?? ($fMeta['attributes']['checksum'] ?? null);
                                if (!empty($checksum) && is_file($realPath)) {
                                    if ($verifyIntegrity) {
                                        $actual = @hash_file('sha256', $realPath);
                                        if ($actual !== $checksum) {
                                            $integrityTampered++;
                                            if (count($details) < 200) {
                                                $details[] = [
                                                    'type' => 'integrity_tampered',
                                                    'severity' => 'critical',
                                                    'directory' => $relDir,
                                                    'file' => $fName,
                                                    'expected_checksum' => $checksum,
                                                    'actual_checksum' => $actual,
                                                    'message' => "Integrity violation: '{$fName}' checksum mismatch."
                                                ];
                                            }
                                        } else {
                                            $integrityVerified++;
                                        }
                                    } else {
                                        $integrityVerified++;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Check disk files against manifest
            $diskEntries = @scandir($currentDir) ?: [];
            foreach ($diskEntries as $de) {
                if ($de === '.' || $de === '..' || $de === self::MANIFEST_FILENAME) {
                    continue;
                }
                if (str_ends_with($de, '.cache') || str_ends_with($de, '.log') || str_ends_with($de, '.bak') || str_ends_with($de, '.tmp')) {
                    continue;
                }
                $itemPath = $currentDir . DIRECTORY_SEPARATOR . $de;
                if (is_file($itemPath) && !isset($manifestFiles[$de])) {
                    $untrackedFiles++;
                    if (count($details) < 200) {
                        $details[] = [
                            'type' => 'untracked_file',
                            'severity' => 'info',
                            'directory' => $relDir,
                            'file' => $de,
                            'message' => "File '{$de}' is untracked in directory manifest."
                        ];
                    }
                }

                if ($recursive && is_dir($itemPath) && !in_array($de, self::DEFAULT_IGNORED_DIRS, true) && !str_starts_with($de, '.')) {
                    $walk($itemPath);
                }
            }
        };

        $walk($safe);

        $healthScore = 100;
        if ($missingManifests > 0) $healthScore -= min(30, $missingManifests * 15);
        if ($untrackedFiles > 0) $healthScore -= min(20, $untrackedFiles * 2);
        if ($invalidYaml > 0) $healthScore -= ($invalidYaml * 20);
        if ($integrityTampered > 0) $healthScore -= ($integrityTampered * 30);
        if ($orphanedEntries > 0) $healthScore -= min(20, $orphanedEntries * 2);
        if ($staleEntries > 0) $healthScore -= min(10, $staleEntries);
        $healthScore = max(0, min(100, $healthScore));

        return [
            'success' => true,
            'root' => self::getRelativePath($safe),
            'health_score' => $healthScore,
            'score' => $healthScore,
            'directories_scanned' => $scannedDirs,
            'manifests_discovered' => $manifestsDiscovered,
            'total_manifests' => $manifestsDiscovered,
            'valid_manifests' => $validManifests,
            'missing_manifests' => $missingManifests,
            'invalid_yaml' => $invalidYaml,
            'untracked_files' => $untrackedFiles,
            'orphaned_entries' => $orphanedEntries,
            'stale_entries' => $staleEntries,
            'integrity_verified' => $integrityVerified,
            'integrity_tampered' => $integrityTampered,
            'integrity_failures' => $integrityTampered,
            'capability_policies' => $capabilityDeclarations,
            'total_issues' => ($missingManifests + $untrackedFiles + $invalidYaml + $orphanedEntries + $staleEntries + $integrityTampered),
            'summary' => [
                'directories_scanned' => $scannedDirs,
                'manifests_discovered' => $manifestsDiscovered,
                'total_manifests' => $manifestsDiscovered,
                'valid_manifests' => $validManifests,
                'missing_manifests' => $missingManifests,
                'invalid_yaml' => $invalidYaml,
                'untracked_files' => $untrackedFiles,
                'orphaned_entries' => $orphanedEntries,
                'stale_entries' => $staleEntries,
                'integrity_verified' => $integrityVerified,
                'integrity_tampered' => $integrityTampered,
                'integrity_failures' => $integrityTampered,
                'capability_policies' => $capabilityDeclarations
            ],
            'details' => array_slice($details, 0, 100)
        ];
    }

    /**
     * Automatically heals a directory or workspace manifests: cleans orphans and tracks untracked files.
     */
    public static function healDirectory(string $dirPath, bool $recursive = true): array
    {
        $res = self::syncDirectory($dirPath, $recursive, true, false);
        $res['healed_entries'] = ($res['synced'] ?? 0) + ($res['pruned'] ?? 0) + ($res['updated'] ?? 0);
        return $res;
    }

    /**
     * Traverses the directory tree visiting every manifest found.
     */
    protected static function walkManifests(string $dir, bool $recursive, callable $callback): void
    {
        $manifestPath = $dir . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
        if (file_exists($manifestPath)) {
            $manifest = self::loadManifest($dir);
            $callback($dir, $manifest);
        }

        if ($recursive) {
            $entries = @scandir($dir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || in_array($entry, self::DEFAULT_IGNORED_DIRS, true) || (is_dir($dir . DIRECTORY_SEPARATOR . $entry) && str_starts_with($entry, '.'))) {
                    continue;
                }
                $sub = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($sub)) {
                    self::walkManifests($sub, true, $callback);
                }
            }
        }
    }
}
