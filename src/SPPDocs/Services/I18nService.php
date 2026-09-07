<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * I18nService
 * Multi-language documentation management, translation parity matrix,
 * out-of-sync drift detection, and side-by-side localization workbench.
 */
class I18nService
{
    public const SUPPORTED_LOCALES = [
        'en' => ['code' => 'en', 'name' => 'English', 'flag' => '🇺🇸'],
        'es' => ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸'],
        'fr' => ['code' => 'fr', 'name' => 'Français', 'flag' => '🇫🇷'],
        'de' => ['code' => 'de', 'name' => 'Deutsch', 'flag' => '🇩🇪'],
        'ja' => ['code' => 'ja', 'name' => '日本語', 'flag' => '🇯🇵'],
        'zh' => ['code' => 'zh', 'name' => '中文', 'flag' => '🇨🇳'],
        'pt' => ['code' => 'pt', 'name' => 'Português', 'flag' => '🇧🇷'],
        'hi' => ['code' => 'hi', 'name' => 'हिन्दी', 'flag' => '🇮🇳'],
    ];

    /**
     * Get active configured locales for a project.
     */
    public static function getProjectLocales(array $project): array
    {
        $configured = $project['locales'] ?? ['en', 'es', 'fr', 'de', 'ja', 'zh'];
        $locales = [];
        foreach ($configured as $code) {
            $code = strtolower(trim($code));
            if (isset(self::SUPPORTED_LOCALES[$code])) {
                $locales[$code] = self::SUPPORTED_LOCALES[$code];
            } else {
                $locales[$code] = ['code' => $code, 'name' => strtoupper($code), 'flag' => '🌐'];
            }
        }
        return $locales;
    }

