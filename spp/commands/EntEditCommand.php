<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EntEditCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $entityName = $args[2] ?? null;
        if (!$entityName) {
            require_once SPP_APP_DIR . '/spp/sppinit.php';
            $entities = \SPPMod\SPPEntity\SPPEntity::listAvailableEntities();
            echo "Available Entities:\n";
            foreach (array_keys($entities) as $name) echo "  - $name\n";
            $entityName = prompt("\nEntity Name to Edit");
        }
        
        require_once SPP_APP_DIR . '/spp/sppinit.php';
        $cfgFile = \SPPMod\SPPEntity\SPPEntity::getEntityConfigFile($entityName);
        if (!$cfgFile) die("Error: Entity '{$entityName}' not found.\n");
        
        try {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($cfgFile);
            $appname = strpos($cfgFile, 'apps/') !== false ? explode('/', explode('apps/', $cfgFile)[1])[0] : 'default';

            while(true) {
                echo "\n--- Editing Entity: {$entityName} ---\n";
                echo "1) Edit Metadata (Table: {$config['table']}, Parent: " . ($config['extends'] ?? 'None') . ", Login: " . ($config['login_enabled'] ? 'Yes' : 'No') . ")\n";
                echo "2) Manage Attributes (" . count($config['attributes'] ?? []) . " defined)\n";
                echo "3) Manage Relationships (" . count($config['relations'] ?? []) . " defined)\n";
                echo "4) Save & Quit\n";
                echo "5) Quit without Saving\n";
                
                $choice = prompt("Choice", "4");
                
                if ($choice == '1') {
                    $config['table'] = prompt("  Database Table", $config['table']);
                    $config['extends'] = prompt("  Extends (Parent)", $config['extends'] ?? '');
                    $config['login_enabled'] = strtolower(prompt("  Enable Login Support? (y/n)", ($config['login_enabled'] ?? false) ? 'y' : 'n')) === 'y';
                } elseif ($choice == '2') {
                    while(true) {
                        echo "\nAttributes:\n";
                        foreach (($config['attributes'] ?? []) as $k => $v) echo "  - $k: $v\n";
                        $act = strtolower(prompt("  (A)dd, (E)dit, (R)emove, (B)ack", "b"));
                        if ($act === 'b') break;
                        if ($act === 'a') {
                            $name = prompt("    Name");
                            if ($name) $config['attributes'][$name] = prompt("    Type", "varchar(255)");
                        } elseif ($act === 'e') {
                            $name = prompt("    Attribute Name to Edit");
                            if (isset($config['attributes'][$name])) {
                                $newName = prompt("      New Name", $name);
                                $type = prompt("      Type", $config['attributes'][$name]);
                                if ($newName !== $name) unset($config['attributes'][$name]);
                                $config['attributes'][$newName] = $type;
                            } else {
                                echo "    Error: Attribute '{$name}' not found.\n";
                            }
                        } elseif ($act === 'r') {
                            $name = prompt("    Name to Remove");
                            if (isset($config['attributes'][$name])) unset($config['attributes'][$name]);
                        }
                    }
                } elseif ($choice == '3') {
                    while(true) {
                        echo "\nRelationships:\n";
                        foreach (($config['relations'] ?? []) as $idx => $rel) {
                            echo "  $idx) {$rel['relation_type']} -> {$rel['child_entity']} ({$rel['child_entity_field']})\n";
                        }
                        $act = strtolower(prompt("  (A)dd, (E)dit, (R)emove, (B)ack", "b"));
                        if ($act === 'b') break;
                        if ($act === 'a') {
                            $target = prompt("    Target Entity");
                            if ($target) {
                                $rel = [
                                    'child_entity' => $target,
                                    'relation_type' => prompt("    Type", "OneToMany"),
                                    'child_entity_field' => prompt("    FK Field", strtolower($entityName) . "_id")
                                ];
                                if ($rel['relation_type'] === 'ManyToMany') {
                                    $rel['pivot_table'] = prompt("    Pivot Table", strtolower($entityName) . "_" . strtolower(basename(str_replace('\\', '/', $target))));
                                }
                                $config['relations'][] = $rel;
                            }
                        } elseif ($act === 'e') {
                            $idx = prompt("    Index to Edit");
                            if (isset($config['relations'][$idx])) {
                                $rel = &$config['relations'][$idx];
                                $rel['child_entity'] = prompt("      Target Entity", $rel['child_entity']);
                                $rel['relation_type'] = prompt("      Type", $rel['relation_type']);
                                $rel['child_entity_field'] = prompt("      FK Field", $rel['child_entity_field']);
                                if ($rel['relation_type'] === 'ManyToMany') {
                                    $rel['pivot_table'] = prompt("      Pivot Table", $rel['pivot_table'] ?? '');
                                } else {
                                    unset($rel['pivot_table']);
                                }
                            } else {
                                echo "    Error: Relation index '{$idx}' not found.\n";
                            }
                        } elseif ($act === 'r') {
                            $idx = prompt("    Index to Remove");
                            if (isset($config['relations'][$idx])) array_splice($config['relations'], $idx, 1);
                        }
                    }
                } elseif ($choice == '4') {
                    \SPPMod\SPPEntity\SPPEntity::saveEntityDefinition($entityName, $appname, $config);
                    echo "Success: Entity definition updated.\n";
                    break;
                } elseif ($choice == '5') {
                    break;
                }
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }

    public function getName(): string
    {
        return 'ent:edit';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ent:edit';
    }
}
