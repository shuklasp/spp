<?php
namespace SPPMod\Lekhak\Filters;

use SPPMod\Lekhak\Core\FilterInterface;

/**
 * Class MathFilter
 * Prepares LaTeX content and injects the local KaTeX renderer.
 */
class MathFilter implements FilterInterface
{
    public function getPriority(): int
    {
        return 50; // Run late (post-process)
    }

    public function preProcess(string &$content, array &$context): void
    {
        // Ensure $ delimiters are protected if needed
    }

    public function postProcess(string &$output, array &$context): void
    {
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        $view = $context['data']['view_name'] ?? ($context['path'] ?? '');
        if (str_contains($reqUri, '/admin') || str_contains((string) $view, 'admin')) {
            return;
        }

        // Detect real math delimiters without being fooled by ordinary CSS/JS dollar signs.
        if (!preg_match('/(?:\$\$.*?\$\$|\$[^$\n]+\$|\\\\\(|\\\\\[)/s', $output)) {
            return;
        }

        if (str_contains($output, 'renderMathInElement(') || str_contains($output, 'katex.min.js')) {
            return;
        }

        $cssPath = 'https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.css';
        $jsPath = 'https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.js';
        $renderPath = 'https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/contrib/auto-render.min.js';

        $injection = "
            <link rel='stylesheet' href='$cssPath'>
            <script defer src='$jsPath'></script>
            <script defer src='$renderPath'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof renderMathInElement !== 'function') return;
                    renderMathInElement(document.body, {
                        delimiters: [
                            {left: '$$', right: '$$', display: true},
                            {left: '$', right: '$', display: false},
                            {left: '\\\\[', right: '\\\\]', display: true},
                            {left: '\\\\(', right: '\\\\)', display: false}
                        ]
                    });
                });
            </script>
        ";

        if (strpos($output, '</head>') !== false) {
            $output = str_replace('</head>', $injection . '</head>', $output);
        } else {
            $output .= $injection;
        }
    }
}
