<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * SPPDocsBuildCommand
 * Pre-renders SPPDocs markdown projects into standalone static HTML/CSS/JS websites.
 * Generates client-side search index and asset bundles for zero-cost edge hosting (Cloudflare, Vercel, S3).
 */
class SPPDocsBuildCommand extends Command
{
    protected string $name = 'sppdocs:build';
    protected string $description = 'Pre-render SPPDocs project documentation into pure static HTML/CSS/JS for edge CDN deployment.';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $projectId = 'demo-app';
        $customOut = null;
        $createZip = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--project=')) {
                $projectId = substr($arg, 10);
            } elseif (str_starts_with($arg, '--out=')) {
                $customOut = substr($arg, 6);
            } elseif ($arg === '--zip') {
                $createZip = true;
            } elseif ($arg === '--help' || $arg === '-h') {
                $this->showHelp();
                return;
            }
        }

        echo "\n\033[36m🚀 SPPDocs Static Site Compiler (SSG)\033[0m\n";
        echo "Compiling documentation for project: \033[33m{$projectId}\033[0m\n";

        // Load SPPDocs config
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') ? (file_exists(SPP_BASE_DIR . '/src') ? SPP_BASE_DIR : dirname(SPP_BASE_DIR)) : dirname(__DIR__, 2));
        $configFile = $baseDir . '/src/SPPDocs/etc/sppdocs.yml';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__, 2) . '/src/SPPDocs/etc/sppdocs.yml';
        }
        if (!file_exists($configFile)) {
            echo "\033[31mError: SPPDocs configuration not found at {$configFile}\033[0m\n";
            return;
        }

        $sppdocsConfig = Yaml::parseFile($configFile);
        $projectPath = $sppdocsConfig['projects'][$projectId] ?? null;

        if (!$projectPath) {
            echo "\033[31mError: Project '{$projectId}' is not registered in sppdocs.yml\033[0m\n";
            return;
        }

        // Normalize path
        if (str_starts_with($projectPath, 'C:/') || str_starts_with($projectPath, 'c:/')) {
            $projectPath = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $projectPath);
        }
        if (!str_starts_with($projectPath, '/') && !str_contains($projectPath, ':\\')) {
            $projectPath = $baseDir . '/' . $projectPath;
        }

        if (!file_exists($projectPath)) {
            echo "\033[31mError: Project YAML definition not found at {$projectPath}\033[0m\n";
            return;
        }

        $project = Yaml::parseFile($projectPath);
        $projectTitle = $project['title'] ?? ucfirst($projectId);

        // Resolve destination directory
        $outDir = $customOut 
            ? (str_starts_with($customOut, '/') || str_contains($customOut, ':\\') ? $customOut : $baseDir . '/' . $customOut)
            : $baseDir . '/src/SPPDocs/dist/' . $projectId;

        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $assetsDir = $outDir . '/assets';
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        // Copy CSS Assets
        $cssSrcDir = $baseDir . '/src/SPPDocs/resources/css';
        if (is_dir($cssSrcDir)) {
            foreach (glob($cssSrcDir . '/*.css') as $cssFile) {
                copy($cssFile, $assetsDir . '/' . basename($cssFile));
            }
        }

        // Resolve pages / docs dir
        $pagesDir = $project['pages_dir'] ?? ($baseDir . '/src/SPPDocs/projects/' . $projectId . '/pages');
        if (str_starts_with($pagesDir, 'C:/') || str_starts_with($pagesDir, 'c:/')) {
            $pagesDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $pagesDir);
        }
        if (!str_starts_with($pagesDir, '/') && !str_contains($pagesDir, ':\\')) {
            $pagesDir = $baseDir . '/' . $pagesDir;
        }

        $compiledPages = 0;
        $searchIndex = [];

        // Compile Markdown Files
        if (is_dir($pagesDir)) {
            $files = glob($pagesDir . '/*.md');
            foreach ($files as $file) {
                $slug = basename($file, '.md');
                $rawMd = file_get_contents($file);

                // Pre-process components
                if (class_exists('\\App\\SPPDocs\\Services\\MarkdownComponentEngine')) {
                    $rawMd = \App\SPPDocs\Services\MarkdownComponentEngine::process($rawMd);
                }

                // Render CommonMark
                $environment = new \League\CommonMark\Environment\Environment([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);
                $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
                $environment->addExtension(new \League\CommonMark\Extension\Table\TableExtension());
                $environment->addExtension(new \League\CommonMark\Extension\FrontMatter\FrontMatterExtension());
                $converter = new \League\CommonMark\MarkdownConverter($environment);

                $doc = $converter->convert($rawMd);
                $bodyHtml = $doc->getContent();
                $fm = [];
                if ($doc instanceof \League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter) {
                    $fm = $doc->getFrontMatter() ?: [];
                }

                $pageTitle = $fm['title'] ?? ucwords(str_replace(['-', '_'], ' ', $slug));

                // Process GitHub alert callouts
                $alerts = [
                    'NOTE' => ['color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.08)', 'icon' => 'ℹ️'],
                    'TIP' => ['color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.08)', 'icon' => '💡'],
                    'IMPORTANT' => ['color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.08)', 'icon' => '📌'],
                    'WARNING' => ['color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.08)', 'icon' => '⚠️'],
                    'CAUTION' => ['color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.08)', 'icon' => '🛑'],
                ];
                foreach ($alerts as $type => $cfg) {
                    $pattern = '/<blockquote>\s*<p>\s*\[!' . $type . '\]\s*(?:<br\s*\/?>)?(.*?)(?:<\/p>)?\s*<\/blockquote>/is';
                    $replacement = '<div class="alert-box" style="border-left: 4px solid ' . $cfg['color'] . '; background: ' . $cfg['bg'] . ';">'
                                 . '<div>' . $cfg['icon'] . ' ' . $type . '</div>'
                                 . '<div>$1</div>'
                                 . '</div>';
                    $bodyHtml = preg_replace($pattern, $replacement, $bodyHtml);
                }

                // Standalone Static HTML Template
                $fullHtml = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n"
                    . "<meta charset=\"UTF-8\">\n"
                    . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
                    . "<title>" . htmlspecialchars($pageTitle . ' - ' . $projectTitle) . "</title>\n"
                    . "<link rel=\"stylesheet\" href=\"./assets/sppdocs.css\">\n"
                    . "<link rel=\"stylesheet\" href=\"./assets/admin.css\">\n"
                    . "</head>\n<body>\n"
                    . "<header class=\"header-container\" style=\"padding: 1rem 2rem; border-bottom: 1px solid var(--vp-c-divider); display: flex; justify-content: space-between; align-items: center;\">\n"
                    . "  <div style=\"font-size: 1.25rem; font-weight: 700; color: var(--vp-c-brand);\">" . htmlspecialchars($projectTitle) . "</div>\n"
                    . "  <div style=\"font-size: 0.85rem; color: var(--vp-c-text-2);\">Static Edge Build</div>\n"
                    . "</header>\n"
                    . "<main style=\"max-width: 900px; margin: 2rem auto; padding: 0 1.5rem; line-height: 1.7;\">\n"
                    . "  <h1>" . htmlspecialchars($pageTitle) . "</h1>\n"
                    . "  <div class=\"content\">" . $bodyHtml . "</div>\n"
                    . "</main>\n"
                    . "</body>\n</html>";

                $targetHtmlFile = $outDir . '/' . ($slug === 'home' || $slug === 'index' ? 'index.html' : $slug . '.html');
                file_put_contents($targetHtmlFile, $fullHtml);
                $compiledPages++;

                $searchIndex[] = [
                    'url' => ($slug === 'home' || $slug === 'index' ? './index.html' : './' . $slug . '.html'),
                    'title' => $pageTitle,
                    'excerpt' => substr(strip_tags($bodyHtml), 0, 160) . '...',
                ];
            }
        }

        // Generate Search Index JSON
        file_put_contents($outDir . '/search-index.json', json_encode($searchIndex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Create Zip Archive if requested
        if ($createZip && class_exists('\\ZipArchive')) {
            $zipFile = $baseDir . '/src/SPPDocs/dist/' . $projectId . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $filesToZip = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($outDir));
                foreach ($filesToZip as $name => $fileObj) {
                    if (!$fileObj->isDir()) {
                        $filePath = $fileObj->getRealPath();
                        $relativePath = substr($filePath, strlen($outDir) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();
                echo "\033[32m📦 Standalone Zip Archive created: {$zipFile}\033[0m\n";
            }
        }

        echo "\033[32m✔ Successfully compiled {$compiledPages} static pages to: {$outDir}\033[0m\n";
        echo "Ready for zero-cost instant hosting on Cloudflare Pages, Vercel, or GitHub Pages.\n\n";
    }

    private function showHelp(): void
    {
        echo "Usage: php spp.php sppdocs:build [options]\n";
        echo "Options:\n";
        echo "  --project=<id>   Specify the project to compile (default: demo-app)\n";
        echo "  --out=<path>     Custom output directory (default: src/SPPDocs/dist/<projectId>)\n";
        echo "  --zip            Create a deployable .zip archive of the static build\n";
    }
}
