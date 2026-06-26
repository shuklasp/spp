<?php
namespace SPPMod\SPPTheme\Api;

use Symfony\Component\Yaml\Yaml;

/**
 * ThemeManager
 * 
 * Handles theme discovery, region management, and layout rendering.
 * Locally contained within the application architecture.
 */
class ThemeManager
{
    private static $activeTheme = null;
    private static $regions = [];
    private static $themeData = [];

    /**
     * Set the active theme for the current request.
     */
    public static function setTheme($themeName)
    {
        $app = \SPP\App::getApp();
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);

        // Resolve Theme Directory across universal context locations, including categorized subdirectories
        $baseDirs = [
            $app->getAppSrcDir() . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'themes',
            $app->getAppSrcDir() . DIRECTORY_SEPARATOR . 'themes',
            $baseDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'lekhak' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'themes',
            $baseDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'lekhak' . DIRECTORY_SEPARATOR . 'themes',
            $baseDir . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'themes',
            $baseDir . DIRECTORY_SEPARATOR . 'themes'
        ];

        $subDirs = ['', 'drupal_themes', 'lekhak_themes', 'wp_themes'];
        $candidates = [];

        foreach ($baseDirs as $bd) {
            foreach ($subDirs as $sd) {
                if ($sd === '') {
                    $candidates[] = $bd . DIRECTORY_SEPARATOR . $themeName;
                } else {
                    $candidates[] = $bd . DIRECTORY_SEPARATOR . $sd . DIRECTORY_SEPARATOR . $themeName;
                }
            }
        }

