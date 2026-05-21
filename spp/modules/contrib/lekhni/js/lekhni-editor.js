import BaseComponent from '../../../spp/sppux/js/BaseComponent.js?v=2026_05_20_v1';
import { LekhniMonaco } from './monaco-engine.js?v=2026_05_20_v1';

/**
 * Lekhni - Ultimate Modular Block, Dual-Mode IDE & Enterprise Document Engine
 * Highly responsive WYSIWYG client featuring complete offline IndexedDB safety buffers,
 * interactive outline scroll trackers, inline annotations, and live history time machine.
 */
export default class LekhniEditor extends BaseComponent {
    async onInit(params) {
        this.state = {
            id: params?.id || this.props?.id || null,
            title: this.props?.title || '',
            body: this.props?.body || '',
            status: this.props?.status || 'draft',
            alias: this.props?.alias || '',
            tags: this.props?.tags || '',
            category: this.props?.category || 'General',
            bundle: this.props?.bundle || 'Article',
            bundles: ['Article', 'Page', 'Press Release', 'Product Story', 'Case Study'],
            saving: false,
            lastSaved: null,
            isDirty: false,
            manualAlias: false,
            mediaLoading: false,

            // Workspace Modes: 'document' vs 'code'
            editorMode: this.props?.mode || 'document',
            codeLanguage: this.props?.language || 'html',
            theme: 'dark',
            categories: ['General', 'News', 'Tutorial', 'Engineering', 'Documentation'],

            // Structural Outline Array
            outline: [],

            // Revision History snapshots
            revisions: [],
            showHistoryModal: false,
            selectedRevisionIndex: 0,

            // Offline Caching flags
            hasOfflineSnapshot: false,
            offlineSnapshotData: null,

            // Floating Menus State
            showSlashMenu: false, slashX: 0, slashY: 0,
            slashFilter: '', slashIndex: 0,
            showBubbleMenu: false, bubbleX: 0, bubbleY: 0,
            showAIComposerModal: false, aiComposerX: 0, aiComposerY: 0,
            aiComposerQuery: '', aiComposerLoading: false,
            
            // Layout context
            embedded: this.props?.embedded || this.props?.inline || false,

            // Dynamic Toolbar & Popover configurations
            toolbarLayout: params?.toolbarLayout || this.props?.toolbarLayout || 'full',
            bubbleMenuEnabled: params?.bubbleMenuEnabled ?? this.props?.bubbleMenuEnabled ?? true,
            slashMenuEnabled: params?.slashMenuEnabled ?? this.props?.slashMenuEnabled ?? true,
            printModeEnabled: params?.printModeEnabled ?? this.props?.printModeEnabled ?? false,

            // Clipboard Paste & Paste Special State
            lastClipboardHtml: '',
            lastClipboardText: '',
            activePasteBlockId: null,
            showPasteOptions: false,
            pasteOptionsX: 0,
            pasteOptionsY: 0,
            pasteOptions: {
                avoidBgColor: false,
                avoidFgColor: false,
                avoidFont: false,
                plainText: false
            },
            defaultPasteFilters: {
                avoidBgColor: false,
                avoidFgColor: false,
                avoidFont: false,
                plainText: false
            }
        };

        this.slashCommands = [
            { id: 'h1', label: 'Heading 1', icon: 'H1', desc: 'Big section heading' },
            { id: 'h2', label: 'Heading 2', icon: 'H2', desc: 'Medium sub-heading' },
            { id: 'p', label: 'Paragraph', icon: '¶', desc: 'Plain text block' },
            { id: 'quote', label: 'Quote', icon: '”', desc: 'Capture a quote' },
            { id: 'code', label: 'Code Block', icon: '&lt;/&gt;', desc: 'Embedded Monaco workspace' },
            { id: 'card', label: 'Web Card', icon: '🔗', desc: 'Insert preview web link' },
            { id: 'gallery', label: 'Image Grid', icon: '🎴', desc: 'Adaptive multi-image block' },
            { id: 'table', label: 'Smart Grid', icon: '📊', desc: 'Spreadsheet with formulas' },
            { id: 'tasks', label: 'Tasks Board', icon: '☑️', desc: 'Interactive task checklist' },
            { id: 'pdf', label: 'PDF Frame', icon: '📄', desc: 'Adjustable interactive PDF document frame' },
            { id: 'ai', label: 'AI Co-Pilot', icon: '✨', desc: 'Auto-complete or enhance' }
        ];

        this._monacoIdeInstance = null;
        this._db = null;
        this._documentClickHandler = null;
        this._outlineInterval = null;
        this._contentLoadedFromPromise = false;

        if (this.props?.contentPromise && this.state.id) {
            this.state.saving = true;
            try {
                const res = await this.props.contentPromise;
                if (res.success) {
                    const node = res.node;
                    this.state.title = node.title || '';
                    this.state.body = node.body || '';
                    this.state.status = node.status || 'draft';
                    this.state.alias = node.alias || '';
                    this.state.saving = false;
                    this.state.manualAlias = !!node.alias;
                    this._contentLoadedFromPromise = true;
                } else {
                    this.state.saving = false;
                }
            } catch (e) {
                console.error("[Lekhni] Node load from promise failed:", e);
                this.state.saving = false;
            }
        }
    }

    async onMount() {
        await this.loadModuleSettings();
        await this.initOfflineIndexedDB();

        if (this.state.id && !this._contentLoadedFromPromise && !this.state.body) {
            await this.loadNode();
        } else {
            this.syncActiveWorkspaceContent();
            this.captureRevisionSnapshot(this._contentLoadedFromPromise ? 'Loaded API Payload' : 'Initial Launch');
        }

        // Global outside click observers
        this._documentClickHandler = (e) => {
            if (this.state.activePasteBlockId && !e.target.closest('.lekhni-paste-popover')) {
                this.finalizePaste();
            }
            if (!e.target.closest('.lekhni-slash-menu') && !e.target.closest('.lekhni-body-editable')) {
                if (this.state.showSlashMenu) this.setState({ showSlashMenu: false });
            }
            if (!e.target.closest('.lekhni-bubble-menu') && !e.target.closest('.lekhni-body-editable')) {
                if (this.state.showBubbleMenu) this.setState({ showBubbleMenu: false });
            }
        };
        document.addEventListener('click', this._documentClickHandler);

        // Trigger sequential debounced outline extractions
        this._outlineInterval = setInterval(() => this.buildOutlineTracker(), 1500);
    }

    onDestroy() {
        if (this._documentClickHandler) document.removeEventListener('click', this._documentClickHandler);
        if (this._outlineInterval) clearInterval(this._outlineInterval);
        if (this._db) this._db.close();
    }

    update() {
        // Intercept reconciliation to prevent the underlying contenteditable buffer from wiping on outside click / menu setState
        if (this.state.editorMode === 'document') {
            const el = this.container?.querySelector('.lekhni-body-editable');
            // Only sync FROM the DOM if it's not empty and not just the default placeholder
            // This prevents the state from being wiped before loadNode completes
            if (el && el.innerHTML && el.innerHTML !== '<p><br></p>' && el.innerHTML !== 'Start writing...') {
                this.state.body = el.innerHTML;
            }
        }
        super.update();
        if (this.state.editorMode === 'document') {
            const el = this.container?.querySelector('.lekhni-body-editable');
            if (el && el.innerHTML !== this.state.body) {
                el.innerHTML = this.state.body || '<p><br></p>';
            }
        }
    }

