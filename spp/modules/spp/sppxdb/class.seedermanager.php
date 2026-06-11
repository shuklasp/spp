<?php

namespace SPPMod\SPPXDB;

use Exception;

/**
 * Class SeederManager
 * Handles database seeding for SPP_XDB.
 */
class SeederManager
{
    protected $db;
    protected $seederDir;

    public function __construct(SPP_XDB $db)
    {
        $this->db = $db;
        $this->seederDir = $db->getDataDir() . '/_seeders';
        if (!is_dir($this->seederDir)) {
            mkdir($this->seederDir, 0777, true);
        }
    }

    public function seed($name = null)
    {
        $files = glob($this->seederDir . '/*.php');
        if (empty($files)) {
            echo "No seeders found.\n";
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            $seederName = pathinfo($file, PATHINFO_FILENAME);
            if ($name !== null && $name !== $seederName) {
                continue;
            }

            echo "Seeding: $seederName...\n";
            $seeder = require $file;
            if (method_exists($seeder, 'run')) {
                $seeder->run($this->db);
                $count++;
            }
        }
        return $count;
    }

    public function create($name)
    {
        $filename = $name . '.php';
        $path = $this->seederDir . '/' . $filename;
        $template = "<?php\n\nuse SPPMod\SPPXDB\SPP_XDB;\n\nreturn new class {\n    public function run(SPP_XDB \$db) {\n        // \$db->connect('users')->insert(['name' => 'John Doe']);\n    }\n};\n";
        file_put_contents($path, $template);
        return $path;
    }
}
