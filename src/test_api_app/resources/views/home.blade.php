{{--
  Home Page — Demonstrates Blade + SPP-UX + YAML Forms + Auth Directives
  Edit this file: src/test_api_app/resources/views/home.blade.php
--}}
@extends('layouts.app')

@section('title', $title ?? 'Home')

@section('content')
    <div class="card">
        <span class="badge badge-primary">BLADE + SPP-UX + FORMS</span>
        <h1>{{ $title ?? 'Welcome' }}</h1>
        <p>This page is rendered by <code>HomeController@index</code> using a <b>Blade template</b>.
        It demonstrates how Blade, SPP-UX components, and YAML forms work together.</p>
    </div>

    {{-- Feature cards --}}
    @if(!empty($features))
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
        @foreach($features as $feature)
        <div class="card" style="text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">{{ $feature['icon'] }}</div>
            <h3>{{ $feature['title'] }}</h3>
            <p>{{ $feature['desc'] }}</p>
        </div>
        @endforeach
    </div>
    @endif

    {{-- SPP-UX Component Mount (embedded in Blade) --}}
    <div class="card">
        <h2>🧩 SPP-UX Component in Blade</h2>
        <p>The counter below is an SPP-UX component mounted via the <code>@sppux</code> Blade directive:</p>
        <div style="margin-top: 1rem;">
            @sppux('counter', ['initialCount' => 42])
        </div>
    </div>

    {{-- YAML Form --}}
    <div class="card">
        <h2>📝 YAML-Driven Form</h2>
        <p>This form is defined in <code>etc/apps/test_api_app/forms/contact.yml</code> and rendered via <code>@sppform</code>:</p>
        @sppform('contact')
    </div>

    {{-- Auth-gated content --}}
    <div class="card">
        <h2>🔒 Auth-Gated Content</h2>
        @sppauth
            <div class="badge badge-success">AUTHENTICATED</div>
            <p style="margin-top: 0.5rem;">You are logged in. This content is only visible to authenticated users.</p>
            <p>Use <code>@sppauth</code> and <code>@endsppauth</code> in Blade templates.</p>
        @endsppauth
        @sppguest
            <p>You are viewing as a guest. <a href="{{ $base_url }}/login" class="btn btn-outline" style="margin-left: 0.5rem;">Login</a></p>
            <p style="margin-top: 0.5rem;">Use <code>@sppguest</code> and <code>@endsppguest</code> to show guest-only content.</p>
        @endsppguest
    </div>
@endsection