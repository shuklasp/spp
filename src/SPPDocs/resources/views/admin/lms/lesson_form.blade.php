@extends('layouts.admin')

@section('title', ($lesson ? 'Edit ' . $lesson['title'] : 'New Lesson') . ' — Course Studio')

@section('content')
@php
    $active_tab = 'lms';
    $isEdit = !empty($lesson);
@endphp

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            <span>{{ $isEdit ? '📝 Edit Lesson & Assessment' : '✨ New Lesson' }}</span>
            <code class="adm-title-project-slug">{{ $course['title'] }}</code>
        </h1>
        <p class="adm-page-desc">
            Author Markdown curriculum, embed video walkthroughs, and visually build interactive knowledge checks.
        </p>
    </div>
    <div class="adm-page-actions">
        <a href="@url('admin/lms/course/edit?project=' . $project_id . '&course=' . $course_id)" class="adm-btn adm-btn-secondary">
            &larr; Back to Course
        </a>
    </div>
</div>

<form action="@url('admin/lms/lesson/save')" method="POST" id="lesson-form">
    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
    <input type="hidden" name="project_id" value="{{ $project_id }}">
    <input type="hidden" name="course_id" value="{{ $course_id }}">

    <!-- 1. Metadata Card -->
    <div class="adm-card" style="margin-bottom: 2rem;">
        <div class="adm-card-header">
            <h2 class="adm-card-title">1. Lesson Identity &amp; Placement</h2>
        </div>

        <div class="adm-form-grid">
            <div class="adm-form-group">
                <label class="adm-form-label">Parent Module</label>
                <select name="module_id" class="adm-form-input" required>
                    @foreach($course['modules'] as $m)
                        <option value="{{ $m['id'] }}" {{ $module_id === $m['id'] ? 'selected' : '' }}>
                            {{ $m['title'] }} ({{ $m['id'] }})
                        </option>
                    @endforeach
                </select>
                <span class="adm-form-desc">Module under which this lesson appears in the syllabus</span>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Lesson Identifier (Slug)</label>
                <input type="text" 
                       name="lesson_id" 
                       value="{{ $lesson['id'] ?? $lesson_id }}" 
                       required 
                       placeholder="e.g. 01_architecture_core" 
                       class="adm-form-input" 
                       {{ $isEdit ? 'readonly' : '' }} 
                       pattern="[a-zA-Z0-9_\-]+" 
                       title="Only letters, numbers, hyphens, and underscores allowed">
                <span class="adm-form-desc">Filename slug (e.g. {{ $lesson_id ?: '01_intro' }}.md)</span>
            </div>

            <div class="adm-form-group" style="grid-column: span 2;">
                <label class="adm-form-label">Lesson Title</label>
                <input type="text" 
                       name="title" 
                       value="{{ $lesson['title'] ?? '' }}" 
                       required 
                       placeholder="e.g. The SPP Request Lifecycle & Routing Engine" 
                       class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Lesson Type</label>
                <select name="type" class="adm-form-input" id="lesson-type-select" onchange="toggleLessonTypePanels(this.value)">
                    <option value="reading" {{ ($lesson['type'] ?? 'reading') === 'reading' ? 'selected' : '' }}>📖 Reading (Markdown text &amp; diagrams)</option>
                    <option value="quiz" {{ ($lesson['type'] ?? '') === 'quiz' ? 'selected' : '' }}>❓ Standalone Assessment (Quiz / Exam)</option>
                    <option value="video" {{ ($lesson['type'] ?? '') === 'video' ? 'selected' : '' }}>🎥 Video + Reading (Video lecture with notes)</option>
                </select>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Estimated Duration</label>
                <input type="text" 
                       name="duration" 
                       value="{{ $lesson['duration'] ?? '15 mins' }}" 
                       placeholder="e.g. 15 mins, 30 mins" 
                       class="adm-form-input">
            </div>

            <div class="adm-form-group" style="grid-column: span 2;" id="video-url-group">
                <label class="adm-form-label">Video Embed URL (Optional)</label>
                <input type="url" 
                       name="video_url" 
                       value="{{ $lesson['video_url'] ?? '' }}" 
                       placeholder="e.g. https://www.youtube.com/embed/... or https://player.vimeo.com/video/..." 
                       class="adm-form-input">
                <span class="adm-form-desc">Embeddable video player URL rendered at the top of the lesson</span>
            </div>

            <div class="adm-form-group" style="grid-column: span 2;">
                <label class="adm-form-label">Summary / Abstract</label>
                <textarea name="summary" rows="2" class="adm-form-input" style="font-family: inherit;" placeholder="Brief synopsis displayed in course syllabus...">{{ $lesson['summary'] ?? '' }}</textarea>
            </div>
        </div>
    </div>

    <!-- 2. Split-Screen Markdown Editor & Live Preview -->
    <div class="adm-card" id="markdown-editor-card" style="margin-bottom: 2rem;">
        <div class="adm-card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="adm-card-title">2. Lesson Content (Markdown)</h2>
            <div style="font-size: 0.8rem; color: var(--adm-text-muted);">
                Supports CommonMark, Tables, Code Highlighting, and Alerts
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; min-height: 420px;">
            <!-- Left: Textarea -->
            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; margin-bottom: 0.4rem;">
                    Markdown Source
                </label>
                <textarea name="markdown" 
                          id="markdown-input" 
                          class="adm-form-input" 
                          style="flex: 1; font-family: monospace; font-size: 0.9rem; line-height: 1.5; resize: vertical; min-height: 380px;" 
                          placeholder="# Lesson Title&#10;&#10;Write your instructional lesson here...&#10;&#10;```php&#10;// Code examples&#10;```"
                          oninput="updateMarkdownLivePreview(this.value)">{{ $lesson['raw_markdown'] ?? '' }}</textarea>
            </div>

            <!-- Right: Real-time Preview -->
            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: flex; justify-content: space-between;">
                    <span>Live Preview</span>
                    <span style="color: #10b981; font-size: 0.75rem;">● Real-time sync</span>
                </label>
                <div id="markdown-preview" 
                     style="flex: 1; background: var(--adm-card-bg, #ffffff); border: 1px solid var(--adm-border, #e2e8f0); border-radius: 8px; padding: 1.25rem; overflow-y: auto; max-height: 500px; font-size: 0.95rem; line-height: 1.6; color: var(--adm-text-main);">
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Assessment & Quiz Builder -->
    <div class="adm-card" style="margin-bottom: 2rem;">
        <div class="adm-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h2 class="adm-card-title">3. Interactive Knowledge Assessment</h2>
                <div style="font-size: 0.85rem; color: var(--adm-text-muted); margin-top: 0.2rem;">
                    Attach formative quizzes or high-stakes certification exams with timed countdowns and anti-cheat shuffling.
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" onclick="addQuestion('multiple_choice')" class="adm-btn adm-btn-secondary adm-btn-sm">
                    + Multiple Choice
                </button>
                <button type="button" onclick="addQuestion('multi_select')" class="adm-btn adm-btn-secondary adm-btn-sm">
                    + Multi-Select
                </button>
                <button type="button" onclick="addQuestion('code_check')" class="adm-btn adm-btn-secondary adm-btn-sm">
                    + Code Inspection
                </button>
            </div>
        </div>

        @php
            $quizData = $lesson['quiz_data'] ?? [];
        @endphp

        <!-- Assessment Config Settings -->
        <div style="background: var(--adm-bg, #f8fafc); border: 1px solid var(--adm-border, #e2e8f0); border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <div class="adm-form-grid" style="margin-bottom: 1rem;">
                <div class="adm-form-group" style="grid-column: span 2;">
                    <label class="adm-form-label">Quiz Description / Instructions</label>
                    <input type="text" 
                           name="quiz_description" 
                           value="{{ $quizData['description'] ?? 'Test your mastery of this lesson.' }}" 
                           class="adm-form-input">
                </div>

                <div class="adm-form-group">
                    <label class="adm-form-label">Passing Threshold (%)</label>
                    <input type="number" 
                           name="quiz_passing_score" 
                           min="1" 
                           max="100" 
                           value="{{ $quizData['passing_score'] ?? 80 }}" 
                           class="adm-form-input">
                </div>

                <div class="adm-form-group">
                    <label class="adm-form-label">Time Limit (Minutes)</label>
                    <input type="number" 
                           name="time_limit_minutes" 
                           min="0" 
                           max="240" 
                           value="{{ $quizData['time_limit_minutes'] ?? 0 }}" 
                           class="adm-form-input" 
                           placeholder="0 for unlimited">
                    <span class="adm-form-desc">0 = Unlimited time. Timed exams auto-submit at 00:00</span>
                </div>

                <div class="adm-form-group">
                    <label class="adm-form-label">Max Attempts Allowed</label>
                    <input type="number" 
                           name="max_attempts" 
                           min="0" 
                           max="50" 
                           value="{{ $quizData['max_attempts'] ?? 0 }}" 
                           class="adm-form-input" 
                           placeholder="0 for unlimited">
                    <span class="adm-form-desc">0 = Unlimited attempts</span>
                </div>

                <div class="adm-form-group">
                    <label class="adm-form-label">Retake Cooldown (Minutes)</label>
                    <input type="number" 
                           name="cooldown_minutes" 
                           min="0" 
                           max="1440" 
                           value="{{ $quizData['cooldown_minutes'] ?? 0 }}" 
                           class="adm-form-input" 
                           placeholder="0 for immediate retake">
                    <span class="adm-form-desc">Wait time between failed attempts</span>
                </div>
            </div>

            <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600; color: var(--adm-text-main); cursor: pointer;">
                    <input type="checkbox" name="shuffle_questions" value="1" {{ !empty($quizData['shuffle_questions']) ? 'checked' : '' }} style="accent-color: #8b5cf6; transform: scale(1.15);">
                    <span>🔀 Shuffle Questions Order</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600; color: var(--adm-text-main); cursor: pointer;">
                    <input type="checkbox" name="shuffle_options" value="1" {{ !empty($quizData['shuffle_options']) ? 'checked' : '' }} style="accent-color: #8b5cf6; transform: scale(1.15);">
                    <span>🔀 Shuffle Choices Order</span>
                </label>
            </div>
        </div>

        <!-- Dynamic Questions Container -->
        <div id="questions-container" style="display: flex; flex-direction: column; gap: 1.5rem;">
            @php
                $existingQuestions = $quizData['questions'] ?? [];
            @endphp

            @forelse($existingQuestions as $qIdx => $q)
                @php
                    $qType = $q['type'] ?? 'multiple_choice';
                @endphp
                <div class="adm-card question-card" data-q-idx="{{ $qIdx }}" style="border: 1px solid #cbd5e1; padding: 1.25rem; background: var(--adm-card-bg, #ffffff);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--adm-border, #e2e8f0); padding-bottom: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="adm-badge" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; font-weight: 700;">
                                Question #<span class="q-number">{{ $qIdx + 1 }}</span>
                            </span>
                            <span style="font-size: 0.8rem; font-weight: 600; color: var(--adm-text-muted); text-transform: uppercase;">
                                {{ ucwords(str_replace('_', ' ', $qType)) }}
                            </span>
                        </div>
                        <button type="button" onclick="removeQuestion(this)" class="adm-btn adm-btn-secondary adm-btn-sm" style="color: #ef4444;">
                            🗑️ Remove
                        </button>
                    </div>

                    <input type="hidden" name="questions[{{ $qIdx }}][id]" value="{{ $q['id'] ?? ('q_' . $qIdx) }}">
                    <input type="hidden" name="questions[{{ $qIdx }}][type]" value="{{ $qType }}">

                    <div style="margin-bottom: 1rem;">
                        <label class="adm-form-label">Question Prompt</label>
                        <input type="text" name="questions[{{ $qIdx }}][prompt]" value="{{ $q['prompt'] ?? '' }}" required class="adm-form-input" placeholder="State the question clearly...">
                    </div>

                    @if($qType === 'multiple_choice' || $qType === 'multi_select')
                        <div style="margin-bottom: 1rem;">
                            <label class="adm-form-label">Answer Choices (Check the correct answers)</label>
                            <div class="options-container" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.5rem;">
                                @foreach($q['options'] ?? [] as $optIdx => $opt)
                                    <div class="option-row" style="display: flex; align-items: center; gap: 0.5rem;">
                                        <input type="{{ $qType === 'multiple_choice' ? 'radio' : 'checkbox' }}" 
                                               name="questions[{{ $qIdx }}][options][{{ $optIdx }}][correct]" 
                                               value="1" 
                                               {{ !empty($opt['correct']) ? 'checked' : '' }} 
                                               style="accent-color: #8b5cf6; transform: scale(1.2);">
                                        <input type="hidden" name="questions[{{ $qIdx }}][options][{{ $optIdx }}][id]" value="{{ $opt['id'] ?? chr(97 + $optIdx) }}">
                                        <input type="text" name="questions[{{ $qIdx }}][options][{{ $optIdx }}][text]" value="{{ $opt['text'] ?? '' }}" required class="adm-form-input" style="flex: 1;" placeholder="Option text...">
                                        <button type="button" onclick="removeOption(this)" class="adm-btn adm-btn-secondary adm-btn-sm" style="padding: 0.35rem 0.6rem;">&times;</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" onclick="addOption({{ $qIdx }}, '{{ $qType }}')" class="adm-btn adm-btn-secondary adm-btn-sm">
                                + Add Choice
                            </button>
                        </div>
                    @elseif($qType === 'code_check')
                        <div style="margin-bottom: 1rem;">
                            <label class="adm-form-label">Code Context (Optional code snippet)</label>
                            <textarea name="questions[{{ $qIdx }}][code]" rows="2" class="adm-form-input" style="font-family: monospace; font-size: 0.9rem;" placeholder="$entity->____('approve');">{{ $q['code'] ?? '' }}</textarea>
                        </div>
                        <div class="adm-form-grid" style="margin-bottom: 1rem;">
                            <div class="adm-form-group">
                                <label class="adm-form-label">Expected Text / Identifier</label>
                                <input type="text" name="questions[{{ $qIdx }}][expected]" value="{{ $q['expected'] ?? '' }}" required class="adm-form-input" style="font-family: monospace;" placeholder="e.g. applyTransition">
                            </div>
                            <div class="adm-form-group">
                                <label class="adm-form-label">Hint (Optional)</label>
                                <input type="text" name="questions[{{ $qIdx }}][hint]" value="{{ $q['hint'] ?? '' }}" class="adm-form-input" placeholder="e.g. Method on SPPEntity">
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="adm-form-label">Explanation / Remediation Feedback</label>
                        <input type="text" name="questions[{{ $qIdx }}][explanation]" value="{{ $q['explanation'] ?? '' }}" class="adm-form-input" placeholder="Displayed to the student after grading...">
                    </div>
                </div>
            @empty
                <div id="no-questions-placeholder" style="text-align: center; padding: 2rem; border: 2px dashed var(--adm-border, #cbd5e1); border-radius: 10px; color: var(--adm-text-muted);">
                    <span>❓</span> No questions configured. Click buttons above to add questions to this unit.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; margin-bottom: 3rem;">
        <div>
            @if($isEdit)
                <button type="button" onclick="confirmDeleteLesson()" class="adm-btn adm-btn-secondary" style="color: #ef4444;">
                    🗑️ Delete Lesson
                </button>
            @endif
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="@url('admin/lms/course/edit?project=' . $project_id . '&course=' . $course_id)" class="adm-btn adm-btn-secondary">
                Cancel
            </a>
            <button type="submit" class="adm-btn adm-btn-primary" style="padding: 0.65rem 1.75rem; font-size: 1rem; font-weight: 700;">
                💾 Save Lesson &amp; Assessment
            </button>
        </div>
    </div>
