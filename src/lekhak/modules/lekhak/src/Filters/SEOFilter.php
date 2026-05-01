<?php
namespace SPPMod\Lekhak\Filters;

use SPPMod\Lekhak\Core\FilterInterface;

/**
 * Class SEOFilter
 * Automatically generates and injects SEO meta tags.
 */
class SEOFilter implements FilterInterface
{
    public function getPriority(): int
    {
        return 90; // Run very late
    }

    public function preProcess(string &$content, array &$context): void
    {
    }

    public function postProcess(string &$output, array &$context): void
    {
        $node = $context['data']['node'] ?? null;
        if (!$node) return;

        $title = $node->title ?? 'Lekhak Content';
        $desc = $this->truncate(strip_tags($node->body ?? ''), 160);
        $url = $this->getCurrentUrl();

        $meta = "
            <!-- Lekhak SEO Suite -->
            <title>$title</title>
            <meta name='description' content='$desc'>
            <link rel='canonical' href='$url'>
            <meta property='og:title' content='$title'>
            <meta property='og:description' content='$desc'>
            <meta property='og:type' content='article'>
            <meta property='og:url' content='$url'>
            <meta name='twitter:card' content='summary_large_image'>
        ";

        if (strpos($output, '<head>') !== false) {
            $output = str_replace('<head>', '<head>' . $meta, $output);
        }
    }

    protected function truncate(string $text, int $limit): string
    {
        if (strlen($text) <= $limit) return $text;
        return substr($text, 0, $limit - 3) . '...';
    }

    protected function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}
