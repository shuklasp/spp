@extends('layout')

@section('content')
<div class="glass-panel" style="max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 1.5rem; margin-bottom: 5px;">Edit Block: {{ ucfirst($block->block_type) }}</h2>
            <p style="color: var(--text-dim);">Configure the content and appearance of this block.</p>
        </div>
        <div class="badge badge-warning">Block ID: #{{ $block->id }}</div>
    </div>

    <form method="POST" id="block-edit-form">
        <div class="stats-grid" style="grid-template-columns: 1fr; gap: 20px;">
            @php
            $predefined = [
                'entity_type' => ['node' => 'Content Node', 'user' => 'User Profile', 'term' => 'Taxonomy Term'],
                'status' => ['published' => 'Published', 'draft' => 'Draft', 'archived' => 'Archived'],
                'storage_strategy' => ['flat' => 'Flat Table', 'dynamic' => 'Dynamic JSON']
            ];
            @endphp

            @foreach($content as $key => $value)
                @if($key === '_style') @continue @endif
                <div class="spp-form-group">
                    <label class="spp-label">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                    <div class="spp-input-wrapper">
                        @if(isset($predefined[$key]))
                            <select name="config[{{ $key }}]">
                                @foreach($predefined[$key] as $k => $v)
                                    <option value="{{ $k }}" {{ $value == $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        @elseif(is_array($value))
                            <textarea name="config[{{ $key }}_raw]" rows="5" style="font-family: monospace;">@foreach($value as $k => $v){{ $k }}={{ $v }}&#10;@endforeach</textarea>
                            <small class="spp-help-text">One entry per line: key=value</small>
                        @elseif(strlen($value) > 50)
                            <textarea name="config[{{ $key }}]" rows="4">{{ $value }}</textarea>
                        @else
                            <input type="text" name="config[{{ $key }}]" value="{{ $value }}">
                        @endif
                    </div>
                </div>
            @endforeach

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--glass-border);">
                <h3 style="font-size: 1rem; margin-bottom: 20px; color: var(--accent-primary);">🎨 Section Theming</h3>
                @php $style = $content['_style'] ?? []; @endphp
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="spp-form-group">
                        <label class="spp-label">Background Style</label>
                        <select name="config[_style][bg_type]">
                            <option value="default" {{ ($style['bg_type']??'') == 'default' ? 'selected' : '' }}>Default Theme</option>
                            <option value="glass" {{ ($style['bg_type']??'') == 'glass' ? 'selected' : '' }}>Glassmorphism</option>
                            <option value="dark" {{ ($style['bg_type']??'') == 'dark' ? 'selected' : '' }}>Deep Dark</option>
                            <option value="primary" {{ ($style['bg_type']??'') == 'primary' ? 'selected' : '' }}>Primary Color</option>
                            <option value="gradient" {{ ($style['bg_type']??'') == 'gradient' ? 'selected' : '' }}>Ocean Gradient</option>
                        </select>
                    </div>
                    <div class="spp-form-group">
                        <label class="spp-label">Padding (Vertical)</label>
                        <select name="config[_style][padding]">
                            <option value="none" {{ ($style['padding']??'') == 'none' ? 'selected' : '' }}>None</option>
                            <option value="small" {{ ($style['padding']??'') == 'small' ? 'selected' : '' }}>Small (30px)</option>
                            <option value="medium" {{ ($style['padding']??'') == 'medium' ? 'selected' : '' }}>Medium (60px)</option>
                            <option value="large" {{ ($style['padding']??'') == 'large' ? 'selected' : '' }}>Large (100px)</option>
                        </select>
                    </div>
                    <div class="spp-form-group">
                        <label class="spp-label">Text Alignment</label>
                        <select name="config[_style][text_align]">
                            <option value="left" {{ ($style['text_align']??'') == 'left' ? 'selected' : '' }}>Left</option>
                            <option value="center" {{ ($style['text_align']??'') == 'center' ? 'selected' : '' }}>Center</option>
                            <option value="right" {{ ($style['text_align']??'') == 'right' ? 'selected' : '' }}>Right</option>
                        </select>
                    </div>
                    <div class="spp-form-group">
                        <label class="spp-label">Entrance Animation</label>
                        <select name="config[_style][animation]">
                            <option value="none" {{ ($style['animation']??'') == 'none' ? 'selected' : '' }}>None</option>
                            <option value="fade" {{ ($style['animation']??'') == 'fade' ? 'selected' : '' }}>Fade In</option>
                            <option value="slide-up" {{ ($style['animation']??'') == 'slide-up' ? 'selected' : '' }}>Slide Up</option>
                            <option value="zoom" {{ ($style['animation']??'') == 'zoom' ? 'selected' : '' }}>Zoom In</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; display: flex; gap: 15px;">
            <button type="submit" class="btn btn-primary">Update Block</button>
            <a href="{{ $admin_root }}/landing/design/{{ $block->page_id }}" class="btn btn-secondary">Back to Designer</a>
        </div>
    </form>
</div>
@endsection
