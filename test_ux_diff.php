<?php
require_once __DIR__ . '/spp/sppinit.php';
\SPP\App::getApp('TestApp');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SPP-UX Reconciliation Test</title>
    <script src="<?php echo \SPPMod\SPPUX\SPPUX::runtimePath(); ?>"></script>
    <script src="<?php echo \SPPMod\SPPUX\SPPUX::loaderPath(); ?>" type="module"></script>
    <style>
        body { font-family: system-ui, sans-serif; padding: 2rem; background: #f0f4f8; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); max-width: 500px; margin: 0 auto; }
        input { width: 100%; padding: 0.5rem; margin: 1rem 0; border: 1px solid #cbd5e0; border-radius: 4px; }
        .status { font-size: 0.875rem; color: #718096; }
        .timer { font-weight: bold; color: #4a5568; }
    </style>
</head>
<body>
    <div id="test-root" data-spp-component="DiffTest"></div>

    <script>
        // Inline component definition for testing
        class DiffTest extends BaseComponent {
            async onInit() {
                this.setState({ 
                    text: '', 
                    counter: 0 
                });
                
                // Auto-increment counter every second to trigger re-renders
                setInterval(() => {
                    this.setState({ counter: this.state.counter + 1 });
                }, 1000);
            }

            render() {
                return html`
                    <div class="card">
                        <h2>DOM Reconciliation Test</h2>
                        <p class="status">The counter updates every second. Try typing in the field below. Focus should NOT be lost.</p>
                        <div class="timer">Counter: ${this.state.counter}</div>
                        
                        <input type="text" 
                               placeholder="Type something here..." 
                               value="${this.state.text}"
                               @input="${(e) => this.setState({ text: e.target.value })}">
                        
                        <p>Live Preview: <strong>${this.state.text || '(empty)'}</strong></p>
                    </div>
                `;
            }
        }
        window.DiffTest = DiffTest;
    </script>
</body>
</html>
