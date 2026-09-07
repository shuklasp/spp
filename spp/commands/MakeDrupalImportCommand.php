<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeDrupalImportCommand
 *
 * Imports content, taxonomies, and media from Drupal JSON:API into SPP
 * using pure PHP stream contexts and cURL (zero external vendor dependencies).
 *
 * @package SPP\CLI\Commands
 * @author Satya Prakash Shukla
 */
class MakeDrupalImportCommand extends Command
{
    protected string $name = 'make:drupal-import';
    protected string $description = 'Import content, taxonomies, and media from Drupal JSON:API into SPP with 0 external dependencies';

    /**
     * Strict CLI SAPI guard to prevent execution from web contexts.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $url = rtrim($this->getOption($args, 'url', ''), '/');
        $type = strtolower($this->getOption($args, 'type', 'all'));
        $bundle = $this->getOption($args, 'bundle', 'article');
        $app = $this->getOption($args, 'app', 'SPPDocs');
        $project = $this->getOption($args, 'project', 'spp');
        $limit = intval($this->getOption($args, 'limit', 50));
        $dryRun = $this->hasFlag($args, 'dry-run');
        $isMock = $this->hasFlag($args, 'mock');

        $this->line("================================================================================");
        $this->line(" 🚀  SPP Drupal Migration Bridge (JSON:API Importer)");
        $this->line("================================================================================");
        $this->line("Target Application : " . $app);
        $this->line("Target Project     : " . $project);
        $this->line("Import Mode        : " . $type);
        $this->line("Remote Endpoint    : " . ($url ?: ($isMock ? '[Mock Fixture Engine]' : 'None')));
        $this->line("--------------------------------------------------------------------------------\n");

        if (empty($url) && !$isMock) {
            $this->error("Missing required parameter: --url=<DrupalBaseUrl> or pass --mock for test fixture import.");
            $this->line("Usage: php spp.php make:drupal-import --url=https://my-drupal-site.org [--type=content|taxonomy|all] [--bundle=article] [--dry-run]");
            return;
        }

        $importedCount = 0;

        // 1. Content Import
        if ($type === 'content' || $type === 'all') {
            $this->line("📥 Fetching Drupal Content Nodes (Bundle: {$bundle})...");
            $nodes = $isMock ? $this->getMockDrupalNodes($bundle) : $this->fetchDrupalEndpoint($url . "/jsonapi/node/{$bundle}?page[limit]={$limit}");

            if (!empty($nodes)) {
                $importedCount += $this->processNodes($nodes, $app, $project, $bundle, $dryRun);
            } else {
                $this->warn("No nodes returned for bundle '{$bundle}'.");
            }
        }

        // 2. Taxonomy Import
        if ($type === 'taxonomy' || $type === 'all') {
            $vocab = $this->getOption($args, 'vocabulary', 'tags');
            $this->line("🏷️  Fetching Drupal Taxonomy Terms (Vocabulary: {$vocab})...");
            $terms = $isMock ? $this->getMockDrupalTerms($vocab) : $this->fetchDrupalEndpoint($url . "/jsonapi/taxonomy_term/{$vocab}?page[limit]={$limit}");

            if (!empty($terms)) {
                $importedCount += $this->processTerms($terms, $app, $project, $vocab, $dryRun);
            } else {
                $this->warn("No taxonomy terms returned for vocabulary '{$vocab}'.");
            }
        }

        $this->line("\n--------------------------------------------------------------------------------");
        $this->info("✅ Drupal Import finished. Successfully processed {$importedCount} records.");
        if ($dryRun) {
            $this->warn("(Dry-run active: No files were written to disk)");
        }
        $this->line("================================================================================\n");
    }

    /**
     * Fetch JSON:API endpoint using zero-dependency pure PHP HTTP stream.
     */
    private function fetchDrupalEndpoint(string $endpoint): array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/vnd.api+json\r\nUser-Agent: SPP-Drupal-Migrator/1.0\r\n",
                'timeout' => 15,
                'ignore_errors' => true
            ]
        ];

        try {
            $context = stream_context_create($opts);
            $response = @file_get_contents($endpoint, false, $context);
            if ($response === false) {
                $this->error("Failed to connect to Drupal endpoint: {$endpoint}");
                return [];
            }

            $json = json_decode($response, true);
            return $json['data'] ?? [];
        } catch (\Throwable $e) {
            $this->error("HTTP request exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Process and transform Drupal nodes into SPP Markdown docs.
     */
    private function processNodes(array $nodes, string $app, string $project, string $bundle, bool $dryRun): int
    {
        $count = 0;
        $targetDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2)) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $project . DIRECTORY_SEPARATOR . $bundle;

        if (!$dryRun && !is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach ($nodes as $node) {
            $attr = $node['attributes'] ?? [];
            $title = $attr['title'] ?? 'Untitled Node';
            $nid = $attr['drupal_internal__nid'] ?? ($node['id'] ?? uniqid());
            $created = strtotime($attr['created'] ?? 'now');
            $rawBody = $attr['body']['value'] ?? ($attr['field_body']['value'] ?? '');

            // Convert basic HTML to Markdown
            $markdownBody = $this->htmlToMarkdown($rawBody);
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) ?: 'node-' . $nid;

            $fileContent = "---\n";
            $fileContent .= "title: " . json_encode($title) . "\n";
            $fileContent .= "drupal_nid: {$nid}\n";
            $fileContent .= "bundle: {$bundle}\n";
            $fileContent .= "created_at: {$created}\n";
            $fileContent .= "---\n\n";
            $fileContent .= "# {$title}\n\n";
            $fileContent .= $markdownBody . "\n";

            $filePath = $targetDir . DIRECTORY_SEPARATOR . "{$slug}.md";

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would create: {$slug}.md ({$title})");
            } else {
                file_put_contents($filePath, $fileContent);
                $this->line("  📄 Created: {$slug}.md ({$title})");
            }
            $count++;
        }

        return $count;
    }

    /**
     * Process and transform Drupal taxonomy terms into SPP taxonomy store.
     */
    private function processTerms(array $terms, string $app, string $project, string $vocabulary, bool $dryRun): int
    {
        $count = 0;
        $baseDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2));
        $taxFile = $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $project . DIRECTORY_SEPARATOR . 'taxonomy.json';

        $existing = [];
        if (file_exists($taxFile)) {
            $existing = json_decode(file_get_contents($taxFile), true) ?: [];
        }

        foreach ($terms as $term) {
            $attr = $term['attributes'] ?? [];
            $name = $attr['name'] ?? 'Unnamed Term';
            $tid = $attr['drupal_internal__tid'] ?? ($term['id'] ?? uniqid());
            $desc = $attr['description']['value'] ?? '';

            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

            $entry = [
                'id' => "term_{$tid}",
                'name' => $name,
                'slug' => $slug,
                'vocabulary' => $vocabulary,
                'description' => strip_tags($desc),
                'drupal_tid' => $tid,
            ];

            $existing["term_{$tid}"] = $entry;

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would import term: '{$name}' (TID: {$tid})");
            } else {
                $this->line("  🏷️  Imported term: '{$name}' (TID: {$tid})");
            }
            $count++;
        }

        if (!$dryRun && !empty($existing)) {
            if (!is_dir(dirname($taxFile))) {
                mkdir(dirname($taxFile), 0755, true);
            }
            file_put_contents($taxFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $count;
    }

    /**
     * Pure PHP lightweight HTML to Markdown converter.
     */
    private function htmlToMarkdown(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Headings
        $text = preg_replace('/<h1[^>]*>(.*?)<\/h1>/si', "# $1\n\n", $html);
        $text = preg_replace('/<h2[^>]*>(.*?)<\/h2>/si', "## $1\n\n", $text);
        $text = preg_replace('/<h3[^>]*>(.*?)<\/h3>/si', "### $1\n\n", $text);
        $text = preg_replace('/<h4[^>]*>(.*?)<\/h4>/si', "#### $1\n\n", $text);

        // Bold & Italic
        $text = preg_replace('/<(?:strong|b)[^>]*>(.*?)<\/(?:strong|b)>/si', "**$1**", $text);
        $text = preg_replace('/<(?:em|i)[^>]*>(.*?)<\/(?:em|i)>/si', "*$1*", $text);

        // Links
        $text = preg_replace('/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si', "[$2]($1)", $text);

        // Paragraphs & Line breaks
        $text = preg_replace('/<p[^>]*>(.*?)<\/p>/si', "$1\n\n", $text);
        $text = preg_replace('/<br\s*\/?>/si', "\n", $text);

        // Code blocks & Inline code
        $text = preg_replace('/<pre><code>(.*?)<\/code><\/pre>/si', "```\n$1\n```\n\n", $text);
        $text = preg_replace('/<code[^>]*>(.*?)<\/code>/si', "`$1`", $text);

        // Strip remaining HTML tags and decode entities
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize extra whitespace
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Mock data fixtures for testing without an active remote Drupal instance.
     */
    private function getMockDrupalNodes(string $bundle): array
    {
        return [
            [
                'id' => '101-mock-uuid',
                'attributes' => [
                    'drupal_internal__nid' => 101,
                    'title' => 'Migrating Drupal Architecture to SPP',
                    'created' => '2026-03-01T12:00:00+00:00',
                    'body' => [
                        'value' => '<h2>Introduction</h2><p>SPP delivers zero-dependency enterprise speed with Drupal-level extensible blocks and hooks.</p><p>Check the <a href="https://spp.dev">SPP Portal</a> for documentation.</p>'
                    ]
                ]
            ],
            [
                'id' => '102-mock-uuid',
                'attributes' => [
                    'drupal_internal__nid' => 102,
                    'title' => 'Zero Bloat Pure PHP Engineering',
                    'created' => '2026-03-02T15:30:00+00:00',
                    'body' => [
                        'value' => '<p>Eliminating heavy vendor dependencies provides sub-millisecond boot times and seamless portability on shared hosting and containers.</p>'
                    ]
                ]
            ]
        ];
    }

    /**
     * Mock taxonomy terms for testing.
     */
    private function getMockDrupalTerms(string $vocabulary): array
    {
        return [
            [
                'id' => '201-term-uuid',
                'attributes' => [
                    'drupal_internal__tid' => 201,
                    'name' => 'Architecture',
                    'description' => ['value' => 'Core architectural concepts and guidelines.'],
                ]
            ],
            [
                'id' => '202-term-uuid',
                'attributes' => [
                    'drupal_internal__tid' => 202,
                    'name' => 'Performance',
                    'description' => ['value' => 'High-performance tuning and optimizations.'],
                ]
            ]
        ];
    }
}
