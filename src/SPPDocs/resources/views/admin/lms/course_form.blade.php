@extends('layouts.admin')

@section('title', ($course ? 'Edit ' . $course['title'] : 'New Course') . ' — Course Studio')

@section('content')
@php
    $active_tab = 'lms';
@endphp

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            <span>{{ $course ? '✏️ Edit Course' : '✨ New Course' }}</span>
            @if($course)
                <code class="adm-title-project-slug">{{ $course['id'] }}</code>
            @endif
        </h1>
        <p class="adm-page-desc">Configure course identity, instructor credentials, passing threshold, and certification eligibility.</p>
    </div>
    <div class="adm-page-actions">
        <a href="@url('admin/lms?project=' . $project_id)" class="adm-btn adm-btn-secondary">
            &larr; Back to Course Studio
        </a>
    </div>
</div>

<form action="@url('admin/lms/course/save')" method="POST">
    <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
    <input type="hidden" name="project_id" value="{{ $project_id }}">

    <div class="adm-card" style="margin-bottom: 2rem;">
        <div class="adm-card-header">
            <h2 class="adm-card-title">1. Course Identity &amp; Level</h2>
        </div>

        <div class="adm-form-grid">
            <div class="adm-form-group">
                <label class="adm-form-label">Course Identifier (Slug)</label>
                <input type="text" 
                       name="course_id" 
                       value="{{ $course['id'] ?? $course_id }}" 
                       required 
                       placeholder="e.g. intro-to-spp" 
                       class="adm-form-input" 
                       {{ $course ? 'readonly' : '' }} 
                       pattern="[a-zA-Z0-9_\-]+" 
                       title="Only letters, numbers, hyphens, and underscores allowed">
                <span class="adm-form-desc">Directory name inside docs/{{ $project_id }}/courses/</span>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Badge Icon (Emoji)</label>
                <input type="text" 
                       name="badge_icon" 
                       value="{{ $course['badge_icon'] ?? '🎓' }}" 
                       class="adm-form-input" 
                       style="font-size: 1.2rem;">
                <span class="adm-form-desc">Emoji representing the course (e.g. 🎓, ⚡, 🛡️)</span>
            </div>

            <div class="adm-form-group" style="grid-column: span 2;">
                <label class="adm-form-label">Course Title</label>
                <input type="text" 
                       name="title" 
                       value="{{ $course['title'] ?? '' }}" 
                       required 
                       placeholder="e.g. Mastering SPP Framework" 
                       class="adm-form-input">
            </div>

            <div class="adm-form-group" style="grid-column: span 2;">
                <label class="adm-form-label">Tagline / Subtitle</label>
                <input type="text" 
                       name="tagline" 
                       value="{{ $course['tagline'] ?? '' }}" 
                       placeholder="e.g. From Novice to Enterprise Architect" 
                       class="adm-form-input">
            </div>

            <div class="adm-form-group" style="grid-column: span 2;">
                <label class="adm-form-label">Description</label>
                <textarea name="description" rows="3" class="adm-form-input" style="font-family: inherit; line-height: 1.5;" placeholder="Comprehensive summary of what the learner will gain...">{{ $course['description'] ?? '' }}</textarea>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Difficulty Level</label>
                <select name="level" class="adm-form-input">
                    @foreach(['Beginner', 'Intermediate', 'Advanced'] as $lvl)
                        <option value="{{ $lvl }}" {{ ($course['level'] ?? 'Intermediate') === $lvl ? 'selected' : '' }}>
                            {{ $lvl }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Category</label>
                <input type="text" 
                       name="category" 
                       value="{{ $course['category'] ?? 'Architecture' }}" 
                       placeholder="e.g. Web Architecture, DevOps" 
                       class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Estimated Duration</label>
                <input type="text" 
                       name="duration" 
                       value="{{ $course['duration'] ?? '2 hours' }}" 
                       placeholder="e.g. 3 hours, 45 mins" 
                       class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Passing Score (%)</label>
                <input type="number" 
                       name="passing_score" 
                       min="1" 
                       max="100" 
                       value="{{ $course['passing_score'] ?? 80 }}" 
                       required 
                       class="adm-form-input">
                <span class="adm-form-desc">Minimum grade required across quizzes to earn certification</span>
            </div>
        </div>
    </div>

    <!-- Instructor Credentials -->
    <div class="adm-card" style="margin-bottom: 2rem;">
        <div class="adm-card-header">
            <h2 class="adm-card-title">2. Instructor &amp; Prerequisites</h2>
        </div>

        <div class="adm-form-grid">
            <div class="adm-form-group">
                <label class="adm-form-label">Instructor Name</label>
                <input type="text" 
                       name="instructor_name" 
                       value="{{ $course['instructor']['name'] ?? 'Prof. Satya Prakash Shukla' }}" 
                       class="adm-form-input">
            </div>

            <div class="adm-form-group">
                <label class="adm-form-label">Instructor Title / Role</label>
                <input type="text" 
                       name="instructor_title" 
                       value="{{ $course['instructor']['title'] ?? 'Chief Framework Architect' }}" 
                       class="adm-form-input">
            </div>

            <div class="adm-form-group" style="grid-column: span 2;">
                <label class="adm-form-label">Instructor Bio</label>
                <textarea name="instructor_bio" rows="2" class="adm-form-input" style="font-family: inherit;">{{ $course['instructor']['bio'] ?? '' }}</textarea>
            </div>

            <div class="adm-form-group" style="grid-column: span 2;">
                <label class="adm-form-label">Prerequisites (one per line)</label>
                <textarea name="prerequisites" rows="3" class="adm-form-input" style="font-family: inherit;" placeholder="Basic familiarity with PHP 8.2+&#10;Understanding of MVC paradigms">{{ implode("\n", $course['prerequisites'] ?? []) }}</textarea>
            </div>
        </div>
    </div>

    <!-- Publication & Certification Toggles -->
    <div class="adm-card" style="margin-bottom: 2rem;">
        <div class="adm-card-header">
            <h2 class="adm-card-title">3. Publication &amp; Certification</h2>
        </div>

        <div class="adm-toggle-row" style="margin-bottom: 1rem;">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">🌐 Published in Public Academy</div>
                <div class="adm-toggle-desc">Make this course visible to students in the public course catalog.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="published" value="1" {{ !empty($course['published']) ? 'checked' : '' }}>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div class="adm-toggle-row" style="margin-bottom: 1rem;">
            <div class="adm-toggle-info">
                <div class="adm-toggle-title">🏆 Issue Verifiable Certificates</div>
                <div class="adm-toggle-desc">Automatically mint cryptographic certificates with unique verification codes when learners reach 100%.</div>
            </div>
            <label class="adm-switch">
                <input type="checkbox" name="certificate_enabled" value="1" {{ (isset($course['certificate_enabled']) ? !empty($course['certificate_enabled']) : true) ? 'checked' : '' }}>
                <span class="adm-slider"></span>
            </label>
        </div>

        <div style="border-top: 1px solid var(--adm-border, #e2e8f0); padding-top: 1.25rem; margin-top: 1rem;">
            <label class="adm-form-label">Academic Progression Mode</label>
            <select name="progression_mode" class="adm-form-input" style="max-width: 400px;">
                <option value="free" {{ ($course['progression_mode'] ?? 'free') === 'free' ? 'selected' : '' }}>
                    🔓 Free Exploration (Students can navigate lessons and quizzes in any order)
                </option>
                <option value="linear" {{ ($course['progression_mode'] ?? '') === 'linear' ? 'selected' : '' }}>
                    🔒 Linear Progression (Prerequisites strictly enforced; units unlock sequentially)
                </option>
            </select>
            <span class="adm-form-desc">In linear mode, lessons display padlock icons until all prior lessons and assessments are passed.</span>
        </div>
    </div>

    <!-- 4. Visual Curriculum & Lesson Organizer (Available once course is created) -->
    @if($course)
        <div class="adm-card" style="margin-bottom: 2rem;">
            <div class="adm-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 class="adm-card-title">4. Curriculum Modules &amp; Visual Lesson Studio</h2>
                    <div style="font-size: 0.85rem; color: var(--adm-text-muted); margin-top: 0.2rem;">
                        Manage syllabus modules, author lessons in-browser, and build assessments.
                    </div>
                </div>
            </div>

            @if(empty($course['modules']))
                <div style="text-align: center; padding: 2rem; border: 2px dashed var(--adm-border, #cbd5e1); border-radius: 8px;">
                    <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">📚</span>
                    <p style="margin: 0 0 1rem 0; color: var(--adm-text-muted);">No curriculum modules defined yet.</p>
                    <a href="@url('admin/lms/lesson/edit?project=' . $project_id . '&course=' . $course['id'] . '&module=mod_1')" class="adm-btn adm-btn-primary adm-btn-sm">
                        + Add First Lesson
                    </a>
                </div>
            @else
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    @foreach($course['modules'] as $mIdx => $mod)
                        <div style="background: var(--adm-bg, #f8fafc); border: 1px solid var(--adm-border, #e2e8f0); border-radius: 10px; padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid var(--adm-border, #e2e8f0); padding-bottom: 0.5rem;">
                                <div>
                                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #ea580c; letter-spacing: 0.05em;">
                                        Module {{ $mIdx + 1 }}
                                    </span>
                                    <h3 style="margin: 0.1rem 0 0 0; font-size: 1.15rem; font-weight: 700; color: var(--adm-text-main);">
                                        {{ $mod['title'] }}
                                    </h3>
                                </div>
                                <a href="@url('admin/lms/lesson/edit?project=' . $project_id . '&course=' . $course['id'] . '&module=' . $mod['id'])" class="adm-btn adm-btn-secondary adm-btn-sm">
                                    + Add Lesson
                                </a>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                @forelse($mod['lessons'] ?? [] as $lsn)
                                    @php
                                        $isQuiz = ($lsn['type'] ?? '') === 'quiz';
                                    @endphp
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; background: var(--adm-card-bg, #ffffff); border: 1px solid var(--adm-border, #e2e8f0); border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                                            <span>{{ $isQuiz ? '❓' : '📖' }}</span>
                                            <div>
                                                <strong style="color: var(--adm-text-main); font-size: 0.92rem;">
                                                    {{ $lsn['title'] }}
                                                </strong>
                                                <span style="font-size: 0.78rem; color: var(--adm-text-muted); margin-left: 0.5rem;">
                                                    ({{ $lsn['duration'] ?? '15 mins' }})
                                                </span>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <a href="@url('admin/lms/lesson/edit?project=' . $project_id . '&course=' . $course['id'] . '&module=' . $mod['id'] . '&lesson=' . $lsn['id'])" 
                                               class="adm-btn adm-btn-secondary adm-btn-sm" style="padding: 0.3rem 0.65rem;">
                                                ✏️ Edit Content &amp; Quiz
                                            </a>
                                            <a href="@url('lms/lesson?projectId=' . $project_id . '&course=' . $course['id'] . '&lesson=' . $lsn['id'])" 
                                               target="_blank" 
                                               class="adm-btn adm-btn-secondary adm-btn-sm" style="padding: 0.3rem 0.65rem;">
                                                👁️ Preview
                                            </a>
                                        </div>
                                    </div>
                                @empty
                                    <div style="font-size: 0.85rem; color: var(--adm-text-muted); font-style: italic; padding: 0.5rem;">
                                        No lessons in this module.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
        <a href="@url('admin/lms?project=' . $project_id)" class="adm-btn adm-btn-secondary">
            Cancel
        </a>
        <button type="submit" class="adm-btn adm-btn-primary" style="padding: 0.65rem 1.75rem; font-size: 1rem; font-weight: 700;">
            💾 Save Course
        </button>
    </div>
</form>
@endsection
