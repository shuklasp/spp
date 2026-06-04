<?php

namespace App\Lekhak\Services;

/**
 * Class TestDotNetAi
 * Polyglot Service Proxy to dotnet runtime.
 */
class TestDotNetAi extends \SPP\PolyglotProxy
{
    protected string $polyglotLang = 'dotnet';
    protected string $polyglotModule = 'C:/projects/apache/school1/src/lekhak/services/dotnet/service.testdotnetai';
}
