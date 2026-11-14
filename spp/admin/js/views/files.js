/**
 * File Manager & Storage Quota View Component (SPP Storage Manager)
 * Provides comprehensive visual filesystem navigation, file editing with rich syntax highlighting,
 * line numbers, ZIP extraction, file creation/deletion, and disk quota monitoring.
 */

import Prism from '../prism.js';
import SPPCoder from '../sppcoder.js';
import TreemapRenderer from '../lib/treemap.js';

const BaseClass = (typeof window !== 'undefined' && (window.BaseComponent || (window.SPPUX && window.SPPUX.BaseComponent))) || class {
    constructor(admin, container, props) {
        this.admin = admin;
        this.container = container;
        this.props = props || {};
        this.state = {};
    }
    setState(partial) {
        this.state = Object.assign({}, this.state, partial);
    }
    update() {
        if (this.container && typeof this.render === 'function') {
            this.container.innerHTML = this.render();
        }
    }
};

export default class FilesView extends BaseClass {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
        this.history = [];
        this.historyIndex = -1;
        this.lastHistoryPush = 0;
        this.editorOptions = {
            indentUnit: '    ',
            indentSize: 4,
            isTab: false,
            wrap: false,
            hints: true,
            showFind: false,
            findQuery: '',
            replaceQuery: '',
            findMatches: [],
            currentMatchIndex: -1,
            activeHintIndex: 0,
            activeHints: [],
            showGoto: false,
            gotoLine: 1,
            fontSize: 13,
            fullscreen: false
        };
        window.filesViewInstance = this;
        this._bindGlobalKeyHandlers();
    }

    async onInit() {
        window.filesViewInstance = this;
        this._bindGlobalKeyHandlers();
        this._bindGlobalContextMenuDismissal();
        this.state = {
            currentPath: '',
            items: [],
            totalItems: 0,
            loading: true,
            searchQuery: '',
            // Selection & Batch Action State
            selectedItems: new Set(),
            isEditingAddress: false,
            // Modern View & Inspector Architecture
            viewMode: localStorage.getItem('spp_files_view_mode') || 'list',
            inspectorOpen: localStorage.getItem('spp_files_inspector_open') === 'true',
            inspectorTab: 'overview',
            selectedItemForInspector: null,
            treeWidth: parseInt(localStorage.getItem('spp_files_tree_width') || '250', 10),
            openDropdown: null,
            sortColumn: 'name',
            sortDirection: 'asc',
            clipboard: null,
            expertMode: localStorage.getItem('spp_admin_expert_mode') === 'true',
            // Modals
            imagePreviewModal: null,
            chmodModal: null,
            bulkDeleteModal: false,
            moveCopyModal: null,
            // Tree state
            treeData: null,
            treeLoading: false,
            expandedFolders: new Set(['']),
            // Quota
            quota: null,
            // Editor state
            activeFile: null,
            fileContent: '',
            fileSize: 0,
            fileExt: '',
            isEditing: false,
            savingFile: false,
            showShortcutsModal: false,
            filePropsModal: null,
            contextMenu: null,
            directoryManifest: null,
            rawManifestModal: null,
            manifestHealthModal: null,
            collapseManifestBanner: true
        };

        this._treeScrollTop = 0;

        // Check hash query params (e.g. #files?path=/var/www/satyalab.net or #files?domain=satyalab.net)
        const hash = window.location.hash || '';
        let initialPath = '';
        let pendingDomain = '';
        if (hash.includes('?')) {
            const qs = hash.substring(hash.indexOf('?') + 1);
            const urlParams = new URLSearchParams(qs);
            if (urlParams.get('path')) {
                initialPath = decodeURIComponent(urlParams.get('path'));
            } else if (urlParams.get('domain')) {
                pendingDomain = decodeURIComponent(urlParams.get('domain'));
            }
        }

        // Render initial unified workstation frame
        this.update();

        // Step 1: Immediately fetch current directory and root tree in PARALLEL
        const sysRoot = this.state.systemRoot || '/';
        const targetInitPath = initialPath || this.state.workspaceRoot || this.state.currentPath || '';

        // Priority 1: Fetch and render folder contents FIRST and IMMEDIATELY!
        const dirPromise = this.fetchDirectory(targetInitPath).then(async () => {
            this.state.loading = false;
            if (!this.state.treeData) {
                this.buildTreeFromDirectoryItems();
            }
            this.update();
            this.pushHistory(this.state.currentPath || sysRoot);

            if (pendingDomain && this.state.siteRoots) {
                const targetSite = this.state.siteRoots.find(s => s.domain === pendingDomain || s.pattern === pendingDomain);
                if (targetSite && targetSite.home_path && targetSite.home_path !== this.state.currentPath) {
                    await this.loadDirectory(targetSite.home_path);
                }
            }
        }).catch(e => {
            console.error("[FilesView] Failed to load initial directory:", e);
            this.state.loading = false;
            this.update();
        });

        // Priority 2: Concurrently load root tree without blocking files view
        this.state.treeLoading = true;
        const treePromise = this.fetchTree(false, sysRoot).then(() => {
            this.state.treeLoading = false;
            if (this.state.currentPath) {
                this.attachSubdirsToTreeNode(this.state.currentPath);
            }
            this.update();
            this.syncTreeFocus();
        }).catch(e => {
            console.warn("[FilesView] Background tree fetch error:", e);
            this.state.treeLoading = false;
            this.update();
        });

        // Step 2: Concurrently fetch quota in background without blocking workstation rendering
        this.fetchQuota().then(() => {
            if (this.container && this.state.quota) {
                this.updateQuotaDisplay();
            }
        }).catch(err => console.warn("[FilesView] Quota fetch error:", err));
    }

    normalizePath(path = '') {
        if (!path) return '';
        let clean = String(path).replace(/\\/g, '/');
        // Absolute Linux path
        if (clean.startsWith('/')) {
            clean = '/' + clean.replace(/^\/+|\/+$/g, '');
            return (clean === '') ? '/' : clean;
        }
        // Windows drive path (e.g. C:/)
        if (/^[a-zA-Z]:/.test(clean)) {
            const drive = clean.substring(0, 2).toUpperCase();
            const rest = clean.substring(2).replace(/^\/+|\/+$/g, '');
            return rest ? `${drive}/${rest}` : `${drive}/`;
        }
        // Relative path
        return clean.replace(/^\/+|\/+$/g, '');
    }

    joinPath(dir, name) {
        if (!dir || dir === '/') return `/${name}`;
        const cleanDir = String(dir).replace(/\/+$/, '');
        return `${cleanDir}/${name}`;
    }

    buildTreeFromDirectoryItems() {
        const rootDirs = (this.state.items || [])
            .filter(it => it.is_dir)
            .map(it => ({
                name: it.name,
                path: it.path,
                is_dir: true,
                children: [],
                has_children: true
            }));
        rootDirs.sort((a, b) => a.name.localeCompare(b.name));
        const cur = this.normalizePath(this.state.currentPath || '');
        const sys = this.normalizePath(this.state.systemRoot || '/');
        const isRoot = (!cur || cur === '/' || cur === sys);

        this.state.treeData = {
            name: isRoot ? 'Root (/)' : (cur.split('/').pop() || 'Root (/)'),
            path: cur || sys,
            is_dir: true,
            children: rootDirs,
            has_children: rootDirs.length > 0
        };
        this.syncTreeSelection();
    }

    attachSubdirsToTreeNode(targetPath) {
        if (!this.state.treeData || !targetPath) return;
        const normTarget = this.normalizePath(targetPath);
        const node = this.findNode(this.state.treeData, normTarget);
        if (node) {
            const subDirs = (this.state.items || [])
                .filter(it => it.is_dir)
                .map(it => ({
                    name: it.name,
                    path: it.path,
                    is_dir: true,
                    children: [],
                    has_children: true
                }));
            subDirs.sort((a, b) => a.name.localeCompare(b.name));
            node.children = subDirs;
            node.has_children = subDirs.length > 0;
            this.syncTreeSelection();
        }
    }

    updateQuotaDisplay() {
        const container = document.getElementById('spp-quota-monitor-container');
        if (container && this.state.quota) {
            container.innerHTML = this.renderQuotaMonitor();
        }
    }

    renderQuotaMonitor() {
        const q = this.state.quota;
        if (!q) return '';
        return `
            <div style="background:var(--card-bg); border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <div>
                        <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">Primary Storage Quota & Capacity</h4>
                        <span style="font-size:0.8rem; color:var(--text-dim);">${q.used_formatted} used of ${q.total_formatted} (${q.free_formatted} available)</span>
                    </div>
                    <span class="suite-pill ${q.used_percentage > 85 ? 'suite-pill-danger' : 'suite-pill-success'}">
                        ${q.used_percentage}% Used
                    </span>
                </div>
                <div class="suite-quota-bar">
                    <div class="suite-quota-fill ${q.used_percentage > 85 ? 'warning' : ''}" style="width:${q.used_percentage}%;"></div>
                </div>

                <!-- Top folder storage breakdown -->
                ${q.breakdown && q.breakdown.length > 0 ? `
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:0.75rem;">
                        ${q.breakdown.map(b => `
                            <span class="suite-pill suite-pill-neutral" style="font-size:0.7rem;">
                                📁 ${this.escapeHtml(b.folder)}: ${b.size_formatted}
                            </span>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    }

    async fetchDirectory(path = '') {
        const normPath = this.normalizePath(path);

        // Auto-expand all ancestor folders of the target directory
        const exp = new Set(this.state.expandedFolders || []);
        exp.add('');
        exp.add('/');
        if (this.state.systemRoot) exp.add(this.state.systemRoot);

        if (normPath && normPath !== '/' && normPath !== this.state.systemRoot) {
            const isAbs = normPath.startsWith('/');
            const parts = normPath.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
            let accum = isAbs ? '' : '';
            for (let i = 0; i < parts.length; i++) {
                accum = isAbs ? `${accum}/${parts[i]}` : (accum ? `${accum}/${parts[i]}` : parts[i]);
                exp.add(accum);
            }
        }

        this.state.currentPath = normPath;
        this.state.expandedFolders = exp;

        try {
            const res = await this.admin.api('storage_list_files', { path: normPath });
            if (res && res.success === false) {
                const errMsg = res.message || res.error || "Failed to list files";
                this.state.errorMessage = errMsg;
                this.state.items = [];
                this.state.totalItems = 0;
                console.warn("[FilesView] Failed to list files:", errMsg);
                return;
            }
            this.state.errorMessage = null;
            const data = (res.success && res.data) ? res.data : res;

            this.state.items = data.items || [];
            this.state.totalItems = data.total_items || 0;
            this.state.directoryManifest = data.directory_manifest || null;
            this.state.currentPath = data.current_path !== undefined ? this.normalizePath(data.current_path) : normPath;
            if (data.system_root) this.state.systemRoot = data.system_root;
            if (data.workspace_root) this.state.workspaceRoot = data.workspace_root;
            if (data.available_roots) this.state.availableRoots = data.available_roots;
            if (data.site_roots) this.state.siteRoots = data.site_roots;

            if (normPath && this.state.treeData) {
                this.attachSubdirsToTreeNode(normPath);
            }
        } catch (e) {
            console.error("Storage list error:", e);
            this.state.errorMessage = e.message || "Failed to list files";
            this.admin.notify("Failed to list files: " + e.message, "error");
        }
    }

    async fetchTree(force = false, root = '') {
        if (this.state.treeData && !force && !root) {
            this.syncTreeSelection();
            return;
        }

        try {
            const targetRoot = root !== '' ? root : (this.state.currentRootScope || this.state.systemRoot || '/');
            const res = await this.admin.api('storage_tree', { root: targetRoot, depth: 1 });
            const data = (res.success && res.data) ? res.data : res;
            if (res.success && data.tree) {
                this.state.treeData = data.tree;
                if (data.system_root) this.state.systemRoot = data.system_root;
                if (data.workspace_root) this.state.workspaceRoot = data.workspace_root;
                if (data.available_roots) this.state.availableRoots = data.available_roots;
                if (data.site_roots) this.state.siteRoots = data.site_roots;
                this.syncTreeSelection();
            }
        } catch (e) {
            console.warn("Tree load error:", e);
        }
    }

    async fetchQuota() {
        try {
            const res = await this.admin.api('storage_disk_usage', {});
            const data = (res.success && res.data) ? res.data : res;
            if (res.success && data.quota) {
                this.state.quota = data.quota;
            }
        } catch (e) {
            console.warn("Quota load error:", e);
        }
    }

    pushHistory(path) {
        if (this.isNavigatingHistory) return;
        const norm = this.normalizePath(path);
        if (this.history.length > 0 && this.history[this.historyIndex] === norm) return;

        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(norm);
        this.historyIndex = this.history.length - 1;
    }

    canGoBack() {
        return this.historyIndex > 0;
    }

    canGoForward() {
        return this.historyIndex < this.history.length - 1;
    }

    async navigateBack() {
        if (!this.canGoBack()) return;
        this.historyIndex--;
        this.isNavigatingHistory = true;
        try {
            await this.loadDirectory(this.history[this.historyIndex], false);
        } finally {
            this.isNavigatingHistory = false;
            this.update();
        }
    }

    async navigateForward() {
        if (!this.canGoForward()) return;
        this.historyIndex++;
        this.isNavigatingHistory = true;
        try {
            await this.loadDirectory(this.history[this.historyIndex], false);
        } finally {
            this.isNavigatingHistory = false;
            this.update();
        }
    }

    toggleAddressEdit(enable = null) {
        this.state.isEditingAddress = (enable !== null) ? enable : !this.state.isEditingAddress;
        this.update();
        if (this.state.isEditingAddress) {
            requestAnimationFrame(() => {
                const input = document.getElementById('spp-files-address-input');
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        }
    }

    submitAddressInput() {
        const input = document.getElementById('spp-files-address-input');
        if (input) {
            const val = input.value.trim();
            this.state.isEditingAddress = false;
            if (val) {
                this.loadDirectory(val);
            } else {
                this.update();
            }
        }
    }

    getFilteredItems() {
        let items = this.state.items || [];
        if (this.state.searchQuery && this.state.searchQuery.trim()) {
            const q = this.state.searchQuery.trim().toLowerCase();
            items = items.filter(it => {
                if (it.name && it.name.toLowerCase().includes(q)) return true;
                if (it.comment && it.comment.toLowerCase().includes(q)) return true;
                if (it.usage && it.usage.toLowerCase().includes(q)) return true;
                if (it.attributes?.tags && Array.isArray(it.attributes.tags) && it.attributes.tags.some(t => String(t).toLowerCase().includes(q))) return true;
                return false;
            });
        }
        if (this.state.sortColumn) {
            const col = this.state.sortColumn;
            const dir = this.state.sortDirection === 'desc' ? -1 : 1;
            items = [...items].sort((a, b) => {
                if (a.is_dir !== b.is_dir) {
                    return a.is_dir ? -1 : 1;
                }
                if (col === 'size') {
                    const sa = a.is_dir ? -1 : (a.size || 0);
                    const sb = b.is_dir ? -1 : (b.size || 0);
                    return (sa - sb) * dir;
                }
                if (col === 'modified') {
                    const ma = a.mtime || 0;
                    const mb = b.mtime || 0;
                    return (ma - mb) * dir;
                }
                if (col === 'description') {
                    const da = a.description || a.comment || '';
                    const db = b.description || b.comment || '';
                    return da.localeCompare(db) * dir;
                }
                return String(a.name || '').localeCompare(String(b.name || '')) * dir;
            });
        }
        return items;
    }

    toggleSelectItem(path, e) {
        if (e) e.stopPropagation();
        const norm = this.normalizePath(path);
        if (this.state.selectedItems.has(norm)) {
            this.state.selectedItems.delete(norm);
        } else {
            this.state.selectedItems.add(norm);
        }
        this.update();
    }

    toggleSelectAll(checked) {
        if (checked) {
            const items = this.getFilteredItems();
            items.forEach(it => this.state.selectedItems.add(this.normalizePath(it.path)));
        } else {
            this.state.selectedItems.clear();
        }
        this.update();
    }

    clearSelection() {
        this.state.selectedItems.clear();
        this.update();
    }

    async loadDirectory(path = '', pushHistory = true) {
        const normPath = this.normalizePath(path);
        if (pushHistory) {
            this.pushHistory(normPath);
        }
        this.closeContextMenu();
        if (this.state.selectedItems) {
            this.state.selectedItems.clear();
        }

        // Preserve current tree scroll position before DOM update
        const treePane = this.container ? this.container.querySelector('.spp-file-tree-scroll, .spp-file-tree-pane') : null;
        if (treePane) {
            this._treeScrollTop = treePane.scrollTop;
        }

        // Auto-expand all ancestor folders of target directory
        const exp = new Set(this.state.expandedFolders || []);
        exp.add('');
        exp.add('/');
        if (this.state.systemRoot) exp.add(this.state.systemRoot);

        if (normPath && normPath !== '/' && normPath !== this.state.systemRoot) {
            const isAbs = normPath.startsWith('/');
            const parts = normPath.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
            let accum = isAbs ? '' : '';
            for (let i = 0; i < parts.length; i++) {
                accum = isAbs ? `${accum}/${parts[i]}` : (accum ? `${accum}/${parts[i]}` : parts[i]);
                exp.add(accum);
            }
        }

        // Tree scope check: if target directory is outside current root scope, reset scope to system root
        const sysRoot = this.normalizePath(this.state.systemRoot || '/');
        if (this.state.currentRootScope && this.state.currentRootScope !== '/' && this.state.currentRootScope !== sysRoot) {
            const scopeNorm = this.normalizePath(this.state.currentRootScope);
            if (!normPath.startsWith(scopeNorm)) {
                this.state.currentRootScope = sysRoot;
                await this.fetchTree(true, sysRoot);
            }
        }

        this.setState({ 
            loading: true, 
            currentPath: normPath,
            expandedFolders: exp
        });
        this.update();
        this.syncTreeFocus();

        // Priority 1: Fetch and render folder files IMMEDIATELY!
        await this.fetchDirectory(normPath);
        this.setState({ loading: false });
        this.update();
        this.syncTreeFocus();

        // Priority 2: Asynchronously ensure tree branch is expanded in background without blocking files view
        this.ensurePathInTree(normPath).catch(err => console.warn("[FilesView] Tree sync error:", err));
    }

    async ensurePathInTree(targetPath) {
        if (!targetPath) return;
        const normTarget = this.normalizePath(targetPath);
        const sysRoot = this.normalizePath(this.state.systemRoot || '/');

        if (!this.state.treeData) {
            await this.fetchTree(true, this.state.currentRootScope || sysRoot);
            if (!this.state.treeData) return;
        }

        const isAbs = normTarget.startsWith('/');
        const isWin = /^[a-zA-Z]:/.test(normTarget);
        const parts = normTarget.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
        let accum = '';
        const segments = [];
        for (let i = 0; i < parts.length; i++) {
            if (isAbs) {
                accum += '/' + parts[i];
            } else if (isWin && i === 0) {
                accum = parts[i] + '/';
            } else {
                accum = (accum && !accum.endsWith('/')) ? `${accum}/${parts[i]}` : `${accum}${parts[i]}`;
            }
            segments.push(this.normalizePath(accum));
        }

        const exp = new Set(this.state.expandedFolders || []);
        exp.add('');
        exp.add('/');
        exp.add(sysRoot);
        segments.forEach(s => exp.add(s));
        this.state.expandedFolders = exp;

        // Traverse down treeData ensuring each ancestor level has its children loaded
        let treeChanged = false;
        for (const seg of segments) {
            let node = this.findNode(this.state.treeData, seg);
            if (!node || (!node.children || node.children.length === 0)) {
                try {
                    const res = await this.admin.api('storage_tree', { root: seg, depth: 1 });
                    const data = (res && res.success && res.data) ? res.data : (res || {});
                    if (res && res.success && data.tree) {
                        treeChanged = true;
                        if (node) {
                            node.children = data.tree.children || [];
                            node.has_children = (node.children.length > 0);
                        } else {
                            const lastSlash = seg.lastIndexOf('/');
                            let parentSeg = lastSlash > 0 ? seg.substring(0, lastSlash) : (lastSlash === 0 ? '/' : sysRoot);
                            if (/^[a-zA-Z]:$/.test(parentSeg)) parentSeg += '/';
                            const pNode = this.findNode(this.state.treeData, parentSeg);
                            if (pNode) {
                                if (!pNode.children) pNode.children = [];
                                const existing = pNode.children.find(c => this.normalizePath(c.path) === seg);
                                if (!existing) {
                                    pNode.children.push(data.tree);
                                }
                            }
                        }
                    } else {
                        // Mark as loaded empty so tree does not stay stuck on Loading...
                        if (node) {
                            node.children = [];
                            node.has_children = false;
                        }
                    }
                } catch (e) {
                    console.warn("[FilesView] Failed to expand tree branch:", seg, e);
                    if (node) {
                        node.children = [];
                        node.has_children = false;
                    }
                }
            }
        }
        if (treeChanged) {
            this.update();
        }
        this.syncTreeSelection();
    }

    toggleManifestBanner(forceState = null) {
        const nextState = (forceState !== null) ? Boolean(forceState) : !this.state.collapseManifestBanner;
        this.state.collapseManifestBanner = nextState;
        localStorage.setItem('spp_files_collapse_manifest_banner', String(nextState));
        this.update();
    }

    async ensureTreeNodeLoaded(targetPath) {
        await this.ensurePathInTree(targetPath);
    }

    async loadTree(force = false, root = '') {
        this.closeContextMenu();
        this.state.treeLoading = true;
        this.update();
        await this.fetchTree(true, root);
        this.state.treeLoading = false;
        this.update();
        this.syncTreeFocus();
    }

    async switchRootScope(rootPath) {
        this.state.currentRootScope = rootPath;
        await this.loadDirectory(rootPath);
        await this.loadTree(true, rootPath);
    }

    async jumpToSiteDirectory(path) {
        if (!path) return;
        await this.loadDirectory(path);
    }

    syncTreeSelection() {
        const normCurrent = this.normalizePath(this.state.currentPath || '');
        const exp = new Set(this.state.expandedFolders || []);
        exp.add('');
        exp.add('/');
        const sysRoot = this.normalizePath(this.state.systemRoot || '/');
        exp.add(sysRoot);

        if (normCurrent && normCurrent !== '/' && normCurrent !== sysRoot) {
            const isAbs = normCurrent.startsWith('/');
            const isWin = /^[a-zA-Z]:/.test(normCurrent);
            const parts = normCurrent.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
            let accum = '';
            for (let i = 0; i < parts.length; i++) {
                if (isAbs) {
                    accum += '/' + parts[i];
                } else if (isWin && i === 0) {
                    accum = parts[i] + '/';
                } else {
                    accum = (accum && !accum.endsWith('/')) ? `${accum}/${parts[i]}` : `${accum}${parts[i]}`;
                }
                exp.add(this.normalizePath(accum));
            }
        }
        this.state.expandedFolders = exp;
    }

    findNode(rootNode, targetPath) {
        if (!rootNode) return null;
        const normRoot = this.normalizePath(rootNode.path || '');
        const normTarget = this.normalizePath(targetPath || '');
        if (normRoot === normTarget) return rootNode;

        const stripRoot = normRoot.replace(/^\/+|\/+$/g, '');
        const stripTarget = normTarget.replace(/^\/+|\/+$/g, '');
        if (stripRoot && stripTarget && stripRoot === stripTarget) return rootNode;

        if (rootNode.children && rootNode.children.length > 0) {
            for (const child of rootNode.children) {
                const found = this.findNode(child, targetPath);
                if (found) return found;
            }
        }
        return null;
    }

    async toggleFolderExpand(path, e) {
        if (e) e.stopPropagation();
        const normPath = this.normalizePath(path);
        const sysRoot = this.normalizePath(this.state.systemRoot || '/');
        const isRoot = (!normPath || normPath === '/' || normPath === sysRoot);
        const exp = new Set(this.state.expandedFolders || []);
        const willExpand = !exp.has(normPath) && (!isRoot || (!exp.has('') && !exp.has('/') && !exp.has(sysRoot)));

        if (willExpand) {
            exp.add(normPath);
            if (isRoot) {
                exp.add('');
                exp.add('/');
                exp.add(sysRoot);
            }
        } else {
            exp.delete(normPath);
            if (isRoot) {
                exp.delete('');
                exp.delete('/');
                exp.delete(sysRoot);
            }
        }

        const treePane = this.container ? this.container.querySelector('.spp-file-tree-pane') : null;
        if (treePane) {
            this._treeScrollTop = treePane.scrollTop;
        }

        const targetNode = this.findNode(this.state.treeData, normPath);
        if (willExpand && targetNode && (!targetNode.children || targetNode.children.length === 0)) {
            targetNode._loading = true;
        }

        this.setState({ expandedFolders: exp });
        this.update();
        this.syncTreeFocus();

        if (willExpand) {
            try {
                await this.ensureTreeNodeLoaded(normPath);
            } finally {
                if (targetNode) {
                    targetNode._loading = false;
                    targetNode._loaded = true;
                }
            }
            this.update();
            this.syncTreeFocus();
        }
    }

    onTreeScroll(scrollTop) {
        this._treeScrollTop = scrollTop;
    }

    syncTreeFocus() {
        requestAnimationFrame(() => {
            const treePane = this.container ? this.container.querySelector('.spp-file-tree-scroll, .spp-file-tree-sidebar') : null;
            if (!treePane) return;

            // 1. Restore scroll position so it never resets to top
            if (this._treeScrollTop !== undefined && this._treeScrollTop > 0) {
                treePane.scrollTop = this._treeScrollTop;
            }

            // 2. Smoothly scroll active node into visual focus
            const activeNode = treePane.querySelector('.spp-tree-node.active');
            if (activeNode) {
                const paneRect = treePane.getBoundingClientRect();
                const nodeRect = activeNode.getBoundingClientRect();

                const isVisible = (
                    nodeRect.top >= paneRect.top &&
                    nodeRect.bottom <= paneRect.bottom
                );

                if (!isVisible) {
                    activeNode.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    setTimeout(() => {
                        if (treePane) this._treeScrollTop = treePane.scrollTop;
                    }, 300);
                }
            }
        });
    }

    afterUpdate() {
        this.syncTreeFocus();
    }

    async loadQuota() {
        try {
            const res = await this.admin.api('storage_disk_usage');
            const data = (res.success && res.data) ? res.data : res;
            this.setState({ quota: data });
        } catch (e) {
            console.warn("Quota fetch error:", e);
        }
    }

    async openFile(relativePath) {
        const ext = relativePath.split('.').pop() || '';
        try {
            const res = await this.admin.api('storage_read_file', { path: relativePath });
            const data = (res.success && res.data) ? res.data : res;

            if (res.success) {
                const content = data.content !== undefined ? data.content : '';
                const fileExt = data.extension || ext;

                this.launchEditor(relativePath, content, fileExt);
            } else {
                this.admin.notify(res.error || "Failed to read file.", "error");
            }
        } catch (e) {
            this.admin.notify("Read error: " + e.message, "error");
        }
    }

    launchEditor(relativePath, content, fileExt) {
        let portal = document.getElementById('sppcoder-modal-portal');
        if (!portal) {
            portal = document.createElement('div');
            portal.id = 'sppcoder-modal-portal';
            document.body.appendChild(portal);
        }

        let mount = document.getElementById('sppcoder-mount-point');
        if (!mount) {
            portal.innerHTML = `
                <div class="spp-editor-modal-overlay active" id="spp-editor-modal-overlay" style="position:fixed; inset:0; z-index:1000000; background:rgba(2, 6, 23, 0.85); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; padding:1.25rem;">
                    <div class="spp-editor-modal-window" style="width:96vw; height:94vh; max-width:1750px; background:#0d1117; border:1px solid #30363d; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 60px -15px rgba(0,0,0,0.9), 0 0 0 1px rgba(255,255,255,0.06);">
                        <div id="sppcoder-mount-point" style="width:100%; height:100%; display:flex; flex-direction:column; overflow:hidden; flex:1;"></div>
                    </div>
                </div>
            `;
            const overlay = document.getElementById('spp-editor-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        this.closeEditor();
                    }
                });
            }
            mount = document.getElementById('sppcoder-mount-point');
        }

        const CoderClass = window.SPPCoder || SPPCoder;
        if (!this.coder || this.coder.container !== mount) {
            this.coder = new CoderClass({
                container: mount,
                api: (action, params) => this.admin.api(action, params),
                notify: (msg, type) => this.admin.notify(msg, type),
                onClose: () => this.closeEditor(),
                onSave: async (path, codeContent) => {
                    const saveRes = await this.admin.api('storage_write_file', { path, content: codeContent });
                    if (!saveRes.success) throw new Error(saveRes.error || "Failed to save file.");
                    this.admin.notify("File saved successfully.", "success");
                }
            });
        }

        this.coder.openTab(relativePath, content, fileExt);
        this.state.isEditing = true;
        this.state.activeFile = relativePath;

        // Visual indicator in file table
        document.querySelectorAll('.suite-file-row').forEach(row => {
            row.classList.remove('suite-file-row-active');
        });
        const safePath = (typeof CSS !== 'undefined' && CSS.escape) ? CSS.escape(relativePath) : relativePath.replace(/"/g, '\\"');
        const activeRow = document.querySelector(`.suite-file-row[data-row-path="${safePath}"]`);
        if (activeRow) activeRow.classList.add('suite-file-row-active');
    }

    async saveFile() {
        if (!this.state.activeFile) return;
        const textarea = document.getElementById('file-editor-textarea');
        const editorText = textarea ? textarea.value : this.state.fileContent;

        this.setState({ savingFile: true });
        this.update();

        try {
            const res = await this.admin.api('storage_write_file', {
                path: this.state.activeFile,
                content: editorText
            });

            if (res.success) {
                this.admin.notify("File saved successfully.", "success");
                this.setState({
                    savingFile: false,
                    fileContent: editorText,
                    fileSize: new Blob([editorText]).size
                });
                const unsavedBadge = document.getElementById('editor-unsaved-badge');
                if (unsavedBadge) unsavedBadge.style.display = 'none';
            } else {
                this.admin.notify(res.error || "Failed to save file.", "error");
                this.setState({ savingFile: false });
            }
        } catch (e) {
            this.admin.notify("Write error: " + e.message, "error");
            this.setState({ savingFile: false });
        }
        this.update();
    }

    closeEditor() {
        if (this.coder) {
            try { this.coder.destroy(); } catch (e) {}
            this.coder = null;
        }
        const portal = document.getElementById('sppcoder-modal-portal');
        if (portal) {
            portal.innerHTML = '';
        }
        this.state.isEditing = false;
        this.state.activeFile = null;
        this._pendingOpenFile = null;
        document.querySelectorAll('.suite-file-row-active').forEach(row => {
            row.classList.remove('suite-file-row-active');
        });
    }

    promptNewItem(type = 'file') {
        this.setState({
            filePropsModal: {
                mode: 'create',
                type: type,
                name: '',
                description: '',
                usage: '',
                tags: '',
                author: '',
                stability: 'stable',
                path: this.state.currentPath ? (this.state.currentPath === '/' ? '/' : `${this.state.currentPath}/`) : ''
            }
        });
        this.update();
    }

    async promptRename(relPath, isDir = false) {
        const oldName = relPath.split('/').pop();
        let currentProps = { description: '', usage: '', tags: [], author: '', stability: 'stable', attributes: {} };
        try {
            const res = await this.admin.api('storage_get_file_props', { path: relPath });
            const data = (res.success && res.data) ? res.data : res;
            if (data.has_props) {
                currentProps = {
                    description: data.description || '',
                    usage: data.usage || '',
                    tags: data.attributes?.tags || data.tags || [],
                    author: data.attributes?.author || data.author || '',
                    stability: data.attributes?.stability || data.stability || 'stable',
                    attributes: data.attributes || {}
                };
            }
        } catch (e) {}

        this.setState({
            filePropsModal: {
                mode: 'rename',
                type: isDir ? 'folder' : 'file',
                oldPath: relPath,
                name: oldName,
                oldName: oldName,
                description: currentProps.description,
                usage: currentProps.usage,
                tags: Array.isArray(currentProps.tags) ? currentProps.tags.join(', ') : '',
                author: currentProps.author,
                stability: currentProps.stability,
                path: relPath
            }
        });
        this.update();
    }

    async editFileProps(relPath, isDir = false) {
        const fileName = relPath.split('/').pop();
        let currentProps = {
            description: '',
            usage: '',
            tags: [],
            author: '',
            stability: 'stable',
            layer: 'unspecified',
            checksum: '',
            required_props: [],
            depends_on: [],
            tests: [],
            assets: [],
            routes: '',
            attributes: {}
        };
        try {
            const res = await this.admin.api('storage_get_file_props', { path: relPath });
            const data = (res.success && res.data) ? res.data : res;
            if (data.has_props || data.description || data.attributes) {
                const attrs = data.attributes || {};
                currentProps = {
                    canonical_path: data.canonical_path || relPath,
                    size: data.size || 0,
                    mime_type: data.mime_type || '',
                    modified: data.modified || '',
                    manifest_path: data.manifest_path || '',
                    description: data.description || '',
                    usage: data.usage || '',
                    tags: attrs.tags || data.tags || [],
                    author: attrs.author || data.author || '',
                    stability: attrs.stability || data.stability || 'stable',
                    layer: attrs.layer || data.layer || 'unspecified',
                    checksum: data.checksum || attrs.checksum || '',
                    review_date: data.review_date || attrs.review_date || '',
                    security: data.security || { allow_network: true, allow_filesystem: true, allow_env: false, allow_shell: false },
                    is_app: !!(data.is_app || attrs.is_app),
                    app_name: data.app_name || attrs.app_name || '',
                    app_type: data.app_type || attrs.app_type || 'app',
                    entry_point: data.entry_point || attrs.entry_point || '',
                    required_props: attrs.required_props || [],
                    depends_on: attrs.depends_on || [],
                    tests: attrs.tests || [],
                    assets: attrs.assets || [],
                    routes: attrs.routes ? (typeof attrs.routes === 'string' ? attrs.routes : JSON.stringify(attrs.routes, null, 2)) : '',
                    feature_flag: attrs.feature_flag ? (typeof attrs.feature_flag === 'string' ? attrs.feature_flag : JSON.stringify(attrs.feature_flag, null, 2)) : '',
                    slo_budget: attrs.slo_budget ? (typeof attrs.slo_budget === 'string' ? attrs.slo_budget : JSON.stringify(attrs.slo_budget, null, 2)) : '',
                    telemetry: attrs.telemetry ? (typeof attrs.telemetry === 'string' ? attrs.telemetry : JSON.stringify(attrs.telemetry, null, 2)) : '',
                    subscribes: attrs.subscribes ? (typeof attrs.subscribes === 'string' ? attrs.subscribes : JSON.stringify(attrs.subscribes, null, 2)) : '',
                    compliance: attrs.compliance ? (typeof attrs.compliance === 'string' ? attrs.compliance : JSON.stringify(attrs.compliance, null, 2)) : '',
                    attributes: attrs
                };
            }
        } catch (e) {
            console.error("Failed to load file properties:", e);
        }

        this.setState({
            filePropsModal: {
                mode: 'edit',
                type: isDir ? 'folder' : 'file',
                name: fileName,
                oldName: fileName,
                path: relPath,
                canonical_path: currentProps.canonical_path || relPath,
                size: currentProps.size || 0,
                mime_type: currentProps.mime_type || '',
                modified: currentProps.modified || '',
                manifest_path: currentProps.manifest_path || '',
                description: currentProps.description,
                usage: currentProps.usage,
                tags: Array.isArray(currentProps.tags) ? currentProps.tags.join(', ') : (currentProps.tags || ''),
                author: currentProps.author,
                stability: currentProps.stability,
                layer: currentProps.layer,
                checksum: currentProps.checksum,
                review_date: currentProps.review_date,
                security_network: currentProps.security?.allow_network !== false,
                security_filesystem: currentProps.security?.allow_filesystem !== false,
                security_env: !!currentProps.security?.allow_env,
                security_shell: !!currentProps.security?.allow_shell,
                taint_sanitizers: Array.isArray(currentProps.security?.taint_sanitizers) ? currentProps.security.taint_sanitizers.join(', ') : (currentProps.security?.taint_sanitizers || ''),
                is_app: !!currentProps.is_app,
                app_name: currentProps.app_name || '',
                app_type: currentProps.app_type || 'app',
                entry_point: currentProps.entry_point || '',
                required_props: Array.isArray(currentProps.required_props) ? currentProps.required_props.join(', ') : (currentProps.required_props || ''),
                depends_on: Array.isArray(currentProps.depends_on) ? currentProps.depends_on.join(', ') : (currentProps.depends_on || ''),
                tests: Array.isArray(currentProps.tests) ? currentProps.tests.join(', ') : (currentProps.tests || ''),
                assets: Array.isArray(currentProps.assets) ? currentProps.assets.join(', ') : (currentProps.assets || ''),
                routes: currentProps.routes,
                feature_flag: currentProps.feature_flag,
                slo_budget: currentProps.slo_budget,
                telemetry: currentProps.telemetry,
                subscribes: currentProps.subscribes,
                compliance: currentProps.compliance,
                ai_directives_importance: currentProps.attributes?.ai_directives?.importance || 'standard',
                ai_directives_rules: Array.isArray(currentProps.attributes?.ai_directives?.rules) ? currentProps.attributes.ai_directives.rules.join('\n') : (currentProps.attributes?.ai_directives?.rules || ''),
                ai_directives_forbidden: Array.isArray(currentProps.attributes?.ai_directives?.forbidden_mutations) ? currentProps.attributes.ai_directives.forbidden_mutations.join(', ') : '',
                ai_directives_allowed: Array.isArray(currentProps.attributes?.ai_directives?.allowed_mutations) ? currentProps.attributes.ai_directives.allowed_mutations.join(', ') : '',
                data_classification: currentProps.attributes?.data_governance?.classification || 'INTERNAL',
                data_mask_fields: Array.isArray(currentProps.attributes?.data_governance?.auto_mask_fields) ? currentProps.attributes.data_governance.auto_mask_fields.join(', ') : '',
                data_regions: Array.isArray(currentProps.attributes?.data_governance?.export_restricted_regions) ? currentProps.attributes.data_governance.export_restricted_regions.join(', ') : '',
                cache_ttl: currentProps.attributes?.cache?.ttl !== undefined ? String(currentProps.attributes.cache.ttl) : '',
                cache_entities: Array.isArray(currentProps.attributes?.cache?.invalidated_by_entities) ? currentProps.attributes.cache.invalidated_by_entities.join(', ') : '',
                cache_events: Array.isArray(currentProps.attributes?.cache?.invalidated_by_events) ? currentProps.attributes.cache.invalidated_by_events.join(', ') : '',
                activeTab: 'overview',
                validationError: null
            }
        });
        this.update();
    }

    async editDirectoryProps() {
        const dirPath = this.state.currentPath || '';
        const dirName = dirPath.split('/').pop() || 'root';
        let currentProps = {
            description: '',
            usage: '',
            tags: [],
            author: '',
            stability: 'stable',
            layer: 'unspecified',
            review_date: '',
            security: {
                allow_network: true,
                allow_filesystem: true,
                allow_env: false,
                allow_shell: false
            },
            attributes: {}
        };
        try {
            const res = await this.admin.api('storage_get_directory_props', { path: dirPath });
            const data = (res.success && res.data) ? res.data : res;
            if (data.directory_props) {
                const dp = data.directory_props;
                currentProps = {
                    description: dp.description || '',
                    usage: dp.usage || '',
                    tags: dp.tags || dp.attributes?.tags || [],
                    author: dp.author || '',
                    stability: dp.stability || 'stable',
                    layer: dp.layer || dp.attributes?.layer || 'unspecified',
                    review_date: dp.review_date || '',
                    security: dp.security || currentProps.security,
                    attributes: dp.attributes || {}
                };
            }
        } catch (e) {
            console.error("Failed to load directory properties:", e);
        }

        this.setState({
            filePropsModal: {
                mode: 'edit_directory',
                type: 'folder',
                name: dirName,
                oldName: dirName,
                path: dirPath,
                canonical_path: dirPath,
                size: 0,
                mime_type: 'directory',
                modified: 'Active Directory',
                manifest_path: `${dirPath}/__spp_file_props.yml`,
                description: currentProps.description,
                usage: currentProps.usage,
                tags: Array.isArray(currentProps.tags) ? currentProps.tags.join(', ') : (currentProps.tags || ''),
                author: currentProps.author,
                stability: currentProps.stability,
                layer: currentProps.layer,
                review_date: currentProps.review_date,
                security_network: currentProps.security?.allow_network !== false,
                security_filesystem: currentProps.security?.allow_filesystem !== false,
                security_env: !!currentProps.security?.allow_env,
                security_shell: !!currentProps.security?.allow_shell,
                taint_sanitizers: Array.isArray(currentProps.security?.taint_sanitizers) ? currentProps.security.taint_sanitizers.join(', ') : '',
                activeTab: 'overview',
                validationError: null
            }
        });
        this.update();
    }

    syncModalInputsToState() {
        const m = this.state.filePropsModal;
        if (!m) return;
        const g = (id) => document.getElementById(id);
        if (g('props-modal-name')) m.name = g('props-modal-name').value;
        if (g('props-modal-desc')) m.description = g('props-modal-desc').value;
        if (g('props-modal-usage')) m.usage = g('props-modal-usage').value;
        if (g('props-modal-tags')) m.tags = g('props-modal-tags').value;
        if (g('props-modal-author')) m.author = g('props-modal-author').value;
        if (g('props-modal-stability')) m.stability = g('props-modal-stability').value;
        if (g('props-modal-layer')) m.layer = g('props-modal-layer').value;
        if (g('props-modal-review-date')) m.review_date = g('props-modal-review-date').value;
        if (g('props-modal-is-app')) m.is_app = g('props-modal-is-app').checked;
        if (g('props-modal-app-name')) m.app_name = g('props-modal-app-name').value;
        if (g('props-modal-app-type')) m.app_type = g('props-modal-app-type').value;
        if (g('props-modal-entry-point')) m.entry_point = g('props-modal-entry-point').value;
        if (g('props-modal-sec-network')) m.security_network = g('props-modal-sec-network').checked;
        if (g('props-modal-sec-filesystem')) m.security_filesystem = g('props-modal-sec-filesystem').checked;
        if (g('props-modal-sec-env')) m.security_env = g('props-modal-sec-env').checked;
        if (g('props-modal-sec-shell')) m.security_shell = g('props-modal-sec-shell').checked;
        if (g('props-modal-sec-taint')) m.taint_sanitizers = g('props-modal-sec-taint').value;
        if (g('props-modal-required-props')) m.required_props = g('props-modal-required-props').value;
        if (g('props-modal-depends-on')) m.depends_on = g('props-modal-depends-on').value;
        if (g('props-modal-tests')) m.tests = g('props-modal-tests').value;
        if (g('props-modal-assets')) m.assets = g('props-modal-assets').value;
        if (g('props-modal-routes')) m.routes = g('props-modal-routes').value;
        if (g('props-modal-feature-flag')) m.feature_flag = g('props-modal-feature-flag').value;
        if (g('props-modal-slo-budget')) m.slo_budget = g('props-modal-slo-budget').value;
        if (g('props-modal-telemetry')) m.telemetry = g('props-modal-telemetry').value;
        if (g('props-modal-subscribes')) m.subscribes = g('props-modal-subscribes').value;
        if (g('props-modal-compliance')) m.compliance = g('props-modal-compliance').value;
        if (g('props-modal-ai-importance')) m.ai_directives_importance = g('props-modal-ai-importance').value;
        if (g('props-modal-ai-rules')) m.ai_directives_rules = g('props-modal-ai-rules').value;
        if (g('props-modal-ai-forbidden')) m.ai_directives_forbidden = g('props-modal-ai-forbidden').value;
        if (g('props-modal-ai-allowed')) m.ai_directives_allowed = g('props-modal-ai-allowed').value;
        if (g('props-modal-data-classification')) m.data_classification = g('props-modal-data-classification').value;
        if (g('props-modal-data-mask-fields')) m.data_mask_fields = g('props-modal-data-mask-fields').value;
        if (g('props-modal-data-regions')) m.data_regions = g('props-modal-data-regions').value;
        if (g('props-modal-cache-ttl')) m.cache_ttl = g('props-modal-cache-ttl').value;
        if (g('props-modal-cache-entities')) m.cache_entities = g('props-modal-cache-entities').value;
        if (g('props-modal-cache-events')) m.cache_events = g('props-modal-cache-events').value;
    }

    switchPropsModalTab(tab) {
        if (!this.state.filePropsModal) return;
        this.syncModalInputsToState();
        this.state.filePropsModal.activeTab = tab;
        this.update();
    }

    async submitFilePropsModal() {
        const m = this.state.filePropsModal;
        if (!m) return;

        this.syncModalInputsToState();

        const errBanner = document.getElementById('props-modal-error-banner');
        const showError = (msg, targetTab = null) => {
            if (targetTab && m.activeTab !== targetTab) {
                this.switchPropsModalTab(targetTab);
            }
            setTimeout(() => {
                const banner = document.getElementById('props-modal-error-banner');
                if (banner) {
                    banner.textContent = msg;
                    banner.style.display = 'block';
                }
            }, 50);
            this.admin.notify(msg, "error");
        };

        if (errBanner) errBanner.style.display = 'none';

        const name = (m.name || '').trim();
        if (!name && m.mode !== 'edit') {
            showError("Please enter a name.", 'general');
            return;
        }

        const description = (m.description || '').trim();
        const usage = (m.usage || '').trim();
        const rawTags = (m.tags || '').trim();
        const tags = rawTags ? rawTags.split(',').map(t => t.trim()).filter(Boolean) : [];
        const author = (m.author || '').trim();
        const stability = (m.stability || 'stable') || 'stable';
        const layer = (m.layer || 'unspecified') || 'unspecified';

        const parseList = (val) => {
            if (!val) return [];
            return String(val).split(',').map(item => item.trim()).filter(Boolean);
        };

        const required_props = parseList(m.required_props);
        const depends_on = parseList(m.depends_on);
        const tests = parseList(m.tests);
        const assets = parseList(m.assets);

        // Parse and validate routes JSON if provided
        let routes = [];
        const rawRoutes = (m.routes || '').trim();
        if (rawRoutes) {
            try {
                const parsed = JSON.parse(rawRoutes);
                if (!Array.isArray(parsed)) {
                    showError("Routes must be a valid JSON array of route objects.", 'contracts');
                    return;
                }
                for (let i = 0; i < parsed.length; i++) {
                    if (!parsed[i].path) {
                        showError(`Route at index ${i} is missing the required 'path' property.`, 'contracts');
                        return;
                    }
                }
                routes = parsed;
            } catch (jsonErr) {
                showError("Invalid JSON in Routes field: " + jsonErr.message, 'contracts');
                return;
            }
        }

        const attributes = { tags, stability, layer };
        if (author) attributes.author = author;
        if (required_props.length > 0) attributes.required_props = required_props;
        if (depends_on.length > 0) attributes.depends_on = depends_on;
        if (tests.length > 0) attributes.tests = tests;
        if (assets.length > 0) attributes.assets = assets;
        if (routes.length > 0) attributes.routes = routes;

        // Parse and validate feature_flag
        const rawFF = (m.feature_flag || '').trim();
        if (rawFF) {
            try {
                const parsed = JSON.parse(rawFF);
                if (typeof parsed !== 'object' || Array.isArray(parsed) || !parsed.flag) {
                    showError("Feature flag must be a JSON object with at least a 'flag' property.", 'governance');
                    return;
                }
                if (parsed.rollout_percentage !== undefined && (parsed.rollout_percentage < 0 || parsed.rollout_percentage > 100)) {
                    showError("Feature flag 'rollout_percentage' must be between 0 and 100.", 'governance');
                    return;
                }
                attributes.feature_flag = parsed;
            } catch (e) {
                showError("Invalid JSON in Feature Flag: " + e.message, 'governance');
                return;
            }
        }

        // Parse and validate slo_budget
        const rawSLO = (m.slo_budget || '').trim();
        if (rawSLO) {
            try {
                const parsed = JSON.parse(rawSLO);
                if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                    showError("SLO Budget must be a JSON object.", 'governance');
                    return;
                }
                attributes.slo_budget = parsed;
            } catch (e) {
                showError("Invalid JSON in SLO Budget: " + e.message, 'governance');
                return;
            }
        }

        // Parse and validate telemetry
        const rawTel = (m.telemetry || '').trim();
        if (rawTel) {
            try {
                const parsed = JSON.parse(rawTel);
                if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                    showError("Telemetry must be a JSON object.", 'governance');
                    return;
                }
                if (parsed.sample_rate !== undefined && (parsed.sample_rate < 0 || parsed.sample_rate > 1)) {
                    showError("Telemetry 'sample_rate' must be between 0.0 and 1.0.", 'governance');
                    return;
                }
                attributes.telemetry = parsed;
            } catch (e) {
                showError("Invalid JSON in Telemetry: " + e.message, 'governance');
                return;
            }
        }

        // Parse and validate subscribes
        const rawSub = (m.subscribes || '').trim();
        if (rawSub) {
            try {
                const parsed = JSON.parse(rawSub);
                if (typeof parsed !== 'object' || Array.isArray(parsed) || !parsed.event) {
                    showError("Event subscription must be a JSON object with at least an 'event' property.", 'compliance');
                    return;
                }
                attributes.subscribes = parsed;
            } catch (e) {
                showError("Invalid JSON in Event Subscriptions: " + e.message, 'compliance');
                return;
            }
        }

        // Parse and validate compliance
        const rawComp = (m.compliance || '').trim();
        if (rawComp) {
            try {
                const parsed = JSON.parse(rawComp);
                if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                    showError("Compliance declaration must be a JSON object.", 'compliance');
                    return;
                }
                if (parsed.data_classification) {
                    const validC = ['PUBLIC', 'INTERNAL', 'RESTRICTED', 'PII_CONFIDENTIAL'];
                    if (!validC.includes(String(parsed.data_classification).toUpperCase())) {
                        showError("Data classification must be PUBLIC, INTERNAL, RESTRICTED, or PII_CONFIDENTIAL.", 'compliance');
                        return;
                    }
                }
                attributes.compliance = parsed;
            } catch (e) {
                showError("Invalid JSON in Compliance: " + e.message, 'compliance');
                return;
            }
        }

        // Package AI Directives
        if (m.ai_directives_rules || m.ai_directives_forbidden || m.ai_directives_allowed || (m.ai_directives_importance && m.ai_directives_importance !== 'standard')) {
            attributes.ai_directives = {
                importance: m.ai_directives_importance || 'standard',
                rules: (m.ai_directives_rules || '').split('\n').map(r => r.trim()).filter(Boolean),
                forbidden_mutations: parseList(m.ai_directives_forbidden),
                allowed_mutations: parseList(m.ai_directives_allowed)
            };
        }

        // Package Data Governance & Sovereignty
        if ((m.data_classification && m.data_classification !== 'INTERNAL') || m.data_mask_fields || m.data_regions) {
            attributes.data_governance = {
                classification: m.data_classification || 'INTERNAL',
                auto_mask_fields: parseList(m.data_mask_fields),
                export_restricted_regions: parseList(m.data_regions)
            };
        }

        // Package Cache Invalidation
        if (m.cache_ttl || m.cache_entities || m.cache_events) {
            attributes.cache = {
                ttl: parseInt(m.cache_ttl || '3600', 10),
                invalidated_by_entities: parseList(m.cache_entities),
                invalidated_by_events: parseList(m.cache_events)
            };
        }

        const propsPayload = {
            description,
            usage,
            tags,
            author,
            stability,
            layer,
            review_date: m.review_date || '',
            security: {
                allow_network: !!m.security_network,
                allow_filesystem: !!m.security_filesystem,
                allow_env: !!m.security_env,
                allow_shell: !!m.security_shell,
                taint_sanitizers: parseList(m.taint_sanitizers)
            },
            is_app: !!m.is_app,
            app_name: m.app_name || '',
            app_type: m.app_type || '',
            entry_point: m.entry_point || '',
            attributes
        };

        try {
            if (m.mode === 'edit_directory') {
                const res = await this.admin.api('storage_save_directory_props', {
                    path: m.path,
                    properties: {
                        description,
                        usage,
                        tags,
                        author,
                        stability,
                        layer,
                        review_date: m.review_date || '',
                        security: {
                            allow_network: !!m.security_network,
                            allow_filesystem: !!m.security_filesystem,
                            allow_env: !!m.security_env,
                            allow_shell: !!m.security_shell,
                            taint_sanitizers: parseList(m.taint_sanitizers)
                        },
                        attributes
                    }
                });
                if (res.success) {
                    this.admin.notify(`Directory properties saved to __spp_file_props.yml.`, "success");
                    this.closeFilePropsModal();
                    await this.loadDirectory(this.state.currentPath);
                } else {
                    showError(res.error || "Failed to save directory properties.");
                }
                return;
            } else if (m.mode === 'create') {
                const fullPath = this.joinPath(this.state.currentPath, name);
                const action = m.type === 'file' ? 'storage_create_file' : 'storage_create_directory';
                const res = await this.admin.api(action, {
                    path: fullPath,
                    properties: propsPayload
                });

                if (res.success) {
                    this.admin.notify(`${m.type === 'file' ? 'File' : 'Directory'} created with manifest properties.`, "success");
                    this.closeFilePropsModal();
                    await Promise.all([
                        this.loadDirectory(this.state.currentPath),
                        this.loadTree(true)
                    ]);
                } else {
                    showError(res.error || "Creation failed.");
                }
            } else if (m.mode === 'rename') {
                const parentPath = m.oldPath.includes('/') ? m.oldPath.substring(0, m.oldPath.lastIndexOf('/')) : '';
                const newPath = parentPath ? `${parentPath}/${name}` : name;

                if (name !== m.oldName) {
                    const renameRes = await this.admin.api('storage_rename_item', {
                        old_path: m.oldPath,
                        new_path: newPath
                    });
                    if (!renameRes.success) {
                        showError(renameRes.error || "Rename failed.");
                        return;
                    }
                    if (this.state.activeFile === m.oldPath) {
                        this.state.activeFile = newPath;
                    }
                }

                // Also save updated properties
                const saveRes = await this.admin.api('storage_save_file_props', {
                    path: newPath,
                    properties: propsPayload
                });

                if (saveRes.success) {
                    this.admin.notify(`Updated '${name}' and directory properties manifest.`, "success");
                    this.closeFilePropsModal();
                    await Promise.all([
                        this.loadDirectory(this.state.currentPath),
                        this.loadTree(true)
                    ]);
                } else {
                    showError(saveRes.error || "Failed to save manifest properties.");
                }
            } else if (m.mode === 'edit') {
                const res = await this.admin.api('storage_save_file_props', {
                    path: m.path,
                    properties: propsPayload
                });

                if (res.success) {
                    this.admin.notify(`File properties saved to __spp_file_props.yml.`, "success");
                    this.closeFilePropsModal();
                    await this.loadDirectory(this.state.currentPath);
                } else {
                    showError(res.error || "Failed to save properties.");
                }
            }
        } catch (e) {
            showError("Operation failed: " + e.message);
        }
    }

    closeFilePropsModal() {
        this.setState({ filePropsModal: null });
        this.update();
    }

    onEscape(e) {
        if (this.state && this.state.filePropsModal) {
            if (e && e.preventDefault) e.preventDefault();
            this.closeFilePropsModal();
            return true;
        }
        if (this.state && this.state.impactModal) {
            if (e && e.preventDefault) e.preventDefault();
            this.closeImpactModal();
            return true;
        }
        if (this.state && this.state.showShortcutsModal) {
            if (e && e.preventDefault) e.preventDefault();
            this.toggleShortcutsModal(false);
            return true;
        }
        return false;
    }

    insertPropsTemplate(fieldId, templateKey) {
        const templates = {
            routes: JSON.stringify([
                {
                    path: "/api/resource",
                    methods: ["GET"],
                    action: "ResourceController@index",
                    middleware: ["auth"]
                }
            ], null, 2),
            feature_flag: JSON.stringify({
                flag: "feature_pilot_v1",
                rollout_percentage: 25,
                target_roles: ["admin", "tester"],
                fallback_target: "LegacyController.php",
                kill_switch: false
            }, null, 2),
            slo_budget: JSON.stringify({
                max_duration_ms: 250,
                max_memory_mb: 64,
                max_db_queries: 12,
                strict_ci: true
            }, null, 2),
            telemetry: JSON.stringify({
                span_name: "Resource.Process",
                sample_rate: 1.0,
                baggage_propagation: ["tenant_id", "user_id"]
            }, null, 2),
            subscribes: JSON.stringify({
                event: "resource.created",
                queue: "default",
                debounce_seconds: 5,
                dead_letter_after_retries: 3
            }, null, 2),
            compliance: JSON.stringify({
                data_classification: "PII_CONFIDENTIAL",
                retention_period_days: 730,
                anonymize_on_delete: true,
                audit_access: true
            }, null, 2)
        };

        const targetEl = document.getElementById(fieldId);
        if (targetEl && templates[templateKey]) {
            targetEl.value = templates[templateKey];
            this.syncModalInputsToState();
            if (this.admin && this.admin.notify) {
                this.admin.notify(`Sample ${templateKey.replace(/_/g, ' ')} template inserted.`, 'info');
            }
        }
    }

    renderHelpCard(icon, title, summary, details = '') {
        return `
            <div style="background:rgba(56, 139, 253, 0.08); border:1px solid rgba(56, 139, 253, 0.22); border-radius:8px; padding:0.75rem 0.95rem; line-height:1.45; color:#c9d1d9;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                    <span style="font-size:1.05rem;">${icon}</span>
                    <strong style="color:#58a6ff; font-size:0.83rem;">${title}</strong>
                </div>
                <div style="color:#8b949e; font-size:0.77rem;">${summary}</div>
                ${details ? `<div style="margin-top:0.45rem; padding-top:0.45rem; border-top:1px dashed rgba(56,139,253,0.2); font-size:0.74rem; color:#8b949e; line-height:1.5;">${details}</div>` : ''}
            </div>
        `;
    }

    _bindGlobalKeyHandlers() {
        if (this._globalKeyDownBound || typeof window === 'undefined') return;
        this._globalKeyDownBound = true;
        window.addEventListener('keydown', (e) => {
            if (window.filesViewInstance !== this) return;

            const isInputFocused = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable;

            if (e.key === 'Escape') {
                if (this.state && this.state.imagePreviewModal) {
                    e.preventDefault();
                    this.closeImagePreview();
                } else if (this.state && this.state.bulkDeleteModal) {
                    e.preventDefault();
                    this.closeBulkDeleteModal();
                } else if (this.state && this.state.moveCopyModal) {
                    e.preventDefault();
                    this.closeMoveCopyModal();
                } else if (this.state && this.state.chmodModal) {
                    e.preventDefault();
                    this.closeChmodModal();
                } else if (this.state && this.state.isEditingAddress) {
                    e.preventDefault();
                    this.toggleAddressEdit(false);
                } else if (this.state && this.state.filePropsModal) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeFilePropsModal();
                } else if (this.state && this.state.impactModal) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeImpactModal();
                } else if (this.state && this.state.showShortcutsModal) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleShortcutsModal(false);
                } else if (this.state && this.state.searchQuery) {
                    this.clearSearch();
                } else if (this.state && this.state.selectedItems && this.state.selectedItems.size > 0) {
                    this.clearSelection();
                }
            } else if (e.altKey && e.key === 'ArrowLeft') {
                e.preventDefault();
                this.navigateBack();
            } else if (e.altKey && e.key === 'ArrowRight') {
                e.preventDefault();
                this.navigateForward();
            } else if ((e.altKey && e.key === 'ArrowUp') || (e.key === 'Backspace' && !isInputFocused)) {
                e.preventDefault();
                this.navigateUp();
            } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a' && !isInputFocused) {
                e.preventDefault();
                this.toggleSelectAll(true);
            } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'l') {
                e.preventDefault();
                this.toggleAddressEdit(true);
            } else if (e.key === 'Delete' && !isInputFocused) {
                if (this.state.selectedItems && this.state.selectedItems.size > 0) {
                    e.preventDefault();
                    this.promptBulkDelete();
                }
            } else if (e.key === 'F2' && !isInputFocused) {
                if (this.state.selectedItems && this.state.selectedItems.size === 1) {
                    e.preventDefault();
                    const single = Array.from(this.state.selectedItems)[0];
                    const item = (this.state.items || []).find(it => this.normalizePath(it.path) === single);
                    this.promptRename(single, item ? item.is_dir : false);
                }
            } else if (e.key === 'F5' || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'r' && !isInputFocused)) {
                e.preventDefault();
                this.loadDirectory(this.state.currentPath);
                this.loadTree(true);
            }
        }, true);
    }

    async inspectImpact(path) {
        try {
            this.admin.notify("Analyzing architectural blast radius...", "info");
            const res = await this.admin.api('manifest_impact', { path });
            if (res.success) {
                this.setState({ impactModal: res });
                this.update();
            } else {
                this.admin.notify(res.error || "Failed to analyze blast radius.", "error");
            }
        } catch (e) {
            this.admin.notify("Analysis failed: " + e.message, "error");
        }
    }

    closeImpactModal() {
        this.setState({ impactModal: null });
        this.update();
    }

    async promptMove(sourcePath) {
        const itemName = sourcePath.split('/').pop();
        const destDir = prompt(`Enter destination directory path to move '${itemName}' into:`, this.state.currentPath || '');
        if (destDir === null) return;

        await this.moveItem(sourcePath, destDir.trim());
    }

    async moveItem(sourcePath, destinationDir) {
        const itemName = sourcePath.split('/').pop();
        try {
            const res = await this.admin.api('storage_move_item', {
                source: sourcePath,
                destination: destinationDir
            });
            if (res.success) {
                this.admin.notify(`Moved '${itemName}' to '${destinationDir || 'root'}'.`, "success");
                if (this.state.activeFile === sourcePath) {
                    const newActive = destinationDir ? `${destinationDir}/${itemName}` : itemName;
                    this.state.activeFile = newActive;
                }
                await Promise.all([
                    this.loadDirectory(this.state.currentPath),
                    this.loadTree(true)
                ]);
            } else {
                this.admin.notify(res.error || "Move failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Move error: " + e.message, "error");
        }
    }

    handleFileInputChange(fileList) {
        if (fileList && fileList.length > 0) {
            this.uploadFiles(fileList, this.state.currentPath);
        }
    }

    async uploadFiles(fileList, targetDir = null) {
        if (!fileList || fileList.length === 0) return;
        const dir = targetDir !== null ? targetDir : this.state.currentPath;
        const files = Array.from(fileList);

        this.admin.notify(`Uploading ${files.length} file(s)...`, "info");

        let successCount = 0;
        for (const file of files) {
            try {
                const base64Data = await this.readFileAsBase64(file);
                const res = await this.admin.api('storage_upload_file', {
                    path: dir,
                    filename: file.name,
                    content_base64: base64Data
                });
                if (res.success) {
                    successCount++;
                } else {
                    this.admin.notify(`Failed to upload '${file.name}': ${res.error}`, "error");
                }
            } catch (e) {
                this.admin.notify(`Upload error on '${file.name}': ${e.message}`, "error");
            }
        }

        if (successCount > 0) {
            this.admin.notify(`Successfully uploaded ${successCount} file(s).`, "success");
            await Promise.all([
                this.loadDirectory(this.state.currentPath),
                this.loadTree(true)
            ]);
        }
    }

    readFileAsBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
            reader.readAsDataURL(file);
        });
    }

    downloadFile(relPath) {
        const downloadUrl = (typeof APP_BASE_URI !== 'undefined' ? APP_BASE_URI : '') +
            '/sppadmin/api.php?action=storage_download_file&path=' + encodeURIComponent(relPath);
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = relPath.split('/').pop();
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    onRowDragStart(e, path) {
        e.dataTransfer.setData('text/plain', path);
        e.dataTransfer.setData('application/x-spp-item', path);
        e.dataTransfer.effectAllowed = 'move';
        const row = e.target.closest('tr');
        if (row) row.classList.add('dragging');
    }

    onRowDragEnd(e) {
        const row = e.target.closest('tr');
        if (row) row.classList.remove('dragging');
        document.querySelectorAll('.drag-over, .drag-active').forEach(el => {
            el.classList.remove('drag-over', 'drag-active');
        });
    }

    onFolderDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'move';
        e.currentTarget.classList.add('drag-over');
    }

    onFolderDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('drag-over');
    }

    async onFolderDrop(e, targetDir) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('drag-over');

        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            await this.uploadFiles(e.dataTransfer.files, targetDir);
            return;
        }

        const sourcePath = e.dataTransfer.getData('application/x-spp-item') || e.dataTransfer.getData('text/plain');
        if (sourcePath && sourcePath !== targetDir) {
            await this.moveItem(sourcePath, targetDir);
        }
    }

    onDropzoneDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.add('drag-active');
    }

    onDropzoneDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('drag-active');
    }

    async onDropzoneDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('drag-active');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            await this.uploadFiles(e.dataTransfer.files, this.state.currentPath);
        }
    }

    onSearchInput(query) {
        this.state.searchQuery = query;
        const tbody = document.getElementById('spp-files-table-body');
        if (tbody) {
            tbody.innerHTML = this.renderFileRows();
            const clearBtn = document.getElementById('spp-files-filter-clear');
            if (clearBtn) {
                clearBtn.style.display = query ? 'inline-block' : 'none';
            }
        } else {
            this.setState({ searchQuery: query });
            this.update();
        }
    }

    clearSearch() {
        this.onSearchInput('');
        const input = document.getElementById('spp-files-filter-input');
        if (input) {
            input.value = '';
            input.focus();
        }
    }

    toggleShortcutsModal(show) {
        this.setState({ showShortcutsModal: typeof show === 'boolean' ? show : !this.state.showShortcutsModal });
        this.update();
    }

    _bindGlobalContextMenuDismissal() {
        if (this._ctxDismissBound) return;
        this._ctxDismissBound = true;
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#spp-file-context-menu')) {
                this.closeContextMenu();
            }
            if (!e.target.closest('.spp-dropdown-container')) {
                this.closeDropdowns();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeContextMenu();
                this.closeDropdowns();
            }
        });
        window.addEventListener('scroll', () => {
            this.closeContextMenu();
        }, true);
    }

    onRowContextMenu(e, path) {
        e.preventDefault();
        e.stopPropagation();
        const normPath = (path || '').replace(/\\/g, '/');
        const item = (this.state.items || []).find(it => (it.path || '').replace(/\\/g, '/') === normPath) || {
            name: normPath.split('/').pop(),
            path: normPath,
            is_dir: false
        };
        this.openContextMenu(e, item, false);
    }

    onTreeContextMenu(e, path) {
        e.preventDefault();
        e.stopPropagation();
        const normPath = (path || '').replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
        const isRoot = !normPath;
        const item = {
            name: isRoot ? 'Root (/)' : normPath.split('/').pop(),
            path: normPath,
            is_dir: true,
            isRoot: isRoot
        };
        this.openContextMenu(e, item, true);
    }

    openContextMenu(e, item, isTreeNode = false) {
        let x = e.clientX || 0;
        let y = e.clientY || 0;

        const menuWidth = 230;
        const menuHeight = 340;
        if (x + menuWidth > window.innerWidth - 10) {
            x = Math.max(10, window.innerWidth - menuWidth - 10);
        }
        if (y + menuHeight > window.innerHeight - 10) {
            y = Math.max(10, window.innerHeight - menuHeight - 10);
        }

        this.state.contextMenu = { x, y, item, isTreeNode };
        this.renderContextMenuPortal();
    }

    closeContextMenu() {
        this.state.contextMenu = null;
        const menu = document.getElementById('spp-file-context-menu');
        if (menu) {
            menu.remove();
        }
    }

    renderContextMenuPortal() {
        const cm = this.state.contextMenu;
        if (!cm || !cm.item) {
            this.closeContextMenu();
            return;
        }

        let menuEl = document.getElementById('spp-file-context-menu');
        if (!menuEl) {
            menuEl = document.createElement('div');
            menuEl.id = 'spp-file-context-menu';
            menuEl.className = 'spp-file-context-menu';
            document.body.appendChild(menuEl);
        }

        menuEl.style.left = `${cm.x}px`;
        menuEl.style.top = `${cm.y}px`;

        const item = cm.item;
        const isDir = Boolean(item.is_dir);
        const isZip = !isDir && (item.extension === 'zip' || (item.name && item.name.endsWith('.zip')));
        const icon = this.getFileIcon(item.extension, isDir, item.name);
        const safePath = this.escapeHtml(item.path || '');

        let html = `
            <div class="spp-ctx-header" title="${this.escapeHtml(item.name)}">
                <span>${icon}</span>
                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1;">${this.escapeHtml(item.name)}</span>
                <span class="suite-pill suite-pill-neutral" style="font-size:0.65rem; padding:0.05rem 0.35rem; margin-left:4px;">
                    ${isDir ? 'Folder' : (item.size ? this.formatBytes(item.size) : 'File')}
                </span>
            </div>
        `;

        const isImage = !isDir && ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'].includes((item.extension || '').toLowerCase());

        if (isDir) {
            html += `
                <div class="spp-ctx-item" onclick="window.filesViewInstance.loadDirectory('${safePath}'); window.filesViewInstance.closeContextMenu();">
                    <span>📂</span> <span>Open Folder</span>
                </div>
                <div class="spp-ctx-item" onclick="window.filesViewInstance.downloadFolderAsZip('${safePath}'); window.filesViewInstance.closeContextMenu();">
                    <span>⬇️</span> <span>Download Folder as ZIP</span>
                </div>
                <div class="spp-ctx-item" onclick="window.filesViewInstance.promptNewItem('file'); window.filesViewInstance.closeContextMenu();">
                    <span>📄</span> <span>New File Here...</span>
                </div>
                <div class="spp-ctx-item" onclick="window.filesViewInstance.promptNewItem('folder'); window.filesViewInstance.closeContextMenu();">
                    <span>📁</span> <span>New Subfolder Here...</span>
                </div>
            `;
        } else {
            if (isImage) {
                html += `
                    <div class="spp-ctx-item" onclick="window.filesViewInstance.previewImage('${safePath}'); window.filesViewInstance.closeContextMenu();">
                        <span>👁️</span> <span>Preview Image</span>
                    </div>
                `;
            }
            html += `
                <div class="spp-ctx-item" onclick="window.filesViewInstance.openFile('${safePath}'); window.filesViewInstance.closeContextMenu();">
                    <span>✏️</span> <span>Edit in Editor</span>
                </div>
                <div class="spp-ctx-item" onclick="window.filesViewInstance.downloadFile('${safePath}'); window.filesViewInstance.closeContextMenu();">
                    <span>⬇️</span> <span>Download File</span>
                </div>
            `;
            if (isZip) {
                html += `
                    <div class="spp-ctx-item" onclick="window.filesViewInstance.extractZip('${safePath}'); window.filesViewInstance.closeContextMenu();">
                        <span>📦</span> <span>Extract ZIP Archive</span>
                    </div>
                `;
            }
        }

        html += `
            <div class="spp-ctx-divider"></div>
            <div class="spp-ctx-item" onclick="window.filesViewInstance.openMoveCopyModal('copy', '${safePath}'); window.filesViewInstance.closeContextMenu();">
                <span>📋</span> <span>Copy To...</span>
            </div>
            <div class="spp-ctx-item" onclick="window.filesViewInstance.openMoveCopyModal('move', '${safePath}'); window.filesViewInstance.closeContextMenu();">
                <span>↗️</span> <span>Move To...</span>
            </div>
            <div class="spp-ctx-item" onclick="window.filesViewInstance.promptRename('${safePath}', ${isDir}); window.filesViewInstance.closeContextMenu();">
                <span>✏️</span> <span>Rename...</span>
            </div>
            <div class="spp-ctx-item" onclick="window.filesViewInstance.openChmodModal('${safePath}', '${item.permissions || (isDir ? '0755' : '0644')}'); window.filesViewInstance.closeContextMenu();">
                <span>🔒</span> <span>Permissions (chmod)...</span>
            </div>
            <div class="spp-ctx-item" onclick="window.filesViewInstance.compressItem('${safePath}', ${isDir}); window.filesViewInstance.closeContextMenu();">
                <span>📦</span> <span>Compress to ZIP...</span>
            </div>
            <div class="spp-ctx-item" onclick="window.filesViewInstance.editFileProps('${safePath}', ${isDir}); window.filesViewInstance.closeContextMenu();">
                <span>🏷️</span> <span>Notes & Tags (__spp_file_props)</span>
            </div>
            <div class="spp-ctx-item" onclick="window.filesViewInstance.copyPathToClipboard('${safePath}'); window.filesViewInstance.closeContextMenu();">
                <span>🔗</span> <span>Copy Server Path</span>
            </div>
        `;

        if (!isDir && (safePath.endsWith('.zone') || safePath.includes('var/dns/'))) {
            const zDomain = safePath.split('/').pop().replace(/\.zone$/, '');
            html += `
                <div class="spp-ctx-item" onclick="window.location.hash='#domains?zone=' + encodeURIComponent('${zDomain}'); window.filesViewInstance.closeContextMenu();">
                    <span>🌐</span> <span>Open in DNS Manager</span>
                </div>
            `;
        }

        if (!isDir) {
            html += `
                <div class="spp-ctx-item" onclick="window.filesViewInstance.verifyFileIntegrity('${safePath}'); window.filesViewInstance.closeContextMenu();">
                    <span>🔒</span> <span>Verify SHA-256 Integrity</span>
                </div>
                <div class="spp-ctx-item" onclick="window.filesViewInstance.signFile('${safePath}'); window.filesViewInstance.closeContextMenu();">
                    <span>🔏</span> <span>Sign File in Manifest</span>
                </div>
                <div class="spp-ctx-item" onclick="window.filesViewInstance.inspectImpact('${safePath}'); window.filesViewInstance.closeContextMenu();">
                    <span>⚡</span> <span>Inspect Blast Radius</span>
                </div>
            `;
        }

        if (!item.isRoot) {
            html += `
                <div class="spp-ctx-divider"></div>
                <div class="spp-ctx-item danger" onclick="window.filesViewInstance.deleteItem('${safePath}', ${isDir}); window.filesViewInstance.closeContextMenu();">
                    <span>🗑️</span> <span>Delete</span>
                </div>
            `;
        }

        menuEl.innerHTML = html;
    }

    copyPathToClipboard(path) {
        const p = path || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(p).then(() => {
                this.admin.notify("Path copied to clipboard: " + p, "info");
            }).catch(() => {
                this._fallbackCopyText(p);
            });
        } else {
            this._fallbackCopyText(p);
        }
    }

    _fallbackCopyText(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            this.admin.notify("Path copied to clipboard: " + text, "info");
        } catch (e) {
            prompt("Copy path manually:", text);
        }
        document.body.removeChild(ta);
    }

    async promptChmod(path, currentPerms = '0644') {
        const val = prompt(`Change permissions (octal, e.g. 0644, 0755, 0777) for '${path}':`, currentPerms || '0644');
        if (!val) return;
        const clean = val.trim();
        if (!/^[0-7]{3,4}$/.test(clean)) {
            this.admin.notify("Invalid octal permissions. Must be 3 or 4 digits 0-7.", "error");
            return;
        }
        try {
            const res = await this.admin.api('storage_chmod', { path, mode: clean });
            if (res.success) {
                this.admin.notify(`Permissions updated for '${path}' (${res.permissions || clean})`, "success");
                await this.loadDirectory(this.state.currentPath);
            } else {
                this.admin.notify(res.error || "Failed to update permissions.", "error");
            }
        } catch (e) {
            this.admin.notify("Error updating permissions: " + e.message, "error");
        }
    }

    async compressItem(path, isDir = false) {
        const name = (path || '').split('/').pop() || 'archive';
        const defaultOut = (path ? path : 'archive') + '.zip';
        const target = prompt(`Enter output ZIP path for '${path}':`, defaultOut);
        if (!target) return;
        try {
            this.admin.notify(`Creating ZIP archive...`, "info");
            const res = await this.admin.api('archive_create', {
                source_path: path,
                destination_path: target
            });
            if (res.success) {
                this.admin.notify("ZIP archive created successfully.", "success");
                await this.loadDirectory(this.state.currentPath);
            } else {
                this.admin.notify(res.error || "Failed to create archive.", "error");
            }
        } catch (e) {
            this.admin.notify("Archive error: " + e.message, "error");
        }
    }

    async deleteItem(relPath, isDir = false) {
        if (!confirm(`Delete ${isDir ? 'directory and all its contents' : 'file'}: '${relPath}'?`)) {
            return;
        }

        try {
            const res = await this.admin.api('storage_delete_item', { path: relPath });
            if (res.success) {
                this.admin.notify("Item deleted.", "success");
                await Promise.all([
                    this.loadDirectory(this.state.currentPath),
                    this.loadTree(true)
                ]);
                if (this.state.activeFile === relPath) {
                    this.closeEditor();
                }
            } else {
                this.admin.notify(res.error || "Failed to delete item.", "error");
            }
        } catch (e) {
            this.admin.notify("Delete error: " + e.message, "error");
        }
    }

    async extractZip(zipPath) {
        if (!confirm(`Extract ZIP archive '${zipPath}' into current directory?`)) {
            return;
        }

        try {
            const res = await this.admin.api('storage_extract_zip', {
                zip_path: zipPath,
                dest_dir: this.state.currentPath
            });

            if (res.success) {
                this.admin.notify(res.message || "Archive extracted successfully.", "success");
                await Promise.all([
                    this.loadDirectory(this.state.currentPath),
                    this.loadTree(true)
                ]);
            } else {
                this.admin.notify(res.error || "Extraction failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Zip error: " + e.message, "error");
        }
    }

    openRowMoreMenu(e, path, isDir = false) {
        e.preventDefault();
        e.stopPropagation();
        const normPath = this.normalizePath(path);
        const item = (this.state.items || []).find(it => this.normalizePath(it.path) === normPath) || {
            name: normPath.split('/').pop(),
            path: normPath,
            is_dir: isDir
        };
        const rect = e.currentTarget.getBoundingClientRect();
        this.state.contextMenu = {
            item: item,
            isTree: false,
            x: Math.max(10, Math.min(window.innerWidth - 260, rect.left - 180)),
            y: Math.min(window.innerHeight - 320, rect.bottom + 4)
        };
        this.renderContextMenuPortal();
    }

    downloadFolderAsZip(folderPath) {
        const url = (typeof APP_BASE_URI !== 'undefined' ? APP_BASE_URI : '') +
            '/sppadmin/api.php?action=storage_download_folder&path=' + encodeURIComponent(folderPath);
        const link = document.createElement('a');
        link.href = url;
        link.download = (folderPath.split('/').pop() || 'archive') + '.zip';
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    downloadSelectedAsZip() {
        if (!this.state.selectedItems || this.state.selectedItems.size === 0) {
            this.admin.notify("No items selected to download.", "warning");
            return;
        }
        const paths = Array.from(this.state.selectedItems);
        const url = (typeof APP_BASE_URI !== 'undefined' ? APP_BASE_URI : '') +
            '/sppadmin/api.php?action=storage_download_selected&paths=' + encodeURIComponent(JSON.stringify(paths));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'bundle_' + Date.now() + '.zip';
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    promptBulkDelete() {
        if (!this.state.selectedItems || this.state.selectedItems.size === 0) {
            this.admin.notify("Please select one or more items to delete.", "warning");
            return;
        }
        this.setState({ bulkDeleteModal: true });
        this.update();
    }

    closeBulkDeleteModal() {
        this.setState({ bulkDeleteModal: false });
        this.update();
    }

    async confirmBulkDelete() {
        const paths = Array.from(this.state.selectedItems);
        this.setState({ bulkDeleteModal: false, loading: true });
        this.update();
        try {
            const res = await this.admin.api('storage_bulk_delete', { paths });
            if (res.success) {
                this.admin.notify(res.message || `Deleted ${res.deleted_count} items.`, "success");
                this.state.selectedItems.clear();
                await Promise.all([
                    this.loadDirectory(this.state.currentPath),
                    this.loadTree(true)
                ]);
            } else {
                this.admin.notify(res.error || "Bulk delete failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Delete error: " + e.message, "error");
        } finally {
            this.setState({ loading: false });
            this.update();
        }
    }

    promptDeleteCurrent() {
        if (this.state.selectedItems && this.state.selectedItems.size > 0) {
            this.promptBulkDelete();
            return;
        }
        const cur = this.state.currentPath;
        const sysRoot = this.state.systemRoot || '/';
        if (!cur || cur === '/' || cur === sysRoot) {
            this.admin.notify("Cannot delete host system root. Select individual items using checkboxes to delete them.", "warning");
            return;
        }
        this.deleteItem(cur, true);
    }

    async promptBulkZip() {
        if (!this.state.selectedItems || this.state.selectedItems.size === 0) {
            this.admin.notify("Please select items to zip.", "warning");
            return;
        }
        const defaultName = (this.state.currentPath ? this.state.currentPath + '/' : '') + 'bundle_' + Date.now() + '.zip';
        const target = prompt("Enter output ZIP path:", defaultName);
        if (!target) return;

        const paths = Array.from(this.state.selectedItems);
        try {
            this.admin.notify(`Creating ZIP archive of ${paths.length} items...`, "info");
            const res = await this.admin.api('storage_compress_items', {
                paths: paths,
                output_path: target
            });
            if (res.success) {
                this.admin.notify(res.message || "Archive created successfully.", "success");
                this.state.selectedItems.clear();
                await Promise.all([
                    this.loadDirectory(this.state.currentPath),
                    this.loadTree(true)
                ]);
            } else {
                this.admin.notify(res.error || "Failed to create archive.", "error");
            }
        } catch (e) {
            this.admin.notify("Zip error: " + e.message, "error");
        }
    }

    openMoveCopyModal(mode = 'move', path = null) {
        const targetPaths = path ? [this.normalizePath(path)] : Array.from(this.state.selectedItems);
        if (targetPaths.length === 0) {
            this.admin.notify(`Please select one or more items to ${mode}.`, "warning");
            return;
        }
        this.setState({
            moveCopyModal: {
                mode: mode,
                paths: targetPaths,
                destination: this.state.currentPath || '/'
            }
        });
        this.update();
    }

    closeMoveCopyModal() {
        this.setState({ moveCopyModal: null });
        this.update();
    }

    async confirmMoveCopy() {
        const m = this.state.moveCopyModal;
        if (!m || !m.paths || m.paths.length === 0) return;
        const destInput = document.getElementById('spp-move-copy-dest');
        const dest = destInput ? destInput.value.trim() : m.destination;
        if (!dest) {
            this.admin.notify("Please specify destination folder path.", "warning");
            return;
        }
        const isCopy = m.mode === 'copy';
        this.setState({ moveCopyModal: null, loading: true });
        this.update();

        try {
            const apiAction = isCopy ? 'storage_bulk_copy' : 'storage_bulk_move';
            const res = await this.admin.api(apiAction, {
                paths: m.paths,
                destination: dest
            });
            if (res.success) {
                this.admin.notify(res.message || `Successfully ${isCopy ? 'copied' : 'moved'} items.`, "success");
                this.state.selectedItems.clear();
                await Promise.all([
                    this.loadDirectory(this.state.currentPath),
                    this.loadTree(true)
                ]);
            } else {
                this.admin.notify(res.error || `${isCopy ? 'Copy' : 'Move'} failed.`, "error");
            }
        } catch (e) {
            this.admin.notify("Operation error: " + e.message, "error");
        } finally {
            this.setState({ loading: false });
            this.update();
        }
    }

    openChmodModal(path = null, currentPerms = null) {
        const targetPaths = path ? [this.normalizePath(path)] : Array.from(this.state.selectedItems);
        if (targetPaths.length === 0) {
            this.admin.notify("Please select one or more items to change permissions.", "warning");
            return;
        }
        const initialPerms = currentPerms || '0644';
        this.setState({
            chmodModal: {
                paths: targetPaths,
                mode: initialPerms,
                recursive: false
            }
        });
        this.update();
    }

    closeChmodModal() {
        this.setState({ chmodModal: null });
        this.update();
    }

    applyChmodPreset(preset) {
        if (!this.state.chmodModal) return;
        this.state.chmodModal.mode = preset;
        this.update();
    }

    updateChmodFromCheckboxes() {
        const oR = document.getElementById('chmod-or')?.checked ? 4 : 0;
        const oW = document.getElementById('chmod-ow')?.checked ? 2 : 0;
        const oX = document.getElementById('chmod-ox')?.checked ? 1 : 0;
        const gR = document.getElementById('chmod-gr')?.checked ? 4 : 0;
        const gW = document.getElementById('chmod-gw')?.checked ? 2 : 0;
        const gX = document.getElementById('chmod-gx')?.checked ? 1 : 0;
        const pR = document.getElementById('chmod-pr')?.checked ? 4 : 0;
        const pW = document.getElementById('chmod-pw')?.checked ? 2 : 0;
        const pX = document.getElementById('chmod-px')?.checked ? 1 : 0;

        const octal = `0${oR + oW + oX}${gR + gW + gX}${pR + pW + pX}`;
        const input = document.getElementById('chmod-octal-input');
        if (input) input.value = octal;
        if (this.state.chmodModal) {
            this.state.chmodModal.mode = octal;
        }
    }

    updateChmodFromInput(val) {
        const clean = (val || '').trim();
        if (/^[0-7]{3,4}$/.test(clean)) {
            if (this.state.chmodModal) {
                this.state.chmodModal.mode = clean;
                this.update();
            }
        }
    }

    async confirmChmod() {
        const m = this.state.chmodModal;
        if (!m || !m.paths || m.paths.length === 0) return;
        const input = document.getElementById('chmod-octal-input');
        const mode = input ? input.value.trim() : (m.mode || '0644');
        const recursive = document.getElementById('chmod-recursive-check')?.checked || false;

        this.setState({ chmodModal: null, loading: true });
        this.update();

        try {
            const res = await this.admin.api('storage_bulk_chmod', {
                paths: m.paths,
                mode: mode,
                recursive: recursive
            });
            if (res.success) {
                this.admin.notify(res.message || `Updated permissions to ${mode}.`, "success");
                await this.loadDirectory(this.state.currentPath);
            } else {
                this.admin.notify(res.error || "Failed to update permissions.", "error");
            }
        } catch (e) {
            this.admin.notify("Chmod error: " + e.message, "error");
        } finally {
            this.setState({ loading: false });
            this.update();
        }
    }

    previewImage(path) {
        const safePath = this.normalizePath(path);
        const filename = safePath.split('/').pop();
        const url = (typeof APP_BASE_URI !== 'undefined' ? APP_BASE_URI : '') +
            '/sppadmin/api.php?action=storage_download_file&path=' + encodeURIComponent(safePath);
        this.setState({
            imagePreviewModal: {
                path: safePath,
                name: filename,
                url: url
            }
        });
        this.update();
    }

    closeImagePreview() {
        this.setState({ imagePreviewModal: null });
        this.update();
    }

    renderBulkDeleteModal() {
        if (!this.state.bulkDeleteModal) return '';
        const items = Array.from(this.state.selectedItems);
        return `
            <div class="spp-shortcuts-modal-overlay" style="position:fixed; inset:0; z-index:999999; background:rgba(2, 6, 23, 0.75); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;" onclick="if(event.target === this) window.filesViewInstance.closeBulkDeleteModal();">
                <div class="spp-shortcuts-modal-window" style="width:100%; max-width:550px; background:#0d1117; border:1px solid #30363d; border-radius:10px; box-shadow:0 25px 60px rgba(0,0,0,0.85); overflow:hidden;" onclick="event.stopPropagation();">
                    <div class="spp-shortcuts-modal-header" style="padding:0.9rem 1.25rem; border-bottom:1px solid #21262d; display:flex; justify-content:space-between; align-items:center; background:#161b22;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.1rem; color:#f85149;">🗑️</span>
                            <h4 style="margin:0; font-size:0.95rem; color:#f0f6fc; font-weight:600;">Confirm Bulk Deletion</h4>
                        </div>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeBulkDeleteModal()" style="border:none; color:#8b949e; font-size:1rem; cursor:pointer;">✕</button>
                    </div>
                    <div style="padding:1.25rem; display:flex; flex-direction:column; gap:0.85rem;">
                        <div style="font-size:0.88rem; color:#f0f6fc; line-height:1.4;">
                            Are you sure you want to permanently delete these <strong>${items.length}</strong> selected item(s)?
                        </div>
                        <div style="max-height:180px; overflow-y:auto; background:rgba(0,0,0,0.3); border:1px solid #30363d; border-radius:6px; padding:0.6rem; display:flex; flex-direction:column; gap:0.35rem;">
                            ${items.map(p => `
                                <div style="font-size:0.78rem; font-family:monospace; color:#c9d1d9; display:flex; align-items:center; gap:0.35rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <span style="color:#f85149;">✕</span>
                                    <span>${this.escapeHtml(p)}</span>
                                </div>
                            `).join('')}
                        </div>
                        <div style="font-size:0.75rem; color:#f85149; background:rgba(248,81,73,0.1); border:1px solid rgba(248,81,73,0.3); border-radius:6px; padding:0.5rem 0.75rem;">
                            ⚠️ Warning: This operation is permanent and cannot be undone. System directories are protected.
                        </div>
                    </div>
                    <div style="padding:0.85rem 1.25rem; background:#161b22; border-top:1px solid #21262d; display:flex; justify-content:flex-end; gap:0.5rem;">
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeBulkDeleteModal()">Cancel</button>
                        <button class="btn danger-btn btn-sm" onclick="window.filesViewInstance.confirmBulkDelete()" style="background:#da3633; color:#ffffff; font-weight:600;">
                            Permanently Delete (${items.length})
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderMoveCopyModal() {
        const m = this.state.moveCopyModal;
        if (!m) return '';
        const isCopy = m.mode === 'copy';
        const items = m.paths || [];
        return `
            <div class="spp-shortcuts-modal-overlay" style="position:fixed; inset:0; z-index:999999; background:rgba(2, 6, 23, 0.75); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;" onclick="if(event.target === this) window.filesViewInstance.closeMoveCopyModal();">
                <div class="spp-shortcuts-modal-window" style="width:100%; max-width:600px; background:#0d1117; border:1px solid #30363d; border-radius:10px; box-shadow:0 25px 60px rgba(0,0,0,0.85); overflow:hidden;" onclick="event.stopPropagation();">
                    <div class="spp-shortcuts-modal-header" style="padding:0.9rem 1.25rem; border-bottom:1px solid #21262d; display:flex; justify-content:space-between; align-items:center; background:#161b22;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.1rem;">${isCopy ? '📋' : '↗️'}</span>
                            <h4 style="margin:0; font-size:0.95rem; color:#f0f6fc; font-weight:600;">${isCopy ? 'Copy' : 'Move'} ${items.length} Item(s)</h4>
                        </div>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeMoveCopyModal()" style="border:none; color:#8b949e; font-size:1rem; cursor:pointer;">✕</button>
                    </div>
                    <div style="padding:1.25rem; display:flex; flex-direction:column; gap:0.85rem;">
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.35rem;">Items to ${isCopy ? 'copy' : 'move'}:</label>
                            <div style="max-height:110px; overflow-y:auto; background:rgba(0,0,0,0.3); border:1px solid #30363d; border-radius:6px; padding:0.5rem; display:flex; flex-direction:column; gap:0.25rem;">
                                ${items.map(p => `<div style="font-size:0.75rem; font-family:monospace; color:#8b949e; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">• ${this.escapeHtml(p)}</div>`).join('')}
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.35rem;">Destination Folder Path <span style="color:#f85149;">*</span></label>
                            <input type="text" id="spp-move-copy-dest" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px; font-family:monospace;" value="${this.escapeHtml(m.destination || this.state.currentPath || '/')}" autofocus />
                            <div style="display:flex; gap:0.3rem; margin-top:0.4rem; flex-wrap:wrap;">
                                <button type="button" class="btn ghost-btn btn-xs" onclick="document.getElementById('spp-move-copy-dest').value = '/'">🖥️ Root (/)</button>
                                <button type="button" class="btn ghost-btn btn-xs" onclick="document.getElementById('spp-move-copy-dest').value = '/var/www'">🌐 /var/www</button>
                                <button type="button" class="btn ghost-btn btn-xs" onclick="document.getElementById('spp-move-copy-dest').value = '/home'">👤 /home</button>
                                ${this.state.workspaceRoot ? `<button type="button" class="btn ghost-btn btn-xs" onclick="document.getElementById('spp-move-copy-dest').value = '${this.escapeHtml(this.state.workspaceRoot)}'">🚀 SPP Workspace</button>` : ''}
                            </div>
                        </div>
                    </div>
                    <div style="padding:0.85rem 1.25rem; background:#161b22; border-top:1px solid #21262d; display:flex; justify-content:flex-end; gap:0.5rem;">
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeMoveCopyModal()">Cancel</button>
                        <button class="btn primary-btn btn-sm" onclick="window.filesViewInstance.confirmMoveCopy()" style="font-weight:600;">
                            ${isCopy ? 'Copy Here' : 'Move Here'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderChmodModal() {
        const m = this.state.chmodModal;
        if (!m) return '';
        const items = m.paths || [];
        const mode = String(m.mode || '0644').replace(/^0+/, '').padStart(3, '0');
        const oR = (parseInt(mode[0], 10) & 4) !== 0;
        const oW = (parseInt(mode[0], 10) & 2) !== 0;
        const oX = (parseInt(mode[0], 10) & 1) !== 0;
        const gR = (parseInt(mode[1], 10) & 4) !== 0;
        const gW = (parseInt(mode[1], 10) & 2) !== 0;
        const gX = (parseInt(mode[1], 10) & 1) !== 0;
        const pR = (parseInt(mode[2], 10) & 4) !== 0;
        const pW = (parseInt(mode[2], 10) & 2) !== 0;
        const pX = (parseInt(mode[2], 10) & 1) !== 0;

        return `
            <div class="spp-shortcuts-modal-overlay" style="position:fixed; inset:0; z-index:999999; background:rgba(2, 6, 23, 0.75); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;" onclick="if(event.target === this) window.filesViewInstance.closeChmodModal();">
                <div class="spp-shortcuts-modal-window" style="width:100%; max-width:550px; background:#0d1117; border:1px solid #30363d; border-radius:10px; box-shadow:0 25px 60px rgba(0,0,0,0.85); overflow:hidden;" onclick="event.stopPropagation();">
                    <div class="spp-shortcuts-modal-header" style="padding:0.9rem 1.25rem; border-bottom:1px solid #21262d; display:flex; justify-content:space-between; align-items:center; background:#161b22;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.1rem;">🔒</span>
                            <h4 style="margin:0; font-size:0.95rem; color:#f0f6fc; font-weight:600;">File Permissions (chmod)</h4>
                        </div>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeChmodModal()" style="border:none; color:#8b949e; font-size:1rem; cursor:pointer;">✕</button>
                    </div>
                    <div style="padding:1.25rem; display:flex; flex-direction:column; gap:0.85rem;">
                        <div style="font-size:0.8rem; color:#8b949e;">Applying permissions to <strong>${items.length}</strong> item(s):</div>
                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:0.75rem; background:#161b22; border:1px solid #30363d; border-radius:8px; padding:0.85rem;">
                            <div>
                                <strong style="display:block; font-size:0.8rem; color:#f0f6fc; margin-bottom:0.4rem;">Owner</strong>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-or" ${oR ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Read (4)</label>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-ow" ${oW ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Write (2)</label>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-ox" ${oX ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Execute (1)</label>
                            </div>
                            <div>
                                <strong style="display:block; font-size:0.8rem; color:#f0f6fc; margin-bottom:0.4rem;">Group</strong>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-gr" ${gR ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Read (4)</label>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-gw" ${gW ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Write (2)</label>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-gx" ${gX ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Execute (1)</label>
                            </div>
                            <div>
                                <strong style="display:block; font-size:0.8rem; color:#f0f6fc; margin-bottom:0.4rem;">Public / Others</strong>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-pr" ${pR ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Read (4)</label>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-pw" ${pW ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Write (2)</label>
                                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; color:#c9d1d9; cursor:pointer;"><input type="checkbox" id="chmod-px" ${pX ? 'checked' : ''} onchange="window.filesViewInstance.updateChmodFromCheckboxes()" /> Execute (1)</label>
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem;">
                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                <label style="font-size:0.8rem; font-weight:600; color:#c9d1d9;">Octal Notation:</label>
                                <input type="text" id="chmod-octal-input" class="spp-find-input" style="width:70px; text-align:center; font-family:monospace; font-weight:700; font-size:0.9rem;" value="0${mode}" oninput="window.filesViewInstance.updateChmodFromInput(this.value)" />
                            </div>
                            <div style="display:flex; gap:0.25rem;">
                                <button type="button" class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.applyChmodPreset('0644')">0644 (File)</button>
                                <button type="button" class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.applyChmodPreset('0755')">0755 (Folder)</button>
                                <button type="button" class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.applyChmodPreset('0400')">0400 (Secret)</button>
                            </div>
                        </div>

                        <div>
                            <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.8rem; color:#c9d1d9; cursor:pointer;">
                                <input type="checkbox" id="chmod-recursive-check" />
                                <span>Apply recursively to all subdirectories and enclosed files</span>
                            </label>
                        </div>
                    </div>
                    <div style="padding:0.85rem 1.25rem; background:#161b22; border-top:1px solid #21262d; display:flex; justify-content:flex-end; gap:0.5rem;">
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeChmodModal()">Cancel</button>
                        <button class="btn primary-btn btn-sm" onclick="window.filesViewInstance.confirmChmod()" style="font-weight:600;">
                            Apply Permissions
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderImagePreviewModal() {
        const m = this.state.imagePreviewModal;
        if (!m) return '';
        return `
            <div class="spp-shortcuts-modal-overlay" style="position:fixed; inset:0; z-index:999999; background:rgba(2, 6, 23, 0.85); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; padding:1.5rem;" onclick="if(event.target === this) window.filesViewInstance.closeImagePreview();">
                <div style="max-width:90vw; max-height:90vh; background:#0d1117; border:1px solid #30363d; border-radius:12px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 60px rgba(0,0,0,0.9);" onclick="event.stopPropagation();">
                    <div style="padding:0.75rem 1.25rem; display:flex; align-items:center; justify-content:space-between; background:#161b22; border-bottom:1px solid #21262d;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span>🖼️</span>
                            <strong style="color:#f0f6fc; font-size:0.9rem;">${this.escapeHtml(m.name)}</strong>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.4rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.downloadFile('${this.escapeHtml(m.path)}')" title="Download Image">⬇️ Download</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeImagePreview()" style="font-size:1rem; border:none; color:#8b949e; cursor:pointer;">✕</button>
                        </div>
                    </div>
                    <div style="padding:1.5rem; display:flex; align-items:center; justify-content:center; overflow:auto; background:radial-gradient(#1e293b 10%, #0d1117 90%);">
                        <img src="${m.url}" alt="${this.escapeHtml(m.name)}" style="max-width:82vw; max-height:75vh; object-fit:contain; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.5);" />
                    </div>
                </div>
            </div>
        `;
    }

    async compressCurrentDirectory() {
        const path = this.state.currentPath || '';
        const name = path ? path.split('/').pop() : 'workspace';
        const defaultOut = (path ? path + '/' : '') + name + '.zip';
        const target = prompt(`Enter output ZIP path:`, defaultOut);
        if (!target) return;

        try {
            this.admin.notify(`Creating ZIP archive of '${path || 'root'}'...`, "info");
            const res = await this.admin.api('archive_create', {
                source_path: path,
                format: 'zip',
                output_path: target
            });
            if (res.success) {
                this.admin.notify(`Archive created successfully: ${res.files_count} files (${res.duration_ms}ms, ${res.compression_ratio_pct}% compressed).`, "success");
                await Promise.all([
                    this.loadDirectory(this.state.currentPath),
                    this.loadTree(true)
                ]);
            } else {
                this.admin.notify(res.error || "Failed to create archive.", "error");
            }
        } catch (e) {
            this.admin.notify("Archive error: " + e.message, "error");
        }
    }

    navigateUp() {
        const cur = this.normalizePath(this.state.currentPath || '');
        const sysRoot = this.state.systemRoot || '/';
        if (!cur || cur === '/' || cur === sysRoot) return;

        const lastSlash = cur.lastIndexOf('/');
        if (lastSlash <= 0) {
            // Parent is system root ('/')
            this.loadDirectory(sysRoot);
        } else {
            let parent = cur.substring(0, lastSlash);
            if (/^[a-zA-Z]:$/.test(parent)) parent += '/';
            this.loadDirectory(parent || sysRoot);
        }
    }

    getLanguage(ext = '', filename = '') {
        const lowerName = (filename || '').toLowerCase();
        if (lowerName === 'dockerfile' || lowerName.startsWith('dockerfile.')) return 'docker';
        if (lowerName === 'makefile' || lowerName === 'cmakelists.txt') return 'bash';
        if (lowerName === 'gemfile' || lowerName === 'rakefile') return 'ruby';
        if (lowerName.endsWith('.blade.php')) return 'php';

        ext = (ext || '').toLowerCase().replace(/^\./, '');
        if (!ext && filename) {
            const parts = filename.split('.');
            if (parts.length > 1) ext = parts.pop().toLowerCase();
        }

        const map = {
            // PHP & Templates
            'php': 'php', 'phtml': 'php', 'twig': 'markup',
            // JavaScript & Web
            'js': 'javascript', 'jsx': 'javascript', 'mjs': 'javascript', 'cjs': 'javascript',
            'ts': 'javascript', 'tsx': 'javascript',
            'html': 'markup', 'htm': 'markup', 'xml': 'markup', 'svg': 'markup', 'xhtml': 'markup',
            'css': 'css', 'scss': 'css', 'sass': 'css', 'less': 'css',
            'json': 'json', 'jsonc': 'json', 'json5': 'json',
            // Polyglot backend runtimes
            'py': 'python', 'pyw': 'python', 'wsgi': 'python',
            'rs': 'rust',
            'go': 'go',
            'java': 'java', 'jav': 'java',
            'c': 'c', 'h': 'c',
            'cpp': 'cpp', 'hpp': 'cpp', 'cc': 'cpp', 'cxx': 'cpp', 'hh': 'cpp',
            'cs': 'csharp', 'csx': 'csharp',
            'rb': 'ruby', 'erb': 'ruby',
            'kt': 'kotlin', 'kts': 'kotlin',
            'swift': 'swift',
            'dart': 'dart',
            'pl': 'perl', 'pm': 'perl', 't': 'perl',
            'lua': 'lua',
            'r': 'r',
            // Shell & Scripting
            'sh': 'bash', 'bash': 'bash', 'zsh': 'bash',
            'ps1': 'powershell', 'psm1': 'powershell', 'psd1': 'powershell',
            'bat': 'powershell', 'cmd': 'powershell',
            // Config, Query & Data
            'sql': 'sql',
            'yml': 'yaml', 'yaml': 'yaml',
            'toml': 'toml',
            'graphql': 'graphql', 'gql': 'graphql',
            'wat': 'wat', 'wasm': 'wat',
            'dockerfile': 'docker', 'dockerignore': 'docker',
            'md': 'markdown', 'markdown': 'markdown',
            'ini': 'ini', 'conf': 'ini', 'env': 'ini', 'properties': 'ini',
            'htaccess': 'ini', 'htpasswd': 'ini', 'sppignore': 'ini', 'gitignore': 'ini'
        };
        return map[ext] || 'plain';
    }

    getFileIcon(ext = '', isDir = false, filename = '') {
        if (isDir) return '📁';
        const lowerName = (filename || '').toLowerCase();
        
        if (lowerName === 'composer.json' || lowerName === 'composer.lock') return '🎼';
        if (lowerName === 'package.json' || lowerName === 'package-lock.json') return '📦';
        if (lowerName.startsWith('dockerfile') || lowerName.includes('docker-compose')) return '🐳';
        if (lowerName === 'makefile' || lowerName === 'cmakelists.txt') return '🛠️';
        if (lowerName === 'gemfile' || lowerName === 'rakefile') return '💎';
        if (lowerName.startsWith('.git')) return '🐙';
        if (lowerName.startsWith('.env')) return '🔐';
        if (lowerName === '.sppignore' || lowerName === '.gitignore') return '🛡️';

        ext = (ext || '').toLowerCase().replace(/^\./, '');
        if (!ext && filename) {
            const parts = filename.split('.');
            if (parts.length > 1) ext = parts.pop().toLowerCase();
        }

        switch (ext) {
            case 'php': return '🐘';
            case 'py':
            case 'pyw': return '🐍';
            case 'rs': return '🦀';
            case 'go': return '🐹';
            case 'java': return '☕';
            case 'c':
            case 'h': return '🔷';
            case 'cpp':
            case 'hpp':
            case 'cc':
            case 'cxx': return '⚙️';
            case 'cs': return '🟣';
            case 'rb':
            case 'erb': return '💎';
            case 'kt':
            case 'kts': return '🟣';
            case 'swift': return '🦅';
            case 'dart': return '🎯';
            case 'pl':
            case 'pm': return '🐪';
            case 'lua': return '🌙';
            case 'r': return '📊';
            case 'js':
            case 'jsx':
            case 'mjs':
            case 'cjs': return '⚡';
            case 'ts':
            case 'tsx': return '🔷';
            case 'json':
            case 'jsonc':
            case 'json5': return '📦';
            case 'html':
            case 'htm':
            case 'xhtml': return '🌐';
            case 'css':
            case 'scss':
            case 'sass':
            case 'less': return '🎨';
            case 'sql': return '🗄️';
            case 'sh':
            case 'bash':
            case 'zsh': return '🐚';
            case 'ps1':
            case 'psm1':
            case 'bat':
            case 'cmd': return '💻';
            case 'yml':
            case 'yaml': return '⚙️';
            case 'toml': return '📑';
            case 'md':
            case 'markdown': return '📝';
            case 'graphql':
            case 'gql': return '🕸️';
            case 'wat':
            case 'wasm': return '🕸️';
            case 'zip':
            case 'tar':
            case 'gz':
            case 'rar':
            case '7z': return '📦';
            case 'png':
            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'svg':
            case 'webp':
            case 'ico': return '🖼️';
            case 'pdf': return '📕';
            case 'txt':
            case 'log': return '📄';
            default: return '📄';
        }
    }


    setViewMode(mode) {
        this.state.viewMode = mode === 'grid' ? 'grid' : 'list';
        localStorage.setItem('spp_files_view_mode', this.state.viewMode);
        this.update();
    }

    toggleInspector(force = null) {
        const next = typeof force === 'boolean' ? force : !this.state.inspectorOpen;
        this.state.inspectorOpen = next;
        localStorage.setItem('spp_files_inspector_open', String(next));
        this.update();
    }

    selectItemForInspector(item) {
        this.state.selectedItemForInspector = item;
        this.state.inspectorOpen = true;
        this.state.inspectorTab = 'overview';
        localStorage.setItem('spp_files_inspector_open', 'true');
        this.update();
    }

    selectItemByPath(path, forceOpen = false) {
        const norm = this.normalizePath(path);
        const item = (this.state.items || []).find(it => this.normalizePath(it.path) === norm);
        if (item) {
            this.state.selectedItemForInspector = item;
            if (forceOpen || this.state.inspectorOpen) {
                this.state.inspectorOpen = true;
                localStorage.setItem('spp_files_inspector_open', 'true');
            }
            this.update();
        }
    }

    inspectCurrentDirectory() {
        this.state.selectedItemForInspector = null;
        this.state.inspectorOpen = true;
        localStorage.setItem('spp_files_inspector_open', 'true');
        this.update();
    }

    switchInspectorTab(tab) {
        this.state.inspectorTab = tab;
        this.update();
    }

    setSortColumn(col) {
        if (this.state.sortColumn === col) {
            this.state.sortDirection = this.state.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.state.sortColumn = col;
            this.state.sortDirection = 'asc';
        }
        this.update();
    }

    getSortIndicator(col) {
        if (this.state.sortColumn !== col) return '<span style="opacity:0.3; font-size:0.65rem;">⇅</span>';
        return this.state.sortDirection === 'desc' ? '<span style="color:var(--primary); font-size:0.65rem;">▼</span>' : '<span style="color:var(--primary); font-size:0.65rem;">▲</span>';
    }

    toggleDropdown(name, event) {
        if (event) event.stopPropagation();
        this.state.openDropdown = (this.state.openDropdown === name) ? null : name;
        this.update();
    }

    closeDropdowns() {
        if (this.state.openDropdown) {
            this.state.openDropdown = null;
            this.update();
        }
    }

    copySelectedItems() {
        if (!this.state.selectedItems || this.state.selectedItems.size === 0) return;
        this.state.clipboard = {
            action: 'copy',
            items: Array.from(this.state.selectedItems)
        };
        this.admin.notify(`Copied ${this.state.clipboard.items.length} item(s) to clipboard.`, 'info');
        this.update();
    }

    cutSelectedItems() {
        if (!this.state.selectedItems || this.state.selectedItems.size === 0) return;
        this.state.clipboard = {
            action: 'cut',
            items: Array.from(this.state.selectedItems)
        };
        this.admin.notify(`Cut ${this.state.clipboard.items.length} item(s) to clipboard.`, 'info');
        this.update();
    }

    async pasteClipboardItems() {
        if (!this.state.clipboard || !this.state.clipboard.items || this.state.clipboard.items.length === 0) return;
        const targetDir = this.state.currentPath || '';
        const action = this.state.clipboard.action;
        let count = 0;
        for (const itemPath of this.state.clipboard.items) {
            const fileName = itemPath.split('/').pop();
            const destPath = this.joinPath(targetDir, fileName);
            try {
                if (action === 'cut') {
                    await this.admin.api('storage_move_item', { source: itemPath, destination: destPath });
                } else {
                    await this.admin.api('storage_copy_item', { source: itemPath, destination: destPath });
                }
                count++;
            } catch (e) {
                console.error("Paste error for item:", itemPath, e);
            }
        }
        if (action === 'cut') {
            this.state.clipboard = null;
        }
        this.admin.notify(`Successfully pasted ${count} item(s).`, 'success');
        await this.loadDirectory(this.state.currentPath);
    }

    promptRenameSingleSelected() {
        if (!this.state.selectedItems || this.state.selectedItems.size !== 1) return;
        const selectedPath = Array.from(this.state.selectedItems)[0];
        const item = (this.state.items || []).find(it => this.normalizePath(it.path) === selectedPath);
        if (item) {
            this.promptRename(item.path, item.is_dir);
        }
    }

    onTreeResizeStart(e) {
        e.preventDefault();
        const startX = e.clientX;
        const startWidth = this.state.treeWidth || 250;
        const onMouseMove = (moveEvent) => {
            const newWidth = Math.max(180, Math.min(500, startWidth + (moveEvent.clientX - startX)));
            this.state.treeWidth = newWidth;
            const treeEl = document.querySelector('.spp-file-tree-sidebar');
            if (treeEl) {
                treeEl.style.width = `${newWidth}px`;
                treeEl.style.flex = `0 0 ${newWidth}px`;
            }
        };
        const onMouseUp = () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            localStorage.setItem('spp_files_tree_width', String(this.state.treeWidth));
        };
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    }

    collapseAllTree() {
        const sysRoot = this.normalizePath(this.state.systemRoot || '/');
        this.state.expandedFolders = new Set(['', '/', sysRoot]);
        this.update();
    }

    expandToCurrent() {
        if (this.state.currentPath) {
            this.ensurePathInTree(this.state.currentPath);
        }
    }

    toggleExpertMode() {
        this.state.expertMode = !this.state.expertMode;
        localStorage.setItem('spp_admin_expert_mode', String(this.state.expertMode));
        if (this.admin && typeof this.admin.setUIMode === 'function') {
            this.admin.setUIMode(this.state.expertMode ? 'expert' : 'normal');
        }
        this.admin.notify(`Switched to ${this.state.expertMode ? 'Expert' : 'Normal'} mode.`, 'info');
        this.update();
    }

    async saveInspectorProperties() {
        const item = this.state.selectedItemForInspector;
        const isFile = Boolean(item && !item.is_dir);
        const targetPath = item ? item.path : (this.state.currentPath || '');

        const g = (id) => document.getElementById(id);
        const desc = g('inspector-prop-desc') ? g('inspector-prop-desc').value.trim() : '';
        const usage = g('inspector-prop-usage') ? g('inspector-prop-usage').value.trim() : '';
        const author = g('inspector-prop-author') ? g('inspector-prop-author').value.trim() : '';
        const reviewDate = g('inspector-prop-review-date') ? g('inspector-prop-review-date').value.trim() : '';
        const stability = g('inspector-prop-stability') ? g('inspector-prop-stability').value : 'stable';
        const layer = g('inspector-prop-layer') ? g('inspector-prop-layer').value : 'unspecified';
        const tagsRaw = g('inspector-prop-tags') ? g('inspector-prop-tags').value : '';
        const tags = tagsRaw.split(',').map(t => t.trim()).filter(Boolean);

        const payload = {
            description: desc,
            usage: usage,
            author: author,
            review_date: reviewDate,
            stability: stability,
            layer: layer,
            tags: tags
        };

        try {
            let res;
            if (isFile) {
                res = await this.admin.api('storage_save_file_props', { path: targetPath, properties: payload });
            } else {
                res = await this.admin.api('storage_save_directory_props', { path: targetPath, properties: payload });
            }
            if (res && res.success) {
                this.admin.notify("Properties successfully saved to __spp_file_props.yml", "success");
                await this.loadDirectory(this.state.currentPath);
            } else {
                this.admin.notify(res.error || "Failed to save properties.", "error");
            }
        } catch (e) {
            this.admin.notify("Save error: " + e.message, "error");
        }
    }

    async saveInspectorManifestYaml() {
        const dirPath = this.state.currentPath || '';
        const textarea = document.getElementById('inspector-manifest-raw-yaml');
        if (!textarea) return;
        const rawYaml = textarea.value;

        try {
            const res = await this.admin.api('storage_save_manifest_raw', { path: dirPath, raw_yaml: rawYaml });
            if (res && res.success) {
                this.admin.notify("Manifest YAML saved successfully.", "success");
                await this.loadDirectory(this.state.currentPath);
            } else {
                this.admin.notify(res.error || "Validation/Save failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Save error: " + e.message, "error");
        }
    }

    async validateInspectorYaml() {
        const textarea = document.getElementById('inspector-manifest-raw-yaml');
        if (!textarea) return;
        const rawYaml = textarea.value;
        try {
            const res = await this.admin.api('storage_validate_manifest_yaml', { raw_yaml: rawYaml });
            const banner = document.getElementById('inspector-manifest-validation-msg');
            if (banner) {
                banner.style.display = 'block';
                if (res && res.success) {
                    banner.style.color = '#3fb950';
                    banner.style.background = 'rgba(63, 185, 80, 0.15)';
                    banner.style.border = '1px solid rgba(63, 185, 80, 0.4)';
                    banner.textContent = '✓ YAML syntax and manifest schema are 100% valid.';
                } else {
                    banner.style.color = '#f85149';
                    banner.style.background = 'rgba(248, 81, 73, 0.15)';
                    banner.style.border = '1px solid rgba(248, 81, 73, 0.4)';
                    banner.textContent = '✕ Syntax error: ' + (res.error || res.message || 'Invalid YAML format.');
                }
            }
        } catch (e) {
            this.admin.notify("Validation error: " + e.message, "error");
        }
    }

    renderHeader() {
        const currentPath = this.state.currentPath || '/';
        let scopeBadge = '🖥️ Platform Scope';
        if (this.state.siteRoots && this.state.siteRoots.length > 0) {
            const matchedSite = this.state.siteRoots.find(s => currentPath === s.home_path || currentPath.startsWith(s.home_path + '/'));
            if (matchedSite) {
                scopeBadge = `🌐 ${matchedSite.domain}`;
            }
        }

        const viewMode = this.state.viewMode || 'list';
        const isInspectorOpen = Boolean(this.state.inspectorOpen);
        const isExpert = Boolean(this.state.expertMode);

        return `
            <div class="spp-fm-header">
                <div class="spp-fm-header-left">
                    <span class="spp-fm-title">📁 File Manager</span>
                    <span class="spp-fm-scope-badge">${this.escapeHtml(scopeBadge)}</span>
                    <span class="spp-fm-path-chip" title="${this.escapeHtml(currentPath)}">
                        ${this.escapeHtml(currentPath)}
                    </span>
                    <span style="font-size:0.75rem; color:#22c55e; display:inline-flex; align-items:center; gap:0.25rem;">
                        ● Online
                    </span>
                </div>

                <div class="spp-fm-header-right">
                    <!-- List vs Grid View Toggle -->
                    <div class="spp-fm-view-switcher">
                        <button type="button" class="spp-fm-view-btn ${viewMode === 'list' ? 'active' : ''}" 
                                onclick="window.filesViewInstance.setViewMode('list')" title="List View (Table)">
                            ☰ List
                        </button>
                        <button type="button" class="spp-fm-view-btn ${viewMode === 'grid' ? 'active' : ''}" 
                                onclick="window.filesViewInstance.setViewMode('grid')" title="Grid View (Cards)">
                            ⊞ Grid
                        </button>
                    </div>

                    <!-- Inspector Drawer Toggle Button -->
                    <button type="button" class="btn ${isInspectorOpen ? 'primary-btn' : 'secondary-btn'} btn-sm" 
                            onclick="window.filesViewInstance.toggleInspector()" 
                            title="${isInspectorOpen ? 'Hide metadata inspector drawer' : 'Show metadata inspector drawer'}"
                            style="padding:0.25rem 0.6rem; font-size:0.8rem; font-weight:600;">
                        👁️ Inspector
                    </button>

                    <!-- Settings / Overflow Dropdown -->
                    <div class="spp-dropdown-container">
                        <button type="button" class="btn ghost-btn btn-sm" 
                                onclick="window.filesViewInstance.toggleDropdown('settings', event)" 
                                title="Workstation Settings & Tools" 
                                style="padding:0.25rem 0.5rem; font-size:0.85rem;">
                            ⚙️ ▾
                        </button>
                        ${this.state.openDropdown === 'settings' ? `
                            <div class="spp-dropdown-menu right">
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.toggleExpertMode()">
                                    <span>${isExpert ? '⚡' : '🌐'}</span>
                                    <span>Mode: <strong>${isExpert ? 'Expert' : 'Normal'}</strong></span>
                                </button>
                                <div class="spp-dropdown-divider"></div>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.openTreemapModal(); window.filesViewInstance.closeDropdowns();">
                                    <span>📊</span> <span>Storage Treemap</span>
                                </button>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.openWebToolsModal(); window.filesViewInstance.closeDropdowns();">
                                    <span>🌐</span> <span>Web Tools & MIME</span>
                                </button>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.openManifestHealthModal(null, false); window.filesViewInstance.closeDropdowns();">
                                    <span>🩺</span> <span>Manifest Health</span>
                                </button>
                                <div class="spp-dropdown-divider"></div>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.toggleShortcutsModal(true); window.filesViewInstance.closeDropdowns();">
                                    <span>⌨️</span> <span>Keyboard Shortcuts</span>
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    renderNavBar() {
        const curPath = this.state.currentPath || '/';
        const sysRoot = this.state.systemRoot || '/';
        const isRoot = (!curPath || curPath === '/' || curPath === sysRoot);

        return `
            <div class="spp-fm-nav-bar">
                <div class="spp-fm-nav-left">
                    <!-- Nav History Buttons -->
                    <div style="display:inline-flex; align-items:center; gap:0.2rem; flex-shrink:0;">
                        <button class="btn secondary-btn btn-sm ${this.canGoBack() ? '' : 'disabled'}" 
                                onclick="${this.canGoBack() ? 'window.filesViewInstance.navigateBack()' : ''}" 
                                title="Back in history (Alt + Left Arrow)" 
                                ${!this.canGoBack() ? 'disabled style="opacity:0.35; cursor:not-allowed; padding:0.22rem 0.45rem;"' : 'style="cursor:pointer; padding:0.22rem 0.45rem;"'}>
                            ◀
                        </button>
                        <button class="btn secondary-btn btn-sm ${this.canGoForward() ? '' : 'disabled'}" 
                                onclick="${this.canGoForward() ? 'window.filesViewInstance.navigateForward()' : ''}" 
                                title="Forward in history (Alt + Right Arrow)" 
                                ${!this.canGoForward() ? 'disabled style="opacity:0.35; cursor:not-allowed; padding:0.22rem 0.45rem;"' : 'style="cursor:pointer; padding:0.22rem 0.45rem;"'}>
                            ▶
                        </button>
                        <button class="btn secondary-btn btn-sm ${!isRoot ? '' : 'disabled'}" 
                                onclick="window.filesViewInstance.navigateUp()" 
                                title="Go up to parent directory (Alt + Up Arrow or Backspace)" 
                                ${isRoot ? 'disabled style="opacity:0.35; cursor:not-allowed; padding:0.22rem 0.45rem;"' : 'style="cursor:pointer; padding:0.22rem 0.45rem;"'}>
                            ▲
                        </button>
                        <button class="btn ghost-btn btn-sm" 
                                onclick="window.filesViewInstance.loadDirectory('${this.escapeHtml(this.state.currentPath)}')" 
                                title="Refresh current folder (F5)" 
                                style="padding:0.22rem 0.45rem;">
                            🔄
                        </button>
                    </div>

                    <!-- Breadcrumbs or Editable Address Field -->
                    ${this.state.isEditingAddress ? `
                        <div style="display:inline-flex; align-items:center; gap:0.3rem; flex:1 1 280px; min-width:0;">
                            <input type="text" id="spp-files-address-input" class="spp-address-bar-input" 
                                   value="${this.escapeHtml(curPath)}" 
                                   onkeydown="if(event.key === 'Enter') { window.filesViewInstance.submitAddressInput(); } else if(event.key === 'Escape') { window.filesViewInstance.toggleAddressEdit(false); }" 
                                   placeholder="/var/www or /home/username..." 
                                   style="width:100%; font-size:0.8rem; padding:0.22rem 0.5rem; font-family:monospace;" />
                            <button class="btn primary-btn btn-sm" onclick="window.filesViewInstance.submitAddressInput()" style="padding:0.22rem 0.5rem;">Go</button>
                            <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.toggleAddressEdit(false)" style="padding:0.22rem 0.4rem;">✕</button>
                        </div>
                    ` : `
                        <div style="display:inline-flex; align-items:center; gap:0.25rem; flex:1 1 auto; min-width:0; overflow:hidden;">
                            <div class="suite-breadcrumbs" style="min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:flex; align-items:center; flex-wrap:wrap; gap:0.2rem;">
                                <span class="suite-breadcrumb-item" onclick="window.filesViewInstance.loadDirectory('${sysRoot}')" title="Navigate to System Root">
                                    🖥️ Root (/)
                                </span>
                                ${this.renderBreadcrumbs()}
                            </div>
                            <button class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.toggleAddressEdit(true)" title="Type server path directly (Ctrl+L)" style="padding:0.12rem 0.4rem; font-size:0.75rem; opacity:0.75; flex-shrink:0;">
                                ✏️
                            </button>
                        </div>
                    `}

                    <!-- Bookmarks & Favorites Dropdown -->
                    <div class="spp-dropdown-container">
                        <button type="button" class="btn secondary-btn btn-sm" 
                                onclick="window.filesViewInstance.toggleDropdown('favorites', event)" 
                                title="Quick bookmarks & site homes" 
                                style="padding:0.22rem 0.55rem; font-size:0.78rem; font-weight:600; white-space:nowrap;">
                            ⭐ Favorites ▾
                        </button>
                        ${this.state.openDropdown === 'favorites' ? `
                            <div class="spp-dropdown-menu">
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.loadDirectory('${sysRoot}'); window.filesViewInstance.closeDropdowns();">
                                    <span>🖥️</span> <span>System Root (/)</span>
                                </button>
                                ${this.state.workspaceRoot ? `
                                    <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.loadDirectory('${this.escapeHtml(this.state.workspaceRoot)}'); window.filesViewInstance.closeDropdowns();">
                                        <span>🚀</span> <span>SPP Workspace</span>
                                    </button>
                                ` : ''}
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.loadDirectory('/var/www'); window.filesViewInstance.closeDropdowns();">
                                    <span>🌐</span> <span>Web Roots (/var/www)</span>
                                </button>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.loadDirectory('/home'); window.filesViewInstance.closeDropdowns();">
                                    <span>👤</span> <span>User Homes (/home)</span>
                                </button>
                                ${(this.state.siteRoots && this.state.siteRoots.length > 0) ? `
                                    <div class="spp-dropdown-divider"></div>
                                    <div style="padding:0.25rem 0.85rem; font-size:0.7rem; font-weight:700; color:var(--text-dim); text-transform:uppercase;">Configured Websites</div>
                                    ${this.state.siteRoots.map(s => `
                                        <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.loadDirectory('${this.escapeHtml(s.home_path)}'); window.filesViewInstance.closeDropdowns();">
                                            <span>${s.icon || '🌐'}</span> <span>${this.escapeHtml(s.domain)}</span>
                                        </button>
                                    `).join('')}
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Instant Search Input -->
                <div class="spp-fm-nav-right">
                    <div style="position:relative; display:inline-flex; align-items:center;">
                        <input type="text" id="spp-files-filter-input" class="spp-find-input" placeholder="🔍 Search files..." 
                            value="${this.escapeHtml(this.state.searchQuery || '')}" 
                            oninput="window.filesViewInstance.onSearchInput(this.value)" 
                            onkeydown="if(event.key === 'Escape') { window.filesViewInstance.clearSearch(); }"
                            style="width:145px; font-size:0.8rem; padding:0.22rem 1.6rem 0.22rem 0.55rem; border-radius:6px;" />
                        <span id="spp-files-filter-clear" onclick="window.filesViewInstance.clearSearch()" 
                              title="Clear filter (Esc)"
                              style="position:absolute; right:6px; cursor:pointer; font-size:0.75rem; color:var(--text-dim); padding:2px; display:${this.state.searchQuery ? 'inline-block' : 'none'};">✕</span>
                    </div>
                </div>
            </div>
        `;
    }

    renderToolbar() {
        const hasSelection = this.state.selectedItems && this.state.selectedItems.size > 0;
        const selectedCount = hasSelection ? this.state.selectedItems.size : 0;
        const hasClipboard = Boolean(this.state.clipboard && this.state.clipboard.items && this.state.clipboard.items.length > 0);

        return `
            <div class="spp-fm-toolbar">
                <div class="spp-fm-toolbar-left">
                    <!-- New Dropdown -->
                    <div class="spp-dropdown-container">
                        <button type="button" class="btn primary-btn btn-sm" 
                                onclick="window.filesViewInstance.toggleDropdown('new', event)" 
                                style="padding:0.25rem 0.65rem; font-size:0.8rem; font-weight:600;">
                            + New ▾
                        </button>
                        ${this.state.openDropdown === 'new' ? `
                            <div class="spp-dropdown-menu">
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.promptNewItem('file'); window.filesViewInstance.closeDropdowns();">
                                    <span>📄</span> <span>New File...</span>
                                </button>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.promptNewItem('folder'); window.filesViewInstance.closeDropdowns();">
                                    <span>📁</span> <span>New Folder...</span>
                                </button>
                            </div>
                        ` : ''}
                    </div>

                    <!-- Upload Button -->
                    <button type="button" class="btn secondary-btn btn-sm" 
                            onclick="document.getElementById('spp-file-upload-input').click()" 
                            title="Upload files from desktop to current directory"
                            style="padding:0.25rem 0.65rem; font-size:0.8rem;">
                        ⬆ Upload
                    </button>

                    <!-- Download Button (Contextual) -->
                    <button type="button" class="btn secondary-btn btn-sm ${hasSelection ? '' : 'disabled'}" 
                            onclick="${hasSelection ? 'window.filesViewInstance.downloadSelectedAsZip()' : ''}" 
                            title="${hasSelection ? `Download ${selectedCount} selected item(s) as ZIP` : 'Select items to download'}"
                            ${!hasSelection ? 'disabled style="opacity:0.4; cursor:not-allowed; padding:0.25rem 0.6rem; font-size:0.8rem;"' : 'style="padding:0.25rem 0.6rem; font-size:0.8rem;"'}>
                        ⬇ Download
                    </button>

                    <div style="width:1px; height:18px; background:var(--glass-border); margin:0 0.2rem;"></div>

                    <!-- Clipboard Actions: Copy, Cut, Paste -->
                    <button type="button" class="btn secondary-btn btn-sm ${hasSelection ? '' : 'disabled'}" 
                            onclick="${hasSelection ? 'window.filesViewInstance.copySelectedItems()' : ''}" 
                            title="Copy selected items to clipboard"
                            ${!hasSelection ? 'disabled style="opacity:0.4; cursor:not-allowed; padding:0.25rem 0.55rem; font-size:0.8rem;"' : 'style="padding:0.25rem 0.55rem; font-size:0.8rem;"'}>
                        📋 Copy
                    </button>
                    <button type="button" class="btn secondary-btn btn-sm ${hasSelection ? '' : 'disabled'}" 
                            onclick="${hasSelection ? 'window.filesViewInstance.cutSelectedItems()' : ''}" 
                            title="Cut selected items to clipboard"
                            ${!hasSelection ? 'disabled style="opacity:0.4; cursor:not-allowed; padding:0.25rem 0.55rem; font-size:0.8rem;"' : 'style="padding:0.25rem 0.55rem; font-size:0.8rem;"'}>
                        ✂ Cut
                    </button>
                    <button type="button" class="btn secondary-btn btn-sm ${hasClipboard ? '' : 'disabled'}" 
                            onclick="${hasClipboard ? 'window.filesViewInstance.pasteClipboardItems()' : ''}" 
                            title="${hasClipboard ? `Paste ${this.state.clipboard.items.length} item(s) into current directory` : 'Clipboard is empty'}"
                            ${!hasClipboard ? 'disabled style="opacity:0.4; cursor:not-allowed; padding:0.25rem 0.55rem; font-size:0.8rem;"' : 'style="padding:0.25rem 0.55rem; font-size:0.8rem;"'}>
                        📋 Paste ${hasClipboard ? `(${this.state.clipboard.items.length})` : ''}
                    </button>

                    <!-- Rename Button (enabled when 1 item selected) -->
                    ${selectedCount === 1 ? `
                        <button type="button" class="btn secondary-btn btn-sm" 
                                onclick="window.filesViewInstance.promptRenameSingleSelected()" 
                                title="Rename selected item"
                                style="padding:0.25rem 0.55rem; font-size:0.8rem;">
                            ✏️ Rename
                        </button>
                    ` : ''}

                    <!-- Delete Button -->
                    ${hasSelection ? `
                        <button type="button" class="btn danger-btn btn-sm" 
                                onclick="window.filesViewInstance.promptBulkDelete()" 
                                title="Delete ${selectedCount} selected item(s)"
                                style="background:#dc2626; color:#ffffff; font-weight:600; padding:0.25rem 0.65rem; font-size:0.8rem;">
                            🗑️ Delete (${selectedCount})
                        </button>
                    ` : `
                        <button type="button" class="btn secondary-btn btn-sm" 
                                onclick="window.filesViewInstance.promptDeleteCurrent()" 
                                title="Delete files or folders"
                                style="padding:0.25rem 0.55rem; font-size:0.8rem;">
                            🗑️ Delete
                        </button>
                    `}
                </div>

                <div class="spp-fm-toolbar-right">
                    <!-- More Actions Dropdown -->
                    <div class="spp-dropdown-container">
                        <button type="button" class="btn secondary-btn btn-sm" 
                                onclick="window.filesViewInstance.toggleDropdown('more', event)" 
                                title="More filesystem and manifest operations" 
                                style="padding:0.25rem 0.6rem; font-size:0.8rem;">
                            ⋯ More ▾
                        </button>
                        ${this.state.openDropdown === 'more' ? `
                            <div class="spp-dropdown-menu right">
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.compressCurrentDirectory(); window.filesViewInstance.closeDropdowns();">
                                    <span>📦</span> <span>Compress Folder to ZIP</span>
                                </button>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.openChmodModal(); window.filesViewInstance.closeDropdowns();">
                                    <span>🔒</span> <span>Change Permissions (chmod)...</span>
                                </button>
                                <div class="spp-dropdown-divider"></div>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.editDirectoryProps(); window.filesViewInstance.closeDropdowns();">
                                    <span>🏷️</span> <span>Directory Props Modal...</span>
                                </button>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.openRawManifestModal(); window.filesViewInstance.closeDropdowns();">
                                    <span>📜</span> <span>Raw YAML Manifest Editor...</span>
                                </button>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.openManifestHealthModal(null, false); window.filesViewInstance.closeDropdowns();">
                                    <span>🩺</span> <span>Manifest Health Dashboard...</span>
                                </button>
                                <div class="spp-dropdown-divider"></div>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.openTreemapModal(); window.filesViewInstance.closeDropdowns();">
                                    <span>📊</span> <span>Storage Treemap</span>
                                </button>
                                <button type="button" class="spp-dropdown-item" onclick="window.filesViewInstance.openWebToolsModal(); window.filesViewInstance.closeDropdowns();">
                                    <span>🌐</span> <span>Web Tools & Logs</span>
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    renderFileGrid() {
        const items = this.getFilteredItems();
        if (items.length === 0) {
            return `
                <div style="padding:3rem 1rem; text-align:center; color:var(--text-dim);">
                    <div style="font-size:2rem; margin-bottom:0.5rem;">📂</div>
                    <div>${this.state.searchQuery ? 'No items match your filter.' : 'This directory is empty.'}</div>
                </div>
            `;
        }

        return `
            <div class="spp-file-grid">
                ${items.map(item => {
                    const normPath = this.normalizePath(item.path);
                    const isSelected = this.state.selectedItems && this.state.selectedItems.has(normPath);
                    const icon = this.getFileIcon(item.extension, item.is_dir, item.name);
                    const isSelectedForInspector = this.state.selectedItemForInspector && this.normalizePath(this.state.selectedItemForInspector.path) === normPath;
                    return `
                        <div class="spp-grid-item ${isSelected ? 'selected' : ''} ${isSelectedForInspector ? 'active' : ''}" 
                             data-path="${this.escapeHtml(item.path)}"
                             onclick="window.filesViewInstance.selectItemByPath('${this.escapeHtml(item.path)}')"
                             ondblclick="${item.is_dir ? `window.filesViewInstance.loadDirectory('${this.escapeHtml(item.path)}')` : `window.filesViewInstance.openFile('${this.escapeHtml(item.path)}')`}"
                             oncontextmenu="window.filesViewInstance.onRowContextMenu(event, '${this.escapeHtml(item.path)}')">
                            <input type="checkbox" class="spp-grid-item-checkbox" 
                                   ${isSelected ? 'checked' : ''} 
                                   onclick="event.stopPropagation(); window.filesViewInstance.toggleSelectItem('${this.escapeHtml(item.path)}', event)" />
                            <div class="spp-grid-item-icon">${icon}</div>
                            <div class="spp-grid-item-name" title="${this.escapeHtml(item.name)}">
                                ${this.escapeHtml(item.name)}
                            </div>
                            <div class="spp-grid-item-meta">
                                ${item.is_dir ? 'Folder' : this.formatBytes(item.size || 0)}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderInspectorDrawer() {
        const item = this.state.selectedItemForInspector;
        const dm = this.state.directoryManifest;
        const currentPath = this.state.currentPath || '/';
        const tab = this.state.inspectorTab || 'overview';

        const targetName = item ? item.name : (currentPath.split('/').pop() || 'Root (/)');
        const targetPath = item ? item.path : currentPath;
        const targetIcon = item ? this.getFileIcon(item.extension, item.is_dir, item.name) : '📁';

        return `
            <div class="spp-file-inspector-drawer">
                <!-- Inspector Header -->
                <div class="spp-inspector-header">
                    <div style="display:flex; align-items:center; gap:0.45rem; min-width:0; overflow:hidden;">
                        <span style="font-size:1.15rem; flex-shrink:0;">${targetIcon}</span>
                        <div style="min-width:0; overflow:hidden;">
                            <div style="font-size:0.85rem; font-weight:700; color:var(--text-bright); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                ${this.escapeHtml(targetName)}
                            </div>
                            <div style="font-size:0.7rem; color:var(--text-dim); font-family:monospace; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                ${this.escapeHtml(targetPath)}
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.3rem;">
                        ${item ? `
                            <button class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.inspectCurrentDirectory()" title="Inspect current directory" style="padding:0.15rem 0.35rem; font-size:0.7rem;">
                                📁 Dir
                            </button>
                        ` : ''}
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.toggleInspector(false)" title="Close Inspector (Esc)" style="padding:0.15rem 0.4rem; font-size:0.9rem;">
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Inspector 6 Navigation Tabs -->
                <div class="spp-inspector-tabs">
                    <button type="button" class="spp-inspector-tab ${tab === 'overview' ? 'active' : ''}" onclick="window.filesViewInstance.switchInspectorTab('overview')">
                        📋 Overview
                    </button>
                    <button type="button" class="spp-inspector-tab ${tab === 'props' ? 'active' : ''}" onclick="window.filesViewInstance.switchInspectorTab('props')">
                        🏷️ Properties
                    </button>
                    <button type="button" class="spp-inspector-tab ${tab === 'manifest' ? 'active' : ''}" onclick="window.filesViewInstance.switchInspectorTab('manifest')">
                        📜 Manifest
                    </button>
                    <button type="button" class="spp-inspector-tab ${tab === 'health' ? 'active' : ''}" onclick="window.filesViewInstance.switchInspectorTab('health')">
                        🩺 Health
                    </button>
                    <button type="button" class="spp-inspector-tab ${tab === 'tracking' ? 'active' : ''}" onclick="window.filesViewInstance.switchInspectorTab('tracking')">
                        📊 Tracking
                    </button>
                    <button type="button" class="spp-inspector-tab ${tab === 'security' ? 'active' : ''}" onclick="window.filesViewInstance.switchInspectorTab('security')">
                        🛡️ Security
                    </button>
                </div>

                <!-- Inspector Tab Content -->
                <div class="spp-inspector-content">
                    ${this.renderInspectorTabContent(tab, item, dm)}
                </div>
            </div>
        `;
    }

    renderInspectorTabContent(tab, item, dm) {
        const isFile = Boolean(item && !item.is_dir);
        const currentPath = this.state.currentPath || '/';
        const hasManifest = Boolean(dm && (dm.has_manifest || dm.exists));
        const dirProps = (dm && dm.directory_props) ? dm.directory_props : (dm?.directory || {});
        const fileProps = item ? item : {};

        const activeProps = isFile ? fileProps : dirProps;
        const desc = activeProps.description || activeProps.comment || (hasManifest ? 'No description declared.' : 'No __spp_file_props.yml declared in this folder.');
        const usage = activeProps.usage || '';
        const author = activeProps.author || '';
        const reviewDate = activeProps.review_date || '';
        const stability = activeProps.stability || 'stable';
        const layer = activeProps.layer || 'unspecified';
        const tags = Array.isArray(activeProps.tags) ? activeProps.tags : (activeProps.attributes?.tags || []);
        const security = activeProps.security || dirProps.security || {};

        if (tab === 'overview') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <!-- Identity Card -->
                    <div style="padding:0.75rem; background:var(--bg-subtle, rgba(0,0,0,0.02)); border:1px solid var(--glass-border); border-radius:6px; display:flex; flex-direction:column; gap:0.4rem;">
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Manifest Registration</div>
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <span class="suite-pill ${hasManifest ? 'suite-pill-success' : 'suite-pill-warning'}" style="font-size:0.7rem;">
                                ${hasManifest ? '🟢 Manifest Active' : '🟡 No Manifest Found'}
                            </span>
                            <span class="suite-pill ${stability === 'stable' ? 'suite-pill-success' : 'suite-pill-warning'}" style="font-size:0.7rem;">
                                ${this.escapeHtml(stability)}
                            </span>
                        </div>
                    </div>

                    <!-- File Details -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; font-size:0.75rem;">
                        <div style="padding:0.5rem; background:var(--bg-subtle); border-radius:4px; border:1px solid var(--glass-border);">
                            <div style="color:var(--text-dim); margin-bottom:2px;">Size</div>
                            <div style="font-weight:600; font-family:monospace;">${item ? (item.is_dir ? 'Directory' : this.formatBytes(item.size || 0)) : (dm?.tracked_files_count ? `${dm.tracked_files_count} files` : 'Directory')}</div>
                        </div>
                        <div style="padding:0.5rem; background:var(--bg-subtle); border-radius:4px; border:1px solid var(--glass-border);">
                            <div style="color:var(--text-dim); margin-bottom:2px;">Permissions</div>
                            <div style="font-weight:600; font-family:monospace;">${item ? (item.permissions || '0755') : '0755'}</div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <div style="font-size:0.75rem; font-weight:700; color:var(--text-dim); margin-bottom:0.25rem;">Description</div>
                        <div style="font-size:0.8rem; color:var(--text-bright); line-height:1.4; padding:0.5rem; background:var(--bg-subtle); border-radius:4px; border:1px solid var(--glass-border);">
                            ${this.escapeHtml(desc)}
                        </div>
                    </div>

                    ${usage ? `
                        <div>
                            <div style="font-size:0.75rem; font-weight:700; color:var(--text-dim); margin-bottom:0.25rem;">Usage Guidelines</div>
                            <div style="font-size:0.75rem; color:#38bdf8; font-family:monospace; line-height:1.3; padding:0.45rem; background:rgba(0,0,0,0.25); border-radius:4px; border:1px solid var(--glass-border);">
                                💡 ${this.escapeHtml(usage)}
                            </div>
                        </div>
                    ` : ''}

                    <!-- Tags & Attributes -->
                    ${tags.length > 0 ? `
                        <div>
                            <div style="font-size:0.75rem; font-weight:700; color:var(--text-dim); margin-bottom:0.25rem;">Tags</div>
                            <div style="display:flex; flex-wrap:wrap; gap:0.25rem;">
                                ${tags.map(t => `<span class="suite-pill suite-pill-neutral" style="font-size:0.68rem;">#${this.escapeHtml(t)}</span>`).join('')}
                            </div>
                        </div>
                    ` : ''}

                    <!-- Quick Actions -->
                    <div style="display:flex; gap:0.4rem; flex-wrap:wrap; margin-top:0.5rem;">
                        <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.switchInspectorTab('props')" style="flex:1; justify-content:center; font-size:0.78rem;">
                            ✏️ Edit Props
                        </button>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.healCurrentManifest()" title="Auto-sync and register untracked files" style="font-size:0.78rem;">
                            ⚡ Auto-Sync
                        </button>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.signCurrentDirectory()" title="Cryptographically sign files" style="font-size:0.78rem;">
                            🔏 Sign
                        </button>
                    </div>
                </div>
            `;
        }

        if (tab === 'props') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.2rem;">Description</label>
                        <textarea id="inspector-prop-desc" class="spp-find-input" rows="3" style="width:100%; box-sizing:border-box; font-size:0.8rem; padding:0.4rem; border-radius:4px;" placeholder="Purpose of this item...">${this.escapeHtml(desc)}</textarea>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.2rem;">Usage Guidelines</label>
                        <textarea id="inspector-prop-usage" class="spp-find-input" rows="2" style="width:100%; box-sizing:border-box; font-size:0.8rem; padding:0.4rem; border-radius:4px;" placeholder="How developers should consume this...">${this.escapeHtml(usage)}</textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.2rem;">Author</label>
                            <input type="text" id="inspector-prop-author" class="spp-find-input" value="${this.escapeHtml(author)}" style="width:100%; box-sizing:border-box; font-size:0.8rem; padding:0.35rem;" placeholder="Team / Dev" />
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.2rem;">Stability</label>
                            <select id="inspector-prop-stability" class="spp-find-input" style="width:100%; box-sizing:border-box; font-size:0.8rem; padding:0.35rem;">
                                <option value="stable" ${stability === 'stable' ? 'selected' : ''}>Stable</option>
                                <option value="beta" ${stability === 'beta' ? 'selected' : ''}>Beta</option>
                                <option value="experimental" ${stability === 'experimental' ? 'selected' : ''}>Experimental</option>
                                <option value="deprecated" ${stability === 'deprecated' ? 'selected' : ''}>Deprecated</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.2rem;">Layer</label>
                            <select id="inspector-prop-layer" class="spp-find-input" style="width:100%; box-sizing:border-box; font-size:0.8rem; padding:0.35rem;">
                                <option value="unspecified" ${layer === 'unspecified' ? 'selected' : ''}>Unspecified</option>
                                <option value="core" ${layer === 'core' ? 'selected' : ''}>Core</option>
                                <option value="app" ${layer === 'app' ? 'selected' : ''}>App</option>
                                <option value="module" ${layer === 'module' ? 'selected' : ''}>Module</option>
                                <option value="presentation" ${layer === 'presentation' ? 'selected' : ''}>Presentation</option>
                                <option value="domain" ${layer === 'domain' ? 'selected' : ''}>Domain</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.2rem;">Review Date</label>
                            <input type="date" id="inspector-prop-review-date" class="spp-find-input" value="${this.escapeHtml(reviewDate)}" style="width:100%; box-sizing:border-box; font-size:0.8rem; padding:0.35rem;" />
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--text-dim); margin-bottom:0.2rem;">Tags (comma separated)</label>
                        <input type="text" id="inspector-prop-tags" class="spp-find-input" value="${this.escapeHtml(tags.join(', '))}" style="width:100%; box-sizing:border-box; font-size:0.8rem; padding:0.35rem;" placeholder="e.g. security, api, internal" />
                    </div>
                    <button type="button" class="btn primary-btn btn-sm" onclick="window.filesViewInstance.saveInspectorProperties()" style="margin-top:0.4rem; justify-content:center;">
                        💾 Save Properties to Manifest
                    </button>
                </div>
            `;
        }

        if (tab === 'manifest') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.6rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.75rem; font-weight:700; color:var(--text-dim);">__spp_file_props.yml</span>
                        <div style="display:flex; gap:0.3rem;">
                            <button type="button" class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.validateInspectorYaml()" title="Validate YAML syntax">
                                🔍 Validate
                            </button>
                            <button type="button" class="btn secondary-btn btn-xs" onclick="window.filesViewInstance.openRawManifestModal()" title="Open full-screen modal editor">
                                ↗ Popout
                            </button>
                        </div>
                    </div>
                    <div id="inspector-manifest-validation-msg" style="display:none; font-size:0.75rem; padding:0.4rem 0.6rem; border-radius:4px;"></div>
                    <textarea id="inspector-manifest-raw-yaml" class="spp-find-input" rows="14" style="width:100%; box-sizing:border-box; font-family:monospace; font-size:0.78rem; padding:0.5rem; background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; line-height:1.4;" placeholder="Loading manifest YAML...">${this.escapeHtml(this.state.rawManifestModal?.rawYaml || dm?.raw_yaml || '')}</textarea>
                    <button type="button" class="btn primary-btn btn-sm" onclick="window.filesViewInstance.saveInspectorManifestYaml()" style="justify-content:center;">
                        💾 Save YAML Manifest
                    </button>
                </div>
            `;
        }

        if (tab === 'health') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <div style="padding:0.85rem; background:var(--bg-subtle); border:1px solid var(--glass-border); border-radius:6px; text-align:center;">
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; font-weight:700;">Manifest Health Score</div>
                        <div style="font-size:2rem; font-weight:700; color:#22c55e; margin:0.3rem 0;">100%</div>
                        <div style="font-size:0.72rem; color:var(--text-dim);">Directory manifest is synchronized with disk.</div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.4rem; font-size:0.78rem;">
                        <div style="display:flex; justify-content:space-between; padding:0.35rem 0.5rem; background:var(--bg-subtle); border-radius:4px;">
                            <span>Tracked Files</span>
                            <span style="font-weight:600;">${dm?.tracked_files_count || (this.state.items || []).length}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:0.35rem 0.5rem; background:var(--bg-subtle); border-radius:4px;">
                            <span>Discrepancies</span>
                            <span style="font-weight:600; color:#22c55e;">0</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:0.35rem 0.5rem; background:var(--bg-subtle); border-radius:4px;">
                            <span>YAML Schema Status</span>
                            <span style="font-weight:600; color:#22c55e;">Valid</span>
                        </div>
                    </div>
                    <button type="button" class="btn primary-btn btn-sm" onclick="window.filesViewInstance.healCurrentManifest()" style="justify-content:center; margin-top:0.3rem;">
                        ⚡ Auto-Sync & Heal Manifest
                    </button>
                </div>
            `;
        }

        if (tab === 'tracking') {
            const files = (this.state.items || []).filter(it => !it.is_dir);
            return `
                <div style="display:flex; flex-direction:column; gap:0.6rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.75rem; font-weight:700; color:var(--text-dim);">Files in Directory (${files.length})</span>
                        <button class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.healCurrentManifest()" title="Register all untracked files into manifest">
                            ⚡ Track All
                        </button>
                    </div>
                    <div style="max-height:280px; overflow-y:auto; display:flex; flex-direction:column; gap:0.25rem;">
                        ${files.map(f => `
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.35rem 0.5rem; background:var(--bg-subtle); border-radius:4px; font-size:0.75rem;">
                                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px;">${this.escapeHtml(f.name)}</span>
                                <span class="suite-pill ${f.has_props ? 'suite-pill-success' : 'suite-pill-neutral'}" style="font-size:0.65rem;">
                                    ${f.has_props ? 'Tracked' : 'Unregistered'}
                                </span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        if (tab === 'security') {
            return `
                <div style="display:flex; flex-direction:column; gap:0.6rem;">
                    <div style="font-size:0.75rem; font-weight:700; color:var(--text-dim); text-transform:uppercase;">Runtime Sandbox Capabilities</div>
                    <div style="display:flex; flex-direction:column; gap:0.4rem;">
                        <label style="display:flex; align-items:center; justify-content:space-between; padding:0.45rem 0.6rem; background:var(--bg-subtle); border-radius:4px; font-size:0.78rem;">
                            <span>allow_network</span>
                            <input type="checkbox" ${security.allow_network !== false ? 'checked' : ''} disabled />
                        </label>
                        <label style="display:flex; align-items:center; justify-content:space-between; padding:0.45rem 0.6rem; background:var(--bg-subtle); border-radius:4px; font-size:0.78rem;">
                            <span>allow_filesystem</span>
                            <input type="checkbox" ${security.allow_filesystem !== false ? 'checked' : ''} disabled />
                        </label>
                        <label style="display:flex; align-items:center; justify-content:space-between; padding:0.45rem 0.6rem; background:var(--bg-subtle); border-radius:4px; font-size:0.78rem;">
                            <span>allow_env</span>
                            <input type="checkbox" ${security.allow_env ? 'checked' : ''} disabled />
                        </label>
                        <label style="display:flex; align-items:center; justify-content:space-between; padding:0.45rem 0.6rem; background:var(--bg-subtle); border-radius:4px; font-size:0.78rem;">
                            <span>allow_shell</span>
                            <input type="checkbox" ${security.allow_shell ? 'checked' : ''} disabled />
                        </label>
                    </div>

                    <div style="margin-top:0.4rem; padding:0.65rem; background:var(--bg-subtle); border:1px solid var(--glass-border); border-radius:6px;">
                        <div style="font-size:0.72rem; color:var(--text-dim); margin-bottom:2px;">SHA-256 Digest</div>
                        <div style="font-size:0.7rem; font-family:monospace; color:var(--text-bright); word-break:break-all;">
                            ${item?.checksum ? this.escapeHtml(item.checksum) : 'Not signed yet'}
                        </div>
                    </div>

                    <button type="button" class="btn primary-btn btn-sm" onclick="window.filesViewInstance.signCurrentDirectory()" style="justify-content:center; margin-top:0.3rem;">
                        🔏 Cryptographically Sign Manifest
                    </button>
                </div>
            `;
        }

        return '';
    }

    render() {
        const q = this.state.quota;
        const items = this.getFilteredItems();
        const hasSelection = this.state.selectedItems && this.state.selectedItems.size > 0;
        const isAllSelected = items.length > 0 && items.every(it => this.state.selectedItems && this.state.selectedItems.has(this.normalizePath(it.path)));
        const viewMode = this.state.viewMode || 'list';
        const treeWidth = this.state.treeWidth || 250;

        return `
            <div class="files-view-wrapper" style="display:flex; flex-direction:column; gap:1rem;">
                <!-- Hidden file upload input for multi-file upload -->
                <input type="file" id="spp-file-upload-input" multiple style="display:none;" onchange="window.filesViewInstance.handleFileInputChange(this.files)" />

                <!-- Storage Quota Monitor Container -->
                <div id="spp-quota-monitor-container">
                    ${this.renderQuotaMonitor()}
                </div>

                <!-- Unified Modern File Manager Workstation (4-Region Architecture) -->
                <div class="spp-file-workstation">
                    <!-- Region A: Compact Application Header -->
                    ${this.renderHeader()}

                    <!-- Region B: Unified Navigation Bar -->
                    ${this.renderNavBar()}

                    <!-- Region 3: Primary Operations Toolbar -->
                    ${this.renderToolbar()}

                    <!-- Region C & D: Main Workspace Body (Left Tree, Center Content, Right Inspector Drawer) -->
                    <div class="spp-file-workstation-body">
                        <!-- Left Pane: Directory Tree -->
                        <div class="spp-file-tree-sidebar" style="width:${treeWidth}px; flex:0 0 ${treeWidth}px;">
                            <div class="spp-file-tree-titlebar">
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <span>📁</span>
                                    <span>Folders</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.2rem;">
                                    <button class="btn ghost-btn btn-xs" onclick="window.filesInstance && window.filesInstance.collapseAllTree ? window.filesInstance.collapseAllTree() : window.filesViewInstance.collapseAllTree()" title="Collapse all folders" style="padding:0.12rem 0.35rem; font-size:0.7rem;">
                                        ⊟
                                    </button>
                                    <button class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.expandToCurrent()" title="Expand tree to current directory" style="padding:0.12rem 0.35rem; font-size:0.7rem;">
                                        🎯
                                    </button>
                                    <button class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.loadTree(true)" title="Refresh Directory Tree" style="padding:0.12rem 0.35rem; font-size:0.7rem;">
                                        🔄
                                    </button>
                                </div>
                            </div>
                            <div class="spp-file-tree-scroll" onscroll="window.filesViewInstance.onTreeScroll(this.scrollTop)">
                                <ul class="spp-tree-list">
                                    ${this.renderDirectoryTree()}
                                </ul>
                            </div>
                        </div>

                        <!-- Resizable Divider Handle -->
                        <div class="spp-tree-resize-handle" onmousedown="window.filesViewInstance.onTreeResizeStart(event)" title="Drag to resize tree sidebar"></div>

                        <!-- Center Pane: Main Files & Folders Content Area -->
                        <div class="spp-file-main-area">
                            <!-- Drag & Drop Upload Zone Strip -->
                            <div class="spp-file-dropzone-strip" 
                                onclick="document.getElementById('spp-file-upload-input').click()"
                                ondragover="window.filesViewInstance.onDropzoneDragOver(event)"
                                ondragleave="window.filesViewInstance.onDropzoneDragLeave(event)"
                                ondrop="window.filesViewInstance.onDropzoneDrop(event)"
                                title="Click or drag files here to upload">
                                <span>📥 Drag & drop files here to upload, or <strong style="color:var(--primary,#ea580c); cursor:pointer;">click to browse</strong></span>
                            </div>

                            <!-- Floating / Sticky Bulk Action Bar -->
                            ${hasSelection ? `
                                <div class="spp-bulk-action-bar">
                                    <div style="display:flex; align-items:center; gap:0.5rem; font-weight:600; font-size:0.85rem; color:var(--text-bright);">
                                        <span>☑️ <strong>${this.state.selectedItems.size}</strong> item(s) selected</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:0.35rem; flex-wrap:wrap;">
                                        <button class="btn danger-btn btn-sm" onclick="window.filesViewInstance.promptBulkDelete()" title="Permanently delete selected files/folders">
                                            🗑️ Delete Selected
                                        </button>
                                        <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.promptBulkZip()" title="Create ZIP archive containing selected items">
                                            📦 Zip Selected
                                        </button>
                                        <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.downloadSelectedAsZip()" title="Download selected items as single ZIP">
                                            ⬇️ Download ZIP
                                        </button>
                                        <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.openMoveCopyModal('move')" title="Move selected items to another directory">
                                            ↗️ Move...
                                        </button>
                                        <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.openMoveCopyModal('copy')" title="Copy selected items to another directory">
                                            📋 Copy...
                                        </button>
                                        <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.openChmodModal()" title="Change permissions of selected items">
                                            🔒 Permissions...
                                        </button>
                                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.clearSelection()" title="Clear selection">
                                            ✕ Deselect
                                        </button>
                                    </div>
                                </div>
                            ` : ''}

                            <!-- Files Area (List View Table or Grid View Cards) -->
                            <div class="spp-file-table-scroll"
                                ondragover="window.filesViewInstance.onDropzoneDragOver(event)"
                                ondragleave="window.filesViewInstance.onDropzoneDragLeave(event)"
                                ondrop="window.filesViewInstance.onDropzoneDrop(event)">
                                ${this.state.loading ? `
                                    <div style="padding:4rem 2rem; text-align:center;">
                                        <div class="sppux-spinner"></div>
                                        <div style="margin-top:0.75rem; color:var(--text-dim); font-size:0.85rem;">Loading files & metadata...</div>
                                    </div>
                                ` : (viewMode === 'grid' ? this.renderFileGrid() : `
                                    <table class="suite-table" style="min-width:680px; width:100%;">
                                        <thead>
                                            <tr>
                                                <th style="width:36px; min-width:36px; max-width:36px; text-align:center; padding:0.5rem 0.3rem;">
                                                    <input type="checkbox" id="spp-file-select-all" class="spp-file-checkbox" 
                                                           ${isAllSelected ? 'checked' : ''} 
                                                           onchange="window.filesViewInstance.toggleSelectAll(this.checked)" 
                                                           title="Select All (Ctrl+A)" />
                                                </th>
                                                <th onclick="window.filesViewInstance.setSortColumn('name')" style="min-width:130px; cursor:pointer;" title="Sort by Name">
                                                    Name ${this.getSortIndicator('name')}
                                                </th>
                                                <th onclick="window.filesViewInstance.setSortColumn('description')" style="min-width:160px; cursor:pointer;" title="Sort by Description">
                                                    Description & Notes ${this.getSortIndicator('description')}
                                                </th>
                                                <th onclick="window.filesViewInstance.setSortColumn('size')" style="width:80px; text-align:right; white-space:nowrap; cursor:pointer;" title="Sort by Size">
                                                    Size ${this.getSortIndicator('size')}
                                                </th>
                                                <th onclick="window.filesViewInstance.setSortColumn('modified')" style="width:140px; white-space:nowrap; cursor:pointer;" title="Sort by Modified Date">
                                                    Modified ${this.getSortIndicator('modified')}
                                                </th>
                                                <th style="width:100px; min-width:100px; text-align:center; padding:0.6rem 0.75rem; white-space:nowrap;">
                                                    Permissions
                                                </th>
                                                <th class="spp-file-actions-col" style="text-align:right; width:150px; min-width:150px; padding:0.6rem 0.75rem; white-space:nowrap;">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody id="spp-files-table-body">
                                            ${this.renderFileRows()}
                                        </tbody>
                                    </table>
                                `)}
                            </div>
                        </div>

                        <!-- Region D: Collapsible Right-Side Inspector Drawer -->
                        ${this.state.inspectorOpen ? this.renderInspectorDrawer() : ''}
                    </div>
                </div>

                <!-- Keyboard Shortcuts Reference Cheatsheet Modal -->
                ${this.state.showShortcutsModal ? this.renderShortcutsCheatsheet() : ''}

                <!-- File Properties & Metadata Manifest Modal -->
                ${this.state.filePropsModal ? this.renderFilePropsModal() : ''}

                <!-- Structured & Raw YAML Manifest Editor Modal -->
                ${this.state.rawManifestModal ? this.renderRawManifestModal() : ''}

                <!-- Manifest OS Health Dashboard Modal -->
                ${this.state.manifestHealthModal ? this.renderManifestHealthModal() : ''}

                <!-- Architectural Blast Radius Modal -->
                ${this.state.impactModal ? this.renderImpactModal() : ''}

                <!-- Bulk Delete Confirmation Modal -->
                ${this.state.bulkDeleteModal ? this.renderBulkDeleteModal() : ''}

                <!-- Move & Copy Item Modal -->
                ${this.state.moveCopyModal ? this.renderMoveCopyModal() : ''}

                <!-- Visual Chmod Modal -->
                ${this.state.chmodModal ? this.renderChmodModal() : ''}

                <!-- Image Lightbox Preview Modal -->
                ${this.state.imagePreviewModal ? this.renderImagePreviewModal() : ''}
            </div>
        `;
    }

    renderDirectoryTree() {
        if (!this.state.treeData) {
            return `
                <li style="padding:1.5rem 1rem; color:var(--text-dim); text-align:center; font-size:0.8rem;">
                    <div class="sppux-spinner" style="margin:0 auto 0.5rem; width:20px; height:20px;"></div>
                    <div>Loading tree...</div>
                </li>
            `;
        }
        return this.renderTreeNode(this.state.treeData);
    }

    renderTreeNode(node) {
        if (!node) return '';
        const normPath = this.normalizePath(node.path || '');
        const currentNorm = this.normalizePath(this.state.currentPath || '');
        const sysRoot = this.normalizePath(this.state.systemRoot || '/');
        const isRoot = (!normPath || normPath === '/' || normPath === sysRoot);
        const isActive = isRoot ? (!currentNorm || currentNorm === '/' || currentNorm === sysRoot) : (currentNorm === normPath);
        const isExpanded = this.state.expandedFolders ? (this.state.expandedFolders.has(normPath) || (isRoot && (this.state.expandedFolders.has('') || this.state.expandedFolders.has('/') || this.state.expandedFolders.has(sysRoot)))) : false;
        const hasChildren = (node.children && node.children.length > 0) || node.has_children === true;

        return `
            <li class="spp-tree-item">
                <div class="spp-tree-node ${isActive ? 'active' : ''}" 
                     data-tree-path="${this.escapeHtml(normPath || sysRoot)}"
                     onclick="window.filesViewInstance.loadDirectory('${this.escapeHtml(normPath || sysRoot)}')"
                     oncontextmenu="window.filesViewInstance.onTreeContextMenu(event, '${this.escapeHtml(normPath || sysRoot)}')"
                     ondragover="window.filesViewInstance.onFolderDragOver(event)"
                     ondragleave="window.filesViewInstance.onFolderDragLeave(event)"
                     ondrop="window.filesViewInstance.onFolderDrop(event, '${this.escapeHtml(normPath || sysRoot)}')"
                     title="${this.escapeHtml(normPath ? normPath : 'Root (/)')} (Right-click for options)">
                    ${hasChildren ? `
                        <span class="spp-tree-arrow" onclick="window.filesViewInstance.toggleFolderExpand('${this.escapeHtml(normPath || sysRoot)}', event)">
                            ${isExpanded ? '▼' : '▶'}
                        </span>
                    ` : `<span class="spp-tree-arrow" style="visibility:hidden;">•</span>`}
                    <span class="spp-tree-icon">${isRoot ? '🖥️' : (isExpanded ? '📂' : '📁')}</span>
                    <span class="spp-tree-label">${this.escapeHtml(isRoot ? 'Root (/)' : node.name)}</span>
                </div>
                ${(hasChildren && isExpanded) ? `
                    <ul class="spp-tree-children">
                        ${node.children && node.children.length > 0 
                            ? node.children.map(child => this.renderTreeNode(child)).join('')
                            : (node._loading
                                ? `<li style="padding:0.25rem 0.75rem; color:var(--text-dim); font-size:0.75rem;"><div class="sppux-spinner" style="width:12px; height:12px; display:inline-block; vertical-align:middle; margin-right:4px;"></div> Loading...</li>`
                                : `<li style="padding:0.25rem 0.75rem; color:var(--text-dim); font-size:0.75rem; font-style:italic;">(empty)</li>`
                            )
                        }
                    </ul>
                ` : ''}
            </li>
        `;
    }

    renderBreadcrumbs() {
        const cur = this.normalizePath(this.state.currentPath || '');
        const sysRoot = this.normalizePath(this.state.systemRoot || '/');
        if (!cur || cur === '/' || cur === sysRoot) return '';

        const isAbs = cur.startsWith('/');
        const isWin = /^[a-zA-Z]:/.test(cur);
        const parts = cur.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);

        let accum = '';
        return parts.map((p, idx) => {
            if (isAbs) {
                accum += '/' + p;
            } else if (isWin && idx === 0) {
                accum = p.endsWith(':') ? (p + '/') : p;
            } else {
                accum = (accum && !accum.endsWith('/')) ? `${accum}/${p}` : `${accum}${p}`;
            }
            const currentAccum = this.normalizePath(accum);
            return `
                <span class="suite-breadcrumb-separator">/</span>
                <span class="suite-breadcrumb-item" onclick="window.filesViewInstance.loadDirectory('${this.escapeHtml(currentAccum)}')">
                    ${this.escapeHtml(p)}
                </span>
            `;
        }).join('');
    }

    renderFileRows() {
        if (this.state.errorMessage) {
            return `<tr><td colspan="7" style="text-align:center; padding:2.5rem; color:var(--text-dim);">
                <div style="font-size:1.5rem; margin-bottom:0.5rem;">⚠️</div>
                <div style="font-weight:600; font-size:0.95rem; color:var(--text-bright); margin-bottom:0.75rem;">${this.escapeHtml(this.state.errorMessage)}</div>
                <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.loadDirectory('${this.escapeHtml(this.state.currentPath || '/')}')">🔄 Retry</button>
            </td></tr>`;
        }

        const items = this.getFilteredItems();

        if (items.length === 0) {
            return `<tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--text-dim);">${this.state.searchQuery ? 'No items match your filter.' : 'Directory is empty.'}</td></tr>`;
        }

        const normalizePath = (p) => (p || '').replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
        return items.map(item => {
            const isZip = item.extension === 'zip' || (item.name && item.name.endsWith('.zip'));
            const isImage = !item.is_dir && ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'].includes((item.extension || '').toLowerCase());
            const isDnsZone = !item.is_dir && (item.is_dns_zone || (item.name && item.name.endsWith('.zone')) || (item.path && item.path.includes('var/dns/')));
            const dnsDomain = isDnsZone ? item.name.replace(/\.zone$/, '') : '';
            const icon = this.getFileIcon(item.extension, item.is_dir, item.name);
            const normItemPath = this.normalizePath(item.path);
            const isSelected = this.state.selectedItems && this.state.selectedItems.has(normItemPath);
            const isActive = this.state.isEditing && !item.is_dir && (normalizePath(this.state.activeFile) === normalizePath(item.path));
            const hasNotes = Boolean(item.comment || item.usage || (item.attributes?.tags && item.attributes.tags.length > 0));

            const rawComment = item.comment || '';
            const cleanComment = rawComment.replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim();
            const isCommentLong = cleanComment.length > 70;
            const displayComment = isCommentLong ? cleanComment.substring(0, 67) + '...' : cleanComment;

            const rawUsage = item.usage || '';
            const cleanUsage = rawUsage.replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim();
            const isUsageLong = cleanUsage.length > 55;
            const displayUsage = isUsageLong ? cleanUsage.substring(0, 52) + '...' : cleanUsage;

            return `
                <tr class="suite-file-row ${isActive ? 'suite-file-row-active' : ''} ${isSelected ? 'suite-file-row-selected' : ''} ${item.is_props_manifest ? 'suite-file-props-row' : ''}" 
                    data-row-path="${this.escapeHtml(item.path)}"
                    onclick="window.filesViewInstance.selectItemByPath('${this.escapeHtml(item.path)}')"
                    ondblclick="${item.is_dir ? `window.filesViewInstance.loadDirectory('${this.escapeHtml(item.path)}')` : (isImage ? `window.filesViewInstance.previewImage('${this.escapeHtml(item.path)}')` : `window.filesViewInstance.openFile('${this.escapeHtml(item.path)}')`)}"
                    oncontextmenu="window.filesViewInstance.onRowContextMenu(event, '${this.escapeHtml(item.path)}')"
                    draggable="true"
                    ondragstart="window.filesViewInstance.onRowDragStart(event, '${this.escapeHtml(item.path)}')"
                    ondragend="window.filesViewInstance.onRowDragEnd(event)"
                    ${item.is_dir ? `
                        ondragover="window.filesViewInstance.onFolderDragOver(event)"
                        ondragleave="window.filesViewInstance.onFolderDragLeave(event)"
                        ondrop="window.filesViewInstance.onFolderDrop(event, '${this.escapeHtml(item.path)}')"
                    ` : ''}>
                    <td style="width:36px; min-width:36px; max-width:36px; text-align:center; padding:0.5rem 0.3rem; vertical-align:middle;" onclick="event.stopPropagation();">
                        <input type="checkbox" class="spp-file-checkbox" 
                               ${isSelected ? 'checked' : ''} 
                               onchange="window.filesViewInstance.toggleSelectItem('${this.escapeHtml(item.path)}', event)" 
                               title="Select '${this.escapeHtml(item.name)}'" />
                    </td>
                    <td style="vertical-align:middle; white-space:nowrap;">
                        <span style="cursor:pointer; display:inline-flex; align-items:center; gap:0.4rem; min-width:0;" 
                            onclick="${item.is_dir ? `window.filesViewInstance.loadDirectory('${this.escapeHtml(item.path)}')` : (isImage ? `window.filesViewInstance.previewImage('${this.escapeHtml(item.path)}')` : `window.filesViewInstance.openFile('${this.escapeHtml(item.path)}')`)}">
                            <span style="font-size:1.1rem; flex-shrink:0;">${icon}</span>
                            <strong style="color:${isActive ? 'var(--primary, #ea580c)' : (item.is_props_manifest ? '#58a6ff' : (item.is_dir ? 'var(--primary-hover)' : 'var(--text-bright)'))}; font-size:0.875rem; font-weight:${isActive || item.is_props_manifest ? '700' : '500'};">
                                ${this.escapeHtml(item.name)}
                            </strong>
                            ${isDnsZone ? `
                                <span class="suite-pill suite-pill-primary" style="font-size:0.65rem; padding:0.12rem 0.45rem; background:rgba(14,165,233,0.15); color:#38bdf8; border:1px solid rgba(14,165,233,0.4);" title="Configured DNS zone file">
                                    🌐 DNS Zone
                                </span>
                            ` : ''}
                            ${isActive ? `
                                <span class="suite-pill suite-pill-primary" style="font-size:0.65rem; padding:0.12rem 0.45rem; text-transform:uppercase; letter-spacing:0.04em; font-weight:700;">
                                    ✏️ Editing
                                </span>
                            ` : ''}
                        </span>
                    </td>
                    <td style="max-width:300px; vertical-align:middle; overflow:hidden;">
                        ${item.is_props_manifest ? `
                            <div style="display:inline-flex; align-items:center; gap:0.4rem;">
                                <span class="suite-pill suite-pill-primary" style="font-size:0.68rem; font-weight:700; background:rgba(56, 139, 253, 0.15); border:1px solid rgba(56, 139, 253, 0.4); color:#58a6ff;">
                                    ⚙️ Properties Manifest
                                </span>
                                <span style="font-size:0.75rem; color:var(--text-dim);">Directory Metadata Standard</span>
                            </div>
                        ` : hasNotes ? `
                            <div style="display:flex; flex-direction:column; gap:0.25rem; max-width:290px;">
                                ${rawComment ? `
                                    <div class="spp-file-comment-preview" 
                                         title="${this.escapeHtml(rawComment)}" 
                                         onclick="window.filesViewInstance.editFileProps('${this.escapeHtml(item.path)}', ${item.is_dir})"
                                         style="font-size:0.8rem; color:var(--text-bright); line-height:1.25; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:pointer;">
                                        ${this.escapeHtml(displayComment)}
                                    </div>
                                ` : ''}
                                ${rawUsage ? `
                                    <div class="spp-file-usage-preview" 
                                         title="${this.escapeHtml(rawUsage)}" 
                                         onclick="window.filesViewInstance.editFileProps('${this.escapeHtml(item.path)}', ${item.is_dir})"
                                         style="font-size:0.72rem; color:var(--text-dim); font-family:monospace; line-height:1.2; background:rgba(255,255,255,0.03); padding:2px 4px; border-radius:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:pointer;">
                                        💡 ${this.escapeHtml(displayUsage)}
                                    </div>
                                ` : ''}
                                <div style="display:flex; gap:0.3rem; flex-wrap:wrap; align-items:center; margin-top:0.1rem;">
                                    ${(item.attributes?.tags || []).map(t => `<span class="suite-pill suite-pill-neutral" style="font-size:0.65rem; padding:0.05rem 0.35rem;">#${this.escapeHtml(t)}</span>`).join('')}
                                    ${item.attributes?.stability ? `<span class="suite-pill ${item.attributes.stability === 'stable' ? 'suite-pill-success' : 'suite-pill-warning'}" style="font-size:0.65rem; padding:0.05rem 0.35rem;">${this.escapeHtml(item.attributes.stability)}</span>` : ''}
                                    <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.editFileProps('${this.escapeHtml(item.path)}', ${item.is_dir})" title="Edit notes & attributes (__spp_file_props.yml)" style="font-size:0.65rem; padding:0.05rem 0.3rem; border:none; opacity:0.75;">📝</button>
                                </div>
                            </div>
                        ` : `
                            <span style="font-size:0.75rem; color:var(--text-dim); cursor:pointer; text-decoration:underline dashed; text-underline-offset:3px;" 
                                  onclick="window.filesViewInstance.editFileProps('${this.escapeHtml(item.path)}', ${item.is_dir})" 
                                  title="Add usage documentation & tags to __spp_file_props.yml">
                                + Add Notes & Tags
                            </span>
                        `}
                    </td>
                    <td style="color:var(--text-dim); font-size:0.8rem; text-align:right; white-space:nowrap; vertical-align:middle;">
                        ${item.is_dir ? '-' : this.formatBytes(item.size)}
                    </td>
                    <td style="color:var(--text-dim); font-size:0.8rem; white-space:nowrap; vertical-align:middle;">${item.modified}</td>
                    <td style="width:110px; min-width:110px; text-align:center; padding:0.6rem 0.75rem; white-space:nowrap; vertical-align:middle;">
                        ${item.permissions ? `<span class="code-badge" style="font-size:0.72rem; padding:0.15rem 0.4rem; font-family:monospace;">${this.escapeHtml(item.permissions)}</span>` : '<span style="color:var(--text-dim); font-size:0.75rem;">-</span>'}
                    </td>
                    <td class="spp-file-actions-col" style="text-align:right; width:150px; min-width:150px; padding:0.6rem 0.75rem; white-space:nowrap; vertical-align:middle;">
                        <div class="spp-row-actions-group">
                            ${item.is_dir ? `
                                <button class="btn secondary-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.downloadFolderAsZip('${this.escapeHtml(item.path)}')" title="Download folder as ZIP">
                                    ⬇️
                                </button>
                                <button class="btn secondary-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.promptRename('${this.escapeHtml(item.path)}', true)" title="Rename folder">
                                    ✏️
                                </button>
                                <button class="btn danger-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.deleteItem('${this.escapeHtml(item.path)}', true)" title="Delete folder permanently">
                                    🗑️
                                </button>
                            ` : isZip ? `
                                <button class="btn secondary-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.extractZip('${this.escapeHtml(item.path)}')" title="Extract archive">
                                    📦
                                </button>
                                <button class="btn secondary-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.downloadFile('${this.escapeHtml(item.path)}')" title="Download archive">
                                    ⬇️
                                </button>
                                <button class="btn danger-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.deleteItem('${this.escapeHtml(item.path)}', false)" title="Delete archive permanently">
                                    🗑️
                                </button>
                            ` : isImage ? `
                                <button class="btn secondary-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.previewImage('${this.escapeHtml(item.path)}')" title="Preview image in lightbox">
                                    👁️
                                </button>
                                <button class="btn secondary-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.downloadFile('${this.escapeHtml(item.path)}')" title="Download file">
                                    ⬇️
                                </button>
                                <button class="btn danger-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.deleteItem('${this.escapeHtml(item.path)}', false)" title="Delete file permanently">
                                    🗑️
                                </button>
                            ` : `
                                ${isDnsZone ? `
                                    <button class="btn secondary-btn btn-sm" onclick="event.stopPropagation(); window.location.hash='#domains?zone=${this.escapeHtml(dnsDomain)}';" title="Open zone in DNS Manager" style="color:#38bdf8; border-color:rgba(56,189,248,0.4);">
                                        🌐 DNS
                                    </button>
                                ` : ''}
                                <button class="btn ${isActive ? 'primary-btn' : 'secondary-btn'} btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.openFile('${this.escapeHtml(item.path)}')" title="Edit file in editor">
                                    ${isActive ? 'Editing' : '✏️'}
                                </button>
                                <button class="btn secondary-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.downloadFile('${this.escapeHtml(item.path)}')" title="Download file">
                                    ⬇️
                                </button>
                                <button class="btn danger-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.deleteItem('${this.escapeHtml(item.path)}', false)" title="Delete file permanently">
                                    🗑️
                                </button>
                            `}
                            <button class="btn ghost-btn btn-sm" onclick="event.stopPropagation(); window.filesViewInstance.selectItemByPath('${this.escapeHtml(item.path)}', true)" title="Inspect metadata & manifest" style="padding:0.2rem 0.4rem; font-size:0.8rem;">
                                👁️
                            </button>
                            <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.openRowMoreMenu(event, '${this.escapeHtml(item.path)}', ${item.is_dir})" title="More actions (Copy, Move, Chmod, Tags, Blast Radius...)">
                                ⋮
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    renderFilePropsModal() {
        const m = this.state.filePropsModal;
        if (!m) return '';

        const titleMap = {
            create: `✨ Create New ${m.type === 'file' ? 'File' : 'Folder'}`,
            rename: `✏️ Rename & Edit Properties (${m.oldName || m.name})`,
            edit: `🏷️ File Properties & Manifest (${m.name})`,
            edit_directory: `📁 Directory Properties & Manifest (${m.name})`
        };

        const tab = m.activeTab || 'overview';

        return `
            <div class="spp-shortcuts-modal-overlay" style="position:fixed; inset:0; z-index:999999; background:rgba(2, 6, 23, 0.75); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;" onclick="if(event.target === this) window.filesViewInstance.closeFilePropsModal();">
                <div class="spp-shortcuts-modal-window" style="width:100%; max-width:820px; background:#0d1117; border:1px solid #30363d; border-radius:10px; box-shadow:0 25px 60px rgba(0,0,0,0.85); overflow:hidden;" onclick="event.stopPropagation();">
                    <div class="spp-shortcuts-modal-header" style="padding:0.9rem 1.25rem; border-bottom:1px solid #21262d; display:flex; justify-content:space-between; align-items:center; background:#161b22;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.1rem;">${m.type === 'folder' ? '📁' : '📄'}</span>
                            <h4 style="margin:0; font-size:0.95rem; color:#f0f6fc; font-weight:600;">${titleMap[m.mode] || 'File Properties'}</h4>
                        </div>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeFilePropsModal()" title="Close (Esc)" style="border:none; color:#8b949e; font-size:1rem; cursor:pointer;">✕</button>
                    </div>

                    <!-- Navigation Tabs -->
                    <div style="display:flex; flex-wrap:wrap; gap:0.35rem; background:#161b22; padding:0.6rem 1.25rem; border-bottom:1px solid #21262d;">
                        <button type="button" class="btn btn-sm ${tab === 'overview' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('overview')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="File identity, size, MIME type, manifest sync">📋 Overview</button>
                        <button type="button" class="btn btn-sm ${tab === 'general' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('general')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="General metadata, description, usage, author, stability">📝 Docs & Tags</button>
                        <button type="button" class="btn btn-sm ${tab === 'app' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('app')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="Application discovery, entry points, and routing">🚀 App & Framework</button>
                        <button type="button" class="btn btn-sm ${tab === 'security' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('security')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="Runtime capabilities, network, env, filesystem, shell guards">🛡️ Capabilities</button>
                        <button type="button" class="btn btn-sm ${tab === 'integrity' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('integrity')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="SHA-256 cryptographic verification and signing">🔒 Integrity</button>
                        <button type="button" class="btn btn-sm ${tab === 'contracts' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('contracts')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="Required props, dependencies, associated tests, routes">📦 Contracts</button>
                        <button type="button" class="btn btn-sm ${tab === 'ai' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('ai')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="AI agent prompts, invariants, and guidelines">🧠 AI Directives</button>
                        <button type="button" class="btn btn-sm ${tab === 'sovereignty' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('sovereignty')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="Data sovereignty, PII classification, and cache tags">🛡️ Sovereignty</button>
                        <button type="button" class="btn btn-sm ${tab === 'governance' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('governance')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="Feature flags, canary rollouts, and SLO performance budgets">⚡ Governance</button>
                        <button type="button" class="btn btn-sm ${tab === 'compliance' ? 'primary-btn' : 'ghost-btn'}" onclick="window.filesViewInstance.switchPropsModalTab('compliance')" style="font-size:0.75rem; padding:0.25rem 0.6rem; white-space:nowrap; border-radius:6px;" title="Event mesh subscriptions and institutional compliance">🔒 Compliance</button>
                    </div>

                    <div style="padding:1.25rem; display:flex; flex-direction:column; gap:0.85rem; max-height:70vh; overflow-y:auto;">
                        <div id="props-modal-error-banner" style="display:none; font-size:0.8rem; color:#f85149; background:rgba(248,81,73,0.15); border:1px solid rgba(248,81,73,0.4); padding:0.5rem 0.75rem; border-radius:6px; line-height:1.4;"></div>

                        ${tab === 'overview' ? `
                            ${this.renderHelpCard('📋', 'File Identity & Filesystem Manifest Registration', 'Overview of canonical path, storage properties, MIME detection, and per-directory manifest synchronization status.', '• <strong>Associated Manifest:</strong> Every directory declares an atomic <code>__spp_file_props.yml</code>.<br>• <strong>Integrity Digest:</strong> Cryptographic SHA-256 fingerprint guarantees zero unauthorized tampering.')}

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#8b949e; margin-bottom:0.25rem;">Canonical Path</label>
                                    <div style="font-size:0.8rem; color:#f0f6fc; font-family:monospace; background:#161b22; padding:0.4rem 0.6rem; border-radius:6px; border:1px solid #30363d; word-break:break-all;">
                                        ${this.escapeHtml(m.canonical_path || m.path || m.name)}
                                    </div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#8b949e; margin-bottom:0.25rem;">Item Type & MIME</label>
                                    <div style="font-size:0.8rem; color:#f0f6fc; font-family:monospace; background:#161b22; padding:0.4rem 0.6rem; border-radius:6px; border:1px solid #30363d;">
                                        ${m.type === 'folder' ? 'Directory' : (m.mime_type || 'application/octet-stream')}
                                    </div>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#8b949e; margin-bottom:0.25rem;">File Size</label>
                                    <div style="font-size:0.85rem; color:#f0f6fc; font-family:monospace; background:#161b22; padding:0.4rem 0.6rem; border-radius:6px; border:1px solid #30363d;">
                                        ${m.type === 'folder' ? '— Directory —' : this.formatBytes(m.size || 0)}
                                    </div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#8b949e; margin-bottom:0.25rem;">Last Modified</label>
                                    <div style="font-size:0.85rem; color:#f0f6fc; font-family:monospace; background:#161b22; padding:0.4rem 0.6rem; border-radius:6px; border:1px solid #30363d;">
                                        ${this.escapeHtml(m.modified || 'Just now')}
                                    </div>
                                </div>
                            </div>

                            <div style="padding:0.75rem; background:rgba(56, 189, 248, 0.08); border:1px solid rgba(56, 189, 248, 0.25); border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-size:0.8rem; font-weight:600; color:#38bdf8;">Governing Directory Manifest</div>
                                    <div style="font-size:0.75rem; color:#8b949e; font-family:monospace;">${this.escapeHtml(m.manifest_path || `${this.state.currentPath}/__spp_file_props.yml`)}</div>
                                </div>
                                <span class="suite-pill suite-pill-success" style="font-size:0.7rem;">Active & Tracked</span>
                            </div>
                        ` : ''}

                        ${tab === 'app' ? `
                            ${this.renderHelpCard('🚀', 'Application Root & Framework Discovery Metadata', 'Registers this directory or file as a first-class SPP application, service, or entry point. Consumed by the SPP Kernel, Scheduler, and domain routing discovery engine.', '• <strong>is_app:</strong> Tells SPP Application Discovery to treat this folder as a discrete application root.<br>• <strong>Entry Point:</strong> Primary bootstrap file (e.g. <code>index.php</code> or <code>App.php</code>).')}

                            <div style="padding:0.75rem; background:#161b22; border:1px solid #30363d; border-radius:6px;">
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; font-weight:600; color:#f0f6fc; cursor:pointer;">
                                    <input type="checkbox" id="props-modal-is-app" ${m.is_app ? 'checked' : ''} style="width:16px; height:16px; accent-color:#0ea5e9;" />
                                    <span>Mark as SPP Application Root (<code>is_app: true</code>)</span>
                                </label>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.35rem; margin-left:1.5rem;">
                                    When checked, the SPP runtime registers this directory as a standalone app context with its own configuration and routing namespace.
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Application Name</label>
                                    <input type="text" id="props-modal-app-name" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.app_name || '')}" placeholder="e.g. school1, student_portal" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Unique application identifier for <code>new \\SPP\\App('name')</code>.</div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Application Type</label>
                                    <select id="props-modal-app-type" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px; background:#161b22; color:#c9d1d9;">
                                        <option value="app" ${m.app_type === 'app' ? 'selected' : ''}>Full Application (MVC / SPA)</option>
                                        <option value="service" ${m.app_type === 'service' ? 'selected' : ''}>Microservice / API Service</option>
                                        <option value="module" ${m.app_type === 'module' ? 'selected' : ''}>SPP Module</option>
                                        <option value="tool" ${m.app_type === 'tool' ? 'selected' : ''}>CLI / Admin Tool</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Entry Point (entry_point)</label>
                                <input type="text" id="props-modal-entry-point" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.entry_point || '')}" placeholder="e.g. index.php or src/App.php" />
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Relative path to the application bootstrap script.</div>
                            </div>
                        ` : ''}

                        ${tab === 'security' ? `
                            ${this.renderHelpCard('🛡️', 'Runtime Capabilities & Isolation Sandbox', 'Fine-grained capability-based security model. ManifestSecurityGuard enforces these restrictions during execution to prevent unauthorized network calls, shell execution, or sandbox escapes.', '• <strong>allow_network:</strong> Ingress/egress socket access.<br>• <strong>allow_filesystem:</strong> Reading/writing files beyond the component sandbox.<br>• <strong>allow_shell:</strong> Spawning system commands via exec() or system().')}

                            <div style="display:flex; flex-direction:column; gap:0.6rem;">
                                <div style="padding:0.75rem; background:#161b22; border:1px solid #30363d; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="font-size:0.85rem; color:#f0f6fc;">allow_network</strong>
                                        <div style="font-size:0.72rem; color:#8b949e;">Permits outbound HTTP, cURL, and socket connections from this component.</div>
                                    </div>
                                    <input type="checkbox" id="props-modal-sec-network" ${m.security_network ? 'checked' : ''} style="width:18px; height:18px; accent-color:#0ea5e9;" />
                                </div>

                                <div style="padding:0.75rem; background:#161b22; border:1px solid #30363d; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="font-size:0.85rem; color:#f0f6fc;">allow_filesystem</strong>
                                        <div style="font-size:0.72rem; color:#8b949e;">Permits arbitrary filesystem reads and writes outside the local directory boundary.</div>
                                    </div>
                                    <input type="checkbox" id="props-modal-sec-filesystem" ${m.security_filesystem ? 'checked' : ''} style="width:18px; height:18px; accent-color:#0ea5e9;" />
                                </div>

                                <div style="padding:0.75rem; background:#161b22; border:1px solid #30363d; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="font-size:0.85rem; color:#f0f6fc;">allow_env</strong>
                                        <div style="font-size:0.72rem; color:#8b949e;">Permits reading server environment variables and secrets via getenv() or $_ENV.</div>
                                    </div>
                                    <input type="checkbox" id="props-modal-sec-env" ${m.security_env ? 'checked' : ''} style="width:18px; height:18px; accent-color:#0ea5e9;" />
                                </div>

                                <div style="padding:0.75rem; background:#161b22; border:1px solid #30363d; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="font-size:0.85rem; color:#f0f6fc;">allow_shell</strong>
                                        <div style="font-size:0.72rem; color:#8b949e;">Permits spawning CLI subprocesses (exec, passthru, proc_open). High-risk privilege.</div>
                                    </div>
                                    <input type="checkbox" id="props-modal-sec-shell" ${m.security_shell ? 'checked' : ''} style="width:18px; height:18px; accent-color:#0ea5e9;" />
                                </div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Taint Sanitizers (taint_sanitizers)</label>
                                <input type="text" id="props-modal-sec-taint" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.taint_sanitizers || '')}" placeholder="htmlspecialchars, filter_var, intval" />
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Functions declared to neutralize user-input taint during static analysis and runtime assertion.</div>
                            </div>
                        ` : ''}

                        ${tab === 'integrity' ? `
                            ${this.renderHelpCard('🔒', 'Cryptographic SHA-256 Digest & Tamper Resistance', 'Every file tracked by __spp_file_props.yml records a cryptographic SHA-256 hash. The FileIntegritySigner validates that files on disk have not been altered or tampered with by external actors.', '• <strong>1-Click Verification:</strong> Recalculates on-disk hash and compares against manifest digest.<br>• <strong>1-Click Signing:</strong> Updates the manifest entry with current SHA-256 fingerprint.')}

                            <div style="padding:1rem; background:#161b22; border:1px solid #30363d; border-radius:6px; display:flex; flex-direction:column; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#8b949e; margin-bottom:0.25rem;">SHA-256 Digest Fingerprint</label>
                                    <div style="font-size:0.8rem; color:#38bdf8; font-family:monospace; background:rgba(0,0,0,0.4); padding:0.5rem 0.75rem; border-radius:6px; border:1px solid #30363d; word-break:break-all;">
                                        ${this.escapeHtml(m.checksum || 'No checksum recorded yet. Click "Sign File" to generate.')}
                                    </div>
                                </div>

                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <span style="font-size:0.8rem; color:#8b949e;">Status:</span>
                                        <span class="suite-pill ${m.checksum ? 'suite-pill-success' : 'suite-pill-warning'}" style="font-size:0.75rem;">
                                            ${m.checksum ? '✓ Signed in Manifest' : '⚠️ Untracked / Unsigned'}
                                        </span>
                                    </div>
                                    <div style="display:flex; gap:0.4rem;">
                                        <button type="button" class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.verifyFileIntegrity('${this.escapeHtml(m.path || m.name)}')">
                                            🔒 Verify Integrity
                                        </button>
                                        <button type="button" class="btn primary-btn btn-sm" onclick="window.filesViewInstance.signFile('${this.escapeHtml(m.path || m.name)}')">
                                            🔏 Sign File
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ` : ''}

                        ${tab === 'general' ? `
                            ${this.renderHelpCard('📋', 'General File Metadata & Clean Architecture Layer', 'Defines the structural identity, tier, and stability lifecycle of this file or directory. SPP compiles this into __spp_file_props.yml to govern dependency graphs, AI code generation, and IDE auto-discovery.', '• <strong>Architectural Layer:</strong> Enforces clean architecture boundaries (e.g. Domain logic must never depend on Presentation controllers).<br>• <strong>Stability:</strong> Signals API readiness (Stable, Beta, Experimental, Deprecated, Internal) to teams and CI/CD pipelines.')}

                            ${m.mode !== 'edit' ? `
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                        ${m.type === 'file' ? 'File Name' : 'Folder Name'} <span style="color:#f85149;">*</span>
                                    </label>
                                    <input type="text" id="props-modal-name" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.name || '')}" placeholder="${m.type === 'file' ? 'e.g. OrderProcessor.php' : 'e.g. services'}" autofocus />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">The filename or directory relative to the current folder (e.g. <code>OrderService.php</code> or <code>services</code>).</div>
                                </div>
                            ` : `
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#8b949e; margin-bottom:0.25rem;">Target Item</label>
                                    <div style="font-size:0.85rem; color:#f0f6fc; font-family:monospace; background:#161b22; padding:0.4rem 0.6rem; border-radius:6px; border:1px solid #30363d;">
                                        ${this.escapeHtml(m.name)}
                                    </div>
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Workspace path of the file or directory being configured.</div>
                                </div>
                            `}

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Description / Purpose</label>
                                <textarea id="props-modal-desc" class="spp-find-input" rows="2" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px; resize:vertical;" placeholder="Brief explanation of what this file or directory achieves...">${this.escapeHtml(m.description || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Explain what this component achieves and its core responsibilities. Read by developers and AI coding agents during architecture synthesis.</div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Usage Example / Invocation Guide</label>
                                <textarea id="props-modal-usage" class="spp-find-input" rows="2" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; font-family:monospace; border-radius:6px; resize:vertical;" placeholder="e.g. OrderProcessor::dispatch($order, $user);">${this.escapeHtml(m.usage || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Provide a clean, copy-pasteable PHP or template invocation snippet (e.g. <code>OrderService::processPayment($orderId);</code> or <code>&lt;x-billing-card :order="$order" /&gt;</code>).</div>
                            </div>

                            ${m.checksum ? `
                                <div style="font-size:0.75rem; color:#8b949e; background:#161b22; padding:0.4rem 0.65rem; border-radius:6px; border:1px solid #30363d; font-family:monospace; display:flex; align-items:center; justify-content:space-between;">
                                    <span>SHA-256: <code style="color:#58a6ff;">${this.escapeHtml(String(m.checksum || '').substring(0, 32))}...</code></span>
                                    <span style="background:#238636; color:#fff; font-size:0.68rem; padding:0.15rem 0.4rem; border-radius:3px; font-weight:600;">Signed</span>
                                </div>
                            ` : ''}

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Architectural Layer</label>
                                    <select id="props-modal-layer" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px; background:#161b22; color:#c9d1d9;">
                                        <option value="domain" ${m.layer === 'domain' ? 'selected' : ''}>🏛️ Domain (Entities, Models)</option>
                                        <option value="application" ${m.layer === 'application' ? 'selected' : ''}>⚙️ Application (Services, Jobs)</option>
                                        <option value="presentation" ${m.layer === 'presentation' ? 'selected' : ''}>🖥️ Presentation (Controllers, APIs)</option>
                                        <option value="infrastructure" ${m.layer === 'infrastructure' ? 'selected' : ''}>🗄️ Infrastructure (DB, Gateways)</option>
                                        <option value="core" ${m.layer === 'core' ? 'selected' : ''}>⚡ Core / System (Kernel, Guards)</option>
                                        <option value="utility" ${m.layer === 'utility' ? 'selected' : ''}>🛠️ Utility (Helpers, Tools)</option>
                                        <option value="view" ${m.layer === 'view' ? 'selected' : ''}>🎨 View / Partial (Templates, UI)</option>
                                        <option value="config" ${m.layer === 'config' ? 'selected' : ''}>📋 Config (Settings, Manifests)</option>
                                        <option value="unspecified" ${(!m.layer || m.layer === 'unspecified') ? 'selected' : ''}>— Unspecified —</option>
                                    </select>
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Enforces layer hierarchy and prevents circular dependencies in blast-radius analysis.</div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Stability Lifecycle</label>
                                    <select id="props-modal-stability" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px; background:#161b22; color:#c9d1d9;">
                                        <option value="stable" ${m.stability === 'stable' ? 'selected' : ''}>🟢 Stable (Production-ready)</option>
                                        <option value="beta" ${m.stability === 'beta' ? 'selected' : ''}>🟡 Beta (Feature-complete)</option>
                                        <option value="experimental" ${m.stability === 'experimental' ? 'selected' : ''}>🟣 Experimental (Active prototype)</option>
                                        <option value="deprecated" ${m.stability === 'deprecated' ? 'selected' : ''}>🔴 Deprecated (To be removed)</option>
                                        <option value="internal" ${m.stability === 'internal' ? 'selected' : ''}>🔒 Internal Only (Private to module)</option>
                                    </select>
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Lifecycle state. Modifying stable files triggers elevated blast-radius alerts.</div>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Author / Team</label>
                                    <input type="text" id="props-modal-author" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.author || '')}" placeholder="e.g. Core Team" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Responsible squad, team, or author for accountability and reviews.</div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Tags (comma separated)</label>
                                    <input type="text" id="props-modal-tags" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.tags || '')}" placeholder="e.g. billing, stripe, queue" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Keywords for taxonomy, discovery, and documentation indexing.</div>
                                </div>
                            </div>
                        ` : ''}

                        ${tab === 'contracts' ? `
                            ${this.renderHelpCard('📦', 'Component Contracts, Dependencies & Declarative Routing', 'Declare input requirements, code dependencies, associated Parikshak test suites, and HTTP routes without manual router configuration.', '• <strong>Dependencies:</strong> Powers automated dependency injection and blast-radius dependency trees.<br>• <strong>Associated Tests:</strong> Automatically flagged for execution in CI/CD when this file changes.<br>• <strong>Declarative Routes:</strong> Direct HTTP dispatch without editing <code>routes.php</code>.')}

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Required Props</label>
                                    <input type="text" id="props-modal-required-props" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.required_props || '')}" placeholder="e.g. user, order, csrf_token" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Comma-separated variables required by this template or class (e.g. <code>user, order, csrf_token</code>).</div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Dependencies (depends_on)</label>
                                    <input type="text" id="props-modal-depends-on" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.depends_on || '')}" placeholder="e.g. SPPDB, UserService" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Classes or services this component relies upon (e.g. <code>SPPDB, UserService, Mailer</code>).</div>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Associated Tests (tests)</label>
                                    <input type="text" id="props-modal-tests" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.tests || '')}" placeholder="e.g. tests/core/test.order.php" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Parikshak automated test suites covering this file (e.g. <code>tests/core/test.order.php</code>).</div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">Frontend Assets (assets)</label>
                                    <input type="text" id="props-modal-assets" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.assets || '')}" placeholder="e.g. sppadmin/js/chart.min.js" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Client JS/CSS assets required by this view (e.g. <code>sppadmin/js/chart.min.js</code>).</div>
                                </div>
                            </div>

                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                    <label style="font-size:0.8rem; font-weight:600; color:#c9d1d9;">Declarative HTTP Routes (JSON Array)</label>
                                    <button type="button" class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.insertPropsTemplate('props-modal-routes', 'routes')" style="font-size:0.7rem; padding:0.15rem 0.45rem; border:1px solid #30363d; border-radius:4px; color:#58a6ff; cursor:pointer;" title="Insert sample JSON route structure">📋 Insert Route Sample</button>
                                </div>
                                <textarea id="props-modal-routes" class="spp-find-input" rows="4" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.8rem; font-family:monospace; border-radius:6px; resize:vertical;" placeholder='[{"path": "/orders", "methods": ["GET"], "action": "OrderController@index"}]'>${this.escapeHtml(m.routes || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">JSON array of route mappings. Supported keys: <code>path</code> (URL), <code>methods</code> (e.g. <code>["GET", "POST"]</code>), <code>action</code> (e.g. <code>"OrderController@index"</code>), <code>middleware</code> (optional array of guards).</div>
                            </div>
                        ` : ''}

                        ${tab === 'governance' ? `
                            ${this.renderHelpCard('⚡', 'Enterprise Feature Flags, Canary A/B & SLO Budgets', 'Configure progressive feature rollouts, emergency kill-switches, distributed W3C tracing, and strict Service Level Objective (SLO) performance budgets enforced by Parikshak tests.', '• <strong>Canary Rollouts:</strong> Deterministic session hashing routes traffic between versions without code redeployments.<br>• <strong>SLO Budgets:</strong> Parikshak automated test suites fail CI if latency, memory, or N+1 queries exceed limits.')}

                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                    <label style="font-size:0.8rem; font-weight:600; color:#c9d1d9;">🚩 Feature Flag & Canary A/B Rollout (JSON)</label>
                                    <button type="button" class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.insertPropsTemplate('props-modal-feature-flag', 'feature_flag')" style="font-size:0.7rem; padding:0.15rem 0.45rem; border:1px solid #30363d; border-radius:4px; color:#58a6ff; cursor:pointer;" title="Insert sample canary config">📋 Insert Canary Sample</button>
                                </div>
                                <textarea id="props-modal-feature-flag" class="spp-find-input" rows="3" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.8rem; font-family:monospace; border-radius:6px; resize:vertical;" placeholder='{\n  "flag": "billing_v2_pilot",\n  "rollout_percentage": 25,\n  "target_roles": ["admin", "tester"],\n  "fallback_target": "LegacyBillingController.php",\n  "kill_switch": false\n}'>${this.escapeHtml(m.feature_flag || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Keys: <code>flag</code> (identifier), <code>rollout_percentage</code> (0-100), <code>target_roles</code> (array), <code>fallback_target</code> (fallback file), <code>kill_switch</code> (boolean emergency stop).</div>
                            </div>

                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                    <label style="font-size:0.8rem; font-weight:600; color:#c9d1d9;">⏱️ SLO Performance Budget (JSON)</label>
                                    <button type="button" class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.insertPropsTemplate('props-modal-slo-budget', 'slo_budget')" style="font-size:0.7rem; padding:0.15rem 0.45rem; border:1px solid #30363d; border-radius:4px; color:#58a6ff; cursor:pointer;" title="Insert sample SLO budget">📋 Insert SLO Sample</button>
                                </div>
                                <textarea id="props-modal-slo-budget" class="spp-find-input" rows="3" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.8rem; font-family:monospace; border-radius:6px; resize:vertical;" placeholder='{\n  "max_duration_ms": 250,\n  "max_memory_mb": 64,\n  "max_db_queries": 12,\n  "strict_ci": true\n}'>${this.escapeHtml(m.slo_budget || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Keys: <code>max_duration_ms</code> (max latency ms), <code>max_memory_mb</code> (RAM cap), <code>max_db_queries</code> (N+1 limit), <code>strict_ci</code> (fail CI on violation).</div>
                            </div>

                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                    <label style="font-size:0.8rem; font-weight:600; color:#c9d1d9;">📡 Distributed W3C Tracing & Telemetry (JSON)</label>
                                    <button type="button" class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.insertPropsTemplate('props-modal-telemetry', 'telemetry')" style="font-size:0.7rem; padding:0.15rem 0.45rem; border:1px solid #30363d; border-radius:4px; color:#58a6ff; cursor:pointer;" title="Insert sample telemetry config">📋 Insert Telemetry Sample</button>
                                </div>
                                <textarea id="props-modal-telemetry" class="spp-find-input" rows="3" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.8rem; font-family:monospace; border-radius:6px; resize:vertical;" placeholder='{\n  "span_name": "OrderProcessor.Execute",\n  "sample_rate": 1.0,\n  "baggage_propagation": ["tenant_id", "actor_id"]\n}'>${this.escapeHtml(m.telemetry || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Keys: <code>span_name</code> (trace operation name), <code>sample_rate</code> (0.0 to 1.0), <code>baggage_propagation</code> (array of headers carried across services).</div>
                            </div>
                        ` : ''}

                        ${tab === 'compliance' ? `
                            ${this.renderHelpCard('🔒', 'Real-time Event-Mesh & Institutional Regulatory Compliance', 'Connect components to the SPP Event-Mesh for asynchronous event handling, and configure long-term regulatory compliance, data retention, and audit logging.', '• <strong>Event-Mesh:</strong> Subscribes this service or job to domain events fired via <code>SPPEvent::fireEvent()</code> with automatic retries.<br>• <strong>Institutional Compliance:</strong> Enforces FERPA/GDPR retention schedules and automated deletion anonymization.')}

                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                    <label style="font-size:0.8rem; font-weight:600; color:#c9d1d9;">⚡ Event-Mesh Subscriptions (JSON)</label>
                                    <button type="button" class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.insertPropsTemplate('props-modal-subscribes', 'subscribes')" style="font-size:0.7rem; padding:0.15rem 0.45rem; border:1px solid #30363d; border-radius:4px; color:#58a6ff; cursor:pointer;" title="Insert sample event subscription">📋 Insert Event Subscription Sample</button>
                                </div>
                                <textarea id="props-modal-subscribes" class="spp-find-input" rows="3" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.8rem; font-family:monospace; border-radius:6px; resize:vertical;" placeholder='{\n  "event": "invoice.settled",\n  "queue": "finance_high_priority",\n  "debounce_seconds": 10,\n  "dead_letter_after_retries": 3\n}'>${this.escapeHtml(m.subscribes || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Keys: <code>event</code> (domain event to listen for), <code>queue</code> (worker queue name), <code>debounce_seconds</code> (batching delay), <code>dead_letter_after_retries</code> (retry limit).</div>
                            </div>

                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                    <label style="font-size:0.8rem; font-weight:600; color:#c9d1d9;">🛡️ Institutional Compliance & PII (JSON)</label>
                                    <button type="button" class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.insertPropsTemplate('props-modal-compliance', 'compliance')" style="font-size:0.7rem; padding:0.15rem 0.45rem; border:1px solid #30363d; border-radius:4px; color:#58a6ff; cursor:pointer;" title="Insert sample compliance rules">📋 Insert Compliance Sample</button>
                                </div>
                                <textarea id="props-modal-compliance" class="spp-find-input" rows="3" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.8rem; font-family:monospace; border-radius:6px; resize:vertical;" placeholder='{\n  "data_classification": "PII_CONFIDENTIAL",\n  "retention_period_days": 730,\n  "anonymize_on_delete": true,\n  "audit_access": true\n}'>${this.escapeHtml(m.compliance || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Keys: <code>data_classification</code> (e.g. <code>PII_CONFIDENTIAL</code>), <code>retention_period_days</code> (days to retain before purge), <code>anonymize_on_delete</code> (boolean), <code>audit_access</code> (boolean).</div>
                            </div>
                        ` : ''}

                        ${tab === 'ai' ? `
                            ${this.renderHelpCard('🧠', 'AI Agent Architectural Guardrails & Mutation Boundaries', 'Defines strict domain constraints and boundaries for AI coding agents (Antigravity, Claude Code, Cursor, Copilot). SPP extracts these directives and injects them directly into AI system prompts to guarantee domain rules are never broken.', '• <strong>Mandatory Invariants:</strong> Injected as strict non-negotiable prompt instructions.<br>• <strong>Forbidden Scopes:</strong> Hard boundaries that automated AI agents are strictly blocked from editing (e.g. encryption keys, audit trails).')}

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                    🧠 AI Attention Priority / Rigor
                                </label>
                                <select id="props-modal-ai-importance" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px; background:#161b22; color:#c9d1d9;">
                                    <option value="standard" ${m.ai_directives_importance === 'standard' ? 'selected' : ''}>Standard Priority (Standard reasoning & verification)</option>
                                    <option value="high" ${m.ai_directives_importance === 'high' ? 'selected' : ''}>High Attention (Exhaustive verification, edge cases)</option>
                                    <option value="mission_critical" ${m.ai_directives_importance === 'mission_critical' ? 'selected' : ''}>🔥 Mission Critical (Strict pre-commit test gating & peer review)</option>
                                </select>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Controls prompt priority and automated verification rigor when AI agents refactor this file.</div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                    📜 Mandatory Domain Rules & Invariants (One per line)
                                </label>
                                <textarea id="props-modal-ai-rules" class="spp-find-input" rows="3" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.8rem; font-family:monospace; border-radius:6px; resize:vertical;" placeholder="Never calculate gross pay without applying tax deduction slab.&#10;All currency operations must use BCMath with scale 4.">${this.escapeHtml(m.ai_directives_rules || '')}</textarea>
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">One rule per line. Strict business rules AI agents must respect (e.g. <code>All monetary math must use BCMath scale 4</code>, <code>Always trigger workflow after_transition hook</code>).</div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                    🚫 Forbidden Mutation Scopes (Comma-separated)
                                </label>
                                <input type="text" id="props-modal-ai-forbidden" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.ai_directives_forbidden || '')}" placeholder="database_connection, audit_logging, crypto_keys" />
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Comma-separated keywords or subsystems AI agents are strictly forbidden from modifying (e.g. <code>crypto_keys, audit_log, password_hash</code>).</div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                    ✅ Permitted Mutation Scopes (Comma-separated)
                                </label>
                                <input type="text" id="props-modal-ai-allowed" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.ai_directives_allowed || '')}" placeholder="tax_rules, validation_rules, formatting" />
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Comma-separated scopes where AI agents have full latitude to optimize (e.g. <code>validation_rules, formatters, query_filters</code>).</div>
                            </div>
                        ` : ''}

                        ${tab === 'sovereignty' ? `
                            ${this.renderHelpCard('🛡️', 'Data Sovereignty, PII Protection & Smart Invalidation', 'Governs institutional data protection (FERPA, GDPR, HIPAA), automated field masking in logs, geographic export boundaries, and automated cache purging when related entities change.', '• <strong>Auto-Masked Fields:</strong> Obscured with asterisks in all logs, debug dumps, and telemetry.<br>• <strong>Invalidation Rules:</strong> Automatically purges cached fragments when designated models or events fire.')}

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                        🛡️ Data Sovereignty Classification
                                    </label>
                                    <select id="props-modal-data-classification" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px; background:#161b22; color:#c9d1d9;">
                                        <option value="PUBLIC" ${m.data_classification === 'PUBLIC' ? 'selected' : ''}>PUBLIC (Freely accessible, no privacy limits)</option>
                                        <option value="INTERNAL" ${m.data_classification === 'INTERNAL' ? 'selected' : ''}>INTERNAL (Institutional internal data, default)</option>
                                        <option value="CONFIDENTIAL" ${m.data_classification === 'CONFIDENTIAL' ? 'selected' : ''}>CONFIDENTIAL (Sensitive business data, role-gated)</option>
                                        <option value="RESTRICTED_PII" ${m.data_classification === 'RESTRICTED_PII' ? 'selected' : ''}>🔒 RESTRICTED_PII (Strict FERPA/GDPR Personally Identifiable Info)</option>
                                    </select>
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Sensitivity classification governing data export, logging, and audit retention.</div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                        ⚡ Cache TTL (Seconds)
                                    </label>
                                    <input type="number" id="props-modal-cache-ttl" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.cache_ttl || '')}" placeholder="3600" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Microcache lifespan in seconds (e.g. <code>3600</code> for 1 hr, <code>86400</code> for 1 day). Set <code>0</code> to disable caching.</div>
                                </div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                    🎭 Auto-Masked PII Fields (Comma-separated)
                                </label>
                                <input type="text" id="props-modal-data-mask-fields" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.data_mask_fields || '')}" placeholder="guardian_contact, national_id, blood_group, bank_account" />
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Comma-separated fields (e.g. <code>guardian_contact, national_id, bank_account</code>) automatically redacted with asterisks in all logs and error traces.</div>
                            </div>

                            <div>
                                <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                    🌍 Export Restricted Geographic Regions (Comma-separated)
                                </label>
                                <input type="text" id="props-modal-data-regions" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.data_regions || '')}" placeholder="US, EU, CN" />
                                <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Comma-separated ISO 3166 country codes (e.g. <code>US, EU, IN</code>) restricted from cross-border data replication.</div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                        ⚡ Invalidation Entities (Comma-separated)
                                    </label>
                                    <input type="text" id="props-modal-cache-entities" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.cache_entities || '')}" placeholder="SchoolFee, FeeDiscount" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Models that automatically purge this cached component on save or delete (e.g. <code>Student, FeePayment</code>).</div>
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.25rem;">
                                        ⚡ Invalidation Events (Comma-separated)
                                    </label>
                                    <input type="text" id="props-modal-cache-events" class="spp-find-input" style="width:100%; box-sizing:border-box; padding:0.45rem 0.65rem; font-size:0.85rem; border-radius:6px;" value="${this.escapeHtml(m.cache_events || '')}" placeholder="fee.payment_received" />
                                    <div style="font-size:0.72rem; color:#8b949e; margin-top:0.25rem;">Domain events (SPPEvent) that automatically trigger cache invalidation (e.g. <code>fee.payment_received</code>).</div>
                                </div>
                            </div>
                        ` : ''}
                    </div>

                    <div style="padding:0.9rem 1.25rem; border-top:1px solid #21262d; background:#161b22; display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.72rem; color:#8b949e;">
                            <span>Manifest OS Enterprise v2</span>
                            <span>•</span>
                            <span>💡 Press <kbd style="background:#21262d; border:1px solid #30363d; border-radius:3px; padding:0.1rem 0.35rem; color:#c9d1d9; font-size:0.7rem; font-family:monospace;">Esc</kbd> to cancel</span>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.closeFilePropsModal()" title="Discard changes and close (Esc)">Cancel (Esc)</button>
                            <button class="btn primary-btn btn-sm" onclick="window.filesViewInstance.submitFilePropsModal()">
                                ${m.mode === 'create' ? 'Create with Manifest' : 'Save Properties'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderDirectoryManifestBanner() {
        if (this.state.collapseManifestBanner) {
            return '';
        }

        const dm = this.state.directoryManifest;
        if (this.state.loading && !dm) {
            return '';
        }

        const path = this.state.currentPath || '/';
        const hasManifest = Boolean(dm && (dm.has_manifest || dm.exists));
        const isValid = Boolean(dm && (dm.manifest_valid || dm.valid));
        const status = dm ? (dm.manifest_status || dm.status) : (hasManifest ? 'valid' : 'missing');
        const props = (dm && dm.directory_props) ? dm.directory_props : (dm?.directory || dm?.props?.directory || {});
        const fileCount = dm ? (dm.file_count_in_manifest !== undefined ? dm.file_count_in_manifest : (dm.files_count !== undefined ? dm.files_count : (dm.files ? Object.keys(dm.files).length : 0))) : 0;
        
        let badgeClass = 'suite-pill-success';
        let badgeLabel = '🟢 Manifest Active';
        if (!hasManifest) {
            badgeClass = 'suite-pill-warning';
            badgeLabel = '🟡 No Manifest Found';
        } else if (!isValid || status === 'corrupted') {
            badgeClass = 'suite-pill-danger';
            badgeLabel = '🔴 Manifest Corrupted / Invalid';
        }

        const desc = props.description || (hasManifest ? 'No description declared in directory manifest.' : 'This directory does not yet declare a per-directory __spp_file_props.yml contract.');
        const usage = props.usage || '';
        const author = props.author || '';
        const stability = props.stability || (hasManifest ? 'stable' : '');
        const reviewDate = props.review_date || '';
        const tags = Array.isArray(props.tags) ? props.tags : (props.attributes?.tags || []);
        const security = props.security || {};

        return `
            <div class="spp-directory-manifest-banner" style="margin-bottom:0.75rem; padding:0.75rem 1rem; background:rgba(15, 23, 42, 0.7); border:1px solid rgba(56, 189, 248, 0.22); border-radius:8px; backdrop-filter:blur(6px); display:flex; flex-direction:column; gap:0.6rem; position:relative;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.75rem;">
                    <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                        <span style="font-size:1.2rem;">📜</span>
                        <strong style="font-size:0.9rem; color:var(--text-bright);">__spp_file_props.yml</strong>
                        <span class="suite-pill ${badgeClass}" style="font-size:0.7rem; font-weight:700;">
                            ${badgeLabel}
                        </span>
                        ${stability ? `<span class="suite-pill ${stability === 'stable' ? 'suite-pill-success' : 'suite-pill-warning'}" style="font-size:0.68rem;">${this.escapeHtml(stability)}</span>` : ''}
                        <span style="font-size:0.75rem; color:var(--text-dim);">
                            ${fileCount} file(s) tracked
                        </span>
                    </div>
                    <!-- Banner Action Buttons -->
                    <div style="display:flex; gap:0.35rem; align-items:center; flex-wrap:wrap;">
                        <button class="btn secondary-btn btn-xs" onclick="window.filesViewInstance.editDirectoryProps()" title="Inspect & edit directory properties in structured inspector">
                            🏷️ Directory Props
                        </button>
                        <button class="btn secondary-btn btn-xs" onclick="window.filesViewInstance.openRawManifestModal()" title="Open raw YAML editor with live validation and diff preview">
                            📜 Raw YAML
                        </button>
                        <button class="btn secondary-btn btn-xs" onclick="window.filesViewInstance.openManifestHealthModal(null, false)" title="View Manifest OS Health dashboard">
                            🩺 Health
                        </button>
                        <button class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.healCurrentManifest()" title="Heal and synchronize untracked files into manifest">
                            ⚡ Auto-Sync
                        </button>
                        <button class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.signCurrentDirectory()" title="Cryptographically sign all files in this directory with SHA-256 integrity digests">
                            🔏 Sign
                        </button>
                        <button class="btn ghost-btn btn-xs" onclick="window.filesViewInstance.toggleManifestBanner(true)" title="Collapse this banner to save vertical space" style="color:var(--text-dim); margin-left:0.25rem;">
                            ✕
                        </button>
                    </div>
                </div>

                <div style="font-size:0.8rem; color:var(--text-bright); line-height:1.4;">
                    ${this.escapeHtml(desc)}
                </div>

                ${usage ? `
                    <div style="font-size:0.75rem; color:#38bdf8; font-family:monospace; background:rgba(0,0,0,0.3); padding:0.25rem 0.5rem; border-radius:4px; overflow-x:auto;">
                        💡 ${this.escapeHtml(usage)}
                    </div>
                ` : ''}

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; font-size:0.75rem; color:var(--text-dim);">
                    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                        ${author ? `<span>👤 <strong>Author:</strong> ${this.escapeHtml(author)}</span>` : ''}
                        ${reviewDate ? `<span>📅 <strong>Review:</strong> ${this.escapeHtml(reviewDate)}</span>` : ''}
                        ${tags.length > 0 ? `
                            <div style="display:inline-flex; gap:0.25rem; align-items:center;">
                                ${tags.map(t => `<span class="suite-pill suite-pill-neutral" style="font-size:0.65rem; padding:0.05rem 0.35rem;">#${this.escapeHtml(t)}</span>`).join('')}
                            </div>
                        ` : ''}
                    </div>
                    ${(security.allow_network !== undefined || security.allow_filesystem !== undefined) ? `
                        <div style="display:inline-flex; gap:0.3rem; align-items:center;">
                            <span style="font-size:0.7rem; color:var(--text-dim);">Capabilities:</span>
                            <span class="suite-pill ${security.allow_network ? 'suite-pill-success' : 'suite-pill-neutral'}" style="font-size:0.65rem;">Net: ${security.allow_network ? '✓' : '✗'}</span>
                            <span class="suite-pill ${security.allow_filesystem ? 'suite-pill-success' : 'suite-pill-neutral'}" style="font-size:0.65rem;">FS: ${security.allow_filesystem ? '✓' : '✗'}</span>
                            <span class="suite-pill ${security.allow_shell ? 'suite-pill-danger' : 'suite-pill-neutral'}" style="font-size:0.65rem;">Shell: ${security.allow_shell ? '✓' : '✗'}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    async openRawManifestModal() {
        const path = this.state.currentPath || '';
        try {
            const res = await this.admin.api('storage_get_manifest_raw', { path });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({
                rawManifestModal: {
                    path: path,
                    manifestPath: data.manifest_path || `${path}/__spp_file_props.yml`,
                    yaml: data.yaml || '',
                    originalYaml: data.yaml || '',
                    validation: null,
                    diff: null,
                    saving: false
                }
            });
            this.update();
        } catch (e) {
            this.admin.notify("Failed to load raw manifest: " + e.message, "error");
        }
    }

    closeRawManifestModal() {
        this.setState({ rawManifestModal: null });
        this.update();
    }

    async validateRawManifest() {
        const m = this.state.rawManifestModal;
        if (!m) return;
        const ta = document.getElementById('spp-raw-manifest-textarea');
        const yaml = ta ? ta.value : m.yaml;
        m.yaml = yaml;
        try {
            const res = await this.admin.api('storage_validate_manifest_yaml', { yaml });
            const data = (res.success && res.data) ? res.data : res;
            m.validation = data;
            m.diff = null;
            this.update();
        } catch (e) {
            this.admin.notify("Validation error: " + e.message, "error");
        }
    }

    async diffRawManifest() {
        const m = this.state.rawManifestModal;
        if (!m) return;
        const ta = document.getElementById('spp-raw-manifest-textarea');
        const yaml = ta ? ta.value : m.yaml;
        m.yaml = yaml;
        try {
            const res = await this.admin.api('storage_diff_manifest', { path: m.path, yaml });
            const data = (res.success && res.data) ? res.data : res;
            m.diff = data.diff || 'No differences.';
            this.update();
        } catch (e) {
            this.admin.notify("Diff error: " + e.message, "error");
        }
    }

    async saveRawManifest() {
        const m = this.state.rawManifestModal;
        if (!m) return;
        const ta = document.getElementById('spp-raw-manifest-textarea');
        const yaml = ta ? ta.value : m.yaml;
        m.yaml = yaml;
        m.saving = true;
        this.update();
        try {
            const res = await this.admin.api('storage_save_manifest_raw', { path: m.path, yaml });
            if (res.success) {
                this.admin.notify("Manifest saved atomically with backup (.bak).", "success");
                this.closeRawManifestModal();
                await this.loadDirectory(this.state.currentPath);
            } else {
                this.admin.notify(res.error || "Failed to save manifest.", "error");
                m.saving = false;
                m.validation = { valid: false, error: res.error };
                this.update();
            }
        } catch (e) {
            this.admin.notify("Save error: " + e.message, "error");
            m.saving = false;
            this.update();
        }
    }

    renderRawManifestModal() {
        const m = this.state.rawManifestModal;
        if (!m) return '';

        return `
            <div class="spp-shortcuts-modal-overlay" style="position:fixed; inset:0; z-index:999999; background:rgba(2, 6, 23, 0.8); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;" onclick="if(event.target === this) window.filesViewInstance.closeRawManifestModal();">
                <div class="spp-shortcuts-modal-window" style="width:100%; max-width:920px; background:#0d1117; border:1px solid #30363d; border-radius:10px; box-shadow:0 25px 60px rgba(0,0,0,0.85); overflow:hidden; display:flex; flex-direction:column; max-height:90vh;" onclick="event.stopPropagation();">
                    <div class="spp-shortcuts-modal-header" style="padding:0.9rem 1.25rem; border-bottom:1px solid #21262d; display:flex; justify-content:space-between; align-items:center; background:#161b22;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.15rem;">📜</span>
                            <div>
                                <h4 style="margin:0; font-size:0.95rem; color:#f0f6fc; font-weight:600;">__spp_file_props.yml — Raw Manifest Editor</h4>
                                <div style="font-size:0.75rem; color:#8b949e; font-family:monospace;">${this.escapeHtml(m.manifestPath)}</div>
                            </div>
                        </div>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeRawManifestModal()" title="Close (Esc)" style="border:none; color:#8b949e; font-size:1rem; cursor:pointer;">✕</button>
                    </div>

                    <div style="padding:1rem 1.25rem; flex:1; display:flex; flex-direction:column; gap:0.75rem; overflow-y:auto;">
                        <div style="font-size:0.78rem; color:#8b949e;">
                            Edit the raw YAML representation of this directory's properties manifest. Modifications are validated before write and committed atomically with automatic <code>.bak</code> rollback safety.
                        </div>

                        ${m.validation ? `
                            <div style="padding:0.6rem 0.85rem; border-radius:6px; font-size:0.8rem; ${m.validation.valid ? 'background:rgba(46,160,67,0.15); border:1px solid rgba(46,160,67,0.4); color:#3fb950;' : 'background:rgba(248,81,73,0.15); border:1px solid rgba(248,81,73,0.4); color:#f85149;'}">
                                ${m.validation.valid ? '✓ YAML syntax valid.' : `⚠️ Validation Error: ${this.escapeHtml(m.validation.error || 'Invalid YAML')}`}
                            </div>
                        ` : ''}

                        ${m.diff ? `
                            <div style="padding:0.6rem 0.85rem; border-radius:6px; background:#161b22; border:1px solid #30363d; font-size:0.75rem; font-family:monospace; max-height:160px; overflow-y:auto; white-space:pre-wrap; color:#c9d1d9;">
                                <strong>Diff Preview against Disk:</strong>\n${this.escapeHtml(m.diff)}
                            </div>
                        ` : ''}

                        <textarea id="spp-raw-manifest-textarea" rows="18" style="width:100%; box-sizing:border-box; font-family:monospace; font-size:0.85rem; line-height:1.45; background:#040d21; border:1px solid #30363d; border-radius:6px; color:#58a6ff; padding:0.75rem; tab-size:2; resize:vertical;" placeholder="# __spp_file_props.yml">${this.escapeHtml(m.yaml)}</textarea>
                    </div>

                    <div style="padding:0.75rem 1.25rem; border-top:1px solid #21262d; background:#161b22; display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; gap:0.4rem;">
                            <button type="button" class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.validateRawManifest()">
                                🔍 Validate YAML
                            </button>
                            <button type="button" class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.diffRawManifest()">
                                👁️ Preview Diff
                            </button>
                        </div>
                        <div style="display:flex; gap:0.4rem;">
                            <button type="button" class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.closeRawManifestModal()">
                                Cancel
                            </button>
                            <button type="button" class="btn primary-btn btn-sm" onclick="window.filesViewInstance.saveRawManifest()" ${m.saving ? 'disabled' : ''}>
                                ${m.saving ? 'Saving...' : '💾 Save Atomically'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async openManifestHealthModal(scopePath = null, recursive = false) {
        const targetPath = scopePath !== null ? scopePath : (this.state.currentPath || '');
        this.setState({
            manifestHealthModal: {
                path: targetPath,
                recursive: recursive,
                loading: true,
                health: null
            }
        });
        this.update();
        try {
            const res = await this.admin.api('storage_manifest_health', { 
                path: targetPath, 
                recursive: recursive ? 1 : 0 
            });
            const data = (res.success && res.data) ? res.data : res;
            this.setState({
                manifestHealthModal: {
                    path: targetPath,
                    recursive: recursive,
                    loading: false,
                    health: data
                }
            });
            this.update();
        } catch (e) {
            this.admin.notify("Health check error: " + e.message, "error");
            this.closeManifestHealthModal();
        }
    }

    closeManifestHealthModal() {
        this.setState({ manifestHealthModal: null });
        this.update();
    }

    async healCurrentManifest() {
        const path = this.state.currentPath || '';
        try {
            const res = await this.admin.api('storage_heal_manifest', { path });
            if (res.success) {
                this.admin.notify(res.message || "Manifest healed and synchronized.", "success");
                await this.loadDirectory(this.state.currentPath);
                if (this.state.manifestHealthModal) {
                    await this.openManifestHealthModal(path, this.state.manifestHealthModal.recursive || false);
                }
            } else {
                this.admin.notify(res.error || "Heal failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Heal error: " + e.message, "error");
        }
    }

    async signCurrentDirectory() {
        const path = this.state.currentPath || '';
        try {
            const res = await this.admin.api('storage_sign_directory', { path, recursive: false });
            if (res.success) {
                this.admin.notify(`Signed ${res.signed_count || 0} file(s) with SHA-256 digests in manifest.`, "success");
                await this.loadDirectory(this.state.currentPath);
            } else {
                this.admin.notify(res.error || "Signing failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Signing error: " + e.message, "error");
        }
    }

    async verifyFileIntegrity(path) {
        try {
            const res = await this.admin.api('storage_verify_integrity', { path });
            const data = (res.success && res.data) ? res.data : res;
            if (data.status === 'verified') {
                this.admin.notify(`✓ Integrity Verified: SHA-256 matches manifest digest.`, "success");
            } else if (data.status === 'tampered') {
                this.admin.notify(`⚠️ Tamper Detected: Expected ${data.expected?.substring(0, 16)}... got ${data.computed?.substring(0, 16)}...`, "error");
            } else {
                this.admin.notify(`Integrity status: ${data.status} (${data.message || ''})`, "info");
            }
            if (this.state.filePropsModal) {
                this.state.filePropsModal.integrity_status = data.status;
                this.state.filePropsModal.checksum = data.computed || data.expected;
                this.update();
            }
        } catch (e) {
            this.admin.notify("Verify error: " + e.message, "error");
        }
    }

    async signFile(path) {
        try {
            const res = await this.admin.api('storage_sign_file', { path });
            if (res.success) {
                this.admin.notify(`File signed with SHA-256 digest: ${res.checksum?.substring(0, 16)}...`, "success");
                if (this.state.filePropsModal) {
                    this.state.filePropsModal.checksum = res.checksum;
                    this.state.filePropsModal.integrity_status = 'verified';
                    this.update();
                }
                await this.loadDirectory(this.state.currentPath);
            } else {
                this.admin.notify(res.error || "Signing failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Signing error: " + e.message, "error");
        }
    }

    renderManifestHealthModal() {
        const m = this.state.manifestHealthModal;
        if (!m) return '';

        const h = m.health || {};
        const score = h.score !== undefined ? h.score : (h.health_score !== undefined ? h.health_score : 100);
        const scoreColor = score >= 90 ? '#2ea043' : (score >= 70 ? '#e3b341' : '#f85149');
        const isRecursive = Boolean(m.recursive);

        return `
            <div class="spp-shortcuts-modal-overlay" style="position:fixed; inset:0; z-index:999999; background:rgba(2, 6, 23, 0.8); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;" onclick="if(event.target === this) window.filesViewInstance.closeManifestHealthModal();">
                <div class="spp-shortcuts-modal-window" style="width:100%; max-width:880px; background:#0d1117; border:1px solid #30363d; border-radius:10px; box-shadow:0 25px 60px rgba(0,0,0,0.85); overflow:hidden; display:flex; flex-direction:column; max-height:90vh;" onclick="event.stopPropagation();">
                    <div class="spp-shortcuts-modal-header" style="padding:0.9rem 1.25rem; border-bottom:1px solid #21262d; display:flex; justify-content:space-between; align-items:center; background:#161b22;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.2rem;">🩺</span>
                            <h4 style="margin:0; font-size:0.95rem; color:#f0f6fc; font-weight:600;">Manifest OS Health & Integrity Dashboard</h4>
                        </div>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeManifestHealthModal()" title="Close (Esc)" style="border:none; color:#8b949e; font-size:1rem; cursor:pointer;">✕</button>
                    </div>

                    <div style="padding:1.25rem; flex:1; display:flex; flex-direction:column; gap:1rem; overflow-y:auto;">
                        ${m.loading ? `
                            <div style="padding:3rem; text-align:center;">
                                <div class="sppux-spinner"></div>
                                <div style="margin-top:0.75rem; color:var(--text-dim); font-size:0.85rem;">Evaluating manifests and file integrity...</div>
                            </div>
                        ` : h.error ? `
                            <div style="padding:1.25rem; background:rgba(248,81,73,0.1); border:1px solid rgba(248,81,73,0.4); border-radius:8px; color:#f85149; font-size:0.85rem;">
                                ⚠️ <strong>Health Evaluation Failed:</strong> ${this.escapeHtml(h.error || 'Unknown error occurred')}
                            </div>
                        ` : `
                            <!-- Overall Health Score Card -->
                            <div style="padding:1.25rem; background:rgba(0,0,0,0.3); border:1px solid #30363d; border-radius:8px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                                <div>
                                    <div style="font-size:0.8rem; color:#8b949e; text-transform:uppercase; letter-spacing:0.05em; font-weight:600;">Overall Manifest Health</div>
                                    <div style="font-size:1.8rem; font-weight:700; color:${scoreColor}; margin-top:0.2rem;">${score}%</div>
                                    <div style="font-size:0.75rem; color:#8b949e; margin-top:0.2rem;">
                                        Scope: <code>${this.escapeHtml(m.path || 'Workspace Root')}</code>
                                        <span class="badge" style="background:#21262d; color:#8b949e; font-size:0.65rem; margin-left:0.35rem; padding:1px 6px; border-radius:4px;">${isRecursive ? 'Recursive' : 'Directory'}</span>
                                    </div>
                                </div>
                                <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                    <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.openManifestHealthModal('${this.escapeHtml(this.state.currentPath || '')}', false)">
                                        🔍 Scan Directory
                                    </button>
                                    <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.openManifestHealthModal('', true)">
                                        🌐 Scan Workspace
                                    </button>
                                    <button class="btn primary-btn btn-sm" onclick="window.filesViewInstance.healCurrentManifest()">
                                        ⚡ Heal & Auto-Sync
                                    </button>
                                </div>
                            </div>

                            <!-- Metrics Grid -->
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:0.75rem;">
                                <div style="padding:0.75rem 1rem; background:#161b22; border:1px solid #30363d; border-radius:6px;">
                                    <div style="font-size:0.75rem; color:#8b949e;">Manifests Discovered</div>
                                    <div style="font-size:1.25rem; font-weight:700; color:#f0f6fc; margin-top:0.2rem;">${h.total_manifests || h.manifests_discovered || 0}</div>
                                </div>
                                <div style="padding:0.75rem 1rem; background:#161b22; border:1px solid #30363d; border-radius:6px;">
                                    <div style="font-size:0.75rem; color:#8b949e;">Valid Manifests</div>
                                    <div style="font-size:1.25rem; font-weight:700; color:#2ea043; margin-top:0.2rem;">${h.valid_manifests || 0}</div>
                                </div>
                                <div style="padding:0.75rem 1rem; background:#161b22; border:1px solid #30363d; border-radius:6px;">
                                    <div style="font-size:0.75rem; color:#8b949e;">Missing Manifests</div>
                                    <div style="font-size:1.25rem; font-weight:700; color:${(h.missing_manifests || 0) > 0 ? '#e3b341' : '#8b949e'}; margin-top:0.2rem;">${h.missing_manifests || 0}</div>
                                </div>
                                <div style="padding:0.75rem 1rem; background:#161b22; border:1px solid #30363d; border-radius:6px;">
                                    <div style="font-size:0.75rem; color:#8b949e;">Invalid YAML Files</div>
                                    <div style="font-size:1.25rem; font-weight:700; color:${(h.invalid_yaml || 0) > 0 ? '#f85149' : '#8b949e'}; margin-top:0.2rem;">${h.invalid_yaml || 0}</div>
                                </div>
                                <div style="padding:0.75rem 1rem; background:#161b22; border:1px solid #30363d; border-radius:6px;">
                                    <div style="font-size:0.75rem; color:#8b949e;">Untracked Files</div>
                                    <div style="font-size:1.25rem; font-weight:700; color:${(h.untracked_files || 0) > 0 ? '#e3b341' : '#8b949e'}; margin-top:0.2rem;">${h.untracked_files || 0}</div>
                                </div>
                                <div style="padding:0.75rem 1rem; background:#161b22; border:1px solid #30363d; border-radius:6px;">
                                    <div style="font-size:0.75rem; color:#8b949e;">Integrity Failures</div>
                                    <div style="font-size:1.25rem; font-weight:700; color:${(h.integrity_failures || h.integrity_tampered || 0) > 0 ? '#f85149' : '#2ea043'}; margin-top:0.2rem;">${h.integrity_failures || h.integrity_tampered || 0}</div>
                                </div>
                            </div>

                            <!-- Discovered Issues & Discrepancies -->
                            ${Array.isArray(h.details) && h.details.length > 0 ? `
                                <div style="margin-top:0.5rem;">
                                    <div style="font-size:0.8rem; font-weight:600; color:#c9d1d9; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                                        <span>Discovered Issues (${h.details.length}${h.total_issues && h.total_issues > h.details.length ? ` of ${h.total_issues}` : ''})</span>
                                        <span style="font-size:0.7rem; color:#8b949e;">Auto-resolvable with Heal & Auto-Sync</span>
                                    </div>
                                    <div style="max-height:220px; overflow-y:auto; border:1px solid #30363d; border-radius:6px; background:#0d1117;">
                                        ${h.details.slice(0, 50).map(d => {
                                            const sevColor = d.severity === 'critical' ? '#f85149' : (d.severity === 'warning' ? '#e3b341' : '#58a6ff');
                                            return `
                                                <div style="padding:0.5rem 0.75rem; border-bottom:1px solid #21262d; font-size:0.78rem; display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
                                                    <div style="display:flex; align-items:center; gap:0.5rem; overflow:hidden;">
                                                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${sevColor}; flex-shrink:0;"></span>
                                                        <span style="color:#e6edf3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${this.escapeHtml(d.message || d.type)}</span>
                                                    </div>
                                                    <span style="font-size:0.7rem; color:#8b949e; font-family:monospace; flex-shrink:0;">${this.escapeHtml(d.directory || '')}</span>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            ` : `
                                <div style="padding:0.75rem; background:rgba(46,160,67,0.1); border:1px solid rgba(46,160,67,0.3); border-radius:6px; color:#2ea043; font-size:0.8rem; display:flex; align-items:center; gap:0.5rem;">
                                    <span>✓</span> <span>All audited files and directories conform to Manifest OS contracts.</span>
                                </div>
                            `}
                        `}
                    </div>

                    <div style="padding:0.75rem 1.25rem; border-top:1px solid #21262d; background:#161b22; display:flex; justify-content:flex-end;">
                        <button type="button" class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.closeManifestHealthModal()">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderImpactModal() {
        const m = this.state.impactModal;
        if (!m) return '';

        const severityColors = {
            CRITICAL: '#f85149',
            HIGH: '#ea580c',
            MEDIUM: '#e3b341',
            LOW: '#2ea043'
        };
        const badgeColor = severityColors[m.severity] || '#58a6ff';

        return `
            <div class="spp-shortcuts-modal-overlay" style="position:fixed; inset:0; z-index:999999; background:rgba(2, 6, 23, 0.75); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;" onclick="if(event.target === this) window.filesViewInstance.closeImpactModal();">
                <div class="spp-shortcuts-modal-window" style="width:100%; max-width:760px; background:#0d1117; border:1px solid #30363d; border-radius:10px; box-shadow:0 25px 60px rgba(0,0,0,0.85); overflow:hidden;" onclick="event.stopPropagation();">
                    <div class="spp-shortcuts-modal-header" style="padding:0.9rem 1.25rem; border-bottom:1px solid #21262d; display:flex; justify-content:space-between; align-items:center; background:#161b22;">
                        <div style="display:flex; align-items:center; gap:0.6rem;">
                            <span style="font-size:1.2rem;">💥</span>
                            <div>
                                <h4 style="margin:0; font-size:0.95rem; color:#f0f6fc; font-weight:600;">Architectural Blast Radius</h4>
                                <span style="font-size:0.75rem; color:#8b949e; font-family:monospace;">${this.escapeHtml(m.target)}</span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="background:${badgeColor}22; border:1px solid ${badgeColor}66; color:${badgeColor}; font-size:0.75rem; padding:0.2rem 0.5rem; border-radius:4px; font-weight:700;">
                                ${m.severity} RISK (${m.risk_score}/100)
                            </span>
                            <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.closeImpactModal()" title="Close (Esc)" style="border:none; color:#8b949e; font-size:1rem; cursor:pointer;">✕</button>
                        </div>
                    </div>

                    <div style="padding:1.25rem; display:flex; flex-direction:column; gap:1rem; max-height:70vh; overflow-y:auto;">
                        <!-- Educational Help Card -->
                        ${this.renderHelpCard('💡', 'Understanding Architectural Blast Radius & Transitive Risk', 'The Blast Radius engine analyzes all transitive upstream and downstream dependencies declared across __spp_file_props.yml manifests. Reviewing this before modifying, refactoring, or deleting files prevents regressions across HTTP routes, queue jobs, and data models.', '• <strong>Risk Score:</strong> Evaluates dependency fan-out, critical security paths, and external route exposure (0-25 Low, 26-50 Medium, 51-75 High, 76-100 Critical).<br>• <strong>Recommended Tests:</strong> Parikshak test suites covering components within this blast radius. Run them before merging changes.')}

                        <!-- Metrics Summary Grid -->
                        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:0.6rem;">
                            <div style="background:#161b22; border:1px solid #30363d; border-radius:6px; padding:0.6rem 0.75rem; text-align:center;">
                                <div style="font-size:1.15rem; font-weight:700; color:#58a6ff;">${m.total_impacted_files}</div>
                                <div style="font-size:0.7rem; color:#8b949e; text-transform:uppercase; font-weight:600;">Impacted Files</div>
                                <div style="font-size:0.68rem; color:#6e7681; margin-top:0.15rem;">Transitive files</div>
                            </div>
                            <div style="background:#161b22; border:1px solid #30363d; border-radius:6px; padding:0.6rem 0.75rem; text-align:center;">
                                <div style="font-size:1.15rem; font-weight:700; color:#bc8cff;">${(m.impacted_routes || []).length}</div>
                                <div style="font-size:0.7rem; color:#8b949e; text-transform:uppercase; font-weight:600;">Web Routes</div>
                                <div style="font-size:0.68rem; color:#6e7681; margin-top:0.15rem;">Exposed endpoints</div>
                            </div>
                            <div style="background:#161b22; border:1px solid #30363d; border-radius:6px; padding:0.6rem 0.75rem; text-align:center;">
                                <div style="font-size:1.15rem; font-weight:700; color:#e3b341;">${(m.impacted_background_jobs || []).length}</div>
                                <div style="font-size:0.7rem; color:#8b949e; text-transform:uppercase; font-weight:600;">Queue Jobs</div>
                                <div style="font-size:0.68rem; color:#6e7681; margin-top:0.15rem;">Event workers</div>
                            </div>
                            <div style="background:#161b22; border:1px solid #30363d; border-radius:6px; padding:0.6rem 0.75rem; text-align:center;">
                                <div style="font-size:1.15rem; font-weight:700; color:#3fb950;">${(m.impacted_tests || []).length}</div>
                                <div style="font-size:0.7rem; color:#8b949e; text-transform:uppercase; font-weight:600;">Tests to Run</div>
                                <div style="font-size:0.68rem; color:#6e7681; margin-top:0.15rem;">Parikshak suites</div>
                            </div>
                        </div>

                        <!-- ASCII / Hierarchical Tree -->
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                                <div style="font-size:0.8rem; font-weight:600; color:#c9d1d9;">Transitive Dependency & Invalidation Tree</div>
                                <span style="font-size:0.7rem; color:#8b949e;">Arrows indicate downstream dependents</span>
                            </div>
                            <pre style="margin:0; background:#080b10; border:1px solid #21262d; border-radius:6px; padding:0.85rem; font-size:0.78rem; font-family:monospace; color:#8b949e; max-height:260px; overflow:auto; white-space:pre-wrap; line-height:1.4;">${this.escapeHtml(m.ascii_tree || 'No downstream dependents found.')}</pre>
                        </div>
                    </div>

                    <div style="padding:0.75rem 1.25rem; border-top:1px solid #21262d; background:#161b22; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.75rem; color:#8b949e;">Derived in real-time from __spp_file_props.yml manifests • Press <kbd style="background:#21262d; border:1px solid #30363d; border-radius:3px; padding:0.1rem 0.35rem; color:#c9d1d9; font-size:0.7rem; font-family:monospace;">Esc</kbd> to close</span>
                        <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.closeImpactModal()" title="Close (Esc)">Close (Esc)</button>
                    </div>
                </div>
            </div>
        `;
    }

    renderEditorModal() {
        return '';
    }

    renderShortcutsCheatsheet() {
        return `
            <div class="spp-shortcuts-modal-overlay" onclick="if(event.target === this) window.filesViewInstance.toggleShortcutsModal(false);">
                <div class="spp-shortcuts-modal-window" onclick="event.stopPropagation();">
                    <div class="spp-shortcuts-modal-header">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.25rem;">⌨️</span>
                            <h4 style="margin:0; font-size:1rem; color:var(--text-bright);">Code Editor Keyboard Shortcuts</h4>
                        </div>
                        <button class="btn ghost-btn btn-sm" onclick="window.filesViewInstance.toggleShortcutsModal(false)" title="Close (Esc)">✕</button>
                    </div>
                    <div class="spp-shortcuts-modal-body">
                        <div class="spp-shortcuts-group">
                            <h5>Editing & History</h5>
                            <div class="spp-shortcuts-grid">
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Save File</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+S</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Undo</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+Z</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Redo</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+Y</kbd> / <kbd class="spp-kbd">Ctrl+⇧+Z</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Toggle Line/Block Comment</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+/</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Indent Selection / Line</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Tab</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Outdent Selection / Line</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Shift+Tab</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Auto-close Brackets & Quotes</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">(</kbd> <kbd class="spp-kbd">{</kbd> <kbd class="spp-kbd">[</kbd> <kbd class="spp-kbd">"</kbd> <kbd class="spp-kbd">'</kbd> <kbd class="spp-kbd">\`</kbd></span></div>
                            </div>
                        </div>
                        <div class="spp-shortcuts-group">
                            <h5>Line Manipulation</h5>
                            <div class="spp-shortcuts-grid">
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Duplicate Line / Selection</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+D</kbd> / <kbd class="spp-kbd">Alt+⇧+↓</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Move Line Up</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Alt+↑</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Move Line Down</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Alt+↓</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Delete Current Line</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+Shift+K</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Clean Trailing Whitespace</span><span class="spp-shortcut-keys">Toolbar 🧹 Clean</span></div>
                            </div>
                        </div>
                        <div class="spp-shortcuts-group">
                            <h5>Search & Navigation</h5>
                            <div class="spp-shortcuts-grid">
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Find Text</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+F</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Find and Replace</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+H</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Go to Line</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Ctrl+G</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Next / Previous Match</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Enter</kbd> / <kbd class="spp-kbd">Shift+Enter</kbd></span></div>
                            </div>
                        </div>
                        <div class="spp-shortcuts-group">
                            <h5>IntelliSense, View & Popover</h5>
                            <div class="spp-shortcuts-grid">
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Navigate Autocomplete Hints</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">↑</kbd> / <kbd class="spp-kbd">↓</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Accept Selected Hint</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Tab</kbd> / <kbd class="spp-kbd">Enter</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Dismiss Hints / Close Popover</span><span class="spp-shortcut-keys"><kbd class="spp-kbd">Esc</kbd></span></div>
                                <div class="spp-shortcut-row"><span class="spp-shortcut-desc">Zoom In / Out</span><span class="spp-shortcut-keys">Toolbar <kbd class="spp-kbd">A-</kbd> / <kbd class="spp-kbd">A+</kbd></span></div>
                            </div>
                        </div>
                    </div>
                    <div style="padding:0.75rem 1.25rem; border-top:1px solid #21262d; background:#161b22; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.75rem; color:#8b949e;">💡 Press <kbd class="spp-kbd">Esc</kbd> anytime to dismiss this cheatsheet</span>
                        <button class="btn secondary-btn btn-sm" onclick="window.filesViewInstance.toggleShortcutsModal(false)" title="Close (Esc)">Close (Esc)</button>
                    </div>
                </div>
            </div>
        `;
    }

    setIndent(val) {
        if (val === 'tab') {
            this.editorOptions.isTab = true;
            this.editorOptions.indentUnit = '\t';
            this.editorOptions.indentSize = 4;
        } else {
            this.editorOptions.isTab = false;
            this.editorOptions.indentSize = parseInt(val, 10) || 4;
            this.editorOptions.indentUnit = ' '.repeat(this.editorOptions.indentSize);
        }
        if (typeof document !== 'undefined') {
            const textarea = document.getElementById('file-editor-textarea');
            if (textarea) {
                textarea.style.tabSize = this.editorOptions.indentSize;
            }
        }
    }

    toggleWrap() {
        this.editorOptions.wrap = !this.editorOptions.wrap;
        if (typeof document !== 'undefined') {
            const viewport = document.getElementById('spp-editor-viewport');
            const btn = document.getElementById('spp-editor-wrap-btn');
            if (viewport) {
                if (this.editorOptions.wrap) {
                    viewport.classList.add('wrap-mode');
                } else {
                    viewport.classList.remove('wrap-mode');
                }
            }
            if (btn) {
                btn.textContent = `⮐ Wrap: ${this.editorOptions.wrap ? 'On' : 'Off'}`;
                if (this.editorOptions.wrap) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            }
        }
    }

    toggleHints() {
        this.editorOptions.hints = !this.editorOptions.hints;
        if (typeof document !== 'undefined') {
            const btn = document.getElementById('spp-editor-hints-btn');
            if (btn) {
                btn.textContent = `💡 Hints: ${this.editorOptions.hints ? 'On' : 'Off'}`;
                if (this.editorOptions.hints) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            }
        }
        if (!this.editorOptions.hints) {
            this.hideCodeHints();
        }
    }

    toggleFindBar(forceState) {
        if (typeof forceState === 'boolean') {
            this.editorOptions.showFind = forceState;
        } else {
            this.editorOptions.showFind = !this.editorOptions.showFind;
        }

        if (typeof document === 'undefined') return;

        const bar = document.getElementById('spp-find-bar');
        const btn = document.getElementById('spp-editor-find-toggle-btn');
        const findInput = document.getElementById('spp-find-query');
        const textarea = document.getElementById('file-editor-textarea');

        if (bar) {
            bar.style.display = this.editorOptions.showFind ? 'flex' : 'none';
        }
        if (btn) {
            if (this.editorOptions.showFind) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }

        if (this.editorOptions.showFind) {
            if (findInput) {
                if (textarea && textarea.selectionStart !== textarea.selectionEnd) {
                    const sel = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd);
                    if (sel && !sel.includes('\n')) {
                        findInput.value = sel;
                        this.editorOptions.findQuery = sel;
                    }
                }
                findInput.focus();
                findInput.select();
                this.performFind(0, true);
                if (findInput) findInput.focus();
            }
        } else {
            this.syncFindHighlights();
            if (textarea) {
                textarea.focus();
            }
        }
    }

    performFind(step = 0, updateSelectionAndScroll = true) {
        if (typeof document === 'undefined') return;

        const findInput = document.getElementById('spp-find-query');
        const countEl = document.getElementById('spp-find-count');
        const textarea = document.getElementById('file-editor-textarea');
        if (!findInput || !textarea) return;

        const query = findInput.value;
        this.editorOptions.findQuery = query;

        if (!query) {
            this.editorOptions.findMatches = [];
            this.editorOptions.currentMatchIndex = -1;
            if (countEl) countEl.textContent = '0 matches';
            this.syncFindHighlights();
            return;
        }

        const val = textarea.value;
        const qLower = query.toLowerCase();
        const valLower = val.toLowerCase();
        const matches = [];
        let pos = 0;

        while ((pos = valLower.indexOf(qLower, pos)) !== -1) {
            matches.push({ start: pos, end: pos + query.length });
            pos += query.length;
        }

        this.editorOptions.findMatches = matches;

        if (matches.length === 0) {
            this.editorOptions.currentMatchIndex = -1;
            if (countEl) countEl.textContent = 'No matches';
            this.syncFindHighlights();
            return;
        }

        if (step !== 0 || this.editorOptions.currentMatchIndex === -1) {
            let nextIndex = this.editorOptions.currentMatchIndex + step;
            if (nextIndex >= matches.length) nextIndex = 0;
            if (nextIndex < 0) nextIndex = matches.length - 1;
            this.editorOptions.currentMatchIndex = nextIndex;
        } else if (this.editorOptions.currentMatchIndex >= matches.length) {
            this.editorOptions.currentMatchIndex = 0;
        }

        const match = matches[this.editorOptions.currentMatchIndex];
        if (countEl) {
            countEl.textContent = `${this.editorOptions.currentMatchIndex + 1} of ${matches.length}`;
        }

        // Render visual highlight markers across the entire editor
        this.syncFindHighlights();

        if (updateSelectionAndScroll && match) {
            const activeEl = document.activeElement;
            const replaceInput = document.getElementById('spp-replace-query');
            const shouldPreserveInputFocus = (activeEl === findInput || activeEl === replaceInput);

            // Update internal selection range without stealing DOM focus
            textarea.setSelectionRange(match.start, match.end);

            // Smoothly scroll to the matched line
            const linesBefore = val.substring(0, match.start).split('\n');
            const lineIdx = linesBefore.length - 1;
            const targetScroll = Math.max(0, lineIdx * 20 - 80);
            textarea.scrollTop = targetScroll;

            // Retain focus in input if user was in find or replace box
            if (shouldPreserveInputFocus && activeEl) {
                activeEl.focus();
            }
        }
    }

    syncFindHighlights() {
        if (typeof document === 'undefined') return;
        const layer = document.getElementById('editor-find-matches-layer');
        const textarea = document.getElementById('file-editor-textarea');
        if (!layer || !textarea) return;

        if (!this.editorOptions.showFind || !this.editorOptions.findQuery || !this.editorOptions.findMatches || this.editorOptions.findMatches.length === 0) {
            layer.innerHTML = '';
            return;
        }

        const val = textarea.value;
        const matches = this.editorOptions.findMatches;
        const activeIdx = this.editorOptions.currentMatchIndex;

        let html = '';
        let lastPos = 0;

        for (let i = 0; i < matches.length; i++) {
            const m = matches[i];
            if (m.start > lastPos) {
                html += this.escapeHtml(val.substring(lastPos, m.start));
            }
            const isActive = (i === activeIdx);
            const cls = isActive ? 'spp-find-marker active' : 'spp-find-marker';
            html += `<mark class="${cls}">${this.escapeHtml(val.substring(m.start, m.end))}</mark>`;
            lastPos = m.end;
        }

        if (lastPos < val.length) {
            html += this.escapeHtml(val.substring(lastPos));
        }
        if (val.endsWith('\n')) {
            html += ' ';
        }

        layer.innerHTML = html;
    }

    findNext() {
        this.performFind(1, true);
        const findInput = document.getElementById('spp-find-query');
        if (findInput && document.activeElement !== document.getElementById('spp-replace-query')) {
            findInput.focus();
        }
    }

    findPrev() {
        this.performFind(-1, true);
        const findInput = document.getElementById('spp-find-query');
        if (findInput && document.activeElement !== document.getElementById('spp-replace-query')) {
            findInput.focus();
        }
    }

    replaceCurrent() {
        if (typeof document === 'undefined') return;

        const replaceInput = document.getElementById('spp-replace-query');
        const textarea = document.getElementById('file-editor-textarea');
        const unsavedBadge = document.getElementById('editor-unsaved-badge');
        if (!replaceInput || !textarea) return;

        const replaceText = replaceInput.value;
        this.editorOptions.replaceQuery = replaceText;

        const matches = this.editorOptions.findMatches;
        const idx = this.editorOptions.currentMatchIndex;

        if (matches.length === 0 || idx < 0 || idx >= matches.length) {
            this.performFind(1, true);
            if (replaceInput) replaceInput.focus();
            return;
        }

        const match = matches[idx];
        const val = textarea.value;

        if (textarea.selectionStart === match.start && textarea.selectionEnd === match.end) {
            this.pushHistory(true);
            textarea.value = val.substring(0, match.start) + replaceText + val.substring(match.end);
            textarea.selectionStart = match.start;
            textarea.selectionEnd = match.start + replaceText.length;
            this.pushHistory(true);
            if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
            if (this.triggerHighlightSync) this.triggerHighlightSync();
            this.performFind(1, true);
        } else {
            this.performFind(1, true);
        }
        if (replaceInput) replaceInput.focus();
    }

    replaceAll() {
        if (typeof document === 'undefined') return;

        const findInput = document.getElementById('spp-find-query');
        const replaceInput = document.getElementById('spp-replace-query');
        const textarea = document.getElementById('file-editor-textarea');
        const unsavedBadge = document.getElementById('editor-unsaved-badge');
        if (!findInput || !replaceInput || !textarea) return;

        const query = findInput.value;
        const replaceText = replaceInput.value;
        if (!query) return;

        const val = textarea.value;
        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(escaped, 'gi');
        const matches = val.match(regex);
        const count = matches ? matches.length : 0;

        if (count === 0) {
            this.admin.notify("No matches found to replace.", "info");
            if (replaceInput) replaceInput.focus();
            return;
        }

        this.pushHistory(true);
        textarea.value = val.replace(regex, replaceText);
        this.pushHistory(true);
        if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
        if (this.triggerHighlightSync) this.triggerHighlightSync();
        this.admin.notify(`Replaced ${count} occurrences.`, "success");
        this.performFind(0, false);
        if (replaceInput) replaceInput.focus();
    }

    getLanguageSuggestions(lang) {
        const dictionaries = {
            php: [
                { text: 'function', type: 'keyword' }, { text: 'class', type: 'keyword' },
                { text: 'public', type: 'keyword' }, { text: 'private', type: 'keyword' },
                { text: 'protected', type: 'keyword' }, { text: 'static', type: 'keyword' },
                { text: 'return', type: 'keyword' }, { text: 'namespace', type: 'keyword' },
                { text: 'use', type: 'keyword' }, { text: 'extends', type: 'keyword' },
                { text: 'implements', type: 'keyword' }, { text: 'interface', type: 'keyword' },
                { text: 'trait', type: 'keyword' }, { text: 'try', type: 'keyword' },
                { text: 'catch', type: 'keyword' }, { text: 'finally', type: 'keyword' },
                { text: 'throw', type: 'keyword' }, { text: 'foreach', type: 'keyword' },
                { text: 'while', type: 'keyword' }, { text: 'match', type: 'keyword' },
                { text: 'array_map', type: 'function' }, { text: 'array_filter', type: 'function' },
                { text: 'array_merge', type: 'function' }, { text: 'in_array', type: 'function' },
                { text: 'json_encode', type: 'function' }, { text: 'json_decode', type: 'function' },
                { text: 'file_get_contents', type: 'function' }, { text: 'file_put_contents', type: 'function' },
                { text: 'isset', type: 'function' }, { text: 'empty', type: 'function' },
                { text: 'strlen', type: 'function' }, { text: 'str_replace', type: 'function' },
                { text: 'substr', type: 'function' }, { text: 'explode', type: 'function' },
                { text: 'implode', type: 'function' }, { text: '$this->renderPartial', type: 'snippet' },
                { text: '$this->renderStaticPartial', type: 'snippet' }, { text: '$this->stream', type: 'snippet' },
                { text: '$this->transitionEntity', type: 'snippet' }, { text: '\\SPP\\SPPEvent::fireEvent', type: 'snippet' },
                { text: '\\SPP\\SPPEvent::triggerHook', type: 'snippet' }, { text: '\\SPPMod\\SPPAI\\SPPAI::callTool', type: 'snippet' }
            ],
            javascript: [
                { text: 'const', type: 'keyword' }, { text: 'let', type: 'keyword' },
                { text: 'var', type: 'keyword' }, { text: 'function', type: 'keyword' },
                { text: 'return', type: 'keyword' }, { text: 'async', type: 'keyword' },
                { text: 'await', type: 'keyword' }, { text: 'import', type: 'keyword' },
                { text: 'export', type: 'keyword' }, { text: 'default', type: 'keyword' },
                { text: 'class', type: 'keyword' }, { text: 'extends', type: 'keyword' },
                { text: 'constructor', type: 'keyword' }, { text: 'try', type: 'keyword' },
                { text: 'catch', type: 'keyword' }, { text: 'finally', type: 'keyword' },
                { text: 'throw', type: 'keyword' }, { text: 'typeof', type: 'keyword' },
                { text: 'instanceof', type: 'keyword' }, { text: 'console.log', type: 'function' },
                { text: 'console.error', type: 'function' }, { text: 'console.warn', type: 'function' },
                { text: 'document.getElementById', type: 'function' }, { text: 'document.querySelector', type: 'function' },
                { text: 'addEventListener', type: 'function' }, { text: 'removeEventListener', type: 'function' },
                { text: 'setTimeout', type: 'function' }, { text: 'setInterval', type: 'function' },
                { text: 'JSON.stringify', type: 'function' }, { text: 'JSON.parse', type: 'function' },
                { text: 'Promise.all', type: 'function' }, { text: 'Promise.resolve', type: 'function' },
                { text: 'Object.keys', type: 'function' }, { text: 'Object.values', type: 'function' },
                { text: 'Object.entries', type: 'function' }, { text: 'Array.isArray', type: 'function' },
                { text: 'fetch', type: 'function' }, { text: 'Math.floor', type: 'function' }
            ],
            python: [
                { text: 'def', type: 'keyword' }, { text: 'class', type: 'keyword' },
                { text: 'import', type: 'keyword' }, { text: 'from', type: 'keyword' },
                { text: 'return', type: 'keyword' }, { text: 'if', type: 'keyword' },
                { text: 'elif', type: 'keyword' }, { text: 'else', type: 'keyword' },
                { text: 'for', type: 'keyword' }, { text: 'in', type: 'keyword' },
                { text: 'while', type: 'keyword' }, { text: 'try', type: 'keyword' },
                { text: 'except', type: 'keyword' }, { text: 'finally', type: 'keyword' },
                { text: 'raise', type: 'keyword' }, { text: 'with', type: 'keyword' },
                { text: 'pass', type: 'keyword' }, { text: 'lambda', type: 'keyword' },
                { text: 'yield', type: 'keyword' }, { text: 'async', type: 'keyword' },
                { text: 'await', type: 'keyword' }, { text: 'print', type: 'function' },
                { text: 'len', type: 'function' }, { text: 'range', type: 'function' },
                { text: 'enumerate', type: 'function' }, { text: 'zip', type: 'function' },
                { text: 'isinstance', type: 'function' }, { text: 'self', type: 'keyword' }
            ],
            go: [
                { text: 'func', type: 'keyword' }, { text: 'package', type: 'keyword' },
                { text: 'import', type: 'keyword' }, { text: 'return', type: 'keyword' },
                { text: 'struct', type: 'keyword' }, { text: 'interface', type: 'keyword' },
                { text: 'type', type: 'keyword' }, { text: 'var', type: 'keyword' },
                { text: 'const', type: 'keyword' }, { text: 'if', type: 'keyword' },
                { text: 'else', type: 'keyword' }, { text: 'for', type: 'keyword' },
                { text: 'range', type: 'keyword' }, { text: 'switch', type: 'keyword' },
                { text: 'case', type: 'keyword' }, { text: 'defer', type: 'keyword' },
                { text: 'go', type: 'keyword' }, { text: 'select', type: 'keyword' },
                { text: 'make', type: 'function' }, { text: 'append', type: 'function' },
                { text: 'fmt.Println', type: 'function' }, { text: 'fmt.Printf', type: 'function' }
            ],
            rust: [
                { text: 'fn', type: 'keyword' }, { text: 'let', type: 'keyword' },
                { text: 'mut', type: 'keyword' }, { text: 'pub', type: 'keyword' },
                { text: 'struct', type: 'keyword' }, { text: 'enum', type: 'keyword' },
                { text: 'impl', type: 'keyword' }, { text: 'trait', type: 'keyword' },
                { text: 'match', type: 'keyword' }, { text: 'return', type: 'keyword' },
                { text: 'use', type: 'keyword' }, { text: 'mod', type: 'keyword' },
                { text: 'println!', type: 'function' }, { text: 'format!', type: 'function' },
                { text: 'vec!', type: 'function' }, { text: 'Result', type: 'keyword' },
                { text: 'Option', type: 'keyword' }, { text: 'Some', type: 'function' },
                { text: 'None', type: 'keyword' }, { text: 'Ok', type: 'function' },
                { text: 'Err', type: 'function' }, { text: 'String::from', type: 'function' }
            ],
            sql: [
                { text: 'SELECT', type: 'keyword' }, { text: 'FROM', type: 'keyword' },
                { text: 'WHERE', type: 'keyword' }, { text: 'INSERT INTO', type: 'keyword' },
                { text: 'UPDATE', type: 'keyword' }, { text: 'DELETE', type: 'keyword' },
                { text: 'JOIN', type: 'keyword' }, { text: 'LEFT JOIN', type: 'keyword' },
                { text: 'INNER JOIN', type: 'keyword' }, { text: 'GROUP BY', type: 'keyword' },
                { text: 'ORDER BY', type: 'keyword' }, { text: 'LIMIT', type: 'keyword' },
                { text: 'HAVING', type: 'keyword' }, { text: 'COUNT', type: 'function' },
                { text: 'DISTINCT', type: 'keyword' }, { text: 'CREATE TABLE', type: 'keyword' },
                { text: 'PRIMARY KEY', type: 'keyword' }, { text: 'AUTO_INCREMENT', type: 'keyword' }
            ],
            css: [
                { text: 'display', type: 'property' }, { text: 'flex', type: 'keyword' },
                { text: 'grid', type: 'keyword' }, { text: 'position', type: 'property' },
                { text: 'relative', type: 'keyword' }, { text: 'absolute', type: 'keyword' },
                { text: 'background', type: 'property' }, { text: 'color', type: 'property' },
                { text: 'font-size', type: 'property' }, { text: 'font-weight', type: 'property' },
                { text: 'border', type: 'property' }, { text: 'border-radius', type: 'property' },
                { text: 'margin', type: 'property' }, { text: 'padding', type: 'property' },
                { text: 'align-items', type: 'property' }, { text: 'justify-content', type: 'property' },
                { text: 'gap', type: 'property' }, { text: 'box-shadow', type: 'property' }
            ],
            markup: [
                { text: 'div', type: 'tag' }, { text: 'span', type: 'tag' },
                { text: 'button', type: 'tag' }, { text: 'input', type: 'tag' },
                { text: 'form', type: 'tag' }, { text: 'table', type: 'tag' },
                { text: 'thead', type: 'tag' }, { text: 'tbody', type: 'tag' },
                { text: 'tr', type: 'tag' }, { text: 'th', type: 'tag' },
                { text: 'td', type: 'tag' }, { text: 'ul', type: 'tag' },
                { text: 'li', type: 'tag' }, { text: 'section', type: 'tag' },
                { text: 'class=""', type: 'property' }, { text: 'style=""', type: 'property' },
                { text: 'id=""', type: 'property' }
            ],
            bash: [
                { text: 'echo', type: 'function' }, { text: 'function', type: 'keyword' },
                { text: 'if', type: 'keyword' }, { text: 'then', type: 'keyword' },
                { text: 'else', type: 'keyword' }, { text: 'fi', type: 'keyword' },
                { text: 'for', type: 'keyword' }, { text: 'in', type: 'keyword' },
                { text: 'do', type: 'keyword' }, { text: 'done', type: 'keyword' },
                { text: 'export', type: 'keyword' }, { text: 'local', type: 'keyword' },
                { text: 'return', type: 'keyword' }, { text: 'exit', type: 'keyword' },
                { text: 'grep', type: 'function' }, { text: 'curl', type: 'function' }
            ]
        };

        return dictionaries[lang] || dictionaries.javascript;
    }

    extractFileSymbols(text, lang) {
        if (!text) return [];
        const symbols = new Map();

        const phpVars = text.match(/\$([a-zA-Z_]\w*)/g);
        if (phpVars) {
            phpVars.forEach(v => symbols.set(v, { text: v, type: 'variable' }));
        }

        const jsVars = text.match(/(?:const|let|var)\s+([a-zA-Z_]\w*)/g);
        if (jsVars) {
            jsVars.forEach(m => {
                const name = m.split(/\s+/)[1];
                if (name) symbols.set(name, { text: name, type: 'variable' });
            });
        }

        const funcMatches = text.match(/(?:function|def|fn|func)\s+([a-zA-Z_]\w*)/g);
        if (funcMatches) {
            funcMatches.forEach(m => {
                const name = m.split(/\s+/)[1];
                if (name) symbols.set(name, { text: name, type: 'function' });
            });
        }

        const classMatches = text.match(/(?:class|interface|trait|struct|enum)\s+([a-zA-Z_]\w*)/g);
        if (classMatches) {
            classMatches.forEach(m => {
                const name = m.split(/\s+/)[1];
                if (name) symbols.set(name, { text: name, type: 'class' });
            });
        }

        return Array.from(symbols.values());
    }

    showCodeHints(prefix, textarea, lang) {
        if (typeof document === 'undefined') return;
        if (!this.editorOptions.hints || !textarea) return;
        const hintsBox = document.getElementById('spp-code-hints');
        if (!hintsBox) return;

        const langDict = this.getLanguageSuggestions(lang);
        const fileSymbols = this.extractFileSymbols(textarea.value, lang);
        const allSuggestions = [...fileSymbols, ...langDict];

        const pLower = prefix.toLowerCase();
        const seen = new Set();
        const matched = [];

        for (const item of allSuggestions) {
            if (seen.has(item.text.toLowerCase())) continue;
            if (item.text.toLowerCase().startsWith(pLower) && item.text.toLowerCase() !== pLower) {
                seen.add(item.text.toLowerCase());
                matched.push(item);
                if (matched.length >= 8) break;
            }
        }

        if (matched.length === 0) {
            this.hideCodeHints();
            return;
        }

        this.editorOptions.activeHints = matched;
        this.editorOptions.activeHintIndex = 0;

        const textBefore = textarea.value.substring(0, textarea.selectionStart);
        const lines = textBefore.split('\n');
        const lineIdx = lines.length - 1;
        const colIdx = lines[lineIdx].length;

        const top = (lineIdx * 20) - textarea.scrollTop + 32;
        const left = Math.min(colIdx * 7.8 - textarea.scrollLeft + 16, Math.max(20, textarea.clientWidth - 290));

        hintsBox.style.top = Math.max(0, top) + 'px';
        hintsBox.style.left = Math.max(10, left) + 'px';

        this.renderCodeHintsList();
        hintsBox.style.display = 'block';
    }

    renderCodeHintsList() {
        if (typeof document === 'undefined') return;
        const hintsBox = document.getElementById('spp-code-hints');
        if (!hintsBox) return;

        const hints = this.editorOptions.activeHints;
        const selIdx = this.editorOptions.activeHintIndex;

        hintsBox.innerHTML = hints.map((h, i) => `
            <div class="spp-hint-item ${i === selIdx ? 'active' : ''}" 
                 data-index="${i}"
                 onmousedown="event.preventDefault(); window.filesViewInstance.insertHintByIndex(${i})">
                <span style="font-weight:600;">${this.escapeHtml(h.text)}</span>
                <span class="spp-hint-badge spp-hint-badge-${h.type || 'keyword'}">${h.type || 'word'}</span>
            </div>
        `).join('');

        const activeEl = hintsBox.querySelector('.spp-hint-item.active');
        if (activeEl) {
            activeEl.scrollIntoView({ block: 'nearest' });
        }
    }

    hideCodeHints() {
        if (typeof document === 'undefined') return;
        const hintsBox = document.getElementById('spp-code-hints');
        if (hintsBox) hintsBox.style.display = 'none';
        this.editorOptions.activeHints = [];
        this.editorOptions.activeHintIndex = 0;
    }

    insertHintByIndex(idx) {
        if (typeof document === 'undefined') return;
        const textarea = document.getElementById('file-editor-textarea');
        const unsavedBadge = document.getElementById('editor-unsaved-badge');
        if (!textarea) return;

        const hint = this.editorOptions.activeHints[idx];
        if (!hint) return;

        const textBefore = textarea.value.substring(0, textarea.selectionStart);
        const match = textBefore.match(/([a-zA-Z0-9_\$]+)$/);
        const prefixLen = match ? match[1].length : 0;

        const start = textarea.selectionStart - prefixLen;
        const end = textarea.selectionStart;
        const val = textarea.value;

        this.pushHistory(true);
        textarea.value = val.substring(0, start) + hint.text + val.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + hint.text.length;
        this.pushHistory(true);

        this.hideCodeHints();
        if (this.triggerHighlightSync) this.triggerHighlightSync();
        if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
        textarea.focus();
    }

    updateCursorPos() {
        if (typeof document === 'undefined') return;
        const posEl = document.getElementById('spp-editor-cursor-pos');
        const textarea = document.getElementById('file-editor-textarea');
        if (!posEl || !textarea) return;
        const textUpToCursor = textarea.value.substring(0, textarea.selectionStart);
        const lines = textUpToCursor.split('\n');
        const line = lines.length;
        const col = lines[lines.length - 1].length + 1;
        posEl.textContent = `Ln ${line}, Col ${col}`;
    }

    pushHistory(force = false) {
        if (typeof document === 'undefined') return;
        const textarea = document.getElementById('file-editor-textarea');
        if (!textarea) return;

        const val = textarea.value;
        const selStart = textarea.selectionStart;
        const selEnd = textarea.selectionEnd;
        const now = Date.now();

        if (this.history.length === 0) {
            this.history.push({ value: val, selStart, selEnd });
            this.historyIndex = 0;
            this.lastHistoryPush = now;
            this.updateUndoRedoButtons();
            return;
        }

        const currentSnapshot = this.history[this.historyIndex];
        if (currentSnapshot && currentSnapshot.value === val) {
            currentSnapshot.selStart = selStart;
            currentSnapshot.selEnd = selEnd;
            return;
        }

        if (!force && (now - this.lastHistoryPush < 600) && this.historyIndex >= 0) {
            this.history[this.historyIndex].value = val;
            this.history[this.historyIndex].selStart = selStart;
            this.history[this.historyIndex].selEnd = selEnd;
            this.updateUndoRedoButtons();
            return;
        }

        if (this.historyIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.historyIndex + 1);
        }

        if (this.history.length >= 200) {
            this.history.shift();
            this.historyIndex--;
        }

        this.history.push({ value: val, selStart, selEnd });
        this.historyIndex = this.history.length - 1;
        this.lastHistoryPush = now;
        this.updateUndoRedoButtons();
    }

    undo() {
        if (this.historyIndex <= 0) return;
        const textarea = document.getElementById('file-editor-textarea');
        if (!textarea) return;

        if (this.history[this.historyIndex] && textarea.value !== this.history[this.historyIndex].value) {
            this.pushHistory(true);
        }

        if (this.historyIndex > 0) {
            this.historyIndex--;
            const state = this.history[this.historyIndex];
            textarea.value = state.value;
            textarea.selectionStart = state.selStart;
            textarea.selectionEnd = state.selEnd;

            if (this.triggerHighlightSync) this.triggerHighlightSync();
            this.updateCursorPos();
            this.updateUndoRedoButtons();
            const unsavedBadge = document.getElementById('editor-unsaved-badge');
            if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
            textarea.focus();
        }
    }

    redo() {
        if (this.historyIndex >= this.history.length - 1) return;
        const textarea = document.getElementById('file-editor-textarea');
        if (!textarea) return;

        this.historyIndex++;
        const state = this.history[this.historyIndex];
        textarea.value = state.value;
        textarea.selectionStart = state.selStart;
        textarea.selectionEnd = state.selEnd;

        if (this.triggerHighlightSync) this.triggerHighlightSync();
        this.updateCursorPos();
        this.updateUndoRedoButtons();
        const unsavedBadge = document.getElementById('editor-unsaved-badge');
        if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
        textarea.focus();
    }

    updateUndoRedoButtons() {
        if (typeof document === 'undefined') return;
        const undoBtn = document.getElementById('spp-editor-undo-btn');
        const redoBtn = document.getElementById('spp-editor-redo-btn');

        const canUndo = this.historyIndex > 0;
        const canRedo = this.historyIndex >= 0 && this.historyIndex < this.history.length - 1;

        if (undoBtn) {
            undoBtn.disabled = !canUndo;
            if (!canUndo) undoBtn.classList.add('disabled');
            else undoBtn.classList.remove('disabled');
        }
        if (redoBtn) {
            redoBtn.disabled = !canRedo;
            if (!canRedo) redoBtn.classList.add('disabled');
            else redoBtn.classList.remove('disabled');
        }
    }

    getCommentDelimiter(ext = '', filename = '') {
        const lang = this.getLanguage(ext, filename);
        switch (lang) {
            case 'php':
            case 'javascript':
            case 'c':
            case 'cpp':
            case 'csharp':
            case 'rust':
            case 'go':
            case 'java':
            case 'kotlin':
            case 'swift':
            case 'dart':
                return { type: 'line', prefix: '// ' };
            case 'python':
            case 'ruby':
            case 'bash':
            case 'yaml':
            case 'docker':
            case 'toml':
            case 'r':
            case 'perl':
                return { type: 'line', prefix: '# ' };
            case 'sql':
            case 'lua':
                return { type: 'line', prefix: '-- ' };
            case 'markup':
                return { type: 'block', open: '<!-- ', close: ' -->' };
            case 'css':
                return { type: 'block', open: '/* ', close: ' */' };
            default:
                return { type: 'line', prefix: '// ' };
        }
    }

    toggleComment() {
        const textarea = document.getElementById('file-editor-textarea');
        if (!textarea) return;

        this.pushHistory(true);

        const val = textarea.value;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const fileName = this.state.activeFile ? this.state.activeFile.split('/').pop() : '';
        const delimiter = this.getCommentDelimiter(this.state.fileExt, fileName);

        const lineStart = val.lastIndexOf('\n', start - 1) + 1;
        let lineEnd = val.indexOf('\n', end);
        if (lineEnd === -1) lineEnd = val.length;

        const block = val.substring(lineStart, lineEnd);
        const lines = block.split('\n');

        if (delimiter.type === 'line') {
            const p = delimiter.prefix.trim();
            const nonBlank = lines.filter(l => l.trim().length > 0);
            const allCommented = nonBlank.length > 0 && nonBlank.every(l => l.trim().startsWith(p));

            let newLines;
            if (allCommented) {
                newLines = lines.map(l => {
                    const trimmed = l.trim();
                    if (!trimmed.startsWith(p)) return l;
                    const match = l.match(new RegExp('^(\\s*)' + p.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '\\s?'));
                    return match ? match[1] + l.substring(match[0].length) : l;
                });
            } else {
                newLines = lines.map(l => {
                    if (l.trim().length === 0) return l;
                    const match = l.match(/^(\s*)/);
                    const indent = match ? match[1] : '';
                    return indent + delimiter.prefix + l.substring(indent.length);
                });
            }

            const newBlock = newLines.join('\n');
            textarea.value = val.substring(0, lineStart) + newBlock + val.substring(lineEnd);
            textarea.selectionStart = lineStart;
            textarea.selectionEnd = lineStart + newBlock.length;
        } else {
            const open = delimiter.open;
            const close = delimiter.close;
            const sel = val.substring(start, end);
            if (start !== end && sel.startsWith(open.trim()) && sel.endsWith(close.trim())) {
                const unwrapped = sel.substring(open.length, sel.length - close.length);
                textarea.value = val.substring(0, start) + unwrapped + val.substring(end);
                textarea.selectionStart = start;
                textarea.selectionEnd = start + unwrapped.length;
            } else if (start !== end) {
                const wrapped = open + sel + close;
                textarea.value = val.substring(0, start) + wrapped + val.substring(end);
                textarea.selectionStart = start;
                textarea.selectionEnd = start + wrapped.length;
            } else {
                const trimmed = block.trim();
                if (trimmed.startsWith(open.trim()) && trimmed.endsWith(close.trim())) {
                    const unwrapped = trimmed.substring(open.length, trimmed.length - close.length);
                    textarea.value = val.substring(0, lineStart) + unwrapped + val.substring(lineEnd);
                    textarea.selectionStart = lineStart;
                    textarea.selectionEnd = lineStart + unwrapped.length;
                } else {
                    const wrapped = open + block + close;
                    textarea.value = val.substring(0, lineStart) + wrapped + val.substring(end);
                    textarea.selectionStart = lineStart;
                    textarea.selectionEnd = lineStart + wrapped.length;
                }
            }
        }

        this.pushHistory(true);
        if (this.triggerHighlightSync) this.triggerHighlightSync();
        this.updateCursorPos();
        const unsaved = document.getElementById('editor-unsaved-badge');
        if (unsaved) unsaved.style.display = 'inline-block';
        textarea.focus();
    }

    duplicateLine() {
        const textarea = document.getElementById('file-editor-textarea');
        if (!textarea) return;

        this.pushHistory(true);

        const val = textarea.value;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;

        if (start === end) {
            const lineStart = val.lastIndexOf('\n', start - 1) + 1;
            let lineEnd = val.indexOf('\n', start);
            if (lineEnd === -1) lineEnd = val.length;

            const curLine = val.substring(lineStart, lineEnd);
            textarea.value = val.substring(0, lineEnd) + '\n' + curLine + val.substring(lineEnd);
            textarea.selectionStart = textarea.selectionEnd = lineEnd + 1 + (start - lineStart);
        } else {
            const sel = val.substring(start, end);
            textarea.value = val.substring(0, end) + (sel.includes('\n') ? '\n' : '') + sel + val.substring(end);
            textarea.selectionStart = end + (sel.includes('\n') ? 1 : 0);
            textarea.selectionEnd = textarea.selectionStart + sel.length;
        }

        this.pushHistory(true);
        if (this.triggerHighlightSync) this.triggerHighlightSync();
        this.updateCursorPos();
        const unsaved = document.getElementById('editor-unsaved-badge');
        if (unsaved) unsaved.style.display = 'inline-block';
        textarea.focus();
    }

    moveLine(direction = 'up') {
        const textarea = document.getElementById('file-editor-textarea');
        if (!textarea) return;

        this.pushHistory(true);

        const val = textarea.value;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;

        const lineStart = val.lastIndexOf('\n', start - 1) + 1;
        let lineEnd = val.indexOf('\n', end);
        if (lineEnd === -1) lineEnd = val.length;

        const currentBlock = val.substring(lineStart, lineEnd);

        if (direction === 'up') {
            if (lineStart === 0) return;
            const prevLineStart = val.lastIndexOf('\n', lineStart - 2) + 1;
            const prevLine = val.substring(prevLineStart, lineStart - 1);

            textarea.value = val.substring(0, prevLineStart) + currentBlock + '\n' + prevLine + val.substring(lineEnd);
            const shift = prevLine.length + 1;
            textarea.selectionStart = start - shift;
            textarea.selectionEnd = end - shift;
        } else {
            if (lineEnd >= val.length) return;
            let nextLineEnd = val.indexOf('\n', lineEnd + 1);
            if (nextLineEnd === -1) nextLineEnd = val.length;
            const nextLine = val.substring(lineEnd + 1, nextLineEnd);

            textarea.value = val.substring(0, lineStart) + nextLine + '\n' + currentBlock + val.substring(nextLineEnd);
            const shift = nextLine.length + 1;
            textarea.selectionStart = start + shift;
            textarea.selectionEnd = end + shift;
        }

        this.pushHistory(true);
        if (this.triggerHighlightSync) this.triggerHighlightSync();
        this.updateCursorPos();
        const unsaved = document.getElementById('editor-unsaved-badge');
        if (unsaved) unsaved.style.display = 'inline-block';
        textarea.focus();
    }

    deleteLine() {
        const textarea = document.getElementById('file-editor-textarea');
        if (!textarea) return;

        this.pushHistory(true);

        const val = textarea.value;
        const start = textarea.selectionStart;

        const lineStart = val.lastIndexOf('\n', start - 1) + 1;
        let lineEnd = val.indexOf('\n', start);
        let nextStart = lineEnd === -1 ? val.length : lineEnd + 1;

        let delStart = lineStart;
        let delEnd = nextStart;
        if (lineEnd === -1 && lineStart > 0) {
            delStart = lineStart - 1;
            delEnd = val.length;
        }

        textarea.value = val.substring(0, delStart) + val.substring(delEnd);
        textarea.selectionStart = textarea.selectionEnd = Math.min(delStart, textarea.value.length);

        this.pushHistory(true);
        if (this.triggerHighlightSync) this.triggerHighlightSync();
        this.updateCursorPos();
        const unsaved = document.getElementById('editor-unsaved-badge');
        if (unsaved) unsaved.style.display = 'inline-block';
        textarea.focus();
    }

    cleanTrailingWhitespace() {
        const textarea = document.getElementById('file-editor-textarea');
        if (!textarea) return;

        const val = textarea.value;
        const cleaned = val
            .replace(/\r\n/g, '\n')
            .split('\n')
            .map(l => l.replace(/[\t ]+$/, ''))
            .join('\n')
            .replace(/\n*$/, '\n');

        if (val !== cleaned) {
            this.pushHistory(true);
            const start = textarea.selectionStart;
            textarea.value = cleaned;
            textarea.selectionStart = textarea.selectionEnd = Math.min(start, cleaned.length);
            this.pushHistory(true);
            if (this.triggerHighlightSync) this.triggerHighlightSync();
            this.updateCursorPos();
            const unsaved = document.getElementById('editor-unsaved-badge');
            if (unsaved) unsaved.style.display = 'inline-block';
            this.admin.notify("Trailing whitespace removed & trailing newline normalized.", "success");
        } else {
            this.admin.notify("File is already clean.", "info");
        }
    }

    toggleGotoBar(forceState) {
        if (typeof forceState === 'boolean') {
            this.editorOptions.showGoto = forceState;
        } else {
            this.editorOptions.showGoto = !this.editorOptions.showGoto;
        }

        if (typeof document === 'undefined') return;

        const bar = document.getElementById('spp-goto-bar');
        const btn = document.getElementById('spp-editor-goto-btn');
        const input = document.getElementById('spp-goto-input');
        const textarea = document.getElementById('file-editor-textarea');

        if (bar) bar.style.display = this.editorOptions.showGoto ? 'flex' : 'none';
        if (btn) {
            if (this.editorOptions.showGoto) btn.classList.add('active');
            else btn.classList.remove('active');
        }

        if (this.editorOptions.showGoto) {
            if (input) {
                if (textarea) {
                    const textUpToCursor = textarea.value.substring(0, textarea.selectionStart);
                    input.value = textUpToCursor.split('\n').length;
                }
                input.focus();
                input.select();
            }
        } else if (textarea) {
            textarea.focus();
        }
    }

    goToLineConfirm() {
        if (typeof document === 'undefined') return;
        const input = document.getElementById('spp-goto-input');
        const textarea = document.getElementById('file-editor-textarea');
        if (!input || !textarea) return;

        const target = parseInt(input.value, 10);
        if (!target || isNaN(target)) return;

        const lines = textarea.value.split('\n');
        const lineNum = Math.max(1, Math.min(lines.length, target));

        let charOffset = 0;
        for (let i = 0; i < lineNum - 1; i++) {
            charOffset += lines[i].length + 1;
        }

        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = charOffset;
        this.updateCursorPos();

        const lineHeight = 20;
        textarea.scrollTop = Math.max(0, (lineNum - 1) * lineHeight - (textarea.clientHeight / 2));

        this.toggleGotoBar(false);
    }

    zoomFontSize(delta) {
        if (delta === 0) this.editorOptions.fontSize = 13;
        else this.editorOptions.fontSize = Math.min(24, Math.max(10, (this.editorOptions.fontSize || 13) + delta));

        const viewport = document.getElementById('spp-editor-viewport');
        const gutter = document.getElementById('editor-gutter');
        const findMatchesLayer = document.getElementById('editor-find-matches-layer');
        const zoomDisplay = document.getElementById('spp-editor-font-size-val');

        if (viewport) {
            viewport.style.setProperty('--editor-font-size', `${this.editorOptions.fontSize}px`);
        }
        if (gutter) {
            gutter.style.setProperty('--editor-font-size', `${this.editorOptions.fontSize}px`);
        }
        if (findMatchesLayer) {
            findMatchesLayer.style.setProperty('--editor-font-size', `${this.editorOptions.fontSize}px`);
        }
        if (zoomDisplay) {
            zoomDisplay.textContent = `${this.editorOptions.fontSize}px`;
        }
    }

    toggleFullscreen() {
        this.editorOptions.fullscreen = !this.editorOptions.fullscreen;
        const card = document.querySelector('.spp-editor-card');
        const btn = document.getElementById('spp-editor-fullscreen-btn');
        if (card) {
            if (this.editorOptions.fullscreen) {
                card.classList.add('fullscreen-mode');
            } else {
                card.classList.remove('fullscreen-mode');
            }
        }
        if (btn) {
            btn.textContent = this.editorOptions.fullscreen ? '🗗 Exit' : '⛶ Fullscreen';
            btn.title = this.editorOptions.fullscreen ? 'Exit Fullscreen (Esc)' : 'Toggle Fullscreen Mode';
            if (this.editorOptions.fullscreen) btn.classList.add('active');
            else btn.classList.remove('active');
        }
    }

    setupCodeEditor() {
        if (!this.state.isEditing) return;

        const textarea = document.getElementById('file-editor-textarea');
        const highlightLayer = document.getElementById('editor-highlight-layer');
        const findMatchesLayer = document.getElementById('editor-find-matches-layer');
        const highlightCode = document.getElementById('editor-highlight-code');
        const gutter = document.getElementById('editor-gutter');
        const lineCountEl = document.getElementById('editor-line-count');
        const unsavedBadge = document.getElementById('editor-unsaved-badge');
        const viewport = document.getElementById('spp-editor-viewport');

        if (!textarea || !highlightCode) return;

        textarea.value = this.state.fileContent || '';
        textarea.style.tabSize = this.editorOptions.indentSize;

        if (this.history.length === 0) {
            this.history.push({
                value: textarea.value,
                selStart: 0,
                selEnd: 0
            });
            this.historyIndex = 0;
            this.lastHistoryPush = Date.now();
        }
        this.updateUndoRedoButtons();

        if (viewport) {
            viewport.style.setProperty('--editor-font-size', `${this.editorOptions.fontSize || 13}px`);
            if (this.editorOptions.wrap) {
                viewport.classList.add('wrap-mode');
            }
        }
        if (gutter) {
            gutter.style.setProperty('--editor-font-size', `${this.editorOptions.fontSize || 13}px`);
        }
        if (findMatchesLayer) {
            findMatchesLayer.style.setProperty('--editor-font-size', `${this.editorOptions.fontSize || 13}px`);
        }
        const card = document.querySelector('.spp-editor-card');
        if (card && this.editorOptions.fullscreen) {
            card.classList.add('fullscreen-mode');
        }

        const fileName = this.state.activeFile ? this.state.activeFile.split('/').pop() : '';
        const lang = this.getLanguage(this.state.fileExt, fileName);
        const prism = window.Prism || Prism;

        const updateCursorPos = () => this.updateCursorPos();

        const syncHighlight = () => {
            const text = textarea.value;
            const grammar = (prism && prism.languages && prism.languages[lang]) ? prism.languages[lang] : (prism && prism.languages ? prism.languages.plain : null);
            
            let highlighted = '';
            if (prism && grammar) {
                try {
                    highlighted = prism.highlight(text, grammar, lang);
                } catch (err) {
                    console.warn("Prism highlight error:", err);
                    highlighted = this.escapeHtml(text);
                }
            } else {
                highlighted = this.escapeHtml(text);
            }

            highlightCode.innerHTML = highlighted + (text.endsWith('\n') ? ' ' : '');

            const lines = text.split('\n');
            const count = lines.length;
            if (lineCountEl) {
                lineCountEl.textContent = `• ${count} ${count === 1 ? 'line' : 'lines'}`;
            }

            if (gutter) {
                let gutterText = '';
                for (let i = 1; i <= count; i++) {
                    gutterText += `<div>${i}</div>`;
                }
                gutter.innerHTML = gutterText;
            }
        };

        this.triggerHighlightSync = syncHighlight;

        // Initial render & cursor position
        syncHighlight();
        updateCursorPos();
        if (this.editorOptions.showFind && this.editorOptions.findQuery) {
            this.performFind(0, false);
        }

        // Scroll synchronization
        textarea.addEventListener('scroll', () => {
            if (highlightLayer) {
                highlightLayer.scrollTop = textarea.scrollTop;
                highlightLayer.scrollLeft = textarea.scrollLeft;
            }
            if (findMatchesLayer) {
                findMatchesLayer.scrollTop = textarea.scrollTop;
                findMatchesLayer.scrollLeft = textarea.scrollLeft;
            }
            if (gutter) {
                gutter.scrollTop = textarea.scrollTop;
            }
        });

        // Focus & Blur
        textarea.addEventListener('blur', () => {
            setTimeout(() => {
                const hintsBox = document.getElementById('spp-code-hints');
                if (hintsBox && !hintsBox.contains(document.activeElement)) {
                    this.hideCodeHints();
                }
            }, 150);
        });

        // Input event for real-time syntax coloring & hints
        let rafId = null;
        textarea.addEventListener('input', () => {
            if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
            this.pushHistory(false);
            updateCursorPos();
            if (rafId) cancelAnimationFrame(rafId);
            rafId = requestAnimationFrame(() => {
                syncHighlight();
                if (this.editorOptions.showFind && this.editorOptions.findQuery) {
                    this.performFind(0, false);
                }
            });

            if (this.editorOptions.hints) {
                const textBefore = textarea.value.substring(0, textarea.selectionStart);
                const match = textBefore.match(/([a-zA-Z0-9_\$]{2,})$/);
                if (match) {
                    this.showCodeHints(match[1], textarea, lang);
                } else {
                    this.hideCodeHints();
                }
            }
        });

        // Cursor & selection tracking
        textarea.addEventListener('click', () => {
            updateCursorPos();
            this.hideCodeHints();
        });

        textarea.addEventListener('keyup', (e) => {
            updateCursorPos();
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown'].includes(e.key)) {
                this.hideCodeHints();
            }
        });

        // Find and replace inputs wiring
        const findQueryInput = document.getElementById('spp-find-query');
        if (findQueryInput) {
            findQueryInput.addEventListener('input', () => this.performFind(0, true));
            findQueryInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (e.shiftKey) this.findPrev();
                    else this.findNext();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.toggleFindBar(false);
                }
            });
        }

        const replaceQueryInput = document.getElementById('spp-replace-query');
        if (replaceQueryInput) {
            replaceQueryInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.replaceCurrent();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.toggleFindBar(false);
                }
            });
        }

        // Go to line input wiring
        const gotoInput = document.getElementById('spp-goto-input');
        if (gotoInput) {
            gotoInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.goToLineConfirm();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.toggleGotoBar(false);
                }
            });
        }

        // Bracket pairs definition
        const bracketPairs = { '(': ')', '[': ']', '{': '}', '"': '"', "'": "'", '`': '`' };
        const closingBrackets = [')', ']', '}', '"', "'", '`'];

        // Keyboard shortcuts and indentation
        textarea.addEventListener('keydown', (e) => {
            // Ctrl+Z / Cmd+Z: Undo / Redo
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
                e.preventDefault();
                if (e.shiftKey) {
                    this.redo();
                } else {
                    this.undo();
                }
                return;
            }

            // Ctrl+Y / Cmd+Y: Redo
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') {
                e.preventDefault();
                this.redo();
                return;
            }

            // Ctrl+/ / Cmd+/: Toggle Line/Block Comment
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                this.toggleComment();
                return;
            }

            // Ctrl+D / Cmd+D: Duplicate Line or Selection
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
                e.preventDefault();
                this.duplicateLine();
                return;
            }

            // Alt+Shift+Down: Duplicate Line or Selection
            if (e.altKey && e.shiftKey && e.key === 'ArrowDown') {
                e.preventDefault();
                this.duplicateLine();
                return;
            }

            // Alt+ArrowUp: Move Line Up
            if (e.altKey && !e.shiftKey && e.key === 'ArrowUp') {
                e.preventDefault();
                this.moveLine('up');
                return;
            }

            // Alt+ArrowDown: Move Line Down
            if (e.altKey && !e.shiftKey && e.key === 'ArrowDown') {
                e.preventDefault();
                this.moveLine('down');
                return;
            }

            // Ctrl+Shift+K: Delete Line
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.deleteLine();
                return;
            }

            // Ctrl+G / Cmd+G: Go to Line
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'g') {
                e.preventDefault();
                this.toggleGotoBar(true);
                return;
            }

            // Ctrl+S / Cmd+S: Save file
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                this.saveFile();
                return;
            }

            // Ctrl+F / Cmd+F: Toggle Find Bar
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
                e.preventDefault();
                this.toggleFindBar(true);
                return;
            }

            // Ctrl+H / Cmd+H: Toggle Find Bar and Focus Replace
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'h') {
                e.preventDefault();
                this.toggleFindBar(true);
                const rInput = document.getElementById('spp-replace-query');
                if (rInput) {
                    rInput.focus();
                    rInput.select();
                }
                return;
            }

            // Code Hints Navigation
            if (this.editorOptions.activeHints && this.editorOptions.activeHints.length > 0) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.editorOptions.activeHintIndex = (this.editorOptions.activeHintIndex + 1) % this.editorOptions.activeHints.length;
                    this.renderCodeHintsList();
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.editorOptions.activeHintIndex = (this.editorOptions.activeHintIndex - 1 + this.editorOptions.activeHints.length) % this.editorOptions.activeHints.length;
                    this.renderCodeHintsList();
                    return;
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this.hideCodeHints();
                    return;
                }
            }

            // Escape key handling (shortcuts modal -> hints -> goto -> find -> fullscreen -> close editor popover)
            if (e.key === 'Escape') {
                if (this.state.showShortcutsModal) {
                    e.preventDefault();
                    this.toggleShortcutsModal(false);
                    return;
                }
                if (this.editorOptions.activeHints && this.editorOptions.activeHints.length > 0) {
                    e.preventDefault();
                    this.hideCodeHints();
                    return;
                }
                if (this.editorOptions.showGoto) {
                    e.preventDefault();
                    this.toggleGotoBar(false);
                    return;
                }
                if (this.editorOptions.showFind) {
                    e.preventDefault();
                    this.toggleFindBar(false);
                    return;
                }
                if (this.editorOptions.fullscreen) {
                    e.preventDefault();
                    this.toggleFullscreen();
                    return;
                }
                e.preventDefault();
                this.closeEditor();
                return;
            }

            // Tab Key: Accept hint OR block/line indent/outdent
            if (e.key === 'Tab') {
                e.preventDefault();
                if (this.editorOptions.activeHints && this.editorOptions.activeHints.length > 0) {
                    this.insertHintByIndex(this.editorOptions.activeHintIndex);
                    return;
                }

                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const val = textarea.value;
                const unit = this.editorOptions.indentUnit;
                const unitLen = unit.length;

                this.pushHistory(true);

                if (start !== end && val.substring(start, end).includes('\n')) {
                    // Multi-line block indent/outdent
                    const lineStart = val.lastIndexOf('\n', start - 1) + 1;
                    let lineEnd = val.indexOf('\n', end);
                    if (lineEnd === -1) lineEnd = val.length;

                    const block = val.substring(lineStart, lineEnd);
                    const lines = block.split('\n');

                    if (e.shiftKey) {
                        let removedChars = 0;
                        let firstLineRemoved = 0;
                        const newLines = lines.map((l, idx) => {
                            let rem = 0;
                            if (l.startsWith(unit)) {
                                rem = unitLen;
                            } else if (l.startsWith('\t')) {
                                rem = 1;
                            } else {
                                const spaceMatch = l.match(/^ +/);
                                if (spaceMatch) {
                                    rem = Math.min(spaceMatch[0].length, this.editorOptions.indentSize);
                                }
                            }
                            if (idx === 0) firstLineRemoved = rem;
                            removedChars += rem;
                            return l.substring(rem);
                        });
                        textarea.value = val.substring(0, lineStart) + newLines.join('\n') + val.substring(lineEnd);
                        textarea.selectionStart = Math.max(lineStart, start - firstLineRemoved);
                        textarea.selectionEnd = Math.max(lineStart, end - removedChars);
                    } else {
                        const newLines = lines.map(l => unit + l);
                        textarea.value = val.substring(0, lineStart) + newLines.join('\n') + val.substring(lineEnd);
                        textarea.selectionStart = start + unitLen;
                        textarea.selectionEnd = end + (unitLen * lines.length);
                    }
                } else {
                    if (e.shiftKey) {
                        const lineStart = val.lastIndexOf('\n', start - 1) + 1;
                        const curLine = val.substring(lineStart);
                        let rem = 0;
                        if (curLine.startsWith(unit)) rem = unitLen;
                        else if (curLine.startsWith('\t')) rem = 1;
                        else {
                            const spaceMatch = curLine.match(/^ +/);
                            if (spaceMatch) rem = Math.min(spaceMatch[0].length, this.editorOptions.indentSize);
                        }
                        if (rem > 0) {
                            textarea.value = val.substring(0, lineStart) + val.substring(lineStart + rem);
                            textarea.selectionStart = Math.max(lineStart, start - rem);
                            textarea.selectionEnd = Math.max(lineStart, end - rem);
                        }
                    } else {
                        textarea.value = val.substring(0, start) + unit + val.substring(end);
                        textarea.selectionStart = textarea.selectionEnd = start + unitLen;
                    }
                }
                this.pushHistory(true);
                syncHighlight();
                updateCursorPos();
                if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
                return;
            }

            // Enter Key: Accept hint OR auto-indent on new line
            if (e.key === 'Enter') {
                if (this.editorOptions.activeHints && this.editorOptions.activeHints.length > 0) {
                    e.preventDefault();
                    this.insertHintByIndex(this.editorOptions.activeHintIndex);
                    return;
                }

                e.preventDefault();
                const start = textarea.selectionStart;
                const val = textarea.value;
                const curLine = val.substring(val.lastIndexOf('\n', start - 1) + 1, start);
                const indentMatch = curLine.match(/^[\t ]*/);
                let indent = indentMatch ? indentMatch[0] : '';
                const trimmed = curLine.trim();
                const prevChar = val[start - 1];
                const nextChar = val[start];

                this.pushHistory(true);

                if ((prevChar === '{' && nextChar === '}') ||
                    (prevChar === '[' && nextChar === ']') ||
                    (prevChar === '(' && nextChar === ')')) {
                    const innerIndent = indent + this.editorOptions.indentUnit;
                    textarea.value = val.substring(0, start) + '\n' + innerIndent + '\n' + indent + val.substring(start);
                    textarea.selectionStart = textarea.selectionEnd = start + 1 + innerIndent.length;
                } else {
                    if (trimmed.endsWith('{') || trimmed.endsWith('[') || trimmed.endsWith('(') || trimmed.endsWith(':') || trimmed.endsWith('=>')) {
                        indent += this.editorOptions.indentUnit;
                    }
                    textarea.value = val.substring(0, start) + '\n' + indent + val.substring(start);
                    textarea.selectionStart = textarea.selectionEnd = start + 1 + indent.length;
                }
                this.pushHistory(true);
                syncHighlight();
                updateCursorPos();
                if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
                return;
            }

            // Bracket & Quote Auto-Closing and Selection Wrapping
            if (bracketPairs[e.key]) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const val = textarea.value;
                const openChar = e.key;
                const closeChar = bracketPairs[openChar];

                if (start !== end) {
                    e.preventDefault();
                    this.pushHistory(true);
                    const sel = val.substring(start, end);
                    textarea.value = val.substring(0, start) + openChar + sel + closeChar + val.substring(end);
                    textarea.selectionStart = start + 1;
                    textarea.selectionEnd = end + 1;
                    this.pushHistory(true);
                    syncHighlight();
                    updateCursorPos();
                    if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
                    return;
                } else {
                    if ((openChar === '"' || openChar === "'" || openChar === '`') && val[start] === openChar) {
                        e.preventDefault();
                        textarea.selectionStart = textarea.selectionEnd = start + 1;
                        updateCursorPos();
                        return;
                    }
                    e.preventDefault();
                    this.pushHistory(true);
                    textarea.value = val.substring(0, start) + openChar + closeChar + val.substring(start);
                    textarea.selectionStart = start + 1;
                    this.pushHistory(true);
                    syncHighlight();
                    updateCursorPos();
                    if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
                    return;
                }
            }

            // Step over closing brackets/quotes
            if (closingBrackets.includes(e.key)) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                if (start === end && textarea.value[start] === e.key) {
                    e.preventDefault();
                    textarea.selectionStart = textarea.selectionEnd = start + 1;
                    updateCursorPos();
                    return;
                }
            }

            // Smart Backspace for Empty Pairs
            if (e.key === 'Backspace') {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const val = textarea.value;
                if (start === end && start > 0) {
                    const prev = val[start - 1];
                    const next = val[start];
                    if ((prev === '(' && next === ')') ||
                        (prev === '[' && next === ']') ||
                        (prev === '{' && next === '}') ||
                        (prev === '"' && next === '"') ||
                        (prev === "'" && next === "'") ||
                        (prev === '`' && next === '`')) {
                        e.preventDefault();
                        this.pushHistory(true);
                        textarea.value = val.substring(0, start - 1) + val.substring(start + 1);
                        textarea.selectionStart = textarea.selectionEnd = start - 1;
                        this.pushHistory(true);
                        syncHighlight();
                        updateCursorPos();
                        if (unsavedBadge) unsavedBadge.style.display = 'inline-block';
                        return;
                    }
                }
            }
        });
    }

    async openTreemapModal() {
        const modal = document.getElementById('modal-container');
        if (!modal) return;

        document.getElementById('modal-title').innerHTML = `
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <span>📊</span>
                <span>Storage Treemap Analyzer</span>
                <span class="suite-pill suite-pill-primary" style="font-size:0.75rem;">${this.escapeHtml(this.state.currentPath || 'Root')}</span>
            </div>
        `;

        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div id="treemap-breadcrumbs" style="font-size:0.85rem; color:var(--text-dim);">
                        📁 Root
                    </div>
                    <button class="btn btn-sm btn-outline" id="treemap-btn-up" style="display:none;">⬆ Up One Folder</button>
                </div>
                <div style="position:relative; width:100%; height:450px; background:rgba(15, 23, 42, 0.6); border-radius:var(--radius-md); border:1px solid var(--glass-border); overflow:hidden;">
                    <canvas id="spp-treemap-canvas" style="width:100%; height:100%; display:block;"></canvas>
                    <div id="treemap-loading" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.4);"><div class="sppux-spinner"></div></div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; color:var(--text-dim);">
                    <span>Tip: Click on any folder to drill in. Color corresponds to file type and depth.</span>
                    <span id="treemap-total-info"></span>
                </div>
            </div>
        `;

        modal.classList.add('active');
        document.getElementById('modal-close').onclick = () => modal.classList.remove('active');
        const saveBtn = document.getElementById('modal-save');
        if (saveBtn) saveBtn.style.display = 'none';

        try {
            const res = await this.admin.api('storage_disk_treemap', { root: this.state.currentPath || '', depth: 3 });
            const data = (res.success && res.data) ? res.data : res;
            const treemap = data.treemap || res.treemap || (res.data && res.data.treemap);

            const loader = document.getElementById('treemap-loading');
            if (loader) loader.style.display = 'none';

            if (treemap) {
                const canvas = document.getElementById('spp-treemap-canvas');
                if (canvas) {
                    const renderer = new TreemapRenderer(canvas, {
                        onDrill: (node, hist) => {
                            const bc = document.getElementById('treemap-breadcrumbs');
                            if (bc) {
                                bc.innerHTML = hist.map(h => `📁 ${this.escapeHtml(h.name)}`).join(' / ');
                            }
                            const upBtn = document.getElementById('treemap-btn-up');
                            if (upBtn) {
                                upBtn.style.display = hist.length > 1 ? 'inline-block' : 'none';
                                upBtn.onclick = () => renderer.drillUp();
                            }
                        },
                        onSelect: (node) => {
                            if (node.path) {
                                modal.classList.remove('active');
                                this.openFile(node.path);
                            }
                        }
                    });
                    window._sppTreemapRenderer = renderer;
                    renderer.setData(treemap);

                    const totalEl = document.getElementById('treemap-total-info');
                    if (totalEl) {
                        totalEl.textContent = `Total Size: ${treemap.formatted_size || '0 B'}`;
                    }
                }
            } else {
                const totalEl = document.getElementById('treemap-total-info');
                if (totalEl) {
                    totalEl.innerHTML = `<span style="color:var(--danger);">${this.escapeHtml(res.error || res.message || 'Failed to calculate treemap')}</span>`;
                }
            }
        } catch (e) {
            const loader = document.getElementById('treemap-loading');
            if (loader) loader.style.display = 'none';
            this.admin.notify("Failed to calculate storage treemap: " + e.message, "error");
        }
    }

    async openWebToolsModal(activeTab = 'errors') {
        const modal = document.getElementById('modal-container');
        if (!modal) return;

        document.getElementById('modal-title').innerHTML = `
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <span>🌐</span>
                <span>Web Server Utilities & Controls</span>
            </div>
        `;

        document.getElementById('modal-body').innerHTML = `
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Subtabs -->
                <div class="suite-tabs" style="margin-bottom:0;">
                    <button class="suite-tab-btn ${activeTab === 'errors' ? 'active' : ''}" onclick="window.filesViewInstance.openWebToolsModal('errors')">🚨 Error Pages (404/500)</button>
                    <button class="suite-tab-btn ${activeTab === 'indexing' ? 'active' : ''}" onclick="window.filesViewInstance.openWebToolsModal('indexing')">📂 Directory Indexing</button>
                    <button class="suite-tab-btn ${activeTab === 'mime' ? 'active' : ''}" onclick="window.filesViewInstance.openWebToolsModal('mime')">📄 MIME Types</button>
                    <button class="suite-tab-btn ${activeTab === 'logs' ? 'active' : ''}" onclick="window.filesViewInstance.openWebToolsModal('logs')">📜 Access Logs</button>
                </div>

                <div id="web-tools-tab-content">
                    <div style="padding:2rem; text-align:center;"><div class="sppux-spinner"></div></div>
                </div>
            </div>
        `;

        modal.classList.add('active');
        document.getElementById('modal-close').onclick = () => modal.classList.remove('active');
        const saveBtn = document.getElementById('modal-save');
        if (saveBtn) saveBtn.style.display = 'none';

        const contentEl = document.getElementById('web-tools-tab-content');

        if (activeTab === 'errors') {
            await this.loadErrorPagesTab(contentEl);
        } else if (activeTab === 'indexing') {
            await this.loadIndexingTab(contentEl);
        } else if (activeTab === 'mime') {
            await this.loadMimeTab(contentEl);
        } else if (activeTab === 'logs') {
            await this.loadAccessLogsTab(contentEl);
        }
    }

    async loadErrorPagesTab(container) {
        try {
            const res = await this.admin.api('storage_get_error_pages');
            const data = (res.success && res.data) ? res.data : res;
            const rawPages = data.pages || res.pages || [];
            const fallbackPages = [
                { code: 400, title: 'Bad Request', exists: false, content: '' },
                { code: 401, title: 'Authorization Required', exists: false, content: '' },
                { code: 403, title: 'Forbidden / Access Denied', exists: false, content: '' },
                { code: 404, title: 'Page Not Found', exists: false, content: '' },
                { code: 500, title: 'Internal Server Error', exists: false, content: '' }
            ];
            const pages = (rawPages && rawPages.length > 0) ? rawPages : fallbackPages;

            container.innerHTML = `
                <div>
                    <p style="font-size:0.85rem; color:var(--text-dim); margin-top:0;">
                        Customize standard HTTP error responses. Saved templates are stored in <code>.spp/error_pages/</code> and registered via <code>ErrorDocument</code> in <code>.htaccess</code>.
                    </p>
                    <div style="display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap;">
                        ${pages.map((p, idx) => `
                            <button class="btn btn-sm ${idx === 0 ? 'primary-btn' : 'btn-outline'} error-code-tab-btn" 
                                    onclick="window.filesViewInstance.selectErrorCodeTab(${p.code})" id="err-btn-${p.code}">
                                ${p.code} (${p.title.split(' ')[0]})
                            </button>
                        `).join('')}
                    </div>

                    ${pages.map((p, idx) => `
                        <div id="err-editor-${p.code}" class="error-page-editor-pane" style="display:${idx === 0 ? 'block' : 'none'};">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                <strong>${p.code} - ${this.escapeHtml(p.title)}</strong>
                                <span class="suite-pill ${p.exists ? 'suite-pill-success' : 'suite-pill-neutral'}">${p.exists ? 'Custom Template Active' : 'Default Fallback'}</span>
                            </div>
                            <textarea id="err-textarea-${p.code}" style="width:100%; height:200px; font-family:monospace; font-size:0.85rem; padding:0.75rem; border-radius:var(--radius-sm); background:var(--input-bg); border:1px solid var(--glass-border); color:var(--text-bright); box-sizing:border-box;">${this.escapeHtml(p.content || `<!DOCTYPE html>\n<html>\n<head><title>${p.code} ${p.title}</title></head>\n<body>\n<h1>${p.code} - ${p.title}</h1>\n<p>The requested URL returned an error.</p>\n</body>\n</html>`)}</textarea>
                            <div style="margin-top:0.75rem; text-align:right;">
                                <button class="btn primary-btn btn-sm" onclick="window.filesViewInstance.saveCustomErrorPage(${p.code})">💾 Save Error Page ${p.code}</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (e) {
            container.innerHTML = `<div style="color:var(--danger);">Failed to load error pages: ${e.message}</div>`;
        }
    }

    selectErrorCodeTab(code) {
        document.querySelectorAll('.error-code-tab-btn').forEach(b => b.classList.replace('primary-btn', 'btn-outline'));
        const activeBtn = document.getElementById(`err-btn-${code}`);
        if (activeBtn) activeBtn.classList.replace('btn-outline', 'primary-btn');

        document.querySelectorAll('.error-page-editor-pane').forEach(p => p.style.display = 'none');
        const activePane = document.getElementById(`err-editor-${code}`);
        if (activePane) activePane.style.display = 'block';
    }

    async saveCustomErrorPage(code) {
        const textarea = document.getElementById(`err-textarea-${code}`);
        if (!textarea) return;
        const content = textarea.value;

        try {
            const res = await this.admin.api('storage_save_error_page', { code, content });
            if (res.success) {
                this.admin.notify(res.message || `Error page ${code} saved.`, "success");
            } else {
                this.admin.notify(res.error || "Save failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async loadIndexingTab(container) {
        try {
            const res = await this.admin.api('storage_get_indexing', { path: this.state.currentPath || '' });
            const data = (res.success && res.data) ? res.data : res;
            const isIndexed = (data.indexed !== undefined) ? data.indexed : (res.indexed || false);

            container.innerHTML = `
                <div style="max-width:550px;">
                    <h4 style="margin:0 0 0.5rem 0;">Directory Indexing (Options ±Indexes)</h4>
                    <p style="font-size:0.85rem; color:var(--text-dim);">
                        Controls whether Apache/Nginx generates a public file listing when an index file (e.g. index.php, index.html) is absent in <code>${this.escapeHtml(this.state.currentPath || 'Root')}</code>.
                    </p>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:1.25rem; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); border-radius:var(--radius-md); margin-top:1rem;">
                        <div>
                            <div style="font-weight:600; color:var(--text-bright);">Public Directory Listing</div>
                            <div style="font-size:0.8rem; color:${isIndexed ? '#34d399' : '#f87171'};">Current Mode: <strong>${isIndexed ? 'Enabled (+Indexes)' : 'Disabled (-Indexes / 403 Forbidden)'}</strong></div>
                        </div>
                        <button class="btn ${isIndexed ? 'btn-danger' : 'primary-btn'} btn-sm" onclick="window.filesViewInstance.toggleIndexingMode(${!isIndexed})">
                            ${isIndexed ? 'Disable Listing (-Indexes)' : 'Enable Listing (+Indexes)'}
                        </button>
                    </div>
                </div>
            `;
        } catch (e) {
            container.innerHTML = `<div style="color:var(--danger);">Error: ${e.message}</div>`;
        }
    }

    async toggleIndexingMode(enable) {
        try {
            const res = await this.admin.api('storage_toggle_indexing', { path: this.state.currentPath || '', enable });
            if (res.success) {
                this.admin.notify(res.message, "success");
                this.openWebToolsModal('indexing');
            } else {
                this.admin.notify(res.error || "Failed to update indexing.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async loadMimeTab(container) {
        try {
            const res = await this.admin.api('storage_list_mime_types');
            const data = (res.success && res.data) ? res.data : res;
            const types = data.types || res.types || [];

            container.innerHTML = `
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <div>
                            <h4 style="margin:0;">Custom MIME Type Mappings</h4>
                            <span style="font-size:0.8rem; color:var(--text-dim);">Maps file extensions to Content-Type headers via <code>AddType</code> in <code>.htaccess</code>.</span>
                        </div>
                    </div>

                    <!-- Add form -->
                    <div style="display:flex; gap:0.5rem; margin-bottom:1.25rem; background:rgba(0,0,0,0.2); padding:0.75rem; border-radius:var(--radius-sm); border:1px solid var(--glass-border);">
                        <input type="text" id="new-mime-ext" placeholder="Extension (e.g. wasm)" style="width:140px; padding:0.4rem; background:var(--input-bg); border:1px solid var(--glass-border); border-radius:var(--radius-sm); color:var(--text-bright); font-size:0.85rem;">
                        <input type="text" id="new-mime-type" placeholder="MIME Type (e.g. application/wasm)" style="flex:1; padding:0.4rem; background:var(--input-bg); border:1px solid var(--glass-border); border-radius:var(--radius-sm); color:var(--text-bright); font-size:0.85rem;">
                        <button class="btn primary-btn btn-sm" onclick="window.filesViewInstance.saveMimeType()">+ Add MIME</button>
                    </div>

                    <div class="suite-table-wrapper" style="max-height:260px; overflow-y:auto;">
                        <table class="suite-table" style="width:100%;">
                            <thead><tr><th>Extension</th><th>MIME Content-Type</th><th>Type</th><th style="text-align:right;">Action</th></tr></thead>
                            <tbody>
                                ${types.map(t => `
                                    <tr>
                                        <td><strong>.${this.escapeHtml(t.extension)}</strong></td>
                                        <td style="color:#38bdf8; font-family:monospace;">${this.escapeHtml(t.mime_type)}</td>
                                        <td><span class="suite-pill ${t.custom ? 'suite-pill-primary' : 'suite-pill-neutral'}">${t.custom ? 'Custom (.htaccess)' : 'Standard'}</span></td>
                                        <td style="text-align:right;">
                                            ${t.custom ? `<button class="btn btn-sm btn-outline text-danger" onclick="window.filesViewInstance.removeMimeType('${this.escapeHtml(t.extension)}')">Remove</button>` : ''}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } catch (e) {
            container.innerHTML = `<div style="color:var(--danger);">Error: ${e.message}</div>`;
        }
    }

    async saveMimeType() {
        const ext = document.getElementById('new-mime-ext')?.value.trim();
        const mime = document.getElementById('new-mime-type')?.value.trim();
        if (!ext || !mime) {
            this.admin.notify("Both extension and MIME type are required.", "error");
            return;
        }

        try {
            const res = await this.admin.api('storage_save_mime_type', { extension: ext, mime_type: mime });
            if (res.success) {
                this.admin.notify(res.message, "success");
                this.openWebToolsModal('mime');
            } else {
                this.admin.notify(res.error || "Save failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async removeMimeType(ext) {
        try {
            const res = await this.admin.api('storage_remove_mime_type', { extension: ext });
            if (res.success) {
                this.admin.notify(res.message, "success");
                this.openWebToolsModal('mime');
            } else {
                this.admin.notify(res.error || "Remove failed.", "error");
            }
        } catch (e) {
            this.admin.notify("Error: " + e.message, "error");
        }
    }

    async loadAccessLogsTab(container) {
        try {
            const info = await this.admin.api('storage_access_log_info');
            const data = (info.success && info.data) ? info.data : info;
            const logs = data.logs || info.logs || [];

            container.innerHTML = `
                <div>
                    <h4 style="margin:0 0 0.5rem 0;">Web Server Access Log Explorer</h4>
                    <p style="font-size:0.85rem; color:var(--text-dim);">
                        Inspect recent HTTP request entries and download raw access log archives.
                    </p>

                    ${logs.length === 0 ? `
                        <div style="padding:2rem; text-align:center; color:var(--text-dim); background:rgba(0,0,0,0.1); border-radius:var(--radius-md);">
                            No standard Apache/Nginx access log files found in default directories.
                        </div>
                    ` : `
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
                            <div style="font-size:0.85rem;">
                                Active Log: <code>${this.escapeHtml(logs[0].path)}</code> (${logs[0].formatted_size})
                            </div>
                            <button class="btn btn-sm btn-outline" onclick="window.filesViewInstance.fetchAccessLogTail('${this.escapeHtml(logs[0].path)}')">🔄 Tail Last 100 Lines</button>
                        </div>
                        <div id="access-log-display" style="padding:1rem; background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.08); border-radius:var(--radius-md); font-family:monospace; font-size:0.75rem; color:#f8fafc; max-height:300px; overflow-y:auto; line-height:1.5;">
                            Click "Tail Last 100 Lines" above to inspect recent web requests.
                        </div>
                    `}
                </div>
            `;
        } catch (e) {
            container.innerHTML = `<div style="color:var(--danger);">Error: ${e.message}</div>`;
        }
    }

    async fetchAccessLogTail(logPath) {
        const display = document.getElementById('access-log-display');
        if (display) display.innerHTML = '<div style="color:var(--text-dim);">Reading log tail...</div>';

        try {
            const res = await this.admin.api('storage_access_log_read', { log_path: logPath, lines: 100 });
            const data = (res.success && res.data) ? res.data : res;
            const lines = data.lines || res.lines || [];
            if (lines.length > 0) {
                if (display) {
                    display.innerHTML = lines.map(l => this.escapeHtml(l)).join('<br>');
                    display.scrollTop = display.scrollHeight;
                }
            } else if (display) {
                display.innerHTML = '<div style="color:var(--text-dim);">Log file is empty.</div>';
            }
        } catch (e) {
            if (display) display.innerHTML = `<div style="color:var(--danger);">Failed to read log: ${e.message}</div>`;
        }
    }

    formatBytes(bytes) {
        if (!bytes) return '0 B';
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
        return bytes + ' B';
    }

    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    update() {
        if (this.container) {
            const activeEl = typeof document !== 'undefined' ? document.activeElement : null;
            const searchInput = typeof document !== 'undefined' ? document.getElementById('spp-files-filter-input') : null;
            const wasSearchFocused = Boolean(activeEl && searchInput && activeEl === searchInput);
            const selStart = (wasSearchFocused && searchInput) ? searchInput.selectionStart : null;
            const selEnd = (wasSearchFocused && searchInput) ? searchInput.selectionEnd : null;

            this.container.innerHTML = this.render();
            window.filesViewInstance = this;
            this.afterUpdate();

            if (wasSearchFocused) {
                const newSearch = document.getElementById('spp-files-filter-input');
                if (newSearch) {
                    newSearch.focus();
                    if (selStart !== null && selEnd !== null) {
                        try { newSearch.setSelectionRange(selStart, selEnd); } catch (e) {}
                    }
                }
            }

            this._bindGlobalKeyHandlers();
        }
    }
}
