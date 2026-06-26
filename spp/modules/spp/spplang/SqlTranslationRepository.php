<?php
namespace SPPMod\SPPLang;

class SqlTranslationRepository implements TranslationRepositoryInterface
{
    private ?\SPPMod\SPPDB\SPPDB $dbInstance = null;

    private function getDB(): \SPPMod\SPPDB\SPPDB
    {
        if ($this->dbInstance === null) {
            $this->dbInstance = new \SPPMod\SPPDB\SPPDB();
        }
        return $this->dbInstance;
    }

    public function ensureSchema(): void
    {
        $db = $this->getDB();
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

    public function save(string $key, string $locale, string $translation, string $status = 'active'): void
    {
        $this->ensureSchema();
        $db = $this->getDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');

        $res = $db->exec_squery("SELECT id FROM %tab% WHERE key_code = ? AND locale = ?", $table, [$key, $locale]);
        if (!empty($res)) {
            $db->exec_squery(
                "UPDATE %tab% SET translation = ?, status = ? WHERE key_code = ? AND locale = ?",
                $table,
                [$translation, $status, $key, $locale]
            );
        } else {
            $db->exec_squery(
                "INSERT INTO %tab% (key_code, locale, translation, status) VALUES (?, ?, ?, ?)",
                $table,
                [$key, $locale, $translation, $status]
            );
        }
    }

    public function getMany(array $filters = []): array
    {
        $this->ensureSchema();
        $db = $this->getDB();
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

    public function getOne(string $key, string $locale = 'en'): string
    {
        $this->ensureSchema();
        $db = $this->getDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('translations');

        $res = $db->exec_squery("SELECT translation FROM %tab% WHERE key_code = ? AND locale = ?", $table, [$key, $locale]);
        if (!empty($res)) {
            return (string) $res[0]['translation'];
        }

        // Fallback to key itself if not found
        return $key;
    }
}
