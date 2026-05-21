/**
 * Lekhni Sovereign Embedded Monaco Code Engine
 * Entirely self-contained zero-dependency local code editing workspace bundle.
 * Provides high-performance multi-language real-time syntax token highlighting.
 */

export const LekhniMonaco = {
    highlightSyntax(code, language) {
        if (!code) return '';
        
        let html = code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        const tokens = [];
        const addToken = (value) => {
            const tokenChar = String.fromCharCode(0xE000 + tokens.length);
            tokens.push(value);
            return tokenChar;
        };

        const lang = (language || 'javascript').toLowerCase();

        // 1. Multi-line Comments
        if (['javascript', 'js', 'typescript', 'ts', 'css', 'php', 'rust', 'go', 'sql'].includes(lang)) {
            html = html.replace(/(\/\*[\s\S]*?\*\/)/g, (_, match) => 
                addToken(`<span style="color: #8b949e; font-style: italic;">${match}</span>`)
            );
        }

        // 2. HTML/XML/Markdown comments
        if (['html', 'xml', 'markdown', 'md', 'php'].includes(lang)) {
            html = html.replace(/(&lt;!--[\s\S]*?--&gt;)/g, (_, match) => 
                addToken(`<span style="color: #8b949e; font-style: italic;">${match}</span>`)
            );
        }

        // 3. Single Line Comments
        if (['javascript', 'js', 'typescript', 'ts', 'php', 'rust', 'go', 'css'].includes(lang)) {
            html = html.replace(/(\/\/.*$)/gm, (_, match) => 
                addToken(`<span style="color: #8b949e; font-style: italic;">${match}</span>`)
            );
        }
        if (['python', 'py', 'ruby', 'rb', 'bash', 'sh', 'yaml', 'yml', 'php'].includes(lang)) {
            html = html.replace(/(#.*$)/gm, (_, match) => 
                addToken(`<span style="color: #8b949e; font-style: italic;">${match}</span>`)
            );
        }
        if (lang === 'sql') {
            html = html.replace(/(--.*$)/gm, (_, match) => 
                addToken(`<span style="color: #8b949e; font-style: italic;">${match}</span>`)
            );
        }

        // 4. Strings
        html = html.replace(/(&quot;.*?&quot;|'.*?')/g, (_, match) => 
            addToken(`<span style="color: #a5d6ff;">${match}</span>`)
        );
        if (['javascript', 'js', 'typescript', 'ts', 'go'].includes(lang)) {
            html = html.replace(/(`[\s\S]*?`)/g, (_, match) => 
                addToken(`<span style="color: #a5d6ff;">${match}</span>`)
            );
        }

        // 5. PHP Tags
        if (lang === 'php') {
            html = html.replace(/(&lt;\?php|\?&gt;)/g, (_, match) => 
                addToken(`<span style="color: #ff7b72; font-weight: bold;">${match}</span>`)
            );
        }

        // 6. Keywords
        const keywords = [
            'const', 'let', 'var', 'function', 'func', 'fn', 'return', 'import', 'export', 
            'class', 'extends', 'if', 'else', 'elif', 'elseif', 'async', 'await', 'switch', 
            'case', 'default', 'break', 'continue', 'for', 'while', 'foreach', 'as', 'in', 
            'try', 'catch', 'finally', 'throw', 'new', 'struct', 'impl', 'interface', 
            'package', 'namespace', 'use', 'public', 'private', 'protected', 'echo', 'print', 
            'global', 'static', 'def', 'from', 'with', 'lambda', 'except', 'raise', 'yield', 
            'assert', 'del', 'pass', 'type', 'select', 'where', 'from', 'insert', 'into', 
            'values', 'update', 'delete', 'create', 'table', 'alter', 'drop', 'and', 'or', 'not'
        ];
        const keywordRegex = new RegExp(`\\b(${keywords.join('|')})\\b`, 'gi');
        html = html.replace(keywordRegex, (_, match) => 
            addToken(`<span style="color: #ff7b72; font-weight: 600;">${match}</span>`)
        );

        const types = [
            'int', 'float', 'string', 'bool', 'boolean', 'number', 'any', 'void', 'nil', 
            'null', 'true', 'false', 'undefined', 'self', 'this', 'None'
        ];
        const typesRegex = new RegExp(`\\b(${types.join('|')})\\b`, 'gi');
        html = html.replace(typesRegex, (_, match) => 
            addToken(`<span style="color: #79c0ff;">${match}</span>`)
        );

        // 7. Numbers
        html = html.replace(/\b(\d+)\b/g, (_, match) => 
            addToken(`<span style="color: #79c0ff;">${match}</span>`)
        );

        // 8. Properties / Selectors / Keys
        if (['json', 'yaml', 'yml'].includes(lang)) {
            html = html.replace(/^(\s*)([a-zA-Z0-9_\-]+):/gm, (_, space, prop) => 
                space + addToken(`<span style="color: #d2a8ff; font-weight: 500;">${prop}</span>`) + ':'
            );
            html = html.replace(/(&quot;[a-zA-Z0-9_\-]+&quot;):/g, (_, prop) => 
                addToken(`<span style="color: #d2a8ff; font-weight: 500;">${prop}</span>`) + ':'
            );
        }
        if (lang === 'css') {
            html = html.replace(/^(\s*)([a-zA-Z0-9_\-\.\#\:\s,>+]+)\s*\{/gm, (_, space, selector) => 
                space + addToken(`<span style="color: #7ee787; font-weight: bold;">${selector}</span>`) + ' {'
            );
            html = html.replace(/([a-zA-Z0-9\-]+)\s*:/g, (_, prop) => 
                addToken(`<span style="color: #ff7b72;">${prop}</span>`) + ':'
            );
            html = html.replace(/(#[0-9a-fA-F]{3,8})\b/g, (_, match) => 
                addToken(`<span style="color: #79c0ff;">${match}</span>`)
            );
        }

        // 9. HTML/XML Tags and Attributes
        if (['html', 'xml', 'php'].includes(lang)) {
            html = html.replace(/(&lt;\/?[a-zA-Z0-9\-:]+)/g, (_, tag) => 
                addToken(`<span style="color: #7ee787; font-weight: 500;">${tag}</span>`)
            );
            html = html.replace(/\b([a-zA-Z0-9\-]+)=/g, (_, attr) => 
                addToken(`<span style="color: #d2a8ff;">${attr}</span>`) + '='
            );
        }

        // 10. Markdown Headers & Accents
        if (['markdown', 'md'].includes(lang)) {
            html = html.replace(/^(#+\s+.*)$/gm, (_, match) => 
                addToken(`<span style="color: #7ee787; font-weight: bold;">${match}</span>`)
            );
            html = html.replace(/^(\s*[\*\-\+]\s+|\s*\d+\.\s+)/gm, (_, match) => 
                addToken(`<span style="color: #ff7b72; font-weight: bold;">${match}</span>`)
            );
            html = html.replace(/(\*\*.*?\*\*)/g, (_, match) => 
                addToken(`<span style="color: #ff7b72; font-weight: bold;">${match}</span>`)
            );
            html = html.replace(/(\*.*?\*|_.*?_)/g, (_, match) => 
                addToken(`<span style="color: #d2a8ff; font-style: italic;">${match}</span>`)
            );
        }

        // 11. Reconstruction
        for (let i = tokens.length - 1; i >= 0; i--) {
            const tokenChar = String.fromCharCode(0xE000 + i);
            html = html.replace(tokenChar, tokens[i]);
        }

        return html;
    },

    stripSyntaxHighlighting(html) {
        if (!html) return '';
        let clean = html;
        clean = clean
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/<span[^>]*>/gi, '')
            .replace(/<\/span>/gi, '')
            .replace(/<div[^>]*>/gi, '')
            .replace(/<\/div>/gi, '\n')
            .replace(/<br\s*\/?>/gi, '\n');
        return clean;
    },

    create(container, options = {}) {
        container.style.position = 'relative';
        container.style.display = 'flex';
        container.style.overflow = 'hidden';
        container.style.background = '#0d1117';
        container.style.border = '1px solid #30363d';
        container.style.borderRadius = '8px';
        container.style.boxShadow = 'inset 0 1px 4px rgba(0,0,0,0.2)';
        container.style.minHeight = options.height || '150px';
        container.style.fontFamily = "'JetBrains Mono', 'Consolas', monospace";

        const language = options.language || 'javascript';
        let contentValue = options.value || '';
        const listeners = [];

        // Line numbers sidebar
        const lineNums = document.createElement('div');
        lineNums.className = 'lekhni-monaco-lines';
        lineNums.style.width = '42px';
        lineNums.style.background = '#161b22';
        lineNums.style.borderRight = '1px solid #30363d';
        lineNums.style.padding = '12px 0';
        lineNums.style.fontSize = '13px';
        lineNums.style.lineHeight = '1.6';
        lineNums.style.color = '#6e7681';
        lineNums.style.textAlign = 'right';
        lineNums.style.userSelect = 'none';
        lineNums.style.overflow = 'hidden';
        lineNums.style.flexShrink = '0';

        const editorWrap = document.createElement('div');
        editorWrap.style.flex = '1';
        editorWrap.style.position = 'relative';
        editorWrap.style.overflow = 'hidden';

        const backdrop = document.createElement('pre');
        backdrop.style.position = 'absolute';
        backdrop.style.inset = '0';
        backdrop.style.margin = '0';
        backdrop.style.padding = '12px 16px';
        backdrop.style.pointerEvents = 'none';
        backdrop.style.overflow = 'hidden';
        backdrop.style.fontSize = '13px';
        backdrop.style.lineHeight = '1.6';
        backdrop.style.color = '#c9d1d9';
        backdrop.style.whiteSpace = 'pre';
        backdrop.style.tabSize = '2';

        const textarea = document.createElement('textarea');
        textarea.style.position = 'absolute';
        textarea.style.inset = '0';
        textarea.style.width = '100%';
        textarea.style.height = '100%';
        textarea.style.margin = '0';
        textarea.style.padding = '12px 16px';
        textarea.style.border = 'none';
        textarea.style.outline = 'none';
        textarea.style.resize = 'none';
        textarea.style.background = 'transparent';
        textarea.style.color = 'transparent';
        textarea.style.caretColor = '#58a6ff';
        textarea.style.fontSize = '13px';
        textarea.style.lineHeight = '1.6';
        textarea.style.fontFamily = 'inherit';
        textarea.style.whiteSpace = 'pre';
        textarea.style.tabSize = '2';
        textarea.spellcheck = false;

        textarea.value = contentValue;

        // --- Autocomplete Setup ---
        const autocompleteWrap = document.createElement('div');
        autocompleteWrap.style.position = 'absolute';
        autocompleteWrap.style.display = 'none';
        autocompleteWrap.style.background = '#161b22';
        autocompleteWrap.style.border = '1px solid #30363d';
        autocompleteWrap.style.borderRadius = '6px';
        autocompleteWrap.style.boxShadow = '0 8px 24px rgba(0,0,0,0.5)';
        autocompleteWrap.style.zIndex = '100';
        autocompleteWrap.style.maxHeight = '150px';
        autocompleteWrap.style.overflowY = 'auto';
        autocompleteWrap.style.color = '#c9d1d9';
        autocompleteWrap.style.fontSize = '12px';
        autocompleteWrap.style.minWidth = '120px';

        const keywords = [
            'const', 'let', 'var', 'function', 'return', 'import', 'export', 
            'class', 'extends', 'if', 'else', 'async', 'await', 'switch', 
            'case', 'default', 'break', 'continue', 'for', 'while', 'try', 
            'catch', 'finally', 'throw', 'new', 'public', 'private', 'static',
            'int', 'float', 'string', 'boolean', 'null', 'true', 'false', 'undefined'
        ];
        
        let autocompleteItems = [];
        let selectedIndex = 0;
        let currentWordStart = 0;
        let currentWordEnd = 0;

        const mirrorDiv = document.createElement('div');
        mirrorDiv.style.position = 'absolute';
        mirrorDiv.style.top = '0'; mirrorDiv.style.left = '0';
        mirrorDiv.style.visibility = 'hidden';
        mirrorDiv.style.whiteSpace = 'pre-wrap';
        mirrorDiv.style.wordWrap = 'break-word';
        mirrorDiv.style.padding = '12px 16px';
        mirrorDiv.style.fontSize = '13px';
        mirrorDiv.style.lineHeight = '1.6';
        mirrorDiv.style.fontFamily = "'JetBrains Mono', 'Consolas', monospace";
        editorWrap.appendChild(mirrorDiv);
        editorWrap.appendChild(autocompleteWrap);

        const updateView = () => {
            const lines = textarea.value.split('\n').length;
            let lineStr = '';
            for (let i = 1; i <= lines; i++) {
                lineStr += `<div style="padding: 0 8px;">${i}</div>`;
            }
            if (lineNums.innerHTML !== lineStr) lineNums.innerHTML = lineStr;
            backdrop.innerHTML = LekhniMonaco.highlightSyntax(textarea.value, language);
        };

        textarea.addEventListener('input', () => {
            contentValue = textarea.value;
            updateView();
            listeners.forEach(fn => fn(contentValue));

            // Autocomplete logic
            const pos = textarea.selectionStart;
            const textBefore = contentValue.substring(0, pos);
            const match = textBefore.match(/([a-zA-Z_]\w*)$/);
            
            if (match) {
                const currentWord = match[1];
                currentWordStart = pos - currentWord.length;
                currentWordEnd = pos;
                
                autocompleteItems = keywords.filter(k => k.toLowerCase().startsWith(currentWord.toLowerCase()) && k !== currentWord);
                
                if (autocompleteItems.length > 0) {
                    // Update mirror to find caret pos
                    mirrorDiv.style.width = textarea.clientWidth + 'px';
                    const textUpToWord = contentValue.substring(0, currentWordStart);
                    mirrorDiv.textContent = textUpToWord;
                    const span = document.createElement('span');
                    span.textContent = currentWord;
                    mirrorDiv.appendChild(span);
                    
                    const spanRect = span.getBoundingClientRect();
                    const wrapRect = editorWrap.getBoundingClientRect();
                    
                    autocompleteWrap.style.left = (spanRect.left - wrapRect.left) + 'px';
                    autocompleteWrap.style.top = (spanRect.bottom - wrapRect.top - textarea.scrollTop + 4) + 'px';
                    
                    renderAutocomplete();
                    autocompleteWrap.style.display = 'block';
                } else {
                    autocompleteWrap.style.display = 'none';
                }
            } else {
                autocompleteWrap.style.display = 'none';
            }
        });

        const renderAutocomplete = () => {
            autocompleteWrap.innerHTML = '';
            autocompleteItems.forEach((item, idx) => {
                const el = document.createElement('div');
                el.style.padding = '4px 12px';
                el.style.cursor = 'pointer';
                el.style.background = idx === selectedIndex ? '#1f6feb' : 'transparent';
                el.style.color = idx === selectedIndex ? '#ffffff' : '#c9d1d9';
                el.textContent = item;
                el.onmousedown = (e) => {
                    e.preventDefault();
                    applyAutocomplete(item);
                };
                autocompleteWrap.appendChild(el);
            });
        };

        const applyAutocomplete = (word) => {
            const before = textarea.value.substring(0, currentWordStart);
            const after = textarea.value.substring(currentWordEnd);
            textarea.value = before + word + after;
            textarea.selectionStart = textarea.selectionEnd = currentWordStart + word.length;
            autocompleteWrap.style.display = 'none';
            textarea.dispatchEvent(new Event('input'));
            textarea.focus();
        };

        textarea.addEventListener('scroll', () => {
            lineNums.scrollTop = textarea.scrollTop;
            backdrop.scrollTop = textarea.scrollTop;
            backdrop.scrollLeft = textarea.scrollLeft;
        });

        textarea.addEventListener('keydown', (e) => {
            if (autocompleteWrap.style.display === 'block') {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = (selectedIndex + 1) % autocompleteItems.length;
                    renderAutocomplete();
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = (selectedIndex - 1 + autocompleteItems.length) % autocompleteItems.length;
                    renderAutocomplete();
                    return;
                }
                if (e.key === 'Enter' || e.key === 'Tab') {
                    e.preventDefault();
                    applyAutocomplete(autocompleteItems[selectedIndex]);
                    return;
                }
                if (e.key === 'Escape') {
                    autocompleteWrap.style.display = 'none';
                    return;
                }
            }

            if (e.key === 'Tab') {
                e.preventDefault();
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                textarea.value = textarea.value.substring(0, start) + '  ' + textarea.value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + 2;
                textarea.dispatchEvent(new Event('input'));
            }
        });

        editorWrap.appendChild(backdrop);
        editorWrap.appendChild(textarea);
        container.appendChild(lineNums);
        container.appendChild(editorWrap);

        updateView();

        return {
            getValue: () => textarea.value,
            setValue: (val) => {
                contentValue = val;
                textarea.value = val;
                updateView();
            },
            onDidChangeContent: (callback) => {
                if (typeof callback === 'function') listeners.push(callback);
            },
            focus: () => textarea.focus(),
            dispose: () => {
                container.innerHTML = '';
            }
        };
    }
};
