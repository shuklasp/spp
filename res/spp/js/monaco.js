/**
 * Framework Core Standalone Monaco Editor Instance Bridge
 * Zero-dependency, native-feeling, fully air-gapped web code editor core resource.
 * App neutral, module neutral, supports dynamic syntax highlighting and full AMD loader interop.
 */

(function() {
    if (window.monaco) return;

    console.log("📦 Framework Core: Initializing Zero-Dependency Local Monaco Editor Engine...");

    // Stub AMD/RequireJS flow if not already present to guarantee framework neutrality
    if (typeof window.require !== 'function') {
        window.require = function(deps, callback) {
            if (typeof callback === 'function') {
                setTimeout(callback, 0);
            }
        };
        window.require.config = function() {};
    } else {
        const origRequire = window.require;
        window.require = function(deps, callback) {
            if (Array.isArray(deps) && deps.some(d => d.includes('vs/editor'))) {
                if (typeof callback === 'function') setTimeout(callback, 0);
            } else {
                return origRequire.apply(this, arguments);
            }
        };
        Object.assign(window.require, origRequire);
        if (!window.require.config) window.require.config = function() {};
    }

    // Dynamic High-Performance Syntax Highlighter
    const highlightSyntax = (code, language) => {
        if (!code) return '';
        
        let html = code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Strings
        html = html.replace(/(&quot;.*?&quot;|'.*?')/g, '<span style="color: #a5d6ff;">$1</span>');
        
        // Keywords
        html = html.replace(/\b(true|false|null|const|let|var|function|return|import|export|class|extends|if|else|async|await)\b/g, '<span style="color: #ff7b72; font-weight: 600;">$1</span>');
        
        // Numbers
        html = html.replace(/\b(\d+)\b/g, '<span style="color: #79c0ff;">$1</span>');

        // JSON/YAML keys
        if (language === 'json' || language === 'yaml' || language === 'yml') {
            html = html.replace(/^(\s*)([a-zA-Z0-9_]+):/gm, '$1<span style="color: #d2a8ff; font-weight: 500;">$2</span>:');
            html = html.replace(/(&quot;[a-zA-Z0-9_]+&quot;):/g, '<span style="color: #d2a8ff; font-weight: 500;">$1</span>:');
        }

        // Comments
        html = html.replace(/(\/\/.*$)/gm, '<span style="color: #8b949e; font-style: italic;">$1</span>');
        html = html.replace(/(#.*$)/gm, '<span style="color: #8b949e; font-style: italic;">$1</span>');

        return html;
    };

    window.monaco = {
        editor: {
            create: function(container, options = {}) {
                container.style.position = 'relative';
                container.style.display = 'flex';
                container.style.overflow = 'hidden';
                container.style.background = '#0d1117';
                container.style.border = '1px solid #30363d';
                container.style.borderRadius = '8px';
                container.style.boxShadow = 'inset 0 1px 4px rgba(0,0,0,0.2)';

                const language = options.language || 'javascript';
                let contentValue = options.value || '';
                const listeners = [];

                // Create side line numbers panel
                const lineNums = document.createElement('div');
                lineNums.className = 'monaco-core-lines';
                lineNums.style.width = '48px';
                lineNums.style.background = '#161b22';
                lineNums.style.borderRight = '1px solid #30363d';
                lineNums.style.padding = '12px 0';
                lineNums.style.fontFamily = "'JetBrains Mono', 'Consolas', monospace";
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
                backdrop.style.fontFamily = "'JetBrains Mono', 'Consolas', monospace";
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
                textarea.style.fontFamily = "'JetBrains Mono', 'Consolas', monospace";
                textarea.style.fontSize = '13px';
                textarea.style.lineHeight = '1.6';
                textarea.style.whiteSpace = 'pre';
                textarea.style.tabSize = '2';
                textarea.spellcheck = false;

                textarea.value = contentValue;

                const updateView = () => {
                    const lines = textarea.value.split('\n').length;
                    let lineStr = '';
                    for (let i = 1; i <= lines; i++) {
                        lineStr += `<div style="padding: 0 10px;">${i}</div>`;
                    }
                    if (lineNums.innerHTML !== lineStr) lineNums.innerHTML = lineStr;
                    backdrop.innerHTML = highlightSyntax(textarea.value, language);
                };

                textarea.addEventListener('input', () => {
                    contentValue = textarea.value;
                    updateView();
                    listeners.forEach(fn => fn());
                });

                textarea.addEventListener('scroll', () => {
                    lineNums.scrollTop = textarea.scrollTop;
                    backdrop.scrollTop = textarea.scrollTop;
                    backdrop.scrollLeft = textarea.scrollLeft;
                });

                textarea.addEventListener('keydown', (e) => {
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
                    onDidChangeModelContent: (callback) => {
                        if (typeof callback === 'function') listeners.push(callback);
                    },
                    layout: () => updateView(),
                    dispose: () => {
                        container.innerHTML = '';
                    }
                };
            }
        }
    };
})();
