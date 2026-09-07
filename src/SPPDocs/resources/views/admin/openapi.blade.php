@extends('layouts.admin')

@section('title', 'Interactive API Explorer — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="margin: 0 0 0.35rem 0; font-size: 1.6rem; font-weight: 700; color: var(--vp-c-text-1);">
                ⚡ Interactive API Explorer & OpenAPI Runner
            </h1>
            <p style="margin: 0; color: var(--vp-c-text-2); font-size: 0.9rem;">
                Browse endpoints, inspect schemas, generate multi-language SDK snippets, and execute live API requests directly from documentation.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="button" class="adm-btn adm-btn-primary" onclick="document.getElementById('importModal').style.display='flex'">
                📥 Import OpenAPI Spec
            </button>
        </div>
    </div>

    @if(!empty($_SESSION['adm_flash_success']))
        <div class="adm-alert adm-alert-success" style="margin-bottom: 1.25rem;">
            {{ $_SESSION['adm_flash_success'] }}
            <?php unset($_SESSION['adm_flash_success']); ?>
        </div>
    @endif

    <!-- Specs Selector Tabs -->
    @if(!empty($specs))
        <div style="display: flex; gap: 0.5rem; border-bottom: 2px solid var(--vp-c-divider); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
            @foreach($specs as $sName => $sInfo)
                <a href="@url('admin/openapi?project=' . urlencode($project_id) . '&spec=' . urlencode($sName))" 
                   class="adm-btn {{ $activeSpecName === $sName ? 'adm-btn-primary' : 'adm-btn-secondary' }} adm-btn-sm" 
                   style="border-radius: 20px; text-decoration: none;">
                    📄 {{ $sInfo['title'] }} <span style="font-size: 0.75rem; opacity: 0.8;">(v{{ $sInfo['version'] }} · {{ $sInfo['endpoint_count'] }} endpoints)</span>
                </a>
            @endforeach
        </div>
    @endif

    @if(empty($specs))
        <div class="adm-card" style="text-align: center; padding: 3rem 1.5rem; background: var(--vp-c-bg-soft); border-radius: 8px;">
            <span style="font-size: 3rem;">⚡</span>
            <h3 style="margin: 1rem 0 0.5rem 0;">No OpenAPI Specifications Imported</h3>
            <p style="color: var(--vp-c-text-2); max-width: 500px; margin: 0 auto 1.5rem auto;">
                Import your Swagger 2.0 or OpenAPI 3.0/3.1 JSON/YAML specification to generate interactive endpoints and documentation runners.
            </p>
            <button type="button" class="adm-btn adm-btn-primary" onclick="document.getElementById('importModal').style.display='flex'">
                📥 Import Your First Spec
            </button>
        </div>
    @elseif(!empty($activeSpec))
        <!-- Active Spec Header Card -->
        <div class="adm-card" style="margin-bottom: 1.5rem; padding: 1.25rem 1.5rem; background: var(--vp-c-bg-soft); border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin: 0 0 0.25rem 0; font-size: 1.3rem;">{{ $activeSpec['info']['title'] ?? $activeSpecName }}</h2>
                <div style="font-size: 0.85rem; color: var(--vp-c-text-2); display: flex; gap: 1rem;">
                    <span><strong>Version:</strong> {{ $activeSpec['info']['version'] ?? '1.0.0' }}</span>
                    <span><strong>OpenAPI:</strong> {{ $activeSpec['openapi'] ?? ($activeSpec['swagger'] ?? '3.0') }}</span>
                    <span><strong>Base URL:</strong> <code>{{ $activeSpec['servers'][0]['url'] ?? 'Relative' }}</code></span>
                </div>
                @if(!empty($activeSpec['info']['description']))
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: var(--vp-c-text-2);">
                        {{ $activeSpec['info']['description'] }}
                    </p>
                @endif
            </div>
            <div>
                <form action="@url('admin/openapi/delete')" method="POST" onsubmit="return confirm('Delete this OpenAPI specification?');" style="margin: 0;">
                    <input type="hidden" name="project_id" value="{{ $project_id }}">
                    <input type="hidden" name="name" value="{{ $activeSpecName }}">
                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">🗑️ Delete Spec</button>
                </form>
            </div>
        </div>

        <!-- Endpoints Rendered by Tag -->
        @if(!empty($groupedEndpoints))
            @foreach($groupedEndpoints as $tag => $eps)
                <div style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.15rem; margin: 0 0 0.75rem 0; display: flex; align-items: center; gap: 0.5rem; color: var(--vp-c-text-1);">
                        📁 {{ $tag }} <span style="font-size: 0.8rem; font-weight: normal; color: var(--vp-c-text-3);">({{ count($eps) }} endpoints)</span>
                    </h3>
                    @foreach($eps as $ep)
                        @spppartial('partials/api_endpoint_card.blade.php', ['endpoint' => $ep])
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="adm-card" style="padding: 2rem; text-align: center; color: var(--vp-c-text-3);">
                No endpoints found in this specification.
            </div>
        @endif
    @endif
</div>

<!-- Import Spec Modal -->
<div id="importModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: var(--vp-c-bg); border-radius: 10px; width: 600px; max-width: 90vw; padding: 1.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); border: 1px solid var(--vp-c-divider);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="margin: 0; font-size: 1.2rem; color: var(--vp-c-text-1);">📥 Import OpenAPI Specification</h3>
            <button type="button" onclick="document.getElementById('importModal').style.display='none'" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--vp-c-text-3);">&times;</button>
        </div>

        <form action="@url('admin/openapi/import')" method="POST">
            <input type="hidden" name="project_id" value="{{ $project_id }}">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Spec Identifier / Name</label>
                <input type="text" name="name" placeholder="e.g. petstore, v1-api" required style="width: 100%; box-sizing: border-box; padding: 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Import from Remote URL (Optional)</label>
                <input type="url" name="url" placeholder="https://api.example.com/openapi.json" style="width: 100%; box-sizing: border-box; padding: 0.5rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;">
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem;">Or Paste YAML / JSON Specification Content</label>
                <textarea name="content" rows="7" placeholder="openapi: 3.0.0&#10;info:&#10;  title: My API&#10;  version: 1.0.0" style="width: 100%; box-sizing: border-box; padding: 0.5rem; font-family: monospace; font-size: 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 6px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="document.getElementById('importModal').style.display='none'">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">Save & Parse Spec</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleEndpointCard(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return;
    const body = card.querySelector('.sppdocs-ep-body');
    const arrow = card.querySelector('.sppdocs-ep-toggle-arrow');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        if (arrow) arrow.style.transform = 'rotate(0deg)';
    } else {
        body.style.display = 'none';
        if (arrow) arrow.style.transform = 'rotate(-90deg)';
    }
}

