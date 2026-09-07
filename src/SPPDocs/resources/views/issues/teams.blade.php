@extends('layouts.base')
@section('title', 'Teams - ' . ($project['title'] ?? 'Project'))

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@section('content')
<div style="max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ \SPP\App::url('issues') }}?projectId={{ $project_id }}" style="color: var(--vp-c-text-3); text-decoration: none; font-size: 0.85rem;">← Back to Issues</a>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap;">
                <h1 style="margin: 0; font-size: 1.75rem;">👥 Teams</h1>
                @if(!empty($all_projects) && count($all_projects) > 1)
                    <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: var(--vp-c-bg-mute); border: 1px solid var(--vp-c-divider); padding: 0.25rem 0.6rem; border-radius: 20px;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--vp-c-text-2); text-transform: uppercase;">Project:</span>
                        <select onchange="window.location.href='{{ \SPP\App::url('issues/teams') }}?projectId=' + encodeURIComponent(this.value)" style="border: none; background: transparent; color: var(--vp-c-text-1); font-weight: 600; font-size: 0.85rem; cursor: pointer; outline: none;">
                            @foreach($all_projects as $pKey => $pCfg)
                                <option value="{{ $pKey }}" {{ $pKey === $project_id ? 'selected' : '' }}>
                                    📁 {{ $pCfg['title'] ?? $pKey }} ({{ $pKey }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <span style="font-size: 0.85rem; font-weight: 600; background: var(--vp-c-bg-mute); border: 1px solid var(--vp-c-divider); padding: 0.25rem 0.6rem; border-radius: 20px; color: var(--vp-c-text-1);">
                        📁 {{ $project['title'] ?? $project_id }}
                    </span>
                @endif
            </div>
            <p style="margin: 0.35rem 0 0 0; color: var(--vp-c-text-2); font-size: 0.85rem;">
                Team rosters and member assignments for <strong>{{ $project['title'] ?? $project_id }}</strong>
            </p>
        </div>
        @if($user && $user['role'] === 'admin')
            <button onclick="document.getElementById('create-team-form').style.display = document.getElementById('create-team-form').style.display === 'none' ? 'block' : 'none'" style="padding: 0.6rem 1.2rem; background: #22c55e; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.85rem;">+ New Team</button>
        @endif
    </div>

    {{-- Create Team Form --}}
    @if($user && $user['role'] === 'admin')
    <div id="create-team-form" style="display: none; margin-bottom: 2rem; padding: 1.5rem; background: var(--vp-c-bg-soft); border-radius: 8px; border: 1px solid var(--vp-c-divider);">
        <h3 style="margin: 0 0 1rem 0; border: none;">Create New Team</h3>
        <form action="{{ \SPP\App::getBaseUrl() }}/issues/teams/save" method="POST">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <div style="margin-bottom: 1rem;">
                <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Team Name *</label>
                <input type="text" name="team_name" required placeholder="e.g. Backend Team" style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.25rem;">Members</label>
                <select name="members[]" multiple style="width: 100%; padding: 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; min-height: 80px;">
                    @foreach($registered_users as $u)
                        <option value="{{ $u }}">{{ $u }}</option>
                    @endforeach
                </select>
                <span style="font-size: 0.75rem; color: var(--vp-c-text-3);">Hold Ctrl/Cmd to select multiple members</span>
            </div>
            <button type="submit" style="padding: 0.6rem 1.5rem; background: #22c55e; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Create Team</button>
        </form>
    </div>
    @endif

    {{-- Teams List --}}
    @forelse($teams as $tid => $team)
        <div style="margin-bottom: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider);">
                <h3 style="margin: 0; border: none; font-size: 1.1rem;">{{ $team['name'] }}</h3>
                <span style="font-size: 0.8rem; color: var(--vp-c-text-3);">{{ count($team['members'] ?? []) }} members</span>
            </div>
            <div style="padding: 1rem;">
                @if(!empty($team['members']))
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                        @foreach($team['members'] as $member)
                            <div style="display: flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.6rem; background: var(--vp-c-bg-soft); border-radius: 6px;">
                                <span style="width: 22px; height: 22px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700;">{{ strtoupper(substr($member, 0, 1)) }}</span>
                                <span style="font-size: 0.85rem;">{{ $member }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p style="color: var(--vp-c-text-3); font-size: 0.85rem;">No members yet.</p>
                @endif

                @if($user && $user['role'] === 'admin')
                <div style="display: flex; gap: 0.5rem;">
                    <details>
                        <summary style="font-size: 0.8rem; color: var(--vp-c-brand); cursor: pointer; padding: 0.3rem 0.6rem; border: 1px solid var(--vp-c-divider); border-radius: 4px;">Edit Members</summary>
                        <form action="{{ \SPP\App::getBaseUrl() }}/issues/teams/save" method="POST" style="margin-top: 0.5rem;">
                            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                            <input type="hidden" name="project_id" value="{{ $project_id }}">
                            <input type="hidden" name="team_id" value="{{ $tid }}">
                            <input type="hidden" name="team_name" value="{{ $team['name'] }}">
                            <select name="members[]" multiple style="width: 100%; padding: 0.4rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; font-size: 0.8rem; min-height: 60px;">
                                @foreach($registered_users as $u)
                                    <option value="{{ $u }}" {{ in_array($u, $team['members'] ?? []) ? 'selected' : '' }}>{{ $u }}</option>
                                @endforeach
                            </select>
                            <button type="submit" style="margin-top: 0.35rem; padding: 0.35rem 0.75rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Save</button>
                        </form>
                    </details>
                    <form action="{{ \SPP\App::getBaseUrl() }}/issues/teams/delete" method="POST" onsubmit="return confirm('Delete this team?')">
                        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                        <input type="hidden" name="project_id" value="{{ $project_id }}">
                        <input type="hidden" name="team_id" value="{{ $tid }}">
                        <button type="submit" style="font-size: 0.8rem; padding: 0.3rem 0.6rem; background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; border-radius: 4px; cursor: pointer;">Delete</button>
                    </form>
                </div>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align: center; padding: 3rem; color: var(--vp-c-text-3); background: var(--vp-c-bg-soft); border-radius: 8px;">
            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No teams created yet.</p>
            <p style="font-size: 0.9rem;">Create a team to assign issues to groups of people.</p>
        </div>
    @endforelse
</div>
@endsection
