<?php

namespace App\Lekhak\Services;

/**
 * Class TestGoAi
 * Polyglot Service Proxy to go runtime.
 */
class TestGoAi extends \SPP\PolyglotProxy
{
    protected string $polyglotLang = 'go';
    protected string $polyglotModule = 'C:/projects/apache/school1/src/lekhak/services/go/service.testgoai.go';
}
