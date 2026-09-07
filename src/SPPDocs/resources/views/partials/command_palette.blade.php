@php
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    $effectiveProjectId = !empty($project_id) ? $project_id : ($_SESSION['sppdocs_current_project'] ?? 'demo-app');
    $projParam = '?projectId=' . urlencode($effectiveProjectId);
@endphp
<div id="sppdocs-cmd-palette" class="sppdocs-cmd-backdrop" data-project-id="{{ $effectiveProjectId }}" data-base-url="{{ \SPP\App::getBaseUrl() }}" style="display: none;" hx-boost="false" onclick="if(event.target===this) closeCommandPalette()">
    <div class="sppdocs-cmd-modal" onclick="event.stopPropagation()">
        <div class="sppdocs-cmd-tabs">
            <button type="button" class="sppdocs-cmd-tab-btn active" id="sppdocs-tab-nav" onclick="switchCmdMode('nav')">
                <span>⚡</span> Commands & Instant Docs
            </button>
            <button type="button" class="sppdocs-cmd-tab-btn" id="sppdocs-tab-ai" onclick="switchCmdMode('ai')">
                <span>🤖</span> Ask AI Assistant (RAG)
            </button>
        </div>

        <div class="sppdocs-cmd-header">
            <span class="sppdocs-cmd-icon" id="sppdocs-cmd-icon-indicator">🔍</span>
            <input type="text" id="sppdocs-cmd-input" class="sppdocs-cmd-input" placeholder="Type a command or jump to... (Press Esc to close)" autocomplete="off">
            <kbd class="sppdocs-cmd-kbd" id="sppdocs-cmd-enter-kbd">ESC</kbd>
        </div>

        <div id="sppdocs-cmd-instant-results"></div>

        <div id="sppdocs-cmd-list" class="sppdocs-cmd-list">
            <div class="sppdocs-cmd-section">Navigation & Quick Actions (Project: {{ $effectiveProjectId }})</div>

            <a href="{{ \SPP\App::url('issues/board') }}{{ $projParam }}" class="sppdocs-cmd-item" data-keyword="board kanban tasks sprint" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>📋</span>
                    <span>Go to Kanban Board</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">B</kbd>
            </a>

            <a href="{{ \SPP\App::url('issues') }}{{ $projParam }}" class="sppdocs-cmd-item" data-keyword="issues list tracker bugs" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>📝</span>
                    <span>View All Issues</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">L</kbd>
            </a>

            <a href="{{ \SPP\App::url('issues') }}{{ $projParam }}&new=1" class="sppdocs-cmd-item" data-keyword="create new issue bug task" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>➕</span>
                    <span>Create New Issue</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">C</kbd>
            </a>

            <a href="{{ \SPP\App::url('milestones') }}{{ $projParam }}" class="sppdocs-cmd-item" data-keyword="milestones sprints roadmap" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>🏁</span>
                    <span>View Milestones & Sprints</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">M</kbd>
            </a>

            <a href="{{ \SPP\App::url('lms') }}{{ $projParam }}" class="sppdocs-cmd-item" data-keyword="academy lms courses learn certifications masterclass" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>🎓</span>
                    <span>Academy &amp; Learning Courses</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">K</kbd>
            </a>

            <a href="{{ \SPP\App::url('lms/dashboard') }}{{ $projParam }}" class="sppdocs-cmd-item" data-keyword="my learning dashboard progress certificates lms" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>🎯</span>
                    <span>My Learning &amp; Certificates</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">Y</kbd>
            </a>

            <a href="{{ \SPP\App::url('admin/lms') }}?project={{ urlencode($effectiveProjectId) }}" class="sppdocs-cmd-item" data-keyword="course studio lms authoring teacher admin" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>🛠️</span>
                    <span>LMS Course Studio</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">S</kbd>
            </a>

            <a href="{{ \SPP\App::url('project/' . urlencode($effectiveProjectId)) }}" class="sppdocs-cmd-item" data-keyword="docs documentation wiki" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>📖</span>
                    <span>Live Documentation</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">D</kbd>
            </a>

            <a href="{{ \SPP\App::url('admin/project/' . urlencode($effectiveProjectId)) }}" class="sppdocs-cmd-item" data-keyword="admin settings project manage config" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>⚙️</span>
                    <span>Project Administration</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">A</kbd>
            </a>

            <a href="{{ \SPP\App::url('/') }}" class="sppdocs-cmd-item" data-keyword="portal projects home hub" hx-boost="false">
                <div class="sppdocs-cmd-item-left">
                    <span>🚀</span>
                    <span>Projects Portal Home</span>
                </div>
                <kbd class="sppdocs-cmd-kbd">P</kbd>
            </a>
        </div>

        <div id="sppdocs-cmd-ai-container" style="display: none;"></div>

        <template id="sppdocs-cmd-ai-loading-tpl">
            <div class="sppdocs-ai-loading">
                <div class="sppdocs-ai-spinner"></div>
                <p>Synthesizing verified documentation passages...</p>
            </div>
        </template>

        <template id="sppdocs-cmd-ai-error-tpl">
            <div class="sppdocs-search-empty">
                <p>Error connecting to search endpoint.</p>
            </div>
        </template>
    </div>