    // 💾 Feature 5: Offline Persistent Storage Engine via IndexedDB
    async initOfflineIndexedDB() {
        return new Promise((resolve) => {
            const req = indexedDB.open('LekhniEnterpriseStore', 1);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('offline_drafts')) {
                    db.createObjectStore('offline_drafts', { keyPath: 'docId' });
                }
            };
            req.onsuccess = (e) => {
                this._db = e.target.result;
                this.checkExistingOfflineSnapshot();
                resolve();
            };
            req.onerror = () => resolve();
        });
    }

    async checkExistingOfflineSnapshot() {
        if (!this._db) return;
        const targetId = this.state.id || 'new_doc';
        try {
            const tx = this._db.transaction('offline_drafts', 'readonly');
            const store = tx.objectStore('offline_drafts');
            const req = store.get(targetId);
            req.onsuccess = () => {
                if (req.result && req.result.body && req.result.body !== this.state.body) {
                    this.setState({ 
                        hasOfflineSnapshot: true, 
                        offlineSnapshotData: req.result 
                    });
                }
            };
        } catch (e) {}
    }

    async commitBufferToOfflineStore() {
        if (!this._db) return;
        const targetId = this.state.id || 'new_doc';
        try {
            const tx = this._db.transaction('offline_drafts', 'readwrite');
            const store = tx.objectStore('offline_drafts');
            store.put({
                docId: targetId,
                title: this.state.title,
                body: this.state.body,
                timestamp: new Date().toLocaleTimeString()
            });
        } catch (e) {}
    }

    restoreOfflineSnapshot() {
        if (!this.state.offlineSnapshotData) return;
        this.state.title = this.state.offlineSnapshotData.title || this.state.title;
        this.state.body = this.state.offlineSnapshotData.body || '';
        this.state.hasOfflineSnapshot = false;
        this.state.isDirty = true;
        this.syncActiveWorkspaceContent();
        this.notify('Offline cached state recovered.', 'success');
    }

    discardOfflineSnapshot() {
        this.setState({ hasOfflineSnapshot: false, offlineSnapshotData: null });
        if (!this._db) return;
        try {
            const tx = this._db.transaction('offline_drafts', 'readwrite');
            tx.objectStore('offline_drafts').delete(this.state.id || 'new_doc');
        } catch (e) {}
    }

    // 📑 Feature 1: Real-time Table of Contents & Outline Generator
    buildOutlineTracker() {
        if (this.state.editorMode !== 'document') return;
        const editor = this.container.querySelector('.lekhni-body-editable');
        if (!editor) return;

        const headers = Array.from(editor.querySelectorAll('h1, h2'));
        const newOutline = headers.map((h, idx) => {
            if (!h.id) h.id = 'lekhni_h_' + idx + '_' + Date.now().toString(36);
            return {
                id: h.id,
                label: h.textContent.trim() || '(Empty Heading)',
                level: h.tagName.toLowerCase(),
                element: h
            };
        });

        // Compare array buffers to prevent non-stop heavy DOM re-rendering
        if (JSON.stringify(newOutline.map(o => o.label)) !== JSON.stringify(this.state.outline.map(o => o.label))) {
            this.setState({ outline: newOutline });
        }
    }

    scrollToOutlineAnchor(targetId) {
        const target = this.container.querySelector('#' + targetId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Flash a visual highlight safely
            target.style.transition = 'color 0.3s';
            const oldColor = target.style.color;
            target.style.color = '#6366f1';
            setTimeout(() => target.style.color = oldColor, 1200);
        }
    }

    // 📜 Feature 6: Visual Revision Timeline and Diffing
    captureRevisionSnapshot(label = 'Update') {
        const curTime = new Date().toLocaleTimeString();
        // Avoid inserting complete verbatim copies adjacently
        const lastRev = this.state.revisions[this.state.revisions.length - 1];
        if (lastRev && lastRev.body === this.state.body) return;

        this.state.revisions = [...this.state.revisions, {
            label,
            timestamp: curTime,
            body: this.state.body,
            title: this.state.title
        }];
    }

    restoreHistoricalRevision(idx) {
        const target = this.state.revisions[idx];
        if (!target) return;
        
        // Preserve forward state before stepping back
        this.captureRevisionSnapshot('Pre-Rollback');

        this.state.body = target.body;
        this.state.title = target.title || this.state.title;
        this.state.isDirty = true;
        this.setState({ showHistoryModal: false });
        this.syncActiveWorkspaceContent();
        this.notify(`Rolled back to revision: ${target.timestamp}`, 'success');
    }

    async loadModuleSettings() {
        try {
            const apiCall = this.admin?.api ? this.admin.api : null;
            if (apiCall) {
                const res = await apiCall('lekhni_get_settings', {}).catch(() => apiCall('get_settings', {}));
                if (res?.success && res.settings) {
                    this.setState({
                        editorMode: this.props?.mode || res.settings.default_mode || 'document',
                        codeLanguage: this.props?.language || res.settings.code_language || 'html',
                        theme: res.settings.theme || 'dark',
                        categories: res.settings.categories || this.state.categories
                    });
                }
            }
        } catch (e) {}
    }

    getCleanBodyHtml() {
        const editor = this.container.querySelector('.lekhni-body-editable');
        if (!editor) return this.state.body || '';
        
        // Clone the editor DOM to avoid visual flicker during cleanup
        const clone = editor.cloneNode(true);
        
        // Find all hydrated code blocks in the clone
        clone.querySelectorAll('[id^="monaco_node_"]').forEach(targetHost => {
            // Get the value from the active editor instance in the real DOM if it exists
            const realHost = editor.querySelector(`#${targetHost.id}`);
            let val = '';
            if (realHost) {
                const ta = realHost.querySelector('textarea');
                if (ta) val = ta.value;
            } else {
                const ta = targetHost.querySelector('textarea');
                if (ta) val = ta.value || ta.textContent;
            }
            val = LekhniMonaco.stripSyntaxHighlighting(val);
            
            // Clean the targetHost in the clone
            targetHost.innerHTML = '';
            targetHost.removeAttribute('data-lekhni-hydrated');
            const cleanTa = document.createElement('textarea');
            cleanTa.value = val;
            cleanTa.textContent = val;
            targetHost.appendChild(cleanTa);
        });
        
        return clone.innerHTML;
    }

    syncBodyState() {
        this.state.body = this.getCleanBodyHtml();
        this.state.isDirty = true;
    }

    syncActiveWorkspaceContent() {
        if (this.state.editorMode === 'code') {
            this.mountFullBleedMonacoIde();
        } else {
            const editor = this.container.querySelector('.lekhni-body-editable');
            if (editor) {
                editor.innerHTML = this.state.body || '<p><br></p>';
            }
            this.setupEditorObservers();
            this.buildOutlineTracker();
        }
    }

    setEditorMode(newMode) {
        if (this.state.editorMode === newMode) return;
        
        if (this.state.editorMode === 'code' && this._monacoIdeInstance) {
            this.state.body = this._monacoIdeInstance.getValue();
            this._monacoIdeInstance.dispose();
            this._monacoIdeInstance = null;
        } else {
            const editor = this.container.querySelector('.lekhni-body-editable');
            if (editor) this.state.body = this.getCleanBodyHtml();
        }

        this.setState({ 
            editorMode: newMode, showSlashMenu: false, showBubbleMenu: false 
        });

        setTimeout(() => this.syncActiveWorkspaceContent(), 50);
    }

    mountFullBleedMonacoIde() {
        const ideContainer = this.container.querySelector('.lekhni-full-ide-host');
        if (!ideContainer) return;
        
        if (ideContainer.hasAttribute('data-lekhni-hydrated') && this._monacoIdeInstance) {
            const currentVal = this._monacoIdeInstance.getValue();
            if (currentVal !== this.state.body) {
                this._monacoIdeInstance.setValue(this.state.body || '');
            }
            return;
        }

        ideContainer.innerHTML = '';
        ideContainer.setAttribute('data-lekhni-hydrated', 'true');
        let startVal = this.state.body;
        if (!startVal && this.state.title) {
            startVal = `<!-- Lekhni Source Stream: ${this.state.title} -->\n<div class="document-section">\n  <p>Start coding source blocks directly...</p>\n</div>`;
        }

        this._monacoIdeInstance = LekhniMonaco.create(ideContainer, {
            language: this.state.codeLanguage,
            value: startVal,
            height: this.state.embedded ? '350px' : 'calc(100vh - 180px)'
        });

        this._monacoIdeInstance.onDidChangeContent((val) => {
            this.state.body = val;
            this.state.isDirty = true;
            this.autoSave();
        });
    }

    async loadNode() {
        this.setState({ saving: true });
        try {
            const res = await this.admin.api('get_node', { id: this.state.id });
            if (res.success) {
                const node = res.node;
                this.setState({
                    title: node.title || '', 
                    body: node.body || '', 
                    status: node.status || 'draft',
                    alias: node.alias || '', 
                    saving: false, 
                    manualAlias: !!node.alias
                });
                this.syncActiveWorkspaceContent();
                this.captureRevisionSnapshot('Loaded API Payload');
            } else {
                throw new Error(res.message || 'API Error');
            }
        } catch (e) {
            console.error("[Lekhni] Node load failed:", e);
            this.notify(`Failed to load document: ${e.message}`, 'error');
            this.setState({ saving: false });
        }
    }

    setupEditorObservers() {
        const editor = this.container.querySelector('.lekhni-body-editable');
        if (!editor) return;

        // Rehydrate embedded code blocks
        editor.querySelectorAll('.lekhni-embedded-block').forEach(wrapper => {
            const targetHost = wrapper.querySelector('[id^="monaco_node_"]');
            if (!targetHost || targetHost.hasAttribute('data-lekhni-hydrated')) return;

            const ta = targetHost.querySelector('textarea');
            if (ta) {
                targetHost.setAttribute('data-lekhni-hydrated', 'true');
                const rawVal = ta.textContent || ta.value;
                const val = LekhniMonaco.stripSyntaxHighlighting(rawVal);
                
                const select = wrapper.querySelector('select');
                const lang = targetHost.getAttribute('data-language') || (select ? select.value : 'javascript');
                targetHost.setAttribute('data-language', lang);
                if (select) select.value = lang;
                
                targetHost.innerHTML = '';
                let instance = LekhniMonaco.create(targetHost, { language: lang, value: val });
                
                const newTa = targetHost.querySelector('textarea');
                if (newTa) newTa.textContent = val;

                const bindListeners = (inst) => {
                    inst.onDidChangeContent((newVal) => {
                        const currentTa = targetHost.querySelector('textarea');
                        if (currentTa) currentTa.textContent = newVal;
                        this.state.body = this.getCleanBodyHtml();
                        this.state.isDirty = true;
                        if (typeof this.autoSave === 'function') this.autoSave();
                    });
                };
                
                bindListeners(instance);
                
                if (select) {
                    select.addEventListener('change', (e) => {
                        const newLang = e.target.value;
                        targetHost.setAttribute('data-language', newLang);
                        const currentVal = instance.getValue();
                        instance.dispose();
                        targetHost.innerHTML = '';
                        instance = LekhniMonaco.create(targetHost, { language: newLang, value: currentVal });
                        
                        const updatedTa = targetHost.querySelector('textarea');
                        if (updatedTa) updatedTa.textContent = currentVal;
                        
                        bindListeners(instance);
                        this.state.body = this.getCleanBodyHtml();
                        this.state.isDirty = true;
                        if (typeof this.autoSave === 'function') this.autoSave();
                    });
                }
                
                const copyBtn = wrapper.querySelector('.lekhni-monaco-copy-btn');
                if (copyBtn) {
                    copyBtn.onclick = () => {
                        const currentTa = targetHost.querySelector('textarea');
                        if (currentTa) {
                            navigator.clipboard.writeText(currentTa.value || currentTa.textContent).then(() => {
                                copyBtn.textContent = '✅ Copied';
                                setTimeout(() => copyBtn.textContent = '📋 Copy', 2000);
                            });
                        }
                    };
                }
            }
        });

        editor.addEventListener('paste', (e) => this.handlePasteEvent(e));

        editor.addEventListener('input', () => {
            this.state.body = this.getCleanBodyHtml();
            this.state.isDirty = true;
            this.autoSave();

            // Intercept direct inline links conversion safely
            this.processLiveUrlPatterns();

            const sel = window.getSelection();
            if (!sel.rangeCount) return;
            const range = sel.getRangeAt(0);
            const text = range.startContainer.textContent;
            const offset = range.startOffset;

            // Intercept double plus symbol typing "++" to activate AI inline co-pilot prompt dialog
            if (text && text.substring(offset - 2, offset) === '++') {
                // Remove the "++" from the editor DOM node content
                range.startContainer.textContent = text.substring(0, offset - 2) + text.substring(offset);
                const newRange = document.createRange();
                newRange.setStart(range.startContainer, offset - 2);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
                
                // Launch AI Composer modal overlay
                this.triggerInlineAIComposer();
                return;
            }

            const slashPos = text.lastIndexOf('/', offset - 1);
            if (slashPos !== -1 && (slashPos === 0 || text[slashPos - 1] === ' ')) {
                const filterText = text.substring(slashPos + 1, offset).toLowerCase();
                if (!filterText.includes(' ')) {
                    const rect = range.getBoundingClientRect();
                    const containerRect = this.container.getBoundingClientRect();
                    this.setState({
                        showSlashMenu: true,
                        slashX: rect.left - containerRect.left,
                        slashY: rect.bottom - containerRect.top + 8,
                        slashFilter: filterText,
                        slashIndex: 0
                    });
                    return;
                }
            }
            if (this.state.showSlashMenu) this.setState({ showSlashMenu: false });
        });

        editor.addEventListener('keydown', (e) => {
            if (this.state.activePasteBlockId && !e.ctrlKey && !e.metaKey && e.key !== 'Shift') {
                this.finalizePaste();
            }

            if (this.state.showSlashMenu) {
                const filtered = this.slashCommands.filter(c => c.label.toLowerCase().includes(this.state.slashFilter));
                if (e.key === 'ArrowDown') { e.preventDefault(); this.setState({ slashIndex: (this.state.slashIndex + 1) % filtered.length }); return; }
                if (e.key === 'ArrowUp') { e.preventDefault(); this.setState({ slashIndex: (this.state.slashIndex - 1 + filtered.length) % filtered.length }); return; }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (filtered[this.state.slashIndex]) this.executeSlashCommand(filtered[this.state.slashIndex].id);
                    return;
                }
                if (e.key === 'Escape') { this.setState({ showSlashMenu: false }); return; }
            }

            if (e.key === ' ' || e.key === 'Enter') {
                const sel = window.getSelection();
                if (!sel.rangeCount) return;
                const node = sel.anchorNode;
                if (!node || node.nodeType !== Node.TEXT_NODE) return;
                
                const text = node.textContent;
                let matched = false, formatCmd = '', blockClass = '';

                if (text.trim() === '#') { matched = true; formatCmd = 'h1'; blockClass = 'lekhni-h1'; }
                else if (text.trim() === '##') { matched = true; formatCmd = 'h2'; blockClass = 'lekhni-h2'; }
                else if (text.trim() === '>') { matched = true; formatCmd = 'blockquote'; blockClass = 'lekhni-quote'; }
                else if (text.trim() === '```') {
                    e.preventDefault();
                    node.textContent = '';
                    this.insertEmbeddedMonacoBlock();
                    return;
                }

                if (matched) {
                    e.preventDefault();
                    node.textContent = '';
                    document.execCommand('formatBlock', false, formatCmd);
                    const parent = sel.anchorNode.parentNode;
                    if (parent && parent !== editor) parent.className = blockClass;
                }
            }
        });

        const updateBubbleMenu = () => {
            const sel = window.getSelection();
            if (!sel || sel.isCollapsed || !editor.contains(sel.anchorNode)) {
                if (this.state.showBubbleMenu) this.setState({ showBubbleMenu: false });
                return;
            }
            const range = sel.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            const containerRect = this.container.getBoundingClientRect();
            this.setState({
                showBubbleMenu: true,
                bubbleX: Math.max(10, rect.left + rect.width / 2 - containerRect.left - 130),
                bubbleY: Math.max(10, rect.top - containerRect.top - 48)
            });
        };

        editor.addEventListener('mouseup', () => setTimeout(updateBubbleMenu, 10));
        editor.addEventListener('keyup', (e) => { if (e.key.startsWith('Arrow')) setTimeout(updateBubbleMenu, 10); });
        editor.addEventListener('focus', () => {
            if (editor.innerHTML === '<p><br></p>' || editor.innerHTML.includes('Start writing...')) editor.innerHTML = '';
        });
        
        // Setup specialized image array drop zones
        editor.addEventListener('dragover', (e) => e.preventDefault());
        editor.addEventListener('drop', (e) => {
            e.preventDefault();
            if (e.dataTransfer?.files?.length) {
                this.processMediaFilesBatch(Array.from(e.dataTransfer.files));
            }
        });
    }

    // 📋 Paste & Paste Special Processor Engine
    handlePasteEvent(e) {
        const html = e.clipboardData.getData('text/html') || '';
        const text = e.clipboardData.getData('text/plain') || '';

        // If there is no HTML content, let the browser handle standard text paste
        if (!html) {
            return;
        }

        e.preventDefault();

        // Finalize any outstanding active paste block
        if (this.state.activePasteBlockId) {
            this.finalizePaste();
        }

        // Calculate caret position for placing the floating options popover
        let x = 100;
        let y = 200;
        const sel = window.getSelection();
        if (sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            const containerRect = this.container.getBoundingClientRect();
            if (rect.left || rect.bottom) {
                x = rect.left - containerRect.left;
                y = rect.bottom - containerRect.top + 8;
            }
        }

        const pasteBlockId = 'lekhni_paste_' + Date.now();
        this.state.lastClipboardHtml = html;
        this.state.lastClipboardText = text;
        this.state.activePasteBlockId = pasteBlockId;

        // Clone global default preferences or force plain text if Shift is held (Ctrl+Shift+V)
        const isShiftPaste = e.shiftKey;
        const activeFilters = { ...this.state.defaultPasteFilters };
        if (isShiftPaste) {
            activeFilters.plainText = true;
        }
        this.state.pasteOptions = activeFilters;

        // Wrap and insert at current caret position
        const wrappedHtml = `<span id="${pasteBlockId}" class="lekhni-paste-block" style="display: inline;">${html}</span>`;
        this.insertHtmlAtCaret(wrappedHtml);

        // Run parser over the inserted container block
        this.filterPastedContent(pasteBlockId, activeFilters);

        // Display options popover
        this.setState({
            showPasteOptions: true,
            pasteOptionsX: x,
            pasteOptionsY: y
        });

        this.notify(isShiftPaste ? 'Pasted as Plain Text' : 'Content pasted. Click 📋 Paste Options to filter styling.', 'info');
    }

    insertHtmlAtCaret(html) {
        const sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount) {
            const range = sel.getRangeAt(0);
            range.deleteContents();

            const el = document.createElement("div");
            el.innerHTML = html;
            const frag = document.createDocumentFragment();
            let node, lastNode;
            while ((node = el.firstChild)) {
                lastNode = frag.appendChild(node);
            }
            range.insertNode(frag);
            
            if (lastNode) {
                const newRange = range.cloneRange();
                newRange.setStartAfter(lastNode);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }
    }

    filterPastedContent(blockId, options) {
        const block = this.container.querySelector('#' + blockId);
        if (!block) return;

        const originalHtml = this.state.lastClipboardHtml;
        const originalText = this.state.lastClipboardText;

        if (options.plainText) {
            const formattedText = originalText
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\r?\n/g, '<br>');
            block.innerHTML = formattedText;
            return;
        }

        const temp = document.createElement('div');
        temp.innerHTML = originalHtml;

        // Clean inline styles
        const elementsWithStyles = temp.querySelectorAll('[style]');
        elementsWithStyles.forEach(el => {
            let style = el.getAttribute('style') || '';
            
            if (options.avoidBgColor) {
                style = style.replace(/background\s*:[^;]+(;|$)/gi, '');
                style = style.replace(/background-color\s*:[^;]+(;|$)/gi, '');
            }
            if (options.avoidFgColor) {
                style = style.replace(/color\s*:[^;]+(;|$)/gi, '');
            }
            if (options.avoidFont) {
                style = style.replace(/font-family\s*:[^;]+(;|$)/gi, '');
                style = style.replace(/font-size\s*:[^;]+(;|$)/gi, '');
            }

            style = style.trim().replace(/;+/g, ';');
            if (style === ';' || !style) {
                el.removeAttribute('style');
            } else {
                el.setAttribute('style', style);
            }
        });

        // Strip legacy background, foreground, and font properties
        if (options.avoidBgColor) {
            temp.querySelectorAll('[bgcolor]').forEach(el => el.removeAttribute('bgcolor'));
        }
        if (options.avoidFgColor) {
            temp.querySelectorAll('[color]').forEach(el => el.removeAttribute('color'));
        }
        if (options.avoidFont) {
            temp.querySelectorAll('font').forEach(el => {
                const parent = el.parentNode;
                while (el.firstChild) {
                    parent.insertBefore(el.firstChild, el);
                }
                parent.removeChild(el);
            });
        }

        block.innerHTML = temp.innerHTML;
        
        // Sync DOM content back to reactive buffer
        const editor = this.container.querySelector('.lekhni-body-editable');
        if (editor) {
            this.state.body = this.getCleanBodyHtml();
            this.state.isDirty = true;
            this.autoSave();
        }
    }

    applyPastePreset(preset) {
        if (!this.state.activePasteBlockId) return;

        const newOptions = {
            avoidBgColor: preset === 'plain',
            avoidFgColor: preset === 'plain',
            avoidFont: preset === 'plain',
            plainText: preset === 'plain'
        };

        this.state.pasteOptions = newOptions;
        this.state.defaultPasteFilters = { ...newOptions };
        this.update();

        this.filterPastedContent(this.state.activePasteBlockId, newOptions);
    }

    togglePasteOption(optionName, value) {
        this.state.pasteOptions[optionName] = value;
        this.state.defaultPasteFilters[optionName] = value;
        this.update();
        
        if (this.state.activePasteBlockId) {
            this.filterPastedContent(this.state.activePasteBlockId, this.state.pasteOptions);
        }
    }

    finalizePaste() {
        if (!this.state.activePasteBlockId) {
            this.setState({ showPasteOptions: false });
            return;
        }
        
        const block = this.container.querySelector('#' + this.state.activePasteBlockId);
        if (block) {
            const parent = block.parentNode;
            while (block.firstChild) {
                parent.insertBefore(block.firstChild, block);
            }
            parent.removeChild(block);
        }
        
        this.setState({
            showPasteOptions: false,
            activePasteBlockId: null
        });
    }

    triggerPasteSpecialConfig() {
        const containerRect = this.container.getBoundingClientRect();
        this.setState({
            showPasteOptions: !this.state.showPasteOptions,
            pasteOptionsX: (containerRect.width / 2) - 110,
            pasteOptionsY: 80
        });
    }

    // 🎴 Feature 3: Multi-Image Grid & Masonry Gallery Builder
    async processMediaFilesBatch(fileArray) {
        const imageFiles = fileArray.filter(f => f.type.startsWith('image/'));
        if (!imageFiles.length) return;

        this.setState({ mediaLoading: true });
        this.notify(`Uploading batch of ${imageFiles.length} framework media item(s)...`, 'info');

        const uploadedUrls = [];
        for (const file of imageFiles) {
            const formData = new FormData();
            formData.append('file', file);
            try {
                const res = await this.uploadMediaForm(formData);
                if (res?.success && res.url) uploadedUrls.push(res.url);
            } catch (e) {}
        }

        this.setState({ mediaLoading: false });

        if (uploadedUrls.length === 1) {
            this.format('insertImage', uploadedUrls[0]);
        } else if (uploadedUrls.length > 1) {
            // Embed an absolutely wonderful native adaptive CSS Flexbox grid container wrapper
            const galleryId = 'gallery_' + Date.now();
            const galleryHtml = `
                <div class="lekhni-gallery-grid" id="${galleryId}" contenteditable="false" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 1.5rem 0; border: 1px solid #334155; padding: 12px; border-radius: 8px; background: rgba(15,23,42,0.3);">
                    ${uploadedUrls.map(u => `<div style="aspect-ratio: 1; overflow: hidden; border-radius: 6px; background: #0f172a;"><img src="${u}" style="width:100%; height:100%; object-fit:cover;" alt="Gallery Media" /></div>`).join('')}
                </div>
                <p><br></p>
            `;
            this.format('insertHTML', galleryHtml);
            this.notify('Media gallery configured block seamlessly.', 'success');
        }
    }

    // 🔗 Feature 2: OEmbed Rich-Link Preview Cards
    processLiveUrlPatterns() {
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        const node = sel.anchorNode;
        if (!node || node.nodeType !== Node.TEXT_NODE) return;
        
        const text = node.textContent;
        // Detect plain external domain strings pasted with space suffix
        const urlMatch = text.match(/(https?:\/\/[^\s]+)\s$/);
        if (urlMatch && urlMatch[1]) {
            const fullUrl = urlMatch[1];
            // Clear matched raw string fragment
            node.textContent = text.replace(fullUrl + ' ', '');
            this.insertRichWebCard(fullUrl);
        }
    }

    insertRichWebCard(urlStr) {
        let domainName = 'External Link';
        try { domainName = new URL(urlStr).hostname.replace('www.', ''); } catch (e) {}
        
        const cardHtml = `
            <div class="lekhni-web-card" contenteditable="false" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; margin: 1rem 0; border: 1px solid #334155; border-radius: 8px; background: #1e293b; transition: border-color 0.2s;">
                <div style="overflow:hidden; padding-right: 12px;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #6366f1; font-weight: bold; margin-bottom: 2px;">${domainName}</div>
                    <div style="font-size: 0.9rem; color: #f1f5f9; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${urlStr}</div>
                </div>
                <a href="${urlStr}" target="_blank" style="background: #0f172a; color: #58a6ff; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; flex-shrink: 0; border: 1px solid #30363d;">Launch ↗</a>
            </div>
            <p><br></p>
        `;
        this.format('insertHTML', cardHtml);
    }

    // 💬 Feature 4: Persistent Inline Annotations & Auditing Bubbles
    addInlineAnnotation() {
        const commentStr = prompt("Enter reviewer comment/annotation details:");
        if (!commentStr?.trim()) return;

        const sel = window.getSelection();
        if (!sel.rangeCount || sel.isCollapsed) return;

        // Apply native DOM wrapper element mapping inline
        const markId = 'annot_' + Date.now();
        const markWrapper = document.createElement('mark');
        markWrapper.id = markId;
        markWrapper.className = 'lekhni-annotation';
        markWrapper.setAttribute('data-comment', commentStr.trim());
        markWrapper.style.background = 'rgba(234, 179, 8, 0.25)';
        markWrapper.style.color = 'inherit';
        markWrapper.style.borderBottom = '2px dotted #eab308';
        markWrapper.style.padding = '0 2px';
        markWrapper.style.borderRadius = '2px';
        markWrapper.style.cursor = 'help';
        markWrapper.title = `Reviewer Annotation: "${commentStr.trim()}"`;

        try {
            const range = sel.getRangeAt(0);
            const content = range.extractContents();
            markWrapper.appendChild(content);
            range.insertNode(markWrapper);
            sel.removeAllRanges();
            this.notify("Annotation note stored inline.", "success");
            this.state.body = this.container.querySelector('.lekhni-body-editable')?.innerHTML || '';
            this.state.isDirty = true;
            this.autoSave();
        } catch (e) {
            this.notify("Failed to wrap multi-level tag annotations.", "error");
        }
    }

    executeSlashCommand(cmdId) {
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        const range = sel.getRangeAt(0);
        const node = range.startContainer;
        if (node && node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent;
            const slashPos = text.lastIndexOf('/');
            if (slashPos !== -1) {
                node.textContent = text.substring(0, slashPos);
                const newRange = document.createRange();
                newRange.setStart(node, slashPos);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
        }
        this.setState({ showSlashMenu: false });

        if (cmdId === 'h1') this.format('formatBlock', 'h1');
        else if (cmdId === 'h2') this.format('formatBlock', 'h2');
        else if (cmdId === 'p') this.format('formatBlock', 'p');
        else if (cmdId === 'quote') this.format('formatBlock', 'blockquote');
        else if (cmdId === 'code') this.insertEmbeddedMonacoBlock();
        else if (cmdId === 'table') this.insertSmartGridTable();
        else if (cmdId === 'tasks') this.insertTasksWidget();
        else if (cmdId === 'pdf') this.insertEmbeddedPdfBlock();
        else if (cmdId === 'card') {
            const u = prompt("Enter web destination URL:");
            if (u) this.insertRichWebCard(u);
        }
        else if (cmdId === 'gallery') {
            // Embed custom standalone skeleton frame block
            this.format('insertHTML', `<div class="lekhni-gallery-grid" style="border: 1px dashed #64748b; padding: 24px; text-align: center; border-radius: 8px; color: #94a3b8; margin: 1rem 0;">🎴 Drag multiple visual media assets directly into the editor view to construct an instant grid array</div><p><br></p>`);
        }
        else if (cmdId === 'ai') this.triggerAICopilot();
    }

    insertEmbeddedMonacoBlock() {
        const editor = this.container.querySelector('.lekhni-body-editable');
        const blockId = 'monaco_node_' + Date.now();
        
        const wrapper = document.createElement('div');
        wrapper.className = 'lekhni-embedded-block'; wrapper.contentEditable = false;
        wrapper.style.margin = '1.5rem 0'; wrapper.style.borderRadius = '8px'; wrapper.style.overflow = 'hidden';

        const header = document.createElement('div');
        header.style.background = '#21262d'; 
        header.style.padding = '6px 12px'; 
        header.style.fontSize = '0.75rem';
        header.style.color = '#8b949e'; 
        header.style.fontFamily = "'JetBrains Mono', monospace";
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        
        const titleSpan = document.createElement('span');
        titleSpan.textContent = 'Inline Code Workspace';
        
        const select = document.createElement('select');
        select.style.background = 'transparent';
        select.style.color = '#58a6ff';
        select.style.border = 'none';
        select.style.outline = 'none';
        select.style.fontFamily = 'inherit';
        select.style.fontSize = 'inherit';
        select.style.cursor = 'pointer';
        
        const languages = [
            { value: 'javascript', label: 'JavaScript' },
            { value: 'html', label: 'HTML / Layout' },
            { value: 'css', label: 'CSS' },
            { value: 'php', label: 'PHP' },
            { value: 'python', label: 'Python' },
            { value: 'sql', label: 'SQL' },
            { value: 'yaml', label: 'YAML' },
            { value: 'json', label: 'JSON' },
            { value: 'markdown', label: 'Markdown' },
            { value: 'typescript', label: 'TypeScript' },
            { value: 'bash', label: 'Shell / Bash' },
            { value: 'rust', label: 'Rust' },
            { value: 'go', label: 'Go' }
        ];
        
        languages.forEach(l => {
            const opt = document.createElement('option');
            opt.value = l.value;
            opt.textContent = l.label;
            opt.style.background = '#161b22';
            opt.style.color = '#c9d1d9';
            select.appendChild(opt);
        });
        
        const controlsGroup = document.createElement('div');
        controlsGroup.style.display = 'flex';
        controlsGroup.style.alignItems = 'center';
        controlsGroup.style.gap = '8px';

        const copyBtn = document.createElement('button');
        copyBtn.className = 'lekhni-monaco-copy-btn';
        copyBtn.textContent = '📋 Copy';
        copyBtn.style.background = 'transparent';
        copyBtn.style.color = '#8b949e';
        copyBtn.style.border = 'none';
        copyBtn.style.cursor = 'pointer';
        copyBtn.style.fontSize = '12px';
        copyBtn.style.padding = '2px 6px';
        copyBtn.style.borderRadius = '4px';
        
        // This onclick won't survive serialization, but the rehydration will pick it up
        copyBtn.onclick = () => {
            const ta = wrapper.querySelector('textarea');
            if (ta) {
                navigator.clipboard.writeText(ta.value || ta.textContent).then(() => {
                    copyBtn.textContent = '✅ Copied';
                    setTimeout(() => copyBtn.textContent = '📋 Copy', 2000);
                });
            }
        };

        controlsGroup.appendChild(select);
        controlsGroup.appendChild(copyBtn);

        header.appendChild(titleSpan);
        header.appendChild(controlsGroup);

        const targetHost = document.createElement('div');
        targetHost.id = blockId; targetHost.style.width = '100%';
        targetHost.setAttribute('data-language', 'javascript');

        wrapper.appendChild(header); wrapper.appendChild(targetHost);

        const sel = window.getSelection();
        if (sel.rangeCount) {
            const range = sel.getRangeAt(0);
            range.insertNode(wrapper);
            const trailing = document.createElement('p'); trailing.innerHTML = '<br>';
            wrapper.parentNode.insertBefore(trailing, wrapper.nextSibling);
            
            const newRange = document.createRange();
            newRange.setStart(trailing, 0); newRange.collapse(true);
            sel.removeAllRanges(); sel.addRange(newRange);
        } else {
            editor.appendChild(wrapper);
        }

        const initVal = '// Code snippet logic...\n';
        targetHost.setAttribute('data-lekhni-hydrated', 'true');
        let instance = LekhniMonaco.create(targetHost, { language: 'javascript', value: initVal });
        
        const ta = targetHost.querySelector('textarea');
        if (ta) ta.textContent = initVal;

        const bindListeners = (inst) => {
            inst.onDidChangeContent((val) => {
                const currentTa = targetHost.querySelector('textarea');
                if (currentTa) currentTa.textContent = val;
                this.state.body = this.getCleanBodyHtml();
                this.state.isDirty = true;
                if (typeof this.autoSave === 'function') this.autoSave();
            });
        };

        bindListeners(instance);

        select.addEventListener('change', (e) => {
            const newLang = e.target.value;
            targetHost.setAttribute('data-language', newLang);
            const currentVal = instance.getValue();
            instance.dispose();
            targetHost.innerHTML = '';
            instance = LekhniMonaco.create(targetHost, { language: newLang, value: currentVal });
            
            const updatedTa = targetHost.querySelector('textarea');
            if (updatedTa) updatedTa.textContent = currentVal;
            
            bindListeners(instance);
            this.state.body = this.getCleanBodyHtml();
            this.state.isDirty = true;
            if (typeof this.autoSave === 'function') this.autoSave();
        });

        this.state.body = this.getCleanBodyHtml();
        this.state.isDirty = true;
    }

    async triggerAICopilot() {
        this.notify("✨ AI Co-Pilot: Generating contextual content...", "info");
        setTimeout(() => {
            this.format('insertHTML', ' <span style="background: rgba(99,102,241,0.15); color: #a5b4fc; padding: 2px 6px; border-radius: 4px;">[AI Completed Layout String]</span> ');
            this.notify("Content stream appended.", "success");
        }, 600);
    }

    insertSmartGridTable() {
        const editor = this.container.querySelector('.lekhni-body-editable');
        const blockId = 'smart_grid_' + Date.now();
        
        const wrapper = document.createElement('div');
        wrapper.className = 'lekhni-embedded-block lekhni-smart-grid-block-wrapper'; 
        wrapper.contentEditable = 'false';
        wrapper.style.margin = '1.5rem 0';
        wrapper.style.borderRadius = '8px';
        wrapper.style.overflow = 'hidden';
        wrapper.style.border = '1px solid #334155';
        wrapper.style.background = '#0f172a';
        wrapper.style.padding = '12px';

        const header = document.createElement('div');
        header.style.background = '#1e293b';
        header.style.padding = '8px 12px';
        header.style.fontSize = '0.8rem';
        header.style.fontWeight = 'bold';
        header.style.color = '#38bdf8';
        header.style.borderBottom = '1px solid #334155';
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.innerHTML = `<span>📊 Smart Grid (Spreadsheet with Formula Calculation)</span>
            <button class="btn btn-sm btn-secondary btn-formula-recalc" style="padding: 2px 8px; font-size: 0.75rem; border-radius: 4px; background: #334155; color: white; border: none; cursor: pointer;">Recalculate</button>`;

        const tableContainer = document.createElement('div');
        tableContainer.style.overflowX = 'auto';

        const table = document.createElement('table');
        table.className = 'lekhni-smart-grid';
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';
        table.style.marginTop = '8px';
        table.style.color = '#cbd5e1';

        // Column Headers A, B, C, D, E
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.innerHTML = `<th style="border: 1px solid #334155; padding: 6px; background: #1e293b; width: 40px; color: #64748b; font-size: 0.75rem;"></th>` +
            ['A', 'B', 'C', 'D', 'E'].map(col => `<th style="border: 1px solid #334155; padding: 6px; background: #1e293b; color: #94a3b8; font-size: 0.75rem; min-width: 80px;">${col}</th>`).join('');
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        for (let r = 1; r <= 5; r++) {
            const tr = document.createElement('tr');
            let rowHTML = `<td style="border: 1px solid #334155; padding: 6px; background: #1e293b; text-align: center; font-weight: bold; color: #64748b; font-size: 0.75rem;">${r}</td>`;
            for (let c = 0; c < 5; c++) {
                const colName = String.fromCharCode(65 + c);
                const cellId = `${colName}${r}`;
                // Populate some initial values for demonstration
                let val = '';
                let formula = '';
                if (r === 1 && c === 0) val = '150';
                else if (r === 2 && c === 0) val = '250';
                else if (r === 3 && c === 0) val = '350';
                else if (r === 4 && c === 0) {
                    val = '750';
                    formula = '=SUM(A1:A3)';
                }
                rowHTML += `<td style="border: 1px solid #334155; padding: 6px; background: #0b0f19; min-width: 80px; position: relative;">
                    <div class="grid-cell-value" style="width: 100%; min-height: 18px; outline: none;" contenteditable="true" data-cell-id="${cellId}" data-formula="${formula}">${val}</div>
                </td>`;
            }
            tr.innerHTML = rowHTML;
            tbody.appendChild(tr);
        }
        table.appendChild(tbody);
        tableContainer.appendChild(table);

        wrapper.appendChild(header);
        wrapper.appendChild(tableContainer);

        const sel = window.getSelection();
        if (sel.rangeCount) {
            const range = sel.getRangeAt(0);
            range.insertNode(wrapper);
            const trailing = document.createElement('p'); trailing.innerHTML = '<br>';
            wrapper.parentNode.insertBefore(trailing, wrapper.nextSibling);
            
            const newRange = document.createRange();
            newRange.setStart(trailing, 0); newRange.collapse(true);
            sel.removeAllRanges(); sel.addRange(newRange);
        } else {
            editor.appendChild(wrapper);
        }

        // Hook Recalculation handler
        const recalcBtn = header.querySelector('.btn-formula-recalc');
        recalcBtn.addEventListener('click', () => this.recalculateGrid(table));

        // Hook input blur event on cells to trigger dynamic recalculation
        table.querySelectorAll('.grid-cell-value').forEach(cell => {
            cell.addEventListener('blur', () => {
                const text = cell.innerText.trim();
                if (text.startsWith('=')) {
                    cell.setAttribute('data-formula', text);
                } else {
                    cell.removeAttribute('data-formula');
                }
                this.recalculateGrid(table);
            });
            cell.addEventListener('focus', () => {
                const formula = cell.getAttribute('data-formula');
                if (formula) {
                    cell.innerText = formula;
                }
            });
        });

        // Trigger initial calculation
        this.recalculateGrid(table);

        this.state.body = this.getCleanBodyHtml();
        this.state.isDirty = true;
    }

    recalculateGrid(table) {
        const cells = table.querySelectorAll('.grid-cell-value');
        const data = {};
        
        cells.forEach(cell => {
            const id = cell.getAttribute('data-cell-id');
            const formula = cell.getAttribute('data-formula');
            let val = cell.innerText.trim();
            
            if (document.activeElement === cell && val.startsWith('=')) {
                return;
            }

            if (formula && formula.startsWith('=')) {
                data[id] = { element: cell, formula: formula, value: null };
            } else {
                const num = parseFloat(val);
                data[id] = { element: cell, formula: null, value: isNaN(num) ? val : num };
            }
        });

        const getVal = (cellId) => {
            const cell = data[cellId];
            if (!cell) return 0;
            if (cell.value !== null) return cell.value;
            if (cell.formula) {
                return evaluateFormula(cell.formula, cellId);
            }
            return 0;
        };

        const evaluateFormula = (formulaStr, currentId) => {
            try {
                const clean = formulaStr.substring(1).toUpperCase().trim();
                const sumMatch = clean.match(/SUM\(([A-Z][0-9]+):([A-Z][0-9]+)\)/);
                const avgMatch = clean.match(/AVERAGE\(([A-Z][0-9]+):([A-Z][0-9]+)\)/);
                const prodMatch = clean.match(/PRODUCT\(([A-Z][0-9]+):([A-Z][0-9]+)\)/);

                const getCellRange = (start, end) => {
                    const startCol = start.charCodeAt(0);
                    const startRow = parseInt(start.substring(1));
                    const endCol = end.charCodeAt(0);
                    const endRow = parseInt(end.substring(1));
                    
                    const values = [];
                    for (let col = Math.min(startCol, endCol); col <= Math.max(startCol, endCol); col++) {
                        for (let row = Math.min(startRow, endRow); row <= Math.max(startRow, endRow); row++) {
                            const cid = `${String.fromCharCode(col)}${row}`;
                            if (cid !== currentId) { 
                                const cellValue = getVal(cid);
                                values.push(typeof cellValue === 'number' ? cellValue : 0);
                            }
                        }
                    }
                    return values;
                };

                if (sumMatch) {
                    const vals = getCellRange(sumMatch[1], sumMatch[2]);
                    return vals.reduce((a, b) => a + b, 0);
                }
                if (avgMatch) {
                    const vals = getCellRange(avgMatch[1], avgMatch[2]);
                    return vals.length ? (vals.reduce((a, b) => a + b, 0) / vals.length) : 0;
                }
                if (prodMatch) {
                    const vals = getCellRange(prodMatch[1], prodMatch[2]);
                    return vals.length ? vals.reduce((a, b) => a * b, 1) : 0;
                }
                return 0;
            } catch (e) {
                console.error("Formula error:", e);
                return '#ERROR';
            }
        };

        Object.keys(data).forEach(id => {
            const cell = data[id];
            if (cell.formula) {
                const result = evaluateFormula(cell.formula, id);
                cell.value = result;
                if (document.activeElement !== cell.element) {
                    cell.element.innerText = result;
                }
            }
        });
    }

    insertTasksWidget() {
        const editor = this.container.querySelector('.lekhni-body-editable');
        const blockId = 'tasks_' + Date.now();

        const wrapper = document.createElement('div');
        wrapper.className = 'lekhni-embedded-block lekhni-tasks-widget-wrapper'; 
        wrapper.contentEditable = 'false';
        wrapper.style.margin = '1.5rem 0';
        wrapper.style.borderRadius = '8px';
        wrapper.style.border = '1px solid #334155';
        wrapper.style.background = '#0f172a';
        wrapper.style.padding = '16px';
        wrapper.style.color = '#f1f5f9';

        const header = document.createElement('div');
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.style.marginBottom = '12px';
        header.innerHTML = `<span style="font-weight:bold; color:#a5b4fc; font-size:0.9rem;">☑️ Project Tasks Checklist</span>
            <button class="btn btn-sm btn-tasks-add" style="padding: 2px 8px; font-size: 0.75rem; border-radius: 4px; background: #6366f1; color: white; border: none; cursor: pointer;">+ Add Item</button>`;

        const listContainer = document.createElement('div');
        listContainer.className = 'tasks-list-container';
        listContainer.style.display = 'flex';
        listContainer.style.flexDirection = 'column';
        listContainer.style.gap = '8px';

        const addTaskItem = (labelText = "New task item...", checked = false) => {
            const item = document.createElement('div');
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.gap = '10px';
            item.style.padding = '6px';
            item.style.background = '#1e293b';
            item.style.borderRadius = '6px';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = checked;
            checkbox.style.cursor = 'pointer';
            checkbox.style.accentColor = '#6366f1';

            const span = document.createElement('span');
            span.contentEditable = 'true';
            span.innerText = labelText;
            span.style.outline = 'none';
            span.style.flexGrow = '1';
            span.style.fontSize = '0.85rem';
            if (checked) {
                span.style.textDecoration = 'line-through';
                span.style.color = '#64748b';
            }

            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    span.style.textDecoration = 'line-through';
                    span.style.color = '#64748b';
                } else {
                    span.style.textDecoration = 'none';
                    span.style.color = '#cbd5e1';
                }
                span.setAttribute('data-checked', checkbox.checked);
                this.state.body = this.getCleanBodyHtml();
                this.state.isDirty = true;
            });

            span.addEventListener('blur', () => {
                this.state.body = this.getCleanBodyHtml();
                this.state.isDirty = true;
            });

            const delBtn = document.createElement('button');
            delBtn.innerHTML = '✕';
            delBtn.style.background = 'transparent';
            delBtn.style.border = 'none';
            delBtn.style.color = '#ef4444';
            delBtn.style.cursor = 'pointer';
            delBtn.style.fontSize = '0.75rem';
            delBtn.addEventListener('click', () => {
                item.remove();
                this.state.body = this.getCleanBodyHtml();
                this.state.isDirty = true;
            });

            item.appendChild(checkbox);
            item.appendChild(span);
            item.appendChild(delBtn);
            listContainer.appendChild(item);
        };

        addTaskItem("Review next-gen workspace engine specifications", true);
        addTaskItem("Verify visual rendering in playground testbed", false);
        addTaskItem("Finalize multi-page visual headers and margins", false);

        wrapper.appendChild(header);
        wrapper.appendChild(listContainer);

        const sel = window.getSelection();
        if (sel.rangeCount) {
            const range = sel.getRangeAt(0);
            range.insertNode(wrapper);
            const trailing = document.createElement('p'); trailing.innerHTML = '<br>';
            wrapper.parentNode.insertBefore(trailing, wrapper.nextSibling);
            
            const newRange = document.createRange();
            newRange.setStart(trailing, 0); newRange.collapse(true);
            sel.removeAllRanges(); sel.addRange(newRange);
        } else {
            editor.appendChild(wrapper);
        }

        header.querySelector('.btn-tasks-add').addEventListener('click', () => {
            addTaskItem();
            this.state.body = this.getCleanBodyHtml();
            this.state.isDirty = true;
        });

        this.state.body = this.getCleanBodyHtml();
        this.state.isDirty = true;
    }

    triggerInlineAIComposer() {
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        const range = sel.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        const containerRect = this.container.getBoundingClientRect();

        this.setState({
            showAIComposerModal: true,
            aiComposerX: rect.left - containerRect.left,
            aiComposerY: rect.bottom - containerRect.top + 8,
            aiComposerQuery: '',
            aiComposerLoading: false
        });
        
        setTimeout(() => {
            const input = this.container.querySelector('.ai-composer-input');
            if (input) input.focus();
        }, 50);
    }

    async submitInlineAIComposer() {
        const query = this.state.aiComposerQuery ? this.state.aiComposerQuery.trim() : '';
        if (!query) return;

        this.setState({ aiComposerLoading: true });
        this.notify("AI is crafting your text...", "info");
        
        setTimeout(() => {
            let aiText = ``;
            if (query.toLowerCase().includes('summary') || query.toLowerCase().includes('summarize')) {
                aiText = `This premium document has been refined and polished by SPP Lekhni's Next-Gen AI engine to deliver maximum readability and concise structured execution outline bounds.`;
            } else if (query.toLowerCase().includes('table') || query.toLowerCase().includes('data')) {
                aiText = `SPP Lekhni Enterprise Workspace delivers unmatched performance, built-in Monaco developer IDE workspaces, interactive formula grids, and standard A4 virtual pagination modes.`;
            } else {
                aiText = `Successfully generated content based on your request "${query}". Lekhni Editor enables professional document composition with real-time responsive formatting blocks.`;
            }

            this.setState({ showAIComposerModal: false, aiComposerLoading: false });
            
            this.format('insertHTML', ` <span style="background: rgba(99,102,241,0.08); border-left: 3px solid #6366f1; padding: 4px 8px; border-radius: 0 4px 4px 0; color: #a5b4fc; font-style: italic;">${aiText}</span> `);
            this.notify("AI text successfully generated and inserted.", "success");
        }, 1000);
    }

    insertEmbeddedPdfBlock() {
        const defaultPdfUrl = 'https://arxiv.org/pdf/1706.03762.pdf';
        
        // Create dynamic premium modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.style.position = 'fixed';
        modalOverlay.style.inset = '0';
        modalOverlay.style.background = 'rgba(15, 23, 42, 0.85)';
        modalOverlay.style.backdropFilter = 'blur(8px)';
        modalOverlay.style.zIndex = '99999';
        modalOverlay.style.display = 'flex';
        modalOverlay.style.alignItems = 'center';
        modalOverlay.style.justifyContent = 'center';
        modalOverlay.style.padding = '2rem';
        modalOverlay.style.fontFamily = "'Inter', sans-serif";

        const modalContent = document.createElement('div');
        modalContent.style.background = '#1e293b';
        modalContent.style.border = '1px solid #334155';
        modalContent.style.borderRadius = '16px';
        modalContent.style.width = '100%';
        modalContent.style.maxWidth = '500px';
        modalContent.style.boxShadow = '0 25px 50px -12px rgba(0,0,0,0.5)';
        modalContent.style.overflow = 'hidden';
        modalContent.style.color = '#f8fafc';
        modalContent.style.animation = 'scaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1)';

        modalContent.innerHTML = `
            <div style="padding: 20px 24px; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; background: #0f172a;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #a5b4fc; display: flex; align-items: center; gap: 8px;">
                    <span>📄</span> Embed PDF Document
                </h3>
                <button class="pdf-modal-close" style="background: transparent; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer; padding: 4px;">✕</button>
            </div>
            <div style="padding: 24px;">
                <!-- Option 1: Paste URL -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; color: #94a3b8; margin-bottom: 8px;">Option 1: Paste PDF Document URL</label>
                    <input class="pdf-url-input" type="text" value="${defaultPdfUrl}" style="width: 100%; background: #0f172a; border: 1px solid #334155; padding: 10px 12px; border-radius: 8px; color: white; font-size: 0.85rem; outline: none; box-sizing: border-box;">
                </div>
                
                <!-- Divider -->
                <div style="display: flex; align-items: center; gap: 12px; margin: 24px 0;">
                    <div style="flex-grow: 1; height: 1px; background: #334155;"></div>
                    <span style="font-size: 0.75rem; color: #64748b; font-weight: bold;">OR</span>
                    <div style="flex-grow: 1; height: 1px; background: #334155;"></div>
                </div>

                <!-- Option 2: Upload File -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; color: #94a3b8; margin-bottom: 8px;">Option 2: Upload Local PDF File</label>
                    <div class="pdf-upload-zone" style="border: 2px dashed #6366f1; background: rgba(99, 102, 241, 0.05); border-radius: 12px; padding: 24px; text-align: center; cursor: pointer; transition: all 0.2s;">
                        <span style="font-size: 2rem; display: block; margin-bottom: 8px;">📤</span>
                        <span style="font-size: 0.85rem; color: #a5b4fc; font-weight: 600; display: block;">Choose a file or drag it here</span>
                        <span style="font-size: 0.72rem; color: #64748b; display: block; margin-top: 4px;">PDF documents up to 50MB</span>
                        <input type="file" class="pdf-file-input" accept="application/pdf" style="display: none;">
                    </div>
                </div>
                
                <div class="pdf-upload-status" style="font-size: 0.78rem; color: #38bdf8; display: none; align-items: center; gap: 8px; margin-top: 8px;">
                    <span style="display: inline-block; width: 12px; height: 12px; border: 2px solid #38bdf8; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                    <span class="pdf-status-text">Uploading to server...</span>
                </div>
            </div>
            <div style="padding: 16px 24px; background: #0f172a; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 12px;">
                <button class="pdf-modal-cancel" style="background: transparent; border: 1px solid #334155; color: #cbd5e1; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; cursor: pointer; font-weight: 600; transition: background 0.15s;">Cancel</button>
                <button class="pdf-modal-submit" style="background: #6366f1; border: none; color: white; padding: 8px 20px; border-radius: 8px; font-size: 0.85rem; cursor: pointer; font-weight: 700; transition: opacity 0.15s;">Embed PDF</button>
            </div>
        `;

        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);

        // Add CSS keyframe for spin and scaleIn
        if (!document.getElementById('pdf-modal-animations-css')) {
            const style = document.createElement('style');
            style.id = 'pdf-modal-animations-css';
            style.innerHTML = `
                @keyframes spin { to { transform: rotate(360deg); } }
                @keyframes scaleIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            `;
            document.head.appendChild(style);
        }

        const closeBtn = modalContent.querySelector('.pdf-modal-close');
        const cancelBtn = modalContent.querySelector('.pdf-modal-cancel');
        const submitBtn = modalContent.querySelector('.pdf-modal-submit');
        const urlInput = modalContent.querySelector('.pdf-url-input');
        const uploadZone = modalContent.querySelector('.pdf-upload-zone');
        const fileInput = modalContent.querySelector('.pdf-file-input');
        const statusContainer = modalContent.querySelector('.pdf-upload-status');
        const statusText = modalContent.querySelector('.pdf-status-text');

        const closeModal = () => {
            modalOverlay.remove();
        };

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        // Upload zone events
        uploadZone.addEventListener('click', () => fileInput.click());

        // Drag & Drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '#4ade80';
            uploadZone.style.background = 'rgba(74, 222, 128, 0.05)';
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.style.borderColor = '#6366f1';
            uploadZone.style.background = 'rgba(99, 102, 241, 0.05)';
        });

        const performUpload = async (file) => {
            if (!file) return;
            if (file.type !== 'application/pdf') {
                statusContainer.style.display = 'flex';
                statusContainer.style.color = '#ef4444';
                statusText.innerText = 'Only PDF files are allowed.';
                return;
            }

            statusContainer.style.display = 'flex';
            statusContainer.style.color = '#38bdf8';
            statusText.innerText = `Uploading "${file.name}"...`;

            const formData = new FormData();
            formData.append('file', file);

            try {
                const res = await this.uploadMediaForm(formData);
                if (res?.success && res.url) {
                    statusContainer.style.color = '#4ade80';
                    statusText.innerText = 'Upload successful!';
                    urlInput.value = window.location.origin + res.url;
                    // Automatically click submit to embed
                    submitBtn.click();
                } else {
                    statusContainer.style.color = '#ef4444';
                    statusText.innerText = res?.message || 'Upload failed.';
                }
            } catch (err) {
                statusContainer.style.color = '#ef4444';
                statusText.innerText = 'Connection error during upload.';
            }
        };

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '#6366f1';
            uploadZone.style.background = 'rgba(99, 102, 241, 0.05)';
            const file = e.dataTransfer?.files?.[0];
            performUpload(file);
        });

        fileInput.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            performUpload(file);
        });

        submitBtn.addEventListener('click', () => {
            const pdfUrl = urlInput.value.trim();
            if (!pdfUrl) {
                alert('Please enter or upload a valid PDF document.');
                return;
            }
            closeModal();
            this.insertEmbeddedPdfBlockMarkup(pdfUrl);
        });
    }

    insertEmbeddedPdfBlockMarkup(pdfUrl) {
        const editor = this.container.querySelector('.lekhni-body-editable');
        const blockId = 'pdf_block_' + Date.now();

        const wrapper = document.createElement('div');
        wrapper.id = blockId;
        wrapper.className = 'lekhni-embedded-block lekhni-pdf-block-wrapper';
        wrapper.contentEditable = 'false';
        wrapper.style.margin = '1.5rem auto';
        wrapper.style.borderRadius = '12px';
        wrapper.style.border = '1px solid #334155';
        wrapper.style.background = '#0f172a';
        wrapper.style.overflow = 'hidden';
        wrapper.style.maxWidth = '100%';
        wrapper.style.width = '100%';
        wrapper.style.boxShadow = '0 10px 30px rgba(0,0,0,0.4)';

        // PDF Block Header and Controls
        const header = document.createElement('div');
        header.style.background = '#1e293b';
        header.style.padding = '10px 16px';
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.style.borderBottom = '1px solid #334155';
        header.style.fontFamily = "'Inter', sans-serif";
        header.style.color = '#cbd5e1';

        header.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.1rem;">📄</span>
                <span style="font-weight: bold; font-size: 0.85rem; color: #a5b4fc;">Interactive PDF Viewer</span>
            </div>
            <div style="display: flex; align-items: center; gap: 14px; font-size: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="color: #94a3b8;">Width:</label>
                    <input class="pdf-width-slider" type="range" min="300" max="800" value="794" style="width: 80px; accent-color: #6366f1;">
                    <span class="pdf-width-val" style="color: #38bdf8;">794px</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="color: #94a3b8;">Height:</label>
                    <input class="pdf-height-slider" type="range" min="200" max="1000" value="500" style="width: 80px; accent-color: #6366f1;">
                    <span class="pdf-height-val" style="color: #38bdf8;">500px</span>
                </div>
                <button class="btn-pdf-delete" style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 0.85rem; padding: 2px 6px;">✕ Remove</button>
            </div>
        `;

        const iframeContainer = document.createElement('div');
        iframeContainer.style.width = '100%';
        iframeContainer.style.background = '#1e293b';
        iframeContainer.style.display = 'flex';
        iframeContainer.style.justifyContent = 'center';
        iframeContainer.style.padding = '10px';

        const iframe = document.createElement('iframe');
        iframe.className = 'pdf-embedded-iframe';
        iframe.src = pdfUrl;
        iframe.style.width = '794px';
        iframe.style.height = '500px';
        iframe.style.border = 'none';
        iframe.style.background = '#ffffff';
        iframe.style.borderRadius = '6px';
        iframe.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';

        iframeContainer.appendChild(iframe);
        wrapper.appendChild(header);
        wrapper.appendChild(iframeContainer);

        const sel = window.getSelection();
        if (sel.rangeCount) {
            const range = sel.getRangeAt(0);
            range.insertNode(wrapper);
            const trailing = document.createElement('p'); trailing.innerHTML = '<br>';
            wrapper.parentNode.insertBefore(trailing, wrapper.nextSibling);
            
            const newRange = document.createRange();
            newRange.setStart(trailing, 0); newRange.collapse(true);
            sel.removeAllRanges(); sel.addRange(newRange);
        } else {
            editor.appendChild(wrapper);
        }

        const widthSlider = header.querySelector('.pdf-width-slider');
        const widthVal = header.querySelector('.pdf-width-val');
        const heightSlider = header.querySelector('.pdf-height-slider');
        const heightVal = header.querySelector('.pdf-height-val');
        const deleteBtn = header.querySelector('.btn-pdf-delete');

        widthSlider.addEventListener('input', (e) => {
            const val = e.target.value;
            iframe.style.width = `${val}px`;
            widthVal.innerText = `${val}px`;
            this.state.body = this.getCleanBodyHtml();
            this.state.isDirty = true;
        });

        heightSlider.addEventListener('input', (e) => {
            const val = e.target.value;
            iframe.style.height = `${val}px`;
            heightVal.innerText = `${val}px`;
            this.state.body = this.getCleanBodyHtml();
            this.state.isDirty = true;
        });

        deleteBtn.addEventListener('click', () => {
            wrapper.remove();
            this.state.body = this.getCleanBodyHtml();
            this.state.isDirty = true;
        });

        this.state.body = this.getCleanBodyHtml();
        this.state.isDirty = true;
    }

    async handlePaste(e) {
        const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
        if (!items) return;
        const fileBatch = [];
        for (const item of items) {
            if (item.type.indexOf('image') !== -1) fileBatch.push(item.getAsFile());
        }
        if (fileBatch.length) {
            e.preventDefault();
            await this.processMediaFilesBatch(fileBatch);
        }
    }

    format(cmd, val = null) {
        const sel = window.getSelection();
        if (sel.rangeCount && !sel.isCollapsed && ['bold', 'italic', 'underline', 'createLink'].includes(cmd)) {
            const range = sel.getRangeAt(0);
            const tagMap = { bold: 'strong', italic: 'em', underline: 'u', createLink: 'a' };
            const tagName = tagMap[cmd];
            
            const wrapper = document.createElement(tagName);
            if (cmd === 'createLink') {
                wrapper.href = val;
                wrapper.target = '_blank';
                wrapper.style.color = '#58a6ff';
                wrapper.style.textDecoration = 'underline';
            }
            
            try {
                wrapper.appendChild(range.extractContents());
                range.insertNode(wrapper);
                sel.removeAllRanges();
                const newRange = document.createRange();
                newRange.selectNodeContents(wrapper);
                sel.addRange(newRange);
            } catch (e) {
                // Fallback to native command if range manipulation throws
                document.execCommand(cmd, false, val);
            }
        } else {
            document.execCommand(cmd, false, val);
        }

        const editor = this.container.querySelector('.lekhni-body-editable');
        if (editor) editor.focus();
        this.setState({ showBubbleMenu: false });
        this.autoSave();
    }

    handleTitleInput(e) {
        const val = e.target.value; this.state.title = val; this.state.isDirty = true;
        if (!this.state.manualAlias) {
            this.state.alias = val.toLowerCase().replace(/[^\w\s-]/g, '').replace(/[\s_-]+/g, '-').replace(/^-+|-+$/g, '');
        }
        this.autoSave();
    }

    async autoSave() {
        if (this.saveTimer) clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => this.save(false), 2000);
        if (this.props?.onChange) this.props.onChange(this.state.body);
        // Sync straight to offline IndexedDB background buffer loop safely
        this.commitBufferToOfflineStore();
    }

    async save(showNotify = true) {
        if (!this.state.isDirty && this.state.id) return;
        if (this.state.editorMode === 'code' && this._monacoIdeInstance) {
            this.state.body = this._monacoIdeInstance.getValue();
        }

        this.setState({ saving: true });
        try {
            const apiCall = this.admin?.api ? this.admin.api : async (act, d) => ({ success: true, id: d.id || 'node_gen', message: 'Saved successfully' });
            const res = await apiCall('save_node', {
                id: this.state.id, title: this.state.title, body: this.state.body, status: this.state.status, alias: this.state.alias, bundle: this.state.bundle
            });
            if (res.success) {
                const savedId = res.id ?? res.data?.id ?? this.state.id;
                const savedAlias = res.alias ?? res.data?.alias ?? this.state.alias;
                this.setState({ id: savedId, alias: savedAlias, saving: false, lastSaved: new Date().toLocaleTimeString(), isDirty: false });
                if (showNotify) this.notify(res.message || "Document saved", 'success');
                // Store permanent sequential revision entry milestone
                this.captureRevisionSnapshot('Server Save');
            } else {
                this.setState({ saving: false });
                if (showNotify) this.notify(res.message || "Save failed", 'error');
            }
        } catch (e) {
            this.setState({ saving: false });
            if (showNotify) this.notify('Save failure', 'error');
        }
    }

    async uploadMediaForm(formData) {
        const apiBase = this.admin?.config?.apiBase || 'resources/admin-api.php';
        const url = new URL(apiBase, window.location.href);
        url.searchParams.set('action', 'lekhni_upload_media');
        const response = await fetch(url.toString(), { method: 'POST', body: formData });
        if (!response.ok) throw new Error(`Upload failed with HTTP ${response.status}`);
        return response.json();
    }

    async publish() { this.state.status = 'published'; this.state.isDirty = true; await this.save(true); }
    notify(msg, type = 'info') { if (this.admin?.notify) this.admin.notify(msg, type); else console.log(`[Lekhni ${type}] ${msg}`); }

    render() {
        const { title, status, saving, lastSaved, alias, tags, category, bundle, bundles, embedded, editorMode, codeLanguage, categories, outline, revisions, showHistoryModal, selectedRevisionIndex, hasOfflineSnapshot, showSlashMenu, slashX, slashY, slashFilter, slashIndex, showBubbleMenu, bubbleX, bubbleY, slashMenuEnabled, bubbleMenuEnabled, showAIComposerModal, aiComposerX, aiComposerY, aiComposerQuery, aiComposerLoading, printModeEnabled, showPasteOptions, pasteOptionsX, pasteOptionsY, pasteOptions, activePasteBlockId } = this.state;
        const filteredSlash = this.slashCommands.filter(c => c.label.toLowerCase().includes(slashFilter));

        return html`
            <div class="lekhni-editor-wrapper ${embedded ? 'mode-embedded' : 'mode-fullscreen'}" style="position: relative;">
                ${!embedded ? html`
                    <nav class="lekhni-nav">
                        <div class="nav-left">
                            <button class="btn-icon" @click="${() => this.back()}" title="Back">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"></path></svg>
                            </button>
                            <div class="workspace-mode-switch" style="display: flex; background: #0f172a; padding: 3px; border-radius: 6px; border: 1px solid #334155;">
                                <button class="mode-tab ${editorMode === 'document' ? 'active' : ''}" @click="${() => this.setEditorMode('document')}">📝 Document</button>
                                <button class="mode-tab ${editorMode === 'code' ? 'active' : ''}" @click="${() => this.setEditorMode('code')}">💻 Code Editor</button>
                            </div>
                        </div>
                        <div class="nav-actions">
                            ${revisions.length > 0 ? html`
                                <button class="btn-secondary btn-sm" @click="${() => this.setState({ showHistoryModal: true, selectedRevisionIndex: revisions.length - 1 })}" title="View historical revision diff snapshots">
                                    🕰️ History (${revisions.length})
                                </button>
                            ` : ''}
                            <button class="btn-secondary" @click="${() => this.setState({ printModeEnabled: !printModeEnabled })}" style="font-size: 0.8rem; margin: 0 4px;">
                                ${printModeEnabled ? '📄 Normal View' : '🖨️ Print View'}
                            </button>
                            <span class="save-status" style="align-self: center; margin: 0 10px;">${saving ? 'Saving...' : (lastSaved ? `Saved at ${lastSaved}` : 'Draft')}</span>
                            <button class="btn-secondary" @click="${() => this.save(true)}">Save Draft</button>
                            <button class="btn-primary" @click="${() => this.publish()}">${status === 'published' ? 'Update' : 'Publish'}</button>
                        </div>
                    </nav>
                ` : html`
                    <div class="embedded-header-tabs" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 16px; background: rgba(30,41,59,0.8); border-bottom: 1px solid #334155; border-radius: 8px 8px 0 0;">
                        <span style="font-size: 0.8rem; font-weight: bold; color: #94a3b8;">Lekhni Instance</span>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            ${revisions.length > 0 ? html`
                                <button class="btn-icon" @click="${() => this.setState({ showHistoryModal: true, selectedRevisionIndex: revisions.length - 1 })}" style="font-size: 0.75rem;">🕰️ Timeline</button>
                            ` : ''}
                            <div class="workspace-mode-switch" style="display: flex; background: #0f172a; padding: 2px; border-radius: 4px;">
                                <button class="mode-tab btn-sm ${editorMode === 'document' ? 'active' : ''}" @click="${() => this.setEditorMode('document')}">Document</button>
                                <button class="mode-tab btn-sm ${editorMode === 'code' ? 'active' : ''}" @click="${() => this.setEditorMode('code')}">Code Editor</button>
                            </div>
                        </div>
                    </div>
                `}

                <!-- Feature 5 Alert: Display warning banner whenever disconnected changes exist inside local cache buffer -->
                ${hasOfflineSnapshot ? html`
                    <div class="offline-recovery-banner fade-in" style="background: #ca8a04; color: white; padding: 8px 16px; font-size: 0.85rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
                        <span>⚠️ Discovered unsaved editing snapshot captured securely offline inside IndexedDB tables.</span>
                        <div style="display: flex; gap: 8px;">
                            <button @click="${() => this.restoreOfflineSnapshot()}" style="background: #0f172a; color: #facc15; border: none; padding: 4px 10px; border-radius: 4px; font-weight: bold; cursor: pointer;">Restore Data</button>
                            <button @click="${() => this.discardOfflineSnapshot()}" style="background: transparent; color: white; border: 1px solid rgba(255,255,255,0.4); padding: 4px 10px; border-radius: 4px; cursor: pointer;">Discard</button>
                        </div>
                    </div>
                ` : ''}

                <div class="lekhni-workspace" style="display: flex; flex-grow: 1; height: ${embedded ? '350px' : '100%'}; min-height: 0; overflow: hidden; background: ${embedded ? 'transparent' : '#0f172a'};">
                    <main class="lekhni-main" style="flex-grow: 1; display: flex; flex-direction: column; position: relative; width: 100%; height: 100%; overflow: hidden;">
                        ${editorMode === 'document' ? html`
                            ${this.renderToolbar()}

                            <div class="lekhni-canvas-viewport" style="flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; min-height: 0; width: 100%;">
                                <div class="lekhni-canvas ${printModeEnabled ? 'canvas-print-preview' : ''}" style="padding: ${embedded ? '1.5rem 1rem' : '4rem 2rem'}; max-width: ${embedded ? '100%' : '800px'}; margin: 0 auto; width: 100%;">
                                    ${!embedded ? html`
                                        <input type="text" class="lekhni-title-input" placeholder="Document Title" .value="${title}" @input="${(e) => this.handleTitleInput(e)}" style="${printModeEnabled ? 'color: white; max-width: 794px; margin: 0 auto 1.5rem auto; display: block;' : ''}">
                                    ` : ''}
                                    <div class="lekhni-body-editable ${printModeEnabled ? 'mode-print-preview' : ''}" contenteditable="true" style="min-height: ${embedded ? '250px' : '500px'}; outline: none; color: #cbd5e1; font-size: 1.1rem; line-height: 1.8;"></div>
                                </div>
                            </div>
                        ` : html`
                            <!-- VSCode Full Bleed IDE Canvas -->
                            <div class="lekhni-ide-wrapper fade-in" style="display: flex; flex-direction: column; flex-grow: 1; width: 100%; height: 100%; background: #0d1117;">
                                <div class="ide-file-tabs" style="background: #161b22; border-bottom: 1px solid #30363d; padding: 4px 16px; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="color: #58a6ff; font-family: 'JetBrains Mono', monospace; font-size: 0.8rem;">📄 document.${codeLanguage}</span>
                                        <span style="color: #8b949e; font-size: 0.7rem;">— Active VSCode Workspace Engine</span>
                                    </div>
                                    <select style="background: #0d1117; color: #58a6ff; border: 1px solid #30363d; border-radius: 4px; padding: 2px 6px; font-size: 0.75rem;" @change="${(e) => { this.setState({ codeLanguage: e.target.value }); setTimeout(() => this.mountFullBleedMonacoIde(), 20); }}">
                                        <option value="html" ?selected="${codeLanguage==='html'}">HTML / Layout</option>
                                        <option value="json" ?selected="${codeLanguage==='json'}">JSON / Blueprint</option>
                                        <option value="javascript" ?selected="${codeLanguage==='javascript'}">JavaScript</option>
                                        <option value="yaml" ?selected="${codeLanguage==='yaml'}">YAML</option>
                                        <option value="css" ?selected="${codeLanguage==='css'}">CSS</option>
                                        <option value="php" ?selected="${codeLanguage==='php'}">PHP</option>
                                        <option value="python" ?selected="${codeLanguage==='python'}">Python</option>
                                        <option value="sql" ?selected="${codeLanguage==='sql'}">SQL</option>
                                        <option value="markdown" ?selected="${codeLanguage==='markdown'}">Markdown</option>
                                        <option value="typescript" ?selected="${codeLanguage==='typescript'}">TypeScript</option>
                                        <option value="bash" ?selected="${codeLanguage==='bash'}">Shell / Bash</option>
                                        <option value="rust" ?selected="${codeLanguage==='rust'}">Rust</option>
                                        <option value="go" ?selected="${codeLanguage==='go'}">Go</option>
                                    </select>
                                </div>
                                <div class="lekhni-full-ide-host" style="flex-grow: 1; width: 100%;"></div>
                            </div>
                        `}

                        <!-- Floating Menus Overlay Over Document Mode -->
                        ${showSlashMenu && slashMenuEnabled ? html`
                            <div class="lekhni-slash-menu fade-in" style="position: absolute; left: ${slashX}px; top: ${slashY}px; background: #1e293b; border: 1px solid #334155; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5); width: 220px; z-index: 100; overflow: hidden;">
                                <div style="padding: 6px 12px; font-size: 0.7rem; color: #64748b; background: #0f172a; text-transform: uppercase; font-weight: 600;">Blocks (${filteredSlash.length})</div>
                                <div class="slash-items-list" style="max-height: 240px; overflow-y: auto;">
                                    ${filteredSlash.map((cmd, idx) => html`
                                        <div class="slash-item ${idx === slashIndex ? 'active' : ''}" @click="${() => this.executeSlashCommand(cmd.id)}" style="padding: 8px 12px; display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                            <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: rgba(255,255,255,0.05); border-radius: 4px; font-size: 0.75rem; font-weight: bold; color: #a5b4fc;">${cmd.icon}</span>
                                            <div>
                                                <div style="font-size: 0.85rem; font-weight: 500; color: #f1f5f9;">${cmd.label}</div>
                                                <div style="font-size: 0.7rem; color: #94a3b8;">${cmd.desc}</div>
                                            </div>
                                        </div>
                                    `)}
                                </div>
                            </div>
                        ` : ''}

                        ${showBubbleMenu && bubbleMenuEnabled ? html`
                            <div class="lekhni-bubble-menu fade-in" style="position: absolute; left: ${bubbleX}px; top: ${bubbleY}px; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); border: 1px solid #334155; border-radius: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); padding: 4px 8px; display: flex; gap: 4px; z-index: 110; align-items: center;">
                                <button @click="${() => this.format('bold')}" style="font-weight: bold;">B</button>
                                <button @click="${() => this.format('italic')}" style="font-style: italic;">I</button>
                                <button @click="${() => this.format('underline')}" style="text-decoration: underline;">U</button>
                                <div class="divider" style="height:14px;"></div>
                                <button @click="${() => { const url = prompt('Link URL:'); if (url) this.format('createLink', url); }}" title="Link String">🔗</button>
                                <button @click="${() => this.addInlineAnnotation()}" title="Attach Inline Persistent Reviewer Annotation Note" style="color: #facc15;">💬 Annotate</button>
                            </div>
                        ` : ''}

                        ${showAIComposerModal ? html`
                            <div class="lekhni-ai-composer fade-in" style="position: absolute; left: ${aiComposerX}px; top: ${aiComposerY}px; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(12px); border: 1px solid #334155; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); width: 340px; z-index: 120; padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                                <div style="font-size: 0.8rem; font-weight: bold; color: #a5b4fc; display: flex; align-items: center; gap: 6px;">
                                    <span>✨ AI Composer Assistant</span>
                                </div>
                                <input type="text" class="ai-composer-input" placeholder="Ask AI to write anything... (e.g. write a summary)" style="width: 100%; background: #0f172a; border: 1px solid #334155; padding: 8px; border-radius: 6px; color: white; font-size: 0.85rem; outline: none;" .value="${aiComposerQuery || ''}" @input="${(e) => this.setState({ aiComposerQuery: e.target.value })}" @keydown="${(e) => { if (e.key === 'Enter') this.submitInlineAIComposer(); else if (e.key === 'Escape') this.setState({ showAIComposerModal: false }); }}">
                                <div style="display: flex; justify-content: flex-end; gap: 6px;">
                                    <button class="btn btn-sm btn-secondary" @click="${() => this.setState({ showAIComposerModal: false })}" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 4px; border: 1px solid #334155; background: transparent; color: white; cursor: pointer;">Cancel</button>
                                    <button class="btn btn-sm btn-primary" @click="${() => this.submitInlineAIComposer()}" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 4px; background: #6366f1; border: none; color: white; cursor: pointer;">${aiComposerLoading ? 'Writing...' : 'Generate'}</button>
                                </div>
                            </div>
                        ` : ''}
                    </main>

                    ${!embedded && editorMode === 'document' ? html`
                        <aside class="lekhni-sidebar" style="width: 320px; background: #1e293b; border-left: 1px solid #334155; padding: 1.5rem; flex-shrink: 0; display: flex; flex-direction: column; gap: 2rem; overflow-y: auto; height: 100%; max-height: 100%;">
                            <!-- Feature 1 Sidebar Section: Document Outline Scroll Spy -->
                            <div class="sidebar-section outline-tracker-section">
                                <h4>📑 Document Outline</h4>
                                ${outline.length > 0 ? html`
                                    <div class="outline-items" style="display: flex; flex-direction: column; gap: 6px; max-height: 220px; overflow-y: auto;">
                                        ${outline.map(o => html`
                                            <div @click="${() => this.scrollToOutlineAnchor(o.id)}" style="font-size: 0.8rem; color: ${o.level === 'h1' ? '#cbd5e1' : '#64748b'}; padding-left: ${o.level === 'h1' ? '0' : '12px'}; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 0.15s;">
                                                ${o.level === 'h1' ? '▪ ' : '▫ '}${o.label}
                                            </div>
                                        `)}
                                    </div>
                                ` : html`
                                    <div style="font-size: 0.75rem; color: #64748b; font-style: italic;">No H1 or H2 structural headers parsed yet. Type # or ## block labels directly to construct indexes seamlessly.</div>
                                `}
                            </div>

                            <div class="sidebar-section">
                                <h4>🚀 Publishing</h4>
                                <div class="field"><label>URL Alias</label><input type="text" .value="${alias}" @input="${(e) => { this.state.alias = e.target.value; this.state.manualAlias = true; this.state.isDirty = true; }}"></div>
                                <div class="field">
                                    <label>Status</label>
                                    <select .value="${status}" @change="${(e) => { this.state.status = e.target.value; this.state.isDirty = true; }}"><option value="draft">Draft</option><option value="published">Published</option></select>
                                </div>
                            </div>

                            <div class="sidebar-section">
                                <h4>🏷️ Classification</h4>
                                <div class="field">
                                    <label>Content Type Bundle</label>
                                    <select .value="${bundle}" @change="${(e) => { this.state.bundle = e.target.value; this.state.isDirty = true; }}">
                                        ${bundles.map(b => html`<option ?selected="${b===bundle}">${b}</option>`)}
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Category</label>
                                    <select .value="${category}" @change="${(e) => this.setState({ category: e.target.value })}">
                                        ${categories.map(cat => html`<option ?selected="${cat===category}">${cat}</option>`)}
                                    </select>
                                </div>
                                <div class="field"><label>Tags</label><input type="text" placeholder="Add tags..." .value="${tags}" @input="${(e) => this.setState({ tags: e.target.value })}"></div>
                            </div>
                        </aside>
                    ` : ''}
                </div>

                <!-- Feature 6 Popover Modal: Visual Revision Time Machine Diff Viewer -->
                ${showHistoryModal ? html`
                    <div class="lekhni-modal-overlay fade-in" style="position: fixed; inset: 0; background: rgba(15,23,42,0.85); backdrop-filter: blur(8px); z-index: 3000; display: flex; align-items: center; justify-content: center; padding: 2rem;">
                        <div class="lekhni-modal-content" style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; width: 100%; max-width: 900px; height: 80vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
                            <div style="padding: 1rem 1.5rem; background: #0f172a; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span style="font-size: 1.1rem; font-weight: bold; color: white;">🕰️ Revision History & Data Diff</span>
                                    <span style="font-size: 0.8rem; color: #64748b; margin-left: 8px;">— Safely inspect and restore historical buffers</span>
                                </div>
                                <button @click="${() => this.setState({ showHistoryModal: false })}" style="background: transparent; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer;">✕</button>
                            </div>
                            <div style="display: flex; flex-grow: 1; overflow: hidden;">
                                <div style="width: 240px; background: #0f172a; border-right: 1px solid #334155; overflow-y: auto; padding: 1rem;">
                                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; margin-bottom: 12px; font-weight: bold;">Captured Revisions</div>
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        ${revisions.map((rev, idx) => html`
                                            <div @click="${() => this.setState({ selectedRevisionIndex: idx })}" style="padding: 8px 12px; border-radius: 6px; background: ${idx === selectedRevisionIndex ? '#334155' : 'transparent'}; cursor: pointer; border: 1px solid ${idx === selectedRevisionIndex ? '#6366f1' : 'transparent'}; transition: all 0.15s;">
                                                <div style="font-size: 0.85rem; font-weight: bold; color: ${idx === selectedRevisionIndex ? 'white' : '#cbd5e1'};">${rev.label || 'Update'}</div>
                                                <div style="font-size: 0.7rem; color: #94a3b8;">${rev.timestamp}</div>
                                            </div>
                                        `)}
                                    </div>
                                </div>
                                <div style="flex-grow: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; background: #0f172a;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                        <span style="font-size: 0.85rem; color: #94a3b8; font-family: monospace;">Snapshot Markup payload preview string</span>
                                        <button class="btn-primary btn-sm" @click="${() => this.restoreHistoricalRevision(selectedRevisionIndex)}">Restore This Revision Buffer</button>
                                    </div>
                                    <div style="background: #0d1117; border: 1px solid #30363d; border-radius: 6px; padding: 1rem; color: #e6edf3; font-family: monospace; font-size: 0.85rem; line-height: 1.6; white-space: pre-wrap; flex-grow: 1; overflow-y: auto;">${revisions[selectedRevisionIndex]?.body || '(Empty Data string)'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                ` : ''}

                ${showPasteOptions ? html`
                    <div class="lekhni-paste-popover fade-in" style="position: absolute; left: ${pasteOptionsX}px; top: ${pasteOptionsY}px; z-index: 2500; background: rgba(15, 23, 42, 0.9); border: 1px solid #334155; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); backdrop-filter: blur(12px); padding: 12px; display: flex; flex-direction: column; gap: 8px; width: 230px; font-family: 'Inter', sans-serif;">
                        <div style="font-size: 0.8rem; font-weight: bold; color: #a5b4fc; border-bottom: 1px solid #334155; padding-bottom: 6px; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 6px;">📋 ${activePasteBlockId ? 'Paste Options' : 'Paste Preferences'}</span>
                            <button @click="${() => this.finalizePaste()}" style="background: transparent; border: none; color: #64748b; font-size: 0.8rem; cursor: pointer; padding: 2px; transition: color 0.2s;">✕</button>
                        </div>
                        ${activePasteBlockId ? html`
                            <button class="paste-opt-btn" @click="${() => this.applyPastePreset('default')}" style="text-align: left; padding: 6px 10px; border-radius: 6px; background: ${(!pasteOptions.avoidBgColor && !pasteOptions.avoidFgColor && !pasteOptions.avoidFont && !pasteOptions.plainText) ? 'rgba(99, 102, 241, 0.2)' : 'transparent'}; border: none; color: white; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; font-weight: 500;">
                                <span>📄</span> Keep Source Formatting
                            </button>
                            <button class="paste-opt-btn" @click="${() => this.applyPastePreset('plain')}" style="text-align: left; padding: 6px 10px; border-radius: 6px; background: ${pasteOptions.plainText ? 'rgba(99, 102, 241, 0.2)' : 'transparent'}; border: none; color: white; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; font-weight: 500;">
                                <span>📝</span> Paste as Plain Text
                            </button>
                            <div style="font-size: 0.7rem; text-transform: uppercase; color: #64748b; padding: 4px 8px; font-weight: bold; border-top: 1px solid #334155; margin-top: 4px; letter-spacing: 0.05em;">Paste Special Filters</div>
                        ` : ''}
                        
                        <label style="display: flex; align-items: center; gap: 10px; padding: 6px 8px; color: #cbd5e1; font-size: 0.8rem; cursor: pointer; margin: 0; border-radius: 6px; transition: background 0.2s;">
                            <input type="checkbox" ?checked="${pasteOptions.avoidBgColor}" @change="${(e) => this.togglePasteOption('avoidBgColor', e.target.checked)}" style="accent-color: #6366f1; cursor: pointer; width: 14px; height: 14px; border-radius: 4px;">
                            Avoid Background Color
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; padding: 6px 8px; color: #cbd5e1; font-size: 0.8rem; cursor: pointer; margin: 0; border-radius: 6px; transition: background 0.2s;">
                            <input type="checkbox" ?checked="${pasteOptions.avoidFgColor}" @change="${(e) => this.togglePasteOption('avoidFgColor', e.target.checked)}" style="accent-color: #6366f1; cursor: pointer; width: 14px; height: 14px; border-radius: 4px;">
                            Avoid Text Color
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; padding: 6px 8px; color: #cbd5e1; font-size: 0.8rem; cursor: pointer; margin: 0; border-radius: 6px; transition: background 0.2s;">
                            <input type="checkbox" ?checked="${pasteOptions.avoidFont}" @change="${(e) => this.togglePasteOption('avoidFont', e.target.checked)}" style="accent-color: #6366f1; cursor: pointer; width: 14px; height: 14px; border-radius: 4px;">
                            Avoid Custom Fonts
                        </label>
                    </div>
                ` : ''}
            </div>

            <style>
                /* Scoped Canvas Viewport with Premium custom scrollbar matching dark-mode glassmorphic theme */
                .lekhni-canvas-viewport {
                    scrollbar-width: thin;
                    scrollbar-color: #334155 #0f172a;
                }
                .lekhni-canvas-viewport::-webkit-scrollbar {
                    width: 8px;
                }
                .lekhni-canvas-viewport::-webkit-scrollbar-track {
                    background: #0f172a;
                }
                .lekhni-canvas-viewport::-webkit-scrollbar-thumb {
                    background: #334155;
                    border-radius: 4px;
                }
                .lekhni-canvas-viewport::-webkit-scrollbar-thumb:hover {
                    background: #6366f1;
                }

                /* Next-Gen Print Layout & Margins */
                .lekhni-canvas.canvas-print-preview {
                    max-width: 100% !important;
                    padding: 2rem 0 !important;
                    background: #090d16 !important;
                }
                .lekhni-body-editable.mode-print-preview {
                    width: 794px !important;
                    min-height: 1123px !important;
                    margin: 0 auto !important;
                    background: #ffffff !important;
                    color: #1e293b !important;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.5) !important;
                    padding: 80px 60px !important;
                    box-sizing: border-box !important;
                    border-radius: 6px !important;
                    position: relative !important;
                    background-image: linear-gradient(to bottom, transparent 1122px, #e2e8f0 1122px, #e2e8f0 1123px, transparent 1123px) !important;
                    background-size: 100% 1123px !important;
                }
                .lekhni-body-editable.mode-print-preview h1 { color: #0f172a !important; }
                .lekhni-body-editable.mode-print-preview h2 { color: #1e293b !important; }
                .lekhni-body-editable.mode-print-preview p { color: #334155 !important; }
                .lekhni-body-editable.mode-print-preview blockquote { color: #475569 !important; border-left-color: #818cf8 !important; }

                /* Smart Formula Grid Styles */
                .lekhni-smart-grid th, .lekhni-smart-grid td {
                    border: 1px solid #334155 !important;
                    padding: 6px !important;
                    font-size: 0.85rem !important;
                }
                .lekhni-smart-grid th {
                    background: #1e293b !important;
                    color: #94a3b8 !important;
                    font-weight: 600 !important;
                    text-align: center !important;
                }
                .lekhni-smart-grid td {
                    background: #0f172a !important;
                }
                .lekhni-body-editable.mode-print-preview .lekhni-smart-grid td {
                    background: #f8fafc !important;
                    color: #334155 !important;
                    border-color: #cbd5e1 !important;
                }
                .lekhni-body-editable.mode-print-preview .lekhni-smart-grid th {
                    background: #e2e8f0 !important;
                    color: #475569 !important;
                    border-color: #cbd5e1 !important;
                }
                .grid-cell-value:focus {
                    background: rgba(99, 102, 241, 0.1) !important;
                    outline: 1px solid #6366f1 !important;
                    border-radius: 2px !important;
                }

                .lekhni-editor-wrapper.mode-fullscreen { position: relative; height: 100%; max-height: 100%; min-height: calc(100vh - 120px); background: #0f172a; display: flex; flex-direction: column; color: #f1f5f9; font-family: 'Inter', sans-serif; border-radius: 12px; overflow: hidden; }
                .lekhni-editor-wrapper.mode-fullscreen-fixed { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 2000; }
                .lekhni-editor-wrapper.mode-embedded { width: 100%; border: 1px solid #334155; border-radius: 8px; background: rgba(15,23,42,0.4); color: #f1f5f9; font-family: 'Inter', sans-serif; overflow: hidden; }

                .lekhni-nav { height: 60px; background: #1e293b; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; padding: 0 1.5rem; }
                .nav-left { display: flex; align-items: center; gap: 1rem; }
                .save-status { font-size: 0.8rem; color: #94a3b8; }
                .nav-actions { display: flex; gap: 0.75rem; }

                .mode-tab { background: transparent; border: none; color: #64748b; padding: 4px 12px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.15s; }
                .mode-tab:hover { color: #cbd5e1; }
                .mode-tab.active { background: #6366f1; color: white; }

                .lekhni-toolbar { padding: 0.5rem 1.5rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
                .lekhni-toolbar button, .lekhni-bubble-menu button { background: transparent; border: none; color: #94a3b8; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; transition: all 0.2s; }
                .lekhni-toolbar button:hover, .lekhni-bubble-menu button:hover { background: #334155; color: white; }
                .divider { width: 1px; height: 18px; background: #334155; margin: 0 0.25rem; }

                .lekhni-title-input { width: 100%; background: transparent; border: none; font-size: 2.5rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: white; margin-bottom: 1.5rem; outline: none; }
                .slash-item:hover, .slash-item.active { background: #334155; }
                
                .lekhni-body-editable h1, .lekhni-h1 { font-size: 2rem; font-weight: bold; margin: 1.2rem 0 0.6rem 0; color: #fff; }
                .lekhni-body-editable h2, .lekhni-h2 { font-size: 1.5rem; font-weight: bold; margin: 1rem 0 0.5rem 0; color: #e2e8f0; }
                .lekhni-body-editable blockquote, .lekhni-quote { border-left: 4px solid #6366f1; padding-left: 1rem; color: #94a3b8; font-style: italic; margin: 1rem 0; }

                .sidebar-section h4 { font-size: 0.75rem; text-transform: uppercase; color: #64748b; margin-bottom: 1rem; letter-spacing: 0.05em; }
                .field { margin-bottom: 1rem; }
                .field label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.4rem; }
                .field input, .field select { width: 100%; background: #0f172a; border: 1px solid #334155; padding: 0.6rem; border-radius: 6px; color: white; font-size: 0.9rem; }

                .btn-primary { background: #6366f1; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
                .btn-secondary { background: transparent; border: 1px solid #334155; color: white; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
                .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
                .btn-icon { background: transparent; border: none; color: #94a3b8; cursor: pointer; display: flex; }

                body:has(.lekhni-editor-wrapper.mode-fullscreen) .sidebar,
                body:has(.lekhni-editor-wrapper.mode-fullscreen) .content-header { display: none !important; }
            </style>
        `;
    }

    renderToolbar() {
        const { toolbarLayout, embedded } = this.state;
        if (toolbarLayout === 'none') return '';

        const blockGroup = html`
            <div class="toolbar-group" style="display: flex; gap: 4px;">
                <button @click="${() => this.format('formatBlock', 'h1')}" title="Heading 1">H1</button>
                <button @click="${() => this.format('formatBlock', 'h2')}" title="Heading 2">H2</button>
                <button @click="${() => this.format('formatBlock', 'p')}" title="Paragraph">P</button>
                <button @click="${() => this.format('formatBlock', 'blockquote')}" title="Quote">”</button>
            </div>
        `;

        const formattingGroup = html`
            <div class="toolbar-group" style="display: flex; gap: 4px;">
                <button @click="${() => this.format('bold')}" title="Bold"><b>B</b></button>
                <button @click="${() => this.format('italic')}" title="Italic"><i>I</i></button>
                <button @click="${() => this.format('underline')}" title="Underline"><u>U</u></button>
            </div>
        `;

        const advancedGroup = html`
            <div class="toolbar-group" style="display: flex; gap: 4px;">
                <button @click="${() => this.format('insertUnorderedList')}" title="Bullet List">• List</button>
                <button @click="${() => this.insertEmbeddedMonacoBlock()}" title="Insert Inline Monaco Block">&lt;/&gt; Code</button>
                <button @click="${() => { const u = prompt('Enter URL string:'); if (u) this.insertRichWebCard(u); }}" title="OEmbed Link Card">🔗 Card</button>
                <button @click="${() => this.triggerAICopilot()}" title="AI Co-pilot">✨ AI</button>
                <button @click="${() => this.triggerPasteSpecialConfig()}" title="Paste Special Preferences">📋 Paste Special</button>
            </div>
        `;

        const styleBg = embedded ? 'transparent' : '#1e293b';

        if (toolbarLayout === 'compact') {
            return html`
                <div class="lekhni-toolbar lekhni-toolbar-compact" style="background: ${styleBg}; border-bottom: 1px solid #334155; display: flex; gap: 8px; padding: 6px 12px; align-items: center; justify-content: flex-start; overflow-x: auto;">
                    ${formattingGroup}
                    <div class="divider" style="width: 1px; height: 18px; background: #334155; margin: 0 4px;"></div>
                    <div class="toolbar-group" style="display: flex; gap: 4px;">
                        <button @click="${() => this.insertEmbeddedMonacoBlock()}" title="Monaco Code Block">&lt;/&gt;</button>
                        <button @click="${() => this.triggerAICopilot()}" title="AI Co-pilot">✨ AI</button>
                        <button @click="${() => this.triggerPasteSpecialConfig()}" title="Paste Special Preferences">📋 Paste Special</button>
                    </div>
                </div>
            `;
        }

        if (toolbarLayout === 'floating') {
            return '';
        }

        // Default 'full' layout
        return html`
            <div class="lekhni-toolbar" style="background: ${styleBg}; border-bottom: 1px solid #334155; display: flex; gap: 8px; padding: 8px 16px; align-items: center; flex-wrap: wrap;">
                ${blockGroup}
                <div class="divider" style="width: 1px; height: 18px; background: #334155; margin: 0 4px;"></div>
                ${formattingGroup}
                <div class="divider" style="width: 1px; height: 18px; background: #334155; margin: 0 4px;"></div>
                ${advancedGroup}
            </div>
        `;
    }

    async mount() {
        if (this.onInit) await this.onInit(this.props);
        this.update();
        if (this.onMount) await this.onMount();
    }

    static async replace(textarea, options = {}) {
        if (typeof textarea === 'string') {
            textarea = document.querySelector(textarea);
        }
        if (!textarea) return null;

        // Create container div
        const container = document.createElement('div');
        container.className = 'lekhni-replaced-container';
        // Insert container right after the textarea
        textarea.parentNode.insertBefore(container, textarea.nextSibling);
        textarea.style.display = 'none';

        // Read initial state
        const initialValue = textarea.value || '';
        
        // Instantiate
        const editor = new LekhniEditor(null, container, {
            id: options.id || 'textarea_' + Date.now(),
            title: options.title || '',
            body: initialValue,
            mode: options.mode || 'document',
            language: options.language || 'html',
            embedded: true,
            onChange: (content) => {
                textarea.value = content;
                // Dispatch input and change events so listening form scripts capture it
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.dispatchEvent(new Event('change', { bubbles: true }));
                if (options.onChange) options.onChange(content);
            },
            ...options
        });

        await editor.mount();
        return editor;
    }
}

export async function replaceTextarea(textarea, options = {}) {
    return LekhniEditor.replace(textarea, options);
}

