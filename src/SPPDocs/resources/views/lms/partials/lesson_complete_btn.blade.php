@php
    $minWatchPercent = (int)($min_watch_percent ?? 0);
    $requiresWatch = !$is_completed && $minWatchPercent > 0;
@endphp
<div id="lesson-complete-wrapper" style="display: inline-flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
    @if($is_completed)
        <form hx-post="@url('lms/lesson/toggle')" hx-target="#lesson-complete-wrapper" hx-swap="outerHTML" style="margin: 0; display: inline;">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <input type="hidden" name="course_id" value="{{ $course['id'] }}">
            <input type="hidden" name="lesson_id" value="{{ $lesson_id }}">
            <input type="hidden" name="action" value="incomplete">
            <button type="submit" 
                    class="adm-btn" 
                    style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.6rem 1.1rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;"
                    title="Click to mark incomplete">
                <span>✅ Completed</span>
                <span style="font-size: 0.75rem; color: var(--vp-c-text-3); font-weight: normal;">(undo)</span>
            </button>
        </form>
    @else
        <form hx-post="@url('lms/lesson/toggle')" hx-target="#lesson-complete-wrapper" hx-swap="outerHTML" style="margin: 0; display: inline;">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">
            <input type="hidden" name="course_id" value="{{ $course['id'] }}">
            <input type="hidden" name="lesson_id" value="{{ $lesson_id }}">
            <input type="hidden" name="action" value="complete">
            <button type="submit" 
                    id="lesson-complete-btn"
                    class="adm-btn" 
                    @if($requiresWatch) disabled data-locked="true" data-min-percent="{{ $minWatchPercent }}" @endif
                    style="background: {{ $requiresWatch ? '#94a3b8' : 'var(--vp-c-brand)' }}; color: #ffffff; border: none; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; cursor: {{ $requiresWatch ? 'not-allowed' : 'pointer' }}; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: {{ $requiresWatch ? 'none' : '0 4px 8px rgba(234, 88, 12, 0.25)' }}; transition: all 0.2s ease;">
                <span id="lesson-complete-btn-label">{{ $requiresWatch ? '🔒 Watch ' . $minWatchPercent . '% to Unlock' : 'Mark as Completed' }}</span>
                <span>✓</span>
            </button>
        </form>
        @if($requiresWatch)
            <span id="video-watch-status" style="font-size: 0.8rem; color: var(--vp-c-text-2); display: inline-flex; align-items: center; gap: 0.25rem;">
                <span>⏱️ Video Progress:</span>
                <strong id="video-watch-percent">0%</strong> / {{ $minWatchPercent }}%
            </span>
        @endif
    @endif

    @if(!empty($next_lesson))
        <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $next_lesson['id'])" 
           class="adm-btn adm-btn-secondary" 
           style="padding: 0.6rem 1.1rem; font-weight: 600; font-size: 0.9rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
            <span>Next Lesson</span>
            <span>&rarr;</span>
        </a>
    @elseif(($progress['percentage'] ?? 0) >= 100)
        <a href="@url('lms/certificate?projectId=' . $project_id . '&code=' . ($progress['certificate_id'] ?? ''))" 
           style="padding: 0.6rem 1.1rem; background: #10b981; color: #fff; font-weight: 700; font-size: 0.9rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
            <span>🏆 View Certificate</span>
        </a>
    @endif
</div>
