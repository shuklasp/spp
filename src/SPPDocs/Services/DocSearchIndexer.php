<?php

namespace App\SPPDocs\Services;

/**
 * DocSearchIndexer
 * Extracts document headings and sections to generate a lightweight, offline-capable
 * full-text search index for the project's documentation.
 */
class DocSearchIndexer
{
    public static function generateIndex(string $pagesDir, string $outputFile): array
    {
        if (!is_dir($pagesDir)) {
            return [];
        }

        $index = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pagesDir));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $content = file_get_contents($file->getPathname());
                $relPath = substr($file->getPathname(), strlen($pagesDir) + 1);
                $slug = str_replace('\\', '/', preg_replace('/\.md$/', '', $relPath));

                $title = ucfirst(basename($slug));
                if (preg_match('/^---\r?\n(.*?)\r?\n---/s', $content, $m)) {
                    $meta = \Symfony\Component\Yaml\Yaml::parse($m[1]) ?: [];
                    if (!empty($meta['title'])) $title = $meta['title'];
                    $content = substr($content, strlen($m[0]));
                }

                // Strip markdown formatting for pure search text
                $cleanText = strip_tags($content);
                $cleanText = preg_replace('/[#*`_\[\]]/', '', $cleanText);
                $cleanText = preg_replace('/\s+/', ' ', trim($cleanText));

                $index[] = [
                    'slug' => $slug,
                    'title' => $title,
                    'preview' => substr($cleanText, 0, 160) . (strlen($cleanText) > 160 ? '...' : ''),
                    'content' => substr($cleanText, 0, 4000)
                ];
            }
        }

        @file_put_contents($outputFile, json_encode($index, JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $index;
    }
}
