<?php

namespace SPPMod\SppDb\Seeding;

use SPPMod\SppDb\SPPEntity;

/**
 * Class SPPFactory
 * Base class for generating mock data for entities.
 */
abstract class SPPFactory
{
    /** @var string The entity class this factory is for. */
    protected $entity;

    /**
     * Define the default state of the model.
     * @return array
     */
    abstract public function definition(): array;

    /**
     * Create multiple instances and save them to the database.
     *
     * @param int $count
     * @param array $overrides
     * @return SPPEntity[]
     */
    public function create(int $count = 1, array $overrides = []): array
    {
        $instances = [];
        $class = $this->entity;

        if (!class_exists($class)) {
            throw new \Exception("Entity class {$class} not found in factory.");
        }

        for ($i = 0; $i < $count; $i++) {
            $data = array_merge($this->definition(), $overrides);
            /** @var SPPEntity $instance */
            $instance = new $class();
            $instance->setValues($data);
            $instance->save();
            $instances[] = $instance;
        }

        return $instances;
    }
}
