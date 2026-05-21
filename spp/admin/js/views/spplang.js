/**
 * SPPLang Translations Management View Component
 * 
 * Provides a highly polished glassmorphic interface to scan codebases,
 * search/filter keys, edit translations, and update translation records.
 */
import BaseComponent from '../../../modules/spp/sppux/js/BaseComponent.js';

export default class SpplangView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: true,
            translations: [],
            locale: 'en',
            search: '',
            status: '',
            scanning: false,
            savingKey: null
        };
        await this.fetchData();
    }

    async fetchData() {
        try {
            const res = await this.api('spplang_get', {
                locale: this.state.locale,
                search: this.state.search,
                status: this.state.status
            });
            if (res.success) {
                this.setState({
                    translations: res.data.translations || [],
                    loading: false
                });
            } else {
                this.setState({ loading: false, error: res.message });
            }
        } catch (err) {
            this.setState({ loading: false, error: err.message });
        }
    }

    async handleScan() {
        this.setState({ scanning: true });
        try {
            const res = await this.api('spplang_scan', { locale: this.state.locale });
            if (res.success) {
                this.notify(res.message || 'Scan completed successfully!', 'success');
                await this.fetchData();
            } else {
                this.notify(res.message || 'Scan failed.', 'error');
            }
        } catch (err) {
            this.notify(err.message || 'Error executing scan.', 'error');
        } finally {
            this.setState({ scanning: false });
        }
    }

    async handleSaveRow(row) {
        const input = document.getElementById(`trans-input-${row.id || row.key_code}`);
        const statusSelect = document.getElementById(`status-select-${row.id || row.key_code}`);
        const newValue = input ? input.value : row.translation;
        const newStatus = statusSelect ? statusSelect.value : row.status;

        this.setState({ savingKey: row.key_code });
        try {
            const res = await this.api('spplang_save', {
                key_code: row.key_code,
                locale: this.state.locale,
                translation: newValue,
                status: newStatus
            });
            if (res.success) {
                this.notify('Translation saved successfully!', 'success');
                // Update local state value without full refetch for seamless UI
                const updatedTranslations = this.state.translations.map(t => {
                    if (t.key_code === row.key_code) {
                        return { ...t, translation: newValue, status: newStatus };
                    }
                    return t;
                });
                this.setState({ translations: updatedTranslations });
            } else {
                this.notify(res.message || 'Failed to save translation.', 'error');
            }
        } catch (err) {
            this.notify(err.message || 'Error saving translation.', 'error');
        } finally {
            this.setState({ savingKey: null });
        }
    }

    handleSearchChange(val) {
        this.setState({ search: val });
        // Debounce search slightly
        if (this.searchTimeout) clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => this.fetchData(), 300);
    }

    handleLocaleChange(val) {
        this.setState({ locale: val, loading: true });
        this.fetchData();
    }

    handleStatusChange(val) {
        this.setState({ status: val, loading: true });
        this.fetchData();
    }

    render() {
        const { loading, translations, locale, search, status, scanning, savingKey, error } = this.state;

        if (loading) {
            return html`
                <div style="padding: 4rem; text-align: center;">
                    <div class="sppux-spinner" style="width: 40px; height: 40px; margin: 0 auto 1.5rem auto;"></div>
                    <div style="opacity: 0.5;">Loading translations grid...</div>
                </div>
            `;
        }

        if (error) {
            return html`
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <h3>Error Loading Translations</h3>
                    <p style="color: var(--danger); font-family: monospace;">${error}</p>
                    <button class="btn primary-btn" @click="${() => this.fetchData()}">Retry</button>
                </div>
            `;
        }

        return html`
            <div class="spplang-workbench" style="display: flex; flex-direction: column; gap: 20px; height: 100%;">
                
                <!-- Action Header Panel -->
                <div class="glass-panel" style="padding: 20px; display: flex; justify-content: space-between; align-items: center; background: var(--glass-bg-accent); border: 1px solid var(--glass-border);">
                    <div>
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                            <span>🌐</span> Translation Workbench
                        </h3>
                        <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: var(--text-dim);">
                            Scan your PHP and YML codebases for translatable strings and manage active database overrides dynamically.
                        </p>
                    </div>
                    <button class="btn primary-btn ${scanning ? 'loading' : ''}" 
                            style="display: flex; align-items: center; gap: 8px; font-weight: 500; min-width: 170px; justify-content: center;"
                            ?disabled="${scanning}"
                            @click="${() => this.handleScan()}">
                        <span>${scanning ? 'Scanning...' : '🔍 Scan Codebase'}</span>
                    </button>
                </div>

                <!-- Filters Panel -->
                <div class="glass-panel" style="padding: 15px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                    <!-- Active Locale Select -->
                    <div style="display: flex; flex-direction: column; gap: 4px; min-width: 140px;">
                        <label style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Active Locale</label>
                        <select class="spp-element" 
                                style="width: 100%; padding: 6px 10px; font-size: 0.8rem;" 
                                .value="${locale}" 
                                @change="${(e) => this.handleLocaleChange(e.target.value)}">
                            <option value="en">🇺🇸 English (en)</option>
                            <option value="es">🇪🇸 Spanish (es)</option>
                            <option value="hi">🇮🇳 Hindi (hi)</option>
                            <option value="fr">🇫🇷 French (fr)</option>
                            <option value="de">🇩🇪 German (de)</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div style="display: flex; flex-direction: column; gap: 4px; min-width: 120px;">
                        <label style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Status</label>
                        <select class="spp-element" 
                                style="width: 100%; padding: 6px 10px; font-size: 0.8rem;" 
                                .value="${status}" 
                                @change="${(e) => this.handleStatusChange(e.target.value)}">
                            <option value="">All Statuses</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div style="display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 200px;">
                        <label style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Search Key or Translation</label>
                        <input type="text" 
                               class="spp-element" 
                               placeholder="Type to search keys or translations..." 
                               style="padding: 6px 10px; font-size: 0.8rem;" 
                               .value="${search}" 
                               @input="${(e) => this.handleSearchChange(e.target.value)}">
                    </div>
                </div>

                <!-- Grid Data Table -->
                <div class="glass-panel" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; flex: 1; min-height: 300px;">
                    <div style="overflow-x: auto; flex: 1;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                            <thead>
                                <tr style="background: rgba(255, 255, 255, 0.03); border-bottom: 1px solid var(--glass-border);">
                                    <th style="padding: 12px 20px; font-weight: 600; color: var(--text-dim); width: 30%;">Key Code / Source Key</th>
                                    <th style="padding: 12px 20px; font-weight: 600; color: var(--text-dim); width: 45%;">Translation Override</th>
                                    <th style="padding: 12px 20px; font-weight: 600; color: var(--text-dim); width: 13%; text-align: center;">Status</th>
                                    <th style="padding: 12px 20px; font-weight: 600; color: var(--text-dim); width: 12%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${translations.map(row => html`
                                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: background 0.2s;" class="hover-row">
                                        <td style="padding: 15px 20px; vertical-align: middle;">
                                            <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--accent-light); word-break: break-all;">
                                                ${row.key_code}
                                            </div>
                                        </td>
                                        <td style="padding: 15px 20px; vertical-align: middle;">
                                            <textarea id="trans-input-${row.id || row.key_code}" 
                                                      class="spp-element" 
                                                      rows="1" 
                                                      style="width: 100%; padding: 6px 10px; font-size: 0.8rem; resize: vertical; min-height: 34px;" 
                                                      placeholder="No translation defined. Standard key fallback will apply.">${row.translation || ''}</textarea>
                                        </td>
                                        <td style="padding: 15px 20px; vertical-align: middle; text-align: center;">
                                            <select id="status-select-${row.id || row.key_code}" 
                                                    class="spp-element" 
                                                    style="padding: 4px 6px; font-size: 0.75rem; min-width: 85px; text-align: center;">
                                                <option value="active" ?selected="${row.status === 'active'}">🟢 Active</option>
                                                <option value="inactive" ?selected="${row.status === 'inactive'}">🔴 Inactive</option>
                                            </select>
                                        </td>
                                        <td style="padding: 15px 20px; vertical-align: middle; text-align: center;">
                                            <button class="btn secondary-btn btn-sm ${savingKey === row.key_code ? 'loading' : ''}" 
                                                    ?disabled="${savingKey !== null}"
                                                    @click="${() => this.handleSaveRow(row)}"
                                                    style="min-width: 60px;">
                                                ${savingKey === row.key_code ? 'Saving' : 'Save'}
                                            </button>
                                        </td>
                                    </tr>
                                `)}
                                ${translations.length === 0 ? html`
                                    <tr>
                                        <td colspan="4" style="padding: 40px; text-align: center; color: var(--text-dim);">
                                            <div style="font-size: 2rem; margin-bottom: 10px;">🔍</div>
                                            <h4>No Translations Found</h4>
                                            <p style="font-size: 0.8rem;">Try changing your search keywords or click "Scan Codebase" to discover translatable strings.</p>
                                        </td>
                                    </tr>
                                ` : ''}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }
}
