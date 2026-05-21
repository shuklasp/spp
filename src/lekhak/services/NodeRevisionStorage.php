<?php
namespace App\Lekhak\Services;

use SPP\Entity\Revision\RevisionStorageInterface;

/**
 * NodeRevisionStorage
 * 
 * App-layer implementation saving full JSON snapshots to a `node_revision` table.
 */
class NodeRevisionStorage implements RevisionStorageInterface
{
    /**
     * Ensures the node_revision table exists.
     */
    private function ensureSchema(): void
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_revision');

        if (!$db->tableExists($table)) {
            $isSqlite = $db->getDriver() === 'sqlite';
            if ($isSqlite) {
                $db->execute_query("CREATE TABLE {$table} (
                    revision_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entity_type VARCHAR(50) NOT NULL,
                    entity_id VARCHAR(50) NOT NULL,
                    data_json TEXT NOT NULL,
                    author VARCHAR(255),
                    message TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            } else {
                $db->execute_query("CREATE TABLE {$table} (
                    revision_id INT AUTO_INCREMENT PRIMARY KEY,
                    entity_type VARCHAR(50) NOT NULL,
                    entity_id VARCHAR(50) NOT NULL,
                    data_json LONGTEXT NOT NULL,
                    author VARCHAR(255),
                    message TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            }
        }
    }

    public function saveRevision(object $entity, string $message = '', string $author = '')
    {
        $this->ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_revision');

        // Extract raw data from entity
        $data = method_exists($entity, 'toArray') ? $entity->toArray() : (array)$entity;
        
        $entityType = 'node';
        $entityId = $entity->id ?? ($data['id'] ?? null);

        if (!$entityId) {
            throw new \Exception("Cannot save revision for an entity without an ID.");
        }

        if (empty($author) && class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            $currentUser = \SPPMod\SPPAuth\SPPAuth::getCurrentUser();
            $author = $currentUser['username'] ?? ($currentUser['id'] ?? 'system');
        }

        $sql = "INSERT INTO {$table} (entity_type, entity_id, data_json, author, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $db->execute_query($sql, [$entityType, $entityId, json_encode($data), $author, $message]);

        // Return the last inserted ID
        $driver = $db->getDriver();
        if ($driver === 'sqlite') {
             $res = $db->execute_query("SELECT last_insert_rowid() as id");
             return $res[0]['id'] ?? null;
        } else {
             $res = $db->execute_query("SELECT LAST_INSERT_ID() as id");
             return $res[0]['id'] ?? null;
        }
    }

    public function loadRevision(string $entityType, $entityId, $revisionId): ?array
    {
        $this->ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_revision');

        $sql = "SELECT data_json FROM {$table} WHERE entity_type = ? AND entity_id = ? AND revision_id = ?";
        $res = $db->execute_query($sql, [$entityType, $entityId, $revisionId]);

        if (empty($res)) return null;

        return json_decode($res[0]['data_json'], true);
    }

    public function listRevisions(string $entityType, $entityId): array
    {
        $this->ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_revision');

        $sql = "SELECT revision_id, author, message, created_at FROM {$table} WHERE entity_type = ? AND entity_id = ? ORDER BY revision_id DESC";
        return $db->execute_query($sql, [$entityType, $entityId]);
    }

    public function deleteRevision(string $entityType, $entityId, $revisionId): bool
    {
        $this->ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_revision');

        $sql = "DELETE FROM {$table} WHERE entity_type = ? AND entity_id = ? AND revision_id = ?";
        $db->execute_query($sql, [$entityType, $entityId, $revisionId]);
        return true;
    }
}
