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

    public static function authorizeTopic(string $topic): bool
    {
        if ($topic === 'global') {
            return true;
        }

        // Admin-only topics
        if (str_starts_with($topic, 'admin_')) {
            if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
                return \SPPMod\SPPAuth\SPPAuth::isLoggedIn() && \SPPMod\SPPAuth\SPPAuth::hasRole(1); // Assuming 1 is admin
            }
            // Fallback for dev mode
            return \SPP\Config\YamlLoader::get('app', 'dev_mode', false);
        }

        // User-specific topics
        if (str_starts_with($topic, 'user_')) {
            $userId = substr($topic, 5);
            if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
                return \SPPMod\SPPAuth\SPPAuth::isLoggedIn() && (string)\SPPMod\SPPAuth\SPPAuth::getUserId() === $userId;
            }
        }

        return true; // Default allow for unknown custom topics
    }

    public static function bootLive()
    {
        return true;
    }
}
