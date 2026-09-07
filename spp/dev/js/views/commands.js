export default class CommandsView extends SPPUX.BaseComponent {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
    }
    async onInit() {
        this.state = {
            categories: {},
            loading: true,
            filterTerm: '',
            activeCommand: null,
            commandUI: '',
            terminalOutput: '',
            executing: false
        };
        await this.fetchCommands();
    }

    async fetchCommands() {
        try {
            const res = await this.admin.api('list_commands');
            if (res.success) {
                this.setState({ categories: res.data.categories, loading: false });
            } else {
                this.admin.notify("Failed to load commands", "error");
                this.setState({ loading: false });
            }
        } catch (e) {
            console.error(e);
            this.setState({ loading: false });
        }
    }

    async loadCommandUI(cmdName) {
        this.setState({ activeCommand: cmdName, commandUI: '<div class="sppux-spinner"></div>', terminalOutput: '' });
        try {
            const res = await this.admin.api('get_command_ui', { command: cmdName });
            if (res.success) {
                this.setState({ commandUI: res.data.html });
            } else {
                this.setState({ commandUI: `<div style="color:red">Error: ${res.message}</div>` });
            }
        } catch (e) {
            console.error(e);
        }
    }

    async executeCommand(cmdName, argsString = null) {
        if (!cmdName) cmdName = this.state.activeCommand;
        if (!cmdName) return;

        let args = argsString;
        if (args === null) {
            const input = document.getElementById('cmdArgs');
            args = input ? input.value : '';
        }

        const initialTerminal = `> php spp.php ${cmdName} ${args}\nExecuting...`;
        this.setState({ terminalOutput: initialTerminal, executing: true });

        try {
            const res = await this.admin.api('execute_command', { command: cmdName, args: args });
            if (res.success) {
                this.setState({ terminalOutput: initialTerminal + '\n\n' + (res.data.output || '(Command completed successfully with no output)'), executing: false });
            } else {
                let errText = initialTerminal + '\n\nERROR: ' + res.message;
                if (res._debug_output) {
                    errText += '\n\nDEBUG: ' + res._debug_output;
                }
                this.setState({ terminalOutput: errText, executing: false });
            }
        } catch (e) {
            this.setState({ terminalOutput: initialTerminal + '\n\nEXCEPTION: ' + e.message, executing: false });
        }
    }

    copyTerminalOutput() {
        if (this.state.terminalOutput) {
            navigator.clipboard.writeText(this.state.terminalOutput);
            if (this.admin.notify) this.admin.notify("Terminal output copied to clipboard", "success");
        }
    }

    clearTerminal() {
        this.setState({ terminalOutput: '' });
    }

    render() {
        // Expose executeCommand and submitActiveCommand globally so form submissions and scripts work seamlessly
        window.executeCommand = (cmdName, args) => this.executeCommand(cmdName, args);
        window.submitActiveCommand = () => {
            const input = document.getElementById('cmdArgs');
            const args = input ? input.value : '';
            this.executeCommand(this.state.activeCommand, args);
        };

        if (this.state.loading) {
            return SPPUX.html`<div class="loading-state"><div class="sppux-spinner"></div> Loading Command Center...</div>`;
        }

        const filter = (this.state.filterTerm || '').toLowerCase().trim();

        // Build Sidebar with search filter
        let filteredCount = 0;
        let sidebarHtml = Object.entries(this.state.categories).map(([prefix, cmds]) => {
            const matchingCmds = cmds.filter(cmd => {
                if (!filter) return true;
                return cmd.name.toLowerCase().includes(filter) || (cmd.description && cmd.description.toLowerCase().includes(filter));
            });

            if (matchingCmds.length === 0) return '';
            filteredCount += matchingCmds.length;

            return SPPUX.html`
                <div class="category" style="margin-bottom: 12px;">
                    <div class="category-title" style="font-weight:700; font-size:0.75rem; color:var(--primary); text-transform:uppercase; letter-spacing:0.05em; padding: 4px 8px;">
                        ${prefix} (${matchingCmds.length})
                    </div>
                    ${matchingCmds.map(cmd => {
                        const isActive = this.state.activeCommand === cmd.name;
                        return SPPUX.html`
                            <div class="cmd-item ${isActive ? 'active' : ''}" 
                                style="padding: 6px 10px; border-radius: 6px; margin: 2px 0; cursor:pointer; font-size: 0.85rem; font-family: monospace; transition: all 0.15s; background: ${isActive ? 'var(--primary-glow, rgba(99,102,241,0.2))' : 'transparent'}; border-left: ${isActive ? '3px solid var(--primary)' : '3px solid transparent'};" 
                                @click=${() => this.loadCommandUI(cmd.name)}
                                title="${cmd.description || cmd.name}">
                                <span style="color:${isActive ? 'var(--text-bright)' : 'var(--text)'}; font-weight:${isActive ? '600' : '400'};">${cmd.name}</span>
                            </div>
                        `;
                    })}
                </div>
            `;
        });

        return SPPUX.html`
            <style>
                .cmd-layout { display: flex; gap: 20px; height: calc(100vh - 140px); }
                .cmd-sidebar { width: 280px; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 8px; padding: 15px; display: flex; flex-direction: column; }
                .cmd-sidebar-list { flex: 1; overflow-y: auto; margin-top: 10px; }
                .cmd-main { flex: 1; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; }
                .cmd-ui-panel { background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 20px; border-radius: 8px; }
                .cmd-terminal-wrapper { flex: 1; min-height: 250px; display: flex; flex-direction: column; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; overflow: hidden; }
                .cmd-terminal-header { display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 8px 14px; font-size: 0.8rem; color: #94a3b8; font-family: monospace; }
                .cmd-terminal { flex: 1; color: #38bdf8; padding: 15px; font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.85rem; overflow-y: auto; white-space: pre-wrap; line-height: 1.5; }
                .cmd-item:hover { background: rgba(255,255,255,0.05) !important; }
                .command-ui-container h3 { margin-top: 0; }
                .spp-input { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid var(--glass-border); border-radius: 4px; background: var(--glass-bg); color: var(--text); }
                .spp-btn { padding: 8px 15px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; }
                .spp-btn:hover { opacity: 0.9; }
            </style>
            
            <div class="cmd-layout">
                <div class="cmd-sidebar">
                    <div style="padding-bottom: 8px; border-bottom: 1px solid var(--glass-border);">
                        <input type="text" 
                            class="spp-element" 
                            placeholder="Search 240+ commands..." 
                            value="${this.state.filterTerm}"
                            @input=${(e) => this.setState({ filterTerm: e.target.value })}
                            style="width: 100%; padding: 7px 10px; font-size: 0.82rem; border-radius: 6px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--text-bright);">
                        <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 6px;">
                            ${filter ? `Found ${filteredCount} matching commands` : `Total: 247 registered commands`}
                        </div>
                    </div>
                    <div class="cmd-sidebar-list">
                        ${sidebarHtml}
                    </div>
                </div>
                <div class="cmd-main">
                    <div class="cmd-ui-panel">
                        ${this.state.activeCommand 
                            ? new SPPUX.TrustedHTML(this.state.commandUI) 
                            : SPPUX.html`
                                <div style="color:var(--text-dim); text-align:center; padding: 40px;">
                                    <div style="font-size: 2.5rem; margin-bottom: 10px;">⚡</div>
                                    <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-bright); margin-bottom: 6px;">CLI Command Center</div>
                                    <p>Select any framework command from the sidebar to inspect its definition, parameters, and execute it live.</p>
                                </div>
                            `}
                    </div>
                    <div class="cmd-terminal-wrapper" style="${this.state.terminalOutput ? '' : 'display:none;'}">
                        <div class="cmd-terminal-header">
                            <span>TERMINAL OUTPUT ${this.state.executing ? '⏳ Running...' : '✓'}</span>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn ghost-btn btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" @click=${() => this.copyTerminalOutput()}>📋 Copy</button>
                                <button class="btn ghost-btn btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" @click=${() => this.clearTerminal()}>✕ Clear</button>
                            </div>
                        </div>
                        <div class="cmd-terminal">
                            ${this.state.terminalOutput}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    afterUpdate() {
        if (this.state.activeCommand && this.state.commandUI) {
            const container = document.querySelector('.cmd-ui-panel');
            if (container) {
                const scripts = container.getElementsByTagName('script');
                for (let i = 0; i < scripts.length; i++) {
                    try {
                        eval(scripts[i].innerText);
                    } catch (e) {
                        console.error("Error evaluating command script:", e);
                    }
                }
            }
        }
    }
}