</form>

@if($isEdit)
    <form action="@url('admin/lms/lesson/delete')" method="POST" id="delete-lesson-form" style="display: none;">
        <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
        <input type="hidden" name="project_id" value="{{ $project_id }}">
        <input type="hidden" name="course_id" value="{{ $course_id }}">
        <input type="hidden" name="lesson_id" value="{{ $lesson['id'] }}">
    </form>
@endif

<script>
let questionCounter = {{ count($existingQuestions ?? []) }};

function toggleLessonTypePanels(val) {
    const mdCard = document.getElementById('markdown-editor-card');
    const videoGroup = document.getElementById('video-url-group');
    if (val === 'quiz') {
        if (mdCard) mdCard.style.display = 'none';
        if (videoGroup) videoGroup.style.display = 'none';
    } else {
        if (mdCard) mdCard.style.display = 'block';
        if (videoGroup) videoGroup.style.display = (val === 'video') ? 'block' : 'none';
    }
}

function updateMarkdownLivePreview(val) {
    const preview = document.getElementById('markdown-preview');
    if (!preview) return;
    if (!val || val.trim() === '') {
        preview.innerHTML = '<span style="color: #94a3b8; font-style: italic;">Preview will appear here as you type...</span>';
        return;
    }
    
    // Clean client-side Markdown preview converter
    let html = val
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/^### (.*$)/gim, '<h3 style="font-size:1.15rem;font-weight:700;margin:1rem 0 0.5rem 0;">$1</h3>')
        .replace(/^## (.*$)/gim, '<h2 style="font-size:1.4rem;font-weight:800;margin:1.25rem 0 0.5rem 0;">$1</h2>')
        .replace(/^# (.*$)/gim, '<h1 style="font-size:1.75rem;font-weight:900;margin:1.5rem 0 0.75rem 0;">$1</h1>')
        .replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/gim, '<em>$1</em>')
        .replace(/`([^`]+)`/gim, '<code style="background:var(--vp-c-divider, #f1f5f9);padding:0.2rem 0.4rem;border-radius:4px;font-family:monospace;">$1</code>')
        .replace(/\n\n/gim, '</p><p style="margin:0.75rem 0;">')
        .replace(/```([\s\S]*?)```/gim, '<pre style="background:#1e1e2e;color:#f8f8f2;padding:1rem;border-radius:8px;overflow-x:auto;"><code>$1</code></pre>');
    
    preview.innerHTML = '<p style="margin:0.75rem 0;">' + html + '</p>';
}

function addQuestion(type) {
    const placeholder = document.getElementById('no-questions-placeholder');
    if (placeholder) placeholder.style.display = 'none';

    const container = document.getElementById('questions-container');
    const idx = questionCounter++;
    const card = document.createElement('div');
    card.className = 'adm-card question-card';
    card.dataset.qIdx = idx;
    card.style = 'border: 1px solid #cbd5e1; padding: 1.25rem; background: var(--adm-card-bg, #ffffff);';

    let typeTitle = (type === 'multiple_choice') ? 'Multiple Choice' : ((type === 'multi_select') ? 'Multi-Select' : 'Code Inspection');
    
    let optionsHtml = '';
    if (type === 'multiple_choice' || type === 'multi_select') {
        const inputType = (type === 'multiple_choice') ? 'radio' : 'checkbox';
        optionsHtml = `
            <div style="margin-bottom: 1rem;">
                <label class="adm-form-label">Answer Choices (Check the correct answers)</label>
                <div class="options-container" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div class="option-row" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="${inputType}" name="questions[${idx}][options][0][correct]" value="1" checked style="accent-color: #8b5cf6; transform: scale(1.2);">
                        <input type="hidden" name="questions[${idx}][options][0][id]" value="a">
                        <input type="text" name="questions[${idx}][options][0][text]" required class="adm-form-input" style="flex: 1;" placeholder="Option A (Correct answer)">
                        <button type="button" onclick="removeOption(this)" class="adm-btn adm-btn-secondary adm-btn-sm">&times;</button>
                    </div>
                    <div class="option-row" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="${inputType}" name="questions[${idx}][options][1][correct]" value="1" style="accent-color: #8b5cf6; transform: scale(1.2);">
                        <input type="hidden" name="questions[${idx}][options][1][id]" value="b">
                        <input type="text" name="questions[${idx}][options][1][text]" required class="adm-form-input" style="flex: 1;" placeholder="Option B">
                        <button type="button" onclick="removeOption(this)" class="adm-btn adm-btn-secondary adm-btn-sm">&times;</button>
                    </div>
                </div>
                <button type="button" onclick="addOption(${idx}, '${type}')" class="adm-btn adm-btn-secondary adm-btn-sm">+ Add Choice</button>
            </div>
        `;
    } else if (type === 'code_check') {
        optionsHtml = `
            <div style="margin-bottom: 1rem;">
                <label class="adm-form-label">Code Context (Optional code snippet)</label>
                <textarea name="questions[${idx}][code]" rows="2" class="adm-form-input" style="font-family: monospace; font-size: 0.9rem;" placeholder="$entity->____('approve');"></textarea>
            </div>
            <div class="adm-form-grid" style="margin-bottom: 1rem;">
                <div class="adm-form-group">
                    <label class="adm-form-label">Expected Text / Identifier</label>
                    <input type="text" name="questions[${idx}][expected]" required class="adm-form-input" style="font-family: monospace;" placeholder="e.g. applyTransition">
                </div>
                <div class="adm-form-group">
                    <label class="adm-form-label">Hint (Optional)</label>
                    <input type="text" name="questions[${idx}][hint]" class="adm-form-input" placeholder="e.g. Method on SPPEntity">
                </div>
            </div>
        `;
    }

    card.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--adm-border, #e2e8f0); padding-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="adm-badge" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; font-weight: 700;">
                    Question #<span class="q-number">${document.querySelectorAll('.question-card').length + 1}</span>
                </span>
                <span style="font-size: 0.8rem; font-weight: 600; color: var(--adm-text-muted); text-transform: uppercase;">
                    ${typeTitle}
                </span>
            </div>
            <button type="button" onclick="removeQuestion(this)" class="adm-btn adm-btn-secondary adm-btn-sm" style="color: #ef4444;">
                🗑️ Remove
            </button>
        </div>
        <input type="hidden" name="questions[${idx}][id]" value="q_${Date.now()}">
        <input type="hidden" name="questions[${idx}][type]" value="${type}">
        <div style="margin-bottom: 1rem;">
            <label class="adm-form-label">Question Prompt</label>
            <input type="text" name="questions[${idx}][prompt]" required class="adm-form-input" placeholder="State the question clearly...">
        </div>
        ${optionsHtml}
        <div>
            <label class="adm-form-label">Explanation / Remediation Feedback</label>
            <input type="text" name="questions[${idx}][explanation]" class="adm-form-input" placeholder="Displayed to the student after grading...">
        </div>
    `;

    container.appendChild(card);
}

