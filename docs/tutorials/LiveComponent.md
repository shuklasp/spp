# SPP LiveComponent: A Novice-First Guide

Welcome to the ultimate guide to **SPP LiveComponent**. Whether you are completely new to SPP or a seasoned veteran, this guide will explain everything you need to know about building highly reactive, dynamic, and modern web applications without writing custom JavaScript.

## What is a LiveComponent?

In modern web development, users expect pages to feel alive. When they click a button, type in a search box, or submit a form, they expect the page to update instantly without a full page reload. Typically, achieving this requires building complex API endpoints and writing frontend JavaScript using frameworks like React, Vue, or Alpine.js.

**SPP LiveComponent solves this by letting you write reactive interfaces entirely in PHP.**

A LiveComponent is a PHP class combined with an HTML view (a partial). When the user interacts with the HTML on the frontend (like clicking a button or typing in a text field), SPP automatically communicates with the PHP class on the backend, updates the state, re-renders the component, and seamlessly morphs the DOM on the frontend. 

It feels like a single-page application (SPA), but you only ever write PHP and standard HTML!

## Core Concepts & Architecture

### 1. The Lifecycle
Every LiveComponent goes through a predictable lifecycle:
- **`mount(array $params)`**: Runs once when the component is first created. This is where you initialize data.
- **`hydrate(array $state)`**: Runs on every subsequent request to restore the public properties from the frontend.
- **`boot()` & `booted()`**: Runs on every single request, useful for setting up dependencies.
- **`updating($name, $value)` & `updated($name, $value)`**: Runs before and after a property is updated via `wire:model`.
- **`rendering()` & `render()` & `rendered()`**: The phase where the HTML is generated.
- **`dehydrate()`**: Runs at the end of the request to serialize the public properties to be sent back to the frontend.

### 2. Zero Inline HTML (Strict External Partials)
In SPP, we never write HTML strings directly inside our PHP classes. **This is a strict framework rule.** Your `render()` method must return the path to an external partial file (e.g., `'partials/counter.html'`).

### 3. State Management
Any `public` property on your LiveComponent class is automatically available in your view. Furthermore, these public properties are automatically tracked. If a user modifies an input bound to a public property (using `wire:model`), the PHP property is updated automatically.

*Security Note:* Because public properties are serialized and sent to the client, you should **never** store sensitive data (like passwords or private keys) in public properties.

## Step-by-Step Tutorial: Building a Counter

Let's build a simple counter to see LiveComponent in action.

### Step 1: Scaffold the Component

We use the SPP CLI to generate the component and its view automatically:

```bash
php spp.php make:livecomponent Counter
```

This creates two files:
1. `class.Counter.php` (The PHP logic)
2. `partials/counter.html` (The HTML view)

### Step 2: Write the PHP Logic

Open your PHP class and add a public property and a method:

```php
<?php
namespace SPP\App\Live;

use SPPMod\SPPView\LiveComponent;

class Counter extends LiveComponent
{
    public int $count = 0;

    public function mount(array $params = []): void
    {
        $this->count = $params['start'] ?? 0;
    }

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count--;
    }

    public function render(): string
    {
        // Notice we return a path to the file, NOT raw HTML strings.
        return 'partials/counter.html';
    }
}
```

### Step 3: Write the HTML View

Open your partial HTML file. We use `wire:click` to tell SPP to call our PHP methods when the buttons are clicked.

```html
<div>
    <h1>Counter: {{ $count }}</h1>
    
    <button wire:click="increment">+</button>
    <button wire:click="decrement">-</button>
</div>
```

### Step 4: Include it in your Page

In your main page template, use the template directive to include the LiveComponent:

```html
<!-- Inside a Twig or Blade template -->
@livecomponent('Counter', ['start' => 5])
```

That's it! You now have a reactive counter. 

## Advanced Features

### 1. Data Binding (`wire:model`)

You can instantly sync an HTML input field to a PHP property using `wire:model`.

```html
<input type="text" wire:model="search">
```

By default, SPP is highly optimized and defers sending the update to the server until the next network request (e.g., when a button is clicked). If you want it to update instantly as the user types, add the `.live` modifier:

```html
<input type="text" wire:model.live.debounce.300ms="search">
```

### 2. Loading Indicators (`wire:loading`)

Because LiveComponents rely on network requests, it's good practice to show loading states. SPP makes this trivial:

```html
<button wire:click="save">Save Changes</button>

<span wire:loading wire:target="save">
    Saving your data... please wait.
</span>
```

### 3. Form Objects (Extracting Complex Forms)

For large forms, putting 20 public properties on your component gets messy. SPP provides `LiveForm` objects.

```php
use SPPMod\SPPView\LiveForm;
use SPP\Attributes\Validate;

class PostForm extends LiveForm
{
    #[Validate('required|min:5')]
    public string $title = '';

    #[Validate('required')]
    public string $content = '';
}
```

Then in your component:
```php
public PostForm $form;

public function save()
{
    $this->form->validate();
    // Save to database...
}
```

### 4. Attributes (`#[Locked]`, `#[Url]`, `#[Renderless]`)

SPP embraces PHP 8 attributes to add superpowers to your components:
- `#[Locked]`: Prevents the frontend from modifying a property. Great for security!
- `#[Url]`: Automatically syncs a property with the browser's URL query string.
- `#[Renderless]`: Tells SPP that a method does not change the UI, skipping the HTML re-rendering phase to save server resources and bandwidth.

### 5. JavaScript Interoperability (No Alpine.js Required)

Unlike Laravel Livewire, SPP LiveComponent does not force you to install Alpine.js. You can interact with your component's state using plain JavaScript via the `$wire` proxy or the `window.SPPEntangle()` helper.

**Using `$wire` to access state:**
You can access the `$wire` object directly from any element inside your component using `SPP.Live.getWireProxy(id)` or by binding it to your vanilla JS. However, the easiest way to bridge state to custom vanilla JS components or Alpine.js (if you choose to use it) is via entangling.

**Using `SPPEntangle` to sync state:**
If you want to sync a LiveComponent property with a frontend JS variable, use the entangle helper:

```html
<div id="my-custom-js-widget">
    <!-- Frontend widget that updates based on LiveComponent state -->
</div>

<script>
    document.addEventListener('spplive:morphed', function(e) {
        // Grab a reference to your component's element
        const el = document.getElementById('my-custom-js-widget');
        
        // Entangle the 'search' property
        // When 'search' changes in PHP, this proxy updates. 
        // When you set this proxy, it sends an update to PHP.
        const searchState = window.SPPEntangle(el, 'search');
        
        console.log(searchState.value); // Read state
        searchState.value = 'New Search'; // Write state (triggers a network request)
    });
</script>
```

This allows seamless integration with Alpine.js, Vue, React, or custom vanilla Web Components.

## Conclusion

SPP LiveComponent bridges the gap between backend reliability and frontend interactivity. By adhering to the external partial rule and utilizing modern features like Idiomorph DOM patching and Form Objects, you can build incredibly robust web applications in a fraction of the time.
