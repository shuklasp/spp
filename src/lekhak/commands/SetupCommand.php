<?php
namespace App\Lekhak\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

/**
 * Class SetupCommand
 * Initializes the Lekhak CMS database tables and ensures the schema is up to date.
 */
class SetupCommand extends Command
{
    protected string $name = 'lekhak:setup';
    protected string $description = 'Initializes Lekhak CMS database tables.';

    public function execute(array $args): void
    {
        $db = new SPPDB();

        echo "Setting up Lekhak tables...\n";

        $db->execute_query("CREATE TABLE IF NOT EXISTS lek_nodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255),
            alias VARCHAR(255),
            body LONGTEXT,
            author_id BIGINT,
            status VARCHAR(20),
            langcode VARCHAR(10),
            translation_id BIGINT,
            created DATETIME,
            changed DATETIME
        )");

        echo "Table 'lek_nodes' ensured.\n";

        // Check and add alias column if it somehow missed the CREATE (legacy support)
        try {
            $db->add_columns('lek_nodes', ['alias' => 'VARCHAR(255)']);
            echo "Column 'alias' verified.\n";
        } catch (\Exception $e) {
            // Ignore if column already exists
        }

        echo "Lekhak setup completed successfully.\n";
    }
}
