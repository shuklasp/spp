## `make:polyglot-partial`

**Purpose**: Scaffold a new external polyglot view partial service file for any registered programming language or custom DSL.

### Synopsis
```bash
php spp.php make:polyglot-partial <ModuleName.ext> [--lang=<language>] [--app=AppName]
```

### Extended Usage
The `make:polyglot-partial` command generates boilerplate service files designed to be rendered within SPP view controllers or Drishyam template macros. It automatically integrates with `\SPP\Core\Polyglot\LanguageRegistry` to support any registered language (Python, Node.js, Go, Rust, Zig, Ruby, Elixir, Lua, C#, Java, Swift, Kotlin, or custom DSLs).

```bash
# Scaffold a Python partial service
php spp.php make:polyglot-partial analytics_chart.py --app=AnalyticsApp

# Scaffold a Node.js partial service
php spp.php make:polyglot-partial real_time_graph.js

# Scaffold a Go partial service
php spp.php make:polyglot-partial high_concurrency_widget.go

# Scaffold a Rust partial service
php spp.php make:polyglot-partial matrix_calculator.rs --lang=rust

# Scaffold a custom formula calculation partial
php spp.php make:polyglot-partial fee_calculator.calc --lang=formula
```

Once scaffolded, the partial can be rendered in a controller:
```php
public function show(): string
{
    return $this->renderPolyglotPartial('analytics_chart.py', [
        'title' => 'Monthly Trends',
        'dataset' => [10, 25, 40, 85]
    ]);
}
```
Or embedded directly in Blade templates:
```blade
@polyglot('services/polyglot/analytics_chart.py', ['title' => 'Live Metrics'])
```

### Options Available
- `<ModuleName.ext>`: The filename of the partial service to scaffold, including its file extension (e.g., `chart.py`, `dashboard.js`, `math.calc`).
- `--lang=<language>`: Explicit language identifier registered in `LanguageRegistry` (e.g., `python`, `node`, `go`, `rust`, `zig`, `formula`). If omitted, it is inferred from the file extension.
- `--app=<AppName>`: Target application context. If provided, places the partial inside `src/<AppName>/services/polyglot/`. Otherwise, places it in `services/polyglot/`.

### Under the Hood Activity
- Resolves the language driver using `LanguageRegistry::getDriver()` from the given language ID or file extension.
- Determines the destination directory based on application context (`src/<app>/services/polyglot/` or `services/polyglot/`).
- Populates language-specific boilerplate with standardized input parsing and HTML/JSON output framing.
- Generates execution stubs adhering to SPP's zero-inline-HTML and external partial architecture.
