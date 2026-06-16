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
        $this->db->exec("CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target TEXT NOT NULL,
            name TEXT NOT NULL,
            params TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function emit(string $componentId, string $event, array $params = []): void {
        $stmt = $this->db->prepare("INSERT INTO events (target, name, params) VALUES (?, ?, ?)");
        $stmt->execute([$componentId, $event, json_encode($params)]);
    }

    public function flush(): array {
        // Since SQLite is a polling fallback, flush() acts as the fetch mechanism.
        // In a real app, you'd pass a last_id to fetch new events.
        $stmt = $this->db->query("SELECT * FROM events ORDER BY id DESC LIMIT 50");
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Clear old events to prevent DB bloat
        $this->db->exec("DELETE FROM events WHERE id NOT IN (SELECT id FROM events ORDER BY id DESC LIMIT 100)");
        
        return array_map(function($row) {
            return [
                'target' => $row['target'],
                'name' => $row['name'],
                'params' => json_decode($row['params'], true)
            ];
        }, $events);
    }
}