        $resolvedDir = null;
        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                $resolvedDir = $dir;
                break;
            }
        }

        if ($resolvedDir) {
            self::$activeTheme = $resolvedDir;
            $manifest = $resolvedDir . DIRECTORY_SEPARATOR . 'theme.yml';
            if (file_exists($manifest)) {
                self::$themeData = Yaml::parseFile($manifest);
            }
            return true;
        }
        return false;
    }

    /**
     * Add content to a theme region.
     */
    public static function setRegion($region, $content)
    {
        self::$regions[$region] = (self::$regions[$region] ?? '') . $content;
    }

    /**
     * Get content of a theme region.
     */
    public static function getRegion($region)
    {
        return self::$regions[$region] ?? '';
    }

    /**
     * Render the page using the active theme's layout.
     */
    public static function renderWithTheme($pageContent, $pageData = [])
    {
        if (!self::$activeTheme) {
            echo $pageContent;
            return;
        }

        $innerBody = $pageContent;
        $originalHead = '';

        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $pageContent, $mBody)) {
            $innerBody = $mBody[1];
        }

        if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $pageContent, $mHead)) {
            $originalHead = $mHead[1];
        }

        // 1-to-1 Drupal Bridge Mapping: Dissect native application artifacts into targeted Drupal regions
        $drupalRegions = [
            'header' => '',
            'primary_menu' => '',
            'sidebar_first' => '',
            'sidebar_second' => '',
            'content' => '',
            'footer' => '',
            'footer_one' => '',
            'slider' => '',
            'highlighted' => '',
            'breadcrumb' => '',
        ];

        if (preg_match('/<header[^>]*>(.*?)<\/header>/is', $innerBody, $m)) {
            $drupalRegions['header'] = $m[1];
            $innerBody = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $innerBody, 1);
        }
        if (preg_match('/<nav[^>]*>(.*?)<\/nav>/is', $innerBody, $m)) {
            $drupalRegions['primary_menu'] = $m[1];
            $innerBody = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $innerBody, 1);
        }
        if (preg_match('/<aside[^>]*class="[^"]*sidebar[^"]*"[^>]*>(.*?)<\/aside>/is', $innerBody, $m)) {
            $drupalRegions['sidebar_first'] = $m[1];
            $innerBody = preg_replace('/<aside[^>]*class="[^"]*sidebar[^"]*"[^>]*>.*?<\/aside>/is', '', $innerBody, 1);
        }
        if (preg_match('/<footer[^>]*>(.*?)<\/footer>/is', $innerBody, $m)) {
            $drupalRegions['footer_one'] = $m[1];
            $drupalRegions['footer'] = $m[1];
            $innerBody = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $innerBody, 1);
        }

        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $innerBody, $m)) {
            $drupalRegions['content'] = $m[1];
        } else {
            $cleanBody = trim($innerBody);
            if (preg_match('/^<div[^>]*class="[^"]*container[^"]*"[^>]*>(.*)<\/div>$/is', $cleanBody, $mDiv)) {
                $cleanBody = $mDiv[1];
            }
            $drupalRegions['content'] = $cleanBody;
        }

        // Core Theme Layer: Dynamic WYSIWYG styling and interaction injector for all installed themes
        $lekhniStyles = <<<HTML
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

    .lekhni-article-body {
        font-family: 'Inter', sans-serif;
        line-height: 1.8;
        font-size: 1.1rem;
        color: #cbd5e1;
    }

    .lekhni-article-body h1, .lekhni-article-body .lekhni-h1 {
        font-family: 'Outfit', sans-serif;
        font-size: 2.2rem;
        font-weight: 800;
        color: #f8fafc;
        margin: 2.5rem 0 1.2rem 0;
    }

    .lekhni-article-body h2, .lekhni-article-body .lekhni-h2 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.7rem;
        font-weight: 700;
        color: #e2e8f0;
        margin: 2rem 0 1rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding-bottom: 8px;
    }

    .lekhni-article-body h3, .lekhni-article-body .lekhni-h3 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.35rem;
        font-weight: 600;
        color: #f1f5f9;
        margin: 1.5rem 0 0.8rem 0;
    }

    .lekhni-article-body p {
        margin-bottom: 1.5rem;
    }

    .lekhni-article-body blockquote, .lekhni-article-body .lekhni-quote {
        border-left: 4px solid #6366f1;
        padding: 8px 0 8px 1.5rem;
        color: #94a3b8;
        font-style: italic;
        margin: 1.5rem 0;
        background: rgba(99, 102, 241, 0.03);
        border-radius: 0 8px 8px 0;
    }

    .lekhni-article-body pre {
        background: #0b0f19;
        border: 1px solid #334155;
        padding: 1.25rem;
        border-radius: 10px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.92rem;
        overflow-x: auto;
        color: #cbd5e1;
        margin: 1.5rem 0;
    }

    .lekhni-article-body code {
        font-family: 'JetBrains Mono', monospace;
        background: rgba(99, 102, 241, 0.15);
        color: #a5b4fc;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.9em;
    }

    .lekhni-article-body ul, .lekhni-article-body ol {
        margin-bottom: 1.5rem;
        padding-left: 2rem;
    }

    .lekhni-article-body li {
        margin-bottom: 0.6rem;
    }

    .lekhni-smart-grid {
        width: 100%;
        border-collapse: collapse;
        margin: 2rem 0;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #334155;
    }

    .lekhni-smart-grid th, .lekhni-smart-grid td {
        border: 1px solid #334155;
        padding: 12px 14px;
        font-size: 0.9rem;
    }

    .lekhni-smart-grid th {
        background: #1e293b;
        color: #94a3b8;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.78rem;
        letter-spacing: 0.05em;
        text-align: center;
    }

    .lekhni-smart-grid td {
        background: #0f172a;
        color: #cbd5e1;
    }

    .grid-cell-value {
        outline: none;
        transition: all 0.15s;
        font-family: 'JetBrains Mono', monospace;
    }

    .grid-cell-value:focus {
        background: rgba(99, 102, 241, 0.15);
        box-shadow: inset 0 0 0 1px #6366f1;
        border-radius: 2px;
    }

    .lekhni-tasks-container {
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid #334155;
        border-radius: 10px;
        padding: 1.25rem;
        margin: 2rem 0;
    }

    .lekhni-task-row {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 10px;
    }

    .lekhni-task-row:last-child {
        margin-bottom: 0;
    }

    .lekhni-task-row input[type="checkbox"] {
        appearance: none;
        width: 20px;
        height: 20px;
        border: 2px solid #6366f1;
        border-radius: 50%;
        cursor: pointer;
        outline: none;
        transition: all 0.15s;
        position: relative;
        background: transparent;
        flex-shrink: 0;
    }

    .lekhni-task-row input[type="checkbox"]:checked {
        background: #6366f1;
        border-color: #6366f1;
    }

    .lekhni-task-row input[type="checkbox"]:checked::after {
        content: "✓";
        position: absolute;
        color: white;
        font-size: 12px;
        font-weight: bold;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    .lekhni-task-label {
        font-size: 0.98rem;
        color: #cbd5e1;
        transition: all 0.15s;
    }

    .lekhni-task-label.task-item-checked {
        text-decoration: line-through;
        color: #64748b !important;
    }

    .lekhni-pdf-block-wrapper {
        margin: 2rem auto;
        border-radius: 14px;
        border: 1px solid #334155;
        background: #0f172a;
        overflow: hidden;
        max-width: 100%;
        box-shadow: 0 15px 35px rgba(0,0,0,0.5);
    }

    .pdf-embedded-iframe {
        border: none;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.25);
        transition: width 0.15s, height 0.15s;
    }

    .lekhni-gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin: 2rem 0;
        border: 1px solid #334155;
        padding: 14px;
        border-radius: 10px;
        background: rgba(15,23,42,0.3);
    }
