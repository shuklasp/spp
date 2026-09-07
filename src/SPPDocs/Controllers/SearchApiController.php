<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\VectorSearchEngine;
use SPP\Response;

class SearchApiController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/api/search/instant', method: 'GET')]
    public function instant()
    {
        $projectId = $_GET['project'] ?? $_GET['projectId'] ?? '';
        $query = trim($_GET['query'] ?? $_GET['search'] ?? '');
        if ($query === '' && isset($_GET['q']) && !str_contains($_GET['q'], 'api/search')) {
            $query = trim($_GET['q']);
        }
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 8)));

        if (empty($projectId) || empty($query)) {
            if ($this->wantsJson()) {
                Response::json(['results' => []]);
                return;
            }
            return $this->renderPartial('partials/instant_search_results.blade.php', [
                'results' => [],
                'project_id' => $projectId,
                'query' => $query
            ]);
        }

        $results = VectorSearchEngine::search($projectId, $query, $limit);

        if (empty($results) && mb_strlen($query) >= 3) {
            $this->recordUnmetQuery($projectId, $query);
        }

        if ($this->wantsJson()) {
            Response::json(['results' => $results]);
            return;
        }

        return $this->renderPartial('partials/instant_search_results.blade.php', [
            'results' => $results,
            'project_id' => $projectId,
            'query' => $query
        ]);
    }

    #[Route('/api/search/ask', method: 'GET')]
    public function ask()
    {
        $projectId = $_GET['project'] ?? $_GET['projectId'] ?? '';
        $query = trim($_GET['query'] ?? $_GET['search'] ?? '');
        if ($query === '' && isset($_GET['q']) && !str_contains($_GET['q'], 'api/search')) {
            $query = trim($_GET['q']);
        }

        if (empty($projectId) || empty($query)) {
            $emptyRes = [
                'query' => $query,
                'answer' => 'Please provide a search question and specify a project.',
                'citations' => [],
                'provider' => 'offline',
            ];
            if ($this->wantsJson()) {
                Response::json($emptyRes);
                return;
            }
            return $this->renderPartial('partials/ai_ask_result.blade.php', [
                'response' => $emptyRes,
                'project_id' => $projectId
            ]);
        }

        $response = VectorSearchEngine::ask($projectId, $query);

        if ($this->wantsJson()) {
            Response::json($response);
            return;
        }

        return $this->renderPartial('partials/ai_ask_result.blade.php', [
            'response' => $response,
            'project_id' => $projectId
        ]);
    }

    private function wantsJson(): bool
    {
        if (isset($_GET['format']) && strtolower($_GET['format']) === 'json') {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    private function recordUnmetQuery(string $projectId, string $query): void
    {
        try {
            $dataDir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/data';
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0777, true);
            }
            $file = $dataDir . '/unmet_queries.json';
            $queries = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

            $normalized = mb_strtolower(trim($query));
            if (!isset($queries[$normalized])) {
                $queries[$normalized] = [
                    'query' => trim($query),
                    'count' => 0,
                    'first_searched' => time(),
                ];
            }
            $queries[$normalized]['count']++;
            $queries[$normalized]['last_searched'] = time();

            if (count($queries) > 150) {
                uasort($queries, function ($a, $b) {
                    return ($b['count'] ?? 1) <=> ($a['count'] ?? 1);
                });
                $queries = array_slice($queries, 0, 150, true);
            }

            @file_put_contents($file, json_encode($queries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        } catch (\Throwable $e) {
            // Non-blocking telemetry
        }
    }
}
