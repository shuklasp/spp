<?php
namespace SPPMod\Sppdb\Commands;

use SPP\CLI\Command;

class MakeMigrationCommand extends Command {
    protected string $name = 'make:migration';
    protected string $description = 'Create a new database migration file';

    public function execute(array $args): void {
        if (empty($args[0])) {
            echo "\033[31mError:\033[0m Migration name required (e.g. create_users_table)\n";
            exit(1);
        }

        $context = \SPP\Scheduler::getContext();
        $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $args[0])); // snake_case
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$name}.php";
        
        $path = SPP_APP_DIR . '/src/' . $context . '/migrations';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        
        $filePath = $path . '/' . $fileName;

        $template = <<<PHP
<?php

use SPPMod\Sppdb\Migration\SPPMigration;

class {$className} extends SPPMigration {
    
    public function up(): void {
        // \$this->db->exec_squery("CREATE TABLE ...");
    }
    
    public function down(): void {
        // \$this->db->exec_squery("DROP TABLE ...");
    }
}
PHP;

        file_put_contents($filePath, $template);
        echo "\033[32mMigration created:\033[0m {$filePath}\n";
    }
}
