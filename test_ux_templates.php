<?php
require_once __DIR__ . '/spp/sppinit.php';
\SPP\App::getApp('TemplateTest');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SPP-UX Declarative Templates Test</title>
    <script src="<?php echo \SPPMod\SPPUX\SPPUX::runtimePath(); ?>"></script>
    <script src="<?php echo \SPPMod\SPPUX\SPPUX::loaderPath(); ?>" type="module"></script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; padding: 3rem; background: #f8fafc; color: #1e293b; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-bottom: 2rem; border: 1px solid #e2e8f0; }
        h1 { font-weight: 800; letter-spacing: -0.025em; margin-bottom: 2rem; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; background: #dbeafe; color: #1e40af; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-bottom: 1rem; }
        .btn { padding: 0.5rem 1rem; background: #6366f1; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .btn:hover { background: #4f46e5; }
        pre { background: #1e293b; color: #f8fafc; padding: 1rem; border-radius: 8px; font-size: 0.875rem; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SPP-UX Phase 2: Declarative Templates</h1>

        <div class="card">
            <span class="badge">Zero-JS Implementation</span>
            <p>This component is defined entirely in a <code>&lt;template&gt;</code> tag. No separate JS file was created.</p>
            
            <!-- Component Mounting Point -->
            <div data-spp-component="InteractiveCard" 
                 data-spp-props='{"title": "Hello from Template!", "count": 10}'>
            </div>
        </div>

        <div class="card">
            <h3>Under the hood:</h3>
            <p>The following template is compiled at runtime into a reactive SPP-UX component:</p>
            <pre>
&lt;template data-spp-ux="InteractiveCard"&gt;
    &lt;div class="template-content"&gt;
        &lt;h3&gt;\${props.title}&lt;/h3&gt;
        &lt;p&gt;Internal State: &lt;strong&gt;\${state.clicks || 0}&lt;/strong&gt;&lt;/p&gt;
        &lt;button class="btn" @click="\${() => this.setState({ clicks: (state.clicks || 0) + 1 })}"&gt;
            Increment State
        &lt;/button&gt;
    &lt;/div&gt;
&lt;/template&gt;
            </pre>
        </div>
    </div>

    <!-- Declarative Component Definition -->
    <template data-spp-ux="InteractiveCard">
        <div class="template-content">
            <h3 style="margin-top:0">${props.title}</h3>
            <p>Internal State: <strong style="color:#6366f1">${state.clicks || 0}</strong></p>
            <p>Props Count Base: <strong>${props.count}</strong></p>
            <button class="btn" @click="${() => this.setState({ clicks: (state.clicks || 0) + 1 })}">
                Increment State
            </button>
            <div style="margin-top: 1rem; font-size: 0.875rem; color: #64748b;">
                Result: ${ (state.clicks || 0) + props.count }
            </div>
        </div>
    </template>

</body>
</html>
