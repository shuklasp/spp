<?php

namespace SPP;

/**
 * class SPPFS
 * Handles File system in SPP.
 *
 * @author Satya Prakash Shukla
 */
class SPPFS extends \SPP\SPPObject
{
    public const EXCLUDED_DIRS = [
        '.git',
        'vendor',
        'node_modules',
        'var/cache',
        'var/sessions',
        'tmp',
        'temp',
        '.cache',
        'dist'
    ];

    public static function findFile($file, $root = SPP_BASE_DIR)
    {
        $filearray = [];
        $stack = new \SPP\Stack();
        do {
            if (!is_dir($root)) {
                continue;
            }

            $dir = @opendir($root);
            if ($dir === false) {
                // Log failure but continue searching other branches in the stack
                continue;
            }

            while (($fl = readdir($dir)) !== false) {
                if ($fl != '.' && $fl != '..') {
                    $path = $root . SPP_DS . $fl;
                    if (is_dir($path)) {
                        $stack->push($path);
                    } else {
                        if ($file == $fl) {
                            $filearray[] = $path;
                        }
                    }
                }
            }
            closedir($dir);
        } while (($root = $stack->pop()) !== false);

        return $filearray;
    }

    /**
     * High-speed fuzzy search for files by name/path across the workspace.
     */
    public static function searchFiles(string $query, int $limit = 20, ?string $baseDir = null): array
    {
        $baseDir = $baseDir ?: (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : getcwd());
        $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');
        $query = trim($query);

        $results = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                function (\SplFileInfo $file, $key, $iterator) {
                    if ($iterator->hasChildren()) {
                        $dirName = $file->getFilename();
                        return !in_array($dirName, self::EXCLUDED_DIRS, true) && !str_starts_with($dirName, '.');
                    }
                    return true;
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $exact = [];
        $prefix = [];
        $contains = [];
        $pathContains = [];

        $lowerQuery = strtolower($query);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) continue;

            $fullPath = str_replace('\\', '/', $fileInfo->getPathname());
            $relPath = ltrim(str_replace($baseDir, '', $fullPath), '/');
            $fileName = $fileInfo->getFilename();
            $lowerName = strtolower($fileName);
            $lowerRel = strtolower($relPath);

            $dir = dirname($relPath);
            if ($dir === '.') $dir = '';
            $entry = [
                'path' => $relPath,
                'name' => $fileName,
                'dir' => $dir,
                'ext' => strtolower($fileInfo->getExtension())
            ];

            if ($query === '') {
                $results[] = $entry;
                if (count($results) >= $limit) break;
                continue;
            }

            if ($lowerName === $lowerQuery) {
                $exact[] = $entry;
            } elseif (str_starts_with($lowerName, $lowerQuery)) {
                $prefix[] = $entry;
            } elseif (str_contains($lowerName, $lowerQuery)) {
                $contains[] = $entry;
            } elseif (str_contains($lowerRel, $lowerQuery)) {
                $pathContains[] = $entry;
            }

            if (count($exact) + count($prefix) + count($contains) >= $limit * 2) {
                break;
            }
        }

        if ($query !== '') {
            $merged = array_merge($exact, $prefix, $contains, $pathContains);
            $results = array_slice($merged, 0, $limit);
        }

