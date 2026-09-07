<?php

namespace App\SPPDocs\Services;

/**
 * SnippetManager
 * Manages reusable global markdown fragments and dynamic variable interpolation
 * for single-source documentation and CMS content.
 */
class SnippetManager
{
    public static function getSnippetsDir(string $projectId): string
    {
        $dir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/snippets';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function listSnippets(string $projectId): array
    {
        $dir = self::getSnippetsDir($projectId);
        $snippets = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.md') as $file) {
                $name = basename($file, '.md');
                $content = @file_get_contents($file) ?: '';
                $snippets[$name] = [
                    'name' => $name,
                    'file' => basename($file),
                    'path' => $file,
                    'preview' => substr(strip_tags($content), 0, 120),
                    'size' => filesize($file),
                    'modified_at' => filemtime($file),
                ];
            }
        }
        ksort($snippets);
        return $snippets;
    }

    public static function getSnippet(string $projectId, string $name): ?string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $name);
        $file = self::getSnippetsDir($projectId) . '/' . $safeName . '.md';
        if (file_exists($file)) {
            return @file_get_contents($file) ?: '';
        }
        return null;
    }

    public static function saveSnippet(string $projectId, string $name, string $content): bool
    {
        $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $name);
        if (empty($safeName)) {
            return false;
        }
        $file = self::getSnippetsDir($projectId) . '/' . $safeName . '.md';
        return (bool)@file_put_contents($file, $content, LOCK_EX);
    }

    public static function deleteSnippet(string $projectId, string $name): bool
    {
        $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $name);
        $file = self::getSnippetsDir($projectId) . '/' . $safeName . '.md';
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }

    /**
     * Expands snippet directives and project variables within raw markdown before rendering.
     * Supports:
     *   ::: snippet name="install-prereqs" :::
     *   :::snippet install-prereqs:::
     *   {{ var.version }}
     */
    public static function expand(string $projectId, string $markdown, array $variables = []): string
    {
        // 1. Expand Snippet blocks (limit recursion depth to 3)
        $depth = 0;
        while ($depth < 3 && (strpos($markdown, '::: snippet') !== false || strpos($markdown, ':::snippet') !== false)) {
            $prevMarkdown = $markdown;
            $markdown = preg_replace_callback('/:::\s*snippet\s+(?:name=["\']([^"\']+)["\']|([a-zA-Z0-9\-_]+))\s*:::/i', function ($matches) use ($projectId) {
                $snippetName = !empty($matches[1]) ? $matches[1] : ($matches[2] ?? '');
                $snippetContent = self::getSnippet($projectId, $snippetName);
                if ($snippetContent !== null) {
                    return "\n\n<!-- snippet: {$snippetName} -->\n" . trim($snippetContent) . "\n<!-- /snippet: {$snippetName} -->\n\n";
                }
                return "\n\n<!-- snippet '{$snippetName}' not found -->\n\n";
            }, $markdown);

            if ($markdown === $prevMarkdown) {
                break;
            }
            $depth++;
        }

        // 2. Variable interpolation: {{ var.appName }} or {{ var.version }}
        if (!empty($variables)) {
            $markdown = preg_replace_callback('/\{\{\s*var\.([a-zA-Z0-9_\-]+)\s*\}\}/i', function ($matches) use ($variables) {
                $key = $matches[1];
                return isset($variables[$key]) ? (string)$variables[$key] : $matches[0];
            }, $markdown);
        }

        return $markdown;
    }
}
