# SPP Multi-Tenant View Scaffolding & Resolution

## What is this Feature?
In SPP, applications can be generated in isolation using the make:app or make:blade-app commands. This creates a multi-tenant application structure where each application has its own self-contained source directory (e.g. src/AppName/). 

Historically, while MakeAppCommand generated Blade views into src/AppName/resources/views, the native ViewLocator—which powers @spppartial, enderPartial(), and Turbo Streams—only searched src/AppName/views/ or the root esources/views/. This caused a frustrating discrepancy where developers had to manually move scaffolded HTML partials to a different directory in order to use them with HTMX.

Additionally, component scaffolders like make:partial, make:stream, and make:live-component would generate views globally rather than respecting the application context.

## What's Changed?

1. **Unified View Resolution**: The core \SPPMod\SPPView\ViewLocator::locate() method has been updated to officially search the src/AppName/resources/views/ directory (and its partials / streams subdirectories). This unifies the directory structure between the Blade engine and Native PHP/HTML partial rendering. You no longer have to split your view storage!
2. **Context-Aware Scaffolding**: 
   - make:partial
   - make:stream
   - make:live-component
   
   These commands now respect the --app=AppName context flag. When scaffolding a partial or a stream for a specific application, the file will be emitted directly into src/AppName/resources/views/partials/ (or streams/), adhering strictly to SPP's multi-tenant architecture. If the context is default, it will fall back to the root esources/views/ directory.

3. **Full Blade Syntax Support in Partials**: 
   Previously, @spppartial strictly evaluated partials as Native PHP. Now, TemplateMacros::spppartial will automatically intercept partials that end with .blade.php and dynamically compile them through the SPPBlade engine! This means you can now safely use all {{  }} directives inside your HTMX partials.

## How It Works in Practice

### Generating a Partial for a Specific Application
Let's say you are building an application called Storefront and you want to generate a new HTMX partial (using Blade) for the cart component.

`ash
php spp.php make:partial cart-item.blade.php --app=Storefront
`

SPP will automatically place the partial inside:
src/Storefront/resources/views/partials/cart-item.blade.php

### Including the Partial natively via Blade
Inside your src/Storefront/resources/views/cart.blade.php, you can seamlessly use the native partial include directive without worrying about absolute paths:

`html
<div class="cart-container" id="cart-list">
    @spppartial('partials/cart-item.blade.php', ['item' => ])
</div>
`

The core ViewLocator will transparently resolve partials/cart-item.blade.php from src/Storefront/resources/views/partials/cart-item.blade.php, and TemplateMacros will automatically compile it via SPPBlade.

## Rationale for the Change
This architectural unification fixes a major pain point for novice developers. By ensuring that the scaffolding commands emit files into the exact directories that the ViewLocator natively supports, and by bridging @spppartial with the SPPBlade engine, we eliminate "Template not found" errors and allow developers to adhere to the strict SPP rules (Zero Inline HTML) without struggling with directory mapping or template syntax restrictions.
