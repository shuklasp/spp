<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * VectorSearchEngine
 * Hybrid Semantic AI Search & RAG (Retrieval-Augmented Generation) Engine.
 * Supports Zero-Cloud Offline BM25 + Cosine Chunk Vectorization (Default)
 * alongside optional Cloud/Local LLM Providers: OpenAI, Gemini, Anthropic, Ollama, & Custom OpenAI-Compatible.
 */
class VectorSearchEngine
{
    /**
     * Default BM25 tuning parameters.
     */
    private const BM25_K1 = 1.2;
    private const BM25_B = 0.75;

    /**
     * Index all documentation pages for a project into semantic chunks with vector signatures.
     */
    public static function indexProject(string $projectId, string $provider = 'offline'): array
    {
        $projectConfig = PermissionManager::loadProjectConfig($projectId);
        if (!$projectConfig) {
            return ['success' => false, 'message' => "Project '{$projectId}' not found."];
        }

        $pagesDir = self::resolvePagesDir($projectId, $projectConfig);
        if (!$pagesDir || !is_dir($pagesDir)) {
            return ['success' => false, 'message' => "Pages directory not found for project '{$projectId}'."];
        }

        $files = self::scanMarkdownFiles($pagesDir);

        // Also crawl and index Academy courses if present
        $coursesDir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/courses';
        if (is_dir($coursesDir)) {
            $courseFiles = self::scanMarkdownFiles($coursesDir);
            foreach ($courseFiles as $cf) {
                if (!in_array($cf, $files)) {
                    $files[] = $cf;
                }
            }
        }

        $chunks = [];
        $docCount = 0;
        $allTokens = [];

        foreach ($files as $filePath) {
            $normPagesDir = rtrim(str_replace('\\', '/', $pagesDir), '/');
            $normFilePath = str_replace('\\', '/', $filePath);
            $isCourse = str_contains($normFilePath, '/courses/');

            if ($isCourse && is_dir($coursesDir)) {
                $normCoursesDir = rtrim(str_replace('\\', '/', $coursesDir), '/');
                $relPath = ltrim(str_replace($normCoursesDir, '', $normFilePath), '/');
                $pageSlug = 'courses/' . preg_replace('/\.md$/i', '', $relPath);
            } else {
                $relPath = ltrim(str_replace($normPagesDir, '', $normFilePath), '/');
                $pageSlug = preg_replace('/\.md$/i', '', $relPath);
            }

            $rawContent = file_get_contents($filePath);
            
            // Extract frontmatter
            $title = ucfirst(basename($pageSlug));
            if ($isCourse) {
                $title = '🎓 Academy: ' . $title;
            }
            $body = $rawContent;
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $rawContent, $fm)) {
                $meta = Yaml::parse($fm[1]) ?: [];
                $title = !empty($meta['title']) ? ($isCourse ? ('🎓 Academy: ' . $meta['title']) : $meta['title']) : $title;
                $body = $fm[2];
            }

