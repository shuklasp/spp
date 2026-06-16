# SPP Live Components & Reactive Forms

The SPP Framework introduces a zero-dependency, server-side reactive component system similar to Laravel Livewire, alongside a powerful client-side validation engine. This allows developers to build dynamic interfaces without relying on heavy external frontend frameworks.

## 1. LiveComponent (Backend Reactivity)

### What is LiveComponent?
`LiveComponent` is a base class that extends traditional SPP UI components. It automatically tracks public properties (state), serializes them, and ships them to the frontend with an HMAC SHA-256 cryptographic signature.

When the frontend triggers an action (like a button click or typing in an input), the state is sent back to the server, rehydrated, the action is executed, and the resulting DOM diff is sent back to patch the page seamlessly.

### Creating a LiveComponent
```php
namespace App\Components;

use SPPMod\SPPView\LiveComponent;

class CounterComponent extends LiveComponent
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
            <button wire:click="increment">Increment</button>
        </div>
        HTML;
    }
}
```

### Security & Tampering Protection
All state data passed to the frontend is automatically signed using the session key `spplive_secret` generating a `wire:checksum`.
If a malicious user attempts to manually alter the JSON state payload before triggering an action, the backend checksum verification will fail, and the request will be rejected.

---

## 2. The Frontend Patcher (`spplive.js`)

The framework ships with a lightweight Javascript manager located at `spp/modules/spp/sppview/js/spplive.js`.
You must include this script on any page that uses LiveComponents:
```html
<script src="/spp/modules/spp/sppview/js/spplive.js"></script>
```

### How It Works:
1. **Discovery:** Scans the DOM for elements with `[wire:id]`.
2. **Interception:** Binds event listeners to `wire:click` and `wire:model` directives.
3. **Transport Routing:**
   - Primary: Attempts to send the payload via WebSockets (`spplive`/`LiveEmitter`).
   - Fallback: Gracefully falls back to AJAX (`class.sppajax.php` using the `live_update` action) if WebSockets are unavailable.
4. **DOM Patching:** Receives the updated HTML from the server and intelligently patches the specific component DOM nodes.

---

## 3. Dynamic Client-Side JS Validators (`sppvalidators`)

Traditional SPP forms generate native HTML5 validation attributes (`required`, `pattern`, `minlength`). However, some advanced logic cannot be represented in HTML5.

Phase 5 introduced a Dynamic Script generation engine directly into the validator pipeline.

### The Problem
Rules like `MatchValidator` (checking if "Password" matches "Confirm Password") or complex custom rules require Javascript, forcing developers to write manual front-end logic.

### The SPP Solution
The `SPP_Single_validator` base class now features a `getClientScript()` hook.
When `ViewForm` compiles and renders the closing `</form>` tag, it intercepts all attached validators:

1. It gathers any non-null strings returned by `getClientScript()`.
2. It wraps them into an intelligent `onSubmit` JavaScript block attached to the form.
3. If validation fails, it natively invokes `element.setCustomValidity()` and `element.reportValidity()`, showing modern, localized browser tooltips instead of crude `alert()` boxes.

### Example: MatchValidator Under The Hood
When you attach a `MatchValidator` in PHP, SPP automatically writes the following Javascript equivalent into the DOM:
```javascript
// Automatically injected by SPP ViewForm
const source = document.getElementById('password_confirm');
const target = document.querySelector('[name="password"]');
if (source && target && source.value !== target.value) {
    source.setCustomValidity('Fields do not match.');
    source.reportValidity();
    return false; // Blocks form submission!
}
```

This guarantees 100% parity between server-side PHP validation rules and instant client-side feedback without requiring external NPM packages.
