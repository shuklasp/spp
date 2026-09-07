<div class="adm-card" id="mediaGridCard">
    <div class="adm-card-header">
        <h2 class="adm-card-title">Stored Media Files</h2>
        <span class="adm-badge" id="mediaCountBadge">{{ count($images) }} asset{{ count($images) === 1 ? '' : 's' }}</span>
    </div>

    <div class="adm-media-grid" id="mediaCardsGrid">
        @foreach($images as $img)
        <div class="adm-media-card" id="media-{{ md5($img['filename']) }}">
            <div class="adm-media-preview-box">
                @if(!empty($img['is_video']))
                    <video src="{{ $img['url'] }}" class="adm-media-video-thumb" preload="metadata" controls></video>
                @else
                    <img src="{{ $img['url'] }}" alt="{{ $img['filename'] }}" class="adm-media-thumb" loading="lazy">
                @endif
            </div>
            <div class="adm-media-info">
                <div class="adm-media-name" title="{{ $img['filename'] }}">{{ $img['filename'] }}</div>
                <div class="adm-media-meta-row">
                    <span>{{ $img['size'] }}</span>
                    <span>{{ date('M j, Y', $img['time']) }}</span>
                </div>
                <div class="adm-media-actions-row">
                    <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" data-copy-url="{{ $img['url'] }}">📋 Copy URL</button>
                    <button type="button" class="adm-btn adm-btn-danger adm-btn-sm" data-delete-filename="{{ $img['filename'] }}" data-media-id="{{ md5($img['filename']) }}">🗑️ Delete</button>
                </div>
                @if(empty($img['is_video']))
                <div class="adm-media-styles-row" style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--vp-c-divider, #333); font-size: 0.75rem;">
                    <div style="font-weight: 600; color: var(--vp-c-text-2, #888); margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center;">
                        <span>🎨 Image Styles (Derivatives)</span>
                    </div>
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                        <button type="button" class="adm-btn adm-btn-secondary adm-btn-xs" style="padding: 2px 6px; font-size: 0.7rem;" data-copy-url="{{ \App\SPPDocs\Services\ImageStylesService::getDerivativeUrl($project_id, 'thumbnail', $img['filename']) }}" title="150x150 square crop">Thumb</button>
                        <button type="button" class="adm-btn adm-btn-secondary adm-btn-xs" style="padding: 2px 6px; font-size: 0.7rem;" data-copy-url="{{ \App\SPPDocs\Services\ImageStylesService::getDerivativeUrl($project_id, 'medium', $img['filename']) }}" title="600x400 proportional fit">Medium</button>
                        <button type="button" class="adm-btn adm-btn-secondary adm-btn-xs" style="padding: 2px 6px; font-size: 0.7rem;" data-copy-url="{{ \App\SPPDocs\Services\ImageStylesService::getDerivativeUrl($project_id, 'banner', $img['filename']) }}" title="1200x630 landscape crop">Banner</button>
                        <button type="button" class="adm-btn adm-btn-secondary adm-btn-xs" style="padding: 2px 6px; font-size: 0.7rem;" data-copy-url="{{ \App\SPPDocs\Services\ImageStylesService::getDerivativeUrl($project_id, 'avatar', $img['filename']) }}" title="96x96 profile icon">Avatar</button>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach

        @if(empty($images))
        <div class="adm-media-empty-card" id="emptyMediaPlaceholder">
            <div class="adm-media-empty-icon">📂</div>
            <div class="adm-media-empty-title">No Media Found</div>
            <div class="adm-media-empty-desc">No media files have been uploaded for this project yet. Use the upload area above to get started.</div>
        </div>
        @endif
    </div>
</div>