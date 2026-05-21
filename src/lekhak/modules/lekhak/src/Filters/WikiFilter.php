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
        // 1. Resolve internal links [[Page Title]] or [[nid:123]]
        $content = preg_replace_callback('/\[\[(.*?)\]\]/', function($m) {
            $raw = $m[1];
            $parts = explode('|', $raw);
            $target = trim($parts[0]);
            $label = trim($parts[1] ?? $target);

            if (strpos($target, 'nid:') === 0) {
                $nid = substr($target, 4);
                return "<a href='/lekhak/node/$nid' class='wiki-link'>$label</a>";
            }

            return "<a href='/lekhak/title/" . urlencode($target) . "' class='wiki-link'>$label</a>";
        }, $content);

        // 2. Bold & Italic Formatting
        $content = preg_replace("/'''''(.*?)'''''/", "<strong><em>$1</em></strong>", $content);
        $content = preg_replace("/'''(.*?)'''/", "<strong>$1</strong>", $content);
        $content = preg_replace("/''(.*?)''/", "<em>$1</em>", $content);

        // 3. Headings Formatting
        $content = preg_replace_callback('/^\s*={5}\s*(.*?)\s*={5}\s*$/m', function($m) {
            return "<h5>" . trim($m[1]) . "</h5>";
        }, $content);
        $content = preg_replace_callback('/^\s*={4}\s*(.*?)\s*={4}\s*$/m', function($m) {
            return "<h4>" . trim($m[1]) . "</h4>";
        }, $content);
        $content = preg_replace_callback('/^\s*={3}\s*(.*?)\s*={3}\s*$/m', function($m) {
            return "<h3>" . trim($m[1]) . "</h3>";
        }, $content);
        $content = preg_replace_callback('/^\s*={2}\s*(.*?)\s*={2}\s*$/m', function($m) {
            return "<h2>" . trim($m[1]) . "</h2>";
        }, $content);

        // 4. Horizontal Rules
        $content = preg_replace('/^\s*----\s*$/m', '<hr>', $content);

        // 5. External Links
        $content = preg_replace('/\[(https?:\/\/[^\s\]]+)\s+([^\]]+)\]/', '<a href="$1" class="wiki-ext-link" target="_blank">$2</a>', $content);
        $content = preg_replace('/\[(https?:\/\/[^\s\]]+)\]/', '<a href="$1" class="wiki-ext-link" target="_blank">$1</a>', $content);

        // 6. Simple Bullet Lists
        $content = preg_replace_callback('/^(?:\*\s+)(.*)$/m', function($m) {
            return "<ul><li>" . trim($m[1]) . "</li></ul>";
        }, $content);
        $content = preg_replace('/<\/ul>\s*<ul>/', '', $content);
    }

    public function postProcess(string &$output, array &$context): void
    {
        $this->preProcess($output, $context);
    }
}
