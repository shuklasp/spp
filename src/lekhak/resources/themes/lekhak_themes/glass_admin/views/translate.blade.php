@extends('layout')

@section('actions')
<a href="/school1/lekhak/admin/content" class="btn btn-secondary">
    ⬅️ Back to Content
</a>
@endsection

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
    <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
</div>

<div style="display: flex; gap: 15px; margin-bottom: 25px;">
    @foreach($languages as $code => $name)
        <a href="?lang={{ $code }}" class="btn {{ $targetLang === $code ? 'btn-primary' : 'btn-secondary' }}" style="padding: 8px 16px; text-decoration: none;">
            {{ $name }}
        </a>
    @endforeach
</div>

<div class="glass-panel" style="padding: 20px;">
    <h3 style="margin-top: 0; color: var(--text-main);">Translating into {{ $languages[$targetLang] }}</h3>
    
    @php
        $fieldsToTranslate = ['title' => $node->title, 'body' => $node->body];
        // Merge custom metadata fields
        if (!empty($node->metadata)) {
            $meta = is_string($node->metadata) ? json_decode($node->metadata, true) : (array)$node->metadata;
            foreach ($meta as $k => $v) {
                if (is_string($v)) {
                    $fieldsToTranslate[$k] = $v;
                }
            }
        }
    @endphp

    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="border-bottom: 1px solid var(--glass-border);">
                <th style="padding: 12px; color: var(--text-dim); font-size: 0.85rem; width: 15%;">Field</th>
                <th style="padding: 12px; color: var(--text-dim); font-size: 0.85rem; width: 35%;">Original (English)</th>
                <th style="padding: 12px; color: var(--text-dim); font-size: 0.85rem; width: 50%;">Translation</th>
            </tr>
        </thead>
        <tbody>
            @foreach($fieldsToTranslate as $field => $originalValue)
            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                <td style="padding: 16px 12px; vertical-align: top; font-weight: bold; color: var(--accent-primary);">
                    {{ ucfirst($field) }}
                </td>
                <td style="padding: 16px 12px; vertical-align: top; color: var(--text-dim); background: rgba(0,0,0,0.1); border-right: 1px solid var(--glass-border);">
                    {{ $originalValue }}
                </td>
                <td style="padding: 16px 12px; vertical-align: top;">
                    <form id="form-{{ $field }}" method="POST" action="" style="display: flex; gap: 10px; flex-direction: column;">
                        <input type="hidden" name="action" value="save_translation">
                        <input type="hidden" name="field" value="{{ $field }}">
                        @if($field === 'body' || $field === 'description')
                            <div id="lekhni-host-{{ $field }}" style="height: 500px; width: 100%; border: 1px solid var(--glass-border); border-radius: 8px; overflow: hidden; position: relative;"></div>
                            <textarea id="textarea-{{ $field }}" name="value" style="display:none;">{{ $translations[$field] ?? '' }}</textarea>
                            <script type="module">
                                import LekhniEditor from '{{ rtrim($web_root ?? '', '/') }}/src/lekhak/comp/editor.js?v=1';
                                const host = document.getElementById('lekhni-host-{{ $field }}');
                                const txt = document.getElementById('textarea-{{ $field }}');
                                const dummyAdapter = { notify: (msg, type) => console.log(msg) };
                                const editor = new LekhniEditor(dummyAdapter, host, {
                                    body: txt.value,
                                    embedded: true,
                                    onChange: (val) => { txt.value = val; }
                                });
                                window.lekhniEditor_{{ $field }} = editor;
                                editor.onInit().then(() => {
                                    editor.update();
                                    if (editor.onMount) editor.onMount();
                                });
                                document.getElementById('form-{{ $field }}').addEventListener('submit', () => {
                                    if (editor.state && typeof editor.state.body !== 'undefined') {
                                        txt.value = editor.state.body;
                                    }
                                });
                            </script>
                        @else
                            <textarea name="value" rows="4" class="form-control" style="width: 100%; padding: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: var(--text-main); border-radius: 4px; resize: vertical;">{{ $translations[$field] ?? '' }}</textarea>
                        @endif
                        <div style="text-align: right;">
                            <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.85rem;">Save {{ ucfirst($field) }}</button>
                        </div>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
