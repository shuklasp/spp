# NAME
`make:mixed-paradigm` - Scaffold a Kitchen Sink view blending SPPView, Drishyam, and SPPUX

# SYNOPSIS
`php spp.php make:mixed-paradigm <ViewName> [--name=<ViewName>]`

# PURPOSE
The `make:mixed-paradigm` command represents the ultimate showcase of the SPP rendering pipeline. It generates a "Kitchen Sink" layout demonstrating three discrete layers of rendering co-existing simultaneously: An outer SPPView (Native PHP AST rendering), a middle Drishyam (Blade) compiled fragment, and an inner SPPUX (Reactive JavaScript Web Component) island.

# OPTIONS AVAILABLE
- `<ViewName>` or `--name=<ViewName>` (string, required): The target root name for the generated mixed-paradigm artifacts.

# UNDER THE HOOD ACTIVITY
The command provisions three distinct files concurrently within the requested application context:
1. **The SPPView Wrapper**: Written to `src/{context}/views/{lowercase_name}.php`. This file constructs a literal PHP AST object (`class {name} extends SPPView`). It initializes a `Drishyam` engine to capture the compiled Blade output, then constructs an HTML document using native `$this->html()`, `$this->head()`, and `$this->body()` invocations, passing down variables.
2. **The Blade Fragment**: Written to `resources/views/{context}/fragments/{lowercase_name}_fragment.blade.php`. This file contains raw HTML augmented by Blade directives (`{{ json_encode($data) }}`), isolated explicitly for Drishyam compilation.
3. **The SPPUX Island**: Written to `comp/{Name}Island.js`. This creates a client-side Reactive web component (`class {Name}Island extends BaseComponent`), complete with asynchronous state initialization (`onInit`) and a functional interactive rendering pipeline using lit-html style template literals.

By executing `file_put_contents` across the three respective structural directories simultaneously, it guarantees seamless orchestration where the parent wrapper dynamically imports the Blade string and embeds the client-side `<spp-element>` tag natively.

# EXAMPLES
**1. Scaffold a complex mixed dashboard:**
```bash
php spp.php make:mixed-paradigm ComplexDashboard
```
