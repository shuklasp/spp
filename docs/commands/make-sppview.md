# NAME
`make:sppview` - Scaffold a new native AST SPPView template

# SYNOPSIS
`php spp.php make:sppview <ViewName> [--name=<ViewName>] [--app=context]`

# PURPOSE
The `make:sppview` command creates a View class utilizing SPPView's native Abstract Syntax Tree (AST) methodology. Unlike Drishyam/Blade which parse string templates, SPPView classes programmatically construct HTML elements via PHP methods (e.g., `$this->div()`, `$this->h1()`), ensuring zero parsing overhead and absolute type safety.

# OPTIONS AVAILABLE
- `<ViewName>` or `--name=<ViewName>` (string, required): The View's PHP Class name.
- `--app=<context>` (string, optional): The application namespace mapping to `src/{context}/views/`.

# UNDER THE HOOD ACTIVITY
It sanitizes the string, extracts the context, and isolates the target path explicitly pointing to `src/{context}/views/{lowercase_name}.php`. 
It constructs a hardcoded PHP source block natively establishing the `App\{Context}\Views` namespace, and extending the core `\SPPMod\SPPView\SPPView` system class. It scaffolds a boilerplate `render(array $data)` function implementing `$this->html()`, `$this->head()`, and `$this->body()` wrappers containing nested node arrays. The physical file is forcibly written to disk using `file_put_contents`.

# EXAMPLES
**1. Scaffold a fast programmatic invoice view:**
```bash
php spp.php make:sppview InvoiceRenderer --app=billing
```
