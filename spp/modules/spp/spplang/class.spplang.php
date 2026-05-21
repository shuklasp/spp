<?php
namespace SPPMod\SPPLang;

/**
 * Class SPPLang
 * Translation scanner, database storage repository, and cache manager.
 */
class SPPLang
{
    /**
     * Ensure translations dynamic SQLite table exists.
     */
    public static function ensureSchema(): void
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');
        if (!$db->tableExists($table)) {
            $db->exec_squery("CREATE TABLE %tab% (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_code TEXT NOT NULL,
                locale VARCHAR(10) NOT NULL,
                translation TEXT,
                status VARCHAR(20) DEFAULT 'active'
            )", $table);
            
            try {
                $db->exec_squery("CREATE UNIQUE INDEX IF NOT EXISTS idx_translations_key_locale ON %tab% (key_code, locale)", $table);
            } catch (\Exception $e) {
                // Ignore silent unique index parsing constraint violations under emulated XDB engine
            }
        }
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
                if ($ext === 'php' || $ext === 'yml' || $ext === 'yaml') {
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
                }
            }
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');
        $discovered = array_keys($keys);
        $newlyAdded = [];

        foreach ($discovered as $key) {
            $res = $db->exec_squery("SELECT id FROM %tab% WHERE key_code = ? AND locale = ?", $table, [$key, $locale]);
            if (empty($res)) {
                $db->exec_squery("INSERT INTO %tab% (key_code, locale, translation, status) VALUES (?, ?, ?, 'active')", 
                    $table, [$key, $locale, $key]
                );
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
        self::ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');
        
        $res = $db->exec_squery("SELECT id FROM %tab% WHERE key_code = ? AND locale = ?", $table, [$key, $locale]);
        if (!empty($res)) {
            $db->exec_squery("UPDATE %tab% SET translation = ?, status = ? WHERE key_code = ? AND locale = ?", 
                $table, [$translation, $status, $key, $locale]
            );
        } else {
            $db->exec_squery("INSERT INTO %tab% (key_code, locale, translation, status) VALUES (?, ?, ?, ?)", 
                $table, [$key, $locale, $translation, $status]
            );
        }
    }

    /**
     * Fetch list of translation keys matching criteria.
     */
    public static function getTranslations(array $filters = []): array
    {
        self::ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');
        
        $sql = "SELECT * FROM %tab%";
        $clauses = [];
        $values = [];

        if (!empty($filters['locale'])) {
            $clauses[] = "locale = ?";
            $values[] = $filters['locale'];
        }
        if (!empty($filters['status'])) {
            $clauses[] = "status = ?";
            $values[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $clauses[] = "(key_code LIKE ? OR translation LIKE ?)";
            $values[] = '%' . $filters['search'] . '%';
            $values[] = '%' . $filters['search'] . '%';
        }

        if (!empty($clauses)) {
            $sql .= " WHERE " . implode(" AND ", $clauses);
        }

        $sql .= " ORDER BY key_code ASC";

        return $db->exec_squery($sql, $table, $values);
    }
}
