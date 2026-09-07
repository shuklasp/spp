@extends('layouts.base')
@section('title', $thread['title'] . ' - ' . $forum['title'])

@section('sidebar')
    @spppartial('partials/sidebar.blade.php', ['project' => $project, 'project_id' => $project_id, 'version' => 'v1', 'current_path' => null])
@endsection

@section('content')
    <div style="margin-bottom: 2rem;">
        <a href="{{ \SPP\App::getBaseUrl() }}/forums/forum?projectId={{ $project_id }}&forumSlug={{ $slug }}" style="text-decoration: none; font-size: 0.9rem; color: var(--vp-c-text-2); display: inline-flex; align-items: center; gap: 0.5rem; transition: color 0.2s;" onmouseover="this.style.color='var(--vp-c-brand)'" onmouseout="this.style.color='var(--vp-c-text-2)'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to {{ $forum['title'] }}
        </a>
    </div>

    <h1 style="margin-top: 0; margin-bottom: 2.5rem; font-size: 2.25rem; font-weight: 800; letter-spacing: -0.02em; color: var(--vp-c-text-1);">{{ $thread['title'] }}</h1>
    
    <!-- Original Post -->
    <div style="border: 1px solid var(--vp-c-brand-light); border-radius: 12px; margin-bottom: 3rem; overflow: hidden; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.08);">
        <div style="background: rgba(234, 88, 12, 0.03); padding: 1.25rem 2rem; border-bottom: 1px solid rgba(234, 88, 12, 0.1); display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, var(--vp-c-brand), var(--vp-c-brand-light)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem; box-shadow: 0 2px 6px rgba(234,88,12,0.3);">
                    {{ strtoupper(substr($thread['author'], 0, 1)) }}
                </div>
                <div>
                    <div style="font-weight: 700; color: var(--vp-c-brand); font-size: 1.05rem;">{{ $thread['author'] }}</div>
                    <div style="font-size: 0.85rem; color: var(--vp-c-text-3); display: flex; align-items: center; gap: 0.35rem; margin-top: 0.2rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        {{ date('M j, Y g:i A', $thread['timestamp']) }}
                    </div>
                </div>
            </div>
            <div style="background: rgba(234,88,12,0.1); color: var(--vp-c-brand); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Original Post</div>
        </div>
        <div style="padding: 2rem; background: var(--vp-c-bg); white-space: pre-wrap; line-height: 1.7; font-size: 1.05rem; color: var(--vp-c-text-1);">{{ $thread['content'] }}</div>
    </div>
    
    <!-- Replies -->
    @if(!empty($thread['replies']))
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
            <h3 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--vp-c-text-1);">Replies <span style="background: var(--vp-c-bg-soft); color: var(--vp-c-text-2); padding: 0.15rem 0.6rem; border-radius: 999px; font-size: 1rem; font-weight: 600; margin-left: 0.5rem;">{{ count($thread['replies']) }}</span></h3>
            <div style="flex: 1; height: 1px; background: var(--vp-c-divider);"></div>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 4rem;">
            @foreach($thread['replies'] as $idx => $reply)
                @php
                    $isSolution = (($thread['solved_reply_index'] ?? -1) === $idx);
                    $reactions = $reply['reactions'] ?? [];
                @endphp
                <div style="border: {{ $isSolution ? '2px solid #22c55e' : '1px solid var(--vp-c-divider)' }}; border-radius: 12px; overflow: hidden; background: var(--vp-c-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.2s, box-shadow 0.2s;">
                    @if($isSolution)
                        <div style="background: #22c55e; color: white; padding: 0.4rem 1.5rem; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; gap: 0.5rem; letter-spacing: 0.03em;">
                            <span>✓</span> ACCEPTED SOLUTION
                        </div>
                    @endif
                    <div style="background: var(--vp-c-bg-alt); padding: 1rem 1.5rem; border-bottom: 1px solid var(--vp-c-divider); display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 0.85rem;">
                            <div style="width: 36px; height: 36px; border-radius: 10px; background: var(--vp-c-divider); color: var(--vp-c-text-2); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem;">
                                {{ strtoupper(substr($reply['author'], 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--vp-c-text-1);">{{ $reply['author'] }}</div>
                                <div style="font-size: 0.8rem; color: var(--vp-c-text-3); margin-top: 0.1rem;">{{ date('M j, Y g:i A', $reply['timestamp']) }}</div>
                            </div>
                        </div>

                        {{-- Mark as Solution Toggle --}}
                        <form action="{{ \SPP\App::getBaseUrl() }}/forums/solution" method="POST" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                            <input type="hidden" name="project_id" value="{{ $project_id }}">
                            <input type="hidden" name="forum_slug" value="{{ $slug }}">
                            <input type="hidden" name="thread_id" value="{{ $thread['id'] }}">
                            <input type="hidden" name="reply_index" value="{{ $idx }}">
                            <button type="submit" style="background: {{ $isSolution ? '#22c55e' : 'var(--vp-c-bg)' }}; color: {{ $isSolution ? '#fff' : 'var(--vp-c-text-2)' }}; border: 1px solid {{ $isSolution ? '#22c55e' : 'var(--vp-c-divider)' }}; border-radius: 6px; padding: 0.3rem 0.65rem; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                {{ $isSolution ? '✓ Solved' : 'Mark as Solution' }}
                            </button>
                        </form>
                    </div>

                    <div style="padding: 1.5rem; white-space: pre-wrap; line-height: 1.6; color: var(--vp-c-text-1);">{{ $reply['content'] }}</div>

                    {{-- Reactions Bar --}}
                    <div style="padding: 0.5rem 1.5rem 0.85rem; border-top: 1px solid var(--vp-c-divider); display: flex; gap: 0.5rem; align-items: center;">
                        @foreach(['👍', '❤️', '🚀'] as $emoji)
                            <form action="{{ \SPP\App::getBaseUrl() }}/forums/react" method="POST" style="margin: 0; display: inline;">
                                <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                                <input type="hidden" name="project_id" value="{{ $project_id }}">
                                <input type="hidden" name="forum_slug" value="{{ $slug }}">
                                <input type="hidden" name="thread_id" value="{{ $thread['id'] }}">
                                <input type="hidden" name="reply_index" value="{{ $idx }}">
                                <input type="hidden" name="emoji" value="{{ $emoji }}">
                                <button type="submit" style="background: var(--vp-c-bg-alt); border: 1px solid var(--vp-c-divider); border-radius: 16px; padding: 0.2rem 0.5rem; font-size: 0.75rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; color: var(--vp-c-text-2);">
                                    <span>{{ $emoji }}</span>
                                    <span>{{ $reactions[$emoji] ?? 0 }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    
    @if($can_post)
        <div style="background: var(--vp-c-bg-alt); padding: 2.5rem; border-radius: 12px; border: 1px solid var(--vp-c-divider); margin-top: 2rem; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--vp-c-brand), var(--vp-c-brand-light));"></div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--vp-c-brand)" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <h3 style="margin: 0; font-size: 1.25rem;">Join the Conversation</h3>
            </div>
            
            <form action="{{ \SPP\App::getBaseUrl() }}/forums/reply" method="POST" style="display: flex; flex-direction: column; gap: 1.25rem;">
                <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
                <input type="hidden" name="project_id" value="{{ $project_id }}">
                <input type="hidden" name="forum_slug" value="{{ $slug }}">
                <input type="hidden" name="thread_id" value="{{ $thread['id'] }}">
                
                <div>
                    <textarea name="content" rows="4" required style="width: 100%; padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px; box-sizing: border-box; font-family: inherit; font-size: 1rem; resize: vertical; background: var(--vp-c-bg); color: var(--vp-c-text-1); outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='var(--vp-c-brand)'" onblur="this.style.borderColor='var(--vp-c-divider)'" placeholder="Type your reply here..."></textarea>
                </div>
                
                <div>
                    <button type="submit" style="background: linear-gradient(135deg, var(--vp-c-brand), var(--vp-c-brand-light)); color: #fff; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; font-size: 1rem; font-family: inherit; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; display: inline-flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(234,88,12,0.3)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
                        Post Reply
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
            </form>
        </div>
    @else
        <div style="background: var(--vp-c-bg-soft); padding: 2rem; border-radius: 12px; border: 1px dashed var(--vp-c-brand-light); text-align: center; color: var(--vp-c-text-2); margin-top: 2rem; display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--vp-c-brand)" stroke-width="2" style="opacity: 0.5;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <div>
                You must be <a href="@url('login')?redirect={{ urlencode($_SERVER['REQUEST_URI'] ?? '') }}" style="color: var(--vp-c-brand); font-weight: 600; text-decoration: none; padding-bottom: 2px; border-bottom: 2px solid var(--vp-c-brand);">logged in</a> to post a reply.
            </div>
        </div>
    @endif
@endsection
