<?php
namespace SPPMod\SPPDB\Migration;

abstract class SPPMigration
{
    protected $db;

    public function __construct()
    {
        $this->db = new \SPPMod\SPPDB\SPPDB();
    }

    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(): void;
}
