# Core Module: SppUx

SppUx is the framework's high-level User Experience and Administrative UI system. it provides the components, layouts, and interaction patterns used to build the SPP Admin Panel and professional application dashboards.

---

## 1. Basic Philosophy
SppUx is built on the **"Component-First"** philosophy. Every UI element in the SPP ecosystem is a reusable component (`BaseComponent`) that manages its own state, rendering, and interaction logic. This ensures a consistent look and feel across all applications.

---

## 2. Architecture
The module is a hybrid of PHP-side component definitions and Frontend-side reactive logic.

### Key Components:
*   **BaseComponent**: The parent class for all interactive UI elements (Modals, Tables, Forms).
*   **UxRegistry**: Manages the registration and discovery of UI components across modules.
*   **Asset Bundler**: Automatically collects and serves the specific CSS and JS required by the active components.

---

## 3. API & Usage

### Creating a Component in PHP
```php
use \SPPMod\SppUx\BaseComponent;

class MyDashboardTable extends BaseComponent {
    public function getTemplate() {
        return 'ux/dashboard_table.blade.php';
    }
}
```

### Using a Component in a View
```html
<php-comp name="DataTable" 
          source="/api/nodes" 
          columns="title,author,date" />
```

---

## 4. Design System
SppUx implements a modern, CSS-variable-based design system that supports:
*   **Dark Mode**: Native support via `data-theme="dark"`.
*   **Glassmorphism**: Premium UI effects for dashboard cards and overlays.
*   **Responsive Layouts**: Flexible grid systems designed for complex data-heavy interfaces.

---
[Back to Modules Index](index.md)
