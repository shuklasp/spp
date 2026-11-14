<?php

use SPPMod\Parikshak\DSL\Registry;
use SPPMod\Parikshak\DSL\Expectation;

require_once __DIR__ . '/TestDefinition.php';

if (!function_exists('test')) {
    function test(string $description, callable $closure): \SPPMod\Parikshak\DSL\TestDefinition {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = 'unknown';
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && $trace['file'] !== __FILE__) {
                $file = $trace['file'];
                break;
            }
        }
        // Normalize file paths to avoid backslash/forward slash mismatches
        $file = str_replace('\\', '/', realpath($file) ?: $file);
        $index = Registry::addTest($file, $description, $closure);
        return new \SPPMod\Parikshak\DSL\TestDefinition($file, $index);
    }
}

if (!function_exists('it')) {
    function it(string $description, callable $closure): \SPPMod\Parikshak\DSL\TestDefinition {
        return test("it " . $description, $closure);
    }
}

if (!function_exists('expect')) {
    function expect($value) {
        return new Expectation($value);
    }
}

if (!function_exists('beforeEach')) {
    function beforeEach(callable $closure) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = 'unknown';
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && $trace['file'] !== __FILE__) {
                $file = $trace['file'];
                break;
            }
        }
        $file = str_replace('\\', '/', realpath($file) ?: $file);
        Registry::addBeforeEach($file, $closure);
    }
}

if (!function_exists('afterEach')) {
    function afterEach(callable $closure) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = 'unknown';
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && $trace['file'] !== __FILE__) {
                $file = $trace['file'];
                break;
            }
        }
        $file = str_replace('\\', '/', realpath($file) ?: $file);
        Registry::addAfterEach($file, $closure);
    }
}

if (!function_exists('testCrud')) {
    function testCrud(string $entityClass, array $attributes = []) {
        $shortName = (new \ReflectionClass($entityClass))->getShortName();
        test("crud lifecycle and database invariants for {$shortName}", function () use ($entityClass, $attributes) {
            $this->assertModelContracts($entityClass, $attributes);
        });
    }
}

if (!function_exists('fakeEvents')) {
    function fakeEvents(): \SPPMod\Parikshak\Fakes\EventFake {
        require_once dirname(__DIR__) . '/Fakes/EventFake.php';
        $fake = new \SPPMod\Parikshak\Fakes\EventFake();
        if (class_exists('\SPP\SPPEvent')) {
            \SPP\SPPEvent::setFake($fake);
        }
        return $fake;
    }
}

if (!function_exists('fakeMail')) {
    function fakeMail(): \SPPMod\Parikshak\Fakes\MailFake {
        require_once dirname(__DIR__) . '/Fakes/MailFake.php';
        $fake = new \SPPMod\Parikshak\Fakes\MailFake();
        if (class_exists('\SPPMod\SPPMail\MailGateway')) {
            \SPPMod\SPPMail\MailGateway::setFake($fake);
        }
        return $fake;
    }
}

if (!function_exists('createOne')) {
    function createOne(string $entityClass, array $overrides = []): object {
        $dummy = new class extends \SPPMod\Parikshak\SPPTestCase {};
        return $dummy->createOne($entityClass, $overrides);
    }
}

if (!function_exists('createMany')) {
    function createMany(string $entityClass, int $count, array $overrides = []): array {
        $dummy = new class extends \SPPMod\Parikshak\SPPTestCase {};
        return $dummy->createMany($entityClass, $count, $overrides);
    }
}

if (!function_exists('makeOne')) {
    function makeOne(string $entityClass, array $overrides = []): object {
        $dummy = new class extends \SPPMod\Parikshak\SPPTestCase {};
        return $dummy->makeOne($entityClass, $overrides);
    }
}

if (!function_exists('arch')) {
    function arch(string $description): \SPPMod\Parikshak\Arch\ArchExpectation {
        require_once dirname(__DIR__) . '/Arch/ArchExpectation.php';
        return new \SPPMod\Parikshak\Arch\ArchExpectation($description);
    }
}

if (!function_exists('fakeQueue')) {
    function fakeQueue(): \SPPMod\Parikshak\Fakes\QueueFake {
        require_once dirname(__DIR__) . '/Fakes/QueueFake.php';
        $fake = new \SPPMod\Parikshak\Fakes\QueueFake();
        if (class_exists('\SPPMod\SPPQueue\SppQueue')) {
            \SPPMod\SPPQueue\SppQueue::setFake($fake);
        }
        return $fake;
    }
}

if (!function_exists('freezeTime')) {
    function freezeTime(string|\DateTimeInterface|null $time = null): \DateTimeImmutable {
        require_once dirname(__DIR__) . '/TimeTravel.php';
        return \SPPMod\Parikshak\TimeTravel::freeze($time);
    }
}

if (!function_exists('travel')) {
    function travel(int $seconds): \DateTimeImmutable {
        require_once dirname(__DIR__) . '/TimeTravel.php';
        return \SPPMod\Parikshak\TimeTravel::travel($seconds);
    }
}

if (!function_exists('travelTo')) {
    function travelTo(string|\DateTimeInterface $target): \DateTimeImmutable {
        require_once dirname(__DIR__) . '/TimeTravel.php';
        return \SPPMod\Parikshak\TimeTravel::travelTo($target);
    }
}

if (!function_exists('travelBack')) {
    function travelBack(): void {
        if (class_exists('\SPPMod\Parikshak\TimeTravel')) {
            \SPPMod\Parikshak\TimeTravel::travelBack();
        }
    }
}

if (!function_exists('forAll')) {
    function forAll(...$generators): \SPPMod\Parikshak\Fuzz\PropertyRunner {
        require_once dirname(__DIR__) . '/Fuzz/Fuzz.php';
        require_once dirname(__DIR__) . '/Fuzz/PropertyRunner.php';
        return new \SPPMod\Parikshak\Fuzz\PropertyRunner($generators);
    }
}



