<?php

namespace App\Lekhak\Services;

/**
 * Class TestAi
 * Polyglot Service Proxy to python runtime.
 */
class TestAi extends \SPP\PolyglotProxy
{
    protected string $polyglotLang = 'python';
    protected string $polyglotModule = 'C:/projects/apache/school1/src/lekhak/services/python/service.testai.py';
}
