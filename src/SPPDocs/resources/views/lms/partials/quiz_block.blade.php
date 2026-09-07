@php
    $status = $quiz_status ?? \App\SPPDocs\Services\LmsService::getQuizStatus($quiz, $quiz_attempt ?? null);
    $timeLimit = (int)($quiz['time_limit_minutes'] ?? 0);
    $canAttempt = $status['can_attempt'] ?? true;
@endphp
<div id="quiz-container" style="background: var(--vp-c-bg); border: 2px solid var(--vp-c-divider); border-radius: 14px; padding: 2rem; margin-top: 2.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #8b5cf6; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                Interactive Assessment
            </div>
            <h3 style="margin: 0 0 0.35rem 0; font-size: 1.4rem; font-weight: 800; color: var(--vp-c-text-1);">
                {{ $quiz['title'] ?? 'Knowledge Check' }}
            </h3>
            @if(!empty($quiz['description']))
                <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.92rem;">
                    {{ $quiz['description'] }}
                </p>
            @endif
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            @if($timeLimit > 0 && $canAttempt)
                <span id="quiz-timer-chip" role="timer" aria-live="polite" aria-atomic="true" style="font-size: 0.8rem; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 9999px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.25); display: inline-flex; align-items: center; gap: 0.35rem;">
                    <span>⏳</span> <span id="quiz-time-display">{{ sprintf('%02d:00', $timeLimit) }}</span>
                    <span id="quiz-timer-sr" style="position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); border: 0;" aria-live="assertive"></span>
                </span>
            @endif
            @if(!empty($status['max_attempts']))
                <span style="font-size: 0.8rem; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25);">
                    Attempt {{ $status['attempts_count'] ?? 0 }} / {{ $status['max_attempts'] }}
                </span>
            @endif
            <span style="font-size: 0.8rem; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 9999px; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.25);">
                Passing: {{ $quiz['passing_score'] ?? 80 }}%
            </span>
        </div>
    </div>

    @if(!empty($status['is_passed']))
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.5rem;">🎉</span>
            <div>
                <strong style="color: #10b981; font-size: 0.95rem;">Assessment Passed!</strong>
                <p style="margin: 0.1rem 0 0 0; font-size: 0.85rem; color: var(--vp-c-text-2);">
                    You cleared this evaluation with score {{ $quiz_attempt['score'] ?? 100 }}%. You may retake it below to improve your score.
                </p>
            </div>
        </div>
    @elseif(!$canAttempt && !empty($status['in_cooldown']))
        <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.5rem;">⏳</span>
            <div>
                <strong style="color: #d97706; font-size: 0.95rem;">Cooldown Active</strong>
                <p style="margin: 0.1rem 0 0 0; font-size: 0.85rem; color: var(--vp-c-text-2);">
                    Please review lesson material. Your next attempt unlocks in 
                    <strong>{{ ceil($status['cooldown_remaining_seconds'] / 60) }} minute(s)</strong>.
                </p>
            </div>
        </div>
    @elseif(!$canAttempt)
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.5rem;">🚫</span>
            <div>
                <strong style="color: #ef4444; font-size: 0.95rem;">Maximum Attempts Exceeded</strong>
                <p style="margin: 0.1rem 0 0 0; font-size: 0.85rem; color: var(--vp-c-text-2);">
                    You have reached the maximum allowed attempts ({{ $status['max_attempts'] }}). Please contact your instructor to request an attempt reset.
                </p>
            </div>
        </div>
    @endif

    <form id="quiz-form" hx-post="@url('lms/quiz/submit')" hx-target="#quiz-container" hx-swap="outerHTML" style="margin: 0;">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">
        <input type="hidden" name="course_id" value="{{ $course['id'] }}">
        <input type="hidden" name="lesson_id" value="{{ $lesson['id'] }}">

        <div style="display: flex; flex-direction: column; gap: 1.75rem; margin-bottom: 2rem;">
            @foreach($quiz['questions'] ?? [] as $qIndex => $q)
                @php
                    $qType = $q['type'] ?? 'multiple_choice';
                @endphp
                <div style="background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 10px; padding: 1.25rem;">
                    <div style="font-weight: 700; font-size: 1rem; color: var(--vp-c-text-1); margin-bottom: 0.85rem; display: flex; gap: 0.5rem; align-items: flex-start;">
                        <span style="color: #8b5cf6;">{{ $qIndex + 1 }}.</span>
                        <span>{{ $q['prompt'] }}</span>
                    </div>

                    @if($qType === 'multiple_choice')
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            @foreach($q['options'] ?? [] as $opt)
                                <label style="display: flex; align-items: center; gap: 0.65rem; padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); cursor: pointer; font-size: 0.92rem; color: var(--vp-c-text-1); transition: border-color 0.15s;">
                                    <input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $opt['id'] }}" required style="accent-color: #8b5cf6; transform: scale(1.15);">
                                    <span>{{ $opt['text'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif($qType === 'multi_select')
                        <div style="font-size: 0.78rem; color: var(--vp-c-text-3); font-style: italic; margin-bottom: 0.5rem;">
                            (Select all options that apply)
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            @foreach($q['options'] ?? [] as $opt)
                                <label style="display: flex; align-items: center; gap: 0.65rem; padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); cursor: pointer; font-size: 0.92rem; color: var(--vp-c-text-1);">
                                    <input type="checkbox" name="answers[{{ $q['id'] }}][]" value="{{ $opt['id'] }}" style="accent-color: #8b5cf6; transform: scale(1.15);">
                                    <span>{{ $opt['text'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif($qType === 'code_check')
                        @if(!empty($q['code']))
                            <pre style="background: #1e1e2e; color: #f8f8f2; padding: 0.85rem 1rem; border-radius: 8px; font-family: monospace; font-size: 0.9rem; margin: 0 0 0.85rem 0; overflow-x: auto;"><code>{{ $q['code'] }}</code></pre>
                        @endif
                        <div style="display: flex; gap: 0.75rem; align-items: center;">
                            <input type="text" 
                                   name="answers[{{ $q['id'] }}]" 
                                   placeholder="Type expected code or identifier..." 
                                   required 
                                   style="flex: 1; padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--vp-c-divider); background: var(--vp-c-bg); color: var(--vp-c-text-1); font-family: monospace; font-size: 0.95rem;">
                            @if(!empty($q['hint']))
                                <span style="font-size: 0.8rem; color: var(--vp-c-text-3); font-style: italic;">
                                    💡 Hint: {{ $q['hint'] }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div style="display: flex; justify-content: flex-end;">
            @if($canAttempt)
                <button type="submit" 
                        id="quiz-submit-btn"
                        class="adm-btn" 
                        style="background: #8b5cf6; color: #ffffff; border: none; padding: 0.75rem 1.75rem; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3);">
                    <span>Submit Assessment</span>
                    <span>&rarr;</span>
                </button>
            @else
                <button type="button" 
                        disabled
                        class="adm-btn" 
                        style="background: #94a3b8; color: #ffffff; border: none; padding: 0.75rem 1.75rem; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: not-allowed; opacity: 0.6; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <span>Assessment Locked</span>
                </button>
            @endif
        </div>
    </form>

    @if($timeLimit > 0 && $canAttempt)
        <script>
        (function() {
            let totalSeconds = {{ $timeLimit * 60 }};
            const display = document.getElementById('quiz-time-display');
            const form = document.getElementById('quiz-form');
            const srAnnounce = document.getElementById('quiz-timer-sr');
            if (!display || !form) return;

            const timer = setInterval(function() {
                totalSeconds--;
                if (totalSeconds <= 0) {
                    clearInterval(timer);
                    display.textContent = '00:00';
                    display.style.color = '#dc2626';
                    if (srAnnounce) srAnnounce.textContent = 'Time expired. Submitting assessment automatically.';
                    // Auto-submit via HTMX
                    if (window.htmx) {
                        window.htmx.trigger(form, 'submit');
                    } else {
                        form.submit();
                    }
                    return;
                }

                const m = Math.floor(totalSeconds / 60);
                const s = totalSeconds % 60;
                display.textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);

                // Spoken screen reader milestones
                if (srAnnounce) {
                    if (totalSeconds === 300) {
                        srAnnounce.textContent = '5 minutes remaining on your assessment.';
                    } else if (totalSeconds === 60) {
                        srAnnounce.textContent = '1 minute remaining on your assessment.';
                    } else if (totalSeconds === 30) {
                        srAnnounce.textContent = '30 seconds remaining on your assessment.';
                    }
                }

                if (totalSeconds <= 60) {
                    display.parentElement.style.background = 'rgba(239, 68, 68, 0.2)';
                    display.parentElement.style.borderColor = '#ef4444';
                }
            }, 1000);
        })();
        </script>
    @endif
</div>
