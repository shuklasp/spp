<?php

namespace App\SPPDocs\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use SPP\App;

/**
 * IssueMarkdownService
 * High-performance, secure GitHub-Flavored Markdown (GFM) renderer for issues, comments, and activity feeds.
 * Includes HTML input escaping for XSS prevention, interactive task lists, issue autolinking, and user mentions.
 */
class IssueMarkdownService
{
    private static ?MarkdownConverter $converter = null;

    private static function getConverter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $config = [
                'html_input' => 'escape', // Absolute XSS prevention
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
            ];

            $environment = new Environment($config);
            $environment->addExtension(new CommonMarkCoreExtension());
            if (class_exists(TableExtension::class)) {
                $environment->addExtension(new TableExtension());
            }

            self::$converter = new MarkdownConverter($environment);
        }
        return self::$converter;
    }

    /**
     * Render raw markdown text into sanitized, styled HTML.
     */
    public static function render(?string $markdown, ?string $projectId = null): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        // 1. Parse via CommonMark with strict HTML input escaping for XSS prevention
        $html = (string)self::getConverter()->convert($markdown);

        // 2. Post-process GFM task checkboxes into real HTML elements
        $html = preg_replace('/<li>\[ \]\s*/i', '<li class="gfm-task-li"><input type="checkbox" disabled class="gfm-task-checkbox"> ', $html);
        $html = preg_replace('/<li>\[x\]\s*(.*?)<\/li>/is', '<li class="gfm-task-li"><input type="checkbox" checked disabled class="gfm-task-checkbox"> <del>$1</del></li>', $html);

        // 3. Autolink @username mentions
        $html = preg_replace_callback('/(?<!\w)@([a-zA-Z0-9_\-\.]+)/', function ($matches) {
            $user = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            return '<span class="gfm-mention">@' . $user . '</span>';
        }, $html);

        // 4. Autolink #123 issue references if projectId is provided
        if (!empty($projectId)) {
            $baseIssueUrl = App::url('issues/view') . '?projectId=' . urlencode($projectId) . '&issueId=';
            $html = preg_replace_callback('/(?<!\w)#([a-zA-Z0-9_\-]+)/', function ($matches) use ($baseIssueUrl) {
                $ref = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
                return '<a href="' . $baseIssueUrl . urlencode($matches[1]) . '" class="gfm-issue-link">#' . $ref . '</a>';
            }, $html);
        }

        return $html;
    }
}
