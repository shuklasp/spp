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

        $entityGuess = preg_replace('/Seeder$/', '', $seederName);
        $tableGuess = strtolower($entityGuess) . 's';

        $code = "<?php\n\nnamespace App\\Seeders;\n\nuse SPPMod\\SPPDB\\SPPSeeder;\n\n";
        $code .= "class {$seederName} extends SPPSeeder\n{\n";
        $code .= "    /**\n     * Run the database seeds.\n     */\n";
        $code .= "    public function run(): void\n    {\n";
        $code .= "        // 1. Clean slate truncate (optional)\n";
        $code .= "        // \$this->truncate('{$tableGuess}');\n\n";
        $code .= "        // 2. Synthesize schema-derived mock data\n";
        $code .= "        \$this->seed('{$entityGuess}', count: 10, overrides: [\n";
        $code .= "            // 'status' => 'active'\n";
        $code .= "        ]);\n\n";
        $code .= "        \$this->log(\"Seeded {$entityGuess} successfully.\");\n";
        $code .= "    }\n}\n";

        $file = $seederPath . '/' . $seederName . '.php';
        file_put_contents($file, $code);

        echo "Success: Seeder class {$seederName} created successfully at src/{$appname}/seeders/{$seederName}.php\n";
    }
}
