<?php

namespace SPP\Core;

/**
 * class Migration
 * 
 * Base class for all module schema and data migrations.
 */
abstract class Migration
{
    /**
     * Run the migration (Upgrade).
     */
    abstract public function up(): void;

    /**
     * Reverse the migration (Downgrade).
     */
    abstract public function down(): void;

    /**
     * Returns the version number this migration targets (e.g. "1.1.0").
     */
    abstract public function getVersion(): string;
    
    /**
     * Helper to execute SQL safely if sppdb is available.
     */
    protected function executeSql(string $sql): bool
    {
        if (class_exists('\\SPP\\DB')) {
            return \SPP\DB::execute($sql);
        }
        return false;
    }
}
