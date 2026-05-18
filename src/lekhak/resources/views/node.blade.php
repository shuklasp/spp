<!DOCTYPE html>
@load_node
<html>
<head>
    <title>{{ $node->title ?? 'Node' }} | Lekhak CMS</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a 70%);
            color: #cbd5e1;
            padding: 3rem 1.5rem;
            margin: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(12px);
            padding: 3rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .btn-back {
            color: #818cf8;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2rem;
            transition: color 0.15s;
        }

        .btn-back:hover {
            color: #a5b4fc;
        }

        h1.article-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.8rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 1rem 0;
            line-height: 1.2;
            background: linear-gradient(135deg, #ffffff 40%, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .metadata {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 2.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .metadata-badge {
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        /* Lekhni Core WYSIWYG Styling rules */
        .lekhni-article-body {
            line-height: 1.8;
            font-size: 1.1rem;
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

        /* Smart Formula Grid Styles */
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

        /* Interactive Task Checklist */
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

        /* Glassmorphic PDF Embed Frames */
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

        /* Flex Gallery Grid */
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
</head>
<body>
    <div class="container">
        <a href="{{ $base_url }}" class="btn-back">&larr; Back to Workspace</a>
        <h1 class="article-title">{{ $node->title ?? 'Untitled Node' }}</h1>
        <div class="metadata">
            <span class="metadata-badge">{{ $node->bundle ?? 'Article' }}</span>
            <span>Published on {{ $node->created ?? 'Unknown Date' }}</span>
        </div>
        
        <div class="lekhni-article-body">
            {!! $node->body ?? '<p style="color:#64748b; font-style:italic;">No content specified.</p>' !!}
        </div>
    </div>

    <!-- Reader-Mode Client Interactive Binders -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // 1. PDF Blocks Resizing Interaction in Read Mode
            document.querySelectorAll('.lekhni-pdf-block-wrapper').forEach(wrapper => {
                const iframe = wrapper.querySelector('.pdf-embedded-iframe');
                const widthSlider = wrapper.querySelector('.pdf-width-slider');
                const widthVal = wrapper.querySelector('.pdf-width-val');
                const heightSlider = wrapper.querySelector('.pdf-height-slider');
                const heightVal = wrapper.querySelector('.pdf-height-val');
                const deleteBtn = wrapper.querySelector('.btn-pdf-delete');

                // Readers don't delete blocks, only resize them
                if (deleteBtn) {
                    deleteBtn.style.display = 'none';
                }

                if (widthSlider && iframe) {
                    const currentWidth = parseInt(iframe.style.width) || 794;
                    widthSlider.value = currentWidth;
                    if (widthVal) widthVal.innerText = `${currentWidth}px`;

                    widthSlider.addEventListener('input', (e) => {
                        const val = e.target.value;
                        iframe.style.width = `${val}px`;
                        if (widthVal) widthVal.innerText = `${val}px`;
                    });
                }

                if (heightSlider && iframe) {
                    const currentHeight = parseInt(iframe.style.height) || 500;
                    heightSlider.value = currentHeight;
                    if (heightVal) heightVal.innerText = `${currentHeight}px`;

                    heightSlider.addEventListener('input', (e) => {
                        const val = e.target.value;
                        iframe.style.height = `${val}px`;
                        if (heightVal) heightVal.innerText = `${val}px`;
                    });
                }
            });

            // 2. Checklist / Tasks Interaction in Read Mode
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

            // 3. Spreadsheet smart cell interaction in Read Mode
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
                                const range = cleanForm.match(/\(([^)]+)\)/)?.[1];
                                if (range) evaluated = evaluateSum(range, cellMap);
                            } else if (cleanForm.startsWith('AVERAGE(')) {
                                const range = cleanForm.match(/\(([^)]+)\)/)?.[1];
                                if (range) evaluated = evaluateAverage(range, cellMap);
                            } else if (cleanForm.startsWith('PRODUCT(')) {
                                const range = cleanForm.match(/\(([^)]+)\)/)?.[1];
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
</body>
</html>