</style>
HTML;

        $lekhniScripts = <<<HTML
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll('.lekhni-pdf-block-wrapper').forEach(wrapper => {
            const iframe = wrapper.querySelector('.pdf-embedded-iframe');
            const widthSlider = wrapper.querySelector('.pdf-width-slider');
            const widthVal = wrapper.querySelector('.pdf-width-val');
            const heightSlider = wrapper.querySelector('.pdf-height-slider');
            const heightVal = wrapper.querySelector('.pdf-height-val');
            const deleteBtn = wrapper.querySelector('.btn-pdf-delete');

            if (deleteBtn) {
                deleteBtn.style.display = 'none';
            }

            if (widthSlider && iframe) {
                const currentWidth = parseInt(iframe.style.width) || 794;
                widthSlider.value = currentWidth;
                if (widthVal) widthVal.innerText = `\${currentWidth}px`;

                widthSlider.addEventListener('input', (e) => {
                    const val = e.target.value;
                    iframe.style.width = `\${val}px`;
                    if (widthVal) widthVal.innerText = `\${val}px`;
                });
            }

            if (heightSlider && iframe) {
                const currentHeight = parseInt(iframe.style.height) || 500;
                heightSlider.value = currentHeight;
                if (heightVal) heightVal.innerText = `\${currentHeight}px`;

                heightSlider.addEventListener('input', (e) => {
                    const val = e.target.value;
                    iframe.style.height = `\${val}px`;
                    if (heightVal) heightVal.innerText = `\${val}px`;
                });
            }
        });

        document.querySelectorAll('.lekhni-tasks-container').forEach(container => {
            container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.removeAttribute('disabled');
                cb.addEventListener('change', (e) => {
                    const textSpan = cb.nextElementSibling;
                    if (textSpan) {
                        if (cb.checked) {
                            textSpan.classList.add('task-item-checked');
                        } else {
                            textSpan.classList.remove('task-item-checked');
                        }
                    }
                });
            });
        });

        document.querySelectorAll('.lekhni-smart-grid').forEach(table => {
            table.querySelectorAll('.grid-cell-value').forEach(cell => {
                cell.addEventListener('blur', () => {
                    recalculateGrid(table);
                });
            });
        });

        function recalculateGrid(table) {
            const cells = Array.from(table.querySelectorAll('td[data-cell-id]'));
            const cellMap = {};
            cells.forEach(c => {
                const id = c.getAttribute('data-cell-id');
                const valEl = c.querySelector('.grid-cell-value');
                const txt = valEl ? (valEl.innerText || valEl.textContent || '').trim() : '';
                cellMap[id] = txt;
            });

            cells.forEach(c => {
                const formula = c.getAttribute('data-formula');
                if (formula && formula.startsWith('=')) {
                    try {
                        const cleanForm = formula.substring(1).toUpperCase();
                        let evaluated = '';
                        if (cleanForm.startsWith('SUM(')) {
                            const range = cleanForm.match(/\\(([^)]+)\\)/)?.[1];
                            if (range) evaluated = evaluateSum(range, cellMap);
                        } else if (cleanForm.startsWith('AVERAGE(')) {
                            const range = cleanForm.match(/\\(([^)]+)\\)/)?.[1];
                            if (range) evaluated = evaluateAverage(range, cellMap);
                        } else if (cleanForm.startsWith('PRODUCT(')) {
                            const range = cleanForm.match(/\\(([^)]+)\\)/)?.[1];
                            if (range) evaluated = evaluateProduct(range, cellMap);
                        }
                        const valEl = c.querySelector('.grid-cell-value');
                        if (valEl) valEl.innerText = evaluated;
                    } catch(e) {}
                }
            });
        }

        function evaluateSum(range, cellMap) {
            const vals = getRangeValues(range, cellMap);
            return vals.reduce((sum, v) => sum + v, 0);
        }
        
        function evaluateAverage(range, cellMap) {
            const vals = getRangeValues(range, cellMap);
            return vals.length ? (vals.reduce((sum, v) => sum + v, 0) / vals.length).toFixed(2) : 0;
        }

        function evaluateProduct(range, cellMap) {
            const vals = getRangeValues(range, cellMap);
            return vals.length ? vals.reduce((prod, v) => prod * v, 1) : 0;
        }

        function getRangeValues(range, cellMap) {
            const [start, end] = range.split(':');
            if (!start) return [];
            if (!end) return [parseFloat(cellMap[start]) || 0];

            const startCol = start.charCodeAt(0);
            const startRow = parseInt(start.substring(1));
            const endCol = end.charCodeAt(0);
            const endRow = parseInt(end.substring(1));

            const vals = [];
            for (let col = Math.min(startCol, endCol); col <= Math.max(startCol, endCol); col++) {
                for (let row = Math.min(startRow, endRow); row <= Math.max(startRow, endRow); row++) {
                    const id = String.fromCharCode(col) + row;
                    const v = parseFloat(cellMap[id]) || 0;
                    vals.push(v);
                }
            }
            return vals;
        }
    });
