<?php
declare(strict_types=1);

namespace SPP\DB;

class Sequence
{
    private static $provider = null;

    public static function setProvider($provider): void
    {
        self::$provider = $provider;
    }

    public static function sequenceExists(string $seqName): bool
    {
        if (self::$provider !== null && method_exists(self::$provider, 'sequenceExists')) {
            return self::$provider::sequenceExists($seqName);
        }
        return false;
    }

    public static function createSequence(string $seqName, int $start = 1, int $increment = 1): void
    {
        if (self::$provider !== null && method_exists(self::$provider, 'createSequence')) {
            self::$provider::createSequence($seqName, $start, $increment);
        }
    }
}
