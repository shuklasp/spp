<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeEntityCommand
 * Scaffolds a new SPPEntity definition.
 */
class MakeEntityCommand extends BaseMakeCommand
{
    protected string $name = 'make:entity';
    protected string $description = 'Create a new SPPEntity definition';

    public function getHelp(): string
    {
        return <<<HELP
Creates a new SPPEntity definition. If run without flags, it will launch an interactive wizard.

Usage:
  php spp.php make:entity [EntityName] [OPTIONS]

Options:
  --app=AppName         Specify the application context (defaults to "default").
  --table=TableName     Specify the database table name (defaults to lowercase plural of EntityName).
  --extends=Class       Specify the parent entity class (e.g. "\App\Entities\User").
  --login=true|false    Enable or disable SPP Login Support for this entity.
  --fields="f1:type,f2" Define attributes. Format: "name:type". Default type is varchar(255).
  --relations="Rel"     Define relationships. Format: "Target:Type:ForeignKey:PivotTable".
                        Example: "\App\Entities\Course:ManyToMany:student_id:student_courses"
  --api, --resource     Generate a REST API controller for this entity.

Examples:
  Interactive Mode:
    php spp.php make:entity Student

  Non-Interactive Mode:
    php spp.php make:entity Student --table=spp_students --fields="name:varchar(255),age:int" --extends="\App\Entities\User" --login=true --relations="\App\Entities\Profile:OneToOne:student_id"
HELP;
    }

    public function execute(array $args): void
    {
        $entityName = $args[2] ?? null;
        
        $fieldsArg = null;
        $tableName = null;
        $extendsClass = '';
        $loginEnabled = false;
        $relationsArg = null;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--fields=')) $fieldsArg = substr($arg, 9);
            if (str_starts_with($arg, '--app=')) $appname = substr($arg, 6);
            if (str_starts_with($arg, '--table=')) $tableName = substr($arg, 8);
            if (str_starts_with($arg, '--extends=')) $extendsClass = substr($arg, 10);
            if (str_starts_with($arg, '--login=')) $loginEnabled = strtolower(substr($arg, 8)) === 'true' || substr($arg, 8) === '1';
            if (str_starts_with($arg, '--relations=')) $relationsArg = substr($arg, 12);
        }

        if (!$entityName) {
            echo "Entity Name (e.g. Student): ";
            $entityName = trim(fgets(STDIN));
        }
        
        if (!$entityName) {
            echo "Error: Entity name is required.\n";
            return;
        }

        $entityName = preg_replace('/[^a-zA-Z0-9_]/', '', $entityName);
        $tableName = $tableName ?: strtolower($entityName) . "s";
        
        $config = [
            'table' => $tableName,
            'id_field' => 'id',
            'sequence' => $tableName . '_seq',
            'extends' => '',
            'login_enabled' => false,
            'attributes' => [],
            'relations' => []
        ];

        $isInteractive = ($fieldsArg === null && empty($extendsClass) && !$loginEnabled && $relationsArg === null);

