/**
 * CommandsView Component (SPP Dev)
 * 
 * Interactive CLI Command Center featuring live command runner, parameter inspection,
 * integrated terminal execution, and a visual 5-field Cron Builder & Task Scheduler.
 */

export default class CommandsView extends SPPUX.BaseComponent {
    constructor(admin, container, props) {
        super(admin, container, props);
        this.admin = admin;
    }

    async onInit() {
        this.state = {
            activeMainTab: 'console', // 'console' | 'cron'
            categories: {},
            loading: true,
            filterTerm: '',
            activeCommand: null,
            commandUI: '',
            terminalOutput: '',
            executing: false,
            // Cron Scheduler State
            cronMinute: '*',
            cronHour: '*',
            cronDay: '*',
            cronMonth: '*',
            cronWeekday: '*',
            cronCommand: 'workflow:process-timeouts',
            cronArgs: '',
            scheduledTasks: [
                { id: 1, name: 'Process SLA Timeouts', expr: '*/5 * * * *', command: 'workflow:process-timeouts', desc: 'Auto-escalates timed-out workflow states', active: true },
                { id: 2, name: 'Queue Worker Daemon', expr: '* * * * *', command: 'queue:work', desc: 'Processes asynchronous background jobs', active: true },
                { id: 3, name: 'Parikshak Health Sweep', expr: '0 * * * *', command: 'test', desc: 'Hourly test run & health verification', active: false },
                { id: 4, name: 'Storage Vacuum & Cache Purge', expr: '0 2 * * *', command: 'cache:clear', desc: 'Nightly maintenance at 2:00 AM', active: true }
            ]
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

    // =============================================
    // CRON SCHEDULER HELPERS
    // =============================================

    applyCronPreset(preset) {
        let parts = ['*', '*', '*', '*', '*'];
        if (preset === 'min') parts = ['* * * * *'.split(' ')];
        else if (preset === '5min') parts = '*/5 * * * *'.split(' ');
        else if (preset === 'hourly') parts = '0 * * * *'.split(' ');
        else if (preset === 'daily_midnight') parts = '0 0 * * *'.split(' ');
        else if (preset === 'daily_8am') parts = '0 8 * * *'.split(' ');
        else if (preset === 'weekly') parts = '0 0 * * 0'.split(' ');
        else if (preset === 'workdays') parts = '0 9 * * 1-5'.split(' ');
        else if (preset === 'monthly') parts = '0 0 1 * *'.split(' ');

        this.setState({
            cronMinute: parts[0],
            cronHour: parts[1],
            cronDay: parts[2],
            cronMonth: parts[3],
            cronWeekday: parts[4]
        });
    }

    getCronExpression() {
        const { cronMinute, cronHour, cronDay, cronMonth, cronWeekday } = this.state;
        return `${cronMinute || '*'} ${cronHour || '*'} ${cronDay || '*'} ${cronMonth || '*'} ${cronWeekday || '*'}`;
    }

    getHumanReadableCron() {
        const { cronMinute, cronHour, cronDay, cronMonth, cronWeekday } = this.state;
        const expr = this.getCronExpression();

        if (expr === '* * * * *') return 'Runs every single minute';
        if (expr === '*/5 * * * *') return 'Runs every 5 minutes';
        if (expr === '0 * * * *') return 'Runs at the beginning of every hour';
        if (expr === '0 0 * * *') return 'Runs once a day at midnight (00:00)';
        if (expr === '0 8 * * *') return 'Runs once a day at 08:00 AM';
        if (expr === '0 0 * * 0') return 'Runs once a week on Sunday at midnight';
        if (expr === '0 9 * * 1-5') return 'Runs every weekday (Mon-Fri) at 09:00 AM';
        if (expr === '0 0 1 * *') return 'Runs on the 1st of every month at midnight';

        return `Runs at minute ${cronMinute}, hour ${cronHour}, day-of-month ${cronDay}, month ${cronMonth}, day-of-week ${cronWeekday}`;
    }

    addScheduledTask() {
        const expr = this.getCronExpression();
        const cmd = this.state.cronCommand;
        const args = this.state.cronArgs ? ' ' + this.state.cronArgs : '';
        const newTask = {
            id: Date.now(),
            name: cmd + args,
            expr: expr,
            command: cmd,
            desc: this.getHumanReadableCron(),
            active: true
        };

        const updated = [...this.state.scheduledTasks, newTask];
        this.setState({ scheduledTasks: updated });
        this.admin.notify(`Task '${cmd}' scheduled (${expr})`, 'success');
    }

    removeScheduledTask(id) {
        const updated = this.state.scheduledTasks.filter(t => t.id !== id);
        this.setState({ scheduledTasks: updated });
        this.admin.notify('Scheduled task removed', 'info');
    }

    toggleScheduledTask(id) {
        const updated = this.state.scheduledTasks.map(t => {
            if (t.id === id) return { ...t, active: !t.active };
            return t;
        });
        this.setState({ scheduledTasks: updated });
    }

    render() {
        window.executeCommand = (cmdName, args) => this.executeCommand(cmdName, args);
        window.submitActiveCommand = () => {
            const input = document.getElementById('cmdArgs');
            const args = input ? input.value : '';
            this.executeCommand(this.state.activeCommand, args);
        };

        if (this.state.loading) {
            return SPPUX.html`<div class="loading-state"><div class="sppux-spinner"></div> Loading Command Center...</div>`;
        }

        const { activeMainTab } = this.state;

        return SPPUX.html`
            <div class="commands-workspace" style="display:flex; flex-direction:column; gap:12px; height:calc(100vh - 120px);">
                <!-- Sub Tab Navigation -->
                <div class="tab-bar-secondary" style="display:flex; gap:6px; border-bottom:1px solid var(--glass-border); padding-bottom:8px;">
                    <button type="button" class="sub-tab-btn ${activeMainTab === 'console' ? 'active' : ''}"
                        @click=${() => this.setState({ activeMainTab: 'console' })}>
                        ⚡ CLI Console & Runner
                    </button>
                    <button type="button" class="sub-tab-btn ${activeMainTab === 'cron' ? 'active' : ''}"
                        @click=${() => this.setState({ activeMainTab: 'cron' })}>
                        ⏰ Scheduled Crons & Tasks
                    </button>
                </div>

                ${activeMainTab === 'cron' ? this.renderCronScheduler() : this.renderConsole()}
            </div>
        `;
    }

    renderConsole() {
        const filter = (this.state.filterTerm || '').toLowerCase().trim();

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
                .cmd-layout { display: flex; gap: 20px; flex: 1; min-height: 0; }
                .cmd-sidebar { width: 280px; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 8px; padding: 15px; display: flex; flex-direction: column; }
                .cmd-sidebar-list { flex: 1; overflow-y: auto; margin-top: 10px; }
                .cmd-main { flex: 1; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; }
                .cmd-ui-panel { background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 20px; border-radius: 8px; }
                .cmd-terminal-wrapper { flex: 1; min-height: 250px; display: flex; flex-direction: column; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; overflow: hidden; }
                .cmd-terminal-header { display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 8px 14px; font-size: 0.8rem; color: #94a3b8; font-family: monospace; }
                .cmd-terminal { flex: 1; color: #38bdf8; padding: 15px; font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.85rem; overflow-y: auto; white-space: pre-wrap; line-height: 1.5; }
                .cmd-item:hover { background: var(--btn-soft-bg) !important; }
                .command-ui-container h3 { margin-top: 0; }
                .spp-input { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid var(--glass-border); border-radius: 4px; background: var(--input-bg); color: var(--text-bright); }
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
                            style="width: 100%; padding: 7px 10px; font-size: 0.82rem; border-radius: 6px; background: var(--input-bg); border: 1px solid var(--glass-border); color: var(--text-bright);">
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

    renderCronScheduler() {
        const { cronMinute, cronHour, cronDay, cronMonth, cronWeekday, cronCommand, cronArgs, scheduledTasks } = this.state;
        const expr = this.getCronExpression();
        const humanDesc = this.getHumanReadableCron();

        return SPPUX.html`
            <div style="display:flex; flex-direction:column; gap:16px; overflow-y:auto; padding:4px;">
                <!-- 5-Field Visual Builder Panel -->
                <div class="glass-panel" style="padding:16px; border-radius:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                        <div>
                            <h3 style="margin:0 0 4px 0; color:var(--text-bright); font-size:1.1rem; display:flex; align-items:center; gap:6px;">
                                <span>⏰</span> Visual 5-Field Cron Builder
                            </h3>
                            <small style="color:var(--text-dim);">Construct and test declarative scheduled execution triggers</small>
                        </div>

                        <!-- Presets Dropdown -->
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:0.8rem; color:var(--text-dim);">Presets:</span>
                            <select class="spp-element" style="padding:4px 8px; font-size:0.8rem; border-radius:6px; background:var(--input-bg); color:var(--text-bright); border:1px solid var(--glass-border);"
                                @change=${(e) => this.applyCronPreset(e.target.value)}>
                                <option value="">-- Choose Preset --</option>
                                <option value="min">Every Minute (* * * * *)</option>
                                <option value="5min">Every 5 Minutes (*/5 * * * *)</option>
                                <option value="hourly">Hourly (0 * * * *)</option>
                                <option value="daily_midnight">Daily at Midnight (0 0 * * *)</option>
                                <option value="daily_8am">Daily at 8:00 AM (0 8 * * *)</option>
                                <option value="workdays">Weekdays at 9:00 AM (0 9 * * 1-5)</option>
                                <option value="weekly">Weekly on Sunday (0 0 * * 0)</option>
                                <option value="monthly">Monthly on 1st (0 0 1 * *)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5 Field Inputs -->
                    <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px; margin-bottom:16px;">
                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:#38bdf8; margin-bottom:4px; display:block;">Minute (0-59)</label>
                            <input type="text" value="${cronMinute}" placeholder="*"
                                style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--glass-border); background:var(--input-bg); color:var(--text-bright); font-family:monospace; text-align:center; font-size:1rem; font-weight:600;"
                                @input=${(e) => this.setState({ cronMinute: e.target.value })}>
                            <small style="color:var(--text-dim); font-size:0.7rem; text-align:center; display:block; margin-top:2px;">* or */5 or 0,30</small>
                        </div>

                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:#818cf8; margin-bottom:4px; display:block;">Hour (0-23)</label>
                            <input type="text" value="${cronHour}" placeholder="*"
                                style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--glass-border); background:var(--input-bg); color:var(--text-bright); font-family:monospace; text-align:center; font-size:1rem; font-weight:600;"
                                @input=${(e) => this.setState({ cronHour: e.target.value })}>
                            <small style="color:var(--text-dim); font-size:0.7rem; text-align:center; display:block; margin-top:2px;">* or */2 or 9-17</small>
                        </div>

                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:#a855f7; margin-bottom:4px; display:block;">Day of Month (1-31)</label>
                            <input type="text" value="${cronDay}" placeholder="*"
                                style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--glass-border); background:var(--input-bg); color:var(--text-bright); font-family:monospace; text-align:center; font-size:1rem; font-weight:600;"
                                @input=${(e) => this.setState({ cronDay: e.target.value })}>
                            <small style="color:var(--text-dim); font-size:0.7rem; text-align:center; display:block; margin-top:2px;">* or 1,15 or */2</small>
                        </div>

                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:#ec4899; margin-bottom:4px; display:block;">Month (1-12)</label>
                            <input type="text" value="${cronMonth}" placeholder="*"
                                style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--glass-border); background:var(--input-bg); color:var(--text-bright); font-family:monospace; text-align:center; font-size:1rem; font-weight:600;"
                                @input=${(e) => this.setState({ cronMonth: e.target.value })}>
                            <small style="color:var(--text-dim); font-size:0.7rem; text-align:center; display:block; margin-top:2px;">* or */3 (Quarterly)</small>
                        </div>

                        <div class="input-group">
                            <label style="font-size:0.78rem; font-weight:600; color:#22c55e; margin-bottom:4px; display:block;">Day of Week (0-6)</label>
                            <input type="text" value="${cronWeekday}" placeholder="*"
                                style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--glass-border); background:var(--input-bg); color:var(--text-bright); font-family:monospace; text-align:center; font-size:1rem; font-weight:600;"
                                @input=${(e) => this.setState({ cronWeekday: e.target.value })}>
                            <small style="color:var(--text-dim); font-size:0.7rem; text-align:center; display:block; margin-top:2px;">0=Sun, 1-5=Mon-Fri</small>
                        </div>
                    </div>

                    <!-- Computed Expression & Human Readout -->
                    <div style="background:var(--card-bg); border:1px solid var(--glass-border); box-shadow:var(--shadow-sm); border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:2px;">Computed Expression</div>
                            <div style="font-family:'JetBrains Mono', monospace; font-size:1.3rem; font-weight:700; color:#38bdf8; letter-spacing:4px;">
                                ${expr}
                            </div>
                        </div>
                        <div style="flex:1; max-width:400px;">
                            <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:2px;">Schedule Translation</div>
                            <div style="font-size:0.92rem; color:#a5b4fc; font-weight:500;">
                                💬 ${humanDesc}
                            </div>
                        </div>
                    </div>

                    <!-- Target Command & Add Button -->
                    <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:12px; align-items:flex-end;">
                        <div class="input-group">
                            <label style="font-size:0.8rem; color:var(--text-dim); margin-bottom:4px; display:block;">Command to Execute</label>
                            <input type="text" value="${cronCommand}" placeholder="e.g. workflow:process-timeouts"
                                style="width:100%; padding:8px 10px; border-radius:6px; border:1px solid var(--glass-border); background:var(--input-bg); color:var(--text-bright); font-family:monospace; font-size:0.85rem;"
                                @input=${(e) => this.setState({ cronCommand: e.target.value })}>
                        </div>

                        <div class="input-group">
                            <label style="font-size:0.8rem; color:var(--text-dim); margin-bottom:4px; display:block;">Command Arguments (Optional)</label>
                            <input type="text" value="${cronArgs}" placeholder="e.g. --app=DemoSchool --force"
                                style="width:100%; padding:8px 10px; border-radius:6px; border:1px solid var(--glass-border); background:var(--input-bg); color:var(--text-bright); font-family:monospace; font-size:0.85rem;"
                                @input=${(e) => this.setState({ cronArgs: e.target.value })}>
                        </div>

                        <div>
                            <button type="button" class="btn primary-btn shine-effect" style="padding:9px 18px;"
                                @click=${() => this.addScheduledTask()}>
                                + Schedule Task
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active Scheduled Tasks List -->
                <div class="glass-panel" style="padding:16px; border-radius:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h4 style="margin:0; color:var(--text-bright); font-size:1rem;">Active Scheduled Tasks (${scheduledTasks.length})</h4>
                        <button type="button" class="btn secondary-btn btn-sm" @click=${() => this.executeCommand('workflow:process-timeouts')}>
                            ⚡ Trigger Escalation Sweep Now
                        </button>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:8px;">
                        ${scheduledTasks.map(t => SPPUX.html`
                            <div style="background:var(--btn-soft-bg); padding:10px 14px; border-radius:8px; border:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; gap:12px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <span style="font-size:1.2rem;">${t.active ? '🟢' : '⏸️'}</span>
                                    <div>
                                        <div style="font-weight:600; color:var(--text-bright); font-family:monospace; font-size:0.9rem;">
                                            php spp.php ${t.command}
                                        </div>
                                        <div style="font-size:0.75rem; color:var(--text-dim); margin-top:2px;">
                                            <span style="color:#38bdf8; font-family:monospace; font-weight:600;">${t.expr}</span> · ${t.desc}
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex; align-items:center; gap:8px;">
                                    <button type="button" class="btn ghost-btn btn-sm" @click=${() => this.executeCommand(t.command)}>
                                        ▶ Test Run
                                    </button>
                                    <button type="button" class="btn secondary-btn btn-sm" @click=${() => this.toggleScheduledTask(t.id)}>
                                        ${t.active ? 'Pause' : 'Resume'}
                                    </button>
                                    <button type="button" class="btn btn-icon danger" style="padding:4px 8px;" @click=${() => this.removeScheduledTask(t.id)}>
                                        ✕
                                    </button>
                                </div>
                            </div>
                        `)}
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
