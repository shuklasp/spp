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
     *
     * @param string $key Translatable label identifier
     * @param string|null $locale Optional override locale
     * @return string Translated string or original key fallback
     */
    public static function translate(string $key, ?string $locale = null): string
    {
        $loc = $locale ?? self::$locale;

        if (isset(self::$translations[$loc][$key])) {
            return self::$translations[$loc][$key];
        }

        // Fallback to SQLite DB table spp_translations if class exists
        if (class_exists('\SPPMod\SPPDB\SPPDB')) {
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');
                if ($db->tableExists($table)) {
                    $res = $db->exec_squery(
                        "SELECT translation FROM %tab% WHERE key_code = ? AND locale = ? AND status = 'active' LIMIT 1",
                        $table,
                        [$key, $loc]
                    );
                    if (!empty($res) && !empty($res[0]['translation'])) {
                        self::$translations[$loc][$key] = $res[0]['translation'];
                        return $res[0]['translation'];
                    }
                }
            } catch (\Exception $e) {
                // Retain strict safety
            }
        }

        return $key;
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
    function __($key, $locale = null)
    {
        return \SPP\Core\Translation::translate($key, $locale);
    }
}
