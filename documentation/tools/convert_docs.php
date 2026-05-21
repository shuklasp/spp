<?php
/**
 * SPP Documentation Converter
 * Converts Markdown to Premium HTML with SATYA STUDIO PRO styling.
 */

$rootDir = dirname(__DIR__);
$files = getMarkdownFiles($rootDir);

$template = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{TITLE}} | SPP Documentation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #4f46e5;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f8fafc;
            --text-dim: #94a3b8;
            --accent: #38bdf8;
            --code-bg: #020617;
            --border: rgba(255, 255, 255, 0.1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.7;
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 3rem;
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        header {
            border-bottom: 1px solid var(--border);
            padding-bottom: 2rem;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-box { display: flex; align-items: center; gap: 1rem; }
        .logo-box img { width: 40px; height: 40px; }
        .logo-box span { font-weight: 800; font-size: 1.2rem; color: var(--accent); }

        h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 1.5rem; color: var(--accent); letter-spacing: -0.02em; }
        h2 { font-size: 1.8rem; margin: 2.5rem 0 1.2rem; color: var(--primary); border-left: 4px solid var(--primary); padding-left: 1rem; }
        h3 { font-size: 1.4rem; margin: 2rem 0 1rem; color: var(--text); }

        p { margin-bottom: 1.2rem; color: var(--text-dim); }

        ul, ol { margin-left: 1.5rem; margin-bottom: 1.5rem; }
        li { margin-bottom: 0.5rem; color: var(--text-dim); }

        a { color: var(--accent); text-decoration: none; transition: color 0.2s; }
        a:hover { color: var(--primary); text-decoration: underline; }

        code {
            font-family: 'JetBrains Mono', monospace;
            background: var(--code-bg);
            padding: 0.2rem 0.4rem;
            border-radius: 0.3rem;
            color: var(--accent);
            font-size: 0.9em;
        }

        pre {
            background: var(--code-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            overflow-x: auto;
            margin: 2rem 0;
            border: 1px solid var(--border);
        }

        pre code { color: #e2e8f0; background: transparent; padding: 0; }

        hr { border: 0; border-top: 1px solid var(--border); margin: 3rem 0; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: var(--text-dim);
        }

        footer {
            margin-top: 5rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-dim);
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-box">
                <img src="{{ASSETS_PATH}}/spp-logo.png" alt="SPP">
                <span>SATYA STUDIO PRO</span>
            </div>
            <div class="meta">DOCS V2.0</div>
        </header>
        
        <main>
            {{CONTENT}}
        </main>

        <footer>
            &copy; 2026 Satya Studio Framework Documentation. Parallel Maintenance Active.
        </footer>
    </div>
</body>
</html>
HTML;

foreach ($files as $mdFile) {
    echo "Processing: $mdFile\n";
    $htmlFile = str_replace('.md', '.html', $mdFile);
    $content = file_get_contents($mdFile);
    
    // Basic Title Extraction
    $title = "Documentation";
    if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
        $title = trim($matches[1]);
    }

    // Convert Markdown to HTML (Simple Regex Engine)
    $html = convertMarkdown($content);

    // Calculate Assets Path (relative to current file)
    $depth = count(explode(DIRECTORY_SEPARATOR, str_replace($rootDir . DIRECTORY_SEPARATOR, '', $mdFile))) - 1;
    $assetsPath = ($depth > 0) ? str_repeat('../', $depth) . 'assets' : 'assets';

    $output = str_replace(
        ['{{TITLE}}', '{{CONTENT}}', '{{ASSETS_PATH}}'],
        [$title, $html, $assetsPath],
        $template
    );

    file_put_contents($htmlFile, $output);
}

echo "Conversion Complete!\n";

function getMarkdownFiles($dir) {
    $results = [];
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            $results = array_merge($results, getMarkdownFiles($path));
        } elseif (substr($file, -3) === '.md') {
            $results[] = $path;
        }
    }
    return $results;
}

function convertMarkdown($md) {
    // Escaping
    $md = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');

    // Headers
    $md = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $md);
    $md = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $md);
    $md = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $md);

    // Bold
    $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);

    // Code Blocks
    $md = preg_replace_callback('/```(\w+)?\n(.+?)\n```/s', function($m) {
        return '<pre><code>' . $m[2] . '</code></pre>';
    }, $md);

    // Inline Code
    $md = preg_replace('/`(.+?)`/', '<code>$1</code>', $md);

    // Lists (Unordered)
    $md = preg_replace('/^\s*\*\s+(.+)$/m', '<ul><li>$1</li></ul>', $md);
    $md = preg_replace('/<\/ul>\s*<ul>/', '', $md);

    // Links (Convert .md to .html)
    $md = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function($m) {
        $url = $m[2];
        if (substr($url, -3) === '.md') {
            $url = substr($url, 0, -3) . '.html';
        }
        return '<a href="' . $url . '">' . $m[1] . '</a>';
    }, $md);

    // Horizontal Rule
    $md = preg_replace('/^---\s*$/m', '<hr>', $md);

    // Paragraphs
    $lines = explode("\n", $md);
    $output = "";
    $inPara = false;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "") {
            if ($inPara) { $output .= "</p>\n"; $inPara = false; }
            continue;
        }
        if (preg_match('/^<(h1|h2|h3|pre|ul|li|hr|div|header|main|footer)/', $line)) {
            if ($inPara) { $output .= "</p>\n"; $inPara = false; }
            $output .= $line . "\n";
        } else {
            if (!$inPara) { $output .= "<p>"; $inPara = true; }
            $output .= $line . " ";
        }
    }
    if ($inPara) $output .= "</p>";

    return $output;
}
