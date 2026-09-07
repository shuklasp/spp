<?php
namespace SPPMod\Parikshak\Faker;

/**
 * Class SPPFaker
 * Basic data generator for tests and factories.
 */
class SPPFaker
{
    public function name(): string
    {
        $first = ['John', 'Jane', 'Alex', 'Emily', 'Chris', 'Sarah'];
        $last = ['Smith', 'Doe', 'Johnson', 'Brown', 'Davis', 'Miller'];
        return $first[array_rand($first)] . ' ' . $last[array_rand($last)];
    }

    public function email(): string
    {
        $domains = ['example.com', 'test.org', 'fake.net'];
        return strtolower(str_replace(' ', '.', $this->name())) . '@' . $domains[array_rand($domains)];
    }

    public function text(int $words = 10): string
    {
        $vocab = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit'];
        $res = [];
        for ($i = 0; $i < $words; $i++) {
            $res[] = $vocab[array_rand($vocab)];
        }
        return ucfirst(implode(' ', $res)) . '.';
    }

    public function number(int $min = 0, int $max = 100): int
    {
        return mt_rand($min, $max);
    }
    
    public function boolean(): bool
    {
        return mt_rand(0, 1) === 1;
    }
}
