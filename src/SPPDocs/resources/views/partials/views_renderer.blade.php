{{-- Reusable Dynamic Views Renderer Partial --}}
@php
    $view = $viewResult['view'] ?? $view ?? [];
    $items = $viewResult['data'] ?? $items ?? [];
    $format = $view['display_format'] ?? 'grid';
    $columns = $view['columns'] ?? ['title', 'date', 'author', 'category'];
    $baseUrl = \SPP\App::getBaseUrl();
    $proj = $project_id ?? ($view['project_id'] ?? 'spp');
@endphp

<div class="sppdocs-view-container" id="view_{{ $view['id'] ?? 'custom' }}">
    @if(empty($items))
        <div class="sppdocs-view-empty" style="text-align: center; padding: 2.5rem 1.5rem; background: var(--vp-c-bg-soft); border-radius: 8px; border: 1px dashed var(--vp-c-divider); color: var(--vp-c-text-3);">
            <p style="margin: 0; font-size: 0.95rem;">No items match this view's criteria.</p>
        </div>
    @elseif($format === 'table')
        {{-- Table Display Format --}}
        <div style="overflow-x: auto; border: 1px solid var(--vp-c-divider); border-radius: 8px; background: var(--vp-c-bg);">
            <table class="adm-table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="background: var(--vp-c-bg-soft); text-align: left; border-bottom: 1px solid var(--vp-c-divider);">
                        @foreach($columns as $col)
                            <th style="padding: 0.75rem 1rem; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: var(--vp-c-text-2);">
                                {{ ucfirst(str_replace('_', ' ', $col)) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $row)
                        @php
                            $targetSlug = $row['slug'] ?? ($row['filename'] ?? '');
                            $itemUrl = $baseUrl . '/project/' . urlencode($proj) . '/' . urlencode($targetSlug);
                            if (($view['collection'] ?? '') === 'blog') {
                                $itemUrl = $baseUrl . '/blog/' . urlencode($proj) . '/' . urlencode($targetSlug);
                            }
                        @endphp
                        <tr style="border-bottom: 1px solid var(--vp-c-divider);">
                            @foreach($columns as $col)
                                <td style="padding: 0.75rem 1rem;">
                                    @if($col === 'title')
                                        <a href="{{ $itemUrl }}" style="font-weight: 600; color: var(--vp-c-brand); text-decoration: none;">
                                            {{ $row['title'] ?? $targetSlug }}
                                        </a>
                                    @elseif($col === 'date' && !empty($row['date']))
                                        <span style="color: var(--vp-c-text-2); font-size: 0.82rem;">
                                            {{ is_numeric($row['date']) ? date('M j, Y', $row['date']) : $row['date'] }}
                                        </span>
                                    @elseif($col === 'category' && !empty($row['category']))
                                        <span style="background: var(--vp-c-bg-mute); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.78rem; border: 1px solid var(--vp-c-divider);">
                                            {{ $row['category'] }}
                                        </span>
                                    @else
                                        <span style="color: var(--vp-c-text-2);">
                                            {{ is_array($row[$col] ?? null) ? implode(', ', $row[$col]) : ($row[$col] ?? '—') }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @elseif($format === 'list')
        {{-- List Display Format --}}
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.6rem;">
            @foreach($items as $row)
                @php
                    $targetSlug = $row['slug'] ?? ($row['filename'] ?? '');
                    $itemUrl = $baseUrl . '/project/' . urlencode($proj) . '/' . urlencode($targetSlug);
                @endphp
                <li style="padding: 0.6rem 0.85rem; border-left: 3px solid var(--vp-c-brand); background: var(--vp-c-bg-soft); border-radius: 0 6px 6px 0; display: flex; justify-content: space-between; align-items: center;">
                    <a href="{{ $itemUrl }}" style="font-weight: 600; color: var(--vp-c-text-1); text-decoration: none;">
                        {{ $row['title'] ?? $targetSlug }}
                    </a>
                    @if(!empty($row['date']))
                        <span style="font-size: 0.78rem; color: var(--vp-c-text-3);">{{ is_numeric($row['date']) ? date('M j, Y', $row['date']) : $row['date'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

    @else
        {{-- Responsive Card Grid Display Format (Default) --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem;">
            @foreach($items as $row)
                @php
                    $targetSlug = $row['slug'] ?? ($row['filename'] ?? '');
                    $itemUrl = $baseUrl . '/project/' . urlencode($proj) . '/' . urlencode($targetSlug);
                    if (($view['collection'] ?? '') === 'blog') {
                        $itemUrl = $baseUrl . '/blog/' . urlencode($proj) . '/' . urlencode($targetSlug);
                    }
                @endphp
                <div class="sppdocs-view-card" style="background: var(--vp-c-bg); border: 1px solid var(--vp-c-divider); border-radius: 8px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between; transition: box-shadow 0.2s, transform 0.2s;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            @if(!empty($row['category']))
                                <span style="background: var(--vp-c-bg-mute); font-size: 0.75rem; font-weight: 600; color: var(--vp-c-brand); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--vp-c-divider);">
                                    {{ $row['category'] }}
                                </span>
                            @endif
                            @if(!empty($row['date']))
                                <span style="font-size: 0.78rem; color: var(--vp-c-text-3); margin-left: auto;">
                                    {{ is_numeric($row['date']) ? date('M j, Y', $row['date']) : $row['date'] }}
                                </span>
                            @endif
                        </div>

                        <h4 style="margin: 0 0 0.5rem 0; font-size: 1.05rem; line-height: 1.35;">
                            <a href="{{ $itemUrl }}" style="color: var(--vp-c-text-1); text-decoration: none; font-weight: 700;">
                                {{ $row['title'] ?? $targetSlug }}
                            </a>
                        </h4>

                        @if(!empty($row['description']) || !empty($row['excerpt']))
                            <p style="margin: 0 0 0.75rem 0; font-size: 0.85rem; color: var(--vp-c-text-2); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ $row['description'] ?? $row['excerpt'] }}
                            </p>
                        @endif
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--vp-c-divider-light); padding-top: 0.75rem; margin-top: 0.5rem; font-size: 0.78rem; color: var(--vp-c-text-3);">
                        <span>{{ $row['author'] ?? 'SPPDocs' }}</span>
                        <a href="{{ $itemUrl }}" style="color: var(--vp-c-brand); text-decoration: none; font-weight: 600;">Read &rarr;</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Pagination Controls --}}
    @if(!empty($viewResult['total_pages']) && $viewResult['total_pages'] > 1)
        <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem;">
            @for($p = 1; $p <= $viewResult['total_pages']; $p++)
                <a href="?page={{ $p }}" 
                   class="adm-btn {{ ($viewResult['current_page'] ?? 1) == $p ? 'adm-btn-primary' : 'adm-btn-secondary' }} adm-btn-sm"
                   style="min-width: 32px; text-align: center; text-decoration: none;">
                    {{ $p }}
                </a>
            @endfor
        </div>
    @endif
</div>