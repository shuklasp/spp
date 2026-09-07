@extends('layouts.admin')

@section('title', 'Multi-Language (i18n) Documentation Hub — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="margin: 0 0 0.35rem 0; font-size: 1.6rem; font-weight: 700; color: var(--vp-c-text-1);">
                🌐 Multi-Language (i18n) Documentation Hub
            </h1>
            <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.9rem;">
                Translation Parity Matrix, automated out-of-sync drift detection, and side-by-side localization workbench.
            </p>
        </div>
        <div>
            <span class="adm-badge adm-badge-admin" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">
                Source Locale: {{ strtoupper($parity['default_locale']) }} (100%)
            </span>
        </div>
    </div>

    @if(!empty($_SESSION['adm_flash_success']))
        <div class="adm-alert adm-alert-success" style="margin-bottom: 1.25rem;">
            {{ $_SESSION['adm_flash_success'] }}
            <?php unset($_SESSION['adm_flash_success']); ?>
        </div>
    @endif

    <!-- Locale Completion Stat Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.75rem;">
        @foreach($parity['target_locales'] as $loc)
            @php
                $stat = $parity['stats'][$loc] ?? [];
                $locInfo = $parity['locales'][$loc] ?? ['name' => $loc, 'flag' => '🌐'];
                $pct = $stat['percent'] ?? 0;
            @endphp
            <div class="adm-card" style="padding: 1.1rem; background: var(--vp-c-bg-soft); border-radius: 8px; border: 1px solid var(--vp-c-divider);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.4rem;">{{ $locInfo['flag'] }}</span>
                        <strong>{{ $locInfo['name'] }}</strong>
                    </div>
                    <span style="font-weight: 700; font-size: 1.1rem; color: {{ $pct >= 80 ? '#10b981' : ($pct >= 40 ? '#f59e0b' : '#ef4444') }};">
                        {{ $pct }}%
                    </span>
                </div>
                <!-- Progress Bar -->
                <div style="height: 6px; background: var(--vp-c-divider); border-radius: 3px; overflow: hidden; margin-bottom: 0.6rem;">
                    <div style="width: {{ $pct }}%; height: 100%; background: {{ $pct >= 80 ? '#10b981' : ($pct >= 40 ? '#f59e0b' : '#ef4444') }};"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--vp-c-text-2);">
                    <span title="Synchronized">✓ {{ $stat['synchronized'] ?? 0 }}</span>
                    <span title="Outdated / Drift" style="color: #f59e0b;">⚠️ {{ $stat['outdated'] ?? 0 }}</span>
                    <span title="Missing" style="color: #ef4444;">❌ {{ $stat['missing'] ?? 0 }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Translation Parity Matrix Table -->
    <div class="adm-card" style="background: var(--vp-c-bg); border-radius: 8px; border: 1px solid var(--vp-c-divider); overflow: hidden; margin-bottom: 2rem;">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--vp-c-divider); display: flex; justify-content: space-between; align-items: center; background: var(--vp-c-bg-soft);">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--vp-c-text-1);">📋 Translation Parity Matrix</h3>
            <span style="font-size: 0.85rem; color: var(--vp-c-text-2);">{{ $parity['total_source_docs'] }} documents tracked</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="adm-table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="background: var(--vp-c-bg-mute); text-align: left; border-bottom: 1px solid var(--vp-c-divider);">
                        <th style="padding: 0.75rem 1rem;">Document Title & Path</th>
                        <th style="padding: 0.75rem 1rem; width: 140px;">Source Updated</th>
                        @foreach($parity['target_locales'] as $loc)
                            @php $locInfo = $parity['locales'][$loc] ?? ['name' => $loc, 'flag' => '🌐']; @endphp
                            <th style="padding: 0.75rem 1rem; text-align: center; width: 120px;">
                                {{ $locInfo['flag'] }} {{ $locInfo['name'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($parity['matrix'] as $row)
                        <tr style="border-bottom: 1px solid var(--vp-c-divider);">
                            <td style="padding: 0.75rem 1rem;">
                                <div style="font-weight: 600; color: var(--vp-c-text-1);">{{ $row['title'] }}</div>
                                <code style="font-size: 0.78rem; color: var(--vp-c-text-3);">{{ $row['slug'] }}</code>
                            </td>
                            <td style="padding: 0.75rem 1rem; font-size: 0.8rem; color: var(--vp-c-text-2);">
                                {{ date('M j, Y', $row['source_mtime']) }}
                            </td>
                            @foreach($parity['target_locales'] as $loc)
                                @php
                                    $locData = $row['locales'][$loc] ?? ['status' => 'missing'];
                                    $st = $locData['status'];
                                @endphp
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <button type="button" 
                                            onclick="openTranslateWorkbench('{{ $row['slug'] }}', '{{ $loc }}')"
                                            style="border: none; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.78rem; font-weight: 600; 
                                                   background: {{ $st === 'synchronized' ? '#ecfdf5' : ($st === 'outdated' ? '#fffbeb' : '#fef2f2') }};
                                                   color: {{ $st === 'synchronized' ? '#059669' : ($st === 'outdated' ? '#d97706' : '#dc2626') }};
                                                   border: 1px solid {{ $st === 'synchronized' ? '#a7f3d0' : ($st === 'outdated' ? '#fde68a' : '#fecaca') }};">
                                        @if($st === 'synchronized')
                                            ✓ Sync
                                        @elseif($st === 'outdated')
                                            ⚠️ Outdated
                                        @else
                                            + Translate
                                        @endif
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Side-by-Side Localization Workbench Modal -->
<div id="workbenchModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: var(--vp-c-bg); border-radius: 10px; width: 1100px; max-width: 95vw; height: 85vh; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: 1px solid var(--vp-c-divider); display: flex; flex-direction: column;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 0.75rem;">
            <div>
                <h3 style="margin: 0 0 0.2rem 0; font-size: 1.25rem; color: var(--vp-c-text-1);">
                    🌐 Localization Workbench: <span id="wb_title_slug" style="font-family: monospace; color: var(--vp-c-brand);"></span>
                </h3>
                <span style="font-size: 0.85rem; color: var(--vp-c-text-2);">Target Locale: <strong id="wb_target_locale_label"></strong></span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick="copySourceToTarget()">📋 Copy Source</button>
                <button type="button" class="adm-btn adm-btn-primary adm-btn-sm" onclick="saveTranslationContent()">💾 Save Translation</button>
                <button type="button" onclick="document.getElementById('workbenchModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--vp-c-text-3); margin-left: 0.5rem;">&times;</button>
            </div>
        </div>

        <!-- 2 Column Side-by-Side Editor -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; flex: 1; min-height: 0;">
            <!-- Left: Source Document (Read Only) -->
            <div style="display: flex; flex-direction: column; height: 100%;">
                <div style="font-size: 0.82rem; font-weight: 700; color: var(--vp-c-text-2); margin-bottom: 0.35rem; display: flex; justify-content: space-between;">
                    <span>🇺🇸 Source English Reference (Read-Only)</span>
                </div>
                <textarea id="wb_source_text" readonly style="flex: 1; width: 100%; box-sizing: border-box; padding: 0.75rem; font-family: monospace; font-size: 0.82rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; background: var(--vp-c-bg-mute); color: var(--vp-c-text-2); resize: none;"></textarea>
            </div>

            <!-- Right: Target Locale Editor -->
            <div style="display: flex; flex-direction: column; height: 100%;">
                <div style="font-size: 0.82rem; font-weight: 700; color: var(--vp-c-text-1); margin-bottom: 0.35rem; display: flex; justify-content: space-between;">
                    <span id="wb_target_header">Target Translation</span>
                    <span id="wb_save_status" style="font-weight: normal; color: #10b981;"></span>
                </div>
                <textarea id="wb_target_text" style="flex: 1; width: 100%; box-sizing: border-box; padding: 0.75rem; font-family: monospace; font-size: 0.82rem; border: 1px solid var(--vp-c-divider); border-radius: 6px; background: var(--vp-c-bg); color: var(--vp-c-text-1); resize: none;"></textarea>
            </div>
        </div>
    </div>
</div>

<script>
let currentWbSlug = '';
let currentWbLocale = '';
const projectId = '{{ $project_id }}';
const defaultVersion = '{{ $project['default_version'] ?? 'v1' }}';
const baseUrl = '{{ \SPP\App::getBaseUrl() }}';

function openTranslateWorkbench(slug, locale) {
    currentWbSlug = slug;
    currentWbLocale = locale;

    document.getElementById('wb_title_slug').textContent = slug;
    document.getElementById('wb_target_locale_label').textContent = locale.toUpperCase();
    document.getElementById('wb_target_header').textContent = locale.toUpperCase() + ' Translation';
    document.getElementById('wb_save_status').textContent = '';

    document.getElementById('wb_source_text').value = 'Loading source content...';
    document.getElementById('wb_target_text').value = 'Loading target content...';
    document.getElementById('workbenchModal').style.display = 'flex';

    fetch(baseUrl + '/admin/i18n/pair?project=' + encodeURIComponent(projectId) + '&version=' + encodeURIComponent(defaultVersion) + '&locale=' + encodeURIComponent(locale) + '&slug=' + encodeURIComponent(slug))
        .then(res => res.json())
        .then(data => {
            document.getElementById('wb_source_text').value = data.source_content || '';
            document.getElementById('wb_target_text').value = data.target_content || '';
        })
        .catch(err => {
            document.getElementById('wb_source_text').value = 'Error loading content: ' + err.message;
        });
}

function copySourceToTarget() {
    const src = document.getElementById('wb_source_text').value;
    if (confirm('Replace target translation with source English text?')) {
        document.getElementById('wb_target_text').value = src;
    }
}

function saveTranslationContent() {
    const content = document.getElementById('wb_target_text').value;
    const statusEl = document.getElementById('wb_save_status');
    statusEl.textContent = 'Saving...';

    fetch(baseUrl + '/admin/i18n/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            project_id: projectId,
            version: defaultVersion,
            locale: currentWbLocale,
            slug: currentWbSlug,
            content: content
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            statusEl.textContent = '✓ Saved successfully!';
            setTimeout(() => { statusEl.textContent = ''; }, 3000);
        } else {
            statusEl.textContent = 'Error saving';
        }
    })
    .catch(err => {
        statusEl.textContent = 'Error: ' + err.message;
    });
}
</script>
@endsection