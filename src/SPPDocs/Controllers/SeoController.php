<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;

class SeoController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/sitemap.xml', method: 'GET')]
    public function sitemapXml()
    {
        $this->loadProjectConfig();
        $baseUrl = \SPP\App::getBaseUrl();
        $urls = [];

        // 1. Root / Docs home
        $urls[] = [
            'loc' => $baseUrl ?: '/',
            'lastmod' => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];

        // 2. Iterate projects
        if (!empty($this->config['projects']) && is_array($this->config['projects'])) {
            foreach ($this->config['projects'] as $pId => $project) {
                $projectHome = $baseUrl . '/project/' . rawurlencode($pId);
                $urls[] = [
                    'loc' => $projectHome,
                    'lastmod' => date('Y-m-d'),
                    'changefreq' => 'daily',
                    'priority' => '0.9'
                ];

                // Crawl Docs / Pages
                $docsDir = $this->resolveDocsDir($pId, $project['default_version'] ?? 'v1');
                if (is_dir($docsDir)) {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($docsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
                    );
                    foreach ($iterator as $file) {
                        if ($file->isFile() && $file->getExtension() === 'md') {
                            $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($docsDir) + 1));
                            $slug = substr($relPath, 0, -3); // trim .md

                            // Check frontmatter status
                            $content = @file_get_contents($file->getPathname()) ?: '';
                            $frontmatter = $this->extractFrontmatter($content);

                            if (($frontmatter['status'] ?? 'published') === 'draft') {
                                continue;
                            }
                            if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
                                continue;
                            }

                            $lastMod = date('Y-m-d', $file->getMTime());
                            if (!empty($frontmatter['updated'])) {
                                $lastMod = date('Y-m-d', strtotime($frontmatter['updated']));
                            }

                            $docUrl = $baseUrl . '/docs/' . rawurlencode($pId) . '/' . rawurlencode($project['default_version'] ?? 'v1') . '/' . $slug;
                            $urls[] = [
                                'loc' => $docUrl,
                                'lastmod' => $lastMod,
                                'changefreq' => 'weekly',
                                'priority' => '0.8'
                            ];
                        }
                    }
                }

                // Crawl Blog if configured
                if (!empty($project['blog_dir'])) {
                    $blogDir = $project['blog_dir'];
                    if (strpos($blogDir, 'C:/') === 0 || strpos($blogDir, 'c:/') === 0) {
                        $blogDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $blogDir);
                    }
                    if (!str_starts_with($blogDir, '/') && !str_contains($blogDir, ':\\')) {
                        $blogDir = dirname(SPP_BASE_DIR) . '/' . $blogDir;
                    }
                    if (is_dir($blogDir)) {
                        $urls[] = [
                            'loc' => $baseUrl . '/blog?project=' . rawurlencode($pId),
                            'lastmod' => date('Y-m-d'),
                            'changefreq' => 'daily',
                            'priority' => '0.8'
                        ];

                        foreach (glob($blogDir . '/*.md') as $bFile) {
                            $bSlug = basename($bFile, '.md');
                            $bContent = @file_get_contents($bFile) ?: '';
                            $bFm = $this->extractFrontmatter($bContent);
                            if (($bFm['status'] ?? 'published') === 'draft') {
                                continue;
                            }
                            if (!empty($bFm['date']) && strtotime($bFm['date']) > time()) {
                                continue;
                            }

                            $bMod = date('Y-m-d', filemtime($bFile));
                            $urls[] = [
                                'loc' => $baseUrl . '/blog?project=' . rawurlencode($pId) . '&post=' . rawurlencode($bSlug),
                                'lastmod' => $bMod,
                                'changefreq' => 'monthly',
                                'priority' => '0.7'
                            ];
                        }
                    }
                }
            }
        }

        // Build XML
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
            $xml .= "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
            $xml .= "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
            $xml .= "    <priority>" . $u['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $xml;
        exit;
    }

    #[Route('/robots.txt', method: 'GET')]
    public function robotsTxt()
    {
        $baseUrl = \SPP\App::getBaseUrl();
        $output = "User-agent: *\n";
        $output .= "Allow: /\n";
        $output .= "Disallow: /admin\n";
        $output .= "Disallow: /admin/\n";
        $output .= "Disallow: /preview\n";
        $output .= "Disallow: /preview/\n";
        $output .= "Disallow: /api/\n\n";
        $output .= "Sitemap: " . ($baseUrl ?: '') . "/sitemap.xml\n";

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo $output;
        exit;
    }

    #[Route('/og/card', method: 'GET')]
    public function ogCard()
    {
        $projectId = $_GET['project'] ?? 'SPPDocs';
        $title = trim($_GET['title'] ?? 'SPPDocs Documentation');
        $desc = trim($_GET['desc'] ?? 'Enterprise documentation and content management system.');

        // Format lines for SVG
        $titleLines = $this->wrapText($title, 32, 2);
        $descLines = $this->wrapText($desc, 55, 2);

        $safeProject = htmlspecialchars(strtoupper($projectId), ENT_XML1, 'UTF-8');

        $titleSvg = '';
        $y = count($titleLines) === 1 ? 290 : 260;
        foreach ($titleLines as $line) {
            $escaped = htmlspecialchars($line, ENT_XML1, 'UTF-8');
            $titleSvg .= "<text x=\"100\" y=\"{$y}\" font-family=\"-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif\" font-size=\"48\" font-weight=\"800\" fill=\"#f8fafc\">{$escaped}</text>\n";
            $y += 56;
        }

        $descSvg = '';
        $descY = $y + 16;
        foreach ($descLines as $dLine) {
            $escapedDesc = htmlspecialchars($dLine, ENT_XML1, 'UTF-8');
            $descSvg .= "<text x=\"100\" y=\"{$descY}\" font-family=\"-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif\" font-size=\"24\" font-weight=\"400\" fill=\"#94a3b8\">{$escapedDesc}</text>\n";
            $descY += 34;
        }

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#090d16" />
      <stop offset="50%" stop-color="#0f172a" />
      <stop offset="100%" stop-color="#1e1b4b" />
    </linearGradient>
    <linearGradient id="accent" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#3b82f6" />
      <stop offset="100%" stop-color="#8b5cf6" />
    </linearGradient>
    <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur stdDeviation="80" result="blur" />
    </filter>
  </defs>

  <!-- Background -->
  <rect width="1200" height="630" fill="url(#bg)" />

  <!-- Ambient Glow Orbs -->
  <circle cx="1050" cy="150" r="220" fill="#3b82f6" opacity="0.25" filter="url(#glow)" />
  <circle cx="150" cy="500" r="260" fill="#8b5cf6" opacity="0.2" filter="url(#glow)" />

  <!-- Card Border / Inner Glow Frame -->
  <rect x="40" y="40" width="1120" height="550" rx="24" fill="none" stroke="rgba(255, 255, 255, 0.1)" stroke-width="2" />

  <!-- Top Badge / Project Pill -->
  <g transform="translate(100, 110)">
    <rect width="auto" height="36" rx="18" fill="rgba(59, 130, 246, 0.2)" stroke="rgba(59, 130, 246, 0.4)" stroke-width="1.5" />
    <text x="18" y="24" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="14" font-weight="700" letter-spacing="1.5" fill="#60a5fa">{$safeProject} &#10022; DOCS</text>
  </g>

  <!-- Main Title -->
  {$titleSvg}

  <!-- Subtitle / Excerpt -->
  {$descSvg}

  <!-- Footer Brand Bar -->
  <line x1="100" y1="520" x2="1100" y2="520" stroke="rgba(255, 255, 255, 0.1)" stroke-width="1" />
  
  <g transform="translate(100, 552)">
    <circle cx="10" cy="-6" r="8" fill="#3b82f6" />
    <text x="30" y="0" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="16" font-weight="600" fill="#f8fafc">SPPDocs CMS</text>
    <text x="180" y="0" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="15" font-weight="400" fill="#64748b">Verified &amp; Single-Sourced</text>
  </g>
</svg>
SVG;

        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo $svg;
        exit;
    }

    private function wrapText(string $text, int $maxChars, int $maxLines): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            if (mb_strlen($currentLine . ' ' . $word) <= $maxChars) {
                $currentLine = trim($currentLine . ' ' . $word);
            } else {
                if (!empty($currentLine)) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
                if (count($lines) >= $maxLines - 1) {
                    break;
                }
            }
        }
        if (!empty($currentLine) && count($lines) < $maxLines) {
            $lines[] = $currentLine;
        }

        if (count($lines) >= $maxLines && count($words) > 0) {
            $lastIndex = count($lines) - 1;
            if (mb_strlen($lines[$lastIndex]) > $maxChars - 3) {
                $lines[$lastIndex] = mb_substr($lines[$lastIndex], 0, $maxChars - 3) . '...';
            }
        }

        return $lines ?: [$text];
    }

    private function extractFrontmatter(string $content): array
    {
        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $content, $matches)) {
            try {
                return \Symfony\Component\Yaml\Yaml::parse($matches[1]) ?: [];
            } catch (\Exception $e) {
                return [];
            }
        }
        return [];
    }

    private function resolveDocsDir(string $projectId, string $version): string
    {
        $project = $this->config['projects'][$projectId] ?? [];
        $docsDir = $project['versions'][$version] ?? ($project['docs_dir'] ?? ('docs/' . $projectId . '/' . $version));
        if (strpos($docsDir, 'C:/') === 0 || strpos($docsDir, 'c:/') === 0) {
            $docsDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $docsDir);
        }
        if (!str_starts_with($docsDir, '/') && !str_contains($docsDir, ':\\')) {
            $docsDir = dirname(SPP_BASE_DIR) . '/' . $docsDir;
        }
        return $docsDir;
    }
}