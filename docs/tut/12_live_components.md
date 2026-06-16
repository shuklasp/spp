# 12. Live Components (Livewire Clone)

SPP now features a highly reactive frontend layer powered by `LiveComponent`, enabling you to build dynamic interfaces in pure PHP without writing complex JavaScript or managing separate API endpoints. This feature works similarly to Laravel Livewire.

---

## Creating a Live Component

To create a live component, extend the `\SPPMod\SPPView\LiveComponent` class and implement the `render()` method.

```php
namespace App\Components;

use SPPMod\SPPView\LiveComponent;

class Counter extends LiveComponent
{
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }

    public function render(): string
    {
        return <<<HTML
        <div>
            <h2>Count: {$this->count}</h2>
            <!-- Use onclick to trigger backend methods -->
            <button onclick="sppLive.call('{$this->id}', 'increment')">
                Increment +
            </button>
        </div>
        HTML;
    }
}
```

### How it Works

1. **State Hydration**: Any `public` properties (like `$count`) are automatically serialized (dehydrated) and sent to the client on the first render.
2. **Tamper Protection**: SPP signs the state payload with an HMAC checksum (`spplive_secret`) to guarantee users cannot tamper with the component's internal state on the frontend.
3. **Execution**: When a user clicks the "Increment" button, the frontend sends a tiny JSON payload to the backend containing the current state and the method to call (`increment`).
4. **Re-rendering**: SPP reconstitutes the class instance, sets the public properties back to their current values, executes the method, and asks the component to re-render.
5. **DOM Patching**: The new HTML is returned to the client and patched into the DOM.

---

## Using Views (Blade, Twig, or AST)

You don't have to return raw HTML from the `render()` method. You can simply return a file path, and SPP will automatically render it using the `ViewCompiler` or your active template engine!

```php
    public function render(): string
    {
        // Renders using the AST ViewCompiler
        return 'views/counter.html'; 
    }
```

Inside `views/counter.html`:
```html
<div>
    <h2>Count: <?php echo $count; ?></h2>
    <button onclick="sppLive.call('<?php echo $id; ?>', 'increment')">Increment +</button>
</div>
```

---

## WebSockets vs AJAX

Under the hood, SPP attempts to use a WebSocket connection via `\SPPMod\SPPLive\LiveEmitter` if available, enabling lightning-fast, bi-directional reactivity. 

If WebSockets are disabled or fail to connect, it **automatically falls back** to the standard AJAX `/api.php?action=live_update` endpoint to ensure your components continue functioning perfectly in any environment!
