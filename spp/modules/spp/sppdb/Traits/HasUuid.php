<?php

namespace SPPMod\SppDb\Traits;

use SPP\Core\EventManager;
use SPPMod\SppDb\SPPEntity;

/**
 * Trait HasUuid
 * Automatically generates a UUID for the entity before creation.
 */
trait HasUuid
{
    /**
     * Boot the trait by registering the before_save listener.
     */
    public static function bootHasUuid()
    {
        \SPP\SPPEvent::listen('entity:before_save', function (\SPP\EventParams $params) {
            $entity = $params->get('entity');
            // Check if this entity uses the HasUuid trait
            $traits = class_uses($entity);
            if (!in_array(HasUuid::class, $traits)) {
                return;
            }

            // If the entity doesn't have an ID, we're creating it.
            // Check if uuid field exists and is empty.
            if (empty($entity->id)) {
                $uuidField = method_exists($entity, 'getUuidField') ? $entity->getUuidField() : 'uuid';

                if (isset($entity->{$uuidField}) && empty($entity->get($uuidField))) {
                    $entity->set($uuidField, self::generateUuidV4());
                } elseif (!isset($entity->{$uuidField})) {
                    // Try setting it dynamically if EAV allows
                    try {
                        $entity->set($uuidField, self::generateUuidV4());
                    } catch (\Exception $e) {
                        // ignore if strict properties
                    }
                }
            }
        });
    }

    /**
     * Generate a UUID v4.
     */
    protected static function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
