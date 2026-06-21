<?php
namespace SPPMod\SPPLive;

class SqliteLiveEngine implements LiveEngineInterface {
    private $db;

    public function __construct() {
        $dbPath = SPP_BASE_DIR . '/var/data/spplive.sqlite';
        if (!is_dir(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0777, true);
        }
        $this->db = new \PDO("sqlite:" . $dbPath);
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Performance: Enable WAL mode
        $this->db->exec('PRAGMA journal_mode=WAL;');

        $this->db->exec("CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target TEXT NOT NULL,
            name TEXT NOT NULL,
            params TEXT,
            topic TEXT DEFAULT 'global',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Automatic migration: add topic column if it doesn't exist
        $stmt = $this->db->query("PRAGMA table_info(events)");
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $hasTopic = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'topic') {
                $hasTopic = true;
                break;
            }
        }
        if (!$hasTopic) {
            $this->db->exec("ALTER TABLE events ADD COLUMN topic TEXT DEFAULT 'global'");
        }

        $this->db->exec("CREATE TABLE IF NOT EXISTS presence (
            topic TEXT NOT NULL,
            user_id TEXT NOT NULL,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (topic, user_id)
        )");
    }

    public function trackPresence(string $topic, string $userId): void {
        $stmt = $this->db->prepare("INSERT INTO presence (topic, user_id, last_seen) VALUES (?, ?, CURRENT_TIMESTAMP) 
                                    ON CONFLICT(topic, user_id) DO UPDATE SET last_seen=CURRENT_TIMESTAMP");
        $stmt->execute([$topic, $userId]);
    }

    public function emit(string $componentId, string $event, array $params = [], string $topic = 'global'): void {
        $stmt = $this->db->prepare("INSERT INTO events (target, name, params, topic) VALUES (?, ?, ?, ?)");
        $stmt->execute([$componentId, $event, json_encode($params), $topic]);
    }

    public function flush(array $topics = ['global']): array {
        if (empty($topics)) return [];
        
        $placeholders = implode(',', array_fill(0, count($topics), '?'));
        $stmt = $this->db->prepare("SELECT * FROM events WHERE topic IN ($placeholders) ORDER BY id ASC");
        $stmt->execute($topics);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (!empty($events)) {
            $ids = array_column($events, 'id');
            $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $delStmt = $this->db->prepare("DELETE FROM events WHERE id IN ($idPlaceholders)");
            $delStmt->execute($ids);
        }
        
        // Periodic cleanup for stale events
        if (rand(1, 100) === 1) {
            $this->db->exec("DELETE FROM events WHERE created_at < datetime('now', '-1 day')");
        }
        
        return array_map(function($row) {
            return [
                'target' => $row['target'],
                'name' => $row['name'],
                'params' => json_decode($row['params'], true),
                'topic' => $row['topic']
            ];
        }, $events);
    }
}