function switchEndpointTab(cardId, tab) {
    const panes = document.querySelectorAll('#' + cardId + ' .sppdocs-ep-tab-pane');
    panes.forEach(p => p.style.display = 'none');

    const btns = document.querySelectorAll('#' + cardId + ' .sppdocs-ep-tab-btn');
    btns.forEach(b => {
        b.classList.remove('active');
        b.style.color = 'var(--vp-c-text-2)';
    });

    const activePane = document.getElementById(cardId + '_pane_' + tab);
    if (activePane) activePane.style.display = 'block';

    const activeBtn = document.getElementById(cardId + '_tab_btn_' + tab);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.style.color = 'var(--vp-c-brand)';
    }
}

function executeEndpointLiveRequest(cardId, method, pathTemplate, serverUrl) {
    const loading = document.getElementById(cardId + '_loading');
    if (loading) loading.style.display = 'inline';

    // Collect parameters
    const paramInputs = document.querySelectorAll('.sppdocs-ep-param[data-card="' + cardId + '"]');
    let resolvedPath = pathTemplate;
    const queryParams = new URLSearchParams();
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    };

    paramInputs.forEach(input => {
        const pName = input.getAttribute('data-name');
        const pIn = input.getAttribute('data-in');
        const val = input.value.trim();
        if (val) {
            if (pIn === 'path') {
                resolvedPath = resolvedPath.replace('{' + pName + '}', encodeURIComponent(val));
            } else if (pIn === 'query') {
                queryParams.append(pName, val);
            } else if (pIn === 'header') {
                headers[pName] = val;
            }
        }
    });

    const qStr = queryParams.toString();
    const fullRelativeUrl = resolvedPath + (qStr ? '?' + qStr : '');

    let baseUrl = serverUrl || window.location.origin;
    if (!baseUrl.startsWith('http://') && !baseUrl.startsWith('https://')) {
        baseUrl = window.location.origin + (baseUrl.startsWith('/') ? '' : '/') + baseUrl;
    }
    const targetUrl = baseUrl.replace(/\/+$/, '') + (fullRelativeUrl.startsWith('/') ? '' : '/') + fullRelativeUrl;

    // Collect body if present
    let body = null;
    const bodyInput = document.querySelector('.sppdocs-ep-body-input[data-card="' + cardId + '"]');
    if (bodyInput && ['POST', 'PUT', 'PATCH'].includes(method)) {
        body = bodyInput.value;
    }

    const proxyEndpoint = '{{ \SPP\App::getBaseUrl() }}/api/openapi/proxy';

    fetch(proxyEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            url: targetUrl,
            method: method,
            headers: headers,
            body: body
        })
    })
    .then(res => res.json())
    .then(data => {
        if (loading) loading.style.display = 'none';

        // Switch to response tab
        switchEndpointTab(cardId, 'response');

        const meta = document.getElementById(cardId + '_resp_meta');
        const statusBadge = document.getElementById(cardId + '_status_badge');
        const latencyBadge = document.getElementById(cardId + '_latency_badge');
        const respBody = document.getElementById(cardId + '_resp_body');

        if (meta) meta.style.display = 'flex';
        if (statusBadge) {
            statusBadge.textContent = data.status + ' ' + (data.status >= 200 && data.status < 300 ? 'OK' : (data.status >= 400 ? 'ERROR' : ''));
            statusBadge.style.background = data.status >= 200 && data.status < 300 ? '#ecfdf5' : '#fef2f2';
            statusBadge.style.color = data.status >= 200 && data.status < 300 ? '#059669' : '#dc2626';
            statusBadge.style.border = '1px solid ' + (data.status >= 200 && data.status < 300 ? '#a7f3d0' : '#fecaca');
        }
        if (latencyBadge) {
            latencyBadge.textContent = (data.latency_ms || 0) + ' ms';
        }
        if (respBody) {
            if (data.json) {
                respBody.textContent = JSON.stringify(data.json, null, 2);
            } else {
                respBody.textContent = data.body || data.error || 'Empty response';
            }
        }
    })
    .catch(err => {
        if (loading) loading.style.display = 'none';
        const respBody = document.getElementById(cardId + '_resp_body');
        if (respBody) respBody.textContent = 'Request error: ' + err.message;
    });
}
</script>
@endsection