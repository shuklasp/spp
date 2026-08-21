# SPPLive Full-Stack: Server-Driven Reactivity

Welcome to Part 2 of the SPP framework tutorials. If you've mastered SPP-UX (our client-side JavaScript engine), you might be wondering: *"How do I connect my JavaScript state to my database securely without writing dozens of API endpoints?"*

Enter **SPPLive**. 

## 1. What is SPPLive?
SPPLive is a Server-Driven UI architecture (inspired by Laravel Livewire). It allows you to write dynamic, reactive components using **pure PHP**. 
When a user clicks a button, SPPLive securely sends the interaction to the PHP server, the server recalculates the HTML, and SPP-UX surgically morphs the new HTML into the browser—all without a single page reload.

## 2. Architecture & Lifecycle
* **Dehydration**: When PHP renders a Live Component, it converts all `public` properties into a JSON state payload. It also generates a **Cryptographic HMAC Checksum** to ensure malicious users can't tamper with the state in their browser.
* **Hydration**: When a user triggers an action (e.g., typing in an input), `spplive.js` bundles the state and checksum, and sends it to the server.
* **Morphing**: PHP executes your backend logic, renders the new view, and returns the HTML string. The frontend uses `SPPUX.reconcileDOM()` to patch the DOM. Because it's a smart diff, the user never loses focus in their input boxes!

## 3. Step-by-Step Tutorial: Live Search

Let's build a real-time database search box.

### Step 1: Scaffold the Component
Use the built-in SPP CLI to generate the boilerplate:
```bash
php spp.php make:live-component SearchBox --app=admin
```
This generates two files: a PHP class and an external HTML partial (adhering strictly to the framework's "Zero Inline HTML" rule).

### Step 2: The PHP Backend (`src/admin/live/class.searchbox.php`)
```php
<?php
namespace Admin\Live;

use SPPMod\SPPView\LiveComponent;

class SearchBox extends LiveComponent 
{
    // Public properties are automatically synced with the browser!
    public string $query = '';
    public array $results = [];

    // This method is triggered from the browser
    public function syncModel(): void 
    {
        if (strlen($this->query) > 2) {
            // Pretend we are querying a database
            $this->results = ['Apple', 'Banana', 'Orange']; 
        } else {
            $this->results = [];
        }
    }

    public function render(): string 
    {
        // Reference the external HTML partial
        return 'views/partials/SearchBox.html';
    }
}
```

### Step 3: The HTML Partial (`resources/admin/views/partials/SearchBox.html`)
```html
<div>
    <h3>Search Fruits</h3>
    
    <!-- wire:model automatically syncs the input with PHP's $query property -->
    <!-- .debounce.500ms prevents spamming the server while the user types! -->
    <input type="text" wire:model.debounce.500ms="query" placeholder="Type a fruit...">
    
    <!-- wire:loading shows visual feedback during the network request -->
    <span wire:loading>Searching...</span>

    <ul>
        <?php foreach ($results as $fruit): ?>
            <li><?= htmlspecialchars($fruit) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
```

That's it! When you type in the box, SPPLive pauses for 500ms (debounce), shows the "Searching..." text, hits the PHP backend, updates the `$results` array, and morphs the `<ul>` directly into the DOM. 

## 4. SPA Navigation (`wire:navigate`)
SPPLive also includes a lightning-fast router. If you have internal links in your application, just add the `wire:navigate` attribute:

```html
<a href="/dashboard" wire:navigate>Go to Dashboard</a>
```

When clicked, SPPLive intercepts the link, fetches the next page in the background, updates the browser URL, and uses the browser's native **View Transitions API** to seamlessly cross-fade to the new page. No full page reloads, ever again!

## 5. Security & Observability (Enterprise Features)
* **Tamper Protection**: Your component state is protected by `wire:checksum`. If a user alters the JSON payload in their browser to elevate privileges, the PHP server will instantly reject the request with a `403 Forbidden` error.
* **W3C Distributed Tracing**: SPPLive automatically propagates `traceparent` telemetry headers across the network boundary, ensuring that you can track a specific user's `wire:click` all the way down to the underlying SQL query in your observability dashboards (e.g., Datadog, OpenTelemetry).