        if (!$isInteractive) {
            if (!empty($extendsClass)) $config['extends'] = $extendsClass;
            if ($loginEnabled) $config['login_enabled'] = true;

            if ($fieldsArg !== null) {
                $fields = explode(',', $fieldsArg);
                foreach ($fields as $field) {
                    if (empty(trim($field))) continue;
                    $parts = explode(':', trim($field));
                    if (count($parts) >= 2) {
                        $config['attributes'][trim($parts[0])] = trim($parts[1]);
                    } else {
                        $config['attributes'][trim($parts[0])] = 'varchar(255)';
                    }
                }
            }
            
            if ($relationsArg !== null) {
                $relations = explode(',', $relationsArg);
                foreach ($relations as $relation) {
                    if (empty(trim($relation))) continue;
                    $parts = explode(':', trim($relation));
                    // Format: Target:Type:FK:Pivot
                    if (count($parts) >= 1) {
                        $target = trim($parts[0]);
                        $type = trim($parts[1] ?? 'OneToMany');
                        $fk = trim($parts[2] ?? strtolower($entityName) . '_id');
                        $rel = [
                            'child_entity' => $target,
                            'relation_type' => $type,
                            'child_entity_field' => $fk
                        ];
                        if ($type === 'ManyToMany') {
                            $rel['pivot_table'] = trim($parts[3] ?? strtolower($entityName) . "_" . strtolower(basename(str_replace('\\', '/', $target))));
                        }
                        $config['relations'][] = $rel;
                    }
                }
            }
        } else {
            // Interactive mode
            echo "Application/Context [{$appname}]: ";
            $inputApp = trim(fgets(STDIN));
            if ($inputApp) $appname = $inputApp;
            
            echo "Database Table [{$tableName}]: ";
            $inputTable = trim(fgets(STDIN));
            if ($inputTable) $config['table'] = $inputTable;
            
            echo "Extends (Parent Entity class, optional): ";
            $config['extends'] = trim(fgets(STDIN));
            
            echo "Enable Login Support? (y/n) [n]: ";
            $config['login_enabled'] = strtolower(trim(fgets(STDIN))) === 'y';
            
            echo "\nEntity Attributes (Press Enter on empty Name to finish):\n";
            while(true) {
                echo "  Attribute Name: ";
                $attrName = trim(fgets(STDIN));
                if (!$attrName) break;
                echo "  Type (e.g. varchar(255), int, text, timestamp) [varchar(255)]: ";
                $attrType = trim(fgets(STDIN)) ?: "varchar(255)";
                $config['attributes'][$attrName] = $attrType;
            }

            echo "\nEntity Relationships (Press Enter on empty Target to finish):\n";
            while(true) {
                echo "  Target Entity (e.g. \\App\\Entities\\Course): ";
                $target = trim(fgets(STDIN));
                if (!$target) break;
                echo "  Relation Type (OneToMany / ManyToMany) [OneToMany]: ";
                $type = trim(fgets(STDIN)) ?: "OneToMany";
                echo "  Foreign Key Field [" . strtolower($entityName) . "_id]: ";
                $fk = trim(fgets(STDIN)) ?: strtolower($entityName) . "_id";
                
                $rel = [
                    'child_entity' => $target,
                    'relation_type' => $type,
                    'child_entity_field' => $fk
                ];
                
                if ($type === 'ManyToMany') {
                    echo "  Pivot Table Name [" . strtolower($entityName) . "_" . strtolower(basename(str_replace('\\', '/', $target))) . "]: ";
                    $rel['pivot_table'] = trim(fgets(STDIN)) ?: strtolower($entityName) . "_" . strtolower(basename(str_replace('\\', '/', $target)));
                }
                
                $config['relations'][] = $rel;
            }
        }

        try {
            // Ensure SPP environment is bootstrapped enough for this
            if (!class_exists('\SPPMod\SPPEntity\SPPEntity')) {
                require_once dirname(__DIR__) . '/sppinit.php';
            }
            \SPPMod\SPPEntity\SPPEntity::saveEntityDefinition($entityName, $appname, $config);
            echo "\nSuccess: Entity {$entityName} saved and scaffolded in {$appname} context.\n";

            // If --api or --resource flag is passed, scaffold a REST controller
            if (in_array('--api', $args) || in_array('--resource', $args)) {
                $controllerName = $entityName . 'Controller';
                $controllerPath = SPP_APP_DIR . "/src/{$appname}/controllers/api";
                if (!is_dir($controllerPath)) {
                    mkdir($controllerPath, 0777, true);
                }
                
                $controllerCode = "<?php\n\nnamespace App\\Controllers\\Api;\n\nuse SPP\\Core\\ResourceController;\nuse SPPMod\\SPPEntity\\SPPEntity;\n\nclass {$controllerName} extends ResourceController\n{\n";
                $controllerCode .= "    protected string \$entityClass = '\\App\\Entities\\{$entityName}';\n\n";
                $controllerCode .= "    public function index() {\n        return \$this->json(\\SPPMod\\SPPEntity\\SPPEntity::get(\$this->entityClass, []));\n    }\n\n";
                $controllerCode .= "    public function show(\$id) {\n        return \$this->json(\\SPPMod\\SPPEntity\\SPPEntity::getById(\$this->entityClass, \$id));\n    }\n\n";
                $controllerCode .= "    public function store() {\n        // Handle create\n    }\n\n";
                $controllerCode .= "    public function update(\$id) {\n        // Handle update\n    }\n\n";
                $controllerCode .= "    public function destroy(\$id) {\n        // Handle delete\n    }\n}\n";

                file_put_contents($controllerPath . '/' . $controllerName . '.php', $controllerCode);
                echo "Success: REST Controller {$controllerName} generated for API scaffolding.\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
