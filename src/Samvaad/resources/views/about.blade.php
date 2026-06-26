@extends('layouts.app')
@section('title', $title ?? 'About')
@section('content')
<div class="card">
    <span class="badge badge-primary">FRAMEWORK GUIDE</span>
    <h1>{{ $title ?? 'About' }} — Architecture Guide</h1>
    <p>This page explains how the SPP framework is structured and how each part works.</p>
</div>

<div class="card">
    <h2>📂 Directory Structure</h2>
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="text-align:left; border-bottom:2px solid var(--border);">
                <th style="padding:0.8rem;">Directory</th>
                <th style="padding:0.8rem;">Purpose</th>
                <th style="padding:0.8rem;">Modify When</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.8rem;"><code>comp/</code></td>
                <td style="padding:0.8rem;">SPP-UX components (JavaScript)</td>
                <td style="padding:0.8rem;">Building reactive UI</td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.8rem;"><code>pages/</code></td>
                <td style="padding:0.8rem;">Native PHP pages (augmented)</td>
                <td style="padding:0.8rem;">Simple server-rendered pages</td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.8rem;"><code>serv/</code></td>
                <td style="padding:0.8rem;">Controllers & services</td>
                <td style="padding:0.8rem;">Business logic, API endpoints</td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.8rem;"><code>resources/views/</code></td>
                <td style="padding:0.8rem;">Blade templates</td>
                <td style="padding:0.8rem;">Server-rendered HTML with directives</td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.8rem;"><code>entities/</code></td>
                <td style="padding:0.8rem;">Database entity definitions</td>
                <td style="padding:0.8rem;">Data models with ORM</td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.8rem;"><code>events/</code></td>
                <td style="padding:0.8rem;">Event handlers</td>
                <td style="padding:0.8rem;">Reacting to framework events</td>
            </tr>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.8rem;"><code>middleware/</code></td>
                <td style="padding:0.8rem;">Route middleware</td>
                <td style="padding:0.8rem;">Auth checks, rate limiting</td>
            </tr>
            <tr>
                <td style="padding:0.8rem;"><code>etc/</code></td>
                <td style="padding:0.8rem;">App config files</td>
                <td style="padding:0.8rem;">Routes, services, forms, settings</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>🔄 Request Lifecycle</h2>
    <ol style="line-height:2.2; color:var(--muted);">
        <li><b>Request arrives</b> → Apache routes to SPP via <code>.htaccess</code></li>
        <li><b>sppinit.php</b> → Boots modules, sets app context via <code>Scheduler::setContext()</code></li>
        <li><b>SPPRouter</b> → Loads <code>pages.yml</code>, resolves route to page/controller</li>
        <li><b>ViewRouter</b> → Dispatches: includes page file OR calls controller method</li>
        <li><b>Augmentation</b> → Injects JS/CSS from <code>ViewPage</code>, applies theme, fires events</li>
        <li><b>Response</b> → Final HTML sent to browser</li>
    </ol>
</div>

<div class="card">
    <h2>🌍 Polyglot Architecture</h2>
    <p>SPP supports multiple rendering paradigms in the same app:</p>
    <ul style="line-height:2; color:var(--muted);">
        <li><b>Blade Templates:</b> <code>@extends</code>, <code>@section</code>, <code>@sppux</code>,
                        <code>@sppform</code>
                    </li>
                    <li><b>Twig Templates:</b> <code>{{ "{% var %}" }}</code>, <code>{{ "{% block %}" }}</code> — via
                        <code>SPPTwig</code>
                    </li>
                    <li><b>Native PHP:</b> Direct PHP output with <code>ViewPage</code> augmentation</li>
                    <li><b>SPP-UX:</b> Reactive components with <code>BaseComponent</code>, <code>html``</code>,
                        <code>setState</code>
                    </li>
                    <li><b>REST API:</b> Controllers returning JSON with <code>header('Content-Type: application/json')</code></li>
                </ul>
            </div>
        @endsection