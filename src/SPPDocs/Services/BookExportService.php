<?php

namespace App\SPPDocs\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\MarkdownConverter;

/**
 * BookExportService
 * Compiles entire documentation trees into unified, print-perfect books
 * with auto-generated cover page, hierarchical Table of Contents, and running headers.
 */
class BookExportService
{
    private static function getConverter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addExtension(new FrontMatterExtension());
        return new MarkdownConverter($environment);
    }

    public static function compileBook(array $project, string $projectId, string $version): array
    {
        $docsDir = I18nService::resolveDocsDir($project, $version);
        $converter = self::getConverter();

        $orderedFiles = self::resolveOrderedFiles($project, $docsDir);

        $chapters = [];
        $toc = [];
        $chapterNumber = 1;

        foreach ($orderedFiles as $item) {
            $filePath = $docsDir . '/' . $item['file'] . '.md';
            if (!file_exists($filePath)) {
                $filePath = $docsDir . '/' . $item['file'];
                if (!file_exists($filePath)) continue;
            }

            $raw = file_get_contents($filePath);
            $frontmatter = [];
            $body = $raw;

            // Strip frontmatter
            if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/ms', $raw, $matches)) {
                $body = trim($matches[2]);
            }

            // Extract headings for TOC
            $headings = [];
            if (preg_match_all('/^(#{1,3})\s+(.+)$/m', $body, $hMatches, PREG_SET_ORDER)) {
                foreach ($hMatches as $hm) {
                    $level = strlen($hm[1]);
                    $title = trim($hm[2]);
                    $anchor = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(str_replace(' ', '-', $title)));
                    $headings[] = [
                        'level' => $level,
                        'title' => $title,
                        'anchor' => $anchor,
                    ];
                }
            }

            $rendered = (string)$converter->convert($body);

            // Enhance callout alerts in HTML
            $rendered = preg_replace(
                '/<p>\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]<\/p>\s*<blockquote>(.*?)<\/blockquote>/is',
                '<div class="sppdocs-callout sppdocs-callout-$1"><div class="callout-title">$1</div><div class="callout-body">$2</div></div>',
                $rendered
            );

            $chapterSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $item['file']);
            $chapterTitle = $item['title'] ?: ($headings[0]['title'] ?? ucfirst(basename($item['file'])));

            $chapters[] = [
                'number' => $chapterNumber,
                'slug' => $chapterSlug,
                'file' => $item['file'],
                'section' => $item['section'] ?? 'Documentation',
                'title' => $chapterTitle,
                'headings' => $headings,
                'html' => $rendered,
            ];

            $toc[] = [
                'number' => $chapterNumber,
                'slug' => $chapterSlug,
                'section' => $item['section'] ?? 'Documentation',
                'title' => $chapterTitle,
                'headings' => array_filter($headings, fn($h) => $h['level'] > 1),
            ];

            $chapterNumber++;
        }

        return [
            'project_id' => $projectId,
            'title' => $project['title'] ?? ucfirst($projectId),
            'motto' => $project['motto'] ?? ($project['description'] ?? 'Technical Documentation'),
            'version' => $version,
            'logo' => $project['logo'] ?? '',
            'generated_at' => date('F j, Y'),
            'toc' => $toc,
            'chapters' => $chapters,
            'total_chapters' => count($chapters),
        ];
    }

    public static function compileMarkdown(array $project, string $projectId, string $version): string
    {
        $book = self::compileBook($project, $projectId, $version);
        $lines = [];

        $lines[] = "# " . $book['title'];
        $lines[] = "*" . $book['motto'] . "*\n";
        $lines[] = "**Version:** " . $book['version'] . " | **Generated:** " . $book['generated_at'] . "\n";
        $lines[] = "---\n";

        $lines[] = "## Table of Contents\n";
        foreach ($book['toc'] as $item) {
            $lines[] = "- [Chapter {$item['number']}: {$item['title']}](#chapter-{$item['slug']})";
            foreach ($item['headings'] as $sub) {
                $indent = str_repeat('  ', $sub['level'] - 1);
                $lines[] = "{$indent}- [{$sub['title']}](#{$sub['anchor']})";
            }
        }
        $lines[] = "\n---\n";

        $docsDir = I18nService::resolveDocsDir($project, $version);
        foreach ($book['chapters'] as $ch) {
            $lines[] = "<a id=\"chapter-{$ch['slug']}\"></a>\n";
            $lines[] = "# Chapter {$ch['number']}: {$ch['title']}\n";

            $filePath = $docsDir . '/' . $ch['file'] . '.md';
            if (file_exists($filePath)) {
                $raw = file_get_contents($filePath);
                if (preg_match('/^---\s*.*?\s*---\s*(.*)$/ms', $raw, $m)) {
                    $raw = trim($m[1]);
                }
                $lines[] = $raw . "\n\n---\n";
            }
        }

        return implode("\n", $lines);
    }

    private static function resolveOrderedFiles(array $project, string $docsDir): array
    {
        $ordered = [];
        $seen = [];

        // Check sidebar config
        if (!empty($project['sidebar']) && is_array($project['sidebar'])) {
            foreach ($project['sidebar'] as $sectionName => $items) {
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (is_array($item)) {
                            foreach ($item as $title => $path) {
                                if (!isset($seen[$path])) {
                                    $ordered[] = [
                                        'file' => $path,
                                        'title' => is_string($title) ? $title : '',
                                        'section' => $sectionName,
                                    ];
                                    $seen[$path] = true;
                                }
                            }
                        } elseif (is_string($item)) {
                            if (!isset($seen[$item])) {
                                $ordered[] = [
                                    'file' => $item,
                                    'title' => ucfirst(basename($item)),
                                    'section' => $sectionName,
                                ];
                                $seen[$item] = true;
                            }
                        }
                    }
                }
            }
        }

        // Add any remaining unlisted markdown files
        if (is_dir($docsDir)) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docsDir));
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $path = $file->getPathname();
                    if (str_contains($path, '.revisions')) continue;

                    $rel = substr($path, strlen($docsDir) + 1, -3); // remove .md
                    $rel = str_replace('\\', '/', $rel);

                    if (!isset($seen[$rel])) {
                        $ordered[] = [
                            'file' => $rel,
                            'title' => ucfirst(basename($rel)),
                            'section' => 'General',
                        ];
                        $seen[$rel] = true;
                    }
                }
            }
        }

        return $ordered;
    }
}