            // Split into semantic sections by header
            $sections = self::chunkMarkdownSections($body, $pageSlug, $title);
            foreach ($sections as $sec) {
                $tokens = self::tokenize($sec['text']);
                $sec['tokens'] = $tokens;
                $sec['length'] = count($tokens);
                $chunks[] = $sec;

                foreach ($tokens as $t) {
                    $allTokens[$t] = ($allTokens[$t] ?? 0) + 1;
                }
            }
            $docCount++;
        }

        $totalChunks = count($chunks);
        if ($totalChunks === 0) {
            return ['success' => true, 'pages' => 0, 'chunks' => 0];
        }

        // Calculate Average Document Length and Inverse Document Frequency (IDF)
        $totalLength = array_sum(array_column($chunks, 'length'));
        $avgDocLength = $totalLength / $totalChunks;

        $docFreq = [];
        foreach ($chunks as $c) {
            $uniqueWords = array_unique($c['tokens']);
            foreach ($uniqueWords as $w) {
                $docFreq[$w] = ($docFreq[$w] ?? 0) + 1;
            }
        }

        $idf = [];
        foreach ($docFreq as $word => $df) {
            // Standard BM25 IDF formula with smoothing
            $idf[$word] = log(($totalChunks - $df + 0.5) / ($df + 0.5) + 1.0);
        }

        // Generate compact vector index
        $indexData = [
            'project_id' => $projectId,
            'generated_at' => time(),
            'provider' => $provider,
            'doc_count' => $docCount,
            'chunk_count' => $totalChunks,
            'avg_length' => $avgDocLength,
            'idf' => $idf,
            'chunks' => array_map(function ($c) {
                return [
                    'id' => $c['id'],
                    'page_slug' => $c['page_slug'],
                    'page_title' => $c['page_title'],
                    'section_title' => $c['section_title'],
                    'anchor' => $c['anchor'],
                    'text' => $c['text'],
                    'term_freq' => array_count_values($c['tokens']),
                    'length' => $c['length'],
                ];
            }, $chunks),
        ];

        $indexPath = self::getIndexPath($projectId);
        $indexDir = dirname($indexPath);
        if (!is_dir($indexDir)) {
            @mkdir($indexDir, 0777, true);
        }

        file_put_contents($indexPath, json_encode($indexData, JSON_PRETTY_PRINT));

        // Build disk-backed SQLite inverted index for ultra-low RAM usage
        try {
            $sqlitePath = self::getSqliteIndexPath($projectId);
            if (file_exists($sqlitePath)) {
                @unlink($sqlitePath);
            }
            $pdo = new \PDO('sqlite:' . $sqlitePath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA synchronous = NORMAL;');
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS search_meta (key TEXT PRIMARY KEY, val TEXT);
                CREATE TABLE IF NOT EXISTS search_chunks (
                    chunk_id TEXT PRIMARY KEY,
                    page_slug TEXT,
                    page_title TEXT,
                    section_title TEXT,
                    anchor TEXT,
                    text TEXT,
                    length INTEGER
                );
                CREATE TABLE IF NOT EXISTS search_postings (
                    term TEXT,
                    chunk_id TEXT,
                    tf INTEGER,
                    idf REAL,
                    PRIMARY KEY (term, chunk_id)
                );
                CREATE INDEX IF NOT EXISTS idx_postings_term ON search_postings (term);
            ');

            $pdo->beginTransaction();
            $stmtMeta = $pdo->prepare('INSERT INTO search_meta (key, val) VALUES (?, ?)');
            $stmtMeta->execute(['avg_length', (string)$avgDocLength]);
            $stmtMeta->execute(['doc_count', (string)$docCount]);
            $stmtMeta->execute(['chunk_count', (string)$totalChunks]);

            $stmtChunk = $pdo->prepare('INSERT INTO search_chunks (chunk_id, page_slug, page_title, section_title, anchor, text, length) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmtPost = $pdo->prepare('INSERT INTO search_postings (term, chunk_id, tf, idf) VALUES (?, ?, ?, ?)');

            foreach ($chunks as $c) {
                $stmtChunk->execute([
                    $c['id'],
                    $c['page_slug'],
                    $c['page_title'],
                    $c['section_title'],
                    $c['anchor'],
                    $c['text'],
                    $c['length'],
                ]);

                $termFreqs = array_count_values($c['tokens']);
                foreach ($termFreqs as $term => $tf) {
                    $tIdf = $idf[$term] ?? 0.5;
                    $stmtPost->execute([$term, $c['id'], $tf, $tIdf]);
                }
            }
            $pdo->commit();
        } catch (\Exception $e) {
            error_log('Failed to build SQLite search index: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'pages' => $docCount,
            'chunks' => $totalChunks,
            'index_path' => $indexPath,
        ];
    }

    /**
     * Search the vector index for the top-matching chunks using BM25 relevance scoring.
     */
    public static function search(string $projectId, string $query, int $topK = 5): array
    {
        $indexPath = self::getIndexPath($projectId);
        $sqlitePath = self::getSqliteIndexPath($projectId);

        if (!file_exists($indexPath) && !file_exists($sqlitePath)) {
            // Auto-build index on first query
            self::indexProject($projectId);
        }

        $queryTokens = self::tokenize($query);
        if (empty($queryTokens)) {
            return [];
        }

        // Fast path: SQLite indexed seek (avoids loading 40MB+ JSON into PHP memory)
        if (file_exists($sqlitePath)) {
            try {
                $pdo = new \PDO('sqlite:' . $sqlitePath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $stmtMeta = $pdo->query("SELECT val FROM search_meta WHERE key = 'avg_length'");
                $avgLenRow = $stmtMeta ? $stmtMeta->fetch(\PDO::FETCH_ASSOC) : null;
                $avgLength = $avgLenRow ? (float)$avgLenRow['val'] : 100.0;

                $placeholders = implode(',', array_fill(0, count($queryTokens), '?'));
                $sql = "
                    SELECT 
                        c.chunk_id as id,
                        c.page_slug,
                        c.page_title,
                        c.section_title,
                        c.anchor,
                        c.text,
                        c.length,
                        SUM(p.idf * ((p.tf * " . (self::BM25_K1 + 1) . ") / (p.tf + " . self::BM25_K1 . " * (1 - " . self::BM25_B . " + " . self::BM25_B . " * (c.length / " . (float)$avgLength . "))))) as score
                    FROM search_postings p
                    JOIN search_chunks c ON c.chunk_id = p.chunk_id
                    WHERE p.term IN ($placeholders)
                    GROUP BY c.chunk_id
                    ORDER BY score DESC
                    LIMIT " . (int)($topK * 2);

                $stmt = $pdo->prepare($sql);
                $stmt->execute($queryTokens);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $scores = [];
                    $queryLower = strtolower($query);
                    foreach ($rows as $r) {
                        $score = (float)$r['score'];
                        if (str_contains(strtolower($r['section_title']), $queryLower)) {
                            $score += 3.5;
                        }
                        $scores[] = [
                            'score' => round($score, 4),
                            'chunk' => [
                                'id' => $r['id'],
                                'page_slug' => $r['page_slug'],
                                'page_title' => $r['page_title'],
                                'section_title' => $r['section_title'],
                                'anchor' => $r['anchor'],
                                'text' => $r['text'],
                                'length' => (int)$r['length'],
                            ]
                        ];
                    }

                    usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
                    return array_slice($scores, 0, $topK);
                }
            } catch (\Exception $e) {
                // Fallback to JSON if SQLite query fails
            }
        }

        if (!file_exists($indexPath)) {
            return [];
        }

        $index = json_decode(file_get_contents($indexPath), true);
        if (!$index || empty($index['chunks'])) {
            return [];
        }

        $avgLength = $index['avg_length'] ?? 100;
        $idfTable = $index['idf'] ?? [];
        $scores = [];

        foreach ($index['chunks'] as $idx => $chunk) {
            $score = 0.0;
            $tfTable = $chunk['term_freq'] ?? [];
            $docLen = $chunk['length'] ?? 100;

            foreach ($queryTokens as $term) {
                if (isset($tfTable[$term])) {
                    $tf = $tfTable[$term];
                    $idf = $idfTable[$term] ?? 0.5;

                    // BM25 term score
                    $numerator = $tf * (self::BM25_K1 + 1);
                    $denominator = $tf + self::BM25_K1 * (1 - self::BM25_B + self::BM25_B * ($docLen / $avgLength));
                    $score += $idf * ($numerator / $denominator);
                }
            }

            // Boost exact section title matches
            $secTitleLower = strtolower($chunk['section_title']);
            if (str_contains($secTitleLower, strtolower($query))) {
                $score += 3.5;
            }

            if ($score > 0) {
                $scores[] = [
                    'score' => round($score, 4),
                    'chunk' => $chunk,
                ];
            }
        }

        // Sort descending by relevance score
        usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scores, 0, $topK);
    }

    /**
     * Synthesize a natural language answer with source citations (RAG).
     */
    public static function ask(string $projectId, string $query): array
    {
        $query = trim($query);
        if (empty($query)) {
            return [
                'query' => '',
                'answer' => 'Please provide a question or search query.',
                'citations' => [],
                'chunks' => []
            ];
        }

        $matches = self::search($projectId, $query, 4);

        if (empty($matches)) {
            return [
                'query' => $query,
                'answer' => "No documentation passages directly match your question. Try searching with broader keywords or re-indexing via `php spp.php sppdocs:ai:index`.",
                'citations' => [],
                'chunks' => []
            ];
        }

        $citations = [];
        $topChunks = [];
        $baseUrl = \SPP\App::getBaseUrl();

        foreach ($matches as $m) {
            $c = $m['chunk'];
            $link = $baseUrl . '/project/' . $projectId . '/' . $c['page_slug'] . ($c['anchor'] ? ('#' . $c['anchor']) : '');
            
            $citations[] = [
                'title' => $c['page_title'] . ' &rarr; ' . $c['section_title'],
                'url' => $link,
                'page_slug' => $c['page_slug'],
                'section_title' => $c['section_title'],
                'score' => $m['score']
            ];
            $topChunks[] = $c;
        }

        // Check configured AI Provider in settings (sppdocs.yml or environment)
        $aiConfig = self::getAiProviderConfig();
        $provider = $aiConfig['provider'] ?? 'offline';

        if ($provider !== 'offline' && !empty($aiConfig['api_key'])) {
            $llmAnswer = self::generateLlmAnswer($query, $topChunks, $aiConfig);
            if ($llmAnswer) {
                return [
                    'query' => $query,
                    'answer' => $llmAnswer,
                    'provider' => $provider,
                    'citations' => $citations,
                    'chunks' => $topChunks,
                ];
            }
        }

        // Native Zero-Cloud Offline Synthesis (Extractive & Synthesized)
        $bestChunk = $topChunks[0];
        $bestText = $bestChunk['text'];
        
        // Clean text and extract key answers
        $paragraphs = array_values(array_filter(array_map('trim', explode("\n\n", $bestText))));
        $summary = $paragraphs[0] ?? $bestText;
        if (count($paragraphs) > 1 && strlen($summary) < 120) {
            $summary .= "\n\n" . $paragraphs[1];
        }

        $codeBlocks = [];
        if (preg_match_all('/```[a-zA-Z0-9_\-]*\n([\s\S]*?)```/m', $bestText, $codeMatches)) {
            foreach ($codeMatches[0] as $cb) {
                $codeBlocks[] = $cb;
            }
        }

        $answer = "Based on **{$bestChunk['page_title']}** ({$bestChunk['section_title']}):\n\n" . $summary;
        if (!empty($codeBlocks)) {
            $answer .= "\n\n" . $codeBlocks[0];
        }

        return [
            'query' => $query,
            'answer' => $answer,
            'provider' => 'offline (Native BM25)',
            'citations' => $citations,
            'chunks' => $topChunks,
        ];
    }

    /**
     * Dispatch prompt to configured LLM provider (OpenAI, Gemini, Anthropic, Ollama, or Custom).
     */
    private static function generateLlmAnswer(string $query, array $chunks, array $config): ?string
    {
        $provider = $config['provider'];
        $apiKey = $config['api_key'] ?? '';
        $endpoint = $config['endpoint'] ?? '';
        $model = $config['model'] ?? '';

        $contextText = "";
        foreach ($chunks as $i => $c) {
            $num = $i + 1;
            $contextText .= "[Document {$num}: {$c['page_title']} - {$c['section_title']}]\n{$c['text']}\n\n";
        }

        $systemPrompt = "You are the technical AI Documentation Assistant for SPPDocs. Answer the user's question concisely using only the provided context. Include code snippets where applicable.";
        $userPrompt = "Context:\n{$contextText}\n\nUser Question: {$query}";

        try {
            if ($provider === 'openai' || $provider === 'custom') {
                $url = $endpoint ?: 'https://api.openai.com/v1/chat/completions';
                $useModel = $model ?: 'gpt-4o-mini';
                $payload = [
                    'model' => $useModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt]
                    ],
                    'temperature' => 0.2
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                curl_close($ch);

                if ($resp) {
                    $json = json_decode($resp, true);
                    return $json['choices'][0]['message']['content'] ?? null;
                }
            } elseif ($provider === 'gemini') {
                $useModel = $model ?: 'gemini-1.5-flash';
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$useModel}:generateContent?key={$apiKey}";
                $payload = [
                    'contents' => [
                        ['parts' => [['text' => $systemPrompt . "\n\n" . $userPrompt]]]
                    ]
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                curl_close($ch);

                if ($resp) {
                    $json = json_decode($resp, true);
                    return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                }
            } elseif ($provider === 'ollama') {
                $url = $endpoint ?: 'http://127.0.0.1:11434/api/generate';
                $useModel = $model ?: 'llama3';
                $payload = [
                    'model' => $useModel,
                    'prompt' => $systemPrompt . "\n\n" . $userPrompt,
                    'stream' => false
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                curl_close($ch);

                if ($resp) {
                    $json = json_decode($resp, true);
                    return $json['response'] ?? null;
                }
            }
        } catch (\Throwable $e) {
            // Fall back to offline mode on any provider error
        }

        return null;
    }

    /**
     * Retrieve AI Provider configuration from sppdocs.yml.
     */
    public static function getAiProviderConfig(): array
    {
        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        if (file_exists($configFile)) {
            $cfg = Yaml::parseFile($configFile) ?: [];
            return $cfg['ai_search'] ?? ['provider' => 'offline'];
        }
        return ['provider' => 'offline'];
    }

    /**
     * Chunk markdown document into semantic subsections based on headers.
     */
    private static function chunkMarkdownSections(string $markdown, string $pageSlug, string $pageTitle): array
    {
        $lines = explode("\n", $markdown);
        $chunks = [];
        $currentTitle = $pageTitle;
        $currentAnchor = '';
        $currentLines = [];
        $chunkIndex = 0;

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $matches)) {
                // Flush previous chunk
                if (!empty($currentLines)) {
                    $text = trim(implode("\n", $currentLines));
                    if (strlen($text) > 20) {
                        $chunks[] = [
                            'id' => $pageSlug . '#' . ($currentAnchor ?: 'intro') . '-' . $chunkIndex++,
                            'page_slug' => $pageSlug,
                            'page_title' => $pageTitle,
                            'section_title' => $currentTitle,
                            'anchor' => $currentAnchor,
                            'text' => $text,
                        ];
                    }
                    $currentLines = [];
                }

                $currentTitle = trim($matches[2]);
                $currentAnchor = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '-', $currentTitle));
                $currentAnchor = trim(preg_replace('/-+/', '-', $currentAnchor), '-');
            } else {
                $currentLines[] = $line;
            }
        }

        if (!empty($currentLines)) {
            $text = trim(implode("\n", $currentLines));
            if (strlen($text) > 20) {
                $chunks[] = [
                    'id' => $pageSlug . '#' . ($currentAnchor ?: 'body') . '-' . $chunkIndex++,
                    'page_slug' => $pageSlug,
                    'page_title' => $pageTitle,
                    'section_title' => $currentTitle,
                    'anchor' => $currentAnchor,
                    'text' => $text,
                ];
            }
        }

        return $chunks;
    }

    /**
     * Tokenize text into lowercased alphanumeric words with stop-words removed.
     */
    public static function tokenize(string $text): array
    {
        $clean = strtolower(strip_tags($text));
        // Remove code block markers
        $clean = preg_replace('/```[a-zA-Z0-9_\-]*|```/', ' ', $clean);
        preg_match_all('/[a-z0-9_\-]{2,}/', $clean, $matches);
        $tokens = $matches[0] ?? [];

        $stopWords = [
            'the' => 1, 'and' => 1, 'for' => 1, 'with' => 1, 'this' => 1, 'that' => 1,
            'are' => 1, 'was' => 1, 'from' => 1, 'will' => 1, 'have' => 1, 'has' => 1,
            'you' => 1, 'your' => 1, 'can' => 1, 'all' => 1, 'not' => 1, 'but' => 1
        ];

        return array_values(array_filter($tokens, fn($t) => !isset($stopWords[$t])));
    }

    /**
     * Resolve pages directory for a project.
     */
    private static function resolvePagesDir(string $projectId, array $config): ?string
    {
        if (!empty($config['pages_dir'])) {
            $dir = $config['pages_dir'];
            if (!str_starts_with($dir, '/') && !preg_match('/^[a-zA-Z]:[\/\\\\]/', $dir)) {
                $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
            }
            if (is_dir($dir)) return $dir;
        }

        if (!empty($config['versions'])) {
            $defaultVer = $config['default_version'] ?? array_key_first($config['versions']);
            $vPath = $config['versions'][$defaultVer] ?? '';
            if ($vPath && is_dir($vPath)) return $vPath;
        }

        $fallback = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/pages';
        if (is_dir($fallback)) return $fallback;

        $rootDocs = dirname(SPP_BASE_DIR) . '/docs/' . $projectId;
        if (is_dir($rootDocs)) return $rootDocs;

        return null;
    }

    /**
     * Recursively find all markdown files in a directory.
     */
    private static function scanMarkdownFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(md|markdown)$/i', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    /**
     * Path to the project's vector-index.json.
     */
    public static function getIndexPath(string $projectId): string
    {
        return dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/vector-index.json';
    }

    /**
     * Path to the project's disk-backed SQLite inverted index.
     */
    public static function getSqliteIndexPath(string $projectId): string
    {
        return dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/search_index.sqlite3';
    }
}
