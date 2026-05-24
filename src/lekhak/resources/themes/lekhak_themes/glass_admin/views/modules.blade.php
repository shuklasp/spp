@extends('layout')

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
    <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
</div>

@if(isset($message))
    <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); padding: 15px; margin-bottom: 20px; color: var(--text-main); border-radius: 4px;">
        {{ $message }}
    </div>
@endif

<form method="POST" action="">
<div class="glass-panel" style="padding: 25px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--glass-border); padding-bottom: 15px;">
        <div>
            <select name="bulk_action" class="form-control" style="display: inline-block; width: auto; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--text-main); padding: 6px 12px; border-radius: 4px;">
                <option value="">- Bulk actions -</option>
                <option value="bulk_enable">Enable</option>
                <option value="bulk_disable">Disable</option>
                <option value="bulk_uninstall">Uninstall</option>
            </select>
            <button type="submit" name="action" value="bulk" class="btn btn-primary" style="margin-left: 10px; padding: 6px 15px;">Apply</button>
        </div>
        <div>
            <a href="/school1/lekhak/admin/modules/update" class="btn btn-secondary" style="border-color: var(--accent-primary); color: var(--accent-primary); text-decoration: none; padding: 6px 15px;">Run Database Updates</a>
        </div>
    </div>

    @foreach($groupedModules as $package => $modules)
        <div style="margin-bottom: 30px;">
            <h3 style="margin-top: 0; border-bottom: 2px solid var(--glass-border); padding-bottom: 10px; color: var(--accent-primary);">{{ $package }}</h3>
            
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 10px; width: 30px;">
                            <input type="checkbox" onclick="var checkboxes = this.closest('table').querySelectorAll('input[type=checkbox]'); for(var i=0; i<checkboxes.length; i++) { checkboxes[i].checked = this.checked; }">
                        </th>
                        <th style="padding: 10px; width: 30px;"></th>
                        <th style="padding: 10px; width: 20%; color: var(--text-dim); font-size: 0.85rem; text-transform: uppercase;">Name</th>
                        <th style="padding: 10px; width: 10%; color: var(--text-dim); font-size: 0.85rem; text-transform: uppercase;">Version</th>
                        <th style="padding: 10px; width: 40%; color: var(--text-dim); font-size: 0.85rem; text-transform: uppercase;">Description</th>
                        <th style="padding: 10px; width: 20%; color: var(--text-dim); font-size: 0.85rem; text-transform: uppercase; text-align: right;">Operations</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($modules as $machineName => $module)
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <td style="padding: 12px 10px;">
                            @if($machineName !== 'lekhak')
                                <input type="checkbox" name="modules[]" value="{{ $machineName }}">
                            @endif
                        </td>
                        <td style="padding: 12px 10px;">
                            @php
                                $statusColor = 'var(--danger)'; // Red for uninstalled
                                $statusTitle = 'Uninstalled';
                                if ($module['status']) {
                                    $statusColor = 'var(--success)'; // Green for enabled
                                    $statusTitle = 'Enabled';
                                } elseif ($module['installed']) {
                                    $statusColor = 'var(--glass-border)'; // Gray for disabled
                                    $statusTitle = 'Disabled';
                                }
                            @endphp
                            <div title="{{ $statusTitle }}" style="width: 12px; height: 12px; border-radius: 50%; background: {{ $statusColor }};"></div>
                        </td>
                        <td style="padding: 12px 10px;">
                            <strong style="color: var(--text-main); font-size: 1rem;">{{ $module['name'] }}</strong><br>
                            <span style="font-family: monospace; font-size: 0.75rem; color: var(--text-dim);">Machine name: {{ $machineName }}</span>
                        </td>
                        <td style="padding: 12px 10px; color: var(--text-dim); font-size: 0.85rem;">
                            {{ $module['version'] }}
                        </td>
                        <td style="padding: 12px 10px; color: var(--text-dim); font-size: 0.85rem;">
                            <div style="margin-bottom: 4px;">{{ $module['description'] }}</div>
                            @if(!empty($module['dependencies']))
                                <div style="font-size: 0.75rem; color: var(--accent-primary); margin-top: 5px;">
                                    <strong>Requires:</strong> {{ implode(', ', $module['dependencies']) }}
                                </div>
                            @endif
                        </td>
                        <td style="padding: 12px 10px; text-align: right; white-space: nowrap;">
                            <div style="display: flex; justify-content: flex-end; gap: 6px;">
                            @if($machineName === 'lekhak')
                                <span style="color: var(--text-dim); font-size: 0.8rem; font-style: italic;">Required Core Module</span>
                            @else
                                    @if($module['status'])
                                        @if(!empty($module['configure']))
                                            <a href="/school1/lekhak/{{ ltrim($module['configure'], '/') }}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 2px 8px; border-color: var(--glass-border); color: var(--text-main); text-decoration: none;" title="Configure">⚙️ Config</a>
                                        @endif
                                        <button type="submit" name="action" value="disable" formaction="?module={{ $machineName }}&action=disable" class="btn btn-secondary" style="font-size: 0.75rem; padding: 2px 8px; border-color: var(--danger); color: var(--danger);">Disable</button>
                                        <button type="submit" name="action" value="uninstall" formaction="?module={{ $machineName }}&action=uninstall" class="btn btn-secondary" style="font-size: 0.75rem; padding: 2px 8px; border-color: var(--danger); color: var(--danger);" onclick="return confirm('Are you sure you want to uninstall this module? All associated data will be deleted.');">Uninstall</button>
                                    @elseif($module['installed'])
                                        <button type="submit" name="action" value="enable" formaction="?module={{ $machineName }}&action=enable" class="btn btn-primary" style="font-size: 0.75rem; padding: 2px 8px;">Enable</button>
                                        <button type="submit" name="action" value="uninstall" formaction="?module={{ $machineName }}&action=uninstall" class="btn btn-secondary" style="font-size: 0.75rem; padding: 2px 8px; border-color: var(--danger); color: var(--danger);" onclick="return confirm('Are you sure you want to uninstall this module? All associated data will be deleted.');">Uninstall</button>
                                    @else
                                        <button type="submit" name="action" value="enable" formaction="?module={{ $machineName }}&action=enable" class="btn btn-primary" style="font-size: 0.75rem; padding: 2px 8px;">Install & Enable</button>
                                    @endif
                            @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
</form>
@endsection
