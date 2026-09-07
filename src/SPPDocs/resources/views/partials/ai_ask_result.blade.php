{{-- AI Ask RAG Result Partial --}}
@php
    $answer = $response['answer'] ?? 'No response generated.';
    $citations = $response['citations'] ?? [];
    $provider = $response['provider'] ?? 'offline';
    $query = $response['query'] ?? '';
    $providerLabel = match($provider) {
        'openai' => '🤖 OpenAI GPT-4o RAG',
        'gemini' => '✨ Google Gemini Pro RAG',
        'anthropic' => '🧠 Claude 3.5 Sonnet RAG',
        'ollama' => '🦙 Local Ollama RAG',
        'custom' => '⚡ Custom OpenAI-Compatible RAG',
        default => '⚡ Zero-API-Key Offline BM25 Vectorizer'
    };
@endphp

<div class="sppdocs-ai-result-card">
    <div class="sppdocs-ai-result-header">
        <span class="sppdocs-ai-badge">{{ $providerLabel }}</span>
        <span class="sppdocs-ai-query-tag">Q: "{{ htmlspecialchars($query) }}"</span>
    </div>

    <div class="sppdocs-ai-answer-body">
        {!! nl2br(htmlspecialchars($answer)) !!}
    </div>

    @if(!empty($citations))
        <div class="sppdocs-ai-citations-section">
            <div class="sppdocs-ai-citations-title">Verified Sources & Citations:</div>
            <div class="sppdocs-ai-citations-grid">
                @foreach($citations as $idx => $cite)
                    <a href="{{ $cite['url'] ?? '#' }}" class="sppdocs-ai-citation-pill" onclick="if(window.closeCommandPalette) window.closeCommandPalette()">
                        <span class="sppdocs-ai-citation-num">[{{ $idx + 1 }}]</span>
                        <span class="sppdocs-ai-citation-text">{!! $cite['title'] ?? 'Section' !!}</span>
                        <span class="sppdocs-ai-citation-icon">↗</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