    /**
     * Resolve docs root directory for a given version.
     */
    public static function resolveDocsDir(array $project, string $version): string
    {
        $rel = $project['versions'][$version] ?? ($project['pages_dir'] ?? 'docs');
        if (is_dir($rel)) {
            return str_replace('\\', '/', realpath($rel));
        }
        if (strpos($rel, 'C:/') === 0 || strpos($rel, 'c:/') === 0) {
            $rel = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $rel);
        }
        if (!str_starts_with($rel, '/') && !str_contains($rel, ':\\')) {
            $base = defined('APP_BASE_DIR') ? APP_BASE_DIR : dirname(SPP_BASE_DIR);
            $rel = $base . '/' . $rel;
        }
        return str_replace('\\', '/', realpath($rel) ?: $rel);
    }

    /**
     * Resolve locale specific documentation directory.
     */
    public static function resolveLocaleDir(array $project, string $version, string $locale): string
    {
        $base = self::resolveDocsDir($project, $version);
        if ($locale === 'en' || $locale === ($project['default_locale'] ?? 'en')) {
            return $base;
        }
        return $base . '/' . $locale;
    }

    /**
     * Compute full Translation Parity Matrix for a project and version.
     */
    public static function getParityMatrix(array $project, string $version): array
    {
        $locales = self::getProjectLocales($project);
        $defaultLocale = $project['default_locale'] ?? 'en';
        $sourceDir = self::resolveLocaleDir($project, $version, $defaultLocale);

        $sourceFiles = [];
        if (is_dir($sourceDir)) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir));
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $path = $file->getPathname();
                    if (str_contains($path, '.revisions')) continue;

                    $rel = substr($path, strlen($sourceDir) + 1);
                    $rel = str_replace('\\', '/', $rel);

                    // Skip subdirectories named after other locales
                    $firstSeg = explode('/', $rel)[0];
                    if (isset($locales[$firstSeg]) && $firstSeg !== $defaultLocale) {
                        continue;
                    }

                    $slug = substr($rel, 0, -3); // remove .md

                    // Extract title from frontmatter or heading
                    $raw = file_get_contents($path);
                    $title = $slug;
                    if (preg_match('/^---\s*.*?title:\s*["\']?(.*?)["\']?\s*\n.*?---/s', $raw, $m)) {
                        $title = trim($m[1]);
                    } elseif (preg_match('/^#\s+(.+)$/m', $raw, $m)) {
                        $title = trim($m[1]);
                    }

                    $sourceFiles[$slug] = [
                        'slug' => $slug,
                        'file' => $rel,
                        'title' => $title,
                        'mtime' => filemtime($path),
                    ];
                }
            }
        }

        ksort($sourceFiles);

        // Stats collector per locale
        $targetLocales = array_diff(array_keys($locales), [$defaultLocale]);
        $stats = [];
        foreach ($targetLocales as $loc) {
            $stats[$loc] = [
                'total' => count($sourceFiles),
                'synchronized' => 0,
                'outdated' => 0,
                'missing' => 0,
                'percent' => 0,
            ];
        }

        $matrix = [];
        foreach ($sourceFiles as $slug => $srcInfo) {
            $row = [
                'slug' => $slug,
                'title' => $srcInfo['title'],
                'source_mtime' => $srcInfo['mtime'],
                'locales' => [],
            ];

            foreach ($targetLocales as $loc) {
                $locDir = self::resolveLocaleDir($project, $version, $loc);
                $targetFile = $locDir . '/' . $srcInfo['file'];

                if (!file_exists($targetFile)) {
                    $row['locales'][$loc] = [
                        'status' => 'missing',
                        'mtime' => 0,
                    ];
                    $stats[$loc]['missing']++;
                } else {
                    $tgtMtime = filemtime($targetFile);
                    if ($tgtMtime >= $srcInfo['mtime']) {
                        $row['locales'][$loc] = [
                            'status' => 'synchronized',
                            'mtime' => $tgtMtime,
                        ];
                        $stats[$loc]['synchronized']++;
                    } else {
                        $row['locales'][$loc] = [
                            'status' => 'outdated',
                            'mtime' => $tgtMtime,
                            'diff_seconds' => $srcInfo['mtime'] - $tgtMtime,
                        ];
                        $stats[$loc]['outdated']++;
                    }
                }
            }

            $matrix[] = $row;
        }

        // Calculate completeness percentages
        foreach ($stats as $loc => &$s) {
            if ($s['total'] > 0) {
                $s['percent'] = round(($s['synchronized'] / $s['total']) * 100, 1);
            }
        }

        return [
            'default_locale' => $defaultLocale,
            'locales' => $locales,
            'target_locales' => $targetLocales,
            'stats' => $stats,
            'matrix' => $matrix,
            'total_source_docs' => count($sourceFiles),
        ];
    }

    /**
     * Get content of source and translated page for side-by-side editing.
     */
    public static function getTranslationPair(array $project, string $version, string $locale, string $slug): array
    {
        $defaultLocale = $project['default_locale'] ?? 'en';
        $srcDir = self::resolveLocaleDir($project, $version, $defaultLocale);
        $tgtDir = self::resolveLocaleDir($project, $version, $locale);

        $srcFile = $srcDir . '/' . $slug . '.md';
        $tgtFile = $tgtDir . '/' . $slug . '.md';

        $sourceContent = file_exists($srcFile) ? file_get_contents($srcFile) : '';
        $targetContent = file_exists($tgtFile) ? file_get_contents($tgtFile) : '';

        return [
            'slug' => $slug,
            'locale' => $locale,
            'source_content' => $sourceContent,
            'target_content' => $targetContent,
            'is_missing' => !file_exists($tgtFile),
            'source_mtime' => file_exists($srcFile) ? filemtime($srcFile) : 0,
            'target_mtime' => file_exists($tgtFile) ? filemtime($tgtFile) : 0,
        ];
    }

    /**
     * Save localized page content.
     */
    public static function saveTranslation(array $project, string $version, string $locale, string $slug, string $content): bool
    {
        $tgtDir = self::resolveLocaleDir($project, $version, $locale);
        $tgtFile = $tgtDir . '/' . $slug . '.md';

        $parent = dirname($tgtFile);
        if (!is_dir($parent)) {
            @mkdir($parent, 0777, true);
        }

        return (bool)@file_put_contents($tgtFile, $content, LOCK_EX);
    }
}