<?php

namespace App\SPPDocs\Services;

/**
 * MarkdownComponentEngine
 * Enables dynamic interactive Blade components embedded directly into Markdown documents.
 * Adheres strictly to Zero Inline HTML and External Partials rules.
 */
class MarkdownComponentEngine
{
    private static array $stash = [];

    /**
     * Extract ::: component ... ::: blocks into safe text tokens before CommonMark conversion.
     */
    public static function extractPlaceholders(string $markdown, $controller = null): string
    {
        self::$stash = [];
        $pattern = '/::: component\s+name=["\']([^"\']+)["\']([^\n]*?)\n([\s\S]*?):::/is';

        return preg_replace_callback($pattern, function ($matches) use ($controller) {
            $name = strtolower(trim($matches[1]));
            $paramString = trim($matches[2] ?? '');
            $body = trim($matches[3] ?? '');

            // Parse parameters
            $params = [];
            if ($paramString) {
                preg_match_all('/([a-zA-Z0-9_\-]+)=["\']([^"\']*)["\']/', $paramString, $pMatches, PREG_SET_ORDER);
                foreach ($pMatches as $pm) {
                    $params[$pm[1]] = $pm[2];
                }
            }

            $rendered = self::renderComponent($name, $params, $body, $controller);
            $token = 'SPPDOCS_COMP_' . bin2hex(random_bytes(6));
            self::$stash[$token] = $rendered;
            return "\n\n" . $token . "\n\n";
        }, $markdown);
    }

    /**
     * Restore rendered component HTML back into the converted HTML.
     */
    public static function restorePlaceholders(string $html): string
    {
        if (empty(self::$stash)) {
            return $html;
        }

        foreach (self::$stash as $token => $rendered) {
            $html = str_replace("<p>{$token}</p>", $rendered, $html);
            $html = str_replace($token, $rendered, $html);
        }

        self::$stash = [];
        return $html;
    }

    public static function process(string $markdown, $controller = null): string
    {
        $converted = self::extractPlaceholders($markdown, $controller);
        return self::restorePlaceholders($converted);
    }

    private static function renderComponent(string $name, array $params, string $body, $controller): string
    {
        $viewName = str_replace('-', '_', $name);
        $partialPath = 'partials/components/' . $viewName . '.blade.php';
        $fullPath = __DIR__ . '/../resources/views/' . $partialPath;

        if (!file_exists($fullPath)) {
            return "<!-- Component '{$name}' not found -->";
        }

        $data = ['params' => $params];

        // Specific component pre-processing
        if ($viewName === 'tabs') {
            $tabs = [];
            $tabBlocks = preg_split('/^@tab:\s*(.+)$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (count($tabBlocks) > 1) {
                for ($i = 1; $i < count($tabBlocks); $i += 2) {
                    $title = trim($tabBlocks[$i]);
                    $content = trim($tabBlocks[$i + 1] ?? '');
                    
                    $contentHtml = '<pre><code>' . htmlspecialchars($content) . '</code></pre>';
                    $tabs[] = [
                        'title' => $title,
                        'content' => $contentHtml,
                    ];
                }
            }
            $data['tabs'] = $tabs;
        }

        // Render via controller renderDocPartial or renderPartial if available
        if ($controller) {
            if (method_exists($controller, 'renderDocPartial')) {
                try {
                    return $controller->renderDocPartial($partialPath, $data);
                } catch (\Throwable $e) {}
            } elseif (method_exists($controller, 'renderPartial')) {
                try {
                    return $controller->renderPartial($partialPath, $data);
                } catch (\Throwable $e) {}
            }
        }

        // Fallback to instantiate DocsController
        if (class_exists('\\App\\SPPDocs\\Controllers\\DocsController')) {
            try {
                $ctrl = new \App\SPPDocs\Controllers\DocsController();
                return $ctrl->renderDocPartial($partialPath, $data);
            } catch (\Throwable $e) {}
        }

        // Fallback to Blade compiler if available
        if (class_exists('\\SPP\\Blade')) {
            try {
                return \SPP\Blade::render($fullPath, $data);
            } catch (\Throwable $e) {}
        }

        // Low-level template include fallback
        extract($data);
        ob_start();
        include $fullPath;
        return ob_get_clean() ?: '';
    }
}
