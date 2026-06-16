<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPPMod\Parikshak\Attributes\DataProvider;

class DataProviderTest extends SPPTestCase
{
    #[DataProvider('additionProvider')]
    public function testAddition(int $a, int $b, int $expected)
    {
        $this->assertEquals($expected, $a + $b);
    }

    public function additionProvider(): array
    {
        return [
            [1, 1, 2],
            [5, 5, 10],
            [10, -5, 5]
        ];
    }
}
