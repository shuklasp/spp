{{-- Interactive OpenAPI Endpoint Card --}}
@php
    $methodColors = [
        'GET' => ['bg' => '#ecfdf5', 'text' => '#059669', 'border' => '#a7f3d0'],
        'POST' => ['bg' => '#eff6ff', 'text' => '#2563eb', 'border' => '#bfdbfe'],
        'PUT' => ['bg' => '#fffbeb', 'text' => '#d97706', 'border' => '#fde68a'],
        'PATCH' => ['bg' => '#fef3c7', 'text' => '#b45309', 'border' => '#fcd34d'],
        'DELETE' => ['bg' => '#fef2f2', 'text' => '#dc2626', 'border' => '#fecaca'],
    ];
    $mc = $methodColors[$endpoint['method']] ?? ['bg' => '#f3f4f6', 'text' => '#4b5563', 'border' => '#e5e7eb'];
    $cardId = 'ep_' . md5($endpoint['method'] . '_' . $endpoint['path']);
@endphp

<div class="sppdocs-endpoint-card" id="{{ $cardId }}" style="border: 1px solid var(--vp-c-divider); border-radius: 8px; margin-bottom: 1.25rem; background: var(--vp-c-bg); overflow: hidden;">
    <!-- Endpoint Header Summary Bar -->
    <div class="sppdocs-ep-header" onclick="toggleEndpointCard('{{ $cardId }}')" style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1.2rem; cursor: pointer; background: var(--vp-c-bg-soft); border-bottom: 1px solid transparent; user-select: none;">
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <span style="font-weight: 700; font-size: 0.8rem; padding: 0.2rem 0.55rem; border-radius: 4px; background: {{ $mc['bg'] }}; color: {{ $mc['text'] }}; border: 1px solid {{ $mc['border'] }}; font-family: monospace;">
                {{ $endpoint['method'] }}
            </span>
            <code style="font-size: 0.95rem; font-weight: 600; color: var(--vp-c-text-1);">{{ $endpoint['path'] }}</code>
            <span style="font-size: 0.85rem; color: var(--vp-c-text-2);">{{ $endpoint['summary'] ?? '' }}</span>
        </div>
        <span class="sppdocs-ep-toggle-arrow" style="color: var(--vp-c-text-3); font-size: 0.85rem; transition: transform 0.2s;">▼</span>
    </div>

    <!-- Endpoint Card Body -->
    <div class="sppdocs-ep-body" style="padding: 1.25rem; display: block;">
        @if(!empty($endpoint['description']))
            <p style="margin-top: 0; margin-bottom: 1rem; color: var(--vp-c-text-2); font-size: 0.9rem;">
                {{ $endpoint['description'] }}
            </p>
        @endif

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
            <!-- Left Column: Parameters & Body Input -->
            <div>
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.85rem; text-transform: uppercase; color: var(--vp-c-text-2); letter-spacing: 0.05em;">Request Parameters</h4>
                
                @if(empty($endpoint['parameters']) && empty($endpoint['request_body']))
                    <p style="font-size: 0.85rem; color: var(--vp-c-text-3); font-style: italic;">No parameters required.</p>
                @endif

                @if(!empty($endpoint['parameters']))
                    <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1rem;">
                        @foreach($endpoint['parameters'] as $param)
                            <div>
                                <label style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.2rem;">
                                    <span>
                                        <code>{{ $param['name'] }}</code>
                                        @if(!empty($param['required']))<span style="color: #ef4444;">*</span>@endif
                                        <span style="font-weight: normal; color: var(--vp-c-text-3); font-size: 0.75rem;">({{ $param['in'] }})</span>
                                    </span>
                                </label>
                                <input type="text" 
                                       class="sppdocs-ep-param" 
                                       data-card="{{ $cardId }}" 
                                       data-name="{{ $param['name'] }}" 
                                       data-in="{{ $param['in'] }}" 
                                       placeholder="{{ $param['example'] ?? $param['description'] }}" 
                                       value="{{ $param['example'] ?? '' }}"
                                       style="width: 100%; box-sizing: border-box; padding: 0.4rem 0.6rem; font-size: 0.85rem; border: 1px solid var(--vp-c-divider); border-radius: 5px; background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($endpoint['request_body']))
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.2rem;">
                            Request Body <span style="font-weight: normal; color: var(--vp-c-text-3);">({{ $endpoint['request_body']['content_type'] ?? 'application/json' }})</span>
                        </label>
                        <textarea class="sppdocs-ep-body-input" 
                                  data-card="{{ $cardId }}" 
                                  rows="6" 
                                  style="width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem; font-family: monospace; font-size: 0.8rem; border: 1px solid var(--vp-c-divider); border-radius: 5px; background: var(--vp-c-bg-mute); color: var(--vp-c-text-1);">{{ $endpoint['request_body']['sample'] ?? '{}' }}</textarea>
                    </div>
                @endif

                <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.75rem;">
                    <button type="button" 
                            class="adm-btn adm-btn-primary adm-btn-sm" 
                            onclick="executeEndpointLiveRequest('{{ $cardId }}', '{{ $endpoint['method'] }}', '{{ $endpoint['path'] }}', '{{ $endpoint['server_url'] }}')">
                        🚀 Send Live Request
                    </button>
                    <span id="{{ $cardId }}_loading" style="display: none; font-size: 0.8rem; color: var(--vp-c-text-2);">Sending...</span>
                </div>
            </div>

            <!-- Right Column: Response Inspector & Code Snippets -->
            <div>
                <!-- Snippet Tabs -->
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--vp-c-divider); padding-bottom: 0.4rem; margin-bottom: 0.6rem;">
                    <div style="display: flex; gap: 0.4rem;">
                        <button type="button" class="sppdocs-ep-tab-btn active" onclick="switchEndpointTab('{{ $cardId }}', 'response')" id="{{ $cardId }}_tab_btn_response" style="background: none; border: none; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; cursor: pointer; border-radius: 4px; color: var(--vp-c-brand);">Live Response</button>
                        <button type="button" class="sppdocs-ep-tab-btn" onclick="switchEndpointTab('{{ $cardId }}', 'curl')" id="{{ $cardId }}_tab_btn_curl" style="background: none; border: none; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; cursor: pointer; border-radius: 4px; color: var(--vp-c-text-2);">cURL</button>
                        <button type="button" class="sppdocs-ep-tab-btn" onclick="switchEndpointTab('{{ $cardId }}', 'js')" id="{{ $cardId }}_tab_btn_js" style="background: none; border: none; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; cursor: pointer; border-radius: 4px; color: var(--vp-c-text-2);">JS (Fetch)</button>
                        <button type="button" class="sppdocs-ep-tab-btn" onclick="switchEndpointTab('{{ $cardId }}', 'php')" id="{{ $cardId }}_tab_btn_php" style="background: none; border: none; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; cursor: pointer; border-radius: 4px; color: var(--vp-c-text-2);">PHP</button>
                        <button type="button" class="sppdocs-ep-tab-btn" onclick="switchEndpointTab('{{ $cardId }}', 'python')" id="{{ $cardId }}_tab_btn_python" style="background: none; border: none; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; cursor: pointer; border-radius: 4px; color: var(--vp-c-text-2);">Python</button>
                    </div>
                </div>

                <!-- Tab Pane: Live Response -->
                <div id="{{ $cardId }}_pane_response" class="sppdocs-ep-tab-pane">
                    <div id="{{ $cardId }}_resp_meta" style="display: none; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.8rem;">
                        <span id="{{ $cardId }}_status_badge" style="font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px;"></span>
                        <span id="{{ $cardId }}_latency_badge" style="color: var(--vp-c-text-3);"></span>
                    </div>
                    <pre id="{{ $cardId }}_resp_body" style="background: var(--vp-c-bg-alt); padding: 0.75rem; border-radius: 6px; font-size: 0.78rem; font-family: monospace; overflow: auto; max-height: 240px; margin: 0; color: var(--vp-c-text-2); border: 1px solid var(--vp-c-divider);">Click "Send Live Request" to inspect the live response.</pre>
                </div>

                <!-- Tab Pane: cURL -->
                @php
                    $initialUrl = rtrim($endpoint['server_url'] ?: \SPP\App::getBaseUrl(), '/') . $endpoint['path'];
                    $snippets = \App\SPPDocs\Services\OpenApiService::generateSnippets($endpoint['method'], $initialUrl, ['Accept' => 'application/json', 'Content-Type' => 'application/json'], $endpoint['request_body']['sample'] ?? null);
                @endphp
                <div id="{{ $cardId }}_pane_curl" class="sppdocs-ep-tab-pane" style="display: none;">
                    <pre style="background: var(--vp-c-bg-alt); padding: 0.75rem; border-radius: 6px; font-size: 0.78rem; font-family: monospace; overflow: auto; max-height: 240px; margin: 0; color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider);"><code>{{ $snippets['curl'] }}</code></pre>
                </div>

                <!-- Tab Pane: JS -->
                <div id="{{ $cardId }}_pane_js" class="sppdocs-ep-tab-pane" style="display: none;">
                    <pre style="background: var(--vp-c-bg-alt); padding: 0.75rem; border-radius: 6px; font-size: 0.78rem; font-family: monospace; overflow: auto; max-height: 240px; margin: 0; color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider);"><code>{{ $snippets['javascript'] }}</code></pre>
                </div>

                <!-- Tab Pane: PHP -->
                <div id="{{ $cardId }}_pane_php" class="sppdocs-ep-tab-pane" style="display: none;">
                    <pre style="background: var(--vp-c-bg-alt); padding: 0.75rem; border-radius: 6px; font-size: 0.78rem; font-family: monospace; overflow: auto; max-height: 240px; margin: 0; color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider);"><code>{{ $snippets['php'] }}</code></pre>
                </div>

                <!-- Tab Pane: Python -->
                <div id="{{ $cardId }}_pane_python" class="sppdocs-ep-tab-pane" style="display: none;">
                    <pre style="background: var(--vp-c-bg-alt); padding: 0.75rem; border-radius: 6px; font-size: 0.78rem; font-family: monospace; overflow: auto; max-height: 240px; margin: 0; color: var(--vp-c-text-1); border: 1px solid var(--vp-c-divider);"><code>{{ $snippets['python'] }}</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>