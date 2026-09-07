@extends('layouts.base')
@section('title', $forum['title'] . ' - Forums')

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => 'v1', 'current_path' => null])
@endsection

@section('content')
    <div style="margin-bottom: 2rem;">
        <a href="{{ \SPP\App::getBaseUrl() }}/forums?projectId={{ $project_id }}" style="text-decoration: none; font-size: 0.9rem;">&larr; Back to Forums</a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 1rem; margin-bottom: 2rem;">
        <div>
            <h1 style="margin: 0;">{{ $forum['title'] }}</h1>
            <p style="margin: 0.5rem 0 0 0; color: var(--vp-c-text-2);">{{ $forum['description'] }}</p>
        </div>
    </div>
    
    <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 3rem;">
        @foreach($threads as $thread)
            <div style="border: 1px solid var(--vp-c-divider); border-radius: 8px; padding: 1rem; background: var(--vp-c-bg);">
                <h3 style="margin: 0 0 0.5rem 0;">
                    <a href="{{ \SPP\App::getBaseUrl() }}/forums/thread?projectId={{ $project_id }}&forumSlug={{ $slug }}&threadId={{ $thread['id'] }}" style="text-decoration: none;">{{ $thread['title'] }}</a>
                </h3>
                <div style="font-size: 0.85rem; color: var(--vp-c-text-3); display: flex; gap: 1rem;">
                    <span>By <strong>{{ $thread['author'] }}</strong></span>
                    <span>{{ date('M j, Y g:i A', $thread['timestamp']) }}</span>
                    <span>{{ $thread['reply_count'] }} replies</span>
                </div>
            </div>
        @endforeach
        
        @if(empty($threads))
            <p style="text-align: center; color: var(--vp-c-text-3); padding: 2rem; border: 1px dashed var(--vp-c-divider); border-radius: 8px;">No threads have been posted here yet.</p>
        @endif
    </div>
    
    @if($can_post)
        <div style="background: var(--vp-c-bg-soft); padding: 2rem; border-radius: 8px; border: 1px solid var(--vp-c-divider);">
            <h3 style="margin-top: 0;">Create a new thread</h3>
            <form action="{{ \SPP\App::getBaseUrl() }}/forums/post" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                <input type="hidden" name="project_id" value="{{ $project_id }}">
                <input type="hidden" name="forum_slug" value="{{ $slug }}">
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Thread Title</label>
                    <input type="text" name="title" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; box-sizing: border-box; font-family: inherit;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Message</label>
                    <textarea name="content" rows="5" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 4px; box-sizing: border-box; font-family: inherit; resize: vertical;"></textarea>
                </div>
                
                <div>
                    <button type="submit" style="background: var(--vp-c-brand); color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 4px; font-weight: 500; cursor: pointer;">Post Thread</button>
                </div>
            </form>
        </div>
    @else
        <div style="background: var(--vp-c-bg-soft); padding: 1rem; border-radius: 8px; text-align: center; color: var(--vp-c-text-2);">
            You must be <a href="@url('login')?redirect={{ urlencode($_SERVER['REQUEST_URI'] ?? '') }}" style="color: var(--vp-c-brand); font-weight: 500; text-decoration: underline;">logged in</a> to create a thread.
        </div>
    @endif
@endsection
