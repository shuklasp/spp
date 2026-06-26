<?php

namespace SPPMod\SPPDB;

/**
 * Base Migration Class
 */
abstract class SPPMigration
{
    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(): void;
}