function removeQuestion(btn) {
    const card = btn.closest('.question-card');
    if (card) {
        card.remove();
        // Re-number
        document.querySelectorAll('.question-card').forEach((c, i) => {
            const numEl = c.querySelector('.q-number');
            if (numEl) numEl.textContent = i + 1;
        });
    }
}

function addOption(qIdx, type) {
    const card = document.querySelector(`.question-card[data-q-idx="${qIdx}"]`);
    if (!card) return;
    const optContainer = card.querySelector('.options-container');
    const count = optContainer.querySelectorAll('.option-row').length;
    const optId = String.fromCharCode(97 + count);
    const inputType = (type === 'multiple_choice') ? 'radio' : 'checkbox';

    const row = document.createElement('div');
    row.className = 'option-row';
    row.style = 'display: flex; align-items: center; gap: 0.5rem;';
    row.innerHTML = `
        <input type="${inputType}" name="questions[${qIdx}][options][${count}][correct]" value="1" style="accent-color: #8b5cf6; transform: scale(1.2);">
        <input type="hidden" name="questions[${qIdx}][options][${count}][id]" value="${optId}">
        <input type="text" name="questions[${qIdx}][options][${count}][text]" required class="adm-form-input" style="flex: 1;" placeholder="Option ${optId.toUpperCase()}">
        <button type="button" onclick="removeOption(this)" class="adm-btn adm-btn-secondary adm-btn-sm">&times;</button>
    `;
    optContainer.appendChild(row);
}

function removeOption(btn) {
    const row = btn.closest('.option-row');
    if (row) row.remove();
}

function confirmDeleteLesson() {
    if (confirm('Are you sure you want to permanently delete this lesson and its content files?')) {
        document.getElementById('delete-lesson-form').submit();
    }
}

// Initial render
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('markdown-input');
    if (input) updateMarkdownLivePreview(input.value);
    const select = document.getElementById('lesson-type-select');
    if (select) toggleLessonTypePanels(select.value);
});
</script>
@endsection
