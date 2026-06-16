# Tutorial 11: Multi-Engine Paradigm Routing

Welcome to the future of frontend development in SPP! 

In earlier tutorials, we built classical SPP applications using `sppview` and the legacy `.html` + PHP rendering pipeline. Today, we're going to explore how SPP's **Multi-Engine Paradigm Router** allows you to seamlessly integrate modern reactive paradigms—using either **Blade** or **Twig**—right alongside your existing legacy routes.

## What is the Paradigm Router?

The Paradigm Router is a transparent interceptor built directly into `sppview`. When you define a route in your configuration (like `url: pages/dashboard.html`), the framework checks to see if the `drishyam` module is enabled.

If it is, the router searches for modern template equivalents in your `resources/views` directory. It uses a cascading fallback sequence:
1. **Twig:** `resources/views/pages/dashboard.twig`
2. **Blade:** `resources/views/pages/dashboard.blade.php`
3. **Legacy HTML/PHP:** `apps/your_app/pages/dashboard.html` (or `.php`)

This architecture allows you to selectively "upgrade" individual routes to modern reactive templates without touching your routing definitions or breaking your legacy pages!

---

## Step 1: Enabling Drishyam

To use Blade or Twig, ensure the `drishyam` (SPP-UX) module is enabled in your `app.yml`:

```yaml
modules:
  - sppview
  - drishyam
```

---

## Step 2: Defining a Route

Let's define a basic route in our `routes.yml`. Notice how we still specify the `.html` extension, preserving our application's contract.

```yaml
routes:
  dashboard:
    url: pages/dashboard.html
    auth: true
```

---

## Step 3: Choosing Your Engine

Now, instead of creating `apps/myapp/pages/dashboard.html`, we'll create a modern template in the `resources/views/pages/` folder. You can use whichever syntax you prefer!

### Option A: Using Twig
Create `resources/views/pages/dashboard.twig`:

```twig
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to the Twig Dashboard</h1>
    
    <div class="user-info">
        {# Using our unified SPP macros! #}
        {% if sppauth() %}
            <p>You are logged in.</p>
            {{ sppform('profile_update') }}
        {% else %}
            <p>Please log in.</p>
        {% endif %}
    </div>

    <!-- Mount an SPP-UX Component seamlessly -->
    {{ sppux('DashboardWidget', {'color': 'blue'}) }}
</body>
</html>
```

### Option B: Using Blade
Alternatively, if you prefer Laravel's Blade syntax, create `resources/views/pages/dashboard.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to the Blade Dashboard</h1>
    
    <div class="user-info">
        {{-- Using the exact same unified SPP macros! --}}
        @sppauth
            <p>You are logged in.</p>
            @sppform('profile_update')
        @endsppauth
    </div>

    <!-- Mount an SPP-UX Component seamlessly -->
    @sppux('DashboardWidget', ['color' => 'blue'])
</body>
</html>
```

---

## Unified Template Macros
Notice how both engines utilize the exact same custom SPP directives! We maintain a single source of truth (`TemplateMacros`) for all our custom tags.

Here are the most common macros available across both Twig and Blade:
- **Forms:** `@sppform('form_name')` / `{{ sppform('form_name') }}`
- **Elements:** `@sppelement('element_id')` / `{{ sppelement('element_id') }}`
- **Auth Checks:** `@sppauth` ... `@endsppauth` / `{% if sppauth() %}`
- **Reactive Components:** `@sppux('Comp')` / `{{ sppux('Comp') }}`
- **React/Vue Components:** `@react('Comp')` / `{{ react('Comp') }}`

---

## Step 4: Simple PHP Execution

What if you have a microservice route that just returns a raw script, or you just want a standard native PHP file? 
You can bypass the Paradigm Router entirely by defining the extension specifically in your route:

```yaml
routes:
  export:
    url: pages/export.php
```

Because the URL does not end in `.html`, the router will skip Twig and Blade checks and natively `include()` your `pages/export.php` file using the `DefaultViewRenderHandler`!

## Conclusion
You are now ready to build multi-paradigm applications. You can maintain your legacy HTML pages indefinitely, rewrite complex interfaces in Blade, and let your designers use Twig—all within the exact same application and routing structure!
