@extends('layouts.base')
@section('title', 'Notifications')

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => $project['default_version'] ?? 'v1', 'current_path' => null])
@endsection

@php
    $typeIcons = ['assigned' => '👤', 'commented' => '💬', 'closed' => '✅', 'mentioned' => '@', 'merged' => '🔀', 'moved' => '➡️'];
    $unread = count(array_filter($notifications, fn($n) => empty($n['read'])));
@endphp

@section('content')
<div style="max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h1 style="margin: 0; font-size: 1.75rem;">🔔 Notifications <span style="font-size: 1rem; color: var(--vp-c-text-3);">({{ $unread }} unread)</span></h1>
        @if($unread > 0)
        <form action="{{ \SPP\App::getBaseUrl() }}/notifications/read-all" method="POST">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <button type="submit" style="padding: 0.5rem 1rem; background: var(--vp-c-brand); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">Mark all read</button>
        </form>
        @endif
    </div>

    <div style="border: 1px solid var(--vp-c-divider); border-radius: 10px; overflow: hidden;">
        @forelse($notifications as $notif)
        <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem 1rem; border-bottom: 1px solid var(--vp-c-divider); {{ empty($notif['read']) ? 'background: #eff6ff;' : '' }}">
            <span style="font-size: 1.2rem; margin-top: 0.1rem;">{{ $typeIcons[$notif['type'] ?? ''] ?? '📌' }}</span>
            <div style="flex: 1;">
                <a href="{{ $notif['link'] ?? '#' }}" style="font-size: 0.9rem; color: var(--vp-c-text-1); text-decoration: none; font-weight: {{ empty($notif['read']) ? '600' : '400' }};">{{ $notif['message'] }}</a>
                <div style="font-size: 0.75rem; color: var(--vp-c-text-3); margin-top: 0.15rem;">{{ date('M j, g:i A', $notif['timestamp'] ?? 0) }}</div>
            </div>
            @if(empty($notif['read']))
            <form action="{{ \SPP\App::getBaseUrl() }}/notifications/read" method="POST" style="flex-shrink: 0;">
                <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                <input type="hidden" name="project_id" value="{{ $project_id }}">
                <input type="hidden" name="notification_id" value="{{ $notif['id'] }}">
                <button type="submit" style="padding: 0.25rem 0.5rem; background: transparent; border: 1px solid var(--vp-c-divider); border-radius: 4px; cursor: pointer; font-size: 0.7rem; color: var(--vp-c-text-3);">✓</button>
            </form>
            @endif
        </div>
        @empty
        <div style="padding: 3rem; text-align: center; color: var(--vp-c-text-3);">
            <p style="font-size: 1.1rem;">🔕 No notifications yet.</p>
            <p style="font-size: 0.9rem;">You'll be notified when you're assigned, mentioned, or when your issues are updated.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
