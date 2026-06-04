<?php

namespace SPPMod\SPPConfig;

/**
 * class SPPConfig
 *
 * Backward compatibility wrapper for the central SPP\SPPConfig core class.
 */
class SPPConfig extends \SPP\SPPObject
{
    /**
     * Gets a configuration value.
     */
    public static function get($propname, $storage = 'yaml')
    {
        return \SPP\SPPConfig::get($propname);
    }

    /**
     * Sets a configuration value.
     */
    public static function set($propname, $propval, $storage = 'yaml')
    {
        \SPP\SPPConfig::set($propname, $propval);
    }

    /**
     * Compatibility methods
     */
    public static function enableCache()
    {
    }
    public static function disableCache()
    {
    }

    public static function varExists($propname)
    {
        return \SPP\SPPConfig::get($propname) !== null ? 1 : 0;
    }
}
