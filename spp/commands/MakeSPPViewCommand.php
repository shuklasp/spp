<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class MakeSPPViewCommand
 * Scaffolds a native AST-based SPPView.
 */
class MakeSPPViewCommand extends BaseMakeCommand
{
    protected string $name = 'make:sppview';
    protected string $description = 'Scaffold a new native AST SPPView template';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

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
            echo "Usage: php spp.php make:sppview <ViewName>\n";
            return;
        }

        $context = $this->getContext($args);
        $targetDir = SPP_APP_DIR . "/src/" . $context . "/views";
        
        $fileName = strtolower($name) . '.php';
        $filePath = $targetDir . "/" . $fileName;

        if (file_exists($filePath)) {
            echo "Error: SPPView {$name} already exists.\n";
            return;
        }

        $content = <<<'PHP'
<?php

namespace App\{{CONTEXT}}\Views;

use SPPMod\SPPView\SPPView;

class {{CLASS_NAME}} extends SPPView
{
    public function render(array $data = []): string
    {
        return $this->html([
            $this->head([
                $this->title('{{CLASS_NAME}} - SPPView'),
                $this->style('
                    .sppview-container { font-family: sans-serif; max-width: 800px; margin: auto; padding: 2rem; }
                    .sppview-header { background: #1a202c; color: white; padding: 2rem; border-radius: 8px; }
                ')
            ]),
            $this->body([
                $this->div(['class' => 'sppview-container'], [
                    $this->div(['class' => 'sppview-header'], [
                        $this->h1('{{CLASS_NAME}} Native View'),
                        $this->p('Rendered purely via AST without template parsing overhead.')
                    ]),
                    $this->div(['style' => 'margin-top: 2rem;'], [
                        $this->h3('Data context:'),
                        $this->pre(print_r($data, true))
                    ])
                ])
            ])
        ]);
    }
}
PHP;

        $content = str_replace(
            ['{{CLASS_NAME}}', '{{CONTEXT}}'], 
            [ucfirst($name), ucfirst($context)], 
            $content
        );
        
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        
        file_put_contents($filePath, $content);
        echo "Success: SPPView created at {$filePath}\n";
    }
}
