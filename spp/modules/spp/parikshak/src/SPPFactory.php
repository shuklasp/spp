<?php
namespace SPPMod\Parikshak;

/**
 * Class SPPFactory
 * Base class for database factories to generate mock entities.
 */
abstract class SPPFactory
{
    protected $entityClass;
    
    /**
     * Define the default state/attributes for the generated entity.
     * @return array
     */
    abstract protected function definition(): array;

    /**
     * Create an instance and save it to the database.
     */
    public function create(array $attributes = [])
    {
        $data = array_merge($this->definition(), $attributes);
        $entityClass = $this->entityClass;
        $entity = new $entityClass();
        
        foreach ($data as $key => $value) {
            $entity->set($key, $value);
        }
        
        $entity->save();
        return $entity;
    }
    
    /**
     * Make an instance without saving it.
     */
    public function make(array $attributes = [])
    {
        $data = array_merge($this->definition(), $attributes);
        $entityClass = $this->entityClass;
        $entity = new $entityClass();
        
        foreach ($data as $key => $value) {
            $entity->set($key, $value);
        }
        
        return $entity;
    }
}
