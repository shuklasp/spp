{{-- Interactive Code Tabs Component --}}
@php
    $tabId = 'tabs_' . bin2hex(random_bytes(4));
@endphp
<div class="sppdocs-component-tabs" id="{{ $tabId }}">
    <div class="sppdocs-tab-nav">
        @foreach($tabs as $idx => $tab)
        <button type="button" class="sppdocs-tab-btn {{ $idx === 0 ? 'active' : '' }}" 
                onclick="switchDocTab('{{ $tabId }}', {{ $idx }})">
            {{ $tab['title'] }}
        </button>
        @endforeach
    </div>
    <div class="sppdocs-tab-panes">
        @foreach($tabs as $idx => $tab)
        <div class="sppdocs-tab-pane {{ $idx === 0 ? 'active' : '' }}" data-tab-index="{{ $idx }}">
            {!! $tab['content'] !!}
        </div>
        @endforeach
    </div>
</div>

<script>
if (!window.switchDocTab) {
    window.switchDocTab = function(containerId, activeIndex) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const buttons = container.querySelectorAll('.sppdocs-tab-btn');
        const panes = container.querySelectorAll('.sppdocs-tab-pane');
        buttons.forEach((btn, i) => btn.classList.toggle('active', i === activeIndex));
        panes.forEach((pane, i) => pane.classList.toggle('active', i === activeIndex));
    };
}
</script>
