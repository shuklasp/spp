<?php

namespace SPPMod\SPPXDB;

use Exception;

/**
 * Class MigrationManager
 * Handles schema versioning and migrations for SPP_XDB.
 */
class MigrationManager
{
    protected $db;
    protected $migrationDir;
    protected $migrationTable = '_migrations';

    public function __construct(SPP_XDB $db)
    {
        $this->db = $db;
        $this->migrationDir = $db->getDataDir() . '/_migrations';
        if (!is_dir($this->migrationDir)) {
            mkdir($this->migrationDir, 0777, true);
        }

        // Ensure migration tracking table exists
        if (!$this->db->tableExists($this->migrationTable)) {
            $this->db->createTable($this->migrationTable, [
                'id' => 'int',
                'migration' => 'varchar',
                'batch' => 'int',
                'executed_at' => 'datetime'
            ]);
        }
    }

    public function migrate()
    {
        $executed = array_column($this->db->table($this->migrationTable)->get(), 'migration');
        $files = glob($this->migrationDir . '/*.php');
        sort($files);

        $batch = ($this->db->table($this->migrationTable)->max('batch') ?? 0) + 1;
        $count = 0;

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!in_array($name, $executed)) {
                echo "Migrating: $name...\n";
                $migration = require $file;
                $migration->up($this->db);

                $this->db->table($this->migrationTable)->insert([
                    'migration' => $name,
                    'batch' => $batch,
                    'executed_at' => date('Y-m-d H:i:s')
                ]);
                $count++;
            }
        }
        return $count;
    }

    public function rollback()
    {
        $maxBatch = $this->db->table($this->migrationTable)->max('batch');
        if (!$maxBatch) {
            return 0;
        }

        $toRollback = $this->db->table($this->migrationTable)
            ->where('batch', $maxBatch)
            ->orderBy('migration', 'DESC')
            ->get();

        $count = 0;
        foreach ($toRollback as $row) {
            $name = $row['migration'];
            $file = $this->migrationDir . '/' . $name . '.php';
            if (file_exists($file)) {
                echo "Rolling back: $name...\n";
                $migration = require $file;
                $migration->down($this->db);
                $this->db->table($this->migrationTable)->where('migration', $name)->delete();
                $count++;
            }
        }
        return $count;
    }

    public function create($name)
    {
        $filename = date('Y_m_d_His') . '_' . $name . '.php';
        $path = $this->migrationDir . '/' . $filename;
        $template = "<?php\n\nuse SPPMod\SPPXDB\SPP_XDB;\n\nreturn new class {\n    public function up(SPP_XDB \$db) {\n        // \$db->querySQL(\"CREATE TABLE ...\");\n    }\n\n    public function down(SPP_XDB \$db) {\n        // \$db->querySQL(\"DROP TABLE ...\");\n    }\n};\n";
        file_put_contents($path, $template);
        return $path;
    }
}
