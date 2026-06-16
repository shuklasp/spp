<?php

namespace SPPMod\SPPDiff;

/**
 * Class RevisionManager
 * Manages gzip-compressed revision chains, dynamic SQLite schemas, and lifecycle event hooks.
 */
class RevisionManager
{
    private static bool $booted = false;

    /**
     * Initialize event hook listeners to trigger automatic revisions.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::ensureSchema();

        // Listen for save hook to record delta snapshots automatically
        \SPP\SPPEvent::listen('entity:before_save', function (\SPP\EventParams $params) {
            $entity = $params->get('entity');
            self::auditEntity($entity);
        });

        self::$booted = true;
    }

    /**
     * Ensure database audit tables exist.
     */
    private static function ensureSchema(): void
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('entity_history');
        if (!$db->tableExists($table)) {
            $db->exec_squery("CREATE TABLE %tab% (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type VARCHAR(100),
                entity_id BIGINT,
                delta TEXT,
                created_at DATETIME,
                user_id VARCHAR(100)
            )", $table);
        }
    }

    /**
     * Audits a saving entity, comparing new values with past DB values.
     * Computes the delta diff, base64 encodes the gzcompressed stream, and logs the revision.
     *
     * @param mixed $entity The saving SPPEntity instance
     */
    public static function auditEntity($entity): void
    {
        if (empty($entity->id)) {
            return;
        }

        try {
            self::ensureSchema();

            $class = get_class($entity);
            // Bypass auditing triggers inside the reload fetch itself
            $existing = $class::find_one(['id' => $entity->id]);
            if (!$existing) {
                return;
            }

            $oldValues = $existing->getValues();
            $newValues = $entity->getValues();

            // Unpack virtual properties from fields_data for granular revision tracking
            if (!empty($oldValues['fields_data'])) {
                $oldVirtual = json_decode($oldValues['fields_data'], true);
                if (is_array($oldVirtual)) {
                    $oldValues = array_merge($oldValues, $oldVirtual);
                }
            }
            if (!empty($newValues['fields_data'])) {
                $newVirtual = json_decode($newValues['fields_data'], true);
                if (is_array($newVirtual)) {
                    $newValues = array_merge($newValues, $newVirtual);
                }
            }

            $delta = DeltaEngine::diff($oldValues, $newValues);
            if (empty($delta)) {
                return;
            }

            if (isset($delta['fields_data'])) {
                unset($delta['fields_data']);
            }

            $serialized = json_encode($delta, JSON_UNESCAPED_UNICODE);
            $compressed = base64_encode(gzcompress($serialized, 9));

            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('entity_history');

            $userId = 'anonymous';
            if (class_exists('\SPPMod\SPPAuth\SPPAuth')) {
                $user = \SPPMod\SPPAuth\SPPAuth::user();
                if ($user) {
                    $userId = $user->id;
                }
            }

            $db->exec_squery(
                "INSERT INTO %tab% (entity_type, entity_id, delta, created_at, user_id) VALUES (?, ?, ?, ?, ?)",
                $table,
                [
                    $class,
                    $entity->id,
                    $compressed,
                    date('Y-m-d H:i:s'),
                    $userId
                ]
            );
        } catch (\Exception $e) {
            error_log("SPPDiff RevisionManager Error: " . $e->getMessage());
        }
    }

    /**
     * Retrieve revision snapshot from target historical delta chain.
     */
    public static function getRevision($entity, int $revisionId)
    {
        self::ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('entity_history');

        $res = $db->exec_squery(
            "SELECT * FROM %tab% WHERE entity_type = ? AND entity_id = ? AND id >= ? ORDER BY id DESC",
            $table,
            [get_class($entity), $entity->id, $revisionId]
        );

        $values = $entity->getValues();
        foreach ($res as $row) {
            $compressed = $row['delta'];
            $serialized = gzuncompress(base64_decode($compressed));
            $delta = json_decode($serialized, true);
            if (is_array($delta)) {
                $values = DeltaEngine::patch($values, $delta);
            }
        }

        $class = get_class($entity);
        $past = new $class();
        $past->setId($entity->id);
        foreach ($values as $k => $v) {
            if ($past->attributeExists($k)) {
                $past->set($k, $v);
            }
        }
        return $past;
    }

    /**
     * Retrieve list of logs for the entity.
     */
    public static function getHistory($entity): array
    {
        self::ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('entity_history');

        return $db->exec_squery(
            "SELECT id, created_at, user_id FROM %tab% WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC",
            $table,
            [get_class($entity), $entity->id]
        );
    }
}
