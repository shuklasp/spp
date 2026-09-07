{{-- Interactive API Playground Component --}}
@php
    $testerId = 'api_test_' . bin2hex(random_bytes(4));
    $method = strtoupper($params['method'] ?? 'GET');
    $endpoint = $params['endpoint'] ?? '/api/v1/resource';
@endphp

<div class="sppdocs-api-playground" id="{{ $testerId }}">
    <div class="sppdocs-api-bar">
        <span class="sppdocs-api-method sppdocs-method-{{ strtolower($method) }}">{{ $method }}</span>
        <input type="text" class="sppdocs-api-endpoint" value="{{ $endpoint }}" readonly>
        <button type="button" class="sppdocs-api-send-btn" onclick="sendApiRequest('{{ $testerId }}')">
            ▶ Send Request
        </button>
    </div>
    <div class="sppdocs-api-response-box" style="display: none;">
        <div class="sppdocs-api-response-meta">
            <span class="sppdocs-api-status">Status: <strong>200 OK</strong></span>
            <span class="sppdocs-api-time">Time: <strong>42ms</strong></span>
        </div>
        <pre class="sppdocs-api-response-body"><code></code></pre>
    </div>
</div>

<script>
if (!window.sendApiRequest) {
    window.sendApiRequest = function(id) {
        const box = document.getElementById(id);
        if (!box) return;
        const respBox = box.querySelector('.sppdocs-api-response-box');
        const codeEl = box.querySelector('.sppdocs-api-response-body code');
        const statusEl = box.querySelector('.sppdocs-api-status strong');
        const timeEl = box.querySelector('.sppdocs-api-time strong');
        const sendBtn = box.querySelector('.sppdocs-api-send-btn');
        const endpoint = box.querySelector('.sppdocs-api-endpoint').value;

        sendBtn.disabled = true;
        sendBtn.textContent = '⏳ Sending...';

        const start = Date.now();
        fetch(endpoint)
            .then(r => {
                const duration = Date.now() - start;
                statusEl.textContent = r.status + ' ' + r.statusText;
                timeEl.textContent = duration + 'ms';
                return r.text();
            })
            .then(text => {
                try {
                    const obj = JSON.parse(text);
                    codeEl.textContent = JSON.stringify(obj, null, 2);
                } catch(e) {
                    codeEl.textContent = text;
                }
                respBox.style.display = 'block';
                sendBtn.disabled = false;
                sendBtn.textContent = '▶ Send Request';
            })
            .catch(err => {
                statusEl.textContent = 'Error';
                timeEl.textContent = (Date.now() - start) + 'ms';
                codeEl.textContent = 'Network Error: ' + err.message;
                respBox.style.display = 'block';
                sendBtn.disabled = false;
                sendBtn.textContent = '▶ Send Request';
            });
    };
}
</script>
