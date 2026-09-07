<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\OpenApiService;
use SPP\Response;

class OpenApiController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/admin/openapi', method: 'GET')]
    public function adminIndex()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $keys = array_keys($this->config['projects'] ?? []);
            $projectId = $keys[0] ?? '';
        }

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }

        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('openapi', $project, $projectId);

        $specs = OpenApiService::listSpecs($projectId);
        $activeSpecName = $_GET['spec'] ?? (array_key_first($specs) ?: '');
        $activeSpec = $activeSpecName ? OpenApiService::getSpec($projectId, $activeSpecName) : null;
        $endpoints = $activeSpec ? OpenApiService::parseEndpoints($activeSpec) : [];

        // Group endpoints by tag
        $grouped = [];
        foreach ($endpoints as $ep) {
            $tag = $ep['tags'][0] ?? 'General';
            $grouped[$tag][] = $ep;
        }

        return $this->render('admin.openapi', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'specs' => $specs,
            'activeSpecName' => $activeSpecName,
            'activeSpec' => $activeSpec,
            'groupedEndpoints' => $grouped,
            'active_tab' => 'openapi',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'user' => $this->getProjectUser(),
        ]);
    }

    #[Route('/admin/openapi/import', method: 'POST')]
    public function importSpec()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('openapi', $project, $projectId);

        $specName = trim($_POST['name'] ?? '');
        $specUrl = trim($_POST['url'] ?? '');
        $rawContent = trim($_POST['content'] ?? '');

        if (!empty($specUrl)) {
            // Fetch remote spec
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'SPPDocs OpenAPI Importer'
                ]
            ]);
            $fetched = @file_get_contents($specUrl, false, $ctx);
            if ($fetched) {
                $rawContent = $fetched;
                if (empty($specName)) {
                    $specName = pathinfo(parse_url($specUrl, PHP_URL_PATH), PATHINFO_FILENAME);
                }
            }
        }

        if (empty($specName)) {
            $specName = 'api_' . date('Ymd_His');
        }

        if (!empty($rawContent)) {
            OpenApiService::saveSpec($projectId, $specName, $rawContent);
            $_SESSION['adm_flash_success'] = "OpenAPI specification '{$specName}' imported successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/openapi?project=" . urlencode($projectId) . "&spec=" . urlencode($specName));
        exit;
    }

    #[Route('/admin/openapi/delete', method: 'POST')]
    public function deleteSpec()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('openapi', $project, $projectId);

        $specName = trim($_POST['name'] ?? '');
        if (!empty($specName)) {
            OpenApiService::deleteSpec($projectId, $specName);
            $_SESSION['adm_flash_success'] = "OpenAPI specification '{$specName}' deleted successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/openapi?project=" . urlencode($projectId));
        exit;
    }

    #[Route('/api/openapi/proxy', method: 'POST')]
    public function proxy()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $targetUrl = trim($input['url'] ?? '');
        $method = strtoupper(trim($input['method'] ?? 'GET'));
        $headers = $input['headers'] ?? [];
        $body = $input['body'] ?? null;

        if (empty($targetUrl)) {
            Response::json(['error' => 'Missing target URL'], 400);
            return;
        }

        // Validate target URL format
        if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            Response::json(['error' => 'Invalid target URL format'], 400);
            return;
        }

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $headerLines = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (!empty($k)) $headerLines[] = "{$k}: {$v}";
            }
        }
        if (!empty($headerLines)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
        }

        $responseRaw = curl_exec($ch);
        $latencyMs = round((microtime(true) - $startTime) * 1000);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            Response::json([
                'success' => false,
                'status' => 0,
                'latency_ms' => $latencyMs,
                'error' => 'cURL Error: ' . $err,
            ]);
            return;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $responseHeadersRaw = substr($responseRaw, 0, $headerSize);
        $responseBody = substr($responseRaw, $headerSize);

        // Attempt JSON parse
        $jsonDecoded = json_decode($responseBody, true);

        Response::json([
            'success' => true,
            'status' => $httpCode,
            'latency_ms' => $latencyMs,
            'body' => $responseBody,
            'json' => $jsonDecoded,
            'headers_raw' => trim($responseHeadersRaw),
        ]);
    }
}