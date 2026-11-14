@extends('layouts.app')

@section('title', 'Notification Center - SPPDocs')

@section('content')
<style>
.spp-notif-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}
.spp-notif-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.spp-notif-title-group h1 {
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 6px 0;
}
.spp-notif-title-group p {
    color: #64748b;
    margin: 0;
    font-size: 14px;
}
.spp-notif-badge-pill {
    display: inline-block;
    background: #4f46e5;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 9999px;
    margin-left: 8px;
}
.spp-notif-btn-allread {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.spp-notif-btn-allread:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}
.spp-notif-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.spp-notif-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.15s ease;
}
.spp-notif-card.unread {
    border-left: 4px solid #4f46e5;
    background: #fcfdff;
}
.spp-notif-left {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    min-width: 0;
}
.spp-notif-icon {
    font-size: 22px;
    line-height: 1;
    padding-top: 2px;
}
.spp-notif-body {
    display: flex;
    flex-direction: column;
}
.spp-notif-header {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}
.spp-notif-title {
    font-weight: 600;
    color: #0f172a;
    font-size: 15px;
}
.spp-notif-author {
    font-size: 13px;
    color: #475569;
}
.spp-notif-time {
    font-size: 12px;
    color: #94a3b8;
}
.spp-notif-text {
    margin: 0;
    color: #475569;
    font-size: 13px;
    line-height: 1.5;
}
.spp-notif-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    margin-left: 16px;
}
.spp-notif-btn-view {
    background: #4f46e5;
    color: #ffffff;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s ease;
}
.spp-notif-btn-view:hover {
    background: #4338ca;
}
.spp-notif-btn-read {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
}
.spp-notif-btn-read:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.spp-notif-empty {
    text-align: center;
    padding: 60px 20px;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    color: #64748b;
}
.spp-notif-empty h3 {
    margin: 0 0 6px 0;
    color: #0f172a;
}
.spp-notif-empty p {
    margin: 0;
}
</style>

<div class="spp-notif-container">
    <div class="spp-notif-header-row">
        <div class="spp-notif-title-group">
            <h1>Notifications @if($unreadCount > 0)<span class="spp-notif-badge-pill">{{ $unreadCount }} new</span>@endif</h1>
            <p>Mentions, peer reviews, quizzes, and course updates for <strong>{{ $username }}</strong></p>
        </div>
        @if($unreadCount > 0)
            <form action="{{ \SPP\App::getBaseUrl() }}/notifications/read-all" method="POST">
                <button type="submit" class="spp-notif-btn-allread">Mark all as read</button>
            </form>
        @endif
    </div>

    <div id="spp-notif-list-container" class="spp-notif-list">
        @spppartial('notifications/partials/notification_list.blade.php', ['notifications' => $notifications])
    </div>
</div>
@endsection
