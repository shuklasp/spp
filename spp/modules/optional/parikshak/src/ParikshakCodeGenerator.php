<?php
namespace SPPMod\Parikshak;

use Symfony\Component\Yaml\Yaml;

/**
 * Class ParikshakCodeGenerator
 * Generates test code, entities, and blueprints.
 */
class ParikshakCodeGenerator
{
    private ParikshakFuzzer $fuzzer;

    public function __construct(ParikshakFuzzer $fuzzer)
    {
        $this->fuzzer = $fuzzer;
    }

    /**
     * Generates a reusable test code file.
     */
    public function generateTestCode(string $entityClass, string $appname): void
    {
        $refl = new \ReflectionClass($entityClass);
        $entityShortName = $refl->getShortName();
        $targetDir = SPP_APP_DIR . '/src/' . $appname . '/tests/auto';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = $targetDir . '/' . $entityShortName . 'AutoTest.php';

        $attributes = $entityClass::getMetadata('attributes');
        $dataStr = var_export(array_map(fn ($t) => $this->fuzzer->fuzz($t, 'fuzz'), $attributes), true);

        $code = "<?php\n";
        $code .= "namespace App\\" . ucfirst($appname) . "\\Tests\\Auto;\n\n";
        $code .= "use $entityClass;\n\n";
        $code .= "/**\n * Auto-generated Test for $entityShortName (Parikshak)\n * Generation Date: " . date('Y-m-d H:i:s') . "\n */\n";
        $code .= "class " . $entityShortName . "AutoTest\n";
        $code .= "{\n    public static function run()\n    {\n";
        $code .= "        echo \"Running evaluator for $entityShortName... \";\n";
        $code .= "        try {\n";
        $code .= "            \$entity = new $entityShortName();\n";
        $code .= "            \$data = $dataStr;\n";
        $code .= "            foreach (\$data as \$k => \$v) \$entity->set(\$k, \$v);\n";
        $code .= "            \$id = \$entity->save();\n";
        $code .= "            if (!\$id) throw new \\Exception('Failed to save entity');\n";
        $code .= "            echo \"OK (ID: \$id)\\n\";\n";
        $code .= "        } catch (\\Exception \$e) {\n";
        $code .= "            echo \"FAILED: \" . \$e->getMessage() . \"\\n\";\n";
        $code .= "        }\n";
        $code .= "    }\n}\n";

        file_put_contents($fileName, $code);
    }

    /**
     * The Dreamer: Local Shorthand Architect
     * Format: ClassName(attr:type, attr:type)
     */
    public function dreamEntity(string $shorthand, string $appname): bool
    {
        if (preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\(([^)]+)\)$/', $shorthand, $m)) {
            $name = $m[1];
            $attrsStr = $m[2];
            $attrs = [];
            foreach (explode(',', $attrsStr) as $pair) {
                $p = explode(':', trim($pair));
                if (count($p) === 2) {
                    $attrName = trim($p[0]);
                    $attrType = trim($p[1]);
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $attrName) && preg_match('/^[a-zA-Z0-9_\(\)]+$/', $attrType)) {
                        $attrs[$attrName] = $attrType;
                    }
                }
            }

            $yamlData = [
                'table' => strtolower($name) . 's',
                'audit' => true,
                'attributes' => []
            ];

            foreach ($attrs as $k => $v) {
                if (strtolower($v) === 'string') {
                    $v = 'varchar(255)';
                }
                $yamlData['attributes'][$k] = $v;
            }
            $yamlData['attributes']['created_at'] = 'varchar(50)';
            $yamlData['attributes']['updated_at'] = 'varchar(50)';

            $yaml = Yaml::dump($yamlData, 4, 2);

            $path = SPP_APP_DIR . "/etc/apps/{$appname}/entities/" . strtolower($name) . ".yml";
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $yaml);

            $php = "<?php\nnamespace App\\" . ucfirst($appname) . "\\Entities;\nclass {$name} extends \\SPPMod\\SPPEntity\\SPPEntity {}\n";
            $phpPath = SPP_APP_DIR . "/src/{$appname}/entities/entity.{$name}.php";
            if (!is_dir(dirname($phpPath))) {
                mkdir(dirname($phpPath), 0777, true);
            }
            file_put_contents($phpPath, $php);

            return true;
        }
        return false;
    }

    /**
     * CRUD Blueprint Generator: Produces boilerplate code based on entity metadata.
     */
    public function generateBlueprint(string $entityClass): array
    {
        $refl = new \ReflectionClass($entityClass);
        $name = $refl->getShortName();
        $lcName = strtolower($name);

        $controller = "<?php\nnamespace App\Default\Controllers;\nclass {$name}Controller extends \SPP\Controller {\n";
        $controller .= "    public function index() {\n        \$this->view('{$lcName}');\n    }\n}\n";

        return [
            'controller' => $controller,
            'view' => "// Auto-generated SPP-UX View for {$name}\nclass {$name}View extends SPPView {\n    render() { return html`<h1>{$name} Management</h1>`; }\n}"
        ];
    }
}
