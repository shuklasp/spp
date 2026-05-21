<?php
namespace App\Lekhak\Commands;

use SPP\CLI\Command;

/**
 * MakeViewCommand
 * 
 * Scaffolds a view definition in the database.
 */
class MakeViewCommand extends Command
{
    public function getName(): string
    {
        return 'make:view';
    }

    public function getDescription(): string
    {
        return 'Create a new view definition (equivalent to Drupal Views).';
    }

    public function execute(array $args): void
    {
        $name = null;
        $baseTable = 'nodes';

        foreach ($args as $arg) {
            if ($arg === 'make:view' || $arg === 'spp.php') continue;
            if (str_starts_with($arg, '--table=')) {
                $baseTable = substr($arg, 8);
            } elseif (!str_starts_with($arg, '--')) {
                $name = $arg;
            }
        }

        if (!$name) {
            $this->error("Usage: php spp.php make:view <view_name> [--table=nodes]");
            return;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('view_definitions');

        // Ensure schema
        if (!$db->tableExists($table)) {
            $isSqlite = $db->getDriver() === 'sqlite';
            if ($isSqlite) {
                $db->execute_query("CREATE TABLE {$table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    label VARCHAR(255),
                    base_table VARCHAR(100),
                    fields TEXT,
                    filters TEXT,
                    sorts TEXT,
                    pagination INTEGER DEFAULT 10,
                    display_format VARCHAR(50) DEFAULT 'list'
                )");
            } else {
                $db->execute_query("CREATE TABLE {$table} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    label VARCHAR(255),
                    base_table VARCHAR(100),
                    fields TEXT,
                    filters TEXT,
                    sorts TEXT,
                    pagination INT DEFAULT 10,
                    display_format VARCHAR(50) DEFAULT 'list'
                )");
            }
        }

        $fields = json_encode(['id', 'title', 'created_at']);
        $filters = json_encode([['field' => 'status', 'op' => '=', 'value' => 'published']]);
        $sorts = json_encode([['field' => 'created_at', 'dir' => 'desc']]);

        try {
            $sql = "INSERT INTO {$table} (name, label, base_table, fields, filters, sorts, pagination, display_format) VALUES (?, ?, ?, ?, ?, ?, 10, 'list')";
            $db->execute_query($sql, [$name, ucfirst($name), $baseTable, $fields, $filters, $sorts]);
            $this->info("View '{$name}' created successfully on base table '{$baseTable}'.");
            $this->line("You can render it via: \\App\\Lekhak\\Services\\ViewRenderer::render('{$name}')");
        } catch (\Exception $e) {
            $this->error("Failed to create view: " . $e->getMessage());
        }
    }
}
