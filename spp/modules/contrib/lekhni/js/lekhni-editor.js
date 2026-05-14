import BaseComponent from '../../../spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { LekhniMonaco } from './monaco-engine.js?v=2026_05_13_v1';

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
            
            // Layout context
            embedded: this.props?.embedded || this.props?.inline || false
        };

        this.slashCommands = [
            { id: 'h1', label: 'Heading 1', icon: 'H1', desc: 'Big section heading' },
            { id: 'h2', label: 'Heading 2', icon: 'H2', desc: 'Medium sub-heading' },
            { id: 'p', label: 'Paragraph', icon: '¶', desc: 'Plain text block' },
            { id: 'quote', label: 'Quote', icon: '”', desc: 'Capture a quote' },
            { id: 'code', label: 'Code Block', icon: '&lt;/&gt;', desc: 'Embedded Monaco workspace' },
            { id: 'card', label: 'Web Card', icon: '🔗', desc: 'Insert preview web link' },
            { id: 'gallery', label: 'Image Grid', icon: '🎴', desc: 'Adaptive multi-image block' },
            { id: 'ai', label: 'AI Co-Pilot', icon: '✨', desc: 'Auto-complete or enhance' }
        ];

        this._monacoIdeInstance = null;
        this._db = null;
    }

    async onMount() {
        await this.loadModuleSettings();
        await this.initOfflineIndexedDB();

        if (this.state.id && !this.state.body) {
            await this.loadNode();
        } else {
            this.syncActiveWorkspaceContent();
            this.captureRevisionSnapshot('Initial Launch');
        }

        // Global outside click observers
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.lekhni-slash-menu') && !e.target.closest('.lekhni-body-editable')) {
                if (this.state.showSlashMenu) this.setState({ showSlashMenu: false });
            }
            if (!e.target.closest('.lekhni-bubble-menu') && !e.target.closest('.lekhni-body-editable')) {
                if (this.state.showBubbleMenu) this.setState({ showBubbleMenu: false });
            }
        });

        // Trigger sequential debounced outline extractions
        setInterval(() => this.buildOutlineTracker(), 1500);
    }

    update() {
        // Intercept reconciliation to prevent the underlying contenteditable buffer from wiping on outside click / menu setState
        if (this.state.editorMode === 'document') {
            const el = this.container?.querySelector('.lekhni-body-editable');
            if (el && el.innerHTML !== '<p><br></p>') {
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

    syncActiveWorkspaceContent() {
        if (this.state.editorMode === 'code') {
            this.mountFullBleedMonacoIde();
        } else {
            const editor = this.container.querySelector('.lekhni-body-editable');
            if (editor && this.state.body) editor.innerHTML = this.state.body;
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
            if (editor) this.state.body = editor.innerHTML;
        }

        this.setState({ 
            editorMode: newMode, showSlashMenu: false, showBubbleMenu: false 
        });

        setTimeout(() => this.syncActiveWorkspaceContent(), 50);
    }

    mountFullBleedMonacoIde() {
        const ideContainer = this.container.querySelector('.lekhni-full-ide-host');
        if (!ideContainer) return;
        
        ideContainer.innerHTML = '';
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
                    title: node.title || '', body: node.body || '', status: node.status || 'draft',
                    alias: node.alias || '', saving: false, manualAlias: !!node.alias
                });
                this.syncActiveWorkspaceContent();
                this.captureRevisionSnapshot('Loaded API Payload');
            }
        } catch (e) {
            this.notify('Failed to load document', 'error');
            this.setState({ saving: false });
        }
    }

    setupEditorObservers() {
        const editor = this.container.querySelector('.lekhni-body-editable');
        if (!editor) return;

        editor.addEventListener('input', () => {
            this.state.body = editor.innerHTML;
            this.state.isDirty = true;
            this.autoSave();

            // Intercept direct inline links conversion safely
            this.processLiveUrlPatterns();

            const sel = window.getSelection();
            if (!sel.rangeCount) return;
            const range = sel.getRangeAt(0);
            const text = range.startContainer.textContent;
            const offset = range.startOffset;

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

        editor.addEventListener('paste', (e) => this.handlePaste(e));
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
            formData.append('action', 'lekhni_upload_media');
            try {
                const apiBase = this.admin?.config?.apiBase || '?action=lekhni_upload_media';
                const res = await fetch(apiBase, { method: 'POST', body: formData }).then(r => r.json());
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
        header.style.background = '#21262d'; header.style.padding = '6px 12px'; header.style.fontSize = '0.75rem';
        header.style.color = '#8b949e'; header.style.fontFamily = "'JetBrains Mono', monospace";
        header.innerHTML = `<span>Inline Code Workspace</span><span style="color:#58a6ff; float:right;">javascript</span>`;

        const targetHost = document.createElement('div');
        targetHost.id = blockId; targetHost.style.width = '100%';

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

        LekhniMonaco.create(targetHost, { language: 'javascript', value: '// Code snippet logic...\n' });
        this.state.body = editor.innerHTML;
        this.state.isDirty = true;
    }

    async triggerAICopilot() {
        this.notify("✨ AI Co-Pilot: Generating contextual content...", "info");
        setTimeout(() => {
            this.format('insertHTML', ' <span style="background: rgba(99,102,241,0.15); color: #a5b4fc; padding: 2px 6px; border-radius: 4px;">[AI Completed Layout String]</span> ');
            this.notify("Content stream appended.", "success");
        }, 600);
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
        document.execCommand(cmd, false, val);
        const editor = this.container.querySelector('.lekhni-body-editable');
        if (editor) editor.focus();
        this.setState({ showBubbleMenu: false });
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
                this.setState({ id: res.id, saving: false, lastSaved: new Date().toLocaleTimeString(), isDirty: false });
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

    async publish() { this.state.status = 'published'; this.state.isDirty = true; await this.save(true); }
    notify(msg, type = 'info') { if (this.admin?.notify) this.admin.notify(msg, type); else console.log(`[Lekhni ${type}] ${msg}`); }

    render() {
        const { title, status, saving, lastSaved, alias, tags, category, bundle, bundles, embedded, editorMode, codeLanguage, categories, outline, revisions, showHistoryModal, selectedRevisionIndex, hasOfflineSnapshot, showSlashMenu, slashX, slashY, slashFilter, slashIndex, showBubbleMenu, bubbleX, bubbleY } = this.state;
        const filteredSlash = this.slashCommands.filter(c => c.label.toLowerCase().includes(slashFilter));

        return html`
            <div class="lekhni-editor-wrapper ${embedded ? 'mode-embedded' : 'mode-fullscreen'}" style="position: relative;">
                ${!embedded ? html`
                    <nav class="lekhni-nav">
                        <div class="nav-left">
                            <button class="btn-icon" @click="${() => location.hash = 'content'}" title="Back">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"></path></svg>
                            </button>
                            <div class="workspace-mode-switch" style="display: flex; background: #0f172a; padding: 3px; border-radius: 6px; border: 1px solid #334155;">
                                <button class="mode-tab ${editorMode === 'document' ? 'active' : ''}" @click="${() => this.setEditorMode('document')}">📝 Document</button>
                                <button class="mode-tab ${editorMode === 'code' ? 'active' : ''}" @click="${() => this.setEditorMode('code')}">💻 VSCode IDE</button>
                            </div>
                        </div>
                        <div class="nav-actions">
                            ${revisions.length > 0 ? html`
                                <button class="btn-secondary btn-sm" @click="${() => this.setState({ showHistoryModal: true, selectedRevisionIndex: revisions.length - 1 })}" title="View historical revision diff snapshots">
                                    🕰️ History (${revisions.length})
                                </button>
                            ` : ''}
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
                                <button class="mode-tab btn-sm ${editorMode === 'code' ? 'active' : ''}" @click="${() => this.setEditorMode('code')}">VSCode IDE</button>
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

                <div class="lekhni-workspace" style="display: flex; flex-grow: 1; min-height: ${embedded ? '350px' : 'calc(100vh - 60px)'}; background: ${embedded ? 'transparent' : '#0f172a'};">
                    <main class="lekhni-main" style="flex-grow: 1; display: flex; flex-direction: column; position: relative; width: 100%;">
                        ${editorMode === 'document' ? html`
                            <div class="lekhni-toolbar" style="background: ${embedded ? 'transparent' : '#1e293b'}; border-bottom: 1px solid #334155;">
                                <div class="toolbar-group">
                                    <button @click="${() => this.format('formatBlock', 'h1')}" title="Heading 1">H1</button>
                                    <button @click="${() => this.format('formatBlock', 'h2')}" title="Heading 2">H2</button>
                                    <button @click="${() => this.format('formatBlock', 'p')}" title="Paragraph">P</button>
                                    <button @click="${() => this.format('formatBlock', 'blockquote')}" title="Quote">”</button>
                                </div>
                                <div class="divider"></div>
                                <div class="toolbar-group">
                                    <button @click="${() => this.format('bold')}" title="Bold"><b>B</b></button>
                                    <button @click="${() => this.format('italic')}" title="Italic"><i>I</i></button>
                                    <button @click="${() => this.format('underline')}" title="Underline"><u>U</u></button>
                                </div>
                                <div class="divider"></div>
                                <div class="toolbar-group">
                                    <button @click="${() => this.format('insertUnorderedList')}" title="Bullet List">• List</button>
                                    <button @click="${() => this.insertEmbeddedMonacoBlock()}" title="Insert Inline Monaco Block">&lt;/&gt; Code</button>
                                    <button @click="${() => { const u = prompt('Enter URL string:'); if (u) this.insertRichWebCard(u); }}" title="OEmbed Link Card">🔗 Card</button>
                                    <button @click="${() => this.triggerAICopilot()}" title="AI Co-pilot">✨ AI</button>
                                </div>
                            </div>

                            <div class="lekhni-canvas" style="padding: ${embedded ? '1.5rem 1rem' : '4rem 2rem'}; max-width: ${embedded ? '100%' : '800px'}; margin: 0 auto; width: 100%;">
                                ${!embedded ? html`
                                    <input type="text" class="lekhni-title-input" placeholder="Document Title" .value="${title}" @input="${(e) => this.handleTitleInput(e)}">
                                ` : ''}
                                <div class="lekhni-body-editable" contenteditable="true" style="min-height: ${embedded ? '250px' : '500px'}; outline: none; color: #cbd5e1; font-size: 1.1rem; line-height: 1.8;"></div>
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
                                    </select>
                                </div>
                                <div class="lekhni-full-ide-host" style="flex-grow: 1; width: 100%;"></div>
                            </div>
                        `}

                        <!-- Floating Menus Overlay Over Document Mode -->
                        ${showSlashMenu ? html`
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

                        ${showBubbleMenu ? html`
                            <div class="lekhni-bubble-menu fade-in" style="position: absolute; left: ${bubbleX}px; top: ${bubbleY}px; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); border: 1px solid #334155; border-radius: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); padding: 4px 8px; display: flex; gap: 4px; z-index: 110; align-items: center;">
                                <button @click="${() => this.format('bold')}" style="font-weight: bold;">B</button>
                                <button @click="${() => this.format('italic')}" style="font-style: italic;">I</button>
                                <button @click="${() => this.format('underline')}" style="text-decoration: underline;">U</button>
                                <div class="divider" style="height:14px;"></div>
                                <button @click="${() => { const url = prompt('Link URL:'); if (url) this.format('createLink', url); }}" title="Link String">🔗</button>
                                <button @click="${() => this.addInlineAnnotation()}" title="Attach Inline Persistent Reviewer Annotation Note" style="color: #facc15;">💬 Annotate</button>
                            </div>
                        ` : ''}
                    </main>

                    ${!embedded && editorMode === 'document' ? html`
                        <aside class="lekhni-sidebar" style="width: 320px; background: #1e293b; border-left: 1px solid #334155; padding: 1.5rem; flex-shrink: 0; display: flex; flex-direction: column; gap: 2rem;">
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
            </div>

            <style>
                .lekhni-editor-wrapper.mode-fullscreen { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: #0f172a; z-index: 2000; display: flex; flex-direction: column; color: #f1f5f9; font-family: 'Inter', sans-serif; }
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
                body:has(.lekhni-editor-wrapper.mode-fullscreen) .main-wrapper { margin-left: 0 !important; }
            </style>
        `;
    }
}
