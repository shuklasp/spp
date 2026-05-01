# SPP-UX Master Framework (v12)

The **SPP-UX** framework is a zero-dependency, high-performance UI toolkit designed for building premium, glassmorphic administrative interfaces within the SPP ecosystem.

## 🚀 Getting Started

Include the framework assets in your page:
```html
<link rel="stylesheet" href="css/sppux.css">
<script src="js/sppux.js"></script>    <!-- Core Engine -->
<script src="js/sppux-ui.js"></script> <!-- UI Library -->
```

## 🏗️ Core Engine (`sppux.js`)

### Reactive Components
Create a custom component by extending `BaseComponent`:
```javascript
class MyProfile extends BaseComponent {
    onInit() { this.setState({ name: 'Guest' }); }
    render() {
        return html`
            <div class="profile-card">
                <h2>Hello, ${this.state.name}</h2>
                <button @click=${() => this.setState({ name: 'Admin' })}>Login</button>
            </div>
        `;
    }
}
```

## 🎨 Visual Library (`sppux-ui.js`)

### Key Helpers
- **Modal**: `SPPUX.Modal.open("Title", "Content", [{ label: "OK", fn: (m) => m.close() }])`
- **Toast**: `SPPUX.Notify.show("Record Saved!", "success")` (Triggers Confetti!)
- **Spotlight**: `SPPUX.Spotlight.open([{ title: "Home", icon: "🏠" }], (item) => {})`
- **Drawer**: `SPPUX.Drawer.open("Title", "Side Panel Content", "right")`

## 🌈 Legendary Themes

### Built-in Presets
You can switch themes dynamically:
```javascript
SPPUX.Theme.set('midnight');  // Indigo/Slate
SPPUX.Theme.set('saffron');   // Saffron/Deep Brown (Legendary)
SPPUX.Theme.set('emerald');   // Green/Emerald
SPPUX.Theme.set('cyberpunk'); // Neon Pink/Purple
```

### Custom Themes (Create your own)
To create a custom look for your app, simply override the CSS variables in your app's stylesheet:
```css
:root {
    --sppux-primary: #f43f5e;           /* Your primary brand color */
    --sppux-primary-glow: rgba(244, 63, 94, 0.4);
    --sppux-panel: rgba(20, 0, 10, 0.98); /* Panel background */
}
```

## 💎 Elite Features
- **File Dropzone**: `SPPUX.Dropzone.render((files) => upload(files))`
- **God-Tier Buttons**: `SPPUX.Button.render("Submit", { variant: 'glass', loading: true })`
- **Smart Tooltips**: Simply add `data-spp-tooltip="Your hint"` to any element.

---
*Built with ❤️ for the Point of Productivity.*
