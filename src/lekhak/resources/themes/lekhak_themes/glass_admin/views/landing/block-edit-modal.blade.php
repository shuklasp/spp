<div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h3 style="font-size: 1.3rem; margin-bottom: 4px; color: var(--text-main);">Edit Block: {{ ucfirst($block->block_type) }}</h3>
        <p style="color: var(--text-dim); font-size: 0.85rem;">Configure the parameters and styles below.</p>
    </div>
    <span class="badge badge-warning">#{{ $block->id }}</span>
</div>

<form method="POST" id="modal-block-edit-form" onsubmit="submitModalEdit(event, {{ $block->id }})">
    <div style="max-height: 60vh; overflow-y: auto; padding-right: 10px; margin-bottom: 25px;">
        @php
        $predefined = [
            'entity_type' => ['node' => 'Content Node', 'user' => 'User Profile', 'term' => 'Taxonomy Term'],
            'status' => ['published' => 'Published', 'draft' => 'Draft', 'archived' => 'Archived'],
            'storage_strategy' => ['flat' => 'Flat Table', 'dynamic' => 'Dynamic JSON']
        ];
        @endphp

        @foreach($content as $key => $value)
            @if($key === '_style') @continue @endif
            <div class="spp-form-group" style="margin-bottom: 20px;">
                <label class="spp-label" style="font-size: 0.85rem;">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                <div class="spp-input-wrapper">
                    @if(isset($predefined[$key]))
                        <select name="config[{{ $key }}]" style="padding: 10px 14px; font-size: 0.9rem;">
                            @foreach($predefined[$key] as $k => $v)
                                <option value="{{ $k }}" {{ $value == $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    @elseif(is_array($value))
                        <textarea name="config[{{ $key }}_raw]" rows="4" style="font-family: monospace; padding: 10px 14px; font-size: 0.85rem;">@foreach($value as $k => $v){{ $k }}={{ $v }}&#10;@endforeach</textarea>
                        <small class="spp-help-text" style="font-size: 0.75rem;">One entry per line: key=value</small>
                    @elseif(strlen($value) > 50)
                        <textarea name="config[{{ $key }}]" rows="3" style="padding: 10px 14px; font-size: 0.9rem;">{{ $value }}</textarea>
                    @else
                        <input type="text" name="config[{{ $key }}]" value="{{ $value }}" style="padding: 10px 14px; font-size: 0.9rem;">
                    @endif
                </div>
            </div>
        @endforeach

        <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--glass-border);">
            <h4 style="font-size: 0.9rem; margin-bottom: 15px; color: var(--accent-primary);">🎨 Section Theming</h4>
            @php $style = $content['_style'] ?? []; @endphp
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="spp-form-group" style="margin-bottom: 10px;">
                    <label class="spp-label" style="font-size: 0.8rem;">Background</label>
                    <select name="config[_style][bg_type]" style="padding: 8px 12px; font-size: 0.85rem;">
                        <option value="default" {{ ($style['bg_type']??'') == 'default' ? 'selected' : '' }}>Default Theme</option>
                        <option value="glass" {{ ($style['bg_type']??'') == 'glass' ? 'selected' : '' }}>Glassmorphism</option>
                        <option value="dark" {{ ($style['bg_type']??'') == 'dark' ? 'selected' : '' }}>Deep Dark</option>
                        <option value="primary" {{ ($style['bg_type']??'') == 'primary' ? 'selected' : '' }}>Primary Color</option>
                        <option value="gradient" {{ ($style['bg_type']??'') == 'gradient' ? 'selected' : '' }}>Ocean Gradient</option>
                    </select>
                </div>
                <div class="spp-form-group" style="margin-bottom: 10px;">
                    <label class="spp-label" style="font-size: 0.8rem;">Padding</label>
                    <select name="config[_style][padding]" style="padding: 8px 12px; font-size: 0.85rem;">
                        <option value="none" {{ ($style['padding']??'') == 'none' ? 'selected' : '' }}>None</option>
                        <option value="small" {{ ($style['padding']??'') == 'small' ? 'selected' : '' }}>Small (30px)</option>
                        <option value="medium" {{ ($style['padding']??'') == 'medium' ? 'selected' : '' }}>Medium (60px)</option>
                        <option value="large" {{ ($style['padding']??'') == 'large' ? 'selected' : '' }}>Large (100px)</option>
                    </select>
                </div>
                <div class="spp-form-group" style="margin-bottom: 10px;">
                    <label class="spp-label" style="font-size: 0.8rem;">Alignment</label>
                    <select name="config[_style][text_align]" style="padding: 8px 12px; font-size: 0.85rem;">
                        <option value="left" {{ ($style['text_align']??'') == 'left' ? 'selected' : '' }}>Left</option>
                        <option value="center" {{ ($style['text_align']??'') == 'center' ? 'selected' : '' }}>Center</option>
                        <option value="right" {{ ($style['text_align']??'') == 'right' ? 'selected' : '' }}>Right</option>
                    </select>
                </div>
                <div class="spp-form-group" style="margin-bottom: 10px;">
                    <label class="spp-label" style="font-size: 0.8rem;">Animation</label>
                    <select name="config[_style][animation]" style="padding: 8px 12px; font-size: 0.85rem;">
                        <option value="none" {{ ($style['animation']??'') == 'none' ? 'selected' : '' }}>None</option>
                        <option value="fade" {{ ($style['animation']??'') == 'fade' ? 'selected' : '' }}>Fade In</option>
                        <option value="slide-up" {{ ($style['animation']??'') == 'slide-up' ? 'selected' : '' }}>Slide Up</option>
                        <option value="zoom" {{ ($style['animation']??'') == 'zoom' ? 'selected' : '' }}>Zoom In</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--glass-border); padding-top: 15px;">
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="padding: 8px 16px; font-size: 0.85rem;">Cancel</button>
        <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">Save Changes</button>
    </div>
</form>
