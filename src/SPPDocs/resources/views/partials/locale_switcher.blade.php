{{-- Multi-Language Locale Switcher Partial --}}
@php
    $locales = \App\SPPDocs\Services\I18nService::getProjectLocales($project ?? []);
    $currentLocale = $locale ?? ($project['default_locale'] ?? 'en');
    $currentLocInfo = $locales[$currentLocale] ?? ['name' => strtoupper($currentLocale), 'flag' => '🌐'];
@endphp

@if(count($locales) > 1)
<div class="sppdocs-locale-switcher" style="position: relative; display: inline-block;">
    <button type="button" 
            class="sppdocs-locale-btn" 
            onclick="document.getElementById('localeMenu').classList.toggle('open')"
            style="display: flex; align-items: center; gap: 0.35rem; background: var(--vp-c-bg-soft); border: 1px solid var(--vp-c-divider); border-radius: 6px; padding: 0.3rem 0.6rem; font-size: 0.82rem; color: var(--vp-c-text-1); cursor: pointer;">
        <span>{{ $currentLocInfo['flag'] }}</span>
        <span>{{ $currentLocInfo['name'] }}</span>
        <span style="font-size: 0.7rem; color: var(--vp-c-text-3);">▼</span>
    </button>

    <div id="localeMenu" class="sppdocs-locale-menu" style="display: none; position: absolute; right: 0; top: 110%; background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); min-width: 140px; z-index: 50; overflow: hidden;">
        @foreach($locales as $code => $loc)
            @php
                $targetUrl = \SPP\App::getBaseUrl() . '/docs/' . ($project_id ?? '') . '/' . ($version ?? 'v1');
                if ($code !== ($project['default_locale'] ?? 'en')) {
                    $targetUrl .= '/' . $code;
                }
                if (!empty($current_path)) {
                    $targetUrl .= '/' . $current_path;
                }
            @endphp
            <a href="{{ $targetUrl }}" 
               style="display: flex; align-items: center; gap: 0.5rem; padding: 0.45rem 0.8rem; font-size: 0.82rem; color: var(--vp-c-text-1); text-decoration: none; border-bottom: 1px solid var(--vp-c-divider-light); background: {{ $code === $currentLocale ? 'var(--vp-c-bg-mute)' : 'transparent' }};">
                <span>{{ $loc['flag'] }}</span>
                <span>{{ $loc['name'] }}</span>
                @if($code === $currentLocale)
                    <span style="margin-left: auto; color: var(--vp-c-brand); font-weight: bold;">✓</span>
                @endif
            </a>
        @endforeach
    </div>
</div>

<style>
.sppdocs-locale-menu.open {
    display: block !important;
}
</style>

<script>
document.addEventListener('click', function(e) {
    const switcher = document.querySelector('.sppdocs-locale-switcher');
    const menu = document.getElementById('localeMenu');
    if (switcher && menu && !switcher.contains(e.target)) {
        menu.classList.remove('open');
    }
});
</script>
@endif