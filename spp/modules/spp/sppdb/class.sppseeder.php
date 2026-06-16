<?php

namespace SPPMod\SppDb\Seeding;

/**
 * Class SPPSeeder
 * Base class for database seeders.
 */
abstract class SPPSeeder
{
    /**
     * Run the database seeds.
     */
    abstract public function run(): void;

    /**
     * Call another seeder class.
     */
    protected function call(string $class)
    {
        $seeder = new $class();
        $seeder->run();
    }
}
