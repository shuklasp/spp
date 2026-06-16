<?php

namespace SPPMod\Drishyam\Migrations;

use SPP\Core\Migration;

class Migration_1_1_0 extends Migration
{
    public function getVersion(): string
    {
        return '1.1.0';
    }

    public function up(): void
    {
        // $this->executeSql("CREATE TABLE ...");
    }

    public function down(): void
    {
        // $this->executeSql("DROP TABLE ...");
    }
}
