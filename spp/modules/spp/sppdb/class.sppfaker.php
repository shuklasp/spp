<?php

namespace SPPMod\SppDb\Seeding;

/**
 * Class SPPFaker
 * A powerful, native random data generator with zero dependencies.
 */
class SPPFaker
{
    private static $firstNames = ['John', 'Jane', 'Alex', 'Emily', 'Chris', 'Katie', 'Michael', 'Sarah', 'David', 'Laura', 'Robert', 'Jessica', 'William', 'Ashley'];
    private static $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez'];
    private static $domains = ['example.com', 'test.com', 'demo.org', 'mail.net', 'company.co'];
    private static $loremWords = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliqua'];

    public static function name(): string
    {
        $first = self::$firstNames[array_rand(self::$firstNames)];
        $last = self::$lastNames[array_rand(self::$lastNames)];
        return "{$first} {$last}";
    }

    public static function firstName(): string
    {
        return self::$firstNames[array_rand(self::$firstNames)];
    }

    public static function lastName(): string
    {
        return self::$lastNames[array_rand(self::$lastNames)];
    }

    public static function email(): string
    {
        $name = strtolower(self::firstName() . '.' . self::lastName() . rand(1, 999));
        $domain = self::$domains[array_rand(self::$domains)];
        return "{$name}@{$domain}";
    }

    public static function word(): string
    {
        return self::$loremWords[array_rand(self::$loremWords)];
    }

    public static function sentence(int $words = 6): string
    {
        $sentence = [];
        for ($i = 0; $i < $words; $i++) {
            $sentence[] = self::word();
        }
        return ucfirst(implode(' ', $sentence)) . '.';
    }

    public static function paragraph(int $sentences = 3): string
    {
        $para = [];
        for ($i = 0; $i < $sentences; $i++) {
            $para[] = self::sentence(rand(4, 10));
        }
        return implode(' ', $para);
    }

    public static function boolean(int $chanceOfGettingTrue = 50): bool
    {
        return rand(1, 100) <= $chanceOfGettingTrue;
    }

    public static function randomNumber(int $min = 0, int $max = 1000): int
    {
        return rand($min, $max);
    }

    public static function randomElement(array $array)
    {
        return $array[array_rand($array)];
    }

    public static function date(string $format = 'Y-m-d', string $max = 'now'): string
    {
        $maxTimestamp = $max === 'now' ? time() : strtotime($max);
        $minTimestamp = strtotime('-10 years', $maxTimestamp);
        $timestamp = rand($minTimestamp, $maxTimestamp);
        return date($format, $timestamp);
    }
}
