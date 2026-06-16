<?php

namespace SPPMod\SPPLive;

/**
 * SPPLive View Orchestrator
 */
class SPPLive extends \SPP\SPPObject
{
    private static ?LiveEngineInterface $engine = null;

    public static function setEngine(LiveEngineInterface $engine): void
    {
        self::$engine = $engine;
    }

    public static function getEngine(): LiveEngineInterface
    {
        if (self::$engine === null) {
            $wsEngine = new WebsocketLiveEngine();
            if ($wsEngine->isAvailable()) {
                self::$engine = $wsEngine;
                return self::$engine;
            }

            if (class_exists('Redis')) {
                $redisEngine = new RedisLiveEngine();
                // Check if connection succeeded
                $reflection = new \ReflectionClass($redisEngine);
                $property = $reflection->getProperty('redis');
                $property->setAccessible(true);
                if ($property->getValue($redisEngine) !== null) {
                    self::$engine = $redisEngine;
                    return self::$engine;
                }
            }

            self::$engine = new SqliteLiveEngine();
        }
        return self::$engine;
    }

    public static function bootLive()
    {
        return true;
    }
}
