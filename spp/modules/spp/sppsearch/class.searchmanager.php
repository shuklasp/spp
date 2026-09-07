<?php

namespace SPPMod\SPPSearch;

use Symfony\Component\Yaml\Yaml;

interface SearchDriverInterface
{
    public function indexDocument(string $collection, string $id, array $document): bool;
    public function deleteDocument(string $collection, string $id): bool;
    public function search(string $collection, string $query, array $options = []): array;
}

/**
 * High-performance embedded SQLite Full-Text Search (FTS5) Driver.
 * Zero external servers required.
 */
class SQLiteFts5Driver implements SearchDriverInterface
{
    protected ?\SQLite3 $db = null;
    protected string $dbPath;

    public function __construct(array $config = [])
    {
        $baseDir = defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4);
        $this->dbPath = $config['path'] ?? ($baseDir . '/var/data/search_fts5.sqlite');
        $this->initDb();
    }

    protected function initDb(): void
    {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->db = new \SQLite3($this->dbPath);
        $this->db->busyTimeout(5000);
        $this->db->exec("PRAGMA journal_mode = WAL;");
        $this->db->exec("
            CREATE VIRTUAL TABLE IF NOT EXISTS fts_index USING fts5(
                id UNINDEXED,
                collection UNINDEXED,
                title,
                body,
                metadata UNINDEXED
            );
        ");
    }

    public function indexDocument(string $collection, string $id, array $document): bool
    {
        $title = $document['title'] ?? '';
        $body = $document['body'] ?? ($document['content'] ?? '');
        $metadata = json_encode($document);

        // Remove previous entry if exists
        $this->deleteDocument($collection, $id);

        $stmt = $this->db->prepare("INSERT INTO fts_index (id, collection, title, body, metadata) VALUES (:id, :collection, :title, :body, :metadata)");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':collection', $collection, SQLITE3_TEXT);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':body', strip_tags((string)$body), SQLITE3_TEXT);
        $stmt->bindValue(':metadata', $metadata, SQLITE3_TEXT);

        return $stmt->execute() !== false;
    }

    public function deleteDocument(string $collection, string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM fts_index WHERE id = :id AND collection = :collection");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':collection', $collection, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }

    public function search(string $collection, string $query, array $options = []): array
    {
        $cleanQuery = preg_replace('/[^\p{L}\p{N}_\- ]/u', '', $query);
        if (empty($cleanQuery)) {
            return [];
        }

        $limit = (int)($options['limit'] ?? 20);
        $words = array_filter(explode(' ', $cleanQuery));
        $matchExpr = implode('* ', $words) . '*';

        $stmt = $this->db->prepare("
            SELECT id, collection, title, metadata, bm25(fts_index) as rank
            FROM fts_index 
            WHERE collection = :collection AND fts_index MATCH :query
            ORDER BY rank
            LIMIT :limit
        ");
        $stmt->bindValue(':collection', $collection, SQLITE3_TEXT);
        $stmt->bindValue(':query', $matchExpr, SQLITE3_TEXT);
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);

        $res = $stmt->execute();
        $hits = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $meta = json_decode($row['metadata'] ?? '{}', true) ?: [];
            $hits[] = array_merge($meta, [
                'id' => $row['id'],
                'collection' => $row['collection'],
                'title' => $row['title'],
                '_score' => $row['rank']
            ]);
        }

        return $hits;
    }
}

/**
 * MeiliSearch REST Driver.
 */
class MeiliSearchDriver implements SearchDriverInterface
{
    protected string $host;
    protected string $apiKey;

    public function __construct(array $config = [])
    {
        $this->host = rtrim($config['host'] ?? 'http://127.0.0.1:7700', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function indexDocument(string $collection, string $id, array $document): bool
    {
        $document['id'] = $id;
        $url = "{$this->host}/indexes/{$collection}/documents";
        return $this->request('POST', $url, [$document]);
    }

    public function deleteDocument(string $collection, string $id): bool
    {
        $url = "{$this->host}/indexes/{$collection}/documents/{$id}";
        return $this->request('DELETE', $url);
    }

    public function search(string $collection, string $query, array $options = []): array
    {
        $url = "{$this->host}/indexes/{$collection}/search";
        $payload = array_merge(['q' => $query], $options);
        $res = $this->request('POST', $url, $payload, true);
        return $res['hits'] ?? [];
    }

    protected function request(string $method, string $url, array $payload = [], bool $returnJson = false)
    {
        $headers = ['Content-Type: application/json'];
        if (!empty($this->apiKey)) {
            $headers[] = "Authorization: Bearer {$this->apiKey}";
        }

        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => 5,
            ]
        ];
        if (!empty($payload)) {
            $opts['http']['content'] = json_encode($payload);
        }

        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) return $returnJson ? [] : false;
        return $returnJson ? (json_decode($res, true) ?: []) : true;
    }
}

class SearchManager
{
    protected static array $drivers = [];
    protected static ?array $config = null;

    public static function driver(?string $name = null): SearchDriverInterface
    {
        $name = $name ?: 'fts5';
        if (isset(self::$drivers[$name])) {
            return self::$drivers[$name];
        }

        if ($name === 'meilisearch') {
            self::$drivers[$name] = new MeiliSearchDriver();
        } else {
            self::$drivers[$name] = new SQLiteFts5Driver();
        }

        return self::$drivers[$name];
    }
}
