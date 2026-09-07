@extends('layouts.admin')
@section('title', 'Analytics & Reader Demand - ' . ($project['title'] ?? 'Project'))

@section('content')
<div class="adm-content-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="margin: 0; font-size: 1.6rem;">📊 Reader Feedback & Search Demand</h1>
            <p style="margin: 0.35rem 0 0 0; color: var(--vp-c-text-2); font-size: 0.9rem;">
                Actionable documentation telemetry and unmet reader search demand for <strong>{{ $project['title'] ?? $projectId }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ \SPP\App::getBaseUrl() }}/admin/project/{{ $projectId }}" class="adm-btn adm-btn-secondary" style="padding: 0.55rem 1rem; font-size: 0.85rem; text-decoration: none;">
                ← Back to Project
            </a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 10px; padding: 1.25rem;">
            <div style="color: var(--vp-c-text-3); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Total Responses</div>
            <div style="font-size: 2rem; font-weight: 700; color: var(--vp-c-text-1); margin-top: 0.25rem;">{{ $totalFeedback }}</div>
            <div style="font-size: 0.75rem; color: var(--vp-c-text-2); margin-top: 0.25rem;">Reader ratings logged</div>
        </div>

        <div style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 10px; padding: 1.25rem;">
            <div style="color: var(--vp-c-text-3); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Helpful (👍)</div>
            <div style="font-size: 2rem; font-weight: 700; color: #16a34a; margin-top: 0.25rem;">{{ $helpfulCount }}</div>
            <div style="font-size: 0.75rem; color: var(--vp-c-text-2); margin-top: 0.25rem;">Positive reader reactions</div>
        </div>

        <div style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 10px; padding: 1.25rem;">
            <div style="color: var(--vp-c-text-3); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Needs Improvement (👎)</div>
            <div style="font-size: 2rem; font-weight: 700; color: #dc2626; margin-top: 0.25rem;">{{ $unhelpfulCount }}</div>
            <div style="font-size: 0.75rem; color: var(--vp-c-text-2); margin-top: 0.25rem;">Readers requesting clarification</div>
        </div>

        <div style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 10px; padding: 1.25rem;">
            <div style="color: var(--vp-c-text-3); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Satisfaction Score</div>
            <div style="font-size: 2rem; font-weight: 700; margin-top: 0.25rem; color: {{ $helpfulRatio >= 80 ? '#16a34a' : ($helpfulRatio >= 60 ? '#ca8a04' : '#dc2626') }};">
                {{ $helpfulRatio }}%
            </div>
            <div style="font-size: 0.75rem; color: var(--vp-c-text-2); margin-top: 0.25rem;">Percentage positive rating</div>
        </div>
    </div>

    {{-- Unmet Search Demand Table --}}
    <div style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h2 style="margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                    🔍 Unmet Search Demand
                    <span style="background: #ef4444; color: white; font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 999px;">{{ count($unmetQueries) }} Missing Topics</span>
                </h2>
                <p style="margin: 0.25rem 0 0 0; color: var(--vp-c-text-2); font-size: 0.85rem;">
                    Queries readers searched for that yielded <strong>zero matching results</strong>. Click "Create Doc" to immediately fill the content gap!
                </p>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider); color: var(--vp-c-text-2);">
                        <th style="padding: 0.75rem 1rem;">Searched Keyword / Query</th>
                        <th style="padding: 0.75rem 1rem;">Reader Searches</th>
                        <th style="padding: 0.75rem 1rem;">Last Searched</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unmetQueries as $qKey => $qData)
                    <tr style="border-bottom: 1px solid var(--vp-c-divider);">
                        <td style="padding: 0.85rem 1rem; font-weight: 600;">
                            <span style="font-family: monospace; background: var(--vp-c-bg-soft); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--vp-c-divider);">
                                {{ $qData['query'] ?? $qKey }}
                            </span>
                        </td>
                        <td style="padding: 0.85rem 1rem; font-weight: 600; color: #ef4444;">
                            {{ $qData['count'] ?? 1 }} times
                        </td>
                        <td style="padding: 0.85rem 1rem; color: var(--vp-c-text-3); font-size: 0.8rem;">
                            {{ !empty($qData['last_searched']) ? date('M j, Y g:i A', $qData['last_searched']) : 'Recently' }}
                        </td>
                        <td style="padding: 0.85rem 1rem; text-align: right;">
                            @php
                                $suggestedSlug = preg_replace('/[^a-zA-Z0-9\-]/', '-', strtolower(trim($qData['query'] ?? $qKey)));
                                $suggestedSlug = trim(preg_replace('/-+/', '-', $suggestedSlug), '-');
                            @endphp
                            <a href="{{ \SPP\App::getBaseUrl() }}/admin/editor?project={{ $projectId }}&type=page&file={{ $suggestedSlug }}" class="adm-btn adm-btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; text-decoration: none;">
                                + Create Doc
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 2.5rem; color: var(--vp-c-text-3);">
                            <span style="font-size: 1.5rem;">🎉</span>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.95rem;">No unmet searches detected. Readers are finding everything they search for!</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Page-by-Page Satisfaction Breakdown --}}
    <div style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
        <h2 style="margin: 0 0 0.5rem 0; font-size: 1.2rem;">📄 Page-by-Page Reader Satisfaction</h2>
        <p style="margin: 0 0 1rem 0; color: var(--vp-c-text-2); font-size: 0.85rem;">
            Detailed breakdown of ratings and comments grouped by documentation path.
        </p>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--vp-c-bg-soft); border-bottom: 1px solid var(--vp-c-divider); color: var(--vp-c-text-2);">
                        <th style="padding: 0.75rem 1rem;">Document Path</th>
                        <th style="padding: 0.75rem 1rem;">👍 Helpful</th>
                        <th style="padding: 0.75rem 1rem;">👎 Unhelpful</th>
                        <th style="padding: 0.75rem 1rem;">Satisfaction</th>
                        <th style="padding: 0.75rem 1rem;">Reader Comments</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pageBreakdown as $pPath => $pStats)
                    @php
                        $pTotal = $pStats['helpful'] + $pStats['unhelpful'];
                        $pRatio = $pTotal > 0 ? round(($pStats['helpful'] / $pTotal) * 100) : 0;
                    @endphp
                    <tr style="border-bottom: 1px solid var(--vp-c-divider);">
                        <td style="padding: 0.85rem 1rem; font-family: monospace; font-size: 0.85rem;">
                            {{ $pPath }}
                        </td>
                        <td style="padding: 0.85rem 1rem; color: #16a34a; font-weight: 600;">
                            {{ $pStats['helpful'] }}
                        </td>
                        <td style="padding: 0.85rem 1rem; color: #dc2626; font-weight: 600;">
                            {{ $pStats['unhelpful'] }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <span style="font-weight: 600; color: {{ $pRatio >= 80 ? '#16a34a' : ($pRatio >= 60 ? '#ca8a04' : '#dc2626') }};">
                                {{ $pRatio }}%
                            </span>
                        </td>
                        <td style="padding: 0.85rem 1rem; font-size: 0.85rem; color: var(--vp-c-text-2);">
                            @if(!empty($pStats['comments']))
                                <details>
                                    <summary style="cursor: pointer; color: var(--vp-c-brand); font-weight: 500;">
                                        View {{ count($pStats['comments']) }} Comment(s)
                                    </summary>
                                    <ul style="margin: 0.5rem 0 0 0; padding-left: 1.25rem; font-size: 0.8rem; line-height: 1.5;">
                                        @foreach($pStats['comments'] as $c)
                                            <li style="margin-bottom: 0.35rem;">
                                                "{{ $c['comment'] }}" 
                                                <span style="color: var(--vp-c-text-3);">({{ date('M j, Y', $c['created_at']) }})</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <span style="color: var(--vp-c-text-3);">None</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2.5rem; color: var(--vp-c-text-3);">
                            No page ratings submitted yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection