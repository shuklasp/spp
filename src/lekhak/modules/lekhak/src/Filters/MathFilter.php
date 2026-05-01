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
        // 1. Detect if math exists
        if (strpos($output, '$') === false && strpos($output, '$$') === false) {
            return;
        }

        // 2. Inject Local KaTeX assets (Zero Dependency)
        // We assume assets are in the lekhak module's res folder
        $base = defined('APP_BASE_URI') ? rtrim(APP_BASE_URI, '/') : '';
        $jsPath = $base . '/res/spp/lekhak/js/katex.min.js';
        $cssPath = $base . '/res/spp/lekhak/css/katex.min.css';
        $renderPath = $base . '/res/spp/lekhak/js/auto-render.min.js';

        $injection = "
            <link rel='stylesheet' href='$cssPath'>
            <script src='$jsPath'></script>
            <script src='$renderPath'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    renderMathInElement(document.body, {
                        delimiters: [
                            {left: '$$', right: '$$', display: true},
                            {left: '$', right: '$', display: false}
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
