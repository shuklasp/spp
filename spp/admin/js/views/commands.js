export default class CommandsView extends SPPUX.BaseComponent {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
    }
    async onInit() {
        this.state = {
            categories: {},
            loading: true,
            activeCommand: null,
            commandUI: '',
            terminalOutput: ''
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
        let args = argsString;
        if (args === null) {
            const input = document.getElementById('cmdArgs');
            args = input ? input.value : '';
        }

        const initialTerminal = `> php spp.php ${cmdName} ${args}\nExecuting...`;
        this.setState({ terminalOutput: initialTerminal });

        try {
            const res = await this.admin.api('execute_command', { command: cmdName, args: args });
            if (res.success) {
                this.setState({ terminalOutput: initialTerminal + '\n\n' + res.data.output });
            } else {
                let errText = initialTerminal + '\n\nERROR: ' + res.message;
                if (res._debug_output) {
                    errText += '\n\nDEBUG: ' + res._debug_output;
                }
                this.setState({ terminalOutput: errText });
            }
        } catch (e) {
            this.setState({ terminalOutput: initialTerminal + '\n\nEXCEPTION: ' + e.message });
        }
    }

    render() {
        // Expose executeCommand globally so inline scripts in command UIs can call it
        window.executeCommand = (cmdName, args) => this.executeCommand(cmdName, args);

        if (this.state.loading) {
            return SPPUX.html`<div class="loading-state"><div class="sppux-spinner"></div> Loading Command Center...</div>`;
        }

        // Build Sidebar
        let sidebarHtml = Object.entries(this.state.categories).map(([prefix, cmds]) => SPPUX.html`
            <div class="category">
                <div class="category-title" style="font-weight:bold; margin-top:15px; color:var(--primary); text-transform:uppercase;">${prefix}</div>
                ${cmds.map(cmd => SPPUX.html`
                    <div class="cmd-item" style="padding: 5px 10px; cursor:pointer;" 
                        @click=${() => this.loadCommandUI(cmd.name)}>
                        <span style="color:var(--text)">${cmd.name}</span>
                    </div>
                `)}
            </div>
        `);

        return SPPUX.html`
            <style>
                .cmd-layout { display: flex; gap: 20px; height: calc(100vh - 120px); }
                .cmd-sidebar { width: 250px; background: var(--glass-bg); border-radius: 8px; padding: 15px; overflow-y: auto; }
                .cmd-main { flex: 1; display: flex; flex-direction: column; gap: 20px; }
                .cmd-ui-panel { background: var(--glass-bg); padding: 20px; border-radius: 8px; }
                .cmd-terminal { flex: 1; background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; font-family: monospace; overflow-y: auto; white-space: pre-wrap; }
                .cmd-item:hover { background: rgba(0,0,0,0.05); }
                .command-ui-container h3 { margin-top: 0; }
                .spp-input { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid var(--glass-border); border-radius: 4px; background: var(--glass-bg); color: var(--text); }
                .spp-btn { padding: 8px 15px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; }
                .spp-btn:hover { opacity: 0.9; }
            </style>
            
            <div class="cmd-layout">
                <div class="cmd-sidebar">
                    ${sidebarHtml}
                </div>
                <div class="cmd-main">
                    <div class="cmd-ui-panel">
                        ${this.state.activeCommand 
                            ? new SPPUX.TrustedHTML(this.state.commandUI) 
                            : SPPUX.html`<div style="color:var(--text-dim); text-align:center; padding: 40px;">Select a command from the left panel</div>`}
                    </div>
                    <div class="cmd-terminal" style="${this.state.terminalOutput ? '' : 'display:none;'}">
                        ${this.state.terminalOutput}
                    </div>
                </div>
            </div>
        `;
    }

    afterUpdate() {
        // Execute any scripts embedded in the loaded command UI
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
