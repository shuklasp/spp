<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class MakeSeederCommand extends BaseMakeCommand
{
    protected string $name = 'make:seeder';
    protected string $description = 'Create a new Database Seeder class';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $seederName = $this->getArgument($args, 0) ?? null;
        $appname = "default";

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app='))
                $appname = substr($arg, 6);
        }

        if (!$seederName) {
            echo "Seeder Name (e.g. UserSeeder): ";
            $seederName = trim(fgets(STDIN));
        }

        if (!$seederName) {
            echo "Error: Seeder name is required.\n";
            return;
        }

        if (!str_ends_with($seederName, 'Seeder')) {
            $seederName .= 'Seeder';
        }

        $seederPath = SPP_APP_DIR . "/src/{$appname}/seeders";
        if (!is_dir($seederPath)) {
            mkdir($seederPath, 0777, true);
        }

        $code = "<?php\n\nnamespace App\\Seeders;\n\nuse SPPMod\\SPPDB\\SPPDB;\n\nclass {$seederName}\n{\n";
        $code .= "    public function run(SPPDB \$db): void\n    {\n";
        $code .= "        // Example:\n";
        $code .= "        // \$db->execute_query(\"INSERT INTO users (name, email) VALUES (?, ?)\", ['John Doe', 'john@example.com']);\n";
        $code .= "        echo \"Seeding {$seederName}...\\n\";\n";
        $code .= "    }\n}\n";

        $file = $seederPath . '/' . $seederName . '.php';
        file_put_contents($file, $code);

        echo "Success: Seeder class {$seederName} created successfully at src/{$appname}/seeders/{$seederName}.php\n";
    }
}
