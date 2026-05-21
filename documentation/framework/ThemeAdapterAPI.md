# Theme Adapter API

## Overview

The Theme Adapter API provides a framework-level contract (`ThemeAdapterInterface`) that allows SPP applications to load and render templates from **any** theme system — including WordPress, Joomla, and native Lekhak themes — without modifying the core framework.

This follows SPP's philosophy of **modularity and dynamic discovery**: the core defines the contract; the application supplies the implementation.

## Architecture

```
┌─────────────────────────────────────┐
│          SPP Core (Framework)       │
│                                     │
│  ┌─────────────────────────────┐    │
│  │  ThemeAdapterInterface      │    │
│  │  ├── loadTemplate($name)    │    │
│  │  └── render($tpl, $ctx)     │    │
│  └─────────────────────────────┘    │
└──────────────┬──────────────────────┘
               │ implements
    ┌──────────┼──────────┐
    │          │          │
    ▼          ▼          ▼
┌────────┐ ┌────────┐ ┌────────┐
│  WP    │ │ Joomla │ │ Native │
│ Adapter│ │ Adapter│ │ Adapter│
└────────┘ └────────┘ └────────┘
   (Application Layer)
```

## Interface

```php
namespace SPP\Theme;

interface ThemeAdapterInterface
{
    public function loadTemplate(string $name): string;
    public function render(string $template, array $context = []): string;
}
```

## Configuration

Set the active adapter in `global-settings.yml`:

```yaml
theme_adapter: native   # Options: native, wp, joomla
native_theme: premium   # Theme directory name (for native)
wp_theme: twentytwentyfour   # Theme name (for WordPress)
joomla_template: cassiopeia  # Template name (for Joomla)
```

## CLI Usage

```bash
# Show current theme
php spp.php theme:activate

# Switch to WordPress adapter
php spp.php theme:activate wp --theme=twentytwentyfour

# Switch to Joomla adapter
php spp.php theme:activate joomla --theme=cassiopeia

# Switch to native Lekhak adapter
php spp.php theme:activate native --theme=premium
```

## Adapters

### WordPressThemeAdapter

- **Location**: `src/lekhak/themes/WordPressThemeAdapter.php`
- **Theme directory**: `src/lekhak/themes/wp-content/themes/<theme-name>/`
- **Template hierarchy**: Follows WP conventions (`front-page.php`, `single.php`, `page.php`, etc.)
- **Features**: Provides `get_header`, `get_footer`, `get_sidebar` closures within template scope

### JoomlaThemeAdapter

- **Location**: `src/lekhak/themes/JoomlaThemeAdapter.php`
- **Template directory**: `src/lekhak/themes/templates/<template-name>/`
- **Conventions**: Supports `templateDetails.xml` metadata parsing and `html/` override folders
- **Features**: Module position discovery from XML, component view overrides

### NativeThemeAdapter

- **Location**: `src/lekhak/themes/NativeThemeAdapter.php`
- **Theme directory**: `src/lekhak/themes/<theme-name>/views/`
- **Supported formats**: `.blade.php`, `.twig`, `.php`
- **Features**: Twig rendering (if `twig/twig` is installed), fallback to `resources/PremiumApp/views/`

## Creating a Custom Adapter

Implement `ThemeAdapterInterface` and register it:

```php
use SPP\Theme\ThemeAdapterInterface;

class MyCustomAdapter implements ThemeAdapterInterface
{
    public function loadTemplate(string $name): string
    {
        // Load template from your custom source
    }

    public function render(string $template, array $context = []): string
    {
        // Render and return HTML
    }
}
```

Then use `ThemeAssetResolver::setAdapter()` to inject it:

```php
use App\Lekhak\Services\ThemeAssetResolver;

ThemeAssetResolver::setAdapter(new MyCustomAdapter());
```

## Asset Resolution

Use `ThemeAssetResolver` to resolve asset paths:

```php
use App\Lekhak\Services\ThemeAssetResolver;

$css = ThemeAssetResolver::getStylesheet();
$logo = ThemeAssetResolver::resolveAsset('images/logo.png');
$type = ThemeAssetResolver::getAdapterType(); // 'native', 'wordpress', or 'joomla'
```
