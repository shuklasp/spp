<?php

namespace App\Lekhak\Migrations;

use SPP\Core\Migration;

class Migration_20260531_144552_create_users_table extends Migration
{
    public function getVersion(): string
    {
        return '1.0.0';
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
