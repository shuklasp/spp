<?php

namespace SPP\Core;

/**
 * Class Translation
 * Provides dynamic interface translation mapping loaded from flat JSON files.
 */
class Translation
{
    private static array $translations = [];
    private static string $locale = 'en';

    /**
     * Load translation catalog for the specified locale.
     *
     * @param string $locale Locale tag (e.g. 'es', 'hi')
     */
    public static function load(string $locale): void
    {
        self::$locale = $locale;
        if (!isset(self::$translations[$locale])) {
            self::$translations[$locale] = [];
        }

        // Candidates for translation dictionaries
        $candidates = [
            SPP_APP_DIR . "/src/lekhak/resources/translations/{$locale}.json",
            SPP_APP_DIR . "/src/lekhak/translations/{$locale}.json",
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        self::$translations[$locale] = array_merge(self::$translations[$locale], $decoded);
                    }
                }
            }
        }
    }

    /**
     * Resolve interface key translation based on loaded catalog.
     * Supports ICU MessageFormat for pluralization and interpolation.
     *
     * @param string $key Translatable label identifier
     * @param array|string $paramsOrLocale Parameters array OR optional override locale
     * @param string|null $locale Optional override locale if parameters provided
     * @return string Translated string or original key fallback
     */
    public static function translate(string $key, $paramsOrLocale = [], ?string $locale = null): string
    {
        $params = [];
        if (is_string($paramsOrLocale)) {
            $locale = $paramsOrLocale;
        } elseif (is_array($paramsOrLocale)) {
            $params = $paramsOrLocale;
        }

        $loc = $locale ?? self::$locale;
        $formatString = null;

        if (isset(self::$translations[$loc][$key])) {
            $formatString = self::$translations[$loc][$key];
        } else {
            // Fallback to SQLite DB table spp_translations if class exists
            if (class_exists('\\SPP\\DB')) {
                try {
                    $db = \SPP\DB::getInstance();
                    $table = \SPP\DB::sppTable('translations');
                    if ($db->tableExists($table)) {
                        $res = $db->execute_query(
                            "SELECT translation FROM {$table} WHERE key_code = ? AND locale = ? AND status = 'active' LIMIT 1",
                            [$key, $loc]
                        );
                        if (!empty($res) && !empty($res[0]['translation'])) {
                            self::$translations[$loc][$key] = $res[0]['translation'];
                            $formatString = $res[0]['translation'];
                        }
                    }
                } catch (\Exception $e) {
                    // Retain strict safety
                }
            }
        }

        if ($formatString === null) {
            $formatString = $key;
        }

        if (empty($params)) {
            return $formatString;
        }

        // Use ICU MessageFormatter if available
        if (extension_loaded('intl')) {
            try {
                $formatter = new \MessageFormatter($loc, $formatString);
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

        // Basic fallback interpolation if intl fails or is absent
        foreach ($params as $k => $v) {
            if (is_scalar($v)) {
                $formatString = str_replace('{' . $k . '}', (string)$v, $formatString);
            }
        }

        return $formatString;
    }

    /**
     * Get active locale.
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }
}

// Register global shorthand translation helper
if (!function_exists('__')) {
    function __($key, $paramsOrLocale = [], ?string $locale = null)
    {
        return \SPP\Core\Translation::translate($key, $paramsOrLocale, $locale);
    }
}
