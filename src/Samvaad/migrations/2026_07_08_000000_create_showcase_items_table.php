<?php


use SPPMod\SPPDB\Migration\SPPMigration;

class CreateShowcaseItemsTable extends SPPMigration
{
    public function up(): void
    {
        $table = \SPPMod\SPPDB\SPPDB::sppTable('showcase_items');
        
        $this->db->execute_query("CREATE TABLE IF NOT EXISTS {$table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(): void
    {
        $table = \SPPMod\SPPDB\SPPDB::sppTable('showcase_items');
        $this->db->execute_query("DROP TABLE IF EXISTS {$table}");
    }
}
