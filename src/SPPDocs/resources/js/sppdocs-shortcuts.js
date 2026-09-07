/**
 * SPPDocs Keyboard Shortcuts & PWA Velocity Engine
 * Linear-Grade Global Hotkeys (C, J, K, X, E, Enter, O, B, L, M, D, ?)
 */
(function() {
    'use strict';

    let activeRowIndex = -1;

    function isInputActive() {
        const el = document.activeElement;
        if (!el) return false;
        const tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
    }

    function getNavigableRows() {
        return Array.from(document.querySelectorAll('.issue-row-item, .kanban-card, .sppdocs-instant-result-item, .sppdocs-cmd-item'));
    }

    function highlightRow(rows, index) {
        rows.forEach((r, idx) => {
            if (idx === index) {
                r.classList.add('keyboard-selected');
                r.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                r.classList.remove('keyboard-selected');
            }
        });
    }

    window.addEventListener('keydown', function(e) {
        if (isInputActive()) return;

        // Modifier key ignore (Cmd, Ctrl, Alt)
        if (e.metaKey || e.ctrlKey || e.altKey) return;

        const key = e.key;

        // '?' -> Toggle Shortcuts Help Cheatsheet
        if (key === '?') {
            e.preventDefault();
            const modal = document.getElementById('sppdocs-shortcuts-help-modal');
            if (modal) {
                modal.style.display = (modal.style.display === 'flex') ? 'none' : 'flex';
            }
            return;
        }

        // 'C' or 'c' -> Create New Issue
        if (key.toLowerCase() === 'c') {
            const modal = document.getElementById('new-issue-modal');
            if (modal) {
                e.preventDefault();
                modal.style.display = 'flex';
                const firstInput = modal.querySelector('input[type="text"], input');
                if (firstInput) firstInput.focus();
                return;
            }
            const createBtn = document.querySelector('a[href*="issues/create"], .btn-create-issue');
            if (createBtn) {
                e.preventDefault();
                createBtn.click();
            }
            return;
        }

        // '/' -> Focus Search Input
        if (key === '/') {
            const searchInput = document.querySelector('input[type="search"], input[name="q"], #issue-search-input');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
                return;
            }
        }

        // 'J' -> Move selection down
        const rows = getNavigableRows();
        if (rows.length > 0) {
            if (key.toLowerCase() === 'j') {
                e.preventDefault();
                activeRowIndex = Math.min(activeRowIndex + 1, rows.length - 1);
                highlightRow(rows, activeRowIndex);
                return;
            }

            // 'K' -> Move selection up
            if (key.toLowerCase() === 'k') {
                e.preventDefault();
                activeRowIndex = Math.max(activeRowIndex - 1, 0);
                highlightRow(rows, activeRowIndex);
                return;
            }

            // 'X' -> Toggle Checkbox on Selected Row
            if (key.toLowerCase() === 'x' && activeRowIndex >= 0 && rows[activeRowIndex]) {
                e.preventDefault();
                const checkbox = rows[activeRowIndex].querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return;
            }

            // 'Enter' or 'O' -> Open Selected Item
            if ((key === 'Enter' || key.toLowerCase() === 'o') && activeRowIndex >= 0 && rows[activeRowIndex]) {
                e.preventDefault();
                const link = rows[activeRowIndex].tagName === 'A' ? rows[activeRowIndex] : rows[activeRowIndex].querySelector('a');
                if (link) {
                    link.click();
                }
                return;
            }
        }

        // 'E' -> Edit current document
        if (key.toLowerCase() === 'e') {
            const editBtn = document.querySelector('.sppdocs-doc-edit-btn, a[href*="admin/editor"], a[href*="issues/view"]');
            if (editBtn) {
                e.preventDefault();
                editBtn.click();
                return;
            }
        }

        // Quick Navigation Jumps (B, L, M, D, A)
        if (key.toLowerCase() === 'b') {
            const boardLink = document.querySelector('a[href*="issues/board"]');
            if (boardLink && boardLink.getAttribute('href')) { e.preventDefault(); window.location.href = boardLink.getAttribute('href'); return; }
        }
        if (key.toLowerCase() === 'l') {
            const listLink = document.querySelector('a[href*="issues"]:not([href*="board"]):not([href*="create"])');
            if (listLink && listLink.getAttribute('href')) { e.preventDefault(); window.location.href = listLink.getAttribute('href'); return; }
        }
        if (key.toLowerCase() === 'm') {
            const msLink = document.querySelector('a[href*="milestones"]');
            if (msLink && msLink.getAttribute('href')) { e.preventDefault(); window.location.href = msLink.getAttribute('href'); return; }
        }
        if (key.toLowerCase() === 'd') {
            const docLink = document.querySelector('a[href*="project/"]:not([href*="admin"])');
            if (docLink && docLink.getAttribute('href')) { e.preventDefault(); window.location.href = docLink.getAttribute('href'); return; }
        }
        if (key.toLowerCase() === 'a') {
            const adminLink = document.querySelector('a[href*="admin/project/"], a.header-admin-btn, a[href*="admin"]');
            if (adminLink && adminLink.getAttribute('href')) { e.preventDefault(); window.location.href = adminLink.getAttribute('href'); return; }
        }
    });

    // PWA Service Worker Registration
    if ('serviceWorker' in navigator && window.location.protocol.startsWith('http')) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/school1/public/sw.js', { scope: '/school1/public/' })
                .then(function(reg) {
                    console.log('SPPDocs Offline PWA Active. Scope:', reg.scope);
                })
                .catch(function(err) {
                    // Non-blocking PWA fallback
                });
        });
    }
})();
