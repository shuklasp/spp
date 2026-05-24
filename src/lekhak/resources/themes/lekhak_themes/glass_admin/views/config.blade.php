@extends('layout')

@section('content')
<div class="lekhak-local-tasks-header" style="margin-bottom: 25px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">{{ $title }}</h2>
        <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-dim);">{{ $subtitle }}</p>
    </div>
    <a href="{{ $admin_root }}/modules" class="btn btn-secondary" style="border-color: var(--glass-border); color: var(--text-main); text-decoration: none; padding: 6px 15px; display: flex; align-items: center; gap: 8px;">
        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Modules
    </a>
</div>

@if(isset($_SESSION['flash_success']))
    <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); padding: 15px; margin-bottom: 20px; color: var(--text-main); border-radius: 4px;">
        {{ $_SESSION['flash_success'] }}
        @php unset($_SESSION['flash_success']); @endphp
    </div>
@endif

<div class="glass-panel" style="padding: 25px;">
    @if(empty($schema))
        <div style="text-align: center; padding: 50px 20px;">
            <svg style="width: 64px; height: 64px; color: var(--glass-border); margin: 0 auto 15px auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <h3 style="font-size: 1.25rem; font-weight: 500; color: var(--text-main); margin-bottom: 8px;">No Configuration Needed</h3>
            <p style="color: var(--text-dim); margin: 0;">This module is currently active and does not require any additional configuration parameters to function.</p>
        </div>
    @else
        <form action="{{ $admin_root }}/config/{{ $moduleName }}" method="POST">
            @foreach($schema as $key => $field)
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.9rem; font-weight: 500; color: var(--text-main); margin-bottom: 5px;" for="{{ $key }}">{{ $field['title'] ?? ucfirst($key) }}</label>
                    
                    @if($field['description'] ?? false)
                        <p style="font-size: 0.8rem; color: var(--text-dim); margin: 0 0 8px 0;">{{ $field['description'] }}</p>
                    @endif

                    @if($field['type'] === 'textarea')
                        <textarea id="{{ $key }}" name="{{ $key }}" rows="{{ $field['rows'] ?? 4 }}" class="form-control" style="width: 100%; max-width: 600px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--text-main); padding: 10px; border-radius: 6px;">{{ $field['value'] }}</textarea>
                    
                    @elseif($field['type'] === 'select')
                        <select id="{{ $key }}" name="{{ $key }}" class="form-control" style="width: 100%; max-width: 600px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--text-main); padding: 10px; border-radius: 6px;">
                            @foreach($field['options'] ?? [] as $optValue => $optLabel)
                                <option value="{{ $optValue }}" {{ ($field['value'] == $optValue) ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    
                    @elseif($field['type'] === 'checkbox')
                        <label style="display: flex; items-align: center; gap: 10px; cursor: pointer; color: var(--text-main); font-size: 0.9rem;">
                            <input type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" {{ $field['value'] ? 'checked' : '' }} style="width: 16px; height: 16px;">
                            <span>Enable this option</span>
                        </label>

                    @else
                        <input type="{{ $field['type'] ?? 'text' }}" id="{{ $key }}" name="{{ $key }}" value="{{ $field['value'] }}" class="form-control" style="width: 100%; max-width: 600px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: var(--text-main); padding: 10px; border-radius: 6px;">
                    @endif
                </div>
            @endforeach

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--glass-border); display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Save Configuration</button>
            </div>
        </form>
    @endif
</div>
@endsection
