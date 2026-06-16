<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeMixedParadigmCommand
 * Scaffolds a Kitchen Sink example blending SPPView, Blade/Twig, and SPPUX.
 */
class MakeMixedParadigmCommand extends BaseMakeCommand
{
    protected string $name = 'make:mixed-paradigm';
    protected string $description = 'Scaffold a Kitchen Sink view blending SPPView, Drishyam, and SPPUX';

    public function execute(array $args): void
    {
        $name = null;
        
        foreach ($args as $i => $arg) {
            if (strpos(strtolower($arg), '--name=') === 0) {
                $name = substr($arg, 7);
            } elseif ($i === 0 && strpos($arg, '--') !== 0) {
                $name = $arg;
            }
        }

        if (!$name) {
            echo "Usage: php spp.php make:mixed-paradigm <ViewName>\n";
            return;
        }

        $context = $this->getContext($args);
        
        $className = ucfirst($name);
        
        // 1. Generate the SPPView Wrapper
        $sppViewDir = SPP_APP_DIR . "/src/" . $context . "/views";
        $sppViewPath = $sppViewDir . "/" . strtolower($className) . ".php";

        // 2. Generate the Blade Fragment
        $bladeDir = SPP_APP_DIR . "/resources/views/" . $context . "/fragments";
        $bladePath = $bladeDir . "/" . strtolower($className) . "_fragment.blade.php";

        // 3. Generate the SPPUX Island
        $uxDir = $this->getTargetDir('comp', $context);
        $uxJsPath = $uxDir . "/" . $className . "Island.js";

        if (file_exists($sppViewPath)) {
            echo "Error: Mixed Paradigm {$name} already exists.\n";
            return;
        }

        // --- SPPView Content ---
        $sppViewContent = <<<'PHP'
<?php

namespace App\{{CONTEXT}}\Views;

use SPPMod\SPPView\SPPView;
use SPPMod\Drishyam\Drishyam;

class {{CLASS_NAME}} extends SPPView
{
    public function render(array $data = []): string
    {
        // Render Blade Fragment
        $drishyam = new Drishyam();
        $bladeContent = $drishyam->render('{{CONTEXT_LOWER}}.fragments.{{FILE_NAME}}_fragment', $data);

        return $this->html([
            $this->head([
                $this->title('Mixed Paradigm - Kitchen Sink'),
                $this->script(['type' => 'module', 'src' => '/src/{{CONTEXT_LOWER}}/comp/{{CLASS_NAME}}Island.js'])
            ]),
            $this->body([
                $this->div(['style' => 'font-family: sans-serif; max-width: 900px; margin: auto; padding: 2rem;'], [
                    $this->h1('Layer 1: SPPView (Native AST)'),
                    $this->p('This outer shell is purely PHP AST.'),
                    
                    $this->hr(),
                    
                    $this->h2('Layer 2: Drishyam (Blade)'),
                    $this->div([], [$bladeContent]),
                    
                    $this->hr(),
                    
                    $this->h2('Layer 3: SPPUX (Reactive Island)'),
                    $this->tag('spp-element', ['name' => '{{CLASS_NAME}}Island'], [])
                ])
            ])
        ]);
    }
}
PHP;
        $sppViewContent = str_replace(
            ['{{CLASS_NAME}}', '{{CONTEXT}}', '{{CONTEXT_LOWER}}', '{{FILE_NAME}}'], 
            [$className, ucfirst($context), strtolower($context), strtolower($className)], 
            $sppViewContent
        );

        // --- Blade Content ---
        $bladeContent = <<<'BLADE'
<div style="background: #fdf2f8; padding: 1rem; border-radius: 8px; border: 1px solid #fbcfe8;">
    <h3>Rendered from Blade Template</h3>
    <p>This section is handled by the Drishyam template engine. It can use Blade directives and partials effortlessly.</p>
    <p><strong>Context Data:</strong> {{ json_encode($data ?? []) }}</p>
</div>
BLADE;

        // --- SPPUX Content ---
        $uxJsContent = <<<'JS'
export default class {{CLASS_NAME}}Island extends BaseComponent {
    async onInit() {
        this.setState({ count: 0 });
    }

    render() {
        return html`
            <div style="background: #ebf8ff; padding: 1rem; border-radius: 8px; border: 1px solid #bee3f8; text-align: center;">
                <h3>Rendered from SPPUX</h3>
                <p>This is a purely client-side reactive island.</p>
                <button 
                    style="background: #3182ce; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;"
                    @click="${() => this.setState({ count: this.state.count + 1 })}">
                    Clicked ${this.state.count} times
                </button>
            </div>
        `;
    }
}
JS;
        $uxJsContent = str_replace('{{CLASS_NAME}}', $className, $uxJsContent);


        // Create directories and write files
        @mkdir(dirname($sppViewPath), 0777, true);
        @mkdir(dirname($bladePath), 0777, true);
        @mkdir(dirname($uxJsPath), 0777, true);

        file_put_contents($sppViewPath, $sppViewContent);
        file_put_contents($bladePath, $bladeContent);
        file_put_contents($uxJsPath, $uxJsContent);

        echo "Success: Mixed Paradigm '{$className}' created.\n";
        echo " - SPPView: {$sppViewPath}\n";
        echo " - Blade:   {$bladePath}\n";
        echo " - SPPUX:   {$uxJsPath}\n";
    }
}