</script>
HTML;

        $drupalRegions['content'] = '<div class="lekhni-article-body">' . $lekhniStyles . $drupalRegions['content'] . $lekhniScripts . '</div>';


        // Check if active theme is an imported decoupled Drupal adapter
        $isDrupalTheme = file_exists(self::$activeTheme . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'page.html.twig') ||
            file_exists(self::$activeTheme . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'page.html.twig');

        if ($isDrupalTheme) {
            // If the view's inner body contained large hero headers, move them to the main content buffer to preserve native layout isolation
            if (!empty($drupalRegions['header']) && (str_contains($drupalRegions['header'], '<h1') || str_contains($drupalRegions['header'], 'cta-btn'))) {
                $drupalRegions['content'] = $drupalRegions['header'] . "\n" . $drupalRegions['content'];
                $drupalRegions['header'] = '';
            }
            if (!empty($drupalRegions['primary_menu']) && str_contains($drupalRegions['primary_menu'], 'logo')) {
                // If index view's raw nav leaked logo structures, suppress it to guarantee beautiful Drupal primary nav rendering
                $drupalRegions['primary_menu'] = '';
            }

            $themePublicPath = self::getThemePublicPath();
            $baseAppUri = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/');

            if (empty($drupalRegions['header'])) {
                $drupalRegions['header'] = <<<DRUPAL_BRAND
                <div class="site-branding" style="display: flex; align-items: center; gap: 15px; padding: 0.5rem 0;">
                    <a href="{$baseAppUri}/lekhak" rel="home" class="site-logo" style="text-decoration: none;">
                        <img src="{$themePublicPath}/logo.svg" alt="Drupal Integrated CMS Logo" style="max-height: 52px; width: auto; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.12));" onerror="this.src='https://placehold.co/180x50/1e3a8a/ffffff?text=DRUPAL+ADAPTER'" />
                    </a>
                    <div class="site-name-slogan" style="display: flex; flex-direction: column; justify-content: center;">
                        <h1 class="site-name" style="margin: 0; font-size: 1.55rem; font-weight: 800; font-family: 'Outfit', sans-serif; letter-spacing: -0.5px; line-height: 1.1;">
                            <a href="{$baseAppUri}/lekhak" style="color: #1e3a8a; text-decoration: none;">VIRTUAL <span style="color: #f97316;">VIDYALAYA</span></a>
                        </h1>
                        <p class="site-slogan" style="margin: 0; font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Premium Polyglot Suite</p>
                    </div>
                </div>
DRUPAL_BRAND;
            }

            if (empty($drupalRegions['primary_menu'])) {
                $drupalRegions['primary_menu'] = <<<DRUPAL_NAV
                <nav class="navigation" role="navigation" style="display: flex; align-items: center;">
                    <ul class="menu primary-menu-list" style="display: flex; list-style: none; margin: 0; padding: 0; gap: 2.2rem; align-items: center; font-family: 'Inter', sans-serif;">
                        <li class="menu-item menu-item--active"><a href="{$baseAppUri}/lekhak" style="color: #f97316; font-weight: 700; text-decoration: none; border-bottom: 2px solid #f97316; padding-bottom: 4px; font-size: 0.95rem;">Home Portal</a></li>
                        <li class="menu-item"><a href="{$baseAppUri}/lekhak/admin" style="color: #1e293b; font-weight: 600; text-decoration: none; transition: color 0.2s; font-size: 0.95rem;">CMS Core Workbench</a></li>
                        <li class="menu-item"><a href="#courses" style="color: #1e293b; font-weight: 600; text-decoration: none; transition: color 0.2s; font-size: 0.95rem;">Academic Curricula</a></li>
                        <li class="menu-item"><a href="#campus" style="color: #1e293b; font-weight: 600; text-decoration: none; transition: color 0.2s; font-size: 0.95rem;">Campus Life</a></li>
                        <li class="menu-item" style="margin-left: 0.5rem;"><a href="#admissions" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: #ffffff; padding: 10px 24px; border-radius: 50px; font-weight: 700; text-decoration: none; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(30,58,138,0.25); display: inline-block;">Apply Now</a></li>
                    </ul>
                </nav>
DRUPAL_NAV;
            }

            $currentContextPath = \SPP\Scheduler::getContext() ?: 'home';
            if (empty($drupalRegions['slider']) && ($currentContextPath === 'home' || $currentContextPath === 'lekhak')) {
                $drupalRegions['slider'] = <<<DRUPAL_SLIDER
                <div class="hero-slider-block" style="position: relative; background: linear-gradient(135deg, rgba(15,23,42,0.85), rgba(30,58,138,0.9)), url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=1200&auto=format&fit=crop') center/cover no-repeat; padding: 7rem 4rem; border-radius: 24px; color: white; margin: 2rem 0 3.5rem; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
                    <div style="position: absolute; top:0; left:0; width: 100%; height: 100%; background: radial-gradient(circle at top right, rgba(249,115,22,0.25), transparent 60%); pointer-events: none;"></div>
                    <div style="max-width: 720px; position: relative; z-index: 10;">
                        <span style="background: rgba(249,115,22,0.95); color: white; font-size: 0.75rem; font-weight: 800; padding: 6px 14px; border-radius: 50px; text-transform: uppercase; letter-spacing: 1px; display: inline-block; margin-bottom: 1.5rem; box-shadow: 0 4px 10px rgba(249,115,22,0.3);">✨ NATIVE DRUPAL ADAPTER ACTIVATED</span>
                        <h2 style="font-size: 3.6rem; font-weight: 800; font-family: 'Outfit', sans-serif; line-height: 1.1; margin-bottom: 1.5rem; text-shadow: 0 2px 8px rgba(0,0,0,0.3);">Next-Gen Knowledge Ecosystem.</h2>
                        <p style="font-size: 1.15rem; opacity: 0.9; margin-bottom: 2.5rem; line-height: 1.6; font-family: 'Inter', sans-serif;">Experience seamless component rendering bridging native Laravel/Blade models directly into decoupled interactive Drupal regions.</p>
                        <div style="display: flex; gap: 1rem;">
                            <a href="#explore" class="btn-hero-primary" style="background: #f97316; color: white; padding: 14px 32px; border-radius: 12px; font-weight: 700; text-decoration: none; font-family: 'Inter', sans-serif; box-shadow: 0 8px 20px rgba(249,115,22,0.4); transition: transform 0.2s; display: inline-block;">Explore Features</a>
                            <a href="{$baseAppUri}/lekhak/admin" class="btn-hero-secondary" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 14px 32px; border-radius: 12px; font-weight: 700; text-decoration: none; font-family: 'Inter', sans-serif; transition: background 0.2s; display: inline-block;">Access Portal</a>
                        </div>
                    </div>
                </div>
DRUPAL_SLIDER;
            }

            if (empty($drupalRegions['footer'])) {
                $drupalRegions['footer'] = <<<DRUPAL_FOOTER
                <div class="premium-drupal-footer" style="background: #0f172a; color: #94a3b8; padding: 5rem 4rem 3rem; border-top: 4px solid #f97316; border-radius: 24px 24px 0 0; margin-top: 5rem; font-family: 'Inter', sans-serif; box-shadow: 0 -10px 30px rgba(0,0,0,0.15);">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; margin-bottom: 4rem;">
                        <div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: white; font-family: 'Outfit', sans-serif; margin-bottom: 1rem;">LEKHAK <span style="color:#f97316;">DRUPAL</span></div>
                            <p style="font-size: 0.9rem; line-height: 1.6; margin-bottom: 1.5rem;">Empowering academic institutions with advanced headless document stores and polyglot translation arrays built on the solid bedrock of enterprise frameworks.</p>
                            <div style="display: flex; gap: 12px;">
                                <span style="width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; color:white; cursor:pointer;">𝕏</span>
                                <span style="width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; color:white; cursor:pointer;">📸</span>
                                <span style="width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; color:white; cursor:pointer;">💼</span>
                            </div>
                        </div>
                        <div>
                            <h4 style="color: white; font-family: 'Outfit', sans-serif; font-size: 1.1rem; margin-bottom: 1.2rem; font-weight: 700;">Academics</h4>
                            <ul style="list-style: none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; font-size:0.9rem;">
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Undergraduate Studies</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Graduate School</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Continuing Education</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Online Certifications</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: white; font-family: 'Outfit', sans-serif; font-size: 1.1rem; margin-bottom: 1.2rem; font-weight: 700;">Admissions</h4>
                            <ul style="list-style: none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; font-size:0.9rem;">
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Tuition & Fees</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Financial Aid</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Campus Tours</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">International Students</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: white; font-family: 'Outfit', sans-serif; font-size: 1.1rem; margin-bottom: 1.2rem; font-weight: 700;">Legal & Security</h4>
                            <ul style="list-style: none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; font-size:0.9rem;">
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Privacy Sandbox</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Terms of Service</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">Accessibility Setup</a></li>
                                <li><a href="#" style="color:#94a3b8; text-decoration:none; transition: color 0.2s;">System Status</a></li>
                            </ul>
                        </div>
                    </div>
                    <div style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 2rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                        <span>&copy; 2026 Virtual Vidyalaya Engine. Native Drupal Adapter Core.</span>
                        <span style="background: rgba(30,58,138,0.5); color: #60a5fa; padding: 4px 10px; border-radius: 4px; font-weight: 600;">v2.0 Enterprise Stable</span>
                    </div>
                </div>
DRUPAL_FOOTER;
                $drupalRegions['footer_one'] = $drupalRegions['footer'];
            }
        }

        // Fetch global blocks and context-specific blocks from landing_blocks
        $currentPageId = 0;
        if (isset($pageData['node']) && is_object($pageData['node']) && isset($pageData['node']->id)) {
            $currentPageId = $pageData['node']->id;
        } elseif (isset($pageData['page']) && is_object($pageData['page']) && isset($pageData['page']->id)) {
            $currentPageId = $pageData['page']->id;
        } elseif (isset($pageData['id'])) {
            $currentPageId = $pageData['id'];
        }

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $blocksTable = \SPPMod\SPPDB\SPPDB::sppTable('landing_blocks');
            if ($db->tableExists($blocksTable)) {
                $rows = $db->execute_query("SELECT * FROM {$blocksTable} WHERE page_id = 0 OR page_id = ? ORDER BY weight ASC", [$currentPageId]);

                foreach ($rows as $row) {
                    $region = $row['region'];
                    if (array_key_exists($region, $drupalRegions)) {
                        $blockContent = '';
                        $blockData = json_decode($row['data'] ?? '{}', true);
                        $blockTitle = $blockData['title'] ?? '';

                        if ($row['block_type'] === 'custom_html' || $row['block_type'] === 'text') {
                            $blockContent = $blockData['content'] ?? $blockData['text'] ?? '';
                        } elseif ($row['block_type'] === 'dynamic_list' || $row['block_type'] === 'dynamic_view') {
                            // Run entity query
                            $entityType = $blockData['entity_type'] ?? 'node';
                            $limit = (int) ($blockData['limit'] ?? 5);
                            $sort = $blockData['sort'] ?? 'created DESC';

                            $conditions = [];
                            if (!empty($blockData['conditions'])) {
                                $conditions = $blockData['conditions'];
                            }

                            $items = [];
                            if ($entityType === 'node' && class_exists('\SPPMod\Lekhak\Core\LekhakNode')) {
                                $items = \SPPMod\Lekhak\Core\LekhakNode::find_all($conditions, $sort, $limit);
                            }

                            $displayStyle = $blockData['display_style'] ?? 'list';

                            $blockContent .= '<div class="dynamic-view-block" style="margin-bottom: 2rem;">';
                            if (!empty($blockTitle)) {
                                $blockContent .= '<h3 class="block-title" style="font-family:\'Outfit\',sans-serif;font-weight:700;color:#f8fafc;margin-bottom:1rem;font-size:1.4rem;">' . htmlspecialchars($blockTitle) . '</h3>';
                            }

                            if (empty($items)) {
                                $blockContent .= '<p style="color:#64748b;font-size:0.85rem;">No content items found.</p>';
                            } else {
                                if ($displayStyle === 'grid') {
                                    $blockContent .= '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1rem;">';
                                    foreach ($items as $item) {
                                        $url = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/') . '/lekhak/node/' . ($item->alias ?? $item->id);
                                        $blockContent .= '<div style="background:rgba(255,255,255,0.03);padding:1.25rem;border-radius:12px;border:1px solid rgba(255,255,255,0.08);transition:transform 0.2s;">';
                                        $blockContent .= '<h4 style="margin:0 0 0.5rem 0;font-size:1.05rem;"><a href="' . $url . '" style="color:#f97316;text-decoration:none;font-weight:700;">' . htmlspecialchars($item->title) . '</a></h4>';
                                        $blockContent .= '<p style="font-size:0.82rem;color:#94a3b8;margin:0;line-height:1.5;">' . substr(strip_tags($item->body ?? ''), 0, 100) . '...</p>';
                                        $blockContent .= '</div>';
                                    }
                                    $blockContent .= '</div>';
                                } elseif ($displayStyle === 'table') {
                                    $blockContent .= '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;color:#cbd5e1;background:rgba(0,0,0,0.2);border-radius:8px;overflow:hidden;">';
                                    $blockContent .= '<thead><tr style="background:rgba(255,255,255,0.05);border-bottom:1px solid rgba(255,255,255,0.08);"><th style="text-align:left;padding:10px 12px;font-weight:700;color:#f8fafc;">Title</th><th style="text-align:left;padding:10px 12px;font-weight:700;color:#f8fafc;">Type</th></tr></thead><tbody>';
                                    foreach ($items as $item) {
                                        $url = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/') . '/lekhak/node/' . ($item->alias ?? $item->id);
                                        $blockContent .= '<tr style="border-bottom:1px solid rgba(255,255,255,0.04);">';
                                        $blockContent .= '<td style="padding:10px 12px;"><a href="' . $url . '" style="color:#f8fafc;text-decoration:none;font-weight:600;">' . htmlspecialchars($item->title) . '</a></td>';
                                        $blockContent .= '<td style="padding:10px 12px;color:#94a3b8;">' . htmlspecialchars($item->bundle ?? 'Page') . '</td>';
                                        $blockContent .= '</tr>';
                                    }
                                    $blockContent .= '</tbody></table>';
                                } else { // 'list'
                                    $blockContent .= '<ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:14px;">';
                                    foreach ($items as $item) {
                                        $url = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/') . '/lekhak/node/' . ($item->alias ?? $item->id);
                                        $blockContent .= '<li style="border-bottom:1px solid rgba(255,255,255,0.04);padding-bottom:10px;display:flex;flex-direction:column;gap:2px;">';
                                        $blockContent .= '<a href="' . $url . '" style="color:#f97316;text-decoration:none;font-weight:600;font-size:0.95rem;transition:color 0.2s;">' . htmlspecialchars($item->title) . '</a>';
                                        $blockContent .= '<span style="font-size:0.75rem;color:#64748b;">Published on ' . date('j M Y', strtotime($item->created ?? 'now')) . '</span>';
                                        $blockContent .= '</li>';
                                    }
                                    $blockContent .= '</ul>';
                                }
                            }
                            $blockContent .= '</div>';
                        } else {
                            $blockContent = '<div class="block-item" style="margin-bottom: 2rem;">';
                            if (!empty($blockTitle)) {
                                $blockContent .= '<h3 style="font-family:\'Outfit\',sans-serif;font-weight:700;color:#f8fafc;margin-bottom:0.75rem;">' . htmlspecialchars($blockTitle) . '</h3>';
                            }
                            $blockContent .= '<p style="color:#cbd5e1;line-height:1.6;font-size:0.95rem;">' . htmlspecialchars($blockData['text'] ?? '') . '</p>';
                            $blockContent .= '</div>';
                        }

                        $drupalRegions[$region] .= "\n" . $blockContent;
                    }
                }
            }

            // Module C & B: Fetch blocks from the new "blocks" table and apply dynamic path visibility & Views queries
            $blocksNewTable = \SPPMod\SPPDB\SPPDB::sppTable('blocks');
            if ($db->tableExists($blocksNewTable)) {
                $newRows = $db->execute_query("SELECT * FROM {$blocksNewTable} ORDER BY weight ASC");
                $currentPath = $_SERVER['REQUEST_URI'] ?? '/lekhak';
                foreach ($newRows as $row) {
                    $region = $row['region'];
                    if (array_key_exists($region, $drupalRegions)) {
                        $visible = true;
                        if (!empty($row['visibility_paths'])) {
                            $visible = false;
                            $patterns = explode("\n", str_replace("\r", "", $row['visibility_paths']));
                            foreach ($patterns as $pattern) {
                                $pattern = trim($pattern);
                                if (empty($pattern))
                                    continue;
                                if ($pattern === '<front>' && ($currentContextPath === 'home' || $currentContextPath === 'lekhak')) {
                                    $visible = true;
                                    break;
                                }
                                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
                                if (preg_match($regex, $currentPath)) {
                                    $visible = true;
                                    break;
                                }
                            }
                        }

                        if ($visible) {
                            $blockContent = '';
                            if ($row['type'] === 'html') {
                                $blockContent = $row['content'];
                            } elseif ($row['type'] === 'view') {
                                $viewResults = \SPPMod\Lekhak\Core\ViewsEngine::executeView($row['name']);
                                if (!empty($viewResults)) {
                                    $blockContent .= '<div class="views-block-' . htmlspecialchars($row['name']) . '" style="margin-bottom: 2rem;">';
                                    if (!empty($row['title'])) {
                                        $blockContent .= '<h3 style="font-family:\'Outfit\',sans-serif;font-weight:700;margin-bottom:1rem;font-size:1.4rem;">' . htmlspecialchars($row['title']) . '</h3>';
                                    }
                                    $blockContent .= '<ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">';
                                    foreach ($viewResults as $item) {
                                        if (is_object($item)) {
                                            $url = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/') . '/lekhak/node/' . ($item->alias ?? $item->id);
                                            $blockContent .= '<li><a href="' . $url . '" style="color:#f97316;text-decoration:none;font-weight:600;">' . htmlspecialchars($item->title) . '</a></li>';
                                        }
                                    }
                                    $blockContent .= '</ul></div>';
                                }
                            }
                            if (!empty($blockContent)) {
                                $drupalRegions[$region] .= "\n" . $blockContent;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            @file_put_contents(SPP_LOG_DIR . '/debug_theme.log', "Block loading failed: " . $e->getMessage() . "\n", FILE_APPEND);
        }

        foreach (self::$regions as $regName => $regContent) {
            $drupalRegions[$regName] = ($drupalRegions[$regName] ?? '') . $regContent;
        }

        self::setRegion('content', $drupalRegions['content']);

        // Prepare template variables
        $vars = array_merge($pageData, self::$regions, $drupalRegions);
        $vars['page'] = $drupalRegions; // Natively expose regions to {{ page.region }} calls

        // Diagnostics
        @file_put_contents(SPP_LOG_DIR . '/debug_theme.log', "[" . date('Y-m-d H:i:s') . "] REGION CONTENT LENGTH: " . strlen($drupalRegions['content'] ?? '') . "\n", FILE_APPEND);
        @file_put_contents(SPP_LOG_DIR . '/debug_theme.log', "[" . date('Y-m-d H:i:s') . "] VARS PAGE CONTENT LENGTH: " . strlen($vars['page']['content'] ?? '') . "\n", FILE_APPEND);
        $vars['theme_path'] = self::getThemePublicPath();
        $vars['assets_root'] = str_replace('\\', '/', dirname(self::getThemePublicPath()));
        $vars['original_head'] = $originalHead;
        $vars['logged_in'] = \SPPMod\SPPAuth\SPPAuth::authSessionExists() ?? false;
        $vars['root_path'] = \SPP\Scheduler::getContext() ?: 'home';

        $layoutFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'layout.blade.php';
        if (!file_exists($layoutFile)) {
            $layoutFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout.blade.php';
        }
        if (!file_exists($layoutFile)) {
            $layoutFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'layout.php';
        }
        if (!file_exists($layoutFile)) {
            $layoutFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout.php';
        }

        // Native external Drupal layout resolution paths
        $twigFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'page.html.twig';
        if (!file_exists($twigFile)) {
            $twigFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'page.html.twig';
        }

        if (file_exists($layoutFile)) {
            if (str_ends_with($layoutFile, '.blade.php') && \SPP\Module::isEnabled('sppblade')) {
                // Intercept layout string to automatically bridge @yield('content') statements into direct buffer outputs
                $rawBlade = file_get_contents($layoutFile);
                if (str_contains($rawBlade, "@yield('content')")) {
                    $rawBlade = str_replace("@yield('content')", "{!! \$content ?? '' !!}", $rawBlade);
                    // Write to a scoped temporary execution proxy template to render safely
                    $proxyFile = dirname($layoutFile) . DIRECTORY_SEPARATOR . 'layout_proxy.blade.php';
                    file_put_contents($proxyFile, $rawBlade);
                    echo \SPPMod\Drishyam\SPPBlade::render($proxyFile, $vars);
                    @unlink($proxyFile);
                } else {
                    echo \SPPMod\Drishyam\SPPBlade::render($layoutFile, $vars);
                }
            } else {
                extract($vars);
                include($layoutFile);
            }
        } elseif (file_exists($twigFile)) {
            // Native zero-dependency modular Twig Template Bridge
            $renderTwig = function ($filePath) use (&$renderTwig, &$vars) {
                if (!file_exists($filePath))
                    return '';
                $content = file_get_contents($filePath);

                $driver = new \SPPMod\Lekhak\Drivers\TwigShimDriver();
                return $driver->parse($content, $vars, function ($incPath, $vars) use (&$renderTwig) {
                    $themeBase = self::$activeTheme;
                    $themeName = basename($themeBase);

                    $incPath = str_replace('\\', '/', $incPath);
                    if (str_contains($incPath, '/')) {
                        $parts = explode('/', $incPath);
                        $first = $parts[0];
                        if (str_starts_with($first, '@') || strtolower($first) === strtolower($themeName)) {
                            array_shift($parts);
                        }
                        $incPath = implode(DIRECTORY_SEPARATOR, $parts);
                    } else {
                        if (str_starts_with($incPath, '@') || strtolower($incPath) === strtolower($themeName)) {
                            $incPath = '';
                        }
                    }

                    $fullIncPath = $themeBase . DIRECTORY_SEPARATOR . $incPath;
                    if (!file_exists($fullIncPath)) {
                        $fullIncPath = $themeBase . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $incPath;
                    }
                    if (file_exists($fullIncPath)) {
                        return $renderTwig($fullIncPath);
                    }
                    return "<!-- Missing include: $incPath -->";
                });
            };

            $renderedPage = $renderTwig($twigFile);

            $htmlTwigFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'html.html.twig';
            if (!file_exists($htmlTwigFile)) {
                $htmlTwigFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'html.html.twig';
            }

            if (file_exists($htmlTwigFile)) {
                $vars['page'] = $renderedPage; // Pass full page buffer to html.html.twig
                $finalHtml = $renderTwig($htmlTwigFile);

                $themeCss = '<link rel="stylesheet" href="' . $vars['theme_path'] . '/css/bootstrap-icons.css">' . "\n" .
                    '<link rel="stylesheet" href="' . $vars['theme_path'] . '/css/style.css">' . "\n" .
                    '<link rel="stylesheet" href="' . $vars['theme_path'] . '/css/responsive.css">' . "\n" .
                    $vars['original_head'];

                $finalHtml = preg_replace('/<css-placeholder[^>]*>/i', $themeCss, $finalHtml);
                $finalHtml = preg_replace('/<head-placeholder[^>]*>/i', '', $finalHtml);
                $finalHtml = preg_replace('/<js-placeholder[^>]*>/i', '', $finalHtml);

                $themeJs = '<script src="' . $vars['theme_path'] . '/js/tiny-slider.js"></script>' . "\n" .
                    '<script src="' . $vars['theme_path'] . '/js/eduxpro.js"></script>' . "\n";
                $finalHtml = preg_replace('/<js-bottom-placeholder[^>]*>/i', $themeJs, $finalHtml);
                echo $finalHtml;
            } else {
                $themeCss = '<link rel="stylesheet" href="' . $vars['theme_path'] . '/css/bootstrap-icons.css">' . "\n" .
                    '<link rel="stylesheet" href="' . $vars['theme_path'] . '/css/style.css">' . "\n" .
                    '<link rel="stylesheet" href="' . $vars['theme_path'] . '/css/responsive.css">' . "\n";
                echo $themeCss . $renderedPage;
            }
        } else {
            echo $pageContent;
        }
    }

    private static function getThemePublicPath()
    {
        $baseUrl = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        $relPath = substr(self::$activeTheme, strlen(SPP_APP_DIR));
        return rtrim($baseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $relPath), '/');
    }

    private static function resolvePathTheme($path, $data)
    {
        $parts = explode('.', $path);
        $val = $data;
        foreach ($parts as $p) {
            if (is_array($val) && isset($val[$p])) {
                $val = $val[$p];
            } elseif (is_object($val)) {
                if (isset($val->$p)) {
                    $val = $val->$p;
                } elseif (method_exists($val, 'get' . ucfirst($p))) {
                    $val = $val->{'get' . ucfirst($p)}();
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        return $val;
    }
}
