import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js?v=2026_05_13_v1';
import { registerNavHandlers, setPageMeta, renderBreadcrumbs } from './lekhak-nav.js';

/**
 * Lekhak Media Library
 * Browse, upload, and manage media files.
 */
export default class MediaView extends BaseComponent {
    async onInit() {
        this.state = { files: [], loading: true, total: 0, uploading: false };

        // SPPEX: Shared navigation handlers
        registerNavHandlers();
        setPageMeta('Media Library', 'Browse, upload, and manage media files');
    }

    async onMount() { await this.fetchMedia(); }

    async fetchMedia() {
        try {
            const res = await this.api.listMedia();
            if (res.success) this.setState({ files: res.files || [], total: res.total || 0, loading: false });
        } catch (e) {
            this.setState({ loading: false });
        }
    }

    async uploadFile() {
        const input = document.createElement('input');
        input.type = 'file'; input.accept = 'image/*,video/*,.pdf';
        input.multiple = true;
        input.onchange = async () => {
            this.setState({ uploading: true });
            for (const file of input.files) {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('action', 'upload_media');
                try {
                    const apiUrl = this.admin?.config?.apiBase || 'admin-api.php';
                    const res = await fetch(new URL(apiUrl, window.location.origin).toString(), { method: 'POST', body: fd }).then(r => r.json());
                    if (res?.success) this.admin?.notify?.(`Uploaded: ${file.name}`, 'success');
                } catch (e) {}
            }
            this.setState({ uploading: false });
            await this.fetchMedia();
        };
        input.click();
    }

    async deleteFile(filename) {
        if (!confirm(`Delete ${filename}?`)) return;
        try {
            await this.api.deleteMedia({ filename });
            this.admin?.notify?.('File deleted.', 'info');
            await this.fetchMedia();
        } catch (e) {
            this.admin?.notify?.('Delete failed.', 'error');
        }
    }

    async renameFile(oldName) {
        const newName = prompt(`Rename ${oldName} to:`, oldName);
        if (!newName || newName === oldName) return;
        
        try {
            const fd = new FormData();
            fd.append('action', 'rename_media');
            fd.append('oldName', oldName);
            fd.append('newName', newName);
            
            const apiUrl = this.admin?.config?.apiBase || 'admin-api.php';
            const res = await fetch(new URL(apiUrl, window.location.origin).toString(), { method: 'POST', body: fd }).then(r => r.json());
            
            if (res?.success) {
                this.admin?.notify?.('File renamed.', 'success');
                await this.fetchMedia();
            } else {
                this.admin?.notify?.(res?.message || 'Rename failed.', 'error');
            }
        } catch (e) {
            this.admin?.notify?.('Rename failed.', 'error');
        }
    }

