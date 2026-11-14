<?php
namespace SPPMod\SPPLive;

class SqliteLiveEngine implements LiveEngineInterface {
    private \PDO $db;
    private array $currentRequestEvents = [];
    private static bool $schemaChecked = false;

    public function __construct() {
        $dbPath = SPP_BASE_DIR . '/var/data/spplive.sqlite';
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0777, true);
        }
        $this->db = new \PDO("sqlite:" . $dbPath);
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Performance: Enable WAL mode and normal synchronous
        $this->db->exec('PRAGMA journal_mode=WAL;');
        $this->db->exec('PRAGMA synchronous=NORMAL;');

        if (!self::$schemaChecked) {
            $this->ensureSchema();
            self::$schemaChecked = true;
        }
    }

    private function ensureSchema(): void {
        // Fast user_version check to avoid redundant DDL locks on every request
        $version = (int)$this->db->query("PRAGMA user_version")->fetchColumn();
        if ($version >= 2) {
            return;
        }

        $this->db->exec("CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target TEXT NOT NULL,
            name TEXT NOT NULL,
            params TEXT,
            topic TEXT DEFAULT 'global',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_events_topic_id ON events (topic, id)");

        $this->db->exec("CREATE TABLE IF NOT EXISTS presence (
            topic TEXT NOT NULL,
            user_id TEXT NOT NULL,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (topic, user_id)
        )");

        $this->db->exec("PRAGMA user_version = 2");
    }

    public function trackPresence(string $topic, string $userId): void {
        $stmt = $this->db->prepare("INSERT INTO presence (topic, user_id, last_seen) VALUES (?, ?, CURRENT_TIMESTAMP) 
                                    ON CONFLICT(topic, user_id) DO UPDATE SET last_seen=CURRENT_TIMESTAMP");
        $stmt->execute([$topic, $userId]);
    }

    public function emit(string $componentId, string $event, array $params = [], string $topic = 'global'): void {
        $stmt = $this->db->prepare("INSERT INTO events (target, name, params, topic) VALUES (?, ?, ?, ?)");
        $stmt->execute([$componentId, $event, json_encode($params), $topic]);
        $id = (int)$this->db->lastInsertId();

        $this->currentRequestEvents[] = [
            'id' => $id,
            'target' => $componentId,
            'name' => $event,
            'params' => $params,
            'topic' => $topic
        ];
    }

    public function getLatestEventId(): int {
        $stmt = $this->db->query("SELECT MAX(id) FROM events");
        $val = $stmt->fetchColumn();
        return $val ? (int)$val : 0;
    }

    public function fetchSince(int $lastId, array $topics = ['global']): array {
        if (empty($topics)) return [];

        $placeholders = implode(',', array_fill(0, count($topics), '?'));
        $params = array_merge([$lastId], $topics);
        $stmt = $this->db->prepare("SELECT id, target, name, params, topic, created_at FROM events WHERE id > ? AND topic IN ($placeholders) ORDER BY id ASC LIMIT 100");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Periodic cleanup for stale events older than 10 minutes
        if (rand(1, 100) === 1) {
            $this->db->exec("DELETE FROM events WHERE created_at < datetime('now', '-10 minutes')");
        }

        return array_map(function($row) {
            return [
                'id' => (int)$row['id'],
                'target' => $row['target'],
                'name' => $row['name'],
                'params' => json_decode($row['params'], true),
                'topic' => $row['topic'],
                'created_at' => $row['created_at']
            ];
        }, $rows);
    }

    public function flush(array $topics = ['global']): array {
        if (empty($topics)) return [];

        $events = [];
        $remaining = [];
        foreach ($this->currentRequestEvents as $evt) {
            if (in_array($evt['topic'] ?? 'global', $topics)) {
                $events[] = $evt;
            } else {
                $remaining[] = $evt;
            }
        }
        $this->currentRequestEvents = $remaining;

        return $events;
    }
}
