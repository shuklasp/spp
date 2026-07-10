<?php

namespace SPPMod\SPPLang;

/**
 * Class SPPLang
 * Translation scanner, database storage repository, and cache manager.
 */
class SPPLang
{
    private static ?TranslationRepositoryInterface $repository = null;

    public static function setRepository(TranslationRepositoryInterface $repo): void
    {
        self::$repository = $repo;
    }

    public static function getRepository(): TranslationRepositoryInterface
    {
        if (self::$repository === null) {
            self::$repository = new SqlTranslationRepository();
        }
        return self::$repository;
    }

    /**
     * Ensure translations repository schema is ready.
     */
    public static function ensureSchema(): void
    {
        self::getRepository()->ensureSchema();
    }

    /**
     * Scans folders recursively parsing __('key') or __("key") triggers.
     * Inserts new labels into database.
     *
     * @param string $dir Target search folder path
     * @param string $locale Locale context
     * @return array Discovered new keys list
     */
    public static function scanDirectory(string $dir, string $locale = 'en'): array
    {
        self::ensureSchema();
        $keys = [];

        if (!is_dir($dir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if ($ext === 'php' || $ext === 'yml' || $ext === 'yaml' || $ext === 'html' || $ext === 'js') {
                    $content = file_get_contents($file->getPathname());
                    if ($content === false) {
                        continue;
                    }

                    // Look for __('key') or __("key") invocations
                    preg_match_all('/__\(\s*(["\'])(.*?)\1\s*(?:,\s*(["\'])(.*?)\3)?\s*\)/s', $content, $matches);

                    if (!empty($matches[2])) {
                        foreach ($matches[2] as $k) {
                            $k = trim($k);
                            if ($k !== '') {
                                $keys[$k] = true;
                            }
                        }
                    }

                    // Look for <spp-lang key="key"> or <spp-trans key="key"> in html/php/js/etc.
                    preg_match_all('/<spp-(?:lang|trans)\s+key=(["\'])(.*?)\1/is', $content, $tagMatches);
                    if (!empty($tagMatches[2])) {
                        foreach ($tagMatches[2] as $k) {
                            $k = trim($k);
                            if ($k !== '') {
                                $keys[$k] = true;
                            }
                        }
                    }
                }
            }
        }

        $repo = self::getRepository();
        $discovered = array_keys($keys);
        $newlyAdded = [];

        foreach ($discovered as $key) {
            if (!$repo->keyExists($key, $locale)) {
                $repo->save($key, $locale, $key);
                $newlyAdded[] = $key;
            }
        }

        return $newlyAdded;
    }

    /**
     * Persist or update translatable keys.
     */
    public static function saveTranslation(string $key, string $locale, string $translation, string $status = 'active'): void
    {
        self::getRepository()->save($key, $locale, $translation, $status);
    }

    /**
     * Fetch list of translation keys matching criteria.
     */
    public static function getTranslations(array $filters = []): array
    {
        return self::getRepository()->getMany($filters);
    }

    /**
     * Retrieve a specific translation by key and locale, with optional parameter interpolation / ICU MessageFormat.
     */
    public static function getTranslation(string $key, string $locale = 'en', array $params = []): string
    {
        $translation = self::getRepository()->getOne($key, $locale);

        if (empty($params)) {
            return $translation;
        }

        if (extension_loaded('intl')) {
            try {
                $formatter = new \MessageFormatter($locale, $translation);
                if ($formatter) {
                    $result = $formatter->format($params);
                    if ($result !== false) {
                        return $result;
                    }
                }
            } catch (\Exception $e) {
                // fallback below
            }
        }

        foreach ($params as $k => $v) {
            if (is_scalar($v)) {
                $translation = str_replace('{' . $k . '}', (string)$v, $translation);
            }
        }

        return $translation;
    }
}
