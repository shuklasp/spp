<?php
namespace SPPMod\Lekhak\Filters;

use SPPMod\Lekhak\Core\FilterInterface;

/**
 * Class WikiFilter
 * Handles [[Internal Links]] and wiki-style markup.
 */
class WikiFilter implements FilterInterface
{
    public function getPriority(): int
    {
        return 10; // Run early
    }

    public function preProcess(string &$content, array &$context): void
    {
        // Resolve [[Page Title]] or [[nid:123]]
        $content = preg_replace_callback('/\[\[(.*?)\]\]/', function($m) {
            $raw = $m[1];
            $parts = explode('|', $raw);
            $target = trim($parts[0]);
            $label = trim($parts[1] ?? $target);

            if (strpos($target, 'nid:') === 0) {
                $nid = substr($target, 4);
                return "<a href='/lekhak/node/$nid' class='wiki-link'>$label</a>";
            }

            // For titles, we'd ideally lookup the NID
            return "<a href='/lekhak/title/" . urlencode($target) . "' class='wiki-link'>$label</a>";
        }, $content);
    }

    public function postProcess(string &$output, array &$context): void
    {
        // No post-processing needed for now
    }
}