</div>

<script>
(function() {
    'use strict';

    function getPalette() { return document.getElementById('sppdocs-cmd-palette'); }
    function getInput() { return document.getElementById('sppdocs-cmd-input'); }
    function getList() { return document.getElementById('sppdocs-cmd-list'); }
    function getInstantBox() { return document.getElementById('sppdocs-cmd-instant-results'); }
    function getAiContainer() { return document.getElementById('sppdocs-cmd-ai-container'); }
    function getIconIndicator() { return document.getElementById('sppdocs-cmd-icon-indicator'); }
    function getTabNav() { return document.getElementById('sppdocs-cmd-tab-nav'); }
    function getTabAi() { return document.getElementById('sppdocs-cmd-tab-ai'); }
    function getEnterKbd() { return document.getElementById('sppdocs-cmd-enter-kbd'); }

    let currentMode = 'nav'; // 'nav' or 'ai'
    let selectedIndex = -1;
    let instantDebounceTimer = null;

    function getBaseUrl() {
        const p = getPalette();
        if (p && p.dataset.baseUrl) return p.dataset.baseUrl;
        return '';
    }

    function getCurrentProjectId() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('projectId')) return params.get('projectId');
        if (params.get('project')) return params.get('project');

        const pathMatch = window.location.pathname.match(/\/(?:admin\/)?project\/([^\/\?]+)/);
        if (pathMatch && pathMatch[1]) return decodeURIComponent(pathMatch[1]);

        const p = getPalette();
        if (p && p.dataset.projectId) return p.dataset.projectId;

        return 'demo-app';
    }

    window.switchCmdMode = function(mode) {
        currentMode = mode;
        const tabNav = getTabNav();
        const tabAi = getTabAi();
        const iconIndicator = getIconIndicator();
        const input = getInput();
        const list = getList();
        const instantBox = getInstantBox();
        const aiContainer = getAiContainer();
        const enterKbd = getEnterKbd();
        const proj = getCurrentProjectId();

        if (mode === 'ai') {
            if (tabAi) tabAi.classList.add('active');
            if (tabNav) tabNav.classList.remove('active');
            if (iconIndicator) iconIndicator.textContent = '🤖';
            if (input) input.placeholder = "Ask AI anything about " + (proj || 'this project') + "... (Press Enter)";
            if (list) list.style.display = 'none';
            if (instantBox) instantBox.innerHTML = '';
            if (aiContainer) aiContainer.style.display = 'block';
            if (enterKbd) enterKbd.textContent = 'ENTER ⏎';
        } else {
            if (tabNav) tabNav.classList.add('active');
            if (tabAi) tabAi.classList.remove('active');
            if (iconIndicator) iconIndicator.textContent = '🔍';
            if (input) input.placeholder = "Type a command or jump to... (Press Esc to close)";
            if (list) list.style.display = 'block';
            if (aiContainer) aiContainer.style.display = 'none';
            if (enterKbd) enterKbd.textContent = 'ESC';
            if (input) filterItems(input.value);
        }
        if (input) {
            input.focus();
        }
    };

    window.openCommandPalette = function(preferredMode) {
        const palette = getPalette();
        const input = getInput();
        if (!palette) return;

        palette.style.display = 'flex';
        if (input) {
            input.value = '';
            setTimeout(function() {
                input.focus();
                input.select();
            }, 10);
        }
        if (preferredMode) {
            window.switchCmdMode(preferredMode);
        } else {
            window.switchCmdMode('nav');
        }
    };

    window.closeCommandPalette = function() {
        const palette = getPalette();
        if (palette) {
            palette.style.display = 'none';
        }
        selectedIndex = -1;
    };

    function getVisibleItems() {
        const list = getList();
        if (!list) return [];
        return Array.from(list.querySelectorAll('.sppdocs-cmd-item')).filter(el => el.style.display !== 'none');
    }

    function updateHighlight() {
        const items = getVisibleItems();
        items.forEach((item, idx) => {
            if (idx === selectedIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }

    function fetchInstantResults(q) {
        const proj = getCurrentProjectId();
        if (!proj || q.length < 2) {
            const instantBox = getInstantBox();
            if (instantBox) instantBox.innerHTML = '';
            return;
        }
        clearTimeout(instantDebounceTimer);
        instantDebounceTimer = setTimeout(() => {
            fetch(getBaseUrl() + '/api/search/instant?project=' + encodeURIComponent(proj) + '&q=' + encodeURIComponent(q))
                .then(r => r.text())
                .then(html => {
                    if (currentMode === 'nav') {
                        const instantBox = getInstantBox();
                        if (instantBox) instantBox.innerHTML = html;
                    }
                })
                .catch(() => {});
        }, 200);
    }

    function executeAiAsk(q) {
        if (!q.trim()) return;
        const proj = getCurrentProjectId();
        const aiContainer = getAiContainer();
        const loadingTpl = document.getElementById('sppdocs-cmd-ai-loading-tpl');
        const errorTpl = document.getElementById('sppdocs-cmd-ai-error-tpl');

        if (aiContainer && loadingTpl) {
            aiContainer.innerHTML = loadingTpl.innerHTML;
        }
        
        fetch(getBaseUrl() + '/api/search/ask?project=' + encodeURIComponent(proj) + '&q=' + encodeURIComponent(q))
            .then(r => r.text())
            .then(html => {
                const curAi = getAiContainer();
                if (curAi) curAi.innerHTML = html;
            })
            .catch(err => {
                const curAi = getAiContainer();
                if (curAi && errorTpl) curAi.innerHTML = errorTpl.innerHTML;
            });
    }

    function filterItems(q) {
        q = (q || '').toLowerCase().trim();
        const list = getList();
        if (!list) return;
        const items = list.querySelectorAll('.sppdocs-cmd-item');
        items.forEach(item => {
            const text = (item.textContent + ' ' + (item.dataset.keyword || '')).toLowerCase();
            item.style.display = (!q || text.includes(q)) ? 'flex' : 'none';
        });
        selectedIndex = 0;
        updateHighlight();

        if (q.length >= 2) {
            fetchInstantResults(q);
        } else {
            const instantBox = getInstantBox();
            if (instantBox) instantBox.innerHTML = '';
        }
    }

    function isKeyK(e) {
        if (!e) return false;
        if (e.key && (e.key.toLowerCase() === 'k' || e.key === 'K')) return true;
        if (e.code === 'KeyK') return true;
        if (e.keyCode === 75 || e.which === 75) return true;
        return false;
    }

    // Capture-Phase Hotkey Handler: Intercepts before browser default actions
    function handleKeyDown(e) {
        const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;

        // 1. Omnibox Buster: Intercept Ctrl+K / Cmd+K at Capture Level
        if (isCtrlOrCmd && isKeyK(e)) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }

            const p = getPalette();
            if (p && p.style.display === 'flex') {
                window.closeCommandPalette();
            } else {
                window.openCommandPalette('nav');
            }
            return false;
        }

        const palette = getPalette();
        const isPaletteOpen = palette && palette.style.display === 'flex';

        // 2. Escape closes palette
        if (e.key === 'Escape' && isPaletteOpen) {
            e.preventDefault();
            e.stopPropagation();
            window.closeCommandPalette();
            return false;
        }

        // 3. Navigation inside palette input
        const input = getInput();
        if (isPaletteOpen && e.target === input) {
            if (currentMode === 'ai') {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    executeAiAsk(input.value);
                }
                return;
            }

            const items = getVisibleItems();
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (items.length > 0) {
                    selectedIndex = (selectedIndex + 1) % items.length;
                    updateHighlight();
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (items.length > 0) {
                    selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                    updateHighlight();
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (items[selectedIndex]) {
                    const href = items[selectedIndex].getAttribute('href');
                    if (href && !href.startsWith('#')) {
                        window.closeCommandPalette();
                        window.location.href = href;
                    } else {
                        items[selectedIndex].click();
                    }
                }
            }
        }
    }

    function handleKeyUp(e) {
        const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;
        if (isCtrlOrCmd && isKeyK(e)) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            return false;
        }
    }

    // Delegated click listener: ensures clean native navigation for all command palette links
    document.addEventListener('click', function(e) {
        const item = e.target.closest('#sppdocs-cmd-palette a.sppdocs-cmd-item, #sppdocs-cmd-palette a.sppdocs-instant-result-item');
        if (item) {
            const href = item.getAttribute('href');
            if (href && !href.startsWith('#') && item.target !== '_blank') {
                window.closeCommandPalette();
                window.location.href = href;
                e.preventDefault();
            }
        }
    });

    // Delegated input listener (handles HTMX swaps seamlessly)
    document.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'sppdocs-cmd-input') {
            if (currentMode === 'nav') {
                filterItems(e.target.value);
            }
        }
    });

    // Register global hotkey listeners with CAPTURE = TRUE once
    if (!window._sppdocsCmdPaletteHotkeysBound) {
        window._sppdocsCmdPaletteHotkeysBound = true;
        window.addEventListener('keydown', handleKeyDown, true);
        window.addEventListener('keyup', handleKeyUp, true);
        window.addEventListener('keypress', function(e) {
            const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;
            if (isCtrlOrCmd && isKeyK(e)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }, true);
    }
})();
</script>