    render() {
        const { files, loading, uploading, total } = this.state;
        const isImg = (type) => type && type.startsWith('image/');

        const fileCards = files.map(f => `
            <div class="media-card" style="background:var(--sidebar-bg);border:1px solid var(--border);border-radius:8px;overflow:hidden;display:flex;flex-direction:column;">
                <div style="height:140px;background:var(--header-bg);display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    ${isImg(f.type) ? `<img src="${f.url}" style="width:100%;height:100%;object-fit:cover;" alt="${f.name}">` : `<span style="font-size:2.5rem;">📄</span>`}
                </div>
                <div style="padding:10px;flex-grow:1;">
                    <div style="font-size:0.78rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${f.name}">${f.name}</div>
                    <div style="font-size:0.68rem;color:var(--text-dim);margin-top:4px;">${f.size} · ${f.modified?.split(' ')[0] || ''}</div>
                </div>
                <div style="padding:8px 10px;border-top:1px solid var(--border);display:flex;gap:6px;justify-content:flex-end;background:var(--sidebar-bg);">
                    <a href="${f.url}" target="_blank" style="font-size:0.75rem;font-weight:600;color:#ffffff;background:var(--primary);text-decoration:none;padding:5px 12px;border-radius:4px;border:none;cursor:pointer;transition:opacity 0.2s;">Open</a>
                    <button class="rename-media-btn" data-name="${f.name}" style="font-size:0.75rem;font-weight:600;color:var(--text);background:var(--header-bg);border:1px solid var(--border);border-radius:4px;padding:4px 12px;cursor:pointer;transition:opacity 0.2s;">Rename</button>
                    <button class="del-media-btn" data-name="${f.name}" style="font-size:0.75rem;font-weight:600;color:#ffffff;background:#dc2626;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;transition:opacity 0.2s;">Delete</button>
                </div>
            </div>`).join('');

        const html = `<div class="lekhak-media-shell">
            <div class="lekhak-admin-toolbar">
                <div class="toolbar-brand">
                    <span class="logo-icon" style="background:linear-gradient(135deg,var(--primary),#a855f7);color:white;width:24px;height:24px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:0.8rem;box-shadow:0 2px 6px rgba(0,0,0,0.2);">🖼️</span>
                    <span style="font-weight:bold;font-family:'Outfit',sans-serif;">Lekhak CMS</span>
                </div>
                <div class="toolbar-links">
                    <a class="toolbar-tab" data-spp-evt="nav-lekhak" data-spp-type="click">Dashboard</a>
                    <a class="toolbar-tab" data-spp-evt="nav-content" data-spp-type="click">Content</a>
                    <a class="toolbar-tab" data-spp-evt="nav-canvas" data-spp-type="click">Pages</a>
                    <a class="toolbar-tab active" data-spp-evt="nav-media" data-spp-type="click">Media</a>
                    <a class="toolbar-tab" data-spp-evt="nav-structure" data-spp-type="click">Structure</a>
                    <a class="toolbar-tab" data-spp-evt="nav-commerce" data-spp-type="click">Commerce</a>
                    <a class="toolbar-tab" data-spp-evt="nav-translations" data-spp-type="click">Translations</a>
                    <a class="toolbar-tab" data-spp-evt="nav-settings" data-spp-type="click">Appearance</a>
                </div>
                <div><button class="btn-toolbar-primary" id="media-upload-btn">${uploading ? '⏳ Uploading...' : '＋ Upload File'}</button></div>
            </div>
            <div style="padding:2rem;max-width:1400px;margin:0 auto;">
                <h1 style="font-family:'Outfit',sans-serif;font-size:2rem;font-weight:800;margin:0 0 4px 0;color:var(--text);">Media Library</h1>
                <p style="color:var(--text-dim);font-size:0.9rem;margin:0 0 1.5rem 0;">${total} file(s) in library. Drag & drop or click upload.</p>
                ${loading ? '<div style="text-align:center;padding:4rem;color:var(--text-dim);">Loading...</div>' :
                  (files.length === 0 ? '<div style="text-align:center;padding:4rem;color:var(--text-dim);"><span style="font-size:3rem;display:block;margin-bottom:12px;">🖼️</span>No media files yet. Upload your first file.</div>' :
                  `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">${fileCards}</div>`)}
            </div>
        </div>
        <style>
            .lekhak-media-shell { font-family:'Inter',sans-serif;color:var(--text);min-height:100vh; }
            .lekhak-admin-toolbar { position:sticky;top:0;z-index:1000;background:var(--header-bg);border-bottom:2px solid var(--border);padding:0 1.5rem;height:50px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 12px rgba(0,0,0,0.1); }
            .toolbar-links { display:flex;height:100%; }
            .toolbar-tab { padding:0 0.75rem;display:flex;align-items:center;color:var(--text-dim);font-size:0.78rem;font-weight:600;text-decoration:none;cursor:pointer;border-bottom:2px solid transparent;height:100%;transition:all 0.2s; }
            .toolbar-tab:hover,.toolbar-tab.active { color:var(--primary);border-bottom-color:var(--primary); }
            .btn-toolbar-primary { background:var(--primary);color:white;border:none;padding:6px 14px;border-radius:6px;font-size:0.8rem;font-weight:800;cursor:pointer; }
        </style>`;
        return new (window.TrustedHTML || Object.getPrototypeOf(this.renderLoading('')).constructor)(html);
    }

    afterUpdate() {
        const btn = document.getElementById('media-upload-btn');
        if (btn && !btn._bound) { btn.onclick = () => this.uploadFile(); btn._bound = true; }
        
        document.querySelectorAll('.rename-media-btn').forEach(b => {
            if (!b._bound) {
                b.onclick = (e) => { e.stopPropagation(); this.renameFile(b.dataset.name); };
                b._bound = true;
            }
        });
        
        document.querySelectorAll('.del-media-btn').forEach(b => {
            if (!b._bound) {
                b.onclick = (e) => { e.stopPropagation(); this.deleteFile(b.dataset.name); };
                b._bound = true;
            }
        });

        // Native HTML5 Drag and Drop zone on the media grid area
        const dropTarget = document.querySelector('.lekhak-media-shell');
        if (dropTarget && !dropTarget._nativeDropzoneInit) {
            dropTarget._nativeDropzoneInit = true;
            
            const preventDefaults = (e) => { e.preventDefault(); e.stopPropagation(); };
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eName => {
                dropTarget.addEventListener(eName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eName => {
                dropTarget.addEventListener(eName, () => dropTarget.style.background = 'rgba(255,255,255,0.05)', false);
            });

            ['dragleave', 'drop'].forEach(eName => {
                dropTarget.addEventListener(eName, () => dropTarget.style.background = '', false);
            });

            dropTarget.addEventListener('drop', async (e) => {
                const files = e.dataTransfer.files;
                if (!files || files.length === 0) return;
                
                this.setState({ uploading: true });
                for (const file of files) {
                    const fd = new FormData();
                    fd.append('file', file);
                    fd.append('action', 'upload_media');
                    try {
                        const apiUrl = this.admin?.config?.apiBase || 'admin-api.php';
                        const res = await fetch(new URL(apiUrl, window.location.origin).toString(), { method: 'POST', body: fd }).then(r => r.json());
                        if (res?.success) this.admin?.notify?.(`Uploaded: ${file.name}`, 'success');
                    } catch (err) {}
                }
                this.setState({ uploading: false });
                await this.fetchMedia();
            }, false);
        }

        // SPPEX.Masonry: Apply fluid Pinterest-style layout to the media grid
        if (typeof SPPEX !== 'undefined' && SPPEX.Masonry) {
            const mediaGrid = document.querySelector('.media-grid-container');
            if (mediaGrid && !mediaGrid._masonryInit) {
                SPPEX.Masonry.init('.media-grid-container', 4);
                mediaGrid._masonryInit = true;
            }
        }
    }
}
