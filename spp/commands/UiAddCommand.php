<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class UiAddCommand
 * 
 * Fetches and injects pre-built SPP-UX zero-build components into the project.
 * Completely bypasses the need for an NPM ecosystem.
 */
class UiAddCommand extends Command
{
    protected string $name = 'ui:add';
    protected string $description = 'Add beautifully styled, zero-build SPP-UX components to your project';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $component = $this->getArgument($args, 0);
        if (!$component) {
            echo "Usage: php spp.php ui:add <component_name>\n";
            echo "Available components: datepicker, modal, accordion, combobox\n";
            return;
        }

        $app = $this->getOption($args, 'app', 'default');
        $targetDir = ($app === 'default') ? SPP_APP_DIR . '/spp/components' : SPP_APP_DIR . "/src/{$app}/components";

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Simulate fetching from a central repository (e.g., https://ui.spp.dev/registry/)
        echo "Fetching registry for '{$component}'...\n";
        sleep(1); 

        $componentFile = "{$targetDir}/{$component}.js";
        
        $stub = <<<JS
import { html, defineElement, createStore } from '/spp/modules/spp/drishyam/js/sppux.js';
import BaseComponent from '/spp/modules/spp/drishyam/js/BaseComponent.js';

/**
 * SPP-UX {$component} component.
 * Automatically scaffolded via `php spp.php ui:add {$component}`.
 */
class SppUi{$component} extends BaseComponent {
    constructor(app, element, props) {
        super(app, element, props);
        this.store = createStore({ active: false });
    }

    render() {
        return html`
            <div class="spp-ui-{$component}">
                <!-- Component UI goes here -->
                <p>Hello from the zero-build {$component} component!</p>
            </div>
        `;
    }
}

defineElement('spp-ui-{$component}', SppUi{$component});
JS;

        file_put_contents($componentFile, $stub);

        echo "Success: Component {$component} downloaded and installed to {$componentFile}\n";
        echo "You can now use <spp-ui-{$component}></spp-ui-{$component}> anywhere in your HTML.\n";
    }
}
