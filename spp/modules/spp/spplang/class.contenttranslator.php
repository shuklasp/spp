<?php

namespace SPPMod\SPPLang;

/**
 * Class ContentTranslator
 * Manages dynamic content translation using the EAV (Entity-Attribute-Value) pattern.
 */
class ContentTranslator
{
    private static ?\SPPMod\SPPDB\SPPDB $dbInstance = null;
    private static array $cache = [];

    private static function getDB(): \SPPMod\SPPDB\SPPDB
    {
        if (self::$dbInstance === null) {
            self::$dbInstance = new \SPPMod\SPPDB\SPPDB();
        }
        return self::$dbInstance;
    }

    /**
     * Ensures the global content translation schema exists.
     */
    public static function ensureSchema(): void
    {
        $db = self::getDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('content_translations');
        if (!$db->tableExists($table)) {
            $db->exec_squery("CREATE TABLE %tab% (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type VARCHAR(100) NOT NULL,
                entity_id VARCHAR(100) NOT NULL,
                locale VARCHAR(10) NOT NULL,
                field_name VARCHAR(100) NOT NULL,
                translation TEXT
            )", $table);

            try {
                // Ensure fast lookups for specific fields
                $db->exec_squery("CREATE UNIQUE INDEX IF NOT EXISTS idx_content_translations_lookup ON %tab% (entity_type, entity_id, locale, field_name)", $table);
            } catch (\Exception $e) {
                // Ignore silent unique index parsing constraint violations under emulated XDB engine
            }
        }
    }

    /**
     * Preloads translations for a batch of entity IDs in a single query, eliminating N+1 queries.
     *
     * @param string $entityType e.g. 'article'
     * @param array $entityIds List of primary keys
     * @param string|null $locale Target locale
     */
    public static function preloadTranslations(string $entityType, array $entityIds, ?string $locale = null): void
    {
        if (empty($entityIds)) return;
        $locale = $locale ?? \SPP\Core\Translation::getLocale();
        self::ensureSchema();
        $db = self::getDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('content_translations');

        $inClause = implode(',', array_fill(0, count($entityIds), '?'));
        $params = array_merge([$entityType, $locale], array_map('strval', $entityIds));

        $res = $db->exec_squery(
            "SELECT entity_id, field_name, translation FROM %tab% WHERE entity_type = ? AND locale = ? AND entity_id IN ($inClause)",
            $table,
            $params
        );

        if (!empty($res)) {
            foreach ($res as $row) {
                $cacheKey = "{$entityType}_{$row['entity_id']}_{$locale}_{$row['field_name']}";
                self::$cache[$cacheKey] = $row['translation'];
            }
        }
    }

    /**
     * Fetch a translated field for a specific entity.
     *
     * @param string $entityType e.g. 'article'
     * @param string|int $entityId The primary key of the entity
     * @param string $fieldName The column/field name
     * @param string|null $locale The target locale (defaults to current active locale)
     * @return string|null The translated text or null if not found
     */
    public static function getTranslation(string $entityType, $entityId, string $fieldName, ?string $locale = null): ?string
    {
        $locale = $locale ?? \SPP\Core\Translation::getLocale();
        $cacheKey = "{$entityType}_{$entityId}_{$locale}_{$fieldName}";

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        self::ensureSchema();
        $db = self::getDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('content_translations');

        $res = $db->exec_squery(
            "SELECT translation FROM %tab% WHERE entity_type = ? AND entity_id = ? AND locale = ? AND field_name = ? LIMIT 1",
            $table,
            [$entityType, (string) $entityId, $locale, $fieldName]
        );

        if (!empty($res) && isset($res[0]['translation'])) {
            self::$cache[$cacheKey] = $res[0]['translation'];
            return self::$cache[$cacheKey];
        }

        self::$cache[$cacheKey] = null;
        return null;
    }

    /**
     * Save a translation for a specific entity field.
     */
    public static function setTranslation(string $entityType, $entityId, string $fieldName, string $translation, ?string $locale = null): void
    {
        $locale = $locale ?? \SPP\Core\Translation::getLocale();
        self::ensureSchema();

        $db = self::getDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('content_translations');

        $res = $db->exec_squery(
            "SELECT id FROM %tab% WHERE entity_type = ? AND entity_id = ? AND locale = ? AND field_name = ?",
            $table,
            [$entityType, (string) $entityId, $locale, $fieldName]
        );

        if (!empty($res)) {
            $db->exec_squery(
                "UPDATE %tab% SET translation = ? WHERE id = ?",
                $table,
                [$translation, $res[0]['id']]
            );
        } else {
            $db->exec_squery(
                "INSERT INTO %tab% (entity_type, entity_id, locale, field_name, translation) VALUES (?, ?, ?, ?, ?)",
                $table,
                [$entityType, (string) $entityId, $locale, $fieldName, $translation]
            );
        }

        $cacheKey = "{$entityType}_{$entityId}_{$locale}_{$fieldName}";
        self::$cache[$cacheKey] = $translation;
    }
}
