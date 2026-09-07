<?php
namespace App\SPPDocs\Controllers;

use SPP\Attributes\Route;

class ApiController
{
    private $config;

    public function __construct()
    {
        // Load SPPDocs Config
        $configFile = dirname(SPP_BASE_DIR) . '/docs/handbook/spp.yml'; // Default fallback
        if (file_exists($configFile)) {
            $this->config = \Symfony\Component\Yaml\Yaml::parseFile($configFile);
        }
    }

    #[Route('/api/v1/content/{project}', method: 'GET')]
    public function listContent($projectId)
    {
        header('Content-Type: application/json');
        
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            return;
        }

        $typeFilter = $_GET['type'] ?? null;
        $tagFilter = $_GET['tag'] ?? null;
        $categoryFilter = $_GET['category'] ?? null;
        
        $cacheDir = SPP_BASE_DIR . '/var/cache/SPPDocs';
        $cacheFile = $cacheDir . "/index_{$projectId}.php";
        
        if (!file_exists($cacheFile)) {
            if (class_exists('\SPPDocs\classes\SPPCMS')) {
                \SPPDocs\classes\SPPCMS::buildIndex($this->config['projects'][$projectId], $projectId);
            }
        }
        
        $index = file_exists($cacheFile) ? require $cacheFile : [];
        $results = [];
        $now = time();
        
        foreach ($index as $type => $files) {
            if ($typeFilter && $type !== $typeFilter) continue;
            
            foreach ($files as $filename => $meta) {
                $fm = $meta['frontmatter'] ?? [];
                
                // Draft/Publish Workflow filtering
                if (($fm['status'] ?? 'published') === 'draft') continue;
                
                if (!empty($fm['date'])) {
                    $postTime = strtotime($fm['date']);
                    if ($postTime > $now) continue;
                }
                
                // Taxonomy filtering
                if ($tagFilter) {
                    $tags = $fm['tags'] ?? [];
                    if (!is_array($tags) || !in_array($tagFilter, $tags)) continue;
                }
                if ($categoryFilter) {
                    if (($fm['category'] ?? '') !== $categoryFilter) continue;
                }
                
                $results[] = [
                    'id' => $filename,
                    'type' => $type,
                    'title' => $fm['title'] ?? ucfirst(basename($filename)),
                    'author' => $fm['author'] ?? null,
                    'date' => $fm['date'] ?? null,
                    'tags' => $fm['tags'] ?? [],
                    'category' => $fm['category'] ?? null,
                    'modified_at' => $meta['modified_at']
                ];
            }
        }
        
        usort($results, function($a, $b) {
            $dateA = $a['date'] ? strtotime($a['date']) : $a['modified_at'];
            $dateB = $b['date'] ? strtotime($b['date']) : $b['modified_at'];
            return $dateB - $dateA;
        });
        
        echo json_encode([
            'project' => $projectId,
            'count' => count($results),
            'data' => $results
        ]);
    }

    #[Route('/api/v1/content/{project}/{type}/{file}', method: 'GET')]
    public function getContent($projectId, $type, $filename)
    {
        header('Content-Type: application/json');
        
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            return;
        }
        
        $project = $this->config['projects'][$projectId];
        $contentTypes = $project['content_types'] ?? [];
        if (empty($contentTypes)) {
            if (isset($project['pages_dir'])) $contentTypes['page'] = ['dir' => $project['pages_dir']];
            if (isset($project['blog_dir'])) $contentTypes['blog'] = ['dir' => $project['blog_dir']];
        }
        
        if (!isset($contentTypes[$type])) {
            http_response_code(404);
            echo json_encode(['error' => 'Type not found']);
            return;
        }
        
        $dir = $contentTypes[$type]['dir'] ?? '';
        if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
            $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
        }
        if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
            $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
        }
        
        $filename = str_replace(['../', '..\\'], '', $filename);
        $fullPath = $dir . '/' . $filename . '.md';
        
        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }
        
        $markdown = file_get_contents($fullPath);
        $environment = new \League\CommonMark\Environment\Environment([]);
        $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
        $environment->addExtension(new \League\CommonMark\Extension\FrontMatter\FrontMatterExtension());
        $converter = new \League\CommonMark\MarkdownConverter($environment);
        
        $doc = $converter->convert($markdown);
        $frontmatter = [];
        if ($doc instanceof \League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter) {
            $frontmatter = $doc->getFrontMatter();
        }
        
        if (($frontmatter['status'] ?? 'published') === 'draft') {
            http_response_code(403);
            echo json_encode(['error' => 'This content is a draft']);
            return;
        }
        
        if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
            http_response_code(403);
            echo json_encode(['error' => 'This content is scheduled for future publication']);
            return;
        }

        echo json_encode([
            'id' => $filename,
            'type' => $type,
            'title' => $frontmatter['title'] ?? ucfirst(basename($filename)),
            'frontmatter' => $frontmatter,
            'content_html' => $doc->getContent(),
            'modified_at' => filemtime($fullPath)
        ]);
    }
}