        return [
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'results' => $results
        ];
    }

    /**
     * Recursively searches file contents across workspace files matching query (Grep).
     */
    public static function searchContent(string $query, array $options = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['success' => true, 'query' => '', 'count' => 0, 'files_count' => 0, 'results' => []];
        }

        $baseDir = $options['base_dir'] ?? (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : getcwd());
        $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');

        $isRegex = !empty($options['is_regex']);
        $matchCase = !empty($options['match_case']);
        $wholeWord = !empty($options['whole_word']);
        $filePattern = trim($options['file_pattern'] ?? '');
        $limit = (int)($options['limit'] ?? 500);

        // Pattern filter
        $patterns = [];
        if ($filePattern !== '') {
            $patterns = array_map('trim', explode(',', $filePattern));
        }

        // Build search regex
        if ($isRegex) {
            $regex = '/' . str_replace('/', '\\/', $query) . '/' . ($matchCase ? '' : 'i');
        } else {
            $escaped = preg_quote($query, '/');
            if ($wholeWord) {
                $escaped = '\\b' . $escaped . '\\b';
            }
            $regex = '/' . $escaped . '/' . ($matchCase ? '' : 'i');
        }

        $binaryExtensions = ['png', 'jpg', 'jpeg', 'gif', 'ico', 'pdf', 'zip', 'gz', 'tar', 'sqlite', 'sqlite3', 'db', 'exe', 'bin', 'woff', 'woff2', 'ttf', 'eot', 'mp3', 'mp4'];
        $results = [];
        $totalMatches = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                function (\SplFileInfo $file, $key, $iterator) {
                    if ($iterator->hasChildren()) {
                        $dirName = $file->getFilename();
                        return !in_array($dirName, self::EXCLUDED_DIRS, true) && !str_starts_with($dirName, '.');
                    }
                    return true;
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) continue;

            $ext = strtolower($fileInfo->getExtension());
            if (in_array($ext, $binaryExtensions, true)) continue;

            $fullPath = str_replace('\\', '/', $fileInfo->getPathname());
            $relPath = ltrim(str_replace($baseDir, '', $fullPath), '/');
            $fileName = $fileInfo->getFilename();

            // Match file pattern if specified
            if (!empty($patterns)) {
                $matchedPattern = false;
                foreach ($patterns as $pat) {
                    if (fnmatch($pat, $fileName) || fnmatch($pat, $relPath)) {
                        $matchedPattern = true;
                        break;
                    }
                }
                if (!$matchedPattern) continue;
            }

            // Stream line by line to keep memory bounded
            $handle = @fopen($fullPath, 'r');
            if (!$handle) continue;

            $lineNumber = 0;
            $fileMatches = [];

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if (@preg_match($regex, $line)) {
                    if (@preg_match_all($regex, $line, $matches, PREG_OFFSET_CAPTURE)) {
                        $firstOffset = $matches[0][0][1] ?? 0;
                        $matchedText = $matches[0][0][0] ?? $query;
                        $preview = trim($line);
                        if (strlen($preview) > 160) {
                            $preview = substr($preview, 0, 157) . '...';
                        }

                        $fileMatches[] = [
                            'line' => $lineNumber,
                            'column' => $firstOffset + 1,
                            'preview' => $preview,
                            'match' => $matchedText
                        ];
                        $totalMatches++;

                        if ($totalMatches >= $limit) break 2;
                    }
                }
            }
            fclose($handle);

            if (!empty($fileMatches)) {
                $results[] = [
                    'file' => $relPath,
                    'count' => count($fileMatches),
                    'matches' => $fileMatches
                ];
            }

            if ($totalMatches >= $limit) break;
        }

        return [
            'success' => true,
            'query' => $query,
            'count' => $totalMatches,
            'files_count' => count($results),
            'results' => $results
        ];
    }

    /**
     * Atomically replaces query string across specified workspace files.
     */
    public static function replaceContent(string $query, string $replacement, array $files = [], array $options = []): array
    {
        $baseDir = $options['base_dir'] ?? (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : getcwd());
        $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');

        $isRegex = !empty($options['is_regex']);
        $matchCase = !empty($options['match_case']);
        $wholeWord = !empty($options['whole_word']);

        if ($isRegex) {
            $regex = '/' . str_replace('/', '\\/', $query) . '/' . ($matchCase ? '' : 'i');
        } else {
            $escaped = preg_quote($query, '/');
            if ($wholeWord) {
                $escaped = '\\b' . $escaped . '\\b';
            }
            $regex = '/' . $escaped . '/' . ($matchCase ? '' : 'i');
        }

        $filesChanged = 0;
        $totalReplacements = 0;

        foreach ($files as $relPath) {
            $relPathNorm = str_replace('\\', '/', (string)$relPath);
            $fullPath = (file_exists($relPath) && is_file($relPath)) ? $relPath : ($baseDir . '/' . ltrim($relPathNorm, '/'));
            if (!file_exists($fullPath) || !is_writable($fullPath)) continue;

            $content = file_get_contents($fullPath);
            $newContent = preg_replace($regex, $replacement, $content, -1, $count);

            if ($count > 0 && $newContent !== null) {
                file_put_contents($fullPath, $newContent);
                $filesChanged++;
                $totalReplacements += $count;
            }
        }

        return [
            'success' => true,
            'files_changed' => $filesChanged,
            'files_count' => $filesChanged,
            'replacements_count' => $totalReplacements
        ];
    }
}
