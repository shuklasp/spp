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

        // Also crawl and index global framework knowledge (tutorials, commands, engine handbook)
        $globalDocRoots = [
            'tutorial' => dirname(SPP_BASE_DIR) . '/docs/tutorials',
            'command'  => dirname(SPP_BASE_DIR) . '/docs/commands',
            'engine'   => dirname(SPP_BASE_DIR) . '/src/SPPDocs/docs',
            'handbook' => dirname(SPP_BASE_DIR) . '/docs/handbook',
        ];
        foreach ($globalDocRoots as $gDir) {
            if (is_dir($gDir)) {
                $gFiles = self::scanMarkdownFiles($gDir);
                foreach ($gFiles as $gf) {
                    if (!in_array($gf, $files)) {
                        $files[] = $gf;
                    }
                }
            }
        }

        $chunks = [];
        $docCount = 0;
        $allTokens = [];

        foreach ($files as $filePath) {
            $normPagesDir = $pagesDir ? rtrim(str_replace('\\', '/', $pagesDir), '/') : '';
            $normFilePath = str_replace('\\', '/', $filePath);
            $isCourse = str_contains($normFilePath, '/courses/');

            $baseDir = dirname(SPP_BASE_DIR);
            $titlePrefix = '';
            if ($isCourse && is_dir($coursesDir)) {
                $normCoursesDir = rtrim(str_replace('\\', '/', $coursesDir), '/');
                $relPath = ltrim(str_replace($normCoursesDir, '', $normFilePath), '/');
                $pageSlug = 'courses/' . preg_replace('/\.md$/i', '', $relPath);
                $titlePrefix = '🎓 Academy: ';
            } elseif (str_contains($normFilePath, '/docs/tutorials/')) {
                $tutDir = rtrim(str_replace('\\', '/', $baseDir . '/docs/tutorials'), '/');
                $relPath = ltrim(str_replace($tutDir, '', $normFilePath), '/');
                $pageSlug = 'tutorials/' . preg_replace('/\.md$/i', '', $relPath);
                $titlePrefix = '🎓 Tutorial: ';
            } elseif (str_contains($normFilePath, '/docs/commands/')) {
                $cmdDir = rtrim(str_replace('\\', '/', $baseDir . '/docs/commands'), '/');
                $relPath = ltrim(str_replace($cmdDir, '', $normFilePath), '/');
                $pageSlug = 'commands/' . preg_replace('/\.md$/i', '', $relPath);
                $titlePrefix = '⚡ Command: ';
            } elseif (str_contains($normFilePath, '/src/SPPDocs/docs/')) {
                $engDir = rtrim(str_replace('\\', '/', $baseDir . '/src/SPPDocs/docs'), '/');
                $relPath = ltrim(str_replace($engDir, '', $normFilePath), '/');
                $pageSlug = 'engine/' . preg_replace('/\.md$/i', '', $relPath);
                $titlePrefix = '📘 Engine: ';
            } elseif (str_contains($normFilePath, '/docs/handbook/')) {
                $hbDir = rtrim(str_replace('\\', '/', $baseDir . '/docs/handbook'), '/');
                $relPath = ltrim(str_replace($hbDir, '', $normFilePath), '/');
                $pageSlug = 'handbook/' . preg_replace('/\.md$/i', '', $relPath);
                $titlePrefix = '📖 Handbook: ';
            } else {
                $relPath = ltrim(str_replace($normPagesDir, '', $normFilePath), '/');
                $pageSlug = preg_replace('/\.md$/i', '', $relPath);
            }

            $rawContent = file_get_contents($filePath);
            
            // Extract frontmatter
            $baseName = preg_replace('/^[0-9]+[_\-]/', '', basename($pageSlug));
            $baseName = ucwords(str_replace(['-', '_'], ' ', $baseName));
            $title = $titlePrefix . $baseName;

            $body = $rawContent;
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $rawContent, $fm)) {
                $meta = Yaml::parse($fm[1]) ?: [];
                if (!empty($meta['title'])) {
                    $title = $titlePrefix . $meta['title'];
                }
                $body = $fm[2];
            } elseif (preg_match('/^#\s+(.+)$/m', $rawContent, $hm)) {
                $title = $titlePrefix . trim($hm[1]);
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
            $stmtMeta = $pdo->prepare('INSERT OR REPLACE INTO search_meta (key, val) VALUES (?, ?)');
            $stmtMeta->execute(['avg_length', (string)$avgDocLength]);
            $stmtMeta->execute(['doc_count', (string)$docCount]);
            $stmtMeta->execute(['chunk_count', (string)$totalChunks]);

            $stmtChunk = $pdo->prepare('INSERT OR REPLACE INTO search_chunks (chunk_id, page_slug, page_title, section_title, anchor, text, length) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmtPost = $pdo->prepare('INSERT OR REPLACE INTO search_postings (term, chunk_id, tf, idf) VALUES (?, ?, ?, ?)');

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

        $uniqueQueryTokens = array_values(array_unique($queryTokens));
        $totalQueryTerms = count($uniqueQueryTokens);
        $queryLower = strtolower($query);

        // Fast path: SQLite indexed seek (avoids loading large JSON files into PHP memory)
        if (file_exists($sqlitePath)) {
            try {
                $pdo = new \PDO('sqlite:' . $sqlitePath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $stmtMeta = $pdo->query("SELECT val FROM search_meta WHERE key = 'avg_length'");
                $avgLenRow = $stmtMeta ? $stmtMeta->fetch(\PDO::FETCH_ASSOC) : null;
                $avgLength = $avgLenRow ? (float)$avgLenRow['val'] : 100.0;

                $placeholders = implode(',', array_fill(0, count($uniqueQueryTokens), '?'));
                $sql = "
                    SELECT 
                        c.chunk_id as id,
                        c.page_slug,
                        c.page_title,
                        c.section_title,
                        c.anchor,
                        c.text,
                        c.length,
                        COUNT(DISTINCT p.term) as term_coverage,
                        SUM(p.idf * ((p.tf * " . (self::BM25_K1 + 1) . ") / (p.tf + " . self::BM25_K1 . " * (1 - " . self::BM25_B . " + " . self::BM25_B . " * (c.length / " . (float)$avgLength . "))))) as score
                    FROM search_postings p
                    JOIN search_chunks c ON c.chunk_id = p.chunk_id
                    WHERE p.term IN ($placeholders)
                    GROUP BY c.chunk_id
                    ORDER BY score DESC
                    LIMIT " . (int)($topK * 4);

                $stmt = $pdo->prepare($sql);
                $stmt->execute($uniqueQueryTokens);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $scores = [];
                    foreach ($rows as $r) {
                        $score = (float)$r['score'];
                        $coverage = (int)($r['term_coverage'] ?? 1);

                        // Coverage multiplier: rewarding chunks matching more distinct query terms
                        if ($totalQueryTerms > 1) {
                            $coverageRatio = $coverage / $totalQueryTerms;
                            $score = $score * (0.6 + 0.8 * $coverageRatio);
                        }

                        $secTitleLower = strtolower($r['section_title']);
                        $pageTitleLower = strtolower($r['page_title']);

                        // Phrase match bonuses in titles
                        if (str_contains($secTitleLower, $queryLower)) {
                            $score += 6.0;
                        }
                        if (str_contains($pageTitleLower, $queryLower)) {
                            $score += 4.0;
                        }

                        // Keyword match bonuses in titles
                        foreach ($uniqueQueryTokens as $qt) {
                            if (str_contains($secTitleLower, $qt)) {
                                $score += 2.0;
                            }
                            if (str_contains($pageTitleLower, $qt)) {
                                $score += 1.5;
                            }
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
            $matchedTerms = 0;

            foreach ($uniqueQueryTokens as $term) {
                if (isset($tfTable[$term])) {
                    $matchedTerms++;
                    $tf = $tfTable[$term];
                    $idf = $idfTable[$term] ?? 0.5;

                    // BM25 term score
                    $numerator = $tf * (self::BM25_K1 + 1);
                    $denominator = $tf + self::BM25_K1 * (1 - self::BM25_B + self::BM25_B * ($docLen / $avgLength));
                    $score += $idf * ($numerator / $denominator);
                }
            }

            if ($score > 0) {
                if ($totalQueryTerms > 1) {
                    $coverageRatio = $matchedTerms / $totalQueryTerms;
                    $score = $score * (0.6 + 0.8 * $coverageRatio);
                }

                $secTitleLower = strtolower($chunk['section_title']);
                $pageTitleLower = strtolower($chunk['page_title'] ?? '');

                if (str_contains($secTitleLower, $queryLower)) {
                    $score += 6.0;
                }
                if (str_contains($pageTitleLower, $queryLower)) {
                    $score += 4.0;
                }
                foreach ($uniqueQueryTokens as $qt) {
                    if (str_contains($secTitleLower, $qt)) {
                        $score += 2.0;
                    }
                    if (str_contains($pageTitleLower, $qt)) {
                        $score += 1.5;
                    }
                }

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
        $answer = self::smartSynthesizeAnswer($query, $topChunks);

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
            } elseif ($provider === 'anthropic') {
                $useModel = $model ?: 'claude-3-5-sonnet-20241022';
                $url = 'https://api.anthropic.com/v1/messages';
                $payload = [
                    'model' => $useModel,
                    'max_tokens' => 1024,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt]
                    ],
                    'temperature' => 0.2
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01'
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                curl_close($ch);

                if ($resp) {
                    $json = json_decode($resp, true);
                    return $json['content'][0]['text'] ?? null;
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
     * Smart Extractive & Query-Focused Answer Synthesis (Offline RAG).
     */
    public static function smartSynthesizeAnswer(string $query, array $topChunks): string
    {
        if (empty($topChunks)) {
            return "No matching documentation found.";
        }

        $queryTokens = self::tokenize($query);
        $bestChunk = $topChunks[0];
        
        // Collect candidate sentences and code snippets from top chunks
        $scoredSentences = [];
        $codeSnippets = [];
        $bulletPoints = [];

        foreach ($topChunks as $ci => $chunk) {
            $chunkWeight = 1.0 / (1.0 + $ci * 0.4);
            $text = $chunk['text'];

            // Extract code blocks
            if (preg_match_all('/```[a-zA-Z0-9_\-]*\n([\s\S]*?)```/m', $text, $cbMatches)) {
                foreach ($cbMatches[0] as $cb) {
                    if (!in_array($cb, $codeSnippets)) {
                        $codeSnippets[] = $cb;
                    }
                }
            }

            // Extract bullet points
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^[-*•]\s+(.+)$/', $trimmed, $bMatch) || preg_match('/^\d+\.\s+(.+)$/', $trimmed, $bMatch)) {
                    $bulletContent = trim($bMatch[1]);
                    if (strlen($bulletContent) > 20 && strlen($bulletContent) < 300) {
                        $bTokens = self::tokenize($bulletContent);
                        $overlap = count(array_intersect($queryTokens, $bTokens));
                        if ($overlap > 0 && !in_array($trimmed, $bulletPoints)) {
                            $bulletPoints[] = $trimmed;
                        }
                    }
                }
            }

            // Clean text of code blocks and split into sentences
            $textWithoutCode = preg_replace('/```[\s\S]*?```/m', ' ', $text);
            $textWithoutCode = preg_replace('/\s+/', ' ', $textWithoutCode);
            $rawSentences = preg_split('/(?<=[.?!])\s+(?=[A-Z0-9`#])/', $textWithoutCode);

            foreach ($rawSentences as $si => $sentence) {
                $sentence = trim($sentence);
                if (strlen($sentence) < 25 || strlen($sentence) > 350) {
                    continue;
                }
                if (str_starts_with($sentence, '|') || str_starts_with($sentence, '#')) {
                    continue;
                }

                $sTokens = self::tokenize($sentence);
                $overlap = count(array_intersect($queryTokens, $sTokens));
                if ($overlap === 0) {
                    continue;
                }

                $sScore = ($overlap * 3.0) * $chunkWeight;
                if ($si === 0) {
                    $sScore += 2.0;
                } elseif ($si === 1) {
                    $sScore += 1.0;
                }

                if (preg_match('/\b(is|are|allows|enables|provides|use|run|configured|command|created|stored|by)\b/i', $sentence)) {
                    $sScore += 1.5;
                }

                $scoredSentences[] = [
                    'text' => $sentence,
                    'score' => $sScore,
                    'chunk' => $chunk,
                    'overlap' => $overlap
                ];
            }
        }

        // Sort sentences by score descending
        usort($scoredSentences, fn($a, $b) => $b['score'] <=> $a['score']);

        $answer = "### 📌 " . $bestChunk['page_title'] . "\n\n";

        if (!empty($scoredSentences)) {
            $selected = [];
            $totalLen = 0;
            foreach ($scoredSentences as $s) {
                $isDupe = false;
                foreach ($selected as $prev) {
                    similar_text($s['text'], $prev, $pct);
                    if ($pct > 70) {
                        $isDupe = true;
                        break;
                    }
                }
                if ($isDupe) continue;

                $selected[] = $s['text'];
                $totalLen += strlen($s['text']);
                if (count($selected) >= 3 || $totalLen > 450) {
                    break;
                }
            }

            $answer .= implode(" ", $selected) . "\n\n";
        } else {
            $paragraphs = array_values(array_filter(array_map('trim', explode("\n\n", $bestChunk['text']))));
            $summary = $paragraphs[0] ?? $bestChunk['text'];
            $answer .= $summary . "\n\n";
        }

        if (!empty($bulletPoints)) {
            $answer .= "#### 📋 Key Concepts & Steps\n\n";
            $topBullets = array_slice($bulletPoints, 0, 4);
            $answer .= implode("\n", $topBullets) . "\n\n";
        }

        if (!empty($codeSnippets)) {
            $answer .= "#### ⚡ Command / Code Reference\n\n";
            $answer .= $codeSnippets[0] . "\n\n";
        }

        return trim($answer);
    }

    /**
     * Retrieve AI Provider configuration from sppdocs.yml or environment variables.
     */
    public static function getAiProviderConfig(): array
    {
        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        $cfg = [];
        if (file_exists($configFile)) {
            $parsed = Yaml::parseFile($configFile) ?: [];
            $cfg = $parsed['ai_search'] ?? [];
        }

        $provider = $cfg['provider'] ?? 'offline';

        // Auto-detect LLM providers from environment if not explicitly forced to offline
        if ($provider === 'offline' || empty($cfg['api_key'])) {
            $geminiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? null);
            if ($geminiKey) {
                return [
                    'provider' => 'gemini',
                    'api_key' => $geminiKey,
                    'model' => $cfg['model'] ?? 'gemini-1.5-flash',
                ];
            }
            $openaiKey = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? null);
            if ($openaiKey) {
                return [
                    'provider' => 'openai',
                    'api_key' => $openaiKey,
                    'model' => $cfg['model'] ?? 'gpt-4o-mini',
                ];
            }
            $anthropicKey = getenv('ANTHROPIC_API_KEY') ?: ($_ENV['ANTHROPIC_API_KEY'] ?? null);
            if ($anthropicKey) {
                return [
                    'provider' => 'anthropic',
                    'api_key' => $anthropicKey,
                    'model' => $cfg['model'] ?? 'claude-3-5-sonnet-20241022',
                ];
            }
        }

        return $cfg ?: ['provider' => 'offline'];
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
     * Tokenize text into lowercased alphanumeric words with stop-words removed and singular stemming.
     */
    public static function tokenize(string $text): array
    {
        $clean = strtolower(strip_tags($text));
        // Remove code block markers
        $clean = preg_replace('/```[a-zA-Z0-9_\-]*|```/', ' ', $clean);
        // Match alphanumeric words and colon-separated CLI commands (e.g. package:enable)
        preg_match_all('/[a-z0-9_\-:]{2,}/', $clean, $matches);
        $rawTokens = $matches[0] ?? [];

        $stopWords = [
            'a' => 1, 'about' => 1, 'above' => 1, 'after' => 1, 'again' => 1, 'against' => 1, 'all' => 1, 'am' => 1,
            'an' => 1, 'and' => 1, 'any' => 1, 'are' => 1, 'aren' => 1, 'as' => 1, 'at' => 1, 'be' => 1,
            'because' => 1, 'been' => 1, 'before' => 1, 'being' => 1, 'below' => 1, 'between' => 1, 'both' => 1,
            'but' => 1, 'by' => 1, 'can' => 1, 'cannot' => 1, 'could' => 1, 'did' => 1, 'do' => 1, 'does' => 1,
            'doing' => 1, 'down' => 1, 'during' => 1, 'each' => 1, 'few' => 1, 'for' => 1, 'from' => 1, 'further' => 1,
            'had' => 1, 'has' => 1, 'have' => 1, 'having' => 1, 'he' => 1, 'her' => 1, 'here' => 1, 'hers' => 1,
            'herself' => 1, 'him' => 1, 'himself' => 1, 'his' => 1, 'how' => 1, 'i' => 1, 'if' => 1, 'in' => 1,
            'into' => 1, 'is' => 1, 'it' => 1, 'its' => 1, 'itself' => 1, 'let' => 1, 'me' => 1, 'more' => 1,
            'most' => 1, 'my' => 1, 'myself' => 1, 'no' => 1, 'nor' => 1, 'not' => 1, 'of' => 1, 'off' => 1,
            'on' => 1, 'once' => 1, 'only' => 1, 'or' => 1, 'other' => 1, 'ought' => 1, 'our' => 1, 'ours' => 1,
            'ourselves' => 1, 'out' => 1, 'over' => 1, 'own' => 1, 'same' => 1, 'she' => 1, 'should' => 1, 'so' => 1,
            'some' => 1, 'such' => 1, 'than' => 1, 'that' => 1, 'the' => 1, 'their' => 1, 'theirs' => 1, 'them' => 1,
            'themselves' => 1, 'then' => 1, 'there' => 1, 'these' => 1, 'they' => 1, 'this' => 1, 'those' => 1,
            'through' => 1, 'to' => 1, 'too' => 1, 'under' => 1, 'until' => 1, 'up' => 1, 'very' => 1, 'was' => 1,
            'we' => 1, 'were' => 1, 'what' => 1, 'when' => 1, 'where' => 1, 'which' => 1, 'while' => 1, 'who' => 1,
            'whom' => 1, 'why' => 1, 'with' => 1, 'would' => 1, 'you' => 1, 'your' => 1, 'yours' => 1, 'yourself' => 1,
            'yourselves' => 1
        ];

        $tokens = [];
        foreach ($rawTokens as $raw) {
            $parts = str_contains($raw, ':') ? explode(':', $raw) : [$raw];
            if (str_contains($raw, ':')) {
                $parts[] = $raw;
            }
            foreach ($parts as $p) {
                $p = trim($p, "-_:");
                if (strlen($p) < 2 || isset($stopWords[$p])) {
                    continue;
                }
                $tokens[] = self::stemWord($p);
            }
        }

        return array_values($tokens);
    }

    /**
     * Morphological word stemmer for English documentation terms.
     */
    public static function stemWord(string $word): string
    {
        $len = strlen($word);
        if ($len <= 3) {
            return $word;
        }
        if (str_ends_with($word, 'ies') && $len > 4) {
            return substr($word, 0, -3) . 'y';
        }
        if (str_ends_with($word, 'es') && $len > 4) {
            if (!preg_match('/(sses|shes|ches|axes|oxes)$/', $word)) {
                return substr($word, 0, -1);
            }
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 's') && !str_ends_with($word, 'ss') && !str_ends_with($word, 'us') && !str_ends_with($word, 'is')) {
            return substr($word, 0, -1);
        }
        return $word;
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
        return DataStorageResolver::getVectorIndexJsonPath($projectId);
    }

    /**
     * Path to the project's disk-backed SQLite inverted index.
     */
    public static function getSqliteIndexPath(string $projectId): string
    {
        return DataStorageResolver::getSearchIndexSqlitePath($projectId);
    }
}
