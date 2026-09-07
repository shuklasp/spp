<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $book['title'] }} — Complete Documentation Book</title>
    <style>
        :root {
            --book-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --code-font: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            --text-color: #1a202c;
            --muted-color: #718096;
            --border-color: #e2e8f0;
            --brand-color: #3182ce;
        }

        body {
            font-family: var(--book-font);
            color: var(--text-color);
            background: #f7fafc;
            margin: 0;
            padding: 0;
            line-height: 1.65;
            font-size: 15px;
        }

        /* Floating Toolbar */
        .book-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #2d3748;
            color: #ffffff;
            padding: 0.75rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .book-toolbar a, .book-toolbar button {
            color: #ffffff;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 6px;
            padding: 0.4rem 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .book-toolbar button.btn-print {
            background: #3182ce;
            border-color: #2b6cb0;
        }
        .book-toolbar a:hover, .book-toolbar button:hover {
            background: rgba(255,255,255,0.25);
        }

        /* Book Container */
        .book-wrapper {
            max-width: 850px;
            margin: 4.5rem auto 3rem auto;
            background: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            border-radius: 4px;
            padding: 4rem 4.5rem;
            box-sizing: border-box;
        }

        /* Cover Page */
        .book-cover {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            min-height: 680px;
            page-break-after: always;
            break-after: page;
            border-bottom: 2px dashed var(--border-color);
            margin-bottom: 3rem;
            padding-bottom: 3rem;
        }
        .book-cover-logo {
            max-width: 140px;
            max-height: 140px;
            margin-bottom: 2rem;
            object-fit: contain;
        }
        .book-cover-title {
            font-size: 2.8rem;
            font-weight: 800;
            color: #1a202c;
            margin: 0 0 0.75rem 0;
            line-height: 1.2;
        }
        .book-cover-motto {
            font-size: 1.25rem;
            color: var(--muted-color);
            max-width: 580px;
            margin: 0 0 2.5rem 0;
        }
        .book-cover-badge {
            background: #ebf8ff;
            color: #2b6cb0;
            border: 1px solid #bee3f8;
            font-size: 0.95rem;
            font-weight: 700;
            padding: 0.35rem 0.9rem;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 3rem;
        }
        .book-cover-meta {
            font-size: 0.88rem;
            color: var(--muted-color);
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
            width: 100%;
        }

        /* Table of Contents */
        .book-toc {
            page-break-after: always;
            break-after: page;
            margin-bottom: 3rem;
            border-bottom: 2px dashed var(--border-color);
            padding-bottom: 3rem;
        }
        .book-toc h2 {
            font-size: 1.75rem;
            border-bottom: 2px solid #1a202c;
            padding-bottom: 0.5rem;
            margin-top: 0;
        }
        .book-toc ul {
            list-style: none;
            padding-left: 0;
        }
        .book-toc li {
            margin-bottom: 0.6rem;
        }
        .book-toc a {
            color: var(--text-color);
            text-decoration: none;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dotted var(--border-color);
            padding-bottom: 0.15rem;
        }
        .book-toc a:hover {
            color: var(--brand-color);
        }
        .book-toc .sub-toc {
            padding-left: 1.5rem;
            margin-top: 0.3rem;
            margin-bottom: 0.5rem;
        }
        .book-toc .sub-toc a {
            font-size: 0.88rem;
            color: var(--muted-color);
        }

        /* Chapters */
        .book-chapter {
            page-break-before: always;
            break-before: page;
            margin-bottom: 3.5rem;
            padding-top: 1rem;
        }
        .book-chapter-section-badge {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--brand-color);
            font-weight: 700;
        }
        .book-chapter-title {
            font-size: 2.1rem;
            font-weight: 800;
            margin: 0.25rem 0 1.5rem 0;
            color: #1a202c;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }

        /* Typography & Code */
        pre {
            background: #f7fafc;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 1rem;
            overflow-x: auto;
            font-family: var(--code-font);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        code {
            font-family: var(--code-font);
            background: #edf2f7;
            padding: 0.15rem 0.35rem;
            border-radius: 4px;
            font-size: 0.88em;
        }
        pre code {
            background: none;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.9rem;
        }
        th, td {
            border: 1px solid var(--border-color);
            padding: 0.6rem 0.85rem;
            text-align: left;
        }
        th {
            background: #f7fafc;
            font-weight: 700;
        }
        blockquote {
            border-left: 4px solid var(--brand-color);
            margin: 1.5rem 0;
            padding: 0.5rem 1rem;
            background: #ebf8ff;
            color: #2b6cb0;
        }

        /* Callouts */
        .sppdocs-callout {
            border-left: 4px solid #3182ce;
            background: #ebf8ff;
            border-radius: 4px;
            padding: 0.85rem 1.15rem;
            margin: 1.5rem 0;
        }
        .sppdocs-callout-TIP { border-color: #38a169; background: #f0fff4; color: #22543d; }
        .sppdocs-callout-WARNING { border-color: #dd6b20; background: #fffaf0; color: #7b341e; }
        .sppdocs-callout-CAUTION { border-color: #e53e3e; background: #fff5f5; color: #742a2a; }
        .callout-title { font-weight: 800; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.25rem; }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff;
                font-size: 11pt;
            }
            .book-toolbar {
                display: none !important;
            }
            .book-wrapper {
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .book-cover {
                min-height: 90vh;
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            .book-toc {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            .book-chapter {
                break-inside: avoid-page;
            }
            h1, h2, h3 {
                break-after: avoid;
            }
            pre, table, blockquote {
                break-inside: avoid;
            }
            @page {
                size: A4;
                margin: 20mm 15mm 20mm 15mm;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Toolbar -->
    <div class="book-toolbar">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="{{ \SPP\App::getBaseUrl() }}/project/{{ $project_id }}">← Back to Documentation</a>
            <span style="font-weight: 700;">📕 {{ $book['title'] }} (v{{ $book['version'] }})</span>
        </div>
        <div style="display: flex; gap: 0.6rem;">
            <a href="{{ \SPP\App::getBaseUrl() }}/export/markdown?project={{ urlencode($project_id) }}&version={{ urlencode($version) }}">
                📥 Export Markdown (.md)
            </a>
            <button type="button" class="btn-print" onclick="window.print()">
                🖨️ Print / Save to PDF
            </button>
        </div>
    </div>

    <!-- Printable Book Container -->
    <div class="book-wrapper">
        <!-- 1. Cover Page -->
        <div class="book-cover">
            @if(!empty($book['logo']))
                <img src="{{ $book['logo'] }}" alt="Logo" class="book-cover-logo">
            @endif
            <h1 class="book-cover-title">{{ $book['title'] }}</h1>
            <p class="book-cover-motto">{{ $book['motto'] }}</p>
            <div class="book-cover-badge">Version {{ $book['version'] }}</div>

            <div class="book-cover-meta">
                <p style="margin: 0.25rem 0;">Published with <strong>SPPDocs CMS</strong></p>
                <p style="margin: 0.25rem 0;">Compiled on {{ $book['generated_at'] }} · {{ $book['total_chapters'] }} Complete Chapters</p>
            </div>
        </div>

        <!-- 2. Table of Contents -->
        <div class="book-toc">
            <h2>Table of Contents</h2>
            <ul>
                @foreach($book['toc'] as $item)
                    <li>
                        <a href="#chapter-{{ $item['slug'] }}">
                            <span><strong>Chapter {{ $item['number'] }}:</strong> {{ $item['title'] }}</span>
                            <span style="color: var(--muted-color); font-size: 0.85rem;">{{ $item['section'] }}</span>
                        </a>
                        @if(!empty($item['headings']))
                            <ul class="sub-toc">
                                @foreach($item['headings'] as $sub)
                                    <li>
                                        <a href="#{{ $sub['anchor'] }}">
                                            <span>{{ $sub['title'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- 3. Chapters -->
        @foreach($book['chapters'] as $chapter)
            <div class="book-chapter" id="chapter-{{ $chapter['slug'] }}">
                <div class="book-chapter-section-badge">{{ $chapter['section'] }} · CHAPTER {{ $chapter['number'] }}</div>
                <h2 class="book-chapter-title">{{ $chapter['title'] }}</h2>
                <div class="book-chapter-content">
                    {!! $chapter['html'] !!}
                </div>
            </div>
        @endforeach
    </div>

    @if(!empty($auto_print))
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        </script>
    @endif
</body>
</html>